<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- File name follows the existing class-ep-*.php convention (class-ep-bookings.php).
/**
 * Event capacity service (overall event capacity, #2012; server enforcement, #2346).
 *
 * This file adds one source of truth for "how many places are left for an event". Nothing calls it yet
 * in the release step that introduced it, so events behave exactly as before.
 *
 * Data model
 * ----------
 * - `em_event_overall_capacity` (post meta, schema v1): a plain positive integer is the size of the event pool.
 *   An empty value, `0`, a missing key, a negative number or any non-numeric text means "no pool".
 *   The meta is intentionally NOT on the `ep_skip_child_event_meta_keys` list: recurring child events and
 *   duplicated events get their own pool of the same size.
 * - `em_event_capacity_rules` (post meta, RESERVED, never written by 4.4.0.0): a versioned array such as
 *   `array( 'v' => 1, 'pools' => array( array( 'id' => 'cat-12', 'scope' => 'category', 'ref' => 12, 'limit' => 30 ) ) )`.
 *   It is read (read-only) and merged into the normalised pool list, so later releases can add per-category pools
 *   without a migration. Absent means exactly the 4.4.0.0 behaviour.
 * - Inventory is derived, never stored: the sum of `qty` per ticket id over bookings whose post status is
 *   in `ep_capacity_counted_statuses` (default completed and pending) and whose `em_status` is not `cancelled`.
 *
 * Public API (global wrappers at the bottom of this file)
 * ---------------------------------------------------------
 * - ep_get_event_overall_capacity( $event_or_id )                  int, 0 = no pool.
 * - ep_get_event_capacity_state( $event_or_id, $args = array() )   array, see EventPrime_Capacity::get_state().
 * - ep_check_capacity_for_cart( $event_or_id, $lines, $args )      true or WP_Error.
 * - ep_acquire_event_capacity_lock( $event_id ) / ep_release_event_capacity_lock( $event_id ).
 *
 * Filters: ep_event_overall_capacity( int $pool, int $event_id ); ep_event_capacity_config( array $config, int $event_id );
 * ep_capacity_counted_statuses( array $statuses, int $event_id ); ep_capacity_held_quantity( int $held, int $event_id, array $args );
 * ep_event_capacity_state( array $state, int $event_id, array $args );
 * ep_enforce_ticket_capacity( bool $enforce, int $event_id, string $context, array $lines );
 * ep_capacity_check_result( true|WP_Error $result, int $event_id, array $lines, array $state, string $context );
 * ep_capacity_inventory_type( string $type, int $event_id, array $args ): 'tickets' (default) or 'seats', for extensions that sell seats.
 * ep_capacity_held_quantity also receives `$args['lines']` (the cart lines) when called from check_cart(), and `$args['context']`.
 * Actions: ep_capacity_check_skipped( int $event_id, string $context, string $reason );
 * ep_capacity_rejected( int $event_id, array $lines, string $reason_code, string $context, WP_Error $error ) when the server guard rejects a cart (hold release); ep_event_capacity_exceeded( int $booking_id, int $event_id, array $state );
 * ep_event_capacity_released( int $event_id, int $freed_qty ).
 * Filter ep_fes_show_overall_capacity( bool $show = false ): reserved switch for the front-end submission form (Q7), nothing reads it yet.
 * ep_after_event_overall_capacity_saved( int $event_id, int $old_pool, int $new_pool ) after the editor changed the pool (0 = none);
 * ep_event_capacity_released also fires when an existing pool is raised (freed = difference) or cleared (freed = 0, amount unknown).
 *
 * WP_Error codes returned by check_cart(): ep_capacity_sold_out, ep_capacity_pool_exceeded, ep_capacity_ticket_exceeded,
 * ep_capacity_invalid_ticket, ep_capacity_invalid_qty. get_error_data() is always
 * `array( 'event_remaining' => int, 'ticket_id' => int|null, 'ticket_remaining' => int|null, 'requested' => int, 'status' => 409 )`.
 *
 * @package EventPrime
 * @since   4.4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'EventPrime_Capacity' ) ) {

	/**
	 * Capacity service.
	 */
	class EventPrime_Capacity {

		/**
		 * Per-request cache of sold counts, keyed by event id, statuses and excluded booking.
		 *
		 * @var array
		 */
		private static $sold_cache = array();

		/**
		 * Locks held by this request, keyed by lock name.
		 *
		 * @var array
		 */
		private static $held_locks = array();

		/**
		 * Whether the shutdown release callback is registered.
		 *
		 * @var bool
		 */
		private static $shutdown_registered = false;

		/**
		 * Whether a lock failure was already logged in this request.
		 *
		 * @var bool
		 */
		private static $lock_failure_logged = false;

		/**
		 * Resolve an event id from an id, an event object or a post.
		 *
		 * @param mixed $event_or_id Event id, event object or WP_Post.
		 * @return int Event id, 0 when none.
		 */
		private function resolve_event_id( $event_or_id ) {
			if ( is_object( $event_or_id ) ) {
				if ( ! empty( $event_or_id->id ) ) {
					return absint( $event_or_id->id );
				}
				if ( ! empty( $event_or_id->em_id ) ) {
					return absint( $event_or_id->em_id );
				}
				if ( ! empty( $event_or_id->ID ) ) {
					return absint( $event_or_id->ID );
				}
				return 0;
			}
			return absint( $event_or_id );
		}

		/**
		 * Parse a stored capacity value: only a plain positive integer is a pool.
		 *
		 * `absint()` is not used because it turns -5 into 5 and '12abc' into 12.
		 *
		 * @param mixed $value Raw value.
		 * @return int Positive integer, or 0.
		 */
		private function parse_positive_int( $value ) {
			if ( is_int( $value ) ) {
				return $value > 0 ? $value : 0;
			}
			if ( is_string( $value ) && ctype_digit( $value ) ) {
				return (int) $value > 0 ? (int) $value : 0;
			}
			return 0;
		}

		/**
		 * Get the overall (pool) capacity of an event.
		 *
		 * @param mixed $event_or_id Event object (reads `em_event_overall_capacity`) or event id.
		 * @return int Pool size, 0 = no pool.
		 */
		public function get_overall_capacity( $event_or_id ) {
			$event_id = $this->resolve_event_id( $event_or_id );
			if ( is_object( $event_or_id ) && isset( $event_or_id->em_event_overall_capacity ) ) {
				$raw = $event_or_id->em_event_overall_capacity;
			} elseif ( $event_id > 0 ) {
				$raw = get_post_meta( $event_id, 'em_event_overall_capacity', true );
			} else {
				$raw = '';
			}
			$pool = $this->parse_positive_int( $raw );
			$pool = apply_filters( 'ep_event_overall_capacity', $pool, $event_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			return $this->parse_positive_int( $pool );
		}

		/**
		 * Get the normalised list of pools for an event.
		 *
		 * The v1 list has one pool, `event`. The reserved `em_event_capacity_rules` meta can add more.
		 *
		 * @param int $event_id Event id.
		 * @return array Pools keyed by pool id: id, scope, ref, limit.
		 */
		public function get_config( $event_id ) {
			$event_id = absint( $event_id );
			$config   = array();
			$limit    = $this->get_overall_capacity( $event_id );
			if ( $limit > 0 ) {
				$config['event'] = array(
					'id'    => 'event',
					'scope' => 'event',
					'ref'   => null,
					'limit' => $limit,
				);
			}
			if ( $event_id > 0 ) {
				$rules = get_post_meta( $event_id, 'em_event_capacity_rules', true );
				if ( is_string( $rules ) && '' !== $rules ) {
					$functions = new Eventprime_Basic_Functions();
					$rules     = $functions->ep_safe_maybe_unserialize( $rules );
				}
				if ( is_array( $rules ) && isset( $rules['v'] ) && 1 === (int) $rules['v'] && ! empty( $rules['pools'] ) && is_array( $rules['pools'] ) ) {
					foreach ( $rules['pools'] as $pool ) {
						if ( ! is_array( $pool ) || empty( $pool['id'] ) || ! is_string( $pool['id'] ) || empty( $pool['scope'] ) ) {
							continue;
						}
						$scope = (string) $pool['scope'];
						if ( ! in_array( $scope, array( 'category', 'ticket' ), true ) ) {
							continue;
						}
						$pool_limit = isset( $pool['limit'] ) ? $this->parse_positive_int( $pool['limit'] ) : 0;
						$ref        = isset( $pool['ref'] ) ? absint( $pool['ref'] ) : 0;
						if ( $pool_limit < 1 || $ref < 1 || 'event' === $pool['id'] ) {
							continue;
						}
						$config[ $pool['id'] ] = array(
							'id'    => $pool['id'],
							'scope' => $scope,
							'ref'   => $ref,
							'limit' => $pool_limit,
						);
					}
				}
			}
			$config = apply_filters( 'ep_event_capacity_config', $config, $event_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			return is_array( $config ) ? $config : array();
		}

		/**
		 * Flush the per-request sold cache.
		 *
		 * @return void
		 */
		public static function flush_cache() {
			self::$sold_cache = array();
		}

		/**
		 * Flush the cache when an em_booking post changes status or is created.
		 *
		 * @param string  $new_status New status.
		 * @param string  $old_status Old status.
		 * @param WP_Post $post       Post.
		 * @return void
		 */
		public static function flush_cache_on_transition( $new_status, $old_status, $post ) {
			if ( is_object( $post ) && isset( $post->post_type ) && 'em_booking' === $post->post_type ) {
				self::$sold_cache = array();
			}
		}

		/**
		 * Normalise one booked ticket row (object or array) to array( id, qty ), or null.
		 *
		 * @param mixed $row Row.
		 * @param bool  $legacy True for the old `order_item_data` shape (variation_id / quantity).
		 * @return array|null
		 */
		private function normalise_booked_row( $row, $legacy ) {
			if ( is_array( $row ) ) {
				$row = (object) $row;
			}
			if ( ! is_object( $row ) ) {
				return null;
			}
			$id  = ! empty( $row->id ) ? absint( $row->id ) : 0;
			$qty = ! empty( $row->qty ) ? absint( $row->qty ) : 0;
			if ( $id > 0 && $qty > 0 ) {
				return array( $id, $qty );
			}
			if ( $legacy && ! empty( $row->variation_id ) ) {
				$variation = absint( $row->variation_id );
				$quantity  = ! empty( $row->quantity ) ? absint( $row->quantity ) : 0;
				if ( $variation > 0 && $quantity > 0 ) {
					return array( $variation, $quantity );
				}
			}
			return null;
		}

		/**
		 * Count sold places for an event. This is the only counting implementation.
		 *
		 * Never reads `$event->all_bookings`. Counted statuses come from `ep_capacity_counted_statuses`
		 * (default completed and pending); bookings whose `em_status` is `cancelled` are skipped.
		 *
		 * @param int   $event_id Event id.
		 * @param array $args     Optional: exclude_booking_id (int), fresh (bool, bypass the per-request cache).
		 * @return array { by_ticket: id => qty, pending_by_ticket: id => qty, total: int, by_status: status => qty, booking_count: int, statuses: array }
		 */
		public function count_sold( $event_id, $args = array() ) {
			$event_id = absint( $event_id );
			$statuses = apply_filters( 'ep_capacity_counted_statuses', array( 'completed', 'pending' ), $event_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			$statuses = is_array( $statuses ) ? array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) ) : array( 'completed', 'pending' );
			$exclude  = ! empty( $args['exclude_booking_id'] ) ? absint( $args['exclude_booking_id'] ) : 0;
			$result   = array(
				'by_ticket'         => array(),
				'pending_by_ticket' => array(),
				'total'             => 0,
				'by_status'         => array_fill_keys( $statuses, 0 ),
				'booking_count'     => 0,
				'statuses'          => $statuses,
			);
			if ( $event_id < 1 || empty( $statuses ) ) {
				return $result;
			}
			$cache_key = $event_id . '|' . implode( ',', $statuses ) . '|' . $exclude;
			if ( empty( $args['fresh'] ) && isset( self::$sold_cache[ $cache_key ] ) ) {
				return self::$sold_cache[ $cache_key ];
			}
			// Full post objects (not ids): WP_Query then primes the post and meta caches in two queries, so reading
			// each booking's meta below costs no query per booking. Terms are not needed.
			$bookings  = get_posts(
				array(
					'numberposts'            => -1,
					'post_type'              => 'em_booking',
					'post_status'            => $statuses,
					'update_post_term_cache' => false,
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bookings are tied to an event only through post meta.
						array(
							'key'     => 'em_event',
							'value'   => $event_id,
							'compare' => '=',
						),
					),
				)
			);
			$functions = new Eventprime_Basic_Functions();
			foreach ( $bookings as $booking ) {
				$booking_id = absint( $booking->ID );
				if ( $booking_id === $exclude ) {
					continue;
				}
				if ( 'cancelled' === get_post_meta( $booking_id, 'em_status', true ) ) {
					continue;
				}
				$post_status = $booking->post_status;
				$order_info  = get_post_meta( $booking_id, 'em_order_info', true );
				if ( is_string( $order_info ) ) {
					$order_info = $functions->ep_safe_maybe_unserialize( $order_info );
				}
				++$result['booking_count'];
				if ( ! is_array( $order_info ) ) {
					continue;
				}
				$rows   = array();
				$legacy = false;
				if ( ! empty( $order_info['tickets'] ) && is_array( $order_info['tickets'] ) ) {
					$rows = $order_info['tickets'];
				} elseif ( ! empty( $order_info['order_item_data'] ) && is_array( $order_info['order_item_data'] ) ) {
					$rows   = $order_info['order_item_data'];
					$legacy = true;
				}
				foreach ( $rows as $row ) {
					$pair = $this->normalise_booked_row( $row, $legacy );
					if ( null === $pair ) {
						continue;
					}
					list( $ticket_id, $qty ) = $pair;
					if ( ! isset( $result['by_ticket'][ $ticket_id ] ) ) {
						$result['by_ticket'][ $ticket_id ] = 0;
					}
					$result['by_ticket'][ $ticket_id ] += $qty;
					$result['total']                   += $qty;
					if ( isset( $result['by_status'][ $post_status ] ) ) {
						$result['by_status'][ $post_status ] += $qty;
					}
					if ( 'pending' === $post_status ) {
						if ( ! isset( $result['pending_by_ticket'][ $ticket_id ] ) ) {
							$result['pending_by_ticket'][ $ticket_id ] = 0;
						}
						$result['pending_by_ticket'][ $ticket_id ] += $qty;
					}
				}
			}
			self::$sold_cache[ $cache_key ] = $result;
			return $result;
		}

		/**
		 * Whether server-side enforcement applies for an event.
		 *
		 * A pool is always enforced. Without a pool the site setting `enforce_ticket_capacity` (absent = on)
		 * and the filter `ep_enforce_ticket_capacity` decide (the #2346 opt-out; no settings UI).
		 *
		 * @param int    $event_id Event id.
		 * @param string $context  checkout, rest, extension, display.
		 * @param array  $lines    Cart lines, passed to the filter.
		 * @return bool
		 */
		public function is_enforced( $event_id, $context = 'checkout', $lines = array() ) {
			$event_id = absint( $event_id );
			if ( $this->get_overall_capacity( $event_id ) > 0 ) {
				return true;
			}
			$functions = new Eventprime_Basic_Functions();
			$setting   = $functions->ep_get_global_settings( 'enforce_ticket_capacity' );
			$enforce   = ( false === $setting ) ? true : ! empty( $setting );
			return (bool) apply_filters( 'ep_enforce_ticket_capacity', $enforce, $event_id, (string) $context, is_array( $lines ) ? $lines : array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
		}

		/**
		 * Whether a ticket can be bought now (visible and inside its sale window).
		 *
		 * @param object $ticket    Ticket row.
		 * @param object $event     Event object.
		 * @param object $functions Eventprime_Basic_Functions instance.
		 * @return bool
		 */
		private function ticket_on_sale( $ticket, $event, $functions ) {
			$visibility = $functions->check_for_ticket_visibility( $ticket, $event );
			if ( empty( $visibility['status'] ) ) {
				return false;
			}
			$window = $functions->check_for_ticket_available_for_booking( $ticket, $event );
			return ! ( is_array( $window ) && ! empty( $window['expire'] ) );
		}

		/**
		 * Build the capacity state of an event.
		 *
		 * Documented contract (new keys may be added, existing keys never change meaning):
		 *
		 * array(
		 *   'version'          => 1,
		 *   'event_id'         => 123,
		 *   'has_pool'         => true,   // a pool exists
		 *   'enforced'         => true,   // the server enforces type and cart limits now
		 *   'total'            => 50,     // int|null pool size, null when no pool
		 *   'capacity_sum'     => 50,     // sum of all ticket type capacities
		 *   'effective_total'  => 50,     // has_pool ? min( capacity_sum, total ) : capacity_sum
		 *   'sold'             => 36,     // counted places, all ticket ids including deleted types
		 *   'sold_by_status'   => array( 'completed' => 30, 'pending' => 6 ),
		 *   'pending'          => 6,
		 *   'inventory'        => 'tickets', // what is sold: 'tickets' (default) or 'seats' (declared by an extension through ep_capacity_inventory_type)
		 *   'held'             => 0,      // places held for in-progress checkouts (0 unless a provider is active)
		 *   'remaining'        => 14,     // >= 0, ignores sale windows
		 *   'available_now'    => 14,     // >= 0, only types that are visible and inside their sale window
		 *   'sold_out'         => false,  // remaining < 1
		 *   'oversold'         => array( 'pool' => 0, 'tickets' => array( 9 => 3 ) ), // ints > 0 only
		 *   'pools'            => array( 'event' => array( 'id', 'scope', 'ref', 'limit', 'sold', 'held', 'remaining' ) ), // empty when no pool
		 *   'tickets'          => array( 9 => array( 'id', 'name', 'category_id', 'capacity', 'sold', 'pending', 'held',
		 *                                            'remaining', 'effective_remaining', 'sold_out', 'on_sale',
		 *                                            'min_per_order', 'max_per_order' ) ),
		 *   'counted_statuses' => array( 'completed', 'pending' ),
		 *   'computed_at'      => 1763337600,
		 * )
		 *
		 * Notes: a capacity of NULL or 0 is read as 0 places (as the front end does today). `on_sale` is only
		 * evaluated for the contexts display, rest and extension, or when `$args['evaluate_on_sale']` is true;
		 * in the checkout context it is reported as true. `held` is event level in v1 (subtracted from pools only).
		 * When `$event_or_id` is an object carrying `all_tickets_data`, that (possibly filtered) list is used.
		 *
		 * @param mixed $event_or_id Event object or id.
		 * @param array $args        context (display|rest|checkout|extension), fresh (bool), exclude_booking_id (int), evaluate_on_sale (bool).
		 * @return array State array.
		 */
		public function get_state( $event_or_id, $args = array() ) {
			$args      = is_array( $args ) ? $args : array();
			$context   = isset( $args['context'] ) ? sanitize_key( $args['context'] ) : 'display';
			$event_id  = $this->resolve_event_id( $event_or_id );
			$sold      = $this->count_sold(
				$event_id,
				array(
					'fresh'              => ! empty( $args['fresh'] ),
					'exclude_booking_id' => isset( $args['exclude_booking_id'] ) ? $args['exclude_booking_id'] : 0,
				)
			);
			$functions = new Eventprime_Basic_Functions();
			$event     = null;
			$tickets   = array();
			if ( $event_id > 0 ) {
				if ( is_object( $event_or_id ) && isset( $event_or_id->all_tickets_data ) && is_array( $event_or_id->all_tickets_data ) ) {
					$event   = $event_or_id;
					$tickets = $event_or_id->all_tickets_data;
				} else {
					$event = $functions->get_single_event( $event_id );
					if ( is_object( $event ) && ! empty( $event->all_tickets_data ) && is_array( $event->all_tickets_data ) ) {
						$tickets = $event->all_tickets_data;
					}
				}
			}
			$evaluate_on_sale = ! empty( $args['evaluate_on_sale'] ) || in_array( $context, array( 'display', 'rest', 'extension' ), true );
			$config           = $this->get_config( $event_id );
			$held             = (int) apply_filters( 'ep_capacity_held_quantity', 0, $event_id, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			$held             = max( 0, $held );

			$ticket_states = array();
			$capacity_sum  = 0;
			$oversold      = array(
				'pool'    => 0,
				'tickets' => array(),
			);
			foreach ( $tickets as $ticket ) {
				if ( ! is_object( $ticket ) || empty( $ticket->id ) ) {
					continue;
				}
				$ticket_id      = absint( $ticket->id );
				$capacity       = isset( $ticket->capacity ) ? max( 0, (int) $ticket->capacity ) : 0;
				$ticket_sold    = isset( $sold['by_ticket'][ $ticket_id ] ) ? (int) $sold['by_ticket'][ $ticket_id ] : 0;
				$ticket_pending = isset( $sold['pending_by_ticket'][ $ticket_id ] ) ? (int) $sold['pending_by_ticket'][ $ticket_id ] : 0;
				$remaining      = max( 0, $capacity - $ticket_sold );
				$capacity_sum  += $capacity;
				if ( $ticket_sold > $capacity ) {
					$oversold['tickets'][ $ticket_id ] = $ticket_sold - $capacity;
				}
				$ticket_states[ $ticket_id ] = array(
					'id'                  => $ticket_id,
					'name'                => isset( $ticket->name ) ? (string) $ticket->name : '',
					'category_id'         => isset( $ticket->category_id ) ? absint( $ticket->category_id ) : 0,
					'capacity'            => $capacity,
					'sold'                => $ticket_sold,
					'pending'             => $ticket_pending,
					'held'                => 0,
					'remaining'           => $remaining,
					'effective_remaining' => $remaining,
					'sold_out'            => $remaining < 1,
					'on_sale'             => ( $evaluate_on_sale && is_object( $event ) ) ? $this->ticket_on_sale( $ticket, $event, $functions ) : true,
					'min_per_order'       => isset( $ticket->min_ticket_no ) ? absint( $ticket->min_ticket_no ) : 0,
					'max_per_order'       => isset( $ticket->max_ticket_no ) ? absint( $ticket->max_ticket_no ) : 0,
				);
			}

			// Pools: sold inside each pool, remaining, and the effective remaining of each ticket.
			$pools = array();
			foreach ( $config as $pool_id => $pool ) {
				$pool_sold = 0;
				if ( 'event' === $pool['scope'] ) {
					$pool_sold = $sold['total'];
				} else {
					foreach ( $ticket_states as $ticket_id => $ticket_state ) {
						if ( ( 'category' === $pool['scope'] && (int) $ticket_state['category_id'] === (int) $pool['ref'] ) || ( 'ticket' === $pool['scope'] && (int) $ticket_id === (int) $pool['ref'] ) ) {
							$pool_sold += $ticket_state['sold'];
						}
					}
				}
				$pool_held      = 'event' === $pool['scope'] ? $held : 0;
				$pool_remaining = max( 0, (int) $pool['limit'] - $pool_sold - $pool_held );
				if ( 'event' === $pool['scope'] && $pool_sold > (int) $pool['limit'] ) {
					$oversold['pool'] = $pool_sold - (int) $pool['limit'];
				}
				$pools[ $pool_id ] = array(
					'id'        => $pool['id'],
					'scope'     => $pool['scope'],
					'ref'       => $pool['ref'],
					'limit'     => (int) $pool['limit'],
					'sold'      => $pool_sold,
					'held'      => $pool_held,
					'remaining' => $pool_remaining,
				);
				foreach ( $ticket_states as $ticket_id => $ticket_state ) {
					$member = 'event' === $pool['scope']
						|| ( 'category' === $pool['scope'] && (int) $ticket_state['category_id'] === (int) $pool['ref'] )
						|| ( 'ticket' === $pool['scope'] && (int) $ticket_id === (int) $pool['ref'] );
					if ( $member ) {
						$ticket_states[ $ticket_id ]['effective_remaining'] = min( $ticket_states[ $ticket_id ]['effective_remaining'], $pool_remaining );
					}
				}
			}

			$sum_effective = 0;
			$sum_available = 0;
			foreach ( $ticket_states as $ticket_state ) {
				$sum_effective += $ticket_state['effective_remaining'];
				if ( $ticket_state['on_sale'] ) {
					$sum_available += $ticket_state['effective_remaining'];
				}
			}
			$remaining_total = $sum_effective;
			$available_now   = $sum_available;
			if ( isset( $pools['event'] ) ) {
				$remaining_total = min( $remaining_total, $pools['event']['remaining'] );
				$available_now   = min( $available_now, $pools['event']['remaining'] );
			}
			$remaining_total = max( 0, $remaining_total );
			$available_now   = max( 0, $available_now );
			$total           = isset( $pools['event'] ) ? $pools['event']['limit'] : null;

			$state = array(
				'version'          => 1,
				'event_id'         => $event_id,
				'has_pool'         => isset( $pools['event'] ),
				'enforced'         => $event_id > 0 ? $this->is_enforced( $event_id, $context ) : false,
				'total'            => $total,
				'capacity_sum'     => $capacity_sum,
				'effective_total'  => null === $total ? $capacity_sum : min( $capacity_sum, $total ),
				'sold'             => (int) $sold['total'],
				'sold_by_status'   => $sold['by_status'],
				'pending'          => isset( $sold['by_status']['pending'] ) ? (int) $sold['by_status']['pending'] : 0,
				'inventory'        => $this->inventory_type( $event_id, $args ),
				'held'             => $held,
				'remaining'        => $remaining_total,
				'available_now'    => $available_now,
				'sold_out'         => $remaining_total < 1,
				'oversold'         => $oversold,
				'pools'            => $pools,
				'tickets'          => $ticket_states,
				'counted_statuses' => $sold['statuses'],
				'computed_at'      => time(),
			);
			$state = apply_filters( 'ep_event_capacity_state', $state, $event_id, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			return $state;
		}

		/**
		 * What an event sells: 'tickets' (default) or 'seats'.
		 *
		 * Core always sells tickets; an extension such as Live Seating declares 'seats' with the filter
		 * `ep_capacity_inventory_type( string $type, int $event_id, array $args )`. Any other value reads as 'tickets'.
		 *
		 * @param int   $event_id Event id.
		 * @param array $args     get_state() arguments.
		 * @return string 'tickets' or 'seats'.
		 */
		private function inventory_type( $event_id, $args ) {
			$type = apply_filters( 'ep_capacity_inventory_type', 'tickets', $event_id, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			return 'seats' === $type ? 'seats' : 'tickets';
		}

		/**
		 * Whether the front-end submission (FES) form may show the overall capacity field.
		 *
		 * Hidden by default (decision Q7: organisers do not set attendance limits). Filter
		 * `ep_fes_show_overall_capacity` (bool, default false) is the switch. In 4.4.0.0 no FES template reads it
		 * yet, so turning it on shows nothing; FES saves never write or clear the pool.
		 *
		 * @return bool
		 */
		public function fes_shows_overall_capacity() {
			return (bool) apply_filters( 'ep_fes_show_overall_capacity', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
		}

		/**
		 * State for front-end display, only when the event has an overall capacity (pool).
		 *
		 * Events without a pool return an empty array and the callers keep their original, unchanged display code
		 * (decision Q11: no-pool events keep today's displayed numbers). Pool events use the state of get_state().
		 *
		 * @param mixed $event_or_id Event object or id. An object carrying `all_tickets_data` limits the state to that list.
		 * @param array $args        Same arguments as get_state().
		 * @return array State array, or an empty array when there is no pool.
		 */
		public function get_pool_state( $event_or_id, $args = array() ) {
			if ( $this->get_overall_capacity( $event_or_id ) < 1 ) {
				return array();
			}
			return $this->get_state( $event_or_id, $args );
		}

		/**
		 * Build the WP_Error of a failed check.
		 *
		 * @param string   $code             Error code.
		 * @param string   $message          Message.
		 * @param array    $state            State array.
		 * @param int|null $ticket_id        Ticket id.
		 * @param int      $requested        Requested quantity.
		 * @return WP_Error
		 */
		private function capacity_error( $code, $message, $state, $ticket_id, $requested ) {
			$ticket_remaining = null;
			if ( null !== $ticket_id && isset( $state['tickets'][ $ticket_id ] ) ) {
				$ticket_remaining = (int) $state['tickets'][ $ticket_id ]['effective_remaining'];
			}
			return new WP_Error(
				$code,
				$message,
				array(
					'event_remaining'  => isset( $state['remaining'] ) ? (int) $state['remaining'] : 0,
					'ticket_id'        => $ticket_id,
					'ticket_remaining' => $ticket_remaining,
					'requested'        => (int) $requested,
					'status'           => 409,
				)
			);
		}

		/**
		 * Validate a cart against the capacity of an event.
		 *
		 * Order of checks: lines are normalised (quantity must be a whole number of 1 or more, duplicate ids are merged);
		 * every ticket must belong to the event; sold out; the per-type ceiling; every pool.
		 *
		 * @param mixed $event_or_id Event object or id.
		 * @param array $lines       Lines: objects with id and qty, or arrays with ticket_id (or id) and qty.
		 * @param array $args        context (checkout|rest|admin-override|extension), fresh (bool, default true), exclude_booking_id (int), enforce (bool|null), check_window (bool), raise_to_min (bool).
		 * @return true|WP_Error
		 */
		public function check_cart( $event_or_id, $lines, $args = array() ) {
			$args     = is_array( $args ) ? $args : array();
			$context  = isset( $args['context'] ) ? sanitize_key( $args['context'] ) : 'checkout';
			$event_id = $this->resolve_event_id( $event_or_id );
			$lines    = is_array( $lines ) ? $lines : array();

			if ( 'admin-override' === $context ) {
				do_action( 'ep_capacity_check_skipped', $event_id, $context, 'admin-override' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
				return apply_filters( 'ep_capacity_check_result', true, $event_id, $lines, array(), $context ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			}

			$enforce = ( isset( $args['enforce'] ) && null !== $args['enforce'] ) ? (bool) $args['enforce'] : $this->is_enforced( $event_id, $context, $lines );
			if ( ! $enforce ) {
				return apply_filters( 'ep_capacity_check_result', true, $event_id, $lines, array(), $context ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			}

			$state_args = array(
				'context'            => $context,
				'fresh'              => isset( $args['fresh'] ) ? (bool) $args['fresh'] : true,
				'exclude_booking_id' => isset( $args['exclude_booking_id'] ) ? $args['exclude_booking_id'] : 0,
				'evaluate_on_sale'   => ! empty( $args['check_window'] ),
				'lines'              => $lines,
			);
			$state      = $this->get_state( $event_or_id, $state_args );

			// Normalise and merge the lines.
			$merged = array();
			foreach ( $lines as $line ) {
				$ticket_id = 0;
				$qty       = null;
				if ( is_object( $line ) ) {
					$ticket_id = isset( $line->id ) ? $line->id : ( isset( $line->ticket_id ) ? $line->ticket_id : 0 );
					$qty       = isset( $line->qty ) ? $line->qty : null;
				} elseif ( is_array( $line ) ) {
					$ticket_id = isset( $line['ticket_id'] ) ? $line['ticket_id'] : ( isset( $line['id'] ) ? $line['id'] : 0 );
					$qty       = isset( $line['qty'] ) ? $line['qty'] : null;
				}
				$ticket_id = $this->parse_positive_int( is_float( $ticket_id ) && floor( $ticket_id ) === $ticket_id ? (int) $ticket_id : $ticket_id );
				if ( is_float( $qty ) && floor( $qty ) === $qty ) {
					$qty = (int) $qty;
				}
				$qty = $this->parse_positive_int( $qty );
				if ( $qty < 1 ) {
					return $this->capacity_error( 'ep_capacity_invalid_qty', __( 'Please choose a valid ticket quantity.', 'eventprime-event-calendar-management' ), $state, $ticket_id > 0 ? $ticket_id : null, 0 );
				}
				if ( $ticket_id < 1 || ! isset( $state['tickets'][ $ticket_id ] ) ) {
					return $this->capacity_error( 'ep_capacity_invalid_ticket', __( 'One of the selected tickets is not available for this event.', 'eventprime-event-calendar-management' ), $state, $ticket_id > 0 ? $ticket_id : null, $qty );
				}
				if ( ! empty( $args['check_window'] ) && empty( $state['tickets'][ $ticket_id ]['on_sale'] ) ) {
					return $this->capacity_error( 'ep_capacity_invalid_ticket', __( 'One of the selected tickets is not available for this event.', 'eventprime-event-calendar-management' ), $state, $ticket_id, $qty );
				}
				if ( ! empty( $args['raise_to_min'] ) && $qty < (int) $state['tickets'][ $ticket_id ]['min_per_order'] ) {
					// The booking path raises a line to the ticket's minimum per order; check the quantity that will really be booked.
					$qty = (int) $state['tickets'][ $ticket_id ]['min_per_order'];
				}
				$merged[ $ticket_id ] = ( isset( $merged[ $ticket_id ] ) ? $merged[ $ticket_id ] : 0 ) + $qty;
			}

			$requested_total = array_sum( $merged );
			$result          = true;

			if ( ! empty( $merged ) && $state['remaining'] < 1 ) {
				$result = $this->capacity_error( 'ep_capacity_sold_out', __( 'No places are left for this event.', 'eventprime-event-calendar-management' ), $state, null, $requested_total );
			}

			// Per-type ceiling (and any pool the ticket belongs to).
			if ( true === $result ) {
				foreach ( $merged as $ticket_id => $qty ) {
					if ( $qty > $state['tickets'][ $ticket_id ]['remaining'] ) {
						$ticket_left = (int) $state['tickets'][ $ticket_id ]['remaining'];
						$ticket_name = wp_strip_all_tags( $state['tickets'][ $ticket_id ]['name'] );
						if ( $ticket_left < 1 ) {
							/* translators: %s: ticket name. */
							$message = sprintf( __( '%s is sold out. Please remove it from your order and try again.', 'eventprime-event-calendar-management' ), $ticket_name );
						} else {
							$message = sprintf(
								/* translators: 1: number of tickets left, 2: ticket name. */
								_n( 'Only %1$d ticket of %2$s is left. Please reduce the quantity and try again.', 'Only %1$d tickets of %2$s are left. Please reduce the quantity and try again.', $ticket_left, 'eventprime-event-calendar-management' ),
								$ticket_left,
								$ticket_name
							);
						}
						$result = $this->capacity_error( 'ep_capacity_ticket_exceeded', $message, $state, $ticket_id, $qty );
						break;
					}
				}
			}

			// Every pool: the quantity of the member tickets together must fit.
			if ( true === $result ) {
				foreach ( $state['pools'] as $pool ) {
					$pool_qty = 0;
					foreach ( $merged as $ticket_id => $qty ) {
						$ticket_state = $state['tickets'][ $ticket_id ];
						if ( 'event' === $pool['scope']
							|| ( 'category' === $pool['scope'] && (int) $ticket_state['category_id'] === (int) $pool['ref'] )
							|| ( 'ticket' === $pool['scope'] && (int) $ticket_id === (int) $pool['ref'] ) ) {
							$pool_qty += $qty;
						}
					}
					if ( $pool_qty > $pool['remaining'] ) {
						$result = $this->capacity_error(
							'ep_capacity_pool_exceeded',
							sprintf(
								/* translators: %d: number of places left for the event. */
								_n( 'Only %d place is left for this event. Please reduce the number of tickets and try again.', 'Only %d places are left for this event. Please reduce the number of tickets and try again.', $pool['remaining'], 'eventprime-event-calendar-management' ),
								$pool['remaining']
							),
							$state,
							null,
							$pool_qty
						);
						break;
					}
				}
			}

			return apply_filters( 'ep_capacity_check_result', $result, $event_id, $lines, $state, $context ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
		}

		/**
		 * Booking AJAX guard: lock the event and validate the posted cart, or send a specific error and stop.
		 *
		 * Admin Attendee Booking override: nothing is locked or checked, the skip is announced. Events where
		 * enforcement is off (no pool and the opt-out) fall through to the legacy checks. On a failed check the
		 * lock is released and a JSON error with the additive `remaining` data is sent (the request ends).
		 *
		 * @param int    $event_id    Event id.
		 * @param string $ticket_json Posted (or normalised) ticket data, JSON.
		 * @param bool   $override    Whether the Admin Attendee Booking override is active.
		 * @param bool   $lock        Whether to take the lock first (false for the re-check under the same lock).
		 * @return bool True when the cart was checked (so the caller re-checks the normalised lines and releases the lock).
		 */
		public function guard_checkout( $event_id, $ticket_json, $override, $lock = true ) {
			$event_id = absint( $event_id );
			if ( $event_id < 1 ) {
				return false;
			}
			if ( $override ) {
				do_action( 'ep_capacity_check_skipped', $event_id, 'checkout', 'Admin Attendee Booking' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
				return false;
			}
			$lines = json_decode( (string) $ticket_json );
			$lines = is_array( $lines ) ? $lines : array();
			if ( $lock ) {
				if ( ! $this->is_enforced( $event_id, 'checkout', $lines ) ) {
					return false;
				}
				$this->acquire_lock( $event_id );
			}
			$result = $this->check_cart(
				$event_id,
				$lines,
				array(
					'context'      => 'checkout',
					'fresh'        => true,
					'raise_to_min' => $lock,
				)
			);
			if ( is_wp_error( $result ) ) {
				$this->release_lock( $event_id );
				do_action( 'ep_capacity_rejected', $event_id, $lines, $result->get_error_code(), 'checkout', $result ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
				wp_send_json_error( $this->ajax_error_payload( $result ) );
			}
			return true;
		}

		/**
		 * Add the private "created with admin override" note to a booking.
		 *
		 * @param int $booking_id Booking id.
		 * @return void
		 */
		public function note_admin_override( $booking_id ) {
			$bookings = new EventPrime_Bookings();
			$bookings->add_notes( absint( $booking_id ), __( 'Created with admin override: capacity was not checked.', 'eventprime-event-calendar-management' ) );
		}

		/**
		 * Build the additive AJAX error payload of a failed capacity check.
		 *
		 * @param WP_Error $error Error returned by check_cart().
		 * @return array { error: message, remaining: { event: int, tickets: array( ticket id => int ) } }
		 */
		public function ajax_error_payload( $error ) {
			$data      = is_wp_error( $error ) ? $error->get_error_data() : array();
			$data      = is_array( $data ) ? $data : array();
			$tickets   = array();
			$ticket_id = isset( $data['ticket_id'] ) ? absint( $data['ticket_id'] ) : 0;
			if ( $ticket_id > 0 && isset( $data['ticket_remaining'] ) && null !== $data['ticket_remaining'] ) {
				$tickets[ $ticket_id ] = (int) $data['ticket_remaining'];
			}
			return array(
				'error'     => is_wp_error( $error ) ? $error->get_error_message() : '',
				'remaining' => array(
					'event'   => isset( $data['event_remaining'] ) ? (int) $data['event_remaining'] : 0,
					'tickets' => $tickets,
				),
			);
		}

		/**
		 * Whether a booking status counts against the capacity (completed and pending by default).
		 *
		 * @param string $status   Booking status.
		 * @param int    $event_id Event id.
		 * @return bool
		 */
		public function is_counted_status( $status, $event_id = 0 ) {
			$statuses = apply_filters( 'ep_capacity_counted_statuses', array( 'completed', 'pending' ), absint( $event_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			$statuses = is_array( $statuses ) ? $statuses : array( 'completed', 'pending' );
			return in_array( sanitize_key( $status ), $statuses, true );
		}

		/**
		 * Places a booking holds right now: its ticket quantities when it is in a counted status and not cancelled, else 0.
		 *
		 * Cheap (reads one booking, no event-wide count). Used to tell when a status change frees places.
		 *
		 * @param int $booking_id Booking id.
		 * @return int
		 */
		public function booking_counted_quantity( $booking_id ) {
			$booking_id = absint( $booking_id );
			$event_id   = absint( get_post_meta( $booking_id, 'em_event', true ) );
			if ( $booking_id < 1 || $event_id < 1 ) {
				return 0;
			}
			$statuses = apply_filters( 'ep_capacity_counted_statuses', array( 'completed', 'pending' ), $event_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			$statuses = is_array( $statuses ) ? $statuses : array( 'completed', 'pending' );
			if ( ! in_array( get_post_status( $booking_id ), $statuses, true ) || 'cancelled' === get_post_meta( $booking_id, 'em_status', true ) ) {
				return 0;
			}
			$order_info = get_post_meta( $booking_id, 'em_order_info', true );
			if ( is_string( $order_info ) ) {
				$functions  = new Eventprime_Basic_Functions();
				$order_info = $functions->ep_safe_maybe_unserialize( $order_info );
			}
			if ( ! is_array( $order_info ) ) {
				return 0;
			}
			$rows   = array();
			$legacy = false;
			if ( ! empty( $order_info['tickets'] ) && is_array( $order_info['tickets'] ) ) {
				$rows = $order_info['tickets'];
			} elseif ( ! empty( $order_info['order_item_data'] ) && is_array( $order_info['order_item_data'] ) ) {
				$rows   = $order_info['order_item_data'];
				$legacy = true;
			}
			$total = 0;
			foreach ( $rows as $row ) {
				$pair = $this->normalise_booked_row( $row, $legacy );
				if ( null !== $pair ) {
					$total += $pair[1];
				}
			}
			return $total;
		}

		/**
		 * Fire ep_event_capacity_released when a status change took places away from the counted bookings.
		 *
		 * @param int $booking_id Booking id.
		 * @param int $before     Result of booking_counted_quantity() before the change.
		 * @return void
		 */
		public function maybe_fire_released( $booking_id, $before ) {
			$before = absint( $before );
			if ( $before < 1 ) {
				return;
			}
			$freed = $before - $this->booking_counted_quantity( $booking_id );
			if ( $freed > 0 ) {
				do_action( 'ep_event_capacity_released', absint( get_post_meta( $booking_id, 'em_event', true ) ), $freed ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			}
		}

		/**
		 * Detect an overshoot when a booking is confirmed and record it. NEVER cancels, changes the status or refunds.
		 *
		 * Called by EventPrime_Bookings::confirm_booking(). Returns before any query when the event has no pool and
		 * enforcement is off. When the pool or one of the booking's ticket types is over its limit, a private booking
		 * note is added, post meta `em_capacity_overshoot` = 1 is set and `ep_event_capacity_exceeded` fires.
		 *
		 * @param int    $booking_id     Booking id.
		 * @param string $booking_status Status just given to the booking.
		 * @return bool True when an overshoot was recorded.
		 */
		public function maybe_record_overshoot( $booking_id, $booking_status ) {
			$booking_id = absint( $booking_id );
			$event_id   = absint( get_post_meta( $booking_id, 'em_event', true ) );
			if ( $booking_id < 1 || $event_id < 1 || ! in_array( $booking_status, array( 'completed', 'pending' ), true ) ) {
				return false;
			}
			if ( get_post_meta( $booking_id, 'em_capacity_overshoot', true ) ) {
				return false;
			}
			if ( ! $this->is_enforced( $event_id, 'confirm' ) ) {
				return false;
			}
			$state     = $this->get_state(
				$event_id,
				array(
					'context' => 'confirm',
					'fresh'   => true,
				)
			);
			$exceeded  = false;
			$order     = get_post_meta( $booking_id, 'em_order_info', true );
			$order     = is_array( $order ) ? $order : array();
			$own_lines = ! empty( $order['tickets'] ) && is_array( $order['tickets'] ) ? $order['tickets'] : array();
			if ( $state['oversold']['pool'] > 0 ) {
				$exceeded = true;
			}
			foreach ( $own_lines as $row ) {
				$pair = $this->normalise_booked_row( $row, false );
				if ( null !== $pair && isset( $state['oversold']['tickets'][ $pair[0] ] ) ) {
					$exceeded = true;
				}
			}
			if ( ! $exceeded ) {
				return false;
			}
			$bookings = new EventPrime_Bookings();
			$bookings->add_notes( $booking_id, __( 'Event capacity was exceeded when this booking was confirmed.', 'eventprime-event-calendar-management' ) );
			update_post_meta( $booking_id, 'em_capacity_overshoot', 1 );
			do_action( 'ep_event_capacity_exceeded', $booking_id, $event_id, $state ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in the file header.
			return true;
		}

		/**
		 * Build the MySQL lock name of an event.
		 *
		 * @param int $event_id Event id.
		 * @return string
		 */
		private function lock_name( $event_id ) {
			global $wpdb;
			return 'ep_cap_' . md5( $wpdb->prefix . (int) $event_id );
		}

		/**
		 * Acquire the per-event capacity lock (MySQL GET_LOCK, up to 5 seconds). Fail-open: false means "not locked".
		 *
		 * One lock per request: a lock already held for another event is released first, because older
		 * MySQL/MariaDB versions drop the earlier lock when a second one is taken.
		 *
		 * @param int $event_id Event id.
		 * @return bool True when this request holds the lock.
		 */
		public function acquire_lock( $event_id ) {
			global $wpdb;
			$event_id = absint( $event_id );
			if ( $event_id < 1 ) {
				return false;
			}
			$name = $this->lock_name( $event_id );
			if ( isset( self::$held_locks[ $name ] ) ) {
				return true;
			}
			foreach ( array_keys( self::$held_locks ) as $other ) {
				$this->release_lock_by_name( $other );
			}
			if ( ! self::$shutdown_registered ) {
				self::$shutdown_registered = true;
				register_shutdown_function( array( 'EventPrime_Capacity', 'release_all_locks' ) );
			}
			$suppress         = $wpdb->suppress_errors( true );
			$wpdb->last_error = '';
			$result           = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $name, 5 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock; no table is read or written.
			$error            = $wpdb->last_error;
			$wpdb->suppress_errors( $suppress );
			if ( '1' === (string) $result && '' === $error ) {
				self::$held_locks[ $name ] = true;
				return true;
			}
			if ( ! self::$lock_failure_logged && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				self::$lock_failure_logged = true;
				error_log( 'EventPrime: could not acquire the capacity lock for event ' . $event_id . '; continuing without it.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug log only, once per request.
			}
			return false;
		}

		/**
		 * Release the capacity lock of an event. Safe to call twice.
		 *
		 * @param int $event_id Event id.
		 * @return void
		 */
		public function release_lock( $event_id ) {
			$this->release_lock_by_name( $this->lock_name( absint( $event_id ) ) );
		}

		/**
		 * Release a lock by name if this request holds it.
		 *
		 * @param string $name Lock name.
		 * @return void
		 */
		private function release_lock_by_name( $name ) {
			global $wpdb;
			if ( ! isset( self::$held_locks[ $name ] ) ) {
				return;
			}
			unset( self::$held_locks[ $name ] );
			$suppress = $wpdb->suppress_errors( true );
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock; no table is read or written.
			$wpdb->suppress_errors( $suppress );
		}

		/**
		 * Release every lock this request holds (shutdown callback).
		 *
		 * @return void
		 */
		public static function release_all_locks() {
			$service = new EventPrime_Capacity();
			foreach ( array_keys( self::$held_locks ) as $name ) {
				$service->release_lock_by_name( $name );
			}
		}
	}

	add_action( 'ep_after_booking_created', array( 'EventPrime_Capacity', 'flush_cache' ), 10, 0 );
	add_action( 'ep_after_update_booking_status', array( 'EventPrime_Capacity', 'flush_cache' ), 10, 0 );
	add_action( 'ep_after_booking_complete', array( 'EventPrime_Capacity', 'flush_cache' ), 10, 0 );
	add_action( 'ep_after_booking_cancelled', array( 'EventPrime_Capacity', 'flush_cache' ), 10, 0 );
	add_action( 'transition_post_status', array( 'EventPrime_Capacity', 'flush_cache_on_transition' ), 10, 3 );
}

if ( ! function_exists( 'ep_get_event_overall_capacity' ) ) {
	/**
	 * Get the overall capacity (pool) of an event.
	 *
	 * @param mixed $event_or_id Event object or id.
	 * @return int Pool size, 0 = no pool.
	 */
	function ep_get_event_overall_capacity( $event_or_id ) { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function, ep_ is the plugin's established prefix.
		return ( new EventPrime_Capacity() )->get_overall_capacity( $event_or_id );
	}
}

if ( ! function_exists( 'ep_get_event_capacity_state' ) ) {
	/**
	 * Get the capacity state of an event. See EventPrime_Capacity::get_state().
	 *
	 * @param mixed $event_or_id Event object or id.
	 * @param array $args        Arguments.
	 * @return array
	 */
	function ep_get_event_capacity_state( $event_or_id, $args = array() ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function, ep_ is the plugin's established prefix.
		return ( new EventPrime_Capacity() )->get_state( $event_or_id, $args );
	}
}

if ( ! function_exists( 'ep_check_capacity_for_cart' ) ) {
	/**
	 * Check a cart against the capacity of an event. See EventPrime_Capacity::check_cart().
	 *
	 * @param mixed $event_or_id Event object or id.
	 * @param array $lines       Cart lines.
	 * @param array $args        Arguments.
	 * @return true|WP_Error
	 */
	function ep_check_capacity_for_cart( $event_or_id, $lines, $args = array() ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function, ep_ is the plugin's established prefix.
		return ( new EventPrime_Capacity() )->check_cart( $event_or_id, $lines, $args );
	}
}

if ( ! function_exists( 'ep_acquire_event_capacity_lock' ) ) {
	/**
	 * Acquire the per-event capacity lock.
	 *
	 * @param int $event_id Event id.
	 * @return bool
	 */
	function ep_acquire_event_capacity_lock( $event_id ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function, ep_ is the plugin's established prefix.
		return ( new EventPrime_Capacity() )->acquire_lock( $event_id );
	}
}

if ( ! function_exists( 'ep_release_event_capacity_lock' ) ) {
	/**
	 * Release the per-event capacity lock.
	 *
	 * @param int $event_id Event id.
	 * @return void
	 */
	function ep_release_event_capacity_lock( $event_id ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function, ep_ is the plugin's established prefix.
		( new EventPrime_Capacity() )->release_lock( $event_id );
	}
}
