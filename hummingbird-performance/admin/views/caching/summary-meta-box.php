<?php
/**
 * Caching summary meta box.
 *
 * @since 1.9.1
 *
 * @package Hummingbird
 *
 * @var int   $cached           Number of cached pages.
 * @var bool  $gravatar         Gravatar Caching status.
 * @var int   $issues           Number of Browser Caching issues.
 * @var int   $pages            Total number of posts and pages in WP.
 * @var bool  $pc_active        Page caching status.
 * @var int   $rss              RSS caching duration.
 * @var bool  $preload_active   Is preloading enabled.
 * @var bool  $preload_running  Is preloading running.
 * @var array $options          Caching options.
 * @var bool  $has_fastcgi      Has FastCGI enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$branded_image = apply_filters( 'wpmudev_branding_hero_image', '' );
?>

<?php if ( $branded_image ) : ?>
	<div class="sui-summary-image-space" aria-hidden="true" style="background-image: url('<?php echo esc_url( $branded_image ); ?>')"></div>
<?php else : ?>
	<div class="sui-summary-image-space" aria-hidden="true"></div>
<?php endif; ?>
<div class="sui-summary-segment">
	<div class="sui-summary-details">
		<?php if ( $has_fastcgi ) { ?>
			<span class="sui-summary-detail">
				<?php esc_html_e( 'Static Server Cache', 'wphb' ); ?>
				<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_attr_e( 'Static Server Cache is currently active and working well.', 'wphb' ); ?>">
					<span class="sui-icon-check-tick sui-success" aria-hidden="true"></span>
				</span>
			</span>
			<span class="sui-summary-sub">
				<?php esc_html_e( 'Sever-level page caching using FastCGI', 'wphb' ); ?>
			</span>
		<?php } else { ?>
		<span class="sui-summary-large"><?php echo $pc_active ? absint( $cached ) : '-'; ?></span>
		<span class="sui-summary-sub">
			<?php esc_html_e( 'Cache files', 'wphb' ); ?>
			<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_attr_e( 'Pages are cached when someone first visits them. This number is the total count of static files being cached (not pages) and can be larger than the total number of physical pages you have.', 'wphb' ); ?>">
				<span class="sui-icon-info" aria-hidden="true"></span>
			</span>
		</span>
		<?php } ?>

		<span class="sui-summary-detail">
			<?php
			$preload_status = __( 'Cache Preloading', 'wphb' );
			$preload_icon   = 'sui-icon-check-tick sui-success';
			if ( ! empty( $options['preload_type']['home_page'] ) && ! empty( $options['preload_type']['on_clear'] ) ) {
				$preload_tooltip = __( 'Cache preloading is currently active for all pages and working well.', 'wphb' );
			} elseif ( ! empty( $options['preload_type']['home_page'] ) ) {
				$preload_tooltip = __( 'Cache preloading is currently active for the homepage and working well.', 'wphb' );
			} else {
				$preload_tooltip = __( 'Cache preloading is currently active and working well.', 'wphb' );
			}

			if ( ! $pc_active || ! $preload_active ) {
				$preload_icon    = 'sui-icon-info';
				$preload_tooltip = __( 'Cache preloading is currently inactive, you can activate it from the settings below', 'wphb' );
			} elseif ( $preload_running ) {
				$preload_status  = __( 'Preloading in progress...', 'wphb' );
				$preload_icon    = 'sui-icon-loader sui-loading';
				$preload_tooltip = __( 'Refresh the page to see the updated count.', 'wphb' );
			}

			echo esc_html( $preload_status );
			?>
			<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php echo esc_attr( $preload_tooltip ); ?>">
				<span class="<?php echo esc_attr( $preload_icon ); ?>" aria-hidden="true"></span>
			</span>
		</span>
		<span class="sui-summary-sub">
			<?php
			esc_html_e( 'Proactive page cache warmup', 'wphb' );

			if ( $preload_active && $pc_active && $preload_running ) {
				echo ' | ';

				printf(
					/* translators: %1$s - opening a tag, %2$s - closing a tag */
					esc_html__( '%1$sCancel%2$s', 'wphb' ),
					'<a href="#" id="wphb-cancel-cache-preload">',
					'</a>'
				);
			}
			?>
		</span>
	</div>
</div>
<div class="sui-summary-segment">
	<ul class="sui-list">
		<li>
			<span class="sui-list-label"><?php esc_html_e( 'Browser Caching', 'wphb' ); ?></span>
			<span class="sui-list-detail">
				<?php if ( 0 < $issues ) : ?>
					<span class="sui-tag sui-tag-warning"><?php echo absint( $issues ); ?></span>
				<?php else : ?>
					<span class="sui-icon-check-tick sui-lg sui-success" aria-hidden="true"></span>
				<?php endif; ?>
			</span>
		</li>
		<li>
			<span class="sui-list-label"><?php esc_html_e( 'Gravatar Caching', 'wphb' ); ?></span>
			<span class="sui-list-detail">
				<?php if ( $gravatar ) : ?>
					<span class="sui-icon-check-tick sui-lg sui-success" aria-hidden="true"></span>
				<?php else : ?>
					<div class="sui-tag sui-tag-disabled"><?php esc_html_e( 'Inactive', 'wphb' ); ?></div>
				<?php endif; ?>
			</span>
		</li>
		<li>
			<span class="sui-list-label"><?php esc_html_e( 'RSS Caching', 'wphb' ); ?></span>
			<span class="sui-list-detail">
				<?php
				printf(
					/* translators: %d - number of minutes */
					esc_html__( '%d minutes', 'wphb' ),
					absint( $rss ) / 60
				);
				?>
			</span>
		</li>
	</ul>
</div>
