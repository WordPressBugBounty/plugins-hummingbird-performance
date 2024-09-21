<?php
/**
 * Page caching header meta box.
 *
 * @package Hummingbird
 *
 * @var string $title                 Module title.
 * @var bool   $has_fastcgi           Has FastCGI enabled.
 * @var bool   $is_fast_cgi_supported Is FastCGI supported.
 * @var bool   $is_subsite            Is subsite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h3  class="sui-box-title"><?php echo esc_html( $title ); ?></h3>
<div class="sui-actions-right">
	<?php if ( $is_fast_cgi_supported && ! $is_subsite ) { ?>
		<?php
			$tooltip_message = $has_fastcgi ? __( 'Use site level cache instead of server level cache. Use this if you’re facing issues with Static Server Cache.', 'wphb' ) : __( 'Switch to Static Server Cache to support up to 10 times more concurrent visitors.', 'wphb' );
			$method_name     = $has_fastcgi ? 'local_page_cache' : 'hosting_static_cache';
		?>
		<span class="sui-tooltip sui-tooltip-constrained sui-tooltip-bottom" id="wphb_switch_cache_info" data-tooltip="<?php echo esc_html( $tooltip_message ); ?>">
			<span class="sui-icon-info" aria-hidden="true"></span>
		</span>
		<a class="sui-actions-right sui-sm" style="margin-right: 10px;" href="javascript:void(0);"
			id="wphb-switch-page-cache-method" data-method="<?php echo esc_attr( $method_name ); ?>"
			aria-hidden="true"
			>
			<span class="sui-description" style="margin: 0 0 0 5px;">
				<?php
					$method_title = $has_fastcgi ? __( 'Local Page Cache', 'wphb' ) : __( 'Static Server Cache', 'wphb' );
					$style_attr   = $has_fastcgi ? 'font-weight:700; color:#888888' : 'font-weight:700; color:#17A8E3';
					printf(
						/* translators: Switch Page cache method link */
						esc_html__( 'Switch to %s method', 'wphb' ),
						'<span style="' . esc_attr( $style_attr ) . '">' . esc_html( $method_title ) . '</span>'
					);
				?>
			</span>
			<!-- Loading State Content -->
			<span class="sui-button-text-onload" style="display: none;">
				<span class="sui-icon-loader sui-loading" aria-hidden="true"></span>
				<?php esc_html_e( 'Switching', 'wphb' ); ?>
			</span>
		</a>
	<?php } ?>
</div>
