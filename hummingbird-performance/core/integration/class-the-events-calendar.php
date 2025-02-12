<?php
/**
 * Integration with The Events Calendar.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class The_Events_Calendar
 */
class The_Events_Calendar {

	/**
	 * The_Events_Calendar constructor.
	 */
	public function __construct() {
		add_filter( 'wphb_should_cache_exit', array( $this, 'wphb_should_cache_exit' ) );
	}

	/**
	 * Should Cache Exit.
	 *
	 * @param bool $should_exit Should Cache Exit. Default: false.
	 */
	public function wphb_should_cache_exit( $should_exit ) {
		// Early exit if The Events Calendar plugin is not active.
		if ( ! $this->is_the_events_calendar_active() ) {
			return $should_exit;
		}

		// Return true immediately if the current post type is 'tribe_events'.
		return ( apply_filters( 'wphb_do_not_cache_tribe_events', true ) && 'tribe_events' === get_post_type() ) ? true : $should_exit;
	}

	/**
	 * Check if The Events Calendar is active.
	 *
	 * @return bool
	 */
	private function is_the_events_calendar_active() {
		return defined( 'TRIBE_EVENTS_FILE' ) && TRIBE_EVENTS_FILE;
	}
}
