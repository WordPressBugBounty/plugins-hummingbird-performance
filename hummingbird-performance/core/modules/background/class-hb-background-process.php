<?php
/**
 * Enhanced Background Process for Hummingbird
 *
 * @package Hummingbird\Core\Modules\Background
 */

namespace Hummingbird\Core\Modules\Background;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HB_Background_Process class
 *
 * Enhanced background processing with improved reliability and monitoring
 */
abstract class HB_Background_Process extends HB_Async_Request {

	/**
	 * Unlimited tasks per request constant
	 */
	const TASKS_PER_REQUEST_UNLIMITED = -1;

	/**
	 * Start time of current process
	 *
	 * @var int
	 */
	private $start_time = 0;

	/**
	 * Cron hook identifier
	 *
	 * @var string
	 */
	private $cron_hook_identifier;

	/**
	 * Cron interval identifier
	 *
	 * @var string
	 */
	private $cron_interval_identifier;

	/**
	 * Process status
	 *
	 * @var HB_Background_Process_Status
	 */
	private $status;

	/**
	 * Tasks per request limit
	 *
	 * @var int
	 */
	private $tasks_per_request = self::TASKS_PER_REQUEST_UNLIMITED;

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
		parent::__construct( $identifier );

		$this->cron_hook_identifier     = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->identifier . '_cron_interval';

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
		add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );

		$this->status              = new HB_Background_Process_Status( $this->identifier );
		$this->thread_safe_options = new Thread_Safe_Options();
	}

	/**
	 * Generate unique ID
	 *
	 * @return string
	 */
	private function generate_unique_id() {
		return md5( microtime() . wp_rand() );
	}

	/**
	 * Dispatch request
	 *
	 * @param string $instance_id Instance ID.
	 * @return array|\WP_Error
	 */
	public function dispatch( $instance_id = '' ) {
		$this->log( "Dispatching a new request for instance $instance_id." );

		// Schedule the cron healthcheck.
		$this->schedule_event();

		// Perform remote post.
		return parent::dispatch( $instance_id );
	}

	/**
	 * Spawn new process instance
	 */
	public function spawn() {
		$instance_id = $this->generate_unique_id();

		$this->log( "Spawning a brand new instance (ID: $instance_id) for the process." );

		$this->set_active_instance_id( $instance_id );
		$this->dispatch( $instance_id );
	}

	/**
	 * Update queue
	 *
	 * @param array $tasks Array of tasks.
	 */
	private function update_queue( $tasks ) {
		$queue_key = $this->get_queue_key();
		$this->log( "Updating queue '$queue_key' with " . count( $tasks ) . ' tasks using atomic operations' );

		// Ensure tasks array is properly indexed and sanitized.
		$sanitized_tasks = array_values( array_filter( $tasks ) );

		$this->log( 'Storing queue tasks: ' . wp_json_encode( $sanitized_tasks ) );

		// Clean up any existing queue to start fresh.
		$this->thread_safe_options->delete_option( $queue_key );

		// Add each task individually using atomic operations.
		if ( ! empty( $sanitized_tasks ) ) {
			$result = $this->thread_safe_options->append_to_array( $queue_key, $sanitized_tasks );
			if ( ! $result ) {
				$this->log( 'Failed to initialize queue with atomic operations' );
			}
		}

		// Immediately verify the queue was stored.
		$verification_queue = $this->get_queue();
		$this->log( 'Queue verification: Expected ' . count( $sanitized_tasks ) . ' tasks, got ' . count( $verification_queue ) . ' tasks' );

		if ( count( $verification_queue ) !== count( $sanitized_tasks ) ) {
			$this->log( 'WARNING: Queue size mismatch! Expected: ' . count( $sanitized_tasks ) . ', Got: ' . count( $verification_queue ) );
		}
	}

	/**
	 * Remove task from queue atomically
	 *
	 * @param mixed $task Task to remove.
	 */
	private function remove_task_from_queue( $task ) {
		$queue_key = $this->get_queue_key();
		$this->log( "Atomically removing task $task from queue using Thread_Safe_Options" );

		try {
			// Use atomic remove_from_array operation directly on tasks array.
			$result = $this->thread_safe_options->remove_from_array( $queue_key, $task );
			if ( $result ) {
				$this->log( "Task $task successfully removed from queue atomically" );
			} else {
				$this->log( "Task $task not found in queue for removal" );
			}
		} catch ( Exception $e ) {
			$this->log( 'Exception during atomic task removal: ' . $e->getMessage() );
		}
	}

	/**
	 * Delete queue
	 */
	private function delete_queue() {
		$queue_key = $this->get_queue_key();
		$this->log( "Deleting queue: $queue_key" );
		$this->thread_safe_options->delete_option( $queue_key );
	}

	/**
	 * Get queue key
	 *
	 * @return string
	 */
	protected function get_queue_key() {
		return $this->identifier . '_queue';
	}

	/**
	 * Maybe handle the request
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing.
		session_write_close();

		$instance_id = empty( $_GET['instance_id'] ) ? false : wp_unslash( $_GET['instance_id'] );

		if ( $this->is_queue_empty() ) {
			// Debug: Log detailed queue information.
			$queue_key = $this->get_queue_key();
			$this->log( "Handler called with instance ID $instance_id but the queue is empty. Killing this instance." );
			return;
		}

		if ( ! $instance_id || ! $this->is_active_instance( $instance_id ) ) {
			// We thought the process died, so we spawned a new instance.
			// Kill this instance and let the new one continue.
			$active_instance_id = $this->get_active_instance_id();
			$this->log( "Handler called with instance ID $instance_id but the active instance ID is $active_instance_id. Killing $instance_id so $active_instance_id can continue." );
			return;
		}

		if ( ! check_ajax_referer( $this->identifier, 'nonce', false ) ) {
			return;
		}

		$this->handle( $instance_id );

		wp_die();
	}

	/**
	 * Check if queue is empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		return empty( $this->get_queue() );
	}

	/**
	 * Check if process is running
	 *
	 * @return bool
	 */
	protected function is_process_running() {
		if ( get_site_transient( $this->get_last_run_transient_key() ) ) {
			// Process already running.
			return true;
		}

		return false;
	}

	/**
	 * Update timestamp
	 *
	 * @param string $instance_id Instance ID.
	 */
	protected function update_timestamp( $instance_id ) {
		$timestamp        = time();
		$this->start_time = $timestamp; // Set start time of current process.
		set_site_transient(
			$this->get_last_run_transient_key(),
			$timestamp,
			$this->get_instance_expiry_duration_seconds()
		);

		$human_readable_timestamp = wp_date( 'Y-m-d H:i:s', $timestamp );
		$this->log( "Setting last run timestamp for instance ID $instance_id to $human_readable_timestamp" );
	}

	/**
	 * Get queue
	 *
	 * @return array
	 */
	protected function get_queue() {
		$queue_key = $this->get_queue_key();

		// Get the queue data - for atomic array operations, it's stored directly as an array.
		$queue_data = $this->thread_safe_options->get_option( $queue_key );
		// The atomic array operations store data as JSON array directly.
		if ( is_array( $queue_data ) ) {
			$this->log( 'Queue data is array with ' . count( $queue_data ) . ' items' );
			return $queue_data;
		}

		// If no queue exists yet, return empty array.
		if ( null === $queue_data || false === $queue_data ) {
			$this->log( 'Queue does not exist yet, returning empty array' );
			return array();
		}

		$this->log( 'Queue data is not an array, returning empty array' );
		return array();
	}

	/**
	 * Handle the process
	 *
	 * @param string $instance_id Instance ID.
	 */
	protected function handle( $instance_id = '' ) {
		$this->log( "Handling instance ID $instance_id." );
		$this->update_timestamp( $instance_id );

		$queue                 = $this->get_queue();
		$processed_tasks_count = 0;

		foreach ( $queue as $key => $value ) {
			$this->log( "Executing task $value." );

			$task_result = $this->task( $value );

			if ( $task_result ) {
				$this->status->task_successful();
			} else {
				$this->log( 'Task returned false - means it failed or was not processed correctly' );
				$this->status->task_failed();
			}

			// Remove task from queue.
			$this->remove_task_from_queue( $value );

			if ( $this->status->is_cancelled() ) {
				$this->log( "While we were busy doing the task $value, the process got cancelled. Clean up and stop." );
				return;
			}

			++$processed_tasks_count;
			$this->log( "Processed tasks count: $processed_tasks_count" );

			if ( $this->task_limit_reached( $processed_tasks_count ) ) {
				$tasks_per_request = $this->get_tasks_per_request();
				$this->log( "Stopping because we are only supposed to perform $tasks_per_request tasks in a single request and we have reached that limit." );
				break;
			}

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				$this->log( 'Time/Memory limits reached, save the queue and dispatch a new request.' );
				break;
			}
		}

		$this->log( sprintf( 'Processing time: %d seconds', time() - $this->start_time ) );

		// Check current queue state from database since local $queue is outdated after atomic operations.
		if ( $this->is_queue_empty() ) {
			$this->complete();
		} else {
			// No need to update queue since atomic operations already handled all changes.
			$this->dispatch( $instance_id );
		}
	}

	/**
	 * Get memory limit in bytes
	 *
	 * @return int
	 */
	private function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( ! $memory_limit || '-1' === $memory_limit ) {
			// Unlimited memory.
			return 0;
		}

		$unit         = strtolower( substr( $memory_limit, -1 ) );
		$memory_limit = (int) $memory_limit;

		switch ( $unit ) {
			case 'g':
				$memory_limit *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$memory_limit *= 1024 * 1024;
				break;
			case 'k':
				$memory_limit *= 1024;
				break;
		}

		return $memory_limit;
	}

	/**
	 * Check if memory is exceeded
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return apply_filters( $this->identifier . '_memory_exceeded', $return );
	}

	/**
	 * Check if time is exceeded
	 *
	 * @return bool
	 */
	protected function time_exceeded() {
		$finish = $this->start_time + $this->get_time_limit();
		$return = false;

		if ( time() >= $finish ) {
			$return = true;
		}

		return apply_filters( $this->identifier . '_time_exceeded', $return );
	}

	/**
	 * Complete the process
	 */
	protected function complete() {
		$this->do_action( 'completed' );
		$this->log( 'Process completed.' );
		$this->cleanup();
		$this->status->complete();
	}

	/**
	 * Schedule cron healthcheck
	 *
	 * @param array $schedules WordPress cron schedules.
	 * @return array
	 */
	public function schedule_cron_healthcheck( $schedules ) {
		$interval = $this->get_cron_interval_seconds();

		// Adds every 5 minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = array(
			'interval' => $interval,
			/* translators: %s: Cron interval in minutes */
			'display'  => sprintf( __( 'Every %d Minutes', 'wphb' ), $interval / MINUTE_IN_SECONDS ),
		);

		return $schedules;
	}

	/**
	 * Handle cron healthcheck
	 */
	public function handle_cron_healthcheck() {
		$this->log( 'Running scheduled health check.' );

		if ( $this->is_process_running() ) {
			$this->log( 'Health check: Process seems healthy, no action required.' );
			exit;
		}

		if ( $this->is_queue_empty() ) {
			$this->log( 'Health check: Process not in progress but the queue is empty, no action required.' );
			$this->clear_scheduled_event();
			exit;
		}

		if ( $this->status->is_cancelled() ) {
			$this->log( 'Health check: Process has been cancelled already, no action required.' );
			$this->clear_scheduled_event();
			exit;
		}

		if ( ! $this->is_revival_limit_reached() ) {
			$this->log( 'Health check: Process instance seems to have died. Spawn a new instance.' );
			$this->revive_process();
		} else {
			$this->log( 'Health check: Process instance seems to have died. Restart disabled, marking the process as dead.' );
			$this->mark_as_dead();
		}

		exit;
	}

	/**
	 * Revive process
	 */
	private function revive_process() {
		$this->do_action( 'revived' );
		$this->increment_revival_count();
		$this->spawn();
	}

	/**
	 * Mark process as dead
	 */
	protected function mark_as_dead() {
		$this->do_action( 'dead' );
		$this->status->mark_as_dead();
		$this->cleanup();
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		$hook = $this->cron_hook_identifier;
		if ( ! wp_next_scheduled( $hook ) ) {
			$interval = $this->cron_interval_identifier;
			$next_run = time() + $this->get_cron_interval_seconds();
			wp_schedule_event( $next_run, $interval, $hook );

			$this->log( "Scheduling new event with hook $hook to run $interval." );
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event() {
		$hook = $this->cron_hook_identifier;
		$this->log( "Cancelling event with hook $hook." );
		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Cancel process
	 */
	private function cancel_process() {
		$this->cleanup();
		$this->log( 'Process cancelled.' );
	}

	/**
	 * Cancel the process
	 */
	public function cancel() {
		// Update the cancel flag first.
		$active_instance_id = $this->get_active_instance_id();
		$this->log( "Starting cancellation (Instance: $active_instance_id)." );
		$this->status->cancel();

		$this->do_action( 'cancelled' );

		$this->log( "Cancelling the process (Instance: $active_instance_id)." );
		$this->cancel_process();
		$this->log( "Cancellation completed (Instance: $active_instance_id)." );
	}

	/**
	 * Task handler
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $task Queue item to iterate over.
	 * @return mixed
	 */
	abstract protected function task( $task );

	/**
	 * Check if instance is active
	 *
	 * @param string $instance_id Instance ID.
	 * @return bool
	 */
	private function is_active_instance( $instance_id ) {
		return $instance_id === $this->get_active_instance_id();
	}

	/**
	 * Set active instance ID
	 *
	 * @param string $instance_id Instance ID.
	 */
	private function set_active_instance_id( $instance_id ) {
		$this->thread_safe_options->set_values( $this->get_active_instance_option_id(), array( 'value' => $instance_id ) );
	}

	/**
	 * Get active instance ID
	 *
	 * @return string
	 */
	private function get_active_instance_id() {
		return $this->thread_safe_options->get_value( $this->get_active_instance_option_id(), 'value', '' );
	}

	/**
	 * Get active instance option ID
	 *
	 * @return string
	 */
	private function get_active_instance_option_id() {
		return $this->identifier . '_active_instance';
	}

	/**
	 * Set process ID
	 *
	 * @param string $instance_id Process ID.
	 */
	private function set_process_id( $instance_id ) {
		$this->thread_safe_options->set_values( $this->get_process_id_option_key(), array( 'value' => $instance_id ) );
	}

	/**
	 * Get process ID
	 *
	 * @return string
	 */
	public function get_process_id() {
		return $this->thread_safe_options->get_value( $this->get_process_id_option_key(), 'value', '' );
	}

	/**
	 * Delete process ID
	 */
	private function delete_process_id() {
		delete_option( $this->get_process_id_option_key() );
	}

	/**
	 * Get process ID option key
	 *
	 * @return string
	 */
	private function get_process_id_option_key() {
		return $this->identifier . '_process_id';
	}

	/**
	 * Get logger
	 *
	 * @return Background_Logger_Container
	 */
	private function logger() {
		return Utils::get_module( 'minify' );
	}

	/**
	 * Get status
	 *
	 * @return HB_Background_Process_Status
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Start the process
	 *
	 * @param array $tasks Array of tasks.
	 */
	public function start( $tasks ) {
		$this->do_action( 'before_start' );

		$total_items = count( $tasks );
		$this->log( "Starting new process with $total_items tasks" );

		$this->status->start( $total_items );
		$this->update_queue( $tasks );

		// Generate ID for the whole process.
		$this->set_process_id( $this->generate_unique_id() );

		// Trigger the started event before dispatching the request to ensure it is called before the completed event.
		$this->do_action( 'started' );

		$this->spawn();
	}

	/**
	 * Get time limit
	 *
	 * @return int
	 */
	private function get_time_limit() {
		return apply_filters( $this->identifier . '_default_time_limit', 20 ); // 20 seconds
	}

	/**
	 * Get instance expiry duration in seconds
	 *
	 * @return int
	 */
	protected function get_instance_expiry_duration_seconds() {
		return MINUTE_IN_SECONDS * 2;
	}

	/**
	 * Get last run transient key
	 *
	 * @return string
	 */
	private function get_last_run_transient_key() {
		return $this->identifier . '_last_run';
	}

	/**
	 * Clear last run timestamp
	 */
	private function clear_last_run_timestamp() {
		delete_site_transient( $this->get_last_run_transient_key() );
	}

	/**
	 * Cleanup process data
	 */
	private function cleanup() {
		// Delete options and transients.
		$this->delete_queue();
		delete_option( $this->get_active_instance_option_id() );
		$this->delete_process_id();
		$this->delete_revival_count();
		$this->clear_last_run_timestamp();

		// Cancel all events.
		$this->clear_scheduled_event();
	}

	/**
	 * Check if task limit is reached
	 *
	 * @param int $processed_tasks_count Number of processed tasks.
	 * @return bool
	 */
	private function task_limit_reached( $processed_tasks_count ) {
		if ( $this->get_tasks_per_request() === self::TASKS_PER_REQUEST_UNLIMITED ) {
			return false;
		}

		return $processed_tasks_count >= $this->get_tasks_per_request();
	}

	/**
	 * Get tasks per request
	 *
	 * @return int
	 */
	public function get_tasks_per_request() {
		return $this->tasks_per_request;
	}

	/**
	 * Set tasks per request
	 *
	 * @param int $tasks_per_request Number of tasks per request.
	 */
	public function set_tasks_per_request( $tasks_per_request ) {
		$this->tasks_per_request = $tasks_per_request;
	}

	/**
	 * Execute action hook
	 *
	 * @param string $action Action name.
	 */
	private function do_action( $action ) {
		do_action( $this->action_name( $action ), $this->identifier, $this );
	}

	/**
	 * Get cron interval in seconds
	 *
	 * @return int
	 */
	private function get_cron_interval_seconds() {
		$minutes = property_exists( $this, 'cron_interval' ) ? $this->cron_interval : 5;

		$interval = apply_filters( $this->identifier . '_cron_interval', $minutes );

		return $interval * MINUTE_IN_SECONDS;
	}

	/**
	 * Get identifier
	 *
	 * @return string
	 */
	public function get_identifier() {
		return $this->identifier;
	}

	/**
	 * Increment revival count
	 */
	private function increment_revival_count() {
		// Use atomic increment instead of read-modify-write.
		$this->thread_safe_options->increment_values( $this->get_revival_count_option_key(), array( 'value' ) );
	}

	/**
	 * Set revival count
	 *
	 * @param int $count Revival count.
	 */
	private function set_revival_count( $count ) {
		$this->thread_safe_options->set_values( $this->get_revival_count_option_key(), array( 'value' => $count ) );
	}

	/**
	 * Get revival count
	 *
	 * @return int
	 */
	public function get_revival_count() {
		return (int) $this->thread_safe_options->get_value( $this->get_revival_count_option_key(), 'value', 0 );
	}

	/**
	 * Delete revival count
	 */
	private function delete_revival_count() {
		$this->thread_safe_options->delete_option( $this->get_revival_count_option_key() );
	}

	/**
	 * Get revival count option key
	 *
	 * @return string
	 */
	private function get_revival_count_option_key() {
		return $this->identifier . '_revival_count';
	}

	/**
	 * Get revival limit
	 *
	 * @return int
	 */
	protected function get_revival_limit() {
		return apply_filters( $this->identifier . '_revival_limit', 5 );
	}

	/**
	 * Check if revival limit is reached
	 *
	 * @return bool
	 */
	protected function is_revival_limit_reached() {
		return $this->get_revival_count() >= $this->get_revival_limit();
	}

	/**
	 * Log message
	 *
	 * @since 3.16.0
	 * @param string $message Message to log.
	 */
	public function log( $message ) {
		$minify_module = Utils::get_module( 'background_processing' );
		$minify_module->log( $message );
	}

	/**
	 * Get action name for the process.
	 *
	 * @param string $action Name of the action.
	 * @return string
	 */
	public function action_name( $action ) {
		return "{$this->identifier}_$action";
	}
}
