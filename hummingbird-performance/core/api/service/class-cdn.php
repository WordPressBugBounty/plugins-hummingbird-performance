<?php
/**
 * Establishes a connection to the WPMU API for CDN.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Core\Api\Service;

use Hummingbird\Core\Api\Exception;
use Hummingbird\Core\Api\Request\WPMUDEV;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDN extends Service.
 */
class CDN extends Service {

	/**
	 * Endpoint name.
	 *
	 * @var string $name
	 */
	public $name = 'smush';

	/**
	 * API version.
	 *
	 * @access private
	 * @var    string $version
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
	 * Maybe enable CDN for the current site.
	 *
	 * @since 4.0
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function maybe_enable_cdn() {
		return $this->request->post(
			'cdn',
			array(
				'api_key' => $this->request->get_api_key(),
				'domain'  => $this->get_domain(),
			)
		);
	}

	/**
	 * Check if CDN is enabled for the current site.
	 *
	 * @since 4.0
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function cdn_check() {
		$api_key = $this->request->get_api_key();

		return $this->request->get(
			"check/{$api_key}",
			array(
				'api_key' => $api_key,
				'domain'  => $this->get_domain(),
			)
		);
	}

	/**
	 * Domain to send to the CDN API.
	 *
	 * Always the main site URL, also on subsites: the WPMU DEV membership belongs to the main site,
	 * so a subsite URL gets "Not a current member".
	 *
	 * @since 4.0
	 *
	 * @return string
	 */
	private function get_domain() {
		return defined( 'WPHB_API_DOMAIN' ) ? WPHB_API_DOMAIN : network_site_url();
	}
}
