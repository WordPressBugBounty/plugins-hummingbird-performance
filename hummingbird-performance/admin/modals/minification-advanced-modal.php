<?php
/**
 * Asset optimization: switch to advanced mode modal.
 *
 * @package Hummingbird
 *
 * @since 2.6.0
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-sm">
	<div
			role="dialog"
			id="wphb-advanced-minification-modal"
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
					<?php esc_html_e( 'Just be Careful!', 'wphb' ); ?>
				</h3>

				<p class="sui-description" id="dialogDescription">
					<?php esc_html_e( 'Manual mode gives you full control over your files but can easily break your website if configured incorrectly.', 'wphb' ); ?>
				</p>

				<p class="sui-description" style="font-weight: 500">
					<?php esc_html_e( 'We recommend you make one tweak at a time and check the frontend of your website each change to avoid any mishaps.', 'wphb' ); ?>
				</p>
				<p class="sui-description" id="dialogDescription">
					<?php
					printf(
						/* translators: %1$s - opening link tag, %2$s - closing link tag */
						esc_html__( 'See the %1$sHow Does it Work%2$s guide.', 'wphb' ),
						'<a href="' . esc_url( Utils::get_documentation_url( 'wphb-minification' ) ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					);
					?>
				</p>
			</div>

			<div class="sui-box-body sui-content-center">
				<div class="sui-form-field">
					<label for="hide-advanced-modal" class="sui-checkbox sui-checkbox-sm">
						<input type="checkbox" id="hide-advanced-modal" aria-labelledby="hide-advanced-label"/>
						<span aria-hidden="true"></span>
						<span id="hide-advanced-label" class="sui-toggle-label">
							<?php esc_html_e( "Don't show me this again", 'wphb' ); ?>
						</span>
					</label>
				</div>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center">
				<button class="sui-button sui-button-ghost" data-modal-close>
					<?php esc_html_e( 'Cancel', 'wphb' ); ?>
				</button>
				<button class="sui-button sui-button-blue" onclick="WPHB_Admin.minification.switchView( 'advanced' )" id="wphb-switch-to-advanced">
					<?php esc_html_e( 'Switch Mode', 'wphb' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
