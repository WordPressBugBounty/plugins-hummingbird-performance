<?php
/**
 * User Timing audit.
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
	<?php esc_html_e( " The User Timing API allows you to measure the timing of specific events or interactions within your web application. This provides valuable insights into how users experience your site in the real world, beyond standard metrics like LCP or CLS. By setting marks and measures for key user flows (e.g., time to complete a form, time to load a critical section), you can identify performance bottlenecks and optimize your site for a better user experience.", 'wphb' ); ?>
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

<?php if ( $audit->details->items ) : ?>
	<table class="sui-table">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'wphb' ); ?></th>
			<th><?php esc_html_e( 'Type', 'wphb' ); ?></th>
			<th><?php esc_html_e( 'Start Time', 'wphb' ); ?></th>
			<th><?php esc_html_e( 'Duration', 'wphb' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $audit->details->items as $item ) : ?>
			<tr>
				<td><?php echo esc_html( $item->name ); ?></td>
				<td><?php echo esc_html( $item->timingType ); ?></td>
				<td><?php echo esc_html( $item->startTime ); ?></td>
				<td><?php echo esc_html( isset( $item->duration ) ? $item->duration : '' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<p>
	<?php
	printf( /* translators: %1$s - opening a tag, %2$s - closing a tag */
		esc_html__( 'Note: This Audit is purely informative but you can %1$slearn more about it here%2$s.', 'wphb' ),
		'<a href="https://web.dev/articles/usertiming" target="_blank">',
		'</a>'
	);
	?>
</p>
