<?php
/**
 * Uptime no membership meta box.
 *
 * @package Hummingbird
 */

use Hummingbird\Core\Hub_Connector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="uptime-hub-connector">
	<img class="sui-image" aria-hidden="true" alt=""
		src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@1x.png' ); ?>"
		srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@1x.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@2x.png' ); ?> 2x" />

	<div class="sui-message-content">
		<h2>
			<?php
			esc_html_e( 'Uptime Monitor', 'wphb' );
			?>
		</h2>
		<p>
			<?php
			esc_html_e( 'Uptime Monitor helps you deliver the best experience for your visitors by letting you know the moment something goes wrong. Just connect a free WPMU DEV account to activate it.', 'wphb' );
			?>
		</p>

		<a class="sui-button sui-button-blue" role="button" href="<?php echo esc_url( Hub_Connector::get_connect_site_url() ); ?>" onclick="window.wphbMixPanel.trackHBUpsell( 'uptime', 'uptime_page', 'cta_clicked', this.href, 'hb_uptime_upsell' );">
			<span class="sui-icon-plug-connected" aria-hidden="true"></span>
			<?php esc_html_e( 'CONNECT SITE FOR INSTANT ALERTS', 'wphb' ); ?>
		</a>
	</div>

	<div class="unlock-features">
		<h3>
			<?php
			esc_html_e( 'Uptime Monitor Features', 'wphb' );
			?>
		</h3>
		<p>
			<?php
			esc_html_e( 'By signing up with WPMU DEV and activating Uptime Monitor, you unlock powerful features designed to prevent site downtime.', 'wphb' );
			?>
		</p>
		<div class="features">
			<div class="features-row sui-row">
				<div class="feature sui-col-md-6">
					<div class="image-container">
						<img aria-hidden="true" alt=""
						src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-down-alerts.png' ); ?>"
						srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-down-alerts@1x.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-down-alerts@2x.png' ); ?> 2x" />
					</div>
					<div class="feature-text">
						<h4>
							<?php esc_html_e( 'Downtime alerts', 'wphb' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Know when a site goes down with 24/7 monitoring and email alerts.', 'wphb' ); ?>
						</p>
					</div>
				</div>
				<div class="feature sui-col-md-6">
					<div class="image-container">
						<img aria-hidden="true" alt=""
						src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-logs.png' ); ?>"
						srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-logs@1x.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-logs@2x.png' ); ?> 2x" />
					</div>
					<div class="feature-text">
						<h4>
							<?php esc_html_e( 'Accurate logs', 'wphb' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Get to the bottom of issues faster with accurate and detailed downtime logs.', 'wphb' ); ?>
						</p>
					</div>
				</div>
			</div>
			<div class="features-row sui-row">
				<div class="feature  sui-col-md-6">
					<div class="image-container">
						<img aria-hidden="true" alt=""
						src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-monitoring.png' ); ?>"
						srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-monitoring@1x.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-monitoring@2x.png' ); ?> 2x" />
					</div>
					<div class="feature-text">
						<h4>
							<?php esc_html_e( 'Speed monitoring & alerts', 'wphb' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Keep your sites at top speed with performance tracking and slow alerts.', 'wphb' ); ?>
						</p>
					</div>
				</div>
				<div class="feature sui-col-md-6">
					<div class="image-container">
						<img aria-hidden="true" alt=""
						src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-oneclick.png' ); ?>"
						srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-oneclick@1x.png' ); ?> 1x, <?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/uptime-feature-oneclick@2x.png' ); ?> 2x" />
					</div>
					<div class="feature-text">
						<h4>
							<?php esc_html_e( 'One-click configs', 'wphb' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Save time with uptime configs that can be applied to all of your sites at once.', 'wphb' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

