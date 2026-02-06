<?php
/**
 * Safe Mode Integration Class
 *
 * @package Hummingbird\Core
 */

namespace Hummingbird\Core;

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
	private const HB_SETTINGS_OPTION = 'wphb_settings';

	/**
	 * Safe mode settings option name.
	 */
	private const SAFE_MODE_SETTINGS_OPTION = 'wphb_safe_mode_settings';

	/**
	 * Safe mode status option name.
	 */
	private const SAFE_MODE_STATUS_OPTION = 'wphb_safe_mode_status';

	/**
	 * Safe mode additional settings transient name.
	 */
	private const SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT = 'wphb_safe_mode_additional_settings';

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
	 * Set safe mode status.
	 *
	 * @param boolean $status The new status.
	 */
	public function set_status( $status ): void {
		$this->is_enabled = (bool) $status;
		Settings::update( self::SAFE_MODE_STATUS_OPTION, $this->is_enabled );
		self::write_page_cache_settings( $this->is_enabled );
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
	 */
	public function delete_data(): void {
		$this->set_status( false );
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
		static $options_to_skip = array(
			'minify' => array(
				'file_path',
				'log',
			),
		);

		static $modules_to_skip = array(
			'uptime',
			'advanced',
			'settings',
			'redis',
			'database',
			'delayjs',
			'critical_css',
			'mixpanel_analytics',
			'background_processing',
			'reports-performance',
			'reports-uptime',
			'reports-database',
			'notifications',
		);

		// 1️⃣ Remove entire modules to skip
		$options = array_diff_key( $options, array_flip( $modules_to_skip ) );

		// 2️⃣ Remove sub-options efficiently
		if ( $specific_module ) {
			$options_to_skip = array(
				$specific_module => $options_to_skip[ $specific_module ] ?? array(),
			);
			$options         = array_diff_key( $options, array_flip( $options_to_skip[ $specific_module ] ) );
		} else {
			foreach ( $options_to_skip as $module => $keys_to_remove ) {
				if ( isset( $options[ $module ] ) && is_array( $options[ $module ] ) ) {
					$options[ $module ] = array_diff_key(
						$options[ $module ],
						array_flip( $keys_to_remove )
					);
				}
			}
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
			padding: 0;
		}

		/* Notice container */
		#wphb-safe-mode-notice .notice {
			margin: 0;
			background: #fffce5;
			border-left: 4px solid #DBA617;
			border-bottom: 1px solid #dcdcde;
			border-radius: 0;
			padding: 12px 15px;
			font-size: 13px;
			color: #1d2327;
		}

		#wphb-safe-mode-notice p {
			margin: 0 0 8px 0;
			line-height: 1.5;
		}

		#wphb-safe-mode-notice p.submit {
			margin: 0;
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
		}

		/* Button styles */
		#wphb-safe-mode-notice p.submit .button {
			display: inline-block;
			padding: 2px 10px;
			border-radius: 3px;
			cursor: pointer;
			text-decoration: none;
			text-transform: none;
			font-weight: 400 !important;
			font-size: 11px !important;
			line-height: 16px;
			vertical-align: middle;
			font-family: sans-serif;
			letter-spacing: 1px;
		}

		#wphb-safe-mode-notice p.submit .button-primary {
			background: #007CBA;
			color: #fff;
			border: 1px solid #007CBA;
		}
		#wphb-safe-mode-notice p.submit .button-primary:hover {
			background: #135e96;
			border-color: #135e96;
		}

		#wphb-safe-mode-notice p.submit .button-secondary {
			background: transparent;
			border: 1px solid #007CBA;
			color: #007CBA;
		}
		#wphb-safe-mode-notice p.submit .button-secondary:hover {
			background: #f0f0f1;
			color: #135e96;
			border-color: #135e96;
		}

		#wphb-safe-mode-notice p.submit .button-link {
			background: none;
			border: none;
			color: #007CBA;
			text-decoration: none;
			padding: 0 4px;
			font-weight: 400 !important;
		}
		#wphb-safe-mode-notice p.submit .button-link:hover {
			color: #135e96;
			text-decoration: underline;
		}

		body.admin-bar #wphb-safe-mode-notice {
			top: 32px;
		}
	</style>

	<div id="wphb-safe-mode-notice">
		<div class="notice notice-info" style="display: flex;">
			<span class="dashicons dashicons-info" style="display: inline-block; vertical-align: middle; margin-right: 8px; color: #666666;"></span>
			<div style="display: inline-block;">
				<p>
					<?php esc_html_e( 'You’re viewing your site’s frontend in Safe Mode. Check your pages for visual or functional issues, inspect the browser console for errors, test with page speed tools, and publish when ready.', 'wphb' ); ?>
				</p>
				<p class="submit">
					<button type="button" class="button button-primary" id="wphb-publish-safe-mode-changes">
						<?php esc_html_e( 'Publish Changes', 'wphb' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="wphb-copy-test-link">
						<?php echo esc_html__( 'Copy test link', 'wphb' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wphb' ) ); ?>" class="button-link" style="padding: 2px 10px;line-height: 2;">
						<?php esc_html_e( 'Go back', 'wphb' ); ?>
					</a>
				</p>
			</div>
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
		set_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT, $additional_settings );
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
	 * @return void
	 */
	public function handle_additional_settings_on_discard(): void {
		$additional_settings = get_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );
		if ( isset( $additional_settings['fast_cgi'] ) ) {
			$tracked_fast_cgi = $additional_settings['fast_cgi'];
			$current_fast_cgi = Fast_CGI::is_fast_cgi_enabled();
			if ( $tracked_fast_cgi === $current_fast_cgi ) {
				return;
			}

			$response = Utils::get_api()->hosting->toggle_fast_cgi( $tracked_fast_cgi );
			if ( isset( $response->is_active ) ) {
				Fast_CGI::update_fast_cgi_status( $response->is_active );
			}
		}
		delete_transient( self::SAFE_MODE_ADDITIONAL_SETTINGS_TRANSIENT );
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

		$filtered_safe_mode = self::filter_options( $safe_mode_settings );
		$filtered_hb        = self::filter_options( $hb_settings );

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
