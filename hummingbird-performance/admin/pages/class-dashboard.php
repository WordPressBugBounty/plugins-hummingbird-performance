<?php
/**
 * Dashboard page.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;
use Hummingbird\Core\Modules\Performance;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard
 *
 * @package Hummingbird\Admin\Pages
 */
class Dashboard extends Page {

	/**
	 * Data localized for the React dashboard (`wphbPage`).
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		$minify             = Utils::get_module( 'minify' );
		$minify_options     = $minify->get_options();
		$page_cache_options = Utils::get_module( 'page_cache' )->get_options();
		$minification_url   = Utils::get_admin_menu_url( 'minification' );
		$performance_report = Settings::get_setting( 'reports', 'performance' );
		$combine_compress   = Utils::get_module( 'minify' )->is_active()
			&& ( ! empty( $minify_options['compress'] ) || ! empty( $minify_options['combine'] ) );
		$ao_available       = ! is_multisite()
			|| true === $minify_options['enabled']
			|| ( 'super-admins' === $minify_options['enabled'] && is_super_admin() );
		$ao_active          = $ao_available && Utils::get_module( 'minify' )->is_active();
		$font_swap_mode     = isset( $minify_options['font_display_value'] ) && 'optional' === $minify_options['font_display_value']
			? 'performant'
			: 'safe';

		return array(
			'dashboard' => array(
				'reportsEnabled'       => ! empty( $performance_report['enabled'] ),
				'pageCachingCanToggle' => ! is_multisite() || is_network_admin() || ( isset( $page_cache_options['enabled'] ) && 'blog-admins' === $page_cache_options['enabled'] ),
				'canClearCache'        => (bool) Utils::get_active_cache_modules(),
				'performance'          => self::get_ui_performance_data(),
				'criticalCssStatus'    => Utils::get_module( 'critical_css' )->get_react_status(),
				'fontSwapMode'         => $font_swap_mode,
				'assetOptimization'    => array(
					'available' => $ao_available,
					'active'    => $ao_active,
					'canToggle' => $ao_available,
				),
				'features'             => array(
					'pageCaching'      => Utils::get_module( 'page_cache' )->is_active(),
					'criticalCss'      => ! empty( $minify_options['critical_css'] ),
					'delayJavascript'  => ! empty( $minify_options['delay_js'] ),
					'combineCompress'  => $combine_compress,
					'fontOptimization' => ! empty( $minify_options['font_swap'] ),
					'cdn'              => Utils::get_module( 'cdn' )->is_active(),
				),
				'urls'                 => array(
					'pageCaching'        => Utils::get_admin_menu_url( 'caching' ),
					'performanceReports' => Utils::get_admin_menu_url( 'advanced' ) . '&view=alerts_reports',
					'criticalCss'        => $minification_url,
					'delayJavascript'    => $minification_url,
					'combineCompress'    => $minification_url,
					'fontOptimization'   => $minification_url,
					'cdn'                => Utils::get_admin_menu_url( 'cdn' ),
				),
			),
		);
	}

	/**
	 * Prepare the latest performance test for the React dashboard card.
	 *
	 * @return array
	 */
	public static function get_ui_performance_data() {
		$report          = Performance::get_last_report();
		$data            = $report && ! is_wp_error( $report ) && isset( $report->data ) ? $report->data : null;
		$time            = $data && ! empty( $data->time ) ? (int) $data->time : 0;
		$site            = home_url( '/' );
		$scan_started_at = Performance::is_doing_report();
		$can_run_test    = Performance::can_run_test( $report );
		$last_tested = '';
		if ( $time ) {
			$elapsed = abs( time() - $time );
			if ( $elapsed < 90 ) {
				$last_tested = __( 'Tested a minute ago', 'wphb' );
			} else {
				// translators: %s: time elapsed since the last performance test.
				$last_tested = sprintf( __( 'Tested %s ago', 'wphb' ), human_time_diff( $time, time() ) );
			}
		}
		// translators: Date format of the last performance test, e.g. "Tue, 22 Sep 1:01 PM".
		$tested_at = $time ? wp_date( __( 'D, j M g:i A', 'wphb' ), $time ) : '';

		$result      = array(
			'lastTested'      => $last_tested,
			'testedAt'        => $tested_at,
			'canRunTest'      => true === $can_run_test && ! $scan_started_at,
			'cooldownMinutes' => true === $can_run_test ? 0 : (int) $can_run_test,
			'cooldownEndsAt'  => true === $can_run_test ? null : Performance::cooldown_ends_at( $report ),
			'isRunning'       => (bool) $scan_started_at,
			'scanStartedAt'   => $scan_started_at ? (int) $scan_started_at : null,
			'devices'         => array(),
		);
		$metric_keys = array(
			'lcp' => 'largest-contentful-paint',
			'tbt' => 'total-blocking-time',
			'cls' => 'cumulative-layout-shift',
			'fcp' => 'first-contentful-paint',
			'si'  => 'speed-index',
		);

		foreach ( array( 'desktop', 'mobile' ) as $device ) {
			$device_report = $data && isset( $data->$device ) ? $data->$device : null;
			$has_report    = $device_report && isset( $device_report->score ) && is_numeric( $device_report->score ) && isset( $device_report->metrics ) && is_object( $device_report->metrics );
			$score         = $has_report ? max( 0, min( 100, (int) round( $device_report->score ) ) ) : null;
			$metrics       = array();

			foreach ( $metric_keys as $id => $key ) {
				$metric         = $has_report && isset( $device_report->metrics->$key ) ? $device_report->metrics->$key : null;
				$metrics[ $id ] = array(
					'value'  => $metric && isset( $metric->displayValue ) && '' !== $metric->displayValue ? sanitize_text_field( $metric->displayValue ) : null, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PageSpeed API property name.
					'status' => $metric && isset( $metric->score ) && is_numeric( $metric->score ) ? self::get_ui_metric_status( (float) $metric->score * 100 ) : 'neutral',
				);
			}

			$ttfb            = $has_report && isset( $device_report->field_data->EXPERIMENTAL_TIME_TO_FIRST_BYTE->percentile ) ? $device_report->field_data->EXPERIMENTAL_TIME_TO_FIRST_BYTE->percentile : null;
			$metrics['ttfb'] = array(
				'value'  => is_numeric( $ttfb ) ? number_format_i18n( (float) $ttfb / 1000, 1 ) . 's' : null,
				'status' => is_numeric( $ttfb ) ? ( 800 >= $ttfb ? 'good' : ( 1800 >= $ttfb ? 'needs-improvement' : 'poor' ) ) : 'neutral',
			);

			$result['devices'][ $device ] = array(
				'score'        => $score,
				'metrics'      => $metrics,
				'pageSpeedUrl' => add_query_arg(
					array(
						'url'         => $site,
						'form_factor' => $device,
					),
					'https://pagespeed.web.dev/analysis'
				),
			);
		}

		return $result;
	}

	/**
	 * Convert a Lighthouse metric score into a badge status.
	 *
	 * @param float $score Metric score on a 0–100 scale.
	 * @return string
	 */
	private static function get_ui_metric_status( $score ) {
		if ( 90 <= $score ) {
			return 'good';
		}

		return 50 <= $score ? 'needs-improvement' : 'poor';
	}
}
