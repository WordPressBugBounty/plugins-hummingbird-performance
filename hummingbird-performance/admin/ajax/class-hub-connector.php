<?php
/**
 * React Hub Connector AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Hub_Connector as Hub_Connector_Component;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hub_Connector.
 */
class Hub_Connector {

	/**
	 * Hub_Connector constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'disconnect_site',
			'check_sync_status',
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Register callbacks.
			 *
			 * @uses disconnect_site()
			 */
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	/**
	 * Sync the site and report the current Hub connection state.
	 *
	 * @since 4.0.0
	 */
	public function check_sync_status() {
		check_ajax_referer( 'auth_nonce', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'wphb' ) ), 403 );
		}

		$sync = Hub_Connector_Component::sync();
		if ( is_wp_error( $sync ) && 'not_logged_in' !== $sync->get_error_code() ) {
			delete_site_transient( Hub_Connector_Component::SYNCING_TRANSIENT_KEY );
			wp_send_json_error(
				array(
					'isSynced' => false,
					'message'  => $sync->get_error_message(),
				),
				403
			);
		}

		$is_logged_in = Hub_Connector_Component::logged_in();
		if ( $is_logged_in ) {
			delete_site_transient( Hub_Connector_Component::SYNCING_TRANSIENT_KEY );
		}

		wp_send_json_success( array( 'isSynced' => $is_logged_in ) );
	}

	/**
	 * Disconnect the site from the Hub.
	 *
	 * @since 4.0.0
	 */
	public function disconnect_site() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Hub_Connector_Component::logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not connected to the Hub.', 'wphb' ),
				)
			);
		}

		if ( ! Hub_Connector_Component::disconnect() ) {
			wp_send_json_error(
				array(
					'message' => __( 'We could not disconnect your site. Please try again.', 'wphb' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your site has been disconnected successfully!', 'wphb' ),
			)
		);
	}
}
