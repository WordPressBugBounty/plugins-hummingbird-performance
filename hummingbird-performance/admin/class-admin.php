<?php
/**
 * Hummingbird admin class.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Admin;

use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\WP_Hummingbird;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Manage the admin core functionality
 */
class Admin {

	/**
	 * Plugin pages.
	 *
	 * @var array
	 */
	public $pages = array();

	/**
	 * Admin notices.
	 *
	 * @var Notices
	 */
	public $admin_notices;

	/**
	 * List of admin pages.
	 *
	 * @since 2.4.0
	 * @var array $admin_pages
	 */
	public static $admin_pages = array(
		'toplevel_page_wphb',
		'toplevel_page_wphb-network',
		'hummingbird_page_wphb-performance',
		'hummingbird_page_wphb-caching',
		'hummingbird_page_wphb-gzip',
		'hummingbird_page_wphb-minification',
		'hummingbird_page_wphb-advanced',
		'hummingbird_page_wphb-uptime',
		'hummingbird_page_wphb-notifications',
		'hummingbird-pro_page_wphb-performance',
		'hummingbird-pro_page_wphb-caching',
		'hummingbird-pro_page_wphb-gzip',
		'hummingbird-pro_page_wphb-minification',
		'hummingbird-pro_page_wphb-advanced',
		'hummingbird-pro_page_wphb-uptime',
		'hummingbird-pro_page_wphb-notifications',
		'hummingbird_page_wphb-performance-network',
		'hummingbird_page_wphb-minification-network',
		'hummingbird_page_wphb-caching-network',
		'hummingbird_page_wphb-gzip-network',
		'hummingbird_page_wphb-uptime-network',
		'hummingbird_page_wphb-notifications-network',
	);

	/**
	 * Admin constructor.
	 */
	public function __construct() {
		$this->admin_notices = Notices::get_instance();

		add_action( 'admin_init', array( $this, 'wphb_refresh_fast_cgi_status' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu_pages' ) );
		add_filter( 'submenu_file', array( $this, 'remove_submenu_item' ) );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			new AJAX();
			new Ajax\Gzip();
			new Ajax\Minify();
			new Ajax\Caching\Browser();
			new Ajax\Caching\Integrations();
			new Ajax\Setup();
		}

		add_action( 'admin_init', array( $this, 'maybe_clear_all_cache' ) );
		add_action( 'admin_init', array( 'Hummingbird\\Core\\Installer', 'maybe_upgrade' ) );
		if ( is_multisite() ) {
			add_action( 'admin_init', array( 'Hummingbird\\Core\\Installer', 'maybe_upgrade_blog' ) );
		}

		add_action( 'admin_footer', array( $this, 'maybe_check_files' ) );

		// Make sure plugin name is correct for adding plugin action links.
		$plugin_name = defined( 'WPHB_WPORG' ) && WPHB_WPORG ? 'hummingbird-performance' : 'wp-hummingbird';
		add_filter( 'network_admin_plugin_action_links_' . $plugin_name . '/wp-hummingbird.php', array( $this, 'add_plugin_action_links' ) );
		add_filter( 'plugin_action_links_' . $plugin_name . '/wp-hummingbird.php', array( $this, 'add_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_meta_links' ), 10, 2 );

		// Filter built-in wpmudev branding script.
		add_filter( 'wpmudev_whitelabel_plugin_pages', array( $this, 'builtin_wpmudev_branding' ) );

		add_action( 'admin_head', array( $this, 'wphb_style_upgrade_pro_upsell' ) );

		// Deactivation survey.
		add_action( 'admin_footer-plugins.php', array( $this, 'load_deactivation_survey_modal' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Triggered when Hummingbird Admin is loaded.
		do_action( 'wphb_admin_loaded' );
	}

	/**
	 * Plugin action on plugin page.
	 *
	 * @param array $actions  Current actions.
	 *
	 * @return array
	 */
	public function add_plugin_action_links( $actions ) {
		// Upgrade link.
		if ( ! Utils::is_member() ) {
			if ( defined( 'WPHB_WPORG' ) && WPHB_WPORG ) {
				$actions['wphb-plugins-upgrade'] = '<a href="' . Utils::get_link( 'plugin', 'hummingbird_pluginlist_upgrade' ) . '" aria-label="' . esc_attr( __( 'Upgrade to Hummingbird Pro', 'wphb' ) ) . '" target="_blank" style="color: #8D00B1;" onclick="window.wphbMixPanel.trackHBUpsell( \'pro_general\', \'plugins_list\', \'cta_clicked\', this.href, \'hb_pro_upsell\' );">' . sprintf( /* translators: %s: Discount percent */ __( 'Upgrade For %s Off!', 'wphb' ), Utils::get_plugin_discount() ) . '</a>';
			} elseif ( ! Utils::is_hosted_site_connected_to_free_hub() ) {
				$actions['wphb-plugins-upgrade'] = '<a href="' . Utils::get_link( 'plugin', 'hummingbird_pluginlist_renew' ) . '" aria-label="' . esc_attr( __( 'Renew Membership', 'wphb' ) ) . '" target="_blank" style="color: #8D00B1;" onclick="window.wphbMixPanel.trackHBUpsell( \'pro_general\', \'plugins_list\', \'cta_clicked\', this.href, \'hb_pro_upsell\' );">' . esc_html__( 'Renew Membership', 'wphb' ) . '</a>';
			}
		}

		// Documentation link.
		$actions['wphb-plugins-docs'] = '<a href="' . Utils::get_link( 'docs', 'hummingbird_pluginlist_docs' ) . '" aria-label="' . esc_attr( __( 'View Hummingbird Documentation', 'wphb' ) ) . '" target="_blank">' . esc_html__( 'Docs', 'wphb' ) . '</a>';

		// Settings link.
		if ( current_user_can( Utils::get_admin_capability() ) ) {
			if ( is_multisite() && ! is_network_admin() ) {
				$url = network_admin_url( 'admin.php?page=wphb' );
			} else {
				$url = Utils::get_admin_menu_url();
			}
			$actions['wphb-plugins-dashboard'] = '<a href="' . $url . '" aria-label="' . esc_attr( __( 'Go to Hummingbird settings', 'wphb' ) ) . '">' . esc_html__( 'Dashboard', 'wphb' ) . '</a>';
		}

		return array_reverse( $actions );
	}

	/**
	 * Add additional links next to the plugin version.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $links  Links array.
	 * @param string $file   Plugin basename.
	 *
	 * @return array
	 */
	public function add_plugin_meta_links( $links, $file ) {
		if ( ! defined( 'WPHB_BASENAME' ) || WPHB_BASENAME !== $file ) {
			return $links;
		}

		if ( defined( 'WPHB_WPORG' ) && WPHB_WPORG ) {
			$links[] = '<a href="https://wordpress.org/support/plugin/hummingbird-performance/reviews/#new-post" target="_blank" title="' . esc_attr__( 'Rate Hummingbird', 'wphb' ) . '">' . esc_html__( 'Rate Hummingbird', 'wphb' ) . '</a>';
			$links[] = '<a href="https://wordpress.org/support/plugin/hummingbird-performance/" target="_blank" title="' . esc_attr__( 'Support', 'wphb' ) . '">' . esc_html__( 'Support', 'wphb' ) . '</a>';
		} else {
			if ( isset( $links[2] ) && false !== strpos( $links[2], 'project/wp-hummingbird' ) ) {
				$links[2] = sprintf( /* translators: %s - Link to Hummingbird project detail page, %s - Text for anchor tag */
					'<a href="%s" target="_blank">%s</a>',
					'https://wpmudev.com/project/wp-hummingbird/',
					__( 'View details', 'wphb' )
				);
			}

			$links[] = '<a href="https://wpmudev.com/get-support/" target="_blank" title="' . esc_attr__( 'Premium Support', 'wphb' ) . '">' . esc_html__( 'Premium Support', 'wphb' ) . '</a>';
		}

		$links[] = '<a href="https://wpmudev.com/roadmap/" target="_blank" title="' . esc_attr__( 'Roadmap', 'wphb' ) . '">' . esc_html__( 'Roadmap', 'wphb' ) . '</a>';

		$links[] = '<a class="wphb-stars" href="https://wordpress.org/support/plugin/hummingbird-performance/reviews/#new-post" target="_blank" rel="noopener noreferrer" title="' . esc_attr__( 'Rate our plugin', 'wphb' ) . '">
					<span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
					</a>';

		echo '<style>.wphb-stars span,.wphb-stars span:hover{color:#ffb900}.wphb-stars span:hover~span{color:#888}</style>';

		return $links;
	}

	/**
	 * Add all the menu pages in admin for the plugin.
	 *
	 * See link below for an explanation on why we only check for pro modules, instead of checking membership.
	 *
	 * @see https://app.asana.com/0/1146162265976268/1137034082303461/f
	 */
	public function add_menu_pages() {
		$hb_title = defined( 'WPHB_WPORG' ) && WPHB_WPORG ? __( 'Hummingbird', 'wphb' ) : __( 'Hummingbird Pro', 'wphb' );

		$current_page = $this->get_current_page_slug();

		$this->hide_the_status_tag();

		$this->pages['wphb']           = new Pages\Dashboard( 'wphb', $hb_title, $hb_title, false, false );
		$this->pages['wphb-dashboard'] = new Pages\Dashboard( 'wphb', __( 'Dashboard', 'wphb' ), __( 'Dashboard', 'wphb' ), 'wphb' );

		if ( ! is_multisite() || is_super_admin() || true === Settings::get_setting( 'subsite_tests', 'performance' ) ) {
			$this->pages['wphb-performance'] = new Pages\Performance( 'wphb-performance', __( 'Performance Test', 'wphb' ), __( 'Performance Test', 'wphb' ), 'wphb' );
		} elseif ( isset( $current_page ) && 'wphb-performance' === $current_page ) {
			// Subsite performance reporting is off, and is a network, let's redirect to network admin.
			$url = add_query_arg( 'view', 'settings', network_admin_url( 'admin.php?page=wphb-performance' ) );
			wp_safe_redirect( $url );
			exit;
		}

		$this->pages['wphb-caching'] = new Pages\Caching( 'wphb-caching', __( 'Caching', 'wphb' ), __( 'Caching', 'wphb' ), 'wphb' );

		if ( ! is_multisite() ) {
			$this->pages['wphb-gzip'] = new Pages\React\Gzip( 'wphb-gzip', __( 'Gzip Compression', 'wphb' ), __( 'Gzip Compression', 'wphb' ), 'wphb' );
		}

		$minify = Settings::get_setting( 'enabled', 'minify' );

		if ( ! is_multisite() || ( ( 'super-admins' === $minify && is_super_admin() ) || ( true === $minify ) ) ) {
			$this->pages['wphb-minification'] = new Pages\Minification( 'wphb-minification', __( 'Asset Optimization', 'wphb' ), __( 'Asset Optimization', 'wphb' ) . $this->get_status_tag_html(), 'wphb' );
		} elseif ( isset( $current_page ) && 'wphb-minification' === $current_page ) {
			// Asset optimization is off, and is a network, let's redirect to network admin.
			$url = add_query_arg( 'minify-instructions', 'true', network_admin_url( 'admin.php?page=wphb#wphb-box-dashboard-minification-network-module' ) );
			wp_safe_redirect( $url );
			exit;
		}

		$this->pages['wphb-advanced'] = new Pages\Advanced( 'wphb-advanced', __( 'Advanced Tools', 'wphb' ), __( 'Advanced Tools', 'wphb' ), 'wphb' );

		if ( ! is_multisite() ) {
			$this->pages['wphb-uptime'] = new Pages\Uptime( 'wphb-uptime', __( 'Uptime', 'wphb' ), __( 'Uptime', 'wphb' ), 'wphb' );
		}

		$this->pages['wphb-notifications'] = new Pages\Notifications( 'wphb-notifications', __( 'Notifications', 'wphb' ), __( 'Notifications', 'wphb' ), 'wphb' );

		if ( ! is_multisite() ) {
			$this->pages['wphb-settings'] = new Pages\Settings( 'wphb-settings', __( 'Settings', 'wphb' ), __( 'Settings', 'wphb' ), 'wphb' );
		}

		if ( ! apply_filters( 'wpmudev_branding_hide_doc_link', false ) ) {
			$this->pages['wphb-tutorials'] = new Pages\React\Tutorials( 'wphb-tutorials', __( 'Tutorials', 'wphb' ), __( 'Tutorials', 'wphb' ), 'wphb' );
		}

		if ( ! Utils::is_member() && ! is_multisite() ) {
			$this->pages['wphb-upgrade'] = new Pages\Upgrade( 'wphb-upgrade', __( 'Hummingbird Pro', 'wphb' ), __( 'Hummingbird Pro', 'wphb' ), 'wphb' );
		}

		if ( $this->wphb_should_add_service_submenu() && ! is_multisite() ) {
			$this->pages['wphb-services'] = new Pages\Services( 'wphb-services', __( 'Expert Services', 'wphb' ), __( 'Expert Services', 'wphb' ) . $this->get_new_tag_html(), 'wphb' );
		}

		$this->pages['wphb-setup'] = new Pages\React\Setup( 'wphb-setup', __( 'Setup Wizard', 'wphb' ), null, 'wphb' );
	}

	/**
	 * Returns status tag html.
	 */
	public function get_status_tag_html() {
		if ( get_option( 'wphb_hide_ao_menu_status_animation' ) ) {
			return '';
		}

		return '<span class="wphb-critical-status-menu"><style>.wphb-critical-status-menu{margin-left:8px;margin-top:6px;position:absolute;vertical-align:middle;width:8px;height:8px;border-radius:50%}.wphb-critical-status-menu,.wphb-critical-status-menu::after,.wphb-critical-status-menu::before{background:#1abc9c}.wphb-critical-status-menu::before{content:"";animation:1.5s infinite wphb-critical-animation}.wphb-critical-status-menu::after{content:"";animation:1.5s -.4s infinite wphb-critical-animation}.wphb-critical-status-menu::after,.wphb-critical-status-menu::before{left:0;top:50%;margin-left:-1px;margin-top:-5px;position:absolute;vertical-align:middle;width:10px;height:10px;border-radius:50%}@keyframes wphb-critical-animation{0%{transform:scale(1);-webkit-transform:scale(1);opacity:1}100%{transform:scale(2);-webkit-transform:scale(2);opacity:0}}@-webkit-keyframes wphb-critical-animation{0%{transform:scale(1);-webkit-transform:scale(1);opacity:1}100%{transform:scale(2);-webkit-transform:scale(2);opacity:0}}</style></span>';
	}

	/**
	 * Returns new tag html.
	 */
	public function get_new_tag_html() {
		return '<span style="margin-left: 10px;padding: 2px 6px;border-radius: 9px;background-color: #1abc9c;color: #FFF;font-size: 8px;letter-spacing: -0.25px;text-transform: uppercase;vertical-align: middle;">' . esc_html__( 'NEW', 'wphb' ) . '</span>';
	}

	/**
	 * Returns current page slug.
	 */
	public function get_current_page_slug() {
		$current_page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$current_page = sanitize_text_field( $current_page );

		return $current_page;
	}

	/**
	 * Hide the animated status tag.
	 */
	public function hide_the_status_tag() {
		if ( 'wphb-minification' === $this->get_current_page_slug() ) {
			update_option( 'wphb_hide_ao_menu_status_animation', true );
		}
	}

	/**
	 * Network menu pages.
	 */
	public function add_network_menu_pages() {
		$this->hide_the_status_tag();

		$hb_title = defined( 'WPHB_WPORG' ) && WPHB_WPORG ? __( 'Hummingbird', 'wphb' ) : __( 'Hummingbird Pro', 'wphb' );

		$this->pages['wphb']               = new Pages\Dashboard( 'wphb', $hb_title, $hb_title, false, false );
		$this->pages['wphb-dashboard']     = new Pages\Dashboard( 'wphb', __( 'Dashboard', 'wphb' ), __( 'Dashboard', 'wphb' ), 'wphb' );
		$this->pages['wphb-performance']   = new Pages\Performance( 'wphb-performance', __( 'Performance Test', 'wphb' ), __( 'Performance Test', 'wphb' ), 'wphb' );
		$this->pages['wphb-caching']       = new Pages\Caching( 'wphb-caching', __( 'Caching', 'wphb' ), __( 'Caching', 'wphb' ), 'wphb' );
		$this->pages['wphb-gzip']          = new Pages\React\Gzip( 'wphb-gzip', __( 'Gzip Compression', 'wphb' ), __( 'Gzip Compression', 'wphb' ), 'wphb' );
		$this->pages['wphb-minification']  = new Pages\Minification( 'wphb-minification', __( 'Asset Optimization', 'wphb' ), __( 'Asset Optimization', 'wphb' ) . $this->get_status_tag_html(), 'wphb' );
		$this->pages['wphb-advanced']      = new Pages\Advanced( 'wphb-advanced', __( 'Advanced Tools', 'wphb' ), __( 'Advanced Tools', 'wphb' ), 'wphb' );
		$this->pages['wphb-uptime']        = new Pages\Uptime( 'wphb-uptime', __( 'Uptime', 'wphb' ), __( 'Uptime', 'wphb' ), 'wphb' );
		$this->pages['wphb-notifications'] = new Pages\Notifications( 'wphb-notifications', __( 'Notifications', 'wphb' ), __( 'Notifications', 'wphb' ), 'wphb' );
		$this->pages['wphb-settings']      = new Pages\Settings( 'wphb-settings', __( 'Settings', 'wphb' ), __( 'Settings', 'wphb' ), 'wphb' );

		if ( ! apply_filters( 'wpmudev_branding_hide_doc_link', false ) ) {
			$this->pages['wphb-tutorials'] = new Pages\React\Tutorials( 'wphb-tutorials', __( 'Tutorials', 'wphb' ), __( 'Tutorials', 'wphb' ), 'wphb' );
		}

		if ( ! Utils::is_member() ) {
			$this->pages['wphb-upgrade'] = new Pages\Upgrade( 'wphb-upgrade', __( 'Hummingbird Pro', 'wphb' ), __( 'Hummingbird Pro', 'wphb' ), 'wphb' );
		}

		if ( $this->wphb_should_add_service_submenu() ) {
			$this->pages['wphb-services'] = new Pages\Services( 'wphb-services', __( 'Expert Services', 'wphb' ), __( 'Expert Services', 'wphb' ) . $this->get_new_tag_html(), 'wphb' );
		}

		$this->pages['wphb-setup'] = new Pages\React\Setup( 'wphb-setup', __( 'Setup Wizard', 'wphb' ), null, 'wphb' );
	}

	/**
	 * Return an instance of a WP Hummingbird Admin Page
	 *
	 * @param string $page_slug  Page slug.
	 *
	 * @return bool|Page
	 */
	public function get_admin_page( $page_slug ) {
		if ( isset( $this->pages[ $page_slug ] ) ) {
			return $this->pages[ $page_slug ];
		}

		return false;
	}

	/**
	 * This will continue running the minification scan on every page update, even if the user leaves the asset
	 * optimization page.
	 * Uses 4 db queries.
	 */
	public function maybe_check_files() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$minify_module = Utils::get_module( 'minify' );
		// Only continue if we are checking files.
		if ( ! $minify_module->is_active() || ! $minify_module->scanner->is_scanning() ) {
			return;
		}

		if ( ! wp_script_is( 'wphb-admin' ) ) {
			Utils::enqueue_admin_scripts( WPHB_VERSION );
		}

		// If we are in minification page, we should redirect when checking files is finished.
		$screen = get_current_screen();
		$minify = isset( $this->pages['wphb-minification']->page_id ) ? $this->pages['wphb-minification']->page_id : '';

		// The minification screen will do it for us.
		if ( $screen->id === $minify ) {
			return;
		}

		?>
		<script>
			jQuery( document ).ready( function() {
				window.WPHB_Admin.getModule( 'minification' ).scanner.start();
				window.WPHB_Admin.getModule( 'minification' ).minificationStarted = true;
			});
		</script>
		<?php
	}

	/**
	 * Add more pages to builtin WPMU DEV branding.
	 *
	 * @since 1.9.3
	 *
	 * @param array $plugin_pages  Plugin pages.
	 *
	 * @return array
	 */
	public function builtin_wpmudev_branding( $plugin_pages ) {
		foreach ( $this->pages as $key => $value ) {
			$plugin_pages[ "hummingbird-pro_page_$key" ] = array(
				'wpmudev_whitelabel_sui_plugins_branding',
				'wpmudev_whitelabel_sui_plugins_footer',
				'wpmudev_whitelabel_sui_plugins_doc_links',
			);
		}

		return $plugin_pages;
	}

	/**
	 * Clear all cache?
	 */
	public function maybe_clear_all_cache() {
		$wphb_clear = filter_input( INPUT_GET, 'wphb-clear' );
		if ( ! $wphb_clear || ! current_user_can( Utils::get_admin_capability() ) ) {
			return;
		}

		check_admin_referer( 'wphb-clear-cache' );

		WP_Hummingbird::flush_cache();
		Utils::get_module( 'page_cache' )->toggle_service( false );

		Utils::get_module( 'cloudflare' )->toggle_apo( false );

		if ( 'all' === $wphb_clear ) {
			Settings::reset_to_defaults();

			// Remove configs.
			delete_site_option( 'wphb-preset_configs' );

			update_option( 'wphb_run_onboarding', true );
			update_option( 'wphb-minification-show-config_modal', true );
			update_option( 'wphb-minification-show-advanced_modal', true );
			delete_option( 'wphb-hide-tutorials' );

			// Clean all cron.
			wp_clear_scheduled_hook( 'wphb_performance_report' );
			wp_clear_scheduled_hook( 'wphb_uptime_report' );
			wp_clear_scheduled_hook( 'wphb_database_report' );
			wp_clear_scheduled_hook( 'wphb_minify_clear_files' );

			if ( is_multisite() ) {
				global $wpdb;
				$offset = 0;
				$limit  = 100;
				while ( $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs} LIMIT {$offset}, {$limit}", ARRAY_A ) ) { // Db call ok; no-cache ok.
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog['blog_id'] );

						Settings::reset_to_defaults();
						update_option( 'wphb_run_onboarding', true );
						update_option( 'wphb-minification-show-config_modal', true );
						update_option( 'wphb-minification-show-advanced_modal', true );

						// Clean all cron.
						wp_clear_scheduled_hook( 'wphb_minify_clear_files' );
					}
					restore_current_blog();
					$offset += $limit;
				}
			}
		}

		wp_safe_redirect( remove_query_arg( 'wphb-clear' ) );
		exit;
	}

	/**
	 * Remove submenu setup point.
	 *
	 * @since 3.3.1
	 *
	 * @param string $submenu_file The submenu file.
	 *
	 * @return string
	 */
	public function remove_submenu_item( $submenu_file ) {
		remove_submenu_page( 'wphb', 'wphb-setup' );
		return $submenu_file;
	}

	/**
	 * Apply inline styles to the "Upgrade to Pro" option in the left sidebar menu.
	 */
	public function wphb_style_upgrade_pro_upsell() {
		if ( Utils::is_member() ) {
			return;
		}

		echo '<style>
			#toplevel_page_wphb ul.wp-submenu li:last-child a[href^="https://wpmudev.com"] {
				background-color: #8d00b1 !important;
				color: #fff !important;
				font-weight: 400 !important;
			}
		</style>';

		echo '<script>
				jQuery(function() {
					jQuery(\'#toplevel_page_wphb ul.wp-submenu li:last-child a[href^="https://wpmudev.com"]\').attr("target", "_blank");
				});
			</script>';
	}

	/**
	 * Determine if the "Expert Services" submenu should be added to the Hummingbird menu.
	 *
	 * @return bool
	 */
	public function wphb_should_add_service_submenu() {
		return Utils::is_member() && ! Utils::is_whitelabel_enabled();
	}

	/**
	 * Refresh fastCGI status.
	 */
	public function wphb_refresh_fast_cgi_status() {
		$current_page = $this->get_current_page_slug();
		if ( 'wphb-caching' !== $current_page ) {
			return;
		}

		Utils::get_api()->hosting->has_fast_cgi_header( true );
	}

	/**
	 * Load deactivation survey modal.
	 */
	public function load_deactivation_survey_modal() {
		$deactivation_survey_template_file = WPHB_DIR_PATH . 'admin/modals/deactivation-survey-modal.php';
		if ( file_exists( $deactivation_survey_template_file ) ) {
			include $deactivation_survey_template_file;
		}
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook  Hook from where the call is made.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		// Styles.
		wp_enqueue_style( 'wphb-admin', WPHB_DIR_URL . 'admin/assets/css/wphb-app.min.css', array(), WPHB_VERSION );

		// Scripts.
		wp_enqueue_script(
			'wphb-wpmudev-sui',
			WPHB_DIR_URL . 'admin/assets/js/wphb-sui.min.js',
			array( 'jquery', 'clipboard' ),
			WPHB_VERSION,
			true
		);
	}
}
