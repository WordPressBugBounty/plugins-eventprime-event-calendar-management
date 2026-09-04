<?php
/**
 * EventPrime Abilities API integration.
 */

defined( 'ABSPATH' ) || exit;

class EventPrime_Abilities {

	public function register_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'eventprime-content-management',
			array(
				'label'       => esc_html__( 'EventPrime Content Management', 'eventprime-event-calendar-management' ),
				'description' => esc_html__( 'Abilities for reading and managing EventPrime entities and attendee data.', 'eventprime-event-calendar-management' ),
			)
		);
	}

	public function register_abilities() {
		if ( ! class_exists( 'WP_Ability' ) || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_list_events_ability();
		$this->register_get_event_ability();
		$this->register_create_event_ability();
		$this->register_update_event_ability();
		$this->register_delete_event_ability();
		$this->register_list_tickets_ability();
		$this->register_check_ticket_availability_ability();
		$this->register_create_booking_ability();
		$this->register_get_booking_ability();
		$this->register_cancel_booking_ability();
		$this->register_list_venues_ability();
		$this->register_create_venue_ability();
		$this->register_list_performers_ability();
		$this->register_create_performer_ability();
		$this->register_list_organizers_ability();
		$this->register_create_organizer_ability();
		$this->register_list_event_types_ability();
		$this->register_create_event_type_ability();
		$this->register_export_attendees_ability();
		$this->register_update_user_timezone_ability();
	}

	protected function register_list_events_ability() {
		wp_register_ability(
			'eventprime/list-events',
			array(
				'label'               => esc_html__( 'List Events', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns EventPrime events visible to the current user.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_collection_query_schema(),
				'output_schema'       => $this->get_collection_output_schema( 'events', esc_html__( 'The returned events.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_list_events' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_get_event_ability() {
		wp_register_ability(
			'eventprime/get-event',
			array(
				'label'               => esc_html__( 'Get Event', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns a single EventPrime event.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_required_id_schema( 'id', esc_html__( 'Event ID.', 'eventprime-event-calendar-management' ) ),
				'output_schema'       => $this->get_entity_output_schema( 'event', esc_html__( 'The returned event.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_get_event' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_create_event_ability() {
		wp_register_ability(
			'eventprime/create-event',
			array(
				'label'               => esc_html__( 'Create Event', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Creates a new EventPrime event using the plugin event creation workflow.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_event_create_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'event', esc_html__( 'The created event payload.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_create_event' ),
				'permission_callback' => array( $this, 'can_create_event' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_update_event_ability() {
		wp_register_ability(
			'eventprime/update-event',
			array(
				'label'               => esc_html__( 'Update Event', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Updates an existing EventPrime event.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_event_update_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'event', esc_html__( 'The updated event payload.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_update_event' ),
				'permission_callback' => array( $this, 'can_update_event' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_delete_event_ability() {
		wp_register_ability(
			'eventprime/delete-event',
			array(
				'label'               => esc_html__( 'Delete Event', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Deletes an EventPrime event.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_required_id_schema( 'id', esc_html__( 'Event ID.', 'eventprime-event-calendar-management' ) ),
				'output_schema'       => $this->get_message_output_schema(),
				'execute_callback'    => array( $this, 'execute_delete_event' ),
				'permission_callback' => array( $this, 'can_delete_event' ),
				'meta'                => $this->get_destructive_meta(),
			)
		);
	}

	protected function register_list_tickets_ability() {
		wp_register_ability(
			'eventprime/list-tickets',
			array(
				'label'               => esc_html__( 'List Tickets', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns EventPrime tickets, optionally filtered by event.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_ticket_list_input_schema(),
				'output_schema'       => $this->get_collection_output_schema( 'tickets', esc_html__( 'The returned tickets.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_list_tickets' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_check_ticket_availability_ability() {
		wp_register_ability(
			'eventprime/check-ticket-availability',
			array(
				'label'               => esc_html__( 'Check Ticket Availability', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Checks whether a ticket or event has available tickets.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_ticket_availability_input_schema(),
				'output_schema'       => $this->get_availability_output_schema(),
				'execute_callback'    => array( $this, 'execute_check_ticket_availability' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_create_booking_ability() {
		wp_register_ability(
			'eventprime/create-booking',
			array(
				'label'               => esc_html__( 'Create Booking', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Creates an EventPrime booking.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_booking_create_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'booking', esc_html__( 'The created booking.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_create_booking' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_get_booking_ability() {
		wp_register_ability(
			'eventprime/get-booking',
			array(
				'label'               => esc_html__( 'Get Booking', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns a single EventPrime booking.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_required_id_schema( 'id', esc_html__( 'Booking ID.', 'eventprime-event-calendar-management' ) ),
				'output_schema'       => $this->get_entity_output_schema( 'booking', esc_html__( 'The returned booking.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_get_booking' ),
				'permission_callback' => array( $this, 'can_view_booking' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_cancel_booking_ability() {
		wp_register_ability(
			'eventprime/cancel-booking',
			array(
				'label'               => esc_html__( 'Cancel Booking', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Cancels an existing EventPrime booking.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_required_id_schema( 'id', esc_html__( 'Booking ID.', 'eventprime-event-calendar-management' ) ),
				'output_schema'       => $this->get_entity_output_schema( 'booking', esc_html__( 'The updated booking.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_cancel_booking' ),
				'permission_callback' => array( $this, 'can_cancel_booking' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_list_venues_ability() {
		wp_register_ability(
			'eventprime/list-venues',
			array(
				'label'               => esc_html__( 'List Venues', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns EventPrime venues.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_collection_query_schema(),
				'output_schema'       => $this->get_collection_output_schema( 'venues', esc_html__( 'The returned venues.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_list_venues' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_create_venue_ability() {
		wp_register_ability(
			'eventprime/create-venue',
			array(
				'label'               => esc_html__( 'Create Venue', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Creates a new EventPrime venue.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_named_entity_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'venue', esc_html__( 'The created venue payload.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_create_venue' ),
				'permission_callback' => array( $this, 'can_manage_event_terms' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_list_performers_ability() {
		wp_register_ability(
			'eventprime/list-performers',
			array(
				'label'               => esc_html__( 'List Performers', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns EventPrime performers.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_collection_query_schema(),
				'output_schema'       => $this->get_collection_output_schema( 'performers', esc_html__( 'The returned performers.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_list_performers' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_create_performer_ability() {
		wp_register_ability(
			'eventprime/create-performer',
			array(
				'label'               => esc_html__( 'Create Performer', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Creates a new EventPrime performer.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_named_entity_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'performer', esc_html__( 'The created performer payload.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_create_performer' ),
				'permission_callback' => array( $this, 'can_create_performer' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_list_organizers_ability() {
		wp_register_ability(
			'eventprime/list-organizers',
			array(
				'label'               => esc_html__( 'List Organizers', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns EventPrime organizers.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_collection_query_schema(),
				'output_schema'       => $this->get_collection_output_schema( 'organizers', esc_html__( 'The returned organizers.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_list_organizers' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_create_organizer_ability() {
		wp_register_ability(
			'eventprime/create-organizer',
			array(
				'label'               => esc_html__( 'Create Organizer', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Creates a new EventPrime organizer.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_named_entity_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'organizer', esc_html__( 'The created organizer payload.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_create_organizer' ),
				'permission_callback' => array( $this, 'can_manage_event_terms' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_list_event_types_ability() {
		wp_register_ability(
			'eventprime/list-event-types',
			array(
				'label'               => esc_html__( 'List Event Types', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns EventPrime event types.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_collection_query_schema(),
				'output_schema'       => $this->get_collection_output_schema( 'event_types', esc_html__( 'The returned event types.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_list_event_types' ),
				'permission_callback' => array( $this, 'allow_public_access' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_create_event_type_ability() {
		wp_register_ability(
			'eventprime/create-event-type',
			array(
				'label'               => esc_html__( 'Create Event Type', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Creates a new EventPrime event type.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_named_entity_input_schema(),
				'output_schema'       => $this->get_entity_output_schema( 'event_type', esc_html__( 'The created event type payload.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_create_event_type' ),
				'permission_callback' => array( $this, 'can_manage_event_terms' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	protected function register_export_attendees_ability() {
		wp_register_ability(
			'eventprime/export-attendees',
			array(
				'label'               => esc_html__( 'Export Attendees', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Returns attendee export data for an EventPrime event.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_required_id_schema( 'event_id', esc_html__( 'Event ID.', 'eventprime-event-calendar-management' ) ),
				'output_schema'       => $this->get_collection_output_schema( 'attendees', esc_html__( 'The exported attendees.', 'eventprime-event-calendar-management' ) ),
				'execute_callback'    => array( $this, 'execute_export_attendees' ),
				'permission_callback' => array( $this, 'can_export_attendees' ),
				'meta'                => $this->get_read_meta(),
			)
		);
	}

	protected function register_update_user_timezone_ability() {
		wp_register_ability(
			'eventprime/update-user-timezone',
			array(
				'label'               => esc_html__( 'Update User Timezone', 'eventprime-event-calendar-management' ),
				'description'         => esc_html__( 'Updates the current user timezone preference used by EventPrime.', 'eventprime-event-calendar-management' ),
				'category'            => 'eventprime-content-management',
				'input_schema'        => $this->get_timezone_input_schema(),
				'output_schema'       => $this->get_message_output_schema(),
				'execute_callback'    => array( $this, 'execute_update_user_timezone' ),
				'permission_callback' => array( $this, 'can_update_timezone' ),
				'meta'                => $this->get_mutation_meta(),
			)
		);
	}

	public function execute_list_events( $input = array() ) {
		return $this->dispatch_rest_handler( 'get_events', 'GET', '/eventprime/v1/events', (array) $input );
	}

	public function execute_get_event( $input = array() ) {
		$input = (array) $input;
		$event_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		return $this->dispatch_rest_handler(
			'get_event',
			'GET',
			'/eventprime/v1/events/' . $event_id,
			array(),
			array(
				'id' => $event_id,
			)
		);
	}

	public function execute_create_event( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_event_create', 'POST', '/eventprime/v1/events', (array) $input );
	}

	public function execute_update_event( $input = array() ) {
		$input    = (array) $input;
		$event_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		unset( $input['id'] );

		return $this->dispatch_rest_handler(
			'handle_event_update',
			'POST',
			'/eventprime/v1/events/' . $event_id,
			$input,
			array(
				'id' => $event_id,
			)
		);
	}

	public function execute_delete_event( $input = array() ) {
		$input    = (array) $input;
		$event_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		return $this->dispatch_rest_handler(
			'handle_event_delete',
			'DELETE',
			'/eventprime/v1/events/' . $event_id,
			array(),
			array(
				'id' => $event_id,
			)
		);
	}

	public function execute_list_tickets( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_tickets_list', 'GET', '/eventprime/v1/tickets', (array) $input );
	}

	public function execute_check_ticket_availability( $input = array() ) {
		$input   = (array) $input;
		$tickets = $this->execute_list_tickets( $input );
		if ( is_wp_error( $tickets ) ) {
			return $tickets;
		}

		$ticket_id = isset( $input['ticket_id'] ) ? absint( $input['ticket_id'] ) : 0;
		$items     = isset( $tickets['tickets'] ) && is_array( $tickets['tickets'] ) ? $tickets['tickets'] : array();
		$matched   = null;

		if ( $ticket_id > 0 ) {
			foreach ( $items as $item ) {
				$item_id = $this->extract_id_from_value( $item );
				if ( $item_id === $ticket_id ) {
					$matched = $item;
					break;
				}
			}

			if ( null === $matched ) {
				return new WP_Error( 'ep_ticket_not_found', esc_html__( 'Ticket not found.', 'eventprime-event-calendar-management' ), array( 'status' => 404 ) );
			}
		}

		$available = false;
		$available_count = 0;
		$source = null !== $matched ? $matched : ( ! empty( $items ) ? $items[0] : null );

		if ( is_array( $source ) && array_key_exists( 'available', $source ) && null !== $source['available'] ) {
			$available_count = (int) $source['available'];
			$available       = $available_count > 0;
		} elseif ( ! empty( $items ) ) {
			$available = true;
		}

		return array(
			'status'          => 'success',
			'available'       => $available,
			'available_count' => $available_count,
			'ticket'          => null !== $matched ? $matched : new stdClass(),
			'ticket_count'    => count( $items ),
		);
	}

	public function execute_create_booking( $input = array() ) {
		$input = (array) $input;

		if ( ! empty( $input['ticket_id'] ) ) {
			$ticket_id    = absint( $input['ticket_id'] );
			$ticket_post  = $ticket_id ? get_post( $ticket_id ) : null;
			$ticket_db_id = $ticket_post && 'em_ticket' === $ticket_post->post_type ? absint( get_post_meta( $ticket_id, 'em_ticket_db_id', true ) ) : 0;

			if ( $ticket_db_id > 0 ) {
				$input['ticket_id'] = $ticket_db_id;
			}
		}

		return $this->dispatch_rest_handler( 'handle_booking_create', 'POST', '/eventprime/v1/bookings', $input );
	}

	public function execute_get_booking( $input = array() ) {
		$input      = (array) $input;
		$booking_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $booking_id ) ) {
			return new WP_Error( 'ep_missing_booking_id', esc_html__( 'A booking ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		return $this->dispatch_rest_handler(
			'handle_booking_get',
			'GET',
			'/eventprime/v1/bookings/' . $booking_id,
			array(),
			array(
				'id' => $booking_id,
			)
		);
	}

	public function execute_cancel_booking( $input = array() ) {
		$input      = (array) $input;
		$booking_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $booking_id ) ) {
			return new WP_Error( 'ep_missing_booking_id', esc_html__( 'A booking ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		return $this->dispatch_rest_handler(
			'handle_booking_update',
			'POST',
			'/eventprime/v1/bookings/' . $booking_id,
			array( 'status' => 'canceled' ),
			array(
				'id' => $booking_id,
			)
		);
	}

	public function execute_list_venues( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_venues_list', 'GET', '/eventprime/v1/venues', (array) $input );
	}

	public function execute_create_venue( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_venue_create', 'POST', '/eventprime/v1/venues', (array) $input );
	}

	public function execute_list_performers( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_performers_list', 'GET', '/eventprime/v1/performers', (array) $input );
	}

	public function execute_create_performer( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_performer_create', 'POST', '/eventprime/v1/performers', (array) $input );
	}

	public function execute_list_organizers( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_organizers_list', 'GET', '/eventprime/v1/organizers', (array) $input );
	}

	public function execute_create_organizer( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_organizer_create', 'POST', '/eventprime/v1/organizers', (array) $input );
	}

	public function execute_list_event_types( $input = array() ) {
		return $this->dispatch_integration_action( 'get_event_type', (array) $input, 'GET' );
	}

	public function execute_create_event_type( $input = array() ) {
		return $this->dispatch_rest_handler( 'handle_event_type_create', 'POST', '/eventprime/v1/event-types', (array) $input );
	}

	public function execute_export_attendees( $input = array() ) {
		$input    = (array) $input;
		$event_id = isset( $input['event_id'] ) ? absint( $input['event_id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		$event = get_post( $event_id );
		if ( empty( $event ) || 'em_event' !== $event->post_type ) {
			return new WP_Error( 'ep_event_not_found', esc_html__( 'Event not found.', 'eventprime-event-calendar-management' ), array( 'status' => 404 ) );
		}

		$bookings = get_posts(
			array(
				'post_type'      => 'em_booking',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => 'em_event',
						'value' => $event_id,
					),
				),
			)
		);

		$attendees = array();
		foreach ( $bookings as $booking_post ) {
			$user_id     = (int) get_post_meta( $booking_post->ID, 'em_user', true );
			$user        = $user_id ? get_userdata( $user_id ) : false;
			$status      = get_post_meta( $booking_post->ID, 'em_status', true );
			$ticket_meta = get_post_meta( $booking_post->ID, 'em_attendee_names', true );

			$attendees[] = array(
				'booking_id'      => (int) $booking_post->ID,
				'event_id'        => $event_id,
				'event_name'      => sanitize_text_field( get_the_title( $event_id ) ),
				'user_id'         => $user_id,
				'user_name'       => $user ? $user->display_name : '',
				'user_email'      => $user ? $user->user_email : '',
				'booking_status'  => sanitize_text_field( $status ),
				'attendee_fields' => maybe_unserialize( $ticket_meta ),
			);
		}

		return array(
			'status'    => 'success',
			'count'     => count( $attendees ),
			'attendees' => $attendees,
		);
	}

	public function execute_update_user_timezone( $input = array() ) {
		$input     = (array) $input;
		$time_zone = isset( $input['time_zone'] ) ? sanitize_text_field( $input['time_zone'] ) : '';
		$user_id   = get_current_user_id();

		if ( empty( $user_id ) ) {
			return new WP_Error( 'ep_not_logged_in', esc_html__( 'You must be logged in to update your timezone.', 'eventprime-event-calendar-management' ), array( 'status' => 401 ) );
		}

		if ( empty( $time_zone ) ) {
			return new WP_Error( 'ep_missing_time_zone', esc_html__( 'A timezone is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		update_user_meta( $user_id, 'ep_user_timezone_meta', $time_zone );

		return array(
			'status'   => 'success',
			'message'  => esc_html__( 'Timezone updated successfully.', 'eventprime-event-calendar-management' ),
			'timezone' => $time_zone,
			'user_id'  => $user_id,
		);
	}

	public function allow_public_access( $input = array() ) {
		unset( $input );
		return true;
	}

	public function can_create_event( $input = array() ) {
		unset( $input );

		if ( current_user_can( 'manage_options' ) || current_user_can( 'publish_em_events' ) || current_user_can( 'edit_em_events' ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to create events.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_update_event( $input = array() ) {
		$event_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $event_id ) || current_user_can( 'edit_em_event', $event_id ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to update this event.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_delete_event( $input = array() ) {
		$event_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'delete_post', $event_id ) || current_user_can( 'delete_em_event', $event_id ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to delete this event.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_view_booking( $input = array() ) {
		$booking_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( empty( $booking_id ) ) {
			return new WP_Error( 'ep_missing_booking_id', esc_html__( 'A booking ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		$booking_user_id = (int) get_post_meta( $booking_id, 'em_user', true );
		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $booking_id ) || ( $booking_user_id > 0 && $booking_user_id === get_current_user_id() ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to view this booking.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_cancel_booking( $input = array() ) {
		return $this->can_view_booking( $input );
	}

	public function can_manage_event_terms( $input = array() ) {
		unset( $input );

		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_em_event_terms' ) || current_user_can( 'edit_em_event_terms' ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to manage EventPrime terms.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_create_performer( $input = array() ) {
		unset( $input );

		if ( current_user_can( 'manage_options' ) || current_user_can( 'publish_em_performers' ) || current_user_can( 'edit_em_performers' ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to create performers.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_export_attendees( $input = array() ) {
		$event_id = isset( $input['event_id'] ) ? absint( $input['event_id'] ) : 0;
		if ( empty( $event_id ) ) {
			return new WP_Error( 'ep_missing_event_id', esc_html__( 'An event ID is required.', 'eventprime-event-calendar-management' ), array( 'status' => 400 ) );
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $event_id ) || current_user_can( 'edit_em_event', $event_id ) ) {
			return true;
		}

		return new WP_Error( 'ep_forbidden', esc_html__( 'You are not allowed to export attendees for this event.', 'eventprime-event-calendar-management' ), array( 'status' => 403 ) );
	}

	public function can_update_timezone( $input = array() ) {
		unset( $input );

		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error( 'ep_not_logged_in', esc_html__( 'You must be logged in to update your timezone.', 'eventprime-event-calendar-management' ), array( 'status' => 401 ) );
	}

	protected function dispatch_rest_handler( $method_name, $http_method, $route, $body = array(), $params = array() ) {
		if ( ! class_exists( 'Eventprime_Rest_Api' ) ) {
			return new WP_Error( 'ep_rest_api_missing', esc_html__( 'EventPrime REST API is unavailable.', 'eventprime-event-calendar-management' ), array( 'status' => 500 ) );
		}

		$rest_api = new Eventprime_Rest_Api();
		if ( ! method_exists( $rest_api, $method_name ) ) {
			return new WP_Error( 'ep_handler_missing', esc_html__( 'The requested EventPrime handler is unavailable.', 'eventprime-event-calendar-management' ), array( 'status' => 500 ) );
		}

		$request = new WP_REST_Request( $http_method, $route );
		$request->set_body_params( $body );
		$request->set_query_params( $body );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = call_user_func( array( $rest_api, $method_name ), $request );

		return $this->normalize_response( $response );
	}

	protected function dispatch_integration_action( $action, $params = array(), $http_method = 'GET' ) {
		if ( ! class_exists( 'Eventprime_Rest_Api' ) ) {
			return new WP_Error( 'ep_rest_api_missing', esc_html__( 'EventPrime REST API is unavailable.', 'eventprime-event-calendar-management' ), array( 'status' => 500 ) );
		}

		$rest_api = new Eventprime_Rest_Api();
		$request  = new WP_REST_Request( $http_method, '/eventprime/v1/integration' );
		$params['action'] = $action;
		$request->set_query_params( $params );
		$request->set_body_params( $params );

		$response = $rest_api->handle_integration( $request );

		return $this->normalize_response( $response );
	}

	protected function normalize_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = rest_ensure_response( $response );
		$status   = $response->get_status();
		$data     = $response->get_data();

		if ( $status >= 400 ) {
			$message = esc_html__( 'The ability request failed.', 'eventprime-event-calendar-management' );
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$message = $data['message'];
			}

			return new WP_Error( 'ep_ability_execution_failed', $message, array( 'status' => $status, 'response' => $data ) );
		}

		if ( is_array( $data ) && isset( $data['status'] ) && 'error' === $data['status'] ) {
			$message = ! empty( $data['message'] ) ? $data['message'] : esc_html__( 'The ability request failed.', 'eventprime-event-calendar-management' );
			return new WP_Error( 'ep_ability_execution_failed', $message, array( 'status' => 400, 'response' => $data ) );
		}

		return is_array( $data ) ? $data : array( 'result' => $data );
	}

	protected function extract_id_from_value( $value ) {
		if ( is_object( $value ) ) {
			if ( isset( $value->ticket_id ) ) {
				return (int) $value->ticket_id;
			}
			if ( isset( $value->id ) ) {
				return (int) $value->id;
			}
			if ( isset( $value->ID ) ) {
				return (int) $value->ID;
			}
			if ( isset( $value->term_id ) ) {
				return (int) $value->term_id;
			}
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['ticket_id'] ) ) {
				return (int) $value['ticket_id'];
			}
			if ( isset( $value['id'] ) ) {
				return (int) $value['id'];
			}
			if ( isset( $value['ID'] ) ) {
				return (int) $value['ID'];
			}
			if ( isset( $value['term_id'] ) ) {
				return (int) $value['term_id'];
			}
		}

		return 0;
	}

	protected function get_read_meta() {
		return array(
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
			),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}

	protected function get_mutation_meta() {
		return array(
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
			),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
		);
	}

	protected function get_destructive_meta() {
		return array(
			'show_in_rest' => true,
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			),
		);
	}

	protected function get_collection_query_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'search'   => array(
					'type'        => 'string',
					'description' => esc_html__( 'Optional search term.', 'eventprime-event-calendar-management' ),
				),
				'page'     => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Page number.', 'eventprime-event-calendar-management' ),
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Items per page.', 'eventprime-event-calendar-management' ),
				),
			),
		);
	}

	protected function get_required_id_schema( $key, $description ) {
		return array(
			'type'       => 'object',
			'properties' => array(
				$key => array(
					'type'        => 'integer',
					'description' => $description,
				),
			),
			'required'   => array( $key ),
		);
	}

	protected function get_named_entity_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'name'        => array(
					'type'        => 'string',
					'description' => esc_html__( 'Entity name.', 'eventprime-event-calendar-management' ),
				),
				'description' => array(
					'type'        => 'string',
					'description' => esc_html__( 'Optional entity description.', 'eventprime-event-calendar-management' ),
				),
			),
			'required'             => array( 'name' ),
		);
	}

	protected function get_event_create_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'em_name'            => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event name.', 'eventprime-event-calendar-management' ),
				),
				'em_start_date_time' => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event start date and time.', 'eventprime-event-calendar-management' ),
				),
				'em_end_date_time'   => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event end date and time.', 'eventprime-event-calendar-management' ),
				),
				'em_start_date'      => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event start date.', 'eventprime-event-calendar-management' ),
				),
				'em_end_date'        => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event end date.', 'eventprime-event-calendar-management' ),
				),
				'em_start_time'      => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event start time.', 'eventprime-event-calendar-management' ),
				),
				'em_end_time'        => array(
					'type'        => 'string',
					'description' => esc_html__( 'Event end time.', 'eventprime-event-calendar-management' ),
				),
				'description'        => array(
					'type'        => 'string',
					'description' => esc_html__( 'Optional event description.', 'eventprime-event-calendar-management' ),
				),
			),
			'required'             => array( 'em_name' ),
		);
	}

	protected function get_event_update_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'            => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Event ID.', 'eventprime-event-calendar-management' ),
				),
				'em_name'       => array(
					'type'        => 'string',
					'description' => esc_html__( 'Updated event name.', 'eventprime-event-calendar-management' ),
				),
				'description'   => array(
					'type'        => 'string',
					'description' => esc_html__( 'Updated event description.', 'eventprime-event-calendar-management' ),
				),
				'em_start_date' => array(
					'type'        => 'string',
					'description' => esc_html__( 'Updated event start date.', 'eventprime-event-calendar-management' ),
				),
				'em_end_date'   => array(
					'type'        => 'string',
					'description' => esc_html__( 'Updated event end date.', 'eventprime-event-calendar-management' ),
				),
			),
			'required'             => array( 'id' ),
		);
	}

	protected function get_ticket_list_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'event_id' => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Optional event ID filter.', 'eventprime-event-calendar-management' ),
				),
			),
		);
	}

	protected function get_ticket_availability_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'event_id'  => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Optional event ID filter.', 'eventprime-event-calendar-management' ),
				),
				'ticket_id' => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Optional ticket ID to inspect.', 'eventprime-event-calendar-management' ),
				),
			),
		);
	}

	protected function get_booking_create_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'event_id'       => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Event ID.', 'eventprime-event-calendar-management' ),
				),
				'ticket_id'      => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Ticket ID.', 'eventprime-event-calendar-management' ),
				),
				'email'          => array(
					'type'        => 'string',
					'description' => esc_html__( 'Customer email.', 'eventprime-event-calendar-management' ),
				),
				'booking_status' => array(
					'type'        => 'string',
					'description' => esc_html__( 'Optional booking status.', 'eventprime-event-calendar-management' ),
				),
			),
			'required'   => array( 'event_id', 'ticket_id', 'email' ),
		);
	}

	protected function get_timezone_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'time_zone' => array(
					'type'        => 'string',
					'description' => esc_html__( 'Timezone string or offset label.', 'eventprime-event-calendar-management' ),
				),
			),
			'required'   => array( 'time_zone' ),
		);
	}

	protected function get_entity_output_schema( $entity_key, $entity_desc ) {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'    => array(
					'type'        => 'string',
					'description' => esc_html__( 'Execution status.', 'eventprime-event-calendar-management' ),
				),
				'count'     => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Affected entity count.', 'eventprime-event-calendar-management' ),
				),
				$entity_key => array(
					'type'                 => 'object',
					'description'          => $entity_desc,
					'additionalProperties' => true,
				),
			),
		);
	}

	protected function get_collection_output_schema( $entity_key, $entity_desc ) {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'    => array(
					'type'        => 'string',
					'description' => esc_html__( 'Execution status.', 'eventprime-event-calendar-management' ),
				),
				'count'     => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Returned item count.', 'eventprime-event-calendar-management' ),
				),
				$entity_key => array(
					'type'        => 'array',
					'description' => $entity_desc,
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
			),
		);
	}

	protected function get_message_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'  => array(
					'type'        => 'string',
					'description' => esc_html__( 'Execution status.', 'eventprime-event-calendar-management' ),
				),
				'message' => array(
					'type'        => 'string',
					'description' => esc_html__( 'Status message.', 'eventprime-event-calendar-management' ),
				),
			),
		);
	}

	protected function get_availability_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'          => array(
					'type'        => 'string',
					'description' => esc_html__( 'Execution status.', 'eventprime-event-calendar-management' ),
				),
				'available'       => array(
					'type'        => 'boolean',
					'description' => esc_html__( 'Whether tickets are available.', 'eventprime-event-calendar-management' ),
				),
				'available_count' => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Available quantity when known.', 'eventprime-event-calendar-management' ),
				),
				'ticket_count'    => array(
					'type'        => 'integer',
					'description' => esc_html__( 'Number of matching tickets.', 'eventprime-event-calendar-management' ),
				),
				'ticket'          => array(
					'type'                 => 'object',
					'description'          => esc_html__( 'Matching ticket data when a specific ticket was requested.', 'eventprime-event-calendar-management' ),
					'additionalProperties' => true,
				),
			),
		);
	}
}
