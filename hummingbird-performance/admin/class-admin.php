<?php
/**
 * Hummingbird admin class.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Admin;

use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Minify;
use Hummingbird\Core\Hub_Connector;

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
	 * Admin constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu_pages' ) );

		// Must run on the menu hooks: WordPress denies access to unregistered pages before admin_init.
		add_action( 'admin_menu', array( $this, 'maybe_redirect_legacy_page' ) );
		add_action( 'network_admin_menu', array( $this, 'maybe_redirect_legacy_page' ) );

		add_action( 'admin_head', array( $this, 'wphb_style_upgrade_pro_upsell' ) );

		add_filter( 'submenu_file', array( $this, 'remove_submenu_item' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( Hub_Connector::is_connection_flow() ) {
			return;
		}

		$this->admin_notices = Notices::get_instance();
		add_action( 'admin_init', array( $this, 'wphb_refresh_fast_cgi_status' ) );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			new AJAX();
			new Ajax\UI_Warnings();
			new Ajax\Safe_Mode();
			new Ajax\Gzip();
			new Ajax\Minify();
			new Ajax\Activity_Log();
			new Ajax\Caching\Browser();
			new Ajax\Caching\Integrations();
			new Ajax\Caching\Page_Caching();
			new Ajax\Caching\Others();
			new Ajax\Advanced\Tools();
			new Ajax\Setup();
			new Ajax\Cdn();
			new Ajax\Notices();
			new Ajax\Configs();
			new Ajax\Database_Cleanup();
			new Ajax\Alerts_Reports();
			new Ajax\Uptime();
			new Ajax\Other_Settings();
			new Ajax\Hub_Connector();
			new Ajax\Feature_Modal();
			new Ajax\Dashboard();
		}

		add_action( 'admin_init', array( 'Hummingbird\\Core\\Installer', 'maybe_upgrade' ) );
		if ( is_multisite() ) {
			add_action( 'admin_init', array( 'Hummingbird\\Core\\Installer', 'maybe_upgrade_blog' ) );
		}
		// After maybe_upgrade(): on a fresh network install it seeds the main site's wphb_run_onboarding.
		add_action( 'admin_init', array( 'Hummingbird\\Core\\Installer', 'maybe_redirect_after_activation' ), 11 );

		// Make sure plugin name is correct for adding plugin action links.
		$plugin_name = defined( 'WPHB_WPORG' ) && WPHB_WPORG ? 'hummingbird-performance' : 'wp-hummingbird';
		add_filter( 'network_admin_plugin_action_links_' . $plugin_name . '/wp-hummingbird.php', array( $this, 'add_plugin_action_links' ) );
		add_filter( 'plugin_action_links_' . $plugin_name . '/wp-hummingbird.php', array( $this, 'add_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_meta_links' ), 10, 2 );

		// Filter built-in wpmudev branding script.
		add_filter( 'wpmudev_whitelabel_plugin_pages', array( $this, 'builtin_wpmudev_branding' ) );

		// Deactivation survey.
		add_action( 'admin_footer-plugins.php', array( $this, 'load_deactivation_survey_modal' ) );

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
				$actions['wphb-plugins-upgrade'] = '<a href="' . Utils::get_link( 'plugin', 'hummingbird_pluginlist_upgrade' ) . '" aria-label="' . esc_attr( __( 'Upgrade to Hummingbird Pro', 'wphb' ) ) . '" target="_blank" style="color: #8D00B1;" onclick="window.wphbMixPanel.trackHBUpsell( \'pro_general\', \'plugins_list\', \'cta_clicked\', this.href, \'hb_pro_upsell\' );">' . esc_html__( 'Get Hummingbird Pro', 'wphb' ) . '</a>';
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
			$links[] = '<a href="https://wordpress.org/support/plugin/hummingbird-performance/reviews/#new-post" target="_blank" title="' . esc_attr__( 'Rate Hummingbird', 'wphb' ) . '" onclick="if(window.wphbMixPanel){window.wphbMixPanel.track(\'Rating Notice\', {Action:\'rate\',\'Notice Type\':\'wordpress_admin\',Location:\'WordPress admin\'});}" >' . esc_html__( 'Rate Hummingbird', 'wphb' ) . '</a>';
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

		$this->pages['wphb']           = new Pages\Dashboard( 'wphb', $hb_title, $hb_title, false, false );
		$this->pages['wphb-dashboard'] = new Pages\Dashboard( 'wphb', __( 'Dashboard', 'wphb' ), __( 'Dashboard', 'wphb' ), 'wphb' );

		$minify = Settings::get_setting( 'enabled', 'minify' );

		if ( ! is_multisite() || ( ( 'super-admins' === $minify && is_super_admin() ) || ( true === $minify ) ) ) {
			$this->pages['wphb-minification'] = new Pages\Minification( 'wphb-minification', __( 'Asset Optimization', 'wphb' ), __( 'Asset Optimization', 'wphb' ), 'wphb' );
		} elseif ( isset( $current_page ) && 'wphb-minification' === $current_page ) {
			// Asset optimization is off, and is a network, let's redirect to network admin.
			$url = network_admin_url( 'admin.php?page=wphb' );
			wp_safe_redirect( $url );
			exit;
		}

		$this->pages['wphb-caching']  = new Pages\Caching( 'wphb-caching', __( 'Cache', 'wphb' ), __( 'Cache', 'wphb' ), 'wphb' );
		$this->pages['wphb-cdn']      = new Pages\Cdn( 'wphb-cdn', __( 'CDN', 'wphb' ), __( 'CDN', 'wphb' ), 'wphb' );
        $this->pages['wphb-advanced'] = new Pages\Advanced( 'wphb-advanced', __( 'Advanced', 'wphb' ), __( 'Advanced', 'wphb' ), 'wphb' );

		$this->pages['wphb-settings'] = new Pages\Settings( 'wphb-settings', __( 'Settings', 'wphb' ), __( 'Settings', 'wphb' ), 'wphb' );

		if ( ! Utils::is_member() ) {
			$this->pages['wphb-upgrade'] = new Pages\Upgrade( 'wphb-upgrade', __( 'Hummingbird Pro', 'wphb' ), __( 'Hummingbird Pro', 'wphb' ), 'wphb' );
		}

		$this->pages['wphb-setup'] = new Pages\React\Setup( 'wphb-setup', __( 'Setup Wizard', 'wphb' ), null, 'wphb' );
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
	 * Network menu pages.
	 */
	public function add_network_menu_pages() {
		$hb_title = defined( 'WPHB_WPORG' ) && WPHB_WPORG ? __( 'Hummingbird', 'wphb' ) : __( 'Hummingbird Pro', 'wphb' );

		$this->pages['wphb']           = new Pages\Dashboard( 'wphb', $hb_title, $hb_title, false, false );
		$this->pages['wphb-dashboard'] = new Pages\Dashboard( 'wphb', __( 'Dashboard', 'wphb' ), __( 'Dashboard', 'wphb' ), 'wphb' );
		$this->pages['wphb-caching']   = new Pages\Caching( 'wphb-caching', __( 'Cache', 'wphb' ), __( 'Cache', 'wphb' ), 'wphb' );
		$this->pages['wphb-cdn']       = new Pages\Cdn( 'wphb-cdn', __( 'CDN', 'wphb' ), __( 'CDN', 'wphb' ), 'wphb' );
		$this->pages['wphb-advanced']  = new Pages\Advanced( 'wphb-advanced', __( 'Advanced', 'wphb' ), __( 'Advanced', 'wphb' ), 'wphb' );
		$this->pages['wphb-settings']  = new Pages\Settings( 'wphb-settings', __( 'Settings', 'wphb' ), __( 'Settings', 'wphb' ), 'wphb' );

		if ( ! Utils::is_member() ) {
			$this->pages['wphb-upgrade'] = new Pages\Upgrade( 'wphb-upgrade', __( 'Hummingbird Pro', 'wphb' ), __( 'Hummingbird Pro', 'wphb' ), 'wphb' );
		}

		$this->pages['wphb-setup'] = new Pages\React\Setup( 'wphb-setup', __( 'Setup Wizard', 'wphb' ), null, 'wphb' );
	}

	/**
	 * Redirect admin pages removed in 4.0.0 to their new location.
	 *
	 * Old links (Hub, emails, bookmarks) would otherwise show the
	 * "Sorry, you are not allowed to access this page." error.
	 *
	 * @since 4.0.0
	 */
	public function maybe_redirect_legacy_page() {
		$legacy_pages = array(
			'wphb-performance' => 'admin.php?page=wphb',
		);

		$current_page = $this->get_current_page_slug();
		if ( ! isset( $legacy_pages[ $current_page ] ) ) {
			return;
		}

		$path = $legacy_pages[ $current_page ];
		$url  = is_network_admin() ? network_admin_url( $path ) : admin_url( $path );

		wp_safe_redirect( $url );
		exit;
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

		?>
		<style>
			#adminmenu #toplevel_page_wphb .wp-submenu a[href*="hummingbird_submenu_upsell"] {
				display: flex;
				align-items: center;
				box-sizing: border-box;
				width: 100%;
				height: 32px;
				margin: 0;
				padding: 7px 12px;
				background: var(--wpmudev-accent-pro, #571EE7);
				color: #fff;
				font-size: 14px;
				font-weight: 400;
				line-height: 1.3;
			}
			#toplevel_page_wphb .wphb-admin-menu-upgrade-pro-tag {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				box-sizing: border-box;
				width: 30px;
				min-width: 30px;
				max-width: 30px;
				height: 18px;
				max-height: 18px;
				margin-left: 5px;
				padding: 0 5px;
				overflow: hidden;
				border: 1px solid #fff;
				border-radius: 1020px;
				color: #fff;
				font-family: Roboto, Arial, sans-serif;
				font-size: 12px;
				font-weight: 400;
				line-height: 20px;
				letter-spacing: 0.1px;
				white-space: nowrap;
			}
			#toplevel_page_wphb .wphb-admin-menu-upgrade-icon {
				display: inline-flex;
				flex-shrink: 0;
				width: 16px;
				height: 16px;
				margin-left: auto;
			}
			#toplevel_page_wphb .wphb-admin-menu-upgrade-icon::after {
				content: '';
				display: block;
				width: 16px;
				height: 16px;
				background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M3.72844 12.2749C3.59094 12.1416 3.51594 11.9833 3.50344 11.7999C3.4951 11.6166 3.5701 11.4437 3.72844 11.2812L9.36594 5.63742L10.6659 4.44367C10.8159 4.302 10.9743 4.23533 11.1409 4.24367C11.3076 4.252 11.4514 4.31658 11.5722 4.43742C11.693 4.55825 11.7534 4.702 11.7534 4.86867C11.7576 5.03533 11.6909 5.1895 11.5534 5.33117L10.3534 6.63117L4.71594 12.2624C4.56177 12.4166 4.39302 12.4916 4.20969 12.4874C4.03052 12.4874 3.8701 12.4166 3.72844 12.2749ZM11.1222 7.97492L11.2659 4.75617L7.93469 4.87492H5.80969C5.61802 4.87492 5.45135 4.81033 5.30969 4.68117C5.16802 4.54783 5.09719 4.38742 5.09719 4.19992C5.09719 4.01242 5.16385 3.852 5.29719 3.71867C5.43469 3.58117 5.60969 3.51242 5.82219 3.51242H11.7597C11.9847 3.51242 12.1639 3.58117 12.2972 3.71867C12.4305 3.85617 12.4972 4.03325 12.4972 4.24992V10.1812C12.4972 10.3853 12.4284 10.5583 12.2909 10.6999C12.1534 10.8374 11.9909 10.9062 11.8034 10.9062C11.6118 10.9062 11.4493 10.8374 11.3159 10.6999C11.1868 10.5583 11.1222 10.3895 11.1222 10.1937V7.97492Z' fill='white'/%3E%3C/svg%3E");
				background-size: 16px 16px;
				background-repeat: no-repeat;
			}
		</style>
		<?php

		echo '<script>
				jQuery(function() {
					jQuery(\'#toplevel_page_wphb .wp-submenu a[href*="hummingbird_submenu_upsell"]\').attr("target", "_blank");
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
		wp_enqueue_style( 'wphb-admin', WPHB_DIR_URL . 'admin/assets/ui/css/wphb-shared-ui.min.css', array(), WPHB_VERSION );

		// Scripts.
		wp_enqueue_script(
			'wphb-shared-ui',
			WPHB_DIR_URL . 'admin/assets/ui/js/wphb-shared-ui.min.js',
			array( 'jquery' ),
			WPHB_VERSION,
			true
		);
	}
}
