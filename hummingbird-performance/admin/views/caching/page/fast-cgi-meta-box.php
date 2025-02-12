<?php
/**
 * FastCGI caching meta box.
 *
 * @package Hummingbird
 *
 * @var object|bool $fast_cgi_settings     FastCGI settings.
 * @var array       $options               Page caching settings.
 * @var bool        $is_fast_cgi_supported Is FastCGI supported.
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<p><?php echo esc_html( Utils::get_page_cache_description() ); ?></p>

<?php
$notice = Utils::is_member() ? esc_html__( 'Static Server Cache is currently active.', 'wphb' ) : esc_html__( 'Static Server Cache is currently active. If you wish to update settings or switch to Local Page Cache, please ensure you are connected to the WPMU DEV Dashboard plugin.', 'wphb' );
$this->admin_notices->show_inline( $notice, Utils::is_member() ? 'success' : 'warning' );
if ( ! $is_fast_cgi_supported ) {
	return;
}
?>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label"><?php esc_html_e( 'Preload caching', 'wphb' ); ?></span>
		<span class="sui-description">
			<?php esc_html_e( 'Enable this feature to automatically create cached versions of your homepage or any page or post. This can be a resource-intensive operation, so use it only when required.', 'wphb' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="sui-form-field">
			<label for="preload" class="sui-toggle">
				<input type="checkbox" name="preload[enabled]" id="preload" value="1" aria-labelledby="preload-label" <?php checked( $options['preload'] ); ?>>
				<span class="sui-toggle-slider" aria-hidden="true"></span>
				<span id="preload-label" class="sui-toggle-label">
					<?php esc_html_e( 'Enable preload caching', 'wphb' ); ?>
				</span>
				<span class="sui-description sui-toggle-description">
					<?php esc_html_e( "The homepage will be preloaded once you enable this feature and then automatically whenever an action triggers your cache to be cleared. A page or post will be preloaded automatically if it's updated or its cached version is cleared.", 'wphb' ); ?>
				</span>
			</label>

			<div class="sui-border-frame sui-toggle-content <?php echo $options['preload'] ? '' : 'sui-hidden'; ?>" id="page_cache_preload_type">
				<span class="sui-description">
					<?php esc_html_e( 'Choose which pages you want to trigger cache preload. We recommend you always preload the homepage.', 'wphb' ); ?>
				</span>
				<div class="sui-form-field">
					<label for="home_page" class="sui-checkbox sui-checkbox-stacked sui-checkbox-sm">
						<input type="checkbox" name="preload_type[home_page]" id="home_page" <?php checked( isset( $options['preload_type'] ) && $options['preload_type']['home_page'] ); ?>>
						<span aria-hidden="true"></span>
						<span><?php esc_html_e( 'Homepage', 'wphb' ); ?></span>
					</label>
					<label for="on_clear" class="sui-checkbox sui-checkbox-stacked sui-checkbox-sm">
						<input type="checkbox" name="preload_type[on_clear]" id="on_clear" <?php checked( isset( $options['preload_type'] ) && $options['preload_type']['on_clear'] ); ?>>
						<span aria-hidden="true"></span>
						<span><?php esc_html_e( "Any page or post that's been updated, or for which the cache was cleared", 'wphb' ); ?></span>
					</label>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label"><?php esc_html_e( 'Settings', 'wphb' ); ?></span>
		<span class="sui-description">
			<?php esc_html_e( 'Fine tune page caching to work how you want it to.', 'wphb' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="sui-form-field">
			<label for="comment_clear" class="sui-toggle">
				<input type="hidden" name="settings[comment_clear]" value="0">
				<input type="checkbox" name="settings[comment_clear]" id="comment_clear" value="1" aria-labelledby="comment_clear-label" <?php checked( $settings['settings']['comment_clear'] ); ?>>
				<span class="sui-toggle-slider" aria-hidden="true"></span>
				<span id="comment_clear-label" class="sui-toggle-label">
					<?php esc_html_e( 'Clear cache on comment post', 'wphb' ); ?>
				</span>
				<span class="sui-description sui-toggle-description">
					<?php esc_html_e( 'The page cache will be cleared after each comment made on a post.', 'wphb' ); ?>
				</span>
			</label>
		</div>
		<div class="sui-form-field">
			<label for="clear_update" class="sui-toggle">
				<input type="checkbox" name="settings[clear_update]" id="clear_update" value="1" aria-labelledby="clear_update-label" <?php checked( $settings['settings']['clear_update'] ); ?>>
				<span class="sui-toggle-slider" aria-hidden="true"></span>
				<span id="clear_update-label" class="sui-toggle-label">
					<?php esc_html_e( 'Clear full cache when post/page is updated', 'wphb' ); ?>
				</span>
				<span class="sui-description sui-toggle-description">
					<?php esc_html_e( 'If one of your pages or posts gets updated, turning this setting on will also regenerate all cached archives and taxonomies for all post types.', 'wphb' ); ?>
				</span>
			</label>
		</div>
	</div><!-- end sui-box-settings-col-2 -->
</div><!-- end row -->

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label"><?php esc_html_e( 'Allow Caching Query String (Params)', 'wphb' ); ?></span>
		<span class="sui-description">
			<?php esc_html_e( 'Specify any query string you want to cache', 'wphb' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="sui-form-field">
			<?php
			$query_params = '';
			if ( isset( $fast_cgi_settings->query_params ) && is_array( $fast_cgi_settings->query_params ) ) {
				$query_params = join( PHP_EOL, $fast_cgi_settings->query_params );
			}
			?>
			<textarea class="sui-form-control" name="query_params" placeholder="<?php esc_attr_e( 'Enter query string one per line', 'wphb' ); ?>"><?php echo $query_params; ?></textarea>
			<span class="sui-description">
				<?php esc_html_e( 'All query strings added to this list will be cached. Enter one string per line.', 'wphb' ); ?>
			</span>
		</div>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label"><?php esc_html_e( 'Exclude Urls From Caching', 'wphb' ); ?></span>
		<span class="sui-description">
			<?php esc_html_e( 'Specify any particular URLs you don’t want to cache at all.', 'wphb' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="sui-form-field">
			<?php
			$bypass_urls = '';
			if ( isset( $fast_cgi_settings->bypass_urls ) && is_array( $fast_cgi_settings->bypass_urls ) ) {
				$bypass_urls = join( PHP_EOL, $fast_cgi_settings->bypass_urls );
			}
			?>
			<textarea class="sui-form-control" name="bypass_urls" placeholder="<?php esc_attr_e( 'Enter relative URLs here, one per line', 'wphb' ); ?>"><?php echo $bypass_urls; ?></textarea>
			<span class="sui-description">
				<?php esc_html_e( 'Allows excluding URLs or URL strings from Static Site Cache. E.g., if you add /blog it will also exclude /blog/samplepost or parentpage/blog from the cache.', 'wphb' ); ?>
			</span>
		</div>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label"><?php esc_html_e( 'Cache Lifetime', 'wphb' ); ?></span>
		<span class="sui-description">
			<?php esc_html_e( 'Change the expiry of the static server cache if needed.', 'wphb' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="sui-form-field">
			<?php $ttl = isset( $fast_cgi_settings->ttl ) ? $fast_cgi_settings->ttl : ''; ?>
			<select name="ttl">
				<option value="60" <?php selected( $ttl, 60 ); ?>><?php esc_html_e( '1 hour', 'wphb' ); ?></option>
				<option value="240" <?php selected( $ttl, 240 ); ?>><?php esc_html_e( '4 hours', 'wphb' ); ?></option>
				<option value="480" <?php selected( $ttl, 480 ); ?>><?php esc_html_e( '8 hours', 'wphb' ); ?></option>
				<option value="720" <?php selected( $ttl, 720 ); ?>><?php esc_html_e( '12 hours', 'wphb' ); ?></option>
				<option value="1440" <?php selected( $ttl, 1440 ); ?>><?php esc_html_e( '24 hours', 'wphb' ); ?></option>
			</select>
			<span class="sui-description">
				<?php esc_html_e( 'Specify the duration for which the static server cache will be valid.', 'wphb' ); ?>
			</span>
		</div>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<strong><?php esc_html_e( 'Deactivate', 'wphb' ); ?></strong>
		<span class="sui-description">
			<?php esc_html_e( 'You can deactivate Static Server Cache at any time. ', 'wphb' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<button type="button" class="sui-button sui-button-ghost" id="wphb-disable-fastcgi" aria-live="polite">
			<!-- Default State Content -->
			<span class="sui-button-text-default">
				<span class="sui-icon-power-on-off" aria-hidden="true"></span>
				<?php esc_html_e( 'Deactivate', 'wphb' ); ?>
			</span>
			<!-- Loading State Content -->
			<span class="sui-button-text-onload">
				<span class="sui-icon-loader sui-loading" aria-hidden="true"></span>
				<?php esc_html_e( 'Deactivating', 'wphb' ); ?>
			</span>
		</button>
		<span class="sui-description">
			<?php esc_html_e( 'Note: You won’t lose any site data by deactivating. Only the cached pages will be removed and will no longer be served to your site visitors. Remember this may result in slower page loads unless you have another caching plugin activated.', 'wphb' ); ?>
		</span>
	</div>
</div>
