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

<div id="wphb-disconnect-modal-wrapper" class="sui-modal sui-modal-sm">
	<div role="dialog" class="sui-modal-content" id="wphb-disconnect-site-modal" aria-modal="true" aria-labelledby="disconnectSite" aria-describedby="dialogDescription">
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60">
				<h3 class="sui-box-title sui-lg" id="disconnectSite">
					<?php esc_html_e( 'Disconnect Site?', 'wphb' ); ?>
				</h3>
			</div>

			<div class="sui-box-body">
				<div class="sui-notice sui-notice-yellow" >
					<p class="sui-description" style="text-align: center;">
					<?php
						esc_html_e( 'Disconnecting this site will disable key Hummingbird features and other connected WPMU DEV tools and services.', 'wphb' );
					?>
					</p>
					<div class="sui-notice-content">
						<div class="sui-notice-message">

							<p class="sui-description">
								<?php
									esc_html_e( 'You’ll lose the following key Hummingbird features', 'wphb' );
								?>
								<ul>
									<li>
										<span class="cross-icon">+</span>
										<div>
										<?php esc_html_e( 'Scheduled Performance Reports', 'wphb' ); ?>
										</div>
									</li>
									<li>
										<span class="cross-icon">+</span>
										<div>
										<?php esc_html_e( 'Uptime Monitoring & Alerts', 'wphb' ); ?>
										</div>
									</li>
									<li>
										<span class="cross-icon">+</span>
										<div>
										<?php esc_html_e( 'Automated Database Cleanup', 'wphb' ); ?>
										</div>
									</li>
									<li>
										<span class="cross-icon">+</span>
										<div>
										<?php esc_html_e( 'Premium WPMU DEV services and site management tools.', 'wphb' ); ?>
										</div>
									</li>
								</ul>
							</p>
						</div>
					</div>
					<div>
						<input id="wphb-disconnect-reason-input" type="text" placeholder="<?php esc_attr_e( 'Mind sharing why you’re disconnecting?', 'wphb' ); ?>" class="sui-form-control wphb-disconnect-reason-input" aria-labelledby="wphb-disconnect-reason-input" />
					</div>
				</div>
				<div class="sui-block-content-center">
					<button id="cancel-disconnect" type="button" class="sui-button sui-button-ghost" data-modal-close="">
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
