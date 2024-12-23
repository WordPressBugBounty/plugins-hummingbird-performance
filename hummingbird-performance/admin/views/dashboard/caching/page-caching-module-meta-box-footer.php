<?php
/**
 * Page caching meta box footer on dashboard page.
 *
 * @package Hummingbird
 *
 * @since 1.7.0
 * @var string $url                   Url to module.
 * @var bool   $is_fast_cgi_supported Is FastCGI supported.
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<a href="<?php echo esc_url( $url ); ?>" class="sui-button sui-button-ghost" name="submit">
	<span class="sui-icon-wrench-tool" aria-hidden="true"></span>
	<?php esc_html_e( 'Configure', 'wphb' ); ?>
</a>

<?php if ( ! Utils::get_api()->hosting->has_fast_cgi_header() && $is_fast_cgi_supported && ! Utils::is_subsite() ) { ?>
	<div class="sui-actions-right" bis_skin_checked="1">
		<button class="sui-button sui-button-blue" id="wphb-switch-page-cache-method" data-method="hosting_static_cache" data-location="dash_widget">
			<span class="sui-button-text-default">
				<?php esc_html_e( 'Switch to Static Server Cache', 'wphb' ); ?>
			</span>
			<span class="sui-button-text-onload">
				<span class="sui-icon-loader sui-loading" aria-hidden="true"></span>
				<?php esc_html_e( 'Switching', 'wphb' ); ?>
			</span>
		</button>
	</div>
<?php } elseif ( ! isset( $_SERVER['WPMUDEV_HOSTED'] ) && ! Utils::is_subsite() && ! Utils::is_whitelabel_enabled() ) { ?>
	<div class="sui-actions-right" bis_skin_checked="1">
		<button class="sui-button sui-button-blue" disabled>
			<span class="sui-button-text-default">
				<?php esc_html_e( 'Switch to Static Server Cache', 'wphb' ); ?>
			</span>
		</button>
		<p class="sui-description">
			<?php
				printf(
					/* translators: %1$s: opening a tag, %2$s: closing a tag */
					esc_html__( 'Requires %1$sWPMU DEV Hosting%2$s', 'wphb' ),
					'<a href="' . esc_attr( Utils::get_link( 'hosting-upsell', 'dash_widget_ssc_hosting_upsell' ) ) . '" target="_blank" onclick="window.wphbMixPanel.trackHBUpsell( \'hosting_static_cache\', \'dash_widget\', \'cta_clicked\', this.href, \'hb_hosting_upsell\' );">',
					'<span class="sui-icon-open-new-window sui-info" aria-hidden="true"></span></a>'
				);
			?>
		</p>
	</div>
<?php } ?>
