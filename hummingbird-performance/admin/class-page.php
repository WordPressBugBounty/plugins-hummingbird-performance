<?php
/**
 * Abstract class for the admin pages.
 *
 * @package Hummingbird\Admin
 */

namespace Hummingbird\Admin;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Admin\Ajax\Safe_Mode;
use Hummingbird\Admin\Ajax\Notices as React_Notices;
use Hummingbird\Core\Hub_Connector;
use Hummingbird\Core\Modules\Performance;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Whitelabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page
 */
abstract class Page {

	/**
	 * Page slug.g
	 *
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Page ID.
	 *
	 * @var false|null|string
	 */
	public $page_id = null;

	/**
	 * Admin notices.
	 *
	 * @var Notices
	 */
	protected $admin_notices;

	/**
	 * Admin_Page constructor.
	 *
	 * @param string $slug        Module slug.
	 * @param string $page_title  Page title.
	 * @param string $menu_title  Menu title.
	 * @param bool   $parent      Parent or not.
	 * @param bool   $render      Render the page.
	 */
	public function __construct( $slug, $page_title, $menu_title, $parent = false, $render = true ) {
		$this->slug = $slug;

		$this->admin_notices = Notices::get_instance();

		if ( ! $parent ) {
			$this->page_id = add_menu_page(
				$page_title,
				$menu_title,
				Utils::get_admin_capability(),
				$slug,
				$render ? array( $this, 'render_ui_page' ) : null,
				$this->get_menu_icon()
			);
		} elseif ( 'wphb-upgrade' === $slug ) {
			$this->page_id = add_submenu_page(
				$parent,
				$page_title,
				sprintf(
					'%1$s<span class="wphb-admin-menu-upgrade-pro-tag">%2$s</span><span class="wphb-admin-menu-upgrade-icon" aria-hidden="true"></span>',
					esc_html__( 'Upgrade', 'wphb' ),
					esc_html__( 'Pro', 'wphb' )
				),
				Utils::get_admin_capability(),
				esc_url( Utils::get_link( 'plugin', 'hummingbird_submenu_upsell' ) ),
			);
		} else {
			$this->page_id = add_submenu_page(
				$parent,
				$page_title,
				$menu_title,
				Utils::get_admin_capability(),
				$slug,
				$render ? array( $this, 'render_ui_page' ) : null
			);
		}

		if ( $render ) {
			add_action( 'load-' . $this->page_id, array( $this, 'trigger_load_action' ) );
			add_filter( 'load-' . $this->page_id, array( $this, 'add_screen_hooks' ) );
		}
	}

	/**
	 * Return the admin menu slug
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Trigger an action before this screen is loaded
	 */
	public function trigger_load_action() {
		do_action( 'wphb_load_admin_page_' . $this->get_slug() );

		// Run setup wizard only on single sites or network admin.
		if ( 'wphb-setup' !== $this->get_slug() && get_option( 'wphb_run_onboarding' ) && ( ! is_multisite() || is_network_admin() ) ) {
			wp_safe_redirect( Utils::get_admin_menu_url( 'setup' ) );
			exit;
		}
	}

	/**
	 * Common hooks for all screens
	 */
	public function add_screen_hooks() {
		add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );
		add_action( 'network_admin_head', array( $this, 'hide_admin_notices' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_ui_page_scripts' ) );

		// Add the admin body classes.
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Return the classes to be added to the body in the admin.
	 *
	 * @param String $classes Classes to be added.
	 * @return String
	 */
	public function admin_body_class( $classes ) {
		if ( 'wphb-setup' === $this->get_slug() ) {
			$classes .= ' wphb-full-screen ';
		}

		$classes .= ' wpmudev-hb-ui-page ';
		if ( 'wphb-setup' === $this->get_slug() ) {
			$classes .= ' wpmudev-hb-setup-wizard-active ';
		}

		return $classes;
	}

	/**
	 * Enqueue an entry from the new UI architecture.
	 *
	 * The corresponding Webpack entry must be named `wphb-ui-{entry}` and the
	 * page template must provide the mount element expected by that entry.
	 *
	 * @param string $entry UI entry name.
	 * @return void
	 */
	protected function enqueue_ui( $entry ) {
		$handle = 'wphb-ui-' . sanitize_key( $entry );

		wp_enqueue_style(
			$handle,
			WPHB_DIR_URL . 'admin/assets/ui/css/' . $handle . '.min.css',
			array(),
			WPHB_VERSION
		);
		wp_enqueue_script(
			$handle,
			WPHB_DIR_URL . 'admin/assets/ui/js/' . $handle . '.min.js',
			array( 'wp-i18n' ),
			WPHB_VERSION,
			true
		);
		wp_set_script_translations( $handle, 'wphb' );
	}

	/**
	 * Enqueue the shared migration shell for every Hummingbird admin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_ui_page_scripts( $hook ) {
		if ( $this->page_id !== $hook ) {
			return;
		}

		$entry  = $this->get_ui_page_entry();
		$handle = 'wphb-ui-' . $entry;

		$dismissed_inline_warnings = get_option( 'wphb_react_dismissed_inline_warnings', array() );
		if ( ! is_array( $dismissed_inline_warnings ) ) {
			$dismissed_inline_warnings = array();
		}
		$dismissed_inline_warnings = array_values(
			array_filter(
				array_map( 'sanitize_key', $dismissed_inline_warnings )
			)
		);

		$safe_mode = SafeMode::instance();
		// has_changes_to_publish() is not memoized and reads two cache files -- call it once.
		$safe_mode_has_changes = $safe_mode->has_changes_to_publish();

		$view = sanitize_text_field( filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW ) );

		$this->enqueue_ui( $entry );
		$scan_started_at = Performance::is_doing_report();

		$page_data            = $this->get_ui_page_data();
		$page_data['notices'] = array_merge(
			$this->get_global_notices(),
			isset( $page_data['notices'] ) && is_array( $page_data['notices'] ) ? $page_data['notices'] : array()
		);

		wp_localize_script(
			$handle,
			'wphbPage',
			array_merge(
				array(
					'pluginName'              => Whitelabel::replace_branding_terms( __( 'Hummingbird', 'wphb' ) ),
					'pageSlug'                => $this->get_slug(),
					'pageTitle'               => get_admin_page_title(),
					'isPro'                   => Utils::is_member(),
					'hasAccessToHub'          => Utils::has_access_to_hub(),
					'isWpOrg'                 => defined( 'WPHB_WPORG' ) && WPHB_WPORG,
					'isMultisite'             => is_multisite(),
					'isNetworkAdmin'          => is_network_admin(),
					'canManageNetwork'        => is_multisite() && current_user_can( 'manage_network' ),
					'trackingEnabled'         => Utils::get_module( 'mixpanel_analytics' )->is_active(),
					'logoUrl'                 => WPHB_DIR_URL . 'admin/assets/image/hb-icon@2x.png',
					'wpmudevUrl'              => Utils::get_link( 'wpmudev', 'hummingbird_page_header' ),
					'supportUrl'              => Utils::get_link( 'support', 'hummingbird_page_header' ),
					'docsUrl'                 => Utils::get_documentation_url( $this->get_slug(), $view ),
					'roadmapUrl'              => Utils::get_link( 'roadmap', 'hummingbird_profile_avatar_roadmap' ),
					'discussionUrl'           => admin_url( 'options-discussion.php' ),
					'upgradeUrl'              => Utils::get_link( 'plugin', 'hummingbird_page_header' ),
					'dashboardUrl'            => is_network_admin()
						? network_admin_url( 'admin.php?page=wphb' )
						: admin_url( 'admin.php?page=wphb' ),
					'pluginUrl'               => Utils::get_link( 'plugin', 'hummingbird_page_brand' ),
					'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
					'fetchNonce'              => wp_create_nonce( 'wphb-fetch' ),
					'performanceScan'         => array( 'startedAt' => $scan_started_at ? $scan_started_at : null ),
					'aoScanNotice'            => $this->get_ao_scan_notice(),
					'rateNotice'              => $this->get_rate_notice(),
					'shareUsageDataNotice'    => $this->get_share_usage_data_notice(),
					'activityLogItems'        => Activity_Log::get_items_for_ui(),
					'locale'                  => get_user_locale(),
					'timeZone'                => Utils::get_timezone_string(),
					'dismissedInlineWarnings' => $dismissed_inline_warnings,
					'whiteLabel'              => Whitelabel::get_data(),
					'highContrastMode'        => (bool) Settings::get_setting( 'accessible_colors', 'settings' ),
					'profileData'             => Hub_Connector::get_profile_data_for_ui(),
					'hubConnector'            => Hub_Connector::data_frontend(),
					'safeMode'                => array(
						'active'          => $safe_mode->get_status(),
						'hasChanges'      => $safe_mode_has_changes,
						// README §7.2 pins the count at 0 while Safe Mode is off.
						'changeCount'     => $safe_mode->get_status() ? Safe_Mode::get_change_count() : 0,
						'previewUrl'      => $safe_mode->get_safe_mode_preview_url(),
						'introPending'    => SafeMode::is_intro_pending(),
						'published'       => isset( $_GET['wphb_safemode_published'] ), // Input var ok.
						'reviewRequested' => isset( $_GET['wphb_safe_mode_review'] ), // Input var ok.
						'canManage'       => current_user_can( Utils::get_admin_capability() ),
					),
					// Persist this notice dismissal because Critical CSS may redirect before the first save completes.
					'autoSaveIntroDismissed'  => React_Notices::is_dismissed( 'auto-save-intro' ),
					'dashRequiredDismissed'   => React_Notices::is_dismissed( 'dashboard-required' ),
					'welcomeModal'            => Feature_Modal::get_modal_data(),
				),
				$page_data
			)
		);
	}

	/**
	 * Asset Optimization scan completion message, for the toast the shared page
	 * layout raises on every Hummingbird page.
	 *
	 * @since 4.0.0
	 *
	 * @return string Empty when there is nothing to show.
	 */
	protected function get_ao_scan_notice() {
		$cap = is_multisite() ? 'manage_network_plugins' : 'update_plugins';
		if ( ! current_user_can( $cap ) ) {
			return '';
		}

		// Don't consume the message while a performance report owns the screen.
		if ( get_transient( 'wphb-doing-report' ) ) {
			return '';
		}

		return Utils::get_ao_background_processing_completion_message();
	}

	/**
	 * "Rate Hummingbird" notice, for the dedicated toast the shared page
	 * layout raises on every Hummingbird page (see the useRateNotice hook).
	 * Kept out of get_global_notices()'s generic `notices` payload because
	 * it has 3 actions instead of the usual single primary action, and the
	 * action labels/handling are static on the JS side.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	protected function get_rate_notice() {
		$notice = $this->admin_notices->get_free_rated_notice();
		if ( empty( $notice ) ) {
			return array();
		}

		return array(
			'id'          => $notice['id'],
			'title'       => $notice['title'],
			'description' => $notice['description'],
		);
	}

	/**
	 * Whether to show the "Share Usage Data?" toast, surfaced on every page
	 * since the performance scan runs in the background (see usePerformanceScan).
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	protected function get_share_usage_data_notice() {
		return (bool) $this->admin_notices->get_share_usage_data_notice();
	}

	/**
	 * Notices surfaced on every Hummingbird page, merged into the shared
	 * `wphbPage.notices` payload.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_global_notices() {
		$notices = array();

		$builders = array(
			'get_free_deactivated_notice',
			'get_upgrade_to_pro_notice',
			'get_plugin_compat_notice',
			'get_site_monitoring_notice',
			'get_cache_global_cleared_notice',
			'get_cache_cleaned_notice',
			'get_update_notice',
		);

		foreach ( $builders as $builder ) {
			$notice = $this->admin_notices->$builder();
			if ( empty( $notice ) ) {
				continue;
			}

			$id             = isset( $notice['id'] ) ? $notice['id'] : $builder;
			$notices[ $id ] = $notice;
		}

		return $notices;
	}

	/**
	 * Extra data a specific page controller wants localized alongside the
	 * shared `wphbPage` data above. Mirrors WP Defender's pattern of each
	 * module contributing its own `data_frontend()` slice, merged once at
	 * the page level, instead of every component fetching its own status
	 * over AJAX on mount.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		return array();
	}

	/**
	 * Get the new UI entry associated with the current admin page.
	 *
	 * @return string
	 */
	protected function get_ui_page_entry() {
		$entries = array(
			'wphb'              => 'dashboard',
			'wphb-minification' => 'asset-optimization',
			'wphb-cdn'          => 'cdn',
			'wphb-advanced'     => 'advanced-tools',
			'wphb-caching'      => 'caching',
			'wphb-settings'     => 'settings',
			'wphb-setup'        => 'setup-wizard',
		);

		return isset( $entries[ $this->get_slug() ] )
			? $entries[ $this->get_slug() ]
			: 'dashboard';
	}

	/**
	 * Render the shared migration shell with intentionally blank page content.
	 *
	 * @return void
	 */
	public function render_ui_page() {
		?>
		<div id="wpmudev-hb-ui-page"></div>
		<?php
	}

	/**
	 * Return this menu page URL
	 *
	 * @return string
	 */
	public function get_page_url() {
		if ( is_multisite() && is_network_admin() ) {
			global $_parent_pages;

			if ( isset( $_parent_pages[ $this->slug ] ) ) {
				$parent_slug = $_parent_pages[ $this->slug ];
				if ( $parent_slug && ! isset( $_parent_pages[ $parent_slug ] ) ) {
					$url = network_admin_url( add_query_arg( 'page', $this->slug, $parent_slug ) );
				} else {
					$url = network_admin_url( 'admin.php?page=' . $this->slug );
				}
			} else {
				$url = '';
			}

			return esc_url( $url );
		}

		return menu_page_url( $this->slug, false );
	}

	/**
	 * Hummingbird icon svg image.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_menu_icon() {
		ob_start();
		?>
		<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path fill-rule="evenodd" clip-rule="evenodd" d="M12.7326 3.2L13.9481 4.05L13.2596 10.2295L10.9901 13.2555L10.7606 13.5615L10.6841 13.944L10.2422 16.137L8.27018 15.287L10.2761 12.7795L10.5481 12.4395L10.6246 12.006L11.4348 7.23704L11.4408 7.23749L11.4451 7.17626L12.1206 3.2H12.7326ZM10.7096 1.5L10.0555 5.42467L0 4.67049L9.10861 11.1063L9.00966 11.7L5.60972 15.95L11.5596 18.5L12.4096 14.25L14.9596 10.85L15.6459 4.67319L20 4.05093V3.20062H15.8095L15.8095 3.2L13.3191 1.5H13.2596H10.7096ZM6.0009 6.82949L9.41994 9.23827L9.77423 7.1125L6.0009 6.82949Z" fill="#F0F6FC" />
		</svg>
		<?php
		$svg = ob_get_clean();

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Hide admin notices for the Hummingbird plugin.
	 *
	 * @since 4.0.0
	 */
	public function hide_admin_notices() {
		?>
		<style>
			body.toplevel_page_wphb .notice,
			body[class*="wphb"] .notice {
				display: none;
			}

			body.toplevel_page_wphb .notice.update-nag,
			body[class*="wphb"] .notice.update-nag {
				display: inherit;
			}
		</style>
		<?php
	}
}
