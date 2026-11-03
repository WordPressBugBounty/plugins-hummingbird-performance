<?php
/**
 * Package Configs service.
 *
 * Provides connection to the WPMU DEV Hub API's `package-configs` endpoint,
 * used to sync saved Configs presets (Settings > Configs) with the Hub.
 *
 * @package Hummingbird\Core\Api\Service
 */

namespace Hummingbird\Core\Api\Service;

use Hummingbird\Core\Api\Request\WPMUDEV;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Package_Configs extends Service.
 */
class Package_Configs extends Service {

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
	 * Package_Configs constructor.
	 *
	 * @throws \Hummingbird\Core\Api\Exception  Exception.
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
	 * Push a config to the Hub.
	 *
	 * @param array $data Config payload (name, description, package, config).
	 *
	 * @return array|object|WP_Error
	 */
	public function sync_config( $data ) {
		$this->request->add_header_argument( 'Content-Type', 'application/json' );

		return $this->request->post( 'package-configs', wp_json_encode( $data ) );
	}

	/**
	 * Fetch this plugin's configs from the Hub.
	 *
	 * @param string $package_id WPMU DEV package id.
	 *
	 * @return array|object|WP_Error
	 */
	public function get_configs( $package_id ) {
		return $this->request->get(
			'package-configs',
			array(
				'package_id' => $package_id,
			)
		);
	}

	/**
	 * Delete a config from the Hub.
	 *
	 * @param string $config_id Hub config id.
	 *
	 * @return array|object|WP_Error
	 */
	public function delete_config( $config_id ) {
		try {
			return $this->request->request( 'package-configs/' . rawurlencode( $config_id ), array(), 'delete' );
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}
}
