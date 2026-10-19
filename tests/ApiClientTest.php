<?php
/**
 * API client unit tests.
 *
 * @package FullBLMenuSyncLoyverse
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests FBMSL_API_Client.
 */
class ApiClientTest extends TestCase {

	public function test_successful_get_returns_array(): void {
		$handler = static function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'stores' => array(
							array(
								'id'   => 'store-1',
								'name' => 'Main',
							),
						),
					)
				),
				'headers'  => array(),
			);
		};

		$client = new FBMSL_API_Client( 'token', $handler );
		$result = $client->get_stores();

		$this->assertIsArray( $result );
		$this->assertSame( 'store-1', $result[0]['id'] );
	}

	public function test_expired_token_returns_auth_error_without_retry_success(): void {
		$calls   = 0;
		$handler = static function () use ( &$calls ) {
			++$calls;
			return array(
				'response' => array( 'code' => 401 ),
				'body'     => wp_json_encode(
					array(
						'errors' => array(
							array( 'details' => 'Unauthorized' ),
						),
					)
				),
				'headers'  => array(),
			);
		};

		$client = new FBMSL_API_Client( 'bad', $handler );
		$client->set_skip_sleep( true );
		$result = $client->get( '/stores' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fbmsl_api_auth', $result->get_error_code() );
		$this->assertSame( 'Unauthorized', $result->get_error_message() );
		$this->assertSame( 1, $calls, '401/403 must not retry' );
	}

	public function test_forbidden_is_auth_error(): void {
		$handler = static function () {
			return array(
				'response' => array( 'code' => 403 ),
				'body'     => wp_json_encode( array( 'message' => 'Forbidden' ) ),
				'headers'  => array(),
			);
		};
		$client = new FBMSL_API_Client( 'bad', $handler );
		$client->set_skip_sleep( true );
		$result = $client->get( '/stores' );
		$this->assertSame( 'fbmsl_api_auth', $result->get_error_code() );
	}

	public function test_rate_limit_retries_then_succeeds(): void {
		$calls   = 0;
		$handler = static function () use ( &$calls ) {
			++$calls;
			if ( $calls < 3 ) {
				return array(
					'response' => array( 'code' => 429 ),
					'body'     => wp_json_encode( array( 'message' => 'Slow down' ) ),
					'headers'  => array( 'retry-after' => '1' ),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'stores' => array(
							array( 'id' => 's1' ),
						),
					)
				),
				'headers'  => array(),
			);
		};

		$client = new FBMSL_API_Client( 'token', $handler );
		$client->set_skip_sleep( true );
		$result = $client->get_stores();

		$this->assertIsArray( $result );
		$this->assertSame( 's1', $result[0]['id'] );
		$this->assertSame( 3, $calls );
	}

	public function test_rate_limit_exhausted_returns_rate_limit_error(): void {
		$handler = static function () {
			return array(
				'response' => array( 'code' => 429 ),
				'body'     => wp_json_encode( array( 'message' => 'Slow down' ) ),
				'headers'  => array( 'retry-after' => '5' ),
			);
		};
		$client = new FBMSL_API_Client( 'token', $handler );
		$client->set_skip_sleep( true );
		$result = $client->get( '/items' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fbmsl_api_rate_limit', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 5, $data['retry_after'] );
	}

	public function test_server_error_returns_server_code_after_retries(): void {
		$handler = static function () {
			return array(
				'response' => array( 'code' => 503 ),
				'body'     => wp_json_encode( array( 'message' => 'Unavailable' ) ),
				'headers'  => array(),
			);
		};
		$client = new FBMSL_API_Client( 'token', $handler );
		$client->set_skip_sleep( true );
		$result = $client->get( '/items' );
		$this->assertSame( 'fbmsl_api_server', $result->get_error_code() );
	}

	public function test_pagination_collects_all_pages(): void {
		$calls   = 0;
		$handler = static function ( $url ) use ( &$calls ) {
			++$calls;
			if ( str_contains( $url, 'cursor=next' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'items' => array(
								array( 'id' => '2' ),
							),
						)
					),
					'headers'  => array(),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'items'  => array(
							array( 'id' => '1' ),
						),
						'cursor' => 'next',
					)
				),
				'headers'  => array(),
			);
		};

		$client = new FBMSL_API_Client( 'token', $handler );
		$items  = $client->get_all_items();

		$this->assertCount( 2, $items );
		$this->assertSame( 2, $calls );
	}

	public function test_get_page_returns_cursor(): void {
		$handler = static function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'items'  => array( array( 'id' => '1' ) ),
						'cursor' => 'abc',
					)
				),
				'headers'  => array(),
			);
		};
		$client = new FBMSL_API_Client( 'token', $handler );
		$page   = $client->get_page( '/items', 'items' );
		$this->assertSame( 'abc', $page['cursor'] );
		$this->assertCount( 1, $page['items'] );
	}
}
