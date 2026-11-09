<?php
/**
 * Mixpanel events for the Asset Optimization area.
 *
 * @since 4.0.1
 * @package Hummingbird\Core\Modules\Mixpanel
 */

namespace Hummingbird\Core\Modules\Mixpanel;

use Hummingbird\Core\Modules\Minify;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Asset_Optimization_Events.
 *
 * Events for Asset Optimization, Delay JS, Critical CSS and font optimization.
 */
class Asset_Optimization_Events extends Events {

	/**
	 * Option keys whose change counts as a "modified" Delay JS update while the feature stays enabled.
	 *
	 * @var string[]
	 */
	const DELAY_JS_MODIFIER_KEYS = array(
		'delay_js_timeout',
		'delay_js_exclude_inline_js',
		'delay_js_exclusions',
		'delay_js_files_exclusion',
		'delay_js_post_types_exclusion',
		'delay_js_post_urls_exclusion',
		'delay_js_plugins_themes_exclusion',
		'delay_js_ads_tracker_exclusion',
	);

	/**
	 * Option keys whose change counts as a "modified" Critical CSS update while the feature stays enabled.
	 *
	 * @var string[]
	 */
	const CRITICAL_CSS_MODIFIER_KEYS = array(
		'critical_css_type',
		'critical_css_remove_type',
		'above_fold_load_stylesheet_method',
	);

	/**
	 * Option keys that make up the Critical CSS exclusion rules.
	 *
	 * @var string[]
	 */
	const CRITICAL_CSS_EXCLUSION_KEYS = array(
		'critical_css_files_exclusion',
		'critical_css_post_urls_exclusion',
		'critical_css_plugins_themes_exclusion',
		'critical_css_keywords',
		'critical_page_types',
		'critical_skipped_custom_post_types',
	);

	/**
	 * Track the module being switched on or off.
	 *
	 * @param bool $was_enabled Module state before the change.
	 * @param bool $is_enabled  Module state after the change.
	 *
	 * @return void
	 */
	public function track_module_status_change( $was_enabled, $is_enabled ) {
		if ( (bool) $was_enabled === (bool) $is_enabled ) {
			return;
		}

		$this->analytics->track_feature_toggle( 'Asset Optimization', $was_enabled, $is_enabled );

		if ( $is_enabled ) {
			$this->track_ao_updated();
		}
	}

	/**
	 * Track a change of the automatic optimization mode (compress/combine).
	 *
	 * @param array $previous Minify options before the save.
	 * @param array $current  Minify options after the save.
	 *
	 * @return void
	 */
	public function track_mode_change( array $previous, array $current ) {
		if ( ! $this->any_option_changed( $previous, $current, array( 'compress', 'combine' ) ) ) {
			return;
		}

		if ( empty( $current['compress'] ) && empty( $current['combine'] ) ) {
			return;
		}

		$this->track_ao_updated();
	}

	/**
	 * Track the `ao_updated` event with the current mode and asset stats.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_ao_updated() {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$stats = Utils::get_ao_stats_data();

		return $this->track(
			'ao_updated',
			array(
				'Mode'                => Utils::get_minification_mode(),
				'assets_found'        => isset( $stats['enqueued_files'] ) ? (int) $stats['enqueued_files'] : 0,
				'total_files'         => (int) Utils::minified_files_count(),
				'filesize_reductions' => isset( $stats['compressed_size'] ) ? absint( $stats['compressed_size'] ) : 0,
				'location'            => $this->get_location(),
			)
		);
	}

	/**
	 * Track a Delay JS change, deriving the update type from the option diff.
	 *
	 * @param array $previous Minify options before the save.
	 * @param array $current  Minify options after the save.
	 *
	 * @return void
	 */
	public function track_delay_js_change( array $previous, array $current ) {
		$update_type = $this->get_update_type( $previous, $current, 'delay_js', self::DELAY_JS_MODIFIER_KEYS );

		if ( '' !== $update_type ) {
			$this->track_delay_js_event( $update_type );
		}
	}

	/**
	 * Track the `js_delay_updated` event from the stored options.
	 *
	 * @param string $update_type 'activate', 'deactivate' or 'modified'.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_delay_js_event( $update_type ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$options  = Utils::get_module( 'minify' )->get_options();
		$keywords = $this->to_property_list( isset( $options['delay_js_exclusions'] ) ? $options['delay_js_exclusions'] : array() );

		$properties = array_merge(
			array(
				'update_type'    => $update_type,
				'Location'       => $this->get_location(),
				'Timeout'        => isset( $options['delay_js_timeout'] ) ? $options['delay_js_timeout'] : '',
				'Excluded Files' => empty( $keywords ) ? 'no' : 'yes',
			),
			$this->get_delay_js_exclusion_properties( $options )
		);

		return $this->track_feature_event( 'JS Delay', 'js_delay_updated', $properties );
	}

	/**
	 * Track a Critical CSS change, deriving the update type from the diff.
	 *
	 * @param array       $previous     Minify options before the save.
	 * @param array       $current      Minify options after the save.
	 * @param string|null $previous_css Manually included CSS before the save, when it was part of it.
	 * @param string|null $current_css  Manually included CSS after the save, when it was part of it.
	 *
	 * @return void
	 */
	public function track_critical_css_change( array $previous, array $current, $previous_css = null, $current_css = null ) {
		$was_enabled = ! empty( $previous['critical_css'] );
		$is_enabled  = ! empty( $current['critical_css'] );

		if ( $was_enabled !== $is_enabled ) {
			$this->track_critical_css_event( $is_enabled ? 'activate' : 'deactivate' );
			return;
		}

		if ( ! $is_enabled ) {
			return;
		}

		$modified = array();
		$default  = array();

		if ( $this->any_option_changed( $previous, $current, self::CRITICAL_CSS_EXCLUSION_KEYS ) ) {
			$modified[] = 'exclusions';
		} else {
			$default[] = 'exclusions';
		}

		if ( null !== $previous_css && null !== $current_css && trim( (string) $previous_css ) !== trim( (string) $current_css ) ) {
			$modified[] = 'inclusions';
		} else {
			$default[] = 'inclusions';
		}

		if ( empty( $modified ) && ! $this->any_option_changed( $previous, $current, self::CRITICAL_CSS_MODIFIER_KEYS ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( null === $current_css ) {
			$current_css = Minify::get_css( 'manual-critical' );
		}

		$this->track_critical_css_event(
			'modified',
			implode( ',', $modified ),
			implode( ',', $default ),
			'' === trim( (string) $current_css ) ? 'inactive' : 'active'
		);
	}

	/**
	 * Track the `critical_css_updated` event from the stored options.
	 *
	 * @param string $update_type       'activate', 'deactivate' or 'modified'.
	 * @param string $settings_modified Comma-separated list of modified setting groups.
	 * @param string $settings_default  Comma-separated list of untouched setting groups.
	 * @param string $manual_inclusions 'active', 'inactive' or 'na'.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_critical_css_event( $update_type, $settings_modified = '', $settings_default = '', $manual_inclusions = 'na' ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$options = Utils::get_module( 'minify' )->get_options();

		$properties = array_merge(
			array(
				'update_type'       => $update_type,
				'Location'          => $this->get_location(),
				'mode'              => Utils::get_module( 'critical_css' )->get_critical_mode_for_mp(),
				'settings_modified' => $settings_modified,
				'settings_default'  => $settings_default,
				'manual_inclusions' => $manual_inclusions,
			),
			$this->get_critical_css_exclusion_properties( $options )
		);

		return $this->track_feature_event( 'Critical Css', 'critical_css_updated', $properties );
	}

	/**
	 * Track a purge of the generated Critical CSS.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_critical_css_cache_purge() {
		return $this->track( 'critical_css_cache_purge', array( 'location' => $this->get_location() ) );
	}

	/**
	 * Track a failed Critical CSS generation.
	 *
	 * @param string $error_code    API error code, if any.
	 * @param string $error_message API error message.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_critical_css_error( $error_code, $error_message ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$error_code    = (string) $error_code;
		$error_message = (string) $error_message;

		// A failed API request stores its message as the code; only the API's own codes (http, css, timeout) are a type.
		if ( $error_code === $error_message ) {
			$error_code = '';
		}

		return $this->track(
			'critical_css_error',
			array(
				'mode'          => Utils::get_module( 'critical_css' )->get_critical_mode_for_mp(),
				'Error Type'    => '' !== $error_code ? $error_code : 'unknown',
				'Error Message' => mb_strlen( $error_message ) > 256 ? mb_substr( $error_message, 0, 256 ) . '...' : $error_message,
			)
		);
	}

	/**
	 * Track font preloading / font swapping being switched on or off.
	 *
	 * @param array $previous Minify options before the save.
	 * @param array $current  Minify options after the save.
	 *
	 * @return void
	 */
	public function track_font_optimization_change( array $previous, array $current ) {
		$features = array(
			'font_optimization' => 'font_preload',
			'font_swap'         => 'font_swapping',
		);

		foreach ( $features as $key => $feature ) {
			$this->analytics->track_feature_toggle( $feature, ! empty( $previous[ $key ] ), ! empty( $current[ $key ] ) );
		}
	}

	/**
	 * Delay JS exclusion rules in the property shape Mixpanel expects.
	 *
	 * @param array $options Minify options.
	 *
	 * @return array
	 */
	private function get_delay_js_exclusion_properties( array $options ) {
		return $this->get_exclusion_properties(
			$options,
			array(
				'exclusions_files'     => 'delay_js_files_exclusion',
				'exclusions_posttypes' => 'delay_js_post_types_exclusion',
				'exclusions_urls'      => 'delay_js_post_urls_exclusion',
				'exclusions_plugins'   => 'delay_js_plugins_themes_exclusion',
				'exclusions_trackers'  => 'delay_js_ads_tracker_exclusion',
				'exclusions_keywords'  => 'delay_js_exclusions',
			)
		);
	}

	/**
	 * Critical CSS exclusion rules in the property shape Mixpanel expects.
	 *
	 * @param array $options Minify options.
	 *
	 * @return array
	 */
	private function get_critical_css_exclusion_properties( array $options ) {
		$properties = $this->get_exclusion_properties(
			$options,
			array(
				'exclusions_files'    => 'critical_css_files_exclusion',
				'exclusions_urls'     => 'critical_css_post_urls_exclusion',
				'exclusions_plugins'  => 'critical_css_plugins_themes_exclusion',
				'exclusions_keywords' => 'critical_css_keywords',
			)
		);

		$post_types = $this->to_property_list( Utils::get_module( 'exclusions' )->get_critical_css_excluded_post_types() );

		$properties['exclusions_posttypes'] = empty( $post_types ) ? 'na' : $post_types;

		return $properties;
	}

	/**
	 * Map exclusion options to Mixpanel properties.
	 *
	 * @param array $options Minify options.
	 * @param array $map     Property name => option key.
	 *
	 * @return array
	 */
	private function get_exclusion_properties( array $options, array $map ) {
		$properties = array();

		foreach ( $map as $property => $option_key ) {
			$values = $this->to_property_list( isset( $options[ $option_key ] ) ? $options[ $option_key ] : array() );

			if ( 'exclusions_urls' === $property && ! empty( $values ) ) {
				$values = array_values( array_filter( array_map( 'get_permalink', array_map( 'absint', $values ) ) ) );
			}

			$properties[ $property ] = empty( $values ) ? 'na' : $values;
		}

		return $properties;
	}
}
