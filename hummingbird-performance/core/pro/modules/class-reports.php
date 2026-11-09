<?php
/**
 * Abstract module for reports modules.
 *
 * Supports Performance reports and Uptime reports.
 * Also used for sending out email reports after performance scans
 *
 * @since 1.9.4
 * @package Hummingbird\Core\Pro\Modules
 */

namespace Hummingbird\Core\Pro\Modules;

use DateTime;
use DateTimeZone;
use Exception;
use Hummingbird\Core\Module;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Whitelabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Reports
 */
abstract class Reports extends Module {

	/**
	 * Module slug. It's used in the database calls. Accepted values: performance, uptime.
	 *
	 * @var string $module
	 */
	protected static $module;

	/**
	 * Tracks initialized cron hooks by module to avoid duplicate callbacks.
	 *
	 * @var array<string,bool>
	 */
	private static $cron_hooks_initialized = array();

	/**
	 * Initialize the module
	 *
	 * @since 1.9.4
	 */
	public function init() {
		add_action( 'wphb_activate', array( $this, 'maybe_enable_cron_jobs' ) );

		// Self-healing safety net: re-check/reschedule whenever the Alerts &
		// Reports settings (Advanced Tools page) load, in case the cron got
		// lost outside of the explicit enable/disable save flow
		// Used to be wired to the legacy 'wphb_admin_do_meta_boxes_wphb-notifications'
		// meta box hook; that hook no longer exists now that the page is
		// fully React-rendered, so hook into the page's load action instead.
		add_action( 'wphb_load_admin_page_wphb-advanced', array( $this, 'maybe_enable_cron_jobs' ) );

		if ( isset( self::$cron_hooks_initialized[ $this::$module ] ) ) {
			return;
		}

		// Process report cron task.
		add_action( 'wphb_' . $this::$module . '_report', array( $this, 'process_report' ) );
		self::$cron_hooks_initialized[ $this::$module ] = true;
	}

	/**
	 * Execute the module actions.
	 */
	public function run() {}

	/**
	 * Implement abstract parent method for clearing cache.
	 */
	public function clear_cache() {}

	/**
	 * Function to process cron report task.
	 */
	abstract protected function process_report();

	/**
	 * Get email subject.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	abstract protected function get_subject();

	/**
	 * Sender name for the report email.
	 *
	 * @since 4.0.1
	 *
	 * @return string
	 */
	protected function get_sender_name() {
		if ( ! Whitelabel::is_enabled() ) {
			return 'Hummingbird';
		}

		$custom_name = Whitelabel::get_plugin_name();

		return $custom_name ? $custom_name : __( 'Reports', 'wphb' );
	}

	/**
	 * Get report params for email.
	 *
	 * @since 3.2.0
	 *
	 * @return array
	 */
	abstract protected function get_params();

	/**
	 * Should send out report.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	protected function should_continue() {
		return true;
	}

	/**
	 * Enable cron task. Triggered during plugin activation.
	 *
	 * @since 1.9.4
	 */
	public function maybe_enable_cron_jobs() {
		if ( ! Utils::has_access_to_hub() ) {
			return;
		}

		$options = Settings::get_setting( 'reports', $this::$module );

		// Not enabled - do nothing.
		if ( ! isset( $options['enabled'] ) || ! $options['enabled'] ) {
			return;
		}

		// Notifications enabled, but not scheduled - reschedule.
		$timestamp = wp_next_scheduled( 'wphb_' . $this::$module . '_report' );
		if ( false === $timestamp ) {
			$next_event = self::get_scheduled_time( $this::$module );
			if ( $next_event ) {
				wp_schedule_single_event( $next_event, 'wphb_' . $this::$module . '_report' );
			}
		}
	}

	/**
	 * Get the schedule time for a scan.
	 *
	 * @since 1.4.5
	 *
	 * @param string $module      Module slug.
	 * @param bool   $clear_cron  Force clear scanning cron.
	 *
	 * @return false|int
	 */
	public static function get_scheduled_time( $module, $clear_cron = true ) {
		if ( $clear_cron ) {
			wp_clear_scheduled_hook( "wphb_{$module}_report" );
		}

		$settings         = Settings::get_setting( 'reports', $module );
		$pro_notification = Utils::pro()->module( 'notifications' );
		if ( false !== $pro_notification && is_object( $pro_notification ) ) {
			$time_str = $pro_notification->get_reports_time_string( $settings );

			return self::local_to_utc( $time_str );
		}

		return false;
	}

	/**
	 * Local time to UTC.
	 *
	 * @since 1.4.5
	 *
	 * @param string $time  Time string.
	 *
	 * @return false|int
	 */
	public static function local_to_utc( $time ) {
		$tz = get_option( 'timezone_string' );
		if ( ! $tz ) {
			$gmt_offset = get_option( 'gmt_offset' );
			if ( 0 === $gmt_offset ) {
				return strtotime( $time );
			}
			$tz = self::get_timezone_string( $gmt_offset );
		}

		if ( ! $tz ) {
			$tz = 'UTC';
		}
		$timezone = new DateTimeZone( $tz );
		try {
			$time = new DateTime( $time, $timezone );
			return $time->getTimestamp();
		} catch ( Exception $e ) {
			error_log( '[' . current_time( 'mysql' ) . '] - Error in local_to_utc(). Error: ' . $e->getMessage() );
		}

		return false;
	}

	/**
	 * Get time zone string.
	 *
	 * @since  1.4.5
	 *
	 * @param  string $timezone  Time zone.
	 *
	 * @return false|string
	 */
	private static function get_timezone_string( $timezone ) {
		$timezone = explode( '.', $timezone );
		if ( isset( $timezone[1] ) ) {
			$timezone[1] = 30;
		} else {
			$timezone[1] = '00';
		}
		$offset                  = implode( ':', $timezone );
		list( $hours, $minutes ) = explode( ':', $offset );
		$seconds                 = $hours * 60 * 60 + $minutes * 60;
		$tz                      = timezone_name_from_abbr( '', $seconds, 1 );
		if ( false === $tz ) {
			$tz = timezone_name_from_abbr( '', $seconds, 0 );
		}

		return $tz;
	}

	/**
	 * Refresh recipient Hub confirmation status before sending a report.
	 *
	 * @since 4.0.0
	 *
	 * @param array $recipients  Recipients currently stored for this report module.
	 *
	 * @return array Recipients with refreshed is_pending/is_subscribed/is_can_resend_confirmation flags.
	 */
	private function refresh_recipient_confirmation_status( array $recipients ) {
		if ( is_multisite() && get_current_blog_id() !== get_main_site_id() ) {
			return $recipients;
		}

		if ( ! Utils::has_access_to_hub() ) {
			return $recipients;
		}

		$has_pending = false;
		foreach ( $recipients as $recipient ) {
			if ( ! empty( $recipient['is_pending'] ) ) {
				$has_pending = true;
				break;
			}
		}

		if ( ! $has_pending ) {
			return $recipients;
		}

		// Only ever ask the Hub about recipients that still need a confirmation.
		$needs_check = static function ( $recipient ) {
			return ! empty( $recipient['is_pending'] ) || empty( $recipient['is_subscribed'] );
		};

		$notifications     = Settings::get_setting( 'notifications', 'uptime' );
		$master_recipients = ( is_array( $notifications ) && ! empty( $notifications['recipients'] ) && is_array( $notifications['recipients'] ) )
			? array_filter( $notifications['recipients'], $needs_check )
			: array();

		$merged       = array_values( $master_recipients );
		$known_emails = wp_list_pluck( $merged, 'email' );
		foreach ( $recipients as $recipient ) {
			if ( empty( $recipient['email'] ) || in_array( $recipient['email'], $known_emails, true ) || ! $needs_check( $recipient ) ) {
				continue;
			}
			$merged[]       = $recipient;
			$known_emails[] = $recipient['email'];
		}

		if ( empty( $merged ) ) {
			return $recipients;
		}

		try {
			$response = Utils::get_api()->uptime->sync_hub(
				array(
					'notification' => array(
						'recipients' => $merged,
					),
				)
			);
		} catch ( Exception $e ) {
			return $recipients;
		}

		if ( is_wp_error( $response ) || empty( $response->notification->recipients ) ) {
			return $recipients;
		}

		$hub_recipients = json_decode( wp_json_encode( $response->notification->recipients ), true );
		$hub_by_email   = array();
		foreach ( $hub_recipients as $hub_recipient ) {
			if ( ! empty( $hub_recipient['email'] ) ) {
				$hub_by_email[ strtolower( $hub_recipient['email'] ) ] = $hub_recipient;
			}
		}

		foreach ( $recipients as $index => $recipient ) {
			if ( empty( $recipient['email'] ) ) {
				continue;
			}

			$hub_match = isset( $hub_by_email[ strtolower( $recipient['email'] ) ] ) ? $hub_by_email[ strtolower( $recipient['email'] ) ] : null;
			if ( ! $hub_match ) {
				continue;
			}

			foreach ( array( 'is_pending', 'is_subscribed', 'is_can_resend_confirmation' ) as $flag ) {
				if ( isset( $hub_match[ $flag ] ) ) {
					$recipients[ $index ][ $flag ] = (bool) $hub_match[ $flag ];
				}
			}
		}

		return $recipients;
	}

	/**
	 * Send out an email report.
	 *
	 * @since 1.4.5
	 *
	 * @param mixed $last_report  Last report data.
	 */
	protected function send_email_report( $last_report ) {
		if ( ! $this->should_continue() ) {
			return;
		}

		$options = Settings::get_setting( 'reports', $this::$module );
		if ( empty( $options['recipients'] ) ) {
			return;
		}

		$options['recipients'] = $this->refresh_recipient_confirmation_status( $options['recipients'] );
		Settings::update_setting( 'reports', $options, $this::$module );

		foreach ( $options['recipients'] as $recipient ) {
			if ( isset( $recipient['is_pending'] ) && $recipient['is_pending'] ) {
				continue;
			}

			if ( isset( $recipient['is_subscribed'] ) && ! $recipient['is_subscribed'] ) {
				continue;
			}

			$params = array(
				'REPORT_TYPE' => $this::$module,
				'USER_NAME'   => $recipient['name'],
				'SITE_URL'    => wp_parse_url( site_url(), PHP_URL_HOST ),
				'SITE_NAME'   => get_bloginfo( 'name' ),
			);
			$params = array_merge( $params, $this->get_params() );

			$email_content = self::issues_list_html( $last_report, $params );

			// Change nl to br.
			$email_content  = stripslashes( $email_content );
			$no_reply_email = 'noreply@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
			$headers        = array(
				'From: ' . $this->get_sender_name() . ' <' . $no_reply_email . '>',
				'Content-Type: text/html; charset=UTF-8',
			);

			wp_mail( $recipient['email'], $this->get_subject(), $email_content, $headers );
		}
	}

	/**
	 * Build issues html table.
	 *
	 * @access private
	 * @param  mixed $last_test  Latest test data.
	 * @param  array $params     Additional data for report.
	 * @return string            HTML for email.
	 * @since  1.4.5
	 */
	protected static function issues_list_html( $last_test, $params ) {
		ob_start();
		self::load_template( 'index', compact( 'last_test', 'params' ) );
		return ob_get_clean();
	}

	/**
	 * Try to load a single reporting template.
	 *
	 * @param string $template  Template name. It should match the filename without extension.
	 * @param array  $args      Variables to pass to the templates.
	 */
	public static function load_template( $template, $args = array() ) {
		$dirs = apply_filters(
			'wphb_reporting_templates_folders',
			array(
				'stylesheet' => get_stylesheet_directory() . '/wphb/',
				'template'   => get_template_directory() . '/wphb/',
				'plugin'     => WPHB_DIR_PATH . 'core/pro/modules/reporting/templates/',
			)
		);

		foreach ( (array) $dirs as $dir ) {
			$file = trailingslashit( $dir ) . "$template.php";
			if ( is_readable( $file ) ) {
				extract( $args );
				include $file;
				break;
			}
		}
	}
}
