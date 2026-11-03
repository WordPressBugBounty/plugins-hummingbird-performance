<?php
/**
 * Advanced tools admin page.
 *
 * @since 1.8
 * @package Hummingbird
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;
use Hummingbird\Core\Modules\Advanced as Advanced_Module;
use Hummingbird\Core\Settings as Settings_Module;
use Hummingbird\Core\Modules\Caching\Preload;
use Hummingbird\Core\Modules\Minify\Minify_Group;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Hub_Connector;
use Hummingbird\Admin\Ajax\Notices;
use Hummingbird\Admin\Ajax\Alerts_Reports;
use Hummingbird\Admin\Ajax\Uptime as Ajax_Uptime;
use Hummingbird\Admin\Ajax\Advanced\Tools as Ajax_Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Advanced_Tools
 *
 * @package Hummingbird\Admin\Pages
 */
class Advanced extends Page {

	use \Hummingbird\Core\Traits\Smush;

	/**
	 * Extra data localized for the Settings page's React shell.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		$advanced   = Settings_Module::get_settings( 'advanced' );
		$lazy       = isset( $advanced['lazy_load'] ) && is_array( $advanced['lazy_load'] ) ? $advanced['lazy_load'] : array();
		$woo_active = function_exists( 'WC' ) || class_exists( 'WooCommerce' );

		$db_schedule = Settings_Module::get_setting( 'reports', 'database' );
		if ( ! empty( $db_schedule['recipients'] ) && is_array( $db_schedule['recipients'] ) ) {
			foreach ( $db_schedule['recipients'] as $index => $db_recipient ) {
				if ( empty( $db_recipient['email'] ) ) {
					continue;
				}

				$db_schedule['recipients'][ $index ]['avatar'] = get_avatar_url( $db_recipient['email'] );
			}
		}

		return array(
			'settingsTools' => array(
				'viewportMeta'       => ! empty( $advanced['viewport_meta'] ),
				'queryStrings'       => ! empty( $advanced['query_string'] ),
				'queryStringsLocked' => apply_filters( 'wphb_query_strings_disabled', false ),
				'removeEmoji'        => ! empty( $advanced['emoji'] ),
				'emojiLocked'        => apply_filters( 'wphb_emojis_disabled', false ),
				'postRevisions'      => isset( $advanced['post_revisions'] ) ? (string) $advanced['post_revisions'] : '',
				'cartFragments'      => isset( $advanced['cart_fragments'] ) ? $advanced['cart_fragments'] : false,
				'wooActive'          => $woo_active,
				'wooLink'            => self_admin_url( 'admin.php?page=wc-settings&tab=products' ),
				'prefetch'           => isset( $advanced['prefetch'] ) && is_array( $advanced['prefetch'] ) ? array_values( $advanced['prefetch'] ) : array(),
				'preconnect'         => isset( $advanced['preconnect'] ) && is_array( $advanced['preconnect'] ) ? array_values( $advanced['preconnect'] ) : array(),
				'lazyLoad'           => array(
					'enabled'   => ! empty( $lazy['enabled'] ),
					'method'    => isset( $lazy['method'] ) ? $lazy['method'] : 'click',
					'button'    => isset( $lazy['button'] ) && is_array( $lazy['button'] ) ? $lazy['button'] : array(),
					'preload'   => ! empty( $lazy['preload'] ),
					'threshold' => isset( $lazy['threshold'] ) ? (int) $lazy['threshold'] : 0,
				),
				'commentLimit'       => (int) get_option( 'comments_per_page' ),
				'gravatarUrl'        => admin_url( 'admin.php?page=wphb-caching&view=others' ),
				'rules'              => Ajax_Tools::get_rules(),
			),
			'databaseCleanup'        => array(
				'counts'          => Advanced_Module::get_db_count(),
				'schedule'        => $db_schedule,
				'isHubConnected'  => Utils::has_access_to_hub(),
				'hubConnectUrl'   => Hub_Connector::get_connect_site_url( 'wphb-settings', 'hummingbird_db_cleanup_connect' ),
				'noticeDismissed' => Notices::is_dismissed( 'database-cleanup-info' ),
			),
			'alertsReports'   => Alerts_Reports::get_page_data(),
			'uptime'          => Ajax_Uptime::get_page_data( $this->get_uptime_data_range() ),
		);
	}

	/**
	 * Sanitized reporting range for the Uptime tab.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	private function get_uptime_data_range() {
		$range = filter_input( INPUT_GET, 'data-range', FILTER_UNSAFE_RAW );
		$range = sanitize_key( $range );

		return in_array( $range, array( 'day', 'week', 'month' ), true ) ? $range : 'day';
	}

}
