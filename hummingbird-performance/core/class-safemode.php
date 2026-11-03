<?php
/**
 * Safe Mode Integration Class
 *
 * @package Hummingbird\Core
 */

namespace Hummingbird\Core;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Admin\Ajax\Notices;
use Hummingbird\Core\Installer;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Filesystem;
use Hummingbird\Core\Modules\Caching\Fast_CGI;

/**
 * Safe Mode Integration Class
 *
 * @since 3.18.0
 */
class SafeMode {

	/**
	 * Safe mode status.
	 *
	 * @var boolean
	 */
	private $is_enabled = null;

	/**
	 * Previewing safe mode.
	 *
	 * @var boolean
	 */
	private $previewing_safe_mode = null;

	/**
	 * Filesystem instance.
	 *
	 * @var Filesystem
	 */
	private $fs;

	/**
	 * Hummingbird settings option name.
	 */
	public const HB_SETTINGS_OPTION = 'wphb_settings';

	/**
	 * Safe mode settings option name.
	 */
	public const SAFE_MODE_SETTINGS_OPTION = 'wphb_safe_mode_settings';

	/**
	 * React notice ID that gates the Safe Mode intro.
	 *
	 * The intro shares the notices endpoint and per-user dismissal store the auto-save
	 * intro already uses, so it needs no option or user meta of its own.
	 */
	public const SAFE_MODE_INTRO_NOTICE_ID = 'safe-mode-intro';

	/**
	 * Safe mode status option name.
	 */
	private const SAFE_MODE_STATUS_OPTION = 'wphb_safe_mode_status';

	/**
	 * Safe mode additional settings transient name.
	 */
	public const SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT = 'wphb_safe_mode_additional_settings';

	/**
	 * Activity log module slug for the two entries Safe Mode itself writes.
	 */
	public const ACTIVITY_MODULE = 'safe-mode';

	/**
	 * Cached copy of the staged settings manifest.
	 *
	 * Loaded from `safe-mode/staged-settings.php` on first use. The load is deferred because the
	 * file calls `__()`, which is not available that early on every request that includes this
	 * class.
	 *
	 * @var array|null
	 */
	private static $staged_settings = null;

	/**
	 * Keys that describe a setting rather than name a child of it.
	 *
	 * `is_nested_group()` uses these to tell a group of settings from a leaf that happens to
	 * hold only arrays - `parent_is` is one, so a leaf carrying just that would otherwise read
	 * as a group. Keep in step with the keys documented in `safe-mode/staged-settings.php`.
	 *
	 * @var array
	 */
	private const SETTING_META_KEYS = array( 'group', 'label', 'parent', 'parent_is', 'stored', 'values', 'hidden' );

	/**
	 * Get the staged settings manifest, keyed by group.
	 *
	 * Each entry is `array( 'module', 'settings' )`. The module is the option set the settings
	 * live under, which several groups can share - the Asset Optimization page renders four
	 * cards against the one `minify` option - and the group is the card the inventory lists.
	 *
	 * @return array Group => `array( 'module', 'settings' )`.
	 */
	private static function staged_settings(): array {
		if ( null === self::$staged_settings ) {
			$groups = array();

			foreach ( (array) require __DIR__ . '/safe-mode/staged-settings.php' as $group => $manifest ) {
				$groups[ $group ] = array(
					'module'   => $manifest['module'],
					'label'    => (string) ( $manifest['label'] ?? '' ),
					'settings' => (array) require $manifest['source'],
				);
			}

			self::$staged_settings = $groups;
		}

		return self::$staged_settings;
	}

	/**
	 * Get the settings one module stages, merged across the groups that share it.
	 *
	 * A module's settings are filtered as one array, so several groups over the same option have
	 * to be merged keyed by setting. `array_merge()` would do it because the keys are strings,
	 * but the loop keeps the merge order explicit.
	 *
	 * @param string $module Module key.
	 *
	 * @return array Setting key => manifest entry.
	 */
	private static function staged_settings_for_module( string $module ): array {
		$settings = array();

		foreach ( self::staged_settings() as $manifest ) {
			if ( $module !== $manifest['module'] ) {
				continue;
			}

			foreach ( $manifest['settings'] as $key => $meta ) {
				$settings[ $key ] = $meta;
			}
		}

		return $settings;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->fs = Filesystem::instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SafeMode
	 */
	public static function instance(): SafeMode {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Check whether the Safe Mode intro should be shown to the current user.
	 *
	 * The dismissal is the shared React notice record, so it is written by the notices
	 * endpoint and stays scoped to the user who acknowledged the intro.
	 *
	 * @return bool
	 */
	public static function is_intro_pending(): bool {
		return ! Notices::is_dismissed( self::SAFE_MODE_INTRO_NOTICE_ID );
	}

	/**
	 * Frontend safe mode box display.
	 *
	 * @return void
	 */
	public function init_safe_mode_box(): void {
		if ( ! $this->previewing_safe_mode() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action( 'wp_body_open', array( $this, 'display_safe_mode_box' ) );
	}

	/**
	 * Initialize safe mode settings.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		// The counter no longer belongs to the settings option, and an install that has not run
		// `upgrade_4_0_0()` yet still carries the legacy value - migrating it first is what lets
		// the mirror below copy an already-clean option, so the snapshot never holds the counter.
		//
		// Skipped while an upgrade is in flight: `Installer::upgrade()` defines `WPHB_UPGRADING`
		// before it reaches `upgrade_4_0_0()`, which already runs this same migration earlier in
		// the request. Running it twice would re-read and re-write `wphb_settings` in the middle of
		// the upgrade, which is the exact read-modify-write overlap the extraction set out to
		// remove. Safe Mode still migrates on its own for installs that never hit `upgrade_4_0_0()`.
		if ( ! defined( 'WPHB_UPGRADING' ) ) {
			Installer::migrate_pages_cached_to_own_option();
		}

		$hb_settings = (array) Settings::get_settings( false, self::HB_SETTINGS_OPTION );
		if ( ! empty( $hb_settings ) ) {
			Settings::update_settings( $hb_settings, false, self::SAFE_MODE_SETTINGS_OPTION );

			$config_file = $this->fs->basedir . 'wphb-cache.php';
			if ( file_exists( $config_file ) ) {
				$this->fs->write( $this->fs->basedir . '/wphb-cache.backup.php', file_get_contents( $config_file ) );
			}
		}
		$this->track_additional_settings();
	}

	/**
	 * Check if the current request is a safe mode call.
	 *
	 * @return boolean
	 */
	public function is_safemode_call(): bool {
		$is_lighthouse = isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 'Lighthouse' ) !== false;

		// Return safe mode settings if enabled and in appropriate contexts (admin, REST API, cron, performance test, previewing).
		return $this->get_status() && ( is_admin() || defined( 'REST_REQUEST' ) || defined( 'DOING_CRON' ) || $is_lighthouse || $this->previewing_safe_mode() );
	}

	/**
	 * Maybe append safe mode query arg to URL.
	 *
	 * @param string $url The URL to modify.
	 * @return string The modified URL.
	 */
	public function maybe_append_query_arg( $url ): string {
		if ( $this->get_status() ) {
			$url = add_query_arg( 'wphb_preview_safe_mode', 'true', $url );
		}

		return $url;
	}

	/**
	 * Get safe mode status.
	 *
	 * @return boolean
	 */
	public function get_status(): bool {
		if ( is_null( $this->is_enabled ) ) {
			$value            = Settings::get( self::SAFE_MODE_STATUS_OPTION, false );
			$this->is_enabled = (bool) $value;
		}

		return $this->is_enabled;
	}

	/**
	 * Set safe mode status, logging the change.
	 *
	 * The order of the two `Activity_Log::record()` calls here is not free. `record()` reads
	 * the flag through `get_status()`, which returns the in-memory `is_enabled`, and returns
	 * early while it is true. So the enable entry has to be written *before* the assignment
	 * below and the disable entry *after* it - taking either on the wrong side of the
	 * assignment leaves it swallowed by that guard.
	 *
	 * This is the only writer of the flag, so every path that enters or leaves Safe Mode is
	 * logged here: the React endpoints, the legacy toggle, and every `delete_data()` caller,
	 * which delegates.
	 *
	 * @param boolean $status The new status.
	 * @param string  $reason Optional. Why Safe Mode is being left, which picks the disable
	 *                        message. `'published'` and `'discarded'` name the two ways of
	 *                        leaving with changes in hand; anything else, including the
	 *                        default, reads as a plain disable.
	 */
	public function set_status( $status, $reason = '' ): void {
		if ( $status ) {
			Activity_Log::record( self::ACTIVITY_MODULE, __( 'Safe Mode enabled.', 'wphb' ) );
		}

		$this->is_enabled = (bool) $status;
		Settings::update( self::SAFE_MODE_STATUS_OPTION, $this->is_enabled );
		self::write_page_cache_settings( $this->is_enabled );

		if ( ! $status && 'reset' !== $reason ) {
			if ( 'published' === $reason ) {
				$message = __( 'Safe Mode changes published.', 'wphb' );
			} elseif ( 'discarded' === $reason ) {
				$message = __( 'Safe Mode changes discarded.', 'wphb' );
			} else {
				$message = __( 'Safe Mode disabled.', 'wphb' );
			}

			Activity_Log::record( self::ACTIVITY_MODULE, $message );
		}
	}

	/**
	 * Discard safe mode changes in cache file and restore last Hummingbird settings.
	 */
	public function discard_cache_config_file(): void {
		if ( file_exists( $this->fs->basedir . '/wphb-cache.backup.php' ) ) {
			$this->fs->write( $this->fs->basedir . '/wphb-cache.php', file_get_contents( $this->fs->basedir . '/wphb-cache.backup.php' ) );
		}
	}

	/**
	 * Delete safe mode settings and status after disabling.
	 *
	 * @param string $reason Optional. Why Safe Mode is being left, passed through to
	 *                       `set_status()` to pick the activity log message. The default is
	 *                       what a bare `delete_data()` on the toggle-off path gets.
	 */
	public function delete_data( $reason = '' ): void {
		$this->set_status( false, $reason );
		if ( is_main_site() && is_multisite() ) {
				delete_site_option( self::SAFE_MODE_SETTINGS_OPTION );
				delete_site_option( self::SAFE_MODE_STATUS_OPTION );
		} else {
			delete_option( self::SAFE_MODE_SETTINGS_OPTION );
			delete_option( self::SAFE_MODE_STATUS_OPTION );
		}
	}

	/**
	 * Reset safe mode settings to last Hummingbird settings.
	 */
	public function reset_safe_mode_settings(): bool {
		if ( ! $this->get_status() ) {
			return false;
		}

		$hb_settings = (array) Settings::get_settings( false, self::HB_SETTINGS_OPTION );
		if ( ! empty( $hb_settings ) ) {
			Settings::update_settings( $hb_settings, false, self::SAFE_MODE_SETTINGS_OPTION );

			$this->fs->write( $this->fs->basedir . '/wphb-cache.php', file_get_contents( $this->fs->basedir . '/wphb-cache.backup.php' ) );
		}

		return true;
	}

	/**
	 * Publish safe mode settings to Hummingbird settings.
	 *
	 * @return void
	 */
	public function publish_safe_mode_settings(): void {
		$safe_mode_settings = Settings::get_settings( false, self::SAFE_MODE_SETTINGS_OPTION );
		Settings::update_settings( $safe_mode_settings, false, self::HB_SETTINGS_OPTION );

		$config_file = $this->fs->basedir . '/wphb-cache.backup.php';
		if ( file_exists( $config_file ) ) {
			unlink( $config_file );
		}
	}

	/**
	 * Check if safe mode is being previewed.
	 *
	 * @return boolean
	 */
	public function previewing_safe_mode(): bool {
		if ( null === $this->previewing_safe_mode ) {
			$safe_mode_status  = $this->get_status();
			$query_param_value = filter_input( INPUT_GET, 'wphb_preview_safe_mode', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			$this->previewing_safe_mode = $safe_mode_status && true === $query_param_value;
		}

		return $this->previewing_safe_mode;
	}

	/**
	 * Write page cache safe mode status to page cache settings.
	 *
	 * @param boolean $status The safe mode status.
	 * @return void
	 */
	private function write_page_cache_settings( bool $status ): void {
		$module                                 = Utils::get_module( 'page_cache' );
		$page_cache_settings                    = $module->get_settings();
		$page_cache_settings['safemode_status'] = $status;
		$module->save_settings( $page_cache_settings );
	}

	/**
	 * Filters safemode settings.
	 *
	 * @param array        $options The settings.
	 * @param string|false $specific_module Optional. Specific module to filter sub-options from. Default false.
	 *
	 * @return array
	 */
	public static function filter_options( array $options, $specific_module = false ): array {
		// Several groups can share a module, so the module list is derived from the groups
		// rather than read off them.
		$modules = array();
		foreach ( self::staged_settings() as $manifest ) {
			$modules[ $manifest['module'] ] = true;
		}
		$modules = false === $specific_module ? array_keys( $modules ) : array( $specific_module );

		foreach ( $modules as $module ) {
			if ( ! isset( $options[ $module ] ) || ! is_array( $options[ $module ] ) ) {
				continue;
			}

			$options[ $module ] = self::keep_staged_options( $options[ $module ], self::staged_settings_for_module( $module ) );
		}

		// A module the manifest does not name is not staged at all.
		if ( false === $specific_module ) {
			$options = array_intersect_key( $options, array_flip( $modules ) );
		}

		return $options;
	}

	/**
	 * Keep only the options named by a module's manifest entry.
	 *
	 * @param array $settings Module settings.
	 * @param array $staged   Manifest entries for the module.
	 *
	 * @return array
	 */
	private static function keep_staged_options( array $settings, array $staged ): array {
		$kept = array();

		foreach ( $staged as $key => $meta ) {
			// Some settings are stored under keys that differ from the manifest key; keep them all.
			if ( ! empty( $meta['stored'] ) ) {
				foreach ( (array) $meta['stored'] as $path ) {
					if ( array_key_exists( $path, $settings ) ) {
						$kept[ $path ] = $settings[ $path ];
					}
				}
				continue;
			}

			// A nested group such as `lazy_load` or `do_assets` holds its own entries.
			if ( self::is_nested_group( $meta ) ) {
				if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
					$kept[ $key ] = self::keep_staged_options( $settings[ $key ], $meta );
				}
				continue;
			}

			if ( array_key_exists( $key, $settings ) ) {
				$kept[ $key ] = $settings[ $key ];
			}
		}

		return $kept;
	}

	/**
	 * Whether a manifest entry is a group of nested settings rather than a setting.
	 *
	 * A nested group holds only entries, one per setting, and carries no metadata of its own.
	 * The metadata keys are checked first: `parent_is` is an array, so a leaf carrying only that
	 * would otherwise look like a group whose children are `parent_is`'s keys.
	 *
	 * @param array $meta Manifest entry.
	 *
	 * @return bool
	 */
	private static function is_nested_group( array $meta ): bool {
		if ( empty( $meta ) ) {
			return false;
		}

		foreach ( self::SETTING_META_KEYS as $meta_key ) {
			if ( array_key_exists( $meta_key, $meta ) ) {
				return false;
			}
		}

		foreach ( $meta as $value ) {
			if ( ! is_array( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Filter two settings snapshots to the options represented by the active UI.
	 *
	 * Parent-setting conditions use the Safe Mode snapshot and must remove inactive
	 * child options from both snapshots to keep change detection and reporting aligned.
	 *
	 * @param array $hb_settings        Live Hummingbird settings.
	 * @param array $safe_mode_settings Safe Mode settings.
	 *
	 * @return array Filtered live and Safe Mode settings.
	 */
	public static function filter_comparable_options( array $hb_settings, array $safe_mode_settings ): array {
		$hb_settings        = self::filter_options( $hb_settings );
		$safe_mode_settings = self::filter_options( $safe_mode_settings );

		// Combine and Compress are stored independently of the module toggle. Compare their
		// effective values so enabling Asset Optimization can show the child rows in the review.
		$hb_settings        = self::normalize_minify_child_settings( $hb_settings );
		$safe_mode_settings = self::normalize_minify_child_settings( $safe_mode_settings );

		if ( Fast_CGI::is_fast_cgi_enabled() ) {
			$local_server_options = array( 'enabled', 'detection', 'integrations' );
			$hb_settings['page_cache']        = self::unset_option_paths( $hb_settings['page_cache'] ?? array(), $local_server_options );
			$safe_mode_settings['page_cache'] = self::unset_option_paths( $safe_mode_settings['page_cache'] ?? array(), $local_server_options );
		}

		$hb_settings        = self::drop_inactive_options( $hb_settings, $safe_mode_settings );
		$safe_mode_settings = self::drop_inactive_options( $safe_mode_settings, $safe_mode_settings );

		$hb_settings        = self::drop_hidden_options( $hb_settings );
		$safe_mode_settings = self::drop_hidden_options( $safe_mode_settings );

		return array( $hb_settings, $safe_mode_settings );
	}

	/**
	 * Normalize Asset Optimization child toggles for comparison.
	 *
	 * The stored child values can remain enabled while Asset Optimization is off. They do not
	 * affect the site until the module is active, so compare them as off in that state.
	 *
	 * @param array $settings Settings snapshot.
	 *
	 * @return array
	 */
	private static function normalize_minify_child_settings( array $settings ): array {
		if ( empty( $settings['minify'] ) || ! is_array( $settings['minify'] ) ) {
			return $settings;
		}

		$minify_enabled = $settings['minify']['enabled'] ?? false;
		if ( is_multisite() ) {
			$minify_enabled = $minify_enabled && ( $settings['minify']['minify_blog'] ?? false );
		}

		if ( $minify_enabled ) {
			return $settings;
		}

		foreach ( array( 'compress', 'combine' ) as $setting ) {
			if ( array_key_exists( $setting, $settings['minify'] ) ) {
				$settings['minify'][ $setting ] = false;
			}
		}

		return $settings;
	}

	/**
	 * Remove the staged settings that are never listed.
	 *
	 * A hidden setting is staged so the settings under it can resolve their `parent`, and the
	 * inventory omits the row it would produce. Dropping it here as well keeps the comparison in
	 * step: `has_changes_to_publish()` compares this pair, and a change to a control the user
	 * cannot see would otherwise leave it claiming changes beside an empty table.
	 *
	 * @param array $settings Settings, already filtered to the staged options.
	 *
	 * @return array
	 */
	private static function drop_hidden_options( array $settings ): array {
		foreach ( self::staged_settings() as $manifest ) {
			$module = $manifest['module'];

			if ( ! isset( $settings[ $module ] ) || ! is_array( $settings[ $module ] ) ) {
				continue;
			}

			foreach ( $manifest['settings'] as $key => $meta ) {
				if ( ! empty( $meta['hidden'] ) ) {
					unset( $settings[ $module ][ $key ] );
				}
			}
		}

		return $settings;
	}

	/**
	 * Remove settings whose parent is off in the Safe Mode snapshot.
	 *
	 * The parent is read from the snapshot on both sides, so the two inventories stay aligned
	 * whatever the live values are.
	 *
	 * @param array $settings           Settings to prune.
	 * @param array $safe_mode_settings Snapshot the parent state is read from.
	 *
	 * @return array
	 */
	private static function drop_inactive_options( array $settings, array $safe_mode_settings ): array {
		foreach ( self::staged_settings() as $manifest ) {
			$module = $manifest['module'];

			if ( ! isset( $settings[ $module ] ) || ! is_array( $settings[ $module ] ) ) {
				continue;
			}

			// `parent` is written relative to the module, so the snapshot the whole module is
			// resolved against stays the module level even inside a nested group.
			$settings[ $module ] = self::drop_inactive_in_module(
				$settings[ $module ],
				self::staged_settings_for_module( $module ),
				$safe_mode_settings[ $module ] ?? array(),
				$safe_mode_settings[ $module ] ?? array()
			);
		}

		return $settings;
	}

	/**
	 * Prune one module's settings against its manifest and the snapshot's parent values.
	 *
	 * @param array $settings  Module settings.
	 * @param array $staged    Manifest entries for the level being walked.
	 * @param array $snapshot  Same level from the Safe Mode snapshot.
	 * @param array $module_snapshot Module-level snapshot, which `parent` paths resolve against.
	 *
	 * @return array
	 */
	private static function drop_inactive_in_module( array $settings, array $staged, array $snapshot, array $module_snapshot ): array {
		foreach ( $staged as $key => $meta ) {
			if ( self::is_nested_group( $meta ) ) {
				if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
					$settings[ $key ] = self::drop_inactive_in_module(
						$settings[ $key ],
						$meta,
						(array) ( $snapshot[ $key ] ?? array() ),
						$module_snapshot
					);
				}
				continue;
			}

			if ( empty( $meta['parent'] ) && empty( $meta['parent_is'] ) ) {
				continue;
			}

			// A setting can carry both conditions, so either one being met hides it: `parent`
			// hides the row while its parent is off, and `parent_is` while the parent equals a
			// given value. Asset Optimization's settings use both - the module toggle gates
			// them, and Dev Mode locks them even while the module is on.
			$inactive = false;

			if ( ! empty( $meta['parent'] ) ) {
				$inactive = ! self::is_parent_enabled( $meta['parent'], $module_snapshot );
			}

			if ( ! $inactive && ! empty( $meta['parent_is'] ) ) {
				$parent_path  = (string) key( (array) $meta['parent_is'] );
				$parent_match = (string) current( (array) $meta['parent_is'] );

				$inactive = self::get_parent_value( $parent_path, $module_snapshot ) === $parent_match;
			}

			if ( ! $inactive ) {
				continue;
			}

			// A setting the UI shows as one control may be stored under several keys, so the
			// manifest key alone would leave the raw database keys behind.
			foreach ( array_merge( array( $key ), (array) ( $meta['stored'] ?? array() ) ) as $path ) {
				unset( $settings[ $path ] );
			}
		}

		return $settings;
	}

	/**
	 * Read a dotted path out of the module snapshot, without the enabled check.
	 *
	 * @param string $parent Dotted path of the parent, relative to the module.
	 * @param array  $snapshot Module settings from the snapshot.
	 *
	 * @return string The value cast to string, or an empty string when the path is absent.
	 */
	private static function get_parent_value( string $parent, array $snapshot ): string {
		$value = $snapshot;

		foreach ( explode( '.', $parent ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return '';
			}

			$value = $value[ $segment ];
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Whether the parent a setting depends on is on in the snapshot.
	 *
	 * @param string $parent   Dotted path of the parent.
	 * @param array  $snapshot Module settings from the snapshot.
	 *
	 * @return bool
	 */
	private static function is_parent_enabled( string $parent, array $snapshot ): bool {
		$value = $snapshot;

		foreach ( explode( '.', $parent ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return false;
			}

			$value = $value[ $segment ];
		}

		return ! empty( $value );
	}

	/**
	 * Flatten a module's manifest entries into an ordered list of paths.
	 *
	 * @param array  $staged Manifest entries.
	 * @param string $prefix Path prefix for nested groups.
	 *
	 * @return array
	 */
	private static function flatten_staged_settings( array $staged, string $prefix = '' ): array {
		$rows = array();

		foreach ( $staged as $key => $meta ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

			if ( self::is_nested_group( $meta ) ) {
				$rows = array_merge( $rows, self::flatten_staged_settings( $meta, $path ) );
				continue;
			}

			$rows[] = array(
				'path'   => $path,
				'label'  => (string) ( $meta['label'] ?? '' ),
				'values' => (array) ( $meta['values'] ?? array() ),
			);
		}

		return $rows;
	}

	/**
	 * Get every setting the manifest stores under more than one key, keyed by module.
	 *
	 * Only the immediate manifest level is walked; no nested group declares `stored` today.
	 *
	 * @return array Module => `array( setting => array( stored path, … ) )`.
	 */
	public static function get_stored_settings(): array {
		$grouped = array();

		foreach ( self::staged_settings() as $manifest ) {
			$module = $manifest['module'];

			foreach ( $manifest['settings'] as $key => $meta ) {
				if ( empty( $meta['stored'] ) ) {
					continue;
				}

				$grouped[ $module ][ $key ] = array_values( (array) $meta['stored'] );
			}
		}

		return $grouped;
	}

	/**
	 * Get the label a manifest setting declares, if it declares one.
	 *
	 * Returns an empty string for a setting the manifest does not name or names without a label,
	 * which is most of them - the admin then falls back to `Configs`, whose map the Settings page
	 * renders from too. Only a setting Safe Mode words differently needs a label here.
	 *
	 * @param string $module  Module key.
	 * @param string $setting Dotted leaf path below the module, without the module.
	 *
	 * @return string
	 */
	public static function get_staged_setting_label( string $module, string $setting ): string {
		$staged = self::staged_settings_for_module( $module );
		$path   = (string) $setting;

		while ( '' !== $path ) {
			$label = self::find_staged_label( $staged, $path );
			if ( '' !== $label ) {
				return $label;
			}

			$separator = strrpos( $path, '.' );
			if ( false === $separator ) {
				break;
			}

			$path = substr( $path, 0, $separator );
		}

		return '';
	}

	/**
	 * Whether a manifest setting asks to be staged without being listed.
	 *
	 * A hidden setting is still staged and still gates the settings under it, but the inventory
	 * omits the row it would produce. Asset Optimization's module toggle is one: the page dropped
	 * its control when Compress and Combine replaced it, and the toggle follows those, so a
	 * change to it is not something the user set.
	 *
	 * @param string $module  Module key.
	 * @param string $setting Dotted leaf path below the module, without the module.
	 *
	 * @return bool
	 */
	public static function is_staged_hidden( string $module, string $setting ): bool {
		$meta = self::find_staged_meta( self::staged_settings_for_module( $module ), (string) $setting );

		return ! empty( $meta['hidden'] );
	}

	/**
	 * Walk a module's manifest entries to find the label declared at a dotted path.
	 *
	 * @param array  $staged Module manifest entries.
	 * @param string $path   Dotted path to resolve.
	 *
	 * @return string
	 */
	private static function find_staged_label( array $staged, string $path ): string {
		$segments = explode( '.', $path );
		$current  = $staged;

		foreach ( $segments as $segment ) {
			if ( ! isset( $current[ $segment ] ) ) {
				return '';
			}

			$meta = $current[ $segment ];

			// A leaf carries the metadata; a nested group holds the next level.
			if ( ! self::is_nested_group( $meta ) ) {
				return (string) ( $meta['label'] ?? '' );
			}

			$current = $meta;
		}

		return '';
	}

	/**
	 * Get the value labels a manifest setting declares, if it declares any.
	 *
	 * @param string $module  Module key.
	 * @param string $setting Dotted leaf path below the module, without the module.
	 *
	 * @return array Value => label.
	 */
	public static function get_staged_value_labels( string $module, string $setting ): array {
		$meta = self::find_staged_meta( self::staged_settings_for_module( $module ), (string) $setting );

		return (array) ( $meta['values'] ?? array() );
	}

	/**
	 * Get the inventory group a manifest setting belongs to.
	 *
	 * A module can render more than one card, and each card is reported as its own group. The
	 * manifest names the group where a setting's card differs from its module key; a setting
	 * without one belongs to the group named after its module.
	 *
	 * Existence is decided by `find_staged_path()` rather than by the metadata: a card's own
	 * toggle is declared with an empty array, and that is a setting like any other. Reading the
	 * metadata instead would send it to the module's group, so a card's toggle would be listed
	 * under a different card than the controls it gates.
	 *
	 * @param string $module  Module key.
	 * @param string $setting Dotted leaf path below the module, without the module.
	 *
	 * @return string Group key, falling back to the module.
	 */
	public static function get_staged_group( string $module, string $setting ): string {
		foreach ( self::staged_settings() as $group => $manifest ) {
			if ( $module !== $manifest['module'] ) {
				continue;
			}

			if ( self::find_staged_path( $manifest['settings'], (string) $setting ) ) {
				return $group;
			}
		}

		return $module;
	}

	/**
	 * Get the display name a manifest group declares.
	 *
	 * The label is a literal in a plain PHP file so the string extractor can read
	 * it; reading it through a variable would leave it untranslatable.
	 *
	 * @param string $group Group key, as returned by `get_staged_group()`.
	 *
	 * @return string Translated label, or an empty string when the group declares none.
	 */
	public static function get_staged_group_label( string $group ): string {
		$manifest = self::staged_settings()[ $group ] ?? null;

		return $manifest ? (string) $manifest['label'] : '';
	}

	/**
	 * Get the listing position of a manifest group.
	 *
	 * The manifest declares its groups in listing order, so position in the file
	 * is the rank.
	 *
	 * @param string $group Group key, as returned by `get_staged_group()`.
	 *
	 * @return int Zero-based position, or `PHP_INT_MAX` when the group is unnamed.
	 */
	public static function get_staged_group_weight( string $group ): int {
		$position = array_search( $group, array_keys( self::staged_settings() ), true );

		return false === $position ? PHP_INT_MAX : (int) $position;
	}

	/**
	 * Get the position of a setting within its group, in the order the manifest declares it.
	 *
	 * A setting the manifest folds together is not a manifest key itself - `font_swap`
	 * is declared by the Font Optimization card while the inventory reports it under
	 * `minify` - so those are matched by dotted path and anything unmatched sorts last.
	 *
	 * @param string $group   Group key, as returned by `get_staged_group()`.
	 * @param string $setting Dotted leaf path of the row.
	 * @param string $module  Module key, to collapse a card's paths onto its manifest.
	 *
	 * @return int Zero-based position, or `PHP_INT_MAX` when the group is unnamed.
	 */
	public static function get_staged_setting_weight( string $group, string $setting, string $module = '' ): int {
		$manifest = self::staged_settings()[ $group ] ?? null;

		if ( ! $manifest ) {
			return PHP_INT_MAX;
		}

		$paths = array();

		foreach ( self::flatten_staged_settings( $manifest['settings'] ) as $position => $row ) {
			$paths[ $row['path'] ] = $position;

			// A composite row can arrive with the module prefix already applied.
			if ( '' !== $module && $module === $manifest['module'] ) {
				$paths[ $module . '.' . $row['path'] ] = $position;
			}
		}

		$path = (string) $setting;

		// A grouped row is stored under several keys, so match its manifest key too.
		while ( '' !== $path ) {
			if ( isset( $paths[ $path ] ) ) {
				return (int) $paths[ $path ];
			}

			$separator = strrpos( $path, '.' );
			if ( false === $separator ) {
				break;
			}

			$path = substr( $path, 0, $separator );
		}

		return PHP_INT_MAX;
	}

	/**
	 * Walk a module's manifest entries to find the metadata declared at a dotted path.
	 *
	 * @param array  $staged Module manifest entries.
	 * @param string $path   Dotted path to resolve.
	 *
	 * @return array
	 */
	private static function find_staged_meta( array $staged, string $path ): array {
		$current = $staged;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
				return array();
			}

			$current = $current[ $segment ];
		}

		return self::is_nested_group( $current ) ? array() : $current;
	}

	/**
	 * Whether a manifest declares a setting at a dotted path.
	 *
	 * Separate from `find_staged_meta()` because a declared setting can carry no metadata: a
	 * card's own toggle is written as an empty array, and that reads the same as a setting the
	 * manifest does not name. This answers the question the metadata cannot.
	 *
	 * @param array  $staged Module manifest entries.
	 * @param string $path   Dotted path of the setting.
	 *
	 * @return bool
	 */
	private static function find_staged_path( array $staged, string $path ): bool {
		$current = $staged;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
				return false;
			}

			$current = $current[ $segment ];
		}

		return ! self::is_nested_group( $current );
	}

	/**
	 * Remove top-level or dotted option paths from a module's settings.
	 *
	 * @param array $options Settings for one module.
	 * @param array $paths   Option paths to remove.
	 *
	 * @return array
	 */
	private static function unset_option_paths( array $options, array $paths ): array {
		foreach ( $paths as $path ) {
			$segments = explode( '.', $path );
			$leaf     = array_pop( $segments );
			$target   = &$options;

			foreach ( $segments as $segment ) {
				if ( ! isset( $target[ $segment ] ) || ! is_array( $target[ $segment ] ) ) {
					continue 2;
				}

				$target = &$target[ $segment ];
			}

			unset( $target[ $leaf ] );
			unset( $target );
		}

		return $options;
	}

	/**
	 * Display safe mode DIV on front-end.
	 *
	 * @since 3.18.0
	 * @refactored from Core->display_safe_mode_box()
	 *
	 * @return void
	 */
	public function display_safe_mode_box(): void {
		?>
		<style>
		/* Wrapper */
		#wphb-safe-mode-notice {
			position: sticky;
			top: 0;
			left: 0;
			width: 100%;
			z-index: 99998;
			box-sizing: border-box;
			margin: 0;
			padding: 15px 20px;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: center;
			gap: 10px 30px;
			background: #fff4d6;
			border-bottom: 1px solid #ffda78;
			font-family: Roboto, Arial, sans-serif;
			color: #1a1a1a;
		}

		#wphb-safe-mode-notice,
		#wphb-safe-mode-notice *,
		#wphb-safe-mode-notice *::before,
		#wphb-safe-mode-notice *::after {
			box-sizing: border-box;
		}

		#wphb-safe-mode-notice p {
			margin: 0;
			padding: 0;
			font-family: inherit;
			color: #1a1a1a;
			text-align: left;
		}

		/* Content */
		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__content {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			flex: 1 1 320px;
			min-width: 0;
			max-width: 100%;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__icon {
			display: flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 auto;
			width: 30px;
			height: 30px;
			margin-top: 3px;
			border-radius: 12px;
			background: #ffb900;
			color: #ffffff;
			line-height: 0;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__icon svg {
			display: block;
			width: 16px;
			height: 16px;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__text {
			flex: 1 1 auto;
			min-width: 0;
			word-break: break-word;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__title,
		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__description {
			font-size: 14px;
			line-height: 22px;
			letter-spacing: 0;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__title {
			font-weight: 700;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__description {
			font-weight: 400;
		}

		/* Actions */
		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: center;
			gap: 5px;
			flex: 0 0 auto;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button {
			display: inline-flex !important;
			align-items: center;
			justify-content: center;
			gap: 5px;
			height: 30px;
			margin: 0 !important;
			padding: 0 10px !important;
			border: 1px solid rgba(26, 26, 26, 0.15) !important;
			border-radius: 10px !important;
			background: transparent !important;
			box-shadow: none !important;
			color: #1a1a1a !important;
			font-family: inherit !important;
			font-size: 12px !important;
			font-weight: 700 !important;
			line-height: 20px;
			letter-spacing: 0.1px;
			text-transform: none !important;
			text-decoration: none !important;
			white-space: nowrap;
			vertical-align: middle;
			cursor: pointer;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button:hover,
		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button:focus {
			background: rgba(26, 26, 26, 0.05) !important;
			color: #1a1a1a !important;
			text-decoration: none !important;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button--primary {
			border-color: #1a1a1a !important;
			background: #1a1a1a !important;
			color: #ffffff !important;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button--primary:hover,
		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button--primary:focus {
			border-color: #333333 !important;
			background: #333333 !important;
			color: #ffffff !important;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button:disabled {
			opacity: 0.6;
			cursor: default;
		}

		#wphb-safe-mode-notice .wpmudev-hb-safe-mode-bar__button svg {
			display: block;
			flex: 0 0 auto;
			width: 16px;
			height: 16px;
		}

		#wphb-copy-test-link {
			position: relative;
		}

		#wphb-copy-test-link::before,
		#wphb-copy-test-link::after {
			position: absolute;
			left: 50%;
			opacity: 0;
			pointer-events: none;
			transform: translateX(-50%);
			transition: opacity 150ms ease;
		}

		#wphb-copy-test-link::before {
			content: '';
			top: calc(100% + 9px);
			border-right: 9px solid transparent;
			border-bottom: 9px solid #1a1a1a;
			border-left: 9px solid transparent;
		}

		#wphb-copy-test-link::after {
			content: attr(data-tooltip);
			top: calc(100% + 16px);
			z-index: 1;
			padding: 4px 10px;
			border-radius: 15px;
			background: #1a1a1a;
			color: #ffffff;
			font-size: 12px;
			font-weight: 400;
			line-height: 18px;
			white-space: nowrap;
		}

		#wphb-copy-test-link.wpmudev-hb-safe-mode-bar__button--tooltip-visible::before,
		#wphb-copy-test-link.wpmudev-hb-safe-mode-bar__button--tooltip-visible::after {
			opacity: 1;
		}

		body.admin-bar #wphb-safe-mode-notice {
			top: 32px;
		}

		@media screen and (max-width: 782px) {
			body.admin-bar #wphb-safe-mode-notice {
				top: 46px;
			}
		}
		</style>

		<div id="wphb-safe-mode-notice" class="wpmudev-hb-safe-mode-bar">
			<div class="wpmudev-hb-safe-mode-bar__content">
				<span class="wpmudev-hb-safe-mode-bar__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
						<path d="M7.99609 15.4961C7.93359 15.4961 7.85547 15.4831 7.76172 15.457C7.67318 15.431 7.58203 15.3919 7.48828 15.3398C6.4362 14.7513 5.54557 14.2279 4.81641 13.7695C4.09245 13.3164 3.50911 12.8737 3.06641 12.4414C2.6237 12.0091 2.30078 11.543 2.09766 11.043C1.89974 10.543 1.80078 9.95703 1.80078 9.28516V3.66016C1.80078 3.24349 1.88411 2.94141 2.05078 2.75391C2.22266 2.56641 2.48047 2.40234 2.82422 2.26172C3.03255 2.17318 3.33984 2.05339 3.74609 1.90234C4.15755 1.74609 4.59766 1.58203 5.06641 1.41016C5.53516 1.23828 5.97266 1.08203 6.37891 0.941406C6.78516 0.795573 7.09245 0.69401 7.30078 0.636719C7.41016 0.605469 7.52474 0.576823 7.64453 0.550781C7.76432 0.519531 7.88151 0.503906 7.99609 0.503906C8.11589 0.503906 8.23568 0.516927 8.35547 0.542969C8.47526 0.563802 8.58724 0.595052 8.69141 0.636719C8.90495 0.704427 9.21484 0.811198 9.62109 0.957031C10.0273 1.09766 10.4648 1.25391 10.9336 1.42578C11.4023 1.59245 11.8398 1.7513 12.2461 1.90234C12.6523 2.05339 12.9622 2.17318 13.1758 2.26172C13.5091 2.40755 13.7617 2.57422 13.9336 2.76172C14.1107 2.94401 14.1992 3.24349 14.1992 3.66016V9.28516C14.1992 9.95703 14.0977 10.5456 13.8945 11.0508C13.6966 11.5508 13.3763 12.0169 12.9336 12.4492C12.4909 12.8815 11.9049 13.3242 11.1758 13.7773C10.4518 14.2357 9.5612 14.7565 8.50391 15.3398C8.41016 15.3919 8.31641 15.431 8.22266 15.457C8.13411 15.4831 8.05859 15.4961 7.99609 15.4961ZM7.99609 14.0352C8.05339 14.0352 8.11328 14.0221 8.17578 13.9961C8.24349 13.9753 8.33203 13.9336 8.44141 13.8711C9.28516 13.3555 9.99349 12.9154 10.5664 12.5508C11.1393 12.181 11.5951 11.832 11.9336 11.5039C12.2773 11.1758 12.5247 10.8164 12.6758 10.4258C12.8268 10.0352 12.9023 9.5612 12.9023 9.00391V3.89453C12.9023 3.76432 12.8867 3.66797 12.8555 3.60547C12.8242 3.54297 12.7539 3.49089 12.6445 3.44922C12.4206 3.37109 12.1628 3.27995 11.8711 3.17578C11.5794 3.07161 11.2721 2.96224 10.9492 2.84766C10.6263 2.73307 10.3034 2.61849 9.98047 2.50391C9.66276 2.38411 9.36068 2.27214 9.07422 2.16797C8.79297 2.05859 8.54818 1.96224 8.33984 1.87891C8.27214 1.85286 8.20964 1.83464 8.15234 1.82422C8.09505 1.80859 8.04297 1.80078 7.99609 1.80078C7.95443 1.80078 7.90495 1.80859 7.84766 1.82422C7.79036 1.83464 7.72786 1.85286 7.66016 1.87891C7.44661 1.96224 7.19661 2.05599 6.91016 2.16016C6.6237 2.26432 6.32161 2.3737 6.00391 2.48828C5.6862 2.59766 5.36328 2.70964 5.03516 2.82422C4.71224 2.9388 4.40495 3.05078 4.11328 3.16016C3.82682 3.26953 3.57161 3.36589 3.34766 3.44922C3.24349 3.49089 3.17578 3.54297 3.14453 3.60547C3.11328 3.66797 3.09766 3.76432 3.09766 3.89453V9.00391C3.09766 9.5612 3.17057 10.0326 3.31641 10.418C3.46745 10.8034 3.71224 11.1628 4.05078 11.4961C4.39453 11.8242 4.85286 12.1732 5.42578 12.543C5.9987 12.9128 6.70964 13.3555 7.55859 13.8711C7.66797 13.9336 7.75391 13.9753 7.81641 13.9961C7.87891 14.0221 7.9388 14.0352 7.99609 14.0352ZM7.19922 11.1367C7.06901 11.1367 6.95182 11.1081 6.84766 11.0508C6.74349 10.9935 6.64193 10.8997 6.54297 10.7695L4.76953 8.60547C4.64974 8.44922 4.58984 8.28776 4.58984 8.12109C4.58984 7.94922 4.64714 7.80339 4.76172 7.68359C4.88151 7.5638 5.02734 7.50391 5.19922 7.50391C5.30859 7.50391 5.40755 7.52474 5.49609 7.56641C5.58464 7.60807 5.67318 7.6862 5.76172 7.80078L7.17578 9.61328L10.1602 4.82422C10.3112 4.58984 10.4961 4.47266 10.7148 4.47266C10.8763 4.47266 11.0221 4.52474 11.1523 4.62891C11.2826 4.73307 11.3477 4.87109 11.3477 5.04297C11.3477 5.13151 11.3294 5.21745 11.293 5.30078C11.2565 5.38411 11.2148 5.46224 11.168 5.53516L7.82422 10.7695C7.67318 11.0143 7.46484 11.1367 7.19922 11.1367Z" fill="currentColor" />
					</svg>
				</span>
				<div class="wpmudev-hb-safe-mode-bar__text">
					<p class="wpmudev-hb-safe-mode-bar__title"><?php esc_html_e( 'Hummingbird Safe Mode', 'wphb' ); ?></p>
					<p class="wpmudev-hb-safe-mode-bar__description"><?php esc_html_e( 'This is a preview of your site with unpublished optimization changes applied.', 'wphb' ); ?></p>
				</div>
			</div>
			<div class="wpmudev-hb-safe-mode-bar__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wphb' ) ); ?>" class="wpmudev-hb-safe-mode-bar__button">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
						<path d="M2.30625 6.37812C2.30625 6.28229 2.325 6.18854 2.3625 6.09687C2.4 6.00521 2.45417 5.92396 2.525 5.85312L5.8875 2.54687C6.03333 2.39687 6.20208 2.32187 6.39375 2.32187C6.59792 2.32187 6.7625 2.38646 6.8875 2.51562C7.0125 2.64479 7.075 2.80729 7.075 3.00312C7.075 3.10729 7.05625 3.19896 7.01875 3.27812C6.98125 3.35729 6.92917 3.42812 6.8625 3.49062L5.43125 4.89687L3.65625 6.37187L5.43125 7.85938L6.8625 9.25937C6.92917 9.32187 6.98125 9.39271 7.01875 9.47188C7.05625 9.55104 7.075 9.64271 7.075 9.74687C7.075 9.94271 7.0125 10.1052 6.8875 10.2344C6.7625 10.3594 6.59792 10.4219 6.39375 10.4219C6.20208 10.4219 6.03333 10.351 5.8875 10.2094L2.525 6.89062C2.45417 6.82396 2.4 6.74479 2.3625 6.65312C2.325 6.56146 2.30625 6.46979 2.30625 6.37812ZM7.325 12.9781C7.325 12.7865 7.39167 12.6219 7.525 12.4844C7.65833 12.351 7.82917 12.2844 8.0375 12.2844H9.41875C10.0354 12.2844 10.5583 12.1719 10.9875 11.9469C11.4208 11.7177 11.75 11.4052 11.975 11.0094C12.2 10.6135 12.3125 10.1635 12.3125 9.65938C12.3125 9.15104 12.2 8.70313 11.975 8.31563C11.75 7.92813 11.4208 7.62396 10.9875 7.40312C10.5583 7.18229 10.0354 7.07187 9.41875 7.07187H5.78125L3.6 6.97187C3.4375 6.96771 3.29792 6.90938 3.18125 6.79688C3.06458 6.68021 3.00625 6.54062 3.00625 6.37812C3.00625 6.20729 3.06458 6.06562 3.18125 5.95312C3.29792 5.84062 3.4375 5.78021 3.6 5.77187L5.78125 5.67187H9.4C10.2958 5.67187 11.0646 5.84271 11.7063 6.18437C12.3479 6.52604 12.8396 6.99896 13.1812 7.60312C13.5229 8.20729 13.6938 8.90729 13.6938 9.70312C13.6938 10.5031 13.525 11.201 13.1875 11.7969C12.8542 12.3927 12.3708 12.8552 11.7375 13.1844C11.1083 13.5135 10.3542 13.6781 9.475 13.6781H8.0375C7.82917 13.6781 7.65833 13.6094 7.525 13.4719C7.39167 13.3344 7.325 13.1698 7.325 12.9781Z" fill="currentColor" />
					</svg>
					<?php esc_html_e( 'Back to Hummingbird', 'wphb' ); ?>
				</a>
				<button type="button" id="wphb-copy-test-link" class="wpmudev-hb-safe-mode-bar__button" data-tooltip="<?php esc_attr_e( 'Copied', 'wphb' ); ?>">
					<?php esc_html_e( 'Copy test link', 'wphb' ); ?>
				</button>
				<a href="<?php echo esc_url( add_query_arg( 'wphb_safe_mode_review', '1', admin_url( 'admin.php?page=wphb' ) ) ); ?>" id="wphb-publish-safe-mode-changes" class="wpmudev-hb-safe-mode-bar__button wpmudev-hb-safe-mode-bar__button--primary">
					<?php esc_html_e( 'Review & Publish', 'wphb' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the home page URL with safe mode preview query string.
	 *
	 * @return string
	 */
	public function get_safe_mode_preview_url(): string {
		return add_query_arg( 'wphb_preview_safe_mode', '1', home_url( '/' ) );
	}

	/**
	 * Track additional safe mode settings.
	 *
	 * @return void
	 */
	private function track_additional_settings(): void {
		$additional_settings = array(
			'fast_cgi' => Fast_CGI::is_fast_cgi_enabled(),
		);
		$fast_cgi_settings  = $this->get_fast_cgi_settings_snapshot();
		if ( ! empty( $fast_cgi_settings ) ) {
			$additional_settings['fast_cgi_settings'] = $fast_cgi_settings;
		}
		set_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT, $additional_settings );
	}

	/**
	 * Get the FastCGI settings needed to compare and restore Static server changes.
	 *
	 * @return array
	 */
	private function get_fast_cgi_settings_snapshot(): array {
		$settings = Fast_CGI::wphb_fast_cgi_data();
		if ( ! is_object( $settings ) ) {
			return array();
		}

		$bypass_urls  = isset( $settings->bypass_urls ) && is_array( $settings->bypass_urls ) ? array_values( $settings->bypass_urls ) : array();
		$query_params = isset( $settings->query_params ) && is_array( $settings->query_params ) ? array_values( $settings->query_params ) : array();
		sort( $bypass_urls );
		sort( $query_params );

		return array(
			'ttl'          => isset( $settings->ttl ) ? (string) $settings->ttl : '',
			'bypass_urls'  => $bypass_urls,
			'query_params' => $query_params,
		);
	}

	/**
	 * Handle additional settings on publish.
	 *
	 * @return void
	 */
	public function handle_additional_settings_on_publish(): void {
		delete_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );
	}

	/**
	 * Handle additional settings on discard.
	 *
	 * @return bool Whether the tracked FastCGI value is back in place. False when the toggle
	 *              request went out but reported no status, which leaves the two out of step.
	 */
	public function handle_additional_settings_on_discard(): bool {
		$additional_settings = get_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );
		if ( isset( $additional_settings['fast_cgi'] ) ) {
			$tracked_fast_cgi = $additional_settings['fast_cgi'];
			$current_fast_cgi = Fast_CGI::is_fast_cgi_enabled();
			if ( $tracked_fast_cgi !== $current_fast_cgi ) {
				$response = Utils::get_api()->hosting->toggle_fast_cgi( $tracked_fast_cgi );
				if ( ! isset( $response->is_active ) ) {
					return false;
				}

				Fast_CGI::update_fast_cgi_status( $response->is_active );
			}
		}

		if ( isset( $additional_settings['fast_cgi_settings'] ) && is_array( $additional_settings['fast_cgi_settings'] ) ) {
			$current_settings = $this->get_fast_cgi_settings_snapshot();
			if ( $additional_settings['fast_cgi_settings'] !== $current_settings ) {
				$response = Utils::get_api()->hosting->wphb_update_fast_cgi_settings( $additional_settings['fast_cgi_settings'] );
				if ( false === $response || is_wp_error( $response ) ) {
					return false;
				}
			}
		}
		delete_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );

		return true;
	}

	/**
	 * Check if there are additional settings changes to publish.
	 *
	 * @return bool
	 */
	public function has_changes_to_publish(): bool {
		$changed = false;

		$safe_mode_settings = Settings::get_settings( false, self::SAFE_MODE_SETTINGS_OPTION );
		$hb_settings        = Settings::get_settings( false, self::HB_SETTINGS_OPTION );

		list( $filtered_hb, $filtered_safe_mode ) = self::filter_comparable_options( $hb_settings, $safe_mode_settings );

		if ( $filtered_safe_mode !== $filtered_hb ) {
			$changed = true;
		}

		// Cache config file changes (excluding safemode_status).
		if ( file_exists( $this->fs->basedir . '/wphb-cache.backup.php' ) &&
			file_exists( $this->fs->basedir . '/wphb-cache.php' ) ) {
			$tracked_config = json_decode( file_get_contents( $this->fs->basedir . '/wphb-cache.backup.php' ), true );
			$current_config = json_decode( file_get_contents( $this->fs->basedir . '/wphb-cache.php' ), true );

			unset( $tracked_config['safemode_status'] );
			unset( $current_config['safemode_status'] );

			$this->array_sort_recursive( $tracked_config );
			$this->array_sort_recursive( $current_config );

			if ( $tracked_config !== $current_config ) {
				$changed = true;
			}
		}

		// Fast CGI setting.
		$additional_settings = get_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );
		if ( isset( $additional_settings['fast_cgi'] ) ) {
			$tracked_fast_cgi = $additional_settings['fast_cgi'];
			$current_fast_cgi = Fast_CGI::is_fast_cgi_enabled();
			if ( $tracked_fast_cgi !== $current_fast_cgi ) {
				$changed = true;
			}
		}
		if ( isset( $additional_settings['fast_cgi_settings'] ) && is_array( $additional_settings['fast_cgi_settings'] ) ) {
			$tracked_settings = $additional_settings['fast_cgi_settings'];
			$current_settings = $this->get_fast_cgi_settings_snapshot();
			$tracked_visible  = array_intersect_key( $tracked_settings, array_flip( array( 'ttl', 'bypass_urls' ) ) );
			$current_visible  = array_intersect_key( $current_settings, array_flip( array( 'ttl', 'bypass_urls' ) ) );
			if ( $tracked_visible !== $current_visible ) {
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Sort array recursively to enable order-independent comparison.
	 *
	 * @param array $array The array to sort.
	 * @return void
	 */
	private function array_sort_recursive( &$array ): void {
		if ( ! is_array( $array ) ) {
			return;
		}

		ksort( $array );

		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				$this->array_sort_recursive( $value );
			}
		}
	}
}
