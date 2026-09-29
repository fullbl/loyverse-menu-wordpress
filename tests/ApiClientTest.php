<?php
/**
 * API client unit tests.
 *
 * @package MenuForLoyverse
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests MFL_API_Client.
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
			);
		};

		$client = new MFL_API_Client( 'token', $handler );
		$result = $client->get_stores();

		$this->assertIsArray( $result );
		$this->assertSame( 'store-1', $result[0]['id'] );
	}

	public function test_api_error_returns_wp_error(): void {
		$handler = static function () {
			return array(
				'response' => array( 'code' => 401 ),
				'body'     => wp_json_encode(
					array(
						'errors' => array(
							array( 'details' => 'Unauthorized' ),
						),
					)
				),
			);
		};

		$client = new MFL_API_Client( 'bad', $handler );
		$result = $client->get( '/stores' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'Unauthorized', $result->get_error_message() );
	}

	public function test_pagination_collects_all_pages(): void {
		$calls = 0;
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
			);
		};

		$client = new MFL_API_Client( 'token', $handler );
		$items  = $client->get_all_items();

		$this->assertCount( 2, $items );
		$this->assertSame( 2, $calls );
	}
}
