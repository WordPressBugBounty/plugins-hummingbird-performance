<?php
/**
 * Activity log AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Modules\Background\Thread_Safe_Options;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activity_Log.
 *
 * Stores a rolling list of "what changed" entries (e.g. settings saved from
 * the React panels) so the Activity Log side panel can show real history
 * instead of demo data.
 */
class Activity_Log {

	/**
	 * Option name the log is stored under.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wphb_activity_log';

	/**
	 * Maximum number of entries to keep.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 50;

	/**
	 * Activity_Log constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wphb_react_activity_log_fetch', array( $this, 'activity_log_fetch' ) );
		add_action( 'wp_ajax_wphb_react_activity_log_frontend', array( $this, 'activity_log_frontend' ) );
	}

	/**
	 * Fetch the stored activity log entries.
	 */
	public function activity_log_fetch() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( array( 'items' => self::get_items_for_ui() ) );
	}

	/**
	 * Fetch the stored activity log entries.
	 */
	public function activity_log_frontend() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data   = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data   = json_decode( html_entity_decode( $data ), true );
		$action = isset( $data['action'] ) ? sanitize_text_field( wp_unslash( $data['action'] ) ) : '';

		switch ( $action ) {
			case 'config_download':
				$config_name = isset( $data['config_name'] ) ? sanitize_text_field( wp_unslash( $data['config_name'] ) ) : '';

				self::record(
					'configs',
					sprintf( /* translators: %s: Config name */
						__( '%s downloaded.', 'wphb' ),
						$config_name
					)
				);
				break;
		}

		wp_send_json_success( array( 'items' => self::get_items_for_ui() ) );
	}

	/**
	 * Get activity log items for UI, displaying a welcome entry when empty.
	 *
	 * @return array
	 */
	public static function get_items_for_ui() {
		self::enforce_notification_limit();
		$items = self::get_items();

		if ( is_wp_error( $items ) ) {
			return array();
		}

		if ( $items ) {
			return $items;
		}

		// The welcome entry is display-only and is never stored.
		return array(
			array(
				'id'        => 'wphb_activity_welcome',
				'timestamp' => gmdate( 'c' ),
				'module'    => 'settings',
				'variant'   => 'default',
				'content'   => apply_filters( 'wpmudev_branding_hide_branding', false )
					? __( 'Welcome.', 'wphb' )
					: __( 'Welcome to Hummingbird', 'wphb' ),
			),
		);
	}

	/**
	 * Get the shared option storage helper.
	 *
	 * @return Thread_Safe_Options
	 */
	private static function storage() {
		static $storage;

		if ( null === $storage ) {
			$storage = new Thread_Safe_Options();
		}

		return $storage;
	}

	/**
	 * Record a new activity log entry.
	 *
	 * Called directly (not over AJAX) by other AJAX handlers right after
	 * they persist a settings change.
	 *
	 * Nothing is recorded while Safe Mode is on. A write made in that state lands in the safe
	 * copy, which is discarded or published on exit, so an entry taken here would describe a
	 * state the live site never had. Entering and leaving Safe Mode are the two real changes
	 * and are logged by `SafeMode::set_status()`, which is the only writer of the flag - so
	 * this is the single choke point the suppression belongs in, not one branch per module.
	 *
	 * `seed_welcome_item()` writes the option directly and never comes through here, so the
	 * welcome entry is unaffected.
	 *
	 * @param string $module  Short slug identifying the area, e.g. 'asset-optimization'.
	 * @param string $content Human-readable, translated description of what changed.
	 * @param string $variant Visual variant for the log entry badge.
	 *
	 * @return array|false The stored entry, or false if Safe Mode is on, the content was empty,
	 *                     or the lock timed out.
	 */
	public static function record( $module, $content, $variant = 'default' ) {
		if ( SafeMode::instance()->get_status() ) {
			return false;
		}

		$content = sanitize_text_field( $content );

		if ( '' === $content ) {
			return false;
		}

		// Bound message size without splitting UTF-8 characters.
		$content = wp_html_excerpt( $content, 1000, '…' );

		$item = array(
			'id'        => 'wphb_activity_' . wp_generate_uuid4(),
			'timestamp' => ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d\TH:i:s.u\Z' ),
			'module'    => substr( sanitize_key( $module ), 0, 64 ),
			'variant'   => substr( sanitize_key( $variant ), 0, 32 ),
			'content'   => $content,
		);

		$result = self::storage()->append_object_to_array( self::OPTION_KEY, $item );

		if ( false === $result || 0 === $result ) {
			return false;
		}

		return $item;
	}

	/**
	 * Enforce the activity limit on UI reads, following Smush's retention flow.
	 *
	 * @return void
	 */
	private static function enforce_notification_limit() {
		$items = self::get_items();

		if ( is_wp_error( $items ) || count( $items ) <= self::MAX_ITEMS ) {
			return;
		}

		usort(
			$items,
			function ( $a, $b ) {
				return $b['timestamp'] <=> $a['timestamp'];
			}
		);
		$items = array_slice( $items, 0, self::MAX_ITEMS );

		if ( false === self::storage()->unsafe_replace_object_array( self::OPTION_KEY, $items ) ) {
			do_action( 'wphb_activity_log_error', new \WP_Error( 'wphb_activity_log_trim_failed', 'Could not trim the activity log.' ) );
		}
	}

	/**
	 * Record a toggle message.
	 *
	 * @param string $module           Module slug.
	 * @param bool   $enabled          Current toggle value.
	 * @param string $enabled_message  Message when enabled.
	 * @param string $disabled_message Message when disabled.
	 *
	 * @return array|false
	 */
	public static function record_toggle( $module, $enabled, $enabled_message, $disabled_message ) {
		return self::record( $module, (bool) $enabled ? $enabled_message : $disabled_message );
	}

	/**
	 * Record a toggle message only when state changed.
	 *
	 * @param string $module           Module slug.
	 * @param bool   $previous         Previous toggle value.
	 * @param bool   $current          Current toggle value.
	 * @param string $enabled_message  Message when enabled.
	 * @param string $disabled_message Message when disabled.
	 *
	 * @return array|false
	 */
	public static function record_toggle_when_changed( $module, $previous, $current, $enabled_message, $disabled_message ) {
		if ( (bool) $previous === (bool) $current ) {
			return false;
		}

		return self::record_toggle( $module, $current, $enabled_message, $disabled_message );
	}

	/**
	 * Record a message only when values differ.
	 *
	 * @param string $module   Module slug.
	 * @param mixed  $previous Previous value.
	 * @param mixed  $current  Current value.
	 * @param string $message  Activity message.
	 *
	 * @return array|false
	 */
	public static function record_when_changed( $module, $previous, $current, $message ) {
		if ( $previous === $current ) {
			return false;
		}

		return self::record( $module, $message );
	}

	/**
	 * Get all activity log entries in stored order, without enforcing retention.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_items() {
		$items = self::storage()->get_object_array( self::OPTION_KEY );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		foreach ( $items as $item ) {
			foreach ( array( 'id', 'timestamp', 'module', 'variant', 'content' ) as $key ) {
				if ( ! isset( $item[ $key ] ) || ! is_string( $item[ $key ] ) ) {
					return new \WP_Error( 'wphb_invalid_activity', 'The activity log contains an invalid entry.' );
				}
			}

			if ( '' === $item['id'] || '' === $item['timestamp'] || '' === $item['content'] ) {
				return new \WP_Error( 'wphb_invalid_activity', 'The activity log contains an empty entry.' );
			}
		}

		return $items;
	}
}
