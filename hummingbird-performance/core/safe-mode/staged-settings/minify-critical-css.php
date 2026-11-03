<?php
/**
 * Safe Mode staged settings: Critical CSS card.
 *
 * One of the four cards the Asset Optimization page renders, grouped here as
 * `minify_critical_css`. Each card keeps working while the others are off, so each is staged and
 * listed on its own.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	// The card's own toggle. Listed on its own - the page renders it whether or not the module
	// toggle is on, so it is not gated on `enabled`.
	'critical_css'                 => array(),
	// The controls below are inert while the card's own toggle is off, so they depend on
	// `critical_css` rather than on the module toggle alone.
	'critical_css_type'            => array( 'label' => __( 'CSS loading mode', 'wphb' ), 'parent' => 'critical_css' ),
	'critical_css_remove_type'     => array( 'parent' => 'critical_css' ),
	'above_fold_load_stylesheet_method' => array( 'parent' => 'critical_css' ),
	// Stored under six keys, listed below.
	'critical_css_files_exclusion' => array(
		'label'  => __( 'Critical CSS exclusions & inclusions', 'wphb' ),
		'parent' => 'critical_css',
		'stored' => array(
			'critical_page_types',
			'critical_skipped_custom_post_types',
			'critical_css_files_exclusion',
			'critical_css_post_urls_exclusion',
			'critical_css_plugins_themes_exclusion',
			'critical_css_keywords',
		),
	),
);
