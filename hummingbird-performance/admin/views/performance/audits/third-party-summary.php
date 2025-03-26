<?php
/**
 * Minimize third-party usage audit.
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
	<?php esc_html_e( 'Third-party resources are external files like scripts, stylesheets, and iframes that load from a different domain than your website. These can include things like analytics trackers, social media widgets, and ad networks. While they can add valuable functionality, they can also significantly slow down your website, especially if you have many of them.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site has minimal third-party resources, which is great for performance!', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		sprintf( /* translators: %s - properly formatted bytes value */
			esc_html__( 'Your site loads %s third-party resources. Consider reducing this number or optimizing how they load to improve performance.', 'wphb' ),
			esc_html( \Hummingbird\Core\Utils::format_bytes( $audit->details->summary->wastedBytes, 0 ) )
		),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Transfer Size', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Blocking Time', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<?php foreach ( $item->subItems->items as $item ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_html( $item->url ); ?>" target="_blank">
								<?php echo esc_html( $item->url ); ?>
							</a>
						</td>
						<td><?php echo esc_html( \Hummingbird\Core\Utils::format_bytes( $item->transferSize ) ); ?></td>
						<td><?php echo esc_html( round( $item->blockingTime ) . ' ms' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h4><?php esc_html_e( 'How to fix', 'wphb' ); ?></h4>
	<ol>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Delay the Loading of Third-Party Scripts:%2$s Use the async or defer attributes in HTML to load third-party scripts without slowing down your website.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Self-Host Critical Third-Party JavaScript Files:%2$s Hosting third-party scripts on your own server gives you greater control over their loading behavior. This reduces DNS lookups and round-trip times, improves HTTP caching, and enables advanced optimizations like HTTP/2 server push.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Remove Unnecessary Third-Party Scripts:%2$s If a third-party script doesn’t provide clear value to your site or users, consider removing it. Many WordPress themes and plugins load unnecessary scripts that may not be essential, impacting performance without benefit. Regularly audit and eliminate such scripts to keep your site lean and fast.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Lazy-Load Third-Party JavaScript:%2$s Embedded third-party elements like ads and videos can significantly impact performance, especially if they come from poorly optimized sources. To improve page load speed, lazy-load these resources so they only load when needed. For example, if you display ads in the footer, you can lazy-load them to load only when the user scrolls down, reducing initial load time and improving user experience.', 'wphb' ),
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
