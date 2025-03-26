<?php
/**
 * Third-party resources with facades audit.
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
	<?php esc_html_e( 'Third-party embeds (like YouTube videos or social media posts) can be heavy and slow down your page load. A facade is a lightweight placeholder that is displayed instead of the actual embed until the user interacts with it (e.g., clicks on it). This can significantly improve initial page load time.', 'wphb' ); ?>
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
	<?php
	$this->admin_notices->show_inline( esc_html__( 'Great! You\'re already using facades to lazy load third-party resources, which improves initial page load time.', 'wphb' ) );
	?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Some of your third-party embeds could be lazy loaded using facades to improve initial page load time.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score ?? 0 )
	);
	?>

	<?php if ( isset( $audit->details ) && isset( $audit->details->items ) ) : ?>
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
				esc_html__( '%1$s Identify Third-Party Resources to Lazy Load:%2$s Look for third-party embeds that are not critical for the initial page load (e.g., videos below the fold).', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Replace Direct Embeds with Static Placeholders:%2$s Instead of loading the third-party resource immediately, use a static placeholder (image, button, or div) that loads the actual resource only when needed.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
<?php endif; ?>
