<?php
/**
 * Safe Mode staged settings: Delay JavaScript card.
 *
 * One of the four cards the Asset Optimization page renders, grouped here as
 * `minify_delay_js`. Each card keeps working while the others are off, so each is staged and
 * listed on its own.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	// The card's own toggle. Listed on its own - the page renders it whether or not the module
	// toggle is on, so it is not gated on `enabled`.
	'delay_js'                   => array(),
	// The controls below are inert while the card's own toggle is off, so they depend on
	// `delay_js` rather than on the module toggle alone.
	'delay_js_timeout'           => array( 'label' => __( 'Delay JavaScript timeout', 'wphb' ), 'parent' => 'delay_js' ),
	'delay_js_exclude_inline_js' => array( 'parent' => 'delay_js' ),
	// Stored under seven keys, listed below.
	'delay_js_exclusions'        => array(
		'label'  => __( 'Exclusions', 'wphb' ),
		'parent' => 'delay_js',
		'stored' => array(
			'delay_js_exclusions',
			'delay_js_exclusion_list',
			'delay_js_files_exclusion',
			'delay_js_post_types_exclusion',
			'delay_js_post_urls_exclusion',
			'delay_js_plugins_themes_exclusion',
			'delay_js_ads_tracker_exclusion',
		),
	),
);
