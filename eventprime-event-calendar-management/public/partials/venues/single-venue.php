<?php
/**
 * View: Single Venue
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/venues/single-venue.php
 *
 */
defined( 'ABSPATH' ) || exit;
$ep_functions = new Eventprime_Basic_Functions;
wp_enqueue_style( 'ep-responsive-slides-css' );
        wp_enqueue_script( 'ep-responsive-slides-js' );

        wp_register_script( 'em-google-map', plugin_dir_url( EP_PLUGIN_FILE ) . 'public/js/em-map.js', array( 'jquery' ), EVENTPRIME_VERSION );
        $gmap_api_key = $ep_functions->ep_get_global_settings( 'gmap_api_key' );
        if( $gmap_api_key ) {
            wp_enqueue_script(
                'google_map_key', 
                'https://maps.googleapis.com/maps/api/js?key='.$gmap_api_key.'&libraries=places,marker,drawing,geometry&callback=Function.prototype&loading=async', 
                array(), EVENTPRIME_VERSION
            );
        }

        wp_enqueue_script(
            'ep-venues-views-js',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/js/em-venue-frontend-custom.js',
            array( 'jquery' ), EVENTPRIME_VERSION
        );
        wp_localize_script(
            'ep-venues-views-js', 
            'ep_frontend', 
            array(
                '_nonce' => wp_create_nonce('ep-frontend-nonce'),
                'ajaxurl'   => admin_url( 'admin-ajax.php' )
            )
        );
        
        wp_localize_script(
            'ep-venues-views-js', 
            'eventprime', 
            array(
                'global_settings' => $ep_functions->ep_get_global_settings('gmap_api_key'),
            )
        );

        $atts                 = array_change_key_case( (array) $atts, CASE_LOWER );
        if ( !isset( $atts['id'] ) || empty( $atts['id'] ) ) { ?>
            <div class="ep-alert ep-alert-warning ep-mt-3">
                <?php echo esc_html_e( 'Please specify the Venue ID in the Shortcode.', 'eventprime-event-calendar-management' ); ?>
            </div><?php
            return; 
        }
        $venue_id             = strval($atts['id']);
        $term                 = get_term( $venue_id );
        $venues_data          = array();
        if( ! empty( $term ) ) {
            $venues_data['term']  = $term;
            $venues_data['venue'] = $ep_functions->get_single_venue( $term->term_id );
            // upcoming events
            $venues_data['hide_upcoming_events'] = $ep_functions->ep_get_global_settings( 'shortcode_hide_upcoming_events' );
            if ( isset( $atts['upcoming'] ) ) {
                $venues_data['hide_upcoming_events'] = 1;
                if ( 1 === $atts['upcoming'] ) {
                    $venues_data['hide_upcoming_events'] = 0;
                }
            }
            // check event limit
            if( isset( $atts['event_limit'] ) ){
                $single_venue_event_limit = ( $atts["event_limit"] == 0 || $atts["event_limit"] == '' ) ? 10 : $atts["event_limit"];
            } else{
                $single_venue_event_limit = ( $ep_functions->ep_get_global_settings( 'single_venue_event_limit' ) == 0 ) ? 10 : $ep_functions->ep_get_global_settings( 'single_venue_event_limit');
            }
            // check hide past events
            if( isset( $atts['hide_past_events'] ) ){
                $hide_past_events = $atts['hide_past_events'];
            } else{
                $hide_past_events = $ep_functions->ep_get_global_settings( 'single_venue_hide_past_events' );
            }
            // get upcoming events for venue
            $paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
            $args = array(
                'orderby'        => 'em_start_date_time',
                'posts_per_page' => $single_venue_event_limit,
                'offset'         => (int)( $paged - 1 ) * (int)$single_venue_event_limit,
                'paged'          => $paged,
                'hide_past_events'=> $hide_past_events
            );
            $args['post_status'] = !empty( $hide_past_events ) == 1 ? 'publish' : 'any';

            $venues_data['events'] = $ep_functions->get_upcoming_events_for_venue( $venue_id, $args );

            $event_args  = array();
            $event_args['show_events']      = ( isset( $atts['show_events'] ) ? $atts['show_events'] : $ep_functions->ep_get_global_settings( 'single_venue_show_events' ) );
            $event_args['event_style']      = ( isset( $atts['event_style'] ) ? $atts['event_style'] : $ep_functions->ep_get_global_settings( 'single_venue_event_display_view' ) );
            $event_args['event_limit']      = $single_venue_event_limit;
            $event_args['event_cols']       = ( isset( $atts['event_cols'] ) ? $ep_functions->ep_check_column_size( $atts['event_cols'] ) : $ep_functions->ep_check_column_size( $ep_functions->ep_get_global_settings( 'single_venue_event_column' ) ) );
            $event_args['load_more']        = ( isset( $atts['load_more'] ) ? $atts['load_more'] : $ep_functions->ep_get_global_settings( 'single_venue_event_load_more' ) );
            $event_args['hide_past_events'] = $hide_past_events;
            $event_args['paged']            = $paged;
            $venues_data['event_args']      = $event_args;
            $venues_data['venue_id']        = $venue_id;
        }
        ob_start();
        wp_enqueue_style(
            'ep-performer-views-css',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/css/ep-frontend-views.css',
            false, EVENTPRIME_VERSION
        );
        $args = (object)$venues_data;
?>
<div class="emagic">
    <?php if( ! empty( $args ) && ! empty( $args->term ) ) {?>
        <div class="ep-single-frontend-view-container ep-mb-5" id="ep_single_frontend_view_container">
            <div class="ep-view-container">

                <?php do_action( 'ep_before_venues_contant');?>

                <!-- box wrapper -->
                <div class="ep-box-wrap ep-details-info-wrap">
                    <div class="ep-box-row">
                        <?php
                        // Load single venue image template
                        $ep_functions->ep_get_template_part( 'venues/single-venue/image', null, $args );
                        ?>
                        <?php
                        // Load single venue image template
                        $ep_functions->ep_get_template_part( 'venues/single-venue/detail', null, $args );
                        ?>
                    </div>
                </div>

                <?php do_action( 'ep_after_venues_contant');?>
                <?php
                if( $args->event_args['show_events'] == 1 ) {
                    // Load upcoming event template
                    $ep_functions->ep_get_template_part( 'venues/single-venue/upcoming-events', null, $args );
                }?>
            </div>
        </div><?php
    } else{?>
        <div class="ep-alert ep-alert-warning ep-mt-3">
            <?php echo esc_html_e( 'No venue found.', 'eventprime-event-calendar-management' ); ?>
        </div><?php
    }?>
</div>