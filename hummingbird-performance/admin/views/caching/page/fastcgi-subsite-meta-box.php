<?php
/**
 * Page caching meta box.
 *
 * @package Hummingbird
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p><?php echo esc_html( Utils::get_page_cache_description() ); ?></p>
<?php
	$this->admin_notices->show_inline( esc_html__( 'Static Server Cache is currently active. By default your subsite inherits your network admin’s cache settings.', 'wphb' ) );
?>
