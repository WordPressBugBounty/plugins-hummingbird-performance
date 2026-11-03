<?php
/**
 * CDN admin page.
 *
 * @package Hummingbird\Admin\Pages
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cdn
 */
class Cdn extends Page {

	/**
	 * Data localized for the React CDN page (`wphbPage`).
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		return array(
			'cdnDocLink' => Utils::get_documentation_url( 'wphb-minification' ),
			'cdn'        => Utils::get_module( 'cdn' )->get_react_settings(),
		);
	}
}
