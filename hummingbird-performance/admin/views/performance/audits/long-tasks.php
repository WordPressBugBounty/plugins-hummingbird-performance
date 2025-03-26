<?php
/**
 * Long main-thread tasks audit.
 *
 * @since 3.11.0
 * @package Hummingbird
 *
 * @var stdClass $audit Audit object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url = add_query_arg(
	array(
		'view' => 'tools',
	),
	\Hummingbird\Core\Utils::get_admin_menu_url( 'minification' )
);

?>

<h4><?php esc_html_e( 'Overview', 'wphb' ); ?></h4>
<p>
	<?php esc_html_e( 'Every website has a "main thread" where the browser handles tasks like rendering the page, running JavaScript code, and responding to user interactions. When tasks on this main thread take too long, it can lead to delays and a sluggish feeling for your visitors, especially when they try to interact with your site. This audit helps pinpoint those long tasks so you can optimize them.', 'wphb' ); ?>
</p>

<h4><?php esc_html_e( 'Status', 'wphb' ); ?></h4>
<?php if ( isset( $audit->errorMessage ) && ! isset( $audit->score ) ) {
	$this->admin_notices->show_inline( /* translators: %s - error message */
		sprintf( esc_html__( 'Error: %s', 'wphb' ), esc_html( $audit->errorMessage ) ),
		'error'
	);
	return;
}
?>
<?php if ( ! isset( $audit->score ) || 1 === $audit->score ) : ?>
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site has minimal long tasks on the main thread, which is great for responsiveness! ----- Excellent! Your site is keeping main thread tasks short and sweet, which means a smoother experience for your users.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Some tasks on the main thread are taking a long time to complete, which can negatively impact user experience. -------- Hummingbird has detected some long tasks on the main thread that could be slowing down your site and making it less responsive.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score ?? 0 )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Start Time', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_html( $item->url ); ?>" target="_blank">
							<?php echo esc_html( $item->url ); ?>
						</a>
					</td>
					<td><?php echo esc_html( $item->startTime ); ?></td>
					<td><?php echo esc_html( isset( $item->duration ) ? $item->duration : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h4><?php esc_html_e( 'How to fix', 'wphb' ); ?></h4>
	<ol>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Minimize Heavy JavaScript Execution:%2$s Use defer and async attributes, reduce third-party scripts, and break long-running JS tasks.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Optimize CSS & Reduce Render-Blocking Styles:%2$s Minify, inline critical CSS, and remove unused styles.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Optimize Images & Lazy Load:%2$s Use WebP, lazy load images/videos, and serve responsive images.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Reduce Heavy Database Queries:%2$s Optimize WP queries, enable object caching, and disable unnecessary cron jobs.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Use a CDN for Faster Asset Delivery:%2$s Deliver assets from a CDN to reduce load times.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
	<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" class="sui-button">
			<?php esc_html_e( 'Configure Delay JS & Critical CSS', 'wphb' ); ?>
		</a>
	<?php endif; ?>
<?php endif; ?>
