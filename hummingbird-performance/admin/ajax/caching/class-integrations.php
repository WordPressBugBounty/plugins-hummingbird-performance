<?php
/**
 * Integrations AJAX actions.
 *
 * @since 3.0.0
 * @package Hummingbird\Admin\Ajax\Caching
 */

namespace Hummingbird\Admin\Ajax\Caching;

use Hummingbird\Core\Modules\Cloudflare;
use Hummingbird\Core\Utils;
use Hummingbird\Admin\Ajax\Activity_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Integrations.
 */
class Integrations {

	/**
	 * Activity log module name.
	 */
	const ACTIVITY_MODULE = 'Cloudflare';

	/**
	 * Integrations constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'cloudflare_status',
			'cloudflare_apo_status',
			'cloudflare_connect',
			'cloudflare_disconnect',
			'cloudflare_zones',
			'cloudflare_clear_cache',
			'cloudflare_save_zone',
			'cloudflare_toggle_apo',
			'cloudflare_toggle_device_cache',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Get Cloudflare status.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_status() {
		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success(
			Utils::get_module( 'cloudflare' )->get_cf_ui_data()
		);
	}

	/**
	 * Check Cloudflare APO status.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_apo_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_module( 'cloudflare' )->get_apo_settings();
		$this->cloudflare_status();

		wp_send_json_success();
	}


	/**
	 * Connect to Cloudflare.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_connect() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$key   = isset( $data['key'] ) ? sanitize_text_field( $data['key'] ) : '';
		$token = isset( $data['token'] ) ? sanitize_text_field( $data['token'] ) : '';
		$zone  = isset( $data['zone'] ) ? sanitize_text_field( $data['zone'] ) : '';

		if ( ! ( $email && $key ) && ! $token && ! $zone ) {
			$message = esc_html__( 'Cannot process the form. Please define either the Email/API key or the API token.', 'wphb' );
			wp_send_json_error( array( 'message' => $message ) );
		}

		$options       = Utils::get_module( 'cloudflare' )->get_options();
		$was_connected = ! empty( $options['enabled'] );

		$options_updated = false;
		if ( ! empty( $email ) && $email !== $options['email'] ) {
			$options_updated  = true;
			$options['email'] = $email;
		}

		if ( ! empty( $key ) && $key !== $options['api_key'] ) {
			$options_updated    = true;
			$options['api_key'] = $key;
		}

		// Only try to set token if API key is not defined.
		if ( empty( $key ) && ! empty( $token ) && $token !== $options['api_key'] ) {
			$options_updated    = true;
			$options['email']   = ''; // Email is not used with API token.
			$options['api_key'] = $token;
		}

		// Save the current credentials, so we can try and connect with them when we check the zones below.
		if ( $options_updated ) {
			Utils::get_module( 'cloudflare' )->update_options( $options );
			Utils::get_api()->cloudflare->refresh_auth();
		}

		$zones = Utils::get_module( 'cloudflare' )->get_zones_list();

		// This will end processing if zones are an issue.
		Utils::get_module( 'cloudflare' )->validate_zones( $zones );

		// Set the module as enabled.
		if ( ! $options['enabled'] ) {
			$options['enabled'] = true;
			Utils::get_module( 'cloudflare' )->update_options( $options );
		}

		$status = Utils::get_module( 'cloudflare' )->process_zones( $zones, $zone );

		if ( $status ) {
			Activity_Log::record( self::ACTIVITY_MODULE, 'Cloudflare connected.' );
			Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'Cloudflare_integration', $was_connected, true );
		}

		// Could not match a zone.
		if ( ! $status ) {
			wp_send_json_success( array( 'zones' => $zones ) );
		}

		wp_send_json_success();
	}

	/**
	 * Disconnect Cloudflare.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_disconnect() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$was_connected = Utils::get_module( 'cloudflare' )->is_connected();

		Utils::get_module( 'cloudflare' )->disconnect();

		Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'Cloudflare_integration', $was_connected, false );

		$this->cloudflare_status();
	}

	/**
	 * Check Cloudflare zones.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_zones() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$zones = Utils::get_module( 'cloudflare' )->get_zones_list();

		// This will end processing if zones are an issue.
		Utils::get_module( 'cloudflare' )->validate_zones( $zones );

		$status = Utils::get_module( 'cloudflare' )->process_zones( $zones );

		// Could not match a zone.
		if ( ! $status ) {
			wp_send_json_success( array( 'zones' => $zones ) );
		}

		wp_send_json_success();
	}

	/**
	 * Clear Cloudflare cache.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_clear_cache() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_module( 'cloudflare' )->clear_cache();
		Activity_Log::record( self::ACTIVITY_MODULE, 'Cloudflare cache cleared.' );

		wp_send_json_success();
	}

	/**
	 * Save selected Cloudflare zone.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_save_zone() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$zone = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$zone = json_decode( html_entity_decode( $zone ), true );

		$zones = Utils::get_module( 'cloudflare' )->get_zones_list();

		// This will end processing if zones are an issue.
		Utils::get_module( 'cloudflare' )->validate_zones( $zones );

		$options = Utils::get_module( 'cloudflare' )->get_options();

		// Set the module as enabled.
		if ( ! $options['enabled'] ) {
			$options['enabled'] = true;
			Utils::get_module( 'cloudflare' )->update_options( $options );
		}

		Utils::get_module( 'cloudflare' )->process_zones( $zones, $zone );

		$this->cloudflare_status();
	}

	/**
	 * Toggle Cloudflare APO.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_toggle_apo() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$status = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$status = json_decode( html_entity_decode( $status ), true );

		$result = Utils::get_module( 'cloudflare' )->toggle_apo( $status );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$options = Utils::get_module( 'cloudflare' )->get_options();

		$apo_status = Utils::get_module( 'cloudflare' )->is_apo_enabled();

		if ( $apo_status !== (bool) $status ) {
			wp_send_json_error( array( 'message' => __( 'Could not update Automatic Platform Optimization status. Please try again.', 'wphb' ) ) );
		}

		Activity_Log::record( self::ACTIVITY_MODULE, 'Cloudflare APO ' . ( $status ? 'connected.' : 'disconnected.' ) );

		wp_send_json_success(
			array(
				'enabled'   => $apo_status,
				'purchased' => $options['apo_paid'],
				'settings'  => $options['apo'],
			)
		);
	}

	/**
	 * Toggle Cloudflare APO cache by device type setting.
	 *
	 * @since 3.0.0
	 */
	public function cloudflare_toggle_device_cache() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$status = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$status = json_decode( html_entity_decode( $status ), true );

		$result = Utils::get_module( 'cloudflare' )->toggle_cache_by_device( $status );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$options = Utils::get_module( 'cloudflare' )->get_options();

		$current_status = isset( $options['apo']['cache_by_device_type'] ) ? $options['apo']['cache_by_device_type'] : false;

		if ( $current_status !== (bool) $status ) {
			wp_send_json_error( array( 'message' => __( 'Could not update Cache by Device Type status. Please try again.', 'wphb' ) ) );
		}

		Activity_Log::record( 'cloudflare', 'Cloudflare cache by device types ' . ( $status ? 'enabled.' : 'disabled.' ) );

		wp_send_json_success(
			array(
				'enabled'   => Utils::get_module( 'cloudflare' )->is_apo_enabled(),
				'purchased' => $options['apo_paid'],
				'settings'  => $options['apo'],
			)
		);
	}
}
