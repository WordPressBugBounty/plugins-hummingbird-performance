<?php
/**
 * Mutex helper for synchronized operations.
 *
 * @package Hummingbird\Core
 */

namespace Hummingbird\Core;

/**
 * Class Mutex
 */
class Mutex {
	/**
	 * Minimum MySQL/MariaDB version required for advisory lock support.
	 *
	 * @var string
	 */
	private static $required_mysql_version = '5.7';

	/**
	 * Lock key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * TRUE: Skip operation if lock is not acquired.
	 * FALSE: Execute operation even without lock.
	 *
	 * @var bool
	 */
	private $break_on_timeout = false;

	/**
	 * Lock wait timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout = 10;

	/**
	 * Cached MySQL/MariaDB version.
	 *
	 * @var string|null
	 */
	private $mysql_version;

	/**
	 * Constructor.
	 *
	 * @param string $key  Lock key.
	 */
	public function __construct( $key ) {
		$this->key = $key;
	}

	/**
	 * Execute an operation under lock.
	 *
	 * @param callable $operation  Operation to execute.
	 *
	 * @return mixed|null
	 */
	public function execute( $operation ) {
		if ( $this->is_supported() ) {
			$acquired = $this->acquire_lock();
			if ( $acquired || ! $this->break_on_timeout() ) {
				try {
					return call_user_func( $operation );
				} finally {
					if ( $acquired ) {
						$this->release_lock();
					}
				}
			}

			return null;
		}

		return call_user_func( $operation );
	}

	/**
	 * Acquire advisory lock.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		global $wpdb;

		$lock = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s,%d) as lock_set',
				array(
					$this->get_key(),
					$this->get_timeout(),
				)
			)
		);

		return 1 === intval( $lock->lock_set );
	}

	/**
	 * Release advisory lock.
	 */
	private function release_lock() {
		global $wpdb;

		$wpdb->get_row(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s) as lock_released',
				array( $this->get_key() )
			)
		);
	}

	/**
	 * Get break-on-timeout mode.
	 *
	 * @return bool
	 */
	public function break_on_timeout() {
		return $this->break_on_timeout;
	}

	/**
	 * Set break-on-timeout mode.
	 *
	 * @param bool $break_on_timeout  Whether to skip operation if lock is not acquired.
	 *
	 * @return self
	 */
	public function set_break_on_timeout( $break_on_timeout ) {
		$this->break_on_timeout = $break_on_timeout;

		return $this;
	}

	/**
	 * Get lock timeout.
	 *
	 * @return int
	 */
	public function get_timeout() {
		return $this->timeout;
	}

	/**
	 * Set lock timeout.
	 *
	 * @param int $timeout  Timeout in seconds.
	 *
	 * @return self
	 */
	public function set_timeout( $timeout ) {
		$this->timeout = max( 0, (int) $timeout );

		return $this;
	}

	/**
	 * Get lock key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Set lock key.
	 *
	 * @param string $key  Lock key.
	 *
	 * @return self
	 */
	public function set_key( $key ) {
		$this->key = $key;

		return $this;
	}

	/**
	 * Determine lock support.
	 *
	 * @return bool
	 */
	private function is_supported() {
		return $this->is_mysql_requirement_met();
	}

	/**
	 * Get active MySQL/MariaDB version.
	 *
	 * @return string
	 */
	private function get_actual_mysql_version() {
		if ( ! $this->mysql_version ) {
			global $wpdb;

			$mysql_version = $wpdb->get_var( 'SELECT VERSION()' );

			$this->mysql_version = preg_replace(
				'/^5\.5\.5-/',
				'',
				(string) $mysql_version
			);
		}

		return $this->mysql_version;
	}

	/**
	 * Check MySQL/MariaDB version requirement.
	 *
	 * @return bool
	 */
	private function is_mysql_requirement_met() {
		return version_compare( $this->get_actual_mysql_version(), self::$required_mysql_version, '>=' );
	}
}
