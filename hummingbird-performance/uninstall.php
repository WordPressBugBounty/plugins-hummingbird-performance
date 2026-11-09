<?php
/**
 * Uninstall file.
 *
 * @package Hummingbird
 */

use Hummingbird\Core\Filesystem;
use Hummingbird\Core\Logger;
use Hummingbird\Core\Settings;

// If uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( class_exists( 'Hummingbird\\WP_Hummingbird' ) ) {
	return;
}

if ( ! class_exists( 'Hummingbird\\Core\\Settings' ) ) {
	/* @noinspection PhpIncludeInspection */
	include_once plugin_dir_path( __FILE__ ) . '/core/class-settings.php';
}

$settings = Settings::get_settings( 'settings', 'wphb_settings' );

if ( $settings['remove_settings'] || $settings['remove_data'] ) {
	if ( ! class_exists( 'Hummingbird\\Core\\Filesystem' ) ) {
		/* @noinspection PhpIncludeInspection */
		include_once plugin_dir_path( __FILE__ ) . '/core/class-filesystem.php';
	}

	$fs = Filesystem::instance();
}

// The CDN module caches one transient per stylesheet version (wphb_cdn_css_<hash>); sweep them by prefix.
$wphb_delete_cdn_css_transients = static function () {
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wphb_cdn_css_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wphb_cdn_css_' ) . '%'
		)
	);
};

if ( $settings['remove_settings'] ) {
	$options = array(
		'wphb_ao_scan_files_process_status',
		'wphb-caching-api-checked',
		'wphb-cloudflare-dash-notice',
		'wphb-deleted-hub-config-ids',
		'wphb-dismissed-notices',
		'wphb-free-install-date',
		'wphb-gzip-api-checked',
		'wphb_hide_ao_menu_status_animation',
		'wphb-hide-tutorials',
		'wphb-minification-files-scanned',
		'wphb-minification-show-advanced_modal',
		'wphb-minification-show-config_modal',
		'wphb-minify-server-errors',
		'wphb-notice-cache-cleaned-show',
		'wphb-notice-cache-global-cleared-show',
		'wphb-notice-connect-for-site-monitoring-show',
		'wphb-notice-free-deactivated-dismissed',
		'wphb-notice-free-deactivated-show',
		'wphb-notice-free-rated-show',
		'wphb-notice-free-rated-later_date',
		'wphb-notice-http2-info-show',
		'wphb-notice-minification-optimized-show',
		'wphb-notice-uptime-info-show',
		'wphb-preset_configs',
		'wphb_process_queue',
		'wphb-quick-setup',
		'wphb_run_onboarding',
		'wphb_scripts_collection',
		'wphb_settings',
		'wphb_page_cache_pages_cached',
		'wphb_show_connected_modal',
		'wphb-stop-report',
		'wphb_styles_collection',
		'wphb_version',
		'wphb_safe_mode_status',
		'wphb_safe_mode_settings',
	);

	// Clear cron at first.
	wp_clear_scheduled_hook( 'wphb_performance_report' );
	wp_clear_scheduled_hook( 'wphb_uptime_report' );
	wp_clear_scheduled_hook( 'wphb_database_report' );
	// Deactivation clears this too, but its page cache purge starts a homepage preload request that re-adds it.
	wp_clear_scheduled_hook( 'wphb_clear_logs' );
	wp_clear_scheduled_hook( 'wphb_get_delay_js_exclusion' );
	if ( wp_next_scheduled( 'wphb_minify_clear_files' ) ) {
		wp_clear_scheduled_hook( 'wphb_minify_clear_files' );
	}

	// Subsite wp_option.
	if ( is_multisite() && ! wp_is_large_network() ) {
		$sites = get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			foreach ( $options as $option ) {
				delete_option( $option );
			}

			// Each site schedules its own copy of these; the clears above only reach the main site.
			wp_clear_scheduled_hook( 'wphb_clear_logs' );
			wp_clear_scheduled_hook( 'wphb_get_delay_js_exclusion' );
			wp_clear_scheduled_hook( 'wphb_minify_clear_files' );

			if ( $settings['remove_data'] ) {
				delete_option( 'wphb-last-report' );
				$wphb_delete_cdn_css_transients();

				if ( ! is_wp_error( $fs->status ) ) {
					$fs->purge_ao_cache();
				}
			}

			restore_current_blog();
		}
	}

	// Network wp_option, wp_sitemeta.
	foreach ( $options as $option ) {
		delete_option( $option );
		delete_site_option( $option );
	}

	// The dismissed React notices are also recorded per user, under the key listed above as
	// `wphb-dismissed-notices`. wp_usermeta is network-wide, so one call clears them all.
	delete_metadata( 'user', 0, 'wphb-dismissed-notices', '', true );
}


if ( $settings['remove_data'] ) {
	// Reports & data.
	delete_site_option( 'wphb-caching-data' );
	delete_site_option( 'wphb-gzip-data' );
	delete_site_option( 'wphb_compression_type' );
	delete_site_option( 'wphb_plugin_timestamps' );

	delete_option( 'wphb-last-report' );
	delete_site_option( 'wphb-last-report' );
	delete_site_option( 'wphb-notice-free-rated-last-score' );
	delete_option( 'wphb_activity_log' );
	delete_site_option( 'wphb_activity_log' );

	delete_site_transient( 'wphb-fast-cgi-enabled' );
	delete_site_transient( 'wphb_hosting_info' );

	// Remove critical css data.
	delete_site_option( 'wphb_cs_process_queue' );
	delete_option( 'wphb_critical_css_log' );
	delete_site_transient( 'wphb-cs-processing' );

	$wphb_delete_cdn_css_transients();

	if ( ! is_wp_error( $fs->status ) ) {
		$fs->clean_up();
		$fs->purge_manual_critical_css();
	}

	if ( ! class_exists( 'Hummingbird\\Core\\Logger' ) ) {
		/* @noinspection PhpIncludeInspection */
		include_once plugin_dir_path( __FILE__ ) . '/core/class-logger.php';
	}
	Logger::cleanup();
}
