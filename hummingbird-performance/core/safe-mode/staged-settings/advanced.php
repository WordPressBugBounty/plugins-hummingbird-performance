<?php
/**
 * Safe Mode staged settings: Advanced settings module.
 *
 * Every `parent` here except `lazy_load.*` is omitted because `Advanced` has no enable flag
 * that gates the rest of the module.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	'viewport_meta'  => array( 'label' => __( 'Viewport meta optimization', 'wphb' ) ),
	'query_string'   => array(),
	'emoji'          => array( 'label' => __( 'Remove default emoji files', 'wphb' ) ),
	'post_revisions' => array( 'label' => __( 'Post revisions', 'wphb' ) ),
	'cart_fragments' => array( 'label' => __( 'WooCommerce cart fragments', 'wphb' ) ),
	'lazy_load'      => array(
		// The toggle and its method render as one control, so the label sits on the toggle.
		'enabled'   => array( 'label' => __( 'Lazy load comments', 'wphb' ) ),
		// `method` is gated on the toggle like every other child, so a cleared toggle drops it
		// from both snapshots and the two stay aligned. The inventory reads the live method
		// from the raw settings when it has to describe a toggle that is still on.
		'method'    => array( 'parent' => 'lazy_load.enabled' ),
		'preload'   => array( 'label' => __( 'Preload comments', 'wphb' ), 'parent' => 'lazy_load.enabled' ),
		'threshold' => array( 'label' => __( 'Comment threshold', 'wphb' ), 'parent' => 'lazy_load.enabled' ),
	),
	'prefetch'       => array( 'label' => __( 'DNS prefetch', 'wphb' ) ),
	'preconnect'     => array( 'label' => __( 'Preconnect', 'wphb' ) ),
);
