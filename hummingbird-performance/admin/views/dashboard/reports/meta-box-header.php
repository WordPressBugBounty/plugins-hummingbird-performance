<?php
/**
 * Notifications meta box header on dashboard page.
 *
 * @package Hummingbird
 *
 * @var string $title  Meta box title.
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h3  class="sui-box-title"><?php echo esc_html( $title ); ?></h3>
<?php if ( ! Utils::has_access_to_hub() ) : ?>
	<span class="sui-tag hb-tag-blue sui-tag-sm sui-tag-ghost" style="margin-left: 10px">
		<?php esc_html_e( 'Connect', 'wphb' ); ?>
	</span>
<?php endif; ?>
