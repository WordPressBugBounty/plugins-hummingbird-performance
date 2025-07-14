<?php
/**
 * Notifications template: header.
 *
 * @since 3.1.1
 * @package Hummingbird
 *
 * @var string $back  Previous slide, for back button.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<button class="sui-button-icon sui-button-float--right" onclick="location.href = wphb.links.notifications;">
	<span class="sui-icon-close sui-md" aria-hidden="true"></span>
	<span class="sui-screen-reader-text"><?php esc_html_e( 'Close this modal', 'wphb' ); ?></span>
</button>

<?php if ( isset( $back ) && $back ) : ?>
	<button class="sui-button-icon sui-button-float--left" data-modal-slide="<?php echo esc_attr( $back ); ?>" data-modal-slide-intro="back">
		<span class="sui-icon-chevron-left sui-md" aria-hidden="true"></span>
		<span class="sui-screen-reader-text"><?php esc_html_e( 'Go back', 'wphb' ); ?></span>
	</button>
<?php endif; ?>
