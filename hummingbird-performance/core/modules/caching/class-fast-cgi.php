<?php
/**
 * Fast CGI class.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.9.0
 */

namespace Hummingbird\Core\Modules\Caching;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fast_CGI
 */
class Fast_CGI {

	/**
	 * Transient name for hosting details.
	 *
	 * @var string
	 */
	const WPHB_HOSTING_TRANSIENT_NAME = 'wphb_hosting_info';

	/**
	 * Transient name for fast CGI status.
	 *
	 * @var string
	 */
	const WPHB_FAST_CGI_STATUS = 'wphb-fast-cgi-enabled';

	/**
	 * Get fastCGI settings field.
	 *
	 * @return array
	 */
	public static function get_fast_cgi_settings_field() {
		return array( 'ttl', 'bypass_urls', 'query_params' );
	}

	/**
	 * Check if fastCGI can be enable.
	 *
	 * @since 3.9.0
	 * @return bool
	 */
	public static function is_fast_cgi_supported() {
		if ( ! Utils::get_api()->hosting->get_site_id() ) {
			return false;
		}

		if ( ! isset( $_SERVER['WPMUDEV_HOSTING_ENV'] ) || 'production' !== $_SERVER['WPMUDEV_HOSTING_ENV'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get fastCGI settings data.
	 *
	 * @since 3.9.0
	 *
	 * @param bool $refresh_data Refresh data.
	 *
	 * @return object|bool
	 */
	public static function wphb_fast_cgi_data( $refresh_data = false ) {
		$hosting = self::get_hosting_detail( $refresh_data );

		return isset( $hosting->static_cache ) ? $hosting->static_cache : false;
	}

	/**
	 * Check whether the site is hosted with high frequency plan.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	public static function is_high_frequency_hosted_site() {
		$hosting = self::get_hosting_detail();

		return isset( $hosting->plan->is_high_frequency ) ? $hosting->plan->is_high_frequency : false;
	}

	/**
	 * Get hosting info.
	 *
	 * @since 3.9.0
	 *
	 * @param bool $refresh_data Refresh data.
	 */
	public static function get_hosting_detail( $refresh_data = false ) {
		$hosting = get_site_transient( self::WPHB_HOSTING_TRANSIENT_NAME );
		if ( $hosting && false === $refresh_data ) {
			return $hosting;
		}

		$site_id = Utils::get_api()->hosting->get_site_id();
		if ( ! $site_id ) {
			self::update_hosting_detail_transient( false );

			return false;
		}

		$hosting = Utils::get_api()->hosting->get_info( $site_id );
		if ( ! is_object( $hosting ) ) {
			self::update_hosting_detail_transient( false );

			return false;
		}

		self::update_hosting_detail_transient( $hosting );

		return $hosting;
	}

	/**
	 * Returns fastCGI status.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	public static function is_fast_cgi_enabled() {
		return get_site_transient( self::WPHB_FAST_CGI_STATUS );
	}

	/**
	 * Update fastCGI status transient.
	 *
	 * @param bool $status FastCGI status.
	 */
	public static function update_fast_cgi_status( $status ) {
		set_site_transient( self::WPHB_FAST_CGI_STATUS, $status, DAY_IN_SECONDS );
	}

	/**
	 * Clear fastCGI status transient.
	 */
	public static function clear_fast_cgi_status() {
		delete_site_transient( self::WPHB_FAST_CGI_STATUS );
	}

	/**
	 * Update hosting info transient.
	 *
	 * @param object $hosting Hosting details.
	 */
	public static function update_hosting_detail_transient( $hosting ) {
		set_site_transient( self::WPHB_HOSTING_TRANSIENT_NAME, $hosting, DAY_IN_SECONDS );
	}
}
