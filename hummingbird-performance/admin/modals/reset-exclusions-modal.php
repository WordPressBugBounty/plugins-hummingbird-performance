<?php
/**
 * Reset exclusion modal.
 *
 * @since 3.11.0
 * @package Hummingbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-md">
	<div role="dialog" class="sui-modal-content" id="wphb-reset-exclusions-modal" aria-modal="true" aria-labelledby="resetSettings" aria-describedby="dialogDescription">
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60">
				<button class="sui-button-icon sui-button-float--right" id="dialog-close-div" data-modal-close="">
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text"><?php esc_attr_e( 'Close this dialog window', 'wphb' ); ?></span>
				</button>

				<i class="sui-notice-icon sui-warning-icon sui-icon-info sui-lg" aria-hidden="true"></i>
				<h3 class="sui-box-title sui-lg" id="resetSettings">
					<?php esc_html_e( 'Reset Active Exclusions?', 'wphb' ); ?>
				</h3>

				<p class="sui-description" id="delay_js_reset_modal_content">
					<?php
					printf(
						/* translators: 1: Open b, 2: Close b */
						esc_html__( 'Are you sure you wish to remove all active Delay JS exclusions for the selected Exclusion Type? %1$sThis action cannot be undone%2$s.', 'wphb' ),
						'<b>',
						'</b>'
					);
					?>
				</p>
				<p class="sui-description" id="critical_css_reset_modal_content">
					<?php
					printf(
						/* translators: 1: Open b, 2: Close b */
						esc_html__( 'Are you sure you wish to remove all active Critical CSS exclusions for the selected Exclusion Type? %1$sThis action cannot be undone%2$s.', 'wphb' ),
						'<b>',
						'</b>'
					);
					?>
				</p>
			</div>

			<div class="sui-box-body">
				<div class="sui-block-content-center">
					<button type="button" class="sui-button sui-button-ghost" data-modal-close="">
						<?php esc_html_e( 'Cancel', 'wphb' ); ?>
					</button>

					<button type="button" class="sui-button sui-button-ghost sui-button-red" onclick="WPHB_Admin.minification.resetExclusion()">
						<span class="sui-icon-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Confirm Reset', 'wphb' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
