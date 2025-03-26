<?php
/**
 * Largest Contentful Paint image audit.
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
		'enable-advanced-settings' => 'true',
		'_wpnonce'                 => wp_create_nonce( 'wphb-enable-advanced-settings' ),
	),
	\Hummingbird\Core\Utils::get_admin_menu_url( 'minification' )
);

?>

<h4><?php esc_html_e( 'Overview', 'wphb' ); ?></h4>
<p>
	<?php esc_html_e( 'The Largest Contentful Paint (LCP) image is often the most prominent visual element on your page, and its loading time significantly impacts how quickly users perceive your page as loaded. If this image is added dynamically (meaning it\'s not immediately present in the HTML), the browser might not prioritize its loading. By preloading the LCP image, you instruct the browser to fetch it early on, improving your LCP score and overall user experience.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'You\'re preloading your LCP image! This helps it load quickly and improves how fast your page feels.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Your LCP image is not being preloaded. Preloading it can significantly improve your LCP score.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( isset( $audit->score ) ? $audit->score : 100 )
	);
	?>

	<?php if ( isset( $audit->details ) && isset( $audit->details->items ) ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
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
					<td><?php echo esc_html( round( $item->wastedMs, -1 ) . ' ms' ); ?></td>
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
				esc_html__( '%1$s Identify the LCP Image:%2$s Use browser developer tools to determine which image is the LCP element on your page.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Preload the Image in HTML:%2$s Add the following <link> tag inside the <head> of your HTML file:', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
			<code>&lt;link rel="preload" as="image" href="path/to/image.jpg"&gt;</code>
		</li>
	</ol>
<?php endif; ?>
