<?php
/**
 * Services page.
 *
 * @since 3.9.0
 * @package Hummingbird\Admin\Pages
 */

namespace Hummingbird\Admin\Pages;

use Hummingbird\Admin\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Services
 */
class Services extends Page {

	/**
	 * Register meta boxes.
	 *
	 * @since 3.9.0
	 */
	public function register_meta_boxes() {
		$this->add_meta_box(
			'services/general',
			'',
			null,
			null,
			null,
			'main',
			array(
				'box_content_class' => 'sui-box sui-message',
			)
		);
	}

	/**
	 * Renders the template header.
	 */
	protected function render_header() {
		?>
		<div class="sui-header">
			<h1 class="sui-header-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		</div>
		<?php
	}
}
