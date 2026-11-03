<?php
/**
 * Hummingbird PRO upgrade page.
 *
 * @since 2.0.1
 * @package Hummingbird\Admin\Pages
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Upgrade extends Page.
 *
 * Needed so Admin can register the `wphb-upgrade` submenu; Page builds the
 * "Upgrade / Pro" menu entry, which links straight to the upsell page.
 */
class Upgrade extends Page {

}
