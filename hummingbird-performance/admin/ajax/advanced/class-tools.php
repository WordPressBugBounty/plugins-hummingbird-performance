<?php
/**
 * Settings Tools AJAX actions for the new React UI.
 *
 * @since 4.0.0
 * @package Hummingbird\Admin\Ajax\Advanced
 */

namespace Hummingbird\Admin\Ajax\Advanced;

use Hummingbird\Admin\Ajax\Activity_Log;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tools
 */
class Tools {

	/**
	 * Activity log module slug.
	 *
	 * @var string
	 */
	const ACTIVITY_MODULE = 'settings';

	/**
	 * Matches one host per line, like //fonts.googleapis.com; the Tools tab uses the same pattern in JavaScript.
	 *
	 * @var string
	 */
	const HOST_PATTERN = '^(?:(?:https?:)?//)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$';

	/**
	 * Digits a whole-number field may hold: 2,147,483,647 is the largest integer every PHP build can store.
	 *
	 * @var int
	 */
	const MAX_DIGITS = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$endpoints = array(
			'settings_tools_status',
			'settings_tools_save',
		);

		foreach ( $endpoints as $endpoint ) {
			add_action( "wp_ajax_wphb_react_{$endpoint}", array( $this, $endpoint ) );
		}
	}

	/**
	 * Get Settings > Tools values.
	 */
	public function settings_tools_status() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		wp_send_json_success( $this->build_status_response() );
	}

	/**
	 * Save Settings > Tools values.
	 */
	public function settings_tools_save() {
		check_ajax_referer( 'wphb-fetch' );

		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			die();
		}

		$raw = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$data = json_decode( html_entity_decode( $raw ), true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wphb' ) ) );
		}

		$advanced = Utils::get_module( 'advanced' );
		$options  = $advanced->get_options();
		$previous = $options;

		$options['viewport_meta'] = ! empty( $data['viewportMeta'] );

		$query_skip = isset( $options['query_strings_global'] ) && $options['query_strings_global'] && ! Utils::is_ajax_network_admin();
		if ( ! $query_skip ) {
			$options['query_string'] = ! empty( $data['queryStrings'] );
		}

		$emoji_skip = isset( $options['emoji_global'] ) && $options['emoji_global'] && ! Utils::is_ajax_network_admin();
		if ( ! $emoji_skip ) {
			$options['emoji'] = ! empty( $data['removeEmoji'] );
		}

		$post_revisions = self::parse_count( isset( $data['postRevisions'] ) ? $data['postRevisions'] : null );
		if ( null !== $post_revisions ) {
			$options['post_revisions'] = (string) $post_revisions;
		}

		if ( ! empty( $data['cartFragmentsEnabled'] ) ) {
			$options['cart_fragments'] = isset( $data['cartFragmentsMode'] ) && 'all' === $data['cartFragmentsMode'] ? 'all' : true;
		} else {
			$options['cart_fragments'] = false;
		}

		$prefetch = self::parse_hosts( isset( $data['prefetch'] ) ? $data['prefetch'] : null, false );
		if ( null !== $prefetch ) {
			$options['prefetch'] = $prefetch;
		}

		$preconnect = self::parse_hosts( isset( $data['preconnect'] ) ? $data['preconnect'] : null, true );
		if ( null !== $preconnect ) {
			$options['preconnect'] = $preconnect;
		}

		$lazy_load                = isset( $options['lazy_load'] ) && is_array( $options['lazy_load'] ) ? $options['lazy_load'] : array();
		$lazy_load['enabled']     = ! empty( $data['lazyLoad']['enabled'] );
		$lazy_load['method']      = isset( $data['lazyLoad']['method'] ) && 'scroll' === $data['lazyLoad']['method'] ? 'scroll' : 'click';

		$lazy_load['preload']     = ! empty( $data['lazyLoad']['preload'] );
		$lazy_load['threshold']   = self::parse_count( isset( $data['lazyLoad']['threshold'] ) ? $data['lazyLoad']['threshold'] : null ) ?? $lazy_load['threshold'] ?? 0;
		$options['lazy_load']     = $lazy_load;

		$advanced->update_options( $options );
		$this->record_tools_changes( $previous, $options );

		wp_send_json_success( $this->build_status_response() );
	}

	/**
	 * Validation rules the Tools tab applies before saving, so the host rule it
	 * shows is the one this endpoint enforces.
	 *
	 * @return array
	 */
	public static function get_rules() {
		return array(
			'hostPattern' => self::HOST_PATTERN,
			'maxDigits'   => self::MAX_DIGITS,
		);
	}

	/**
	 * Whole number from a submitted field, or null when it is not one.
	 *
	 * @param mixed $value Submitted value.
	 *
	 * @return int|null
	 */
	private static function parse_count( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( (string) $value );

		return ctype_digit( $value ) && strlen( $value ) <= self::MAX_DIGITS ? (int) $value : null;
	}

	/**
	 * Host list from a submitted textarea, or null when any line is not a host.
	 *
	 * @param mixed $value             Submitted value.
	 * @param bool  $allow_crossorigin Whether a trailing `crossorigin` is accepted.
	 *
	 * @return string[]|null
	 */
	private static function parse_hosts( $value, $allow_crossorigin ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), 'strlen' ) );

		foreach ( $lines as $line ) {
			$host = $allow_crossorigin ? preg_replace( '/\s+crossorigin$/i', '', $line ) : $line;
			if ( ! preg_match( '~' . self::HOST_PATTERN . '~i', $host ) ) {
				return null;
			}
		}

		return $lines;
	}

	/**
	 * Record Activity Log entries for Settings > Tools changes.
	 *
	 * @param array $previous Previous advanced options.
	 * @param array $current  Current advanced options.
	 */
	private function record_tools_changes( $previous, $current ) {
		$prev_viewport = ! empty( $previous['viewport_meta'] );
		$next_viewport = ! empty( $current['viewport_meta'] );
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$prev_viewport,
			$next_viewport,
			__( 'Viewport meta optimization enabled.', 'wphb' ),
			__( 'Viewport meta optimization disabled.', 'wphb' )
		);

		$prev_query_strings = ! empty( $previous['query_string'] );
		$next_query_strings = ! empty( $current['query_string'] );
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$prev_query_strings,
			$next_query_strings,
			__( 'URL query string removal enabled.', 'wphb' ),
			__( 'URL query string removal disabled.', 'wphb' )
		);

		$prev_emoji = ! empty( $previous['emoji'] );
		$next_emoji = ! empty( $current['emoji'] );
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$prev_emoji,
			$next_emoji,
			__( 'Default emoji files removal enabled.', 'wphb' ),
			__( 'Default emoji files removal disabled.', 'wphb' )
		);

		$prev_post_revisions = isset( $previous['post_revisions'] ) ? max( 0, (int) $previous['post_revisions'] ) : 0;
		$next_post_revisions = isset( $current['post_revisions'] ) ? max( 0, (int) $current['post_revisions'] ) : 0;
		if ( $prev_post_revisions !== $next_post_revisions ) {
			if ( 0 === $next_post_revisions ) {
				Activity_Log::record( self::ACTIVITY_MODULE, __( 'Post revisions disabled.', 'wphb' ) );
			} else {
				Activity_Log::record(
					self::ACTIVITY_MODULE,
					sprintf(
						/* translators: %d: number of stored revisions. */
						__( 'Post revisions set to %d revisions.', 'wphb' ),
						$next_post_revisions
					)
				);
			}
		}

		$prev_cart_fragments = isset( $previous['cart_fragments'] ) ? $previous['cart_fragments'] : false;
		$next_cart_fragments = isset( $current['cart_fragments'] ) ? $current['cart_fragments'] : false;
		if ( $prev_cart_fragments !== $next_cart_fragments ) {
			if ( $next_cart_fragments ) {
				$target = 'all' === $next_cart_fragments
					? __( 'all pages', 'wphb' )
					: __( 'non-WooCommerce pages', 'wphb' );

				Activity_Log::record(
					self::ACTIVITY_MODULE,
					sprintf(
						/* translators: %s: selected target pages. */
						__( 'WooCommerce cart fragments disabled for %s.', 'wphb' ),
						$target
					)
				);
			} else {
				Activity_Log::record( self::ACTIVITY_MODULE, __( 'WooCommerce cart fragments setting disabled.', 'wphb' ) );
			}
		}

		$prev_lazy_load = isset( $previous['lazy_load'] ) && is_array( $previous['lazy_load'] ) ? $previous['lazy_load'] : array();
		$next_lazy_load = isset( $current['lazy_load'] ) && is_array( $current['lazy_load'] ) ? $current['lazy_load'] : array();

		$prev_lazy_enabled = ! empty( $prev_lazy_load['enabled'] );
		$next_lazy_enabled = ! empty( $next_lazy_load['enabled'] );
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$prev_lazy_enabled,
			$next_lazy_enabled,
			__( 'Lazy load comments enabled.', 'wphb' ),
			__( 'Lazy load comments disabled.', 'wphb' )
		);

		$prev_method = isset( $prev_lazy_load['method'] ) && 'scroll' === $prev_lazy_load['method'] ? 'scroll' : 'click';
		$next_method = isset( $next_lazy_load['method'] ) && 'scroll' === $next_lazy_load['method'] ? 'scroll' : 'click';
		if ( $prev_method !== $next_method ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				'scroll' === $next_method
					? __( 'Comments lazy loaded on scroll.', 'wphb' )
					: __( 'Comments lazy loaded on click.', 'wphb' )
			);
		}

		$prev_preload = ! empty( $prev_lazy_load['preload'] );
		$next_preload = ! empty( $next_lazy_load['preload'] );
		Activity_Log::record_toggle_when_changed(
			self::ACTIVITY_MODULE,
			$prev_preload,
			$next_preload,
			__( 'Preloading comments enabled.', 'wphb' ),
			__( 'Preloading comments disabled.', 'wphb' )
		);

		$prev_threshold = isset( $prev_lazy_load['threshold'] ) ? max( 0, (int) $prev_lazy_load['threshold'] ) : 0;
		$next_threshold = isset( $next_lazy_load['threshold'] ) ? max( 0, (int) $next_lazy_load['threshold'] ) : 0;
		if ( $prev_threshold !== $next_threshold ) {
			Activity_Log::record(
				self::ACTIVITY_MODULE,
				sprintf(
					/* translators: %d: comments lazy load threshold value. */
					__( 'Comments lazyload threshold set to %d.', 'wphb' ),
					$next_threshold
				)
			);
		}

		$prev_prefetch = isset( $previous['prefetch'] ) && is_array( $previous['prefetch'] ) ? array_values( $previous['prefetch'] ) : array();
		$next_prefetch = isset( $current['prefetch'] ) && is_array( $current['prefetch'] ) ? array_values( $current['prefetch'] ) : array();
		Activity_Log::record_when_changed(
			self::ACTIVITY_MODULE,
			$prev_prefetch,
			$next_prefetch,
			__( 'DNS prefetch list updated.', 'wphb' )
		);

		$prev_preconnect = isset( $previous['preconnect'] ) && is_array( $previous['preconnect'] ) ? array_values( $previous['preconnect'] ) : array();
		$next_preconnect = isset( $current['preconnect'] ) && is_array( $current['preconnect'] ) ? array_values( $current['preconnect'] ) : array();
		Activity_Log::record_when_changed(
			self::ACTIVITY_MODULE,
			$prev_preconnect,
			$next_preconnect,
			__( 'Preconnect list updated.', 'wphb' )
		);
	}

	/**
	 * Build normalized response used by Settings Tools UI.
	 *
	 * @return array
	 */
	private function build_status_response() {
		$options = Utils::get_module( 'advanced' )->get_options();
		$lazy    = isset( $options['lazy_load'] ) && is_array( $options['lazy_load'] ) ? $options['lazy_load'] : array();
		$woo_active = function_exists( 'WC' ) || class_exists( 'WooCommerce' );

		return array(
			'viewportMeta'       => ! empty( $options['viewport_meta'] ),
			'queryStrings'       => ! empty( $options['query_string'] ),
			'queryStringsLocked' => apply_filters( 'wphb_query_strings_disabled', false ),
			'removeEmoji'        => ! empty( $options['emoji'] ),
			'emojiLocked'        => apply_filters( 'wphb_emojis_disabled', false ),
			'postRevisions'      => isset( $options['post_revisions'] ) ? (string) $options['post_revisions'] : '',
			'cartFragments'      => isset( $options['cart_fragments'] ) ? $options['cart_fragments'] : false,
			'wooActive'          => $woo_active,
			'wooLink'            => self_admin_url( 'admin.php?page=wc-settings&tab=products' ),
			'prefetch'           => isset( $options['prefetch'] ) && is_array( $options['prefetch'] ) ? array_values( $options['prefetch'] ) : array(),
			'preconnect'         => isset( $options['preconnect'] ) && is_array( $options['preconnect'] ) ? array_values( $options['preconnect'] ) : array(),
			'lazyLoad'           => array(
				'enabled'   => ! empty( $lazy['enabled'] ),
				'method'    => isset( $lazy['method'] ) ? $lazy['method'] : 'click',
				'button'    => isset( $lazy['button'] ) && is_array( $lazy['button'] ) ? $lazy['button'] : array(),
				'preload'   => ! empty( $lazy['preload'] ),
				'threshold' => isset( $lazy['threshold'] ) ? (int) $lazy['threshold'] : 10,
			),
			'commentLimit'       => (int) get_option( 'comments_per_page' ),
			'rules'              => self::get_rules(),
		);
	}
}
