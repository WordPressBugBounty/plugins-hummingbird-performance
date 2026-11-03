<?php
/**
 * Configs module class.
 *
 * @since 3.0.1
 * @package Hummingbird\Core
 */

namespace Hummingbird\Core;

use Exception;
use Hummingbird\Core\Integration\Opcache;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Configs
 */
class Configs {

	/**
	 * WPMU DEV package id for Hummingbird, used by every Hub configs call.
	 *
	 * @since 4.0.0
	 */
	const PACKAGE_ID = '1081721';

	/**
	 * Site option holding Hub config ids deleted locally but not yet removed on the Hub.
	 *
	 * @since 4.0.0
	 */
	const DELETED_HUB_IDS_OPTION = 'wphb-deleted-hub-config-ids';

	/**
	 * Config defaults (that are different from plugin defaults).
	 *
	 * @var array $defaults
	 */
	private $defaults = array(
		'gravatar'   => array(
			'enabled' => true,
		),
		// Asset Optimization is off on a fresh install, and Compress and Combine are what switch it on.
		'minify'     => array(
			'combine'  => false,
			'compress' => false,
		),
		'page_cache' => array(
			'enabled' => true,
		),
		'settings'   => array(
			'control' => true,
		),
	);

	/**
	 * Human readable setting names, keyed by submodule and then by leaf setting.
	 *
	 * Filled on first use rather than on construction: the map holds translated strings,
	 * and `Configs` is instantiated from REST and Hub paths that can run before
	 * translations load.
	 *
	 * @since 4.0.0
	 *
	 * @var array $descriptions
	 */
	private $descriptions = array();

	/**
	 * Human readable setting values, keyed by submodule, then by leaf setting, then by the
	 * value the option stores.
	 *
	 * Only values that read as a machine token live here - free text, counts, numbers and
	 * booleans are left to the caller.
	 *
	 * @since 4.0.0
	 *
	 * @var array $value_labels
	 */
	private $value_labels = array();

	/**
	 * Pushes a newly-created config to the Hub.
	 *
	 * @since 4.0.0
	 *
	 * @param array $config Config with `name`, `description`, `config` fields.
	 *
	 * @return array|object|WP_Error Decoded Hub response (includes `id`) or WP_Error.
	 */
	public function sync_config_to_hub( $config ) {
		return Utils::get_api()->package_configs->sync_config(
			array(
				'name'        => sanitize_text_field( $config['name'] ?? '' ),
				'description' => sanitize_text_field( $config['description'] ?? '' ),
				'package'     => array(
					'name' => Utils::is_member() ? 'Hummingbird Pro' : 'Hummingbird',
					'id'   => self::PACKAGE_ID,
				),
				'config'      => wp_json_encode( $config['config'] ?? array() ),
			)
		);
	}

	/**
	 * Get the stored list of configs, seeding the built-in "Default config" the first time.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function get_stored_configs() {
		$stored_configs = get_site_option( 'wphb-preset_configs', false );

		if ( false === $stored_configs ) {
			$stored_configs = array( $this->get_basic_config() );

			update_site_option( 'wphb-preset_configs', $stored_configs );
		}

		return $stored_configs;
	}

	/**
	 * Adds the "Default" configuration to the local configs.
	 *
	 * @since 3.0.1
	 */
	public function get_basic_config() {
		$settings = Settings::get_default_settings();
		ksort( $settings );

		$settings = $this->remove_non_unique_settings( $settings );
		$settings = array_replace_recursive( $settings, $this->defaults );
		$settings = $this->remove_disabled_options( $settings );

		$settings['minify']['compression_type'] = get_option( 'wphb_compression_type' );

		$basic_config = array(
			'id'          => 1,
			'name'        => __( 'Default config', 'wphb' ),
			'description' => __( 'Recommended performance config for every site.', 'wphb' ),
			'default'     => true,
			'config'      => array(
				'configs' => array(
					'settings' => $settings,
				),
			),
		);

		$basic_config['config']['strings'] = $this->format_config_to_display( $basic_config['config']['configs'] );

		return $basic_config;
	}

	/**
	 * Gets a new config array based on the current settings.
	 *
	 * @since 3.0.1
	 *
	 * @return array
	 */
	public function get_config_from_current() {
		$settings = Settings::get_settings();
		ksort( $settings );

		$settings = $this->remove_non_unique_settings( $settings );
		$settings = $this->remove_disabled_options( $settings );

		// Compress and Combine are what switch Asset Optimization on, so export them as off while
		// it's off (sites where it's off still store them as on).
		$ao_active                      = (bool) Utils::get_module( 'minify' )->is_active();
		$settings['minify']['compress'] = $ao_active && $settings['minify']['compress'];
		$settings['minify']['combine']  = $ao_active && $settings['minify']['combine'];

		$settings['minify']['compression_type'] = get_option( 'wphb_compression_type' );

		$configs = compact( 'settings' );

		return array(
			'config' => array(
				'configs' => $configs,
				'strings' => $this->format_config_to_display( $configs ),
			),
		);
	}

	/**
	 * Save uploaded config.
	 *
	 * @since 3.0.1
	 *
	 * @param array $file  Uploaded file.
	 */
	public function save_uploaded_config( $file ) {
		try {
			$config = $this->decode_and_validate_config_file( $file );
		} catch ( Exception $e ) {
			return new WP_Error( 'error_saving', $e->getMessage() );
		}

		return $config;
	}

	/**
	 * Apply a config based on a given ID.
	 *
	 * @since 3.0.1
	 *
	 * @param int  $id                     The ID of the config to apply.
	 * @param bool $apply_network_settings Whether network-scoped settings may be written.
	 *                                     Defaults to true so the Hub and the legacy admin
	 *                                     keep their behaviour; pass false from a subsite.
	 *
	 * @return array|WP_Error
	 */
	public function apply_config_by_id( $id, $apply_network_settings = true ) {
		$stored_configs = get_site_option( 'wphb-preset_configs', array() );

		$config = false;
		foreach ( $stored_configs as $config_data ) {
			if ( $config_data['id'] === $id ) {
				$config = $config_data;
				break;
			}
		}

		// The config with the given ID doesn't exist.
		if ( ! $config ) {
			return new WP_Error( '404', __( 'The given config ID does not exist', 'wphb' ) );
		}

		$this->apply_config( $config['config']['configs'], $apply_network_settings );

		return array( 'name' => $config['name'] );
	}

	/**
	 * Apply selected config.
	 *
	 * @since 3.0.1
	 *
	 * @param array $config                 Config to apply.
	 * @param bool  $apply_network_settings Whether network-scoped settings may be written.
	 *                                      Defaults to true; pass false from a subsite.
	 */
	public function apply_config( $config, $apply_network_settings = true ) {
		$settings = Settings::get_settings();

		// Redis integration was removed in 4.0.0; configs from older versions still carry its key.
		unset( $config['settings']['redis'] );

		if ( is_multisite() && ! $apply_network_settings ) {
			$config['settings'] = $this->remove_network_only_options( $config['settings'] );
		}

		$new_settings = array_replace_recursive( $settings, $config['settings'] );

		// Disable opcache integration if opcache not enabled on server.
		$opcache_option = isset( $new_settings['page_cache'] ) && isset( $new_settings['page_cache']['integrations'] ) && isset( $new_settings['page_cache']['integrations']['opcache'] );
		if ( true === $opcache_option && ! Opcache::get_instance()->is_enabled() ) {
			$new_settings['page_cache']['integrations']['opcache'] = false;
		}

		// Disable WooCommerce cart fragments if site does not have WooCommerce.
		$cart_fragments = isset( $new_settings['advanced'] ) && isset( $new_settings['advanced']['cart_fragments'] );
		if ( true === $cart_fragments && ! class_exists( 'woocommerce' ) ) {
			$new_settings['advanced']['cart_fragments'] = false;
		}

		Settings::update_settings( $new_settings );

		// Compress and Combine are what switch Asset Optimization on. Configs without them leave it as is.
		if ( isset( $config['settings']['minify']['compress'] ) || isset( $config['settings']['minify']['combine'] ) ) {
			$minify     = Utils::get_module( 'minify' );
			$ao_enabled = ! empty( $new_settings['minify']['compress'] ) || ! empty( $new_settings['minify']['combine'] );

			if ( (bool) $minify->is_active() !== $ao_enabled ) {
				if ( $ao_enabled ) {
					$minify->toggle_service( true );
				} else {
					// Same as switching both off on the AO page: keep the collected files and exclusions.
					$minify->disable( true, false );
				}
			}
		}

		// Toggle Uptime module.
		if ( $config['settings']['uptime']['enabled'] !== $settings['uptime']['enabled'] ) {
			if ( $config['settings']['uptime']['enabled'] ) {
				Utils::get_module( 'uptime' )->enable();
				Utils::get_module( 'uptime' )->get_last_report( 'week', true );
			} else {
				Utils::get_module( 'uptime' )->disable();
			}
		}

		update_option( 'wphb_run_onboarding', null );
	}

	/**
	 * Drop settings a subsite has no business writing.
	 *
	 * @since 4.0.0
	 *
	 * @param array $settings Settings from the config being applied.
	 *
	 * @return array
	 */
	private function remove_network_only_options( $settings ) {
		if ( ! isset( $settings['cdn'] ) || ! is_array( $settings['cdn'] ) ) {
			return $settings;
		}

		unset( $settings['cdn']['enabled'], $settings['cdn']['cdn_settings'] );

		if ( empty( $settings['cdn'] ) ) {
			unset( $settings['cdn'] );
		}

		return $settings;
	}

	/**
	 * Re-check configs on the Hub and merge them into the stored list.
	 *
	 * @since 4.0.0
	 *
	 * @return array|WP_Error
	 */
	public function sync_from_hub() {
		if ( ! Utils::has_access_to_hub() ) {
			return new WP_Error(
				'hub_required',
				__( 'Please connect your site to WPMU DEV to sync configs.', 'wphb' )
			);
		}

		$response = Utils::get_api()->package_configs->get_configs( self::PACKAGE_ID );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$hub_configs = $this->normalize_hub_configs_response( $response );

		if ( null === $hub_configs ) {
			return new WP_Error(
				'hub_response_invalid',
				__( 'Could not read configs from the Hub.', 'wphb' )
			);
		}

		$stored   = $this->get_stored_configs();
		$deleted  = $this->get_deleted_hub_ids();
		$next     = array();
		$consumed = array();

		// The default config is local-only and always leads the list.
		foreach ( $stored as $index => $config ) {
			if ( ! empty( $config['default'] ) ) {
				$next[]             = $config;
				$consumed[ $index ] = true;
			}
		}

		foreach ( $hub_configs as $hub_config ) {
			$hub_id = (string) $hub_config['id'];

			// Deleted here, but the Hub delete has not succeeded yet: do not re-add it.
			if ( in_array( $hub_id, $deleted, true ) ) {
				continue;
			}

			$match       = null;
			$match_index = null;

			foreach ( $stored as $index => $config ) {
				if ( ! empty( $config['default'] ) || isset( $consumed[ $index ] ) ) {
					continue;
				}

				$local_hub_id = isset( $config['hub_id'] ) ? (string) $config['hub_id'] : '';
				$local_id     = isset( $config['id'] ) ? (string) $config['id'] : '';

				if ( $local_hub_id === $hub_id || $local_id === $hub_id ) {
					$match       = $config;
					$match_index = $index;
					break;
				}
			}

			if ( null !== $match_index ) {
				$consumed[ $match_index ] = true;
			}

			$updated = is_array( $match ) ? $match : array(
				'id'   => $hub_config['id'],
				'date' => $hub_config['date'],
			);

			$updated['hub_id']      = $hub_config['id'];
			$updated['name']        = $hub_config['name'];
			$updated['description'] = $hub_config['description'];

			if ( ! empty( $hub_config['config'] ) ) {
				$updated['config'] = $hub_config['config'];

				if ( ! empty( $updated['config']['configs'] ) ) {
					$updated['config']['strings'] = $this->format_config_to_display( $updated['config']['configs'] );
				}
			}

			$next[] = $this->sanitize_config( $updated );
		}

		foreach ( $stored as $index => $config ) {
			if ( isset( $consumed[ $index ] ) || ! empty( $config['default'] ) || ! empty( $config['hub_id'] ) ) {
				continue;
			}

			$next[] = $this->push_config_to_hub( $config );
		}

		update_site_option( 'wphb-preset_configs', $next );

		return $next;
	}

	/**
	 * Push a never-synced config to the Hub, returning it with its Hub id attached.
	 *
	 * @since 4.0.0
	 *
	 * @param array $config Stored config row.
	 *
	 * @return array
	 */
	private function push_config_to_hub( $config ) {
		$response = $this->sync_config_to_hub( $config );

		if ( is_wp_error( $response ) ) {
			return $config;
		}

		$response = is_object( $response ) ? (array) $response : $response;
		$hub_id   = is_array( $response ) && ! empty( $response['id'] ) ? $response['id'] : '';

		if ( '' === $hub_id ) {
			return $config;
		}

		$config['id']     = $hub_id;
		$config['hub_id'] = $hub_id;

		return $config;
	}

	/**
	 * Delete a config from the Hub.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hub_id Hub config id.
	 *
	 * @return bool True when the Hub confirmed the delete.
	 */
	private function delete_config_from_hub( $hub_id ) {
		$hub_id = (string) $hub_id;

		if ( '' === $hub_id ) {
			return false;
		}

		$this->add_deleted_hub_id( $hub_id );

		if ( ! Utils::has_access_to_hub() ) {
			return false;
		}

		$response = Utils::get_api()->package_configs->delete_config( $hub_id );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = (array) $response;

		if ( isset( $response['deleted'] ) && ! $response['deleted'] ) {
			return false;
		}

		$this->remove_deleted_hub_id( $hub_id );

		return true;
	}

	/**
	 * Delete configs by id, working from the stored list rather than one sent by the browser.
	 *
	 * @since 4.0.0
	 *
	 * @param array $ids Config ids to delete. The default config is never deleted.
	 *
	 * @return array The remaining configs.
	 */
	public function delete_configs( $ids ) {
		$ids       = array_map( 'strval', array_filter( (array) $ids, 'is_scalar' ) );
		$remaining = array();
		$removed   = array();

		foreach ( $this->get_stored_configs() as $config ) {
			if ( empty( $config['default'] ) && isset( $config['id'] ) && in_array( (string) $config['id'], $ids, true ) ) {
				$removed[] = $config;
			} else {
				$remaining[] = $config;
			}
		}

		if ( empty( $removed ) ) {
			return $remaining;
		}

		update_site_option( 'wphb-preset_configs', $remaining );

		foreach ( $removed as $config ) {
			if ( ! empty( $config['hub_id'] ) ) {
				$this->delete_config_from_hub( $config['hub_id'] );
			}
		}

		return $remaining;
	}

	/**
	 * Hub config ids deleted locally whose Hub delete has not succeeded yet.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_deleted_hub_ids() {
		return array_map( 'strval', (array) get_site_option( self::DELETED_HUB_IDS_OPTION, array() ) );
	}

	/**
	 * Remember a Hub config id that was deleted locally.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hub_id Hub config id.
	 */
	private function add_deleted_hub_id( $hub_id ) {
		$ids   = $this->get_deleted_hub_ids();
		$ids[] = (string) $hub_id;

		update_site_option( self::DELETED_HUB_IDS_OPTION, array_values( array_unique( $ids ) ) );
	}

	/**
	 * Forget a tombstoned Hub config id once the Hub has confirmed the delete.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hub_id Hub config id.
	 */
	private function remove_deleted_hub_id( $hub_id ) {
		$ids = array_diff( $this->get_deleted_hub_ids(), array( (string) $hub_id ) );

		update_site_option( self::DELETED_HUB_IDS_OPTION, array_values( $ids ) );
	}

	/**
	 * Normalize a Hub package-configs payload into stored-config rows.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $response Decoded Hub response.
	 *
	 * @return array|null List of configs, or null when the shape is unrecognized.
	 */
	private function normalize_hub_configs_response( $response ) {
		if ( is_object( $response ) ) {
			$response = (array) $response;
		}

		if ( ! is_array( $response ) ) {
			return null;
		}

		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$items = $response['data'];
		} elseif ( isset( $response['configs'] ) && is_array( $response['configs'] ) ) {
			$items = $response['configs'];
		} elseif ( isset( $response['id'] ) ) {
			$items = array( $response );
		} elseif ( array() === $response || array_keys( $response ) === range( 0, count( $response ) - 1 ) ) {
			$items = $response;
		} else {
			return null;
		}

		$configs = array();

		foreach ( $items as $item ) {
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}

			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$payload = array();
			if ( ! empty( $item['config'] ) ) {
				$decoded = is_array( $item['config'] ) ? $item['config'] : json_decode( $item['config'], true );
				if ( is_string( $decoded ) ) {
					$decoded = json_decode( $decoded, true );
				}
				if ( is_array( $decoded ) ) {
					$payload = isset( $decoded['configs'] ) || isset( $decoded['strings'] )
						? $decoded
						: array( 'configs' => $decoded );
				}
			}

			$date = $item['date'] ?? $item['updated_at'] ?? $item['created_at'] ?? time();

			$configs[] = array(
				'id'          => $item['id'],
				'name'        => sanitize_text_field( $item['name'] ?? '' ),
				'description' => sanitize_text_field( $item['description'] ?? '' ),
				'date'        => is_numeric( $date ) ? (int) $date : time(),
				'config'      => $payload,
			);
		}

		return $configs;
	}

	/**
	 * Sanitize config data.
	 *
	 * @param array $config  Config.
	 *
	 * @return array
	 */
	public function sanitize_config( $config ) {
		if ( isset( $config['name'] ) ) {
			$name = sanitize_text_field( $config['name'] );

			$config['name'] = empty( $name ) ? __( 'Undefined', 'wphb' ) : $name;
		}

		if ( isset( $config['description'] ) ) {
			$config['description'] = sanitize_text_field( $config['description'] );
		}

		return $config;
	}

	/**
	 * Decode and validate the uploaded config file.
	 *
	 * @since 3.0.1
	 *
	 * @param array $file  Uploaded file.
	 *
	 * @return array
	 * @throws Exception  When there's an error with the uploaded file.
	 */
	private function decode_and_validate_config_file( $file ) {
		if ( ! $file ) {
			throw new Exception( __( 'The configs file is required', 'wphb' ) );
		} elseif ( ! empty( $file['error'] ) ) {
			/* translators: %s - error message */
			throw new Exception( sprintf( __( 'Error: %s.', 'wphb' ), $file['error'] ) );
		} elseif ( 'application/json' !== $file['type'] ) {
			throw new Exception( __( 'The file must be a JSON.', 'wphb' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json_file = file_get_contents( $file['tmp_name'] );
		if ( ! $json_file ) {
			throw new Exception( __( 'There was an error getting the contents of the file.', 'wphb' ) );
		}

		$configs = json_decode( $json_file, true );
		if ( empty( $configs ) || ! is_array( $configs ) ) {
			throw new Exception( __( 'There was an error decoding the file.', 'wphb' ) );
		}

		// Make sure the config has a name and configs.
		if ( empty( $configs['name'] ) || empty( $configs['config'] ) ) {
			throw new Exception( __( 'The uploaded config must have a name and a set of settings. Please make sure the uploaded file is the correct one.', 'wphb' ) );
		}

		$configs = $this->sanitize_config( $configs );

		// Let's re-create this to avoid differences between imported settings coming from other versions.
		$configs['config']['strings'] = $this->format_config_to_display( $configs['config']['configs'] );

		if ( empty( $configs['config']['configs'] ) ) {
			throw new Exception( __( 'The provided configs list isn’t correct. Please make sure the uploaded file is the correct one.', 'wphb' ) );
		}

		// Don't keep these if they exist.
		unset( $configs['hub_id'] );
		unset( $configs['default'] );

		return $configs;
	}

	/**
	 * Remove non-unique settings.
	 *
	 * @since 3.0.1
	 *
	 * @param array $settings  Array of settings.
	 *
	 * @return array
	 */
	private function remove_non_unique_settings( $settings ) {
		if ( isset( $settings['minify'] ) ) {
			$minify_keys_to_keep = array(
				'combine',
				'compress',
				'font_swap',
				'font_display_value',
				'font_optimization',
				'preload_fonts_mode',
				'critical_css',
				'delay_js',
			);

			$settings['minify'] = array_intersect_key( $settings['minify'], array_flip( $minify_keys_to_keep ) );
		}

		// Uptime.
		if ( isset( $settings['uptime'] ) ) {
			if ( isset( $settings['uptime']['notifications'] ) ) {
				unset( $settings['uptime']['notifications'] );
			}
			if ( isset( $settings['uptime']['reports'] ) ) {
				unset( $settings['uptime']['reports'] );
			}
		}

		// Page caching.
		// `pages_cached` moved to its own option (`Settings::PAGES_CACHED_OPTION`), so it is no
		// longer exported with a config. The `cache_blog` scrub below predates that and stays as
		// is; a config exported before the split may still carry `pages_cached`, and
		// `apply_config()` ignores unknown keys, so importing one is harmless.
		if ( ! is_multisite() && ! is_network_admin() ) {
			unset( $settings['page_cache']['cache_blog'] );
		}

		// Browser caching.
		if ( isset( $settings['caching'] ) ) {
			unset( $settings['caching'] );
		}

		// Cloudflare.
		if ( isset( $settings['cloudflare'] ) ) {
			unset( $settings['cloudflare'] );
		}

		// Performance test.
		if ( isset( $settings['performance'] ) ) {
			if ( isset( $settings['performance']['reports'] ) ) {
				unset( $settings['performance']['reports'] );
			}
			if ( isset( $settings['performance']['dismissed'] ) ) {
				unset( $settings['performance']['dismissed'] );
			}
			if ( ! is_multisite() && ! is_network_admin() && isset( $settings['performance']['subsite_tests'] ) ) {
				unset( $settings['performance']['subsite_tests'] );
			}
		}

		// Advanced tools.
		if ( isset( $settings['advanced'] ) ) {
			if ( isset( $settings['advanced']['prefetch'] ) ) {
				unset( $settings['advanced']['prefetch'] );
			}
			if ( isset( $settings['advanced']['preconnect'] ) ) {
				unset( $settings['advanced']['preconnect'] );
			}
			if ( ! is_multisite() && ! is_network_admin() && isset( $settings['advanced']['query_strings_global'] ) ) {
				unset( $settings['advanced']['query_strings_global'] );
			}
			if ( ! is_multisite() && ! is_network_admin() && isset( $settings['advanced']['emoji_global'] ) ) {
				unset( $settings['advanced']['emoji_global'] );
			}
			if ( isset( $settings['advanced']['lazy_load'] ) && isset( $settings['advanced']['lazy_load']['button'] ) ) {
				unset( $settings['advanced']['lazy_load']['button'] );
			}
		}

		// Database reports.
		if ( isset( $settings['database'] ) ) {
			unset( $settings['database'] );
		}

		// Redis integration was removed in 4.0.0; sites upgraded from 3.x still store its key.
		if ( isset( $settings['redis'] ) ) {
			unset( $settings['redis'] );
		}

		return $settings;
	}

	/**
	 * Remove settings for modules that are inactive.
	 *
	 * @since 3.0.1
	 *
	 * @param array $settings  Array of settings.
	 *
	 * @return array
	 */
	private function remove_disabled_options( $settings ) {
		if ( is_multisite() && is_network_admin() ) {
			$this->defaults['page_cache']['enabled'] = 'blog-admins';
		}

		if ( isset( $settings['advanced'] ) && isset( $settings['advanced']['lazy_load'] ) && isset( $settings['advanced']['lazy_load']['enabled'] ) && ! $settings['advanced']['lazy_load']['enabled'] ) {
			unset( $settings['advanced']['lazy_load']['method'] );
			unset( $settings['advanced']['lazy_load']['threshold'] );
		}

		if ( isset( $settings['page_cache'] ) && isset( $settings['page_cache']['enabled'] ) ) {
			if ( ! $settings['page_cache']['enabled'] ) {
				$settings['page_cache'] = array( 'enabled' => false );
			} elseif ( isset( $settings['page_cache']['preload'] ) && ! $settings['page_cache']['preload'] && isset( $settings['page_cache']['preload_type'] ) ) {
				unset( $settings['page_cache']['preload_type'] );
			}
		}

		return $settings;
	}

	/**
	 * Format config for display.
	 *
	 * @since 3.0.1
	 *
	 * @param array $config  Current config.
	 */
	private function format_config_to_display( $config ) {
		$display_values = array();

		if ( ! isset( $config['settings'] ) ) {
			return $display_values;
		}

		foreach ( $config['settings'] as $module => $settings ) {
			if ( ! is_array( $settings ) || empty( $settings ) ) {
				continue;
			}

			$display_values[ $module ] = array( $this->format_settings( $module, $settings ) );
		}

		foreach ( array( 'gravatar', 'rss' ) as $caching_module ) {
			if ( empty( $display_values[ $caching_module ] ) ) {
				continue;
			}

			$existing                        = isset( $display_values['page_cache'][0] ) ? $display_values['page_cache'][0] : '';
			$display_values['page_cache'][0] = $existing . $display_values[ $caching_module ][0];
			unset( $display_values[ $caching_module ] );
		}

		return $display_values;
	}

	/**
	 * Format settings array to readable values.
	 *
	 * @since 3.0.1
	 *
	 * @param string $module    Module ID.
	 * @param array  $settings  Module settings.
	 *
	 * @return string
	 */
	private function format_settings( $module, $settings ) {
		if ( 'minify' === $module ) {
			return $this->format_minify_settings( $settings );
		}

		if ( 'settings' === $module ) {
			return $this->format_general_settings( $settings );
		}

		$values = '';

		foreach ( $settings as $setting => $value ) {
			$result = $this->get_setting_description( $module, $setting, $value );

			if ( ! $result ) {
				continue;
			}

			$values .= $result;
		}

		return $values;
	}

	/**
	 * Format Asset Optimization settings for display.
	 *
	 * @since 4.0.0
	 *
	 * @param array $settings  The `minify` module's settings.
	 *
	 * @return string
	 */
	private function format_minify_settings( $settings ) {
		$values = '';

		if ( isset( $settings['combine'] ) ) {
			$values .= $this->get_setting_description( 'minify', 'combine', $settings['combine'] );
		}

		if ( isset( $settings['compress'] ) ) {
			$values .= $this->get_setting_description( 'minify', 'compress', $settings['compress'] );
		}

		$font_swap    = ! empty( $settings['font_swap'] );
		$font_display = isset( $settings['font_display_value'] ) ? $settings['font_display_value'] : 'swap';
		$swap_tab     = ! $font_swap ? 'off' : ( 'optional' === $font_display ? 'performant' : 'safe' );
		$values      .= $this->format_enum_row(
			__( 'Swap web fonts', 'wphb' ),
			array(
				'safe'       => __( 'Safe', 'wphb' ),
				'performant' => __( 'Performant', 'wphb' ),
				'off'        => __( 'Off', 'wphb' ),
			),
			$swap_tab
		);

		$preload_on   = ! empty( $settings['font_optimization'] );
		$preload_mode = isset( $settings['preload_fonts_mode'] ) ? $settings['preload_fonts_mode'] : 'automatic';
		$preload_tab  = ! $preload_on ? 'off' : ( 'manual' === $preload_mode ? 'manual' : 'auto' );
		$values      .= $this->format_enum_row(
			__( 'Preload fonts', 'wphb' ),
			array(
				'auto'   => __( 'Auto', 'wphb' ),
				'manual' => __( 'Manual', 'wphb' ),
				'off'    => __( 'Off', 'wphb' ),
			),
			$preload_tab
		);

		if ( isset( $settings['critical_css'] ) ) {
			$values .= $this->format_pro_gated_row( __( 'Critical CSS', 'wphb' ), $settings['critical_css'] );
		}

		if ( isset( $settings['delay_js'] ) ) {
			$values .= $this->format_pro_gated_row( __( 'Delay JavaScript', 'wphb' ), $settings['delay_js'] );
		}

		$compression_type = isset( $settings['compression_type'] ) ? $settings['compression_type'] : get_option( 'wphb_compression_type' );
		$values          .= $this->format_enum_row(
			__( 'Compression type', 'wphb' ),
			array(
				'br'   => __( 'Brotli', 'wphb' ),
				'gzip' => __( 'GZIP', 'wphb' ),
			),
			'br' === $compression_type ? 'br' : 'gzip'
		);

		return $values;
	}

	/**
	 * Format the "Settings" module for display.
	 *
	 * @since 4.0.0
	 *
	 * @param array $settings  The `settings` module's settings.
	 *
	 * @return string
	 */
	private function format_general_settings( $settings ) {
		$values = '';

		if ( isset( $settings['tracking'] ) ) {
			$values .= $this->get_setting_description( 'settings', 'tracking', $settings['tracking'] );
		}

		if ( isset( $settings['accessible_colors'] ) ) {
			$values .= $this->get_setting_description( 'settings', 'accessible_colors', $settings['accessible_colors'] );
		}

		if ( isset( $settings['control'] ) ) {
			$values .= $this->get_setting_description( 'settings', 'control', $settings['control'] );
		}

		if ( isset( $settings['remove_settings'] ) || isset( $settings['remove_data'] ) ) {
			$preserved    = empty( $settings['remove_settings'] ) && empty( $settings['remove_data'] );
			$value_string = $preserved ? __( 'Active', 'wphb' ) : __( 'Inactive', 'wphb' );
			$values      .= __( 'Preserve settings', 'wphb' ) . ' - ' . $value_string . "\n";
		}

		return $values;
	}

	/**
	 * Build one "Label - Value" row for a setting with more than two possible states (e.g. "Swap web fonts - Safe/Performant/Off").
	 *
	 * @since 4.0.0
	 *
	 * @param string $label     Row label.
	 * @param array  $options   Array of option key => display name.
	 * @param string $selected  The option key that's currently active.
	 *
	 * @return string
	 */
	private function format_enum_row( $label, $options, $selected ) {
		$value_string = isset( $options[ $selected ] ) ? $options[ $selected ] : reset( $options );
		return $label . ' - ' . $value_string . "\n";
	}

	/**
	 * Format a Pro-gated toggle row.
	 *
	 * @since 4.0.0
	 *
	 * @param string $label  Row label.
	 * @param bool   $value  Stored setting value.
	 *
	 * @return string
	 */
	private function format_pro_gated_row( $label, $value ) {
		if ( ! Utils::is_member() ) {
			return $label . ' - ' . __( 'Locked', 'wphb' ) . "\n";
		}

		$value_string = $value ? __( 'Active', 'wphb' ) : __( 'Inactive', 'wphb' );
		return $label . ' - ' . $value_string . "\n";
	}

	/**
	 * Get the setting name map, filling it on first use.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_descriptions(): array {
		if ( ! empty( $this->descriptions ) ) {
			return $this->descriptions;
		}

		$this->descriptions = array(
			'advanced'                => array(
				'query_string'   => __( 'Remove URL query strings', 'wphb' ),
				'emoji'          => __( 'Remove Emoji JS & CSS files', 'wphb' ),
				'cart_fragments' => __( 'Disable WooCommerce cart fragments', 'wphb' ),
			),
			'advanced_lazy_load'      => array(
				'enabled' => __( 'Lazyload comments', 'wphb' ),
			),
			'cdn'                     => array(
				'enabled' => __( 'CDN network', 'wphb' ),
			),
			'gravatar'                => array(
				'enabled' => __( 'Gravatar cache', 'wphb' ),
			),
			'minify'                  => array(
				'enabled'  => __( 'Asset optimization', 'wphb' ),
				'combine'                               => __( 'Combine assets', 'wphb' ),
				'compress'                              => __( 'Compress assets', 'wphb' ),
				'delay_js'                              => __( 'Delay JavaScript', 'wphb' ),
				'delay_js_timeout'                      => __( 'Automatic fallback timeout', 'wphb' ),
				'delay_js_exclusions'                   => __( 'Delay JavaScript exclusions', 'wphb' ),
				'delay_js_exclusion_list'               => __( 'Delay JavaScript exclusion list', 'wphb' ),
				'delay_js_files_exclusion'              => __( 'Delay JavaScript file exclusions', 'wphb' ),
				'delay_js_post_types_exclusion'         => __( 'Delay JavaScript post type exclusions', 'wphb' ),
				'delay_js_post_urls_exclusion'          => __( 'Delay JavaScript URL exclusions', 'wphb' ),
				'delay_js_plugins_themes_exclusion'     => __( 'Delay JavaScript plugin and theme exclusions', 'wphb' ),
				'delay_js_ads_tracker_exclusion'        => __( 'Delay JavaScript ad and tracker exclusions', 'wphb' ),
				'delay_js_exclude_inline_js'            => __( 'Exclude inline JavaScript from delay', 'wphb' ),
				'delay_js_keywords_advanced_view'       => __( 'Advanced View', 'wphb' ),
				'critical_css'                          => __( 'Critical CSS', 'wphb' ),
				'critical_css_type'                     => __( 'Critical CSS type', 'wphb' ),
				'critical_css_remove_type'              => __( 'Critical CSS removal type', 'wphb' ),
				'critical_css_mode'                     => __( 'CSS loading mode', 'wphb' ),
				'above_fold_load_stylesheet_method'     => __( 'Above the fold stylesheet loading', 'wphb' ),
				'critical_page_types'                   => __( 'Critical CSS page types', 'wphb' ),
				'critical_skipped_custom_post_types'    => __( 'Critical CSS skipped post types', 'wphb' ),
				'critical_css_files_exclusion'          => __( 'Critical CSS file exclusions', 'wphb' ),
				'critical_css_post_urls_exclusion'      => __( 'Critical CSS URL exclusions', 'wphb' ),
				'critical_css_plugins_themes_exclusion' => __( 'Critical CSS plugin and theme exclusions', 'wphb' ),
				'critical_css_keywords'                 => __( 'Critical CSS keywords', 'wphb' ),
				'font_optimization'                     => __( 'Preload Fonts', 'wphb' ),
				'font_swap'                             => __( 'Swap web fonts', 'wphb' ),
				'font_display_value'                    => __( 'Swap web fonts', 'wphb' ),
				'preload_fonts_mode'                    => __( 'Preload Fonts mode', 'wphb' ),
			),
			'page_cache'              => array(
				'enabled'    => __( 'Page cache', 'wphb' ),
				'cache_blog' => __( 'Allow subsites to disable page caching', 'wphb' ),
				'preload'    => __( 'Preload cache', 'wphb' ),
			),
			'page_cache_integrations' => array(
				'varnish' => __( 'Purge Varnish cache', 'wphb' ),
				'opcache' => __( 'Purge OPcache', 'wphb' ),
			),
			'page_cache_preload_type' => array(
				'home_page' => __( 'Preload (homepage)', 'wphb' ),
				'on_clear'  => __( 'Preload (any page or post)', 'wphb' ),
			),
			'performance'             => array(
				'subsite_tests' => __( 'Performance tests on subsites', 'wphb' ),
			),
			'rss'                     => array(
				'enabled' => __( 'RSS cache', 'wphb' ),
			),
			'settings'                => array(
				'accessible_colors' => __( 'High contrast mode', 'wphb' ),
				'control'           => __( 'Cache control in admin bar', 'wphb' ),
				'tracking'          => __( 'Usage tracking', 'wphb' ),
			),
			'uptime'                  => array(
				'enabled' => __( 'Uptime', 'wphb' ),
			),
		);

		return $this->descriptions;
	}

	/**
	 * Get the human readable name of a setting, without its value.
	 *
	 * The map is keyed the way `get_setting_description()` descends into it: that method
	 * synthesises `$module . '_' . $setting` on each nested array, so a dotted path is looked
	 * up against the longest name the map holds before shorter ones, and a setting the map
	 * does not name returns an empty string for the caller to fall back from.
	 *
	 * @since 4.0.0
	 *
	 * @param string $module  Module ID.
	 * @param string $setting Dotted leaf path below the module.
	 *
	 * @return string
	 */
	public function get_setting_label( $module, $setting ) {
		$descriptions = $this->get_descriptions();
		$segments     = explode( '.', (string) $setting );

		for ( $depth = count( $segments ) - 1; $depth >= 0; $depth-- ) {
			$submodule = $module;

			for ( $i = 0; $i < $depth; $i++ ) {
				$submodule .= '_' . $segments[ $i ];
			}

			$leaf = implode( '.', array_slice( $segments, $depth ) );

			if ( isset( $descriptions[ $submodule ][ $leaf ] ) ) {
				return $descriptions[ $submodule ][ $leaf ];
			}
		}

		return '';
	}

	/**
	 * Get the setting value map, filling it on first use.
	 *
	 * Keyed exactly as `$descriptions` is - submodule, then leaf - with the stored value added
	 * as the third key. The strings are reused from where the same value is already shown to
	 * the user, rather than written again here.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_value_labels(): array {
		if ( ! empty( $this->value_labels ) ) {
			return $this->value_labels;
		}

		$expiry = array(
			'1h/A3600'      => __( '1 hour', 'wphb' ),
			'3h/A10800'     => __( '3 hours', 'wphb' ),
			'4h/A14400'     => __( '4 hours', 'wphb' ),
			'5h/A18000'     => __( '5 hours', 'wphb' ),
			'6h/A21600'     => __( '6 hours', 'wphb' ),
			'12h/A43200'    => __( '12 hours', 'wphb' ),
			'16h/A57600'    => __( '16 hours', 'wphb' ),
			'20h/A72000'    => __( '20 hours', 'wphb' ),
			'1d/A86400'     => __( '1 day', 'wphb' ),
			'2d/A172800'    => __( '2 days', 'wphb' ),
			'3d/A259200'    => __( '3 days', 'wphb' ),
			'4d/A345600'    => __( '4 days', 'wphb' ),
			'5d/A432000'    => __( '5 days', 'wphb' ),
			'8d/A691200'    => __( '8 days', 'wphb' ),
			'16d/A1382400'  => __( '16 days', 'wphb' ),
			'24d/A2073600'  => __( '24 days', 'wphb' ),
			'1M/A2592000'   => __( '1 month', 'wphb' ),
			'2M/A5184000'   => __( '2 months', 'wphb' ),
			'3M/A7776000'   => __( '3 months', 'wphb' ),
			'6M/A15552000'  => __( '6 months', 'wphb' ),
			'1y/A31536000'  => __( '1 year', 'wphb' ),
		);

		$this->value_labels = array(
			'caching'    => array(
				'expiry_css'        => $expiry,
				'expiry_javascript' => $expiry,
				'expiry_media'      => $expiry,
				'expiry_images'     => $expiry,
			),
			'minify'     => array(
				'critical_css_type'                 => array(
					'remove'         => __( 'Full Page CSS Optimization (Recommended)', 'wphb' ),
					'asynchronously' => __( 'Above-the-fold CSS Optimization', 'wphb' ),
				),
				'critical_css_remove_type'          => array(
					'user_interaction_with_remove' => __( 'Load on user interaction', 'wphb' ),
					'remove_unused'                => __( 'Remove unused CSS', 'wphb' ),
				),
				'critical_css_mode'                 => array(
					'critical_css' => __( 'Critical CSS', 'wphb' ),
					'manual_css'   => __( 'CSS Above the fold', 'wphb' ),
				),
				'above_fold_load_stylesheet_method' => array(
					'load_stylesheet_on_user_interaction' => __( 'Load on user interaction', 'wphb' ),
					'load_stylesheet_async'               => __( 'Load asynchronously', 'wphb' ),
				),
				'font_display_value'                => array(
					'swap'     => __( 'Safe', 'wphb' ),
					'optional' => __( 'Performant', 'wphb' ),
				),
				'preload_fonts_mode'                => array(
					'automatic' => __( 'Automatic', 'wphb' ),
					'manual'    => __( 'Manual only', 'wphb' ),
				),
			),
			'page_cache' => array(
				'detection' => array(
					'auto'   => __( 'Auto', 'wphb' ),
					'manual' => __( 'Manual', 'wphb' ),
					'none'   => __( 'Off', 'wphb' ),
				),
			),
		);

		return $this->value_labels;
	}

	/**
	 * Get the human readable name of a setting value.
	 *
	 * Resolved against the map the same way `get_setting_label()` resolves a setting name, so
	 * a dotted path behind a nested array reaches `$module . '_' . $setting`. Values the map
	 * does not name - free text, counts, numbers, booleans, credentials - return an empty
	 * string, which is the caller's signal to fall back.
	 *
	 * @since 4.0.0
	 *
	 * @param string $module  Module ID.
	 * @param string $setting Dotted leaf path below the module.
	 * @param mixed  $value   The stored setting value.
	 *
	 * @return string
	 */
	public function get_setting_value_label( $module, $setting, $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$labels   = $this->get_value_labels();
		$segments = explode( '.', (string) $setting );

		for ( $depth = count( $segments ) - 1; $depth >= 0; $depth-- ) {
			$submodule = $module;

			for ( $i = 0; $i < $depth; $i++ ) {
				$submodule .= '_' . $segments[ $i ];
			}

			$leaf = implode( '.', array_slice( $segments, $depth ) );

			if ( isset( $labels[ $submodule ][ $leaf ][ $value ] ) ) {
				return $labels[ $submodule ][ $leaf ][ $value ];
			}
		}

		return '';
	}

	/**
	 * Format a setting value to a readable string.
	 *
	 * @since 3.0.1
	 *
	 * @param string      $module   Module ID.
	 * @param string      $setting  Setting name.
	 * @param bool|string $value    Setting value.
	 *
	 * @return string
	 */
	private function get_setting_description( $module, $setting, $value ) {
		$descriptions = $this->get_descriptions();

		// Overwrites for non-common values.
		if ( 'page_cache' === $module ) {
			if ( 'enabled' === $setting && 'blog-admins' === $value ) {
				$value = true;
			}

			if ( 'cache_blog' === $setting ) {
				$cache_status = Settings::get_setting( 'enabled', 'page_cache' );
				$value_string = 'blog-admins' === $cache_status ? __( 'Active', 'wphb' ) : __( 'Inactive', 'wphb' );
				return $descriptions[ $module ][ $setting ] . ' - ' . $value_string . "\n";
			}

			// File change detection: Auto/Manual/Off, from a single
			// 'auto'/'manual'/'none' field.
			if ( 'detection' === $setting ) {
				return $this->format_enum_row(
					__( 'File change detection', 'wphb' ),
					array(
						'auto'   => __( 'Auto', 'wphb' ),
						'manual' => __( 'Manual', 'wphb' ),
						'none'   => __( 'Off', 'wphb' ),
					),
					$value
				);
			}
		}

		if ( 'cdn' === $module && 'enabled' === $setting ) {
			return $this->format_pro_gated_row( __( 'CDN network', 'wphb' ), $value );
		}

		// Loop over early.
		if ( is_array( $value ) ) {
			$submodule = $module . '_' . $setting;

			$strings = '';
			foreach ( $value as $el => $val ) {
				$strings .= $this->get_setting_description( $submodule, $el, $val );
			}

			return $strings;
		}

		// No setting and not array - exit.
		if ( ! isset( $descriptions[ $module ] ) || ! isset( $descriptions[ $module ][ $setting ] ) ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			$value_string = $value ? __( 'Active', 'wphb' ) : __( 'Inactive', 'wphb' );
			return $descriptions[ $module ][ $setting ] . ' - ' . $value_string . "\n";
		}

		// Settings in page caching module.
		if ( '1' === $value || '0' === $value ) {
			$value_string = '1' === $value ? __( 'Active', 'wphb' ) : __( 'Inactive', 'wphb' );
			return $descriptions[ $module ][ $setting ] . ' - ' . $value_string . "\n";
		}

		if ( is_string( $value ) ) {
			return $descriptions[ $module ][ $setting ] . ' - ' . ucfirst( $value ) . "\n";
		}

		return '';
	}

}
