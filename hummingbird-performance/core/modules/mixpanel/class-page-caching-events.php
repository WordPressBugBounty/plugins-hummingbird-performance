<?php
/**
 * Mixpanel events for the Page Caching area.
 *
 * @since 4.0.1
 * @package Hummingbird\Core\Modules\Mixpanel
 */

namespace Hummingbird\Core\Modules\Mixpanel;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Caching_Events.
 *
 * Events for local page caching and the hosting static cache (FastCGI).
 */
class Page_Caching_Events extends Events {

	/**
	 * `Method` value of the local page cache.
	 *
	 * @var string
	 */
	const METHOD_LOCAL = 'local_page_cache';

	/**
	 * `Method` value of the hosting static cache.
	 *
	 * @var string
	 */
	const METHOD_HOSTING = 'hosting_static_cache';

	/**
	 * Config file `settings` flags => the name reported in the `modified_settings` property.
	 *
	 * @var array<string, string>
	 */
	const SETTING_FLAGS = array(
		'cache_identifier' => 'identify_cached_pages',
		'debug_log'        => 'debug_log',
		'comment_clear'    => 'purge_on_comment',
		'clear_update'     => 'hb_settings_full_purge',
		'mobile'           => 'hb_settings_mobile',
	);

	/**
	 * Track page caching being switched on or off.
	 *
	 * @param bool   $was_enabled State before the change.
	 * @param bool   $is_enabled  State after the change.
	 * @param string $method      METHOD_LOCAL or METHOD_HOSTING.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_toggle( $was_enabled, $is_enabled, $method = self::METHOD_LOCAL ) {
		if ( (bool) $was_enabled === (bool) $is_enabled ) {
			return false;
		}

		$this->analytics->track_feature_toggle( self::METHOD_LOCAL === $method ? 'Page Caching' : $method, $was_enabled, $is_enabled );

		return $this->track_page_caching_updated( $is_enabled ? 'activate' : 'deactivate', $method );
	}

	/**
	 * Track a switch between the local page cache and the hosting static cache.
	 *
	 * @param string $method The method switched to: METHOD_LOCAL or METHOD_HOSTING.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_method_switch( $method ) {
		return $this->track_page_caching_updated( 'switch_method', $method );
	}

	/**
	 * Track a change of the page cache settings stored in the config file.
	 *
	 * @param array $previous Config file settings before the save.
	 * @param array $current  Config file settings after the save.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_settings_change( array $previous, array $current ) {
		$modified = array();

		$prev_flags = isset( $previous['settings'] ) && is_array( $previous['settings'] ) ? $previous['settings'] : array();
		$next_flags = isset( $current['settings'] ) && is_array( $current['settings'] ) ? $current['settings'] : array();

		foreach ( self::SETTING_FLAGS as $key => $name ) {
			if ( ! empty( $prev_flags[ $key ] ) !== ! empty( $next_flags[ $key ] ) ) {
				$modified[] = $name;
			}
		}

		if ( $this->option_changed( $previous, $current, 'page_types' ) ) {
			$modified[] = 'hb_page_types';
		}

		$prev_interval = isset( $previous['clear_interval'] ) && is_array( $previous['clear_interval'] ) ? $previous['clear_interval'] : array();
		$next_interval = isset( $current['clear_interval'] ) && is_array( $current['clear_interval'] ) ? $current['clear_interval'] : array();

		if ( ! empty( $prev_interval['enabled'] ) !== ! empty( $next_interval['enabled'] ) ) {
			$modified[] = 'hb_cache_interval';
		} elseif ( ! empty( $next_interval['enabled'] ) && $this->option_changed( $prev_interval, $next_interval, 'interval' ) ) {
			$modified[] = 'hb_cache_interval';
		}

		$prev_exclude = isset( $previous['exclude'] ) && is_array( $previous['exclude'] ) ? $previous['exclude'] : array();
		$next_exclude = isset( $current['exclude'] ) && is_array( $current['exclude'] ) ? $current['exclude'] : array();

		if ( $this->any_option_changed( $prev_exclude, $next_exclude, array( 'url_strings', 'user_agents', 'cookies' ) ) ) {
			$modified[] = 'hb_exclusions';
		}

		if ( $this->option_changed( $prev_exclude, $next_exclude, 'url_strings' ) ) {
			$modified[] = 'hb_settings_url_queries';
		}

		if ( empty( $modified ) ) {
			return false;
		}

		return $this->track_page_caching_updated( 'modified', self::METHOD_LOCAL, $modified );
	}

	/**
	 * Track a change of the page cache options stored in the database (preload, Varnish, OPcache).
	 *
	 * @param array $previous Module options before the save.
	 * @param array $current  Module options after the save.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_options_change( array $previous, array $current ) {
		$modified = array();

		$prev_preload = ! empty( $previous['preload'] );
		$next_preload = ! empty( $current['preload'] );

		if ( $prev_preload !== $next_preload ) {
			$modified[] = 'preload_caching';
		} elseif ( $next_preload && $this->option_changed( $previous, $current, 'preload_type' ) ) {
			$modified[] = 'preload_caching';
		}

		$prev_integrations = isset( $previous['integrations'] ) && is_array( $previous['integrations'] ) ? $previous['integrations'] : array();
		$next_integrations = isset( $current['integrations'] ) && is_array( $current['integrations'] ) ? $current['integrations'] : array();

		foreach ( array( 'varnish', 'opcache' ) as $integration ) {
			if ( ! empty( $prev_integrations[ $integration ] ) !== ! empty( $next_integrations[ $integration ] ) ) {
				$modified[] = 'hb_integration_' . $integration;
			}
		}

		if ( empty( $modified ) ) {
			return false;
		}

		return $this->track_page_caching_updated( 'modified', self::METHOD_LOCAL, $modified );
	}

	/**
	 * Track a change of the hosting static cache settings.
	 *
	 * @param string[] $modified_settings Names of the settings the handler found changed.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_hosting_settings_change( array $modified_settings ) {
		if ( empty( $modified_settings ) ) {
			return false;
		}

		return $this->track_page_caching_updated( 'modified', self::METHOD_HOSTING, $modified_settings );
	}

	/**
	 * Record the `page_caching_updated` event.
	 *
	 * @param string   $update_type       'activate', 'deactivate', 'modified' or 'switch_method'.
	 * @param string   $method            METHOD_LOCAL or METHOD_HOSTING.
	 * @param string[] $modified_settings Names of the settings that changed, if any.
	 *
	 * @return bool Whether the event was sent.
	 */
	private function track_page_caching_updated( $update_type, $method, array $modified_settings = array() ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$options = Utils::get_module( 'page_cache' )->get_options();

		return $this->track(
			'page_caching_updated',
			array(
				'update_type'       => $update_type,
				'Method'            => $method,
				'Location'          => $this->get_location(),
				'modified_settings' => $this->to_setting_list( $modified_settings ),
				'preload_homepage'  => ! empty( $options['preload_type']['home_page'] ) ? 'enabled' : 'disabled',
			)
		);
	}
}
