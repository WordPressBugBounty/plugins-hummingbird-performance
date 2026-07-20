<?php
/**
 * Defender integration.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

use Hummingbird\Core\Modules\Page_Cache;
use Hummingbird\Core\Utils;
use WP_Defender\Component\Bot_Trap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Defender
 */
class Defender {

	/**
	 * WPDefender constructor.
	 */
	public function __construct() {
		if ( ! $this->is_active() ) {
			return;
		}

		// Redirect Current Hummingbird admin URL to Mask Login URL.
		add_filter( 'wpdef_maybe_redirect_to_mask_login_url', array( $this, 'maybe_redirect_to_mask_login_url' ), 10, 2 );
		add_action( 'wpdef_rotate_malicious_bot_secret_hash', array( $this, 'clear_page_cache_on_bot_trap_hash_change' ), 11 );
	}

	/**
	 * Check and redirect current Hummingbird admin URL to mask login URL.
	 *
	 * @param bool   $allowed     Should we redirect to Mask Login URL?.
	 * @param string $current_url Current URL to check.
	 * @return bool
	 */
	public function maybe_redirect_to_mask_login_url( $allowed, $current_url ) {
		$current_page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$current_page = sanitize_text_field( $current_page );

		if ( is_admin() && ! empty( $current_page ) && false !== strpos( $current_page, 'wphb' ) ) {
			return true;
		}

		return $allowed;
	}

	/**
	 * Check if Defender plugin is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return apply_filters( 'wphb_defender_is_active', defined( 'DEFENDER_VERSION' ) && DEFENDER_VERSION );
	}

	/**
	 * Clear Hummingbird page cache when Defender Bot Trap hash changes.
	 *
	 * @since 3.19.0
	 * @return void
	 */
	public function clear_page_cache_on_bot_trap_hash_change(): void {

		if ( ! class_exists( Bot_Trap::class ) ) {
			return;
		}

		if ( apply_filters( 'wphb_defender_skip_clear_page_cache_on_bot_trap_hash_change', false ) ) {
			return;
		}

		$page_cache = Utils::get_module( 'page_cache' );

		if ( ! $page_cache instanceof Page_Cache || ! $page_cache->is_active() ) {
			return;
		}

		$page_cache->clear_cache();
	}

}
