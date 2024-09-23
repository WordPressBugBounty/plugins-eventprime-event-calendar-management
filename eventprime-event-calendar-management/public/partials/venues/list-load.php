<?php
/**
 * View: Venue List
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/venues/list.php
 *
 */
$ep_function = new Eventprime_Basic_Functions;
?>

<?php
if( isset( $args->venues ) && !empty( $args->venues ) ) {?>
    <?php
    switch ( $args->display_style ) {
        case 'card':
        case 'grid': 
            $ep_function-> ep_get_template_part( 'venues/list/views/card', null, $args );
            break;
        case 'box': 
        case 'colored_grid':
            $ep_function->ep_get_template_part( 'venues/list/views/box', null, $args );
            break;
        case 'list':
        case 'rows': 
            $ep_function->ep_get_template_part( 'venues/list/views/list', null, $args );
            break;
        default: 
            $ep_function->ep_get_template_part( 'venues/list/views/card', null, $args ); // Loading card view by default
    }
} else{?>
    <div class="ep-alert-warning ep-alert-info">
        <?php ( isset( $_GET['ep_search'] ) ) ? esc_html_e( 'No Venue found related to your search.', 'eventprime-event-calendar-management' ) : esc_html_e( 'No Venue found.', 'eventprime-event-calendar-management' ); ?>
    </div><?php
}?>