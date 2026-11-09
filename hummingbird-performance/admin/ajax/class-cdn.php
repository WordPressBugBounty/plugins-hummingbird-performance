<?php
/**
 * CDN AJAX actions.
 *
 * @since 4.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cdn.
 */
class Cdn {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'cdn';

	/**
	 * Cdn constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'cdn_toggle',
			'cdn_save_exclusions',
			'cdn_refresh_status',
			'cdn_exclusion_options',
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Register callbacks.
			 *
			 * @uses cdn_toggle()
			 * @uses cdn_save_exclusions()
			 * @uses cdn_refresh_status()
			 * @uses cdn_exclusion_options()
			 */
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	/**
	 * Enable/disable CDN.
	 *
	 * @since 4.0
	 */
	public function cdn_toggle() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$value       = filter_input( INPUT_POST, 'data', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$cdn         = Utils::get_module( 'cdn' );
		$was_enabled = $cdn->is_active();

		$cdn->toggle_cdn( $value );
		$is_enabled = $cdn->is_active();

		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_enabled,
			$is_enabled,
			__( 'CDN enabled.', 'wphb' ),
			__( 'CDN disabled.', 'wphb' )
		);
		Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'CDN', $was_enabled, $is_enabled );

		$settings = $cdn->get_react_settings();

		if ( ! $value ) {
			$notice = esc_html__( 'CDN disabled. If you’re using page or server caching, clear it to ensure everything works properly.', 'wphb' );
		} elseif ( $settings['cdnEnabling'] ) {
			$notice = esc_html__( 'CDN setup in progress. Changes may take up to 30 minutes to propagate.', 'wphb' );
		} else {
			$notice = esc_html__( 'CDN enabled. If you’re using page or server caching, clear it to ensure everything works properly.', 'wphb' );
		}

		wp_send_json_success( array_merge( $settings, array( 'notice' => $notice ) ) );
	}

	/**
	 * Re-check CDN provisioning status with the WPMU DEV API.
	 *
	 * @since 4.0
	 */
	public function cdn_refresh_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$cdn    = Utils::get_module( 'cdn' );
		$result = $cdn->refresh_status();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $cdn->get_react_settings() );
	}

	/**
	 * Update CDN exclusion rules.
	 *
	 * @since 4.0
	 */
	public function cdn_save_exclusions() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$raw            = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$text           = (string) json_decode( html_entity_decode( $raw ), true );
		$previous_files = Settings::get_setting( 'excluded_files', 'cdn' );
		$previous_files = is_array( $previous_files ) ? $previous_files : array();

		$excluded_files = array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
		$excluded_files = Utils::wphb_sanitize_data( $excluded_files );

		Settings::update_setting( 'excluded_files', $excluded_files, 'cdn' );

		Activity_Log::record_when_changed(
			self::ACTIVITY_MODULE,
			$previous_files,
			$excluded_files,
			__( 'CDN exclusions list updated.', 'wphb' )
		);

		wp_send_json_success( Utils::get_module( 'cdn' )->get_react_settings() );
	}

	/**
	 * CDN exclusion options.
	 *
	 * @since 4.0
	 */
	public function cdn_exclusion_options() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$exclusions = Utils::get_module( 'exclusions' );
		$assets     = array_merge( $exclusions->get_assets( 'scripts' ), $exclusions->get_assets( 'styles' ) );

		$options = array();

		foreach ( $assets as $handle => $asset ) {
			$src = $asset['src'] ?? '';

			if ( ! $src ) {
				continue;
			}

			$path = wp_parse_url( $src, PHP_URL_PATH );
			$path = $path ? $path : $src;

			$options[] = array(
				'value' => $path,
				'label' => sprintf( '%s (file %s)', $handle, basename( $path ) ),
			);
		}

		wp_send_json_success( array( 'files' => $options ) );
	}
}
