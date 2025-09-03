<?php
/**
 * Background Process Status for Hummingbird
 *
 * @package Hummingbird\Core\Modules\Background
 */

namespace Hummingbird\Core\Modules\Background;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HB_Background_Process_Status class
 *
 * Handles status tracking for background processes using thread-safe operations
 * based on the Smush Background_Process_Status pattern but with atomic database operations
 */
class HB_Background_Process_Status {

	/**
	 * Status constants matching Smush pattern
	 */
	const PROCESSING      = 'in_processing';
	const CANCELLED       = 'is_cancelled';
	const COMPLETED       = 'is_completed';
	const DEAD            = 'is_dead';
	const TOTAL_ITEMS     = 'total_items';
	const PROCESSED_ITEMS = 'processed_items';
	const FAILED_ITEMS    = 'failed_items';

	/**
	 * Process identifier
	 *
	 * @var string
	 */
	private $identifier;

	/**
	 * Thread safe options handler
	 *
	 * @var Thread_Safe_Options
	 */
	private $thread_safe_options;

	/**
	 * Constructor
	 *
	 * @param string $identifier Process identifier.
	 */
	public function __construct( $identifier ) {
		$this->identifier          = $identifier;
		$this->thread_safe_options = new Thread_Safe_Options();
	}

	/**
	 * Get data using thread-safe operations with proper defaults
	 *
	 * @return array
	 */
	public function get_data() {
		$defaults = array(
			self::PROCESSING      => false,
			self::CANCELLED       => false,
			self::COMPLETED       => false,
			self::DEAD            => false,
			self::TOTAL_ITEMS     => 0,
			self::PROCESSED_ITEMS => 0,
			self::FAILED_ITEMS    => 0,
		);

		$option_value = $this->thread_safe_options->get_option(
			$this->get_option_id(),
			$defaults
		);

		// Ensure we always have all required keys with proper defaults.
		return wp_parse_args( $option_value, $defaults );
	}

	/**
	 * Set multiple data values atomically.
	 *
	 * @param array $updated Updated data.
	 */
	private function set_data( $updated ) {
		$this->thread_safe_options->set_values( $this->get_option_id(), $updated );
	}

	/**
	 * Get a single value using thread-safe operations with proper defaults.
	 *
	 * @param string $key The key to retrieve.
	 * @return mixed
	 */
	private function get_value( $key ) {
		$defaults = array(
			self::PROCESSING      => false,
			self::CANCELLED       => false,
			self::COMPLETED       => false,
			self::DEAD            => false,
			self::TOTAL_ITEMS     => 0,
			self::PROCESSED_ITEMS => 0,
			self::FAILED_ITEMS    => 0,
		);

		$default_value = isset( $defaults[ $key ] ) ? $defaults[ $key ] : false;

		return $this->thread_safe_options->get_value( $this->get_option_id(), $key, $default_value );
	}

	/**
	 * Set a single value atomically using thread-safe operations.
	 *
	 * @param string $key   The key to set.
	 * @param mixed  $value The value to set.
	 */
	private function set_value( $key, $value ) {
		$this->thread_safe_options->set_values( $this->get_option_id(), array( $key => $value ) );
	}

	/**
	 * Get the option ID.
	 *
	 * @return string
	 */
	private function get_option_id() {
		return $this->identifier . '_status';
	}

	/**
	 * Check if process is in processing state.
	 *
	 * @return bool
	 */
	public function is_in_processing() {
		return $this->get_value( self::PROCESSING );
	}

	/**
	 * Get total items count
	 *
	 * @return int
	 */
	public function get_total_items() {
		return (int) $this->get_value( self::TOTAL_ITEMS );
	}

	/**
	 * Set total items count.
	 *
	 * @param int $total_items Total items count.
	 */
	public function set_total_items( $total_items ) {
		$this->set_value( self::TOTAL_ITEMS, $total_items );
	}

	/**
	 * Get processed items count
	 *
	 * @return int
	 */
	public function get_processed_items() {
		return (int) $this->get_value( self::PROCESSED_ITEMS );
	}

	/**
	 * Set processed items count.
	 *
	 * @param int $processed_items Processed items count.
	 */
	public function set_processed_items( $processed_items ) {
		$this->set_value( self::PROCESSED_ITEMS, $processed_items );
	}

	/**
	 * Get failed items count
	 *
	 * @return int
	 */
	public function get_failed_items() {
		return (int) $this->get_value( self::FAILED_ITEMS );
	}

	/**
	 * Set failed items count (fixes bug in original where it was setting PROCESSED_ITEMS).
	 *
	 * @param int $failed_items Failed items count.
	 */
	public function set_failed_items( $failed_items ) {
		$this->set_value( self::FAILED_ITEMS, $failed_items );
	}

	/**
	 * Check if process is cancelled
	 *
	 * @return bool
	 */
	public function is_cancelled() {
		return $this->get_value( self::CANCELLED );
	}

	/**
	 * Set cancelled state.
	 *
	 * @param bool $is_cancelled Cancelled state.
	 */
	public function set_is_cancelled( $is_cancelled ) {
		$this->set_value( self::CANCELLED, $is_cancelled );
	}

	/**
	 * Check if process is dead.
	 *
	 * @return bool
	 */
	public function is_dead() {
		return $this->get_value( self::DEAD );
	}

	/**
	 * Check if process is completed.
	 *
	 * @return bool
	 */
	public function is_completed() {
		return $this->get_value( self::COMPLETED );
	}

	/**
	 * Set completed state
	 *
	 * @param bool $is_completed Completed state.
	 */
	public function set_is_completed( $is_completed ) {
		$this->set_value( self::COMPLETED, $is_completed );
	}

	/**
	 * Start the process with thread-safe operations.
	 *
	 * @param int $total_items Total number of items to process.
	 */
	public function start( $total_items ) {
		$this->set_data(
			array(
				self::PROCESSING      => true,
				self::CANCELLED       => false,
				self::DEAD            => false,
				self::COMPLETED       => false,
				self::TOTAL_ITEMS     => $total_items,
				self::PROCESSED_ITEMS => 0,
				self::FAILED_ITEMS    => 0,
			)
		);
	}

	/**
	 * Mark task as successful using atomic increment.
	 */
	public function task_successful() {
		// Use atomic increment with automatic initialization - no race condition.
		$this->thread_safe_options->increment_values( $this->get_option_id(), array( self::PROCESSED_ITEMS ) );
	}

	/**
	 * Mark task as failed using atomic increment.
	 */
	public function task_failed() {
		// Use atomic increment with automatic initialization - no race condition.
		$this->thread_safe_options->increment_values( $this->get_option_id(), array( self::PROCESSED_ITEMS, self::FAILED_ITEMS ) );
	}

	/**
	 * Complete the process
	 */
	public function complete() {
		$this->set_data(
			array(
				self::PROCESSING => false,
				self::CANCELLED  => false,
				self::DEAD       => false,
				self::COMPLETED  => true,
			)
		);
	}

	/**
	 * Cancel the process
	 */
	public function cancel() {
		$this->set_data(
			array(
				self::PROCESSING => false,
				self::CANCELLED  => true,
				self::DEAD       => false,
				self::COMPLETED  => false,
			)
		);
	}

	/**
	 * Mark process as dead
	 */
	public function mark_as_dead() {
		$this->set_data(
			array(
				self::PROCESSING => false,
				self::CANCELLED  => false,
				self::DEAD       => true,
				self::COMPLETED  => false,
			)
		);
	}

	/**
	 * Clear status
	 */
	public function clear() {
		$this->thread_safe_options->delete_option( $this->get_option_id() );
	}

	/**
	 * Check if process is running (compatibility method)
	 *
	 * @return bool
	 */
	public function is_running() {
		return $this->is_in_processing();
	}

	/**
	 * Get status option key (compatibility method)
	 *
	 * @return string
	 */
	private function get_status_key() {
		return $this->get_option_id();
	}
}
