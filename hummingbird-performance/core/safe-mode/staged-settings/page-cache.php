<?php
/**
 * Safe Mode staged settings: Page Caching module.
 *
 * `enabled` gates almost everything, including the rows nested under `settings` and
 * `integrations`. Those nested groups therefore carry `parent` on their children, not on the
 * group itself, because the group key is not a setting.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	'enabled'              => array(),
	'cache_method'         => array( 'label' => __( 'Cache method', 'wphb' ) ),
	'preload'              => array( 'label' => __( 'Preload cache', 'wphb' ), 'parent' => 'enabled' ),
	'preload_type'         => array( 'parent' => 'enabled' ),
	'cache_lifetime'       => array( 'label' => __( 'Cache lifetime', 'wphb' ) ),
	'page_types'           => array( 'label' => __( 'Page types', 'wphb' ), 'parent' => 'enabled' ),
	'settings'             => array(
		'mobile'           => array( 'label' => __( 'Cache on mobile devices', 'wphb' ), 'parent' => 'enabled' ),
		'logged_in'        => array( 'label' => __( 'Cache for logged-in users', 'wphb' ), 'parent' => 'enabled' ),
		'url_queries'      => array( 'label' => __( 'Cache URL queries', 'wphb' ), 'parent' => 'enabled' ),
		'cache_headers'    => array( 'label' => __( 'Cache HTTP headers', 'wphb' ), 'parent' => 'enabled' ),
		'cache_404'        => array( 'label' => __( 'Cache 404 requests', 'wphb' ), 'parent' => 'enabled' ),
		'cache_identifier' => array( 'label' => __( 'Identify cached pages', 'wphb' ), 'parent' => 'enabled' ),
		'compress'         => array( 'label' => __( 'Serve compressed cached files', 'wphb' ), 'parent' => 'enabled' ),
		'clear_update'     => array( 'label' => __( 'Clear full cache after a post or page update', 'wphb' ), 'parent' => 'enabled' ),
		'comment_clear'    => array( 'label' => __( 'Clear cache after a comment is posted', 'wphb' ), 'parent' => 'enabled' ),
	),
	'detection'            => array(
		'label'  => __( 'File change detection', 'wphb' ),
		'parent' => 'enabled',
		'values' => array(
			'auto'   => __( 'Automatic', 'wphb' ),
			'manual' => __( 'Notify me', 'wphb' ),
			'none'   => __( 'Off', 'wphb' ),
		),
	),
	'clear_interval'       => array( 'label' => __( 'Clear cache at set intervals', 'wphb' ), 'parent' => 'enabled' ),
	'integrations'         => array(
		'varnish' => array( 'label' => __( 'Purge Varnish cache', 'wphb' ), 'parent' => 'enabled' ),
		'opcache' => array( 'label' => __( 'Purge OPcache', 'wphb' ), 'parent' => 'enabled' ),
	),
	'exclusions'           => array( 'label' => __( 'Exclusions', 'wphb' ), 'parent' => 'enabled' ),
);
