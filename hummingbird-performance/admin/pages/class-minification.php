<?php
/**
 * Asset optimization admin pages.
 *
 * @package Hummingbird\Admin\Pages
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Ajax\Notices as React_Notices;
use Hummingbird\Admin\Page;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Minification extends Page
 */
class Minification extends Page {

	/**
	 * Data localized for the new React Asset Optimization page (`wphbPage`).
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		$notices = $this->get_asset_optimization_notices();

		return array(
			'minify'                => Utils::get_module( 'minify' )->data_frontend(),
			'view'                  => Settings::get_setting( 'view', 'minify' ),
			'notices'               => $notices,
			'settingsOthersUrl'     => add_query_arg( 'view', 'other', Utils::get_admin_menu_url( 'settings' ) ),
			'gzip'                  => Utils::get_module( 'gzip' )->get_react_settings(),
			'aoDocLink'             => Utils::get_documentation_url( 'wphb-minification' ),
			'gzipConfigure'         => Utils::get_module( 'gzip' )->get_configure_react_settings(),
			'delayJsExclusions'     => Utils::get_module( 'exclusions' )->get_delay_js_react_options(),
			'criticalCssExclusions' => Utils::get_module( 'exclusions' )->get_critical_css_react_options(),
		);
	}

	/**
	 * Add HTTP/2 notice for the new UI notices payload.
	 *
	 * @since 2.6.0
	 *
	 * @param array $notices Existing notices.
	 *
	 * @return array
	 */
	public function render_http2_notice( $notices ) {
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		if ( Utils::is_whitelabel_enabled() ) {
			return $notices;
		}

		if ( ! $this->admin_notices->can_show_notice( 'http2-info' ) ) {
			return $notices;
		}

		if ( Utils::get_module( 'minify' )->scanner->is_scanning() ) {
			return $notices;
		}

		if ( isset( $_SERVER['WPMUDEV_HOSTED'] ) ) {
			$notices['http3_quic_optimization'] = array(
				'title'       => __( 'HTTP/3 Optimization Active', 'wphb' ),
				'description' => __( 'Your server is running the HTTP/3 (with QUIC) protocol which automatically optimizes the delivery of your assets for you. You can still combine, and move your files, though this may not always improve performance.', 'wphb' ),
			);

			return $notices;
		}

		$notices['http3_hosting_upsell'] = array(
			'title'       => __( 'Boost Asset Delivery with HTTP/3', 'wphb' ),
			'description' => __( 'Did you know WPMU DEV Hosting runs the HTTP/3 (with QUIC) protocol, which automatically optimizes the delivery of your assets for you? Improve your site speed and performance by hosting your site with WPMU DEV.', 'wphb' ),
		);

		return $notices;
	}

	/**
	 * Resolve Asset Optimization notices for React toast rendering.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_asset_optimization_notices() {
		add_filter( 'wphb_asset_optimization_notices', array( $this, 'render_http2_notice' ) );

		$notices = apply_filters( 'wphb_asset_optimization_notices', array() );

		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$normalized = array();

		foreach ( $notices as $key => $notice ) {
			$notice_key = sanitize_key( (string) $key );
			if ( '' === $notice_key || React_Notices::is_dismissed( $notice_key ) ) {
				continue;
			}

			if ( is_string( $notice ) ) {
				$title       = '';
				$description = trim( $notice );
			} elseif ( is_array( $notice ) ) {
				$title       = isset( $notice['title'] ) ? trim( (string) $notice['title'] ) : '';
				$description = isset( $notice['description'] ) ? trim( (string) $notice['description'] ) : '';
			} else {
				continue;
			}

			if ( '' === $description ) {
				continue;
			}

			$normalized[ $notice_key ] = array(
				'title'       => $title,
				'description' => $description,
			);
		}

		return $normalized;
	}
}
