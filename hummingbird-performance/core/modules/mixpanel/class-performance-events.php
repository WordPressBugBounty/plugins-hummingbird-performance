<?php
/**
 * Mixpanel events for the performance test.
 *
 * @since 4.0.1
 * @package Hummingbird\Core\Modules\Mixpanel
 */

namespace Hummingbird\Core\Modules\Mixpanel;

use Hummingbird\Core\Modules\Performance;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Performance_Events.
 */
class Performance_Events extends Events {

	/**
	 * Lighthouse metrics reported per device, as Utils::get_performance_metric_for_mp() names them.
	 *
	 * @var string[]
	 */
	const METRICS = array( 'cls', 'fcp', 'inp', 'lcp', 'speed', 'tbt', 'ttfb' );

	/**
	 * Devices every metric is reported for.
	 *
	 * @var string[]
	 */
	const DEVICES = array( 'desktop', 'mobile' );

	/**
	 * Track a performance test being started.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_scan_started() {
		$report = Performance::get_last_report();

		return $this->track(
			'plugin_scan_started',
			array(
				'score_mobile_previous'  => isset( $report->data->mobile->score ) ? $report->data->mobile->score : '-',
				'score_desktop_previous' => isset( $report->data->desktop->score ) ? $report->data->desktop->score : '-',
				'AO Status'              => $this->get_ao_status(),
				'Location'               => $this->get_location(),
			)
		);
	}

	/**
	 * Track a finished performance test with its metrics, plus `performance_test_error` when it failed.
	 *
	 * @param string|int $mobile_score  Mobile score, or '-' when there is none.
	 * @param string|int $desktop_score Desktop score, or '-' when there is none.
	 * @param string     $error_message Why the test failed, if it did.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_scan_finished( $mobile_score, $desktop_score, $error_message = '' ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$properties = array(
			'score_mobile'    => $mobile_score,
			'score_desktop'   => $desktop_score,
			'active_features' => Utils::get_active_features(),
			'AO Status'       => $this->get_ao_status(),
			'Location'        => $this->get_location(),
		);

		// A test that produced no results leaves no report, or one without metrics; the metric helper needs both.
		$report  = Performance::get_last_report();
		$metrics = isset( $report->data->mobile->metrics, $report->data->desktop->metrics ) ? Utils::get_performance_metric_for_mp() : array();
		foreach ( self::METRICS as $metric ) {
			foreach ( self::DEVICES as $device ) {
				$key                = $metric . '_' . $device;
				$properties[ $key ] = isset( $metrics[ $key ] ) ? $metrics[ $key ] : 'N/A';
			}
		}

		foreach ( Performance::get_lcp_submetrics_for_mp() as $submetric => $values ) {
			foreach ( self::DEVICES as $device ) {
				$properties[ $submetric . '_' . $device ] = isset( $values[ $device ] ) ? $values[ $device ] : 'N/A';
			}
		}

		foreach ( Performance::get_audits_for_mp() as $group => $values ) {
			foreach ( self::DEVICES as $device ) {
				$properties[ 'audits_' . $group . '_' . $device ] = isset( $values[ $device ] ) ? $values[ $device ] : 'N/A';
			}
		}

		$properties['core_web_vitals_mobile']  = Performance::core_web_vitals_status( 'mobile' );
		$properties['core_web_vitals_desktop'] = Performance::core_web_vitals_status();
		$properties['autoloader_health']       = Utils::get_autoloaded_health();
		$properties['autoloader_size']         = Utils::get_autoloaded_options_size();

		$sent = $this->track( 'plugin_scan_finished', $properties );

		if ( '' !== (string) $error_message ) {
			$this->track( 'performance_test_error', array( 'Error Message' => $error_message ) );
		}

		return $sent;
	}

	/**
	 * Whether Asset Optimization is still processing files.
	 *
	 * @return string 'complete' or 'incomplete'.
	 */
	private function get_ao_status() {
		return Utils::is_ao_processing() ? 'incomplete' : 'complete';
	}
}
