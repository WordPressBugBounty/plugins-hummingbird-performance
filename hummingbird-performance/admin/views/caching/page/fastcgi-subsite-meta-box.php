<?php
/**
 * Page caching meta box.
 *
 * @package Hummingbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p><?php esc_html_e( 'Hummingbird stores static HTML copies of your pages and posts to decrease page load time.', 'wphb' ); ?></p>
<?php
	$this->admin_notices->show_inline( esc_html__( 'Static Server Cache is currently active. By default your subsite inherits your network admin’s cache settings.', 'wphb' ) );
?>
