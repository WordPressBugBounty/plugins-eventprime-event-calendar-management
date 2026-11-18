<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- File name follows the existing class-ep-*.php convention (class-ep-bookings.php).
/**
 * Helpers for bookings created or changed through the REST API and the Abilities API (#2347, #2012).
 *
 * The REST handlers stay in `class-eventprime-rest-api.php`; this class holds the logic that turns an API request into a
 * real booking: ticket resolution, quantity validation, the standard `em_order_info`, status changes that keep the post
 * status in step, and the additive capacity fields (`remaining`, `event_remaining`, `overall_capacity`).
 *
 * Hooks used (all defined by the capacity service, see `class-ep-capacity.php`):
 * - `ep_capacity_check_skipped( int $event_id, string $context, string $reason )` with context `rest` and reason
 *   `rest_override` (admin `override_capacity`) or `status_not_counted` (cancelled, failed or refunded booking).
 * - `ep_event_capacity_released( int $event_id, int $freed_qty )` when a status change frees places.
 * - `ep_update_booking_order_info( array $order_info, array $data )` (existing filter) runs on the order info.
 *
 * @package EventPrime
 * @since   4.4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'EventPrime_Rest_Booking' ) ) {

	/**
	 * REST and Abilities booking helpers.
	 */
	class EventPrime_Rest_Booking {

		/**
		 * Largest quantity accepted for one ticket in one request.
		 */
		const MAX_QTY = 1000;

		/**
		 * Largest number of ticket lines accepted in one request.
		 */
		const MAX_LINES = 50;

		/**
		 * Largest overall capacity accepted from the API.
		 */
		const MAX_POOL = 1000000;

		/**
		 * Read a positive whole number from request input.
		 *
		 * Accepts integers, whole floats and digit-only strings. Anything else (booleans, arrays, signs, decimals, text) is 0.
		 *
		 * @param mixed $value Raw value.
		 * @return int Positive integer, 0 when the value is not one.
		 */
		public function positive_int( $value ) {
			if ( is_int( $value ) ) {
				return $value > 0 ? $value : 0;
			}
			if ( is_float( $value ) ) {
				return ( $value > 0 && floor( $value ) === $value && $value < PHP_INT_MAX ) ? (int) $value : 0;
			}
			if ( is_string( $value ) && preg_match( '/^\s*\d{1,18}\s*$/', $value ) ) {
				return (int) trim( $value );
			}
			return 0;
		}

		/**
		 * Whether a request flag is on (true, 1, "1", "true", "yes", "on").
		 *
		 * @param mixed $value Raw value.
		 * @return bool
		 */
		public function is_truthy( $value ) {
			if ( is_bool( $value ) ) {
				return $value;
			}
			if ( is_int( $value ) ) {
				return 1 === $value;
			}
			return is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		/**
		 * Translate a ticket mirror post id (as listed by GET /tickets) into the core ticket id.
		 *
		 * @param int $post_id Ticket post id.
		 * @return int Core ticket id, 0 when the id is not a ticket mirror post.
		 */
		public function mirror_to_core( $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id < 1 ) {
				return 0;
			}
			$post = get_post( $post_id );
			if ( ! $post || 'em_ticket' !== $post->post_type ) {
				return 0;
			}
			return absint( get_post_meta( $post_id, 'em_ticket_db_id', true ) );
		}

		/**
		 * Resolve a ticket reference of a booking request to a core ticket row of the booking event.
		 *
		 * Both kinds of id are accepted: the core ticket id (the original contract of POST /bookings) and the ticket
		 * post id that GET /tickets returns. The core id is tried first so that existing callers keep working. When a
		 * mirror post id and a core id of the same event are the same number, the core ticket wins; send the core id
		 * or use an id that is not shared to avoid the overlap.
		 *
		 * @param mixed $reference     Ticket id from the request.
		 * @param int   $event_id      Booking event id.
		 * @param bool  $prefer_mirror Try the ticket post id first (used by the Abilities wrapper).
		 * @return object|null Ticket row, null when no ticket of this event matches.
		 */
		public function resolve_ticket_reference( $reference, $event_id, $prefer_mirror = false ) {
			$reference = $this->positive_int( $reference );
			$event_id  = absint( $event_id );
			if ( $reference < 1 || $event_id < 1 ) {
				return null;
			}
			$functions  = new Eventprime_Basic_Functions();
			$mirror     = $this->mirror_to_core( $reference );
			$candidates = $prefer_mirror ? array( $mirror, $reference ) : array( $reference, $mirror );
			foreach ( $candidates as $core_id ) {
				if ( $core_id < 1 ) {
					continue;
				}
				$rows = $functions->ep_get_ticket_data( $core_id );
				if ( ! empty( $rows ) && isset( $rows[0]->event_id ) && absint( $rows[0]->event_id ) === $event_id ) {
					return $rows[0];
				}
			}
			return null;
		}

		/**
		 * Build the validated ticket lines of a booking request.
		 *
		 * Input is `tickets` (array of `{ ticket_id, qty }`, wins when present) or the single `ticket_id` with an optional
		 * `quantity`. A missing quantity is the ticket's minimum per order (at least 1). Duplicate tickets are merged.
		 * Errors are WP_Error with HTTP 400: ep_invalid_tickets, ep_invalid_ticket, ep_invalid_quantity.
		 *
		 * @param array $body     Request body.
		 * @param int   $event_id Booking event id.
		 * @return array|WP_Error Lines keyed by core ticket id: array( 'ticket_id' => int, 'qty' => int, 'row' => object ).
		 */
		public function parse_lines( $body, $event_id ) {
			$raw = array();
			if ( isset( $body['tickets'] ) && ! empty( $body['tickets'] ) ) {
				if ( ! is_array( $body['tickets'] ) || count( $body['tickets'] ) > self::MAX_LINES ) {
					return new WP_Error( 'ep_invalid_tickets', esc_html__( 'The tickets field must be a list of ticket lines (at most 50).', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
				}
				foreach ( $body['tickets'] as $line ) {
					$line = is_object( $line ) ? (array) $line : $line;
					if ( ! is_array( $line ) ) {
						return new WP_Error( 'ep_invalid_tickets', esc_html__( 'Each ticket line needs a ticket_id and a qty.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
					}
					$reference = isset( $line['ticket_id'] ) ? $line['ticket_id'] : ( isset( $line['id'] ) ? $line['id'] : 0 );
					$qty       = null;
					if ( array_key_exists( 'qty', $line ) ) {
						$qty = $line['qty'];
					} elseif ( array_key_exists( 'quantity', $line ) ) {
						$qty = $line['quantity'];
					}
					$raw[] = array( $reference, $qty );
				}
			} else {
				$reference = isset( $body['ticket_id'] ) ? $body['ticket_id'] : 0;
				$qty       = ( isset( $body['quantity'] ) && null !== $body['quantity'] ) ? $body['quantity'] : null;
				$raw[]     = array( $reference, $qty );
			}

			$lines = array();
			foreach ( $raw as $pair ) {
				$row = $this->resolve_ticket_reference( $pair[0], $event_id );
				if ( null === $row ) {
					return new WP_Error( 'ep_invalid_ticket', esc_html__( 'Invalid ticket: it does not exist or does not belong to this event.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
				}
				$core_id = absint( $row->id );
				$min     = isset( $row->min_ticket_no ) ? absint( $row->min_ticket_no ) : 0;
				if ( null === $pair[1] ) {
					$qty = max( 1, $min );
				} else {
					$qty = $this->positive_int( $pair[1] );
					if ( $qty < 1 || $qty > self::MAX_QTY ) {
						return new WP_Error( 'ep_invalid_quantity', esc_html__( 'The quantity must be a whole number between 1 and 1000.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
					}
				}
				if ( isset( $lines[ $core_id ] ) ) {
					$lines[ $core_id ]['qty'] += $qty;
				} else {
					$lines[ $core_id ] = array(
						'ticket_id' => $core_id,
						'qty'       => $qty,
						'row'       => $row,
					);
				}
			}

			foreach ( $lines as $core_id => $line ) {
				$min = isset( $line['row']->min_ticket_no ) ? absint( $line['row']->min_ticket_no ) : 0;
				$max = isset( $line['row']->max_ticket_no ) ? absint( $line['row']->max_ticket_no ) : 0;
				if ( $line['qty'] > self::MAX_QTY || ( $min > 0 && $line['qty'] < $min ) || ( $max > 0 && $line['qty'] > $max ) ) {
					return new WP_Error(
						'ep_invalid_quantity',
						esc_html__( 'The quantity is outside the minimum or maximum allowed per order for this ticket.', 'eventprime-event-calendar-management' ),
						array(
							'status'        => 400,
							'ticket_id'     => $core_id,
							'min_per_order' => $min,
							'max_per_order' => $max,
						)
					);
				}
			}
			return $lines;
		}

		/**
		 * Build the standard order info of a booking, the same shape the core checkout stores.
		 *
		 * Names and prices come from the ticket rows (never from the request). Offers and fees come from the same
		 * calculation the checkout uses.
		 *
		 * @param int   $event_id Event id.
		 * @param array $lines    Lines from parse_lines().
		 * @param array $context  Extra data passed to the ep_update_booking_order_info filter.
		 * @return array Order info: tickets (objects), event_fixed_price, booking_total.
		 */
		public function build_order_info( $event_id, $lines, $context = array() ) {
			$functions = new Eventprime_Basic_Functions();
			$tickets   = array();
			$subtotal  = 0.0;
			foreach ( $lines as $line ) {
				$cart      = (object) $functions->eventprime_update_cart_response( $line['ticket_id'], $line['qty'] );
				$tickets[] = (object) array(
					'id'             => absint( $line['ticket_id'] ),
					'category_id'    => isset( $cart->category_id ) ? absint( $cart->category_id ) : 0,
					'name'           => isset( $cart->name ) ? $cart->name : '',
					'price'          => isset( $cart->price ) ? (float) $cart->price : 0.0,
					'qty'            => absint( $line['qty'] ),
					'offer'          => isset( $cart->offer ) ? (float) $cart->offer : 0.0,
					'additional_fee' => isset( $cart->additional_fee ) && is_array( $cart->additional_fee ) ? $cart->additional_fee : array(),
					'subtotal'       => isset( $cart->subtotal ) ? (float) $cart->subtotal : 0.0,
				);
				$subtotal += isset( $cart->subtotal ) ? (float) $cart->subtotal : 0.0;
			}
			$fixed      = (float) get_post_meta( $event_id, 'em_fixed_event_price', true );
			$order_info = array(
				'tickets'           => $tickets,
				'event_fixed_price' => $fixed,
				'booking_total'     => round( $subtotal + $fixed, 4 ),
			);
			$context    = is_array( $context ) ? $context : array();
			return apply_filters( 'ep_update_booking_order_info', $order_info, $context ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook, also fired by the checkout.
		}

		/**
		 * Ticket summary of a stored booking, for the REST response.
		 *
		 * @param array $order_info Order info built by build_order_info().
		 * @return array List of { ticket_id, name, price, qty, subtotal }.
		 */
		public function summarise_tickets( $order_info ) {
			$out  = array();
			$rows = isset( $order_info['tickets'] ) && is_array( $order_info['tickets'] ) ? $order_info['tickets'] : array();
			foreach ( $rows as $row ) {
				$row   = (array) $row;
				$out[] = array(
					'ticket_id' => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
					'name'      => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '',
					'price'     => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
					'qty'       => isset( $row['qty'] ) ? absint( $row['qty'] ) : 0,
					'subtotal'  => isset( $row['subtotal'] ) ? (float) $row['subtotal'] : 0.0,
				);
			}
			return $out;
		}

		/**
		 * Change the status of a booking: validate it, keep post_status and em_status in step, release places.
		 *
		 * No emails and no refund hooks are fired (decision Q6): the update only changes what the booking counts as.
		 * A booking that comes back into a counted status is checked against the capacity (HTTP 409) unless an
		 * administrator passes the override.
		 *
		 * @param int   $booking_id Booking id.
		 * @param mixed $status     Requested status.
		 * @param bool  $override   Whether the caller may and did ask to skip the capacity check.
		 * @return string|WP_Error The new status, or WP_Error (ep_invalid_booking_status 400, capacity errors 409).
		 */
		public function apply_status( $booking_id, $status, $override = false ) {
			$booking_id = absint( $booking_id );
			$status     = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
			$functions  = new Eventprime_Basic_Functions();
			$allowed    = array_keys( $functions->ep_get_booking_status() );
			if ( ! in_array( $status, $allowed, true ) ) {
				return new WP_Error( 'ep_invalid_booking_status', esc_html__( 'Invalid booking status.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
			}

			$capacity = new EventPrime_Capacity();
			$event_id = absint( get_post_meta( $booking_id, 'em_event', true ) );
			$before   = $capacity->booking_counted_quantity( $booking_id );
			$locked   = false;

			if ( $before < 1 && $event_id > 0 && $capacity->is_counted_status( $status, $event_id ) ) {
				$lines = $this->lines_from_booking( $booking_id );
				if ( ! empty( $lines ) ) {
					if ( $override ) {
						do_action( 'ep_capacity_check_skipped', $event_id, 'rest', 'rest_override' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook documented in class-ep-capacity.php.
					} elseif ( $capacity->is_enforced( $event_id, 'rest', $lines ) ) {
						$locked = $capacity->acquire_lock( $event_id );
						$result = $capacity->check_cart(
							$event_id,
							$lines,
							array(
								'context' => 'rest',
								'fresh'   => true,
							)
						);
						if ( is_wp_error( $result ) ) {
							$capacity->release_lock( $event_id );
							return $result;
						}
					}
				}
			}

			wp_update_post(
				array(
					'ID'          => $booking_id,
					'post_status' => $status,
				)
			);
			update_post_meta( $booking_id, 'em_status', $status );
			EventPrime_Capacity::flush_cache();
			if ( $locked ) {
				$capacity->release_lock( $event_id );
			}
			$capacity->maybe_fire_released( $booking_id, $before );
			return $status;
		}

		/**
		 * Ticket lines of a stored booking, for a capacity re-check.
		 *
		 * @param int $booking_id Booking id.
		 * @return array List of array( 'ticket_id' => int, 'qty' => int ).
		 */
		protected function lines_from_booking( $booking_id ) {
			$order_info = get_post_meta( $booking_id, 'em_order_info', true );
			$lines      = array();
			if ( ! is_array( $order_info ) || empty( $order_info['tickets'] ) || ! is_array( $order_info['tickets'] ) ) {
				return $lines;
			}
			foreach ( $order_info['tickets'] as $row ) {
				$row = (array) $row;
				if ( ! empty( $row['id'] ) && ! empty( $row['qty'] ) ) {
					$lines[] = array(
						'ticket_id' => absint( $row['id'] ),
						'qty'       => absint( $row['qty'] ),
					);
				}
			}
			return $lines;
		}

		/**
		 * Read and validate the `overall_capacity` field of an event create or update request.
		 *
		 * - Field absent: `present` is false (an update must then leave the pool unchanged).
		 * - Empty string, null or 0: `present` is true and `value` is '' (no pool; an update clears the pool).
		 * - A whole number 1 to 1,000,000 (int or digit string): `value` is that integer.
		 * - Anything else (negative, decimal, text, boolean, array): WP_Error ep_invalid_overall_capacity, HTTP 400.
		 *
		 * @param array $body Request body.
		 * @return array|WP_Error array( 'present' => bool, 'value' => int|string ).
		 */
		public function parse_overall_capacity( $body ) {
			if ( ! is_array( $body ) || ! array_key_exists( 'overall_capacity', $body ) ) {
				return array(
					'present' => false,
					'value'   => '',
				);
			}
			$value = $body['overall_capacity'];
			if ( null === $value || '' === $value || 0 === $value || '0' === $value ) {
				return array(
					'present' => true,
					'value'   => '',
				);
			}
			$number = $this->positive_int( $value );
			if ( $number < 1 || $number > self::MAX_POOL ) {
				return new WP_Error( 'ep_invalid_overall_capacity', esc_html__( 'The overall capacity must be a whole number between 1 and 1,000,000, or empty for no limit.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
			}
			return array(
				'present' => true,
				'value'   => $number,
			);
		}

		/**
		 * Store a validated overall capacity on an event (a positive number, or '' to remove the limit).
		 *
		 * @param int   $event_id Event id.
		 * @param array $pool     Result of parse_overall_capacity().
		 * @return void
		 */
		public function save_overall_capacity( $event_id, $pool ) {
			if ( ! empty( $pool['present'] ) ) {
				update_post_meta( absint( $event_id ), 'em_event_overall_capacity', $pool['value'] );
				EventPrime_Capacity::flush_cache();
			}
		}

		/**
		 * Additive capacity fields of an event for REST and Abilities output.
		 *
		 * @param int        $event_id Event id.
		 * @param array|null $state    A state from EventPrime_Capacity::get_state(), when the caller has one.
		 * @return array { overall_capacity: int|null, event_remaining: int|null (null when the event has no tickets) }
		 */
		public function event_capacity_fields( $event_id, $state = null ) {
			if ( ! is_array( $state ) ) {
				$state = $this->get_state( $event_id );
			}
			return array(
				'overall_capacity' => ( isset( $state['total'] ) && null !== $state['total'] ) ? (int) $state['total'] : null,
				'event_remaining'  => empty( $state['tickets'] ) ? null : (int) $state['remaining'],
			);
		}

		/**
		 * Capacity state of an event for the REST context.
		 *
		 * @param int $event_id Event id.
		 * @return array
		 */
		public function get_state( $event_id ) {
			$service = new EventPrime_Capacity();
			return $service->get_state( absint( $event_id ), array( 'context' => 'rest' ) );
		}

		/**
		 * Additive per-ticket capacity fields for GET /tickets.
		 *
		 * @param int   $ticket_post_id Ticket post id (the id GET /tickets lists).
		 * @param array $state          Event capacity state.
		 * @return array { capacity: int|null, remaining: int|null, sold: int|null }
		 */
		public function ticket_capacity_fields( $ticket_post_id, $state ) {
			$core_id = $this->mirror_to_core( $ticket_post_id );
			if ( $core_id < 1 || ! isset( $state['tickets'][ $core_id ] ) ) {
				return array(
					'capacity'  => null,
					'remaining' => null,
					'sold'      => null,
				);
			}
			$ticket = $state['tickets'][ $core_id ];
			return array(
				'capacity'  => (int) $ticket['capacity'],
				'remaining' => (int) $ticket['effective_remaining'],
				'sold'      => (int) $ticket['sold'],
			);
		}
	}
}
