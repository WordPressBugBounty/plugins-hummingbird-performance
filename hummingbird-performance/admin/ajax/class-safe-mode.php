<?php
/**
 * Safe Mode lifecycle AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Configs;
use Hummingbird\Core\Filesystem;
use Hummingbird\Core\Modules\Caching\Fast_CGI;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Safe_Mode
 */
class Safe_Mode {

	/**
	 * Collection-backed controls that represent one user-facing setting.
	 *
	 * @var array
	 */
	private const ATOMIC_SETTINGS = array(
		'advanced' => array(
			'prefetch',
			'preconnect',
		),
		'cdn'      => array(
			'excluded_files',
		),
	);

	/**
	 * Settings whose values should not be displayed verbatim in the change inventory.
	 *
	 * @var array
	 */
	private const CUSTOM_VALUE_SETTINGS = array(
		'advanced' => array(
			'prefetch',
			'preconnect',
		),
		'cdn'      => array(
			'excluded_files',
		),
		'minify'   => array(
			'preload_fonts',
		),
	);

	/**
	 * Safe_Mode constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'safe_mode_activate',
			'safe_mode_deactivate',
			'safe_mode_changes',
			'safe_mode_publish',
			'safe_mode_discard',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Enable Safe Mode.
	 *
	 * @since 4.0.0
	 */
	public function safe_mode_activate() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$safe_mode = SafeMode::instance();

		// Already active - re-running init_settings() would overwrite the safe copy with the live settings.
		if ( $safe_mode->get_status() ) {
			wp_send_json_success( array( 'safeMode' => $this->get_safe_mode_payload() ) );
		}

		$fs          = Filesystem::instance();
		$has_config  = file_exists( $fs->basedir . 'wphb-cache.php' );
		$backup_file = $fs->basedir . '/wphb-cache.backup.php';

		// Snapshot the live settings and the page cache config before the status flips.
		$safe_mode->init_settings();

		// init_settings() swallows a failed write, so the backup file is the only observable result.
		if ( $has_config && ! file_exists( $backup_file ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Safe Mode could not be enabled: the page cache configuration could not be backed up.', 'wphb' ),
				)
			);
		}

		$safe_mode->set_status( true );

		Utils::get_module( 'mixpanel_analytics' )->events( 'general' )->track_safe_mode( 'enabled' );

		wp_send_json_success( array( 'safeMode' => $this->get_safe_mode_payload() ) );
	}

	/**
	 * Disable Safe Mode.
	 *
	 * Discarding the safe copy is not a side effect of deactivating - that is a separate
	 * decision made in the disable modal.
	 *
	 * @since 4.0.0
	 */
	public function safe_mode_deactivate() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$safe_mode = SafeMode::instance();

		if ( $safe_mode->get_status() ) {
			$safe_mode->set_status( false );

			Utils::get_module( 'mixpanel_analytics' )->events( 'general' )->track_safe_mode( 'disabled' );
		}

		wp_send_json_success( array( 'safeMode' => $this->get_safe_mode_payload() ) );
	}

	/**
	 * Publish the Safe Mode changes to the live settings.
	 *
	 * Order matters: `publish_safe_mode_settings()` reads the safe option, so nothing may
	 * delete it before that call - `delete_data()` is what removes it.
	 *
	 * @since 4.0.0
	 */
	public function safe_mode_publish() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		// `publish()` on the React side posts `{ clearCache, source }`; `source` is 'frontend' when the preview bar's link opened the review.
		$raw         = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data        = json_decode( html_entity_decode( (string) $raw ), true );
		$clear_cache = ! empty( $data['clearCache'] );
		$change      = isset( $data['source'] ) && 'frontend' === $data['source'] ? 'publish_frontend' : 'publish_plugin';

		$safe_mode = SafeMode::instance();

		$safe_mode->publish_safe_mode_settings();
		$safe_mode->delete_data( 'published' );
		$safe_mode->handle_additional_settings_on_publish();

		Utils::get_module( 'mixpanel_analytics' )->events( 'general' )->track_safe_mode( 'disabled', $change );

		if ( $clear_cache ) {
			$modules = Utils::get_active_cache_modules();

			foreach ( $modules as $module => $name ) {
				$mod = Utils::get_module( $module );

				// The module list is filtered, so an entry can name a module that is not loaded.
				if ( ! $mod || ! $mod->is_active() ) {
					continue;
				}

				if ( 'minify' === $module ) {
					$mod->clear_files();
				} else {
					$mod->clear_cache();
				}
			}
		}

		wp_send_json_success( array( 'safeMode' => $this->get_safe_mode_payload() ) );
	}

	/**
	 * Discard the Safe Mode changes and restore the live settings.
	 *
	 * @since 4.0.0
	 */
	public function safe_mode_discard() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$safe_mode = SafeMode::instance();

		$safe_mode->discard_cache_config_file();
		$safe_mode->delete_data( 'discarded' );

		Utils::get_module( 'mixpanel_analytics' )->events( 'general' )->track_safe_mode( 'disabled', 'discard' );

		// The FastCGI re-toggle is an outbound call and the one step here that can fail. It
		// cannot be inferred from the transients afterwards - a failed toggle and a successful
		// disable leave the same state - so it reports its own result.
		if ( ! $safe_mode->handle_additional_settings_on_discard() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your changes were discarded, but FastCGI could not be restored. Please set it manually.', 'wphb' ),
				)
			);
		}

		wp_send_json_success( array( 'safeMode' => $this->get_safe_mode_payload() ) );
	}

	/**
	 * List the settings changed while Safe Mode was active.
	 *
	 * `safeMode` rides along beside the list so the client can re-read the whole payload from
	 * this one request - `applyResponse()` replaces it wholesale when the key is present, and
	 * `hasChanges` is not derivable on the client (README §7.2).
	 *
	 * @since 4.0.0
	 */
	public function safe_mode_changes() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$changes = self::get_changes();

		wp_send_json_success(
			array(
				'changes'     => $changes,
				'changeCount' => count( $changes ),
				'safeMode'    => $this->get_safe_mode_payload(),
			)
		);
	}

	/**
	 * Get the unpublished Safe Mode changes.
	 *
	 * One entry per differing leaf option in the `filter_options()` projected diff, plus one
	 * synthetic entry for the FastCGI transient. The generated page cache configuration is
	 * still published but omitted because its user-facing controls are already represented.
	 *
	 * `oldValue` is the live value and `newValue` the Safe Mode value, so the pair reads in
	 * the direction the user made the change. Every row carries a `settingLabel` and an
	 * `oldValueLabel` / `newValueLabel` pair beside them: the machine key and the stored
	 * value stay on the wire untouched, and the labels are what the table renders. A row
	 * whose value is a credential carries null values and null labels.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public static function get_changes(): array {
		$raw_hb_settings        = (array) Settings::get_settings( false, SafeMode::HB_SETTINGS_OPTION );
		$raw_safe_mode_settings = (array) Settings::get_settings( false, SafeMode::SAFE_MODE_SETTINGS_OPTION );

		list( $hb_settings, $safe_mode_settings ) = SafeMode::filter_comparable_options(
			$raw_hb_settings,
			$raw_safe_mode_settings
		);
		$is_static_server                         = (bool) Fast_CGI::is_fast_cgi_enabled();

		$configs = new Configs();
		$changes = self::extract_composite_changes(
			$configs,
			$hb_settings,
			$safe_mode_settings,
			$raw_hb_settings,
			$raw_safe_mode_settings
		);

		foreach ( self::diff_leaves( $hb_settings, $safe_mode_settings, array() ) as $leaf ) {
			$module    = $leaf['path'][0];
			$setting   = implode( '.', array_slice( $leaf['path'], 1 ) );

			// Asset Optimization's encoded permission is represented by the normalized rows above.
			if ( 'minify' === $module && 'enabled' === $setting ) {
				continue;
			}

			// A hidden setting is staged for the settings that hang off it but never listed.
			// Asset Optimization's module toggle is one, so a change to it is not reported.
			if ( SafeMode::is_staged_hidden( $module, $setting ) ) {
				continue;
			}

			$secret    = self::is_credential( $leaf['path'][ count( $leaf['path'] ) - 1 ] );
			$group     = SafeMode::get_staged_group( $module, $setting );
			$changes[] = array(
				'module'        => $module,
				'group'         => $group,
				'label'         => self::get_module_label( $group ),
				'setting'       => $setting,
				'settingLabel'  => self::format_setting_label( $configs, $module, $setting ),
				'oldValue'      => $secret ? null : self::format_value( $leaf['old'] ),
				'newValue'      => $secret ? null : self::format_value( $leaf['new'] ),
				'oldValueLabel' => $secret ? null : self::format_value_label( $configs, $module, $setting, $leaf['old'] ),
				'newValueLabel' => $secret ? null : self::format_value_label( $configs, $module, $setting, $leaf['new'] ),
				'redacted'      => $secret,
			);
		}

		$changes = array_merge( $changes, self::get_page_cache_config_changes( $is_static_server ) );

		// The generated cache file mirrors controls already listed above. Keep its difference
		// out of the user-facing inventory, but account for it in the consistency check below.
		$has_hidden_cache_config_change = self::get_page_cache_config_diff() > 0;

		$additional_settings = get_transient( SafeMode::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );
		if ( $is_static_server && is_array( $additional_settings ) ) {
			$changes = array_merge( $changes, self::get_static_server_setting_changes( $additional_settings ) );
		}
		if ( is_array( $additional_settings ) && isset( $additional_settings['fast_cgi'] ) ) {
			$tracked_fast_cgi    = (bool) $additional_settings['fast_cgi'];
			$current_fast_cgi    = (bool) Fast_CGI::is_fast_cgi_enabled();
			$static_server_label = __( 'Static server', 'wphb' ) . "\n" . __( 'Uses FastCGI', 'wphb' );

			if ( $tracked_fast_cgi !== $current_fast_cgi ) {
				$changes[] = array(
					'module'        => 'page_cache',
					'label'         => self::get_module_label( 'page_cache' ),
					'setting'       => 'cache_method',
					// Transient-tracked, not an option - named here instead.
					'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'cache_method' ),
					'oldValue'      => $tracked_fast_cgi ? 'static' : 'local',
					'newValue'      => $current_fast_cgi ? 'static' : 'local',
					'oldValueLabel' => $tracked_fast_cgi ? $static_server_label : __( 'Local server', 'wphb' ),
					'newValueLabel' => $current_fast_cgi ? $static_server_label : __( 'Local server', 'wphb' ),
					'redacted'      => false,
				);
			}
		}

		// `has_changes_to_publish()` is the authority on whether anything is pending. It sorts
		// both sides and compares them strictly, so it agrees with the walk above by key order
		// and by leaf value alike. An empty list beside a true from it means the two have come
		// apart, and the user would see a banner claiming changes with an empty table.
		if ( WP_DEBUG && empty( $changes ) && ! $has_hidden_cache_config_change && SafeMode::instance()->has_changes_to_publish() ) {
			wp_trigger_error( __METHOD__, 'Safe Mode reports unpublished changes but the change inventory is empty.' );
		}

		return self::order_changes( $changes );
	}

	/**
	 * Build Static server rows from the Safe Mode snapshot.
	 *
	 * @param array $additional_settings Additional settings captured when Safe Mode started.
	 *
	 * @return array
	 */
	private static function get_static_server_setting_changes( array $additional_settings ): array {
		if ( ! isset( $additional_settings['fast_cgi_settings'] ) || ! is_array( $additional_settings['fast_cgi_settings'] ) ) {
			return array();
		}

		$changes          = array();
		$tracked_settings = $additional_settings['fast_cgi_settings'];
		$current_settings = Fast_CGI::wphb_fast_cgi_data();

		$tracked_ttl = isset( $tracked_settings['ttl'] ) ? (int) $tracked_settings['ttl'] : 0;
		$current_ttl = is_object( $current_settings ) && isset( $current_settings->ttl ) ? (int) $current_settings->ttl : 0;
		if ( $tracked_ttl !== $current_ttl ) {
			$changes[] = array(
				'module'        => 'page_cache',
				'label'         => self::get_module_label( 'page_cache' ),
				'setting'       => 'cache_lifetime',
				'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'cache_lifetime' ),
				'oldValue'      => $tracked_ttl,
				'newValue'      => $current_ttl,
				'oldValueLabel' => self::format_minutes( $tracked_ttl ),
				'newValueLabel' => self::format_minutes( $current_ttl ),
				'redacted'      => false,
			);
		}

		$tracked = isset( $tracked_settings['bypass_urls'] ) ? array_values( (array) $tracked_settings['bypass_urls'] ) : array();
		$current = is_object( $current_settings ) && isset( $current_settings->bypass_urls )
			? array_values( (array) $current_settings->bypass_urls )
			: array();

		sort( $tracked );
		sort( $current );

		if ( $tracked === $current ) {
			return $changes;
		}

		$tracked_has_exclusions = ! empty( $tracked );
		$current_has_exclusions = ! empty( $current );

		$changes[] = array(
			'module'        => 'page_cache',
			'label'         => self::get_module_label( 'page_cache' ),
			'setting'       => 'exclusions',
			'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'exclusions' ),
			'oldValue'      => $tracked_has_exclusions ? 'custom' : 'empty',
			'newValue'      => $current_has_exclusions ? 'custom' : 'empty',
			'oldValueLabel' => $tracked_has_exclusions ? __( 'Custom', 'wphb' ) : __( 'Empty', 'wphb' ),
			'newValueLabel' => $current_has_exclusions ? __( 'Custom', 'wphb' ) : __( 'Empty', 'wphb' ),
			'redacted'      => false,
		);

		return $changes;
	}

	/**
	 * Format a minute value using the units shown by the Static server cache lifetime control.
	 *
	 * @param int $minutes Number of minutes.
	 *
	 * @return string
	 */
	private static function format_minutes( int $minutes ): string {
		if ( 0 === $minutes % ( 24 * 60 ) ) {
			$days = $minutes / ( 24 * 60 );
			/* translators: %d: number of days. */
			return sprintf( _n( '%d day', '%d days', $days, 'wphb' ), $days );
		}

		if ( 0 === $minutes % 60 ) {
			$hours = $minutes / 60;
			/* translators: %d: number of hours. */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'wphb' ), $hours );
		}

		/* translators: %d: number of minutes. */
		return sprintf( _n( '%d minute', '%d minutes', $minutes, 'wphb' ), $minutes );
	}

	/**
	 * Order the rows so the groups follow the manifest and each group's settings follow the
	 * order its card declares them in.
	 *
	 * Ranked on the group and the setting together: a setting's position is only meaningful
	 * inside its own group, so ranking the two separately would interleave the groups.
	 *
	 * @param array $changes Change inventory rows.
	 *
	 * @return array
	 */
	private static function order_changes( array $changes ): array {
		$indexes = array();
		$rows    = array();

		foreach ( $changes as $index => $change ) {
			$group     = (string) ( $change['group'] ?? $change['module'] );
			$indexes[] = $index;
			$rows[]    = array(
				'index'  => $index,
				'group'  => SafeMode::get_staged_group_weight( $group ),
				'weight' => SafeMode::get_staged_setting_weight(
					$group,
					(string) ( $change['setting'] ?? '' ),
					(string) ( $change['module'] ?? '' )
				),
				'row'    => $change,
			);
		}

		usort(
			$rows,
			static function ( $left, $right ) {
				return $left['group'] <=> $right['group']
					?: $left['weight'] <=> $right['weight']
					?: $left['index'] <=> $right['index'];
			}
		);

		foreach ( $indexes as $offset => $index ) {
			$changes[ $index ] = $rows[ $offset ]['row'];
		}

		return $changes;
	}

	/**
	 * Extract settings represented by one UI control but stored under multiple keys.
	 *
	 * @param Configs $configs                 Config label provider.
	 * @param array   $hb_settings             Live settings, modified to remove extracted keys.
	 * @param array   $safe_mode_settings      Safe Mode settings, modified to remove extracted keys.
	 * @param array   $raw_hb_settings         Live settings as stored, before the manifest filtered them.
	 * @param array   $raw_safe_mode_settings  Safe Mode settings as stored, before the manifest filtered them.
	 *
	 * @return array
	 */
	private static function extract_composite_changes(
		$configs,
		array &$hb_settings,
		array &$safe_mode_settings,
		array $raw_hb_settings,
		array $raw_safe_mode_settings
	): array {
		$changes   = self::extract_page_cache_permission_changes( $hb_settings, $safe_mode_settings );
		$changes   = array_merge( $changes, self::extract_asset_optimization_permission_changes( $hb_settings, $safe_mode_settings ) );
		$changes   = array_merge( $changes, self::extract_grouped_changes( $configs, $hb_settings, $safe_mode_settings ) );
		$changes   = array_merge( $changes, self::extract_cart_fragments_change( $hb_settings, $safe_mode_settings ) );
		$changes   = array_merge(
			$changes,
			self::extract_lazy_load_comments_change( $hb_settings, $safe_mode_settings, $raw_hb_settings, $raw_safe_mode_settings )
		);
		$changes   = array_merge( $changes, self::extract_page_cache_preload_change( $hb_settings, $safe_mode_settings ) );
		$changes   = array_merge( $changes, self::extract_critical_css_mode_change( $hb_settings, $safe_mode_settings ) );
		$live_mode = self::get_font_swap_mode( $hb_settings['minify'] ?? array() );
		$safe_mode = self::get_font_swap_mode( $safe_mode_settings['minify'] ?? array() );

		unset(
			$hb_settings['minify']['font_swap'],
			$hb_settings['minify']['font_display_value'],
			$safe_mode_settings['minify']['font_swap'],
			$safe_mode_settings['minify']['font_display_value']
		);

		if ( $live_mode === $safe_mode ) {
			return $changes;
		}

		$labels = array(
			'off'        => __( 'Off', 'wphb' ),
			'safe'       => __( 'Safe', 'wphb' ),
			'performant' => __( 'Performant', 'wphb' ),
		);

		$changes[] = self::build_change(
			'minify',
			'font_swap',
			array(
				'settingLabel'  => SafeMode::get_staged_setting_label( 'minify', 'font_swap' ),
				'oldValue'      => $live_mode,
				'newValue'      => $safe_mode,
				'oldValueLabel' => $labels[ $live_mode ],
				'newValueLabel' => $labels[ $safe_mode ],
				'redacted'      => false,
			)
		);

		return $changes;
	}

	/**
	 * Split the multisite Asset Optimization permission from its encoded enabled value.
	 *
	 * @param array $hb_settings        Live settings, modified to remove the encoded value.
	 * @param array $safe_mode_settings Safe Mode settings, modified to remove the encoded value.
	 *
	 * @return array
	 */
	private static function extract_asset_optimization_permission_changes( array &$hb_settings, array &$safe_mode_settings ): array {
		if ( ! is_multisite() ) {
			return array();
		}

		$live = $hb_settings['minify']['enabled'] ?? false;
		$safe = $safe_mode_settings['minify']['enabled'] ?? false;

		$live_enabled = false !== $live;
		$safe_enabled = false !== $safe;
		$live_role    = 'super-admins' === $live ? 'super-admin' : 'subsite-admin';
		$safe_role    = 'super-admins' === $safe ? 'super-admin' : 'subsite-admin';
		$changes      = array();

		$hb_settings['minify']['enabled'] = $live_enabled;
		$safe_mode_settings['minify']['enabled'] = $safe_enabled;

		if ( $live_enabled !== $safe_enabled || ( $safe_enabled && $live_role !== $safe_role ) ) {
			$changes[] = self::build_change(
				'minify',
				'enabled',
				array(
					'settingLabel'  => SafeMode::get_staged_setting_label( 'minify', 'enabled' ),
					'oldValue'      => $live_enabled,
					'newValue'      => $safe_enabled,
					'toggleChanged' => $live_enabled !== $safe_enabled,
					'oldValueLabel' => $live_enabled
						? __( 'On', 'wphb' ) . "\n" . ( 'super-admin' === $live_role ? __( 'Super Admin', 'wphb' ) : __( 'Subsite Admin', 'wphb' ) )
						: __( 'Off', 'wphb' ),
					'newValueLabel' => $safe_enabled
						? __( 'On', 'wphb' ) . "\n" . ( 'super-admin' === $safe_role ? __( 'Super Admin', 'wphb' ) : __( 'Subsite Admin', 'wphb' ) )
						: __( 'Off', 'wphb' ),
					'redacted'      => false,
				)
			);
		}

		return $changes;
	}

	/**
	 * Split the multisite page-cache permission from the encoded enabled value.
	 *
	 * `blog-admins` means page caching is on and subsites may disable it. The review table should
	 * show those as the two controls users see, rather than exposing the stored sentinel value.
	 *
	 * @param array $hb_settings        Live settings, modified to remove the encoded value.
	 * @param array $safe_mode_settings Safe Mode settings, modified to remove the encoded value.
	 *
	 * @return array
	 */
	private static function extract_page_cache_permission_changes( array &$hb_settings, array &$safe_mode_settings ): array {
		$live = $hb_settings['page_cache']['enabled'] ?? false;
		$safe = $safe_mode_settings['page_cache']['enabled'] ?? false;

		$live_enabled = false !== $live;
		$safe_enabled = false !== $safe;
		$live_subsites_can_disable = 'blog-admins' === $live;
		$safe_subsites_can_disable = 'blog-admins' === $safe;
		$changes = array();

		// Keep normalized values available to the page-cache config inventory below.
		$hb_settings['page_cache']['enabled'] = $live_enabled;
		$safe_mode_settings['page_cache']['enabled'] = $safe_enabled;

		if ( $live_subsites_can_disable !== $safe_subsites_can_disable ) {
			$changes[] = self::build_change(
				'page_cache',
				'cache_blog',
				array(
					'settingLabel'  => __( 'Allow subsites to disable page caching', 'wphb' ),
					'oldValue'      => $live_subsites_can_disable,
					'newValue'      => $safe_subsites_can_disable,
					'oldValueLabel' => $live_subsites_can_disable ? __( 'On', 'wphb' ) : __( 'Off', 'wphb' ),
					'newValueLabel' => $safe_subsites_can_disable ? __( 'On', 'wphb' ) : __( 'Off', 'wphb' ),
					'redacted'      => false,
				)
			);
		}

		return $changes;
	}

	/**
	 * Collapse the cart-fragments toggle and scope into the single control shown in Tools.
	 *
	 * @param array $hb_settings        Live settings, modified to remove the extracted key.
	 * @param array $safe_mode_settings Safe Mode settings, modified to remove the extracted key.
	 *
	 * @return array
	 */
	private static function extract_cart_fragments_change( array &$hb_settings, array &$safe_mode_settings ): array {
		$live = self::get_cart_fragments_state( $hb_settings['advanced']['cart_fragments'] ?? false );
		$safe = self::get_cart_fragments_state( $safe_mode_settings['advanced']['cart_fragments'] ?? false );

		unset( $hb_settings['advanced']['cart_fragments'], $safe_mode_settings['advanced']['cart_fragments'] );

		if ( $live['identity'] === $safe['identity'] ) {
			return array();
		}

		return array(
			self::build_change(
				'advanced',
				'cart_fragments',
				array(
					'settingLabel'  => SafeMode::get_staged_setting_label( 'advanced', 'cart_fragments' ),
					'oldValue'      => $live['value'],
					'newValue'      => $safe['value'],
					'oldValueLabel' => $live['label'],
					'newValueLabel' => $safe['label'],
					'redacted'      => false,
				)
			),
		);
	}

	/**
	 * Resolve the cart-fragments toggle and its active scope.
	 *
	 * @param mixed $setting Stored cart-fragments setting.
	 *
	 * @return array
	 */
	private static function get_cart_fragments_state( $setting ): array {
		$enabled = ! empty( $setting );
		$scope   = 'all' === $setting ? 'all' : 'non_woo';
		$labels  = array(
			'non_woo' => __( 'Non-WooCommerce', 'wphb' ),
			'all'     => __( 'All pages', 'wphb' ),
		);

		return array(
			'identity' => $enabled ? 'enabled:' . $scope : 'disabled',
			'value'    => $enabled,
			'label'    => $enabled ? __( 'On', 'wphb' ) . "\n" . $labels[ $scope ] : __( 'Off', 'wphb' ),
		);
	}

	/**
	 * Collapse Lazy Load status and loading method into the single control shown in Tools.
	 *
	 * The manifest gates `method` on the toggle, so a cleared toggle drops it from both
	 * filtered snapshots. The status still has to be read to describe the side that is on,
	 * so the method is taken from the raw settings there.
	 *
	 * @param array $hb_settings             Live settings, modified to remove extracted keys.
	 * @param array $safe_mode_settings      Safe Mode settings, modified to remove extracted keys.
	 * @param array $raw_hb_settings         Live settings as stored, before filtering.
	 * @param array $raw_safe_mode_settings  Safe Mode settings as stored, before filtering.
	 *
	 * @return array
	 */
	private static function extract_lazy_load_comments_change(
		array &$hb_settings,
		array &$safe_mode_settings,
		array $raw_hb_settings,
		array $raw_safe_mode_settings
	): array {
		$live = self::get_lazy_load_comments_state( $hb_settings['advanced']['lazy_load'] ?? array(), $raw_hb_settings );
		$safe = self::get_lazy_load_comments_state( $safe_mode_settings['advanced']['lazy_load'] ?? array(), $raw_safe_mode_settings );

		unset(
			$hb_settings['advanced']['lazy_load']['enabled'],
			$hb_settings['advanced']['lazy_load']['method'],
			$safe_mode_settings['advanced']['lazy_load']['enabled'],
			$safe_mode_settings['advanced']['lazy_load']['method']
		);

		if ( $live['identity'] === $safe['identity'] ) {
			return array();
		}

		return array(
			self::build_change(
				'advanced',
				'lazy_load.enabled',
				array(
					// The toggle and its method render as one control, so the row is keyed on the toggle.
					'settingLabel'  => SafeMode::get_staged_setting_label( 'advanced', 'lazy_load.enabled' ),
					'oldValue'      => $live['value'],
					'newValue'      => $safe['value'],
					'oldValueLabel' => $live['label'],
					'newValueLabel' => $safe['label'],
					'redacted'      => false,
				)
			),
		);
	}

	/**
	 * Resolve Lazy Load status and its active loading method.
	 *
	 * The method is read from the raw settings because the manifest-filtered snapshot has
	 * already dropped it whenever the Safe Mode snapshot has the toggle off. Only the side
	 * that is on needs it, so the raw value is consulted only there.
	 *
	 * @param array $settings     Filtered Lazy Load settings, which carry the toggle.
	 * @param array $raw_settings Settings as stored, which carry the method.
	 *
	 * @return array
	 */
	private static function get_lazy_load_comments_state( array $settings, array $raw_settings = array() ): array {
		$enabled = ! empty( $settings['enabled'] );
		$methods = array(
			'scroll' => __( 'On scroll', 'wphb' ),
			'click'  => __( 'On click', 'wphb' ),
		);

		$raw = $raw_settings['advanced']['lazy_load'] ?? array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		// The filtered snapshot wins when it still holds the method; the raw settings are the
		// fallback for the side that is on while the other side cleared the toggle.
		$stored = $settings['method'] ?? ( $raw['method'] ?? null );
		$method = isset( $stored ) && isset( $methods[ $stored ] )
			? $stored
			: 'click';

		return array(
			'identity' => $enabled ? 'enabled:' . $method : 'disabled',
			'value'    => $enabled,
			'label'    => $enabled ? __( 'On', 'wphb' ) . "\n" . $methods[ $method ] : __( 'Off', 'wphb' ),
		);
	}

	/**
	 * Collapse preload status and targets into the single control shown in Page Caching.
	 *
	 * @param array $hb_settings        Live settings, modified to remove extracted keys.
	 * @param array $safe_mode_settings Safe Mode settings, modified to remove extracted keys.
	 *
	 * @return array
	 */
	private static function extract_page_cache_preload_change( array &$hb_settings, array &$safe_mode_settings ): array {
		$live = self::get_page_cache_preload_state( $hb_settings['page_cache'] ?? array() );
		$safe = self::get_page_cache_preload_state( $safe_mode_settings['page_cache'] ?? array() );

		unset(
			$hb_settings['page_cache']['preload'],
			$hb_settings['page_cache']['preload_type'],
			$safe_mode_settings['page_cache']['preload'],
			$safe_mode_settings['page_cache']['preload_type']
		);

		if ( $live['identity'] === $safe['identity'] ) {
			return array();
		}

		return array(
			self::build_change(
				'page_cache',
				'preload',
				array(
					'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'preload' ),
					'oldValue'      => $live['value'],
					'newValue'      => $safe['value'],
					'oldValueLabel' => $live['label'],
					'newValueLabel' => $safe['label'],
					'redacted'      => false,
				)
			),
		);
	}

	/**
	 * Resolve preload status and its active targets.
	 *
	 * @param array $settings Page Caching settings.
	 *
	 * @return array
	 */
	private static function get_page_cache_preload_state( array $settings ): array {
		$enabled = ! empty( $settings['preload'] );
		$targets = array();

		if ( $enabled && ! empty( $settings['preload_type']['home_page'] ) ) {
			$targets['home_page'] = __( 'Homepage', 'wphb' );
		}

		if ( $enabled && ! empty( $settings['preload_type']['on_clear'] ) ) {
			$targets['on_clear'] = __( 'Any updated page or post', 'wphb' );
		}

		$identity = $enabled ? 'enabled' : 'disabled';
		if ( $targets ) {
			$identity .= ':' . implode( ',', array_keys( $targets ) );
		}

		$label = $enabled ? __( 'On', 'wphb' ) : __( 'Off', 'wphb' );
		if ( $targets ) {
			$label .= "\n" . implode( "\n", $targets );
		}

		return array(
			'identity' => $identity,
			'value'    => $enabled,
			'label'    => $label,
		);
	}

	/**
	 * Collapse the Critical CSS mode and its conditional behavior into one UI row.
	 *
	 * @param array $hb_settings        Live settings, modified to remove extracted keys.
	 * @param array $safe_mode_settings Safe Mode settings, modified to remove extracted keys.
	 *
	 * @return array
	 */
	private static function extract_critical_css_mode_change( array &$hb_settings, array &$safe_mode_settings ): array {
		$keys = array(
			'critical_css_type',
			'critical_css_remove_type',
			'above_fold_load_stylesheet_method',
		);
		$live = self::get_critical_css_mode( $hb_settings['minify'] ?? array() );
		$safe = self::get_critical_css_mode( $safe_mode_settings['minify'] ?? array() );

		foreach ( $keys as $key ) {
			unset( $hb_settings['minify'][ $key ], $safe_mode_settings['minify'][ $key ] );
		}

		if ( $live === $safe ) {
			return array();
		}

		return array(
			self::build_change(
				'minify',
				'critical_css_type',
				array(
					'settingLabel'  => SafeMode::get_staged_setting_label( 'minify', 'critical_css_type' ),
					'oldValue'      => $live['value'],
					'newValue'      => $safe['value'],
					'oldValueLabel' => $live['label'],
					'newValueLabel' => $safe['label'],
					'redacted'      => false,
				)
			),
		);
	}

	/**
	 * Resolve the selected Critical CSS mode and its conditional behavior.
	 *
	 * @param array $settings Asset Optimization settings.
	 *
	 * @return array
	 */
	private static function get_critical_css_mode( array $settings ): array {
		$type = $settings['critical_css_type'] ?? 'remove';

		if ( 'asynchronously' === $type ) {
			$method       = $settings['above_fold_load_stylesheet_method'] ?? 'load_stylesheet_on_user_interaction';
			$method_label = 'load_stylesheet_async' === $method
				? __( 'Load asynchronously', 'wphb' )
				: __( 'Load on user interaction', 'wphb' );

			return array(
				'value' => $type . ':' . $method,
				'label' => __( 'Above the fold', 'wphb' ) . "\n" . $method_label,
			);
		}

		$method       = $settings['critical_css_remove_type'] ?? 'user_interaction_with_remove';
		$method_label = 'remove_unused' === $method
			? __( 'Remove unused CSS', 'wphb' )
			: __( 'Load on user interaction', 'wphb' );

		return array(
			'value' => 'remove:' . $method,
			'label' => __( 'Full page', 'wphb' ) . "\n" . $method_label,
		);
	}

	/**
	 * Collapse related stored paths into their user-facing settings.
	 *
	 * The paths come from the manifest's `stored` key - the same declaration
	 * `keep_staged_options()` filters by, so a setting stored under several keys is
	 * named once, beside the setting.
	 *
	 * @param Configs $configs            Config label provider.
	 * @param array   $hb_settings        Live settings, modified to remove grouped paths.
	 * @param array   $safe_mode_settings Safe Mode settings, modified to remove grouped paths.
	 *
	 * @return array
	 */
	private static function extract_grouped_changes( $configs, array &$hb_settings, array &$safe_mode_settings ): array {
		$changes = array();

		foreach ( SafeMode::get_stored_settings() as $module => $groups ) {
			foreach ( $groups as $setting => $paths ) {
				$old = array();
				$new = array();

				foreach ( $paths as $path ) {
					$old[ $path ] = self::get_path_value( $hb_settings[ $module ] ?? array(), $path );
					$new[ $path ] = self::get_path_value( $safe_mode_settings[ $module ] ?? array(), $path );
					self::unset_path_value( $hb_settings[ $module ], $path );
					self::unset_path_value( $safe_mode_settings[ $module ], $path );
				}

				if ( $old === $new ) {
					continue;
				}

				$old_value = self::has_group_value( $old ) ? 'custom' : 'empty';
				$new_value = self::has_group_value( $new ) ? 'custom' : 'empty';

				$changes[] = self::build_change(
					$module,
					$setting,
					array(
						'settingLabel'  => self::format_setting_label( $configs, $module, $setting ),
						'oldValue'      => $old_value,
						'newValue'      => $new_value,
						'oldValueLabel' => 'empty' === $old_value ? __( 'Empty', 'wphb' ) : __( 'Custom', 'wphb' ),
						'newValueLabel' => 'empty' === $new_value ? __( 'Empty', 'wphb' ) : __( 'Custom', 'wphb' ),
						'redacted'      => false,
					)
				);
			}
		}

		return $changes;
	}

	/**
	 * Build the change row for a setting the manifest folds together, or that the extraction
	 * resolves from several stored keys.
	 *
	 * A module's changes are not always shown under the module's own heading. Asset Optimization
	 * is four cards against the one `minify` option, so `font_swap` belongs under Font
	 * Optimization and `critical_css_type` under Critical CSS. The manifest knows which, through
	 * `SafeMode::get_staged_group()`, and the row has to carry that group for the table to file it
	 * under the right heading - the module alone would put all four cards back in one row.
	 *
	 * @param string $module  Module key.
	 * @param string $setting Dotted leaf path below the module, without the module.
	 * @param array  $values  `oldValue`, `newValue` and their `*Label`s.
	 *
	 * @return array
	 */
	private static function build_change( string $module, string $setting, array $values ): array {
		$group = SafeMode::get_staged_group( $module, $setting );

		return array_merge(
			array(
				'module'  => $module,
				'group'   => $group,
				'label'   => self::get_module_label( $group ),
				'setting' => $setting,
			),
			$values
		);
	}

	/**
	 * Get a dotted path from an array.
	 *
	 * @param array  $values Values to inspect.
	 * @param string $path   Dotted path.
	 *
	 * @return mixed
	 */
	private static function get_path_value( array $values, $path ) {
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $values ) || ! array_key_exists( $segment, $values ) ) {
				return null;
			}

			$values = $values[ $segment ];
		}

		return $values;
	}

	/**
	 * Remove a dotted path from an array.
	 *
	 * @param array|null $values Values to update.
	 * @param string     $path   Dotted path.
	 *
	 * @return void
	 */
	private static function unset_path_value( &$values, $path ): void {
		if ( ! is_array( $values ) ) {
			return;
		}

		$segments = explode( '.', $path );
		$leaf     = array_pop( $segments );
		$target   = &$values;

		foreach ( $segments as $segment ) {
			if ( ! isset( $target[ $segment ] ) || ! is_array( $target[ $segment ] ) ) {
				return;
			}

			$target = &$target[ $segment ];
		}

		unset( $target[ $leaf ] );
	}

	/**
	 * Check whether a grouped setting contains a selected value.
	 *
	 * @param array $values Grouped values.
	 *
	 * @return bool
	 */
	private static function has_group_value( array $values ): bool {
		foreach ( $values as $value ) {
			if ( is_array( $value ) ? self::has_group_value( $value ) : ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the selected Swap web fonts option from its stored values.
	 *
	 * @param array $settings Asset Optimization settings.
	 *
	 * @return string
	 */
	private static function get_font_swap_mode( array $settings ): string {
		if ( empty( $settings['font_swap'] ) ) {
			return 'off';
		}

		return isset( $settings['font_display_value'] ) && 'optional' === $settings['font_display_value']
			? 'performant'
			: 'safe';
	}

	/**
	 * Get the number of unpublished Safe Mode changes.
	 *
	 * @since 4.0.0
	 *
	 * @return int
	 */
	public static function get_change_count(): int {
		return count( self::get_changes() );
	}

	/**
	 * Count the differing leaves between the backed up and the live page cache config.
	 *
	 * Mirrors the cache file comparison in `SafeMode::has_changes_to_publish()`, including
	 * ignoring `safemode_status` - that is the flag Safe Mode itself writes.
	 *
	 * @since 4.0.0
	 *
	 * @return int
	 */
	private static function get_page_cache_config_diff(): int {
		$configs = self::get_page_cache_configs();
		if ( empty( $configs ) ) {
			return 0;
		}

		return count( self::diff_leaves( $configs['tracked'], $configs['current'], array() ) );
	}

	/**
	 * Build inventory rows for user-facing settings stored in the page cache config file.
	 *
	 * @return array
	 */
	private static function get_page_cache_config_changes( bool $is_static_server = false ): array {
		$configs = self::get_page_cache_configs();
		if ( empty( $configs ) ) {
			return array();
		}

		$tracked = $configs['tracked'];
		$current = $configs['current'];

		// Everything here is a child of page caching, so nothing applies while the module is off.
		$safe_mode_settings = (array) Settings::get_settings( false, SafeMode::SAFE_MODE_SETTINGS_OPTION );
		if ( empty( $safe_mode_settings['page_cache']['enabled'] ) ) {
			return array();
		}

		$changes = array();

		if ( ! $is_static_server ) {
			$tracked_page_types = array(
				'page_types'        => array_values( (array) ( $tracked['page_types'] ?? array() ) ),
				'custom_post_types' => array_values( (array) ( $tracked['custom_post_types'] ?? array() ) ),
			);
			$current_page_types = array(
				'page_types'        => array_values( (array) ( $current['page_types'] ?? array() ) ),
				'custom_post_types' => array_values( (array) ( $current['custom_post_types'] ?? array() ) ),
			);

			sort( $tracked_page_types['page_types'] );
			sort( $tracked_page_types['custom_post_types'] );
			sort( $current_page_types['page_types'] );
			sort( $current_page_types['custom_post_types'] );

			if ( $tracked_page_types !== $current_page_types ) {
				$tracked_has_types = self::has_group_value( $tracked_page_types );
				$current_has_types = self::has_group_value( $current_page_types );
				$changes[]         = array(
					'module'        => 'page_cache',
					'label'         => self::get_module_label( 'page_cache' ),
					'setting'       => 'page_types',
					'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'page_types' ),
					'oldValue'      => $tracked_has_types ? 'custom' : 'empty',
					'newValue'      => $current_has_types ? 'custom' : 'empty',
					'oldValueLabel' => $tracked_has_types ? __( 'Custom', 'wphb' ) : __( 'Empty', 'wphb' ),
					'newValueLabel' => $current_has_types ? __( 'Custom', 'wphb' ) : __( 'Empty', 'wphb' ),
					'redacted'      => false,
				);
			}
		}

		if ( ! $is_static_server ) {
			$tracked_interval = self::get_page_cache_clear_interval( $tracked );
			$current_interval = self::get_page_cache_clear_interval( $current );

			if ( $tracked_interval !== $current_interval ) {
				$changes[] = array(
					'module'        => 'page_cache',
					'label'         => self::get_module_label( 'page_cache' ),
					'setting'       => 'clear_interval',
					'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'clear_interval' ),
					'oldValue'      => $tracked_interval['enabled'] ? $tracked_interval['interval'] : false,
					'newValue'      => $current_interval['enabled'] ? $current_interval['interval'] : false,
					'oldValueLabel' => self::format_page_cache_clear_interval( $tracked_interval ),
					'newValueLabel' => self::format_page_cache_clear_interval( $current_interval ),
					'redacted'      => false,
				);
			}
		}

		if ( ! $is_static_server ) {
			$tracked_exclusions = self::get_page_cache_exclusions( $tracked );
			$current_exclusions = self::get_page_cache_exclusions( $current );

			if ( $tracked_exclusions !== $current_exclusions ) {
				$tracked_has_exclusions = self::has_group_value( $tracked_exclusions );
				$current_has_exclusions = self::has_group_value( $current_exclusions );
				$changes[]              = array(
					'module'        => 'page_cache',
					'label'         => self::get_module_label( 'page_cache' ),
					'setting'       => 'exclusions',
					'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', 'exclusions' ),
					'oldValue'      => $tracked_has_exclusions ? 'custom' : 'empty',
					'newValue'      => $current_has_exclusions ? 'custom' : 'empty',
					'oldValueLabel' => $tracked_has_exclusions ? __( 'Custom', 'wphb' ) : __( 'Empty', 'wphb' ),
					'newValueLabel' => $current_has_exclusions ? __( 'Custom', 'wphb' ) : __( 'Empty', 'wphb' ),
					'redacted'      => false,
				);
			}
		}

		$boolean_settings = array(
			'settings.clear_update',
			'settings.comment_clear',
		);

		if ( ! $is_static_server ) {
			$boolean_settings = array_merge(
				array(
					'settings.mobile',
					'settings.logged_in',
					'settings.url_queries',
					'settings.cache_headers',
					'settings.cache_404',
					'settings.cache_identifier',
					'settings.compress',
				),
				$boolean_settings
			);
		}

		foreach ( $boolean_settings as $setting ) {
			$tracked_value = (bool) self::get_path_value( $tracked, $setting );
			$current_value = (bool) self::get_path_value( $current, $setting );

			if ( $tracked_value === $current_value ) {
				continue;
			}

			$changes[] = array(
				'module'        => 'page_cache',
				'label'         => self::get_module_label( 'page_cache' ),
				'setting'       => $setting,
				'settingLabel'  => SafeMode::get_staged_setting_label( 'page_cache', $setting ),
				'oldValue'      => $tracked_value,
				'newValue'      => $current_value,
				'oldValueLabel' => $tracked_value ? __( 'On', 'wphb' ) : __( 'Off', 'wphb' ),
				'newValueLabel' => $current_value ? __( 'On', 'wphb' ) : __( 'Off', 'wphb' ),
				'redacted'      => false,
			);
		}

		return $changes;
	}

	/**
	 * Normalize the three Page Caching exclusion lists as one user-facing setting.
	 *
	 * @param array $config Page cache configuration.
	 *
	 * @return array
	 */
	private static function get_page_cache_exclusions( array $config ): array {
		$exclusions = array(
			'url_strings' => array_values( (array) self::get_path_value( $config, 'exclude.url_strings' ) ),
			'user_agents' => array_values( (array) self::get_path_value( $config, 'exclude.user_agents' ) ),
			'cookies'     => array_values( (array) self::get_path_value( $config, 'exclude.cookies' ) ),
		);

		foreach ( $exclusions as &$values ) {
			sort( $values );
		}
		unset( $values );

		return $exclusions;
	}

	/**
	 * Normalize the page cache interval control for comparison.
	 *
	 * @param array $config Page cache configuration.
	 *
	 * @return array
	 */
	private static function get_page_cache_clear_interval( array $config ): array {
		$enabled = (bool) self::get_path_value( $config, 'clear_interval.enabled' );

		return array(
			'enabled'  => $enabled,
			'interval' => $enabled ? (int) self::get_path_value( $config, 'clear_interval.interval' ) : 0,
		);
	}

	/**
	 * Format the page cache interval as the status and selected duration shown in the UI.
	 *
	 * @param array $interval Normalized interval state.
	 *
	 * @return string
	 */
	private static function format_page_cache_clear_interval( array $interval ): string {
		if ( ! $interval['enabled'] ) {
			return __( 'Off', 'wphb' );
		}

		$hours = $interval['interval'];
		if ( 0 === $hours % 24 ) {
			$days = $hours / 24;
			/* translators: %d: number of days. */
			$duration = sprintf( _n( '%d day', '%d days', $days, 'wphb' ), $days );
		} else {
			/* translators: %d: number of hours. */
			$duration = sprintf( _n( '%d hour', '%d hours', $hours, 'wphb' ), $hours );
		}

		return $duration;
	}

	/**
	 * Load the backed-up and current page cache configuration files.
	 *
	 * @return array
	 */
	private static function get_page_cache_configs(): array {
		$fs          = Filesystem::instance();
		$backup_file = $fs->basedir . '/wphb-cache.backup.php';
		$config_file = $fs->basedir . 'wphb-cache.php';

		if ( ! file_exists( $backup_file ) || ! file_exists( $config_file ) ) {
			return array();
		}

		$tracked = json_decode( file_get_contents( $backup_file ), true );
		$current = json_decode( file_get_contents( $config_file ), true );

		if ( ! is_array( $tracked ) || ! is_array( $current ) ) {
			return array();
		}

		unset( $tracked['safemode_status'], $current['safemode_status'] );

		return compact( 'tracked', 'current' );
	}

	/**
	 * Recursively collect the differing leaves between two settings arrays.
	 *
	 * Comparison is by key rather than by order, which is what `has_changes_to_publish()` gets
	 * from sorting both sides before its strict compare.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $old  The live value.
	 * @param mixed $new  The Safe Mode value.
	 * @param array $path Path to the node being compared.
	 *
	 * @return array List of `array( 'path', 'old', 'new' )`.
	 */
	private static function diff_leaves( $old, $new, array $path ): array {
		if ( self::is_atomic_setting_path( $path ) ) {
			if ( $old === $new ) {
				return array();
			}

			return array(
				array(
					'path' => $path,
					'old'  => $old,
					'new'  => $new,
				),
			);
		}

		if ( is_array( $old ) && is_array( $new ) ) {
			$leaves = array();

			foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $key ) {
				$in_old = array_key_exists( $key, $old );
				$in_new = array_key_exists( $key, $new );

				if ( ! $in_old || ! $in_new ) {
					$leaves[] = array(
						'path' => array_merge( $path, array( $key ) ),
						'old'  => $in_old ? $old[ $key ] : null,
						'new'  => $in_new ? $new[ $key ] : null,
					);
					continue;
				}

				$leaves = array_merge( $leaves, self::diff_leaves( $old[ $key ], $new[ $key ], array_merge( $path, array( $key ) ) ) );
			}

			return $leaves;
		}

		if ( $old === $new ) {
			return array();
		}

		return array(
			array(
				'path' => $path,
				'old'  => $old,
				'new'  => $new,
			),
		);
	}

	/**
	 * Check whether a path belongs to a collection-backed single setting.
	 *
	 * A matching path is compared whole and stops the recursion, so a list-backed control is
	 * reported as one row rather than one row per item.
	 *
	 * @param array $path Full setting path, beginning with its module.
	 *
	 * @return bool
	 */
	private static function is_atomic_setting_path( array $path ): bool {
		if ( count( $path ) < 2 ) {
			return false;
		}

		$module  = (string) $path[0];
		$setting = implode( '.', array_slice( $path, 1 ) );

		return isset( self::ATOMIC_SETTINGS[ $module ] )
			&& in_array( $setting, self::ATOMIC_SETTINGS[ $module ], true );
	}

	/**
	 * Make a leaf value safe to render and to JSON encode.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $value The leaf value.
	 *
	 * @return mixed
	 */
	private static function format_value( $value ) {
		// A key that is an array on one side and a decoded object on the other is emitted as
		// a leaf by `diff_leaves()`, so an object has to render as a count too - a cast, not
		// `count()` directly, which throws on an object that is not `Countable`.
		if ( is_array( $value ) || is_object( $value ) ) {
			$count = count( (array) $value );

			return sprintf(
				/* translators: %d: number of nested items. */
				_n( '%d item', '%d items', $count, 'wphb' ),
				$count
			);
		}

		if ( is_scalar( $value ) || is_null( $value ) ) {
			return $value;
		}

		return __( 'Unsupported value', 'wphb' );
	}

	/**
	 * Whether a leaf key holds a credential.
	 *
	 * The response is rendered in the admin and may end up screenshotted into a support
	 * ticket, so a credential is reported as changed without its value.
	 *
	 * @since 4.0.0
	 *
	 * @param string $key The leaf option key.
	 *
	 * @return bool
	 */
	private static function is_credential( $key ): bool {
		return 1 === preg_match( '/(?:^|_)(?:pass(?:word|wd)?|secret|token|credentials?|auth|apikey|key|email|licen[cs]e)$/i', (string) $key );
	}

	/**
	 * Display name for an inventory group.
	 *
	 * The label comes from the manifest, where the literals sit in a plain PHP file the string
	 * extractor reads - reading them through a variable here would leave them untranslatable.
	 * A group the manifest does not name has no label and falls back to its key, which is what
	 * a module key the manifest does not stage would give.
	 *
	 * @since 4.0.0
	 *
	 * @param string $group Group key, which is a module key unless the manifest named another.
	 *
	 * @return string
	 */
	private static function get_module_label( $group ): string {
		$label = SafeMode::get_staged_group_label( (string) $group );

		return '' !== $label ? $label : (string) $group;
	}

	/**
	 * Display name for a changed setting, falling back to the raw key.
	 *
	 * @param Configs $configs The configs module, which holds the setting name map.
	 * @param string  $module  Module key.
	 * @param string  $setting Dotted leaf path below the module.
	 *
	 * @return string
	 */
	private static function format_setting_label( $configs, $module, $setting ): string {
		$path = preg_replace( '/\.\d+(?=\.|$)/', '', (string) $setting );

		while ( '' !== $path ) {
			$label = SafeMode::get_staged_setting_label( $module, $path );
			if ( '' !== $label ) {
				return $label;
			}

			$label = $configs->get_setting_label( $module, $path );
			if ( $label ) {
				return $label;
			}

			$separator = strrpos( $path, '.' );
			if ( false === $separator ) {
				break;
			}

			$path = substr( $path, 0, $separator );
		}

		return (string) $setting;
	}

	/**
	 * Display label for a changed setting value, falling back to the formatted value.
	 *
	 * The fallback lives here for the same reason `format_setting_label()`'s does: the React
	 * table renders one field, so it cannot pick a label the backend would not have picked.
	 * A value the map does not name falls back to the formatted value - which is already
	 * English for counts, free text and numbers - and a boolean falls back to the Active /
	 * Inactive wording `Configs::get_setting_description()` uses for the same booleans.
	 *
	 * @since 4.0.0
	 *
	 * @param Configs $configs The configs module, which holds the setting value map.
	 * @param string  $module  Module key.
	 * @param string  $setting Dotted leaf path below the module.
	 * @param mixed   $value   The leaf value.
	 *
	 * @return mixed
	 */
	private static function format_value_label( $configs, $module, $setting, $value ) {
		// Post revisions is a count, but the control's own hint reads "enter 0 to disable
		// revisions", so zero is an off state rather than a number. Naming it keeps the row
		// readable without the user having to know that convention.
		if ( 'advanced' === $module && 'post_revisions' === $setting && 0 === absint( $value ) ) {
			return __( 'Off', 'wphb' );
		}

		if ( 'rss' === $module && 'duration' === $setting ) {
			$seconds = absint( $value );

			if ( 0 === $seconds % HOUR_IN_SECONDS ) {
				$hours = $seconds / HOUR_IN_SECONDS;

				/* translators: %d: number of hours. */
				return sprintf( _n( '%d hour', '%d hours', $hours, 'wphb' ), $hours );
			}

			if ( 0 === $seconds % MINUTE_IN_SECONDS ) {
				$minutes = $seconds / MINUTE_IN_SECONDS;

				/* translators: %d: number of minutes. */
				return sprintf( _n( '%d minute', '%d minutes', $minutes, 'wphb' ), $minutes );
			}

			/* translators: %d: number of seconds. */
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'wphb' ), $seconds );
		}

		if ( 'minify' === $module && 'delay_js_timeout' === $setting ) {
			$seconds = absint( $value );

			return sprintf(
				/* translators: %d: Delay JavaScript timeout in seconds. */
				_n( '%d second', '%d seconds', $seconds, 'wphb' ),
				$seconds
			);
		}

		if ( self::is_custom_value_setting( $module, $setting ) ) {
			return empty( $value ) ? __( 'Empty', 'wphb' ) : __( 'Custom', 'wphb' );
		}

		$value_labels = SafeMode::get_staged_value_labels( $module, $setting );
		if ( is_scalar( $value ) && isset( $value_labels[ $value ] ) ) {
			return $value_labels[ $value ];
		}

		$label = $configs->get_setting_value_label( $module, $setting, $value );

		if ( '' !== $label ) {
			return $label;
		}

		if ( is_bool( $value ) ) {
			return $value ? __( 'Active', 'wphb' ) : __( 'Inactive', 'wphb' );
		}

		return self::format_value( $value );
	}

	/**
	 * Check whether a setting value should be represented as custom text.
	 *
	 * @param string $module  Module key.
	 * @param string $setting Dotted leaf path below the module.
	 *
	 * @return bool
	 */
	private static function is_custom_value_setting( $module, $setting ): bool {
		return isset( self::CUSTOM_VALUE_SETTINGS[ $module ] )
			&& in_array( $setting, self::CUSTOM_VALUE_SETTINGS[ $module ], true );
	}

	/**
	 * Build the Safe Mode payload.
	 *
	 * Matches the shape of the page data payload, which is the single source of truth for it.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_safe_mode_payload(): array {
		$safe_mode = SafeMode::instance();

		return array(
			'active'       => $safe_mode->get_status(),
			'hasChanges'   => $safe_mode->has_changes_to_publish(),
			// README §7.2 pins the count at 0 while Safe Mode is off.
			'changeCount'  => $safe_mode->get_status() ? self::get_change_count() : 0,
			'previewUrl'   => $safe_mode->get_safe_mode_preview_url(),
			'introPending' => SafeMode::is_intro_pending(),
			'published'    => isset( $_GET['wphb_safemode_published'] ), // Input var ok.
			'canManage'    => current_user_can( Utils::get_admin_capability() ),
		);
	}
}
