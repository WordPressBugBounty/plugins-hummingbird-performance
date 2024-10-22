<?php
/**
 * Upgrade highlight modal.
 *
 * @since 2.6.0
 * @package Hummingbird
 */

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
					<?php esc_html_e( 'Above-the-Fold Critical CSS: Now Faster Than Ever!', 'wphb' ); ?>
				</h3>
			</div>

			<div class="sui-box-body sui-spacing-top--20 sui-spacing-bottom--30">
				<div class="wphb-upgrade-feature">
					<p class="wphb-upgrade-item-desc" style="text-align: center">
						<?php
						echo Utils::is_member() ? esc_html__( 'Experience lightning-fast page loads and higher PageSpeed scores by deferring below-the-fold CSS until user interaction. Switch to the enhanced Above-the-Fold CSS handling for a smoother, faster experience on both desktop and mobile!', 'wphb' ) : esc_html__( 'Experience lightning-fast page loads and higher PageSpeed scores by deferring below-the-fold CSS until user interaction. Upgrade your plugin to unlock a smoother, faster experience on both desktop and mobile!', 'wphb' );
						?>
					</p>
				</div>
				<div class="wphb-upgrade-feature">
					<?php
					if ( ! Utils::is_member() ) {
						$hb_button      = esc_html__( 'UNLOCK NOW -  80% OFF', 'wphb' );
						$hb_button_link = Utils::get_link( 'plugin', 'welcome_modal_critcalcss_abovefold' );
					} elseif ( is_multisite() ) {
						$hb_button      = esc_html__( 'Got it', 'wphb' );
						$hb_button_link = '#';
						printf( /* translators: %1$s - opening p tag, %2$s - opening <strong> tag, %3$s - closing <strong> tag, %4$s - closing p tag */
							esc_html__( '%1$sTo enable this feature, go to %2$sAsset Optimization > Extra Optimization%3$s.%4$s', 'wphb' ),
							'<p class="wphb-upgrade-item-desc" style="text-align: center;margin-top: 10px">',
							'<strong>',
							'</strong>',
							'</p>'
						);
					} else {
						$hb_button      = esc_html__( 'TRY IT NOW', 'wphb' );
						$hb_button_link = Utils::get_admin_menu_url( 'minification' ) . '&view=tools';
					}
					?>
				</div>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center sui-spacing-bottom--50">
				<a href="<?php echo esc_url( $hb_button_link ); ?>" data-track-action="cta_clicked"
					class="sui-button sui-button-blue"
					<?php
					if ( ! Utils::is_member() ) {
						echo esc_attr( 'target="_blank"' );
					}
					?>
					onclick="window.WPHB_Admin.dashboard.hideUpgradeSummary( this )">
					<span class="sui-button-text-default">
						<?php echo esc_html( $hb_button ); ?>
						<?php echo ! Utils::is_member() ? wp_kses_post( '<span class="sui-icon-open-new-window" aria-hidden="true" style="margin-left: -2px;"></span>' ) : ''; ?>
					</span>
				</a>
			</div>
		</div>
	</div>
</div>
