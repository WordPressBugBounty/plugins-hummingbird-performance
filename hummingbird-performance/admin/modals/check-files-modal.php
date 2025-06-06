<?php
/**
 * Asset optimization: checking files modal.
 *
 * @package Hummingbird
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-modal sui-modal-lg">
	<div role="dialog" class="sui-modal-content" id="check-files-modal" aria-modal="true" aria-labelledby="checkingFiles" aria-describedby="dialogDescription">
		<div class="sui-box">
			<div class="sui-box-header">
				<h3 class="sui-box-title" id="checkingFiles">
					<?php esc_html_e( 'Checking files', 'wphb' ); ?>
				</h3>
				<div class="sui-actions-right">
					<small class="sui-no-margin-bottom"><?php esc_html_e( 'File check in progress...', 'wphb' ); ?></small>
				</div>
			</div>

			<div class="sui-box-body">
				<p id="dialogDescription">
					<?php esc_html_e( 'Hummingbird is running a file check to see what files can be optimized.', 'wphb' ); ?>
				</p>

				<div class="sui-progress-block">
					<div class="sui-progress">
						<span class="sui-progress-icon" aria-hidden="true">
							<span class="sui-icon-loader sui-loading"></span>
						</span>
						<div class="sui-progress-text">
							<span>0%</span>
						</div>
						<div class="sui-progress-bar" aria-hidden="true">
							<span style="width: 0"></span>
						</div>
					</div>
					<button class="sui-button-icon sui-tooltip" id="cancel-minification-check" onclick="WPHB_Admin.minification.scanner.cancel()" type="button" data-modal-close="" data-tooltip="<?php esc_attr_e( 'Cancel Test', 'wphb' ); ?>">
						<span class="sui-icon-close" aria-hidden="true"></span>
					</button>
				</div>

				<div class="sui-progress-state sui-margin-bottom">
					<span class="sui-progress-state-text"><?php esc_html_e( 'Looking for files...', 'wphb' ); ?></span>
				</div>

				<?php if ( ! Utils::is_member() ) : ?>
					<?php
					$this->admin_notices->show_inline(
						esc_html__( 'Did you know the Pro version of Hummingbird comes up to 2x better compression and a CDN to store your assets on? Get it as part of a WPMU DEV membership.', 'wphb' ),
						'info',
						sprintf( /* translators: %1$s - opening a tag, %2$s -  closing a tag */
							esc_html__( '%1$sLearn more%2$s', 'wphb' ),
							'<a href="' . esc_url( Utils::get_link( 'plugin', 'hummingbird_ao_scanner_cdn_link' ) ) . '" target="_blank" onclick="window.wphbMixPanel.trackHBUpsell( \'cdn\', \'ao_scanner\', \'cta_clicked\', this.href, \'hb_cdn_upsell\' );">',
							'</a>'
						)
					);
					?>
				<?php endif; ?>

				<?php $cdn_status = Utils::get_module( 'minify' )->get_cdn_status(); ?>
				<?php if ( ! is_multisite() && Utils::is_member() ) : ?>
					<form method="post" id="enable-cdn-form">
						<div class="sui-border-frame">
							<label for="enable_cdn" class="sui-toggle">
								<input type="checkbox" name="enable_cdn" id="enable_cdn" aria-labelledby="enable_cdn-label" aria-describedby="enable_cdn-description" <?php checked( $cdn_status ); ?>>
								<span class="sui-toggle-slider" aria-hidden="true"></span>
								<span id="enable_cdn-label" class="sui-toggle-label">
									<?php esc_html_e( 'Store my files on the WPMU DEV CDN', 'wphb' ); ?>
								</span>
								<span id="enable_cdn-description" class="sui-description">
									<?php esc_html_e( 'By default your files are hosted on your own server. With this pro setting enabled we will host your files on WPMU DEV’s secure and hyper fast CDN.', 'wphb' ); ?>
								</span>
								<span class="sui-description sui-toggle-description">
									<?php esc_html_e( 'Note: Some externally hosted files can cause issues when added to the CDN. You can exclude these files from being hosted in the Settings tab.', 'wphb' ); ?>
								</span>
							</label>
						</div>
					</form>
				<?php elseif ( is_multisite() && Utils::is_member() ) : ?>
					<input type="checkbox" aria-hidden="true" name="enable_cdn" id="enable_cdn" <?php checked( $cdn_status ); ?> style="display: none" hidden>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		jQuery('label[for="enable_cdn"]').on('click', function(e) {
			e.preventDefault();
			var checkbox = jQuery('input[name="enable_cdn"]');
			checkbox.prop('checked', !checkbox.prop('checked') );
		});
	</script>
</div>
