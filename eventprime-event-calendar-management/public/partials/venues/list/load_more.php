<?php
/**
 * View: Venues List - Load More
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/venues/list/load_more.php
 *
 */
$ep_functions = new Eventprime_Basic_Functions;
?>
<?php 
$max_num_pages = ceil( $args->venue_count / $args->limit );
if( $max_num_pages > 1 && isset( $args->load_more ) && $args->load_more == 1 ) {?>
    <div class="ep-venues-load-more ep-frontend-loadmore ep-box-w-100 ep-my-4 ep-text-center">
        <input type="hidden" id="ep-venues-style" value="<?php echo esc_attr($args->display_style);?>"/>
        <input type="hidden" id="ep-venues-limit" value="<?php echo esc_attr($args->limit);?>"/>
        <input type="hidden" id="ep-venues-cols" value="<?php echo esc_attr($args->column);?>"/>
        <input type="hidden" id="ep-venues-featured" value="<?php echo esc_attr($args->featured);?>"/>
        <input type="hidden" id="ep-venues-popular" value="<?php echo esc_attr($args->popular);?>"/>
        <input type="hidden" id="ep-venues-search" value="<?php echo esc_attr($args->enable_search);?>"/>
        <input type="hidden" id="ep-venues-paged" value="<?php echo esc_attr($args->paged);?>"/>
        <input type="hidden" id="ep-venues-box-color" value="<?php echo ( isset( $args->box_color ) && ! empty( $args->box_color ) ) ? esc_attr(implode( ',', $args->box_color )) : '';?>"/>
        <button data-max="<?php echo esc_attr($max_num_pages);?>" id="ep-loadmore-event-venues" class="ep-btn ep-btn-outline-primary"><span class="ep-spinner ep-spinner-border-sm ep-mr-1"></span><?php esc_html_e( 'Load more', 'eventprime-event-calendar-management' );?></button>
    </div><?php
}?>