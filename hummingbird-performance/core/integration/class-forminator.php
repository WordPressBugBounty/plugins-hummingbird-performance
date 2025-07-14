<?php
/**
 * Forminator integration.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Forminator
 */
class Forminator {

	/**
	 * Forminator constructor.
	 */
	public function __construct() {
		if ( ! $this->is_active() ) {
			return;
		}

		// Include form style changes in Forminator.
		add_filter( 'wphb_validate_handle_version', array( $this, 'maybe_not_validate_version' ), 10, 2 );
	}

	/**
	 * Check if we should not validate the version of Forminator handle.
	 *
	 * @param bool   $allowed Whether to allow the handle version check.
	 * @param string $handle  The handle of the script or style.
	 *
	 * @return bool
	 */
	public function maybe_not_validate_version( $allowed, $handle ) {
		if ( (bool) preg_match( '/^forminator-module-css-(\d+)$/', $handle ) !== false ) {
			return false;
		}

		return $allowed;
	}

	/**
	 * Check if Forminator plugin is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return apply_filters( 'wphb_forminator_is_active', defined( 'FORMINATOR_VERSION' ) && FORMINATOR_VERSION );
	}

}
