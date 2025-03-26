<?php
/**
 * Critical requests chaining audit.
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
	<?php esc_html_e( 'When your website loads, some resources are essential for the initial display of the page (like HTML, CSS, and key JavaScript files). These are called critical requests. Sometimes, these critical requests depend on each other, creating a chain where one resource can\'t load until the one before it is finished. Long chains of critical requests can significantly delay how quickly your page displays, affecting metrics like Largest Contentful Paint (LCP) and First Contentful Paint (FCP).', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site has efficiently managed critical request chains, leading to faster initial page loads.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Hummingbird has detected critical request chains that could be optimized to improve your page load speed.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->chains ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Size', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Start Time', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'End Time', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $audit->details->chains as $chain ) {
				if ( isset( $chain->children ) && is_object( $chain->children ) ) {
					foreach ( $chain->children as $child ) {
						if ( isset( $child->request ) ) {
							?>
							<tr>
								<td>
									<a href="<?php echo esc_html( $child->request->url ?? null ); ?>" target="_blank">
										<?php echo esc_html( $child->request->url ?? null ); ?>
									</a>
								</td>
								<td><?php echo esc_html( \Hummingbird\Core\Utils::format_bytes( $child->request->transferSize ?? null ) ); ?></td>
								<td><?php echo esc_html( $child->request->startTime ?? null ); ?></td>
								<td><?php echo esc_html( $child->request->endTime ?? null ); ?></td>
							</tr>
							<?php
						}
					}
				}
			}
			?>
			</tbody>
		</table>
	<?php endif; ?>

	<h4><?php esc_html_e( 'How to fix', 'wphb' ); ?></h4>
	<ol>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Remove Unused CSS:%2$s Unused CSS refers to styles that aren’t needed on a page, making files larger and slowing down load times. Use tools like PurifyCSS to scan your site, remove unnecessary CSS, and optimize performance.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Preload Key Requests for Faster Loading:%2$s Preloading critical resources (e.g., scripts, fonts, stylesheets) ensures they load earlier, reducing delays and improving page speed.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Optimize Web Font Loading with font-display:%2$s Use the font-display property in CSS to control how web fonts load and render, improving page performance and user experience.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Minify CSS & JavaScript for Faster Loading:%2$s Minification removes unnecessary characters (e.g., spaces, comments, and line breaks) from CSS and JavaScript files without affecting functionality. This reduces file size, shortens critical request chains, and improves page speed.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
	<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" class="sui-button">
			<?php esc_html_e( 'Configure Delay JS, Critical CSS, & Fonts', 'wphb' ); ?>
		</a>
	<?php endif; ?>
<?php endif; ?>
