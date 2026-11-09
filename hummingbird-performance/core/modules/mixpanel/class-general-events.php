<?php
/**
 * Mixpanel events that are not tied to one optimization module.
 *
 * @since 4.0.1
 * @package Hummingbird\Core\Modules\Mixpanel
 */

namespace Hummingbird\Core\Modules\Mixpanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class General_Events.
 *
 * Events not tied to one module: Safe Mode, the rating notice, the Hub disconnect and the setup wizard.
 */
class General_Events extends Events {

	/**
	 * Track Safe Mode being switched, published or discarded.
	 *
	 * @param string $mode   'enabled' or 'disabled'.
	 * @param string $change How the staged changes were resolved: 'publish_plugin',
	 *                       'publish_frontend', 'discard', or 'na' for a plain switch.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_safe_mode( $mode, $change = 'na' ) {
		return $this->track(
			'Safe Mode',
			array(
				'Mode'     => $mode,
				'Changes'  => $change,
				'Location' => $this->analytics->get_page_label(),
			)
		);
	}

	/**
	 * Track the answer to the rating notice; 'Dashboard' is the only Location the 4.0 sheet keeps for it.
	 *
	 * @param string $action 'rate', 'remind_later' or 'dismiss'.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_rating_notice( $action ) {
		return $this->track(
			'Rating Notice',
			array(
				'Action'   => $action,
				'Location' => 'Dashboard',
			)
		);
	}

	/**
	 * Track the Hub disconnect modal; a submit is sent even when opted out (as in 3.x), a cancel is not.
	 *
	 * @param string $action 'submit' or 'cancel'.
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_disconnect_site( $action ) {
		$properties = array(
			'Modal Action'    => $action,
			'Tracking Status' => $this->is_enabled() ? 'opted_in' : 'opted_out',
		);

		if ( 'submit' === $action ) {
			return $this->analytics->send( 'Disconnect Site', $properties );
		}

		return $this->track( 'Disconnect Site', $properties );
	}

	/**
	 * Track how the setup wizard was left.
	 *
	 * @param string $action 'quit' (closed), 'skip' (skipped for now) or
	 *                       'performance_test' (finished with a test).
	 *
	 * @return bool Whether the event was sent.
	 */
	public function track_setup_wizard( $action ) {
		return $this->track( 'Setup Wizard', array( 'Action' => $action ) );
	}
}
