<?php
/**
 * Async Request for Hummingbird Background Processing
 *
 * @package Hummingbird\Core\Modules\Background
 */

namespace Hummingbird\Core\Modules\Background;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Async_Request class
 *
 * Base class for asynchronous request handling
 */
abstract class HB_Async_Request {

	/**
	 * Prefix for action
	 *
	 * @var string
	 */
	protected $prefix = 'wphb';

	/**
	 * Action name
	 *
	 * @var string
	 */
	protected $action = 'async_request';

	/**
	 * Identifier
	 *
	 * @var string
	 */
	protected $identifier;

	/**
	 * Data to pass to the request
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Constructor
	 *
	 * @param string $identifier Unique identifier for this request type.
	 */
	public function __construct( $identifier ) {
		$this->identifier = $identifier;

		add_action( 'wp_ajax_' . $this->identifier, array( $this, 'maybe_handle' ) );
		add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'maybe_handle' ) );
	}

	/**
	 * Set data
	 *
	 * @param array $data Data to set.
	 * @return $this
	 */
	public function data( $data ) {
		$this->data = $data;
		return $this;
	}

	/**
	 * Dispatch the async request
	 *
	 * @param string $instance_id Instance ID for the request.
	 * @return array|WP_Error
	 */
	public function dispatch( $instance_id = '' ) {
		$url = add_query_arg( $this->get_query_args( $instance_id ), $this->get_query_url() );
		$args = $this->get_post_args();

		return wp_remote_post( esc_url_raw( $url ), $args );
	}

	/**
	 * Get query arguments
	 *
	 * @param string $instance_id Instance ID.
	 * @return array
	 */
	protected function get_query_args( $instance_id = '' ) {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		$args = array(
			'action' => $this->identifier,
			'nonce'  => wp_create_nonce( $this->identifier ),
		);

		if ( ! empty( $instance_id ) ) {
			$args['instance_id'] = $instance_id;
		}

		return $args;
	}

	/**
	 * Get query URL
	 *
	 * @return string
	 */
	protected function get_query_url() {
		if ( property_exists( $this, 'query_url' ) ) {
			return $this->query_url;
		}

		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * Get POST arguments
	 *
	 * @return array
	 */
	protected function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		return array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => $this->data,
			'cookies'   => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

	/**
	 * Maybe handle the request
	 */
	public function maybe_handle() {
		// Override in child classes if needed.
		$this->handle();
	}

	/**
	 * Handle the request
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	abstract protected function handle();

	/**
	 * Get identifier
	 *
	 * @return string
	 */
	public function get_identifier() {
		return $this->identifier;
	}

	/**
	 * Get action name for hooks
	 *
	 * @param string $action Action suffix.
	 * @return string
	 */
	public function action_name( $action ) {
		return "{$this->identifier}_{$action}";
	}
}
