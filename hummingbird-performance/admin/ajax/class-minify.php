<?php
/**
 * Asset Optimization AJAX actions.
 *
 * @since 2.7.2
 * @package Hummingbird\Admin\Ajax\Caching
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Modules\Minify\Fonts;
use Hummingbird\Core\Modules\Minify\Minify_Group;
use Hummingbird\Core\Modules\Minify\Sources_Collector;
use Hummingbird\Core\Modules\Page_Cache;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Whitelabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Minify.
 */
class Minify {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'asset-optimization';

	/**
	 * Minify constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'minify_toggle_status',
			'minify_auto_status',
			'minify_clear_cache',
			'minify_recheck_files',
			'minify_reset_settings',
			'minify_auto_save_settings',
			'minify_manual_save_settings',
			'minify_manual_status',
			'minify_regenerate_asset',
			'minify_start_ao_scan',
			'minify_background_processing_status',
			'minify_delay_js_status',
			'minify_delay_js_save_settings',
			'minify_critical_css_status',
			'minify_critical_css_save_settings',
			'minify_font_optimization_status',
			'minify_font_optimization_save_settings',
			'minify_critical_css_regenerate',
			'minify_search_posts',
			'minify_delay_js_exclusion_options',
			'minify_delay_js_save_exclusions',
			'minify_critical_css_exclusion_options',
			'minify_critical_css_save_exclusions',
			'minify_save_network_settings',
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Register callbacks.
			 *
			 * @uses minify_toggle_status()
			 * @uses minify_auto_status()
			 * @uses minify_clear_cache()
			 * @uses minify_recheck_files()
			 * @uses minify_reset_settings()
			 * @uses minify_auto_save_settings()
			 * @uses minify_manual_save_settings()
			 * @uses minify_manual_status()
			 * @uses minify_regenerate_asset()
			 * @uses minify_start_ao_scan()
			 * @uses minify_background_processing_status()
			 * @uses minify_delay_js_status()
			 * @uses minify_delay_js_save_settings()
			 * @uses minify_critical_css_status()
			 * @uses minify_critical_css_save_settings()
			 * @uses minify_critical_css_regenerate()
			 * @uses minify_font_optimization_status()
			 * @uses minify_font_optimization_save_settings()
			 * @uses minify_search_posts()
			 * @uses minify_delay_js_exclusion_options()
			 * @uses minify_delay_js_save_exclusions()
			 * @uses minify_critical_css_exclusion_options()
			 * @uses minify_critical_css_save_exclusions()
			 * @uses minify_save_network_settings()
			 */
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	/**
	 * Ensure the current user is allowed to configure Asset Optimization.
	 */
	private function ensure_asset_optimization_permission() {
		$options = Utils::get_module( 'minify' )->get_options();
		$allowed = ! is_multisite()
			|| true === $options['enabled']
			|| ( 'super-admins' === $options['enabled'] && is_super_admin() );

		if ( ! $allowed ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to configure Asset Optimization.', 'wphb' ),
				),
				403
			);
		}
	}

	/**
	 * Switch the Asset Optimization module on or off.
	 *
	 * There's no standalone Asset Optimization toggle in the UI anymore -
	 * Compress/Combine's own activity log entries already cover what the
	 * user actually changed, so this doesn't log anything itself.
	 *
	 * @since 4.0.0
	 *
	 * @param bool $enable         Whether the module should be on.
	 * @param bool $reset_settings When switching off, also reset exclusions and per-file settings.
	 */
	private function set_ao_status( $enable, $reset_settings = true ) {
		$minify     = Utils::get_module( 'minify' );
		$was_active = $minify->is_active();

		if ( $enable ) {
			// No scan here: files are collected as visitors load pages, and
			// "Re-check" scans on demand.
			$minify->toggle_service( true );
		} else {
			$minify->disable( true, $reset_settings );
		}

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_module_status_change( $was_active, $minify->is_active() );
	}

	/**
	 * Enable/disable the Asset Optimization module as a whole.
	 *
	 * Mirrors the legacy `?enable=1`/`?disable=1` page-redirect flow in
	 * Admin\Pages\Minification::on_load() as an AJAX action, so the React
	 * page's master toggle doesn't need a full page reload.
	 *
	 * @since 4.0.0
	 */
	public function minify_toggle_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$value = filter_input( INPUT_POST, 'data', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		$this->set_ao_status( (bool) $value );

		wp_send_json_success(
			array(
				'enabled' => $value,
			)
		);
	}

	/**
	 * Get asset optimization status.
	 *
	 * @since 2.7.2
	 */
	public function minify_auto_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Utils::get_module( 'minify' )->get_auto_optimize_settings() );
	}

	/**
	 * Fetch/refresh asset optimization status.
	 *
	 * @since 2.7.2
	 */
	public function minify_clear_cache() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_module( 'minify' )->clear_cache( false );
		Utils::get_module( 'critical_css' )->regenerate_critical_css();

		if ( Utils::get_module( 'critical_css' )->is_active() ) {
			Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_critical_css_cache_purge();
		}

		wp_send_json_success(
			array(
				'isCriticalActive' => Utils::get_module( 'critical_css' )->is_active(),
			)
		);
	}

	/**
	 * Re-check files.
	 *
	 * @since 2.7.2
	 */
	public function minify_recheck_files() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		Utils::get_module( 'minify' )->clear_cache( false );

		Sources_Collector::clear_collection();

		// Activate minification if is not.
		$was_active = Utils::get_module( 'minify' )->is_active();
		Utils::get_module( 'minify' )->toggle_service( true );
		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_module_status_change( $was_active, Utils::get_module( 'minify' )->is_active() );
		Utils::get_module( 'minify' )->scanner->init_scan();

		wp_send_json_success();
	}

	/**
	 * Reset asset optimization settings.
	 *
	 * @since 2.7.2
	 */
	public function minify_reset_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$options = Utils::get_module( 'minify' )->get_options();

		$defaults = Settings::get_default_settings();

		$options['do_assets']    = $defaults['minify']['do_assets'];
		$options['dont_minify']  = $defaults['minify']['dont_minify'];
		$options['dont_combine'] = $defaults['minify']['dont_combine'];
		$options['fonts']        = $defaults['minify']['fonts'];

		Utils::get_module( 'minify' )->update_options( $options );
		Utils::get_module( 'minify' )->clear_cache( false );

		wp_send_json_success();
	}

	/**
	 * Save asset optimization settings.
	 *
	 * @since 2.7.2
	 */
	public function minify_auto_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$this->ensure_asset_optimization_permission();

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$options  = Utils::get_module( 'minify' )->get_options();
		$previous = $options;

		// Update compress and combine settings.
		$compress_changed = false;
		$combine_changed  = false;

		if ( isset( $settings['compress'] ) ) {
			$compress_changed    = $options['compress'] !== $settings['compress'];
			$options['compress'] = $settings['compress'];
		}

		if ( isset( $settings['combine'] ) ) {
			$combine_changed    = $options['combine'] !== $settings['combine'];
			$options['combine'] = $settings['combine'];
		}

		// Process font optimization changes.
		$fonts_disabled               = isset( $settings['fonts'] ) && false === filter_var( $settings['fonts'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$options['do_assets']['fonts'] = ! $fonts_disabled && $settings['combine'];
		if ( false === $options['do_assets']['fonts'] ) {
			$options['fonts'] = array();
		}

		$collections = Sources_Collector::get_collection();

		foreach ( array( 'scripts', 'styles' ) as $type ) {
			$new_value = ! ( isset( $settings[ $type ] ) && false === $settings[ $type ] );

			$remove_exclusions = true === $new_value && false === $options['do_assets'][ $type ];

			// Save the type selection.
			$options['do_assets'][ $type ] = $new_value;

			// By default, we minify and combine everything.
			$options['dont_minify'][ $type ] = array();
			if ( $settings['combine'] ) {
				$options['dont_combine'][ $type ] = array();
			} else {
				$options['dont_combine'][ $type ] = array_keys( $collections[ $type ] );
			}

			// At this point we have no setting field? Weird, let's skip further processing.
			if ( ! isset( $settings[ $type ] ) ) {
				continue;
			}

			$handles = array();
			if ( false === $options['do_assets'][ $type ] ) {
				// If an option (CSS/JS) is disabled, put all handles in the "don't do" list.
				$handles = array_keys( $collections[ $type ] );
			} elseif ( ! $remove_exclusions && count( $settings['exclusions'][ $type ] ) !== count( $collections[ $type ] ) ) {
				// If the exclusion does not have all the assets, exclude the selected ones.
				$handles = $settings['exclusions'][ $type ];
			}

			$options['dont_minify'][ $type ] = $handles;
			// We've already excluded all the handles for compress-only mode above.
			if ( $settings['combine'] ) {
				$options['dont_combine'][ $type ] = $handles;
			}
		}

		Utils::get_module( 'minify' )->update_options( $options );

		// After we've updated the options - process fonts.
		if ( true === $options['do_assets']['fonts'] ) {
			do_action( 'wphb_process_fonts' );
		}

		Utils::get_module( 'minify' )->clear_cache( false );

		// Compress and Combine replace the Asset Optimization main toggle: the
		// module is on while either of them is. Switching off keeps exclusions
		// and file settings, so turning one back on picks up where it left off.
		$was_active = (bool) Utils::get_module( 'minify' )->is_active();
		$ao_enabled = ! empty( $options['compress'] ) || ! empty( $options['combine'] );
		if ( $was_active !== $ao_enabled ) {
			$this->set_ao_status( $ao_enabled, false );
		}

		// Compress/Combine/Fonts only really take effect while the module itself
		// is active - fresh installs (and sites that never touched Asset
		// Optimization) already store these as on in the database, so compare
		// them gated by the module's active state rather than the raw stored
		// value. Otherwise the very first save (switching the module on) would
		// both miss the "enabled" entry for the field that was actually toggled
		// and log spurious "disabled" entries for the others.
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_active && ( isset( $previous['compress'] ) ? (bool) $previous['compress'] : false ),
			$ao_enabled && ( isset( $options['compress'] ) ? (bool) $options['compress'] : false ),
			__( 'Compressed CSS/JS files enabled.', 'wphb' ),
			__( 'Compressed CSS/JS files disabled.', 'wphb' )
		);

		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_active && ( isset( $previous['combine'] ) ? (bool) $previous['combine'] : false ),
			$ao_enabled && ( isset( $options['combine'] ) ? (bool) $options['combine'] : false ),
			__( 'Combined CSS/JS files enabled.', 'wphb' ),
			__( 'Combined CSS/JS files disabled.', 'wphb' )
		);

		$file_type_messages = array(
			'styles' => array(
				__( 'CSS files chosen to optimize automatically were enabled.', 'wphb' ),
				__( 'CSS files chosen to optimize automatically were disabled.', 'wphb' ),
			),
			'scripts' => array(
				__( 'JS files chosen to optimize automatically were enabled.', 'wphb' ),
				__( 'JS files chosen to optimize automatically were disabled.', 'wphb' ),
			),
		);
		foreach ( $file_type_messages as $key => $messages ) {
			Activity_Log::record_toggle_when_changed(
				self::ACTIVITY_MODULE,
				isset( $previous['do_assets'][ $key ] ) ? (bool) $previous['do_assets'][ $key ] : false,
				isset( $options['do_assets'][ $key ] ) ? (bool) $options['do_assets'][ $key ] : false,
				$messages[0],
				$messages[1]
			);
		}

		// Fonts are additionally gated by Combine itself - `do_assets.fonts` is
		// only ever saved as true alongside it (see above) - so compare it the
		// same active-gated way as Compress/Combine rather than the raw stored
		// value.
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$was_active && ( isset( $previous['do_assets']['fonts'] ) ? (bool) $previous['do_assets']['fonts'] : false ),
			$ao_enabled && ( isset( $options['do_assets']['fonts'] ) ? (bool) $options['do_assets']['fonts'] : false ),
			__( 'Font files chosen to optimize automatically were enabled.', 'wphb' ),
			__( 'Font files chosen to optimize automatically were disabled.', 'wphb' )
		);

		// set_ao_status() above reports the module switching on or off; this reports a Compress/Combine change while it stays on.
		if ( $was_active === $ao_enabled ) {
			Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_mode_change( $previous, $options );
		}

		$settings_changed_message = false;
		if ( $compress_changed || $combine_changed ) {
			if ( $settings['combine'] ) {
				$optimization_mode = __( 'Combine and Compress optimizations are', 'wphb' );
			} elseif ( $settings['compress'] ) {
				$optimization_mode = __( 'Compress optimization is', 'wphb' );
			} else {
				$optimization_mode = __( 'None optimization is', 'wphb' );
			}

			$settings_changed_message = sprintf( /* translators: %1$s - optimization mode, %2$s - opening <a> tag, %3$s - closing </a> tag */
				esc_html__( '%1$s active. Plugins and theme files are now being queued for processing and will gradually be optimized as they are requested by your visitors. For more information on how automatic optimization works, you can check %2$sHow Does It Work%3$s section.', 'wphb' ),
				$optimization_mode,
				sprintf(
					"<a href='%s' target='_blank' >",
					esc_url( Utils::get_documentation_url( 'wphb-minification' ) )
				),
				'</a>'
			);
		}

		list( $excluded_styles, $excluded_scripts ) = Utils::get_module( 'minify' )->get_auto_exclusions( $options );

		wp_send_json_success(
			array(
				'assets'     => $collections,
				'mode'       => Utils::get_minification_mode(),
				'enabled'    => array(
					'styles'  => $options['do_assets']['styles'],
					'scripts' => $options['do_assets']['scripts'],
					'fonts'   => $options['do_assets']['fonts'],
				),
				'exclusions' => array(
					'styles'  => $excluded_styles,
					'scripts' => $excluded_scripts,
				),
				'compress'   => $options['compress'],
				'combine'    => $options['combine'],
				'notice'     => ( $settings['compress'] || $settings['combine'] ) && $settings_changed_message ? $settings_changed_message : false,
			)
		);
	}

	/**
	 * Get Delay JS settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_delay_js_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Utils::get_module( 'delayjs' )->get_react_settings() );
	}

	/**
	 * Save Delay JS settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_delay_js_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$this->ensure_asset_optimization_permission();

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$minify   = Utils::get_module( 'minify' );
		$options  = $minify->get_options();
		$previous = $options;

		$delay_js_changed = false;

		if ( isset( $settings['delay_js'] ) ) {
			$delay_js_changed    = $options['delay_js'] !== (bool) $settings['delay_js'];
			$options['delay_js'] = (bool) $settings['delay_js'];
		}

		if ( isset( $settings['delay_js_timeout'] ) ) {
			$options['delay_js_timeout'] = (int) $settings['delay_js_timeout'];
		}

		if ( isset( $settings['delay_js_exclude_inline_js'] ) ) {
			$options['delay_js_exclude_inline_js'] = (bool) $settings['delay_js_exclude_inline_js'];
		}

		$minify->update_options( $options );
		$minify->clear_cache( false );

		if ( $delay_js_changed ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				$options['delay_js']
					? __( 'Delay JavaScript enabled.', 'wphb' )
					: __( 'Delay JavaScript disabled.', 'wphb' )
			);
		}

		if ( isset( $previous['delay_js_timeout'] ) && isset( $options['delay_js_timeout'] ) && (int) $previous['delay_js_timeout'] !== (int) $options['delay_js_timeout'] ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %d: delay timeout in seconds. */
					__( 'Delay JavaScript timeout changed to %d seconds.', 'wphb' ),
					(int) $options['delay_js_timeout']
				)
			);
		}

		if ( isset( $previous['delay_js_exclude_inline_js'] ) && isset( $options['delay_js_exclude_inline_js'] ) && (bool) $previous['delay_js_exclude_inline_js'] !== (bool) $options['delay_js_exclude_inline_js'] ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				$options['delay_js_exclude_inline_js']
					? __( 'Excluded inline JavaScript from delay.', 'wphb' )
					: __( 'Included inline JavaScript in delay.', 'wphb' )
			);
		}

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_delay_js_change( $previous, $options );

		wp_send_json_success(
			array(
				'delay_js'                   => $options['delay_js'],
				'delay_js_timeout'           => $options['delay_js_timeout'],
				'delay_js_exclude_inline_js' => $options['delay_js_exclude_inline_js'],
			)
		);
	}

	/**
	 * Get Critical CSS settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_critical_css_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Utils::get_module( 'critical_css' )->get_react_settings() );
	}

	/**
	 * Save Critical CSS settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_critical_css_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$this->ensure_asset_optimization_permission();

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$minify   = Utils::get_module( 'minify' );
		$options  = $minify->get_options();
		$previous = $options;

		$critical_css_changed = false;

		if ( isset( $settings['critical_css'] ) ) {
			$critical_css_changed    = $options['critical_css'] !== (bool) $settings['critical_css'];
			$options['critical_css'] = (bool) $settings['critical_css'];

			// Upgraded sites can still store the old '' default (or 'manual_css'):
			// like toggle_critical_css(), enabling switches to generated Critical CSS.
			if ( $options['critical_css'] ) {
				$options['critical_css_mode'] = 'critical_css';
			}
		}

		if ( isset( $settings['critical_css_type'] ) ) {
			$options['critical_css_type'] = sanitize_key( $settings['critical_css_type'] );
		}

		if ( isset( $settings['critical_css_remove_type'] ) ) {
			$options['critical_css_remove_type'] = sanitize_key( $settings['critical_css_remove_type'] );
		}

		if ( isset( $settings['above_fold_load_stylesheet_method'] ) ) {
			$options['above_fold_load_stylesheet_method'] = sanitize_key( $settings['above_fold_load_stylesheet_method'] );
		}

		$minify->update_options( $options );

		// Full page and above the fold generate different CSS, so switching
		// between them regenerates (as in 3.x); the other loading options only
		// change how pages print the existing CSS.
		$type_changed = $previous['critical_css_type'] !== $options['critical_css_type'];

		if ( $options['critical_css'] && ( $type_changed || ! empty( $settings['regenerate'] ) ) ) {
			Utils::get_module( 'critical_css' )->regenerate_critical_css();
		}

		if ( $critical_css_changed ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				$options['critical_css']
					? __( 'Critical CSS enabled.', 'wphb' )
					: __( 'Critical CSS disabled.', 'wphb' )
			);
		}

		if ( isset( $previous['critical_css_type'] ) && $previous['critical_css_type'] !== $options['critical_css_type'] ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				'remove' === $options['critical_css_type']
					? __( 'Full page critical CSS enabled.', 'wphb' )
					: __( 'Above-the-fold critical CSS enabled.', 'wphb' )
			);
		}

		if ( isset( $previous['critical_css_remove_type'] ) && $previous['critical_css_remove_type'] !== $options['critical_css_remove_type'] ) {
			$full_page_labels = array(
				'user_interaction_with_remove' => __( 'Load on interaction', 'wphb' ),
				'remove_unused'                => __( 'Remove unused CSS', 'wphb' ),
			);
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: optimization mode label. */
					__( 'Full page critical CSS optimization set to %s.', 'wphb' ),
					$full_page_labels[ $options['critical_css_remove_type'] ] ?? $options['critical_css_remove_type']
				)
			);
		}

		if ( isset( $previous['above_fold_load_stylesheet_method'] ) && $previous['above_fold_load_stylesheet_method'] !== $options['above_fold_load_stylesheet_method'] ) {
			$above_fold_labels = array(
				'load_stylesheet_on_user_interaction' => __( 'Load on user interaction', 'wphb' ),
				'load_stylesheet_async'               => __( 'Load asynchronously', 'wphb' ),
			);
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: above-the-fold loading method label. */
					__( 'Above-the-fold critical CSS set to %s.', 'wphb' ),
					$above_fold_labels[ $options['above_fold_load_stylesheet_method'] ] ?? $options['above_fold_load_stylesheet_method']
				)
			);
		}

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_critical_css_change( $previous, $options );

		wp_send_json_success( Utils::get_module( 'critical_css' )->get_react_settings() );
	}

	/**
	 * Discard the generated Critical CSS and queue a fresh run.
	 *
	 * @since 4.0.0
	 */
	public function minify_critical_css_regenerate() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$critical_css = Utils::get_module( 'critical_css' );

		$critical_css->regenerate_critical_css();

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_critical_css_cache_purge();

		wp_send_json_success( $critical_css->get_react_settings() );
	}

	/**
	 * Get Font Optimization settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_font_optimization_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Fonts::get_react_settings() );
	}

	/**
	 * Save Font Optimization settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_font_optimization_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$this->ensure_asset_optimization_permission();

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$minify   = Utils::get_module( 'minify' );
		$options  = $minify->get_options();
		$previous = $options;

		$font_optimization_changed = false;

		if ( isset( $settings['font_optimization'] ) ) {
			$font_optimization_changed    = $options['font_optimization'] !== (bool) $settings['font_optimization'];
			$options['font_optimization'] = (bool) $settings['font_optimization'];
		}

		if ( isset( $settings['preload_fonts'] ) ) {
			$preload_fonts = self::parse_font_files( $settings['preload_fonts'] );
			if ( null !== $preload_fonts ) {
				$options['preload_fonts'] = $preload_fonts;
			}
		}

		if ( isset( $settings['font_swap'] ) ) {
			$options['font_swap'] = (bool) $settings['font_swap'];
		}

		if ( isset( $settings['font_display_value'] ) ) {
			$options['font_display_value'] = sanitize_key( $settings['font_display_value'] );
		}

		// Free sites are restricted to manual preload mode (matches
		// Minification::on_load()'s enforcement for the legacy page).
		if ( isset( $settings['preload_fonts_mode'] ) ) {
			$options['preload_fonts_mode'] = Utils::is_member() ? sanitize_key( $settings['preload_fonts_mode'] ) : 'manual';
		}

		$minify->update_options( $options );

		// Preload tags / font-display CSS are rendered per-request, so a
		// page-cache clear (not a minify-files clear) is what's needed here.
		Utils::get_module( 'page_cache' )->clear_cache();

		if ( $font_optimization_changed ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				$options['font_optimization']
					? __( 'Preload Fonts enabled.', 'wphb' )
					: __( 'Preload Fonts disabled.', 'wphb' )
			);
		}

		$previous_swap_mode = $this->get_font_swap_mode( $previous );
		$current_swap_mode  = $this->get_font_swap_mode( $options );

		if ( $previous_swap_mode !== $current_swap_mode ) {
			Activity_Log::record_toggle_when_changed(
				self::ACTIVITY_MODULE,
				'off' !== $previous_swap_mode,
				'off' !== $current_swap_mode,
				__( 'Swap web fonts enabled.', 'wphb' ),
				__( 'Swap web fonts disabled.', 'wphb' )
			);

			if ( 'safe' === $previous_swap_mode || 'safe' === $current_swap_mode ) {
				Activity_Log::record_toggle_when_changed(
					self::ACTIVITY_MODULE,
					'safe' === $previous_swap_mode,
					'safe' === $current_swap_mode,
					__( 'Safe web fonts enabled.', 'wphb' ),
					__( 'Safe web fonts disabled.', 'wphb' )
				);
			}

			if ( 'performant' === $previous_swap_mode || 'performant' === $current_swap_mode ) {
				Activity_Log::record_toggle_when_changed(
					self::ACTIVITY_MODULE,
					'performant' === $previous_swap_mode,
					'performant' === $current_swap_mode,
					__( 'Performant web fonts enabled.', 'wphb' ),
					__( 'Performant web fonts disabled.', 'wphb' )
				);
			}
		}

		if ( isset( $previous['preload_fonts_mode'] ) && isset( $options['preload_fonts_mode'] ) && $previous['preload_fonts_mode'] !== $options['preload_fonts_mode'] ) {
			$preload_mode_label = 'automatic' === $options['preload_fonts_mode']
				? __( 'Automatic', 'wphb' )
				: __( 'Manual', 'wphb' );
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %s: preload mode. */
					__( 'Preload fonts mode set to %s.', 'wphb' ),
					$preload_mode_label
				)
			);
		}

		Activity_Log::record_when_changed(
			self::ACTIVITY_MODULE,
			isset( $previous['preload_fonts'] ) ? (string) $previous['preload_fonts'] : '',
			isset( $options['preload_fonts'] ) ? (string) $options['preload_fonts'] : '',
			__( 'Preload fonts files list updated.', 'wphb' )
		);

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_font_optimization_change( $previous, $options );

		wp_send_json_success( Fonts::get_react_settings() );
	}

	/**
	 * Preload font files list, one entry per line, or null when any line is not
	 * a font file; the stored list is then left alone.
	 *
	 * @param mixed $value Submitted textarea value.
	 *
	 * @return string|null
	 */
	private static function parse_font_files( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( $value ) ) ), 'strlen' ) );

		foreach ( $lines as $line ) {
			if ( ! Fonts::is_valid_font_url( $line ) ) {
				return null;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Search posts by title, for the "Post URLs" exclusion picker.
	 *
	 * @since 4.0.0
	 */
	public function minify_search_posts() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data  = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data  = json_decode( html_entity_decode( $data ), true );
		$query = isset( $data['query'] ) ? sanitize_text_field( $data['query'] ) : '';

		wp_send_json_success( Utils::get_module( 'exclusions' )->search_posts( $query ) );
	}

	/**
	 * Get the browsable exclusion options for the Delay JS exclusions modal.
	 *
	 * @since 4.0.0
	 */
	public function minify_delay_js_exclusion_options() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Utils::get_module( 'exclusions' )->get_delay_js_react_options() );
	}

	/**
	 * Save Delay JS exclusions.
	 *
	 * @since 4.0.0
	 */
	public function minify_delay_js_save_exclusions() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$minify   = Utils::get_module( 'minify' );
		$options  = $minify->get_options();
		$previous = $options;

		$options['delay_js_files_exclusion']          = isset( $settings['files'] ) ? array_map( 'sanitize_text_field', (array) $settings['files'] ) : array();
		$options['delay_js_post_types_exclusion']     = isset( $settings['post_types'] ) ? array_map( 'sanitize_key', (array) $settings['post_types'] ) : array();
		$options['delay_js_post_urls_exclusion']      = isset( $settings['post_urls'] ) ? array_map( 'absint', (array) $settings['post_urls'] ) : array();
		$options['delay_js_plugins_themes_exclusion'] = isset( $settings['plugins'] ) ? array_map( 'sanitize_text_field', (array) $settings['plugins'] ) : array();
		$options['delay_js_ads_tracker_exclusion']    = isset( $settings['ads'] ) ? array_map( 'sanitize_key', (array) $settings['ads'] ) : array();
		$options['delay_js_exclusions']               = isset( $settings['keywords'] ) ? array_map( 'sanitize_text_field', (array) $settings['keywords'] ) : array();

		$minify->update_options( $options );
		$minify->clear_cache( false );

		Activity_Log::record( self::ACTIVITY_MODULE, __( 'Delay JavaScript exclusions list updated.', 'wphb' ) );

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_delay_js_change( $previous, $options );

		wp_send_json_success( Utils::get_module( 'delayjs' )->get_react_settings() );
	}

	/**
	 * Get the browsable exclusion options for the Critical CSS exclusions
	 * modal, plus the current manually-included CSS for the Inclusions tab.
	 *
	 * @since 4.0.0
	 */
	public function minify_critical_css_exclusion_options() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( Utils::get_module( 'exclusions' )->get_critical_css_react_options() );
	}

	/**
	 * Save Critical CSS exclusions and the manually-included CSS.
	 *
	 * @since 4.0.0
	 */
	public function minify_critical_css_save_exclusions() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$minify                 = Utils::get_module( 'minify' );
		$options                = $minify->get_options();
		$previous               = $options;
		$previous_inclusion_css = $minify->get_css( 'manual-critical' );
		$previous_exclusions    = $this->get_critical_css_exclusions_snapshot( $options );

		$options['critical_css_files_exclusion']          = isset( $settings['files'] ) ? array_map( 'sanitize_text_field', (array) $settings['files'] ) : array();
		$options['critical_css_post_urls_exclusion']      = isset( $settings['post_urls'] ) ? array_map( 'absint', (array) $settings['post_urls'] ) : array();
		$options['critical_css_plugins_themes_exclusion'] = isset( $settings['plugins'] ) ? array_map( 'sanitize_text_field', (array) $settings['plugins'] ) : array();
		$options['critical_css_keywords']                 = isset( $settings['keywords'] ) ? array_map( 'sanitize_text_field', (array) $settings['keywords'] ) : array();

		if ( isset( $settings['post_types'] ) ) {
			$selected_post_types = array_map( 'sanitize_key', (array) $settings['post_types'] );
			$page_types          = Page_Cache::get_page_types( true );

			$options['critical_page_types']               = array_values( array_diff( $page_types, $selected_post_types ) );
			$options['critical_skipped_custom_post_types'] = array_values( array_diff( $selected_post_types, $page_types ) );
		}

		$minify->update_options( $options );

		// Saving never generates: Critical CSS is only (re)generated from the Regenerate button.
		if ( isset( $settings['inclusion_css'] ) && is_string( $settings['inclusion_css'] ) ) {
			$minify->save_css( $settings['inclusion_css'], 'manual-critical' );
		}

		Activity_Log::record_when_changed(
			self::ACTIVITY_MODULE,
			wp_json_encode( $previous_exclusions ),
			wp_json_encode( $this->get_critical_css_exclusions_snapshot( $options ) ),
			__( 'Critical CSS exclusions list updated.', 'wphb' )
		);
		Activity_Log::record_when_changed(
			self::ACTIVITY_MODULE,
			(string) $previous_inclusion_css,
			(string) $minify->get_css( 'manual-critical' ),
			__( 'Critical CSS inclusions list updated.', 'wphb' )
		);

		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_critical_css_change(
			$previous,
			$options,
			(string) $previous_inclusion_css,
			(string) $minify->get_css( 'manual-critical' )
		);

		wp_send_json_success(
			array_merge(
				Utils::get_module( 'critical_css' )->get_react_settings(),
				array( 'inclusion_css' => $minify->get_css( 'manual-critical' ) )
			)
		);
	}

	/**
	 * Build a comparable snapshot of the Critical CSS exclusion-related option
	 * values, used to only log activity when the saved exclusions actually
	 * change rather than on every save.
	 *
	 * @since 4.0.0
	 *
	 * @param array $options Minify module options (as returned by `get_options()`).
	 *
	 * @return array
	 */
	private function get_critical_css_exclusions_snapshot( $options ) {
		$snapshot = array(
			'files'                     => $options['critical_css_files_exclusion'] ?? array(),
			'post_urls'                 => $options['critical_css_post_urls_exclusion'] ?? array(),
			'plugins'                   => $options['critical_css_plugins_themes_exclusion'] ?? array(),
			'keywords'                  => $options['critical_css_keywords'] ?? array(),
			'page_types'                => $options['critical_page_types'] ?? array(),
			'skipped_custom_post_types' => $options['critical_skipped_custom_post_types'] ?? array(),
		);

		// Sort each list so re-saving the same set in a different order isn't
		// treated as a change.
		foreach ( $snapshot as &$value ) {
			if ( is_array( $value ) ) {
				sort( $value );
			}
		}

		return $snapshot;
	}

	/**
	 * Save asset optimization settings (manual mode).
	 *
	 * @since 3.4.0
	 *
	 * @return void
	 */
	public function minify_manual_save_settings() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$settings = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$settings = json_decode( html_entity_decode( $settings ), true );

		$minify = Utils::get_module( 'minify' );
		$this->save_manual_settings( $settings, array( $minify, 'update_options' ) );

		$this->minify_manual_status();
	}

	private function save_manual_settings( $settings, $save_method ) {
		$minify  = Utils::get_module( 'minify' );
		$options = $minify->get_options();

		foreach ( $settings as $action => $assets ) {
			if ( ! isset( $options[ $action ] ) || ! is_array( $options[ $action ] ) ) {
				continue;
			}

			if ( 'fonts' !== $action ) {
				// Prevent fatal error.
				$assets_scripts = ( empty( $assets['scripts'] ) || ! is_array( $assets['scripts'] ) ) ? array() : $assets['scripts'];
				$diff_scripts   = array_diff( $assets_scripts, $this->hb_is_array( $options[ $action ], 'scripts' ) );
				$this->clear_out_group( $diff_scripts, 'scripts' );

				// Prevent fatal error.
				$assets_styles = ( empty( $assets['styles'] ) || ! is_array( $assets['styles'] ) ) ? array() : $assets['styles'];
				$diff_styles   = array_diff( $assets_styles, $this->hb_is_array( $options[ $action ], 'styles' ) );
				$this->clear_out_group( $diff_styles, 'styles' );
			}

			$options[ $action ] = $assets;
		}

		call_user_func( $save_method, $options );

		// Remove notice.
		delete_site_option( 'wphb-notice-minification-optimized-show' );

		// Reset Complete time.
		$minify::update_ao_completion_time( true );

		// Clear all the page cache.
		do_action( 'wphb_clear_page_cache' );
	}

	/**
	 * Helper function to check if the key exists and is an array.
	 *
	 * @param array  $data Data array.
	 * @param string $key  Key to check.
	 *
	 * @return array
	 */
	public function hb_is_array( $data, $key ) {
		return isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
	}

	/**
	 * Clear out groups for assets, where settings have changed.
	 *
	 * @since 3.4.0
	 *
	 * @param array  $assets Changed asset handles.
	 * @param string $type   Asset type (scripts or styles).
	 *
	 * @return void
	 */
	private function clear_out_group( $assets, $type ) {
		if ( empty( $assets ) ) {
			return;
		}

		foreach ( $assets as $asset ) {
			$changed_groups = Minify_Group::get_groups_from_handle( $asset, $type );
			foreach ( $changed_groups as $group ) {
				/**
				 * Delete those groups.
				 *
				 * @var Minify_Group $group
				 */
				$group->delete_file();
			}
		}
	}

	/**
	 * Status for asset optimization manual mode.
	 *
	 * @since 3.4.0
	 */
	public function minify_manual_status() {
		check_ajax_referer( 'wphb-fetch' );

		// Check permission.
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$minify  = Utils::get_module( 'minify' );
		$options = SafeMode::filter_options( $minify->get_options(), 'minify' );

		wp_send_json_success(
			array(
				'options' => array_map( array( $this, 'flatten_array' ), $options ),
				'assets'  => $minify->get_processed_collection(),
			)
		);
	}

	/**
	 * Flatten the input array.
	 *
	 * We do this, because when we send an array with non-consecutive indexes, for example:
	 *   Array (
	 *     [0] => astra-google-fonts
	 *     [17] => global-styles
	 *     [18] => woocommerce-inline
	 *   )
	 * it is converted to an Object in JavaScript. So we need to reset the indexes.
	 *
	 * @param array $arr  Input array.
	 *
	 * @return mixed
	 */
	public function flatten_array( $arr ) {
		if ( ! is_array( $arr ) ) {
			return $arr;
		}

		// Recurse into children first.
		foreach ( $arr as $key => $value ) {
			$arr[ $key ] = $this->flatten_array( $value );
		}

		// If all keys are integers, reindex them.
		if ( $this->has_all_integer_keys( $arr ) ) {
			$arr = array_values( $arr );
		}

		return $arr;
	}

	/**
	 * Derive UI swap mode from minify font settings.
	 *
	 * @param array $options Minify options array.
	 *
	 * @return string
	 */
	private function get_font_swap_mode( array $options ) {
		if ( empty( $options['font_swap'] ) ) {
			return 'off';
		}

		return isset( $options['font_display_value'] ) && 'optional' === $options['font_display_value']
			? 'performant'
			: 'safe';
	}

	/**
	 * Check if all keys in an array are integers.
	 *
	 * @param array $arr Input array.
	 * @return bool
	 */
	private function has_all_integer_keys( array $arr ): bool {
		foreach ( array_keys( $arr ) as $key ) {
			if ( ! is_int( $key ) ) {
				return false;
			}
		}
		return true;
	}


	/**
	 * Reset individual file.
	 *
	 * @since 1.9.2
	 * @since 3.4.0 Moved out from Ajax class.
	 */
	public function minify_regenerate_asset() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! isset( $data['handle'] ) || ! isset( $data['type'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error removing asset file.', 'wphb' ),
				)
			);
		}

		Utils::get_module( 'minify' )->clear_file( $data['handle'], $data['type'] );

		wp_send_json_success();
	}

	/**
	 * Start asset optimization scan.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function minify_start_ao_scan() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$minify = Utils::get_module( 'minify' );
		$minify->clear_cache( false );

		Sources_Collector::clear_collection();

		$minify->start_process();

		wp_send_json_success(
			array(
				'notice' => Whitelabel::replace_branding_terms( __( 'Hummingbird is running a file check to see what files can be optimized. Feel free to close this page while Hummingbird works its magic in the background.', 'wphb' ) ),
			)
		);
	}

	/**
	 * Get background processing status.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function minify_background_processing_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$background_process = Utils::get_module( 'background_processing' )->get_processor( 'ao_scan' );
		$status             = $background_process->get_status();
		$notice             = '';
		if ( ! $status->is_running() ) {
			$notice = Utils::get_ao_background_processing_completion_message();
		}

		// Infinite-loop safety mechanism triggered; consume so it shows once.
		$infinite_loop_notice = '';
		if ( get_transient( 'wphb_infinite_loop_warning' ) ) {
			$infinite_loop_notice = Whitelabel::replace_branding_terms( __( 'Issues processing queue. Hummingbird performance can be degraded.', 'wphb' ) );
			delete_transient( 'wphb_infinite_loop_warning' );
		}

		wp_send_json_success(
			array(
				'isAoScanProcessing' => (bool) $status->is_running(),
				'notice'             => $notice,
				'infiniteLoopNotice' => $infinite_loop_notice,
				'serverErrorNotice'  => $this->get_server_error_notice(),
			)
		);
	}

	/**
	 * Repeated server errors pausing AO processing (see Errors_Controller::is_server_error()).
	 *
	 * @since 3.4.0 Brought back here in 4.0.0 from removed dead code.
	 *
	 * @return array
	 */
	private function get_server_error_notice() {
		$minify = Utils::get_module( 'minify' );

		// The errors controller only exists while the module is active.
		if ( ! $minify->is_active() ) {
			return array();
		}

		$errors_controller = $minify->errors_controller;

		if ( ! $errors_controller->is_server_error() ) {
			return array();
		}

		$server_errors = $errors_controller->get_server_errors();

		return array(
			'message' => sprintf( /* translators: %d: Time left before another retry. */
				__( 'It seems that we are having problems in our servers. Asset optimization will be turned off for %d minutes', 'wphb' ),
				$errors_controller->server_error_time_left()
			),
			'error'   => $server_errors[0]->get_error_message(),
		);
	}

	/**
	 * Save the network-wide Asset Optimization settings.
	 *
	 * @since 4.0.0
	 */
	public function minify_save_network_settings() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! is_multisite() || ! current_user_can( 'manage_network' ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing settings data.', 'wphb' ) ) );
		}

		$minify = Utils::get_module( 'minify' );

		if ( array_key_exists( 'filePath', $data ) ) {
			$file_path = htmlspecialchars( sanitize_text_field( $data['filePath'] ) );

			// A refused location keeps the stored one; the field never sends it.
			if ( self::is_allowed_file_path( $file_path ) ) {
				$options              = $minify->get_options();
				$options['file_path'] = $file_path;
				$minify->update_options( $options );
			}
		}

		$enabled = false;
		if ( ! empty( $data['enabled'] ) ) {
			$enabled = isset( $data['role'] ) && 'super-admins' === $data['role'] ? 'super-admins' : true;
		}

		$was_active = $minify->is_active();
		$minify->toggle_service( $enabled, true );
		Utils::get_module( 'mixpanel_analytics' )->events( 'asset_optimization' )->track_module_status_change( $was_active, $minify->is_active() );

		wp_send_json_success(
			array(
				'enabled'  => Settings::get_setting( 'enabled', 'minify' ),
				'filePath' => Settings::get_setting( 'file_path', 'minify' ),
			)
		);
	}

	/**
	 * Whether a custom Asset Optimization folder may be saved.
	 *
	 * @since 4.0.2
	 *
	 * @param string $file_path Sanitized folder.
	 *
	 * @return bool
	 */
	private static function is_allowed_file_path( $file_path ) {
		if ( '' === $file_path ) {
			return true;
		}

		return '' !== trim( $file_path, '/.' ) && ! in_array( '..', preg_split( '#[\\\\/]#', $file_path ), true );
	}
}
