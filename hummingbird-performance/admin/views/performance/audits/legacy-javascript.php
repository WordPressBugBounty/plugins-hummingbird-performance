<?php
/**
 * Legacy JavaScript audit.
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
	<?php esc_html_e( "Modern browsers support the latest JavaScript features, while older browsers often need extra code (polyfills and transforms) to understand them. Serving this extra code to modern browsers is inefficient and can slow down your page load time. By using a modern script deployment strategy, you can send only the necessary code to each browser, improving performance for users with modern browsers.", 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site is using a modern script deployment strategy, delivering optimized JavaScript code to modern browsers.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Your site is serving legacy JavaScript code to modern browsers. This can slow down page load times. Consider implementing a modern script deployment strategy to deliver optimized code to different browsers.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
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
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Use module/nomodule pattern:%2$s This involves creating two versions of your JavaScript files: one with modern code (using type="module") and one with legacy code and polyfills (using type="nomodule"). Modern browsers will only load the modern version, while older browsers will load the legacy version.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %3$s - closing </strong> tag */
				esc_html__( '%1$s Differential serving:%2$s Use a build tool or bundler to create separate bundles for modern and legacy browsers and configure your server to serve the appropriate bundle based on the user\'s browser.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
		</li>
	</ol>
<?php endif; ?>
