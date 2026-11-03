<?php
/**
 * Safe Mode staged settings: CDN module.
 *
 * `excluded_files` has no parent: the exclusion list stays meaningful whether or not the CDN is
 * switched on, and the previous inventory listed it in both states.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	'enabled'        => array( 'label' => __( 'CDN network', 'wphb' ) ),
	'excluded_files' => array( 'label' => __( 'Exclusions', 'wphb' ) ),
);
