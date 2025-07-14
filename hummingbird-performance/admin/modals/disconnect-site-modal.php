<?php
/**
 * Disconnect site modal.
 *
 * @since 3.15.0
 * @package Hummingbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-sm">
	<div role="dialog" class="sui-modal-content" id="wphb-disconnect-site-modal" aria-modal="true" aria-labelledby="disconnectSite" aria-describedby="dialogDescription">
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60">
				<button class="sui-button-icon sui-button-float--right" id="dialog-close-div" data-modal-close="">
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text"><?php esc_attr_e( 'Close this dialog window', 'wphb' ); ?></span>
				</button>

				<h3 class="sui-box-title sui-lg" id="disconnectSite">
					<?php esc_html_e( 'Disconnect Site?', 'wphb' ); ?>
				</h3>

				<p class="sui-description" id="dialogDescription">
					<?php esc_html_e( 'Do you want to disconnect your site from WPMU DEV?', 'wphb' ); ?>
				</p>
			</div>

			<div class="sui-box-body">
				<div class="sui-notice sui-notice-yellow" >
					<div class="sui-notice-content">
						<div class="sui-notice-message">
							<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
							<p class="sui-description" style="color:#888888">
								<?php
								printf(
									/* translators: %s: Hub name */
									esc_html__( 'Note that disconnecting your site from %s will disable other services that rely on this connection.', 'wphb' ),
									'<strong style="color:#888888">WPMU DEV</strong>'
								);
								?>
							</p>
						</div>
					</div>
				</div>
				<div class="sui-block-content-center">
					<button type="button" class="sui-button sui-button-ghost" data-modal-close="">
						<?php esc_html_e( 'Cancel', 'wphb' ); ?>
					</button>

					<button type="button" class="sui-button sui-button-gray" onclick="WPHB_Admin.settings.confirmDisconnectSite(this)">
						<span class="sui-button-text-default">
							<span class="sui-icon-plug-disconnected" aria-hidden="true"></span>
							<?php esc_html_e( 'Disconnect site', 'wphb' ); ?>
						</span>
						<span class="sui-button-text-onload">
							<span class="sui-icon-loader sui-loading" aria-hidden="true"></span>
							<?php esc_html_e( 'Disconnect site', 'wphb' ); ?>
						</span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
