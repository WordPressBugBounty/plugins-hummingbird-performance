<?php
/**
 * Handle all AJAX actions in admin side
 *
 * @package Hummingbird
 */

namespace Hummingbird\Admin;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Core\Integration\Opcache;
use Hummingbird\Core\Modules\Caching\Preload;
use Hummingbird\Core\Modules\Page_Cache;
use Hummingbird\Core\Modules\Performance;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Caching\Fast_CGI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJAX
 *
 * @package Hummingbird\Admin
 */
class AJAX {
	/**
	 * AJAX constructor.
	 */
	public function __construct() {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return;
		}

		// Parse clear cache click from frontend admin bar.
		add_action( 'wp_ajax_wphb_front_clear_cache', array( $this, 'clear_frontend_cache' ) );
		// Parse clear full cache from admin notice.
		add_action( 'wp_ajax_wphb_global_clear_cache', array( $this, 'clear_global_cache' ) );
		// Clear selected module cache.
		add_action( 'wp_ajax_wphb_clear_caches', array( $this, 'clear_modules_cache' ) );
		// Clear Cloudflare cache from admin bar.
		add_action( 'wp_ajax_wphb_front_clear_cloudflare', array( $this, 'clear_frontend_cloudflare' ) );

		/**
		 * Multisite global cache clear.
		 *
		 * @since 2.7.0
		 */
		if ( is_multisite() ) {
			// Get the total number of sites in a network.
			add_action( 'wp_ajax_wphb_get_network_sites', array( $this, 'get_network_sites' ) );
			// Clear cache on network subsites.
			add_action( 'wp_ajax_wphb_clear_network_cache', array( $this, 'clear_network_cache' ) );
		}

		/**
		 * PERFORMANCE TEST AJAX ACTIONS
		 */

		// Run performance test.
		add_action( 'wp_ajax_wphb_performance_run_test', array( $this, 'performance_run_test' ) );

		/**
		 * CACHING MODULE AJAX ACTIONS
		 */

		// Clear cache.
		add_action( 'wp_ajax_wphb_clear_module_cache', array( $this, 'clear_module_cache' ) );

		/* PAGE CACHING */

		// Save page caching settings.
		add_action( 'wp_ajax_wphb_page_cache_save_settings', array( $this, 'page_cache_save_settings' ) );
		// Save page caching settings.
		add_action( 'wp_ajax_wphb_fast_cgi_save_settings', array( $this, 'fast_cgi_save_settings' ) );
		// Gutenberg clear cache for post.
		add_action( 'wp_ajax_wphb_gutenberg_clear_post_cache', array( $this, 'gutenberg_clear_post_cache' ) );
		// Cancel cache preload.
		add_action( 'wp_ajax_wphb_preload_cancel', array( $this, 'cancel_cache_preload' ) );
		// Remove advanced-cache.php file.
		add_action( 'wp_ajax_wphb_remove_advanced_cache', array( $this, 'remove_advanced_cache' ) );
		// Disable FastCGI cache.
		add_action( 'wp_ajax_wphb_disable_fast_cgi', array( $this, 'disable_fast_cgi' ) );
		// Switch Cache method.
		add_action( 'wp_ajax_wphb_switch_cache_method', array( $this, 'switch_cache_method' ) );

		/* INTEGRATIONS */

		// Cloudflare purge cache.
		add_action( 'wp_ajax_wphb_cloudflare_purge_cache', array( $this, 'cloudflare_purge_cache' ) );
		// Cloudflare recheck zones.
		add_action( 'wp_ajax_wphb_cloudflare_recheck_zones', array( $this, 'cloudflare_recheck_zones' ) );

		/**
		 * ADVANCED TOOLS AJAX ACTIONS
		 */

		// Clean database.
		add_action( 'wp_ajax_wphb_advanced_db_delete_data', array( $this, 'advanced_db_delete_data' ) );
		// Save settings in advanced tools module.
		add_action( 'wp_ajax_wphb_advanced_save_settings', array( $this, 'advanced_save_settings' ) );
		// Purge cache preloader.
		add_action( 'wp_ajax_wphb_advanced_purge_cache', array( $this, 'advanced_purge_cache' ) );
		// Purge asset optimization groups.
		add_action( 'wp_ajax_wphb_advanced_purge_minify', array( $this, 'advanced_purge_minify' ) );
		// Purge asset optimization orphaned data.
		add_action( 'wp_ajax_wphb_advanced_purge_orphaned', array( $this, 'advanced_purge_orphaned' ) );

		// Create Critical Css.
		add_action( 'wp_ajax_wphb_gutenberg_create_css_file', array( $this, 'gutenberg_create_css_file' ) );
		// Re-Create Critical Css.
		add_action( 'wp_ajax_wphb_gutenberg_recreate_css_file', array( $this, 'gutenberg_recreate_css_file' ) );
		// Revert Critical Css.
		add_action( 'wp_ajax_wphb_gutenberg_revert_css_file', array( $this, 'gutenberg_revert_css_file' ) );
		// Get Critical Css status for single post.
		add_action( 'wp_ajax_wphb_gutenberg_get_critical_status_for_single_post', array( $this, 'gutenberg_get_critical_status_for_single_post' ) );

		// Switch critical CSS.
		add_action( 'wp_ajax_wphb_switch_to_critical_css_from_legacy', array( $this, 'wphb_switch_to_critical_css_from_legacy' ) );
	}

	/**
	 * Handle clear cache button click from the frontend top admin bar.
	 *
	 * @since 1.9.3
	 */
	public function clear_frontend_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$pc_module = Utils::get_module( 'page_cache' );
		$status    = $pc_module->clear_cache();

		if ( ! $status ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Handle clear cache button click from the frontend top admin bar.
	 *
	 * @since 1.9.3
	 */
	public function clear_global_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );
		$all_clear = filter_input( INPUT_GET, 'all_clear', FILTER_SANITIZE_NUMBER_INT );
		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$modules = Utils::get_active_cache_modules();

		foreach ( $modules as $module => $name ) {
			$mod = Utils::get_module( $module );

			if ( ! $mod->is_active() ) {
				continue;
			}

			if ( 'minify' === $module ) {
				$mod->clear_files();
			} else {
				$mod->clear_cache();
			}
		}

		// Remove notice.
		delete_option( 'wphb-notice-cache-cleaned-show' );
		if ( (bool) $all_clear ) {
			update_option(
				'wphb-notice-cache-global-cleared-show',
				array(
					'time'    => time(),
					'modules' => '',
				)
			);
		}

		wp_send_json_success();
	}

	/**
	 * Clear cache from selected modules.
	 *
	 * @since 2.7.1
	 */
	public function clear_modules_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$modules = filter_input( INPUT_POST, 'modules', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( ! $modules ) {
			wp_send_json_success();
		}

		// Do not clear Varnish cache.
		if ( ! in_array( 'varnish', $modules, true ) ) {
			remove_action( 'wphb_clear_cache_url', array( Utils::get_module( 'page_cache' ), 'clear_external_cache' ) );
		} else {
			$key = array_search( 'varnish', $modules, true );
			unset( $modules[ $key ] );

			// Page cache is disabled in modules... Oh well, force manual purge.
			if ( ! in_array( 'page_cache', $modules, true ) ) {
				Utils::get_api()->varnish->purge_cache( '' );
			}
		}

		// Do not clear Opcache.
		if ( ! in_array( 'opcache', $modules, true ) ) {
			remove_action( 'wphb_clear_cache_url', array( Opcache::get_instance(), 'purge_cache' ) );
		} else {
			$key = array_search( 'opcache', $modules, true );
			unset( $modules[ $key ] );

			// Page cache is disabled in modules... Oh well, force manual purge.
			if ( ! in_array( 'page_cache', $modules, true ) ) {
				Opcache::get_instance()->purge_cache();
			}
		}

		$string_for_notice = '';

		if ( $modules ) {
			$strings_map = array(
				'page_cache' => 'Page',
				'minify'     => 'Asset Optimization',
				'rss_cache'  => 'RSS',
				'opcache'    => 'OPcache',
				'fast_cgi'   => 'FastCGI ',
				'varnish'    => 'Varnish',
				'gravatar'   => 'Gravatar',
			);
			$mapped      = array();
			foreach ( $modules as $module ) {
				$mapped[] = isset( $strings_map[ $module ] ) ? $strings_map[ $module ] : ucfirst( $module );
			}
			$string_for_notice = implode( ', ', $mapped );
		}

		foreach ( $modules as $module ) {
			$mod = Utils::get_module( $module );

			if ( false === $mod || ! $mod->is_active() ) {
				continue;
			}

			if ( 'minify' === $module ) {
				$mod->clear_files();
			} else {
				$mod->clear_cache();
			}
		}

		update_option(
			'wphb-notice-cache-global-cleared-show',
			array(
				'time'    => time(),
				'modules' => $string_for_notice,
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Cache purged.', 'wphb' ),
			)
		);
	}

	/**
	 * Clear Cloudflare cache from admin bar.
	 *
	 * @since 2.7.2
	 */
	public function clear_frontend_cloudflare() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$status = Utils::get_module( 'cloudflare' )->clear_cache();

		if ( ! $status ) {
			wp_send_json_error();
		}

		update_option(
			'wphb-notice-cache-global-cleared-show',
			array(
				'time'    => time(),
				'modules' => 'Cloudflare',
			)
		);

		wp_send_json_success();
	}

	/**
	 * Get the number of subsites in a network.
	 *
	 * @since 2.7.0
	 */
	public function get_network_sites() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		global $wpdb;

		$count = wp_cache_get( 'wphb_network_subsites' );

		if ( false === $count ) {
			$count = $wpdb->get_var( "SELECT COUNT( blog_id ) FROM $wpdb->blogs" ); // Db call ok.
		}

		wp_cache_set( 'wphb_network_subsites', $count );

		wp_send_json_success( $count );
	}

	/**
	 * Clear a batch of network subsite caches.
	 *
	 * @since 2.7.0
	 */
	public function clear_network_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		// Note: we can not use Utils::get_admin_capability() because is_network_admin() does not work in ajax request.
		if ( ! current_user_can( 'manage_network' ) ) {
			die();
		}

		$sites  = filter_input( INPUT_POST, 'sites', FILTER_SANITIZE_NUMBER_INT );
		$offset = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_NUMBER_INT );

		$args = array(
			'number' => (int) $sites,
			'offset' => (int) $offset,
		);

		$sites = get_sites( $args );

		// This is quick hack to force the page cache module to not clear only main site cache.
		$http_host_backup = '';
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$http_host_backup     = wp_unslash( $_SERVER['HTTP_HOST'] );
			$_SERVER['HTTP_HOST'] = '';
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			Utils::get_module( 'page_cache' )->clear_cache( $site->domain . $site->path, false, false );
		}

		// Revert the HTTP_HOST value back.
		if ( ! empty( $http_host_backup ) ) {
			$_SERVER['HTTP_HOST'] = $http_host_backup;
		}

		// Reset cached pages count.
		Page_Cache::set_cached_pages_count( 0 );

		restore_current_blog();
		wp_send_json_success();
	}

	/**
	 * *************************
	 * PERFORMANCE TEST AJAX ACTIONS
	 ***************************/

	/**
	 * Run performance test.
	 *
	 * Ajax will trigger this method every 3 seconds, until 'finished' = true.
	 * Logic behind this:
	 * - Remove quick setup (if not removed) and init performance scan (if not running)
	 * - Running < 15 seconds  - return control to ajax
	 * - Running 15-89 seconds - check if report is on the server, if not - return to ajax
	 * - Running 90+ seconds   - stop performance test
	 */
	public function performance_run_test() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$react_poll = isset( $_POST['react_poll'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['react_poll'] ) ); // Input var okay.
		$start_scan = isset( $_POST['start_scan'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['start_scan'] ) ); // Input var okay.
		$started_at = Performance::is_doing_report();

		if ( ! $started_at ) {
			// The React UI distinguishes starting a test from polling one. A poll must
			// never silently start a new test after an error or timeout.
			if ( $react_poll && ! $start_scan ) {
				$stopped_error = __( 'The performance test stopped before a report was available. Please try again.', 'wphb' );
				Activity_Log::record( 'performance', __( 'Performance test failed.', 'wphb' ) );
				Utils::get_module( 'mixpanel_analytics' )->events( 'performance' )->track_scan_finished( '-', '-', $stopped_error );
				wp_send_json_success(
					array(
						'finished' => true,
						'hasError' => $stopped_error,
					)
				);
			}
			$can_run_test = Performance::can_run_test();
			if ( true !== $can_run_test && $can_run_test > 0 ) {
				// Same shape as above: the UI shows the message and stops polling.
				wp_send_json_success(
					array(
						'finished' => true,
						'hasError' => sprintf( /* translators: %d: Number of minutes until another performance test can be run. */
							_n( 'Hummingbird is catching her breath - you can run another test in %d minute.', 'Hummingbird is catching her breath - you can run another test in %d minutes.', $can_run_test, 'wphb' ),
							$can_run_test
						),
					)
				);
			}
			// Must run before init_scan(): that call deletes the previous report, whose scores this event sends.
			Utils::get_module( 'mixpanel_analytics' )->events( 'performance' )->track_scan_started();
			Utils::get_module( 'performance' )->init_scan();
			Activity_Log::record( 'performance', __( 'Performance test started.', 'wphb' ) );
			wp_send_json_success( array( 'finished' => false ) );
		}

		$now = time();
		if ( $now >= ( $started_at + 15 ) ) {
			$mobile  = '-';
			$desktop = '-';

			// If we're over 1 minute - timeout.
			if ( $now >= ( $started_at + 90 ) ) {
				Performance::set_doing_report( false );
				Activity_Log::record( 'performance', __( 'Performance test failed.', 'wphb' ) );
				$timeout_error = $react_poll ? __( 'The performance test returned no results.', 'wphb' ) : 'we’re over 1 minute - timeout';
				Utils::get_module( 'mixpanel_analytics' )->events( 'performance' )->track_scan_finished( $mobile, $desktop, $timeout_error );
				wp_send_json_success(
					array(
						'finished'     => true,
						'mobileScore'  => $mobile,
						'desktopScore' => $desktop,
						'hasError'     => $timeout_error,
					)
				);
			}

			// The report should be finished by this time, let's get the results.
			Performance::refresh_report();
			$report = Performance::get_last_report();

			// Do not cancel the scan if the report is not latest one.
			$current_gmt_time = time();
			if ( $report && ! is_wp_error( $report ) ) {
				$date_time = $report->data->time;
				if ( ( $date_time + 120 ) < $current_gmt_time ) {
					Settings::delete( 'wphb-stop-report' );
					// Keep the original start time so the next poll does not start a new scan.
					set_transient( 'wphb-doing-report', $started_at, max( 1, ( $started_at + 90 ) - $now ) );
					wp_send_json_success( array( 'finished' => false ) );
				}
			}

			// Do not cancel the scan if the report is not ready. We might still have some time to wait.
			if ( is_wp_error( $report ) ) {
				// Check if the report is still not available on the server.
				$error = $report->get_error_data( 'performance-error' );
				if ( isset( $error['details'] ) && 'Performance Results not found' === $error['details'] ) {
					Settings::delete( 'wphb-stop-report' );
					set_transient( 'wphb-doing-report', $started_at, max( 1, ( $started_at + 90 ) - $now ) );
					wp_send_json_success( array( 'finished' => false ) );
				}
			}

			/**
			 * On consecutive scans, sometimes a newer version of the report won't be available, and we will get the
			 * old version. In that case - skip and continue on the scan.
			 */
			if ( isset( $report ) && isset( $report->data ) && isset( $report->data->time ) && ( $now - $report->data->time ) > 300 ) {
				Settings::delete( 'wphb-stop-report' );
				set_transient( 'wphb-doing-report', $started_at, max( 1, ( $started_at + 90 ) - $now ) );
				wp_send_json_success( array( 'finished' => false ) );
			}

			if ( isset( $report ) && isset( $report->data->mobile->score ) ) {
				$mobile = $report->data->mobile->score;
			}
			if ( isset( $report ) && isset( $report->data->desktop->score ) ) {
				$desktop = $report->data->desktop->score;
			}

			$performance_error = is_wp_error( $report ) ? $report->get_error_message() : Performance::get_performance_report_error( $report );
			Activity_Log::record(
				'performance',
				$performance_error
					? __( 'Performance test failed.', 'wphb' )
					: __( 'Performance test completed.', 'wphb' )
			);

			Utils::get_module( 'mixpanel_analytics' )->events( 'performance' )->track_scan_finished( $mobile, $desktop, $performance_error ? $performance_error : '' );

			wp_send_json_success(
				array(
					'finished'        => true,
					'mobileScore'     => $mobile,
					'desktopScore'    => $desktop,
					'hasError'        => $react_poll && is_wp_error( $report ) ? $report->get_error_message() : $performance_error,
					'performanceData' => \Hummingbird\Admin\Pages\Dashboard::get_ui_performance_data(),
				)
			);
		}

		// Just do nothing until the report is finished.
		wp_send_json_success( array( 'finished' => false ) );
	}

	/**
	 * *************************
	 * CACHING MODULE AJAX ACTIONS
	 ***************************/

	/**
	 * Purge cache for selected module.
	 *
	 * @since 1.9.0
	 */
	public function clear_module_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) || ! isset( $_POST['module'] ) ) { // Input var okay.
			die();
		}

		$modules = array( 'page_cache', 'gravatar' );
		$module  = sanitize_text_field( wp_unslash( $_POST['module'] ) ); // Input var ok.

		// Works only for supported modules.
		if ( ! in_array( $module, $modules, true ) ) {
			wp_send_json_success(
				array(
					'success' => false,
				)
			);
		}

		$reload = false;

		$current_fast_cgi = Fast_CGI::is_fast_cgi_enabled();
		$status           = Utils::get_module( $module )->clear_cache();

		// Make sure we reload the page if the FastCGI status has changed.
		if ( 'page_cache' === $module ) {
			$reload = Utils::get_api()->hosting->has_fast_cgi() !== $current_fast_cgi;
		}

		wp_send_json_success(
			array(
				'success' => $status,
				'reload'  => $reload,
			)
		);
	}

	/**
	 * Save fastCGI settings.
	 *
	 * @since 3.9.0
	 */
	public function fast_cgi_save_settings() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) || ! isset( $_POST['data'] ) ) { // Input var okay.
			die();
		}

		// Get the data from ajax.
		parse_str( wp_unslash( $_POST['data'] ), $data ); // Input var ok.

		if ( isset( $data['query_params'] ) && ! empty( $data['query_params'] ) ) {
			$data['query_params'] = preg_split( '/[\r\n\t]+/', $data['query_params'] );
		} else {
			$data['query_params'] = '';
		}

		if ( isset( $data['bypass_urls'] ) && ! empty( $data['bypass_urls'] ) ) {
			$data['bypass_urls'] = preg_split( '/[\r\n\t]+/', $data['bypass_urls'] );
		} else {
			$data['bypass_urls'] = '';
		}

		$data['ttl'] = trim( $data['ttl'] );
		$preload     = isset( $data['preload'] ) && isset( $data['preload']['enabled'] ) ? (bool) $data['preload']['enabled'] : false;

		$existing_settings = Fast_CGI::wphb_fast_cgi_data();
		$settings_modified = array();

		if ( ! empty( $existing_settings ) ) {
			if ( $data['ttl'] !== trim( $existing_settings->ttl ) ) {
				$settings_modified[] = 'cache_lifetime';
			}

			if ( $data['bypass_urls'] !== $existing_settings->bypass_urls ) {
				$settings_modified[] = 'exclude_url';
			}

			if ( $data['query_params'] !== $existing_settings->query_params ) {
				$settings_modified[] = 'cache_query_param';
			}
		}

		$error_message = '';
		if ( ! empty( $data ) && ! empty( $settings_modified ) ) {
			$response      = Utils::get_api()->hosting->wphb_update_fast_cgi_settings( $data );
			$error_message = is_wp_error( $response ) ? $response->get_error_message() : '';
		}

		$module    = Utils::get_module( 'page_cache' );
		$options   = $module->get_options();
		$preload   = isset( $data['preload'] ) && isset( $data['preload']['enabled'] ) ? true : false;
		$home_page = isset( $data['preload_type']['home_page'] ) ? $data['preload_type']['home_page'] : false;
		$on_clear  = isset( $data['preload_type']['on_clear'] ) ? $data['preload_type']['on_clear'] : false;
		if ( $preload !== $options['preload'] ) {
			$settings_modified[] = 'preload_caching';
		} elseif ( ! empty( $preload ) && ( wp_validate_boolean( $home_page ) !== wp_validate_boolean( $options['preload_type']['home_page'] ) || wp_validate_boolean( $on_clear ) !== wp_validate_boolean( $options['preload_type']['on_clear'] ) ) ) {
			$settings_modified[] = 'preload_caching';
		}

		// Cache preload.
		$options['preload'] = $preload;
		if ( $options['preload'] ) {
			$options['preload_type']['home_page'] = isset( $data['preload_type']['home_page'] ) && $data['preload_type']['home_page'];
			$options['preload_type']['on_clear']  = isset( $data['preload_type']['on_clear'] ) && $data['preload_type']['on_clear'];
		}

		$module->update_options( $options );
		$settings                              = $module->get_settings();
		$settings['settings']['comment_clear'] = isset( $data['settings']['comment_clear'] ) && $data['settings']['comment_clear'];
		$settings['settings']['clear_update']  = isset( $data['settings']['clear_update'] ) && $data['settings']['clear_update'];
		$module->save_settings( $settings );

		if ( ! empty( $settings_modified ) ) {
			Utils::get_module( 'page_cache' )->clear_cache();
		}

		wp_send_json_success(
			array(
				'success'          => true,
				'fastCGIResponse'  => $error_message,
				'settingsModified' => ! empty( $settings_modified ) ? implode( ',', $settings_modified ) : '',
				'cacheLifetime'    => ! empty( $data['ttl'] ) ? $data['ttl'] / 60 : $data['ttl'],
				'preloadHomepage'  => isset( $options['preload_type'] ) && $options['preload_type']['home_page'] ? 'enabled' : 'disabled',
			)
		);
	}

	/**
	 * Save page caching settings.
	 *
	 * @since 1.9.0
	 */
	public function page_cache_save_settings() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) || ! isset( $_POST['data'] ) ) { // Input var okay.
			die();
		}

		parse_str( wp_unslash( $_POST['data'] ), $data ); // Input var ok.

		$page_types        = array();
		$custom_post_types = array();

		$url_strings = '';
		$user_agents = '';
		$cookies     = '';

		if ( isset( $data['page_types'] ) && is_array( $data['page_types'] ) ) { // Input var ok.
			$page_types = array_keys( wp_unslash( $data['page_types'] ) ); // Input var ok.
		}

		if ( isset( $data['custom_post_types'] ) && is_array( $data['custom_post_types'] ) ) { // Input var ok.
			$custom_post_types_data = wp_unslash( $data['custom_post_types'] ); // Input var ok.
			foreach ( $custom_post_types_data as $custom_post_type => $value ) {
				if ( $value ) {
					$custom_post_types[] = $custom_post_type;
				}
			}
		}

		$cache_settings = Page_Cache::get_default_settings();
		if ( isset( $data['settings'] ) ) {
			$cache_settings = wp_parse_args( $data['settings'], $cache_settings['settings'] );
		} else {
			$cache_settings = array_map( '__return_false', $cache_settings['settings'] );
		}
		$cache_settings = array_map( 'absint', $cache_settings );

		if ( isset( $data['url_strings'] ) ) { // Input var ok.
			$url_strings = sanitize_textarea_field( wp_unslash( $data['url_strings'] ) ); // Input var okay.
			$url_strings = preg_split( '/[\r\n\t ]+/', $url_strings );

			foreach ( $url_strings as $id => $string ) {
				$string             = str_replace( '\\', '', $string );
				$string             = str_replace( '/', '\/', $string );
				$string             = preg_replace( '/.php$/', '\\.php', $string );
				$string             = preg_replace( '/.xml$/', '\\.xml', $string );
				$string             = preg_replace( '/.yml$/', '\\.yml', $string );
				$url_strings[ $id ] = $string;
			}
		}

		if ( isset( $data['user_agents'] ) ) { // Input var ok.
			$user_agents = sanitize_textarea_field( wp_unslash( $data['user_agents'] ) ); // Input var okay.
			$user_agents = preg_split( '/[\r\n\t]+/', $user_agents );
		}

		if ( isset( $data['cookies'] ) ) { // Input var ok.
			$cookies = sanitize_textarea_field( wp_unslash( $data['cookies'] ) ); // Input var okay.
			$cookies = preg_split( '/[\r\n\t]+/', $cookies );
		}

		$settings['page_types']             = $page_types;
		$settings['custom_post_types']      = $custom_post_types;
		$settings['settings']               = $cache_settings;
		$settings['exclude']['url_strings'] = $url_strings;
		$settings['exclude']['user_agents'] = $user_agents;
		$settings['exclude']['cookies']     = $cookies;

		$module            = Utils::get_module( 'page_cache' );
		$options           = $module->get_options();
		$existing_settings = $module->get_settings();
		$existing_options  = $options;

		if ( isset( $data['settings']['admins_disable_caching'] ) && 1 === absint( $data['settings']['admins_disable_caching'] ) ) {
			$options['enabled'] = 'blog-admins';
		} elseif ( $module->is_active() ) {
			$options['enabled'] = true;
		}

		// Integrations.
		$defaults = Settings::get_default_settings();
		if ( isset( $data['integrations'] ) ) {
			$options['integrations'] = wp_parse_args( $data['integrations'], $defaults['page_cache']['integrations'] );
		} else {
			$options['integrations'] = array_map( '__return_false', $defaults['page_cache']['integrations'] );
		}

		// Cache preload.
		$options['preload'] = isset( $data['preload'] ) && isset( $data['preload']['enabled'] ) ? (bool) $data['preload']['enabled'] : false;
		if ( $options['preload'] ) {
			$options['preload_type']['home_page'] = isset( $data['preload_type']['home_page'] ) && $data['preload_type']['home_page'];
			$options['preload_type']['on_clear']  = isset( $data['preload_type']['on_clear'] ) && $data['preload_type']['on_clear'];
		}

		// Clear cache interval. Only update, when option is enabled.
		$settings['clear_interval']['enabled'] = isset( $data['clear_interval']['enabled'] );
		if ( 'days' === $data['clear_interval']['period'] ) {
			$interval = $data['clear_interval']['interval'] * 24;
		} else {
			$interval = $data['clear_interval']['interval'];
		}
		$settings['clear_interval']['interval'] = $interval;

		$settings_modified = array();
		if ( ! empty( $existing_settings ) ) {
			$preload   = isset( $data['preload'] ) && isset( $data['preload']['enabled'] ) ? true : false;
			$home_page = isset( $data['preload_type']['home_page'] ) ? $data['preload_type']['home_page'] : false;
			$on_clear  = isset( $data['preload_type']['on_clear'] ) ? $data['preload_type']['on_clear'] : false;
			if ( $preload !== $existing_options['preload'] ) {
				$settings_modified[] = 'preload_caching';
			} elseif ( ! empty( $preload ) && ( wp_validate_boolean( $home_page ) !== wp_validate_boolean( $existing_options['preload_type']['home_page'] ) || wp_validate_boolean( $on_clear ) !== wp_validate_boolean( $existing_options['preload_type']['on_clear'] ) ) ) {
				$settings_modified[] = 'preload_caching';
			}

			$cache_identifier = isset( $data['settings']['cache_identifier'] ) ? $data['settings']['cache_identifier'] : false;
			if ( wp_validate_boolean( $cache_identifier ) !== wp_validate_boolean( $existing_settings['settings']['cache_identifier'] ) ) {
				$settings_modified[] = 'identify_cached_pages';
			}

			$debug_log = isset( $data['settings']['debug_log'] ) ? $data['settings']['debug_log'] : false;
			if ( wp_validate_boolean( $debug_log ) !== wp_validate_boolean( $existing_settings['settings']['debug_log'] ) ) {
				$settings_modified[] = 'debug_log';
			}

			$comment_clear = isset( $data['settings']['comment_clear'] ) ? $data['settings']['comment_clear'] : false;
			if ( wp_validate_boolean( $comment_clear ) !== wp_validate_boolean( $existing_settings['settings']['comment_clear'] ) ) {
				$settings_modified[] = 'purge_on_comment';
			}

			if ( $page_types !== $existing_settings['page_types'] ) {
				$settings_modified[] = 'hb_page_types';
			}

			$clear_interval = isset( $data['clear_interval']['enabled'] ) ? $data['clear_interval']['enabled'] : false;
			if ( $clear_interval !== $existing_settings['clear_interval']['enabled'] ) {
				$settings_modified[] = 'hb_cache_interval';
			} elseif ( ! empty( $clear_interval ) && ( $data['clear_interval']['period'] !== $existing_settings['clear_interval']['period'] || $interval !== $existing_settings['clear_interval']['interval'] ) ) {
				$settings_modified[] = 'hb_cache_interval';
			}

			$varnish = isset( $data['integrations']['varnish'] ) ? $data['integrations']['varnish'] : false;
			if ( wp_validate_boolean( $varnish ) !== wp_validate_boolean( $existing_options['integrations']['varnish'] ) ) {
				$settings_modified[] = 'hb_integration_varnish';
			}

			$opcache = isset( $data['integrations']['opcache'] ) ? $data['integrations']['opcache'] : false;
			if ( wp_validate_boolean( $opcache ) !== wp_validate_boolean( $existing_options['integrations']['opcache'] ) ) {
				$settings_modified[] = 'hb_integration_opcache';
			}

			if ( $url_strings !== $existing_settings['exclude']['url_strings'] || $user_agents !== $existing_settings['exclude']['user_agents'] || $cookies !== $existing_settings['exclude']['cookies'] ) {
				$settings_modified[] = 'hb_exclusions';
			}

			if ( $url_strings !== $existing_settings['exclude']['url_strings'] ) {
				$settings_modified[] = 'hb_settings_url_queries';
			}

			$clear_update = isset( $data['settings']['clear_update'] ) ? $data['settings']['clear_update'] : false;
			if ( wp_validate_boolean( $clear_update ) !== wp_validate_boolean( $existing_settings['settings']['clear_update'] ) ) {
				$settings_modified[] = 'hb_settings_full_purge';
			}

			$mobile = isset( $data['settings']['mobile'] ) ? $data['settings']['mobile'] : false;
			if ( wp_validate_boolean( $mobile ) !== wp_validate_boolean( $existing_settings['settings']['mobile'] ) ) {
				$settings_modified[] = 'hb_settings_mobile';
			}
		}

		$module->update_options( $options );
		$module->save_settings( $settings );

		wp_send_json_success(
			array(
				'success'          => true,
				'settingsModified' => ! empty( $settings_modified ) ? implode( ',', $settings_modified ) : 'na',
				'preloadHomepage'  => isset( $options['preload_type'] ) && $options['preload_type']['home_page'] ? 'enabled' : 'disabled',
			)
		);
	}

	/**
	 * Clear cache for selected page, when 'clear cache' button is clicked from Gutenberg post edit screen.
	 *
	 * @since 1.9.4
	 */
	public function gutenberg_clear_post_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['postId'] ) ) { // Input var okay.
			die();
		}

		$id = absint( wp_unslash( $_POST['postId'] ) );

		Utils::get_module( 'page_cache' )->clear_cache_action( $id );

		wp_send_json_success();
	}

	/**
	 * Cancel cache preloading.
	 *
	 * @since 2.1.0
	 */
	public function cancel_cache_preload() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			die();
		}

		if ( ! is_multisite() || ! Utils::is_ajax_network_admin() ) {
			$preloader = new Preload();
			$preloader->cleanup();
			wp_send_json_success();
		}

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to clear preload queues for the network.', 'wphb' ) ), 403 );
		}

		Preload::cleanup_network();
		wp_send_json_success();
	}

	/**
	 * Remove the advanced-cache.php file.
	 *
	 * @since 3.1.1
	 */
	public function remove_advanced_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			die();
		}

		$adv_cache_file = dirname( get_theme_root() ) . '/advanced-cache.php';
		if ( file_exists( $adv_cache_file ) ) {
			unlink( $adv_cache_file );
		}

		wp_send_json_success();
	}

	/**
	 * Disable FastCGI cache.
	 *
	 * This duplicates Admin\Ajax\disable_fast_cgi()
	 *
	 * @since 3.4.0
	 *
	 * @return void
	 */
	public function disable_fast_cgi() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			die();
		}

		$response      = Utils::get_api()->hosting->toggle_fast_cgi( false );
		$error_message = is_wp_error( $response ) ? $response->get_error_message() : '';
		// Get page cache module.
		$page_cache = Utils::get_module( 'page_cache' );
		$options    = $page_cache->get_options();
		// Disable page cache.
		$page_cache->disable();

		wp_send_json_success(
			array(
				'fastCGIResponse' => $error_message,
				'preloadHomepage' => isset( $options['preload_type'] ) && $options['preload_type']['home_page'] ? 'enabled' : 'disabled',
			)
		);
	}

	/**
	 * Switch cache method.
	 *
	 * @since 3.9.0
	 *
	 * @return void
	 */
	public function switch_cache_method() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$method                = sanitize_text_field( wp_unslash( $_POST['method'] ) ); // Input var ok.
		$is_fast_cgi_activated = null;
		$error_message         = '';
		$module                = Utils::get_module( 'page_cache' );

		$activate_hosting_static_cache = null;
		if ( 'hosting_static_cache' === $method ) {
			$activate_hosting_static_cache = true;
		} elseif ( 'local_page_cache' === $method ) {
			$activate_hosting_static_cache = false;
		}

		if ( ! is_null( $activate_hosting_static_cache ) ) {
			$response = Utils::get_api()->hosting->toggle_fast_cgi( $activate_hosting_static_cache );
			if ( $activate_hosting_static_cache && isset( $response->is_active ) ) {
				Fast_CGI::update_fast_cgi_status( $response->is_active );
			}

			$error_message         = is_wp_error( $response ) ? $response->get_error_message() : '';
			$is_fast_cgi_activated = ! empty( $error_message ) ? 'error' : $activate_hosting_static_cache;

			if ( 'hosting_static_cache' === $method ) {
				$module->disable();
			} elseif ( 'local_page_cache' === $method ) {
				$module->toggle_service( true, true );
			}
		}

		$options = $module->get_options();

		// Clear cache is required at this point because we are changing the cache method.
		$module->clear_cache();

		wp_send_json_success(
			array(
				'isFastCGIActivated' => $is_fast_cgi_activated,
				'fastCGIResponse'    => $error_message,
				'method'             => $method,
				'preloadHomepage'    => isset( $options['preload_type'] ) && $options['preload_type']['home_page'] ? 'enabled' : 'disabled',
			)
		);
	}

	/**
	 * Purge Cloudflare cache.
	 */
	public function cloudflare_purge_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_module( 'cloudflare' )->clear_cache();

		wp_send_json_success();
	}

	/**
	 * Recheck Cloudflare zones.
	 */
	public function cloudflare_recheck_zones() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

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
	 * *************************
	 * ASSET OPTIMIZATION AJAX ACTIONS
	 ***************************/

	/**
	 * *************************
	 * ADVANCED TOOLS AJAX ACTIONS
	 ***************************/

	/**
	 * Cleanup selected data type from db.
	 *
	 * @since 1.8
	 */
	public function advanced_db_delete_data() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) || ! isset( $_POST['data'] ) ) { // Input var okay.
			die();
		}

		$available_types = array( 'revisions', 'drafts', 'trash', 'spam', 'trash_comment', 'expired_transients', 'transients', 'all' );
		$type            = sanitize_text_field( wp_unslash( $_POST['data'] ) ); // Input var ok.

		if ( ! in_array( $type, $available_types, true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid type specified.', 'wphb' ),
				)
			);
		}

		$adv_module = Utils::get_module( 'advanced' );
		$removed    = $adv_module->delete_db_data( $type );

		if ( ! is_array( $removed ) || ( 0 === $removed['items'] && 0 > $removed['left']->total ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error deleting data.', 'wphb' ),
				)
			);
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of database entries */
				'message' => sprintf( __( '%d database entries were deleted successfully.', 'wphb' ), $removed['items'] ),
				'left'    => $removed['left'],
			)
		);
	}

	/**
	 * Update settings for advanced tools.
	 *
	 * @since 1.8
	 */
	public function advanced_save_settings() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) || ! isset( $_POST['form'] ) ) { // Input var okay.
			wp_die();
		}

		$form = sanitize_text_field( wp_unslash( $_POST['form'] ) ); // Input var ok.
		parse_str( wp_unslash( $_POST['data'] ), $data ); // Input var ok.

		$adv_module = Utils::get_module( 'advanced' );
		$options    = $adv_module->get_options();

		$remove_query_strings   = $options['query_string'];
		$disable_cart_fragments = $options['cart_fragments'];
		$remove_emojis          = $options['emoji'];
		$prefetch_dns           = $options['prefetch'];
		$lazy_comments          = $options['lazy_load']['enabled'];
		$preconnect             = $options['preconnect'];
		$viewport_meta          = $options['viewport_meta'];

		// General settings tab.
		if ( 'advanced-general-settings' === $form ) {
			$skip = isset( $options['query_strings_global'] ) && $options['query_strings_global'] && ! Utils::is_ajax_network_admin();
			if ( ! $skip ) {
				$options['query_string']         = isset( $data['query_strings'] ) && 'on' === $data['query_strings'];
				$options['query_strings_global'] = isset( $data['query_strings_global'] ) && 'on' === $data['query_strings_global'];
			}

			if ( isset( $data['cart_fragments'] ) && 'on' === $data['cart_fragments'] ) {
				$options['cart_fragments'] = isset( $data['cart_fragments_value'] ) && '1' === $data['cart_fragments_value'] ? true : 'all';
			} else {
				$options['cart_fragments'] = false;
			}

			$skip = isset( $options['emoji_global'] ) && $options['emoji_global'] && ! Utils::is_ajax_network_admin();
			if ( ! $skip ) {
				$options['emoji']        = isset( $data['emojis'] ) && 'on' === $data['emojis'];
				$options['emoji_global'] = isset( $data['emojis_global'] ) && 'on' === $data['emojis_global'];
			}

			$options['post_revisions'] = '';
			if ( isset( $data['post_revisions'] ) && $data['post_revisions'] >= 0 ) {
				$options['post_revisions'] = $data['post_revisions'];
			}

			$options['viewport_meta'] = isset( $data['viewport_meta'] ) && 'on' === $data['viewport_meta'];

			$options['prefetch'] = array();
			if ( isset( $data['url_strings'] ) && ! empty( $data['url_strings'] ) ) {
				$options['prefetch'] = preg_split( '/[\r\n\t ]+/', $data['url_strings'] );
			}

			$options['preconnect'] = array();
			if ( isset( $data['preconnect_strings'] ) && ! empty( $data['preconnect_strings'] ) ) {
				$options['preconnect'] = preg_split( '/[\r\n\t]+/', $data['preconnect_strings'] );
			}
		}

		// Lazy load tab.
		if ( 'advanced-lazy-settings' === $form ) {
			$options['lazy_load'] = array(
				'enabled'   => isset( $data['lazy_load'] ) && 'on' === $data['lazy_load'],
				'method'    => isset( $data['method'] ) ? $data['method'] : 'click',
				'button'    => isset( $data['button'] ) ? $data['button'] : '',
				'threshold' => isset( $data['threshold'] ) ? $data['threshold'] : 0,
				'preload'   => isset( $data['preload'] ) && 'on' === $data['preload'],
			);
		}

		// Track MP event.
		$mp_events = array();
		if ( $remove_query_strings !== $options['query_string'] ) {
			$mp_events[] = array( 'remove_query_strings' => $options['query_string'] );
		}

		if ( $disable_cart_fragments !== $options['cart_fragments'] ) {
			$mp_events[] = array( 'disable_cart_fragments' => $options['cart_fragments'] );
		}

		if ( $remove_emojis !== $options['emoji'] ) {
			$mp_events[] = array( 'remove_emojis' => $options['emoji'] );
		}

		if ( $prefetch_dns !== $options['prefetch'] ) {
			$mp_events[] = array( 'prefetch_dns' => ! empty( $options['prefetch'] ) ? true : false );
		}

		if ( $preconnect !== $options['preconnect'] ) {
			$mp_events[] = array( 'preconnect_domains' => ! empty( $options['preconnect'] ) ? true : false );
		}

		if ( $lazy_comments !== $options['lazy_load']['enabled'] ) {
			$mp_events[] = array( 'lazy_comments' => $options['lazy_load']['enabled'] );
		}

		if ( $viewport_meta !== $options['viewport_meta'] ) {
			$mp_events[] = array( 'viewport_meta' => $options['viewport_meta'] );
		}

		$adv_module->update_options( $options );
		wp_send_json_success(
			array(
				'success'   => true,
				'mp_events' => $mp_events,
			)
		);
	}

	/**
	 * Purge page cache preloader.
	 *
	 * @since 2.7.0
	 */
	public function advanced_purge_cache() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			die();
		}

		if ( ! is_multisite() || ! Utils::is_ajax_network_admin() ) {
			$preloader = new Preload();
			$preloader->cleanup();
			wp_send_json_success();
		}

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to clear preload queues for the network.', 'wphb' ) ), 403 );
		}

		Preload::cleanup_network();
		wp_send_json_success();
	}

	/**
	 * Purge asset optimization groups.
	 *
	 * @since 2.7.0
	 */
	public function advanced_purge_minify() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			die();
		}

		Utils::get_module( 'minify' )->clear_files();

		wp_send_json_success();
	}

	/**
	 * Purge asset optimization orphaned data.
	 *
	 * @since 2.7.0
	 */
	public function advanced_purge_orphaned() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			die();
		}

		$rows = filter_input( INPUT_POST, 'rows', FILTER_SANITIZE_NUMBER_INT );

		Utils::get_module( 'advanced' )->purge_orphaned_step( (int) $rows );

		$load = sys_getloadavg();
		wp_send_json_success(
			array(
				'highCPU' => $load[0] > 0.5,
			)
		);
	}

	/**
	 * *************************
	 * LOGGER MODULE AJAX ACTIONS
	 ***************************/

	/**
	 * *************************
	 * HUMMINGBIRD ADMIN SETTINGS AJAX ACTIONS
	 ***************************/

	/**
	 * Create critical css for selected page from Gutenberg post edit screen.
	 *
	 * @since 3.6.0
	 */
	public function gutenberg_create_css_file() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['postId'] ) ) { // Input var okay.
			die();
		}

		$id = absint( wp_unslash( $_POST['postId'] ) );

		// Also clear cache.
		Utils::get_module( 'page_cache' )->clear_cache_action( $id );

		$status                          = Utils::get_module( 'critical_css' )->create_post_css_file( $id );
		$single_post_critical_css_status = Utils::get_module( 'critical_css' )->get_single_post_critical_css_status( $id );

		if ( 'error' === $single_post_critical_css_status ) {
			$type      = get_post_type( $id ) . '-' . $id;
			$get_error = Utils::get_module( 'critical_css' )->get_queue_item_by_type( $type );
			$message   = isset( $get_error->error_message ) ? $get_error->error_message : esc_html__( 'There was some error in generating Critical CSS', 'wphb' );
		} elseif ( 'processing' === $single_post_critical_css_status ) {
			$message = esc_html__( 'Critical CSS is being generated', 'wphb' );
		} else {
			$message = esc_html__( 'Critical CSS generated', 'wphb' );
		}

		wp_send_json_success(
			array(
				'success'                     => $status,
				'singlePostCriticalCSSStatus' => $single_post_critical_css_status,
				'message'                     => $message,
			)
		);
	}

	/**
	 * Re Create critical css for selected page from Gutenberg post edit screen.
	 *
	 * @since 3.6.0
	 */
	public function gutenberg_recreate_css_file() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['postId'] ) ) { // Input var okay.
			die();
		}

		$id = absint( wp_unslash( $_POST['postId'] ) );
		// Also clear cache.
		Utils::get_module( 'page_cache' )->clear_cache_action( $id );
		$status                          = Utils::get_module( 'critical_css' )->recreate_post_css_file( $id );
		$single_post_critical_css_status = Utils::get_module( 'critical_css' )->get_single_post_critical_css_status( $id );
		$message                         = '';

		if ( 'processing' === $single_post_critical_css_status ) {
			$message = esc_html__( 'Critical CSS is being re-generated', 'wphb' );
		} elseif ( 'error' === $single_post_critical_css_status ) {
			$type      = get_post_type( $id ) . '-' . $id;
			$get_error = Utils::get_module( 'critical_css' )->get_queue_item_by_type( $type );
			$message   = isset( $get_error->error_message ) ? $get_error->error_message : esc_html__( 'There was some error in generating Critical CSS', 'wphb' );
		} elseif ( $single_post_critical_css_status ) {
			$message = esc_html__( 'Critical CSS re-generated', 'wphb' );
		}

		wp_send_json_success(
			array(
				'success'                     => $status,
				'singlePostCriticalCSSStatus' => $single_post_critical_css_status,
				'message'                     => $message,
			)
		);
	}

	/**
	 * Create critical css for selected page from Gutenberg post edit screen.
	 *
	 * @since 3.6.0
	 */
	public function gutenberg_revert_css_file() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['postId'] ) ) { // Input var okay.
			die();
		}

		$id = absint( wp_unslash( $_POST['postId'] ) );

		// Also clear cache.
		Utils::get_module( 'page_cache' )->clear_cache_action( $id );
		$status                          = Utils::get_module( 'critical_css' )->revert_post_css_file( $id );
		$single_post_critical_css_status = Utils::get_module( 'critical_css' )->get_single_post_critical_css_status( $id );
		$message                         = esc_html__( 'Critical CSS file deleted successfully', 'wphb' );

		wp_send_json_success(
			array(
				'success'                     => $status,
				'singlePostCriticalCSSStatus' => $single_post_critical_css_status,
				'message'                     => $message,
			)
		);
	}

	/**
	 * Get critical css status for single post.
	 *
	 * @since 3.6.0
	 */
	public function gutenberg_get_critical_status_for_single_post() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['postId'] ) ) { // Input var okay.
			die();
		}

		$id          = absint( wp_unslash( $_POST['postId'] ) );
		$type        = get_post_type( $id ) . '-' . $id;
		$item_detail = Utils::get_module( 'critical_css' )->get_queue_item_by_type( $type );

		wp_send_json_success(
			array(
				'singlePostCriticalCSSStatus' => Utils::get_module( 'critical_css' )->get_single_post_critical_css_status( $id ),
				'singlePostCriticalDetail'    => $item_detail,
				'errorCode'                   => Utils::get_module( 'critical_css' )->get_error_code_from_log( (array) $item_detail ),
			)
		);
	}

	/**
	 * Switch Critical CSS.
	 */
	public function wphb_switch_to_critical_css_from_legacy() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) { // Input var okay.
			die();
		}

		delete_option( 'wphb-notice-legacy-critical-css-show' );
		if ( Utils::is_member() ) {
			Utils::get_module( 'critical_css' )->toggle_critical_css( true );
		}

		wp_send_json_success();
	}
}
