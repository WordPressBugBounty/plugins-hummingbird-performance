<?php
/**
 * Avoid large layout shifts.
 *
 * @since 3.11.0
 * @package Hummingbird
 *
 * @var stdClass $audit  Audit object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url = \Hummingbird\Core\Utils::get_admin_menu_url( 'minification' );

?>

<h4><?php esc_html_e( 'Overview', 'wphb' ); ?></h4>
<p>
	<?php esc_html_e( "By default, a browser must download, parse, and process all the external stylesheets it encounters before it can be rendered on a user's screen. Removing or deferring unused rules in your stylesheet makes it load faster.", 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Nice! you have passed the audit.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Avoid large layout shifts.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score ?? 0 )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
				<th><?php esc_html_e( 'Cause', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<?php if ( isset( $item->subItems->items ) && is_array( $item->subItems->items ) ) : ?>
					<?php foreach ( $item->subItems->items as $sub_item ) : ?>
						<tr>
							<td>
								<?php
									$item_link = $sub_item->extra->value ?? $sub_item->extra->snippet ?? '';
								?>
								<a href="<?php echo esc_url( $item_link ); ?>" target="_blank">
									<?php echo esc_html( $item_link ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $sub_item->cause ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h4><?php esc_html_e( 'How to fix', 'wphb' ); ?></h4>
	<ol>
		<li><?php esc_html_e( "Use Hummingbird's Asset Optimization module to move critical styles inline.", 'wphb' ); ?></li>
		<li><?php esc_html_e( 'Combine non-critical styles, compress your stylesheets, and move them into the footer.', 'wphb' ); ?></li>
	</ol>
	<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" class="sui-button">
			<?php esc_html_e( 'Configure Asset Optimization', 'wphb' ); ?>
		</a>
	<?php endif; ?>
<?php endif; ?>
