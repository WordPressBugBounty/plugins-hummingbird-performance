<?php
/**
 * Uptime settings meta box.
 *
 * @package Hummingbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Hummingbird\Core\Utils;

?>
<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label"><?php esc_html_e( 'Deactivate', 'wphb' ); ?></span>
		<span class="sui-description">
			<?php esc_html_e( 'If you no longer wish to use Hummingbird’s Uptime Monitor you can turn it off completely.', 'wphb' ); ?>
		</span>
	</div><!-- end col-third -->
	<div class="sui-box-settings-col-2">
		<a id="wphb-disable-uptime" href="#" class="sui-button sui-button-ghost" onclick="wphbMixPanel.disableFeature( 'Uptime' )">
			<?php esc_html_e( 'Deactivate', 'wphb' ); ?>
		</a>
		<span class="spinner standalone"></span>
		<?php if ( ! Utils::is_whitelabel_enabled() ) { ?>
			<div class="sui-notice sui-notice-blue" style="margin-top: 10px;">
				<div class="sui-notice-content">
					<div class="sui-notice-message">
						<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
						<p>
							<?php
							printf( /* translators: %1$s - opening <a> tag, %2$s - closing </a> tag */
								esc_html__( 'Deactivation of Uptime is not recommended if the %1$sProactive Monitoring%2$s service is enabled.', 'wphb' ),
								'<a href="' . esc_url( Utils::get_link( 'expert-services', 'hummingbird_services_uptime_settings_notice' ) ) . '" target="_blank" onclick="window.wphbMixPanel.trackHBUpsell( \'expert_services_uptime\', \'uptime_settings\', \'cta_clicked\', this.href, \'expert_services_upsell\' );">',
								'</a>'
							)
							?>
						</p>
					</div>
				</div>
			</div>
		<?php } ?>
	</div>
</div>
