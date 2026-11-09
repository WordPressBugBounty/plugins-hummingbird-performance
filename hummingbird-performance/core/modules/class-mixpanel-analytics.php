<?php
/**
 * Mixpanel Analytics module.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.9.4
 */

namespace Hummingbird\Core\Modules;

use Hummingbird\Core\Module;
use Hummingbird\Core\Module_Server;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Traits\Module as ModuleContract;
use Hummingbird\Core\Utils;
use InvalidArgumentException;
use WPMUDEV_Analytics_V4;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixpanel_Analytics
 *
 * Checks the usage-tracking opt-in, sends events, and knows which admin page a request came from; event shapes live in the `Mixpanel\*_Events` builders.
 */
class Mixpanel_Analytics extends Module {
	use ModuleContract;

	/**
	 * Key to store Mixpanel token.
	 *
	 * @var string
	 */
	const PROJECT_TOKEN = '5d545622e3a040aca63f2089b0e6cae7';

	/**
	 * Event builder class per area; each one owns that area's event names and properties.
	 *
	 * @since 4.0.1
	 *
	 * @var array<string, class-string<Mixpanel\Events>>
	 */
	const EVENT_BUILDERS = array(
		'general'            => Mixpanel\General_Events::class,
		'asset_optimization' => Mixpanel\Asset_Optimization_Events::class,
		'page_cache'         => Mixpanel\Page_Caching_Events::class,
		'performance'        => Mixpanel\Performance_Events::class,
	);

	/**
	 * Admin page slug => `Location` value sent with setting changes.
	 *
	 * @since 4.0.1
	 *
	 * @var array<string, string>
	 */
	const LOCATIONS = array(
		'wphb'              => 'dash_widget',
		'wphb-minification' => 'ao_page',
		'wphb-caching'      => 'pagecaching_page',
		'wphb-setup'        => 'setup_wizard',
	);

	/**
	 * Admin page slug (or `slug:tab`) => page label used as the Safe Mode event's `Location`.
	 *
	 * @since 4.0.1
	 *
	 * @var array<string, string>
	 */
	const PAGE_LABELS = array(
		'wphb'                           => 'Dashboard',
		'wphb-minification'              => 'Asset Optimization',
		'wphb-caching'                   => 'Page Caching',
		'wphb-caching:others'            => 'Other Caching',
		'wphb-cdn'                       => 'CDN',
		'wphb-advanced'                  => 'Uptime',
		'wphb-advanced:database_cleanup' => 'Database Cleanup',
		'wphb-advanced:tools'            => 'Tools',
		'wphb-advanced:alerts_reports'   => 'Reports & Alerts',
		'wphb-settings:other'            => 'Other Settings',
		'wphb-settings:configs'          => 'Configs',
		'wphb-settings:permissions'      => 'Permissions',
	);

	/**
	 * WPMUDEV Analytics class.
	 *
	 * @var WPMUDEV_Analytics_V4
	 */
	private $analytics;

	/**
	 * Instantiated event builders, by area.
	 *
	 * @since 4.0.1
	 *
	 * @var array<string, Mixpanel\Events>
	 */
	private $events = array();

	/**
	 * Initialize module.
	 *
	 * @since 3.9.4
	 */
	public function init() {
		add_filter( 'wp_hummingbird_is_active_module_mixpanel_analytics', array( $this, 'module_status' ) );
		add_action( 'wphb_mixpanel_usage_tracking_value_update', array( $this, 'wphb_track_opt_toggle' ), 10, 3 );
		add_action( 'wp_ajax_wphb_track_deactivation', array( $this, 'wphb_track_deactivation' ) );
		add_action( 'admin_init', array( $this, 'wphb_track_plugin_activation' ) );
		add_action( 'wp_ajax_wphb_analytics_track_event', array( $this, 'wphb_handle_track_request_for_mixpanel' ) );
		add_action( 'wp_ajax_wphb_react_ui_error', array( $this, 'wphb_track_react_ui_error' ) );
	}

	/**
	 * Execute module actions.
	 *
	 * @since 3.9.4
	 */
	public function run() {
		if ( defined( 'WPHB_BASENAME' ) ) {
			$plugin_basename = WPHB_BASENAME;
			add_action( "deactivate_$plugin_basename", array( $this, 'track_plugin_deactivation' ) );
		}
	}

	/**
	 * Track the usage-tracking toggle; sent even when opted out, so the Opt Out event itself still goes out.
	 *
	 * @param bool   $is_mixpanel_value_updated Mixpanel value updated.
	 * @param bool   $opted_value Opted value.
	 * @param string $location Location of the toggle action.
	 */
	public function wphb_track_opt_toggle( $is_mixpanel_value_updated, $opted_value, $location = 'settings' ) {
		if ( $is_mixpanel_value_updated ) {
			$this->send( $opted_value ? 'Opt In' : 'Opt Out', $this->get_opt_properties( $location ) );
		}
	}

	/**
	 * Properties of the Opt In and Opt Out events: where it happened and which plugins were active.
	 *
	 * @since 4.0.1
	 *
	 * @param string $location Location of the toggle action.
	 *
	 * @return array
	 */
	private function get_opt_properties( $location ) {
		return array(
			'Location'       => $location,
			'active_plugins' => $this->get_active_plugins(),
		);
	}

	/**
	 * Get module status.
	 *
	 * @return bool
	 */
	public function module_status() {
		if ( ! Settings::get_setting( 'tracking', 'settings' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Event builder for an area.
	 *
	 * @since 4.0.1
	 *
	 * @param string $area One of the EVENT_BUILDERS keys.
	 *
	 * @return Mixpanel\Events
	 *
	 * @throws InvalidArgumentException When the area is not registered.
	 */
	public function events( $area ) {
		if ( ! isset( self::EVENT_BUILDERS[ $area ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown Mixpanel event area: %s', esc_html( $area ) ) );
		}

		if ( ! isset( $this->events[ $area ] ) ) {
			$class                 = self::EVENT_BUILDERS[ $area ];
			$this->events[ $area ] = new $class( $this );
		}

		return $this->events[ $area ];
	}

	/**
	 * Record an event, subject to the usage-tracking opt-in.
	 *
	 * @since 4.0.1 Public and gated; 3.x had every caller check the opt-in itself.
	 *
	 * @param string $event      Mixpanel event name.
	 * @param array  $properties Mixpanel event properties.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track( $event, $properties = array() ) {
		if ( ! $this->is_active() ) {
			return false;
		}

		return $this->send( $event, $properties );
	}

	/**
	 * Send an event without checking the opt-in; only for Opt Out, the deactivation survey and a submitted Hub disconnect.
	 *
	 * @since 4.0.1
	 *
	 * @param string $event      Mixpanel event name.
	 * @param array  $properties Mixpanel event properties.
	 *
	 * @return bool Whether the event was sent. Analytics never surfaces errors.
	 */
	public function send( $event, $properties = array() ) {
		try {
			$this->get_analytics()->track( $event, $properties );

			return true;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Hummingbird Mixpanel: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return false;
		}
	}

	/**
	 * Track a feature being switched on or off; does nothing when the state did not change.
	 *
	 * @since 4.0.1
	 *
	 * @param string $feature     Feature name as Mixpanel knows it.
	 * @param bool   $was_enabled State before the change.
	 * @param bool   $is_enabled  State after the change.
	 *
	 * @return bool Whether an event was sent.
	 */
	public function track_feature_toggle( $feature, $was_enabled, $is_enabled ) {
		if ( (bool) $was_enabled === (bool) $is_enabled ) {
			return false;
		}

		return $this->track(
			$is_enabled ? 'plugin_feature_activate' : 'plugin_feature_deactivate',
			array( 'feature' => $feature )
		);
	}

	/**
	 * Where the current request was made from, as the `Location` property.
	 *
	 * @since 4.0.1
	 *
	 * @return string A LOCATIONS value, or 'na' outside the plugin pages (cron, CLI, other screens).
	 */
	public function get_location() {
		$page = $this->get_request_page();

		return isset( self::LOCATIONS[ $page['slug'] ] ) ? self::LOCATIONS[ $page['slug'] ] : 'na';
	}

	/**
	 * Label of the plugin page (and tab) the current request was made from.
	 *
	 * @since 4.0.1
	 *
	 * @return string A PAGE_LABELS value, or 'na' outside the plugin pages.
	 */
	public function get_page_label() {
		$page = $this->get_request_page();

		if ( '' === $page['view'] ) {
			$page['view'] = $this->get_default_view( $page['slug'] );
		}

		foreach ( array( $page['slug'] . ':' . $page['view'], $page['slug'] ) as $key ) {
			if ( isset( self::PAGE_LABELS[ $key ] ) ) {
				return self::PAGE_LABELS[ $key ];
			}
		}

		return 'na';
	}

	/**
	 * Tab a page opens on when its URL names none: the first tab the React sidebar shows for that site.
	 *
	 * @since 4.0.1
	 *
	 * @param string $slug Page slug.
	 *
	 * @return string Tab id, or '' for pages without tabs.
	 */
	private function get_default_view( $slug ) {
		$is_subsite = is_multisite() && ! Utils::is_referrer_network_admin();

		if ( 'wphb-settings' === $slug ) {
			// Network admin opens on Permissions; a subsite has neither Permissions nor Configs.
			if ( is_multisite() && ! $is_subsite ) {
				return 'permissions';
			}

			return $is_subsite ? 'other' : 'configs';
		}

		if ( 'wphb-advanced' === $slug ) {
			// Subsites have no Uptime tab.
			return $is_subsite ? 'database_cleanup' : 'uptime';
		}

		return '';
	}

	/**
	 * Page slug and tab the request came from, read from the referrer the same way Utils::is_referrer_network_admin() does.
	 *
	 * @since 4.0.1
	 *
	 * @return array{slug: string, view: string} Empty strings when unknown.
	 */
	private function get_request_page() {
		$page    = array(
			'slug' => '',
			'view' => '',
		);
		$referer = wp_get_raw_referer();
		$query   = $referer ? wp_parse_url( $referer, PHP_URL_QUERY ) : '';

		if ( ! $query ) {
			return $page;
		}

		wp_parse_str( $query, $args );

		$page['slug'] = isset( $args['page'] ) && is_string( $args['page'] ) ? sanitize_key( $args['page'] ) : '';
		$page['view'] = isset( $args['view'] ) && is_string( $args['view'] ) ? sanitize_key( $args['view'] ) : '';

		return $page;
	}

	/**
	 * Returns WPMUDEV Analytics instance.
	 *
	 * @return WPMUDEV_Analytics_V4
	 */
	private function get_analytics() {
		if ( is_null( $this->analytics ) ) {
			$this->analytics = $this->initialize_analytics_tracking();
		}

		return $this->analytics;
	}

	/**
	 * Initialize WPMUDEV Analytics tracking.
	 *
	 * @return WPMUDEV_Analytics_V4
	 */
	private function initialize_analytics_tracking() {
		if ( ! class_exists( 'WPMUDEV_Analytics_V4' ) ) {
			require_once WPHB_DIR_PATH . 'core/externals/wpmudev-analytics/autoload.php';
		}

		$mixpanel = new WPMUDEV_Analytics_V4( 'hummingbird', 'Hummingbird', 55, $this->get_token() );
		$mixpanel->identify( $this->get_unique_id() );
		$mixpanel->registerAll( $this->get_super_properties() );

		return $mixpanel;
	}

	/**
	 * Get mixpanel project token.
	 *
	 * @return string
	 */
	public function get_token() {
		if ( empty( $this->get_unique_id() ) ) {
			return '';
		}

		return self::PROJECT_TOKEN;
	}

	/**
	 * Get unique ID for mixpanel.
	 *
	 * @return array
	 */
	public function get_unique_id() {
		$site_url         = home_url();
		$has_valid_domain = $this->has_valid_domain( $site_url );
		if ( ! $has_valid_domain ) {
			$site_url         = site_url();
			$has_valid_domain = $this->has_valid_domain( $site_url );
		}

		return $has_valid_domain ? $this->normalize_url( $site_url ) : '';
	}

	/**
	 * Check if URL has valid domain.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function has_valid_domain( $url ) {
		$pattern = '/^(https?:\/\/)?([a-z0-9-]+\.)*[a-z0-9-]+(\.[a-z]{2,})/i';

		return preg_match( $pattern, $url );
	}

	/**
	 * Normalize URL.
	 *
	 * @param string $url URL.
	 *
	 * @return array
	 */
	private function normalize_url( $url ) {
		$url = str_replace( array( 'http://', 'https://', 'www.' ), '', $url );

		return untrailingslashit( $url );
	}

	/**
	 * Get super properties.
	 *
	 * @return array
	 */
	public function get_super_properties() {
		global $wpdb, $wp_version;

		$super_properties = array(
			'plugin'            => 'Hummingbird',
			'plugin_type'       => Utils::is_member() ? 'pro' : 'free',
			'plugin_version'    => WPHB_VERSION,
			'wp_version'        => $wp_version,
			'wp_type'           => is_multisite() ? 'multisite' : 'single',
			'locale'            => get_locale(),
			'active_theme'      => wp_get_theme()->get( 'Name' ),
			'php_version'       => PHP_VERSION,
			'mysql_version'     => $wpdb->db_version(),
			'server_type'       => Module_Server::get_server_type(),
			'whitelabel_status' => Utils::is_whitelabel_enabled() ? 'enabled' : 'disabled',
			'hosting_status'    => Utils::is_site_hosted_on_wpmudev() ? 'hosted' : 'not-hosted',
			'nulled_status'     => $this->wphb_get_nulled_status(),
		);

		return array_merge( $super_properties, $this->get_date_time_properties() );
	}

	/**
	 * Return date time properties.
	 *
	 * @return array
	 */
	private function get_date_time_properties() {
		$properties  = array();
		$timestamps  = get_site_option( 'wphb_plugin_timestamps', array() );
		$time_events = array(
			'plugin_installed' => 'Installation Date',
			'plugin_activated' => 'Activation Date',
			'plugin_upgraded'  => 'Last Updated',
		);

		foreach ( $time_events as $event_key => $event_name ) {
			if ( ! empty( $timestamps[ $event_key ] ) ) {
				$properties[ $event_name ] = date( 'c', $timestamps[ $event_key ] );
			}
		}

		return $properties;
	}

	/**
	 * Get nulled status.
	 *
	 * @return string
	 */
	private function wphb_get_nulled_status() {
		$plugin_type = 'free';

		if ( Utils::is_member() ) {
			$user_can_install_hb = class_exists( 'WPMUDEV_Dashboard' ) && method_exists( \WPMUDEV_Dashboard::$upgrader, 'user_can_install' ) ? \WPMUDEV_Dashboard::$upgrader->user_can_install( 1081721, true ) : false;
			$plugin_type         = ! $user_can_install_hb ? 'nulled' : 'pro';
		}

		return $plugin_type;
	}

	/**
	 * Legacy tracking endpoint, still used by the Gutenberg block; the React UI uses `wphb_react_analytics_track`.
	 *
	 * @return void
	 */
	public function wphb_handle_track_request_for_mixpanel() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) { // Input var okay.
			die();
		}

		$event_name = $this->get_event_name();
		$this->track(
			$event_name,
			$this->get_event_properties( $event_name )
		);

		wp_send_json_success();
	}

	/**
	 * Track an uncaught React UI error.
	 *
	 * @return void
	 */
	public function wphb_track_react_ui_error() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wphb' ) ), 403 );
		}

		$code            = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : 'UnknownError';
		$message         = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$stack           = isset( $_POST['stack'] ) ? sanitize_textarea_field( wp_unslash( $_POST['stack'] ) ) : '';
		$component_stack = isset( $_POST['component_stack'] ) ? sanitize_textarea_field( wp_unslash( $_POST['component_stack'] ) ) : '';
		$page            = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '';

		$this->track(
			'hb_error_encountered',
			array(
				'Error Type'      => 'react_ui',
				'Error Code'      => $code,
				'Error Message'   => $message,
				'Stack Trace'     => $stack,
				'Component Stack' => $component_stack,
				'Page'            => $page,
			)
		);

		wp_send_json_success();
	}

	/**
	 * Get event name.
	 *
	 * @return string
	 */
	private function get_event_name() {
		$event_name = isset( $_POST['event'] ) ? $this->wphb_sanitize_data( wp_unslash( $_POST['event'] ) ) : '';

		switch ( $event_name ) {
			case 'activate':
				$event_name = 'plugin_feature_activate';
				break;
			case 'deactivate':
				$event_name = 'plugin_feature_deactivate';
				break;
		}

		return $event_name;
	}

	/**
	 * Get event properties.
	 *
	 * @param string $event_name Event name.
	 *
	 * @return string
	 */
	private function get_event_properties( $event_name ) {
		$properties = ! empty( $_POST['properties'] ) ? $this->wphb_sanitize_data( wp_unslash( $_POST['properties'] ) ) : array();

		return $properties;
	}

	/**
	 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
	 *
	 * @param string|array $data Data to sanitize.
	 *
	 * @return string|array
	 */
	public function wphb_sanitize_data( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'wphb_sanitize_data' ), $data );
		} else {
			return is_scalar( $data ) ? sanitize_text_field( $data ) : $data;
		}
	}

	/**
	 * Track the deactivation survey; sent even when opted out, as in 3.x.
	 */
	public function wphb_track_deactivation() {
		check_ajax_referer( 'wphb-fetch', 'nonce' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) { // Input var okay.
			die();
		}

		$event_name = $this->get_event_name();
		$properties = array_merge(
			$this->get_event_properties( $event_name ),
			array(
				'active_features' => Utils::get_active_features(),
				'active_plugins'  => $this->get_active_plugins(),
			)
		);

		$this->send( $event_name, $properties );

		wp_send_json_success();
	}

	/**
	 * Get lists of active features.
	 *
	 * @return array
	 */
	private function get_active_plugins() {
		// Retrieve unique list of active plugin files (both network and single site).
		$active_plugin_files = is_multisite() ? wp_get_active_network_plugins() : array();
		$active_plugin_files = array_merge( $active_plugin_files, wp_get_active_and_valid_plugins() );

		// Map the plugin files to their names and filter out empty names.
		$active_plugins = array_filter( array_map( array( $this, 'get_plugin_name' ), $active_plugin_files ) );

		return $active_plugins;
	}

	/**
	 * Get plugin name.
	 *
	 * @param string $plugin_file Plugin file.
	 *
	 * @return string
	 */
	private function get_plugin_name( $plugin_file ) {
		// The deactivation hook can run outside wp-admin (Hub, WP-CLI), where this function is not loaded.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( $plugin_file );

		return ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : ''; // Return plugin name if available, otherwise empty string.
	}

	/**
	 * Track plugin activation event.
	 *
	 * @return void
	 */
	public function wphb_track_plugin_activation() {
		if ( get_site_option( 'wphb-track-plugin-activation' ) ) {
			delete_site_option( 'wphb-track-plugin-activation' );

			$tracking_value = Settings::get_setting( 'tracking', 'settings' );
			if ( $tracking_value ) {
				$this->wphb_track_opt_toggle( true, true, 'reactivate' );
			}
		}
	}

	/**
	 * Track deactivation location.
	 */
	public function track_plugin_deactivation() {
		$this->send( 'Opt Out', $this->get_opt_properties( $this->get_deactivation_location() ) );
	}

	/**
	 * Get deactivation location.
	 *
	 * @return string
	 */
	private function get_deactivation_location() {
		$is_hub_request = ! empty( $_REQUEST['wpmudev-hub'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
		if ( $is_hub_request ) {
			return 'deactivate_hub';
		}

		$is_dashboard_request = wp_doing_ajax() && ! empty( $_REQUEST['action'] ) && 'wdp-project-deactivate' === wp_unslash( $_REQUEST['action'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		return $is_dashboard_request ? 'deactivate_dashboard' : 'deactivate_pluginlist';
	}
}
