<?php
/**
 * Others Caching AJAX actions for the new React UI (Gravatar, RSS).
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax\Caching
 */

namespace Hummingbird\Admin\Ajax\Caching;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Others
 */
class Others {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'caching';

	/**
	 * Others constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'others_status',
			'gravatar_toggle',
			'gravatar_clear',
			'rss_toggle',
			'rss_save_settings',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Get combined Gravatar + RSS caching status.
	 *
	 * @since 4.0.0
	 */
	public function others_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$gravatar    = Utils::get_module( 'gravatar' );
		$rss         = Utils::get_module( 'rss' );
		$rss_options = $rss->get_options();

		$gravatar_error = is_wp_error( $gravatar->error ) ? $gravatar->error->get_error_message() : false;

		wp_send_json_success(
			array(
				'gravatar' => array(
					'enabled' => $gravatar_error ? false : $gravatar->is_active(),
					'error'   => $gravatar_error,
				),
				'rss'      => array(
					'enabled'  => $rss->is_active(),
					'duration' => isset( $rss_options['duration'] ) ? (int) $rss_options['duration'] : 3600,
				),
			)
		);
	}

	/**
	 * Enable or disable Gravatar caching.
	 *
	 * @since 4.0.0
	 */
	public function gravatar_toggle() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$enable     = filter_input( INPUT_POST, 'data', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$gravatar   = Utils::get_module( 'gravatar' );
		$was_active = $gravatar->is_active();

		if ( $enable ) {
			$gravatar->enable();
		} else {
			$gravatar->disable();
			$gravatar->clear_cache();
		}

		Activity_Log::record_toggle(
			self::ACTIVITY_MODULE,
			$enable,
			__( 'Gravatar caching enabled.', 'wphb' ),
			__( 'Gravatar caching disabled.', 'wphb' )
		);
		Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'Gravatar Caching', $was_active, $gravatar->is_active() );

		do_action( 'wphb_clear_page_cache' );

		wp_send_json_success( array( 'enabled' => (bool) $enable ) );
	}

	/**
	 * Clear all cached Gravatar images.
	 *
	 * @since 4.0.0
	 */
	public function gravatar_clear() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		// A bool from Filesystem::purge().
		if ( ! Utils::get_module( 'gravatar' )->clear_cache() ) {
			wp_send_json_error(
				array(
					'message' => __( 'There was an error during the cache purge. Check folder permissions are 755 for /wp-content/wphb-cache or delete directory manually.', 'wphb' ),
				)
			);
		}

		// Cached pages still point at the purged avatar files.
		do_action( 'wphb_clear_page_cache' );

		wp_send_json_success(
			array(
				'message' => __( 'Gravatar cache purged.', 'wphb' ),
			)
		);
	}

	/**
	 * Enable or disable RSS caching.
	 *
	 * @since 4.0.0
	 */
	public function rss_toggle() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$enable     = filter_input( INPUT_POST, 'data', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$rss        = Utils::get_module( 'rss' );
		$was_active = $rss->is_active();

		if ( $enable ) {
			$rss->enable();
		} else {
			$rss->disable();
		}

		Activity_Log::record_toggle(
			self::ACTIVITY_MODULE,
			$enable,
			__( 'RSS caching enabled.', 'wphb' ),
			__( 'RSS caching disabled.', 'wphb' )
		);
		Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'RSS Caching', $was_active, $rss->is_active() );

		do_action( 'wphb_clear_page_cache' );

		wp_send_json_success( array( 'enabled' => (bool) $enable ) );
	}

	/**
	 * Save RSS caching settings.
	 *
	 * Expects data: { duration: <seconds as integer> }
	 *
	 * @since 4.0.0
	 */
	public function rss_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) || ! isset( $data['duration'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$rss               = Utils::get_module( 'rss' );
		$options           = $rss->get_options();
		$previous_duration = isset( $options['duration'] ) ? (int) $options['duration'] : 0;

		$options['duration'] = max( 0, (int) $data['duration'] );
		$rss->update_options( $options );

		$selected_unit  = isset( $data['unit'] ) ? sanitize_text_field( $data['unit'] ) : 'seconds';
		$selected_value = isset( $data['value'] ) ? (int) $data['value'] : 0;

		switch ( $selected_unit ) {
			case 'hours':
				$selected_unit = _n( 'hour', 'hours', $selected_value, 'wphb' );
				break;
			case 'minutes':
				$selected_unit = _n( 'minute', 'minutes', $selected_value, 'wphb' );
				break;
			default:
				$selected_unit = _n( 'second', 'seconds', $selected_value, 'wphb' );
				break;
		}

		if ( $previous_duration !== (int) $options['duration'] ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %d: RSS cache expiry duration in seconds. */
					__( 'RSS caching expiry time set to %1$s %2$s.', 'wphb' ),
					$selected_value,
					$selected_unit
				)
			);
		}

		do_action( 'wphb_clear_page_cache' );

		wp_send_json_success( array( 'duration' => $options['duration'] ) );
	}
}
