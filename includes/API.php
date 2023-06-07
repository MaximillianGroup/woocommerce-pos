<?php

/**
 * REST API Class.
 *
 * @author   Paul Kilmurray <paul@kilbot.com>
 *
 * @see     http://wcpos.com
 */

namespace WCPOS\WooCommercePOS;

use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Ramsey\Uuid\Uuid;

class API {
	/**
	 * WCPOS REST API namespaces and endpoints.
	 *
	 * @var array
	 */
	protected $controllers = array();
	private $wc_rest_api_handler;


	public function __construct() {
		// Init and register routes for the WCPOS REST API
		$this->controllers = array(
			'auth'     => new API\Auth(),
			'settings' => new API\Settings(),
			'stores'   => new API\Stores(),
			'emails'   => new API\Order_Emails(),
		);

		foreach ( $this->controllers as $key => $controller_class ) {
			$controller_class->register_routes();
		}

		// Allows requests from WCPOS Desktop and Mobile Apps
		add_filter( 'rest_allowed_cors_headers', array( $this, 'rest_allowed_cors_headers' ), 10, 1 );
		add_filter( 'rest_pre_serve_request', array( $this, 'rest_pre_serve_request' ), 10, 4 );

		// Adds authentication to for JWT bearer tokens
		add_filter( 'determine_current_user', array( $this, 'determine_current_user' ) );

		// Adds uuid for the WordPress install
		add_filter( 'rest_index', array( $this, 'rest_index' ), 10, 1 );

		/*
		 * These filters allow changes to the WC REST API response
		 * Note: I needed to init WC API patches earlier than rest_dispatch_request for validation patch
		 * Note: The rest_request_before_callbacks filter needs any early priority because we may use in the loaded classes
		 */
		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ), 5, 3 );
		add_filter( 'rest_dispatch_request', array( $this, 'rest_dispatch_request' ), 10, 4 );

		$this->prevent_messages();
	}

	/**
	 * Error messages and notices can cause the JSON response to fail.
	 */
	private function prevent_messages() {
		error_reporting( 0 );
		@ini_set( 'display_errors', 0 );
	}

	/**
	 * Add CORS headers to the REST API response.
	 *
	 * @param string[] $allow_headers The list of request headers to allow.
	 *
	 * @return string[] $allow_headers
	 */
	public function rest_allowed_cors_headers( array $allow_headers ): array {
		$allow_headers[] = 'X-WCPOS';
		$allow_headers[] = 'X-HTTP-Method-Override';

		return $allow_headers;
	}

	/**
	 * Add Access Control Allow Headers for POS app.
	 *
	 * @param bool             $served  Whether the request has already been served.
	 *                                  Default false.
	 * @param WP_HTTP_Response $result  Result to send to the client. Usually a `WP_REST_Response`.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  Server instance.
	 *
	 * @return bool $served
	 */
	public function rest_pre_serve_request( bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		$server->send_header( 'Access-Control-Allow-Origin', '*' );

		return $served;
	}

	/**
	 * Check request for any login tokens.
	 *
	 * @param false|int $user_id User ID if one has been determined, false otherwise.
	 *
	 * @return false|int|void
	 */
	public function determine_current_user( $user_id ) {
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		// extract Bearer token from Authorization Header
		list($token) = sscanf( $this->get_auth_header(), 'Bearer %s' );

		if ( $token ) {
			$decoded_token = $this->controllers['auth']->validate_token( $token, false );

			if ( empty( $decoded_token ) || is_wp_error( $decoded_token ) ) {
				return $user_id;
			}
			$user = ! empty( $decoded_token->data->user->id ) ? $decoded_token->data->user->id : $user_id;

			return absint( $user );
		}

		return $user_id;
	}

	/**
	 * @return false|string
	 */
	public function get_auth_header() {
		// Get HTTP Authorization Header.
		$header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( $_SERVER['HTTP_AUTHORIZATION'] ) : false;

		// Check for alternative header.
		if ( ! $header && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}

		return $header;
	}

	/**
	 * Add uuid to the WP REST API index.
	 *
	 * @param WP_REST_Response $response Response data
	 * @return WP_REST_Response
	 */
	public function rest_index( WP_REST_Response $response ): WP_REST_Response {
		$uuid = get_option( 'woocommerce_pos_uuid' );
		if ( ! $uuid ) {
			$uuid = Uuid::uuid4()->toString();
			update_option( 'woocommerce_pos_uuid', $uuid );
		}
		$response->data['uuid'] = $uuid;

		return $response;
	}

	/**
	 * Filters the pre-calculated result of a REST API dispatch request.
	 *
	 * Allow hijacking the request before dispatching by returning a non-empty. The returned value
	 * will be used to serve the request instead.
	 *
	 * @param mixed           $result  Response to replace the requested version with. Can be anything
	 *                                 a normal endpoint can return, or null to not hijack the request.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function rest_pre_dispatch( $result, $server, $request ) {
		return $result;
	}

	/**
	 * Filters the response before executing any REST API callbacks.
	 *
	 * NOTE: route matching and authentication have run at this point.
	 *
	 * We use this hook to determine the controller class and load our duck punches.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
	 */
	public function rest_request_before_callbacks( $response, $handler, $request ) {
		/**
		 * Here we attempt to determine the controller class from the handler.
		 *
		 * Note: the handler can be a closure, in which case we can't determine the controller.
		 */
		if ( isset( $handler['callback'] ) && is_array( $handler['callback'] ) && is_object( $handler['callback'][0] ) ) {
			$controller = get_class( $handler['callback'][0] );
		} else if ( is_object( $handler['callback'] ) ) {
			$controller = get_class( $handler['callback'] );
		} else {
			return $response;
		}

		/**
		 * If the controller is one of the WooCommerce REST API controllers, we can hijack the request
		 */
		switch ( $controller ) {
			case 'WC_REST_Orders_Controller':
				$this->wc_rest_api_handler = new API\Orders( $request );
				break;
			case 'WC_REST_Products_Controller':
				$this->wc_rest_api_handler = new API\Products( $request );
				break;
			case 'WC_REST_Product_Variations_Controller':
				$this->wc_rest_api_handler = new API\Product_Variations( $request );
				break;
			case 'WC_REST_Customers_Controller':
				$this->wc_rest_api_handler = new API\Customers( $request );
				break;
			case 'WC_REST_Taxes_Controller':
				$this->wc_rest_api_handler = new API\Taxes( $request );
				break;
			case 'WC_REST_Payment_Gateways_Controller':
				$this->wc_rest_api_handler = new API\Payment_Gateways( $request );
				break;
			case 'WC_REST_Product_Categories_Controller':
				$this->wc_rest_api_handler = new API\Product_Categories( $request );
				break;
			case 'WC_REST_Product_Tags_Controller':
				$this->wc_rest_api_handler = new API\Product_Tags( $request );
				break;
		}

		return $response;
	}

	/**
	 * Filters the REST API dispatch request result.
	 *
	 * @param mixed           $dispatch_result Dispatch result, will be used if not empty.
	 * @param WP_REST_Request $request         Request used to generate the response.
	 * @param string          $route           Route matched for the request.
	 * @param array           $handler         Route handler used for the request.
	 *
	 * @return mixed
	 */
	public function rest_dispatch_request( $dispatch_result, $request, $route, $handler ) {
		$params = $request->get_params();

		if ( isset( $params['posts_per_page'] ) && -1 == $params['posts_per_page'] && isset( $params['fields'] ) ) {
			if ( $this->wc_rest_api_handler ) {
				$dispatch_result = $this->wc_rest_api_handler->get_all_posts( $params['fields'] );
			}
		}

		return $dispatch_result;
	}
}
