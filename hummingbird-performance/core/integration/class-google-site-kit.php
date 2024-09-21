<?php
/**
 * Integration with Site Kit by Google.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Google_Site_Kit
 */
class Google_Site_Kit {

	/**
	 * Google_Site_Kit constructor.
	 */
	public function __construct() {
		add_filter( 'wphb_api_request_timeout', array( $this, 'wphb_api_request_timeout' ) );
	}

	/**
	 * Increase API request timeout if Google_Site_Kit plugin is enabled.
	 *
	 * @param bool $timeout API request timeout.
	 *
	 * @return bool
	 */
	public function wphb_api_request_timeout( $timeout ) {
		if ( $this->is_google_site_kit_active() ) {
			$timeout = 30;
		}

		return $timeout;
	}

	/**
	 * Check if Google_Site_Kit is active.
	 *
	 * @return bool
	 */
	private function is_google_site_kit_active() {
		return defined( 'GOOGLESITEKIT_VERSION' ) && GOOGLESITEKIT_VERSION;
	}
}
