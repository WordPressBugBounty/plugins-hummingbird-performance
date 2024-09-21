<?php
/**
 * Render page.
 *
 * @package Hummingbird
 *
 * @var $this Page
 *
 * @var array|wp_error $report  Report, set in render_inner_content().
 */

use Hummingbird\Admin\Page;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $this->has_meta_boxes( 'summary' ) ) {
	$this->do_meta_boxes( 'summary' );
} ?>

<?php if ( $report ) : ?>
	<?php if ( Utils::is_ao_processing() ) { ?>
		<div class="sui-notice sui-notice-yellow">
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Asset optimization is still in progress. We recommend that you wait until the process completes before you run a performance test in order to get the most accurate scores.', 'wphb' ); ?></p>
				</div>
			</div>
		</div>
	<?php } ?>
	<?php $this->show_tabs_flat(); ?>
	<?php $this->do_meta_boxes( $this->get_current_tab() ); ?>
<?php else : ?>
	<?php $this->do_meta_boxes(); ?>
<?php endif; ?>

<script>
	jQuery(document).ready( function() {
		window.WPHB_Admin.getModule( 'performance' );
	});
</script>
