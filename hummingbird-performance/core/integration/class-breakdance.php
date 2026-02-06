<?php
/**
 * Integration with breakdance builder.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Breakdance
 */
class Breakdance {

	/**
	 * Breakdance constructor.
	 */
	public function __construct() {
		add_filter( 'wphb_should_delay_js', array( $this, 'wphb_disable_delay_js_for_breakdance' ) );
	}

	/**
	 * Disable delay js for breakdance builder.
	 *
	 * @param bool $should_delay Whether to delay or not.
	 *
	 * @return bool
	 */
	public function wphb_disable_delay_js_for_breakdance( $should_delay ) {
		$breakdance = filter_input( INPUT_GET, 'breakdance', FILTER_UNSAFE_RAW );
		if ( $this->is_breakdance_active() && ! empty( $breakdance ) ) {
			return false;
		}

		return $should_delay;
	}

	/**
	 * Check if Breakdance is active.
	 *
	 * @return bool
	 */
	private function is_breakdance_active() {
		return defined( 'BREAKDANCE_MODE' ) && BREAKDANCE_MODE;
	}
}
