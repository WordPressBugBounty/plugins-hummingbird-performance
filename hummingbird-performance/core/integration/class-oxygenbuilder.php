<?php
/**
 * Integration with Oxygen Builder.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OxygenBuilder
 */
class OxygenBuilder {

	/**
	 * OxygenBuilder constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'wphb_oxygen_builder_after_init' ), 10 );
	}

	/**
	 * Run on init action.
	 */
	public function wphb_oxygen_builder_after_init() {
		if ( $this->is_oxygen_builder_active() && $this->is_oxygen_builder_launched_on_frontend() ) {
			add_action( 'save_post', array( $this, 'save_post' ), 10, 3 );
		}
	}

	/**
	 * Reset cache after a page update from the frontend.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {
		do_action( 'wphb_clear_page_cache', $post_id ); // Clear page cache for the supplied post.
	}

	/**
	 * Check if Oxygen Builder is active.
	 *
	 * @return bool
	 */
	public function is_oxygen_builder_active() {
		return defined( 'CT_VERSION' ) && CT_VERSION;
	}

	/**
	 * Check if oxygen builder is active on frontend.
	 *
	 * @return bool
	 */
	public function is_oxygen_builder_launched_on_frontend() {
		$ct_builder = filter_input( INPUT_GET, 'action' );

		return 'ct_save_components_tree' === $ct_builder;
	}
}
