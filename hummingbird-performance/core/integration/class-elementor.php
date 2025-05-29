<?php
/**
 * Integration with Elementor.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Elementor
 */
class Elementor {

	/**
	 * Elementor constructor.
	 */
	public function __construct() {
		add_filter( 'wphb_dont_add_handle_to_collection', array( $this, 'wphb_dont_add_handle_to_collection' ), 10, 4 );
		add_action( 'elementor/core/files/clear_cache', array( $this, 'clear_cache' ) );
		add_action( 'elementor/maintenance_mode/mode_changed', array( $this, 'clear_cache' ) );
		add_action( 'update_option__elementor_global_css', array( $this, 'clear_cache' ) );
		add_action( 'delete_option__elementor_global_css', array( $this, 'clear_cache' ) );
	}

	/**
	 * Do not add handle to collection for the Elementor dynamic enqueue styles.
	 *
	 * @param bool   $value      Current value.
	 * @param string $handle     Resource handle.
	 * @param string $source_url Script URL.
	 * @param string $type       Resource type.
	 *
	 * @return bool
	 */
	public function wphb_dont_add_handle_to_collection( $value, $handle, $source_url, $type ) {
		if ( 'styles' === $type && $this->is_elementor_active() && strpos( $handle, 'elementor-post-' ) !== false ) {
			return true;
		}

		return $value;
	}

	/**
	 * Check if Elementor is active.
	 *
	 * @return bool
	 */
	private function is_elementor_active() {
		return class_exists( 'Elementor\Plugin' );
	}

	/**
	 * Clear caches when Elementor changes the CSS or change the mode.
	 *
	 * @return void
	 */
	public function clear_cache() {
		if ( ! $this->is_elementor_using_external_css() ) {
			return;
		}

		Utils::get_module( 'page_cache' )->clear_cache();
	}

	/**
	 * Whether Elementor is set to use external CSS files.
	 */
	public function is_elementor_using_external_css() {
		return 'internal' !== get_option( 'elementor_css_print_method' );
	}
}
