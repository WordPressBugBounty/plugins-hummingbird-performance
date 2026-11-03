<?php
/**
 * Safe Mode staged settings: Font Optimization card.
 *
 * One of the four cards the Asset Optimization page renders, grouped here as `minify_fonts`.
 * Each card keeps working while the others are off, so each is staged and listed on its own.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Every control here stands on its own - the card renders whether or not the module toggle
	// is on, so none of them is gated on `enabled`.
	'font_swap'          => array( 'label' => __( 'Swap web fonts', 'wphb' ) ),
	'font_display_value' => array(),
	'font_optimization'  => array(),
	'preload_fonts_mode' => array(),
	'preload_fonts'      => array( 'label' => __( 'Preload font files', 'wphb' ) ),
);
