<?php
/**
 * Integration with Elementor.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

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
}
