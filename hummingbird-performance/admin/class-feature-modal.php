<?php
/**
 * Release ("welcome to X.0") modal state.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin
 */

namespace Hummingbird\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Feature_Modal.
 */
class Feature_Modal {

	/**
	 * Site option armed by the upgrade routine..
	 */
	const FEATURE_SLUG = 'wphb_show_upgrade_summary';

	/**
	 * Release whose copy the modal renders.
	 */
	const FEATURE_VERSION = '4.0.0';

	/**
	 * Whether the welcome modal should be shown on this request.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	public static function should_display() {
		if ( ! get_site_option( self::FEATURE_SLUG ) ) {
			return false;
		}

		return ! apply_filters( 'wpmudev_branding_hide_doc_link', false );
	}

	/**
	 * Data localized for the welcome modal on every React page.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public static function get_modal_data() {
		return array(
			'show'    => self::should_display(),
			'version' => self::FEATURE_VERSION,
		);
	}

	/**
	 * Forget the modal — dismissed by the user, or reset along with settings.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public static function delete_modal_key() {
		delete_site_option( self::FEATURE_SLUG );
	}
}
