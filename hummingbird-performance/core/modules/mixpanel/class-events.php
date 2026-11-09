<?php
/**
 * Base class of the Mixpanel event builders.
 *
 * @since 4.0.1
 * @package Hummingbird\Core\Modules\Mixpanel
 */

namespace Hummingbird\Core\Modules\Mixpanel;

use Hummingbird\Core\Modules\Mixpanel_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Events.
 *
 * Base class of the event builders: each builder owns one area's event names and properties and records them through the module.
 */
abstract class Events {

	/**
	 * Gate and transport.
	 *
	 * @var Mixpanel_Analytics
	 */
	protected $analytics;

	/**
	 * Events constructor.
	 *
	 * @param Mixpanel_Analytics $analytics Gate and transport.
	 */
	public function __construct( Mixpanel_Analytics $analytics ) {
		$this->analytics = $analytics;
	}

	/**
	 * Whether tracking is on; lets a builder skip building expensive properties.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return $this->analytics->is_active();
	}

	/**
	 * Record an event, subject to the usage-tracking opt-in.
	 *
	 * @param string $event      Event name.
	 * @param array  $properties Event properties.
	 *
	 * @return bool Whether the event was sent.
	 */
	protected function track( $event, array $properties = array() ) {
		return $this->analytics->track( $event, $properties );
	}

	/**
	 * Where the current request was made from, as the `Location` property.
	 *
	 * @return string
	 */
	protected function get_location() {
		return $this->analytics->get_location();
	}

	/**
	 * Record a feature's event, plus `plugin_feature_activate` / `plugin_feature_deactivate` when it was switched on or off.
	 *
	 * @param string $feature    Feature name as Mixpanel knows it.
	 * @param string $event      Event name.
	 * @param array  $properties Event properties, including `update_type`.
	 *
	 * @return bool Whether the event was sent.
	 */
	protected function track_feature_event( $feature, $event, array $properties ) {
		$update_type = isset( $properties['update_type'] ) ? $properties['update_type'] : '';

		if ( 'activate' === $update_type || 'deactivate' === $update_type ) {
			$this->analytics->track_feature_toggle( $feature, 'deactivate' === $update_type, 'activate' === $update_type );
		}

		return $this->track( $event, $properties );
	}

	/**
	 * Derive a feature's update type from an option diff.
	 *
	 * @param array    $previous      Options before the save.
	 * @param array    $current       Options after the save.
	 * @param string   $toggle_key    Option that switches the feature on/off.
	 * @param string[] $modifier_keys Options whose change counts as "modified" while the feature is on.
	 *
	 * @return string 'activate', 'deactivate', 'modified' or '' when nothing relevant changed.
	 */
	protected function get_update_type( array $previous, array $current, $toggle_key, array $modifier_keys ) {
		$was_enabled = ! empty( $previous[ $toggle_key ] );
		$is_enabled  = ! empty( $current[ $toggle_key ] );

		if ( $was_enabled !== $is_enabled ) {
			return $is_enabled ? 'activate' : 'deactivate';
		}

		if ( $is_enabled && $this->any_option_changed( $previous, $current, $modifier_keys ) ) {
			return 'modified';
		}

		return '';
	}

	/**
	 * Whether any of the given options differs between two snapshots.
	 *
	 * @param array    $previous Options before the save.
	 * @param array    $current  Options after the save.
	 * @param string[] $keys     Option keys to compare.
	 *
	 * @return bool
	 */
	protected function any_option_changed( array $previous, array $current, array $keys ) {
		foreach ( $keys as $key ) {
			if ( $this->option_changed( $previous, $current, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an option differs between two snapshots, ignoring list order and number formatting.
	 *
	 * @param array  $previous Options before the save.
	 * @param array  $current  Options after the save.
	 * @param string $key      Option key.
	 *
	 * @return bool
	 */
	protected function option_changed( array $previous, array $current, $key ) {
		$old = isset( $previous[ $key ] ) ? $previous[ $key ] : null;
		$new = isset( $current[ $key ] ) ? $current[ $key ] : null;

		return $this->normalize_option_value( $old ) !== $this->normalize_option_value( $new );
	}

	/**
	 * Normalize an option value for comparison.
	 *
	 * @param mixed $value Option value.
	 *
	 * @return mixed
	 */
	protected function normalize_option_value( $value ) {
		if ( is_array( $value ) ) {
			$value = array_map( array( $this, 'normalize_option_value' ), $value );

			if ( empty( $value ) ) {
				return array();
			}

			if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
				sort( $value );
				return $value;
			}

			ksort( $value );
			return $value;
		}

		if ( is_bool( $value ) || null === $value ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (string) ( $value + 0 );
		}

		return is_string( $value ) ? trim( $value ) : $value;
	}

	/**
	 * Turn a stored rule (list or multi-line text) into a flat list without empty entries.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return string[]
	 */
	protected function to_property_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$list = array();
		foreach ( $value as $entry ) {
			if ( is_scalar( $entry ) ) {
				$entry = trim( (string) $entry );
				if ( '' !== $entry ) {
					$list[] = $entry;
				}
			}
		}

		return $list;
	}

	/**
	 * Comma-separated setting names for the `modified_settings` property, or 'na' when empty.
	 *
	 * @param string[] $names Setting names.
	 *
	 * @return string
	 */
	protected function to_setting_list( array $names ) {
		return empty( $names ) ? 'na' : implode( ',', $names );
	}
}
