<?php
/**
 * Integration with Gtranslate.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gtranslate
 */
class Gtranslate {

	/**
	 * Google_Site_Kit constructor.
	 */
	public function __construct() {
		add_filter( 'wphb_dont_add_handle_to_collection', array( $this, 'wphb_dont_add_handle_to_collection' ), 10, 4 );
	}

	/**
	 * Do not add handle to collection for the gtranslate dynamic enqueue script.
	 *
	 * @param bool   $value      Current value.
	 * @param string $handle     Resource handle.
	 * @param string $source_url Script URL.
	 * @param string $type       Resource type.
	 *
	 * @return bool
	 */
	public function wphb_dont_add_handle_to_collection( $value, $handle, $source_url, $type ) {
		if ( 'scripts' === $type && $this->is_gtranslate_active() && strpos( $handle, 'gt_widget_script_' ) !== false ) {
			return true;
		}

		return $value;
	}

	/**
	 * Check if Gtranslate is active.
	 *
	 * @return bool
	 */
	private function is_gtranslate_active() {
		return class_exists( 'GTranslate' ) && class_exists( 'GTranslateWidget' );
	}
}
