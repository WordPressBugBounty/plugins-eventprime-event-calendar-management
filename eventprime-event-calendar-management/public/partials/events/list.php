<?php
/**
 * View: Events List
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/events/list.php
 *
 */
$ep_functions = new Eventprime_Basic_Functions;
$events_data = $ep_functions->load_event_common_options( $atts );
$event_data = (object)$events_data;
wp_enqueue_script( 'jquery-ui-datepicker' );
wp_enqueue_script( 'jquery-ui-slider' );
wp_enqueue_style(
            'em-front-jquery-ui',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/css/jquery-ui.min.css',
            false, EVENTPRIME_VERSION
);
// enqueue select2
wp_enqueue_style(
        'em-front-select2-css',
        plugin_dir_url( EP_PLUGIN_FILE ) . 'public/css/select2.min.css',
        false, EVENTPRIME_VERSION
);
wp_enqueue_script(
        'em-front-select2-js',
        plugin_dir_url( EP_PLUGIN_FILE ) . 'public/js/select2.full.min.js',
        array( 'jquery' ), EVENTPRIME_VERSION
);
// load calendar library
wp_enqueue_style(
        'ep-front-event-calendar-css',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/css/ep-calendar.min.css',
        false, EVENTPRIME_VERSION
);
wp_enqueue_script(
        'ep-front-event-moment-js',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/js/moment.min.js',
        array('jquery'), EVENTPRIME_VERSION
);
wp_enqueue_script(
        'ep-front-event-calendar-js',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/js/ep-calendar.min.js',
        array('jquery'), EVENTPRIME_VERSION
);
wp_enqueue_script(
        'ep-front-event-fulcalendar-moment-js',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/js/fullcalendar-moment.min.js',
        array('jquery'), EVENTPRIME_VERSION
);
wp_enqueue_script(
        'ep-front-event-fulcalendar-local-js',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/js/locales-all.js',
        array('jquery'), EVENTPRIME_VERSION
);
wp_enqueue_style(
        'ep-front-events-css',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/css/ep-frontend-events.css',
        false, EVENTPRIME_VERSION
);

wp_enqueue_style(
        'em-front-common-utility-css',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/css/em-front-common-utility.css',
        false, EVENTPRIME_VERSION
);

wp_enqueue_style(
        'ep-front-events-css',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/css/ep-frontend-events.css',
        false, EVENTPRIME_VERSION
);

wp_enqueue_script(
        'ep-front-events-js',
        plugin_dir_url(EP_PLUGIN_FILE) . 'public/js/ep-frontend-events.js',
        array('jquery'), EVENTPRIME_VERSION
);
// get calendar events
$cal_events = array();

//if (!empty($events_data['events']->posts)) {
//    $cal_events = $ep_functions->get_front_calendar_view_event($events_data['events']->posts);
//}
wp_localize_script(
        'ep-front-events-js',
        'em_front_event_object',
        array(
            '_nonce' => wp_create_nonce('ep-frontend-nonce'),
            'ajaxurl' => admin_url('admin-ajax.php', null),
            'filter_applied_text' => esc_html__('Filter Applied', 'eventprime-event-calendar-management'),
            'filters_applied_text' => esc_html__('Filters Applied', 'eventprime-event-calendar-management'),
            'nonce_error' => esc_html__('Please refresh the page and try again.', 'eventprime-event-calendar-management'),
            'event_attributes' => $atts,
            'start_of_week' => get_option('start_of_week'),
            'cal_events' => $cal_events,
            'view' => $events_data['display_style'],
            'local' => $ep_functions->ep_get_calendar_locale(),
            'event_types' => $ep_functions->ep_global_settings_button_title('Event-Types'),
            'performers' => $ep_functions->ep_global_settings_button_title('Performers'),
            'venues' => $ep_functions->ep_global_settings_button_title('Venues'),
            'organizers' => $ep_functions->ep_global_settings_button_title('Organizers'),
            'list_week_btn_text' => esc_html__('Agenda', 'eventprime-event-calendar-management'),
            'hide_time_on_front_calendar' => $ep_functions->ep_get_global_settings( 'hide_time_on_front_calendar' ),
        )
);

// localized global settings
$global_settings = $ep_functions->ep_get_global_settings();
$currency_symbol = $ep_functions->ep_currency_symbol();
$datepicker_format = $ep_functions->ep_get_datepicker_format( 2 );
wp_localize_script(
        'ep-front-events-js',
        'eventprime',
        array(
            'global_settings' => $global_settings,
            'currency_symbol' => $currency_symbol,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'trans_obj' => $ep_functions->ep_define_common_field_errors(),
            'datepicker_format'    => $datepicker_format
        )
);

// event masonry view library
wp_enqueue_script('masonry');
wp_enqueue_style('masonry');
// event slide view library
wp_enqueue_style('ep-responsive-slides-css');
wp_enqueue_script('ep-responsive-slides-js');
$event_data->event_atts = $atts;
?>

<div class="emagic">
    <?php do_action( 'ep_events_list_before_render_content', $event_data ); ?>

    <div class="ep-events-container ep-box-wrap ep-<?php echo esc_attr($event_data->display_style );?>-view ep-my-4" id="ep-events-container">
        <?php
        if( $event_data->show_event_filter == 1 ) {
            // Load event search template
            $ep_functions->ep_get_template_part( 'events/list/search', null, $event_data );
        }?>
        
        <?php do_action( 'ep_events_list_before_content', $event_data ); ?>

        <div id="ep-events-content-container" class="ep-mt-4"><?php
            if( isset( $event_data->events ) && !empty( $event_data->events ) ) {?>
                <div class="ep-events ep-box-row ep-event-list-<?php echo esc_attr( $event_data->display_style );?>-container <?php if( $event_data->display_style == 'masonry' ) { echo esc_attr( 'masonry-entry' ); } ?> ep_events_front_views_<?php echo esc_attr( $event_data->display_style);?>_<?php echo esc_attr( $event_data->section_id);?>" id="ep_events_front_views_<?php echo esc_attr( $event_data->display_style);?>" data-section_id="<?php echo esc_attr( $event_data->section_id);?>">
                    <?php
                    switch ( $event_data->display_style ) {
                        case 'card': 
                        case 'square_grid': 
                            $ep_functions->ep_get_template_part( 'events/list/views/card', null, $event_data );
                            break;
                        case 'list':
                        case 'rows':    
                            $ep_functions->ep_get_template_part( 'events/list/views/list', null, $event_data );
                            break;
                        case 'masonry': 
                        case 'staggered_grid': 
                            $ep_functions->ep_get_template_part( 'events/list/views/masonry', null, $event_data );
                            break;
                        case 'slider': 
                            $ep_functions->ep_get_template_part( 'events/list/views/slider', null, $event_data );
                            break;
                        default: 
                            //echo 'test';die;
                            $ep_functions->ep_get_template_part( 'events/list/views/calendar', null, $event_data );
                    }?>
                </div><?php
            } else{?>
                <div class="ep-alert ep-alert-warning ep-mt-3">
                    <?php ( isset( $_GET['ep_search'] ) ) ? esc_html_e( 'No event found related to your search.', 'eventprime-event-calendar-management' ) : esc_html_e( 'Currently, there are no events planned. Please check back later.', 'eventprime-event-calendar-management' ); ?>
                </div><?php
            }?>
            <?php
            // Load event load more template
            if(!isset($args->display_style) || (isset($args->display_style) && $args->display_style!=='slider'))
            {
                $ep_functions->ep_get_template_part( 'events/list/load_more', null, $event_data );
            }
            ?>
            <?php do_action( 'ep_event_filter_arguments_content', $event_data->atts,$event_data->params ); ?>
        </div>
        <?php do_action( 'ep_events_list_after_content', $event_data ); ?>
    </div>
</div>