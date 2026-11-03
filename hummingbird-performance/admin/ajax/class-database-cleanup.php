<?php
/**
 * React Database Cleanup AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Modules\Advanced;
use Hummingbird\Core\Pro\Modules\Reports;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Database_Cleanup.
 */
class Database_Cleanup {

	/**
	 * Database_Cleanup constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'db_cleanup_delete',
			'db_cleanup_schedule_save',
			'db_cleanup_schedule_disable',
			'db_cleanup_search_users',
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Register callbacks.
			 *
			 * @uses db_cleanup_delete()
			 * @uses db_cleanup_schedule_save()
			 * @uses db_cleanup_schedule_disable()
			 * @uses db_cleanup_search_users()
			 */
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	/**
	 * Delete one (or all) database cleanup data types.
	 *
	 * @since 4.0.0
	 */
	public function db_cleanup_delete() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		$type         = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : '';
		$valid_types  = array_merge( array_keys( Advanced::get_db_fields() ), array( 'all' ) );

		if ( ! in_array( $type, $valid_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cleanup type.', 'wphb' ) ) );
		}

		$result = Utils::get_module( 'advanced' )->delete_db_data( $type );

		wp_send_json_success(
			array(
				'type'  => $type,
				'items' => $result['items'],
				'left'  => $result['left'],
			)
		);
	}

	/**
	 * Save the scheduled cleanup settings (frequency/day/time/tables/recipients).
	 *
	 * @since 4.0.0
	 */
	public function db_cleanup_schedule_save() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use this feature.', 'wphb' ) ) );
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		$settings = Settings::get_setting( 'reports', 'database' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$frequency = isset( $data['frequency'] ) ? (int) $data['frequency'] : 7;
		if ( ! in_array( $frequency, array( 1, 7, 30 ), true ) ) {
			$frequency = 7;
		}

		$settings['enabled']   = ! empty( $data['enabled'] );
		$settings['frequency'] = $frequency;

		if ( 30 === $frequency ) {
			$settings['day'] = isset( $data['day'] ) ? (int) $data['day'] : 1;
		} elseif ( 7 === $frequency ) {
			$settings['day'] = isset( $data['day'] ) ? sanitize_text_field( $data['day'] ) : '';
		} else {
			$settings['day'] = '';
		}

		$time = isset( $data['time'] ) ? sanitize_text_field( $data['time'] ) : '';
		if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
			$time = '00:00';
		}

		// Unlike performance/uptime reports, database cleanup never calls an
		// external API (see Reports_Database::process_report() - it's a plain
		// wp-cron + local delete + wp_mail()), so there's no "don't spam the
		// API" reason to randomize away from the time the user picked.
		$settings['time'] = $time;

		$fields             = array_keys( Advanced::get_db_fields() );
		$tables             = isset( $data['tables'] ) && is_array( $data['tables'] ) ? $data['tables'] : array();
		$settings['tables'] = array();
		foreach ( $fields as $field ) {
			$settings['tables'][ $field ] = ! empty( $tables[ $field ] );
		}

		$recipients             = isset( $data['recipients'] ) && is_array( $data['recipients'] ) ? $data['recipients'] : array();
		$settings['recipients'] = array();
		foreach ( $recipients as $recipient ) {
			if ( empty( $recipient['email'] ) ) {
				continue;
			}
			$settings['recipients'][] = array(
				'name'  => isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : '',
				'email' => sanitize_email( $recipient['email'] ),
			);
		}

		$settings['last_sent'] = '';

		Settings::update_setting( 'reports', $settings, 'database' );

		if ( $settings['enabled'] ) {
			$next_event = Reports::get_scheduled_time( 'database' );
			if ( $next_event ) {
				wp_schedule_single_event( $next_event, 'wphb_database_report' );
			}
		} else {
			wp_clear_scheduled_hook( 'wphb_database_report' );
		}

		wp_send_json_success( array( 'settings' => $this->with_avatars( $settings ) ) );
	}

	/**
	 * Disable scheduled cleanup reports.
	 *
	 * @since 4.0.0
	 */
	public function db_cleanup_schedule_disable() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use this feature.', 'wphb' ) ) );
		}

		$settings = Settings::get_setting( 'reports', 'database' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['enabled'] = false;

		wp_clear_scheduled_hook( 'wphb_database_report' );

		Settings::update_setting( 'reports', $settings, 'database' );

		wp_send_json_success( array( 'settings' => $this->with_avatars( $settings ) ) );
	}

	/**
	 * Add a gravatar URL to each recipient, for display only.
	 *
	 * @since 4.0.0
	 *
	 * @param array $settings  Report settings.
	 *
	 * @return array
	 */
	private function with_avatars( $settings ) {
		if ( empty( $settings['recipients'] ) || ! is_array( $settings['recipients'] ) ) {
			return $settings;
		}

		foreach ( $settings['recipients'] as $index => $recipient ) {
			if ( empty( $recipient['email'] ) ) {
				continue;
			}

			$settings['recipients'][ $index ]['avatar'] = get_avatar_url( $recipient['email'] );
		}

		return $settings;
	}

	/**
	 * Search WordPress users for the recipient picker.
	 *
	 * @since 4.0.0
	 */
	public function db_cleanup_search_users() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to use this feature.', 'wphb' ) ) );
		}

		$data  = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data  = json_decode( html_entity_decode( $data ), true );
		$query = isset( $data['query'] ) ? sanitize_text_field( $data['query'] ) : '';

		$users = Utils::pro()->module( 'notifications' )->get_users( "*$query*", array() );

		wp_send_json_success( array( 'users' => $users ) );
	}

}
