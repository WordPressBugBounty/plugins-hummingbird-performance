<?php
/**
 * AO scan Background Process for Hummingbird.
 *
 * @package Hummingbird\Core\Modules\Background
 */

namespace Hummingbird\Core\Modules\Minify;

use Hummingbird\Core\Modules\Background\HB_Background_Process;
use Hummingbird\Core\Utils;
use WP_Http_Cookie;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Optimization Scan Background Process for Hummingbird.
 *
 * This class handles the background processing of URLs for asset optimization
 * scanning. It collects CSS and JavaScript assets from multiple pages in a
 * non-blocking, queue-based system that respects memory and time limits.
 *
 * The process operates asynchronously to avoid blocking the main WordPress
 * execution and provides progress tracking capabilities for admin interfaces.
 *
 * @package Hummingbird\Core\Modules\Minify
 * @since   3.16.0
 */
class AO_Scan_Process extends HB_Background_Process {

	/**
	 * Default number of URLs to process per request.
	 *
	 * @since 3.16.0
	 * @var   int
	 */
	const DEFAULT_TASKS_PER_REQUEST = 5;

	/**
	 * Initialize the AO scan background process.
	 *
	 * Sets up the background process with default configuration for asset optimization
	 * scanning, including batch size and process identification.
	 *
	 * @since 3.16.0
	 *
	 * @param string $identifier Custom action identifier for the process.
	 *                       If not provided, uses the default action.
	 */
	public function __construct( $identifier ) {
		parent::__construct( $identifier );

		// Set configurable batch size for AO scan.
		$this->set_tasks_per_request();
	}

	/**
	 * Set the number of tasks to process per request for AO scanning.
	 *
	 * Overrides the parent method to provide AO-specific validation and logging.
	 *
	 * @since 3.16.0
	 * @param int $tasks_per_request Number of tasks to process per request. If null, uses optimal default.
	 */
	public function set_tasks_per_request( $tasks_per_request = null ) {
		// If no parameter provided, use our optimal configuration.
		if ( null === $tasks_per_request ) {
			// Allow customization via filter.
			$tasks_per_request = apply_filters( 'wphb_ao_scan_tasks_per_request', self::DEFAULT_TASKS_PER_REQUEST );
		}

		// Ensure it's a positive integer with reasonable bounds.
		$tasks_per_request = absint( $tasks_per_request );
		$tasks_per_request = max( 1, min( $tasks_per_request, 50 ) ); // Between 1 and 50.

		// Call parent method to actually set the value.
		parent::set_tasks_per_request( $tasks_per_request );
	}

	/**
	 * Process a single URL for asset optimization scanning.
	 *
	 * @since 3.16.0
	 *
	 * @param mixed $item Queue item to iterate over. Expected to be a URL string.
	 *
	 * @return mixed Returns false to remove item from queue on completion/failure,
	 *               or returns the item to re-queue if resource limits exceeded.
	 */
	protected function task( $item ) {
		$url = $item;

		if ( empty( $url ) ) {
			$this->log( 'Empty URL provided, skipping item' );
			return false;
		}

		$this->log( "Starting AO scan for: $url" );

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$this->log( "Invalid URL format: $url" );
			return false;
		}

		// Perform the AO scan.
		$result = $this->collect_assets_from_url( $url );

		if ( isset( $result['success'] ) && $result['success'] ) {
			$this->log( "Successfully Scanned: $url" );

			// Fire action for successful scan.
			do_action( 'wphb_ao_scan_url_success', $url, $result );

			return true;
		} else {
			$error_message = isset( $result['error'] ) ? $result['error'] : 'Unknown error';
			$this->log( "Failed to scan: $url - {$error_message}" );

			return false;
		}
	}

	/**
	 * Collect CSS and JavaScript assets from a single URL.
	 *
	 * @since 3.16.0
	 *
	 * @param string $url The URL to collect assets from. Must be a valid HTTP/HTTPS URL.
	 *
	 * @return array {
	 *     Result array containing operation status and details.
	 *
	 *     @type bool   $success Whether the asset collection request was initiated successfully.
	 *     @type string $error   Error message if the operation failed. Only present on failure.
	 *     @type string $message Success message describing the operation. Only present on success.
	 * }
	 */
	private function collect_assets_from_url( $url ) {
		// One call logged out.
		$args = array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => false,
		);

		$result = wp_safe_remote_get( $url, $args );

		// Check if the non-authenticated request failed.
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => 'Non-authenticated request failed: ' . $result->get_error_message(),
			);
		}

		// For non-blocking requests, we can't get response codes.
		$this->log( "Request sent to: $url" );

		return array(
			'success' => true,
			'message' => 'AO scan request sent',
		);
	}

	/**
	 * Handle completion of the asset optimization scanning process.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function complete() {
		$this->log( 'AO scan process completed successfully' );

		// Store completion notice data for admin display.
		$this->store_completion_notice();

		// Fire completion action.
		do_action( 'wphb_ao_scan_completed' );

		parent::complete();
	}

	/**
	 * Store completion notice data for admin display.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	private function store_completion_notice() {
		set_site_transient( 'wphb-notice-ao-scan-completion-show', 'yes', DAY_IN_SECONDS );
	}

	/**
	 * Start the AO scan process with comprehensive validation and error handling.
	 *
	 * This method encapsulates all the logic for starting an asset optimization scan,
	 * including URL generation, validation, sanitization, and error handling.
	 *
	 * @since 3.16.0
	 * @return bool|\WP_Error True on success, false on failure, WP_Error on exception.
	 */
	public function start_scan() {
		// Generate URLs to scan.
		$urls = Utils::get_module( 'minify' )->scanner->get_scan_urls();

		if ( empty( $urls ) || ! is_array( $urls ) ) {
			$this->log( 'No URLs generated for scanning or invalid URL format' );
			return false;
		}

		// Keep only valid http/https URLs.
		$urls = array_filter( $urls, 'wp_http_validate_url' );

		if ( empty( $urls ) ) {
			$this->log( 'No valid URLs provided' );
			return false;
		}

		$urls = array_map( 'esc_url_raw', $urls );

		// Check if already running.
		if ( $this->get_status()->is_running() ) {
			$this->log( 'AO scan is already running' );
			return false;
		}

		$this->log( 'Starting AO scan for ' . count( $urls ) . ' URLs' );

		try {
			// Start the AO scanning.
			$result = $this->scan_ao_assets( $urls );

			if ( $result ) {
				$this->log( 'AO scan started successfully' );
				return true;
			} else {
				$this->log( 'Failed to start AO scan process' );
				return false;
			}
		} catch ( Exception $e ) {
			$error = new \WP_Error( 'ao_scan_exception', $e->getMessage() );
			$this->log( 'AO scan exception: ' . $e->getMessage() );
			return $error;
		}
	}

	/**
	 * Add multiple URLs to the asset optimization scanning queue.
	 *
	 * @since 3.16.0
	 *
	 * @param array $urls Array of URL strings to be processed for asset collection.
	 *                    Each URL must be a valid HTTP/HTTPS URL.
	 *
	 * @return bool True if URLs were successfully queued for processing,
	 *              false if the URL array is empty, invalid, or not an array.
	 */
	public function scan_ao_assets( $urls ) {
		if ( empty( $urls ) || ! is_array( $urls ) ) {
			$this->log( 'scan_ao_assets called with empty or invalid URLs array' );
			return false;
		}

		// Just pass URLs directly - the task() method expects simple URL strings.
		$this->start( $urls );

		return true;
	}
}
