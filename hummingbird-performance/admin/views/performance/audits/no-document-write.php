<?php
/**
 * No document write audit.
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
	<?php esc_html_e( 'document.write() is an outdated method for adding content to a webpage. While it might seem convenient, it can cause serious performance issues, especially for users with slow connections. When document.write() is used to inject external scripts, it can block the page from rendering until those scripts are downloaded and executed, leading to significant delays and a poor user experience.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site is not using document.write(), which is great for performance!', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Hummingbird has detected the use of document.write() on your site. Consider removing or replacing it to improve page load times.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Source', 'wphb' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit->details->items as $item ) : ?>
				<tr>
					<td>
						<?php
						$formatted_url = 'N/A';
						if ( isset( $item->source->url, $item->source->line, $item->source->column ) ) {
							$formatted_url = rtrim( $item->source->url, '/' ) . ':' . $item->source->line . ':' . $item->source->column;
						}
						?>
						<a href="<?php echo esc_html( $item->source->url ); ?>" target="_blank">
							<?php echo esc_html( $formatted_url ); ?>
						</a>
					</td>
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
				esc_html__( '%1$s Avoid injecting external scripts with document.write():%2$s If you\'re using document.write() to add external scripts, find alternative methods such as adding the <script> tag directly in your HTML or using asynchronous loading techniques.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Use modern methods for adding content:%2$s Instead of document.write(), use modern DOM manipulation techniques like innerHTML or appendChild to add content to your page.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( '%1$s Review third-party scripts:%2$s Some third-party scripts might use document.write(). If possible, find alternative scripts or contact the provider to see if they offer a version that avoids this method.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
<?php endif; ?>
