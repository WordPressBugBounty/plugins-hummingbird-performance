<?php
/**
 * Safe mode confirmation modal.
 *
 * @package Hummingbird
 *
 * @since 3.18.0
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-sm">
	<div
			role="dialog"
			id="wphb-safe-mode-confirmation-modal"
			class="sui-modal-content"
			aria-modal="true"
			aria-labelledby="switchAdvanced"
			aria-describedby="dialogDescription"
	>
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60">
				<button class="sui-button-icon sui-button-float--right" data-modal-close >
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text"><?php esc_attr_e( 'Close this modal', 'wphb' ); ?></span>
				</button>
				<h3 class="sui-box-title sui-lg" id="switchAdvanced">
					<?php esc_html_e( 'Unpublished changes', 'wphb' ); ?>
				</h3>

				<p class="sui-description" id="dialogDescription">
					<?php esc_html_e( 'There are unpublished changes made in safe mode. Do you want to publish the changes to live or discard them?', 'wphb' ); ?>
				</p>
			</div>

			<div class="sui-box-body" style="text-align: center;">
				<div class="sui-form-field">
					<label for="wphb-safe-mode-clear-all-cache" class="sui-checkbox">
						<input type="checkbox" id="wphb-safe-mode-clear-all-cache" aria-labelledby="wphb-safe-mode-clear-all-cache-label">
						<span aria-hidden="true"></span>
						<span id="wphb-safe-mode-clear-all-cache-label">
							<?php esc_html_e( 'Clear cache after publishing change.', 'wphb' ); ?>
						</span>
					</label>
				</div>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center">
				<button class="sui-button sui-button-ghost" onclick="window.WPHB_Admin.discardSafeMode(this)">
					<?php esc_html_e( 'Discard', 'wphb' ); ?>
				</button>
				<button class="sui-button sui-button-blue" onclick="window.WPHB_Admin.publishSafeMode(this)">
					<span class="sui-icon-check" aria-hidden="true"></span>
					<?php esc_html_e( 'Publish', 'wphb' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
