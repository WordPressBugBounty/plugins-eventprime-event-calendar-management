<?php
/**
 * Dismissible admin notice for Elementor Pro users about the Enable Pretty URLs setting.
 *
 * Guidance only: no routing, permalink or redirect behaviour is changed here.
 *
 * @package EventPrime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and dismisses the Elementor Pro Enable Pretty URLs admin notice.
 */
class EventPrime_Elementor_Seo_Notice {

	const USER_META = 'ep_notice_elementor_seo';

	/**
	 * Render the notice when every condition is met.
	 */
	public function maybe_render_notice() {
		if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_settings_screen() || ! $this->is_seo_off() || ! $this->is_elementor_pro_active() || $this->is_dismissed() ) {
			return;
		}

		$url = admin_url( 'edit.php?post_type=em_event&page=ep-settings&tab=general&sub_tab=seo' );
		?>
		<div class="notice notice-info is-dismissible ep-elementor-seo-notice"><p><strong><?php esc_html_e( 'Elementor Pro detected.', 'eventprime-event-calendar-management' ); ?></strong> <?php esc_html_e( 'Theme Builder display conditions for single events are applied to an event\'s own page address, which EventPrime only uses when "Enable Pretty URLs" is on and WordPress Settings > Permalinks is not Plain.', 'eventprime-event-calendar-management' ); ?> <?php esc_html_e( 'With the setting off, event links use the Events page instead, so a single-event template may not apply.', 'eventprime-event-calendar-management' ); ?> <a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open the SEO setting', 'eventprime-event-calendar-management' ); ?></a></p></div>
		<?php
	}

	/**
	 * AJAX: remember the dismissal for the current user.
	 */
	public function ajax_dismiss_notice() {
		$nonce = filter_input( INPUT_POST, 'nonce' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ep_dismissable_notice_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed security check.', 'eventprime-event-calendar-management' ) ), 400 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'eventprime-event-calendar-management' ) ), 400 );
		}

		update_user_meta( get_current_user_id(), self::USER_META, 1 );
		wp_send_json_success();
	}

	/**
	 * Only the EventPrime settings screen.
	 *
	 * @return bool
	 */
	private function is_settings_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && in_array( $screen->id, array( 'eventprime_page_ep-settings', 'em_event_page_ep-settings' ), true );
	}

	/**
	 * Is the Enable Pretty URLs setting off?
	 *
	 * @return bool
	 */
	private function is_seo_off() {
		return 0 === (int) ( new Eventprime_Basic_Functions() )->ep_get_global_settings( 'enable_seo_urls' );
	}

	/**
	 * Passive Elementor Pro detection: nothing from Elementor is loaded or called.
	 *
	 * @return bool
	 */
	private function is_elementor_pro_active() {
		return (bool) apply_filters( 'eventprime_elementor_pro_active', defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin', false ) );
	}

	/**
	 * Has the current user dismissed the notice?
	 *
	 * @return bool
	 */
	private function is_dismissed() {
		return (bool) get_user_meta( get_current_user_id(), self::USER_META, true );
	}
}
