<?php
/**
 * Uses passive event listeners audit.
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
	<?php esc_html_e( "When you interact with a webpage (like scrolling or touching), the browser uses event listeners to trigger JavaScript functions that respond to those interactions. By default, these listeners can sometimes block the browser from smoothly scrolling the page, especially on touch devices. Marking your event listeners as 'passive' tells the browser that your JavaScript won't prevent the page from scrolling, allowing for a smoother and more responsive experience.", 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your event listeners are optimized for smooth scrolling!', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Some of your event listeners are not marked as passive, which could potentially hinder smooth scrolling on your page.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Location', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_html( $item->source->url ); ?>" target="_blank">
							<?php echo esc_html( $item->source->url ); ?>
						</a>
					</td>
					<td><?php echo esc_html( 'line: ' . $item->source->line ); ?></td>
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
				esc_html__( '%1$s Identify touch and wheel listeners:%2$s Review your JavaScript code and identify event listeners that are attached to touch or wheel events (e.g., touchstart, touchmove, wheel).', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Add the passive option:%2$s To fix this audit, add a passive: true flag to every flagged event listener', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
			<code> document.addEventListener('touchstart', onTouchStart, {passive: true}); </code>
		</li>
	</ol>
<?php endif; ?>
