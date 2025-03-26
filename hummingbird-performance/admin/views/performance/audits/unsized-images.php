<?php
/**
 * Unsized images audit.
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
	<?php esc_html_e( 'When images load on a webpage, they take up space. If the browser doesn\'t know the exact dimensions of an image beforehand, it might have to rearrange the page layout once the image loads, causing content to jump around. This is called a layout shift, and it can be frustrating for users. By specifying the width and height of your images in your HTML code, you give the browser the information it needs to reserve the correct space, preventing these shifts and improving your CLS score.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'All your images have explicit width and height attributes! This helps create a smoother, more stable user experience.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Some of your images are missing width and height attributes. This could lead to layout shifts and negatively impact your CLS score.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<?php if ( $audit->details->items ) : ?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'wphb' ); ?></th>
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
				esc_html__( '%1$s Specify Width & Height for Images/Videos:%2$s Define both width and height attributes for images and videos to ensure proper spacing and prevent layout shifts.', 'wphb' ),
				'<strong>',
				'</strong>'
			);
			?>
			<pre><code>&lt;img src="devpug.jpg" width="640" height="360" alt="Devpug Sleeping"&gt;</code></pre>
		</li>
	</ol>
<?php endif; ?>
