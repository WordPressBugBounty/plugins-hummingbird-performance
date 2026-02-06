<?php
/**
 * Asset optimization: switch to advanced mode modal.
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
			id="wphb-safe-mode-modal"
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
					<?php esc_html_e( 'Enable Safe Mode?', 'wphb' ); ?>
				</h3>

				<p class="sui-description" id="dialogDescription">
					<?php esc_html_e( 'Safe Mode lets you safely test different optimization settings without affecting how visitors experience your site. Only logged-in admins can see the optimized version while testing.', 'wphb' ); ?>
				</p>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center">
				<button class="sui-button sui-button-ghost" data-modal-close>
					<?php esc_html_e( 'Cancel', 'wphb' ); ?>
				</button>
				<button class="sui-button" onclick="window.WPHB_Admin.toggleSafeMode( this )" id="wphb-enable-safe-mode">
					<?php esc_html_e( 'Enable', 'wphb' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
