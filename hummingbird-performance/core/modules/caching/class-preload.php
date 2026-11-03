<?php
/**
 * Preload cache files.
 *
 * @since 2.1.0
 * @package Hummingbird\Core\Modules\Caching
 */

namespace Hummingbird\Core\Modules\Caching;

use Hummingbird\Core\Filesystem;
use Hummingbird\Core\Modules\Background\HB_Background_Process;
use Hummingbird\Core\Modules\Background\Thread_Safe_Options;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Preload
 */
class Preload extends HB_Background_Process {

	// Each task sleeps 0.5s, so this keeps requests under the 20s time_exceeded() budget.
	const DEFAULT_TASKS_PER_REQUEST = 20;

	public function __construct() {
		$identifier = 'wphb_cache_preload' . ( is_multisite() ? '_' . get_current_blog_id() : '' );
		parent::__construct( $identifier );
		$this->set_tasks_per_request();
	}

	/** Configure the batch size, following the AO scan worker. */
	public function set_tasks_per_request( $tasks_per_request = null ) {
		if ( null === $tasks_per_request ) {
			$tasks_per_request = apply_filters( 'wphb_cache_preload_tasks_per_request', self::DEFAULT_TASKS_PER_REQUEST );
		}
		parent::set_tasks_per_request( max( 1, min( absint( $tasks_per_request ), 50 ) ) );
	}

	/**
	 * Start a fixed batch using the shared worker's queue and lifecycle.
	 *
	 * @param array $requests URL/mobile request data.
	 * @return bool Whether the batch was started.
	 */
	public function start_preload( $requests ) {
		// Do not start another preload job from a preloader HTTP request.
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
			if ( preg_match( '/Hummingbird.+?\/Cache Preloader/', $user_agent ) ) {
				return false;
			}
		}
		if ( empty( $requests ) || ! $this->should_process() ) {
			return false;
		}

		$tasks = $this->get_queue_tasks( $requests );
		if ( false === $tasks ) {
			return false;
		}

		$has_queued_tasks = ! $this->is_queue_empty();
		$process_running  = $this->is_process_running();

		if ( ! $has_queued_tasks && ! $process_running ) {
			parent::start( $tasks );
			return true;
		}

		if ( ! $process_running ) {
			$merged_tasks = array_merge( $this->get_queue(), $tasks );
			parent::start( $merged_tasks );
			return true;
		}

		$queued_tasks_before_append = count( $this->get_queue() );
		$storage                    = new Thread_Safe_Options();
		$result                     = $storage->append_to_array( $this->get_queue_key(), $tasks );
		if ( false === $result ) {
			return false;
		}

		$status        = $this->get_status();
		$current_total = $status->get_total_items();
		if ( $current_total < 1 ) {
			$current_total = $status->get_processed_items() + $queued_tasks_before_append;
		}
		$status->set_total_items( $current_total + count( $tasks ) );

		return true;
	}

	/**
	 * Prepare request payloads for queue storage.
	 *
	 * @param array $requests URL/mobile request data.
	 * @return array|false
	 */
	private function get_queue_tasks( $requests ) {
		$tasks = array();
		foreach ( $requests as $request ) {
			$request = is_array( $request ) ? $request : $this->get_preload_request( $request );
			// The shared worker stores scalar tasks, so encode the request payload.
			$task = wp_json_encode( $request );
			if ( false === $task ) {
				return false;
			}
			$tasks[] = $task;
		}
		return $tasks;
	}

	/** Use the shared cancellation lifecycle for site and network cleanup. */
	public function cleanup() {
		$this->cancel();
	}

	/** Keep legacy intervals available until the upgrade removes their pending events. */
	public function schedule_cron_healthcheck( $schedules ) {
		$schedules                                     = parent::schedule_cron_healthcheck( $schedules );
		$schedules['wphb_cache_preload_cron_interval'] = array(
			'interval' => $this->get_cron_interval_seconds(),
			'display'  => __( 'Preload cache health check', 'wphb' ),
		);
		return $schedules;
	}

	/** Preload logging must not write to the asset-optimization log. */
	public function log( $message ) {}

	/**
	 * Read page-cache options directly, without changing the WordPress object cache.
	 *
	 * @return array
	 */
	private function get_persisted_page_cache_options() {
		global $wpdb;
		$name     = Settings::get_setting_name();
		$settings = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ) );
		$settings = is_array( $settings ) ? $settings : array();
		if ( is_multisite() ) {
			$network  = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s", get_current_network_id(), $name ) ) );
			$settings = array_merge_recursive( $settings, is_array( $network ) ? $network : array() );
		}
		$defaults = Settings::get_default_settings();
		return wp_parse_args( isset( $settings['page_cache'] ) ? $settings['page_cache'] : array(), $defaults['page_cache'] );
	}

	/** Check whether the current site permits preloading. */
	protected function should_process() {
		$options            = $this->get_persisted_page_cache_options();
		$is_preload_enabled = ! empty( $options['preload'] );
		if ( ! $is_preload_enabled ) {
			return false;
		}

		$is_fast_cgi_enabled = Fast_CGI::is_fast_cgi_enabled();
		if ( $is_fast_cgi_enabled ) {
			// Mirror page-cache behavior: FastCGI means server-side cache is active.
			return true;
		}

		$cache_permission      = $options['enabled'];
		$is_page_cache_enabled = ! empty( $cache_permission );
		if ( ! $is_page_cache_enabled ) {
			return false;
		}

		$subsites_can_disable_cache = 'blog-admins' === $cache_permission;
		// Intentionally different from Page_Cache::module_status() on multisite:
		// preload admission always follows the current site's cache_blog flag, so
		// background jobs do not run for subsites that opted out.
		if ( ! is_multisite() || ! $subsites_can_disable_cache ) {
			return true;
		}

		$is_subsite_cache_enabled = ! empty( $options['cache_blog'] );
		return $is_subsite_cache_enabled;
	}

	/**
	 * Stop preload work on every site in the current network.
	 */
	public static function cleanup_network() {
		self::for_each_site(
			function () {
				$worker = new self();
				$worker->cleanup();
				// These temporary workers must not register callbacks on another site.
				remove_filter( 'cron_schedules', array( $worker, 'schedule_cron_healthcheck' ) );
				remove_action( $worker->cron_hook_identifier, array( $worker, 'handle_cron_healthcheck' ) );
				remove_action( 'wp_ajax_' . $worker->identifier, array( $worker, 'maybe_handle' ) );
				remove_action( 'wp_ajax_nopriv_' . $worker->identifier, array( $worker, 'maybe_handle' ) );
			}
		);
	}

	/**
	 * Discard legacy unowned batches and events when upgrading to site-owned queues.
	 */
	public static function migrate_legacy() {
		self::clear_legacy_batches();
		delete_site_transient( 'wphb_cache_preload_process_lock' );
		self::for_each_site(
			function () {
				wp_clear_scheduled_hook( 'wphb_cache_preload_cron' );
			}
		);
	}

	/** Delete batch options written by the legacy preload worker. */
	private static function clear_legacy_batches() {
		global $wpdb;
		$pattern = $wpdb->esc_like( 'wphb_cache_preload_batch_' ) . '%';
		if ( is_multisite() ) {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key LIKE %s", get_current_network_id(), $pattern ) );
			foreach ( $keys as $key ) {
				delete_network_option( get_current_network_id(), $key );
			}
		} else {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
			foreach ( $keys as $key ) {
				delete_option( $key );
			}
		}
	}

	/**
	 * Iterate site IDs in bounded pages and always restore the caller's context.
	 */
	private static function for_each_site( $callback ) {
		if ( ! is_multisite() ) {
			$callback();
			return;
		}
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'network_id' => get_current_network_id(),
					'fields'     => 'ids',
					'number'     => 100,
					'offset'     => $offset,
					'orderby'    => 'id',
					'order'      => 'ASC',
				)
			);
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				try {
					$callback();
				} finally {
					restore_current_blog();
				}
			}
			$offset += 100;
		} while ( count( $sites ) === 100 );
	}

	/**
	 * Task that does the preloading of each item (url).
	 *
	 * @param mixed $item  Queue item to iterate over.
	 *
	 * @return bool
	 */
	protected function task( $item ) {
		$item = is_string( $item ) ? json_decode( $item, true ) : $item;
		if ( is_array( $item ) ) {
			$is_mobile = $item['is_mobile'];
			$url       = $item['url'];
		} else {
			// Handle the case when $item is not an array.
			$is_mobile = false;
			$url       = $item;
		}

		$args = array(
			'timeout'    => 0.01,
			'blocking'   => false,
			'user-agent' => $is_mobile ? Utils::get_mobile_user_agent() : 'Hummingbird ' . WPHB_VERSION . '/Cache Preloader',
			'sslverify'  => false,
		);

		$response = wp_remote_get( esc_url_raw( $url ), $args );
		usleep( 500000 );

		return ! is_wp_error( $response );
	}

	/**
	 * Populate the queue for preloading with the provided URL, or preload all pages.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url  URL of the page to preload. Leave blank to preload all.
	 */
	private function preload( $url ) {
		$this->start_preload( array( $url ) );
	}

	/**
	 * Check if the desired path is already cached in the filesystem.
	 *
	 * @since 2.7.3
	 *
	 * @param string $path  Path to cacche.
	 *
	 * @return bool
	 */
	private function is_cached( $path ) {
		global $wphb_fs;

		// Init filesystem.
		if ( ! $wphb_fs ) {
			$wphb_fs = Filesystem::instance();
		}

		$http_host = '';
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$http_host = htmlentities( wp_unslash( $_SERVER['HTTP_HOST'] ) ); // Input var ok.
		} elseif ( function_exists( 'get_option' ) ) {
			$http_host = preg_replace( '/https?:\/\//', '', get_option( 'siteurl' ) );
		}

		return is_dir( $wphb_fs->cache_dir . $http_host . $path );
	}

	/**
	 * Callback function after clearing cache for a page/post.
	 *
	 * @since 2.1.0
	 *
	 * @param string $path  Path to page.
	 */
	public function preload_page_on_purge( $path ) {
		// Do not parse empty paths.
		if ( ! $path ) {
			return;
		}

		// Do not preload if not enabled.
		$enabled = Settings::get_setting( 'preload', 'page_cache' );
		if ( ! $enabled ) {
			return;
		}

		if ( $this->is_cached( $path ) ) {
			return;
		}

		$types = Settings::get_setting( 'preload_type', 'page_cache' );

		if ( isset( $types['on_clear'] ) && $types['on_clear'] ) {
			$url = get_option( 'home' ) . $path;
			$this->preload( $this->get_preload_request( $url ) );
		}
	}

	/**
	 * Preload home page.
	 *
	 * @since 2.3.0
	 */
	public function preload_home_page() {
		$requests = array( $this->get_preload_request( get_option( 'home' ) ) );
		if ( Utils::is_mobile_preload_allowed() ) {
			$requests[] = $this->get_preload_request( get_option( 'home' ), true );
		}
		$this->start_preload( $requests );
	}

	/**
	 * Get preload request data.
	 *
	 * @param string $url        URL of the page to preload.
	 * @param bool   $is_mobile  Is preload for mobile device.
	 */
	public function get_preload_request( $url, $is_mobile = false ) {
		if ( SafeMode::instance()->get_status() ) {
			$url = add_query_arg( 'wphb_preview_safe_mode', 'true', $url );
		}

		return array(
			'url'       => $url,
			'is_mobile' => $is_mobile,
		);
	}
}
