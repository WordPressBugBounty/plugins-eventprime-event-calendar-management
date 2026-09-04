<?php
/* --------------------------------------------------------- *
 *  API & Webhooks Settings - EventPrime
 * --------------------------------------------------------- */

$global_settings     = new Eventprime_Global_Settings();
$global_options      = $global_settings->ep_get_settings();
$enable_api          = ! empty( $global_options->enable_api );
$ep_functions        = new Eventprime_Basic_Functions();
$available_roles     = $ep_functions->ep_get_all_user_roles();
$rest_api            = new Eventprime_Rest_Api();
$rest_policies       = $rest_api->ep_get_rest_access_policies_config();
$saved_rest_policies = get_option( 'ep_rest_api_policies', array() );
?>

<div class="ep-setting-tab-content" style="width:100%; max-width:none;">
    <h2><?php esc_html_e( 'API & Webhooks', 'eventprime-event-calendar-management' ); ?></h2>
    <input type="hidden" name="em_setting_type" value="api_settings">

    <div style="width:100%; max-width:none;">
        <p>
            <strong><?php esc_html_e( 'API Endpoint:', 'eventprime-event-calendar-management' ); ?></strong>
            <code><?php echo esc_html( home_url( '/wp-json/eventprime/v1/integration' ) ); ?></code>
        </p>
        <p class="description">
            <?php esc_html_e( 'Unified integration endpoint. Use the "action" or "trigger" parameter to request resources or perform integration operations.', 'eventprime-event-calendar-management' ); ?>
        </p>
        <p class="description">
            <?php esc_html_e( 'Examples:', 'eventprime-event-calendar-management' ); ?>
            <br>
            <code>?action=get_events</code> &nbsp;&mdash;&nbsp; <?php esc_html_e( 'List events', 'eventprime-event-calendar-management' ); ?>
            <br>
            <code>?action=get_tickets&event_id=123</code> &nbsp;&mdash;&nbsp; <?php esc_html_e( 'List tickets for event ID 123', 'eventprime-event-calendar-management' ); ?>
            <br>
            <code>?trigger=create_event</code> &nbsp;&mdash;&nbsp; <?php esc_html_e( 'Get a sample payload for a newly created event', 'eventprime-event-calendar-management' ); ?>
        </p>
    </div>

    <div style="margin:18px 0;">
        <label class="ep-toggle-btn">
            <input name="enable_api" id="enable_api" type="checkbox" value="1" <?php checked( $enable_api ); ?>>
            <span class="ep-toogle-slider round"></span>
        </label>
        <label for="enable_api" style="margin-left:8px; font-weight:600;">
            <?php esc_html_e( 'Enable API', 'eventprime-event-calendar-management' ); ?>
        </label>
        <div class="ep-help-tip-info ep-my-2 ep-text-muted">
            <?php esc_html_e( 'Toggle the EventPrime REST API on or off. When disabled, API endpoints will return 403 responses from the bridge and will not register public REST routes.', 'eventprime-event-calendar-management' ); ?>
        </div>
    </div>

    <div style="width:100%; max-width:none;">
        <h3 style="margin-bottom:8px;"><?php esc_html_e( 'REST Access Policies', 'eventprime-event-calendar-management' ); ?></h3>
        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e( 'Each route, action, and trigger has its own role mapping. Access is granted after authentication only when the current user matches one of the selected roles.', 'eventprime-event-calendar-management' ); ?>
        </p>
        <p style="margin:0 0 12px;">
            <label for="ep-rest-policy-search" style="display:block; font-weight:600; margin-bottom:6px;">
                <?php esc_html_e( 'Filter APIs', 'eventprime-event-calendar-management' ); ?>
            </label>
            <input
                type="search"
                id="ep-rest-policy-search"
                class="regular-text"
                style="width:100%; max-width:420px;"
                placeholder="<?php esc_attr_e( 'Search by route, action, trigger, description, parameter, or example', 'eventprime-event-calendar-management' ); ?>"
            >
        </p>

        <table id="ep-rest-policy-table" class="widefat striped" style="width:100%; max-width:none; table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:12%;"><?php esc_html_e( 'REST API', 'eventprime-event-calendar-management' ); ?></th>
                    <th style="width:16%;"><?php esc_html_e( 'Target', 'eventprime-event-calendar-management' ); ?></th>
                    <th style="width:26%;"><?php esc_html_e( 'Description', 'eventprime-event-calendar-management' ); ?></th>
                    <th style="width:16%;"><?php esc_html_e( 'Required Params', 'eventprime-event-calendar-management' ); ?></th>
                    <th style="width:40%;"><?php esc_html_e( 'Allowed Roles', 'eventprime-event-calendar-management' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rest_policies as $policy_key => $policy ) : ?>
                <?php
                $selected_roles     = isset( $saved_rest_policies[ $policy_key ] ) && is_array( $saved_rest_policies[ $policy_key ] )
                    ? array_map( 'sanitize_key', $saved_rest_policies[ $policy_key ] )
                    : ( isset( $policy['default_roles'] ) ? (array) $policy['default_roles'] : array() );
                $policy_search_text = strtolower(
                    implode(
                        ' ',
                        array(
                            $policy['label'],
                            $policy['target'],
                            $policy['description'],
                            isset( $policy['required_params'] ) ? $policy['required_params'] : '',
                            isset( $policy['example'] ) ? $policy['example'] : '',
                        )
                    )
                );
                ?>
                <tr data-policy-search="<?php echo esc_attr( $policy_search_text ); ?>">
                    <td style="vertical-align:top;">
                        <strong><?php echo esc_html( $policy['label'] ); ?></strong>
                    </td>
                    <td style="vertical-align:top; word-break:break-word;">
                        <code><?php echo esc_html( $policy['target'] ); ?></code>
                    </td>
                    <td style="vertical-align:top;">
                        <?php echo esc_html( $policy['description'] ); ?>
                        <?php if ( ! empty( $policy['example'] ) ) : ?>
                            <div style="margin-top:8px;">
                                <strong><?php esc_html_e( 'Example:', 'eventprime-event-calendar-management' ); ?></strong>
                                <code style="white-space:normal; display:block; margin-top:4px;"><?php echo esc_html( $policy['example'] ); ?></code>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align:top;">
                        <?php echo esc_html( isset( $policy['required_params'] ) ? $policy['required_params'] : '' ); ?>
                    </td>
                    <td style="vertical-align:top;">
                        <?php foreach ( $available_roles as $role_key => $role_label ) : ?>
                            <label style="display:inline-block; min-width:132px; margin:0 12px 6px 0;">
                                <input
                                    type="checkbox"
                                    name="ep_rest_api_policies[<?php echo esc_attr( $policy_key ); ?>][]"
                                    value="<?php echo esc_attr( $role_key ); ?>"
                                    <?php checked( in_array( $role_key, $selected_roles, true ) ); ?>
                                >
                                <?php echo esc_html( $role_label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('ep-rest-policy-search');
    var table = document.getElementById('ep-rest-policy-table');

    if (!searchInput || !table) {
        return;
    }

    searchInput.addEventListener('input', function () {
        var query = searchInput.value.toLowerCase().trim();
        var rows = table.querySelectorAll('tbody tr');

        rows.forEach(function (row) {
            var haystack = row.getAttribute('data-policy-search') || '';
            row.style.display = !query || haystack.indexOf(query) !== -1 ? '' : 'none';
        });
    });
});
</script>
