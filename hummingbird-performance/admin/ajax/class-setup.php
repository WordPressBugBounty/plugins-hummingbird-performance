<?php
/**
 * Setup wizard AJAX actions.
 *
 * @since 3.3.1
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Setup.
 */
class Setup {

	/**
	 * Setup constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_cancel_wizard', array( $this, 'cancel' ) );
		add_action( 'wp_ajax_wphb_react_resume_wizard', array( $this, 'resume' ) );
		add_action( 'wp_ajax_wphb_react_complete_wizard', array( $this, 'complete' ) );
	}

	/**
	 * Cancel wizard.
	 *
	 * @since 3.3.1
	 *
	 * @return void
	 */
	public function cancel() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$params = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$params = json_decode( html_entity_decode( (string) $params ), true );

		update_option( 'wphb_run_onboarding', null );

		Utils::get_module( 'mixpanel_analytics' )->events( 'general' )->track_setup_wizard(
			isset( $params['reason'] ) && 'skip' === $params['reason'] ? 'skip' : 'quit'
		);

		wp_send_json_success();
	}

	/**
	 * Resume a skipped wizard.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function resume() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		update_option( 'wphb_run_onboarding', true );
		wp_send_json_success();
	}

	/**
	 * Complete wizard.
	 *
	 * @since 3.3.1
	 *
	 * @return void
	 */
	public function complete() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$params = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$params = json_decode( html_entity_decode( $params ), true );

		if ( 'runPerf' === $params ) {
			Utils::get_module( 'mixpanel_analytics' )->events( 'general' )->track_setup_wizard( 'performance_test' );
		}

		update_option( 'wphb_run_onboarding', null );
		wp_send_json_success();
	}
}
