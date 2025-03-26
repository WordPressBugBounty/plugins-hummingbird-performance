<?php
/**
 * LCP-lazy-loaded audit.
 *
 * @since 3.11.0
 * @package Hummingbird
 *
 * @var stdClass $audit Audit object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url = \Hummingbird\Core\Utils::get_admin_menu_url( 'minification' );

?>

<h4><?php esc_html_e( 'Overview', 'wphb' ); ?></h4>
<p>
	<?php esc_html_e( 'Lazy loading is a technique that defers the loading of images until they are needed (typically when they are about to scroll into view). This can improve initial page load time. However, if you lazy load your Largest Contentful Paint (LCP) image, which is usually the most prominent image above the fold, it can actually delay the LCP and make your page feel slower.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your LCP image is not lazily loaded, which helps it render quickly.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Your LCP image is being lazily loaded. This can negatively impact your LCP score.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( isset( $audit->details ) && isset( $audit->details->items ) ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Element', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<tr>
					<td> <?php echo esc_html( $item->node->snippet ); ?> </td>
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
				esc_html__( '%1$s Identify your LCP image:%2$s Use browser developer tools to determine which image is the LCP element on your page.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Remove lazy loading:%2$s If your LCP image has a `loading="lazy"` attribute in the `<img>` tag, remove it.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Eager load the LCP image:%2$s Ensure your LCP image is loaded normally (without lazy loading) so it\'s prioritized by the browser', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
<?php endif; ?>
