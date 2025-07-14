<?php
/**
 * Site connected modal.
 *
 * @since 3.15.0
 * @package Hummingbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-sm">
	<div
			role="dialog"
			id="wphb-hub-connection-success-modal"
			class="sui-modal-content"
			aria-modal="true"
			aria-labelledby="wphb-hub-connection-success-modal-title"
	>
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60">
				<span class="sui-icon-check-tick sui-success sui-xl" aria-hidden="true"></span>

				<h3 id="wphb-hub-connection-success-modal-title" class="sui-box-title sui-lg" style="white-space: inherit">
					<?php esc_html_e( 'Site connected successfully!', 'wphb' ); ?>
				</h3>
			</div>

			<div class="sui-box-body sui-spacing-bottom--30">
				<p style="text-align: center">
					<?php
						echo '<strong>' . esc_html__( 'Congratulations!', 'wphb' ) . ' </strong> ';
						esc_html_e( 'Your site is now successfully connected, unlocking powerful tools to keep your site running smoothly.', 'wphb' );
					?>
				</p>
				<ul>
					<li>
						<span class="sui-icon-check" aria-hidden="true"></span>
						<?php
							esc_html_e( 'Uptime: Get alerts if your site goes down.', 'wphb' );
						?>
					</li>
					<li>
						<span class="sui-icon-check" aria-hidden="true"></span>
						<?php
							esc_html_e( 'Notifications: Schedule reports and updates.', 'wphb' );
						?>
					</li>
				</ul>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center sui-spacing-bottom--50">
				<a href="" class="sui-button sui-button-blue"  data-modal-close="">
					<span class="sui-button-text-default">
						<?php echo esc_html__( 'GOT IT', 'wphb' ); ?>
					</span>
				</a>
			</div>
		</div>
	</div>
</div>
