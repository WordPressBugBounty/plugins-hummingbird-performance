<?php
/**
 * Exclusions class.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.11.0
 */

namespace Hummingbird\Core\Modules;

use Hummingbird\Core\Module;
use Hummingbird\Core\Traits\Module as ModuleContract;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Modules\Minify\Sources_Collector;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Exclusion.
 */
class Exclusions extends Module {
	use ModuleContract;

	/**
	 * Retrieve the template or name of the currently active theme.
	 *
	 * @return string
	 */
	public function get_active_theme() {
		$theme = wp_get_theme();

		return array( 'active_theme' => $theme->get( 'Name' ) );
	}

	/**
	 * Retrieve a list of active plugins, including sitewide active plugins in multisite installations.
	 *
	 * @return array
	 */
	public function get_active_plugins() {
		$plugins        = array();
		$active_plugins = array_merge(
			(array) get_option( 'active_plugins', array() ),
			is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array()
		);

		foreach ( $active_plugins as $plugin ) {
			$plugin_data        = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$plugins[ $plugin ] = $plugin_data['Name'];
		}

		return $plugins;
	}

	/**
	 * Retrieve a list of active plugins and the currently active theme.
	 *
	 * @return array
	 */
	public function get_active_theme_and_plugins() {
		return array_merge( $this->get_active_theme(), $this->get_active_plugins() );
	}

	/**
	 * Retrieve all exclusions.
	 *
	 * @param string $type The type of exclusions to retrieve.
	 *
	 * @return array
	 */
	public function get_all_possible_exclusions( $type = 'scripts' ) {
		$all_exclusions = array(
			'posts'   => $this->get_post_types(),
			'files'   => $this->get_assets( $type ),
			'urls'    => $this->get_formatted_post_urls(),
			'plugins' => $this->get_active_theme_and_plugins(),
			'ads'     => $this->get_ads_tracker_exclusion(),
		);

		if ( 'styles' === $type ) {
			return array_intersect_key(
				$all_exclusions,
				array_flip( array( 'posts', 'files', 'plugins', 'urls' ) )
			);
		}

		return $all_exclusions;
	}

	/**
	 * Retrieve settings for the exclusion textareas, including default values and descriptions.
	 *
	 * @param string $type   The type of exclusions to retrieve.
	 * @param string $prefix The prefix for the setting keys.
	 *
	 * @return array
	 */
	public function get_exclusion_settings( $type = 'scripts', $prefix = 'delay_js_' ) {
		$is_critical_css = 'critical_css_' === $prefix;
		$keyword_key     = $is_critical_css ? 'keywords' : 'exclusions';

		$settings = array(
			$prefix . 'all_exclusions'           => array(
				'title'           => __( 'All Exclusions', 'wphb' ),
				'exclusion_name'  => 'all_exclusions',
				'description'     => __( 'Choose an exclusion type and add/remove exclusions here.', 'wphb' ),
				'placeholder'     => __( 'Enter URLs, keywords, or file paths to exclude from optimization.', 'wphb' ),
				'class'           => '',
				'type'            => 'all_exclusion',
				'value'           => $this->get_all_possible_exclusions( $type ),
				'selected_values' => array(),
			),
			$prefix . 'files_exclusion'          => array(
				'title'           => __( 'Files', 'wphb' ),
				'exclusion_name'  => 'files',
				'description'     => __( 'Select file paths to exclude from optimization.', 'wphb' ),
				'placeholder'     => __( 'Enter file paths to exclude from optimization.', 'wphb' ),
				'value'           => $this->get_assets( $type ),
				'class'           => 'sui-hidden',
				'type'            => 'select',
				'selected_values' => $this->get_exclusion_setting( $prefix . 'files_exclusion' ),
			),
			$prefix . 'post_types_exclusion'     => array(
				'title'           => __( 'Post Types', 'wphb' ),
				'exclusion_name'  => 'posts',
				'description'     => __( 'Select post types to exclude from optimization.', 'wphb' ),
				'placeholder'     => __( 'Enter post types to exclude from optimization.', 'wphb' ),
				'value'           => $this->get_post_types(),
				'class'           => 'sui-hidden',
				'type'            => 'select',
				'selected_values' => $is_critical_css ? $this->get_critical_css_excluded_post_types() : $this->get_exclusion_setting( $prefix . 'post_types_exclusion' ),
			),
			$prefix . 'post_urls_exclusion'      => array(
				'title'           => __( 'Post URLs', 'wphb' ),
				'exclusion_name'  => 'urls',
				'description'     => __( 'Select post URLs to exclude from optimization.', 'wphb' ),
				'placeholder'     => __( 'Enter post URLs to exclude from optimization.', 'wphb' ),
				'value'           => $this->get_formatted_post_urls( $prefix . 'post_urls_exclusion' ),
				'class'           => 'sui-hidden',
				'type'            => 'select',
				'selected_values' => $this->get_exclusion_setting( $prefix . 'post_urls_exclusion' ),
			),
			$prefix . 'plugins_themes_exclusion' => array(
				'title'           => __( 'Plugins/Themes', 'wphb' ),
				'exclusion_name'  => 'plugins',
				'description'     => __( 'Select plugin or theme names to exclude from optimization.', 'wphb' ),
				'placeholder'     => __( 'Enter plugin or theme names to exclude from optimization.', 'wphb' ),
				'value'           => $this->get_active_theme_and_plugins(),
				'class'           => 'sui-hidden',
				'type'            => 'select',
				'selected_values' => $this->get_exclusion_setting( $prefix . 'plugins_themes_exclusion' ),
			),
			$prefix . $keyword_key               => array(
				'title'           => __( 'Keywords', 'wphb' ),
				'exclusion_name'  => 'keywords',
				'description'     => __( 'Enter keywords to exclude from optimization.', 'wphb' ),
				'placeholder'     => __( 'Enter keywords to exclude from optimization.', 'wphb' ),
				'value'           => $is_critical_css ? $this->get_exclusion_setting( $prefix . 'keywords' ) : $this->get_manually_excluded_keywords(),
				'class'           => 'sui-hidden',
				'type'            => 'select',
				'selected_values' => $is_critical_css ? $this->get_exclusion_setting( $prefix . 'keywords' ) : $this->get_manually_excluded_keywords(),
			),
		);

		if ( ! $is_critical_css ) {
			$settings[ $prefix . 'ads_tracker_exclusion' ] = array(
				'title'           => __( 'Ads/Trackers', 'wphb' ),
				'exclusion_name'  => 'ads',
				'description'     => __( 'Select ads or trackers to exclude from optimization.', 'wphb' ),
				'placeholder'     => __( 'Enter ads or trackers to exclude from optimization.', 'wphb' ),
				'value'           => $this->get_ads_tracker_exclusion(),
				'class'           => 'sui-hidden',
				'type'            => 'select',
				'selected_values' => $this->get_exclusion_setting( 'delay_js_ads_tracker_exclusion' ),
			);

			if ( defined( 'WPHB_INCLUDE_DEFAULT_EXCLUSIONS' ) ) {
				$default_exclusion_lists = Utils::get_module( 'delayjs' )->get_pre_defined_exclusion_list();
				$default_exclusion_lists = array_reduce(
					$default_exclusion_lists,
					function ( $carry, $item ) {
						$carry[ $item ] = $item;
						return $carry;
					},
					array()
				);

				$settings[ $prefix . 'default_exclusions' ] = array(
					'title'           => __( 'Default Exclusions', 'wphb' ),
					'exclusion_name'  => 'default_exclusions',
					'description'     => __( 'List of default exclusions', 'wphb' ),
					'placeholder'     => __( 'Default Exclusions', 'wphb' ),
					'value'           => $default_exclusion_lists,
					'class'           => 'sui-hidden',
					'type'            => 'select',
					'selected_values' => $default_exclusion_lists,
				);
			}
		}

		return $settings;
	}

	/**
	 * Retrieve settings for delay JS exclusions.
	 *
	 * @return array
	 */
	public function get_delay_js_exclusion_settings() {
		return $this->get_exclusion_settings( 'scripts', 'delay_js_' );
	}

	/**
	 * Retrieve a list of publicly accessible post types.
	 *
	 * @return array
	 */
	public function get_post_types() {
		$post_types = $this->get_custom_post_types();
		$pages_type = Page_Cache::get_page_types();

		return array_merge( $post_types, $pages_type );
	}

	/**
	 * Retrieve a list of custom post types.
	 *
	 * @param bool $keys Whether to return the keys of the post types.
	 * @return array
	 */
	public function get_custom_post_types( $keys = false ) {
		$post_types = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
				'_builtin'           => false,
			),
			'objects',
		);

		if ( $keys ) {
			return array_keys( $post_types );
		}

		// Use array_map to create key-value pairs of post type slug => label.
		$post_types_formatted = array_map(
			function ( $post_type ) {
				return $post_type->label;
			},
			$post_types
		);

		return $post_types_formatted;
	}

	/**
	 * Retrieve a list of default exclusions for ads and trackers.
	 *
	 * @return array
	 */
	public function get_ads_tracker_exclusion() {
		return array(
			'amazon_ads'         => array(
				'title'      => __( 'Amazon Ads', 'wphb' ),
				'exclusions' => array( 'amazon-adsystem.com' ),
			),
			'google_adsense'     => array(
				'title'      => __( 'Google AdSense', 'wphb' ),
				'exclusions' => array( 'adsbygoogle' ),
			),
			'google_analytics'   => array(
				'title'      => __( 'Google Analytics', 'wphb' ),
				'exclusions' => array(
					'google-analytics.com\/analytics.js',
					"ga\\( '",
					"ga\\('",
				),
			),
			'google_maps'        => array(
				'title'      => __( 'Google Maps', 'wphb' ),
				'exclusions' => array(
					'maps.googleapis.com',
					'maps.google.com',
				),
			),
			'google_optimize'    => array(
				'title'      => __( 'Google Optimize', 'wphb' ),
				'exclusions' => array(
					'a,s,y,n,c,h,i,d,e',
					'googleoptimize.com\/optimize.js',
					'async-hide',
				),
			),
			'google_recaptcha'   => array(
				'title'      => __( 'Google Recaptcha', 'wphb' ),
				'exclusions' => array( 'recaptcha' ),
			),
			'google_tag_manager' => array(
				'title'      => __( 'Google Tag Manager', 'wphb' ),
				'exclusions' => array(
					'\/gtag\/js',
					'gtag\\(',
					'\/gtm.js',
					'async-hide',
				),
			),
			'hubspot'            => array(
				'title'      => __( 'HubSpot', 'wphb' ),
				'exclusions' => array(
					'\/jquery-?[0-9.](.*)(.min|.slim|.slim.min)?.js',
					'\/jquery-migrate(.min)?.js',
					'js(.*).hsforms.net',
					'hbspt.forms.create',
				),
			),
			'refari'             => array(
				'title'      => __( 'Refari', 'wphb' ),
				'exclusions' => array(
					'widget.refari.co',
					'refari',
				),
			),
			'reviews_io'         => array(
				'title'      => __( 'Reviews.io', 'wphb' ),
				'exclusions' => array(
					'\/carousel-inline-iframeless\/dist.js',
					'carouselInlineWidget',
				),
			),
			'stripe'             => array(
				'title'      => __( 'Stripe', 'wphb' ),
				'exclusions' => array(
					'js.stripe.com',
				),
			),
			'trust_index'        => array(
				'title'      => __( 'Trustindex', 'wphb' ),
				'exclusions' => array(
					'cdn.trustindex.io\/loader.js',
					'cdn.trustindex.io\/loader-cert.js',
				),
			),
			'typeform'           => array(
				'title'      => __( 'Typeform', 'wphb' ),
				'exclusions' => array(
					'\/next\/embed.js',
				),
			),
			'typekit'            => array(
				'title'      => __( 'Typekit', 'wphb' ),
				'exclusions' => array(
					'typekit',
				),
			),
			'venatus_media'      => array(
				'title'      => __( 'Venatus Media', 'wphb' ),
				'exclusions' => array(
					'\/ad-manager.min.js',
					'__vm_add',
				),
			),
			'wistia'             => array(
				'title'      => __( 'Wistia', 'wphb' ),
				'exclusions' => array(
					'fast.wistia.com',
					'\/assets\/external\/E-v1.js',
				),
			),
			'yandex_ads'         => array(
				'title'      => __( 'Yandex Ads', 'wphb' ),
				'exclusions' => array(
					'yandex.ru',
					'window.yaContextCb',
				),
			),
		);
	}

	/**
	 * Retrieves an associative array of post IDs and their corresponding post titles.
	 *
	 * @param string $setting_key The setting key to retrieve the post URLs from.
	 *
	 * @return array
	 */
	public function get_formatted_post_urls( $setting_key = 'delay_js_post_urls_exclusion' ) {
		$post_urls_exclusion = $this->get_exclusion_setting( $setting_key );

		$post_id_name_map = array();
		foreach ( $post_urls_exclusion as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$post_id_name_map[ $post_id ] = $post->post_title . ' (' . get_permalink( $post->ID ) . ')';
			}
		}

		return $post_id_name_map;
	}

	/**
	 * Search posts based on the provided query.
	 *
	 * @param string $query The search query to find posts.
	 *
	 * @return array The list of posts that match the search query.
	 */
	public function search_posts( $query ) {
		$args = array(
			'posts_per_page' => 10,
			'post_status'    => 'publish',
			's'              => $query,
		);

		$query   = new WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$results[] = array(
					'id'    => get_the_ID(),
					'name'  => get_the_title() . ' (' . get_permalink() . ')',
					'label' => 'urls',
				);
			}
		}

		wp_reset_postdata();

		return $results;
	}

	/**
	 * Reset exclusion settings to their default values.
	 *
	 * @param string $reset_exclusions The type of exclusions to reset.
	 * @param string $type             The type of exclusion to reset.
	 */
	public function reset_exclusion_to_defaults( $reset_exclusions, $type ) {
		$exclusion_settings_map = array(
			'delay_js'     => array(
				'delay_js_files_exclusion',
				'delay_js_post_types_exclusion',
				'delay_js_post_urls_exclusion',
				'delay_js_plugins_themes_exclusion',
				'delay_js_ads_tracker_exclusion',
				'delay_js_exclusions',
			),
			'critical_css' => array(
				'critical_page_types',
				'critical_skipped_custom_post_types',
				'critical_css_files_exclusion',
				'critical_css_post_urls_exclusion',
				'critical_css_plugins_themes_exclusion',
				'critical_css_keywords',
			),
		);

		if ( isset( $exclusion_settings_map[ $type ] ) ) {
			$this->reset_exclusion( $reset_exclusions, $exclusion_settings_map[ $type ] );
		}
	}

	/**
	 * Reset specified exclusion settings to their default values.
	 *
	 * @param string $reset_exclusions     The type of exclusions to reset.
	 * @param array  $exclusion_settings   The exclusion settings keys to reset.
	 */
	private function reset_exclusion( $reset_exclusions, $exclusion_settings ) {
		$defaults = Settings::get_default_settings();
		$minify   = $defaults['minify'];

		// Reset all exclusions if requested.
		if ( in_array( $reset_exclusions, array( 'delay_js_all_exclusions', 'critical_css_all_exclusions' ), true ) ) {
			foreach ( $exclusion_settings as $exclusion ) {
				Settings::update_setting( $exclusion, $minify[ $exclusion ], 'minify' );
			}
		} elseif ( isset( $minify[ $reset_exclusions ] ) && ! empty( $reset_exclusions ) ) {
			Settings::update_setting( $reset_exclusions, $minify[ $reset_exclusions ], 'minify' );
		} elseif ( 'critical_css_post_types_exclusion' === $reset_exclusions ) {
			Settings::update_setting( 'critical_page_types', $minify['critical_page_types'], 'minify' );
			Settings::update_setting( 'critical_skipped_custom_post_types', $minify['critical_skipped_custom_post_types'], 'minify' );
		}
	}

	/**
	 * Check if the current post type should be excluded from delay.
	 *
	 * @return bool
	 */
	public function is_current_post_type_excluded() {
		$excluded_post_type = $this->get_exclusion_setting( 'delay_js_post_types_exclusion' );
		$page_type_checks   = array(
			'frontpage' => is_front_page(),
			'home'      => is_home() && 'posts' !== get_option( 'show_on_front' ) && ! is_multisite(),
			'page'      => is_page(),
			'single'    => is_single(),
			'archive'   => is_archive(),
			'category'  => is_category(),
			'tag'       => is_tag(),
		);

		if ( ! empty( $excluded_post_type ) ) {
			foreach ( $page_type_checks as $type => $check ) {
				if ( $check && in_array( $type, $excluded_post_type, true ) ) {
					return true;
				}
			}
		}

		if ( in_array( get_post_type(), $excluded_post_type, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the current page should be excluded from delay and critical.
	 *
	 * @param string $setting_key The setting key to retrieve the post URLs from.
	 *
	 * @return bool
	 */
	public function is_current_page_excluded( $setting_key = 'delay_js_post_urls_exclusion' ) {
		global $post;
		$setting_key        = 'critical_css' === $setting_key ? 'critical_css_post_urls_exclusion' : $setting_key;
		$selected_exclusion = array_map( 'intval', $this->get_exclusion_setting( $setting_key ) );

		if ( isset( $post->ID ) && in_array( $post->ID, $selected_exclusion, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns and array of excluded URLs.
	 *
	 * @param string $setting_key The setting key to retrieve the post URLs from.
	 * @return array
	 */
	public function get_formatted_theme_plugin_exclusions( $setting_key = 'delay_js_plugins_themes_exclusion' ) {
		$excluded_urls               = array();
		$excluded_plugins_and_themes = $this->get_exclusion_setting( $setting_key );
		foreach ( $excluded_plugins_and_themes as $plugin_or_theme ) {
			if ( 'active_theme' === $plugin_or_theme ) {
				$excluded_urls[] = get_stylesheet_directory_uri();
			} else {
				$excluded_urls[] = plugins_url( '', WP_PLUGIN_DIR . '/' . $plugin_or_theme );
			}
		}

		return $excluded_urls;
	}

	/**
	 * Returns excluded ads and tracker for delay JS.
	 *
	 * @return array
	 */
	public function get_excluded_js_ads_tracker() {
		$selected_exclusion = $this->get_exclusion_setting( 'delay_js_ads_tracker_exclusion' );
		$excluded_item      = array();
		$all_exclusions     = $this->get_ads_tracker_exclusion();

		foreach ( $selected_exclusion as $item ) {
			if ( isset( $all_exclusions[ $item ]['exclusions'] ) ) {
				$excluded_item = array_merge( $excluded_item, $all_exclusions[ $item ]['exclusions'] );
			}
		}

		return $excluded_item;
	}

	/**
	 * Check if the current page should be excluded from delay.
	 *
	 * @return array
	 */
	public function get_excluded_keywords() {
		$excluded = (array) Utils::get_module( 'delayjs' )->get_pre_defined_exclusion_list();
		$excluded = array_unique( array_merge( $excluded, $this->get_manually_excluded_keywords() ) );

		return $excluded;
	}

	/**
	 * Get manually excluded keywords.
	 *
	 * @return array
	 */
	public function get_manually_excluded_keywords() {
		$options             = Utils::get_module( 'minify' )->get_options();
		$delay_js_exclusions = $options['delay_js_exclusions'];

		if ( ! is_array( $delay_js_exclusions ) ) {
			$delay_js_exclusions = explode( "\n", $delay_js_exclusions );
		}

		$delay_js_exclusions = array_map( 'trim', array_filter( $delay_js_exclusions ) );

		if ( ! $delay_js_exclusions ) {
			$delay_js_exclusions = array();
		}

		return $delay_js_exclusions;
	}

	/**
	 * Get all exclusions related to assets path.
	 *
	 * @return array
	 */
	public function get_combined_asset_path_exclusion_list_for_delay_js() {
		$exclusions = array_merge(
			$this->get_exclusion_setting( 'delay_js_files_exclusion' ),
			$this->get_excluded_js_ads_tracker(),
			$this->get_formatted_theme_plugin_exclusions(),
			$this->get_excluded_keywords(),
		);

		$exclusions = array_map( array( $this, 'wphb_clean_scripts' ), $exclusions );

		/**
		 * Filter the list of all exclusions related to assets path.
		 *
		 * @param array $exclusions The list of exclusions.
		 */
		return apply_filters( 'wphb_delay_js_exclusions', $exclusions );
	}

	/**
	 * Cleans JS scripts.
	 *
	 * @since 3.11.0
	 *
	 * @param array $value Script src value.
	 *
	 * @return string
	 */
	public function wphb_clean_scripts( $value ) {
		return trim( str_replace( array( '+', '?ver', '#' ), array( '\+', '\?ver', '\#' ), $value ) );
	}

	/**
	 * Retrieve settings for the exclusion textareas, including default values and descriptions for critical css.
	 *
	 * @return array
	 */
	public function get_critical_css_exclusion_settings() {
		return $this->get_exclusion_settings( 'styles', 'critical_css_' );
	}

	/**
	 * Get selected exclusion for page types and custom post types.
	 *
	 * @return array
	 */
	public function get_critical_css_excluded_post_types() {
		$page_types              = $this->get_exclusion_setting( 'critical_page_types' );
		$all_pages_type          = Page_Cache::get_page_types( true );
		$all_excluded_pages_type = array_diff( $all_pages_type, $page_types );
		$custom_post_types       = $this->get_exclusion_setting( 'critical_skipped_custom_post_types' );

		return array_merge( $all_excluded_pages_type, $custom_post_types );
	}

	/**
	 * Get the exclusion setting.
	 *
	 * @param string $setting_key The setting key to retrieve the post URLs from.
	 *
	 * @return array
	 */
	private function get_exclusion_setting( $setting_key ) {
		return Settings::get_setting( $setting_key, 'minify' );
	}

	/**
	 * Get an array of assets based on the type.
	 *
	 * @param string $type Type.
	 *
	 * @return array
	 */
	public function get_assets( $type ) {
		$assets = Sources_Collector::get_collection();

		return 'styles' === $type ? $assets['styles'] : $assets['scripts'];
	}

	/**
	 * Get all criticL CSS exclusions related to assets path.
	 *
	 * @return array
	 */
	public function get_combined_asset_path_exclusion_list_for_critical_css() {
		$exclusions = array_merge(
			$this->get_exclusion_setting( 'critical_css_files_exclusion' ),
			$this->get_exclusion_setting( 'critical_css_keywords' ),
			$this->get_formatted_theme_plugin_exclusions( 'critical_css_plugins_themes_exclusion' ),
		);

		/**
		 * Filter the list of all exclusions related to assets path for critical CSS.
		 *
		 * @param array $exclusions The list of exclusions.
		 */
		return apply_filters( 'wphb_critical_css_exclusions', $exclusions );
	}

	/**
	 * Get excluded files for critical CSS.
	 *
	 * @return array
	 */
	public function get_excluded_files_for_critical_css() {
		$ignored_files = $this->get_exclusion_setting( 'critical_css_files_exclusion' );
		$styles        = $this->get_assets( 'styles' );

		return $this->get_excluded_src_values( $styles, $ignored_files );
	}

	/**
	 * Get src values from the CSS file.
	 *
	 * @param array $css_file      CSS file.
	 * @param array $ignored_files Keys.
	 *
	 * @return array
	 */
	public function get_excluded_src_values( $css_file, $ignored_files ) {
		$src_values = array();

		foreach ( $ignored_files as $key ) {
			if ( isset( $css_file[ $key ] ) && isset( $css_file[ $key ]['src'] ) ) {
				$src_values[] = $this->ensure_full_url( $css_file[ $key ]['src'] );
			}
		}

		return $src_values;
	}

	/**
	 * Ensure full URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public function ensure_full_url( $url ) {
		if ( strpos( $url, '/' ) === 0 || ( strpos( $url, 'http://' ) === false && strpos( $url, 'https://' ) === false ) ) {
			$url = site_url( $url );
		}

		return $url;
	}

	/**
	 * Get ignored assets for critical API.
	 *
	 * @return array
	 */
	public function get_ignored_files_for_critical_api() {
		$ignored_files = array_merge(
			$this->get_excluded_files_for_critical_css(),
			$this->get_exclusion_setting( 'critical_css_keywords' ),
			$this->get_formatted_theme_plugin_exclusions( 'critical_css_plugins_themes_exclusion' ),
		);

		/**
		 * Filter the list of all ignored files for critical API.
		 *
		 * @param array $ignored_files An array of ignored files.
		 */
		return apply_filters( 'wphb_ignored_files_for_critical_api', $ignored_files );
	}
}
