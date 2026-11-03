<?php
/**
 * Safe Mode staged settings: RSS caching module.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	'enabled'  => array( 'label' => __( 'RSS caching', 'wphb' ) ),
	'duration' => array(
		'label'  => __( 'Expiry time', 'wphb' ),
		'parent' => 'enabled',
	),
);
