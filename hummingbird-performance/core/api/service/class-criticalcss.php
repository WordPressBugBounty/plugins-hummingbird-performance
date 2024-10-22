<?php
/**
 * Establishes a connection to the WPMU API for generating critical CSS.
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
 * Class CriticalCss extends Service.
 */
class CriticalCss extends Service {

	/**
	 * Endpoint name.
	 *
	 * @var string $name
	 */
	public $name = 'performance';

	/**
	 * API version.
	 *
	 * @access private
	 * @var    string $version
	 */
	private $version = 'v3';

	/**
	 * Timeout.
	 *
	 * @var int
	 */
	const CRITICAL_API_TIMEOUT = 120;

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
	 * Designed to facilitate the process of obtaining Critical CSS for a given URL. It achieves this by connecting to a dedicated API service responsible for generating the Critical CSS content.
	 *
	 * @since 3.6.0
	 *
	 * @param array  $urls    URLs to generate critical CSS.
	 * @param string $type    Types of critical CSS generation: CRITICAL for above-the-fold and PURGE for the entire page's CSS.
	 * @param array  $ignored URLs to ignore.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function generate_critical_css( $urls, $type = 'CRITICAL', $ignored = array() ) {
		$this->request->set_timeout( self::CRITICAL_API_TIMEOUT );

		return $this->request->post(
			'critical-css/calculate/',
			array(
				'domain'  => $this->get_network_home_url_on_subsite(),
				'type'    => $type,
				'items'   => $urls,
				'ignored' => $ignored,
			)
		);
	}

	/**
	 * Designed to retrieve and obtain Critical CSS for a specific ID.
	 *
	 * @since 3.6.0
	 *
	 * @param string $id Id for the generated queue.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function get_generated_critical_css( $id ) {
		$this->request->set_timeout( self::CRITICAL_API_TIMEOUT );

		return $this->request->get(
			'critical-css/get/',
			array(
				'id'     => $id,
				'domain' => $this->get_network_home_url_on_subsite(),
			)
		);
	}

	/**
	 * Designed to retrieve network home url on subsite.
	 *
	 * @since 3.6.0
	 *
	 * @return string
	 */
	public function get_network_home_url_on_subsite() {
		return is_multisite() && ! is_main_site() ? network_home_url() : $this->request->get_this_site();
	}
}
