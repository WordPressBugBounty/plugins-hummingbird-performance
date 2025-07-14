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
	 * The valid screen for the Hub Connector.
	 *
	 * @var string
	 */
	public static $valid_screen = false;

	/**
	 * The instance of this class.
	 *
	 * @var Hub_Connector|null
	 */
	private static $instance;

	/**
	 * The allowed screens for the Hub Connector.
	 *
	 * @var array
	 */
	private $allowed_screens = array();

	/**
	 * Hub_Connector constructor.
	 */
	private function __construct() {
		$this->init();

		add_action( 'wpmudev_hub_connector_first_sync_completed', array( $this, 'sync_after_connect' ) );
		\WPMUDEV\Hub\Connector::get();

		if ( ! self::$valid_screen || ! self::is_connection_flow() ) {
			return;
		}

		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ), 11 );
		add_filter( 'wpmudev_hub_connector_localize_text_vars', array( $this, 'customize_text_vars' ), 10, 2 );
		add_filter( 'wpmudev_hub_connector_localize_vars', array( $this, 'add_hub_connector_data' ), 10, 1 );

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
		if ( file_exists( $hub_connector_lib ) ) {
			include_once $hub_connector_lib;
		}

		if ( class_exists( 'WPMUDEV\Hub\Connector' ) ) {
			$this->allowed_screens = array( 'wphb-uptime', 'wphb-notifications' );

			// verify valid request.
			if ( self::is_connection_flow() && ! wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), self::CONNECTION_ACTION ) ) {
				wp_die(
					esc_html__( 'Invalid request. Please go back and retry', 'wphb' ),
					esc_html__( 'Error', 'wphb' ),
					array( 'response' => 403 )
				);
			}

			$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
			if ( in_array( $page, $this->allowed_screens, true ) ) {
				self::$valid_screen = str_replace( 'wphb-', '', $page );
			}

			return;

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
		Utils::get_module( 'uptime' )->enable();
		add_option( 'wphb_show_connected_modal', true );
	}

	/**
	 * Return the classes to be added to the body in the admin.
	 *
	 * @param String $classes Classes to be added.
	 * @return String
	 */
	public function admin_body_class( $classes ) : string {
		$classes .= ' ' . WPMUDEV_HUB_CONNECTOR_SUI_VERSION;

		return $classes;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render(): void {
		do_action( 'wpmudev_hub_connector_ui', 'hummingbird' );
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
	 * Adds the Hub connector data to the Hummingbird data.
	 *
	 * @param array $extra_args The Hummingbird data.
	 *
	 * @return array The Hummingbird data with the Hub connector data.
	 */
	public function add_hub_connector_data( $extra_args ): array {
		if ( key_exists( 'login', $extra_args ) && key_exists( 'register_url', $extra_args['login'] ) ) {
			$utm_campaign = filter_input( INPUT_GET, 'utm_campaign', FILTER_UNSAFE_RAW );
			$register_url = $extra_args['login']['register_url'];
			$register_url = add_query_arg(
				array(
					'connect_ref'  => self::PLUGIN_IDENTIFIER,
					'utm_medium'   => 'plugin',
					'utm_source'   => self::PLUGIN_IDENTIFIER,
					'utm_campaign' => empty( $utm_campaign ) ? 'hummingbird_' . self::$valid_screen . '_connect_button' : $utm_campaign,
					'utm_content'  => 'hub-connector',
				),
				$register_url
			);

			$extra_args['login']['register_url'] = $register_url;
		}

		return $extra_args;
	}

	/**
	 * Modify text string vars.
	 *
	 * @param array  $texts  Vars.
	 * @param string $plugin Plugin identifier.
	 *
	 * @return array
	 */
	public function customize_text_vars( $texts, $plugin ): array {
		if ( self::PLUGIN_IDENTIFIER === $plugin ) {
			$feature      = ucfirst( self::$valid_screen );
			$feature_part = ucfirst( self::PLUGIN_IDENTIFIER ) . ' - ' . esc_html( $feature );

			$texts['create_account_desc'] = sprintf(
				/* translators: %1$s: Feature, %2$s: Opening italic tag, %3$s: Closing italic tag. */
				esc_html__( 'Create a free account to connect your site to WPMU DEV and activate %1$s. %2$s It`s fast, seamless, and free. %3$s', 'wphb' ),
				'<strong>' . $feature_part . '</strong>',
				'<i>',
				'</i>'
			);
			$texts['login_desc'] = sprintf(
				/* translators: %s: Feature */
				esc_html__( 'Log in with your WPMU DEV account credentials to activate %s.', 'wphb' ),
				$feature_part
			);
		}

		return $texts;
	}

	/**
	 * Get connection URL
	 *
	 * @param string $target_page The target page to connect to.
	 * @param string $utm_campaign The UTM campaign to append to the URL.
	 *
	 * @return string
	 */
	public static function get_connect_site_url( $target_page = 'wphb-uptime', $utm_campaign = '' ): string {
		$args = array();

		if ( ! self::is_wpmudev_dashboard_connected() && ( class_exists( 'WPMUDEV_Dashboard' ) ) ) {
			$args['page'] = 'wpmudev';
		} else {
			$args['page']        = $target_page;
			$args['_wpnonce']    = wp_create_nonce( self::CONNECTION_ACTION );
			$args['page_action'] = self::CONNECTION_ACTION;
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
}
