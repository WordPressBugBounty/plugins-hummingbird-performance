<?php
/**
 * CDN module.
 *
 * @package Hummingbird\Core\Modules
 * @since 4.0
 */

namespace Hummingbird\Core\Modules;

use Hummingbird\Admin\Ajax\Notices;
use Hummingbird\Core\Module;
use Hummingbird\Core\Traits\Module as ModuleContract;
use Hummingbird\Core\Utils;
use Hummingbird\Core\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDN
 */
class Cdn extends Module {
	use ModuleContract;

	/**
	 * CDN hostname (no protocol, no trailing slash).
	 *
	 * @var string
	 */
	private $cdn_base_url;

	/**
	 * Site base URL.
	 *
	 * @var string|null Site base URL.
	 */
	private $site_base_url = null;

	/**
	 * Paths of Asset Optimization files that must stay on the site, keyed by path.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	private $local_paths = array();

	/**
	 * Cron hook name.
	 *
	 * @since 4.0
	 */
	const CRON_HOOK = 'wp_hb_daily_cron';

	/**
	 * API version.
	 *
	 * @access private
	 * @var    string $version
	 */
	private $version = '2.0';

	/**
	 * Execute module actions.
	 *
	 * @since 4.0
	 */
	public function run() {
		add_action( 'admin_init', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'cron_update_stats' ) );
		add_filter( 'wphb_buffer', array( $this, 'rewrite_cdn_urls_in_buffer' ), 9 );
		add_filter( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 99, 2 );

		add_action( 'wphb_minify_group_enqueued', array( $this, 'keep_excluded_group_local' ) );
	}

	/**
	 * Schedule cron
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Update CDN status via Cron.
	 */
	public function cron_update_stats() {
		$this->refresh_status();
	}

	/**
	 * Re-check CDN status with the WPMU DEV API and persist it.
	 *
	 * @since 4.0.0
	 *
	 * @return object|WP_Error The new status, or the API error.
	 */
	public function refresh_status() {
		$api          = Utils::get_api();
		$status       = $this->get_cdn_settings();
		$cdn_enabling = isset( $status->cdn_enabling ) && $status->cdn_enabling;
		$api_response = $cdn_enabling ? $api->cdn->maybe_enable_cdn() : $api->cdn->cdn_check();
		$new_status   = $this->parse_cdn_status_response( $api_response );

		if ( $new_status && ! is_wp_error( $new_status ) ) {
			$previous_url = $this->get_active_cdn_url();

			Settings::update_setting( 'cdn_settings', $new_status, 'cdn' );

			$this->purge_page_cache_on_url_change( $previous_url );
		}

		return $new_status;
	}

	/**
	 * Data localized for the React CDN page, and returned by the AJAX
	 * endpoints after a toggle/refresh so the UI can update without a
	 * separate round trip.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function get_react_settings() {
		$options  = $this->get_options();
		$settings = $options['cdn_settings'];

		return array(
			'enabled'         => $this->is_active(),
			'exclusions'      => implode( "\n", (array) $options['excluded_files'] ),
			'cdnEnabling'     => ! empty( $settings->cdn_enabling ),
			'endpointUrl'     => ! empty( $settings->endpoint_url ) ? $settings->endpoint_url : '',
			'statusString'    => $this->get_status_string( $this->is_active(), $settings ),
			'bandwidthUrl'    => Utils::get_link( 'account', 'hummingbird_cdn_bandwidth' ),
			'noticeDismissed' => Notices::is_dismissed( 'cdn-exclusions-info' ),
			'aoEnabled'       => Utils::get_module( 'minify' )->is_active(),
		);
	}

	/**
	 * Get CDN status data.
	 *
	 * @since 4.0.0
	 *
	 * @param bool         $enabled  Local on/off toggle.
	 * @param object|array $settings Stored `cdn_settings` (API response).
	 *
	 * @return string 'disabled'|'activating'|'overcap'|'upgrade'|'enabled'
	 */
	private function get_status_string( $enabled, $settings ) {
		if ( ! $enabled ) {
			return 'disabled';
		}

		if ( ! empty( $settings->cdn_enabling ) ) {
			return 'activating';
		}

		$bandwidth      = isset( $settings->bandwidth ) ? (float) $settings->bandwidth : 0.0;
		$bandwidth_plan = ! empty( $settings->bandwidth_plan ) ? (int) $settings->bandwidth_plan : 10;
		$percentage     = round( 100 * $bandwidth / 1024 / 1024 / 1024 / $bandwidth_plan );

		if ( $percentage >= 100 ) {
			return 'overcap';
		}

		if ( $percentage >= 90 ) {
			return 'upgrade';
		}

		return ! empty( $settings->cdn_enabled ) ? 'enabled' : 'disabled';
	}

	/**
	 * Check if CDN is active in settings.
	 */
	public function is_active() {
		$options = $this->get_options();

		return Utils::is_member() && ! empty( $options['enabled'] );
	}

	/**
	 * Determine if we should rewrite content for CDN.
	 *
	 * @return bool
	 */
	protected function should_rewrite_cdn_url() {
		static $should_rewrite = null;

		if ( null !== $should_rewrite ) {
			return $should_rewrite;
		}

		$should_rewrite = ! ( is_admin() || is_customize_preview() || is_embed() );

		return $should_rewrite;
	}

	/**
	 * Rewrite URLs in HTML buffer to CDN URLs.
	 *
	 * @param string $html HTML content buffer.
	 * @return string
	 */
	public function rewrite_cdn_urls_in_buffer( $html ) {
		if ( ! $this->should_rewrite_cdn_url() ) {
			return $html;
		}

		if ( ! $this->can_use_cdn() ) {
			return $html;
		}

		$site_url     = $this->get_site_base_url();
		$site_escaped = preg_quote( $site_url, '#' );
		$paths        = $this->get_allowed_paths();

		// Match absolute or relative URLs pointing to CSS and JS files (with optional query strings).
		$pattern = '#<[^>]+(?:href|src)=\s*(?<full>(?<prefix>["\'(])\s*' .
		'(?<url>' .
		'(?:https?:)?' . $site_escaped . '/(?:' . $paths . ')[^"\',)\s]+\.(?:css|js)(?:\?[^"\',)\s]*)?' .
		'|' .
		'/(?!/)[^"\')>\s]+\.(?:css|js)(?:\?[^"\',)\s]*)?' .
		')\s*' .
		'(?<suffix>["\')]))[^>]*>#i';

		/**
		 * Filter the regex pattern used to match URLs for CDN rewriting.
		 *
		 * @since 4.0
		 *
		 * @param string $pattern The regex pattern.
		 */
		$pattern = apply_filters( 'wphb_cdn_rewrite_pattern', $pattern );

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				return str_replace( $matches['url'], $this->rewrite_url( $matches['url'], $matches[0] ), $matches[0] );
			},
			$html
		);
	}

	/**
	 * Rewrite individual URL match to CDN.
	 *
	 * @param string $url      Regex matches.
	 * @param string $full_tag Full tag.
	 * @return string
	 */
	public function rewrite_url( $url, $full_tag ) {
		// Avoid rewriting backend/admin URLs.
		if ( stripos( $url, admin_url() ) !== false ) {
			return $url;
		}

		$cdn_base = trim( $this->get_cdn_base_url() );

		if ( empty( $cdn_base ) ) {
			return $url;
		}

		// Skip if already using CDN.
		if ( strpos( $url, $cdn_base ) === 0 ) {
			return $url;
		}

		// Skip if file is excluded.
		if ( $this->is_file_excluded( $full_tag ) ) {
			return $url;
		}

		// Skip Asset Optimization files built from an excluded source.
		if ( isset( $this->local_paths[ wp_parse_url( $url, PHP_URL_PATH ) ] ) ) {
			return $url;
		}

		$parsed = wp_parse_url( $url );

		// Handle relative URLs (e.g., /wp-content/... or images/foo.jpg).
		if ( empty( $parsed['host'] ) ) {
			return rtrim( $cdn_base, '/' ) . '/' . ltrim( $url, '/' );
		}

		$home_host = ltrim( $this->get_site_base_url( false ), '//' );

		// For scheme-less (protocol-relative) URLs.
		if ( empty( $parsed['scheme'] ) ) {
			$cdn_host = preg_replace( '#^https?:\/\/#i', '', $cdn_base );
			return str_ireplace( $home_host, $cdn_host, $url );
		}

		// Replace full home URL with CDN base.
		foreach ( array( 'http', 'https' ) as $scheme ) {
			$url = str_ireplace( $scheme . '://' . $home_host, rtrim( $cdn_base, '/' ), $url );
		}

		// Remove all query params before adding the digest, using the original query string for the digest value.
		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['query'] ) && '' !== $parsed_url['query'] ) {
			$digest = md5( $parsed_url['query'] );
			// Remove the query string from the URL entirely.
			$url = preg_replace( '/\?.*/', '', $url ) . '?digest=' . $digest;
		}

		return $url;
	}

	/**
	 * Get CDN status.
	 *
	 * @return object
	 */
	private function get_cdn_base_url() {
		if ( is_null( $this->cdn_base_url ) ) {
			$this->cdn_base_url = $this->prepare_cdn_base_url();
		}

		return $this->cdn_base_url;
	}

	/**
	 * Get CDN settings.
	 *
	 * @return object|null
	 */
	public function get_cdn_settings() {
		$options = $this->get_options();

		return $options['cdn_settings'];
	}

	/**
	 * Get the prepared CDN base URL.
	 *
	 * @return string
	 */
	private function prepare_cdn_base_url() {
		$settings = $this->get_cdn_settings();

		if ( empty( $settings ) || empty( $settings->site_id ) || empty( $settings->endpoint_url ) ) {
			return '';
		}

		$site_id  = absint( $settings->site_id );
		$endpoint = untrailingslashit( $settings->endpoint_url );

		return trailingslashit(
			sprintf(
				'https://%s/%s/%d',
				untrailingslashit( $settings->endpoint_url ),
				$this->version,
				absint( $settings->site_id )
			)
		);
	}

	/**
	 * Check if CDN is ready to be used.
	 *
	 * @return bool
	 */
	private function can_use_cdn() {
		// The CDN module needs to be active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// CDN does not work on staging.
		if ( $this->is_staging_environment() ) {
			return false;
		}

		$setting = $this->get_cdn_settings();
		if ( empty( $setting ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if current environment is staging.
	 *
	 * @return bool
	 */
	private function is_staging_environment() {
		$env = isset( $_SERVER['WPMUDEV_HOSTING_ENV'] ) ? sanitize_text_field( wp_unslash( $_SERVER['WPMUDEV_HOSTING_ENV'] ) ) : '';

		return 'staging' === $env;
	}

	/**
	 * Check if a file is excluded from CDN.
	 *
	 * @param string $file_url File URL.
	 *
	 * @return bool
	 */
	public function is_file_excluded( $file_url ) {
		if ( empty( $file_url ) ) {
			return false;
		}

		static $excluded_files = null;

		if ( null === $excluded_files ) {
			$options        = $this->get_options();
			$excluded_files = $options['excluded_files'] ?? array();
		}

		if ( ! is_array( $excluded_files ) || empty( $excluded_files ) ) {
			return false;
		}

		foreach ( $excluded_files as $pattern ) {
			// Escape the pattern to prevent unsafe regex.
			$escaped_pattern = preg_quote( $pattern, '#' );

			if ( preg_match( "#{$escaped_pattern}#i", $file_url ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep the generated file of an Asset Optimization group off the CDN when one of its sources is excluded.
	 *
	 * @since 4.0.0
	 *
	 * @param \Hummingbird\Core\Modules\Minify\Minify_Group $group Group being enqueued.
	 */
	public function keep_excluded_group_local( $group ) {
		if ( ! $this->is_active() ) {
			return;
		}

		foreach ( $group->get_handles() as $handle ) {
			if ( $this->is_file_excluded( $group->get_handle_url( $handle ) ) ) {
				$this->local_paths[ wp_parse_url( $group->get_file_url(), PHP_URL_PATH ) ] = true;
				return;
			}
		}
	}

	/**
	 * Retrieves the base URL of the site, with optional protocol prefix.
	 *
	 * @param bool $with_protocol Optional. Whether to include the 'https://' protocol. Default false.
	 *
	 * @return string The base URL of the site.
	 */
	private function get_site_base_url( bool $with_protocol = false ): string {
		if ( empty( $this->site_base_url ) ) {
			$this->site_base_url = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		return $with_protocol ? 'https://' . $this->site_base_url : '//' . $this->site_base_url;
	}

	/**
	 * Returns the allowed directory paths for CDN rewriting as a regex group.
	 *
	 * @return string Regex group of allowed paths.
	 */
	private function get_allowed_paths() {
		static $paths = null;

		if ( null !== $paths ) {
			return $paths;
		}

		$list = array();

		$add = static function ( $url ) use ( &$list ) {
			$path = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
			if ( $path ) {
				$list[] = preg_quote( trailingslashit( $path ), '#' );
			}
		};

		$add( content_url() );
		$add( includes_url() );

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) ) {
			$add( $uploads['baseurl'] );
		}

		$paths = implode( '|', $list );

		return $paths;
	}

	/**
	 * Toggle CDN helper function.
	 *
	 * @param bool $value CDN status to set.
	 */
	public function toggle_cdn( $value ) {
		$previous_url       = $this->get_active_cdn_url();
		$options            = $this->get_options();
		$options['enabled'] = $value;
		$this->update_options( $options );

		if ( $value ) {
			$this->enable_cdn();
		} else {
			// Otherwise this site's server keeps pinging the CDN API daily
			// forever, even after the feature is turned off.
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		$this->purge_page_cache_on_url_change( $previous_url );
	}

	/**
	 * The CDN base URL rewriting is currently using, or an empty string when nothing is rewritten.
	 *
	 * @since 4.0
	 *
	 * @return string
	 */
	private function get_active_cdn_url(): string {
		$this->cdn_base_url = null;

		return $this->is_active() ? $this->get_cdn_base_url() : '';
	}

	/**
	 * Purge the page cache when the rewrite target changed.
	 *
	 * @since 4.0
	 *
	 * @param string $previous_url Rewrite target in effect before the settings write.
	 *
	 * @return void
	 */
	private function purge_page_cache_on_url_change( string $previous_url ): void {
		if ( $previous_url === $this->get_active_cdn_url() ) {
			return;
		}

		Utils::get_module( 'page_cache' )->clear_cache();
	}

	/**
	 * Enable CDN for the current site.
	 *
	 * @since 4.0
	 *
	 * @return void
	 */
	public function enable_cdn() {
		$api = Utils::get_api();

		$enable_response = $this->parse_cdn_status_response( $api->cdn->maybe_enable_cdn() );
		if ( is_wp_error( $enable_response ) ) {
			return;
		}

		Settings::update_setting( 'cdn_settings', $enable_response, 'cdn' );
	}

	/**
	 * Handles and processes the response received from the CDN enpoint.
	 *
	 * @since 4.0
	 *
	 * @param array|WP_Error $response API response.
	 * @return object|WP_Error
	 */
	public function parse_cdn_status_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Too many requests.
		if ( is_null( $response ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests, please try again in a moment.', 'wphb' ) );
		}

		// Some other error from API.
		if ( ! $response->success ) {
			return new WP_Error( $response->data->error_code, $response->data->message );
		}

		return $response->data;
	}

	/**
	 * Add DNS prefetch for CDN URL.
	 *
	 * @param array  $urls          Array of resources and their attributes, or URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for. One of 'dns-prefetch', 'preconnect', 'prefetch', or 'prerender'.
	 * @return array
	 */
	public function dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}

		if ( ! $this->can_use_cdn() ) {
			return $urls;
		}

		$cdn_base_url = $this->get_cdn_base_url();
		if ( empty( $cdn_base_url ) ) {
			return $urls;
		}

		if ( in_array( $cdn_base_url, $urls, true ) ) {
			return $urls;
		}

		$urls[] = $cdn_base_url;

		return $urls;
	}
}
