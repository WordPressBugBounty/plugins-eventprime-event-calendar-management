<?php
$global_settings = new Eventprime_Global_Settings;
$global_options = $global_settings->ep_get_settings();
$ep_functions = new Eventprime_Basic_Functions;
$sub_options = $global_settings->sub_options;
?>
<table class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="send_event_approved_email">
                        <?php esc_html_e( 'Enable/Disable', 'eventprime-event-calendar-management' );?>
                    </label>
                </th>
                <td class="forminp forminp-text">
                    <label class="ep-toggle-btn">
                        <input name="send_event_approved_email" id="send_event_approved_email" type="checkbox" value="1" <?php echo isset($global_options->send_event_approved_email ) && $global_options->send_event_approved_email == 1 ? 'checked' : '';?>>
                        <span class="ep-toogle-slider round"></span>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="event_approved_email_subject">
                        <?php esc_html_e( 'Subject', 'eventprime-event-calendar-management' );?><span class="ep-required">*</span>
                    </label>
                </th>
                <td class="forminp forminp-text">
                    <input name="event_approved_email_subject"  class="regular-text" id="event_approved_email_subject" type="text" value="<?php echo isset($global_options->event_approved_email_subject) ? esc_attr($global_options->event_approved_email_subject) : esc_html__('Your Submitted Event approved!', 'eventprime-event-calendar-management');?>" required>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="event_approved_email">
                        <?php esc_html_e( 'Contents', 'eventprime-event-calendar-management' );?><span class="ep-required">*</span>
                    </label>
                </th>
                <td class="forminp forminp-text">
                    <?php 
                    $content = isset($global_options->event_approved_email) ? $global_options->event_approved_email : '';
                    wp_editor( $content, 'event_approved_email' );?>
                </td>
            </tr>
            
        </tbody>
    </table>