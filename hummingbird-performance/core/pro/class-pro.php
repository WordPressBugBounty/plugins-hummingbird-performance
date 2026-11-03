<?php
/**
 * Class Pro manages the premium side of Hummingbird
 *
 * @since 1.5.0
 * @package Hummingbird\Core\Pro
 */

namespace Hummingbird\Core\Pro;

use Hummingbird\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pro
 */
class Pro {

	/**
	 * Class instance
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Saves the modules object instances
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public $modules = array();

	/**
	 * Return the plugin instance
	 *
	 * @return Pro
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class
	 *
	 * @since 1.5.0
	 */
	public function init() {
		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {
			include_once ABSPATH . 'wp-includes/plugin.php';
		}

		// No `$wpmudev_notices` screens: the Dashboard-required modal replaces the legacy banner on
		// Hummingbird pages. The library is still needed for the modal's one-click install link.
		/* @noinspection PhpIncludeInspection */
		if ( ! defined( 'WPHB_WPORG' ) ) {
			include_once WPHB_DIR_PATH . 'core/externals/dash-notice/wpmudev-dash-notification.php';
		}

		$this->load_modules();
	}

	/**
	 * Load AJAX functionality
	 *
	 * @since 1.5.0
	 */
	public function load_ajax() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			new Pro_AJAX();
		}
	}

	/**
	 * Load WP Hummingbird Pro modules
	 *
	 * @since 1.5.0
	 */
	private function load_modules() {
		$modules = apply_filters(
			'wp_hummingbird_modules',
			array(
				'reports-performance',
				'reports-uptime',
				'reports-database',
				'notifications',
			)
		);

		array_walk( $modules, array( $this, 'load_module' ), true );
	}

	/**
	 * Load a single module
	 *
	 * @param string $module  Module slug.
	 *
	 * @since 1.5.0
	 */
	public function load_module( $module ) {
		$parts = explode( '-', $module );
		$parts = array_map( 'ucfirst', $parts );
		$class = implode( '_', $parts );

		$class_name = 'Hummingbird\\Core\\Pro\\Modules\\' . $class;

		/**
		 * Module.
		 *
		 * @var Module $module_obj
		 */
		$module_obj = new $class_name( $module );

		if ( $module_obj instanceof $class_name ) {
			if ( $module_obj->is_active() ) {
				$module_obj->run();
			}

			$this->modules[ $module ] = $module_obj;
		}
	}

	/**
	 * Get a pro module instance
	 *
	 * @since 3.1.1
	 *
	 * @param string $module Module slug.
	 *
	 * @return bool|Modules\Notifications
	 */
	public function module( $module ) {
		return isset( $this->modules[ $module ] ) ? $this->modules[ $module ] : false;
	}

}
