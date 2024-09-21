<?php
/**
 * Fonts optimization.
 *
 * @package Hummingbird\Core\Modules
 * @since 3.8.0
 */

namespace Hummingbird\Core\Modules\Minify;

use Hummingbird\Core\Utils;
use Hummingbird\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fonts
 */
class Fonts {

	/**
	 * Key to store the Google font domain.
	 *
	 * @var string
	 */
	const GOOGLE_FONT_DOMAIN = 'https://fonts.googleapis.com/css';

	/**
	 * Instantiate the class.
	 *
	 * @since  3.8.0
	 */
	public function __construct() {
		add_filter( 'wphb_buffer', array( $this, 'wphb_process_buffer' ), 9999 );
		add_filter( 'wphb_minify_file_content', array( $this, 'wphb_minify_file_content' ), 10, 4 );
		add_filter( 'wp_hummingbird_default_options', array( $this, 'wp_hummingbird_default_options' ) );
	}

	/**
	 * Process the buffer.
	 *
	 * @param string $html Buffer content.
	 *
	 * @return string
	 */
	public function wphb_process_buffer( $html ) {
		$html = $this->add_font_display_swap_to_all_font_faces( $html );
		$html = $this->wphb_preload_fonts( $html );

		return $html;
	}

	/**
	 * Set default value for preload_fonts_mode to manual if free user is detected.
	 *
	 * @param array $defaults An array of default settings.
	 *
	 * @return array
	 */
	public function wp_hummingbird_default_options( $defaults ) {
		if ( ! Utils::is_member() ) {
			$defaults['minify']['preload_fonts_mode'] = 'manual';
		}

		return $defaults;
	}

	/**
	 * Check if minify is active.
	 *
	 * @return bool
	 */
	public function is_minify_enabled() {
		return Utils::get_module( 'minify' )->is_active();
	}

	/**
	 * Check if font preloading is enabled.
	 *
	 * @return bool
	 */
	public function is_preload_enabled() {
		if ( is_feed() ) {
			return false;
		}

		$options = Settings::get_settings( 'minify' );

		return $this->is_minify_enabled() && $options['font_optimization'];
	}

	/**
	 * Check if font swapping is enabled.
	 *
	 * @return bool
	 */
	public function is_font_swap_enabled() {
		$options = Settings::get_settings( 'minify' );

		return $this->is_minify_enabled() && $options['font_swap'];
	}

	/**
	 * Applies font-display: swap to all font-family declarations.
	 *
	 * @since  3.8.0
	 *
	 * @param string $content  HTML page buffer.
	 * @param string $handle   Handle.
	 * @param string $type     Resource type.
	 * @param string $is_local IS handle local.
	 *
	 * @return string
	 */
	public function wphb_minify_file_content( $content, $handle, $type, $is_local ) {
		if ( 'styles' === $type ) {
			return $this->add_font_display_swap_to_all_font_faces( $content );
		}

		return $content;
	}

	/**
	 * Preload fonts.
	 *
	 * @since  3.8.0
	 * @param string $html HTML page buffer.
	 *
	 * @return string
	 */
	public function wphb_preload_fonts( $html ) {
		$html = $this->add_preload_to_local_fonts( $html );

		return $this->combine_google_fonts( $html );
	}

	/**
	 * Get manually defined fonts for preloading.
	 *
	 * @since 3.8.0
	 *
	 * @return array
	 */
	public function get_manually_defined_fonts_for_preloading() {
		$options       = Utils::get_module( 'minify' )->get_options();
		$preload_fonts = $options['preload_fonts'];

		if ( ! is_array( $preload_fonts ) ) {
			$preload_fonts = explode( "\n", $preload_fonts );
		}

		$preload_fonts = array_filter( $preload_fonts );

		return $preload_fonts;
	}

	/**
	 * Add preload to manually defined fonts.
	 *
	 * @param string $html Html buffer.
	 *
	 * @return string
	 *
	 * @since 3.8.0
	 */
	public function add_preload_to_local_fonts( $html ) {
		if ( ! $this->is_preload_enabled() ) {
			return $html;
		}

		/**
		 * Filters whether to disable preload fonts or not.
		 *
		 * @since 3.8.0
		 *
		 * @param bool $disable_preload_fonts True to disable, false otherwise.
		 */
		if ( apply_filters( 'wphb_disable_preload_fonts', false ) ) {
			return $html;
		}

		$preload_fonts = $this->get_manually_defined_fonts_for_preloading();
		if ( empty( $preload_fonts ) ) {
			return $html;
		}

		/**
		 * Filters the local fonts to preload.
		 *
		 * @since 3.8.0
		 *
		 * @param array $fonts Array of local fonts.
		 */
		$preload_fonts = array_map( array( $this, 'is_valid_font_url' ), (array) apply_filters( 'wphb_preload_fonts', $preload_fonts ) );
		$preload_fonts = array_unique( array_filter( $preload_fonts ) );

		if ( empty( $preload_fonts ) ) {
			return $html;
		}

		$preloads = '';

		foreach ( $preload_fonts as $font ) {
			$font_url  = wp_parse_url( $font, PHP_URL_PATH );
			$preloads .= $this->get_local_font_preload_markup( home_url( $font_url ) );
		}

		return preg_replace( '#</title>#iU', '</title>' . $preloads, $html, 1 );
	}

	/**
	 * Verifies that a font is valid.
	 *
	 * This function is used to validate a font file path. It checks if the file has a valid font file extension.
	 *
	 * @param string $font_url The path to the font file.
	 *
	 * @since 3.8.0
	 *
	 * @return string|bool
	 */
	public function is_valid_font_url( $font_url ) {
		$font_url = trim( $font_url );
		$font_ext = wp_parse_url( $font_url, PHP_URL_PATH );

		$font_ext = strtolower( pathinfo( $font_ext, PATHINFO_EXTENSION ) );

		if ( ! in_array( $font_ext, $this->get_allowed_fonts(), true ) ) {
			return false;
		}

		return $font_url;
	}

	/**
	 * Add preload links to font in USED CSS.
	 *
	 * @param string $html     HTML page content.
	 * @param string $used_css Used CSS content.
	 *
	 * @return string
	 */
	public function add_preload_to_fonts_in_used_css( $html, $used_css ) {
		if ( ! $this->is_preload_enabled() ) {
			return $html;
		}

		$preload_fonts_mode = $this->get_preload_fonts_mode();
		if ( 'automatic' !== $preload_fonts_mode ) {
			return $html;
		}

		/**
		 * Filters whether to add preload links to font in USED CSS or not.
		 *
		 * @since 3.8.0
		 *
		 * @param bool $add True to add preload, false to ignore.
		 */
		if ( ! apply_filters( 'wphb_add_preload_to_font_used_css', true ) ) {
			return $html;
		}

		$font_faces = $this->find_matches( '/@font-face\s*{\s*(?<data>[^}]+)}/is', $used_css );

		if ( empty( $font_faces ) ) {
			return $html;
		}

		$urls          = array();
		$preload_fonts = '';

		foreach ( $font_faces as $font_face ) {
			if ( ! empty( $font_face['data'] ) ) {
				$font_url = $this->get_first_font_url( $font_face['data'] );

				if ( ! empty( $font_url ) ) {
					$font_url = $this->correct_the_font_url( $font_url );
					if ( $this->is_font_url_manually_defined( $font_url ) ) {
						continue;
					}

					$urls[] = $font_url;
				}
			}
		}

		if ( ! empty( $urls ) ) {
			$urls          = array_unique( array_filter( $urls ) );
			$preload_fonts = $this->generate_preload_fonts( $urls );
		}

		$replace = preg_replace( '#</title>#iU', '</title>' . $preload_fonts, $html, 1 );

		return $replace ?? $html;
	}

	/**
	 * Check if font URL is manually defined.
	 *
	 * @param string $font_url Font URL.
	 *
	 * @return bool
	 */
	public function is_font_url_manually_defined( $font_url ) {
		$preload_fonts = $this->get_manually_defined_fonts_for_preloading();

		if ( empty( $preload_fonts ) ) {
			return false;
		}

		foreach ( $preload_fonts as $preload_font ) {
			$font_url = untrailingslashit( ltrim( $font_url, '/' ) );
			if ( strpos( $font_url, $preload_font ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Correct the local font URL.
	 *
	 * @param string $font_url HTML page content.
	 *
	 * @return string
	 */
	public function correct_the_font_url( $font_url ) {
		if ( strpos( $font_url, '../' ) === false ) {
			return $font_url;
		}

		$directories = array( '/themes/', '/plugins/', '/uploads/' );
		foreach ( $directories as $directory ) {
			if ( strpos( $font_url, $directory ) !== false ) {
				$font_url = content_url( '/' ) . strstr( $font_url, $directory );

				return $font_url;
			}
		}

		return '';
	}

	/**
	 * Find the first font URL from the font-face declaration.
	 *
	 * @param string $font_face Font-face declaration content.
	 *
	 * @return string
	 */
	public function get_first_font_url( $font_face ) {
		$sources = $this->find_matches( '/src:\s*(?<urls>[^;}]*)/is', $font_face );
		if ( empty( $sources ) ) {
			return '';
		}

		return array_reduce(
			$sources,
			function ( $carry, $src ) {
				if ( ! empty( $carry ) ) {
					return $carry;
				}

				if ( empty( $src['urls'] ) ) {
					return '';
				}

				$urls = explode( ',', $src['urls'] );

				foreach ( $urls as $url ) {
					if ( false !== strpos( $url, '.eot' ) ) {
						continue;
					}

					if ( preg_match( '/url\(\s*[\'"]?(?<url>[^\'")]+)[\'"]?\)/is', $url, $matches ) ) {
						return trim( $matches['url'] );
					}
				}

				return '';
			}
		);
	}

	/**
	 * Converts URLs to preload link tags.
	 *
	 * @param array $urls An array of font URLs.
	 *
	 * @return string
	 */
	public function generate_preload_fonts( $urls ) {
		$preload_fonts = '';

		foreach ( $urls as $url ) {
			$preload_fonts .= $this->get_local_font_preload_markup( $url );
		}

		return $preload_fonts;
	}

	/**
	 * Return Markup for preload local fonts.
	 *
	 * @param array $url Font URL.
	 *
	 * @return string
	 */
	public function get_local_font_preload_markup( $url ) {
		return sprintf(
			'<link rel="preload" as="font" href="%s" crossorigin>',
			esc_url( $url )
		);
	}

	/**
	 * Returns Font formats allowed to be preloaded.
	 *
	 * @since 3.8.0
	 *
	 * @return array
	 */
	public function get_allowed_fonts() {
		$fonts = array(
			'otf',
			'ttf',
			'svg',
			'woff',
			'woff2',
		);

		/**
		 * Filters the array of allowed fonts.
		 *
		 * @since 3.8.0
		 *
		 * @param array $fonts Array of allowed fonts that we are preloading.
		 */
		return (array) apply_filters( 'wphb_allowed_preload_fonts', $fonts );
	}

	/**
	 * Add or update font-display: swap to all font-family declarations.
	 *
	 * @since 3.8.0
	 *
	 * @param string $css_content CSS content.
	 *
	 * @return string
	 */
	public function add_font_display_swap_to_all_font_faces( $css_content ) {
		if ( ! $this->is_font_swap_enabled() ) {
			return $css_content;
		}

		$font_display_value = $this->get_font_display_value();

		$css_content = preg_replace_callback(
			'/@font-face\s*{(?<display_value>[^}]+)}/i',
			function ( $matches ) use ( $font_display_value ) {
				if ( preg_match( '/font-display:\s*(\w*);?/i', $matches['display_value'], $attribute ) ) {
					if ( strtolower( $attribute[1] ) === $font_display_value ) {
						return $matches[0];
					}

					return preg_replace( '/(font-display:\s*)\w*;?/i', '$1' . $font_display_value . ';', $matches[0] );
				}

				return str_replace( $matches['display_value'], "font-display: $font_display_value;{$matches['display_value']}", $matches[0] );
			},
			$css_content
		);

		return $css_content;
	}

	/**
	 * Combine multiple Google fonts URLs.
	 *
	 * Derived from https://gist.github.com/eugenealegiojo/dbdd620a998458aa2eb1f124b2f0b18e
	 *
	 * @param string $html HTML buffer content.
	 */
	public function combine_google_fonts( $html ) {
		if ( ! $this->is_font_swap_enabled() ) {
			return $html;
		}

		/**
		 * Filters whether to disable Google font combine or not.
		 *
		 * @since 3.8.0
		 *
		 * @param bool $disable_preload_fonts True to disable, false otherwise.
		 */
		if ( apply_filters( 'wphb_disable_google_font_combine', false ) ) {
			return $html;
		}

		$matches = $this->find_matches( '/<link(?:\s+(?:(?!href\s*=\s*)[^>])+)?(?:\s+href\s*=\s*([\'"])(?<url>(?:https?:)?\/\/fonts\.googleapis\.com\/css[^\d](?:(?!\1).)+)\1)(?:\s+[^>]*)?>/Umsi', $html );
		if ( empty( $matches ) ) {
			return $html;
		}

		$google_fonts_data = array();
		$families          = array();
		$subsets           = array();
		$font_args         = array();

		// Process all Google fonts.
		foreach ( $matches as $match ) {
			$style_src = html_entity_decode( $match[2] );
			$url       = wp_parse_url( $style_src );
			if ( is_string( $url['query'] ) ) {
				parse_str( $url['query'], $parsed_url );

				if ( isset( $parsed_url['family'] ) ) {
					// Collect all subsets.
					if ( isset( $parsed_url['subset'] ) ) {
						$subsets[] = rawurlencode( trim( $parsed_url['subset'] ) );
					}

					$font_families = explode( '|', $parsed_url['family'] );
					foreach ( $font_families as $parsed_font ) {
						$get_font = explode( ':', $parsed_font );

						// Extract the font data.
						if ( ! empty( $get_font[0] ) ) {
							$family  = $get_font[0];
							$weights = ! empty( $get_font[1] ) ? explode( ',', $get_font[1] ) : array();

							// Combine weights if family has been enqueued.
							if ( isset( $google_fonts_data[ $family ] ) && $weights !== $google_fonts_data[ $family ]['weights'] ) {
								$combined_weights                        = array_merge( $weights, $google_fonts_data[ $family ]['weights'] );
								$google_fonts_data[ $family ]['weights'] = array_unique( $combined_weights );
							} else {
								$google_fonts_data[ $family ] = array(
									'family'  => $family,
									'weights' => $weights,
								);
							}
						}
					}
				}
			}
		}

		// Combine all extracted fonts.
		if ( count( $google_fonts_data ) > 0 ) {
			foreach ( $google_fonts_data as $family => $data ) {
				if ( ! empty( $data['weights'] ) ) {
					$families[] = $family . ':' . implode( ',', $data['weights'] );
				} else {
					$families[] = $family;
				}
			}

			if ( ! empty( $families ) ) {
				$font_args['family'] = implode( '|', $families );

				if ( ! empty( $subsets ) ) {
					$font_args['subset'] = implode( ',', $subsets );
				}

				$font_display_value = $this->get_font_display_value();

				/**
				 * Filters the display swap for Google.
				 *
				 * @since 3.8.0
				 *
				 * @param string
				 */
				$font_display = apply_filters( 'wphb_google_font_display', $font_display_value );

				if ( ! empty( $font_display ) ) {
					$font_args['display'] = $font_display;
				}

				/**
				 * Filters the Google font domain.
				 *
				 * @since 3.8.0
				 *
				 * @param string
				 */
				$fonts_domain = apply_filters( 'wphb_google_fonts_domain', self::GOOGLE_FONT_DOMAIN );

				$src = esc_url_raw( add_query_arg( $font_args, $fonts_domain ) );

				foreach ( $matches as $font ) {
					$html = str_replace( $font[0], '', $html );
				}

				$html = preg_replace( '/<\/title>/i', '$0' . $this->get_google_font_preload_markup( $src ), $html, 1 );
			}
		}

		return $html;
	}

	/**
	 * Returns the Google Fonts markup.
	 *
	 * @since 3.8.0
	 *
	 * @param string $url Google Fonts URL.
	 *
	 * @return string
	 */
	public function get_google_font_preload_markup( $url ) {
		$preload_markup = sprintf(
			'<link rel="preload" as="style" href="%1$s" />',
			$url
		);

		$stylesheet_markup = sprintf(
			'<link rel="stylesheet" href="%1$s" media="print" onload="this.media=\'all\'" />', // phpcs:ignore
			$url
		);

		$noscript_markup = sprintf(
			'<noscript><link rel="stylesheet" href="%1$s" /></noscript>', // phpcs:ignore
			$url
		);

		return $preload_markup . $stylesheet_markup . $noscript_markup;
	}

	/**
	 * Finds given patterns in the HTML.
	 *
	 * @param string $pattern Pattern to match.
	 * @param string $html    HTML content.
	 *
	 * @return array
	 */
	public function find_matches( $pattern, $html ) {
		preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return array();
		}

		return $matches;
	}

	/**
	 * Get the font display value.
	 *
	 * @return string
	 */
	public function get_font_display_value() {
		$font_display_value = Settings::get_setting( 'font_display_value', 'minify' );

		return ! empty( $font_display_value ) ? $font_display_value : 'swap';
	}

	/**
	 * Get the preload fonts mode.
	 *
	 * @return string
	 */
	public function get_preload_fonts_mode() {
		$preload_fonts_mode = Settings::get_setting( 'preload_fonts_mode', 'minify' );

		return ! empty( $preload_fonts_mode ) ? $preload_fonts_mode : 'automatic';
	}
}
