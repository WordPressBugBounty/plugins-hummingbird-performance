<?php
/**
 * React Alerts & Reports AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Exception;
use Hummingbird\Core\Hub_Connector;
use Hummingbird\Core\Pro\Modules\Reports;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Alerts_Reports.
 */
class Alerts_Reports {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'alerts_reports';

	/**
	 * Alerts_Reports constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'alerts_reports_status',
			'alerts_reports_save',
			'alerts_reports_search_users',
			'alerts_reports_send_confirmation',
			'alerts_reports_resend_confirmation',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	/**
	 * Get Alerts & Reports page values.
	 *
	 * @since 4.0.0
	 */
	public function alerts_reports_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( self::get_page_data() );
	}

	/**
	 * Save Alerts & Reports values.
	 *
	 * @since 4.0.0
	 */
	public function alerts_reports_save() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$raw  = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $raw ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$performance_payload  = isset( $data['performanceReports'] ) && is_array( $data['performanceReports'] ) ? $data['performanceReports'] : array();
		$uptime_report_payload = isset( $data['uptimeReports'] ) && is_array( $data['uptimeReports'] ) ? $data['uptimeReports'] : array();
		$uptime_alert_payload = isset( $data['uptimeAlerts'] ) && is_array( $data['uptimeAlerts'] ) ? $data['uptimeAlerts'] : array();
		$is_subsite_context = is_multisite() && ( get_current_blog_id() !== get_main_site_id() );

		$performance_enabled = ! empty( $performance_payload['enabled'] );
		$uptime_report_enabled = ! empty( $uptime_report_payload['enabled'] );
		$uptime_alert_enabled = ! empty( $uptime_alert_payload['enabled'] );

		if ( $is_subsite_context ) {
			$uptime_report_enabled = false;
			$uptime_alert_enabled = false;
		}

		if ( ( $performance_enabled || $uptime_report_enabled || $uptime_alert_enabled ) && ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use this feature.', 'wphb' ) ) );
		}

		$performance_settings = Settings::get_setting( 'reports', 'performance' );
		if ( ! is_array( $performance_settings ) ) {
			$performance_settings = array();
		}

		$uptime_report_settings = Settings::get_setting( 'reports', 'uptime' );
		if ( ! is_array( $uptime_report_settings ) ) {
			$uptime_report_settings = array();
		}

		$uptime_alert_settings = Settings::get_setting( 'notifications', 'uptime' );
		if ( ! is_array( $uptime_alert_settings ) ) {
			$uptime_alert_settings = array();
		}

		$was_performance_enabled   = ! empty( $performance_settings['enabled'] );
		$was_uptime_report_enabled = ! empty( $uptime_report_settings['enabled'] );
		$was_uptime_alert_enabled  = ! empty( $uptime_alert_settings['enabled'] );

		$incoming_recipients = isset( $uptime_alert_payload['recipients'] ) && is_array( $uptime_alert_payload['recipients'] )
			? $uptime_alert_payload['recipients']
			: array();

		$sanitized_recipients = $this->sanitize_recipients_with_subscriptions( $incoming_recipients );

		if ( $is_subsite_context ) {
			$performance_settings['all_recipients'] = $this->get_all_recipients(
				$sanitized_recipients,
				isset( $performance_settings['all_recipients'] ) && is_array( $performance_settings['all_recipients'] )
					? $performance_settings['all_recipients']
					: array()
			);
		} else {
			$uptime_alert_settings['all_recipients'] = $this->get_all_recipients(
				$sanitized_recipients,
				isset( $uptime_alert_settings['all_recipients'] ) && is_array( $uptime_alert_settings['all_recipients'] )
					? $uptime_alert_settings['all_recipients']
					: array()
			);
		}

		$performance_settings['recipients'] = $this->get_recipients_for_subscription(
			$sanitized_recipients,
			'performance',
			isset( $performance_settings['recipients'] ) && is_array( $performance_settings['recipients'] )
				? $performance_settings['recipients']
				: array()
		);

		if ( ! $is_subsite_context ) {
			$uptime_report_settings['recipients'] = $this->get_recipients_for_subscription(
				$sanitized_recipients,
				'uptime',
				isset( $uptime_report_settings['recipients'] ) && is_array( $uptime_report_settings['recipients'] )
					? $uptime_report_settings['recipients']
					: array()
			);

			$uptime_alert_settings['recipients'] = $this->get_recipients_for_subscription(
				$sanitized_recipients,
				'alerts',
				isset( $uptime_alert_settings['recipients'] ) && is_array( $uptime_alert_settings['recipients'] )
					? $uptime_alert_settings['recipients']
					: array()
			);
		}

		$performance_settings['enabled'] = $performance_enabled;
		$this->apply_schedule_payload( $performance_settings, isset( $performance_payload['schedule'] ) ? $performance_payload['schedule'] : array() );

		$report_keys = isset( $performance_payload['selectedReports'] ) && is_array( $performance_payload['selectedReports'] )
			? array_map( 'sanitize_key', $performance_payload['selectedReports'] )
			: array();

		$performance_settings['metrics'] = in_array( 'score_metrics', $report_keys, true );
		$performance_settings['audits'] = in_array( 'audits', $report_keys, true );
		$performance_settings['historic'] = in_array( 'historic_field_data', $report_keys, true );

		$device = isset( $performance_payload['device'] ) ? sanitize_key( $performance_payload['device'] ) : 'all_devices';
		if ( 'all_devices' === $device ) {
			$device = 'both';
		}
		if ( ! in_array( $device, array( 'both', 'desktop', 'mobile' ), true ) ) {
			$device = 'both';
		}
		$performance_settings['type'] = $device;
		$performance_settings['last_sent'] = '';

		if ( ! $is_subsite_context ) {
			$uptime_report_settings['enabled'] = $uptime_report_enabled;
			$uptime_report_settings['show_ping'] = true;
			$this->apply_schedule_payload( $uptime_report_settings, isset( $uptime_report_payload['schedule'] ) ? $uptime_report_payload['schedule'] : array() );
			$uptime_report_settings['last_sent'] = '';

			$uptime_alert_settings['enabled'] = $uptime_alert_enabled;
			$uptime_alert_settings['threshold'] = $this->sanitize_threshold(
				isset( $uptime_alert_payload['threshold'] ) ? $uptime_alert_payload['threshold'] : 5
			);
		}

		Settings::update_setting( 'reports', $performance_settings, 'performance' );
		if ( ! $is_subsite_context ) {
			Settings::update_setting( 'reports', $uptime_report_settings, 'uptime' );
			Settings::update_setting( 'notifications', $uptime_alert_settings, 'uptime' );
		}

		$this->sync_report_schedule( 'performance', $performance_enabled );
		if ( ! $is_subsite_context && Utils::get_module( 'uptime' )->is_active() ) {
			$this->sync_report_schedule( 'uptime', $uptime_report_enabled );
			$this->sync_uptime_alerts( $uptime_alert_settings );
		}

		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_performance_enabled,
			$performance_enabled,
			__( 'Performance reports enabled.', 'wphb' ),
			__( 'Performance reports disabled.', 'wphb' )
		);

		if ( ! $is_subsite_context ) {
			Activity_Log::record_toggle_when_changed(
				self::ACTIVITY_MODULE,
				$was_uptime_report_enabled,
				$uptime_report_enabled,
				__( 'Uptime reports enabled.', 'wphb' ),
				__( 'Uptime reports disabled.', 'wphb' )
			);

			Activity_Log::record_toggle_when_changed(
				self::ACTIVITY_MODULE,
				$was_uptime_alert_enabled,
				$uptime_alert_enabled,
				__( 'Uptime alerts enabled.', 'wphb' ),
				__( 'Uptime alerts disabled.', 'wphb' )
			);
		}

		wp_send_json_success( self::get_page_data() );
	}

	/**
	 * Search WordPress users for the recipient picker.
	 *
	 * @since 4.0.0
	 */
	public function alerts_reports_search_users() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$raw  = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $raw ), true );
		$query = isset( $data['query'] ) ? sanitize_text_field( $data['query'] ) : '';

		$users = Utils::pro()->module( 'notifications' )->get_users( "*$query*", array() );

		wp_send_json_success( array( 'users' => $users ) );
	}

	/**
	 * Send confirmation email for a recipient invite.
	 *
	 * @since 4.0.0
	 */
	public function alerts_reports_send_confirmation() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use this feature.', 'wphb' ) ) );
		}

		$raw  = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $raw ), true );

		$name  = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

		if ( ! $email ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'wphb' ) ) );
		}

		// Subsites recipients performance only, thus not subscription confirmed.
		if ( is_multisite() && ( get_current_blog_id() !== get_main_site_id() ) ) {
			wp_send_json_success(
				array(
					'subscribed' => true,
					'pending'    => false,
					'canResend'  => false,
				)
			);
		}

		try {
			$notifications = Settings::get_setting( 'notifications', 'uptime' );
			if ( ! is_array( $notifications ) ) {
				$notifications = array();
			}

			$recipients = array(
				array(
					'name'  => $name,
					'email' => $email,
				),
			);

			if ( ! empty( $notifications['recipients'] ) && is_array( $notifications['recipients'] ) ) {
				$emails = wp_list_pluck( $notifications['recipients'], 'email' );
				if ( ! in_array( $email, $emails, true ) ) {
					$recipients = array_merge( $notifications['recipients'], $recipients );
				}
			}

			$response = Utils::get_api()->uptime->sync_hub(
				array(
					'notification' => array(
						'recipients' => $recipients,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error(
					array(
						'message' => $response->get_error_message(),
					)
				);
			}

			$confirmed_recipients = ( is_object( $response ) && isset( $response->notification->recipients ) && is_array( $response->notification->recipients ) )
				? $response->notification->recipients
				: array();
			$emails               = wp_list_pluck( $confirmed_recipients, 'email' );
			$key                  = array_search( $email, $emails, true );

			if ( false === $key || ! isset( $confirmed_recipients[ $key ] ) ) {
				wp_send_json_success(
					array(
						'subscribed' => false,
						'pending'    => true,
						'canResend'  => true,
					)
				);
			}

			wp_send_json_success(
				array(
					'subscribed' => ! empty( $confirmed_recipients[ $key ]->is_subscribed ),
					'pending'    => ! empty( $confirmed_recipients[ $key ]->is_pending ),
					'canResend'  => ! empty( $confirmed_recipients[ $key ]->is_can_resend_confirmation ),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Resend the confirmation email to an already-invited recipient.
	 *
	 * Uses the dedicated resend endpoint instead of re-syncing the whole
	 * recipients list, since the recipient is already known to the hub.
	 *
	 * @since 4.0.0
	 */
	public function alerts_reports_resend_confirmation() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use this feature.', 'wphb' ) ) );
		}

		$raw   = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data  = json_decode( html_entity_decode( $raw ), true );
		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

		if ( ! $email ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'wphb' ) ) );
		}

		if ( is_multisite() && ( get_current_blog_id() !== get_main_site_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'This action is not available on subsites.', 'wphb' ) ) );
		}

		try {
			$response = Utils::get_api()->uptime->resend_confirmation( $email );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error(
					array(
						'message' => $response->get_error_message(),
					)
				);
			}

			Activity_Log::record(
				self::ACTIVITY_MODULE,
				/* translators: %s: recipient email address. */
				sprintf( __( 'Confirmation email resent to %s.', 'wphb' ), $email )
			);

			wp_send_json_success(
				array(
					'subscribed' => false,
					'pending'    => true,
					'canResend'  => true,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Build frontend page data for Settings > Alerts & Reports.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public static function get_page_data() {
		$performance_reports = Settings::get_setting( 'reports', 'performance' );
		if ( ! is_array( $performance_reports ) ) {
			$performance_reports = array();
		}

		$uptime_reports = Settings::get_setting( 'reports', 'uptime' );
		if ( ! is_array( $uptime_reports ) ) {
			$uptime_reports = array();
		}

		$uptime_alerts = Settings::get_setting( 'notifications', 'uptime' );
		if ( ! is_array( $uptime_alerts ) ) {
			$uptime_alerts = array();
		}

		$performance_recipients = self::normalize_recipients_for_view( isset( $performance_reports['recipients'] ) ? $performance_reports['recipients'] : array() );
		$performance_all_recipients = self::normalize_recipients_for_view(
			isset( $performance_reports['all_recipients'] ) && is_array( $performance_reports['all_recipients'] )
				? $performance_reports['all_recipients']
				: ( isset( $performance_reports['recipients'] ) ? $performance_reports['recipients'] : array() )
		);

		return array(
			'isHubConnected'     => Utils::has_access_to_hub(),
			'isUptimeActive'     => Utils::get_module( 'uptime' )->is_active(),
			'uptimeUrl'          => add_query_arg( array( 'view' => 'uptime' ), Utils::get_admin_menu_url( 'advanced' ) ),
			'hubConnectUrl'      => Hub_Connector::get_connect_site_url( 'wphb-settings', 'hummingbird_alerts_reports_connect' ),
			'performanceReports' => array(
				'enabled'         => ! empty( $performance_reports['enabled'] ),
				'frequency'       => isset( $performance_reports['frequency'] ) ? (int) $performance_reports['frequency'] : 7,
				'day'             => isset( $performance_reports['day'] ) ? $performance_reports['day'] : '',
				'time'            => isset( $performance_reports['time'] ) ? sanitize_text_field( $performance_reports['time'] ) : '00:00',
				'device'          => isset( $performance_reports['type'] ) ? sanitize_key( $performance_reports['type'] ) : 'both',
				'metrics'         => ! isset( $performance_reports['metrics'] ) || (bool) $performance_reports['metrics'],
				'audits'          => ! isset( $performance_reports['audits'] ) || (bool) $performance_reports['audits'],
				'historic'        => ! isset( $performance_reports['historic'] ) || (bool) $performance_reports['historic'],
				'recipients'      => $performance_recipients,
				'allRecipients'   => $performance_all_recipients,
			),
			'uptimeReports'      => array(
				'enabled'         => ! empty( $uptime_reports['enabled'] ),
				'frequency'       => isset( $uptime_reports['frequency'] ) ? (int) $uptime_reports['frequency'] : 7,
				'day'             => isset( $uptime_reports['day'] ) ? $uptime_reports['day'] : '',
				'time'            => isset( $uptime_reports['time'] ) ? sanitize_text_field( $uptime_reports['time'] ) : '00:00',
				'showPing'        => true,
				'recipients'      => self::normalize_recipients_for_view( isset( $uptime_reports['recipients'] ) ? $uptime_reports['recipients'] : array() ),
			),
			'uptimeAlerts'       => array(
				'enabled'       => ! empty( $uptime_alerts['enabled'] ),
				'threshold'     => isset( $uptime_alerts['threshold'] ) ? (int) $uptime_alerts['threshold'] : 5,
				'recipients'    => self::normalize_recipients_for_view( isset( $uptime_alerts['recipients'] ) ? $uptime_alerts['recipients'] : array() ),
				'allRecipients' => self::normalize_recipients_for_view( self::get_master_recipients_for_view( $performance_reports, $uptime_reports, $uptime_alerts ) ),
			),
		);
	}

	/**
	 * Build the source list used by the React recipient table.
	 *
	 * @param array $performance_reports Performance report settings.
	 * @param array $uptime_reports      Uptime report settings.
	 * @param array $uptime_alerts       Uptime alert settings.
	 *
	 * @return array
	 */
	private static function get_master_recipients_for_view( $performance_reports, $uptime_reports, $uptime_alerts ) {
		if ( isset( $uptime_alerts['all_recipients'] ) && is_array( $uptime_alerts['all_recipients'] ) ) {
			return $uptime_alerts['all_recipients'];
		}

		$combined = array_merge(
			isset( $performance_reports['recipients'] ) && is_array( $performance_reports['recipients'] ) ? $performance_reports['recipients'] : array(),
			isset( $uptime_reports['recipients'] ) && is_array( $uptime_reports['recipients'] ) ? $uptime_reports['recipients'] : array(),
			isset( $uptime_alerts['recipients'] ) && is_array( $uptime_alerts['recipients'] ) ? $uptime_alerts['recipients'] : array()
		);

		return self::normalize_recipients_for_view( $combined );
	}

	/**
	 * Convert report schedule values from payload into settings.
	 *
	 * @param array $settings Existing settings array.
	 * @param array $schedule Input schedule payload.
	 */
	private function apply_schedule_payload( &$settings, $schedule ) {
		$frequency = isset( $schedule['frequency'] ) ? (int) $schedule['frequency'] : 7;
		if ( ! in_array( $frequency, array( 1, 7, 30 ), true ) ) {
			$frequency = 7;
		}

		$settings['frequency'] = $frequency;

		if ( 30 === $frequency ) {
			$settings['day'] = isset( $schedule['day'] ) ? max( 1, min( 31, (int) $schedule['day'] ) ) : 1;
		} elseif ( 7 === $frequency ) {
			$day = isset( $schedule['day'] ) ? sanitize_key( $schedule['day'] ) : 'monday';
			if ( ! in_array( $day, array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ), true ) ) {
				$day = 'monday';
			}
			$settings['day'] = $day;
		} else {
			$settings['day'] = '';
		}

		$time = isset( $schedule['time'] ) ? sanitize_text_field( $schedule['time'] ) : '00:00';
		if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
			$time = '00:00';
		}
		$settings['time'] = $time;
	}

	/**
	 * Keep report cron aligned with `enabled`.
	 *
	 * @param string $module   Module slug.
	 * @param bool   $enabled  Enabled state.
	 */
	private function sync_report_schedule( $module, $enabled ) {
		if ( ! $enabled ) {
			wp_clear_scheduled_hook( "wphb_{$module}_report" );
			return;
		}

		$next_scan_time = Reports::get_scheduled_time( $module );
		if ( $next_scan_time ) {
			wp_schedule_single_event( $next_scan_time, "wphb_{$module}_report" );
		}
	}

	/**
	 * Sync uptime notification values with The Hub.
	 *
	 * @param array $settings Notification settings.
	 */
	private function sync_uptime_alerts( $settings ) {
		if ( ! Utils::has_access_to_hub() ) {
			return;
		}

		$request_data = array(
			'notification' => array(
				'is_active'   => ! empty( $settings['enabled'] ),
				'threshold'   => isset( $settings['threshold'] ) ? (int) $settings['threshold'] : 5,
				'recipients'  => isset( $settings['recipients'] ) && is_array( $settings['recipients'] )
					? $settings['recipients']
					: array(),
			),
		);

		try {
			$response = Utils::get_api()->uptime->sync_hub( $request_data );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error(
					array(
						'message' => $response->get_error_message(),
					)
				);
			}
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Sanitize uptime threshold.
	 *
	 * @param mixed $threshold Threshold in minutes.
	 *
	 * @return int
	 */
	private function sanitize_threshold( $threshold ) {
		$value = (int) $threshold;
		if ( ! in_array( $value, array( 0, 5, 10, 30 ), true ) ) {
			$value = 5;
		}

		return $value;
	}

	/**
	 * Build recipients list from incoming payload.
	 *
	 * @param mixed $recipients User provided recipients.
	 *
	 * @return array
	 */
	private function sanitize_recipients_with_subscriptions( $recipients ) {
		if ( ! is_array( $recipients ) ) {
			$recipients = array();
		}

		$sanitized = array();
		$seen      = array();

		foreach ( $recipients as $recipient ) {
			if ( ! is_array( $recipient ) || empty( $recipient['email'] ) ) {
				continue;
			}

			$email = sanitize_email( $recipient['email'] );
			if ( ! $email ) {
				continue;
			}

			$lower_email = strtolower( $email );
			if ( in_array( $lower_email, $seen, true ) ) {
				continue;
			}

			$seen[] = $lower_email;

			$subscriptions = isset( $recipient['subscriptions'] ) && is_array( $recipient['subscriptions'] )
				? $recipient['subscriptions']
				: array();

			$is_pending = null;
			if ( array_key_exists( 'pending', $recipient ) ) {
				$is_pending = (bool) $recipient['pending'];
			} elseif ( array_key_exists( 'is_pending', $recipient ) ) {
				$is_pending = (bool) $recipient['is_pending'];
			}

			$is_subscribed = null;
			if ( array_key_exists( 'subscribed', $recipient ) ) {
				$is_subscribed = (bool) $recipient['subscribed'];
			} elseif ( array_key_exists( 'is_subscribed', $recipient ) ) {
				$is_subscribed = (bool) $recipient['is_subscribed'];
			}

			$can_resend = null;
			if ( array_key_exists( 'canResend', $recipient ) ) {
				$can_resend = (bool) $recipient['canResend'];
			} elseif ( array_key_exists( 'is_can_resend_confirmation', $recipient ) ) {
				$can_resend = (bool) $recipient['is_can_resend_confirmation'];
			}

			$sanitized_recipient = array(
				'name'  => isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : '',
				'email' => $email,
				'subscriptions' => array(
					'performance' => ! empty( $subscriptions['performance'] ),
					'uptime'      => ! empty( $subscriptions['uptime'] ),
					'alerts'      => ! empty( $subscriptions['alerts'] ),
				),
			);

			if ( null !== $is_pending ) {
				$sanitized_recipient['is_pending'] = $is_pending;
			}

			if ( null !== $is_subscribed ) {
				$sanitized_recipient['is_subscribed'] = $is_subscribed;
			}

			if ( null !== $can_resend ) {
				$sanitized_recipient['is_can_resend_confirmation'] = $can_resend;
			}

			$sanitized[] = $sanitized_recipient;
		}

		return $sanitized;
	}

	/**
	 * Keep only recipients subscribed to one feature.
	 *
	 * @param array  $recipients          Sanitized recipients with subscriptions.
	 * @param string $subscription_key    One of performance|uptime|alerts.
	 * @param array  $existing_recipients Existing recipients for metadata merge.
	 *
	 * @return array
	 */
	private function get_recipients_for_subscription( $recipients, $subscription_key, $existing_recipients ) {
		if ( ! in_array( $subscription_key, array( 'performance', 'uptime', 'alerts' ), true ) ) {
			return array();
		}

		$existing_by_email = array();
		foreach ( $existing_recipients as $recipient ) {
			if ( ! is_array( $recipient ) || empty( $recipient['email'] ) ) {
				continue;
			}

			$email = sanitize_email( $recipient['email'] );
			if ( ! $email ) {
				continue;
			}

			$existing_by_email[ strtolower( $email ) ] = $recipient;
		}

		$filtered = array();
		foreach ( $recipients as $recipient ) {
			if ( ! is_array( $recipient ) || empty( $recipient['email'] ) ) {
				continue;
			}

			if ( empty( $recipient['subscriptions'][ $subscription_key ] ) ) {
				continue;
			}

			$email = sanitize_email( $recipient['email'] );
			if ( ! $email ) {
				continue;
			}

			$payload = array(
				'name'  => isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : '',
				'email' => $email,
			);

			if ( isset( $recipient['is_pending'] ) ) {
				$payload['is_pending'] = (bool) $recipient['is_pending'];
			}

			if ( isset( $recipient['is_subscribed'] ) ) {
				$payload['is_subscribed'] = (bool) $recipient['is_subscribed'];
			}

			if ( isset( $recipient['is_can_resend_confirmation'] ) ) {
				$payload['is_can_resend_confirmation'] = (bool) $recipient['is_can_resend_confirmation'];
			}

			$email_key = strtolower( $email );
			if ( isset( $existing_by_email[ $email_key ] ) && is_array( $existing_by_email[ $email_key ] ) ) {
				$payload = wp_parse_args( $payload, $existing_by_email[ $email_key ] );
			}

			$filtered[] = $payload;
		}

		return $filtered;
	}

	/**
	 * Build a full recipient list independent of per-feature subscriptions.
	 *
	 * @param array $recipients          Sanitized recipients with subscriptions.
	 * @param array $existing_recipients Existing recipients for metadata merge.
	 *
	 * @return array
	 */
	private function get_all_recipients( $recipients, $existing_recipients ) {
		$existing_by_email = array();
		foreach ( $existing_recipients as $recipient ) {
			if ( ! is_array( $recipient ) || empty( $recipient['email'] ) ) {
				continue;
			}

			$email = sanitize_email( $recipient['email'] );
			if ( ! $email ) {
				continue;
			}

			$existing_by_email[ strtolower( $email ) ] = $recipient;
		}

		$all_recipients = array();
		foreach ( $recipients as $recipient ) {
			if ( ! is_array( $recipient ) || empty( $recipient['email'] ) ) {
				continue;
			}

			$email = sanitize_email( $recipient['email'] );
			if ( ! $email ) {
				continue;
			}

			$payload = array(
				'name'  => isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : '',
				'email' => $email,
			);

			if ( isset( $recipient['is_pending'] ) ) {
				$payload['is_pending'] = (bool) $recipient['is_pending'];
			}

			if ( isset( $recipient['is_subscribed'] ) ) {
				$payload['is_subscribed'] = (bool) $recipient['is_subscribed'];
			}

			if ( isset( $recipient['is_can_resend_confirmation'] ) ) {
				$payload['is_can_resend_confirmation'] = (bool) $recipient['is_can_resend_confirmation'];
			}

			$email_key = strtolower( $email );
			if ( isset( $existing_by_email[ $email_key ] ) && is_array( $existing_by_email[ $email_key ] ) ) {
				$payload = wp_parse_args( $payload, $existing_by_email[ $email_key ] );
			}

			$all_recipients[] = $payload;
		}

		return $all_recipients;
	}

	/**
	 * Normalize recipient records for frontend rendering.
	 *
	 * @param mixed $recipients Recipients from settings.
	 *
	 * @return array
	 */
	private static function normalize_recipients_for_view( $recipients ) {
		if ( ! is_array( $recipients ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $recipients as $recipient ) {
			if ( ! is_array( $recipient ) || empty( $recipient['email'] ) ) {
				continue;
			}

			$email = sanitize_email( $recipient['email'] );
			if ( ! $email ) {
				continue;
			}

			$normalized[] = array(
				'name'   => isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : '',
				'email'  => $email,
				'avatar' => get_avatar_url( $email ),
				'pending' => isset( $recipient['is_pending'] ) ? (bool) $recipient['is_pending'] : false,
				'subscribed' => isset( $recipient['is_subscribed'] ) ? (bool) $recipient['is_subscribed'] : true,
				'canResend' => isset( $recipient['is_can_resend_confirmation'] ) ? (bool) $recipient['is_can_resend_confirmation'] : false,
			);
		}

		return $normalized;
	}
}
