<?php
/**
 * Gzip AJAX actions.
 *
 * @since 2.2.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Module_Server;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gzip.
 */
class Gzip {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'asset-optimization';

	/**
	 * Gzip constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_gzip_status', array( $this, 'status' ) );
		add_action( 'wp_ajax_wphb_react_gzip_rules', array( $this, 'apply_rules' ) );
		add_action( 'wp_ajax_wphb_react_gzip_configure_options', array( $this, 'configure_options' ) );
	}

	/**
	 * Fetch/refresh gzip status.
	 *
	 * @since 2.2.0
	 */
	public function status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$params = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$params = json_decode( html_entity_decode( $params ), true );

		wp_send_json_success( Utils::get_module( 'gzip' )->get_react_settings( 'refresh' === $params ) );
	}

	/**
	 * Add/remove Gzip .htaccess rules.
	 *
	 * @since 2.2.0
	 */
	public function apply_rules() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$params = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$params = json_decode( html_entity_decode( $params ), true );

		$was_enabled = Module_Server::is_htaccess_written( 'gzip' );

		if ( 'add' === $params ) {
			Module_Server::save_htaccess( 'gzip' );
			Activity_Log::record( self::ACTIVITY_MODULE, __( 'Gzip compression enabled.', 'wphb' ) );
			Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'Gzip', $was_enabled, true );
			wp_send_json_success(
				array(
					'status'           => Utils::get_module( 'gzip' )->get_analysis_data( true, true ),
					'compression_type' => get_option( 'wphb_compression_type' ),
					'htaccess_written' => Module_Server::is_htaccess_written( 'gzip' ),
				)
			);
		}

		if ( 'remove' === $params ) {
			Module_Server::unsave_htaccess( 'gzip' );
			Activity_Log::record( self::ACTIVITY_MODULE, __( 'Gzip compression disabled.', 'wphb' ) );
			Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'Gzip', $was_enabled, false );
			wp_send_json_success(
				array(
					'status'           => Utils::get_module( 'gzip' )->get_analysis_data( true, true ),
					'compression_type' => get_option( 'wphb_compression_type' ),
					'htaccess_written' => Module_Server::is_htaccess_written( 'gzip' ),
				)
			);
		}

		wp_send_json_error( __( 'Error updating .htaccess file', 'wphb' ), 500 );
	}

	/**
	 * Fetch the data needed to render the "Configure Compression" modal:
	 * detected/available server types, .htaccess state, and the code
	 * snippet for each server type.
	 *
	 * @since 4.0.0
	 */
	public function configure_options() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Utils::get_module( 'gzip' )->get_configure_react_settings() );
	}

}
