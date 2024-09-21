<?php
/**
 * Integration with Avada theme.
 *
 * @package Hummingbird\Core\Integration
 */

namespace Hummingbird\Core\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Avada
 */
class Avada {

	/**
	 * Avada constructor.
	 */
	public function __construct() {
		add_action( 'fusion_cache_reset_after', array( $this, 'clear_hb_cache_after_fusion_cache_reset' ) );
		add_filter( 'wphb_do_not_run_ao_files', array( $this, 'wphb_do_not_run_ao_files' ) );
	}

	/**
	 * Do not run files when editing Avada pages.
	 *
	 * @param bool $do_not_run Whether to run AO files.
	 *
	 * @return bool
	 */
	public function wphb_do_not_run_ao_files( $do_not_run ) {
		$is_avada_live_builder = filter_input( INPUT_GET, 'fb-edit', FILTER_VALIDATE_BOOLEAN );
		if ( $this->is_avada_active() && $is_avada_live_builder ) {
			$do_not_run = true;
		}

		return $do_not_run;
	}

	/**
	 * Reset cache after Avada cache reset.
	 *
	 * @return void
	 */
	public function clear_hb_cache_after_fusion_cache_reset() {
		if ( $this->is_avada_active() ) {
			do_action( 'wphb_clear_page_cache' );
		}
	}

	/**
	 * Check if Avada is active.
	 *
	 * @return bool
	 */
	private function is_avada_active() {
		return defined( 'AVADA_VERSION' ) && AVADA_VERSION || defined( 'FUSION_BUILDER_VERSION' ) && FUSION_BUILDER_VERSION;
	}
}
