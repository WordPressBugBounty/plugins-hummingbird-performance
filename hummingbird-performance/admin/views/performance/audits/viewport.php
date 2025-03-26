<?php
/**
 * Viewport audit.
 *
 * @since 3.11.0
 * @package Hummingbird
 *
 * @var stdClass $audit Audit object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url = \Hummingbird\Core\Utils::get_admin_menu_url( 'advanced' );

?>

<h4><?php esc_html_e( 'Overview', 'wphb' ); ?></h4>
<p>
	<?php esc_html_e( 'The <meta name="viewport"> tag tells browsers how to scale your website on different devices, especially important for mobile phones. Without it, your site might appear zoomed in or out on smaller screens, making it difficult to navigate and interact with. This tag also helps prevent a delay in how quickly your site responds to user input, like taps.', 'wphb' ); ?>
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
	<?php $this->admin_notices->show_inline( esc_html__( 'Your site has a correctly configured <meta name="viewport"> tag! This ensures your site looks good and responds quickly on all devices.', 'wphb' ) ); ?>
<?php else : ?>
	<?php
	$this->admin_notices->show_inline(
		esc_html__( 'Your site is missing the <meta name="viewport"> tag or it\'s not configured correctly. This can lead to a poor user experience on mobile devices and slow down how quickly your site reacts to user actions.', 'wphb' ),
		\Hummingbird\Core\Modules\Performance::get_impact_class( $audit->score )
	);
	?>

	<h4><?php esc_html_e( 'How to fix', 'wphb' ); ?></h4>
	<ol>
		<li>
			<?php
			printf( /* translators: %1$s -  opening <strong> tag, %2$s - closing </strong> tag */
				esc_html__( 'You can add the <meta name="viewport"> tag in your in your page\'s <head> like %1$s <meta name="viewport" content="width=device-width, initial-scale=1"> %2$s', 'wphb' ),
				'<code>',
				'</code>'
			);
			?>
		</li>
	</ol>
	<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" class="sui-button">
			<?php esc_html_e( 'Configure Viewport', 'wphb' ); ?>
		</a>
	<?php endif; ?>
<?php endif; ?>
