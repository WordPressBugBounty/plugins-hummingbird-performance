<?php
/**
 * Page Caching AJAX actions for the new React UI.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax\Caching
 */

namespace Hummingbird\Admin\Ajax\Caching;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Core\Modules\Page_Cache;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Caching\Fast_CGI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Caching
 */
class Page_Caching {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'caching';

	/**
	 * Page_Caching constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'page_cache_status',
			'page_cache_save_settings',
			'page_cache_save_options',
			'page_cache_save_fast_cgi_settings',
			'page_cache_switch_method',
			'page_cache_toggle',
			'page_cache_clear',
			'page_caching_save_permissions',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Get page cache status, settings, and option values.
	 *
	 * @since 4.0.0
	 */
	public function page_cache_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$module  = Utils::get_module( 'page_cache' );
		$options = $module->get_options();

		$file_count         = Page_Cache::get_cached_pages_count();
		$fast_cgi_settings  = Fast_CGI::wphb_fast_cgi_data();
		$static_cache_ttl   = isset( $fast_cgi_settings->ttl ) ? (int) $fast_cgi_settings->ttl : 480;
		$static_bypass_urls = isset( $fast_cgi_settings->bypass_urls ) && is_array( $fast_cgi_settings->bypass_urls ) ? $fast_cgi_settings->bypass_urls : array();

		$page_cache_error = is_wp_error( $module->error ) ? $module->error->get_error_message() : false;

		wp_send_json_success(
			array(
				'enabled'            => $module->is_active(),
				'error'              => $page_cache_error,
				'settings'           => $module->get_settings(),
				'cachedFiles'        => $file_count,
				'adminsCanDisable'   => isset( $options['enabled'] ) && 'blog-admins' === $options['enabled'],
				'cacheMethod'        => Fast_CGI::is_fast_cgi_enabled() ? 'static' : 'local',
				'isFastCgiSupported' => Fast_CGI::is_fast_cgi_supported(),
				'staticCacheTtl'     => $static_cache_ttl,
				'staticBypassUrls'   => $static_bypass_urls,
				'detection'          => $options['detection'],
				'preload'            => isset( $options['preload'] ) ? (bool) $options['preload'] : false,
				'preloadType'        => isset( $options['preload_type'] ) ? $options['preload_type'] : array(
					'home_page' => true,
					'on_clear'  => false,
				),
				'integrations'       => isset( $options['integrations'] ) ? $options['integrations'] : array(
					'varnish' => false,
					'opcache' => false,
				),
			)
		);
	}

	/**
	 * Save page cache settings stored in the JSON config file
	 * (page_types, settings.*, exclude.*, clear_interval).
	 *
	 * @since 4.0.0
	 */
	public function page_cache_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$module   = Utils::get_module( 'page_cache' );
		$previous = $module->get_settings();
		$current  = $previous;

		// Update settings.* keys.
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$bool_keys = array( 'logged_in', 'url_queries', 'cache_404', 'clear_update', 'debug_log', 'cache_identifier', 'compress', 'mobile', 'comment_clear', 'cache_headers' );

			foreach ( $bool_keys as $key ) {
				if ( array_key_exists( $key, $data['settings'] ) ) {
					$current['settings'][ $key ] = (int) (bool) $data['settings'][ $key ];
				}
			}
		}

		// Update page_types — validate against the authoritative list from Page_Cache.
		if ( isset( $data['page_types'] ) && is_array( $data['page_types'] ) ) {
			$valid                 = \Hummingbird\Core\Modules\Page_Cache::get_page_types( true );
			$current['page_types'] = array_values( array_intersect( array_map( 'sanitize_key', $data['page_types'] ), $valid ) );
		}

		// Update custom_post_types exclusion list — validate slugs against registered post types.
		if ( isset( $data['custom_post_types'] ) && is_array( $data['custom_post_types'] ) ) {
			$registered_cpts              = array_keys(
				get_post_types(
					array(
						'public'   => true,
						'_builtin' => false,
					)
				)
			);
			$current['custom_post_types'] = array_values(
				array_intersect(
					array_map( 'sanitize_key', $data['custom_post_types'] ),
					$registered_cpts
				)
			);
		}

		// Update exclusions.
		if ( isset( $data['exclude'] ) && is_array( $data['exclude'] ) ) {
			foreach ( array( 'url_strings', 'user_agents', 'cookies' ) as $key ) {
				if ( isset( $data['exclude'][ $key ] ) && is_array( $data['exclude'][ $key ] ) ) {
					$values = array_values( array_filter( array_map( 'sanitize_text_field', $data['exclude'][ $key ] ) ) );

					// URL strings are used as regex patterns — apply the same escaping the old UI used.
					if ( 'url_strings' === $key ) {
						$values = array_map(
							static function ( $string ) {
								$string = str_replace( '\\', '', $string );
								$string = str_replace( '/', '\/', $string );
								$string = preg_replace( '/.php$/', '\\.php', $string );
								$string = preg_replace( '/.xml$/', '\\.xml', $string );
								$string = preg_replace( '/.yml$/', '\\.yml', $string );
								return $string;
							},
							$values
						);
					}

					$current['exclude'][ $key ] = $values;
				}
			}
		}

		// Update clear_interval.
		if ( isset( $data['clear_interval'] ) && is_array( $data['clear_interval'] ) ) {
			if ( array_key_exists( 'enabled', $data['clear_interval'] ) ) {
				$current['clear_interval']['enabled'] = (bool) $data['clear_interval']['enabled'];
			}
			if ( isset( $data['clear_interval']['interval'] ) ) {
				$current['clear_interval']['interval'] = max( 1, (int) $data['clear_interval']['interval'] );
			}
		}

		$module->save_settings( $current );

		$this->record_page_cache_settings_changes( $previous, $current );

		wp_send_json_success(
			array(
				'settings'          => $current,
				'custom_post_types' => $current['custom_post_types'] ?? array(),
			)
		);
	}

	/**
	 * Save page cache module options stored in WP options
	 * (preload, preload_type, integrations).
	 *
	 * @since 4.0.0
	 */
	public function page_cache_save_options() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$module   = Utils::get_module( 'page_cache' );
		$options  = $module->get_options();
		$previous = $options;

		if ( isset( $data['detection'] ) && in_array( $data['detection'], array( 'auto', 'manual', 'none' ), true ) ) {
			$options['detection'] = $data['detection'];

			// Remove notice if File Change Detection is set to 'auto' or 'none'.
			if ( 'auto' === $options['detection'] || 'none' === $options['detection'] ) {
				delete_option( 'wphb-notice-cache-cleaned-show' );
			}
		}

		// Multisite: allow blog admins to toggle page caching on their own sites.
		if ( is_multisite() && array_key_exists( 'admins_disable_caching', $data ) ) {
			if ( (bool) $data['admins_disable_caching'] ) {
				$options['enabled'] = 'blog-admins';
			} elseif ( $module->is_active() ) {
				$options['enabled'] = true;
			}
		}

		if ( array_key_exists( 'preload', $data ) ) {
			$options['preload'] = (bool) $data['preload'];
		}

		if ( isset( $data['preloadType'] ) && is_array( $data['preloadType'] ) ) {
			$pre_home_page = array_key_exists( 'home_page', $data['preloadType'] ) && (bool) $data['preloadType']['home_page'];
			$pre_on_clear  = array_key_exists( 'on_clear', $data['preloadType'] ) && (bool) $data['preloadType']['on_clear'];
			if ( ! isset( $options['preload_type'] ) ) {
				$options['preload_type'] = array(
					'home_page' => false,
					'on_clear'  => false,
				);
			}
			$options['preload_type']['home_page'] = $pre_home_page;
			$options['preload_type']['on_clear']  = $pre_on_clear;
			if ( ! $pre_home_page && ! $pre_on_clear ) {
				$options['preload'] = false;
			}
		}

		if ( isset( $data['integrations'] ) && is_array( $data['integrations'] ) ) {
			if ( ! isset( $options['integrations'] ) ) {
				$options['integrations'] = array(
					'varnish' => false,
					'opcache' => false,
				);
			}
			if ( array_key_exists( 'varnish', $data['integrations'] ) ) {
				$options['integrations']['varnish'] = (bool) $data['integrations']['varnish'];
			}
			if ( array_key_exists( 'opcache', $data['integrations'] ) ) {
				$options['integrations']['opcache'] = (bool) $data['integrations']['opcache'];
			}
		}

		$module->update_options( $options );

		$this->record_page_cache_options_changes( $previous, $options );

		wp_send_json_success(
			array(
				'detection'    => $options['detection'],
				'preload'      => $options['preload'],
				'preloadType'  => $options['preload_type'],
				'integrations' => $options['integrations'],
			)
		);
	}

	/**
	 * Save FastCGI/static-server settings for React caching UI.
	 *
	 * Mirrors legacy fast_cgi_save_settings behavior using JSON payload.
	 *
	 * @since 4.0.0
	 */
	public function page_cache_save_fast_cgi_settings() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$ttl          = isset( $data['ttl'] ) ? trim( (string) $data['ttl'] ) : '';
		$allowed_ttls = array( '60', '240', '480', '720', '1440' );
		if ( ! in_array( $ttl, $allowed_ttls, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cache lifetime.', 'wphb' ) ) );
		}

		$query_params = '';
		if ( isset( $data['query_params'] ) ) {
			if ( is_array( $data['query_params'] ) ) {
				$query_params = array_values( array_filter( array_map( 'sanitize_text_field', $data['query_params'] ) ) );
			} else {
				$query_params_raw = sanitize_textarea_field( (string) $data['query_params'] );
				$query_params     = '' === trim( $query_params_raw ) ? '' : preg_split( '/[\r\n\t]+/', $query_params_raw );
			}
		}

		$bypass_urls = '';
		if ( isset( $data['bypass_urls'] ) ) {
			if ( is_array( $data['bypass_urls'] ) ) {
				$bypass_urls = array_values( array_filter( array_map( 'sanitize_text_field', $data['bypass_urls'] ) ) );
			} else {
				$bypass_urls_raw = sanitize_textarea_field( (string) $data['bypass_urls'] );
				$bypass_urls     = '' === trim( $bypass_urls_raw ) ? '' : preg_split( '/[\r\n\t]+/', $bypass_urls_raw );
			}
		}

		$hosting_payload = array(
			'ttl'          => $ttl,
			'bypass_urls'  => $bypass_urls,
			'query_params' => $query_params,
		);

		$existing_settings = Fast_CGI::wphb_fast_cgi_data();
		$settings_modified = array();

		if ( ! empty( $existing_settings ) ) {
			if ( $ttl !== trim( (string) $existing_settings->ttl ) ) {
				$settings_modified[] = 'cache_lifetime';
			}

			$existing_bypass_urls = isset( $existing_settings->bypass_urls ) ? $existing_settings->bypass_urls : '';
			if ( self::normalize_url_list( $bypass_urls ) !== self::normalize_url_list( $existing_bypass_urls ) ) {
				$settings_modified[] = 'exclude_url';
			}

			$existing_query_params = isset( $existing_settings->query_params ) ? $existing_settings->query_params : '';
			if ( self::normalize_url_list( $query_params ) !== self::normalize_url_list( $existing_query_params ) ) {
				$settings_modified[] = 'cache_query_param';
			}
		}

		$error_message          = '';
		$fast_cgi_update_failed = false;
		if ( ! empty( $settings_modified ) ) {
			$response = Utils::get_api()->hosting->wphb_update_fast_cgi_settings( $hosting_payload );
			if ( is_wp_error( $response ) ) {
				$fast_cgi_update_failed = true;
				$error_message          = $response->get_error_message();
			}
		}

		$module           = Utils::get_module( 'page_cache' );
		$options          = $module->get_options();
		$previous_options = $options;

		$preload   = isset( $data['preload'] ) ? (bool) $data['preload'] : false;
		$home_page = isset( $data['preloadType']['home_page'] ) ? (bool) $data['preloadType']['home_page'] : false;
		$on_clear  = isset( $data['preloadType']['on_clear'] ) ? (bool) $data['preloadType']['on_clear'] : false;

		if ( $preload !== (bool) $options['preload'] ) {
			$settings_modified[] = 'preload_caching';
		} elseif ( $preload && ( $home_page !== (bool) $options['preload_type']['home_page'] || $on_clear !== (bool) $options['preload_type']['on_clear'] ) ) {
			$settings_modified[] = 'preload_caching';
		}

		$options['preload'] = $preload;
		if ( $options['preload'] ) {
			$options['preload_type']['home_page'] = $home_page;
			$options['preload_type']['on_clear']  = $on_clear;
		}

		$module->update_options( $options );

		$settings                              = $module->get_settings();
		$previous_settings                     = $settings;
		$settings['settings']['comment_clear'] = isset( $data['settings']['comment_clear'] ) ? (bool) $data['settings']['comment_clear'] : false;
		$settings['settings']['clear_update']  = isset( $data['settings']['clear_update'] ) ? (bool) $data['settings']['clear_update'] : false;
		$module->save_settings( $settings );

		// Only log these as changed if the hosting API actually applied them —
		// otherwise the real (remote) state never changed, and logging it
		// anyway would show a change here that a page reload won't reflect.
		if ( ! empty( $existing_settings ) && ! $fast_cgi_update_failed ) {
			$existing_bypass_urls  = isset( $existing_settings->bypass_urls ) ? $existing_settings->bypass_urls : '';
			$existing_query_params = isset( $existing_settings->query_params ) ? $existing_settings->query_params : '';
			if ( self::normalize_url_list( $bypass_urls ) !== self::normalize_url_list( $existing_bypass_urls ) ) {
				Activity_Log::record( self::ACTIVITY_MODULE, __( 'URL string exclusions list updated.', 'wphb' ) );
			}
			if ( self::normalize_url_list( $query_params ) !== self::normalize_url_list( $existing_query_params ) ) {
				Activity_Log::record( self::ACTIVITY_MODULE, __( 'Cache URL queries configuration updated.', 'wphb' ) );
			}
		}

		$this->record_page_cache_options_changes( $previous_options, $options );

		$settings_toggle_messages = array(
			'clear_update'  => array(
				__( 'Cache clearing on post/page updates enabled.', 'wphb' ),
				__( 'Cache clearing on post/page updates disabled.', 'wphb' ),
			),
			'comment_clear' => array(
				__( 'Cache clearing on new comments enabled.', 'wphb' ),
				__( 'Cache clearing on new comments disabled.', 'wphb' ),
			),
		);
		$this->record_settings_toggles( $previous_settings, $settings, $settings_toggle_messages );

		if ( ! empty( $settings_modified ) ) {
			$module->clear_cache();
		}

		if ( ! $fast_cgi_update_failed && isset( $existing_settings->ttl ) && $ttl !== trim( (string) $existing_settings->ttl ) ) {
			$ttl_hours = (int) $ttl / 60;
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: 1: cache lifetime value, 2: cache lifetime unit. */
					__( 'Cache lifetime set to %1$d %2$s.', 'wphb' ),
					$ttl_hours,
					_n( 'hour', 'hours', $ttl_hours, 'wphb' )
				)
			);
		}

		// The hosting update failing means the ttl/exclusions/query-param
		// changes above never actually took effect remotely — report this as
		// a failure rather than silently "succeeding" with a value that a
		// page reload won't actually show.
		if ( $fast_cgi_update_failed ) {
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		wp_send_json_success(
			array(
				'success'          => true,
				'fastCGIResponse'  => $error_message,
				'settingsModified' => ! empty( $settings_modified ) ? implode( ',', array_unique( $settings_modified ) ) : '',
				'cacheLifetime'    => '' !== $ttl ? ( (int) $ttl ) / 60 : $ttl,
				'preloadHomepage'  => isset( $options['preload_type'] ) && $options['preload_type']['home_page'] ? 'enabled' : 'disabled',
				'staticCacheTtl'   => (int) $ttl,
				'staticBypassUrls' => is_array( $bypass_urls ) ? $bypass_urls : array(),
			)
		);
	}

	/**
	 * Normalize a bypass-urls/query-params value for "did it change" comparisons.
	 *
	 * The same list of values can arrive as an array (from the request payload)
	 * or as a string (from the remote hosting API response), an empty list can
	 * be represented as either `array()` or `''` depending on the source, and
	 * the remote API is free to return the list in a different order than it
	 * was submitted in. Comparing those raw values with `!==` causes
	 * false-positive "changed" detection, which keeps re-logging/re-saving
	 * settings that never actually changed until the page is reloaded and the
	 * frontend state is re-synced with the backend's representation. Mirrors
	 * the normalization already used for safe-mode comparisons, see
	 * Safe_Mode::get_fast_cgi_settings_snapshot().
	 *
	 * @since 4.0.0
	 *
	 * @param array|string $value Raw bypass_urls or query_params value.
	 * @return array Normalized, trimmed, non-empty, order-independent list of values.
	 */
	private static function normalize_url_list( $value ) {
		if ( ! is_array( $value ) ) {
			$value = '' === trim( (string) $value ) ? array() : preg_split( '/[\r\n\t]+/', (string) $value );
		}

		$list = array_values(
			array_filter(
				array_map( 'trim', $value ),
				static function ( $item ) {
					return '' !== $item;
				}
			)
		);
		sort( $list );

		return $list;
	}

	/**
	 * Switch between local (PHP) and static (FastCGI/hosting) cache method.
	 *
	 * Mirrors wphb_switch_cache_method from class-ajax.php, adapted for the
	 * React UI nonce/data convention.
	 *
	 * Expects data: { method: 'local' | 'static' }
	 *
	 * @since 4.0.0
	 */
	public function page_cache_switch_method() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data   = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data   = json_decode( html_entity_decode( $data ), true );
		$method = isset( $data['method'] ) ? sanitize_key( $data['method'] ) : '';

		if ( ! in_array( $method, array( 'local', 'static' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cache method.', 'wphb' ) ) );
		}

		$module        = Utils::get_module( 'page_cache' );
		$error_message = '';
		$fast_cgi_on   = null;

		if ( 'static' === $method ) {
			$response = Utils::get_api()->hosting->toggle_fast_cgi( true );
			if ( ! is_wp_error( $response ) && isset( $response->is_active ) ) {
				Fast_CGI::update_fast_cgi_status( $response->is_active );
			}
			$error_message = is_wp_error( $response ) ? $response->get_error_message() : '';
			$fast_cgi_on   = empty( $error_message );
			if ( $fast_cgi_on ) {
				$module->disable();
			}
		} else {
			$response      = Utils::get_api()->hosting->toggle_fast_cgi( false );
			$error_message = is_wp_error( $response ) ? $response->get_error_message() : '';
			$fast_cgi_on   = false;
			$module->toggle_service( true, true );
		}

		// Always clear cached files when switching methods.
		$module->clear_cache();

		$file_count         = Page_Cache::get_cached_pages_count();
		$fast_cgi_settings  = Fast_CGI::wphb_fast_cgi_data();
		$static_cache_ttl   = isset( $fast_cgi_settings->ttl ) ? (int) $fast_cgi_settings->ttl : 480;
		$static_bypass_urls = isset( $fast_cgi_settings->bypass_urls ) && is_array( $fast_cgi_settings->bypass_urls ) ? $fast_cgi_settings->bypass_urls : array();

		$local_settings    = $module->get_settings();
		$local_bypass_urls = isset( $local_settings['exclude']['url_strings'] ) && is_array( $local_settings['exclude']['url_strings'] )
			? $local_settings['exclude']['url_strings']
			: array();

		if ( '' === $error_message ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				'static' === $method
					? __( 'Cache method set to Static server cache.', 'wphb' )
					: __( 'Cache method set to Local page cache.', 'wphb' )
			);
		}

		wp_send_json_success(
			array(
				'method'           => $method,
				'fastCgiOn'        => $fast_cgi_on,
				'error'            => $error_message,
				'cachedFiles'      => $file_count,
				'enabled'          => $module->is_active(),
				'staticCacheTtl'   => $static_cache_ttl,
				'staticBypassUrls' => $static_bypass_urls,
				'localBypassUrls'  => $local_bypass_urls,
			)
		);
	}

	/**
	 * Enable or disable the page caching module.
	 *
	 * @since 4.0.0
	 */
	public function page_cache_toggle() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		// Defaults to local: the dashboard card toggles page caching without a method selector, so a
		// payload that only carries `enable` must not fall through to the Static server branch and
		// call the hosting API. Validate rather than letting `else` catch everything, so an
		// unrecognised method is an error the caller can see instead of a silent method switch.
		//
		// `enable` has to be present, so a payload missing the key fails loudly instead of reading as
		// false and silently disabling caching.
		if ( ! isset( $data['enable'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing enable flag.', 'wphb' ) ) );
		}

		$enable       = (bool) $data['enable'];
		$cache_method = isset( $data['cache_method'] ) ? sanitize_key( $data['cache_method'] ) : 'local';

		if ( ! in_array( $cache_method, array( 'local', 'static' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cache method.', 'wphb' ) ) );
		}

		if ( 'local' === $cache_method ) {
			$module = Utils::get_module( 'page_cache' );

			if ( $enable ) {
				$module->enable( true );
			} else {
				$module->disable();
			}

			Activity_Log::record_toggle(
				self::ACTIVITY_MODULE,
				$enable,
				__( 'Local page caching enabled.', 'wphb' ),
				__( 'Local page caching disabled.', 'wphb' )
			);
		} else {
			$response = Utils::get_api()->hosting->toggle_fast_cgi( $enable );
			if ( isset( $response->is_active ) ) {
				Fast_CGI::update_fast_cgi_status( $response->is_active );
			}

			Activity_Log::record_toggle(
				self::ACTIVITY_MODULE,
				$enable,
				__( 'Static server caching enabled.', 'wphb' ),
				__( 'Static server caching disabled.', 'wphb' )
			);
		}

		$file_count = Page_Cache::get_cached_pages_count();

		wp_send_json_success(
			array(
				'enabled'     => (bool) $enable,
				'cachedFiles' => $file_count,
			)
		);
	}

	/**
	 * Human-readable label for a page-type/custom-post-type slug.
	 *
	 * @since 4.0.0
	 *
	 * @param string $slug Page type or custom post type slug.
	 *
	 * @return string
	 */
	private function get_page_type_label( $slug ) {
		$labels = Page_Cache::get_page_types();
		if ( isset( $labels[ $slug ] ) ) {
			return $labels[ $slug ];
		}

		$post_type = get_post_type_object( $slug );
		if ( $post_type && isset( $post_type->labels->name ) ) {
			return $post_type->labels->name;
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Record activity-log items for page-cache JSON settings changes.
	 *
	 * @param array $previous Previous settings payload.
	 * @param array $current  Current settings payload.
	 *
	 * @return void
	 */
	private function record_page_cache_settings_changes( array $previous, array $current ) {
		$prev_settings = isset( $previous['settings'] ) && is_array( $previous['settings'] ) ? $previous['settings'] : array();
		$next_settings = isset( $current['settings'] ) && is_array( $current['settings'] ) ? $current['settings'] : array();

		$toggle_messages = array(
			'mobile'           => array(
				__( 'Cache on mobile devices enabled.', 'wphb' ),
				__( 'Cache on mobile devices disabled.', 'wphb' ),
			),
			'logged_in'        => array(
				__( 'Cache for logged-in users enabled.', 'wphb' ),
				__( 'Cache for logged-in users disabled.', 'wphb' ),
			),
			'url_queries'      => array(
				__( 'Cache URL queries enabled.', 'wphb' ),
				__( 'Cache URL queries disabled.', 'wphb' ),
			),
			'cache_headers'    => array(
				__( 'Cache HTTP headers enabled.', 'wphb' ),
				__( 'Cache HTTP headers disabled.', 'wphb' ),
			),
			'cache_404'        => array(
				__( 'Cache 404 requests enabled.', 'wphb' ),
				__( 'Cache 404 requests disabled.', 'wphb' ),
			),
			'cache_identifier' => array(
				__( 'Identify cached pages enabled.', 'wphb' ),
				__( 'Identify cached pages disabled.', 'wphb' ),
			),
			'comment_clear'    => array(
				__( 'Cache clearing on new comments enabled.', 'wphb' ),
				__( 'Cache clearing on new comments disabled.', 'wphb' ),
			),
			'clear_update'     => array(
				__( 'Cache clearing on post/page updates enabled.', 'wphb' ),
				__( 'Cache clearing on post/page updates disabled.', 'wphb' ),
			),
		);

		$this->record_settings_toggles( $previous, $current, $toggle_messages );

		$prev_interval = isset( $previous['clear_interval'] ) && is_array( $previous['clear_interval'] ) ? $previous['clear_interval'] : array();
		$next_interval = isset( $current['clear_interval'] ) && is_array( $current['clear_interval'] ) ? $current['clear_interval'] : array();

		$prev_interval_enabled = ! empty( $prev_interval['enabled'] );
		$next_interval_enabled = ! empty( $next_interval['enabled'] );
		if ( $prev_interval_enabled !== $next_interval_enabled ) {
			Activity_Log::record_toggle(
				self::ACTIVITY_MODULE,
				$next_interval_enabled,
				__( 'Cache interval enabled.', 'wphb' ),
				__( 'Cache interval disabled.', 'wphb' )
			);
		}

		$prev_interval_hours = isset( $prev_interval['interval'] ) ? (int) $prev_interval['interval'] : 0;
		$next_interval_hours = isset( $next_interval['interval'] ) ? (int) $next_interval['interval'] : 0;
		if ( $next_interval_hours > 0 && $prev_interval_hours !== $next_interval_hours ) {
			$interval_value = $next_interval_hours;
			$interval_unit  = _n( 'hour', 'hours', $interval_value, 'wphb' );
			if ( 0 === $next_interval_hours % 24 ) {
				$interval_value = (int) ( $next_interval_hours / 24 );
				$interval_unit  = _n( 'day', 'days', $interval_value, 'wphb' );
			}

			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: 1: interval value, 2: interval unit. */
					__( 'Cache interval timing set to %1$d %2$s.', 'wphb' ),
					$interval_value,
					$interval_unit
				)
			);
		}

		$prev_page_types = isset( $previous['page_types'] ) && is_array( $previous['page_types'] ) ? $previous['page_types'] : array();
		$next_page_types = isset( $current['page_types'] ) && is_array( $current['page_types'] ) ? $current['page_types'] : array();

		$enabled_page_types  = array_values( array_diff( $next_page_types, $prev_page_types ) );
		$disabled_page_types = array_values( array_diff( $prev_page_types, $next_page_types ) );

		// custom_post_types is stored as an EXCLUDE list (a CPT is cached unless
		// listed here), so the enabled/disabled direction is inverted compared
		// to page_types.
		$prev_excluded_cpts = isset( $previous['custom_post_types'] ) && is_array( $previous['custom_post_types'] ) ? $previous['custom_post_types'] : array();
		$next_excluded_cpts = isset( $current['custom_post_types'] ) && is_array( $current['custom_post_types'] ) ? $current['custom_post_types'] : array();

		$enabled_cpts  = array_values( array_diff( $prev_excluded_cpts, $next_excluded_cpts ) );
		$disabled_cpts = array_values( array_diff( $next_excluded_cpts, $prev_excluded_cpts ) );

		$enabled_coverage  = array_merge( $enabled_page_types, $enabled_cpts );
		$disabled_coverage = array_merge( $disabled_page_types, $disabled_cpts );

		if ( ! empty( $enabled_coverage ) ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: comma-separated page types. */
					__( 'Cache coverage enabled for %s.', 'wphb' ),
					implode( ', ', array_map( array( $this, 'get_page_type_label' ), $enabled_coverage ) )
				)
			);
		}

		if ( ! empty( $disabled_coverage ) ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: comma-separated page types. */
					__( 'Cache coverage disabled for %s.', 'wphb' ),
					implode( ', ', array_map( array( $this, 'get_page_type_label' ), $disabled_coverage ) )
				)
			);
		}

		$prev_url_strings = isset( $previous['exclude']['url_strings'] ) && is_array( $previous['exclude']['url_strings'] ) ? $previous['exclude']['url_strings'] : array();
		$next_url_strings = isset( $current['exclude']['url_strings'] ) && is_array( $current['exclude']['url_strings'] ) ? $current['exclude']['url_strings'] : array();
		Activity_Log::record_when_changed( self::ACTIVITY_MODULE, $prev_url_strings, $next_url_strings, __( 'URL string exclusions list updated.', 'wphb' ) );

		$prev_user_agents = isset( $previous['exclude']['user_agents'] ) && is_array( $previous['exclude']['user_agents'] ) ? $previous['exclude']['user_agents'] : array();
		$next_user_agents = isset( $current['exclude']['user_agents'] ) && is_array( $current['exclude']['user_agents'] ) ? $current['exclude']['user_agents'] : array();
		Activity_Log::record_when_changed( self::ACTIVITY_MODULE, $prev_user_agents, $next_user_agents, __( 'User agent exclusions list updated.', 'wphb' ) );

		$prev_cookies = isset( $previous['exclude']['cookies'] ) && is_array( $previous['exclude']['cookies'] ) ? $previous['exclude']['cookies'] : array();
		$next_cookies = isset( $current['exclude']['cookies'] ) && is_array( $current['exclude']['cookies'] ) ? $current['exclude']['cookies'] : array();
		Activity_Log::record_when_changed( self::ACTIVITY_MODULE, $prev_cookies, $next_cookies, __( 'Cookies exclusions list updated.', 'wphb' ) );
	}

	/**
	 * Record activity-log items for page-cache options changes.
	 *
	 * @param array $previous Previous options payload.
	 * @param array $current  Current options payload.
	 *
	 * @return void
	 */
	private function record_page_cache_options_changes( array $previous, array $current ) {
		$toggle_changes = array(
			array(
				isset( $previous['preload'] ) ? (bool) $previous['preload'] : false,
				isset( $current['preload'] ) ? (bool) $current['preload'] : false,
				__( 'Preload caching enabled.', 'wphb' ),
				__( 'Preload caching disabled.', 'wphb' ),
			),
			array(
				isset( $previous['preload_type']['home_page'] ) ? (bool) $previous['preload_type']['home_page'] : false,
				isset( $current['preload_type']['home_page'] ) ? (bool) $current['preload_type']['home_page'] : false,
				__( 'Homepage preload caching enabled.', 'wphb' ),
				__( 'Homepage preload caching disabled.', 'wphb' ),
			),
			array(
				isset( $previous['preload_type']['on_clear'] ) ? (bool) $previous['preload_type']['on_clear'] : false,
				isset( $current['preload_type']['on_clear'] ) ? (bool) $current['preload_type']['on_clear'] : false,
				__( 'Page/post preload caching enabled.', 'wphb' ),
				__( 'Page/post preload caching disabled.', 'wphb' ),
			),
			array(
				isset( $previous['integrations']['varnish'] ) ? (bool) $previous['integrations']['varnish'] : false,
				isset( $current['integrations']['varnish'] ) ? (bool) $current['integrations']['varnish'] : false,
				__( 'Purge Varnish cache enabled.', 'wphb' ),
				__( 'Purge Varnish cache disabled.', 'wphb' ),
			),
			array(
				isset( $previous['integrations']['opcache'] ) ? (bool) $previous['integrations']['opcache'] : false,
				isset( $current['integrations']['opcache'] ) ? (bool) $current['integrations']['opcache'] : false,
				__( 'Purge OPcache enabled.', 'wphb' ),
				__( 'Purge OPcache disabled.', 'wphb' ),
			),
		);

		foreach ( $toggle_changes as $toggle_change ) {
			$this->record_direct_toggle_change(
				$toggle_change[0],
				$toggle_change[1],
				$toggle_change[2],
				$toggle_change[3]
			);
		}

		$prev_mode = isset( $previous['detection'] ) ? sanitize_key( $previous['detection'] ) : '';
		$next_mode = isset( $current['detection'] ) ? sanitize_key( $current['detection'] ) : '';
		if ( $next_mode && $prev_mode !== $next_mode ) {
			$mode_labels = array(
				'auto'   => __( 'Automatic', 'wphb' ),
				'manual' => __( 'Notify me', 'wphb' ),
				'none'   => __( 'Off', 'wphb' ),
			);
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: file change detection mode label. */
					__( 'File change detection set to %s.', 'wphb' ),
					$mode_labels[ $next_mode ]
				)
			);
		}
	}

	/**
	 * Record selected boolean setting changes from `settings` arrays.
	 *
	 * @param array $previous Previous settings payload.
	 * @param array $current  Current settings payload.
	 * @param array $messages Setting key => [enabled message, disabled message].
	 *
	 * @return void
	 */
	private function record_settings_toggles( array $previous, array $current, array $messages ) {
		$prev_settings = isset( $previous['settings'] ) && is_array( $previous['settings'] ) ? $previous['settings'] : array();
		$next_settings = isset( $current['settings'] ) && is_array( $current['settings'] ) ? $current['settings'] : array();

		foreach ( $messages as $key => $message_pair ) {
			$this->record_toggle_change( $prev_settings, $next_settings, $key, $message_pair[0], $message_pair[1] );
		}
	}

	/**
	 * Record a boolean setting change.
	 *
	 * @param array  $previous         Previous settings map.
	 * @param array  $current          Current settings map.
	 * @param string $key              Setting key.
	 * @param string $enabled_message  Message when enabled.
	 * @param string $disabled_message Message when disabled.
	 *
	 * @return void
	 */
	private function record_toggle_change( array $previous, array $current, $key, $enabled_message, $disabled_message ) {
		$has_previous = array_key_exists( $key, $previous );
		$has_current  = array_key_exists( $key, $current );

		if ( ! $has_previous || ! $has_current ) {
			return;
		}

		$prev = (bool) $previous[ $key ];
		$next = (bool) $current[ $key ];

		if ( $prev === $next ) {
			return;
		}

		Activity_Log::record_toggle( self::ACTIVITY_MODULE, $next, $enabled_message, $disabled_message );
	}

	/**
	 * Record a toggle change when previous and current values are already resolved.
	 *
	 * @param bool   $previous_value   Previous value.
	 * @param bool   $current_value    Current value.
	 * @param string $enabled_message  Message when enabled.
	 * @param string $disabled_message Message when disabled.
	 *
	 * @return void
	 */
	private function record_direct_toggle_change( $previous_value, $current_value, $enabled_message, $disabled_message ) {
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			(bool) $previous_value,
			(bool) $current_value,
			$enabled_message,
			$disabled_message
		);
	}

	/**
	 * Clear all page cache files.
	 *
	 * @since 4.0.0
	 */
	public function page_cache_clear() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$current_fast_cgi = Fast_CGI::is_fast_cgi_enabled();
		$status           = Utils::get_module( 'page_cache' )->clear_cache();

		if ( ! $status ) {
			wp_send_json_error(
				array(
					'message' => __( 'There was an error during the cache purge. Check folder permissions are 755 for /wp-content/wphb-cache or delete directory manually.', 'wphb' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'cachedFiles' => Page_Cache::get_cached_pages_count(),
				'message'     => __( 'Page cache purged.', 'wphb' ),
				'reload'      => Utils::get_api()->hosting->has_fast_cgi() !== $current_fast_cgi,
			)
		);
	}

	/**
	 * Save whether subsites may switch page caching off for themselves.
	 *
	 * @since 4.0.0
	 */
	public function page_caching_save_permissions() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! is_multisite() || ! current_user_can( 'manage_network' ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) || ! array_key_exists( 'subsitesCanDisable', $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing settings data.', 'wphb' ) ) );
		}

		$module  = Utils::get_module( 'page_cache' );
		$options = $module->get_options();

		if ( ! empty( $data['subsitesCanDisable'] ) ) {
			$options['enabled'] = 'blog-admins';
		} elseif ( $module->is_active() ) {
			$options['enabled'] = true;
		}

		$module->update_options( $options );

		wp_send_json_success( $this->get_permissions_state() );
	}

	/**
	 * Current page caching subsite controls.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_permissions_state() {
		$module  = Utils::get_module( 'page_cache' );
		$options = $module->get_options();

		return array(
			'subsitesCanDisable' => isset( $options['enabled'] ) && 'blog-admins' === $options['enabled'],
			'moduleActive'       => isset( $options['enabled'] ) && false !== $options['enabled'],
		);
	}
}
