<?php
/**
 * Background Processing Module for WP Hummingbird.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.16.0
 */

namespace Hummingbird\Core\Modules;

use Hummingbird\Core\Module;
use Hummingbird\Core\Traits\Module as ModuleContract;
use Hummingbird\Core\Modules\Minify\AO_Scan_Process; // Import AO_Scan_Process for background processing.
use Hummingbird\Core\Utils;
use Hummingbird\Core\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Background_Processing
 *
 * This module provides background processing capabilities to WP Hummingbird
 * using the deliciousbrains/wp-background-processing library.
 */
class Background_Processing extends Module {
	use ModuleContract;

	/**
	 * Module slug name
	 *
	 * @var string
	 */
	protected $slug = 'background-processing';

	/**
	 * Unique identifier for this background process.
	 *
	 * @since 3.16.0
	 * @var   string
	 */
	protected $action = 'wphb_ao_scan_files_process';

	/**
	 * Background processing instances storage
	 *
	 * @var array
	 */
	private $processors = array();

	/**
	 * Async request instances storage
	 *
	 * @var array
	 */
	private $async_requests = array();

	/**
	 * Execute the module actions
	 *
	 * @since 3.16.0
	 */
	public function run() {
		add_action( 'init', array( $this, 'setup_processors' ) );
		add_action( 'init', array( $this, 'maybe_cancel_background_processes' ) );
	}

	/**
	 * Setup default processors
	 *
	 * @since 3.16.0
	 */
	public function setup_processors() {
		/**
		 * Filter to enable/disable the background processing module.
		 *
		 * @since 3.16.0
		 *
		 * @param bool $is_enabled Whether the background processing module is enabled.
		 */
		if ( ! apply_filters( 'wp_hummingbird_background_processing_enabled', true ) ) {
			return;
		}

		// Initialize default processors with multisite-aware identifiers.
		$ao_action = $this->make_process_identifier();
		$this->register_processor( 'ao_scan', new AO_Scan_Process( $ao_action ) );
	}

	/**
	 * Register a background processor
	 *
	 * @since 3.16.0
	 * @param string $name      Processor name.
	 * @param object $processor Processor instance.
	 */
	public function register_processor( $name, $processor ) {
		$this->processors[ $name ] = $processor;
	}

	/**
	 * Create a unique process identifier for background processes.
	 *
	 * In multisite environments, this ensures each site has its own separate
	 * background process queue to prevent conflicts and data mixing between sites.
	 *
	 * @since 3.16.0
	 *
	 * @return string Unique process identifier including site ID for multisite installations.
	 */
	private function make_process_identifier() {
		$identifier = $this->action;

		if ( is_multisite() ) {
			$site_id     = get_current_blog_id();
			$identifier .= '_' . $site_id;
		}

		return $identifier;
	}

	/**
	 * Get a registered processor
	 *
	 * @since 3.16.0
	 * @param string $name Processor name.
	 * @return object|null
	 */
	public function get_processor( $name ) {
		return isset( $this->processors[ $name ] ) ? $this->processors[ $name ] : null;
	}

	/**
	 * Maybe start AO scan process.
	 *
	 * @since 3.16.0
	 * @return bool|\WP_Error
	 */
	public function maybe_start_scan() {
		if ( ! Utils::get_module( 'minify' )->is_active() ) {
			$this->log( 'AO module is not active.' );
			return;
		}

		$processor = $this->get_processor( 'ao_scan' );
		if ( ! $processor ) {
			$this->log( 'AO scan processor not available.' );
			return;
		}

		// Delegate all scan logic to the AO scan processor.
		return $processor->start_scan();
	}

	/**
	 * Cancel background processes programmatically based on various conditions.
	 *
	 * This method provides multiple ways to cancel running background processes:
	 * - Constant: WP_HUMMINGBIRD_STOP_BACKGROUND_PROCESSING
	 * - Filter: wp_hummingbird_stop_background_processing
	 * - URL Parameter: ?wp_hummingbird_stop_background_processing=1
	 * - Admin action
	 *
	 * @since 3.16.0
	 * @return void
	 */
	public function maybe_cancel_background_processes() {
		// Check if cancellation is triggered by constant.
		$constant_value = defined( 'WP_HUMMINGBIRD_STOP_BACKGROUND_PROCESSING' ) && WP_HUMMINGBIRD_STOP_BACKGROUND_PROCESSING;

		// Check if cancellation is triggered by filter.
		$filter_value = apply_filters( 'wp_hummingbird_stop_background_processing', false );

		// Check if cancellation is triggered by URL parameter (admin users only).
		$capability  = is_multisite() ? 'manage_network' : 'manage_options';
		$param_value = ! empty( $_GET['wp_hummingbird_stop_background_processing'] ) && current_user_can( $capability );

		// Determine if any cancellation condition is met.
		$should_cancel = $constant_value || $filter_value || $param_value;

		if ( ! $should_cancel ) {
			return;
		}

		$cancelled_any = false;

		// Cancel all registered processors that are currently running.
		foreach ( $this->processors as $processor_name => $processor ) {
			if ( ! $processor || ! method_exists( $processor, 'get_status' ) ) {
				continue;
			}

			$status = $processor->get_status();

			// Check if the process is running and not already cancelled.
			if ( method_exists( $status, 'is_running' ) && method_exists( $status, 'is_cancelled' ) ) {
				$is_running   = $status->is_running();
				$is_cancelled = $status->is_cancelled();

				if ( $is_running && ! $is_cancelled ) {
					$this->log( "Cancelling background process '{$processor_name}' due to programmatic cancellation request." );

					if ( method_exists( $processor, 'cancel' ) ) {
						$processor->cancel();
						$cancelled_any = true;
					}
				}
			}
		}

		if ( $cancelled_any ) {
			$this->log( 'Background processing cancelled programmatically.' );

			// Fire action for other modules to handle cancellation.
			do_action( 'wp_hummingbird_background_processing_cancelled' );
		}
	}
}
