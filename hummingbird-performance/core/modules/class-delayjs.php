<?php
/**
 * Delay Js module.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.5.0
 */

namespace Hummingbird\Core\Modules;

use Hummingbird\Core\Module;
use Hummingbird\Core\Traits\Module as ModuleContract;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delay Js
 */
class Delayjs extends Module {

	use ModuleContract;

	/**
	 * Must exclude from delay js script.
	 *
	 * @var array
	 */
	private $must_excluded = array( 'wphb-add-delay' );

	/**
	 * Holds excluded script IDs.
	 *
	 * @var array
	 */
	public $excluded_script_ids = array();

	/**
	 * Initialize module.
	 */
	public function init() {
		add_action( 'cornerstone_before_boot_app', array( $this, 'wphb_cs_disable_delay_for_app' ) );
		add_action( 'cornerstone_before_custom_endpoint', array( $this, 'wphb_cs_disable_delay_for_app' ) );
		add_action( 'cs_preview_frame_load', array( $this, 'wphb_cs_disable_delay_for_app' ) );

		add_filter( 'wphb_buffer', array( $this, 'delay_js' ) );

		if ( ! wp_next_scheduled( 'wphb_get_delay_js_exclusion' ) ) {
			wp_schedule_event( time(), 'daily', 'wphb_get_delay_js_exclusion' );
		}

		add_action( 'wphb_get_delay_js_exclusion', array( $this, 'get_delay_js_exclusion' ) );

		// Add delay js to scripts.
		add_action( 'wp_print_footer_scripts', array( $this, 'wphb_add_delay_js' ), 5 );
		// Add delay scripts hub client page.
		add_action( 'wpmudev_hub_template_footer', array( $this, 'wphb_add_delay_js' ) );

		// Fetch exclusion list on plugin activation.
		add_action( 'admin_init', array( $this, 'wphb_prelaod_exclusion_lists' ) );
		add_action( 'wphb_load_exclusion_list_schedule_single_event', array( $this, 'wphb_load_exclusion_list_first_time_from_api' ) );
		add_filter( 'wphb_minify_resource', array( $this, 'filter_resource_minify' ), 10, 4 );
	}

	/**
	 * Schedule the single event cron for exclusion lists.
	 */
	public function wphb_prelaod_exclusion_lists() {
		$minify  = Utils::get_module( 'minify' );
		$options = $minify->get_options();
		if ( isset( $options['delay_js_exclusion_list'] ) && false !== $options['delay_js_exclusion_list'] ) {
			return;
		}

		// Run the cron once.
		wp_schedule_single_event( time(), 'wphb_load_exclusion_list_schedule_single_event' );
	}

	/**
	 * Fetching the exclusion list from API first time only, this will only call once.
	 */
	public function wphb_load_exclusion_list_first_time_from_api() {
		$this->call_api_and_update_exclusion_lists();
	}

	/**
	 * Call the API and fetched the latest exclusion list from server.
	 */
	public function call_api_and_update_exclusion_lists() {
		$minify   = Utils::get_module( 'minify' );
		$api      = Utils::get_api();
		$response = $api->performance->get_delayjs_exclusion();
		$options  = $minify->get_options();

		if ( ! is_wp_error( $response ) && ! empty( $response ) ) {
			$options['delay_js_exclusion_list'] = (array) $response;
			$minify->update_options( $options );
		} elseif ( empty( $options['delay_js_exclusion_list'] ) ) {
			$options['delay_js_exclusion_list'] = $this->must_excluded;
			$minify->update_options( $options );
		}
	}

	/**
	 * Store the JS exclusion list in DB after getting it from API.
	 */
	public function get_delay_js_exclusion() {
		// Return early, if minify and delay are not enabled.
		if ( ! $this->is_delay_enable() ) {
			return;
		}

		$this->call_api_and_update_exclusion_lists();
	}

	/**
	 * Disabled delay for Cornerstone Builder.
	 */
	public function wphb_cs_disable_delay_for_app() {
		if ( ! defined( 'WPHBDONOTDELAYJS' ) ) {
			define( 'WPHBDONOTDELAYJS', true );
		}
	}

	/**
	 * Should we delay the script or not?
	 *
	 * @return bool
	 */
	public function should_delay_script() {
		$avoid_delayjs = filter_input( INPUT_GET, 'avoid-delayjs', FILTER_VALIDATE_BOOLEAN );

		if ( ! apply_filters( 'wphb_should_delay_js', true ) ) {
			return false;
		}

		if ( $avoid_delayjs || ( defined( 'WPHBDONOTDELAYJS' ) && WPHBDONOTDELAYJS ) || Utils::is_amp() || Utils::wphb_is_page_builder() || is_preview() || is_customize_preview() ) {
			return false;
		}

		if ( Utils::get_module( 'exclusions' )->is_current_post_type_excluded() ) {
			return false;
		}

		if ( Utils::get_module( 'exclusions' )->is_current_page_excluded() ) {
			return false;
		}

		return true;
	}

	/**
	 * Toggle CDN helper function.
	 *
	 * @param bool $value  CDN status to set.
	 */
	public function toggle_delay_js( $value ) {
		$minify_options      = Utils::get_module( 'minify' );
		$options             = $minify_options->get_options();
		$options['delay_js'] = $value;

		$minify_options->update_options( $options );
	}

	/**
	 * Print delay js script.
	 *
	 * @since 3.5.0
	 */
	public function wphb_add_delay_js() {
		if ( ! $this->should_delay_script() ) {
			return;
		}

		// Return early, if minify and delay are not enabled.
		if ( ! $this->is_delay_enable() ) {
			return;
		}

		// Prevent duplicate injection.
		static $script_already_printed = false;
		if ( $script_already_printed ) {
			return;
		}

		$options          = Utils::get_module( 'minify' )->get_options();
		$delay_js_file    = WPHB_DIR_PATH . 'admin/assets/js/wphb-add-delay.min.js';
		$delay_js_content = file_get_contents( $delay_js_file );

		if ( ! empty( $delay_js_content ) ) {
			$script_already_printed = true;
			$delay_js_timeout       = $options['delay_js_timeout'];
			$delay_js_content_timer = '';

			if ( $delay_js_timeout > 0 ) {
				$delay_js_timeout_s     = $delay_js_timeout * 1000;
				$delay_js_content_timer = 'var delay_js_timeout_timer = ' . $delay_js_timeout_s . ';';
			}

			echo '<script type="text/javascript" id="wphb-add-delay">' . $delay_js_content_timer . $delay_js_content . '</script>';
		}
	}

	/**
	 * Check delay and minify are active.
	 *
	 * @since 3.5.0
	 *
	 * @return boolean
	 */
	public function is_delay_enable() {
		if ( ! Utils::is_member() ) {
			return false;
		}

		$options = Utils::get_module( 'minify' )->get_options();

		if ( ! Utils::get_module( 'minify' )->is_active() ) {
			return false;
		}

		$options  = Utils::get_module( 'minify' )->get_options();
		$delay_js = $options['delay_js'];

		if ( ! $delay_js ) {
			return false;
		}

		return true;
	}

	/**
	 * Adds delay JS attribute to the page html.
	 *
	 * @since 3.5.0
	 *
	 * @param string $html Html for the page.
	 *
	 * @return string
	 */
	public function delay_js( $html ) {
		if ( ! $this->should_delay_script() ) {
			return $html;
		}

		// Return early, if minify and delay are not enabled.
		if ( ! $this->is_delay_enable() ) {
			return $html;
		}

		return $this->add_delay_to_scripts( $html );
	}

	/**
	 * Adds delay JS attribute to the page html.
	 *
	 * @since 3.11.0
	 *
	 * @param string $html Html for the page.
	 *
	 * @return string
	 */
	public function add_delay_to_scripts( $html ) {
		$replaced_html = preg_replace_callback(
			'/<\s*script\s*(?<attr>[^>]*?)?>(?<content>.*?)?<\s*\/\s*script\s*>/ims',
			array(
				$this,
				'wphb_replace_scripts',
			),
			$html
		);

		if ( empty( $replaced_html ) ) {
			return $html;
		}

		return $replaced_html;
	}

	/**
	 * Returns an array of delay js exclusion.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function get_pre_defined_exclusion_list() {
		$minify  = Utils::get_module( 'minify' );
		$options = $minify->get_options();

		if ( isset( $options['delay_js_exclusion_list'] ) && ! empty( $options['delay_js_exclusion_list'] ) ) {
			return $options['delay_js_exclusion_list'];
		}

		return $this->must_excluded;
	}

	/**
	 * Finds Delay js exclude.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function wphb_find_delay_js_exclude() {
		return Utils::get_module( 'exclusions' )->get_combined_asset_path_exclusion_list_for_delay_js();
	}

	/**
	 * Filter minified resources.
	 *
	 * @param bool   $value   Current value.
	 * @param string $handle  Resource handle.
	 * @param string $type    Script or style.
	 * @param string $url     Script URL.
	 *
	 * @return bool
	 */
	public function filter_resource_minify( $value, $handle, $type, $url ) {
		if ( $this->is_delay_enable() && 'scripts' === $type ) {
			$patterns = Utils::get_module( 'exclusions' )->get_combined_asset_path_exclusion_list_for_delay_js();
			if ( empty( $patterns ) ) {
				return $value;
			}

			$combined_pattern = '#(' . implode( '|', array_map( 'preg_quote', $patterns ) ) . ')#i';
			if ( ! empty( $url ) && preg_match( $combined_pattern, $url ) ) {
				$this->excluded_script_ids[] = $handle;
			}
		}

		return $value;
	}

	/**
	 * Adds wphb-delay-type to the JS scripts.
	 *
	 * @since 3.5.0
	 *
	 * @param array $matches Matches array for scripts regex.
	 *
	 * @return string
	 */
	public function wphb_replace_scripts( $matches ) {
		$excluded_patterns = $this->wphb_find_delay_js_exclude();
		$excluded_ids      = $this->excluded_script_ids;

		// Merge patterns and IDs into a single list.
		$exclusions = array_merge(
			$excluded_patterns,
			$excluded_ids
		);

		foreach ( $exclusions as $item ) {
			if ( preg_match( "#{$item}#i", $matches[0] ) ) {
				return $matches[0];
			}
		}

		if ( $this->should_exclude_inline_script_from_delay() && isset( $matches['attr'] ) && ! preg_match( '/src=["\']?([^"\'>]+)/', $matches['attr'] ) ) {
			return $matches[0];
		}

		$matches['attr'] = trim( $matches['attr'] );
		$delay_js        = $matches[0];

		if ( ! empty( $matches['attr'] ) ) {

			if (
				strpos( $matches['attr'], 'type=' ) !== false
				&&
				! preg_match( '/type\s*=\s*["\'](?:text|application)\/(?:(?:x\-)?javascript|ecmascript|jscript)["\']|type\s*=\s*["\'](?:module)[ "\']/i', $matches['attr'] )
			) {
				return $matches[0];
			}

			$delay_attr = preg_replace( '/type=(["\'])(.*?)\1/i', 'data-wphb-$0', $matches['attr'], 1 );

			if ( null !== $delay_attr ) {
				$delay_js = preg_replace( '#' . preg_quote( $matches['attr'], '#' ) . '#i', $delay_attr, $matches[0], 1 );
			}
		}

		return str_ireplace( '<script', '<script type="wphb-delay-type"', $delay_js );
	}

	/**
	 * Check if inline script should not be delayed.
	 *
	 * @return bool
	 */
	public function should_exclude_inline_script_from_delay() {
		static $delay_js_exclude_inline_js = null;
		if ( null === $delay_js_exclude_inline_js ) {
			$delay_js_exclude_inline_js = Settings::get_setting( 'delay_js_exclude_inline_js', 'minify' );
		}

		return $delay_js_exclude_inline_js || apply_filters( 'wphb_do_not_delay_inline_scripts', false );
	}
}
