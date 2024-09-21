<?php
/**
 * Compatibility with WCML.
 *
 * @since 3.9.3
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCML
 */
class WCML {

	/**
	 * Hummingbird WCML Integration constructor.
	 */
	public function __construct() {
		if ( ! $this->is_wcml_active() ) {
			return;
		}

		add_filter( 'wcml_is_cache_enabled_for_switching_currency', array( $this, 'is_cache_enabled_for_switching_currency' ) );
	}

	/**
	 * Check if cache is enabled for switching currency.
	 *
	 * @param bool $cache_enabled Cache enabled.
	 *
	 * @return bool
	 */
	public function is_cache_enabled_for_switching_currency( $cache_enabled ) {
		if ( Utils::get_module( 'page_cache' )->is_active() ) {
			$cache_enabled = true;
		}

		return $cache_enabled;
	}

	/**
	 * Check if WCML is active.
	 *
	 * @return bool
	 */
	private function is_wcml_active() {
		return defined( 'WCML_VERSION' ) && defined( 'ICL_SITEPRESS_VERSION' );
	}
}
