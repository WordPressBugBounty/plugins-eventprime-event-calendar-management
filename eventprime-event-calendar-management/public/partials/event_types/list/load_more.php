<?php
/**
 * View: Event Types List - Load More
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/event_types/list/load_more.php
 *
 */
?>
<?php 
$max_num_pages = ceil( $args->event_types_count / $args->limit );
if( $max_num_pages > 1 && isset( $args->load_more ) && $args->load_more == 1 ) {?>
    <div class="ep-types-load-more ep-frontend-loadmore ep-box-w-100 ep-my-4 ep-text-center">
    <input type="hidden" id="ep-types-style" value="<?php echo esc_attr($args->display_style);?>"/>
        <input type="hidden" id="ep-types-limit" value="<?php echo esc_attr($args->limit);?>"/>
        <input type="hidden" id="ep-types-cols" value="<?php echo esc_attr($args->column);?>"/>
        <input type="hidden" id="ep-types-featured" value="<?php echo esc_attr($args->featured);?>"/>
        <input type="hidden" id="ep-types-popular" value="<?php echo esc_attr($args->popular);?>"/>
        <input type="hidden" id="ep-types-search" value="<?php echo esc_attr($args->enable_search);?>"/>
        <input type="hidden" id="ep-types-paged" value="<?php echo esc_attr($args->paged);?>"/>
        <input type="hidden" id="ep-types-box-color" value="<?php echo ( isset( $args->box_color ) && ! empty( $args->box_color ) ) ? esc_attr(implode( ',', $args->box_color )) : '';?>"/>
        <button data-max="<?php echo esc_attr($max_num_pages);?>" id="ep-loadmore-ep-types" class="ep-btn ep-btn-outline-primary"><span class="ep-spinner ep-spinner-border-sm ep-mr-1"></span><?php esc_html_e( 'Load more', 'eventprime-event-calendar-management' );?></button>
    </div><?php
}?>