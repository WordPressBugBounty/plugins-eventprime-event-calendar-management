<?php
/**
 * View: Single Organizer
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/organizers/single-ep-event-organizer.php
 *
 */
defined( 'ABSPATH' ) || exit;

get_header(); ?>
	
    <section id="<?php echo esc_attr(apply_filters('ep_event_organizer_page_html_id', 'main-content')); ?>" class="<?php echo esc_attr(apply_filters('ep_event_organizer_page_html_class', 'ep-container')); ?>">

        <?php do_action('ep_before_main_content'); ?>

        <?php do_action('ep_before_events_loop'); ?>

        <?php
            $term_obj = get_queried_object();
            $atts = array();
            $atts['id'] = $term_obj->term_id;
            $public = new Eventprime_Event_Calendar_Management_Public('eventprime-event-calendar-management', EVENTPRIME_VERSION);
            echo wp_kses_post( $public->load_single_event_organizer($atts) );
            ?>

        <?php do_action('ep_after_events_loop'); ?>

    </section>

    <?php do_action('ep_after_main_content'); ?>

<?php get_footer();