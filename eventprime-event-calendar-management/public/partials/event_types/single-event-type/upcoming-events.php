<?php
/**
 * View: Single Performer - Upcoming Events
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/performers/single-performer/upcoming-events.php
 *
 */
defined( 'ABSPATH' ) || exit;
$ep_functions = new Eventprime_Basic_Functions;
?>
<div class="ep-box-col-12 event-<?php echo esc_attr($args->event_args['event_style']);?>-view">
    <div class="ep-row-heading ep-text-center ep-my-4">
        <div class="ep-upcoming-title ep-fw-bold ep-fs-5 ep-mt-5 ep-d-flex ep-justify-content-center">
            <?php 
            $single_type_event_section_title =  !empty( $ep_functions->ep_get_global_settings( 'single_type_event_section_title' ) ) ? $ep_functions->ep_get_global_settings( 'single_type_event_section_title' ) : esc_html__("Upcoming Events", "eventprime-event-calendar-management"); 
            echo wp_kses_post( $single_type_event_section_title ); 
            ?>
            <span class="em_events_count-wrap em_bg"></span>
        </div>
    </div>
    <div id="ep-upcoming-events" class="em_content_area ep-upcoming-events">
        <div class="event-details-upcoming-<?php echo esc_attr($args->event_args['event_style']);?>-view">
        <?php if( isset( $args->events->posts ) && ! empty( $args->events->posts ) && count( $args->events->posts ) > 0 ) {
            ?>
            <div class="ep-box-row" id="ep-eventtype-upcoming-events"><?php
            switch ( $args->event_args['event_style'] ) {
                case 'card':
                case 'grid':
                    $ep_functions->ep_get_template_part( 'events/upcoming-events/views/card', null, $args );
                    break;
                case 'mini-list':
                case 'plain_list':
                    $ep_functions->ep_get_template_part( 'events/upcoming-events/views/mini-list', null, $args );
                    break;
                case 'list':
                case 'rows': 
                    $ep_functions->ep_get_template_part( 'events/upcoming-events/views/list', null, $args );
                    break;
                default: 
                    $ep_functions->ep_get_template_part( 'events/upcoming-events/views/mini-list', null, $args );
            }?>
            </div>
        <?php
        } else{?>
            <div class="ep-alert ep-alert-warning ep-mt-3 ep-py-2">
                <?php esc_html_e( 'No upcoming event found.', 'eventprime-event-calendar-management' ); ?>
            </div><?php
        }?>
        <?php
        // Load event load more template
        $ep_functions->ep_get_template_part( 'event_types/single-event-type/load_more', null, $args );
        ?>
      </div>  
    </div>
</div>
