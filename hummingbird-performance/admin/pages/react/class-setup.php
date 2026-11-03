<?php
/**
 * React based Setup Wizard page.
 *
 * @since 3.3.1
 * @package Hummingbird\Admin\Pages\React
 */

namespace Hummingbird\Admin\Pages\React;

use Hummingbird\Admin\Page;
use Hummingbird\Core\Modules\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Setup extends Page.
 *
 * Needed so Admin can register the `wphb-setup` submenu; Page renders
 * the React wizard itself.
 */
class Setup extends Page {
	/**
	 * Data localized for the React wizard (`wphbPage.setupWizard`).
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_ui_page_data() {
		$cooldown_ends_at = Performance::cooldown_ends_at();

		return array(
			'setupWizard' => array(
				'cooldownEndsAt' => $cooldown_ends_at >= time() ? $cooldown_ends_at : null,
			),
		);
	}
}
