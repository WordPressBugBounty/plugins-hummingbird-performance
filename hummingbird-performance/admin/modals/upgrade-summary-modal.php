<?php
/**
 * Upgrade highlight modal.
 *
 * @since 2.6.0
 * @package Hummingbird
 */

use Hummingbird\Core\Hub_Connector;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Caching\Fast_CGI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-md">
	<div
			role="dialog"
			id="upgrade-summary-modal"
			class="sui-modal-content"
			aria-modal="true"
			aria-labelledby="upgrade-summary-modal-title"
	>
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60">
				<?php if ( ! apply_filters( 'wpmudev_branding_hide_branding', false ) ) : ?>
					<figure class="sui-box-banner" aria-hidden="true">
						<img src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/upgrade-summary-bg.png' ); ?>" alt=""
							srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/upgrade-summary-bg.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/upgrade-summary-bg@2x.png' ); ?> 2x">
					</figure>
				<?php endif; ?>

				<button class="sui-button-icon sui-button-float--right" data-track-action="closed" onclick="window.WPHB_Admin.dashboard.hideUpgradeSummary( this )">
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text"><?php esc_attr_e( 'Close this modal', 'wphb' ); ?></span>
				</button>

				<h3 id="upgrade-summary-modal-title" class="sui-box-title sui-lg" style="white-space: inherit">
					<?php esc_html_e( 'Smarter optimization, smoother workflow', 'wphb' ); ?>
				</h3>
			</div>

			<div class="sui-box-body sui-spacing-top--20 sui-spacing-bottom--30">
				<div class="wphb-upgrade-feature">
					<p class="wphb-upgrade-item-desc" style="text-align: center">
						<?php
						esc_html_e( 'We\'re making it easier than ever to optimize your site without slowing down your workflow. Asset Optimization Scan now runs in the background while you continue working. Dev mode gives you control of advanced tweaks, and Gzip compression joins the optimization party - all in one streamlined workflow!', 'wphb' );
						?>
					</p>
				</div>
				<div class="wphb-upgrade-feature">
					<?php
						$hb_button      = esc_html__( 'Check it out', 'wphb' );
						$hb_button_link = Utils::get_admin_menu_url( 'minification' );
					?>
				</div>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center sui-spacing-bottom--50">
				<a href="<?php echo esc_url( $hb_button_link ); ?>" data-track-action="cta_clicked"
					class="sui-button sui-button-blue"
					onclick="window.WPHB_Admin.dashboard.hideUpgradeSummary( this )">
					<span class="sui-button-text-default">
						<?php echo esc_html( $hb_button ); ?>
					</span>
				</a>
			</div>
		</div>
	</div>
</div>
