<?php
/**
 * Integration with WooCommerce theme.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerce
 */
class WooCommerce {

	/**
	 * List of jQuery handles that should not be optimized.
	 *
	 * @var array
	 */
	private $jquery_handles = array( 'jquery-core', 'jquery-migrate' );

	/**
	 * WooCommerce constructor.
	 */
	public function __construct() {
		// Only add hooks if WooCommerce is active.
		if ( $this->is_woocommerce_active() ) {
			add_filter( 'wphb_should_add_critical_css', array( $this, 'disable_optimization_for_cart_and_checkout' ) );
			add_filter( 'wphb_should_delay_js', array( $this, 'disable_optimization_for_cart_and_checkout' ) );
			add_filter( 'wp', array( $this, 'disable_minify_on_woo_pages' ) );
			add_filter( 'wphb_minify_resource', array( $this, 'filter_resource_minify' ), 10, 4 );
			add_filter( 'wphb_combine_resource', array( $this, 'filter_resource_combine' ), 10, 3 );
		}
	}

	/**
	 * Disable minify on WooCommerce pages.
	 *
	 * @since 3.10.0
	 */
	public function disable_minify_on_woo_pages() {
		if ( $this->should_disable_optimization() ) {
			add_filter( 'wp_hummingbird_is_active_module_minify', '__return_false' );
		}
	}

	/**
	 * Disable optimization for Cart and Checkout pages.
	 *
	 * @param bool $should_optimize Whether optimization should be enabled.
	 * @return bool
	 */
	public function disable_optimization_for_cart_and_checkout( $should_optimize ) {
		return $this->should_disable_optimization() ? false : $should_optimize;
	}

	/**
	 * Check if optimization should be disabled for Cart and Checkout pages.
	 *
	 * @return bool
	 */
	private function should_disable_optimization() {
		// Early return if optimization is explicitly enabled.
		if ( defined( 'WPHB_ENABLE_WOO_OPTIMIZATION' ) && WPHB_ENABLE_WOO_OPTIMIZATION ) {
			return false;
		}

		// Check if we're on cart or checkout pages.
		return is_cart() || is_checkout();
	}

	/**
	 * Filter minified resources.
	 *
	 * @param bool   $value   Current value.
	 * @param string $handle  Resource handle.
	 * @param string $type    Script or style.
	 * @param string $url     Script URL.
	 *
	 * @return bool
	 */
	public function filter_resource_minify( $value, $handle, $type, $url ) {
		return $this->should_exclude_jquery_handle( $handle ) ? false : $value;
	}

	/**
	 * Filter combine resources.
	 *
	 * @param bool   $value   Current value.
	 * @param string $handle  Resource handle.
	 * @param string $type    Script or style.
	 *
	 * @return bool
	 */
	public function filter_resource_combine( $value, $handle, $type ) {
		return $this->should_exclude_jquery_handle( $handle ) ? false : $value;
	}

	/**
	 * Check if a handle should be excluded from optimization (jQuery handles).
	 *
	 * @param string $handle Resource handle.
	 * @return bool
	 */
	private function should_exclude_jquery_handle( $handle ) {
		return in_array( $handle, $this->jquery_handles, true );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return function_exists( 'is_woocommerce' ) && class_exists( 'WooCommerce' );
	}
}
