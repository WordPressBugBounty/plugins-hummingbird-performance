<?php
/**
 * React Configs AJAX actions.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax
 */

namespace Hummingbird\Admin\Ajax;

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Configs.
 */
class Configs {
	/**
	 * Site option holding the stored configs list.
	 *
	 * @since 4.0.0
	 */
	const OPTION_NAME = 'wphb-preset_configs';

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'configs';

	/**
	 * Configs constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'configs_save_list',
			'configs_delete',
			'configs_create',
			'configs_upload',
			'configs_apply',
			'configs_sync_hub',
			'configs_recheck_hub',
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Register callbacks.
			 *
			 * @uses configs_save_list()
			 * @uses configs_delete()
			 * @uses configs_create()
			 * @uses configs_upload()
			 * @uses configs_apply()
			 * @uses configs_sync_hub()
			 * @uses configs_recheck_hub()
			 */
			add_action( "wp_ajax_wphb_react_$endpoint", array( $this, $endpoint ) );
		}
	}

	private function handleActivityLog( $existing_list, $list ) {
		$existing_by_id = array_column( $existing_list, null, 'id' );
		$current_by_id  = array_column( $list, null, 'id' );

		// Configs added or edited.
		foreach ( $current_by_id as $id => $current ) {
			// Ignore default config if it isn't an actual saved config.
			if ( ! isset( $existing_by_id[ $id ] ) ) {
				Activity_Log::record(
					self::ACTIVITY_MODULE,
					sprintf(
						/* translators: %s: config name */
						__( '%s saved.', 'wphb' ),
						$current['name']
					)
				);

				continue;
			}

			$existing = $existing_by_id[ $id ];

			// Config itself changed.
			if ( ( $existing['name'] ?? '' ) !== ( $current['name'] ?? '' ) ) {
				Activity_Log::record(
					self::ACTIVITY_MODULE,
					sprintf(
						/* translators: %s: config name */
						__( '%s edited.', 'wphb' ),
						$current['name']
					)
				);
			}

			if ( ( $existing['description'] ?? '' ) !== ( $current['description'] ?? '' ) ) {
				if ( empty( $current['description'] ) ) {
					Activity_Log::record(
						self::ACTIVITY_MODULE,
						sprintf(
						/* translators: %s: config name */
							__( '%s note deleted.', 'wphb' ),
							$current['name']
						)
					);
				} elseif ( empty( $existing['description'] ) ) {
					Activity_Log::record(
						self::ACTIVITY_MODULE,
						sprintf(
						/* translators: %s: config name */
							__( '%s note added.', 'wphb' ),
							$current['name']
						)
					);
				} else {
					Activity_Log::record(
						self::ACTIVITY_MODULE,
						sprintf(
						/* translators: %s: config name */
							__( '%s note edited.', 'wphb' ),
							$current['name']
						)
					);
				}
			}
		}

		// Configs deleted.
		foreach ( $existing_by_id as $id => $existing ) {
			if ( ! isset( $current_by_id[ $id ] ) ) {
				Activity_Log::record(
					self::ACTIVITY_MODULE,
					sprintf(
						/* translators: %s: config name */
						__( '%s deleted.', 'wphb' ),
						$existing['name']
					)
				);
			}
		}
	}

	/**
	 * Persist the full (client-mutated) configs list.
	 *
	 * @since 4.0.0
	 */
	public function configs_save_list() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$list = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$list = json_decode( html_entity_decode( $list ), true );

		if ( ! is_array( $list ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing configs data.', 'wphb' ) ) );
		}

		$configs = new \Hummingbird\Core\Configs();

		foreach ( $list as $key => $value ) {
			$list[ $key ] = $configs->sanitize_config( $value );
		}

		$existing = get_site_option( self::OPTION_NAME, array() );
		update_site_option( self::OPTION_NAME, $list );
		$this->handleActivityLog( $existing, $list );

		wp_send_json_success( array( 'configs' => $list ) );
	}

	/**
	 * Delete configs by id, locally and on the Hub.
	 *
	 * @since 4.0.0
	 */
	public function configs_delete() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );
		$ids  = isset( $data['ids'] ) && is_array( $data['ids'] ) ? $data['ids'] : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing config IDs.', 'wphb' ) ) );
		}

		$configs  = new \Hummingbird\Core\Configs();
		$existing = $configs->get_stored_configs();
		$list     = $configs->delete_configs( $ids );

		$this->handleActivityLog( $existing, $list );

		wp_send_json_success( array( 'configs' => $list ) );
	}

	/**
	 * Build a new config object from the site's current settings.
	 *
	 * @since 4.0.0
	 */
	public function configs_create() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$configs = new \Hummingbird\Core\Configs();

		wp_send_json_success( $configs->get_config_from_current() );
	}

	/**
	 * Decode and validate an uploaded config JSON file.
	 *
	 * @since 4.0.0
	 */
	public function configs_upload() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$file = $_FILES['file'] ?? false;

		$configs    = new \Hummingbird\Core\Configs();
		$new_config = $configs->save_uploaded_config( $file );

		if ( is_wp_error( $new_config ) ) {
			wp_send_json_error( array( 'message' => $new_config->get_error_message() ) );
		}

		wp_send_json_success( $new_config );
	}

	/**
	 * Apply a stored config by id.
	 *
	 * @since 4.0.0
	 */
	public function configs_apply() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );
		$id   = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing config ID.', 'wphb' ) ) );
		}

		// is_network_admin() is always false during admin-ajax, so fall back to the
		// referer check. Subsite applies must not write network-scoped settings.
		$apply_network_settings = ! is_multisite() || Utils::is_ajax_network_admin();

		$configs  = new \Hummingbird\Core\Configs();
		$response = $configs->apply_config_by_id( $id, $apply_network_settings );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		Activity_Log::record(
			self::ACTIVITY_MODULE,
			sprintf(
			/* translators: %s: config name */
				__( '%s applied.', 'wphb' ),
				isset( $response['name'] ) ? $response['name'] : '',
			)
		);

		wp_send_json_success();
	}

	/**
	 * Push a newly-created config to the Hub.
	 *
	 * @since 4.0.0
	 */
	public function configs_sync_hub() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		if ( ! Utils::has_access_to_hub() ) {
			wp_send_json_error( array( 'message' => __( 'Please connect your site to WPMU DEV to sync this config.', 'wphb' ) ) );
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $data ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing config data.', 'wphb' ) ) );
		}

		$configs  = new \Hummingbird\Core\Configs();
		$response = $configs->sync_config_to_hub( $data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Pull configs from the Hub and refresh the stored list.
	 *
	 * @since 4.0.0
	 */
	public function configs_recheck_hub() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$configs = new \Hummingbird\Core\Configs();
		$list    = $configs->sync_from_hub();

		if ( is_wp_error( $list ) ) {
			wp_send_json_error( array( 'message' => $list->get_error_message() ) );
		}

		wp_send_json_success( array( 'configs' => $list ) );
	}
}
