<?php
/**
 * Mixpanel Analytics module.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.9.4
 */

namespace Hummingbird\Core\Modules;

use Hummingbird\Core\Module;
use Hummingbird\Core\Traits\Module as ModuleContract;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Module_Server;
use WPMUDEV_Analytics_V2;
use WPMUDEV_Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixpanel_Analytics
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
	 * WPMUDEV Analytics class.
	 *
	 * @var WPMUDEV_Analytics
	 */
	private $analytics;

	/**
	 * Initialize module.
	 *
	 * @since 3.9.4
	 */
	public function init() {
		add_filter( 'wp_hummingbird_is_active_module_mixpanel_analytics', array( $this, 'module_status' ) );
		add_action( 'wphb_mixpanel_usage_tracking_value_update', array( $this, 'wphb_track_opt_toggle' ), 10, 2 );
		add_action( 'wp_ajax_wphb_track_deactivation', array( $this, 'wphb_track_deactivation' ) );
	}

	/**
	 * Execute module actions.
	 *
	 * @since 3.9.4
	 */
	public function run() {
		add_action( 'wp_ajax_wphb_analytics_track_event', array( $this, 'wphb_handle_track_request_for_mixpanel' ) );
	}

	/**
	 * Track Mixpanel event when user toggles the tracking option.
	 *
	 * @param bool $is_mixpanel_value_updated Mixpanel value updated.
	 * @param bool $opted_value               Opted value.
	 */
	public function wphb_track_opt_toggle( $is_mixpanel_value_updated, $opted_value ) {
		if ( $is_mixpanel_value_updated ) {
			$this->track( $opted_value ? 'Opt In' : 'Opt Out' );
		}
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
	 * Track Mixpanel event.
	 *
	 * @param string $event      Mixpanel event name.
	 * @param array  $properties Mixpanel event properties.
	 *
	 * @return array
	 */
	private function track( $event, $properties = array() ) {
		return $this->get_analytics()->track( $event, $properties );
	}

	/**
	 * Returns WPMUDEV Analytics instance.
	 *
	 * @return WPMUDEV_Analytics_V2
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
	 * @return WPMUDEV_Analytics_V2
	 */
	private function initialize_analytics_tracking() {
		if ( ! class_exists( 'WPMUDEV_Analytics_V2' ) ) {
			require_once WPHB_DIR_PATH . 'core/externals/wpmudev-analytics/autoload.php';
		}

		$mixpanel = new WPMUDEV_Analytics_V2( 'hummingbird', 'Hummingbird', 55, $this->get_token() );
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
	 * Handle track request for Mixpanel.
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
	 * Maybe track additional event.
	 *
	 * @return void
	 */
	private function maybe_track_additional_event() {
		$event_name = isset( $_POST['additionalEvent'] ) ? $this->wphb_sanitize_data( wp_unslash( $_POST['additionalEvent'] ) ) : '';
		$properties = isset( $_POST['additionalProperties'] ) ? $this->wphb_sanitize_data( wp_unslash( $_POST['additionalProperties'] ) ) : array();

		if ( ! empty( $event_name ) && ! empty( $properties ) ) {
			$this->track(
				$event_name,
				$properties,
			);
		}
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
	 * Track event for setup performance test.
	 *
	 * @return void
	 */
	public function track_event_for_setup_performance_test() {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->track(
			'plugin_scan_started',
			array(
				'score_mobile_previous'  => '-',
				'score_desktop_previous' => '-',
				'Location'               => 'setup_wizard',
			)
		);
	}

	/**
	 * Track HB deactivation.
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

		$this->track( $event_name, $properties );

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
		$plugin_data = get_plugin_data( $plugin_file );

		return ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : ''; // Return plugin name if available, otherwise empty string.
	}
}
