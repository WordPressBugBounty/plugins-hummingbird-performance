<?php
/**
 * White label helper.
 *
 * Wraps the WPMU DEV Dashboard's white label settings for Hummingbird: whether white label is
 * active, which parts of the branding are hidden, and the custom plugin label an agency may have
 * configured under Dashboard > White Label > Plugin labels.
 *
 * @since 4.0.1
 * @package Hummingbird\Core
 */

namespace Hummingbird\Core;

use WPMUDEV_Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Whitelabel
 */
class Whitelabel {

	/**
	 * Hummingbird's WPMU DEV project ID, the key the Dashboard stores custom plugin labels under.
	 */
	const PLUGIN_ID = 1081721;

	/**
	 * Memoized result of is_enabled().
	 *
	 * @var bool|null
	 */
	private static $enabled = null;

	/**
	 * Whether white label is active: switched on in the Dashboard and allowed by the membership.
	 *
	 * @since 4.0.1
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( null === self::$enabled ) {
			self::$enabled = class_exists( '\WPMUDEV_Dashboard' )
				&& is_object( WPMUDEV_Dashboard::$whitelabel )
				&& method_exists( WPMUDEV_Dashboard::$whitelabel, 'can_whitelabel' )
				&& WPMUDEV_Dashboard::$whitelabel->can_whitelabel();
		}

		return self::$enabled;
	}

	/**
	 * Whether the "Branding" white label option hides WPMU DEV branding.
	 *
	 * @since 4.0.1
	 *
	 * @return bool
	 */
	public static function hide_branding() {
		return (bool) apply_filters( 'wpmudev_branding_hide_branding', false );
	}

	/**
	 * Whether the "Documentation links" white label option hides links to wpmudev.com.
	 *
	 * @since 4.0.1
	 *
	 * @return bool
	 */
	public static function hide_doc_link() {
		return (bool) apply_filters( 'wpmudev_branding_hide_doc_link', false );
	}

	/**
	 * Whether the Pro tag next to the plugin name is hidden. Hidden whenever white label is
	 * active, same as Smush; the filter is shared with Smush so one snippet covers both plugins.
	 *
	 * @since 4.0.1
	 *
	 * @return bool
	 */
	public static function hide_pro_tag() {
		return (bool) apply_filters( 'wpmudev_branding_hide_pro_tag', self::is_enabled() );
	}

	/**
	 * Hummingbird's entry from the Dashboard's "Plugin labels", or an empty array when labels are
	 * off, not configured for Hummingbird, or not applied to the current subsite.
	 *
	 * @since 4.0.1
	 *
	 * @return array
	 */
	private static function get_label_settings() {
		if ( ! self::is_enabled() || ! method_exists( WPMUDEV_Dashboard::$whitelabel, 'get_settings' ) ) {
			return array();
		}

		$settings = WPMUDEV_Dashboard::$whitelabel->get_settings();

		if ( empty( $settings['labels_enabled'] ) || empty( $settings['labels_config'][ self::PLUGIN_ID ] ) ) {
			return array();
		}

		// The Dashboard applies labels network-wide unless the network admin picked specific subsites.
		if ( is_multisite() && ! is_network_admin() && empty( $settings['labels_networkwide'] ) ) {
			$sites = isset( $settings['labels_subsites'] ) ? array_map( 'intval', (array) $settings['labels_subsites'] ) : array();

			if ( ! in_array( get_current_blog_id(), $sites, true ) ) {
				return array();
			}
		}

		return (array) $settings['labels_config'][ self::PLUGIN_ID ];
	}

	/**
	 * The custom plugin name configured in the Dashboard, or an empty string.
	 *
	 * @since 4.0.1
	 *
	 * @return string
	 */
	public static function get_plugin_name() {
		$label = self::get_label_settings();

		if ( empty( $label['name'] ) || ! is_scalar( $label['name'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $label['name'] );
	}

	/**
	 * The custom plugin icon configured in the Dashboard.
	 *
	 * @since 4.0.1
	 *
	 * @return array {
	 *     @type string|null $url   Image URL for the "upload" and "link" types, null when the agency
	 *                              chose "no icon", an empty string otherwise.
	 *     @type string      $class Dashicon class for the "dashicon" type, without the "dashicons-" prefix.
	 * }
	 */
	public static function get_plugin_icon() {
		$label = self::get_label_settings();
		$icon  = array(
			'url'   => '',
			'class' => '',
		);

		switch ( isset( $label['icon_type'] ) ? (string) $label['icon_type'] : '' ) {
			case 'none':
				$icon['url'] = null;
				break;
			case 'dashicon':
				if ( ! empty( $label['icon_class'] ) ) {
					$icon['class'] = sanitize_html_class( $label['icon_class'] );
				}
				break;
			case 'link':
				if ( ! empty( $label['icon_url'] ) ) {
					$icon['url'] = esc_url_raw( $label['icon_url'] );
				}
				break;
			case 'upload':
				if ( ! empty( $label['thumb_id'] ) ) {
					$icon['url'] = self::get_uploaded_icon_url( (int) $label['thumb_id'] );
				}
				break;
		}

		return $icon;
	}

	/**
	 * Gets the uploaded white label icon URL, resolving the attachment on the main site as the Dashboard does.
	 *
	 * @since 4.0.1
	 *
	 * @param int $attachment_id Attachment ID from the label settings.
	 *
	 * @return string Image URL, or an empty string when the attachment is gone.
	 */
	private static function get_uploaded_icon_url( $attachment_id ) {
		$switched = is_multisite() && ! is_main_site();

		if ( $switched ) {
			switch_to_blog( get_main_site_id() );
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );

		if ( $switched ) {
			restore_current_blog();
		}

		return $url ? $url : '';
	}

	/**
	 * Replace branding terms in a string while white label is active.
	 *
	 * The plugin name is only replaced when the agency configured a custom one: without a label the
	 * admin menu still says Hummingbird, so the UI copy keeps saying it too.
	 *
	 * @since 4.0.1
	 *
	 * @param string $text  Text to process.
	 * @param array  $terms Extra search => replacement pairs.
	 *
	 * @return string
	 */
	public static function replace_branding_terms( $text, $terms = array() ) {
		if ( ! self::is_enabled() ) {
			return $text;
		}

		$custom_name = self::get_plugin_name();

		if ( $custom_name ) {
			$terms = array_merge(
				array(
					'Hummingbird Pro' => $custom_name,
					'Hummingbird'     => $custom_name,
				),
				$terms
			);
		}

		/**
		 * Filter the branding terms replaced in Hummingbird's text while white label is active.
		 *
		 * @since 4.0.1
		 *
		 * @param array $terms Search => replacement pairs.
		 */
		$terms = apply_filters( 'wphb_whitelabel_replacement_terms', $terms );

		return $terms ? strtr( $text, $terms ) : $text;
	}

	/**
	 * Pick the white label variant of a string while white label is active.
	 *
	 * @since 4.0.1
	 *
	 * @param string $original_text   Default text.
	 * @param string $whitelabel_text Text without brand-specific wording.
	 *
	 * @return string
	 */
	public static function get_whitelabel_text( $original_text, $whitelabel_text ) {
		return self::is_enabled() ? $whitelabel_text : $original_text;
	}

	/**
	 * White label state localized for the React pages as `wphbPage.whiteLabel`.
	 *
	 * @since 4.0.1
	 *
	 * @return array
	 */
	public static function get_data() {
		if ( ! self::is_enabled() ) {
			return array( 'whitelabelEnabled' => false );
		}

		$icon = self::get_plugin_icon();

		return array(
			'whitelabelEnabled'     => true,
			'customPluginName'      => self::get_plugin_name(),
			// null tells the UI the agency chose "no icon".
			'customPluginIconUrl'   => $icon['url'],
			'customPluginIconClass' => $icon['class'],
			/**
			 * Filter the text shown under the plugin name in the page header instead of
			 * "by wpmudev" while white label is active. Empty by default.
			 *
			 * @since 4.0.1
			 *
			 * @param string $made_by Plain text.
			 */
			'customMadeBy'          => (string) apply_filters( 'wphb_whitelabel_custom_made_by', '' ),
			'hideBranding'          => self::hide_branding(),
			'hideDocLink'           => self::hide_doc_link(),
			'hideProTag'            => self::hide_pro_tag(),
		);
	}
}
