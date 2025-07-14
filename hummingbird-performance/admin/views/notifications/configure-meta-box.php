<?php
/**
 * Notifications configure meta box.
 *
 * @since 3.1.1
 * @package Hummingbird
 */

use Hummingbird\Core\Hub_Connector;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-box-body">
	<p><?php esc_html_e( 'Activate and schedule notifications and reports in one place. Automate your workflow with daily, weekly or monthly reports sent directly to your inbox.', 'wphb' ); ?></p>
</div>

<div class="sui-box-settings-row">
	<table class="sui-table sui-table-flushed">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Notifications', 'wphb' ); ?></th>
			<th class="sui-hidden-xs"><?php esc_html_e( 'Type', 'wphb' ); ?></th>
			<th colspan="3" class="sui-hidden-xs"><?php esc_html_e( 'Status', 'wphb' ); ?></th>
		</tr>
		</thead>

		<tbody>
		<tr>
			<td class="sui-table-item-title">
				<span class="sui-icon-calendar sui-hidden-xs" aria-hidden="true"></span>
				<?php esc_html_e( 'Performance Test', 'wphb' ); ?>
			</td>
			<td class="sui-hidden-xs"><?php esc_html_e( 'Reporting', 'wphb' ); ?></td>
			<td class="sui-hidden-xs"><span class="sui-tag hb-tag-blue sui-tag-sm"><?php esc_html_e( 'Connect', 'wphb' ); ?></span></td>
			<td colspan="2"><?php esc_html_e( 'Schedule performance tests and receive customized results by email.', 'wphb' ); ?></td>
		</tr>
		<tr>
			<td class="sui-table-item-title">
				<span class="sui-icon-mail sui-hidden-xs" aria-hidden="true"></span>
				<?php esc_html_e( 'Uptime', 'wphb' ); ?>
			</td>
			<td class="sui-hidden-xs"><?php esc_html_e( 'Notification', 'wphb' ); ?></td>
			<td class="sui-hidden-xs"><span class="sui-tag sui-tag-sm hb-tag-blue"><?php esc_html_e( 'Connect', 'wphb' ); ?></span></td>
			<td colspan="2"><?php esc_html_e( 'Receive an email when this website is unavailable.', 'wphb' ); ?></td>
		</tr>
		<tr>
			<td class="sui-table-item-title">
				<span class="sui-icon-calendar sui-hidden-xs" aria-hidden="true"></span>
				<?php esc_html_e( 'Uptime', 'wphb' ); ?>
			</td>
			<td class="sui-hidden-xs"><?php esc_html_e( 'Reporting', 'wphb' ); ?></td>
			<td class="sui-hidden-xs"><span class="sui-tag hb-tag-blue sui-tag-sm"><?php esc_html_e( 'Connect', 'wphb' ); ?></span></td>
			<td colspan="2"><?php esc_html_e( 'Schedule uptime reports and receive results by email.', 'wphb' ); ?></td>
		</tr>
		<tr>
			<td class="sui-table-item-title">
				<span class="sui-icon-mail sui-hidden-xs" aria-hidden="true"></span>
				<?php esc_html_e( 'Database Cleanup', 'wphb' ); ?>
			</td>
			<td class="sui-hidden-xs"><?php esc_html_e( 'Reporting', 'wphb' ); ?></td>
			<td class="sui-hidden-xs"><span class="sui-tag hb-tag-blue sui-tag-sm"><?php esc_html_e( 'Connect', 'wphb' ); ?></span></td>
			<td colspan="2"><?php esc_html_e( 'Schedule database cleanups and receive results by email.', 'wphb' ); ?></td>
		</tr>
		</tbody>
	</table>
</div>
<?php if ( ! is_multisite() || is_network_admin() ) : ?>
	<div class="sui-box-settings-row sui-upsell-row">
		<div class="sui-upsell-notice__content">
			<div class="sui-notice sui-notice-blue">
				<div class="sui-notice-content">
					<div class="sui-notice-message">
						<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
						<p>
							<?php esc_html_e( 'Get customized performance and uptime reports delivered to your inbox — daily, weekly, or monthly. All you need is a free WPMU DEV account to enable reporting.', 'wphb' ); ?>
							<br/>
							<a href="<?php echo esc_url( Hub_Connector::get_connect_site_url( 'wphb-notifications' ) ); ?>" class="sui-button sui-button-blue" style="margin-top: 10px" onclick="window.wphbMixPanel.trackHBUpsell( 'notifications', 'notifications_page', 'cta_clicked', this.href, 'hb_notifications_upsell' );">
								<?php esc_html_e( 'CONNECT SITE', 'wphb' ); ?>
							</a>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
