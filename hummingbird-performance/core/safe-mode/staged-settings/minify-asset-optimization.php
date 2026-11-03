<?php
/**
 * Safe Mode staged settings: Asset Optimization card.
 *
 * One of the four cards the Asset Optimization page renders. Each card is staged and listed on
 * its own, because each keeps working while the others are off, so the module key `minify` is
 * shared but every card is its own group in the inventory.
 *
 * `view` is a Settings-page control rather than an Asset Optimization one, so it is not staged
 * here. It is grouped separately, as `settings`.
 *
 * `parent_is` on the card's controls: the Dev Mode tab dims the whole card, so none of them can be
 * set while the mode is on. `view` itself is never gated that way - it is the row that reports the
 * switch.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	// The module toggle is represented by the Subsites control in the Permissions screen.
	// That control only exists on multisite installs, so keep the setting staged but hide its
	// inventory row on single-site installs.
	'enabled'      => array(
		'label'  => __( 'Subsites', 'wphb' ),
		'hidden' => ! is_multisite(),
	),
	// A Settings-page control that renders in this card's header, so it is listed here rather
	// than as a group of its own. Never gated on Dev Mode - it is the row that reports that
	// switch - and not on `enabled` either, since the mode stands whether the module is on.
	'view'         => array(
		'label'  => __( 'Dev Mode', 'wphb' ),
		'values' => array(
			'basic'    => __( 'Off', 'wphb' ),
			'advanced' => __( 'On', 'wphb' ),
		),
	),
	// The Dev Mode tab dims the whole card, so nothing in it can be set while the mode is on.
	// `parent_is` carries that; `parent` carries the card's own toggle where a control needs it.
	'minify'       => array( 'parent_is' => array( 'view' => 'advanced' ) ),
	'compress'     => array( 'parent_is' => array( 'view' => 'advanced' ) ),
	'combine'      => array( 'parent_is' => array( 'view' => 'advanced' ) ),
	'do_assets'    => array(
		'styles'  => array( 'label' => __( 'Optimize CSS assets', 'wphb' ), 'parent' => 'enabled', 'parent_is' => array( 'view' => 'advanced' ) ),
		'scripts' => array( 'label' => __( 'Optimize JavaScript assets', 'wphb' ), 'parent' => 'enabled', 'parent_is' => array( 'view' => 'advanced' ) ),
		// Font assets are also only processed alongside combining: the save handler stores
		// this as `fonts && combine`, so the page offers the file type only while Combine is
		// on. Either condition hides the row - it is not a state the page can set.
		'fonts'   => array( 'label' => __( 'Optimize font assets', 'wphb' ), 'parent' => 'combine', 'parent_is' => array( 'view' => 'advanced' ) ),
	),
	// The Exclusions control writes `dont_minify`. `dont_combine` is not listed beside it: the
	// save handler either mirrors `dont_minify` into it while Combine is on, or fills it with
	// every handle while Combine is off, so it never carries a choice of its own.
	'dont_minify'  => array(
		'label'     => __( 'Combine & compress exclusions', 'wphb' ),
		'parent'    => 'enabled',
		'parent_is' => array( 'view' => 'advanced' ),
		'stored'    => array( 'dont_minify' ),
	),
);
