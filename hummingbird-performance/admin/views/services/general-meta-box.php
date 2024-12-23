<?php
/**
 * Services page meta box.
 *
 * @package Hummingbird
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<?php if ( ! apply_filters( 'wpmudev_branding_hide_branding', false ) ) : ?>
	<img class="sui-image" aria-hidden="true" alt=""
		src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/service-graphic@1x.png' ); ?>"
		srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/service-graphic@1x.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/service-graphic@2x.png' ); ?> 2x" />
<?php endif; ?>

<div class="sui-message-content">
	<span class="sui-tag"><?php esc_html_e( 'Site Speed Optimization', 'wphb' ); ?></span>
	<h1 style="margin: 12px 0 10px 0;"><?php esc_html_e( 'Want an Effortless Speed Boost?', 'wphb' ); ?></h1>
	<p>
		<?php esc_html_e( 'Optimize your site effortlessly with our Speed Optimization experts. Achieve top scores quickly, saving you time and ensuring peak performance for you or your clients. Enjoy faster load times, enhanced site performance, and a superior user experience.', 'wphb' ); ?>
	</p>

	<a href="<?php echo esc_url( Utils::get_link( 'expert-services', 'hummingbird_services_submenu_upsell' ) ); ?>" target="_blank" class="sui-button sui-button-blue" onclick="window.wphbMixPanel.trackHBUpsell( 'expert_services_performance', 'submenu', 'cta_clicked', this.href, 'expert_services_upsell' );">
		<?php esc_html_e( 'Learn More', 'wphb' ); ?>
		<span class="sui-icon-open-new-window" aria-hidden="true" style="margin-left: -3px;"></span>
	</a>
</div>
<script>
	jQuery(document).ready( function() {
		window.wphbMixPanel.trackHBUpsell( 'expert_services_performance', 'submenu', 'page_viewed', "<?php echo esc_url( Utils::get_link( 'expert-services', 'hummingbird_services_submenu_upsell' ) ); ?>", 'expert_services_upsell' );
	} );
</script>
