<?php
/**
 * View: Single Performer
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/performers/single-performer.php
 *
 */
defined( 'ABSPATH' ) || exit;
$ep_functions = new Eventprime_Basic_Functions;
wp_enqueue_style( 'ep-responsive-slides-css' );
        wp_enqueue_script( 'ep-responsive-slides-js' );
        wp_enqueue_script(
            'ep-performer-views-js',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/js/em-performer-frontend-custom.js',
            array( 'jquery' ), EVENTPRIME_VERSION
        );
        wp_localize_script(
            'ep-performer-views-js', 
            'ep_frontend', 
            array(
                '_nonce' => wp_create_nonce('ep-frontend-nonce'),
                'ajaxurl'   => admin_url( 'admin-ajax.php' )
            )
        );
        $atts                    = array_change_key_case( (array) $atts, CASE_LOWER );
        if ( !isset( $atts['id'] ) || empty( $atts['id'] ) ) { ?>
            <div class="ep-alert ep-alert-warning ep-mt-3">
                <?php echo esc_html_e( 'Please specify the Performer ID in the Shortcode.', 'eventprime-event-calendar-management' ); ?>
            </div><?php
            return; 
        }
        $performer_id            = absint( $atts['id'] );
        $post                    = get_post( $performer_id );
        $performers_data         = array();
        if( ! empty( $post ) ) {
            $performers_data['post'] = $post;
            $performers_data['performer'] = $ep_functions->get_single_performer( $post->ID );
            // upcoming events
            $performers_data['hide_upcoming_events'] = $ep_functions->ep_get_global_settings( 'shortcode_hide_upcoming_events' ); // Incorrect context!!!
            if ( isset( $atts['upcoming'] ) ) {
                $performers_data['hide_upcoming_events'] = 1;
                if ( 1 === $atts['upcoming'] ) {
                    $performers_data['hide_upcoming_events'] = 0;
                }
            }
            // check event limit
            if( isset( $atts['event_limit'] ) ){
                $single_performer_event_limit = ( $atts["event_limit"] == 0 || $atts["event_limit"] == '' ) ? 10 : $atts["event_limit"];
            } else{
                $single_performer_event_limit = ( $ep_functions->ep_get_global_settings( 'single_performer_event_limit' ) == 0 ) ? 10 : $ep_functions->ep_get_global_settings( 'single_performer_event_limit');
            }
            // check hide past events
            if( isset( $atts['hide_past_events'] ) ){
                $hide_past_events = $atts['hide_past_events'];
            } else{
                $hide_past_events = $ep_functions->ep_get_global_settings( 'single_performer_hide_past_events' );
            }
            // get events
            $paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
            // get upcoming events for performer
            $args = array(
                'orderby'        => 'em_start_date_time',
                'posts_per_page' => $single_performer_event_limit,
                'offset'         => (int) ( $paged - 1 ) * (int)$single_performer_event_limit,
                'paged'          => $paged,
                'hide_past_events'=> $hide_past_events,
            );
            
            $args['post_status'] = !empty( $hide_past_events ) == 1 ? 'publish' : 'any';
            $performers_data['events'] = $ep_functions->get_upcoming_events_for_performer( $post->ID , $args);
            
            $event_args  = array();
            $event_args['show_events']      = ( isset( $atts['show_events'] ) ? $atts['show_events'] : $ep_functions->ep_get_global_settings( 'single_performer_show_events' ) );
            $event_args['event_style']      = ( isset( $atts['event_style'] ) ? $atts['event_style'] : $ep_functions->ep_get_global_settings( 'single_performer_event_display_view' ) );
            $event_args['event_limit']      = $single_performer_event_limit;
            $event_args['event_cols']       = ( isset( $atts['event_cols'] ) ? $ep_functions->ep_check_column_size( $atts['event_cols'] ) : $ep_functions->ep_check_column_size( $ep_functions->ep_get_global_settings( 'single_performer_event_column' ) ) );
            $event_args['load_more']        = ( isset( $atts['load_more'] ) ? $atts['load_more'] : $ep_functions->ep_get_global_settings( 'single_performer_event_load_more' ) );
            $event_args['hide_past_events'] = $hide_past_events;
            $event_args['paged']            = $paged;
            $performers_data['event_args']  = $event_args;
        }
        
        ob_start();
        wp_enqueue_style(
            'ep-performer-views-css',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/css/ep-frontend-views.css',
            false, EVENTPRIME_VERSION
        );
        $args = (object)$performers_data;
if( post_password_required( $args->post->ID ) ){
    // if performers are password protected
    echo get_the_password_form();
}else{
?>
<div class="emagic">
    <?php if( ! empty( $args ) && ! empty( $args->post ) ) {?>
        <div class="ep-single-frontend-view-container ep-mb-5" id="ep_single_frontend_view_container">
            <div class="ep-box-wrap ep-view-container">

                <?php do_action( 'ep_before_performers_contant');?>

                <!-- box wrapper -->
                <div class="ep-details-info-wrap">
                    <div class="ep-box-row">
                        <?php
                        // Load single performer image template
                        $ep_functions->ep_get_template_part( 'performers/single-performer/image', null, $args );
                        ?>
                        <?php
                        // Load single performer image template
                        $ep_functions->ep_get_template_part( 'performers/single-performer/detail', null, $args );
                        ?>
                    </div>
                </div>

                <?php do_action( 'ep_after_performers_contant');?>

                <?php
                if( $args->event_args['show_events'] == 1 ) {
                    // Load upcoming event template
                    $ep_functions->ep_get_template_part( 'performers/single-performer/upcoming-events', null, $args );
                }?>
            </div>
        </div><?php
    } else{?>
        <div class="ep-alert ep-alert-warning ep-mt-3">
            <?php echo esc_html_e( 'No performer found.', 'eventprime-event-calendar-management' ); ?>
        </div><?php
    }?>
</div>
<?php } ?>