<?php
/**
 * View: Organizers List
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/organizers/list.php
 *
 */
$db_handler = new EP_DBhandler;
$ep_functions = new Eventprime_Basic_Functions;
wp_enqueue_script(
            'ep-organizer-views-js',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/js/em-organizer-frontend-custom.js',
            array( 'jquery' ), EVENTPRIME_VERSION
        );
        wp_localize_script(
            'ep-organizer-views-js', 
            'ep_frontend', 
            array(
                '_nonce' => wp_create_nonce('ep-frontend-nonce'),
                'ajaxurl'   => admin_url( 'admin-ajax.php' ),
                'organizer_no_of_columns' => $ep_functions->ep_get_global_settings('organizer_no_of_columns')
            )
        );
        $organizers_data = array();
        $settings                           = new Eventprime_Global_Settings;
        $organizers_settings                = $settings->ep_get_settings( 'organizers' );

        $organizers_data['display_style']   = isset( $atts['display_style'] ) ? $atts["display_style"] : $organizers_settings->organizer_display_view;
        $organizers_data['limit']           = isset( $atts['limit'] ) ? ( empty($atts["limit"]) || !is_numeric($atts["limit"]) ? 10 : $atts["limit"]) : (empty($organizers_settings->organizer_limit) ? 10 : $organizers_settings->organizer_limit );
        $organizers_data['column']            = isset( $atts['cols'] ) && is_numeric($atts['cols']) ? $atts['cols']  : $organizers_settings->organizer_no_of_columns;
        $organizers_data['cols']            = isset( $atts['cols'] ) && is_numeric($atts['cols']) ? $ep_functions->ep_check_column_size( $atts['cols'] ) : $ep_functions->ep_check_column_size( $organizers_settings->organizer_no_of_columns );
        $organizers_data['load_more']       = isset( $atts['load_more'] ) ? $atts['load_more'] : $organizers_settings->organizer_load_more;
        $organizers_data['enable_search']   = isset( $atts['search'] ) ? $atts['search'] : $organizers_settings->organizer_search;
        $organizers_data['featured']        = isset( $atts["featured"] ) ? $atts["featured"] : 0;
        $organizers_data['popular']         = isset( $atts["popular"] ) ? $atts["popular"] : 0;
        $organizers_data['box_color'] = '';
        $order                               = isset( $atts["order"] ) ? $atts["order"] : 'desc';
        $orderby                             = isset( $atts["orderby"] ) ? $atts["orderby"] : 'term_id';
        $organizers_data['box_color'] = '';
        if( $organizers_data['display_style'] == 'box' || $organizers_data['display_style'] == 'colored_grid' ) {
            $organizers_data['box_color'] = ( isset( $atts["organizer_box_color"] ) && ! empty( $atts["organizer_box_color"] ) ) ? $atts["organizer_box_color"] : $organizers_settings->organizer_box_color;
            $organizers_data['colorbox_start'] = 1;
        }
       

        // Set query arguments
        $paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
        $organizers_data['paged'] = $paged;
        $ep_search = isset( $_GET['ep_search'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
        
        $pargs = array(
            'orderby'    => $orderby,
            'order'   =>$order,
            'name__like' => $ep_search,
        );

        if ( $organizers_data['featured'] == 1 && ( $organizers_data['popular'] == 1 ) ) {
            $pargs['meta_query'] = array(
                'relation' => 'AND',
                array(
                   'key'       => 'em_is_featured',
                   'value'     => 1,
                   'compare'   => '='
                )
            );
            $pargs['orderby'] ='count';
            $pargs['order'] ='DESC';
        }

        // get featured event organizers
        if( $organizers_data['featured'] == 1 && ( $organizers_data['popular'] == 0 || $organizers_data['popular'] == '' ) ){ 
            $pargs['meta_query'] = array(
                'relation' => 'AND',
                array(
                   'key'       => 'em_is_featured',
                   'value'     => 1,
                   'compare'   => '='
                )
            );
        }
        
        // Get popular event organizers 
        if( $organizers_data['popular'] == 1 && ( $organizers_data['featured'] == 0 || $organizers_data['featured'] == '' ) ){
            $pargs['orderby'] ='count';
            $pargs['order'] ='DESC';
        }
        
        $terms_per_page = $pargs['limit'] = $organizers_data['limit'];
        
        $organizers = $db_handler->ep_get_taxonomy_terms_with_pagination('em_event_organizer', $paged, $terms_per_page, $pargs);
        unset($pargs['meta_query']);
        $organizers_data['organizers_count'] = $pargs['total_count'] = $organizers['total_terms'];
        $organizers_data['organizers'] = $organizers['terms'];
        $pargs['load_more'] = $organizers_data['load_more'];
        $pargs['paged'] = $organizers['current_page'];
        $pargs['style'] = $organizers_data['display_style'];
        $pargs['featured'] = $organizers_data['featured'];
        $pargs['popular'] = $organizers_data['popular'];
        $pargs['cols'] = $organizers_data['column'];
        $pargs['box_color'] = '';
        $pargs['ep_search'] = $ep_search;
        
//        $organizers_data['organizers_count'] = $ep_functions->get_organizers_count( $pargs, $ep_search, $organizers_data['featured'], $organizers_data['popular'] );
//        $pargs = wp_parse_args( $pargs, $limit_args );
//        $organizers_data['organizers'] = $ep_functions->get_organizers_data( $pargs );

        ob_start();
        wp_enqueue_style(
            'ep-organizer-views-css',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/css/ep-frontend-views.css',
            false, EVENTPRIME_VERSION
        );
        $args =  (object)$organizers_data;
?>

<div class="emagic">
    <div class="ep-organizers-container ep-mb-5" id="ep-organizers-container">
        <?php
        // Load performer search template
        $ep_functions->ep_get_template_part( 'organizers/list/search', null, $args );
        ?>

        <?php do_action( 'ep_organizers_list_before_content', $args ); ?>

        <?php
        if( isset( $args->organizers ) && !empty( $args->organizers ) ) {?>
            <div class="em_organizers dbfl">
                <div class="ep-event-organizers-<?php echo esc_attr($args->display_style);?>-container ep-box-wrap">
                    <div id="ep-event-organizers-loader-section" class="ep-px-3 ep-box-row ep-box-top ep-organizer-<?php echo esc_attr($args->display_style);?>-wrap ">
                        <?php
                        switch ( $args->display_style ) {
                            case 'card':
                            case 'grid':
                                $ep_functions->ep_get_template_part( 'organizers/list/views/card', null, $args );
                                break;
                            case 'box': 
                            case 'colored_grid':
                                $ep_functions->ep_get_template_part( 'organizers/list/views/box', null, $args );
                                break;
                            case 'list': 
                            case 'rows':
                                $ep_functions->ep_get_template_part( 'organizers/list/views/list', null, $args );
                                break;
                            default: 
                                $ep_functions->ep_get_template_part( 'organizers/list/views/card', null, $args ); // Loading card view by default
                        }?>
                    </div>
                </div>
            </div><?php
        } else{?>
            <div class="ep-alert ep-alert-warning ep-mt-3 ep-fs-6">
                <?php ( isset( $_GET['ep_search'] ) ) ? esc_html_e( 'No organizers found related to your search.', 'eventprime-event-calendar-management' ) : esc_html_e( 'Currently, there are no organizer. Please check back later.', 'eventprime-event-calendar-management' ); ?>
            </div><?php
        }?>

        <?php
        // Load performer load more template
        //$ep_functions->ep_get_template_part( 'organizers/list/load_more', null, $args );
        $ep_functions->ep_load_more_html('ep-organizers', (object)$pargs);
        do_action( 'ep_organizers_list_after_content', $args ); 
        ?>
    </div> 
</div>