<?php
/**
 * React Uptime AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uptime.
 */
class Uptime {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'uptime';

	/**
	 * Uptime constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_uptime_toggle', array( $this, 'toggle' ) );
		add_action( 'wp_ajax_wphb_react_uptime_range', array( $this, 'range' ) );
	}

	/**
	 * Enable or disable Uptime.
	 *
	 * @since 4.0.0
	 */
	public function toggle() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$raw  = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( (string) $raw ), true );
		$next = is_array( $data ) && isset( $data['enabled'] ) ? (bool) $data['enabled'] : false;

		if ( $next && ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use Uptime.', 'wphb' ) ) );
		}

		$uptime     = Utils::get_module( 'uptime' );
		$was_active = $uptime->is_active();

		if ( $next ) {
			$result = $uptime->enable();
		} else {
			$result = $uptime->disable();
		}

		// Keep the local option in sync with the remote state when the API request fails.
		if ( is_wp_error( $result ) ) {
			if ( $was_active ) {
				$uptime->enable_locally();
			} else {
				$uptime->disable_locally();
			}

			$message = $result->get_error_message();
			if ( $next && 'Invalid URL.' === $message ) {
				$message = __( 'Uptime could not be enabled because your site URL is invalid. Check your WordPress Address (URL) and try again.', 'wphb' );
			}

			wp_send_json_error(
				array(
					'message' => $message,
				)
			);
		}

		$is_active_now = $uptime->is_active();

		if ( $is_active_now !== $next ) {
			wp_send_json_error(
				array(
					'message' => __( 'Uptime could not be updated.', 'wphb' ),
				)
			);
		}

		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_active,
			$is_active_now,
			__( 'Uptime enabled.', 'wphb' ),
			__( 'Uptime disabled.', 'wphb' )
		);
		Utils::get_module( 'mixpanel_analytics' )->track_feature_toggle( 'Uptime', $was_active, $is_active_now );

		wp_send_json_success( self::get_page_data( $this->get_requested_range() ) );
	}

	/**
	 * Switch the reporting range and return the refreshed payload.
	 *
	 * @since 4.0.0
	 */
	public function range() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$range = $this->get_requested_range();

		wp_send_json_success( self::get_page_data( $range ) );
	}

	/**
	 * Requested reporting range from the AJAX payload, defaulting to `day`.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	private function get_requested_range() {
		$raw   = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data  = json_decode( html_entity_decode( (string) $raw ), true );
		$range = is_array( $data ) && isset( $data['range'] ) ? sanitize_key( $data['range'] ) : '';

		return in_array( $range, array( 'day', 'week', 'month' ), true ) ? $range : 'day';
	}

	/**
	 * Helpers.
	 */

	/**
	 * Expand a compact Uptime API duration into a readable label.
	 *
	 * The API returns downtime as shorthand such as `8m`, `45s` or `1h 5m`.
	 * Convert each part into number + spelled-out unit, e.g. `8 mins`.
	 *
	 * @since 4.0.0
	 *
	 * @param string $duration Raw duration from the Uptime API.
	 *
	 * @return string Human-readable duration.
	 */
	private static function format_duration( $duration ) {
		$duration = trim( (string) $duration );

		if ( '' === $duration ) {
			return '';
		}

		$units = array(
			'h' => array( __( 'hr', 'wphb' ), __( 'hrs', 'wphb' ) ),
			'm' => array( __( 'min', 'wphb' ), __( 'mins', 'wphb' ) ),
			's' => array( __( 'sec', 'wphb' ), __( 'secs', 'wphb' ) ),
		);

		$parts = array();
		foreach ( preg_split( '/\s+/', $duration ) as $token ) {
			// Every token must be a full `<number><unit>` pair, e.g. `8m` or `1h`.
			if ( ! preg_match( '/^(\d+(?:\.\d+)?)([hms])$/i', $token, $match ) ) {
				return $duration;
			}

			$value  = (float) $match[1];
			$unit   = strtolower( $match[2] );
			$label  = 1.0 === $value ? $units[ $unit ][0] : $units[ $unit ][1];
			$number = (string) $value;

			$parts[] = $number . ' ' . $label;
		}

		return implode( ' ', $parts );
	}

	/**
	 * Normalize the availability value from the Uptime API.
	 *
	 * The API has returned this both as a number and as a formatted string such
	 * as `99.6%`, so accept either shape instead of discarding the value.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $value Raw availability from the Uptime API.
	 *
	 * @return float|null Availability percentage, or null when it is unavailable.
	 */
	private static function parse_availability( $value ) {
		if ( null === $value || '' === $value || ! is_scalar( $value ) ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$value = rtrim( trim( $value ), '%' );
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		// A zero means "no data yet" rather than a genuine 0% availability.
		return (float) $value > 0 ? (float) $value : null;
	}

	/**
	 * Total downtime seconds recorded in the report for the requested period.
	 *
	 * @since 4.0.0
	 *
	 * @param object $report Uptime report object.
	 * @param int    $start  Period start timestamp.
	 * @param int    $end    Period end timestamp.
	 *
	 * @return int
	 */
	private static function get_recorded_downtime( $report, $start, $end ) {
		if ( ! isset( $report->events ) || ! is_array( $report->events ) ) {
			return 0;
		}

		$downtime = 0;
		foreach ( $report->events as $event ) {
			if ( empty( $event->down ) || empty( $event->up ) ) {
				continue;
			}

			$down = max( (int) $event->down, $start );
			$up   = min( (int) $event->up, $end );

			if ( $up > $down ) {
				$downtime += $up - $down;
			}
		}

		return $downtime;
	}

	/**
	 * Format an availability percentage for display.
	 *
	 * Keeps up to two decimals so a short outage does not read as a perfect
	 * score, while trimming noise such as `99.60` down to `99.6`.
	 *
	 * @since 4.0.0
	 *
	 * @param float $value Availability percentage.
	 *
	 * @return string
	 */
	private static function format_availability( $value ) {
		$value     = min( 100, max( 0, (float) $value ) );
		$formatted = number_format( $value, 2, '.', '' );

		return rtrim( rtrim( $formatted, '0' ), '.' ) . '%';
	}

	/**
	 * Build the Uptime tab payload shared by the page and the AJAX endpoints.
	 *
	 * @since 4.0.0
	 *
	 * @param string $range Reporting range.
	 *
	 * @return array
	 */
	public static function get_page_data( $range = 'day' ) {
		if ( ! in_array( $range, array( 'day', 'week', 'month' ), true ) ) {
			$range = 'day';
		}

		$uptime  = Utils::get_module( 'uptime' );
		$options = Settings::get_settings( 'uptime' );
		// Uptime is network-only on multisite.
		$base = is_multisite()
			? network_admin_url( 'admin.php?page=wphb-advanced' )
			: Utils::get_admin_menu_url( 'advanced' );

		$data = array(
			'isActive'         => $uptime->is_active(),
			'isHubConnected'   => Utils::has_access_to_hub(),
			'welcomeDismissed' => Notices::is_dismissed( 'uptime-welcome-modal' ),
			'range'            => $range,
			'alertsReportsUrl' => add_query_arg( array( 'view' => 'alerts_reports' ), $base ),
			'reportsEnabled'   => ! empty( $options['reports']['enabled'] ),
			'alertsEnabled'    => ! empty( $options['notifications']['enabled'] ),
			'stats'            => null,
			'responseTime'     => array(),
			'availability'     => array(),
			'events'           => array(),
		);

		if ( ! $data['isActive'] || ! $data['isHubConnected'] ) {
			return $data;
		}

		$report = $uptime->get_last_report( $range );
		if ( ! is_object( $report ) || is_wp_error( $report ) ) {
			return $data;
		}

		return array_merge( $data, self::build_report_data( $report, $range ) );
	}

	/**
	 * Build the display payload for an Uptime report.
	 *
	 * @since 4.0.0
	 *
	 * @param object $report Uptime report object.
	 * @param string $range  Reporting range.
	 *
	 * @return array
	 */
	public static function build_report_data( $report, $range ) {
		$now = time();

		$range_periods = array(
			'day'   => DAY_IN_SECONDS,
			'week'  => WEEK_IN_SECONDS,
			'month' => 30 * DAY_IN_SECONDS,
		);
		$period = isset( $range_periods[ $range ] ) ? $range_periods[ $range ] : DAY_IN_SECONDS;
		$start  = $now - $period;

		$outages = isset( $report->outages ) ? (int) $report->outages : 0;

		$availability = self::parse_availability( isset( $report->availability ) ? $report->availability : null );

		// A short outage can still be reported as a rounded 100%. Fall back to the
		// downtime recorded in the report so it never reads as a perfect score.
		if ( null === $availability || $availability >= 100 ) {
			$recorded_downtime = self::get_recorded_downtime( $report, $start, $now );

			if ( $recorded_downtime > 0 ) {
				$availability = max( 0, ( $period - $recorded_downtime ) / $period * 100 );
			}
		}

		$uptime_text = null === $availability ? '100%' : self::format_availability( $availability );

		$downtime_parts = array();
		if ( ! empty( $report->period_downtime ) ) {
			$downtime_parts[] = self::format_duration( $report->period_downtime );
		}
		if ( $outages > 0 ) {
			/* translators: %s: number of outages. */
			$downtime_parts[] = sprintf( _n( '%s outage', '%s outages', $outages, 'wphb' ), number_format_i18n( $outages ) );
		}

		$up_since = '';
		if ( ! empty( $report->up_since ) ) {
			$up_since = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $report->up_since ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		}

		// Response time series: [ [ datetime, milliseconds ], ... ].
		$response_time = array();
		if ( ! empty( $report->chart_json ) ) {
			$decoded = is_array( $report->chart_json ) ? $report->chart_json : json_decode( $report->chart_json, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $point ) {
					if ( ! is_array( $point ) || count( $point ) < 2 || null === $point[0] || '' === $point[0] ) {
						continue;
					}

					$response_time[] = array(
						'time'  => $point[0],
						'value' => null === $point[1] || '' === $point[1] ? null : (float) $point[1],
					);
				}
			}
		}

		$values  = array_filter( wp_list_pluck( $response_time, 'value' ), 'is_numeric' );
		$average = null;
		$maximum = null;
		$minimum = null;
		if ( ! empty( $values ) ) {
			$average = round( array_sum( $values ) / count( $values ) );
			$maximum = round( max( $values ) );
			$minimum = round( min( $values ) );
		} elseif ( isset( $report->response_time ) && is_numeric( $report->response_time ) ) {
			$average = round( (float) $report->response_time );
		}

		// Events and availability timeline.
		$events_data = array();
		$segments    = array();
		$cursor      = $start;

		$events = ( isset( $report->events ) && is_array( $report->events ) ) ? $report->events : array();
		usort(
			$events,
			function ( $a, $b ) {
				return ( isset( $a->down ) ? (int) $a->down : 0 ) <=> ( isset( $b->down ) ? (int) $b->down : 0 );
			}
		);

		foreach ( $events as $event ) {
			if ( empty( $event->down ) || empty( $event->up ) ) {
				continue;
			}

			$down = (int) $event->down;
			$up   = (int) $event->up;

			if ( $down > $cursor ) {
				$segments[] = array(
					'start'  => $cursor,
					'end'    => $down,
					'status' => 'up',
					'label'  => __( 'Website available', 'wphb' ),
				);
			}

			$duration   = ! empty( $event->downtime ) ? self::format_duration( $event->downtime ) : '';
			$segments[] = array(
				'start'  => $down,
				'end'    => $up,
				'status' => 'down',
				'label'  => $duration
					/* translators: %s: downtime duration. */
					? sprintf( __( 'Down for %s', 'wphb' ), $duration )
					: __( 'Website down', 'wphb' ),
			);

			$cursor = max( $cursor, $up );

			$events_data[] = array(
				'status'   => 'down',
				'time'     => gmdate( 'G:i', $down ) . ' UTC',
				'duration' => $duration ? $duration : '—',
				'date'     => gmdate( 'F j, Y', $down ),
				'message'  => ! empty( $event->details ) ? $event->details : __( 'Connection error', 'wphb' ),
			);
			$events_data[] = array(
				'status'   => 'up',
				'time'     => gmdate( 'G:i', $up ) . ' UTC',
				'duration' => '—',
				'date'     => gmdate( 'F j, Y', $up ),
				'message'  => __( 'Check passed', 'wphb' ),
			);
		}

		if ( $cursor < $now ) {
			$segments[] = array(
				'start'  => $cursor,
				'end'    => $now,
				'status' => 'up',
				'label'  => __( 'Website available', 'wphb' ),
			);
		}

		if ( empty( $segments ) ) {
			$segments[] = array(
				'start'  => $start,
				'end'    => $now,
				'status' => 'up',
				'label'  => __( 'Website available', 'wphb' ),
			);
		}

		return array(
			'stats'        => array(
				'status'   => ! empty( $report->is_up ) ? 'up' : 'down',
				'uptime'   => $uptime_text,
				'downtime' => ! empty( $downtime_parts ) ? implode( ', ', $downtime_parts ) : '—',
				'upSince'  => $up_since ? $up_since : '—',
				'response' => array(
					'average' => null !== $average ? $average . ' ms' : '—',
					'maximum' => null !== $maximum ? $maximum . ' ms' : '—',
					'minimum' => null !== $minimum ? $minimum . ' ms' : '—',
				),
			),
			'responseTime' => $response_time,
			'availability' => $segments,
			'events'       => $events_data,
		);
	}
}
