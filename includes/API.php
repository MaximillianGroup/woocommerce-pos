<?php

/**
 * WC REST API Class
 *
 * @package  WCPOS\WooCommercePOS\API
 * @author   Paul Kilmurray <paul@kilbot.com>
 * @link     http://wcpos.com
 */

namespace WCPOS\WooCommercePOS;

use WP_REST_Request;
use WP_REST_Server;

class API {

	private $handler;
	const REST_NAMESPACE = SHORT_NAME . '/v1/';


	/**
	 *
	 */
	public function __construct() {
		//      add_filter( 'rest_index', array( $this, 'rest_index' ) );
		// note: I needed to init WC API patches earlier than rest_dispatch_request for validation patch
		add_filter( 'rest_pre_dispatch', array( $this, 'rest_pre_dispatch' ), 10, 3 );
		add_filter( 'rest_dispatch_request', array( $this, 'rest_dispatch_request' ), 10, 4 );
		add_filter( 'rest_endpoints', array( $this, 'rest_endpoints' ), 99, 1 );

		$this->init();
	}

	/**
	 *
	 */
	public function init() {

		// Validate JWT token
		register_rest_route( self::REST_NAMESPACE, '/jwt/authorize', array(
			'methods'             => 'POST',
			'callback'            => array( new Auth\JWT(), 'generate_token' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'username' => array(
					/* translators: WordPress */
					'description' => __( 'Username', 'wordpress' ),
					'type'        => 'string',
				),
				'password' => array(
					/* translators: WordPress */
					'description' => __( 'Password', 'wordpress' ),
					'type'        => 'string',
				),
			),
		) );

		// Validate JWT token
		register_rest_route( self::REST_NAMESPACE, '/jwt/validate', array(
			'methods'             => 'POST',
			'callback'            => array( new Auth\JWT(), 'validate_token' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'jwt' => array(
					'description' => __( 'JWT token.', PLUGIN_NAME ),
					'type'        => 'string',
				),
			),
		) );

		// Refresh JWT token
		register_rest_route( self::REST_NAMESPACE, '/jwt/refresh', array(
			'methods'             => 'POST',
			'callback'            => array( new Auth\JWT(), 'refresh_token' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'jwt' => array(
					'description' => __( 'JWT token.', PLUGIN_NAME ),
					'type'        => 'string',
				),
			),
		) );

		// Revoke JWT token
		register_rest_route( self::REST_NAMESPACE, '/jwt/revoke', array(
			'methods'             => 'POST',
			'callback'            => array( new Auth\JWT(), 'revoke_token' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'jwt' => array(
					'description' => __( 'JWT token.', PLUGIN_NAME ),
					'type'        => 'string',
				),
			),
		) );

		// Stores
		register_rest_route( self::REST_NAMESPACE, '/stores', array(
			'methods'             => 'GET',
			'callback'            => array( new API\Stores(), 'get_stores' ),
			'permission_callback' => '__return_true',
		) );

		// Settings
		register_rest_route( self::REST_NAMESPACE, '/settings', array(
			'methods'             => 'GET',
			'callback'            => array( new API\Settings(), 'get_settings' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::REST_NAMESPACE, '/settings', array(
			'methods'             => 'POST',
			'callback'            => array( new API\Settings(), 'save_settings' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce_pos' );
			},
		) );
	}

	/**
	 * Filters the pre-calculated result of a REST API dispatch request.
	 *
	 * Allow hijacking the request before dispatching by returning a non-empty. The returned value
	 * will be used to serve the request instead.
	 *
	 * @param mixed $result Response to replace the requested version with. Can be anything
	 *                                 a normal endpoint can return, or null to not hijack the request.
	 * @param WP_REST_Server $server Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function rest_pre_dispatch( $result, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/wc/v3/orders' ) ) {
			$this->handler = new API\Orders( $request );
		}
		if ( 0 === strpos( $request->get_route(), '/wc/v3/products' ) ) {
			$this->handler = new API\Products( $request );
		}
		if ( 0 === strpos( $request->get_route(), '/wc/v3/customers' ) ) {
			$this->handler = new API\Customers( $request );
		}

		return $result;
	}

	/**
	 * Filters the REST API dispatch request result.
	 *
	 * @param mixed $dispatch_result Dispatch result, will be used if not empty.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @param string $route Route matched for the request.
	 * @param array $handler Route handler used for the request.
	 *
	 * @return mixed
	 */
	public function rest_dispatch_request( $dispatch_result, $request, $route, $handler ) {
		$params = $request->get_params();

		if ( isset( $params['posts_per_page'] ) && - 1 == $params['posts_per_page'] && isset( $params['fields'] ) ) {
			if ( $this->handler ) {
				$dispatch_result = $this->handler->get_all_posts( $params['fields'] );
			}
		}

		return $dispatch_result;
	}

	/**
	 * Filters the array of available REST API endpoints.
	 *
	 * @param array $endpoints The available endpoints. An array of matching regex patterns, each mapped
	 *                         to an array of callbacks for the endpoint. These take the format
	 *                         `'/path/regex' => array( $callback, $bitmask )` or
	 *                         `'/path/regex' => array( array( $callback, $bitmask ).
	 *
	 * @return array
	 *
	 */
	public function rest_endpoints( array $endpoints ): array {

		// add ordering by meta_value to customers endpoint
		if ( isset( $endpoints['/wc/v3/customers'] ) ) {
			$endpoint = $endpoints['/wc/v3/customers'];

			// allow ordering by meta_value
			$endpoint[0]['args']['orderby']['enum'][] = 'meta_value';

			// add valid meta_key
			$endpoint[0]['args']['meta_key'] = array(
				'description'       => 'The meta key to query',
				'type'              => 'string',
				'enum'              => array( 'first_name', 'last_name', 'email' ),
				'validate_callback' => 'rest_validate_request_arg',
			);
		}

		return $endpoints;
	}

}
