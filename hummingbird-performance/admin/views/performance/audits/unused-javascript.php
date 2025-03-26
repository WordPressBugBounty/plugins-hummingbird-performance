<?php
/**
 * Unused javascript audit.
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
	<?php esc_html_e( 'JavaScript files are essential for interactive features on websites, but unused JavaScript code wastes bandwidth and slows down your page load time. This is because the browser still needs to download, parse, and execute the code, even if it\'s not used. By identifying and removing unused JavaScript, you can significantly improve your website\'s performance, especially for metrics like Largest Contentful Paint (LCP) and First Contentful Paint (FCP).', 'wphb' ); ?>
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
<?php if ( isset( $audit->score ) && 1 === $audit->score ) : ?>
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site has minimal unused JavaScript! This means faster loading times and a better user experience.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		sprintf( /* translators: %s - properly formatted bytes value */
			esc_html__( 'You can save %s by defering or delaying the following JS files.', 'wphb' ),
			esc_html( \Hummingbird\Core\Utils::format_bytes( $audit->details->overallSavingsBytes, 0 ) )
		),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Size', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Savings', 'wphb' ); ?></th>
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
					<td><?php echo esc_html( \Hummingbird\Core\Utils::format_bytes( $item->totalBytes ) ); ?></td>
					<td><?php echo esc_html( \Hummingbird\Core\Utils::format_bytes( $item->wastedBytes ) ); ?></td>
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
				esc_html__( '%1$s Optimize Performance by Delaying JavaScript:%2$s Delaying JavaScript execution helps improve page load speed by preventing unnecessary scripts from loading immediately. Instead, scripts are loaded only when a user interacts with the page (e.g., scrolling, clicking, or tapping). This reduces initial load time, improves Core Web Vitals, and boosts PageSpeed Insights scores.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Load JavaScript Only When Needed:%2$s Many WordPress themes and plugins load JavaScript files across your entire site, even when they are only required on specific pages. This unnecessary loading increases page size, slows down performance, and affects Core Web Vitals. By conditionally loading JavaScript, you ensure that scripts are only executed when they are actually needed. This reduces HTTP requests, improves load times, and optimizes your site\'s performance.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
	<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" class="sui-button">
			<?php esc_html_e( 'Configure Delay JS', 'wphb' ); ?>
		</a>
	<?php endif; ?>
<?php endif; ?>
