<?php
/**
 * Setting admin page.
 *
 * @package Hummingbird\Admin\Pages
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;
use Hummingbird\Admin\Ajax\Notices;
use Hummingbird\Core\Configs;
use Hummingbird\Core\Settings as Settings_Module;
use Hummingbird\Core\Utils;
use Hummingbird\WP_Hummingbird;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings extends Page
 */
class Settings extends Page {
	/**
	 * Data localized for the new React Settings page (`wphbPage`).
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		if ( $this->is_subsite() ) {
			$minify_options = Utils::get_module( 'minify' )->get_options();
			$ao_available   = true === $minify_options['enabled']
				|| ( 'super-admins' === $minify_options['enabled'] && is_super_admin() );

			return array(
				'otherSettings' => array(
					'view'                    => Settings_Module::get_setting( 'view', 'minify' ),
					'devModeUrl'              => admin_url( 'admin.php?page=wphb-minification&view=dev-mode' ),
					'canUseAssetOptimization' => $ao_available,
					'showDevModeModal'        => (bool) get_option( 'wphb-minification-show-advanced_modal' ),
					'showAutomaticModeModal'  => (bool) get_option( 'wphb-minification-show-config_modal' ),
					'logDownloads'            => $this->get_log_downloads(),
				),
			);
		}

		$configs    = new Configs();
		$settings   = Settings_Module::get_settings( 'settings' );
		$page_cache = Utils::get_module( 'page_cache' )->get_settings();

		$release_timestamp = defined( 'WPHB_RELEASE_DATE' ) ? strtotime( WPHB_RELEASE_DATE ) : false;
		$log_downloads     = $this->get_log_downloads();
		$dev_mode_url      = add_query_arg( 'view', 'dev-mode', Utils::get_admin_menu_url( 'minification' ) );

		return array(
			'configs'                => $configs->get_stored_configs(),
			'configTipsDismissed'    => Notices::is_dismissed( 'configs-tips-toast' ),
			'configWelcomeDismissed' => Notices::is_dismissed( 'configs-welcome-modal' ),
			'otherSettings'   => array(
				'view'                   => Settings_Module::get_setting( 'view', 'minify' ),
				'devModeUrl'             => $dev_mode_url,
				'showDevModeModal'       => (bool) get_option( 'wphb-minification-show-advanced_modal' ),
				'showAutomaticModeModal' => (bool) get_option( 'wphb-minification-show-config_modal' ),
				'accessibleColors'       => (bool) $settings['accessible_colors'],
				'tracking'               => (bool) $settings['tracking'],
				'removeSettings'         => (bool) $settings['remove_settings'],
				'removeData'             => (bool) $settings['remove_data'],
				'control'                => $settings['control'],
				'controlModules'         => Utils::get_cache_control_modules(),
				'debugLog'               => (bool) $page_cache['settings']['debug_log'] || (bool) Settings_Module::get_setting( 'log', 'minify' ),
				'logDownloads'           => $log_downloads,
				'noticeDismissed'        => Notices::is_dismissed( 'debug-log-info' ),
				'cacheModules'           => Utils::get_cache_modules(),
				'version'                => WPHB_VERSION,
				'releaseDate'            => $release_timestamp ? date_i18n( get_option( 'date_format' ), $release_timestamp ) : '',
			),
			// Network admin only -- the subsite controls behind the Permissions.
			'permissions'            => is_network_admin() ? $this->get_permissions_data() : null,
		);
	}

	/**
	 * Whether this is a subsite admin screen on a network.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	private function is_subsite() {
		return is_multisite() && ! is_network_admin();
	}

	/**
	 * Debug logs this screen is allowed to offer.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_log_downloads() {
		$logger = WP_Hummingbird::get_instance()->core->logger;

		if ( ! $logger ) {
			return array();
		}

		$labels = array(
			'page_cache' => __( 'Page caching log', 'wphb' ),
			'minify'     => __( 'Asset optimization log', 'wphb' ),
		);

		$is_subsite = $this->is_subsite();
		$downloads  = array();

		foreach ( $logger->get_existing_logs() as $log_module ) {
			if ( is_multisite() ) {
				if ( 'minify' === $log_module && ! $is_subsite ) {
					continue;
				}

				if ( 'page_cache' === $log_module && $is_subsite ) {
					continue;
				}
			}

			$downloads[] = array(
				'id'    => $log_module,
				'label' => $labels[ $log_module ],
				'url'   => add_query_arg(
					array(
						'logs'     => 'download',
						'module'   => $log_module,
						'_wpnonce' => wp_create_nonce( 'wphb-log-action' ),
					)
				),
			);
		}

		return $downloads;
	}

	/**
	 * Subsite controls for the Permissions tab.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	private function get_permissions_data() {
		$page_cache         = Utils::get_module( 'page_cache' );
		$page_cache_options = $page_cache->get_options();

		return array(
			'assetOptimization' => array(
				'enabled'  => Settings_Module::get_setting( 'enabled', 'minify' ),
				'filePath' => Settings_Module::get_setting( 'file_path', 'minify' ),
			),
			'pageCaching'       => array(
				'subsitesCanDisable' => isset( $page_cache_options['enabled'] ) && 'blog-admins' === $page_cache_options['enabled'],
				'moduleActive'       => isset( $page_cache_options['enabled'] ) && false !== $page_cache_options['enabled'],
			),
		);
	}
}
