<?php
/**
 * Integrations meta box.
 *
 * @since 2.5.0
 * @package Hummingbird
 *
 * @var bool   $cf_is_connected        If Cloudflare is connected (success auth).
 * @var bool   $disable_redis          Whether to disable redis ( In case it's not supported )
 * @var bool   $error                  If there was an error connecting to redis
 * @var bool   $has_cloudflare         If Cloudflare is connected (DNS connected).
 * @var bool   $is_redis_object_cache  Is Redis object cache enabled.
 * @var bool   $redis_connected        Redis server status.
 * @var bool   $redis_enabled          Redis enabled.
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<p class="sui-no-margin-bottom" style="padding-bottom: 10px">
	<?php esc_html_e( 'Integrate with powerful third-party providers to gain full control over your caching.', 'wphb' ); ?>
</p>

<div class="sui-accordion sui-accordion-flushed">
	<div class="sui-accordion-header">
		<div class="sui-accordion-col-12">
			<?php esc_html_e( 'Available Integrations', 'wphb' ); ?>
		</div>
	</div>

	<div class="sui-accordion-item <?php echo $cf_is_connected || $has_cloudflare ? 'sui-accordion-item--open' : ''; ?>" id="wphb-react-cloudflare"></div>
</div>

<?php
if ( ! is_multisite() || is_network_admin() ) {
	$this->modal( 'integration-redis-connect' );
}
?>

<script>
	jQuery( document ).ready( function() {
		window.WPHB_Admin.getModule( 'cloudflare' );
	} );
</script>
