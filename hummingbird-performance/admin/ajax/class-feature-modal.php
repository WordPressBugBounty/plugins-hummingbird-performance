<?php
/**
 * Release ("welcome to X.0") modal AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Admin\Feature_Modal as Feature_Modal_State;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Feature_Modal.
 */
class Feature_Modal {

	/**
	 * Feature_Modal constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_welcome_modal_dismiss', array( $this, 'dismiss' ) );
	}

	/**
	 * Dismiss the welcome modal for good.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function dismiss() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Feature_Modal_State::delete_modal_key();

		wp_send_json_success();
	}
}
