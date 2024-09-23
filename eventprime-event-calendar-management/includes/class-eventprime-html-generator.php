<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Eventprime_html_Generator {

    public function ep_get_recurrence_interval() {
        $repeats = array(
            'daily' => esc_html__('Day(s)', 'eventprime-event-calendar-management'),
            'weekly' => esc_html__('Week(s)', 'eventprime-event-calendar-management'),
            'monthly' => esc_html__('Month(s)', 'eventprime-event-calendar-management'),
            'yearly' => esc_html__('Year(s)', 'eventprime-event-calendar-management'),
            'advanced' => esc_html__('Advanced', 'eventprime-event-calendar-management'),
            'custom_dates' => esc_html__('Custom Dates', 'eventprime-event-calendar-management'),
        );
        return $repeats;
    }

    public function get_ep_event_checkout_field_tabs() {
        $ep_functions = new Eventprime_Basic_Functions;
        $tabs = apply_filters(
                'ep_event_checkout_field_tabs',
                array(
                    'attendee_fields' => array(
                        'label' => esc_html__('Attendee Fields', 'eventprime-event-calendar-management'),
                        'target' => 'ep_event_attendee_fields_data',
                        'class' => array('ep_event_attendee_fields_wrap'),
                        'priority' => 10,
                    ),
                    'booking_fields' => array(
                        'label' => esc_html__('Booking Fields', 'eventprime-event-calendar-management'),
                        'target' => 'ep_event_booking_fields_data',
                        'class' => array('ep_event_booking_fields_wrap'),
                        'priority' => 20,
                    ),
                )
        );
        // Sort tabs based on priority.
        uasort($tabs, array($ep_functions, 'event_data_tabs_sort'));
        return $tabs;
    }

    /**
     * get name sub field in table structure
     * 
     * @param array $event_checkout_attendee_fields Saved Attendee Fields.
     * 
     * @return Field Html.
     */
    public function ep_get_checkout_essentials_fields_rows($event_checkout_attendee_fields = array(), $is_popup = '') {
        $field = '<tr class="ep-event-checkout-esse-name-field" title="' . esc_html__('Add attendee name field', 'eventprime-event-calendar-management') . '">';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name">' . esc_html__('Name', 'eventprime-event-calendar-management') . '</label>';
        $field .= '</td>';
        $field .= '<td>' . esc_html__('For adding attendee names', 'eventprime-event-calendar-management') . '</td>';
        $field .= '<td>';
        $em_event_checkout_name_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name']) && $event_checkout_attendee_fields['em_event_checkout_name'] == 1 ) ? 'checked="checked"' : '';
        $field .= '<input type="checkbox" name="em_event_checkout_name" class="ep-form-check-input" id="em_event_checkout_name' . $is_popup . '" value="1" data-label="' . esc_html__('Name', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_checked . '>';
        $field .= '</td>';
        $field .= '<td>&nbsp;</td>';
        $field .= '</tr>';
        $field .= $this->ep_get_name_sub_fields_rows($event_checkout_attendee_fields, $is_popup);
        return $field;
    }

    public function ep_get_name_sub_fields_rows($event_checkout_attendee_fields, $is_popup = '') {
        $field = $display = '';
        if (isset($event_checkout_attendee_fields['em_event_checkout_name']) && $event_checkout_attendee_fields['em_event_checkout_name'] == 1) {
            $display = 'style="display:table-row;"';
        }
        // first name
        $em_event_checkout_name_first_name_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name_first_name']) && $event_checkout_attendee_fields['em_event_checkout_name_first_name'] == 1 ) ? 'checked="checked"' : '';
        $first_name_display = ( empty($em_event_checkout_name_first_name_checked) ? 'style="display:none;"' : '' );
        $em_event_checkout_name_first_name_required_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name_first_name_required']) && $event_checkout_attendee_fields['em_event_checkout_name_first_name_required'] == 1 ) ? 'checked="checked"' : '';
        $field .= '<tr class="ep-sub-field-first-name ep-event-checkout-field-name-sub-row" title="' . esc_html__('Add attendee first name field', 'eventprime-event-calendar-management') . '" ' . $display . '>';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name_first_name' . $is_popup . '" class="ep-form-label">' . esc_html__('First Name', 'eventprime-event-calendar-management') . '</label></div>';
        $field .= '</td>';
        $field .= '<td>' . esc_html__('For adding attendee first name', 'eventprime-event-calendar-management') . '</td>';
        $field .= '<td>';
        $field .= '<div class="ep-form-check-wrap ep-di-flex ep-items-center"><input type="checkbox" class="ep-form-check-input ep-name-sub-fields" data-field_type="first-name" name="em_event_checkout_name_first_name' . $is_popup . '" id="em_event_checkout_name_first_name' . $is_popup . '" value="1" data-label="' . esc_html__('First Name', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_first_name_checked . '>';
        $field .= '</td>';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name_first_name_required' . $is_popup . '" class="ep-form-label ep-ml-3 ep-first-name-required" title="' . esc_html__('Require attendee first name field', 'eventprime-event-calendar-management') . '">';
        $field .= '<input type="checkbox" name="em_event_checkout_name_first_name_required' . $is_popup . '" id="em_event_checkout_name_first_name_required' . $is_popup . '" value="1" data-label="' . esc_html__('Required', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_first_name_required_checked . '>';
        $field .= '</label>';
        $field .= '</td>';
        $field .= '</tr>';
        // middle name
        $em_event_checkout_name_middle_name_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name_middle_name']) && $event_checkout_attendee_fields['em_event_checkout_name_middle_name'] == 1 ) ? 'checked="checked"' : '';
        $middle_name_display = ( empty($em_event_checkout_name_middle_name_checked) ? 'style="display:none;"' : '' );
        $em_event_checkout_name_middle_name_required_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name_middle_name_required']) && $event_checkout_attendee_fields['em_event_checkout_name_middle_name_required'] == 1 ) ? 'checked="checked"' : '';
        $field .= '<tr class="ep-sub-field-middle-name ep-event-checkout-field-name-sub-row" title="' . esc_html__('Add attendee middle name field', 'eventprime-event-calendar-management') . '" ' . $display . '>';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name_middle_name' . $is_popup . '" class="ep-form-label">' . esc_html__('Middle Name', 'eventprime-event-calendar-management') . '</label></div>';
        $field .= '</td>';
        $field .= '<td>' . esc_html__('For adding attendee middle name', 'eventprime-event-calendar-management') . '</td>';
        $field .= '<td>';
        $field .= '<div class="ep-form-check-wrap ep-di-flex ep-items-center"><input type="checkbox" class="ep-form-check-input ep-name-sub-fields" data-field_type="middle-name" name="em_event_checkout_name_middle_name' . $is_popup . '" id="em_event_checkout_name_middle_name' . $is_popup . '" value="1" data-label="' . esc_html__('Middle Name', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_middle_name_checked . '>';
        $field .= '</td>';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name_middle_name_required' . $is_popup . '" class="ep-form-label ep-ml-3 ep-middle-name-required" title="' . esc_html__('Require attendee middle name field', 'eventprime-event-calendar-management') . '">';
        $field .= '<input type="checkbox" name="em_event_checkout_name_middle_name_required' . $is_popup . '" id="em_event_checkout_name_middle_name_required' . $is_popup . '" value="1" data-label="' . esc_html__('Required', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_middle_name_required_checked . '>';
        $field .= '</label>';
        $field .= '</td>';
        $field .= '</tr>';
        // last name
        $em_event_checkout_name_last_name_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name_last_name']) && $event_checkout_attendee_fields['em_event_checkout_name_last_name'] == 1 ) ? 'checked="checked"' : '';
        $last_name_display = ( empty($em_event_checkout_name_last_name_checked) ? 'style="display:none;"' : '' );
        $em_event_checkout_name_last_name_required_checked = ( isset($event_checkout_attendee_fields['em_event_checkout_name_last_name_required']) && $event_checkout_attendee_fields['em_event_checkout_name_last_name_required'] == 1 ) ? 'checked="checked"' : '';
        $field .= '<tr class="ep-sub-field-last-name ep-event-checkout-field-name-sub-row" title="' . esc_html__('Add attendee last name field', 'eventprime-event-calendar-management') . '" ' . $display . '>';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name_last_name' . $is_popup . '" class="ep-form-label">' . esc_html__('Last Name', 'eventprime-event-calendar-management') . '</label></div>';
        $field .= '</td>';
        $field .= '<td>' . esc_html__('For adding attendee last name', 'eventprime-event-calendar-management') . '</td>';
        $field .= '<td>';
        $field .= '<div class="ep-form-check-wrap ep-di-flex ep-items-center"><input type="checkbox" class="ep-form-check-input ep-name-sub-fields" data-field_type="last-name" name="em_event_checkout_name_last_name' . $is_popup . '" id="em_event_checkout_name_last_name' . $is_popup . '" value="1" data-label="' . esc_html__('Last Name', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_last_name_checked . '>';
        $field .= '</td>';
        $field .= '<td>';
        $field .= '<label for="em_event_checkout_name_last_name_required' . $is_popup . '" class="ep-form-label ep-ml-3 ep-last-name-required" title="' . esc_html__('Require attendee last name field', 'eventprime-event-calendar-management') . '">';
        $field .= '<input type="checkbox" name="em_event_checkout_name_last_name_required' . $is_popup . '" id="em_event_checkout_name_last_name_required' . $is_popup . '" value="1" data-label="' . esc_html__('Required', 'eventprime-event-calendar-management') . '" ' . $em_event_checkout_name_last_name_required_checked . '>';
        $field .= '</label>';
        $field .= '</td>';
        $field .= '</tr>';
        return $field;
    }

    /*
     * return fixed checkout fields
     */

    public function ep_get_checkout_fixed_fields($em_event_checkout_fixed_fields = array()) {
        $terms_check = (!empty($em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_enabled']) ? 'checked="checked"' : '' );
        $display_check = (!empty($em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_enabled']) ? '' : 'style="display:none;"' );
        $term_label = (!empty($em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_label']) ? $em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_label'] : '' );
        $field = '<div class="ep-event-checkout-fixed-field ep-box-row">';
        $field .= '<div class="ep-box-col-12 ep-mt-3"><div class="ep-form-check"><label class="ep-form-label" for="em_event_checkout_fixed_terms">';
        $field .= '<input type="checkbox" name="em_event_checkout_fixed_terms" id="em_event_checkout_fixed_terms" class="ep-form-check-input" value="1" data-label="' . esc_html__('Terms & Conditions', 'eventprime-event-calendar-management') . '" ' . esc_attr($terms_check) . '>' . esc_html__('Terms & Conditions', 'eventprime-event-calendar-management');
        $field .= '</label></div></div>';
        $field .= '<div class="ep-box-col-12 ep-mt-3 ep-event-terms-sub-fields" ' . $display_check . '>';
        $field .= '<input type="text" name="em_event_checkout_terms_label" class="ep-form-control" id="em_event_checkout_terms_label" placeholder="' . esc_html__('Enter Label', 'eventprime-event-calendar-management') . '" value="' . esc_attr($term_label) . '">';
        $field .= '<div class="ep-error-message" id="ep_fixed_field_label_error"></div>';
        $field .= $this->ep_get_terms_sub_fields($em_event_checkout_fixed_fields);
        $field .= '</div>';
        $field .= '</div>';
        return $field;
    }

    /**
     * get terms sub field
     */
    public function ep_get_terms_sub_fields($em_event_checkout_fixed_fields) {
        $ep_functions = new Eventprime_Basic_Functions;
        $field = '';
        $term_option = (!empty($em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_option']) ? $em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_option'] : '' );
        $term_content = (!empty($em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_content']) ? $em_event_checkout_fixed_fields['em_event_checkout_fixed_terms_content'] : '' );
        // select page option
        $field .= '<div class="ep-sub-field-terms-page ep-box-row ep-mt-3">';
        $field .= '<div class="ep-box-col-1 "><label for="em_event_checkout_terms_page_option">';
        if ($term_option == 'page') {
            $field .= '<input type="radio" class="ep-terms-sub-fields" data-terms_type="page" name="em_event_checkout_terms_option" id="em_event_checkout_terms_page_option" value="page" data-label="' . esc_html__('Select Page', 'eventprime-event-calendar-management') . '" checked>';
        } else {
            $field .= '<input type="radio" class="ep-terms-sub-fields" data-terms_type="page" name="em_event_checkout_terms_option" id="em_event_checkout_terms_page_option" value="page" data-label="' . esc_html__('Select Page', 'eventprime-event-calendar-management') . '">';
        }
        $field .= '</label></div>';
        $field .= '<div class="ep-box-col-11 ep-sub-field-terms-page-options">';
        if ($term_option == 'page') {
            $field .= '<select name="em_event_checkout_terms_page" id="em_event_checkout_terms_page" class="ep-form-control ep-event-terms-options">';
        } else {
            $field .= '<select name="em_event_checkout_terms_page" id="em_event_checkout_terms_page" class="ep-form-control ep-event-terms-options" disabled>';
        }
        $field .= '<option value="">' . esc_html__('Select Page', 'eventprime-event-calendar-management') . '</option>';
        foreach ($ep_functions->ep_get_all_pages_list() as $page_id => $page_title) {
            if ($term_option == 'page' && is_int($term_content) && $term_content == $page_id) {
                $field .= '<option value="' . $page_id . '" selected>' . $page_title . '</option>';
            } else {
                $field .= '<option value="' . $page_id . '">' . $page_title . '</option>';
            }
        }
        $field .= '</select>';
        $field .= '<div class="ep-error-message" id="ep_fixed_field_page_option_error"></div>';
        $field .= '</div>';
        $field .= '</div>';

        // enter external url option
        $field .= '<div class="ep-sub-field-terms-url ep-box-row ep-mt-3">';
        $field .= '<div class="ep-box-col-1 "><label for="em_event_checkout_terms_url_option">';
        if ($term_option == 'url') {
            $field .= '<input type="radio" class="ep-terms-sub-fields" data-terms_type="url" name="em_event_checkout_terms_option" id="em_event_checkout_terms_url_option" value="url" data-label="' . esc_html__('Enter URL', 'eventprime-event-calendar-management') . '" checked>';
        } else {
            $field .= '<input type="radio" class="ep-terms-sub-fields" data-terms_type="url" name="em_event_checkout_terms_option" id="em_event_checkout_terms_url_option" value="url" data-label="' . esc_html__('Enter URL', 'eventprime-event-calendar-management') . '">';
        }
        $field .= '</label></div>';
        $field .= '<div class="ep-box-col-11 ep-sub-field-terms-url-options">';
        if ($term_option == 'url') {
            $field .= '<input type="url" name="em_event_checkout_terms_url" id="em_event_checkout_terms_url" class="ep-form-control ep-event-terms-options" placeholder="' . esc_html__('Enter URL', 'eventprime-event-calendar-management') . '" value="' . esc_attr($term_content) . '">';
        } else {
            $field .= '<input type="url" name="em_event_checkout_terms_url" id="em_event_checkout_terms_url" class="ep-form-control ep-event-terms-options" placeholder="' . esc_html__('Enter URL', 'eventprime-event-calendar-management') . '" disabled>';
        }
        $field .= '<div class="ep-error-message" id="ep_fixed_field_url_option_error"></div>';
        $field .= '</div>';
        $field .= '</div>';

        // enter custom content option
        $content = '';
        $field .= '<div class="ep-sub-field-terms-content ep-box-row ep-mt-3">';
        $field .= '<div class="ep-box-col-1 "><label for="em_event_checkout_terms_content_option">';
        if ($term_option == 'content') {
            $field .= '<input type="radio" class="ep-terms-sub-fields" data-terms_type="content" name="em_event_checkout_terms_option" id="em_event_checkout_terms_content_option" value="content" data-label="' . esc_html__('Enter Custom Content', 'eventprime-event-calendar-management') . '" checked>';
        } else {
            $field .= '<input type="radio" class="ep-terms-sub-fields" data-terms_type="content" name="em_event_checkout_terms_option" id="em_event_checkout_terms_content_option" value="content" data-label="' . esc_html__('Enter Custom Content', 'eventprime-event-calendar-management') . '">';
        }
        $field .= '</label></div>';
        $field .= '<div class="ep-box-col-11 ep-mb-3">';
        $field .= esc_html__('Enter Custom Content', 'eventprime-event-calendar-management');
        $field .= '</div>';
        if ($term_option == 'content') {
            $field .= '<div class="ep-box-col-12 ep-sub-field-terms-content-options ep-mt-3">';
            $field .= $ep_functions->ep_get_wp_editor(wp_kses_post($term_content), 'description');
            $field .= '<div class="ep-error-message" id="ep_fixed_field_custom_option_error"></div>';
            $field .= '</div>';
        } else {
            $field .= '<div class="ep-box-col-12 ep-sub-field-terms-content-options ep-mt-3" style="display:none;">';
            $field .= $ep_functions->ep_get_wp_editor("", 'description');
            $field .= '<div class="ep-error-message" id="ep_fixed_field_custom_option_error"></div>';
            $field .= '</div>';
        }
        $field .= '</div>';
        return $field;
    }

    public function ep_add_event_statisticts_data($post) {
        $event_id = $post->ID;
        $dbhandler = new EP_DBhandler;
        $ep_functions = new Eventprime_Basic_Functions;
        $all_bookings = $dbhandler->eventprime_get_all_posts('em_booking', 'posts', 'completed', 'ID', 0, 'ASC', -1, 'em_event', $post->ID);
        $total_booking = 0;
        $event_booking_count = 0;
        $total_booking_numbers = 0;
        if (!empty($all_bookings)) {
            $event_booking_count = count($all_bookings);
            foreach ($all_bookings as $booking) {
                $booking_data = $ep_functions->load_booking_detail($booking->ID, false, $booking);
                if (!empty($booking_data)) {
                    if (isset($booking_data->em_order_info['tickets']) && !empty($booking_data->em_order_info['tickets'])) {
                        $booked_tickets = $booking_data->em_order_info['tickets'];
                        foreach ($booked_tickets as $ticket) {
                            if (!empty($ticket->id) && !empty($ticket->qty)) {
                                $total_booking += $ticket->qty;
                            }
                        }
                    } else if (isset($booking_data->em_order_info['order_item_data']) && !empty($booking_data->em_order_info['order_item_data'])) {
                        $booked_tickets = $booking_data->em_order_info['order_item_data'];
                        foreach ($booked_tickets as $ticket) {
                            if (isset($ticket->quantity)) {
                                $total_booking += $ticket->quantity;
                            } else if (isset($ticket->qty)) {
                                $total_booking += $ticket->qty;
                            }
                        }
                    }
                }
            }
        } 
        
        ?>
        <div class="ep-event-summary-data-list">
            <label><?php esc_html_e('Total Bookings:', 'eventprime-event-calendar-management'); ?></label>
            <label>
                <?php
                esc_attr_e($event_booking_count);
                if (!empty($event_booking_count)) {
                    $event_booking_url = admin_url('edit.php?s&post_status=all&post_type=em_booking&event_id=' . esc_attr($event_id));
                    ?> 
                    <a href="<?php echo esc_url($event_booking_url); ?>" target="__blank">
                        <?php esc_html_e('View', 'eventprime-event-calendar-management'); ?>
                    </a><?php }
                    ?>
            </label>
        </div>
        <div class="ep-event-summary-data-list">
            <label><?php esc_html_e('Total Attendees:', 'eventprime-event-calendar-management'); ?></label>
            <label>
                <?php
                esc_attr_e($total_booking);
                if (!empty($total_booking)) {
                    $event_attendee_page_url = admin_url('admin.php?page=ep-event-attendees-list&event_id=' . esc_attr($event_id));
                    ?> 
                    <a href="<?php echo esc_url($event_attendee_page_url); ?>" target="__blank">
                        <?php esc_html_e('View', 'eventprime-event-calendar-management'); ?>
                    </a><?php }
                    ?>
            </label>
        </div>
        <?php
    }

}
