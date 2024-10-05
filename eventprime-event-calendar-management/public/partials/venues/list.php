<?php
/**
 * View: Venue List
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/venues/list.php
 *
 */
$ep_functions = new Eventprime_Basic_Functions;
$db_handler = new EP_DBhandler;
wp_enqueue_script(
            'ep-venues-views-js',
            plugin_dir_url( EP_PLUGIN_FILE )  . 'public/js/em-venue-frontend-custom.js',
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
        $venues_data = array();
        $settings                     = new Eventprime_Global_Settings;
        $venues_settings              = $settings->ep_get_settings( 'venues' );
       
        $venues_data['display_style'] = isset( $atts['display_style'] ) ? $atts["display_style"] : $venues_settings->venue_display_view;
        $venues_data['limit'] = isset( $atts['limit'] ) ? ( empty($atts["limit"]) || !is_numeric($atts["limit"]) ? 10 : $atts["limit"]) : ( !empty( $venues_settings->venue_limit ) ? $venues_settings->venue_limit:10 );
        $venues_data['column']        = isset( $atts['cols'] ) && is_numeric( $atts['cols'] ) ? $atts['cols'] : $venues_settings->venue_no_of_columns;
        $venues_data['cols']          = isset( $atts['cols'] ) && is_numeric( $atts['cols'] ) ? $ep_functions->ep_check_column_size( $atts['cols'] ) : $ep_functions->ep_check_column_size( $venues_settings->venue_no_of_columns );
        $venues_data['load_more']     = isset( $atts['load_more'] ) ? $atts['load_more'] : $venues_settings->venue_load_more;
        $venues_data['enable_search'] = isset( $atts['search'] ) ? $atts['search'] : $venues_settings->venue_search;
        $venues_data['featured']      = isset( $atts["featured"] ) ? $atts["featured"] : 0;
        $venues_data['popular']       = isset( $atts["popular"] ) ? $atts["popular"] : 0;
        $order                               = isset( $atts["order"] ) ? $atts["order"] : 'desc';
        $orderby                             = isset( $atts["orderby"] ) ? $atts["orderby"] : 'term_id';
        $venues_data['box_color'] = '';
        if( $venues_data['display_style'] == 'box' || $venues_data['display_style'] == 'colored_grid' ) {
            $venues_data['box_color'] = ( isset( $atts["venue_box_color"] ) && ! empty( $atts["venue_box_color"] ) ) ? $atts["venue_box_color"] : $venues_settings->venue_box_color;
            $venues_data['colorbox_start'] = 1;
        }
        
        // Set query arguments
        $paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
        $venues_data['paged'] = $paged;
        $ep_search = isset( $_GET['ep_search'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
        $pargs = array(
            'orderby'    => $orderby,
            'order'   =>$order,
            'name__like' => $ep_search,
        );

        
        // Get featured event venues
        if( $venues_data['featured'] == 1 && ( $venues_data['popular'] == 0 || $venues_data['popular'] == '' ) ){ 
            $pargs['meta_query'] = array(
                'relation' => 'AND',
                array(
                   'key'       => 'em_is_featured',
                   'value'     => 1,
                   'compare'   => '='
                )
                
            );
        }
        // Get popular event types
        if( $venues_data['popular'] == 1 && ( $venues_data['featured'] == 0 || $venues_data['featured'] == '' ) ){
            
            $pargs['orderby'] ='count';
            $pargs['order'] ='DESC';
        }
        
        
        $terms_per_page = $pargs['limit'] = $venues_data['limit'];
        
        $venues = $db_handler->ep_get_taxonomy_terms_with_pagination('em_venue', $paged, $terms_per_page, $pargs);
        unset($pargs['meta_query']);
        $venues_data['venue_count'] = $pargs['total_count'] = $venues['total_terms'];
        $venues_data['venues'] = $venues['terms'];
        $pargs['load_more'] = $venues_data['load_more'];
        $pargs['paged'] = $venues['current_page'];
        $pargs['style'] = $venues_data['display_style'];
        $pargs['featured'] = $venues_data['featured'];
        $pargs['popular'] = $venues_data['popular'];
        $pargs['cols'] = $venues_data['column'];
        $pargs['box_color'] = '';
        $pargs['ep_search'] = $ep_search;
        
       
        ob_start();
        wp_enqueue_style(
            'ep-venue-views-css',
            plugin_dir_url( EP_PLUGIN_FILE )  . 'public/css/ep-frontend-views.css',
            false, EVENTPRIME_VERSION
        );
        $args = (object)$venues_data;
?>

<div class="emagic">
    <div class="ep-event-venues-container ep-mb-5" id="ep-event-venues-container">
        <?php
        // Load performer search template
        $ep_functions->ep_get_template_part( 'venues/list/search', null, $args );
        ?>

        <?php do_action( 'ep_venues_list_before_content', $args ); ?>

        <?php
        if( isset( $args->venues ) && !empty( $args->venues ) ) {?>
            <div class="em_venues dbfl">
                <div class="ep-box-wrap ep-event-venues-<?php echo esc_attr($args->display_style);?>-container">
                    <div id="ep-event-venues-loader-section" class="ep-box-row ep-box-top ep-venue-<?php echo esc_attr($args->display_style);?>-wrap">
                        <?php
                        switch ( $args->display_style ) {
                            case 'card':
                            case 'grid': 
                                $ep_functions->ep_get_template_part( 'venues/list/views/card', null, $args );
                                break;
                            case 'box': 
                            case 'colored_grid':
                                $ep_functions->ep_get_template_part( 'venues/list/views/box', null, $args );
                                break;
                            case 'list': 
                            case 'rows': 
                                $ep_functions->ep_get_template_part( 'venues/list/views/list', null, $args );
                                break;
                            default: 
                                $ep_functions->ep_get_template_part( 'venues/list/views/card', null, $args ); // Loading card view by default
                        }?>
                    </div>
                </div>
            </div><?php
        } else{?>
            <div class="ep-alert ep-alert-warning ep-mt-3">
                <?php ( isset( $_GET['ep_search'] ) ) ? esc_html_e( 'No Venue found related to your search.', 'eventprime-event-calendar-management' ) : esc_html_e( 'Currently, there are no venue. Please check back later.', 'eventprime-event-calendar-management' ); ?>
            </div><?php
        }?>

        <?php
        // Load performer load more template
        $ep_functions->ep_load_more_html('ep-venues', (object)$pargs);
        do_action( 'ep_venues_list_after_content', $args ); 
        ?>
    </div>
</div>