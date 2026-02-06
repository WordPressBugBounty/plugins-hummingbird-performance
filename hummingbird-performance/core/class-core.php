<?php
/**
 * Core class.
 *
 * @package Hummingbird\Core
 */

namespace Hummingbird\Core;

use Hummingbird\Core\Modules\Minify;
use WP_Admin_Bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Core
 */
class Core {

	/**
	 * API
	 *
	 * @var Api\API
	 */
	public $api;

	/**
	 * Hummingbird logs
	 *
	 * @since 1.9.2
	 * @var Logger
	 */
	public $logger;

	/**
	 * Saves the modules object instances
	 *
	 * @var array
	 */
	public $modules = array();

	/**
	 * Core constructor.
	 */
	public function __construct() {
		$this->init();
		$this->init_integrations();
		$this->load_modules();

		SafeMode::instance()->init_safe_mode_box();

		// Return is user has no proper permissions.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			return;
		}

		$this->add_menu_bar_actions();
	}

	/**
	 * Initialize core modules.
	 *
	 * @since 1.7.2
	 */
	private function init() {
		// Register private policy text.
		add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
		add_filter( 'wpmudev_notices_is_disabled', array( $this, 'wpmudev_remove_email_from_disabled_list' ), 10, 3 );

		Hub_Connector::get_instance();

		// Init the API.
		$this->api = new Api\API();

		// Init logger.
		$this->logger = Logger::get_instance();

		// Load the cross sell module.
		add_action( 'init', array( $this, 'load_cross_sell_module' ), 9 );
	}

	/**
	 * Init integration modules.
	 *
	 * @since 2.1.0
	 */
	private function init_integrations() {
		new Integration\Builders();
		new Integration\Divi();
		new Integration\Gutenberg();
		new Integration\WPH();
		new Integration\SiteGround();
		Integration\Opcache::get_instance();
		Integration\Weglot::get_instance();
		new Integration\Wpengine();
		new Integration\WPMUDev();
		new Integration\Defender();
		new Integration\Avada();
		new Integration\OxygenBuilder();
		new Integration\Google_Site_Kit();
		new Integration\WooCommerce();
		new Integration\WCML();
		new Integration\Gtranslate();
		new Integration\Elementor();
		new Integration\The_Events_Calendar();
		new Integration\Forminator();
		new Integration\Breakdance();
	}

	/**
	 * Load WP Hummingbird modules
	 */
	private function load_modules() {
		/**
		 * Filters the modules slugs list
		 */
		$modules = apply_filters(
			'wp_hummingbird_modules',
			array( 'minify', 'gzip', 'caching', 'performance', 'uptime', 'cloudflare', 'gravatar', 'page_cache', 'advanced', 'rss', 'redis', 'delayjs', 'critical_css', 'mixpanel_analytics', 'exclusions', 'background_processing' )
		);

		array_walk( $modules, array( $this, 'load_module' ) );
	}

	/**
	 * Add menu bar actions.
	 */
	private function add_menu_bar_actions() {
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			return;
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_global' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_global' ) );

		// Defer the loading of the global js.
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 2 );
	}

	/**
	 * Load a single module
	 *
	 * @param string $module  Module slug.
	 */
	public function load_module( $module ) {
		$parts = explode( '_', $module );
		$parts = array_map( 'ucfirst', $parts );
		$class = implode( '_', $parts );

		$class_name = 'Hummingbird\\Core\\Modules\\' . ucfirst( $class );

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
			$this->logger->register_module( $module );
		}
	}

	/**
	 * Add HB menu to the admin bar
	 *
	 * @param WP_Admin_Bar $admin_bar  Admin bar.
	 */
	public function admin_bar_menu( $admin_bar ) {
		$menu = array();

		$active_modules = Utils::get_active_cache_modules();
		if ( empty( $active_modules ) ) {
			return; // No active caching modules - exit.
		}

		$minify           = Settings::get_setting( 'enabled', 'minify' );
		$pc_module        = Settings::get_setting( 'enabled', 'page_cache' );
		$is_cache_enabled = true === (bool) $pc_module || Utils::get_api()->hosting->has_fast_cgi_header();

		// Do not strict compare $pc_module to true, because it can also be 'blog-admins'.
		if ( ! is_multisite() || ( ( 'super-admins' === $minify && is_super_admin() ) || true === $minify || $is_cache_enabled ) ) {
			$cache_control = Settings::get_setting( 'control', 'settings' );
			if ( true === $cache_control ) {
				$menu['wphb-clear-all-cache'] = array( 'title' => __( 'Clear all cache', 'wphb' ) );
			} elseif ( is_array( $cache_control ) ) {
				foreach ( $active_modules as $module => $name ) {
					if ( ! in_array( $module, $cache_control, true ) ) {
						continue;
					}

					if ( 'cloudflare' === $module ) {
						if ( Utils::get_module( 'cloudflare' )->is_connected() && Utils::get_module( 'cloudflare' )->is_zone_selected() ) {
							$menu['wphb-clear-cloudflare'] = array( 'title' => __( 'Clear Cloudflare cache', 'wphb' ) );
						}

						continue;
					}

					$menu[ 'wphb-clear-cache-' . $module ] = array(
						'title' => __( 'Clear', 'wphb' ) . ' ' . strtolower( $name ),
						'meta'  => array(
							'onclick' => "WPHBGlobal.clearCache(\"$module\");",
						),
					);
				}
			}
		}

		if ( is_multisite() && is_network_admin() && $is_cache_enabled && ! empty( $cache_control ) ) {
			if ( true === $cache_control || ( is_array( $cache_control ) && in_array( 'page_cache', $cache_control, true ) ) ) {
				$menu['wphb-clear-cache-network-wide'] = array( 'title' => __( 'Clear page cache on all subsites', 'wphb' ) );
			}
		}

		if ( ! is_admin() ) {
			if ( Utils::get_module( 'minify' )->is_active() ) {
				$avoid_minify = filter_input( INPUT_GET, 'avoid-minify', FILTER_VALIDATE_BOOLEAN );

				$menu['wphb-page-minify'] = array(
					'title' => $avoid_minify ? __( 'See this page minified', 'wphb' ) : __( 'See this page unminified', 'wphb' ),
					'href'  => $avoid_minify ? remove_query_arg( 'avoid-minify' ) : add_query_arg( 'avoid-minify', 'true' ),
				);
			}
		}

		if ( empty( $menu ) ) {
			return;
		}

		$menu_args = array(
			'id'    => 'wphb',
			'title' => __( 'Hummingbird', 'wphb' ),
			'href'  => admin_url( 'admin.php?page=wphb' ),
		);

		if ( is_multisite() && is_main_site() ) {
			$menu_args['href'] = network_admin_url( 'admin.php?page=wphb' );
		} elseif ( is_multisite() && ! is_main_site() ) {
			unset( $menu_args['href'] );
		}

		$admin_bar->add_node( $menu_args );
		foreach ( $menu as $id => $tab ) {
			$admin_bar->add_node(
				array(
					'id'     => $id,
					'parent' => $menu_args['id'],
					'title'  => $tab['title'],
					'href'   => isset( $tab['href'] ) ? $tab['href'] : '#',
					'meta'   => isset( $tab['meta'] ) ? $tab['meta'] : '',
				)
			);
		}
	}

	/**
	 * Enqueue global scripts.
	 *
	 * @since 1.9.3
	 */
	public function enqueue_global() {
		wp_enqueue_script(
			'wphb-global',
			WPHB_DIR_URL . 'admin/assets/js/wphb-global.min.js',
			array( 'underscore', 'jquery' ),
			WPHB_VERSION,
			true
		);

		$is_hb_page = is_admin() && preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id );

		wp_localize_script(
			'wphb-global',
			'wphbGlobal',
			array(
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wphb-fetch' ),
				'minify_url'       => admin_url( 'admin.php?page=wphb-minification' ),
				'is_hb_page'       => $is_hb_page,
				'copyText'         => esc_html__( 'Link copied', 'wphb' ),
				'previewLink'      => SafeMode::instance()->get_safe_mode_preview_url(),
				'hb_dashboard_url' => admin_url( 'admin.php?page=wphb' ),
				'publishing'       => esc_html__( 'Publishing...', 'wphb' ),
			)
		);

		global $pagenow;
		if ( is_admin() && ! $is_hb_page && 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
			$args = array(
				'nonces' => array(
					'HBFetchNonce' => wp_create_nonce( 'wphb-fetch' ),
				),
			);

			$args = array_merge_recursive( $args, Utils::get_tracking_data() );
			wp_localize_script( 'wphb-global', 'wphb', $args );
		}
	}

	/**
	 * Defer global scripts.
	 *
	 * @since 1.9.3
	 *
	 * @param string $tag     HTML element tag.
	 * @param string $handle  Script handle.
	 *
	 * @return string
	 */
	public function add_defer_attribute( $tag, $handle ) {
		if ( 'wphb-global' !== $handle ) {
			return $tag;
		}
		return str_replace( ' src', ' defer="defer" src', $tag );
	}

	/**
	 * Register private policy text.
	 */
	public function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf( /* translators: %1$s - Text, %2$s - Link to privacy policy page */
			'<h3>%1$s</h3><p>%2$s</p>',
			__( 'Third parties', 'wphb' ),
			sprintf(
				/* translators: %1$s - opening a tag, %2$s - closing a tag */
				__( 'Hummingbird uses the Stackpath Content Delivery Network (CDN). Stackpath may store web log information of site visitors, including IPs, UA, referrer, Location and ISP info of site visitors for 7 days. Files and images served by the CDN may be stored and served from countries other than your own. Stackpath’s privacy policy can be found %1$shere%2$s.', 'wphb' ),
				'<a href="https://www.stackpath.com/legal/privacy-statement/" target="_blank">',
				'</a>'
			)
		);

		wp_add_privacy_policy_content(
			__( 'Hummingbird', 'wphb' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Removed the email prompt notice form disabled list and adding the giveaway to the disabled list.
	 *
	 * @param bool   $is_disabled Is notice disabled.
	 * @param string $type        Notice type.
	 * @param string $plugin      Plugin ID.
	 *
	 * @return bool
	 */
	public function wpmudev_remove_email_from_disabled_list( $is_disabled, $type, $plugin ) {
		if ( 'hummingbird' === $plugin && 'email' === $type ) {
			return false;
		}

		if ( 'rate' === $type && 'yes' !== get_option( 'wphb-notice-free-rated-show' ) ) {
			return true;
		}

		return $is_disabled;
	}

	/**
	 * Load cross sell module.
	 *
	 * @return bool
	 */
	public function load_cross_sell_module() {
		if ( Utils::is_member() ) {
			return;
		}

		$cross_sell_plugin_file = WPHB_DIR_PATH . 'core/externals/plugins-cross-sell-page/plugin-cross-sell.php';
		if ( ! file_exists( $cross_sell_plugin_file ) ) {
			return;
		}

		static $cross_sell_handler = null;
		if ( ! is_null( $cross_sell_handler ) ) {
			return;
		}

		if ( ! class_exists( '\WPMUDEV\Modules\Plugin_Cross_Sell' ) ) {
			require_once $cross_sell_plugin_file;
		}

		$submenu_params = array(
			'slug'            => 'hummingbird-performance',
			'parent_slug'     => 'wphb',
			'menu_slug'       => 'wphb-cross-sell',
			'position'        => 8,
			'translation_dir' => WPHB_DIR_PATH . 'languages',
		);

		$cross_sell_handler = new \WPMUDEV\Modules\Plugin_Cross_Sell( $submenu_params );
	}
}
