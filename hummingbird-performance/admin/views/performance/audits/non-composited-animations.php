<?php
/**
 * Non-composited animations audit.
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
	<?php esc_html_e( 'Compositing is a technique browsers use to render animations smoothly. When animations are not composited, they can appear jerky or cause unexpected shifts in the page layout, leading to a poor user experience. This can negatively impact your Cumulative Layout Shift (CLS) score, which measures the visual stability of your page.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'All animations on your site are composited! This means smoother animations and a better user experience.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Some animations on your site are not composited, which could lead to jerky animations and affect your CLS score.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score ?? 0 )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Element', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Name', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<?php if ( isset( $item->subItems->items ) && is_array( $item->subItems->items ) ) : ?>
					<?php foreach ( $item->subItems->items as $sub_item ) : ?>
						<tr>
						<td><?php echo esc_html( $sub_item->failureReason ); ?></td>
							<td><?php echo esc_html( $sub_item->animation ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h4><?php esc_html_e( 'How to fix', 'wphb' ); ?></h4>
	<ol>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Use transform animations:%2$s Animations that use the transform property (e.g., translate, scale, rotate) are generally composited by default.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Promote elements to their own layers:%2$s You can force an element to be composited by using the will-change CSS property. However, use this property sparingly as it can increase memory usage.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Avoid animating properties that trigger layout changes:%2$s Animating properties like width, height, or margin can cause the browser to recalculate the layout, leading to non-composited animations. Instead, try animating properties that only affect the paint or composite steps, such as opacity or transform.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Keep animations simple:%2$s Complex animations are more likely to be non-composited. Try to simplify your animations or break them down into smaller, more manageable parts.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
<?php endif; ?>
