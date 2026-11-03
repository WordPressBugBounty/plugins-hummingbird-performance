<?php
/**
 * React inline warning dismissal AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UI_Warnings
 */
class UI_Warnings {

	/**
	 * Option key used to persist dismissed inline warning ids.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wphb_react_dismissed_inline_warnings';

	/**
	 * UI_Warnings constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'inline_warnings_status',
			'inline_warning_dismiss',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Return dismissed inline warning IDs.
	 *
	 * @since 4.0.0
	 */
	public function inline_warnings_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success(
			array(
				'ids' => $this->get_dismissed_warning_ids(),
			)
		);
	}

	/**
	 * Persist a dismissed inline warning ID.
	 *
	 * Expects data: { id: <string> }
	 *
	 * @since 4.0.0
	 */
	public function inline_warning_dismiss() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid warning id.', 'wphb' ) ) );
		}

		$id = sanitize_key( $data['id'] );
		if ( empty( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid warning id.', 'wphb' ) ) );
		}

		$ids = $this->get_dismissed_warning_ids();
		if ( ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}

		update_option( self::OPTION_KEY, $ids, false );

		wp_send_json_success( array( 'ids' => $ids ) );
	}

	/**
	 * Get sanitized dismissed warning IDs from DB.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_dismissed_warning_ids() {
		$ids = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$ids = array_map( 'sanitize_key', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}
}
