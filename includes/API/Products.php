<?php

namespace WCPOS\WooCommercePOS\API;

use Ramsey\Uuid\Uuid;
use WC_Data;
use WCPOS\WooCommercePOS\Logger;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WC_Product_Query;
use function is_array;

class Products {
	private $request;

	/**
	 * Products constructor.
	 *
	 * @param $request WP_REST_Request
	 */
	public function __construct( WP_REST_Request $request ) {
		$this->request = $request;

		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ), 10, 3 );
		add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'product_response' ), 10, 3 );
		add_filter( 'woocommerce_rest_product_object_query', array( $this, 'product_query' ), 10, 2 );
		add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'orderby_stock_quantity' ), 10, 2 );
		add_filter( 'woocommerce_rest_product_schema', array( $this, 'add_barcode_to_product_schema' ) );
		add_action( 'woocommerce_rest_insert_product_object', array( $this, 'insert_product_object' ), 10, 3 );
	}

	/**
	 * @param $schema
	 * @return array
	 */
	public function add_barcode_to_product_schema( $schema ): array {
		$schema['properties']['barcode'] = array(
			'description' => __( 'Barcode', 'woocommerce-pos' ),
			'type'        => 'string',
			'context'     => array( 'view', 'edit' ),
		);
		return $schema;
	}

	/**
	 * Filters the response before executing any REST API callbacks.
	 *
	 * We can use this filter to bypass data validation checks
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
	 */
	public function rest_request_before_callbacks( $response, $handler, $request ) {
		if ( is_wp_error( $response ) ) {
			// Check if the error code 'rest_invalid_param' exists
			if ( $response->get_error_message( 'rest_invalid_param' ) ) {
				// Get the error data for 'rest_invalid_param'
				$error_data = $response->get_error_data( 'rest_invalid_param' );

				// Check if the invalid parameter was 'line_items'
				if ( array_key_exists( 'stock_quantity', $error_data['params'] ) ) {
					// Get the 'line_items' details
					$line_items_details = $error_data['details']['stock_quantity'];

					//
					if ( $line_items_details['code'] === 'rest_invalid_type' && woocommerce_pos_get_settings( 'general', 'decimal_qty' ) ) {
							unset( $error_data['params']['stock_quantity'], $error_data['details']['stock_quantity'] );
					}
				}

				// Check if the invalid parameter was 'orderby'
				if ( array_key_exists( 'orderby', $error_data['params'] ) ) {
					// Get the 'orderby' details
					$orderby_details = $error_data['details']['orderby'];

					// Get the 'orderby' request
					$orderby_request = $request->get_param( 'orderby' );

					// Extended 'orderby' values
					$orderby_extended = array(
						'stock_quantity',
					);

					// Check if 'orderby' has 'rest_not_in_enum', but is in the extended 'orderby' values
					if ( $orderby_details['code'] === 'rest_not_in_enum' && in_array( $orderby_request, $orderby_extended, true ) ) {
						unset( $error_data['params']['orderby'], $error_data['details']['orderby'] );
					}
				}

				// Check if $error_data['params'] is empty
				if ( empty( $error_data['params'] ) ) {
					return null;
				} else {
					// Remove old error data and add new error data
					$error_message = 'Invalid parameter(s): ' . implode( ', ', array_keys( $error_data['params'] ) ) . '.';

					$response->remove( 'rest_invalid_param' );
					$response->add( 'rest_invalid_param', $error_message, $error_data );
				}
			}
		}

		return $response;
	}

	/**
	 * Fires after a single object is created or updated via the REST API.
	 *
	 * @param WC_Data         $object    Inserted object.
	 * @param WP_REST_Request $request   Request object.
	 * @param boolean $creating  True when creating object, false when updating.
	 */
	public function insert_product_object( WC_Data $object, WP_REST_Request $request, bool $creating ) {
		$barcode_field = woocommerce_pos_get_settings( 'general', 'barcode_field' );
		$barcode = $request->get_param( 'barcode' );
		if ( $barcode ) {
			$object->update_meta_data( $barcode_field, $barcode );
			$object->save_meta_data();
		}
	}

	/**
	 * Filter the product response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WC_Data          $product  Product data.
	 * @param WP_REST_Request  $request  Request object.
	 *
	 * @return WP_REST_Response $response The response object.
	 */
	public function product_response( WP_REST_Response $response, WC_Data $product, WP_REST_Request $request ): WP_REST_Response {
		$data = $response->get_data();

		/**
		 * Make sure the product has a uuid
		 */
		$uuid = $product->get_meta( '_woocommerce_pos_uuid' );
		if ( ! $uuid ) {
			$uuid = Uuid::uuid4()->toString();
			$product->update_meta_data( '_woocommerce_pos_uuid', $uuid );
			$product->save_meta_data();
			$data['meta_data'] = $product->get_meta_data();
		}

		/**
		 * Add barcode field
		 */
		$barcode_field = woocommerce_pos_get_settings( 'general', 'barcode_field' );
		$data['barcode'] = $product->get_meta( $barcode_field );

		/**
		 * Truncate the product description
		 */
		$max_length = 100;
		$plain_text_description = wp_strip_all_tags( $data['description'], true );
		if ( strlen( $plain_text_description ) > $max_length ) {
			$truncated_description = substr( $plain_text_description, 0, $max_length - 3 ) . '...';
			$data['description'] = $truncated_description;
		}

		/**
		 * Check the response size and log a debug message if it is over the maximum size.
		 */
		$response_size = strlen( serialize( $response->data ) );
		$max_response_size = 10000;
		if ( $response_size > $max_response_size ) {
			Logger::log( "Product ID {$product->get_id()} has a response size of {$response_size} bytes, exceeding the limit of {$max_response_size} bytes." );
		}

		/**
		 * If product is variable, add the max and min prices and add them to the meta data
		 * @TODO - only need to update if there is a change
		 */
		if ( $product->is_type( 'variable' ) ) {
			$product->update_meta_data( '_woocommerce_pos_variable_prices', wp_json_encode(
				array(
					'price' => array(
						'min' => $product->get_variation_price(),
						'max' => $product->get_variation_price( 'max' ),
					),
					'regular_price' => array(
						'min' => $product->get_variation_regular_price(),
						'max' => $product->get_variation_regular_price( 'max' ),
					),
					'sale_price' => array(
						'min' => $product->get_variation_sale_price(),
						'max' => $product->get_variation_sale_price( 'max' ),
					),
				)
			) );
			$product->save_meta_data();
			$data['meta_data'] = $product->get_meta_data();
		}

		/**
		 * Reset the new response data
		 */
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Filter the query arguments for a request.
	 *
	 * @param array           $args    Key value array of query var to query value.
	 * @param WP_REST_Request $request The request used.
	 *
	 * @return array $args Key value array of query var to query value.
	 */
	public function product_query( array $args, WP_REST_Request $request ): array {
		// Note!: date_query is removed from the query, use 'after' and delete this filter

		//		$params = $request->get_query_params();
		//		if ( isset( $params['date_modified_gmt_after'] ) ) {
		//			$date_query = array(
		//				'column' => 'post_modified_gmt',
		//				'after'  => $params['date_modified_gmt_after'],
		//			);
		//			array_push( $args['date_query'], $date_query );
		// //			array_push( $args['after'], $date_query );
		//
		//		}

		return $args;
	}

	/**
	 * Filters all query clauses at once, for convenience.
	 *
	 * Covers the WHERE, GROUP BY, JOIN, ORDER BY, DISTINCT,
	 * fields (SELECT), and LIMIT clauses.
	 *
	 * @param string[] $clauses {
	 *     Associative array of the clauses for the query.
	 *
	 *     @type string $where    The WHERE clause of the query.
	 *     @type string $groupby  The GROUP BY clause of the query.
	 *     @type string $join     The JOIN clause of the query.
	 *     @type string $orderby  The ORDER BY clause of the query.
	 *     @type string $distinct The DISTINCT clause of the query.
	 *     @type string $fields   The SELECT clause of the query.
	 *     @type string $limits   The LIMIT clause of the query.
	 * }
	 * @param WP_Query $wp_query   The WP_Query instance (passed by reference).
	 */
	public function orderby_stock_quantity( array $clauses, WP_Query $wp_query ): array {
		global $wpdb;

		// add option to order by stock quantity
		if ( isset( $wp_query->query_vars['orderby'] ) && 'stock_quantity' === $wp_query->query_vars['orderby'] ) {
			// Join the postmeta table to access the stock data
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS stock_meta ON {$wpdb->posts}.ID = stock_meta.post_id AND stock_meta.meta_key='_stock'";

			// Order the query results: records with _stock meta_value first, then NULL, then items without _stock meta_key
			$order = isset( $wp_query->query_vars['order'] ) ? $wp_query->query_vars['order'] : 'DESC';
			$clauses['orderby']  = 'CASE';
			$clauses['orderby'] .= ' WHEN stock_meta.meta_value IS NOT NULL THEN 1';
			$clauses['orderby'] .= ' WHEN stock_meta.meta_value IS NULL THEN 2';
			$clauses['orderby'] .= ' ELSE 3';
			$clauses['orderby'] .= " END {$order}, COALESCE(stock_meta.meta_value+0, 0) {$order}";
		}

		return $clauses;
	}



	/**
	 * Search SQL filter for matching against post title only.
	 *
	 * @param string   $search
	 * @param WP_Query $wp_query
	 */
	public function posts_search( $search, $wp_query ): string {
		if ( ! empty( $search ) && ! empty( $wp_query->query_vars['search_terms'] ) ) {
			global $wpdb;

			$q = $wp_query->query_vars;
			$n = ! empty( $q['exact'] ) ? '' : '%';

			$search = array();

			foreach ( (array) $q['search_terms'] as $term ) {
				$search[] = $wpdb->prepare( "$wpdb->posts.post_title LIKE %s", $n . $wpdb->esc_like( $term ) . $n );
			}

			if ( ! is_user_logged_in() ) {
				$search[] = "$wpdb->posts.post_password = ''";
			}

			$search = ' AND ' . implode( ' AND ', $search );
		}

		return $search;
	}

	/**
	 * Returns array of all product ids, name.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function get_all_posts( array $fields = array() ): array {
		$pos_only_products = woocommerce_pos_get_settings( 'general', 'pos_only_products' );

		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);

		if ( $pos_only_products ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => '_pos_visibility',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => '_pos_visibility',
					'value' => 'online_only',
					'compare' => '!=',
				),
			);
		}

		$product_query = new WP_Query( $args );
		$product_ids = $product_query->posts;

		// Convert the array of product IDs to an array of objects with product IDs as integers
		return array_map( array( $this, 'format_id' ), $product_ids );
	}

	/**
	 * Returns thumbnail if it exists, if not, returns the WC placeholder image.
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	private function get_thumbnail( int $id ): string {
		$image    = false;
		$thumb_id = get_post_thumbnail_id( $id );

		if ( $thumb_id ) {
			$image = wp_get_attachment_image_src( $thumb_id, 'shop_thumbnail' );
		}

		if ( is_array( $image ) ) {
			return $image[0];
		}

		return wc_placeholder_img_src();
	}

	/**
	 * @param string $product_id
	 *
	 * @return object
	 */
	private function format_id( string $product_id ): object {
		return (object) array( 'id' => (int) $product_id );
	}
}
