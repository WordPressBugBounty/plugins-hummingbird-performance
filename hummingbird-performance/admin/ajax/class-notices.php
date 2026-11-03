<?php
/**
 * React notices AJAX actions.
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
 * Class Notices.
 */
class Notices {

	/**
	 * Single option holding every dismissed notice ID, instead of one
	 * autoloaded row per notice — keeps the options table (and the
	 * autoloaded-options payload loaded on every admin request) flat
	 * regardless of how many notices this ever grows to.
	 *
	 * @since 4.0.0
	 */
	const OPTION_NAME = 'wphb-dismissed-notices';

	/**
	 * User meta key holding dismissed user-specific notice IDs.
	 *
	 * @since 4.0.0
	 */
	const USER_META_NAME = 'wphb-dismissed-notices';

	/**
	 * Notices constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_notice_dismiss', array( $this, 'dismiss' ) );
	}

	/**
	 * Check if a React notice has been dismissed.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id  Notice ID.
	 *
	 * @return bool
	 */
	public static function is_dismissed( $id ) {
		if ( self::is_user_dismissed( $id ) ) {
			return true;
		}

		return in_array( $id, get_option( self::OPTION_NAME, array() ), true );
	}

	/**
	 * Check if a user-specific React notice has been dismissed.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Notice ID.
	 *
	 * @return bool
	 */
	public static function is_user_dismissed( $id ) {
		$dismissed = get_user_meta( get_current_user_id(), self::USER_META_NAME, true );

		return is_array( $dismissed ) && in_array( $id, $dismissed, true );
	}

	/**
	 * Mark a React notice as dismissed.
	 *
	 * Shared by the AJAX handler and by code migrating a legacy (pre-React)
	 * dismissal into this store, so there's a single write path.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id     Notice ID.
	 * @param string $scope  'user' to dismiss for the current user only, otherwise site-wide.
	 */
	public static function mark_dismissed( $id, $scope = 'site' ) {
		if ( 'user' === $scope ) {
			$dismissed = get_user_meta( get_current_user_id(), self::USER_META_NAME, true );
			$dismissed = is_array( $dismissed ) ? $dismissed : array();

			if ( ! in_array( $id, $dismissed, true ) ) {
				$dismissed[] = $id;
				update_user_meta( get_current_user_id(), self::USER_META_NAME, $dismissed );
			}

			return;
		}

		$dismissed = get_option( self::OPTION_NAME, array() );

		if ( ! in_array( $id, $dismissed, true ) ) {
			$dismissed[] = $id;
			// Not needed outside the AO screens that check it — keep it
			// out of the autoloaded options payload.
			update_option( self::OPTION_NAME, $dismissed, false );
		}
	}

	/**
	 * Dismiss a React notice.
	 *
	 * @since 4.0.0
	 */
	public function dismiss() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data       = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data       = json_decode( html_entity_decode( $data ), true );
		$id         = isset( $data['id'] ) ? sanitize_key( $data['id'] ) : '';
		$scope      = isset( $data['scope'] ) && 'user' === $data['scope'] ? 'user' : 'site';
		$sub_action = isset( $data['subAction'] ) ? sanitize_key( $data['subAction'] ) : '';

		if ( ! $id ) {
			wp_send_json_error();
		}

		// 'cache-global-cleared' and 'free-rated' aren't permanent dismissals.
		if ( 'cache-global-cleared' === $id ) {
			delete_option( 'wphb-notice-cache-global-cleared-show' );
		} elseif ( 'free-rated' === $id ) {
			if ( 'remind_later' === $sub_action ) {
				update_site_option( 'wphb-notice-free-rated-later_date', time() + ( 7 * DAY_IN_SECONDS ) );
			} else {
				update_site_option( 'wphb-notice-free-rated-later_date', 'never' );
			}
			delete_option( 'wphb-notice-free-rated-show' );
		} else {
			self::mark_dismissed( $id, $scope );
		}

		wp_send_json_success( array( 'id' => $id ) );
	}
}
