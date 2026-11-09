<?php
/**
 * Product analytics AJAX endpoint for the React UI.
 *
 * @since 4.0.1
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Analytics.
 */
class Analytics {

	/**
	 * Events the browser may report; everything else is tracked by the server inside the request that makes the change.
	 *
	 * @var string[]
	 */
	const CLIENT_EVENTS = array( 'Disconnect Site', 'indentify_cached_pages_interest' );

	/**
	 * Analytics constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_analytics_track', array( $this, 'analytics_track' ) );
	}

	/**
	 * Record an event observed by the React UI.
	 *
	 * @since 4.0.1
	 *
	 * @return void
	 */
	public function analytics_track() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data  = json_decode( (string) filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW ), true );
		$event = isset( $data['event'] ) && is_string( $data['event'] ) ? sanitize_text_field( $data['event'] ) : '';

		if ( ! in_array( $event, self::CLIENT_EVENTS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid analytics payload.', 'wphb' ) ), 400 );
		}

		$mixpanel   = Utils::get_module( 'mixpanel_analytics' );
		$properties = isset( $data['properties'] ) && is_array( $data['properties'] ) ? $mixpanel->wphb_sanitize_data( $data['properties'] ) : array();

		wp_send_json_success( array( 'tracked' => $mixpanel->track( $event, $properties ) ) );
	}
}
