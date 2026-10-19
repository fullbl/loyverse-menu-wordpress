<?php
/**
 * Loyverse REST API client.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin HTTP client for api.loyverse.com.
 */
class FBMSL_API_Client {

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
	 * Whether sleep/backoff should be skipped (unit tests).
	 *
	 * @var bool
	 */
	private $skip_sleep = false;

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
	 * Disable sleep between retries (tests).
	 *
	 * @param bool $skip Skip sleep.
	 */
	public function set_skip_sleep( bool $skip ): void {
		$this->skip_sleep = $skip;
	}

	/**
	 * Build client from saved settings.
	 *
	 * @return self|WP_Error
	 */
	public static function from_settings() {
		$settings = FBMSL_Settings::get_settings();
		$token    = isset( $settings['api_token'] ) ? (string) $settings['api_token'] : '';
		if ( '' === $token ) {
			return new WP_Error( 'fbmsl_no_token', __( 'Loyverse API token is not configured.', 'fullbl-menu-sync-for-loyverse' ) );
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
	 * Fetch all categories (paginated). Prefer get_page() during sync.
	 *
	 * @return array|WP_Error
	 */
	public function get_all_categories() {
		return $this->get_all_pages( '/categories', 'categories' );
	}

	/**
	 * Fetch all items (paginated). Prefer get_page() during sync.
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
	 * Fetch a single page for resumable sync.
	 *
	 * @param string      $path     Endpoint path.
	 * @param string      $list_key JSON list key.
	 * @param string|null $cursor   Cursor from previous page.
	 * @param array       $query    Extra query args.
	 * @param int         $limit    Page size (max 250).
	 * @return array{items:array,cursor:?string}|WP_Error
	 */
	public function get_page( string $path, string $list_key, ?string $cursor = null, array $query = array(), int $limit = 250 ) {
		$args = array_merge( $query, array( 'limit' => max( 1, min( 250, $limit ) ) ) );
		if ( $cursor ) {
			$args['cursor'] = $cursor;
		}

		$response = $this->get( $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$batch = isset( $response[ $list_key ] ) && is_array( $response[ $list_key ] ) ? $response[ $list_key ] : array();
		$next  = ! empty( $response['cursor'] ) ? (string) $response['cursor'] : null;

		return array(
			'items'  => $batch,
			'cursor' => $next,
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
			$page = $this->get_page( $path, $list_key, $cursor, $query );
			if ( is_wp_error( $page ) ) {
				return $page;
			}
			$items  = array_merge( $items, $page['items'] );
			$cursor = $page['cursor'];
			++$page_count;
		} while ( $cursor && $page_count < $max_pages );

		return $items;
	}

	/**
	 * Perform HTTP request with auth / rate-limit handling.
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

		$max_attempts = (int) apply_filters( 'fbmsl_api_max_retries', 4 );
		$attempt      = 0;

		while ( true ) {
			++$attempt;

			if ( $this->request_handler ) {
				$response = call_user_func( $this->request_handler, $url, $args );
			} else {
				$response = wp_remote_request( $url, $args );
			}

			if ( is_wp_error( $response ) ) {
				if ( $attempt >= $max_attempts ) {
					return $response;
				}
				$this->backoff_sleep( $attempt, 0 );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$raw  = wp_remote_retrieve_body( $response );
			$data = json_decode( $raw, true );

			if ( 401 === $code || 403 === $code ) {
				$message = $this->extract_error_message( $data, __( 'Loyverse API token is invalid or expired.', 'fullbl-menu-sync-for-loyverse' ) );
				return new WP_Error(
					'fbmsl_api_auth',
					$message,
					array(
						'status' => $code,
						'body'   => $data,
					)
				);
			}

			if ( 429 === $code || $code >= 500 ) {
				if ( $attempt >= $max_attempts ) {
					$message     = $this->extract_error_message(
						$data,
						429 === $code
							? __( 'Loyverse API rate limit reached. Sync will retry later.', 'fullbl-menu-sync-for-loyverse' )
							: __( 'Loyverse API temporarily unavailable.', 'fullbl-menu-sync-for-loyverse' )
					);
					$retry_after = $this->parse_retry_after( $response );
					return new WP_Error(
						429 === $code ? 'fbmsl_api_rate_limit' : 'fbmsl_api_server',
						$message,
						array(
							'status'      => $code,
							'body'        => $data,
							'retry_after' => $retry_after,
						)
					);
				}
				$retry_after = $this->parse_retry_after( $response );
				$this->backoff_sleep( $attempt, $retry_after );
				continue;
			}

			if ( $code < 200 || $code >= 300 ) {
				$message = $this->extract_error_message( $data, __( 'Loyverse API request failed.', 'fullbl-menu-sync-for-loyverse' ) );
				return new WP_Error(
					'fbmsl_api_error',
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
				return new WP_Error( 'fbmsl_api_invalid_json', __( 'Invalid JSON from Loyverse API.', 'fullbl-menu-sync-for-loyverse' ) );
			}

			return $data;
		}
	}

	/**
	 * Extract human-readable API error.
	 *
	 * @param mixed  $data            Decoded body.
	 * @param string $fallback_message Fallback message.
	 * @return string
	 */
	private function extract_error_message( $data, string $fallback_message ): string {
		if ( is_array( $data ) && ! empty( $data['errors'][0]['details'] ) ) {
			return (string) $data['errors'][0]['details'];
		}
		if ( is_array( $data ) && ! empty( $data['message'] ) ) {
			return (string) $data['message'];
		}
		return $fallback_message;
	}

	/**
	 * Parse Retry-After header (seconds).
	 *
	 * @param array|mixed $response HTTP response.
	 * @return int
	 */
	private function parse_retry_after( $response ): int {
		if ( ! is_array( $response ) ) {
			return 0;
		}
		$headers = isset( $response['headers'] ) ? $response['headers'] : array();
		$value   = '';
		if ( is_array( $headers ) && isset( $headers['retry-after'] ) ) {
			$value = $headers['retry-after'];
		} elseif ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			$value = $headers['retry-after'] ?? '';
		}
		if ( is_array( $value ) ) {
			$value = $value[0] ?? '';
		}
		$seconds = (int) $value;
		return max( 0, min( 300, $seconds ) );
	}

	/**
	 * Sleep with exponential backoff.
	 *
	 * @param int $attempt     Attempt number (1-based).
	 * @param int $retry_after Hint from Retry-After header.
	 */
	private function backoff_sleep( int $attempt, int $retry_after ): void {
		if ( $this->skip_sleep ) {
			return;
		}
		$base = (int) apply_filters( 'fbmsl_api_backoff_base', 2 );
		$wait = $retry_after > 0 ? $retry_after : (int) min( 60, pow( $base, $attempt ) );
		/**
		 * Filter sleep seconds before API retry.
		 *
		 * @param int $wait    Seconds.
		 * @param int $attempt Attempt number.
		 */
		$wait = (int) apply_filters( 'fbmsl_api_backoff_seconds', $wait, $attempt );
		if ( $wait > 0 ) {
			sleep( $wait );
		}
	}
}
