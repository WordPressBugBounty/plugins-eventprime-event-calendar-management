<?php
/**
 * View: Performers List
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/performers/list.php
 *
 */
$ep_functions = new Eventprime_Basic_Functions;
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
        $settings                           = new Eventprime_Global_Settings;
        $performers_settings                = $settings->ep_get_settings( 'performers' );
        $performers_data                    = array();
        $performers_data['display_style']   = isset( $atts['display_style'] ) ? $atts["display_style"] : $performers_settings->performer_display_view;
        $performers_data['limit']           = isset( $atts['limit'] ) ? ( empty($atts["limit"]) || !is_numeric($atts["limit"]) ? 10 : $atts["limit"]) : ( empty( $performers_settings->performer_limit ) ? 10 : $performers_settings->performer_limit );
        $performers_data['column']          = isset( $atts['cols'] ) && is_numeric($atts['cols']) ? $atts['cols'] : $performers_settings->performer_no_of_columns;
        $performers_data['cols']            = isset( $atts['cols'] ) && is_numeric($atts['cols']) ? $ep_functions->ep_check_column_size( $atts['cols'] ) : $ep_functions->ep_check_column_size( $performers_settings->performer_no_of_columns );
        $performers_data['load_more']       = isset( $atts['load_more'] ) ? $atts['load_more'] : $performers_settings->performer_load_more;
        $performers_data['enable_search']   = isset( $atts['search'] ) ? $atts['search'] : $performers_settings->performer_search;
        $performers_data['featured']        = isset( $atts["featured"] ) ? $atts["featured"] : 0;
        $performers_data['popular']         = isset( $atts["popular"] ) ? $atts["popular"] : 0;
        $performers_data['orderby']         = isset( $atts["orderby"] ) ? $atts["orderby"] : 'date';
        if($performers_data['orderby'] == 'rand'){
            
            $performers_data['orderby'] = 'RAND('.rand().')';
        }
        $performers_data['box_color'] = '';
        $performers_data['box_color'] = '';
        if( $performers_data['display_style'] == 'box' || $performers_data['display_style'] == 'colored_grid' ) {
            $performers_data['box_color'] = ( isset( $atts["performer_box_color"] ) && ! empty( $atts["performer_box_color"] ) ) ? $atts["performer_box_color"] : $performers_settings->performer_box_color;
            $performers_data["colorbox_start"] = 1;
        }
        // set query arguments
        $paged     = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
        $performers_data['paged'] = $paged;
        $ep_search = isset( $_GET['ep_search'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
        $pargs     = array(
            'orderby'        => $performers_data['orderby'],
            'posts_per_page' => $performers_data['limit'],
            'offset'         => (int)( $paged - 1 ) * (int)$performers_data['limit'],
            'paged'          => $paged,
            's'              => $ep_search,
        );
        // if featured enabled then get featured performers
        if( $performers_data['popular'] == 0 && $performers_data['featured'] == 1 ) {
            $pargs['meta_query'] = array(
                'relation'     => 'AND',
                array(
                    'key'      => 'em_display_front',
                    'value'    => 1,
                    'compare'  => '='
                ),
                array(
                    'key'   => 'em_is_featured',
                    'value' => 1
                )
            );
        }

        $performers_data['performers'] = $ep_functions->get_performers_post_data( $pargs );

        if( $performers_data['popular'] == 1 && $performers_data['featured'] == 0) {
            $performers_data['performers'] = $ep_functions->get_popular_event_performers($performers_data['limit']);
        }

        if( $performers_data['popular'] == 1 && $performers_data['featured'] == 1) {
            $performers_data['performers'] = $ep_functions->get_popular_event_performers($performers_data['limit'], $performers_data['featured']);
        }
        ob_start();
        wp_enqueue_style(
            'ep-performer-views-css',
            plugin_dir_url( EP_PLUGIN_FILE ) . 'public/css/ep-frontend-views.css',
            false, EVENTPRIME_VERSION
        );
        $args = (object)$performers_data;
?>
<div class="emagic">
    <div class="ep-performers-container ep-mb-5 ep-box-wrap" id="ep-performers-container">
        <?php
        // Load performer search template
        $ep_functions->ep_get_template_part( 'performers/list/search', null, $args );
        ?>

        <?php do_action( 'ep_performers_list_before_content', $args ); ?>

        <?php
        if( isset( $args->performers ) && !empty( $args->performers ) ) {?>
            <div class="em_performers ep-event-performers-<?php echo esc_attr($args->display_style);?>-container ep-px-3">
                <div id="ep-event-performers-loader-section" class="ep-box-row ep-box-top ep-performer-<?php echo esc_attr($args->display_style);?>-wrap ">
                    <?php
                    switch ( $args->display_style ) {
                        case 'card':
                        case 'grid':
                            $ep_functions->ep_get_template_part( 'performers/list/views/card', null, $args );
                            break;
                        case 'box': 
                        case 'colored_grid':
                            $ep_functions->ep_get_template_part( 'performers/list/views/box', null, $args );
                            break;
                        case 'list':
                        case 'rows': 
                            $ep_functions->ep_get_template_part( 'performers/list/views/list', null, $args );
                            break;
                        default: 
                            $ep_functions->ep_get_template_part( 'performers/list/views/card', null, $args ); // Loading card view by default
                    }?>
                </div>
            </div><?php
        } else{?>
            <div class="ep-alert ep-alert-warning ep-mt-3">
                <?php ( isset( $_GET['ep_search'] ) ) ? esc_html_e( 'No performers found related to your search.', 'eventprime-event-calendar-management' ) : esc_html_e( 'Currently, there are no performer. Please check back later.', 'eventprime-event-calendar-management' ); ?>
            </div><?php
        }?>

        <?php
        // Load performer load more template
        $ep_functions->ep_get_template_part( 'performers/list/load_more', null, $args );
        ?>

        <?php do_action( 'ep_performers_list_after_content', $args ); ?>

    </div>
</div>