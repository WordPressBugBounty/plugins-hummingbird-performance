<?php
/**
 * Dashboard page
 *
 * @package Hummingbird
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->do_meta_boxes( 'main' ); ?>

<div class="sui-row">
	<div class="sui-col-lg-6">
		<?php $this->do_meta_boxes( 'box-dashboard-left' ); ?>
		<?php if ( ! is_multisite() || is_network_admin() ) : ?>
			<div id="wphb-dashboard-configs"></div>
		<?php endif; ?>
	</div>
	<div class="sui-col-lg-6"><?php $this->do_meta_boxes( 'box-dashboard-right' ); ?></div>
</div>

<?php
$this->modal( 'clear-cache' );

?>

<script>
	jQuery( document).ready( function () {
		window.WPHB_Admin.getModule( 'dashboard' );
	});
</script>
