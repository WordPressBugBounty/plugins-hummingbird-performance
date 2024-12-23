<?php
/**
 * Provides connection to WPMU API to perform queries against Hosting endpoints.
 *
 * @sice 3.3.1
 *
 * @package Hummingbird
 */

namespace Hummingbird\Core\Api\Service;

use Hummingbird\Core\Api\Exception;
use Hummingbird\Core\Api\Request\WPMUDEV;
use Hummingbird\Core\Modules\Caching\Fast_CGI;
use WP_Error;
use WPMUDEV_Dashboard;
use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hosting extends Service.
 */
class Hosting extends Service {
	/**
	 * Endpoint name.
	 *
	 * @var string $name
	 */
	public $name = 'hub';

	/**
	 * API version.
	 *
	 * @access private
	 *
	 * @var string $version
	 */
	private $version = 'v1';

	/**
	 * Performance constructor.
	 *
	 * @throws Exception  Exception.
	 */
	public function __construct() {
		$this->request = new WPMUDEV( $this );
	}

	/**
	 * Getter method for api version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get hosting info.
	 *
	 * @param int $site_id  Site ID.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function get_info( $site_id ) {
		return $this->request->get(
			'sites/' . $site_id . '/modules/hosting',
			array(
				'domain' => $this->request->get_this_site(),
			)
		);
	}

	/**
	 * Enable/Disable FastCGI.
	 *
	 * @param bool $action Whether to enable or disable fastCGI.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function toggle_fast_cgi( $action ) {
		$site_id = $this->get_site_id();
		if ( ! $site_id ) {
			return false;
		}

		if ( false === $action ) {
			Fast_CGI::clear_fast_cgi_status();
		}

		$this->request->add_post_argument( 'is_active', $action );

		return $this->request->put(
			'sites/' . $site_id . '/modules/hosting/static-cache',
			array(
				'domain' => $this->request->get_this_site(),
			)
		);
	}

	/**
	 * Update FastCGI settings.
	 *
	 * @param array $data An array of fastCGI data.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function wphb_update_fast_cgi_settings( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		$site_id = $this->get_site_id();

		if ( ! $site_id ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, Fast_CGI::get_fast_cgi_settings_field(), true ) ) {
				continue;
			}

			$this->request->add_post_argument( $key, $value );
		}

		$response = $this->request->put(
			'sites/' . $site_id . '/modules/hosting/static-cache',
			array(
				'domain' => $this->request->get_this_site(),
			)
		);

		if ( ! is_wp_error( $response ) && ! empty( $response ) ) {
			$hosting               = Fast_CGI::get_hosting_detail();
			$hosting               = isset( $hosting->static_cache ) ? $hosting : new stdClass();
			$hosting->static_cache = $response;
			Fast_CGI::update_hosting_detail_transient( $hosting );
		}

		return $response;
	}

	/**
	 * Get site ID from Dashboard plugin.
	 *
	 * @since 3.3.1
	 * @since 3.4.0 Moved here from Setup class.
	 *
	 * @return false|int
	 */
	public function get_site_id() {
		// Only check on WPMU DEV hosting.
		if ( ! isset( $_SERVER['WPMUDEV_HOSTED'] ) ) {
			return false;
		}

		if ( ! class_exists( 'WPMUDEV_Dashboard' ) ) {
			return false;
		}

		if ( ! method_exists( 'WPMUDEV_Dashboard_Api', 'get_site_id' ) ) {
			return false;
		}

		return WPMUDEV_Dashboard::$api->get_site_id();
	}

	/**
	 * Get FastCGI status.
	 *
	 * @since 3.4.0 Moved here from Setup class.
	 *
	 * @return bool
	 */
	public function has_fast_cgi() {
		$site_id = $this->get_site_id();

		if ( $site_id && function_exists( 'wpmudev_hosting_features' ) ) {
			$hosting_details = wpmudev_hosting_features();
			if ( isset( $hosting_details['static_cache'] ) ) {
				Fast_CGI::update_fast_cgi_status( $hosting_details['static_cache'] );
				return $hosting_details['static_cache'];
			}
		}

		return false;
	}

	/**
	 * Check if there's a `x-cache` header on a request, which would mean FastCGI is enabled on a site.
	 *
	 * @since 3.4.0
	 *
	 * @param bool $refresh_data Whether to refresh the data or not.
	 *
	 * @return bool
	 */
	public function has_fast_cgi_header( $refresh_data = false ) {
		static $already_fetched = false;

		// Only check on WPMU DEV hosting.
		if ( ! isset( $_SERVER['WPMUDEV_HOSTED'] ) ) {
			return false;
		}

		$fast_cgi_enabled = Fast_CGI::is_fast_cgi_enabled();

		if ( ! $refresh_data && ( $fast_cgi_enabled || $already_fetched ) ) {
			return $fast_cgi_enabled;
		}

		$head = wp_remote_head(
			home_url(),
			array(
				'sslverify' => false,
			)
		);

		$already_fetched = true;

		if ( ! is_wp_error( $head ) ) {
			$headers = wp_remote_retrieve_headers( $head );
			if ( isset( $headers['x-cache'] ) ) {
				Fast_CGI::update_fast_cgi_status( true );
				return true;
			}
		}

		Fast_CGI::update_fast_cgi_status( false );
		return false;
	}
}
