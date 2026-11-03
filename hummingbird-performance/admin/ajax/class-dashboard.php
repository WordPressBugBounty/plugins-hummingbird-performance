<?php
/**
 * Dashboard AJAX actions for the new React UI.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard
 */
class Dashboard {

	/**
	 * Dashboard constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_dashboard_clear_cache', array( $this, 'clear_cache' ) );
	}

	/**
	 * Clear every active cache type.
	 *
	 * @since 4.0.0
	 */
	public function clear_cache() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		foreach ( array_keys( Utils::get_active_cache_modules() ) as $module ) {
			$mod = Utils::get_module( $module );

			// The list is filtered, so an entry can name a module that is not loaded.
			if ( false === $mod || ! $mod->is_active() ) {
				continue;
			}

			if ( 'minify' === $module ) {
				$mod->clear_files();
			} else {
				$mod->clear_cache();
			}
		}

		wp_send_json_success(
			array(
				'message' => __( 'Cache purged.', 'wphb' ),
			)
		);
	}
}
