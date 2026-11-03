<?php
/**
 * Class Settings manages common settings for modules.
 *
 * @package Hummingbird\Core
 * @since 1.8
 */

namespace Hummingbird\Core;

use Hummingbird\Core\SafeMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Plugin instance.
	 *
	 * @var Settings
	 */
	private static $instance;

	/**
	 * List of available modules.
	 *
	 * @since 1.8
	 *
	 * @var array
	 */
	private static $available_modules = array(
		'minify',
		'page_cache',
		'performance',
		'uptime',
		'gravatar',
		'caching',
		'cloudflare',
		'advanced',
		'rss',
		'settings',
		'database',
		'cdn',
	);

	/**
	 * List of network modules that have settings for each sub-site.
	 *
	 * @since 1.8
	 *
	 * @var array
	 */
	private static $network_modules = array( 'caching', 'minify', 'page_cache', 'performance', 'advanced', 'cloudflare', 'cdn' );

	/**
	 * Cached default settings.
	 *
	 * @var array|null
	 */
	private static $default_settings = null;

	/**
	 * Re-entrant lock depth per lock name for the current PHP request.
	 *
	 * @var array<string,int>
	 */
	private static $lock_depth = array();

	/**
	 * Timeout in seconds for settings write mutex lock acquisition.
	 */
	private const SETTINGS_MUTEX_TIMEOUT_SECONDS = 5;

	/**
	 * Default settings option name.
	 */
	private const SETTINGS_OPTION_NAME = 'wphb_settings';

	/**
	 * Safe mode settings option name.
	 */
	private const SAFE_MODE_SETTINGS_OPTION_NAME = 'wphb_safe_mode_settings';

	/**
	 * Return the plugin instance.
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Option holding the number of pages currently in the page cache.
	 *
	 * Deliberately its own option rather than `wphb_settings` > `page_cache` > `pages_cached`.
	 * The counter is runtime state, not a setting: every cache hit rewrites it, and every write
	 * through `update_settings()` replaces the whole option. Keeping it in the settings option
	 * made any concurrent save lose the other's change - a cache hit rebuilding
	 * `wphb_settings` from the copy it loaded at request start would revert a setting saved
	 * moments earlier in another request.
	 */
	public const PAGES_CACHED_OPTION = 'wphb_page_cache_pages_cached';

	/**
	 * Settings constructor.
	 */
	private function __construct() {
	}

	/**
	 * Return the plugin default settings.
	 *
	 * @return array  Default Hummingbird settings.
	 */
	public static function get_default_settings() {
		$defaults = array(
			'minify'      => array(
				'enabled'                               => false,
				'use_cdn'                               => true,
				'delay_js'                              => false,
				'critical_css'                          => false,
				'critical_css_type'                     => 'remove',
				'critical_css_remove_type'              => 'user_interaction_with_remove',
				'critical_css_mode'                     => 'critical_css',
				'critical_page_types'                   => array(),
				'critical_skipped_custom_post_types'    => array(),
				'above_fold_load_stylesheet_method'     => 'load_stylesheet_on_user_interaction',
				'critical_css_files_exclusion'          => array(),
				'critical_css_post_urls_exclusion'      => array(),
				'critical_css_plugins_themes_exclusion' => array(),
				'critical_css_keywords'                 => array(),
				'font_optimization'                     => false,
				'preload_fonts'                         => '',
				'font_swap'                             => false,
				'font_display_value'                    => 'swap',
				'preload_fonts_mode'                    => 'automatic',
				'log'                                   => false,
				'file_path'                             => '',
				// Only for multisites. Toggles minification in a subsite
				// By default is true as if 'minify'-'enabled' is set to false, this option has no meaning.
				'minify_blog'                           => false,
				'view'                                  => 'basic', // Accepts: 'basic' or 'advanced'.
				'type'                                  => 'speedy', // Accepts: 'speedy' or 'basic'.
				'compress'                              => true,
				'combine'                               => true,
				'do_assets'                             => array( // Assets to optimize.
					'styles'  => true,
					'scripts' => true,
					'fonts'   => true,
				),
				// Only for multisite.
				'block'                                 => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'dont_minify'                           => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'dont_combine'                          => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'position'                              => array( // Move to footer.
					'scripts' => array(),
					'styles'  => array(),
				),
				'defer'                                 => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'inline'                                => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'nocdn'                                 => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'delay_js_exclusions'                   => '',
				'delay_js_exclusion_list'               => false,
				'delay_js_timeout'                      => 20,
				'fonts'                                 => array(),
				'delay_js_files_exclusion'              => array(),
				'delay_js_post_types_exclusion'         => array(),
				'delay_js_post_urls_exclusion'          => array(),
				'delay_js_plugins_themes_exclusion'     => array(),
				'delay_js_ads_tracker_exclusion'        => array(),
				'delay_js_exclude_inline_js'            => false,
				'delay_js_keywords_advanced_view'       => false,
				'preload'                               => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'async'                                 => array(
					'scripts' => array(),
					'styles'  => array(),
				),
				'ao_completed_time'                     => '',
			),
			'cdn'         => array(
				'enabled'        => false,
				'excluded_files' => array(),
				'cdn_settings'   => array(),
			),
			'uptime'      => array(
				'enabled'       => false,
				'notifications' => array(
					'enabled' => false,
				),
				'reports'       => array(
					'enabled' => false,
				),
			),
			'gravatar'    => array(
				'enabled' => false,
			),
			/*
			 * `pages_cached` used to be a default here. It is intentionally absent: the counter
			 * moved to `PAGES_CACHED_OPTION` (see the constant above). Leaving it out of the
			 * defaults is what stops `get_settings()` from re-injecting a zero counter into every
			 * read, while the migration in `Installer::migrate_pages_cached_to_own_option()` moves
			 * any legacy value across exactly once.
			 */
			'page_cache'  => array(
				'enabled'      => false,
				// Only for multisites. Toggles page caching in a subsite
				// By default is true as if 'page_cache'-'enabled' is set to false, this option has no meaning.
				'cache_blog'   => true,
				'detection'    => 'auto', // Accepts: manual, auto and none.
				'integrations' => array(
					'varnish' => false,
					'opcache' => false,
				),
				'preload'      => true,
				'preload_type' => array(
					'home_page' => true,
					'on_clear'  => false,
				),
			),
			'caching'     => array(
				// Always enabled, so no 'enabled' option.
				'expiry_css'        => '1y/A31536000',
				'expiry_javascript' => '1y/A31536000',
				'expiry_media'      => '1y/A31536000',
				'expiry_images'     => '1y/A31536000',
			),
			'cloudflare'  => array(
				'enabled'      => false,
				'connected'    => false,
				'last_check'   => false,
				'email'        => '',
				'api_key'      => '',
				'account_id'   => '',
				'zone'         => '',
				'zone_name'    => '',
				'plan'         => false,
				'page_rules'   => array(),
				'cache_expiry' => 31536000,
				'apo_paid'     => false,
				'apo'          => array(),
			),
			'performance' => array(
				'reports'       => array(
					'enabled' => false,
				),
				'subsite_tests' => true,
				'dismissed'     => false,
			),
			'advanced'    => array(
				'query_string'         => false,
				'query_strings_global' => false, // If true, will force query_string on all subsites.
				'emoji'                => false,
				// A revision count, stored as the string the Tools tab writes. The boolean the
				// other toggles default to would put `false` on one side of a comparison and
				// `'0'` on the other, for the same "keep none" state.
				'post_revisions'       => '0',
				'viewport_meta'        => true,
				'emoji_global'         => false, // If true, will force emoji on all subsites.
				'prefetch'             => array(),
				'preconnect'           => array(),
				'cart_fragments'       => false,
				'lazy_load'            => array(
					'enabled'   => false,
					'method'    => 'click',
					'button'    => array(
						'dimensions' => array(
							'height'         => 0,
							'width'          => 0,
							'radius'         => 0,
							'padding_top'    => 0,
							'padding_right'  => 0,
							'padding_bottom' => 0,
							'padding_left'   => 0,
						),
						'color'      => array(
							'background' => '',
							'border'     => '',
							'hover'      => '',
							'text'       => '',
							'hover_text' => '',
							'visibility' => array(
								'background' => true,
								'border'     => true,
								'text'       => true,
								'hover'      => true,
								'hover_text' => true,
							),
						),
						'alignment'  => array(
							'align'      => 'center',
							'full_width' => 'on',
							'left'       => 0,
							'right'      => 0,
							'top'        => 0,
							'bottom'     => 0,
						),
					),
					'threshold' => 10,
					'preload'   => false,
				),
			),
			'rss'         => array(
				'enabled'  => true,
				'duration' => 3600,
			),
			'settings'    => array(
				'accessible_colors' => false,
				'remove_settings'   => false,
				'remove_data'       => false,
				'tracking'          => false,
				'control'           => false, // Cache control in admin bar.
				'control_modules'   => array(),
			),
			'database'    => array(
				'reports' => array(
					'enabled' => false,
				),
			),
		);

		/**
		 * Filter the default settings.
		 * Useful when adding new settings to the plugin
		 */
		return apply_filters( 'wp_hummingbird_default_options', $defaults );
	}

	/**
	 * Array of settings per sub-site.
	 *
	 * @access private
	 *
	 * @param string $module  Module for to get sub site setting fields for.
	 *
	 * @return array
	 */
	private static function get_blog_option_names( $module ) {
		if ( ! in_array( $module, self::$network_modules, true ) ) {
			return array();
		}

		$options = array(
			'caching'     => array( 'expiry_css', 'expiry_javascript', 'expiry_media', 'expiry_images' ),
			'minify'      => array( 'minify_blog', 'view', 'type', 'combine', 'compress', 'do_assets', 'block', 'dont_minify', 'dont_combine', 'position', 'defer', 'inline', 'nocdn', 'fonts', 'preload', 'async', 'ao_completed_time', 'delay_js', 'delay_js_exclusions', 'delay_js_exclusion_list', 'delay_js_timeout', 'delay_js_files_exclusion', 'delay_js_post_types_exclusion', 'delay_js_post_urls_exclusion', 'delay_js_plugins_themes_exclusion', 'delay_js_ads_tracker_exclusion', 'delay_js_keywords_advanced_view', 'delay_js_exclude_inline_js', 'critical_css', 'critical_css_type', 'critical_css_remove_type', 'critical_css_mode', 'critical_page_types', 'critical_skipped_custom_post_types', 'font_optimization', 'above_fold_load_stylesheet_method', 'critical_css_files_exclusion', 'critical_css_post_urls_exclusion', 'critical_css_plugins_themes_exclusion', 'critical_css_keywords', 'preload_fonts', 'font_swap', 'font_display_value', 'preload_fonts_mode' ),
			'page_cache'  => array( 'cache_blog' ),
			'performance' => array( 'dismissed', 'reports' ),
			'advanced'    => array( 'query_string', 'viewport_meta', 'emoji', 'post_revisions', 'prefetch', 'preconnect', 'cart_fragments' ),
			'cloudflare'  => array( 'enabled', 'connected', 'last_check', 'email', 'api_key', 'account_id', 'zone', 'zone_name', 'plan', 'page_rules', 'cache_expiry', 'apo_paid', 'apo' ),
			'cdn'         => array( 'enabled', 'excluded_files' ),
		);

		return $options[ $module ];
	}

	/**
	 * Filter out sub site options from network options on multisite.
	 *
	 * @access private
	 *
	 * @param array $options  Options array.
	 *
	 * @return array
	 */
	private static function filter_multisite_options( $options ) {
		$network_options = array();
		$blog_options    = array();

		foreach ( $options as $module => $setting ) {
			/*
			 * Skip if module is not registered.
			 * Only needed in case an update to 1.8 manually by replacing the files.
			 */
			if ( ! in_array( $module, self::$available_modules, true ) ) {
				continue;
			}

			$data = array_fill_keys( self::get_blog_option_names( $module ), self::get_blog_option_names( $module ) );

			$network_options[ $module ] = array_diff_key( $setting, $data );
			$blog_options[ $module ]    = array_intersect_key( $setting, $data );
		}

		// array_filter will remove all empty values.
		return array(
			'network' => $network_options,
			'blog'    => array_filter( $blog_options ),
		);
	}

	/**
	 * Reset database to default settings. Will overwrite all current settings.
	 * This can be moved out to update_settings, because it's almost identical.
	 */
	public static function reset_to_defaults() {
		$defaults = self::get_default_settings();

		if ( ! is_multisite() ) {
			update_option( self::get_setting_name(), $defaults );
		} else {
			$options = self::filter_multisite_options( $defaults );
			update_site_option( self::get_setting_name(), $options['network'] );
			update_option( self::get_setting_name(), $options['blog'] );
		}
	}

	/**
	 * Return the plugin settings.
	 *
	 * @param bool|string $for_module  Module to fetch options for.
	 *
	 * @return array  Hummingbird settings.
	 * @param bool|string $force_setting_name  Force a specific setting name.
	 */
	public static function get_settings( $for_module = false, $force_setting_name = false ) {
		$setting_name = $force_setting_name ? $force_setting_name : self::get_setting_name();
		if ( ! is_multisite() ) {
			$options = get_option( $setting_name, array() );
		} else {
			$blog_options    = get_option( $setting_name, array() );
			$network_options = get_site_option( $setting_name, array() );
			$options         = array_merge_recursive( $blog_options, $network_options );
		}

		$defaults = self::get_default_settings();

		// We need to parse each module individually.
		foreach ( $defaults as $module => $option ) {
			// If there is nothing set in the current option, we use the default set.
			if ( ! isset( $options[ $module ] ) ) {
				$options[ $module ] = $option;
				continue;
			}
			// Else we combine defaults with current options.
			$options[ $module ] = wp_parse_args( $options[ $module ], $option );
		}

		if ( $for_module ) {
			return apply_filters( "wphb_get_settings_for_module_$for_module", $options[ $for_module ] );
		}

		return $options;
	}

	/**
	 * Update the plugin settings.
	 *
	 * @param array       $new_settings  New settings.
	 * @param bool|string $for_module    Module to update settings for.
	 * @param bool|string $force_setting_name  Force a specific setting name.
	 */
	public static function update_settings( $new_settings, $for_module = false, $force_setting_name = false ) {
		$setting_name = $force_setting_name ? $force_setting_name : self::get_setting_name();

		self::with_settings_write_lock(
			$setting_name,
			function () use ( $new_settings, $for_module, $setting_name ) {
				$settings_to_persist = $new_settings;

				if ( $for_module ) {
					$options                       = self::get_settings( false, $setting_name );
					$options[ $for_module ]        = $settings_to_persist;
					$settings_to_persist           = $options;
				}

				self::persist_settings( $settings_to_persist, $setting_name );
			}
		);
	}

	/**
	 * Get setting.
	 *
	 * @param string      $option_name  Return a single WP Hummingbird setting.
	 * @param bool|string $for_module   Module to fetch options for.
	 *
	 * @return mixed
	 */
	public static function get_setting( $option_name, $for_module = false ) {
		$options = self::get_settings( $for_module );

		if ( ! isset( $options[ $option_name ] ) ) {
			return '';
		}

		/**
		 * Failsafe for when options are stored incorrectly.
		 */
		$defaults = self::get_default_settings();
		if ( $for_module ) {
			$defaults = $defaults[ $for_module ];
		}

		if ( self::is_exception( $for_module, $options, $option_name ) ) {
			return $options[ $option_name ];
		}

		if ( gettype( $defaults[ $option_name ] ) !== gettype( $options[ $option_name ] ) ) {
			self::update_setting( $option_name, $defaults[ $option_name ], $for_module );
			return $defaults[ $option_name ];
		}

		return $options[ $option_name ];
	}

	/**
	 * Check if setting has an exception.
	 *
	 * In get_settings we compare the values to defaults (including value type).
	 * Two options can be bool/string: minify -> enabled and page_cache -> enabled.
	 *
	 * @since 1.8.1
	 *
	 * @param string $module       Module.
	 * @param array  $options      Options.
	 * @param string $option_name  Option name.
	 *
	 * @return bool
	 */
	private static function is_exception( $module, $options, $option_name ) {
		$exceptions = array(
			'minify'      => 'super-admins',
			'page_cache'  => 'blog-admins',
			'performance' => 'super-admins',
		);

		// Cache control in Settings can be an array or boolean.
		if ( 'settings' === $module && 'control' === $option_name && is_array( $options[ $option_name ] ) ) {
			return true;
		}

		if ( isset( $exceptions[ $module ] ) && $exceptions[ $module ] === $options[ $option_name ] ) {
			return true;
		}

		return false;
	}

	/**
	 * Update selected plugin setting.
	 *
	 * @param string      $option_name  Setting name.
	 * @param mixed       $value        Setting value.
	 * @param bool|string $for_module   Module to update settings for.
	 */
	public static function update_setting( $option_name, $value, $for_module = false ) {
		$setting_name = self::get_setting_name();

		self::with_settings_write_lock(
			$setting_name,
			function () use ( $option_name, $value, $for_module, $setting_name ) {
				if ( $for_module ) {
					$options                 = self::get_settings( false, $setting_name );
					$module_options          = isset( $options[ $for_module ] ) ? $options[ $for_module ] : array();
					$module_options[ $option_name ] = $value;
					$options[ $for_module ]  = $module_options;
					self::persist_settings( $options, $setting_name );
					return;
				}

				$options                 = self::get_settings( false, $setting_name );
				$options[ $option_name ] = $value;
				self::persist_settings( $options, $setting_name );
			}
		);
	}

	/**
	 * Persist the provided settings payload.
	 *
	 * @param array  $new_settings  Settings payload to persist.
	 * @param string $setting_name  Settings option name.
	 */
	private static function persist_settings( $new_settings, $setting_name ) {
		if ( self::SAFE_MODE_SETTINGS_OPTION_NAME === $setting_name ) {
			$safemode_settings = SafeMode::filter_options( $new_settings );
			foreach ( $safemode_settings as $module => $settings ) {
				$new_settings[ $module ] = wp_parse_args( $settings, $new_settings[ $module ] );
			}
		}

		if ( ! is_multisite() ) {
			update_option( $setting_name, $new_settings );
		} else {
			$options = self::filter_multisite_options( $new_settings );
			update_site_option( $setting_name, $options['network'] );
			update_option( $setting_name, $options['blog'] );
		}
	}

	/**
	 * Execute settings write operation under a short MySQL advisory lock.
	 *
	 * @param string   $setting_name  Settings option name.
	 * @param callable $callback      Write operation to execute.
	 *
	 * @return mixed
	 */
	private static function with_settings_write_lock( $setting_name, $callback ) {
		$lock_name = self::get_settings_lock_name( $setting_name );

		if ( isset( self::$lock_depth[ $lock_name ] ) ) {
			++self::$lock_depth[ $lock_name ];
			try {
				return $callback();
			} finally {
				--self::$lock_depth[ $lock_name ];
			}
		}

		$mutex = new Mutex( $lock_name );
		$mutex->set_timeout( self::SETTINGS_MUTEX_TIMEOUT_SECONDS )->set_break_on_timeout( true );
		$result = null;
		$ran    = false;

		self::$lock_depth[ $lock_name ] = 1;

		try {
			$mutex->execute(
				function () use ( $callback, &$result, &$ran ) {
					$ran    = true;
					$result = $callback();
				}
			);
		} finally {
			unset( self::$lock_depth[ $lock_name ] );
		}

		if ( ! $ran ) {
			return null;
		}

		return $result;
	}

	/**
	 * Build a MySQL advisory lock name scoped to this site and setting name.
	 *
	 * @param string $setting_name  Settings option name.
	 *
	 * @return string
	 */
	private static function get_settings_lock_name( $setting_name ) {
		$site_scope = is_multisite() ? get_current_blog_id() . ':' . get_current_network_id() : 'single';
		$hash       = md5( $setting_name . '|' . $site_scope );

		return 'wphb_settings_' . $hash;
	}

	/**
	 * Return a single WP Hummingbird option.
	 *
	 * @param string $option   Option.
	 * @param mixed  $default  Optional. Default value to return if the option does not exist.
	 *
	 * @return mixed
	 */
	public static function get( $option, $default = false ) {
		if ( ! is_main_site() ) {
			$value = get_option( $option, $default );
		} else {
			$value = get_site_option( $option, $default );
		}

		return $value;
	}

	/**
	 * Delete a single WP Hummingbird option.
	 *
	 * @param string $option  Option.
	 */
	public static function delete( $option ) {
		if ( ! is_main_site() ) {
			delete_option( $option );
		} else {
			delete_site_option( $option );
		}
	}

	/**
	 * Update option.
	 *
	 * @param string      $option   WP Hummingbird option name.
	 * @param mixed       $value    WP Hummingbird option value.
	 * @param string|bool $autoload Optional. Whether to load the option when WordPress starts up. For existing options,
	 *                              `$autoload` can only be updated using `update_option()` if `$value` is also changed.
	 *                              Accepts 'yes'|true to enable or 'no'|false to disable. For non-existent options,
	 *                              the default value is 'yes'. Default null.
	 */
	public static function update( $option, $value, $autoload = null ) {
		if ( ! is_main_site() ) {
			update_option( $option, $value, $autoload );
		} else {
			update_site_option( $option, $value );
		}
	}

	/**
	 * Get setting name based on safe mode and context.
	 *
	 * @return string The appropriate settings option name.
	 */
	public static function get_setting_name(): string {
		if ( class_exists( SafeMode::class ) && SafeMode::instance()->is_safemode_call() ) {
			return self::SAFE_MODE_SETTINGS_OPTION_NAME;
		}

		return self::SETTINGS_OPTION_NAME;
	}
}
