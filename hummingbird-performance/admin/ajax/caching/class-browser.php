<?php
/**
 * Browser Caching AJAX actions.
 *
 * @since 2.7.2
 * @package Hummingbird\Admin\Ajax\Caching
 */

namespace Hummingbird\Admin\Ajax\Caching;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Core\Module_Server;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Browser.
 */
class Browser {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'caching';

	/**
	 * Browser constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'browser_caching_status',
			'update_expiry',
			'clear_cache',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Fetch/refresh browser caching status.
	 *
	 * @since 2.7.2
	 */
	public function browser_caching_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$params = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$params = json_decode( html_entity_decode( $params ), true );

		$force  = 'refresh' === $params;
		$status = Utils::get_module( 'caching' )->get_analysis_data( $force, $force );

		$cloudflare = Utils::get_module( 'cloudflare' )->is_connected() && Utils::get_module( 'cloudflare' )->is_zone_selected();
		if ( $cloudflare ) {
			// Fill the report with values from Cloudflare.
			$status = array_fill_keys( array_keys( $status ), Utils::get_module( 'cloudflare' )->get_caching_expiration( true ) );
		}

		// Current expiry value settings (user selected, not actual server settings).
		$options = Settings::get_settings( 'caching' );
		$expires = array(
			'CSS'        => $options['expiry_css'],
			'JavaScript' => $options['expiry_javascript'],
			'Media'      => $options['expiry_media'],
			'Images'     => $options['expiry_images'],
		);

		wp_send_json_success(
			array(
				'status'           => $status,
				'expires'          => $expires,
				'human'            => array_map( array( 'Hummingbird\\Core\\Utils', 'human_read_time_diff' ), $status ),
				'detectedServer'   => Module_Server::get_server_type(),
				'usingCloudflare'  => Utils::get_module( 'cloudflare' )->has_cloudflare(),
				'cloudflareAuthed' => Utils::get_module( 'cloudflare' )->is_connected(),
				'cloudflareSetUp'  => $cloudflare,
				'cloudflareNotice' => get_site_option( 'wphb-cloudflare-dash-notice' ),
			)
		);
	}

	/**
	 * Update expiry rules.
	 *
	 * @since 3.2.0
	 */
	public function update_expiry() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		$expiry_times = array(
			'expiry_javascript' => isset( $data['expires']['JavaScript'] ) ? sanitize_text_field( $data['expires']['JavaScript'] ) : '1y/A31536000',
			'expiry_css'        => isset( $data['expires']['CSS'] ) ? sanitize_text_field( $data['expires']['CSS'] ) : '1y/A31536000',
			'expiry_media'      => isset( $data['expires']['Media'] ) ? sanitize_text_field( $data['expires']['Media'] ) : '1y/A31536000',
			'expiry_images'     => isset( $data['expires']['Images'] ) ? sanitize_text_field( $data['expires']['Images'] ) : '1y/A31536000',
		);
		$submitted_expiry_times = $expiry_times;

		$options          = Utils::get_module( 'caching' )->get_options();
		$previous_options = $options;

		// Do not update our database values if we're adjusting for Cloudflare, because the format is different.
		if ( isset( $data['server'] ) && 'cloudflare' === $data['server'] ) {
			$expiry_times = $options;
		} else {
			$options = wp_parse_args( $expiry_times, $options );
			Utils::get_module( 'caching' )->update_options( $options );
		}

		/**
		 * Pass in caching type and value into a custom function.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args {
		 *     Array of selected type and value.
		 *
		 *     @type string $type                   Type of cached data, can be one of following:
		 *                                          `javascript`, `css`, `media` or `images`.
		 *     @type array  $sanitized_expiry_times Set expiry values (for example, 1h/A3600), first part can be:
		 *                                          `[n]h` for [n] hours (for example, 1h, 4h, 11h, etc),
		 *                                          `[n]d` for [n] days (for example, 1d, 4d, 11d, etc),
		 *                                          `[n]M` for [n] months (for example, 1M, 4M, 11M, etc),
		 *                                          `[n]y` for [n] years (for example, 1y, 4y, 11y, etc),
		 *                                          second part is the first part in seconds ( 1 hour = 3600 sec).
		 * }
		 */
		do_action(
			'wphb_caching_set_expiration',
			array(
				'expiry_times' => $expiry_times,
			)
		);

		$response = array(
			'snippets' => array(
				'apache' => Module_Server::get_code_snippet( 'caching', 'apache' ),
				'nginx'  => Module_Server::get_code_snippet( 'caching', 'nginx' ),
				'iis'    => Module_Server::get_code_snippet( 'caching', 'iis' ),
			),
			'expires'  => array(
				'CSS'        => $options['expiry_css'],
				'JavaScript' => $options['expiry_javascript'],
				'Media'      => $options['expiry_media'],
				'Images'     => $options['expiry_images'],
			),
		);

		if ( isset( $data['server'] ) && in_array( $data['server'], array( 'apache', 'cloudflare' ), true ) ) {
			if ( 'apache' === $data['server'] ) {
				Module_Server::unsave_htaccess( 'caching' );
				$response['htaccessUpdated'] = Module_Server::save_htaccess( 'caching' );
			}

			if ( 'cloudflare' === $data['server'] ) {
				$updated = Utils::get_module( 'cloudflare' )->set_caching_expiration( $data['expires']['CSS'] );

				$response['htaccessUpdated'] = ! is_wp_error( $updated );
			}

			$response['status'] = Utils::get_module( 'caching' )->get_analysis_data( true, true );
			$response['human']  = array_map( array( 'Hummingbird\\Core\\Utils', 'human_read_time_diff' ), $response['status'] );
		}

		$file_type_labels = array(
			'expiry_javascript' => __( 'JavaScript', 'wphb' ),
			'expiry_css'        => __( 'CSS', 'wphb' ),
			'expiry_media'      => __( 'Media', 'wphb' ),
			'expiry_images'     => __( 'Images', 'wphb' ),
		);

		foreach ( $file_type_labels as $key => $label ) {
			$previous_value = isset( $previous_options[ $key ] ) ? (string) $previous_options[ $key ] : '';
			$current_value  = isset( $submitted_expiry_times[ $key ] ) ? (string) $submitted_expiry_times[ $key ] : '';

			if ( $previous_value === $current_value ) {
				continue;
			}

			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: 1: asset type (JavaScript/CSS/Media/Images), 2: duration string. */
					__( '%1$s browser caching expiry set to %2$s.', 'wphb' ),
					$label,
					self::format_expiry_for_log( $current_value )
				)
			);
		}

		if ( isset( $data['server'] ) ) {
			$server_labels = array(
				'apache'     => __( 'Apache', 'wphb' ),
				'nginx'      => __( 'Nginx', 'wphb' ),
				'litespeed'  => __( 'LiteSpeed', 'wphb' ),
				'iis'        => __( 'IIS', 'wphb' ),
				'cloudflare' => __( 'Cloudflare', 'wphb' ),
			);

			$server = sanitize_key( $data['server'] );
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: server type. */
					__( 'Browser caching configured for %s server type.', 'wphb' ),
					$server_labels[ $server ] ?? ucfirst( $server )
				)
			);
		}

		wp_send_json_success( $response );
	}

	/**
	 * Convert an expiry token (for example `1y/A31536000`) into readable text.
	 *
	 * @param string $expiry_value Expiry token.
	 *
	 * @return string
	 */
	private static function format_expiry_for_log( $expiry_value ) {
		if ( preg_match( '/^(\\d+)([hdMy])\\/A\\d+$/', $expiry_value, $matches ) ) {
			$value = (int) $matches[1];
			$unit  = $matches[2];
			$labels = array(
				'h' => _n( 'hour', 'hours', $value, 'wphb' ),
				'd' => _n( 'day', 'days', $value, 'wphb' ),
				'M' => _n( 'month', 'months', $value, 'wphb' ),
				'y' => _n( 'year', 'years', $value, 'wphb' ),
			);

			return sprintf( '%1$d %2$s', $value, $labels[ $unit ] ?? '' );
		}

		return $expiry_value;
	}

	/**
	 * Clear Cloudflare cache.
	 *
	 * @since 3.2.0
	 */
	public function clear_cache() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_module( 'cloudflare' )->clear_cache();
		wp_send_json_success();
	}

}
