<?php
/**
 * Setup wizard AJAX actions.
 *
 * @since 3.3.1
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Caching\Fast_CGI;

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
		add_action( 'wp_ajax_wphb_react_check_requirements', array( $this, 'check_requirements' ) );
		add_action( 'wp_ajax_wphb_react_remove_advanced_cache', array( $this, 'remove_advanced_cache' ) );
		add_action( 'wp_ajax_wphb_react_disable_fast_cgi', array( $this, 'disable_fast_cgi' ) );
		add_action( 'wp_ajax_wphb_react_cancel_wizard', array( $this, 'cancel' ) );
		add_action( 'wp_ajax_wphb_react_complete_wizard', array( $this, 'complete' ) );
		add_action( 'wp_ajax_wphb_react_settings', array( $this, 'update_settings' ) );
		add_action( 'wp_ajax_wphb_react_track_user_consent_toggle', array( $this, 'track_user_consent_toggle' ) );
	}

	/**
	 * Check setup requirements.
	 *
	 * @since 3.3.1
	 */
	public function check_requirements() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$status = array(
			'advCacheFile' => false,
			'fastCGI'      => Utils::get_api()->hosting->has_fast_cgi(),
		);

		// Check for advanced-cache.php conflicts.
		if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			$advanced_cache         = file_get_contents( WP_CONTENT_DIR . '/advanced-cache.php' );
			$status['advCacheFile'] = false === strpos( $advanced_cache, 'WPHB_ADVANCED_CACHE' );
		}

		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * Remove the advanced-cache.php file.
	 *
	 * @since 3.3.1
	 *
	 * @return void
	 */
	public function remove_advanced_cache() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$adv_cache_file = dirname( get_theme_root() ) . '/advanced-cache.php';
		if ( file_exists( $adv_cache_file ) ) {
			unlink( $adv_cache_file );
		}

		$this->check_requirements();
	}

	/**
	 * Disable FastCGI cache on WPMU DEV hosting.
	 *
	 * @since 3.3.1
	 *
	 * @return void
	 */
	public function disable_fast_cgi() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_api()->hosting->toggle_fast_cgi( false );

		$this->check_requirements();
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

		update_option( 'wphb_run_onboarding', null );
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
			Utils::get_module( 'mixpanel_analytics' )->track_event_for_setup_performance_test();
		}

		update_option( 'wphb_run_onboarding', null );
		wp_send_json_success();
	}

	/**
	 * Update settings.
	 *
	 * @since 3.3.1
	 *
	 * @return void
	 */
	public function update_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$settings = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		// Tracking (make sure it's always updated).
		if ( is_admin() || ( is_multisite() && is_network_admin() ) ) {
			$tracking = isset( $settings['tracking'] ) && $settings['tracking'];
			Settings::update_setting( 'tracking', $tracking, 'settings' );
		}

		if ( 'ao' === $settings['module'] ) {
			if ( Utils::is_ajax_network_admin() ) {
				// On network admin we have a different set of options.
				$value = isset( $settings['enable'] ) && $settings['enable'];
				Utils::get_module( 'minify' )->toggle_service( $value, true );
			}

			if ( isset( $settings['enable'] ) && $settings['enable'] ) {
				$options = Settings::get_settings( 'minify' );

				$options['enabled']   = isset( $settings['enable'] ) && $settings['enable'];
				$options['compress']  = isset( $settings['aoCompress'] ) && $settings['aoCompress'];
				$options['combine']   = isset( $settings['aoCombine'] ) && $settings['aoCombine'];
				$options['use_cdn']   = isset( $settings['aoCdn'] ) && $settings['aoCdn'];
				$options['delay_js']  = isset( $settings['delayJS'] ) && $settings['delayJS'];
				$options['font_swap'] = isset( $settings['delayJS'] ) && $settings['fontSwap'];

				Settings::update_settings( $options, 'minify' );

				// Starts the AO scan.
				Utils::get_module( 'minify' )->start_process();

				// Enable/disable critical CSS and generate it if enabled.
				$critical_css = isset( $settings['criticalCSS'] ) && $settings['criticalCSS'];
				Utils::get_module( 'critical_css' )->toggle_critical_css( $critical_css );

				// Track mixpanel events.
				$options = Utils::get_module( 'minify' )->get_options();

				// Track Mixpanel event for delay JS.
				$update_type = ! empty( $options['delay_js'] ) ? 'activate' : 'deactivate';
				Utils::get_module( 'mixpanel_analytics' )->track_delay_js_event( $update_type, 'wizard' );

				// Track Mixpanel event for critical CSS.
				$update_type = ! empty( $options['critical_css'] ) ? 'activate' : 'deactivate';
				Utils::get_module( 'mixpanel_analytics' )->track_critical_css_event( $update_type, 'wizard' );
			} elseif ( ! Utils::is_ajax_network_admin() ) {
				Utils::get_module( 'minify' )->disable();
			}
		} elseif ( 'uptime' === $settings['module'] && Utils::get_module( 'uptime' )->has_access() ) {
			if ( isset( $settings['enable'] ) && $settings['enable'] ) {
				Utils::get_module( 'uptime' )->enable();
			} else {
				Utils::get_module( 'uptime' )->disable();
			}
		} elseif ( 'caching' === $settings['module'] ) {
			if ( Utils::is_ajax_network_admin() ) {
				define( 'WPHB_IS_NETWORK_ADMIN', true );
			}

			if ( ! empty( $settings['fastCGI'] ) && Fast_CGI::is_fast_cgi_supported() ) {
				if ( ! Utils::get_api()->hosting->has_fast_cgi() ) {
					Utils::get_api()->hosting->toggle_fast_cgi( true );
				}

				$control = isset( $settings['clearCacheButton'] ) && $settings['clearCacheButton'];
				Settings::update_setting( 'control', $control, 'settings' );
				$caching_setting                              = Utils::get_module( 'page_cache' )->get_settings();
				$caching_setting['settings']['comment_clear'] = (int) ( isset( $settings['clearOnComment'] ) && $settings['clearOnComment'] );

				Utils::get_module( 'page_cache' )->save_settings( $caching_setting );
			} elseif ( isset( $settings['enable'] ) && $settings['enable'] ) {
				Utils::get_module( 'page_cache' )->enable( true );

				$caching_setting = Utils::get_module( 'page_cache' )->get_settings();

				$caching_setting['settings']['cache_headers'] = (int) ( isset( $settings['cacheHeader'] ) && $settings['cacheHeader'] );
				$caching_setting['settings']['mobile']        = (int) ( isset( $settings['cacheOnMobile'] ) && $settings['cacheOnMobile'] );
				$caching_setting['settings']['comment_clear'] = (int) ( isset( $settings['clearOnComment'] ) && $settings['clearOnComment'] );

				Utils::get_module( 'page_cache' )->save_settings( $caching_setting );

				$control = isset( $settings['clearCacheButton'] ) && $settings['clearCacheButton'];
				Settings::update_setting( 'control', $control, 'settings' );

				// Disable FastCGI if it's enabled.
				if ( Utils::get_api()->hosting->has_fast_cgi_header() ) {
					Utils::get_api()->hosting->toggle_fast_cgi( false );
				}
			} else {
				Utils::get_module( 'page_cache' )->disable();

				// Disable FastCGI if it's enabled.
				if ( Utils::get_api()->hosting->has_fast_cgi_header() ) {
					Utils::get_api()->hosting->toggle_fast_cgi( false );
				}
			}
		} else {
			$options = Settings::get_settings( 'advanced' );

			// Advanced tools options.
			$options['query_string']   = isset( $settings['queryStrings'] ) && $settings['queryStrings'];
			$options['cart_fragments'] = isset( $settings['cartFragments'] ) && $settings['cartFragments'];
			$options['emoji']          = isset( $settings['removeEmoji'] ) && $settings['removeEmoji'];

			Settings::update_settings( $options, 'advanced' );
		}

		wp_send_json_success();
	}

	/**
	 * Track user consent toggle.
	 *
	 * @since 3.10.0
	 *
	 * @return void
	 */
	public function track_user_consent_toggle() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$tracking       = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$tracking       = json_decode( html_entity_decode( $tracking ), true );
		$tracking_value = Settings::get_setting( 'tracking', 'settings' );

		if ( $tracking_value !== $tracking ) {
			Settings::update_setting( 'tracking', $tracking, 'settings' );
			do_action( 'wphb_mixpanel_usage_tracking_value_update', true, $tracking );
		}

		wp_send_json_success();
	}
}
