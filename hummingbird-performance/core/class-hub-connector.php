<?php
/**
 * Hub_Connector class.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Core;

use Hummingbird\Core\Api\Request\WPMUDEV;
use Hummingbird\Core\Utils;
use WPMUDEV_Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hub_Connector
 */
class Hub_Connector {

	/**
	 * The identifier for the Hummingbird plugin in the Hub.
	 *
	 * @const string
	 */
	public const PLUGIN_IDENTIFIER = 'hummingbird';

	/**
	 * The action name used for the Hub connection.
	 *
	 * @const string
	 */
	public const CONNECTION_ACTION = 'hub_connection';

	/**
	 * Temporary state used while the site completes its first Hub sync.
	 *
	 * @const string
	 */
	public const SYNCING_TRANSIENT_KEY = 'wphb_hc_site_syncing';

	/**
	 * Query arg naming the Hummingbird entry point a Hub connection was started from.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	public const CONNECTION_SOURCE_PARAM = 'hub_connection_source';

	/**
	 * The instance of this class.
	 *
	 * @var Hub_Connector|null
	 */
	private static $instance;

	/**
	 * Hub_Connector constructor.
	 */
	private function __construct() {
		$this->init();

		add_action( 'wpmudev_hub_connector_first_sync_completed', array( $this, 'sync_after_connect' ) );
		\WPMUDEV\Hub\Connector::get();
	}

	/**
	 * Get instance of this class
	 *
	 * @return Hub_Connector
	 */
	public static function get_instance(): Hub_Connector {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the Hub Connector module and set its options.
	 *
	 * The `extra/hub-connector/connector.php` file is required, and the options are set for the Hub Connector module.
	 *
	 * @return void
	 */
	private function init() :void {
		$hub_connector_lib = WPHB_DIR_PATH . 'core/externals/hub-connector/connector.php';
		if ( file_exists( $hub_connector_lib ) && ! class_exists( '\WPMUDEV\Hub\Connector' ) ) {
			include_once $hub_connector_lib;
		}

		$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		if ( empty( $page ) || ( 'wphb' !== $page && strpos( $page, 'wphb-' ) !== 0 ) ) {
			return;
		}

		// verify valid request.
		if ( class_exists( 'WPMUDEV\Hub\Connector' ) && self::is_connection_flow() && ! wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), self::CONNECTION_ACTION ) ) {
			wp_die(
				esc_html__( 'Invalid request. Please go back and retry', 'wphb' ),
				esc_html__( 'Error', 'wphb' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Process after first connect.
	 *
	 * This method is called when the first sync with the Hub Connector is completed.
	 *
	 * @return void
	 */
	public function sync_after_connect() {
		add_option( 'wphb_show_connected_modal', true );
	}

	/**
	 * Render the Hub Connector success modal if applicable.
	 *
	 * @return void
	 */
	public static function render_hub_connector_success_modal() : void {
		if ( ! get_option( 'wphb_show_connected_modal' ) ) {
			return;
		}

		delete_option( 'wphb_show_connected_modal' );
		$file = WPHB_DIR_PATH . 'admin/modals/hub-connection-success-modal.php';

		if ( file_exists( $file ) ) {
			include_once $file;
		}

		?>
		<script>
			window.addEventListener("load", function(){
				window.SUI.openModal( 'wphb-hub-connection-success-modal', 'wpbody-content', undefined, false );
			});
		</script>
		<?php
	}

	/**
	 * Checks if the Hub Connection flow.
	 *
	 * @return bool
	 */
	public static function is_connection_flow(): bool {
		$action = filter_input( INPUT_GET, 'page_action', FILTER_UNSAFE_RAW );
		return ( ! empty( $action ) && self::CONNECTION_ACTION === $action );
	}

	/**
	 * Checks if Hub Connector grants access to the page.
	 *
	 * @return bool
	 */
	public static function has_access(): bool {
		return class_exists( '\WPMUDEV\Hub\Connector' ) && self::logged_in();
	}

	/**
	 * Checks if Hub Connector is logged in.
	 *
	 * @return bool
	 */
	public static function logged_in(): bool {
		return class_exists( '\WPMUDEV\Hub\Connector\API' ) && \WPMUDEV\Hub\Connector\API::get()->is_logged_in();
	}

	/**
	 * Disconnect site.
	 *
	 * @return bool
	 */
	public static function disconnect(): bool {
		return class_exists( '\WPMUDEV\Hub\Connector\API' ) && \WPMUDEV\Hub\Connector\API::get()->logout();
	}

	/**
	 * Sync site data with the Hub.
	 *
	 * @return bool|\WP_Error
	 */
	public static function sync() {
		if ( ! class_exists( '\WPMUDEV\Hub\Connector\API' ) ) {
			return false;
		}

		return \WPMUDEV\Hub\Connector\API::get()->sync_site();
	}

	/**
	 * Check whether a post-authentication Hub sync is pending.
	 *
	 * @return bool
	 */
	public static function is_syncing(): bool {
		if ( self::logged_in() || self::has_login_error() ) {
			delete_site_transient( self::SYNCING_TRANSIENT_KEY );

			return false;
		}

		$auth_nonce = sanitize_text_field( (string) filter_input( INPUT_GET, 'auth_nonce', FILTER_UNSAFE_RAW ) );
		if ( current_user_can( Utils::get_admin_capability() ) && wp_verify_nonce( $auth_nonce, 'auth_nonce' ) ) {
			$page_action = sanitize_text_field( (string) filter_input( INPUT_GET, 'page_action', FILTER_UNSAFE_RAW ) );
			$api_key     = sanitize_text_field( (string) filter_input( INPUT_GET, 'set_apikey', FILTER_UNSAFE_RAW ) );

			if ( self::CONNECTION_ACTION === $page_action && '' !== $api_key ) {
				set_site_transient( self::SYNCING_TRANSIENT_KEY, true, MINUTE_IN_SECONDS );
			}
		}

		return (bool) get_site_transient( self::SYNCING_TRANSIENT_KEY );
	}

	/**
	 * Hub connection entry points and the Hummingbird screen each one returns to.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private static function get_connection_sources(): array {
		return array(
			'profile_menu'        => array(),
			'uptime'              => array(
				'page' => 'wphb-advanced',
				'view' => 'uptime',
			),
			'database_cleanup'    => array(
				'page' => 'wphb-advanced',
				'view' => 'database_cleanup',
			),
			'performance_reports' => array(
				'page' => 'wphb-advanced',
				'view' => 'alerts_reports',
			),
			'uptime_reports'      => array(
				'page' => 'wphb-advanced',
				'view' => 'alerts_reports',
			),
			'uptime_threshold'    => array(
				'page' => 'wphb-advanced',
				'view' => 'alerts_reports',
			),
			'welcome_modal'       => array(),
		);
	}

	/**
	 * Entry point named by the current Hub callback request.
	 *
	 * @since 4.0.0
	 *
	 * @return string Empty when the request names none, or an unknown one.
	 */
	private static function get_requested_connection_source(): string {
		$source = sanitize_key( (string) filter_input( INPUT_GET, self::CONNECTION_SOURCE_PARAM, FILTER_UNSAFE_RAW ) );

		return array_key_exists( $source, self::get_connection_sources() ) ? $source : '';
	}

	/**
	 * Build a URL for every connection entry point.
	 *
	 * @since 4.0.0
	 *
	 * @param callable $builder Receives the source, returns its URL.
	 *
	 * @return array URLs keyed by source.
	 */
	private static function get_urls_by_source( callable $builder ): array {
		$sources = array_keys( self::get_connection_sources() );

		return array_combine( $sources, array_map( $builder, $sources ) );
	}

	/**
	 * Check whether the Hub returned an authentication error.
	 *
	 * @return bool
	 */
	public static function has_login_error(): bool {
		$page_action = sanitize_text_field( (string) filter_input( INPUT_GET, 'page_action', FILTER_UNSAFE_RAW ) );
		$api_error   = sanitize_text_field( (string) filter_input( INPUT_GET, 'api_error', FILTER_UNSAFE_RAW ) );

		return self::CONNECTION_ACTION === $page_action && ! in_array( trim( $api_error ), array( '', '0' ), true );
	}

	/**
	 * Get the authentication error returned by the Hub.
	 *
	 * @return string
	 */
	public static function get_login_error(): string {
		if ( ! self::has_login_error() ) {
			return '';
		}

		$api_error = sanitize_key( (string) filter_input( INPUT_GET, 'api_error', FILTER_UNSAFE_RAW ) );
		if ( 'auth' === $api_error || 1 === (int) $api_error ) {
			return __( 'Your login details were incorrect. Please make sure you\'re using your WPMU DEV email and password and try again.', 'wphb' );
		}

		$api_error_message = sanitize_text_field( (string) filter_input( INPUT_GET, 'api_error_msg', FILTER_UNSAFE_RAW ) );
		if ( '' !== $api_error_message ) {
			return $api_error_message;
		}

		return __( 'Login failed. Please try again.', 'wphb' );
	}

	/**
	 * Get connection URL
	 *
	 * @param string $target_page The target page to connect to.
	 * @param string $utm_campaign The UTM campaign to append to the URL.
	 * @param string $view         Tab of the target page, when it has tabs.
	 *
	 * @return string
	 */
	public static function get_connect_site_url( $target_page = 'wphb-uptime', $utm_campaign = '', $view = '' ): string {
		$args = array();

		if ( ! self::is_wpmudev_dashboard_connected() && ( class_exists( 'WPMUDEV_Dashboard' ) ) ) {
			$args['page'] = 'wpmudev';
		} else {
			$args['page']        = $target_page;
			$args['_wpnonce']    = wp_create_nonce( self::CONNECTION_ACTION );
			$args['page_action'] = self::CONNECTION_ACTION;

			if ( ! empty( $view ) ) {
				$args['view'] = $view;
			}
		}
		if ( ! empty( $utm_campaign ) ) {
			$args['utm_campaign'] = $utm_campaign;
		}

		$admin_url = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );

		return add_query_arg( $args, $admin_url );
	}

	/**
	 * Check if WPMUDEV Dashboard is installed
	 *
	 * @return bool
	 */
	public static function is_wpmudev_dashboard_connected(): bool {
		return class_exists( 'WPMUDEV_Dashboard' ) &&
			is_object( WPMUDEV_Dashboard::$api ) &&
			method_exists( WPMUDEV_Dashboard::$api, 'get_membership_status' ) && WPMUDEV_Dashboard::$api->has_key();
	}

	/**
	 * Check if the WPMU DEV Dashboard plugin is present on disk.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	public static function is_dash_installed(): bool {
		return file_exists( WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php' );
	}

	/**
	 * Check if the WPMU DEV Dashboard plugin is active.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	public static function is_dash_activated(): bool {
		return class_exists( 'WPMUDEV_Dashboard' );
	}

	/**
	 * One-click activation URL for the installed WPMU DEV Dashboard plugin.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	public static function get_dash_activate_url(): string {
		$plugin = 'wpmudev-updates/update-notifications.php';
		$url    = is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$url    = add_query_arg(
			array(
				'action' => 'activate',
				'plugin' => rawurlencode( $plugin ),
			),
			$url
		);

		return add_query_arg( '_wpnonce', wp_create_nonce( 'activate-plugin_' . $plugin ), $url );
	}

	/**
	 * One-click install URL for the WPMU DEV Dashboard plugin.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	public static function get_dash_install_url(): string {
		if ( ! class_exists( 'WPMUDEV_Dashboard_Notice' ) ) {
			return 'https://wpmudev.com/project/wpmu-dev-dashboard/';
		}

		$url = is_multisite() ? network_admin_url( 'update.php' ) : admin_url( 'update.php' );
		$url = add_query_arg(
			array(
				'action' => 'install-plugin',
				'plugin' => 'install_wpmudev_dash',
			),
			$url
		);

		return add_query_arg( '_wpnonce', wp_create_nonce( 'install-plugin_install_wpmudev_dash' ), $url );
	}

	/**
	 * Admin URL for the current context.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	private static function get_admin_url(): string {
		return is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
	}

	/**
	 * Hub member profile for the React UI.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty when the site isn't connected, or the profile is unusable.
	 */
	public static function get_profile_data_for_ui(): array {
		if ( ! class_exists( '\WPMUDEV\Hub\Connector\Data' ) ) {
			return array();
		}

		$hub_logged_in       = self::logged_in();
		$dashboard_connected = self::is_wpmudev_dashboard_connected();

		if ( ! $hub_logged_in && ! $dashboard_connected ) {
			return array();
		}

		if (
			! $hub_logged_in &&
			is_object( WPMUDEV_Dashboard::$settings ) &&
			method_exists( WPMUDEV_Dashboard::$settings, 'get' )
		) {
			$dashboard_profile = WPMUDEV_Dashboard::$settings->get( 'profile_data' );
			$profile           = isset( $dashboard_profile['profile'] ) ? $dashboard_profile['profile'] : array();
		} else {
			$profile = \WPMUDEV\Hub\Connector\Data::get()->profile_data();
		}

		if ( ! is_array( $profile ) || empty( $profile['user_name'] ) ) {
			return array();
		}

		$display_name = isset( $profile['name'] ) ? (string) $profile['name'] : '';
		$user_name    = (string) $profile['user_name'];

		return array(
			'initials'    => '' === $display_name ? '' : mb_strtoupper( mb_substr( $display_name, 0, 1 ) ),
			'avatar'      => isset( $profile['avatar'] ) ? (string) $profile['avatar'] : '',
			'displayName' => $display_name,
			'userName'    => $user_name,
			'email'       => is_email( $user_name ) ? $user_name : '',
		);
	}

	/**
	 * Hub signup URL for the "Create free account" action.
	 *
	 * The React UI swaps in the entry point's own utm_campaign (HUB_CREATE_ACCOUNT_CAMPAIGNS).
	 *
	 * @since 4.0.0
	 *
	 * @param string $source Connection entry point, see get_connection_sources().
	 *
	 * @return string
	 */
	public static function get_hub_signup_url( string $source = '' ): string {
		if ( ! class_exists( '\WPMUDEV\Hub\Connector\Data' ) ) {
			return '';
		}

		$redirect_url = add_query_arg( 'auth_nonce', wp_create_nonce( 'auth_nonce' ), self::get_hub_auth_redirect_url( $source ) );

		return add_query_arg(
			array(
				'signup'           => 'site-connect',
				'site_connect_url' => rawurlencode( $redirect_url ),
				'connect_ref'      => self::PLUGIN_IDENTIFIER,
				'utm_medium'       => 'plugin',
				'utm_source'       => self::PLUGIN_IDENTIFIER,
				'utm_campaign'     => 'hummingbird_profile_avatar_connect',
				'utm_content'      => 'hub-connector',
			),
			\WPMUDEV\Hub\Connector\Data::get()->server_url( 'register' )
		);
	}

	/**
	 * Get Hub Auth URL (https://wpmudev.com/api/dashboard/v2/site-authenticate).
	 *
	 * @return string
	 */
	public static function get_hub_auth_url(): string {
		return class_exists( '\WPMUDEV\Hub\Connector\API' ) ? \WPMUDEV\Hub\Connector\API::get()->rest_url( 'site-authenticate' ) : '';
	}

	/**
	 * Get Hub Google authentication URL.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	public static function get_hub_google_auth_url(): string {
		return class_exists( '\WPMUDEV\Hub\Connector\API' ) ? \WPMUDEV\Hub\Connector\API::get()->rest_url( 'google-auth' ) : '';
	}

	/**
	 * Get the URL used by Hub authentication to return to Hummingbird.
	 *
	 * Entry points that live on a specific screen return there; the rest return to the screen
	 * (and tab) the connection was started from.
	 *
	 * @since 4.0.0
	 *
	 * @param string $source Connection entry point, see get_connection_sources().
	 *
	 * @return string
	 */
	public static function get_hub_auth_redirect_url( string $source = '' ): string {
		$sources = self::get_connection_sources();
		$target  = isset( $sources[ $source ] ) ? $sources[ $source ] : array();

		if ( empty( $target['page'] ) ) {
			$page   = sanitize_text_field( (string) filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) );
			$target = array(
				'page' => 0 === strpos( $page, 'wphb' ) ? $page : 'wphb',
				'view' => sanitize_key( (string) filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW ) ),
			);
		}

		$args = array( 'hub_connector_callback' => 1 );
		if ( isset( $sources[ $source ] ) ) {
			$args[ self::CONNECTION_SOURCE_PARAM ] = $source;
		}

		return add_query_arg( $args, self::get_connect_site_url( $target['page'], '', $target['view'] ) );
	}

	/**
	 * Get the site domain expected by Hub authentication.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	public static function get_site_domain(): string {
		return class_exists( '\WPMUDEV\Hub\Connector\Data' ) ? \WPMUDEV\Hub\Connector\Data::get()->network_site_url() : '';
	}

	/**
	 * Get Hub forgot-password URL.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	public static function get_hub_forgot_password_url(): string {
		return class_exists( '\WPMUDEV\Hub\Connector\Data' )
			? \WPMUDEV\Hub\Connector\Data::get()->server_url( 'forgot-password' )
			: 'https://wpmudev.com/forgot-password';
	}

	/**
	 * Hub connection state for the React UI.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public static function data_frontend(): array {
		$is_logged_in = self::logged_in();

		return array(
			'isLoggedIn'             => $is_logged_in || self::is_wpmudev_dashboard_connected(),
			'isHubConnectorLoggedIn' => $is_logged_in && ! self::is_wpmudev_dashboard_connected(),
			'isDashboardConnected'   => self::is_wpmudev_dashboard_connected(),
			'isDashInstalled'        => self::is_dash_installed(),
			'isDashActivated'        => self::is_dash_activated(),
			'dashUrl'                => add_query_arg( 'page', 'wpmudev', self::get_admin_url() ),
			'hubSignupUrls'          => self::get_urls_by_source( array( __CLASS__, 'get_hub_signup_url' ) ),
			'dashActivateUrl'        => self::get_dash_activate_url(),
			'dashInstallUrl'         => self::get_dash_install_url(),
			'hubAuthUrl'             => self::get_hub_auth_url(),
			'googleAuthUrl'          => self::get_hub_google_auth_url(),
			'redirectUrls'           => self::get_urls_by_source( array( __CLASS__, 'get_hub_auth_redirect_url' ) ),
			'domain'                 => self::get_site_domain(),
			'authNonce'              => wp_create_nonce( 'auth_nonce' ),
			'forgotPasswordUrl'      => self::get_hub_forgot_password_url(),
			'isSyncing'              => self::is_syncing(),
			'hasLoginError'          => self::has_login_error(),
			'loginErrorMessage'      => self::get_login_error(),
			'connectionSource'       => self::get_requested_connection_source(),
		);
	}
}
