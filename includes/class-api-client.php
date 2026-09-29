<?php
/**
 * Loyverse REST API client.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin HTTP client for api.loyverse.com.
 */
class LM_API_Client {

	public const BASE_URL = 'https://api.loyverse.com/v1.0';

	/**
	 * API token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Optional injectable request callback for tests.
	 *
	 * @var callable|null
	 */
	private $request_handler;

	/**
	 * Constructor.
	 *
	 * @param string        $token            Bearer token.
	 * @param callable|null $request_handler  Optional override for wp_remote_request.
	 */
	public function __construct( string $token, ?callable $request_handler = null ) {
		$this->token           = $token;
		$this->request_handler = $request_handler;
	}

	/**
	 * Build client from saved settings.
	 *
	 * @return self|WP_Error
	 */
	public static function from_settings() {
		$settings = LM_Settings::get_settings();
		$token    = isset( $settings['api_token'] ) ? (string) $settings['api_token'] : '';
		if ( '' === $token ) {
			return new WP_Error( 'lm_no_token', __( 'Loyverse API token is not configured.', 'loyverse-menu' ) );
		}
		return new self( $token );
	}

	/**
	 * Test connection by fetching merchant/stores.
	 *
	 * @return array|WP_Error
	 */
	public function test_connection() {
		return $this->get( '/stores' );
	}

	/**
	 * List stores.
	 *
	 * @return array|WP_Error
	 */
	public function get_stores() {
		$response = $this->get( '/stores' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return isset( $response['stores'] ) && is_array( $response['stores'] ) ? $response['stores'] : array();
	}

	/**
	 * Fetch all categories (paginated).
	 *
	 * @return array|WP_Error
	 */
	public function get_all_categories() {
		return $this->get_all_pages( '/categories', 'categories' );
	}

	/**
	 * Fetch all items (paginated).
	 *
	 * @return array|WP_Error
	 */
	public function get_all_items() {
		return $this->get_all_pages( '/items', 'items' );
	}

	/**
	 * Fetch inventory levels for a store.
	 *
	 * @param string $store_id Store ID.
	 * @return array|WP_Error
	 */
	public function get_inventory_levels( string $store_id ) {
		return $this->get_all_pages(
			'/inventory_levels',
			'inventory_levels',
			array( 'store_ids' => $store_id )
		);
	}

	/**
	 * Create or update a webhook.
	 *
	 * @param array $payload Webhook body.
	 * @return array|WP_Error
	 */
	public function upsert_webhook( array $payload ) {
		return $this->request( 'POST', '/webhooks', $payload );
	}

	/**
	 * List webhooks created by this token/app.
	 *
	 * @return array|WP_Error
	 */
	public function get_webhooks() {
		$response = $this->get( '/webhooks' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return isset( $response['webhooks'] ) && is_array( $response['webhooks'] ) ? $response['webhooks'] : array();
	}

	/**
	 * Delete a webhook.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @return true|WP_Error
	 */
	public function delete_webhook( string $webhook_id ) {
		$result = $this->request( 'DELETE', '/webhooks/' . rawurlencode( $webhook_id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	/**
	 * GET helper.
	 *
	 * @param string $path   Path.
	 * @param array  $query  Query args.
	 * @return array|WP_Error
	 */
	public function get( string $path, array $query = array() ) {
		return $this->request( 'GET', $path, null, $query );
	}

	/**
	 * Paginate until cursor exhausted.
	 *
	 * @param string $path         Endpoint path.
	 * @param string $list_key     JSON list key.
	 * @param array  $query        Extra query args.
	 * @return array|WP_Error
	 */
	private function get_all_pages( string $path, string $list_key, array $query = array() ) {
		$items      = array();
		$cursor     = null;
		$max_pages  = 100;
		$page_count = 0;

		do {
			$args = array_merge( $query, array( 'limit' => 250 ) );
			if ( $cursor ) {
				$args['cursor'] = $cursor;
			}

			$response = $this->get( $path, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$batch = isset( $response[ $list_key ] ) && is_array( $response[ $list_key ] ) ? $response[ $list_key ] : array();
			$items = array_merge( $items, $batch );
			$cursor = ! empty( $response['cursor'] ) ? (string) $response['cursor'] : null;
			++$page_count;
		} while ( $cursor && $page_count < $max_pages );

		return $items;
	}

	/**
	 * Perform HTTP request.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path.
	 * @param array|null $body   JSON body.
	 * @param array      $query  Query string.
	 * @return array|WP_Error
	 */
	public function request( string $method, string $path, ?array $body = null, array $query = array() ) {
		$url = self::BASE_URL . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		if ( $this->request_handler ) {
			$response = call_user_func( $this->request_handler, $url, $args );
		} else {
			$response = wp_remote_request( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'Loyverse API request failed.', 'loyverse-menu' );
			if ( is_array( $data ) && ! empty( $data['errors'][0]['details'] ) ) {
				$message = (string) $data['errors'][0]['details'];
			} elseif ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$message = (string) $data['message'];
			}
			return new WP_Error(
				'lm_api_error',
				$message,
				array(
					'status' => $code,
					'body'   => $data,
				)
			);
		}

		if ( '' === $raw ) {
			return array();
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'lm_api_invalid_json', __( 'Invalid JSON from Loyverse API.', 'loyverse-menu' ) );
		}

		return $data;
	}
}
