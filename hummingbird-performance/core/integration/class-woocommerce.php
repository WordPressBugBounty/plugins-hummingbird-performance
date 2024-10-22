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
	 * WooCommerce constructor.
	 */
	public function __construct() {
		add_filter( 'wphb_should_add_critical_css', array( $this, 'wphb_disable_optimization_for_cart_and_checkout' ) );
		add_filter( 'wphb_should_delay_js', array( $this, 'wphb_disable_optimization_for_cart_and_checkout' ) );
		add_filter( 'wp', array( $this, 'disable_minify_on_woo_pages' ) );
	}

	/**
	 * Disable minify on WooCommerce pages.
	 *
	 * @since 3.10.0
	 */
	public function disable_minify_on_woo_pages() {
		if ( $this->is_woocommerce_active() && $this->wphb_should_disable_optimization_for_cart_and_checkout() ) {
			add_filter( 'wp_hummingbird_is_active_module_minify', '__return_false' );
		}
	}

	/**
	 * Disable optimization for Cart and Checkout pages.
	 *
	 * @return bool
	 */
	public function wphb_disable_optimization_for_cart_and_checkout( $should_optimize ) {
		if ( $this->is_woocommerce_active() && $this->wphb_should_disable_optimization_for_cart_and_checkout() ) {
			return false;
		}

		return $should_optimize;
	}

	/**
	 * Check if optimization should be disabled for Cart and Checkout pages.
	 *
	 * @return bool
	 */
	public function wphb_should_disable_optimization_for_cart_and_checkout() {
		return ( ! defined( 'WPHB_ENABLE_WOO_OPTIMIZATION' ) || ! WPHB_ENABLE_WOO_OPTIMIZATION ) && ( is_cart() || is_checkout() );
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
