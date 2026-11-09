<?php
/**
 * Notices class.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Admin;

use Hummingbird\Admin\Ajax\Notices as React_Notices;
use Hummingbird\Core\Hub_Connector;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\SafeMode;
use Hummingbird\Core\Whitelabel;
use Hummingbird\Core\Modules\Performance;
use WPMUDEV_Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notices
 */
class Notices {
	/**
	 * In order to avoid duplicated notices,
	 * we save notices IDs here
	 *
	 * @var    array $displayed_notices
	 * @access protected
	 */
	protected static $displayed_notices = array();

	/**
	 * Instance of class.
	 *
	 * @since  1.7.0
	 * @access private
	 * @var    $instance
	 */
	private static $instance = null;

	/**
	 * Return the plugin instance.
	 *
	 * @since 1.7.0
	 * @return Notices
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Notices constructor.
	 */
	public function __construct() {
		$current_page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );

		// Do not init notices on setup page.
		if ( 'wphb-setup' === sanitize_text_field( $current_page ) ) {
			return;
		}

		$dismiss = filter_input( INPUT_GET, 'wphb-dismiss', FILTER_UNSAFE_RAW );
		if ( $dismiss ) {
			$this->dismiss( sanitize_text_field( $dismiss ) );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		add_action( 'upgrader_process_complete', array( $this, 'plugin_changed' ) );
		add_action( 'activated_plugin', array( $this, 'plugin_changed' ) );
		add_action( 'deactivated_plugin', array( $this, 'plugin_changed' ) );
		add_action( 'after_switch_theme', array( $this, 'plugin_changed' ) );

		// This will show notice on both multisite and single site.
		add_action( 'admin_notices', array( $this, 'clear_cache' ) );
		add_action( 'network_admin_notices', array( $this, 'clear_cache' ) );
		add_action( 'admin_notices', array( $this, 'safe_mode_notice' ) );
		add_action( 'admin_notices', array( $this, 'cache_global_cleared' ) );

		// Only show notices to users who can do something about it (update, for example).
		$cap = is_multisite() ? 'manage_network_plugins' : 'update_plugins';
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		if ( is_multisite() ) {
			add_action( 'network_admin_notices', array( $this, 'upgrade_to_pro' ) );
			add_action( 'network_admin_notices', array( $this, 'free_version_deactivated' ) );
			add_action( 'network_admin_notices', array( $this, 'site_monitoring' ) );
			add_action( 'network_admin_notices', array( $this, 'plugin_compat_check' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'upgrade_to_pro' ) );
			add_action( 'admin_notices', array( $this, 'free_version_deactivated' ) );
			add_action( 'admin_notices', array( $this, 'site_monitoring' ) );
			add_action( 'admin_notices', array( $this, 'plugin_compat_check' ) );
		}
	}

	/**
	 * Clear the notice blocker on plugin activate/deactivate.
	 *
	 * @since 1.7.0
	 * @used-by activated_plugin action
	 * @used-by deactivated_plugin action
	 */
	public function plugin_changed() {
		$detection = Settings::get_setting( 'detection', 'page_cache' );

		// Do nothing selected in settings.
		if ( 'none' === $detection ) {
			return;
		}

		// Show notice.
		if ( 'manual' === $detection ) {
			update_option( 'wphb-notice-cache-cleaned-show', 'yes' );
			return;
		}

		// Auto clear cache, don't show any notice.
		if ( 'auto' === $detection ) {
			$modules = array( 'page_cache', 'minify' );
			foreach ( $modules as $mod ) {
				$module = Utils::get_module( $mod );
				if ( ! $module->is_active() ) {
					continue;
				}

				// Make sure no settings are cleared during auto page cache purge.
				if ( 'minify' === $mod ) {
					$module->clear_cache( false );
				} else {
					$module->clear_cache();
				}
			}
		}
	}

	/**
	 * Display sui compatible notice with flexibility.
	 *
	 * @since 3.15.0
	 * @access private
	 * @param  string $id             Unique identifier.
	 * @param  string $message        Notice message.
	 * @param  bool   $additional     Additional content that goes after the message text.
	 * @param  bool   $only_hb_pages  Show message only on Hummingbird pages.
	 * @param  string $sui_class       SUI class for the notice.
	 */
	private function show_sui_notice( $id = '', $message = '', $additional = false, $only_hb_pages = false, $sui_class = 'blue' ) {
		// Only run on HB pages.
		if ( $only_hb_pages && ! preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', $id ), 'wphb-dismiss-notice' );
		?>
		<div id="<?php echo esc_attr( $id ); ?>-active-notice" class="sui-wrap notice-<?php echo esc_attr( $id ); ?>">
			<div class="sui-notice sui-notice-<?php echo esc_attr( $sui_class ); ?>">
				<div class="sui-notice-content">
					<div class="sui-notice-message">
							<a class="wphb-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>">
								<span class="sui-icon-close sui-sm"></span>
								<span class="screen-reader-text">
									<?php esc_html_e( 'Dismiss this notice.', 'wphb' ); ?>
								</span>
							</a>
							<?php echo wp_kses_post( $message ); ?>
							<?php if ( $additional ) : ?>
								<p>
									<?php echo wp_kses_post( $additional ); ?>
								</p>
							<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display notice HTML code.
	 *
	 * @since  1.7.0
	 * @access private
	 * @param  string $id             Accepted: upgrade-to-pro, free-deactivated, free-rated, plugin-compat.
	 * @param  string $message        Notice message.
	 * @param  bool   $additional     Additional content that goes after the message text.
	 * @param  bool   $only_hb_pages  Show message only on Hummingbird pages.
	 * @param  string $notice_class    Notice class: info, warning, error, success.
	 * @param  string $dismiss_icon_class  Dashicon class for the dismiss icon.
	 */
	private function show_notice( $id = '', $message = '', $additional = false, $only_hb_pages = false, $notice_class = 'info', $dismiss_icon_class = '' ) {
		// Only run on HB pages.
		if ( $only_hb_pages && ! preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', $id ), 'wphb-dismiss-notice' );
		?>
		<div class="notice-<?php echo esc_attr( $notice_class ); ?> notice wphb-notice">
			<a class="wphb-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>">
				<span class="dashicons <?php echo esc_attr( $dismiss_icon_class ? $dismiss_icon_class : 'dashicons-dismiss' ); ?>"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Dismiss this notice.', 'wphb' ); ?>
				</span>
			</a>
			<?php echo wp_kses_post( $message ); ?>
			<?php if ( $additional ) : ?>
				<p>
					<?php echo wp_kses_post( $additional ); ?>
				</p>
			<?php endif; ?>
		</div>
		<style>
			.wphb-notice .wphb-dismiss {
				color: #aaaaaa;
				float: right;
				padding: 15px;
				position: absolute;
				right: 1px;
				text-decoration: none;
				top: 0;
			}
			body:not(.wpmud) .wphb-notice .wphb-dismiss {
				position: relative;
				padding: 10px 0;
			}
		</style>
		<?php
	}

	/**
	 * Check if a notice has been dismissed by the current user.
	 *
	 * Will accept: 'user' for user options, 'option' for site wide options and
	 *              'site' for sub site options.
	 *
	 * @since  1.7.0 changed to private
	 * @access private
	 * @param  string $notice  Notice.
	 * @param  string $mode    Default: 'user'.
	 * @return mixed
	 */
	private function is_dismissed( $notice, $mode = 'user' ) {
		if ( 'user' === $mode ) {
			return get_user_meta( get_current_user_id(), 'wphb-' . $notice . '-dismissed' );
		}

		if ( 'option' === $mode ) {
			return 'yes' !== get_option( 'wphb-notice-' . $notice . '-show' );
		}

		return false;
	}

	/**
	 * Dismiss a notice.
	 *
	 * @since  1.7.0 changed to private
	 * @access private
	 * @param  string $notice  Notice.
	 */
	private function dismiss( $notice ) {
		check_admin_referer( 'wphb-dismiss-notice' );

		// These notices are also served as toasts in the new UI.
		$react_notices = array(
			'upgrade-to-pro'              => 'user',
			'plugin-compat'               => 'user',
			'free-deactivated'            => 'site',
			'connect-for-site-monitoring' => 'site',
			'cache-cleaned'               => 'site',
		);

		if ( isset( $react_notices[ $notice ] ) ) {
			React_Notices::mark_dismissed( $notice, $react_notices[ $notice ] );

			$redirect = remove_query_arg( array( 'wphb-dismiss', '_wpnonce' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		$user_notices = array(
			'safe-mode-active',
		);

		$options_notices = array(
			'free-rated',
			'legacy-critical-css',
			'cache-global-cleared',
		);

		if ( in_array( $notice, $user_notices, true ) ) {
			update_user_meta( get_current_user_id(), 'wphb-' . $notice . '-dismissed', true );
		} elseif ( in_array( $notice, $options_notices, true ) ) {
			delete_option( 'wphb-notice-' . $notice . '-show' );
			if ( 'free-rated' === $notice ) {
				update_option( 'wphb-notice-free-rated-later_date', 'never' );
			}
		}

		$redirect = remove_query_arg( array( 'wphb-dismiss', '_wpnonce' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Show top floating notice (SUI style).
	 *
	 * @since 2.6.0
	 *
	 * @param string $message     The notice text.
	 * @param string $type        Notice type.
	 * @param bool   $dismissible Whether the notice is dismissible.
	 */
	public function show_floating( $message, $type = 'success', $dismissible = true ) {
		?>
		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				WPHB_Admin.notices.show(
					"<?php echo wp_kses_post( $message ); ?>",
					"<?php echo esc_attr( $type ); ?>",
					<?php echo $dismissible ? 'true' : 'false'; ?>
				);
			} );
		</script>
		<?php
	}

	/**
	 * Show inline notice (SUI style).
	 *
	 * @since 2.6.0
	 *
	 * @param string $message  The notice text.
	 * @param string $class    Class for the notice wrapper.
	 * @param mixed  ...$data  Variable list of addition text.
	 */
	public function show_inline( $message, $class = 'success', ...$data ) {
		if ( 'sui-upsell-notice' === $class ) {
			$this->show_inline_upsell( $message, ...$data );
			return;
		}
		?>
		<div class="sui-notice sui-notice-<?php echo esc_attr( $class ); ?>">
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php echo wp_kses_post( $message ); ?></p>
					<?php foreach ( $data as $p ) : ?>
						<?php if ( ! empty( $p ) ) : ?>
							<?php echo '<p>' . $p . '</p>'; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show inline upsell notice (SUI style).
	 *
	 * This is an upsell implementation of an upsell notice to show with an image on the left side.
	 * Can be triggered by calling show_inline() with a 'sui-upsell-notice' $class as an argument.
	 *
	 * @since 2.6.0
	 *
	 * @param string $message  The notice text.
	 * @param mixed  ...$data  Variable list of addition text.
	 */
	private function show_inline_upsell( $message, ...$data ) {
		?>
		<div class="sui-upsell-notice">
			<p>
				<?php echo wp_kses_post( $message ); ?>
				<?php foreach ( $data as $p ) : ?>
					<?php echo wp_kses_post( $p ); ?>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if the notice can be displayed.
	 *
	 * @since 2.6.0  Refactored from show().
	 *
	 * @param string $id  Unique identifier for the notice.
	 *
	 * @return bool
	 */
	public function can_show_notice( $id ) {
		// Is already dismissed ?
		if ( $this->is_dismissed( $id, 'option' ) ) {
			return false;
		}

		if ( in_array( $id, self::$displayed_notices, true ) ) {
			return false;
		}

		self::$displayed_notices[] = $id;

		return true;
	}

	/**
	 * Show inline dismissible notice (SUI style).
	 *
	 * @since 2.6.0  Refactored from show().
	 *
	 * @param string $id       Unique identifier for the notice.
	 * @param string $message  The notice text.
	 * @param string $class    Class for the notice wrapper.
	 */
	public function show_inline_dismissible( $id, $message, $class = 'sui-notice-error' ) {
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			return;
		}

		// Is already dismissed ?
		if ( $this->is_dismissed( $id, 'option' ) ) {
			return;
		}

		if ( in_array( $id, self::$displayed_notices, true ) ) {
			return;
		}

		self::$displayed_notices[] = $id;
		?>
		<div class="sui-notice <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $id ); ?>" role="alert" style="display: block">
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php echo wp_kses_post( $message ); ?></p>
					<p>
						<a role="button" href="#" style="color: #888;text-transform: uppercase" onclick="WPHB_Admin.notices.dismiss( this )">
							<?php esc_html_e( 'Dismiss', 'wphb' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * *************************
	 * NOTICES
	 ***************************/

	/**
	 * Available notices.
	 *
	 * @see Notices::upgrade_to_pro()
	 * @see Notices::free_version_deactivated()
	 * @see Notices::get_free_rated_notice()
	 */

	/**
	 * Build the Upgrade to Pro notice payload.
	 *
	 * The new UI's React notice store (`React_Notices`) is the single source
	 * of truth for dismissal going forward.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_upgrade_to_pro_notice() {
		if ( ! React_Notices::is_dismissed( 'upgrade-to-pro' ) && $this->is_dismissed( 'upgrade-to-pro' ) ) {
			React_Notices::mark_dismissed( 'upgrade-to-pro', 'user' );
		}

		if ( React_Notices::is_dismissed( 'upgrade-to-pro' ) ) {
			return array();
		}

		if ( ! class_exists( 'WPMUDEV_Dashboard' ) ) {
			return array();
		}

		$dashboard = WPMUDEV_Dashboard::instance();
		if ( ! is_object( $dashboard ) ) {
			return array();
		}

		if ( ! ( defined( 'WPHB_WPORG' ) && WPHB_WPORG && Utils::is_member() ) ) {
			return array();
		}

		return array(
			'id'          => 'upgrade-to-pro',
			'title'       => __( 'Upgrade to Pro', 'wphb' ),
			'description' => Whitelabel::replace_branding_terms( __( 'Awww yeah! You’ve got access to Hummingbird Pro! Let’s upgrade your free version so you can start using premium features.', 'wphb' ) ),
			'scope'       => 'user',
			'actions'     => array(
				array(
					'label' => __( 'Upgrade', 'wphb' ),
					'url'   => esc_url( WPMUDEV_Dashboard::$ui->page_urls->plugins_url ),
				),
			),
		);
	}

	/**
	 * Show Upgrade to Pro notice
	 *
	 * User is authenticated into WPMU DEV but it has free version installed.
	 */
	public function upgrade_to_pro() {
		$notice = $this->get_upgrade_to_pro_notice();
		if ( empty( $notice ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$action  = isset( $notice['actions'][0] ) ? $notice['actions'][0] : null;
		$message = esc_html( $notice['description'] );
		if ( $action ) {
			$message .= ' <a href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a>';
		}

		$this->show_notice( 'upgrade-to-pro', '<p>' . $message . '</p>', false, true );
	}

	/**
	 * Build the Free version deactivated notice payload.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_free_deactivated_notice() {
		if ( ! file_exists( WP_PLUGIN_DIR . '/hummingbird-performance/wp-hummingbird.php' ) ) {
			return array();
		}

		// If the Pro version files are not there, or plugin is not active - bail.
		if ( ! file_exists( WP_PLUGIN_DIR . '/wp-hummingbird/wp-hummingbird.php' ) || ! is_plugin_active( 'wp-hummingbird/wp-hummingbird.php' ) ) {
			// Probably a stored notice from a previous install - remove the notice.
			delete_site_option( 'wphb-notice-free-deactivated-show' );
			return array();
		}

		if ( ! React_Notices::is_dismissed( 'free-deactivated' ) && $this->is_dismissed( 'free-deactivated', 'option' ) ) {
			React_Notices::mark_dismissed( 'free-deactivated', 'site' );
		}

		if ( React_Notices::is_dismissed( 'free-deactivated' ) ) {
			return array();
		}

		return array(
			'id'          => 'free-deactivated',
			'title'       => __( 'Free version deactivated', 'wphb' ),
			'description' => Whitelabel::replace_branding_terms( __( 'We noticed you’re running both the free and pro versions of Hummingbird. No biggie! We’ve deactivated the free version for you. Enjoy the pro features!', 'wphb' ) ),
			'scope'       => 'site',
		);
	}

	/**
	 * Notice displayed when the free version is deactivated because the pro one was already active
	 *
	 *  (see Page::get_global_notices()), so bail out here to
	 * avoid a duplicate notice.
	 */
	public function free_version_deactivated() {
		$notice = $this->get_free_deactivated_notice();
		if ( empty( $notice ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$this->show_notice(
			'free-deactivated',
			'<p>' . esc_html( $notice['description'] ) . '</p>'
		);
	}

	/**
	 * Build the Site Monitoring notice payload.
	 *
	 * User is not authenticated into WPMU DEV and it has a report.
	 *
	 * The new UI's React notice store (`React_Notices`) is the single source
	 * of truth for dismissal going forward. The legacy site option is only
	 * read here to migrate a pre-existing dismissal into the new store once.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_site_monitoring_notice() {
		$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		if ( 'wphb-performance' !== $page ) {
			return array();
		}

		if ( ! React_Notices::is_dismissed( 'connect-for-site-monitoring' ) && $this->is_dismissed( 'connect-for-site-monitoring', 'option' ) ) {
			React_Notices::mark_dismissed( 'connect-for-site-monitoring', 'site' );
		}

		if ( React_Notices::is_dismissed( 'connect-for-site-monitoring' ) ) {
			return array();
		}

		if ( ! Settings::get( 'wphb-last-report' ) || Utils::has_access_to_hub() ) {
			return array();
		}

		if ( is_multisite() && ! is_network_admin() ) {
			return array();
		}

		return array(
			'id'          => 'connect-for-site-monitoring',
			'title'       => esc_html__( 'Connect now for free', 'wphb' ),
			'description' => esc_html__( 'Get notified instantly when your site is down or too slow — and act fast to keep visitors happy. All you need is a free WPMU DEV account.', 'wphb' ),
			'scope'       => 'site',
			'actions'     => array(
				array(
					'label' => esc_html__( 'Connect Site to Activate', 'wphb' ),
					'url'   => esc_url( Hub_Connector::get_connect_site_url( 'wphb-uptime', 'hummingbird_performance-test_uptime-prompt' ) ),
				),
			),
		);
	}

	/**
	 * Show Site Monitoring to Performance report.
	 *
	 * Served via the `wphbPage.notices`
	 * payload instead (see Page::get_global_notices()), so bail out here.
	 */
	public function site_monitoring() {
		$notice = $this->get_site_monitoring_notice();
		if ( empty( $notice ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$action = $notice['actions'][0];

		$image_part = sprintf(
			'<img class="sui-image" aria-hidden="true" alt="" src="%1$s" srcset="%1$s 1x, %2$s 2x" />',
			esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@1x.png' ),
			esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@2x.png' )
		);
		$heading    = esc_html__( 'Free 24/7 Site Monitoring', 'wphb' );
		$link_icon  = '<span class="sui-icon-link" aria-hidden="true"></span>';

		$message  = '<div class="wphb-wrapper"> <div>' . $image_part . '</div>
		<div><h4>' . $heading . '</h4> <p>' . esc_html( $notice['description'] ) . '</p>';
		$message .= '<a class="sui-button sui-button-blue" href="' . esc_url( $action['url'] ) . '"> ' . $link_icon . esc_html( $action['label'] ) . ' </a></div></div>';

		$this->show_sui_notice( 'connect-for-site-monitoring', $message, false, true );
	}

	/**
	 * Build the "Rate Hummingbird" notice payload.
	 *
	 * This is the single source of truth for the show/hide conditions and
	 * copy for Page::get_rate_notice()
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_free_rated_notice() {
		if ( Utils::is_admin_dashboard() ) {
			return array();
		}

		if ( $this->is_dismissed( 'free-rated', 'option' ) ) {
			return array();
		}

		$minify_module = Utils::get_module( 'minify' );
		if ( $minify_module->scanner->is_scanning() ) {
			return array();
		}

		// Show only if at least 7 days have past after installation of the free version.
		$rate_later = get_site_option( 'wphb-notice-free-rated-later_date' );
		if ( time() < (int) $rate_later ) {
			return array();
		}

		$title       = '';
		$description = Whitelabel::replace_branding_terms( __( 'We\'ve spent countless hours developing Hummingbird and making it free for you to use. We would really appreciate it if you dropped us a quick rating!', 'wphb' ) );

		$saved_scores         = get_site_option( 'wphb-notice-free-rated-last-score', false );
		$last_report_scores   = Performance::get_last_report_scores();
		$performance_improved = false;

		if ( $minify_module->is_active() && $last_report_scores && $saved_scores ) {
			$types = array( 'mobile', 'desktop' );
			foreach ( $types as $type ) {
				if ( 0 !== $last_report_scores[ $type ] && 0 !== $saved_scores[ $type ] && $last_report_scores[ $type ] - $saved_scores[ $type ] >= 10 ) {
					$performance_improved = $last_report_scores[ $type ] > $performance_improved ? $last_report_scores[ $type ] : $performance_improved;
				}
			}
		}

		if ( $performance_improved ) {
			/* translators: %s: performance score. */
			$title       = sprintf( __( 'Performance improved! Your site’s score is now %s.', 'wphb' ), $performance_improved );
			$description = Whitelabel::replace_branding_terms( __( 'Your site is now faster and more efficient thanks to Hummingbird’s optimizations. Enjoying the results? Share your experience with a review on WordPress.org.', 'wphb' ) );
		}

		return array(
			'id'          => 'free-rated',
			'title'       => $title,
			'description' => $description,
		);
	}

	/**
	 * Whether to show the "Share Usage Data?" notice (title/description are
	 * static strings on the frontend). Hidden once tracking is on or dismissed.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	public function get_share_usage_data_notice() {
		if ( Utils::is_subsite() || Settings::get_setting( 'tracking', 'settings' ) ) {
			return false;
		}

		return ! React_Notices::is_dismissed( 'share-usage-data' );
	}

	/**
	 * Build the "Clear Cache" notice payload.
	 *
	 * Shown after a plugin/theme change (or when the cache-detection setting
	 * is 'manual') that may require clearing Hummingbird's cache.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_cache_cleaned_notice() {
		if ( ! React_Notices::is_dismissed( 'cache-cleaned' ) && $this->is_dismissed( 'cache-cleaned', 'option' ) ) {
			React_Notices::mark_dismissed( 'cache-cleaned', 'site' );
		}

		if ( React_Notices::is_dismissed( 'cache-cleaned' ) ) {
			return array();
		}

		// Only show if minification or page cache is enabled.
		$minify_active  = Utils::get_module( 'minify' )->is_active();
		$caching_active = Utils::get_module( 'page_cache' )->is_active();

		// If both modules disabled - don't show notice.
		if ( ! $minify_active && ! $caching_active ) {
			return array();
		}

		$actions = array();

		if ( $minify_active ) {
			$description = Whitelabel::replace_branding_terms( __( "We've noticed you've made changes to your website. If you’ve installed new plugins or themes, we recommend you re-check Hummingbird's Asset Optimization configuration to ensure those new files are added	correctly.", 'wphb' ) );

			$recheck_url = add_query_arg(
				array(
					'recheck-files' => 'true',
					'_wpnonce'      => wp_create_nonce( 'wphb-recheck-files' ),
				),
				Utils::get_admin_menu_url( 'minification' )
			);

			$actions[] = array(
				'label' => __( 'Re-check Asset Optimization', 'wphb' ),
				'url'   => esc_url( $recheck_url ),
			);
		} else {
			$description = Whitelabel::replace_branding_terms( __( "We've noticed you've made changes to your website. We recommend you clear Hummingbird's page cache to avoid any issues.", 'wphb' ) );
		}

		// Mirrors the legacy notice's always-present "Clear Cache" button
		// (bound to an instant AJAX purge there); here it links to the
		// Caching page instead, since the generic toast only supports
		// URL-opening actions (see usePageNotices.jsx).
		$actions[] = array(
			'label' => __( 'Clear Cache', 'wphb' ),
			'url'   => esc_url( Utils::get_admin_menu_url( 'caching' ) ),
		);

		return array(
			'id'          => 'cache-cleaned',
			'description' => $description,
			'scope'       => 'site',
			'actions'     => $actions,
		);
	}

	/**
	 * Show clear cache notice.
	 *
	 * On the new UI's Hummingbird pages this is served via the `wphbPage.notices`
	 * payload instead (see Page::get_global_notices()), so bail out here to
	 * avoid a duplicate notice.
	 *
	 * @since 1.7.0
	 */
	public function clear_cache() {
		$notice = $this->get_cache_cleaned_notice();
		if ( empty( $notice ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$minify_active  = Utils::get_module( 'minify' )->is_active();
		$caching_active = Utils::get_module( 'page_cache' )->is_active();

		$additional = '';

		if ( $minify_active ) {
			// Add new files link.
			$recheck_file_url = add_query_arg(
				array(
					'recheck-files' => 'true',
					'_wpnonce'      => wp_create_nonce( 'wphb-recheck-files' ),
				),
				Utils::get_admin_menu_url( 'minification' )
			);

			if ( ! ( is_multisite() && is_network_admin() ) ) {
				$additional .= '<a href="' . esc_url( $recheck_file_url ) . '" class="button button-primary" style="margin-right:10px">' . __( 'Re-check Asset Optimization', 'wphb' ) . '</a>';
			}
		}

		$additional .= '<a href="#" id="wp-admin-notice-wphb-clear-cache" class="button">' . __( 'Clear Cache', 'wphb' ) . '</a>';
		if ( $caching_active ) {
			$adjust_settings_url = Utils::get_admin_menu_url( 'caching' ) . '#wphb-detection-settings';
			if ( ! is_multisite() || is_network_admin() ) {
				$additional .= '<a href="' . esc_url( $adjust_settings_url ) . '" style="color:#888;margin-left:10px;text-decoration:none">' . __( 'Adjust notification settings', 'wphb' ) . '</a>';
			}
		}

		$this->show_notice(
			'cache-cleaned',
			'<p>' . $notice['description'] . '</p>',
			$additional
		);
	}

	/**
	 * Generates text for the admin notice with a list of incompatible plugins
	 *
	 * @param array $incompat_plugins List of incompatible plugins if any.
	 *
	 * @return string Text message to be displayed
	 */
	public static function plugin_incompat_message( $incompat_plugins ) {
		$plugin_list = self::join_plugin_names_with_and( $incompat_plugins );

		if ( count( $incompat_plugins ) > 1 ) {
			$text = '<p>' . Whitelabel::replace_branding_terms(
				sprintf(
					/* translators: %s - plugin names, comma-separated with "and" before the last one, bold */
					esc_html__( 'Multiple performance plugins are active alongside Hummingbird (%s), which can cause conflicts or unpredictable behavior. For best results, we recommend keeping Hummingbird as your primary performance plugin and disabling the others.', 'wphb' ),
					$plugin_list
				)
			) . '</p>';
		} else {
			$text = '<p>' . Whitelabel::replace_branding_terms(
				sprintf( /* translators: %s - plugin name */
					esc_html__( '%s is active alongside Hummingbird. Running multiple performance plugins can cause conflicts or unexpected results, so for the best performance and stability, we recommend using Hummingbird as your only performance plugin and disabling other caching or optimization plugins.', 'wphb' ),
					$plugin_list
				)
			) . '</p>';
		}

		return $text;
	}

	/**
	 * Bold-wrap and "and"-join a list of incompatible plugin names.
	 *
	 * @since 4.0.0
	 *
	 * @param array $incompat_plugins List of incompatible plugins if any.
	 *
	 * @return string
	 */
	private static function join_plugin_names_with_and( $incompat_plugins ) {
		$names = array();
		foreach ( $incompat_plugins as $plugin_name ) {
			$names[] = '<strong>' . esc_html( $plugin_name ) . '</strong>';
		}

		$count = count( $names );
		if ( $count <= 1 ) {
			return $count ? $names[0] : '';
		}

		$last = array_pop( $names );

		if ( 1 === count( $names ) ) {
			/* translators: 1: first plugin name, 2: last plugin name */
			return sprintf( __( '%1$s and %2$s', 'wphb' ), $names[0], $last );
		}

		/* translators: 1: comma-separated plugin names (all but the last), 2: last plugin name */
		return sprintf( __( '%1$s, and %2$s', 'wphb' ), implode( ', ', $names ), $last );
	}

	/**
	 * Build the Plugin Compatibility notice payload.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_plugin_compat_notice() {
		if ( ! React_Notices::is_dismissed( 'plugin-compat' ) && $this->is_dismissed( 'plugin-compat' ) ) {
			React_Notices::mark_dismissed( 'plugin-compat', 'user' );
		}

		if ( React_Notices::is_dismissed( 'plugin-compat' ) ) {
			return array();
		}

		$incompatible_plugins = Utils::get_incompat_plugin_list();

		if ( count( $incompatible_plugins ) <= 0 ) {
			return array();
		}

		$plugin_list = self::join_plugin_names_with_and( $incompatible_plugins );

		if ( count( $incompatible_plugins ) > 1 ) {
			$description = Whitelabel::replace_branding_terms(
				sprintf(
					/* translators: %s - plugin names, comma-separated with "and" before the last one, bold */
					__( 'Multiple performance plugins are active alongside Hummingbird (%s), which can cause conflicts or unpredictable behavior. For best results, we recommend keeping Hummingbird as your primary performance plugin and disabling the others.', 'wphb' ),
					$plugin_list
				)
			);
		} else {
			$description = Whitelabel::replace_branding_terms(
				sprintf(
					/* translators: %s - plugin name */
					__( '%s is active alongside Hummingbird. Running multiple performance plugins can cause conflicts or unexpected results, so for the best performance and stability, we recommend using Hummingbird as your only performance plugin and disabling other caching or optimization plugins.', 'wphb' ),
					$plugin_list
				)
			);
		}

		if ( is_multisite() && current_user_can( 'manage_network_plugins' ) ) {
			$plugins_url = network_admin_url( 'plugins.php' );
		} else {
			$plugins_url = admin_url( 'plugins.php' );
		}

		return array(
			'id'          => 'plugin-compat',
			'description' => $description,
			'scope'       => 'user',
			'actions'     => array(
				array(
					'label' => __( 'Manage plugins', 'wphb' ),
					'url'   => esc_url( $plugins_url ),
				),
			),
		);
	}

	/**
	 * Display an admin notice if any of the incompatible plugin is installed.
	 *
	 */
	public function plugin_compat_check() {
		$notice = $this->get_plugin_compat_notice();
		if ( empty( $notice ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$incompatible_plugins = Utils::get_incompat_plugin_list();
		$text                 = $this->plugin_incompat_message( $incompatible_plugins );
		$action               = $notice['actions'][0];

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', 'plugin-compat' ), 'wphb-dismiss-notice' );

		$additional  = '<a href="' . esc_url( $action['url'] ) . '" id="wphb-manage-plugins" class="button button-primary">' . esc_html( $action['label'] ) . '</a>';
		$additional .= '<a role="button" href="' . esc_url( $dismiss_url ) . '" class="wphb-dismiss-cta">' . esc_html__( 'Dismiss', 'wphb' ) . '</a>';

		$this->show_notice( 'plugin-compat', $text, $additional, true );
	}

	/**
	 * Show safe mode notice on the WordPress dashboard.
	 *
	 * @since 3.18.0
	 */
	public function safe_mode_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_dismissed( 'safe-mode-active' ) ) {
			return;
		}

		if ( ! SafeMode::instance()->get_status() || Hub_Connector::is_connection_flow() ) {
			return;
		}

		if ( ! Utils::is_admin_dashboard( true ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$message = Whitelabel::replace_branding_terms(
			sprintf(
				/* translators: %1$s - Opening strong tag, %2$s - Closing strong tag */
				esc_html__( '%1$sSafe Mode%2$s is active. Test and preview Hummingbird changes without affecting your live site, then publish when you\'re ready.', 'wphb' ),
				'<strong>',
				'</strong>'
			)
		);

		$preview_changes = SafeMode::instance()->get_safe_mode_preview_url();
		$review_url      = add_query_arg( 'wphb_safe_mode_review', '1', Utils::get_admin_menu_url() );

		$buttons = sprintf(
			'<a href="%1$s" class="button" style="margin-right:10px">%2$s</a>
			<a href="#" class="button" id="wphb-copy-test-link" style="margin-right:10px;min-width:120px;text-align:center;">%3$s</a>
			<a href="%4$s" class="button button-primary">%5$s</a>',
			esc_url( $preview_changes ),
			esc_html__( 'Preview changes', 'wphb' ),
			esc_html__( 'Copy test link', 'wphb' ),
			esc_url( $review_url ),
			esc_html__( 'Review & Publish', 'wphb' )
		);

		$this->show_notice(
			'safe-mode-active',
			sprintf( '<p>%s</p>', $message ),
			$buttons,
			false,
			'warning safe-mode-dashboard-notice'
		);
	}

	/**
	 * Build the Cache Global Cleared notice payload.
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_cache_global_cleared_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$notice_data = get_option( 'wphb-notice-cache-global-cleared-show', array() );
		if ( empty( $notice_data['time'] ) ) {
			return array();
		}

		$gmt  = get_option( 'gmt_offset' );
		$date = gmdate( 'F j, Y', (int) $notice_data['time'] + ( $gmt * HOUR_IN_SECONDS ) );
		$time = gmdate( 'g:i a', (int) $notice_data['time'] + ( $gmt * HOUR_IN_SECONDS ) );

		return array(
			'id'          => 'cache-global-cleared',
			'description' => Whitelabel::replace_branding_terms(
				sprintf(
					/* translators: %1$s - cleared modules, %2$s - date, %3$s - time */
					__( 'Hummingbird cleared the %1$s cache on %2$s at %3$s.', 'wphb' ),
					$notice_data['modules'],
					$date,
					$time
				)
			),
		);
	}

	/**
	 * Show cache global cleared notice.
	 *
	 * @since 3.19.0
	 */
	public function cache_global_cleared() {
		$notice = $this->get_cache_global_cleared_notice();
		if ( empty( $notice ) ) {
			return;
		}

		if ( preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$notice_data = get_option( 'wphb-notice-cache-global-cleared-show', array() );

		$gmt     = get_option( 'gmt_offset' );
		$date    = gmdate( 'F j, Y', (int) $notice_data['time'] + ( $gmt * HOUR_IN_SECONDS ) );
		$time    = gmdate( 'g:i a', (int) $notice_data['time'] + ( $gmt * HOUR_IN_SECONDS ) );
		$message = Whitelabel::replace_branding_terms(
			sprintf(
				/* translators: %1$s - Opening strong tag, %2$s - Closing strong tag */
				esc_html__( 'Hummingbird cleared the%1$s cache on %2$s at %3$s.', 'wphb' ),
				'<strong> ' . $notice_data['modules'] . '</strong>',
				'<strong>' . $date . '</strong>',
				'<strong>' . $time . '</strong>'
			)
		);

		$message = '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . $message;
		$this->show_notice(
			'cache-global-cleared',
			sprintf( '<p>%s</p>', $message ),
			false,
			false,
			'success wphb-cache-global-cleared ',
			'dashicons-saved'
		);
		?>
		<style>
			.notice.wphb-cache-global-cleared {
				border-left-color: #1ABC9C;
			}
			.notice.wphb-cache-global-cleared a.wphb-dismiss {
				position: relative;
				padding: 10px 0;
			}
			.notice.wphb-cache-global-cleared a.wphb-dismiss:focus {
				box-shadow: none;
			}
			.notice.wphb-cache-global-cleared p {
				margin: 7px 0;
			}
			.notice.wphb-cache-global-cleared .dashicons-yes-alt {
				color: #1ABC9C;
				font-size: 20px;
				vertical-align: middle;
				margin-right: 8px;
			}
			.notice.wphb-cache-global-cleared span.dashicons-saved::before {
				color: #AAAAAA;
				background-color: #F8F8F8;
				padding: 3px;
			}
			.notice.wphb-cache-global-cleared span.dashicons-saved:hover::before {
				background-color: #a3a3a34a;
			}
		</style>
		<?php
	}

	/**
	 * Build the "Update available" notice payload for the Hummingbird plugin
	 *
	 * @since 4.0.0
	 *
	 * @return array Empty array when there's nothing to show.
	 */
	public function get_update_notice() {
		$cap = is_multisite() ? 'manage_network_plugins' : 'update_plugins';
		if ( ! current_user_can( $cap ) ) {
			return array();
		}

		$updates       = get_site_transient( 'update_plugins' );
		$plugin_update = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) && isset( $updates->response[ WPHB_BASENAME ] ) )
			? $updates->response[ WPHB_BASENAME ]
			: null;

		if ( ! is_object( $plugin_update ) || empty( $plugin_update->new_version ) ) {
			return array();
		}

		$latest_version = (string) $plugin_update->new_version;
		if ( ! version_compare( WPHB_VERSION, $latest_version, '<' ) ) {
			return array();
		}

		$id = 'update-available-' . str_replace( '.', '-', $latest_version );

		if ( React_Notices::is_dismissed( $id ) ) {
			return array();
		}

		return array(
			'id'          => $id,
			/* translators: %s: Latest Hummingbird version number. */
			'title'       => Whitelabel::replace_branding_terms( sprintf( __( 'Hummingbird %s is here', 'wphb' ), $latest_version ) ),
			'description' => __( 'Update now for the latest improvements.', 'wphb' ),
			'scope'       => 'site',
			'actions'     => array(
				array(
					'label' => __( 'Learn more', 'wphb' ),
					'url'   => esc_url( Utils::get_link( 'plugin', 'hummingbird_update_notice_learn_more' ) . '#changelog' ),
				),
				array(
					// No `url` -- this action just dismisses the notice.
					'label' => __( 'Not now', 'wphb' ),
				),
				array(
					'label' => __( 'Update now', 'wphb' ),
					'url'   => esc_url( $this->get_plugin_update_url() ),
				),
			),
		);
	}

	/**
	 * Build the nonce-protected URL that triggers the Hummingbird plugin update.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	private function get_plugin_update_url() {
		return add_query_arg(
			array(
				'action'   => 'upgrade-plugin',
				'plugin'   => WPHB_BASENAME,
				'_wpnonce' => wp_create_nonce( 'upgrade-plugin_' . WPHB_BASENAME ),
			),
			self_admin_url( 'update.php' )
		);
	}
}
