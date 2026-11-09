<?php
/**
 * React Other Settings AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Logger;
use Hummingbird\Core\Modules\Minify;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\WP_Hummingbird;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Other_Settings.
 */
class Other_Settings {
	const ACTIVITY_MODULE = 'other_settings';

	/**
	 * Other_Settings constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'other_settings_save',
			'other_settings_reset',
			'other_settings_clear_log',
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Register callbacks.
			 *
			 * @uses other_settings_save()
			 * @uses other_settings_reset()
			 * @uses other_settings_clear_log()
			 */
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	/**
	 * Persist the Other Settings panel.
	 *
	 * @since 4.0.0
	 */
	public function other_settings_save() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing settings data.', 'wphb' ) ) );
		}

		if ( $this->is_subsite_request() ) {
			$minify_options = Utils::get_module( 'minify' )->get_options();
			$allowed        = true === $minify_options['enabled']
				|| ( 'super-admins' === $minify_options['enabled'] && is_super_admin() );

			if ( ! $allowed ) {
				wp_send_json_error(
					array( 'message' => __( 'You do not have permission to configure Asset Optimization.', 'wphb' ) ),
					403
				);
			}

			if ( isset( $data['view'] ) ) {
				$view = sanitize_key( $data['view'] );
				if ( in_array( $view, array( 'basic', 'advanced' ), true ) ) {
					$this->update_dev_mode_view( $view );

					if ( 'advanced' === $view ) {
						$minify = Utils::get_module( 'minify' );
						if ( ! $minify->is_active() ) {
							$minify->toggle_service( true );
							$minify->start_process();
						}
					}
				}
			}

			if ( isset( $data['dismiss_asset_mode_modal'] ) ) {
				$modal = sanitize_key( $data['dismiss_asset_mode_modal'] );
				if ( 'advanced' === $modal ) {
					delete_option( 'wphb-minification-show-advanced_modal' );
				} elseif ( 'basic' === $modal ) {
					delete_option( 'wphb-minification-show-config_modal' );
				}
			}

			wp_send_json_success( $this->get_state() );
		}

		$settings          = Settings::get_settings( 'settings' );
		$existing_settings = $settings;
		$prev_tracking     = $settings['tracking'];

		foreach ( array( 'accessible_colors', 'remove_settings', 'remove_data', 'tracking' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$settings[ $key ] = (bool) $data[ $key ];
			}
		}

		if ( array_key_exists( 'control', $data ) ) {
			$settings['control'] = $this->sanitize_cache_control( $data['control'] );

			/**
			 * Remember the custom selection, so switching to "All cache" and back
			 * does not discard the cache types the user picked.
			 */
			if ( is_array( $settings['control'] ) ) {
				$settings['control_modules'] = $settings['control'];
			}
		}

		Settings::update_settings( $settings, 'settings' );
		$this->handle_activity_logs( $existing_settings, $settings );

		if ( isset( $data['view'] ) ) {
			$view = sanitize_key( $data['view'] );
			if ( in_array( $view, array( 'basic', 'advanced' ), true ) ) {
				$this->update_dev_mode_view( $view );

				// Dev Mode needs the Asset Optimization service and its scanned.
				if ( 'advanced' === $view ) {
					$minify = Utils::get_module( 'minify' );
					if ( ! $minify->is_active() ) {
						$minify->toggle_service( true, Utils::is_ajax_network_admin() );
						$minify->start_process();
					}
				}
			}
		}

		if ( isset( $data['dismiss_asset_mode_modal'] ) ) {
			$modal = sanitize_key( $data['dismiss_asset_mode_modal'] );
			if ( 'advanced' === $modal ) {
				delete_option( 'wphb-minification-show-advanced_modal' );
			} elseif ( 'basic' === $modal ) {
				delete_option( 'wphb-minification-show-config_modal' );
			}
		}

		if ( $prev_tracking !== $settings['tracking'] ) {
			$location = isset( $data['tracking_source'] ) && 'share_usage_notice' === $data['tracking_source'] ? 'Share Usage Notice' : 'settings';
			do_action( 'wphb_mixpanel_usage_tracking_value_update', true, $settings['tracking'], $location );
		}

		if ( array_key_exists( 'debug_log', $data ) ) {
			$debug_log = (bool) $data['debug_log'];

			$page_cache          = Utils::get_module( 'page_cache' );
			$page_cache_settings = $page_cache->get_settings();
			$previous_settings   = $page_cache_settings;

			$was_debug_log_enabled = (bool) $page_cache_settings['settings']['debug_log'] || (bool) Settings::get_setting( 'log', 'minify' );

			$page_cache_settings['settings']['debug_log'] = (int) $debug_log;
			$page_cache->save_settings( $page_cache_settings );

			Settings::update_setting( 'log', $debug_log, 'minify' );

			Activity_Log::record_toggle_when_changed(
				self::ACTIVITY_MODULE,
				$was_debug_log_enabled,
				$debug_log,
				__( 'Debug log enabled.', 'wphb' ),
				__( 'Debug log disabled.', 'wphb' )
			);
			// Same page cache setting as the Page Caching tab, so it reports the same event.
			Utils::get_module( 'mixpanel_analytics' )->events( 'page_cache' )->track_settings_change( $previous_settings, $page_cache_settings );
		}

		wp_send_json_success( $this->get_state() );
	}

	/**
	 * Save the Dev Mode view and log changes to its enabled state.
	 *
	 * @since 4.0.0
	 *
	 * @param string $view Validated Asset Optimization view.
	 */
	private function update_dev_mode_view( $view ) {
		$previous_view = Settings::get_setting( 'view', 'minify' );
		Settings::update_setting( 'view', $view, 'minify' );
		$current_view = Settings::get_setting( 'view', 'minify' );

		if ( 'advanced' === $previous_view && 'basic' === $current_view ) {
			Utils::get_module( 'minify' )->clear_cache( true, false, true );
		}

		Activity_Log::record_toggle_when_changed(
			'asset-optimization',
			'advanced' === $previous_view,
			'advanced' === $current_view,
			__( 'Dev Mode enabled.', 'wphb' ),
			__( 'Dev Mode disabled.', 'wphb' )
		);

		if ( $previous_view !== $current_view ) {
			Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_ao_updated();
		}
	}

	/**
	 * Whether this request came from a subsite admin screen on a network.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	private function is_subsite_request() {
		if ( ! is_multisite() ) {
			return false;
		}

		return ! is_network_admin() && ! Utils::is_ajax_network_admin();
	}

	/**
	 * Delete a single debug log file.
	 *
	 * @since 4.0.0
	 */
	public function other_settings_clear_log() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data   = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data   = json_decode( html_entity_decode( $data ), true );
		$module = isset( $data['module'] ) ? sanitize_key( $data['module'] ) : '';

		if ( ! in_array( $module, Logger::DOWNLOADABLE_MODULES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown log file.', 'wphb' ) ) );
		}

		if ( is_multisite() ) {
			$is_subsite = $this->is_subsite_request();

			if ( ( 'minify' === $module && ! $is_subsite ) || ( 'page_cache' === $module && $is_subsite ) ) {
				wp_send_json_error( array( 'message' => __( 'That log file cannot be cleared from here.', 'wphb' ) ) );
			}
		}

		$logger = WP_Hummingbird::get_instance()->core->logger;

		$logger->clear( $module );

		wp_send_json_success( array( 'logs' => $logger->get_existing_logs() ) );
	}

	/**
	 * Reset the plugin back to factory defaults.
	 *
	 * @since 4.0.0
	 */
	public function other_settings_reset() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		SafeMode::instance()->discard_cache_config_file();
		SafeMode::instance()->delete_data( 'reset' );

		WP_Hummingbird::flush_cache();
		Utils::get_module( 'page_cache' )->toggle_service( false );

		Utils::get_module( 'cloudflare' )->toggle_apo( false );

		$tracking_value = Settings::get_setting( 'tracking', 'settings' );
		Settings::reset_to_defaults();
		Minify::save_css( '', 'manual-critical' );

		// Disable FastCGI if it's enabled.
		if ( Utils::get_api()->hosting->has_fast_cgi_header() ) {
			Utils::get_api()->hosting->toggle_fast_cgi( false );
		}

		// Remove configs.
		delete_site_option( 'wphb-preset_configs' );

		update_option( 'wphb_run_onboarding', true );
		update_option( 'wphb-minification-show-config_modal', true );
		update_option( 'wphb-minification-show-advanced_modal', true );
		delete_option( 'wphb-hide-tutorials' );

		// Clean all cron.
		wp_clear_scheduled_hook( 'wphb_performance_report' );
		wp_clear_scheduled_hook( 'wphb_uptime_report' );
		wp_clear_scheduled_hook( 'wphb_database_report' );
		wp_clear_scheduled_hook( 'wphb_minify_clear_files' );

		// Reset notices.
		delete_option( Notices::OPTION_NAME );
		delete_metadata( 'user', 0, Notices::USER_META_NAME, '', true );

		if ( is_multisite() ) {
			global $wpdb;
			$offset = 0;
			$limit  = 100;
			while ( $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs} LIMIT {$offset}, {$limit}", ARRAY_A ) ) { // Db call ok; no-cache ok.
				foreach ( $blogs as $blog ) {
					switch_to_blog( $blog['blog_id'] );

					Settings::reset_to_defaults();
					update_option( 'wphb_run_onboarding', true );
					update_option( 'wphb-minification-show-config_modal', true );
					update_option( 'wphb-minification-show-advanced_modal', true );

					// Clean all cron.
					wp_clear_scheduled_hook( 'wphb_minify_clear_files' );
				}
				restore_current_blog();
				$offset += $limit;
			}
		}

		if ( $tracking_value ) {
			do_action( 'wphb_mixpanel_usage_tracking_value_update', true, false, 'reset' );
		}

		// record activity log reset.
		Activity_Log::record(
			self::ACTIVITY_MODULE,
			__( 'Settings reset to defaults.', 'wphb' )
		);

		wp_send_json_success();
	}

	/**
	 * Normalize the cache control shortcut value.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $control  Raw value: false, true, or a list of module keys.
	 *
	 * @return bool|array
	 */
	private function sanitize_cache_control( $control ) {
		if ( ! is_array( $control ) ) {
			return (bool) $control;
		}

		$available = array_keys( Utils::get_cache_modules() );

		return array_values( array_intersect( $available, array_map( 'sanitize_key', $control ) ) );
	}

	/**
	 * Get render data.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_state() {
		$settings = Settings::get_settings( 'settings' );

		// Read from the config file, the same place save_settings() writes to.
		$page_cache = Utils::get_module( 'page_cache' )->get_settings();

		return array(
			'view'                   => Settings::get_setting( 'view', 'minify' ),
			'showDevModeModal'       => (bool) get_option( 'wphb-minification-show-advanced_modal' ),
			'showAutomaticModeModal' => (bool) get_option( 'wphb-minification-show-config_modal' ),
			'accessibleColors'       => (bool) $settings['accessible_colors'],
			'tracking'               => (bool) $settings['tracking'],
			'removeSettings'         => (bool) $settings['remove_settings'],
			'removeData'             => (bool) $settings['remove_data'],
			'control'                => $settings['control'],
			'controlModules'         => Utils::get_cache_control_modules(),
			'debugLog'               => (bool) $page_cache['settings']['debug_log'] || (bool) Settings::get_setting( 'log', 'minify' ),
			'cacheModules'           => Utils::get_cache_modules(),
			'version'                => WPHB_VERSION,
		);
	}

	/**
	 * Handle activity logs for Other Settings changes.
	 *
	 * @since 4.0.0
	 *
	 * @param array $existing_settings The existing settings.
	 * @param array $new_settings      The new settings.
	 *
	 * @return void
	 */
	private function handle_activity_logs( $existing_settings, $new_settings ) {
		$was_tracking_enabled = isset( $existing_settings['tracking'] ) ? (bool) $existing_settings['tracking'] : false;
		$is_tracking_enabled  = isset( $new_settings['tracking'] ) ? (bool) $new_settings['tracking'] : false;

		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_tracking_enabled,
			$is_tracking_enabled,
			__( 'Usage tracking enabled.', 'wphb' ),
			__( 'Usage tracking disabled.', 'wphb' )
		);

		// clear_cache shortcut.
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			isset( $existing_settings['control'] ) ? (bool) $existing_settings['control'] : false,
			isset( $new_settings['control'] ) ? (bool) $new_settings['control'] : false,
			__( 'Clear cache shortcut enabled.', 'wphb' ),
			__( 'Clear cache shortcut disabled.', 'wphb' )
		);

		// accessible_colors.
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			isset( $existing_settings['accessible_colors'] ) ? (bool) $existing_settings['accessible_colors'] : false,
			isset( $new_settings['accessible_colors'] ) ? (bool) $new_settings['accessible_colors'] : false,
			__( 'Accessible colors enabled.', 'wphb' ),
			__( 'Accessible colors disabled.', 'wphb' )
		);

		// preserve settings/data (remove_settings and remove_data are toggled together).
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			isset( $existing_settings['remove_settings'] ) ? (bool) $existing_settings['remove_settings'] : false,
			isset( $new_settings['remove_settings'] ) ? (bool) $new_settings['remove_settings'] : false,
			__( 'Preserve settings disabled.', 'wphb' ),
			__( 'Preserve settings enabled.', 'wphb' )
		);
	}
}
