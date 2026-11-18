<?php
/**
 * Capacity section of the event editor Tickets tab (#2012, summary card on top).
 *
 * Included by meta-box-tickets-panel-html.php inside #ep-event-tickets-options, between the two "add" buttons
 * and the ticket list. The input keeps the name `em_event_overall_capacity`, the id `ep_event_overall_capacity`
 * and `data-booked` (the save contract). The live line, the allocation bar, the notes and the per-ticket
 * sold/left lines are written by admin/js/em-admin-metabox-custom.js from the ticket rows on the page; this file
 * renders the same first state on the server from one capacity state.
 *
 * Expects: $post (WP_Post). Sets $ep_overall_capacity (int, 0 = none) for the panel template.
 *
 * @package EventPrime
 * @since   4.4.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables, local to the including method.

$ep_overall_capacity = function_exists( 'ep_get_event_overall_capacity' ) ? absint( ep_get_event_overall_capacity( $post->ID ) ) : 0;
$ep_oc_pool          = $ep_overall_capacity;
$ep_oc_state         = array();
if ( 'auto-draft' !== $post->post_status && function_exists( 'ep_get_event_capacity_state' ) ) {
	$ep_oc_state = ep_get_event_capacity_state(
		$post->ID,
		array(
			'context' => 'admin',
			'fresh'   => true,
		)
	);
}
$ep_oc_booked    = ! empty( $ep_oc_state['sold'] ) ? absint( $ep_oc_state['sold'] ) : 0;
$ep_oc_left      = ! empty( $ep_oc_state['remaining'] ) ? absint( $ep_oc_state['remaining'] ) : 0;
$ep_oc_sum       = ! empty( $ep_oc_state['capacity_sum'] ) ? absint( $ep_oc_state['capacity_sum'] ) : 0;
$ep_oc_has_types = ! empty( $ep_oc_state['tickets'] );
$ep_oc_seats     = ( isset( $ep_oc_state['inventory'] ) && 'seats' === $ep_oc_state['inventory'] );
$ep_oc_sold_map  = array();
if ( $ep_oc_has_types ) {
	foreach ( $ep_oc_state['tickets'] as $ep_oc_ticket_id => $ep_oc_ticket_state ) {
		$ep_oc_sold_map[ absint( $ep_oc_ticket_id ) ] = isset( $ep_oc_ticket_state['sold'] ) ? absint( $ep_oc_ticket_state['sold'] ) : 0;
	}
}

// First state of the live line (the script keeps it current while the admin edits).
if ( ! $ep_oc_has_types && $ep_oc_pool > 0 ) {
	/* translators: %d: overall event capacity. */
	$ep_oc_live = sprintf( __( '%d total. Add a ticket type to start selling.', 'eventprime-event-calendar-management' ), $ep_oc_pool );
} elseif ( ! $ep_oc_has_types ) {
	$ep_oc_live = __( 'No event total and no ticket types yet. Add a ticket type to start selling.', 'eventprime-event-calendar-management' );
} elseif ( $ep_oc_pool > 0 ) {
	if ( $ep_oc_left < 1 ) {
		/* translators: 1: overall event capacity, 2: tickets sold, 3: tickets left. */
		$ep_oc_live = sprintf( __( '%1$d total, %2$d sold, %3$d left. Sold out.', 'eventprime-event-calendar-management' ), $ep_oc_pool, $ep_oc_booked, $ep_oc_left );
	} else {
		/* translators: 1: overall event capacity, 2: tickets sold, 3: tickets left. */
		$ep_oc_live = sprintf( __( '%1$d total, %2$d sold, %3$d left.', 'eventprime-event-calendar-management' ), $ep_oc_pool, $ep_oc_booked, $ep_oc_left );
	}
} else {
	/* translators: 1: most tickets all ticket types can sell, 2: tickets sold, 3: tickets left. */
	$ep_oc_live = sprintf( __( 'No event total. Ticket types can sell up to %1$d in total: %2$d sold, %3$d left.', 'eventprime-event-calendar-management' ), $ep_oc_sum, $ep_oc_booked, $ep_oc_left );
}

if ( $ep_oc_seats ) {
	$ep_oc_help = __( 'Optional. Limits the total number of seats sold across all ticket types.', 'eventprime-event-calendar-management' );
} elseif ( $ep_oc_pool > 0 ) {
	$ep_oc_help = __( 'Total tickets across all ticket types. Each ticket type\'s quantity is a limit for that type only.', 'eventprime-event-calendar-management' );
} else {
	$ep_oc_help = __( 'Leave blank for no overall limit. Each ticket type then sells up to its own Available Quantity, exactly as before.', 'eventprime-event-calendar-management' );
}

// Live Seating note (inventory = seats), exact wording of the extension report section B5.
$ep_oc_seat_lines = array();
if ( $ep_oc_seats ) {
	$ep_oc_seat_lines[] = __( 'This event uses seat selection. Each seat is one place and the seats you assign to ticket types set the ticket quantities.', 'eventprime-event-calendar-management' );
	if ( $ep_oc_pool > 0 && $ep_oc_sum > 0 && $ep_oc_pool < $ep_oc_sum ) {
		/* translators: 1: overall event capacity, 2: seats assigned to ticket types. */
		$ep_oc_seat_lines[] = sprintf( __( 'Only %1$d of the %2$d seats can be sold. Once %1$d places are booked the event shows as sold out, even if seats remain free on the plan.', 'eventprime-event-calendar-management' ), $ep_oc_pool, $ep_oc_sum );
	} elseif ( $ep_oc_pool > 0 && $ep_oc_sum > 0 && $ep_oc_pool > $ep_oc_sum ) {
		/* translators: 1: overall event capacity, 2: seats assigned to ticket types. */
		$ep_oc_seat_lines[] = sprintf( __( 'The overall capacity (%1$d) is higher than the %2$d seats assigned to ticket types, so the seats are the limit. Raise the seats in the seat plan to sell more.', 'eventprime-event-calendar-management' ), $ep_oc_pool, $ep_oc_sum );
	}
}
?>
<!-- ep-capacity:start -->
<div class="ep-box-col-12 ep-capacity-wrap" id="ep_event_capacity_section">
	<section class="ep-capacity-card" id="ep_event_overall_capacity_row" aria-labelledby="ep_capacity_heading" data-ep-capacity-inventory="<?php echo esc_attr( $ep_oc_seats ? 'seats' : 'tickets' ); ?>" data-ep-capacity-seats="<?php echo esc_attr( $ep_oc_sum ); ?>" data-ep-capacity-sold="<?php echo esc_attr( $ep_oc_booked ); ?>" data-ep-capacity-sold-map="<?php echo esc_attr( wp_json_encode( (object) $ep_oc_sold_map ) ); ?>">
		<h3 class="ep-capacity-heading" id="ep_capacity_heading"><?php esc_html_e( 'Event capacity', 'eventprime-event-calendar-management' ); ?></h3>
		<div class="ep-capacity-grid">
			<div class="ep-capacity-field">
				<label class="ep-form-label ep-capacity-label" for="ep_event_overall_capacity"><?php esc_html_e( 'Total event capacity', 'eventprime-event-calendar-management' ); ?> <span class="ep-capacity-opt"><?php esc_html_e( '(optional)', 'eventprime-event-calendar-management' ); ?></span></label>
				<input type="number" min="0" step="1" id="ep_event_overall_capacity" name="em_event_overall_capacity" value="<?php echo esc_attr( $ep_oc_pool > 0 ? $ep_oc_pool : '' ); ?>" class="ep-form-control ep-capacity-input" placeholder="<?php esc_attr_e( 'No limit', 'eventprime-event-calendar-management' ); ?>" data-booked="<?php echo esc_attr( $ep_oc_booked ); ?>" aria-describedby="ep_event_overall_capacity_help ep_event_overall_capacity_rule ep_event_overall_capacity_error">
				<div class="ep-error-message" id="ep_event_overall_capacity_error" role="alert"></div>
				<p class="ep-capacity-help" id="ep_event_overall_capacity_help"><?php echo esc_html( $ep_oc_help ); ?></p>
				<p class="ep-capacity-rule" id="ep_event_overall_capacity_rule"<?php echo $ep_oc_pool > 0 ? '' : ' hidden'; ?>><?php esc_html_e( 'The event total always wins over a ticket limit.', 'eventprime-event-calendar-management' ); ?></p>
			</div>
			<div class="ep-capacity-summary">
				<p class="ep-capacity-live" id="ep_capacity_live" aria-live="polite"><?php echo esc_html( $ep_oc_live ); ?></p>
				<div class="ep-capacity-barwrap" id="ep_capacity_bar"></div>
				<p class="ep-capacity-count-note"><?php esc_html_e( 'Counts completed and pending bookings. Cancelled, refunded and failed bookings free their tickets.', 'eventprime-event-calendar-management' ); ?></p>
			</div>
		</div>
		<div class="ep-capacity-seatnote" id="ep_capacity_seat_notes"<?php echo $ep_oc_seats ? '' : ' hidden'; ?>>
			<?php foreach ( $ep_oc_seat_lines as $ep_oc_seat_line ) : ?>
				<p><?php echo esc_html( $ep_oc_seat_line ); ?></p>
			<?php endforeach; ?>
		</div>
		<ul class="ep-capacity-notes" id="ep_capacity_notes" aria-live="polite"></ul>
	</section>
	<h4 class="ep-capacity-list-heading"><?php esc_html_e( 'Ticket types', 'eventprime-event-calendar-management' ); ?></h4>
	<p class="ep-capacity-empty" id="ep_capacity_empty"<?php echo $ep_oc_has_types ? ' hidden' : ''; ?>><?php esc_html_e( 'No ticket types yet. Use "Add Ticket Type" above to create the first one.', 'eventprime-event-calendar-management' ); ?></p>
</div>
<!-- ep-capacity:end -->
