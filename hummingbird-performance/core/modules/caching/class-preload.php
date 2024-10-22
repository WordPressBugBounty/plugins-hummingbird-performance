<?php
/**
 * Preload caache files.
 *
 * @since 2.1.0
 * @package Hummingbird\Core\Modules\Caching
 */

namespace Hummingbird\Core\Modules\Caching;

use Hummingbird\Core\Filesystem;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Preload
 */
class Preload extends Background_Process {

	/**
	 * Database row prefix.
	 *
	 * @since 2.1.0
	 * @var string $prefix
	 */
	protected $prefix = 'wphb';

	/**
	 * Unique process ID.
	 *
	 * @since 2.1.0
	 * @var string $action
	 */
	protected $action = 'cache_preload';

	/**
	 * Task that does the preloading of each item (url).
	 *
	 * @param mixed $item  Queue item to iterate over.
	 *
	 * @return bool
	 */
	protected function task( $item ) {
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
			'user-agent' => $is_mobile ? $this->get_mobile_user_agent() : 'Hummingbird ' . WPHB_VERSION . '/Cache Preloader',
			'sslverify'  => false,
		);

		wp_remote_get( esc_url_raw( $url ), $args );
		usleep( 500000 );

		return false;
	}

	/**
	 * Get mobile user agent.
	 *
	 * @return string
	 */
	private function get_mobile_user_agent() {
		$mobile_user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1';

		/**
		 * Filter the user agent used for preloading, ensuring the HTTP request is detected as coming from a mobile device.
		 *
		 * @param string $mobile_user_agent The mobile user agent.
		 */
		$mobile_user_agent = apply_filters( 'wphb_mobile_user_agent', $mobile_user_agent );

		return 'Hummingbird ' . WPHB_VERSION . '/Cache Preloader ' . $mobile_user_agent;
	}

	/**
	 * Populate the queue for preloading with the provided URL, or preload all pages.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url  URL of the page to preload. Leave blank to preload all.
	 */
	private function preload( $url ) {
		// Try to avoid recursive loops.
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
			if ( preg_match( '/Hummingbird.+?\/Cache Preloader/', $user_agent ) ) {
				return;
			}
		}

		$this->push_to_queue( $url );
		$this->save()->dispatch();
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

		if ( isset( $types['on_clear'] ) && $types['on_clear'] && ! $this->is_process_running() ) {
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
		if ( $this->is_process_running() ) {
			return;
		}

		$this->preload( $this->get_preload_request( get_option( 'home' ) ) );
		if ( Utils::is_mobile_preload_allowed() ) {
			$this->preload( $this->get_preload_request( get_option( 'home' ), true ) );
		}
	}

	/**
	 * Get preload request data.
	 *
	 * @param string $url        URL of the page to preload.
	 * @param bool   $is_mobile  Is preload for mobile device.
	 */
	public function get_preload_request( $url, $is_mobile = false ) {
		return array(
			'url'       => $url,
			'is_mobile' => $is_mobile,
		);
	}
}
