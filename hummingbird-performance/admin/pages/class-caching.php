<?php
/**
 * Caching pages: page caching, browser caching, gravatar caching, rss caching, settings for page caching.
 *
 * @package Hummingbird
 *
 * @since 1.9.0
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;
use Hummingbird\Core\Module_Server;
use Hummingbird\Core\Modules\Page_Cache;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Caching\Fast_CGI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Caching
 *
 * @property array tabs
 */
class Caching extends Page {

	use \Hummingbird\Core\Traits\WPConfig;

	/**
	 * Data localized for the new React Caching page (`wphbPage`).
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		$page_cache = Utils::get_module( 'page_cache' );
		$gravatar   = Utils::get_module( 'gravatar' );
		$rss        = Utils::get_module( 'rss' );
		$pc_options = $page_cache->get_options();
		$rss_opts   = $rss->get_options();

		// Page caching can hit a dynamic error.
		$page_cache_error = is_wp_error( $page_cache->error ) ? $page_cache->error->get_error_message() : false;

		$file_count = Page_Cache::get_cached_pages_count();

		// Dynamic server-side data consumed by the React page.
		$raw_cpts          = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			),
			'objects'
		);
		$custom_post_types = array_values(
			array_map(
				static function ( $cpt ) {
					return array(
						'name'  => $cpt->name,
						'label' => $cpt->label,
					);
				},
				$raw_cpts
			)
		);

		$gzip             = Utils::get_module( 'gzip' )->get_analysis_data();
		$opcache          = \Hummingbird\Core\Integration\Opcache::get_instance();
		$browser_status   = Utils::get_module( 'caching' )->get_analysis_data();
		$cloudflare_setup = Utils::get_module( 'cloudflare' )->is_connected() && Utils::get_module( 'cloudflare' )->is_zone_selected();

		if ( $cloudflare_setup ) {
			$browser_status = array_fill_keys( array_keys( $browser_status ), Utils::get_module( 'cloudflare' )->get_caching_expiration( true ) );
		}

		$gravatar_error = is_wp_error( $gravatar->error ) ? $gravatar->error->get_error_message() : false;

		$caching_settings   = Settings::get_settings( 'caching' );
		$fast_cgi_settings  = Fast_CGI::wphb_fast_cgi_data( true );
		$static_bypass_urls = isset( $fast_cgi_settings->bypass_urls ) && is_array( $fast_cgi_settings->bypass_urls ) ? $fast_cgi_settings->bypass_urls : array();
		$browser_expires    = array(
			'CSS'        => isset( $caching_settings['expiry_css'] ) ? $caching_settings['expiry_css'] : '1y/A31536000',
			'JavaScript' => isset( $caching_settings['expiry_javascript'] ) ? $caching_settings['expiry_javascript'] : '1y/A31536000',
			'Media'      => isset( $caching_settings['expiry_media'] ) ? $caching_settings['expiry_media'] : '1y/A31536000',
			'Images'     => isset( $caching_settings['expiry_images'] ) ? $caching_settings['expiry_images'] : '1y/A31536000',
		);

		return array(
			'pageCache' => array_merge(
				$page_cache->get_settings(),
				array(
					'enabled'            => $page_cache->is_active(),
					'error'              => $page_cache_error,
					'cachedFiles'        => $file_count,
					'adminsCanDisable'   => isset( $pc_options['enabled'] ) && 'blog-admins' === $pc_options['enabled'],
					'detection'          => isset( $pc_options['detection'] ) ? $pc_options['detection'] : 'auto',
					'preload'            => isset( $pc_options['preload'] ) ? (bool) $pc_options['preload'] : false,
					'preloadType'        => isset( $pc_options['preload_type'] ) ? $pc_options['preload_type'] : array(
						'home_page' => true,
						'on_clear'  => false,
					),
					'integrations'       => isset( $pc_options['integrations'] ) ? $pc_options['integrations'] : array(
						'varnish' => false,
						'opcache' => false,
					),
					'customPostTypes'    => $custom_post_types,
					'blogIsFrontpage'    => 'posts' === get_option( 'show_on_front' ) && ! is_multisite(),
					'isWpmuHosting'      => Utils::is_site_hosted_on_wpmudev(),
					'opcacheEnabled'     => $opcache->is_enabled(),
					'canCompress'        => ! isset( $gzip['HTML'] ) || ! $gzip['HTML'],
					'cacheMethod'        => Fast_CGI::is_fast_cgi_enabled() ? 'static' : 'local',
					'isFastCgiSupported' => Fast_CGI::is_fast_cgi_supported(),
					'staticCacheTtl'     => isset( $fast_cgi_settings->ttl ) ? (int) $fast_cgi_settings->ttl : 480,
					'staticBypassUrls'   => $static_bypass_urls,
					'staticQueryParams'  => isset( $fast_cgi_settings->query_params ) && is_array( $fast_cgi_settings->query_params ) ? $fast_cgi_settings->query_params : array(),
				)
			),
			'others'    => array(
				'browser'    => array(
					'detectedServer' => Module_Server::get_server_type(),
					'status'         => $browser_status,
					'expires'        => $browser_expires,
					'human'          => array_map( array( 'Hummingbird\\Core\\Utils', 'human_read_time_diff' ), $browser_status ),
				),
				'gravatar'   => array(
					'enabled' => $gravatar_error ? false : $gravatar->is_active(),
					'error'   => $gravatar_error,
				),
				'rss'        => array(
					'enabled'  => $rss->is_active(),
					'duration' => isset( $rss_opts['duration'] ) ? (int) $rss_opts['duration'] : 3600,
				),
				'cloudflare' => Utils::get_module( 'cloudflare' )->get_cf_ui_data(),

			),
		);
	}

	/**
	 * Execute an action for specified module.
	 *
	 * Action will execute if:
	 * - Both action and module vars are defined;
	 * - Action is available as a methods in a selected module.
	 *
	 * Used actions: enable, disable, disconnect.
	 * Supported modules: page_cache, caching, cloudflare, gravatar, rss.
	 *
	 * @since 1.9.0  Moved here from on_load().
	 */
	public function trigger_load_action() {
		parent::trigger_load_action();

		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['module'] ) ) { // Input var ok.
			return;
		}

		check_admin_referer( 'wphb-caching-actions' );
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // Input var ok.
		$module = sanitize_text_field( wp_unslash( $_GET['module'] ) ); // Input var ok.

		// If unsupported module - exit.
		$mod = Utils::get_module( $module );

		// Allow only supported actions.
		if ( ! $mod || ! in_array( $action, array( 'enable', 'disable', 'disconnect' ), true ) ) {
			return;
		}

		if ( method_exists( $mod, $action ) ) {
			call_user_func( array( $mod, $action ) );
		}

		$redirect_url = add_query_arg( array( 'view' => $module ), Utils::get_admin_menu_url( 'caching' ) );
		wp_safe_redirect( $redirect_url );
	}
}
