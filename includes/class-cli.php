<?php
/**
 * WP-CLI commands for local/dev sync testing.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * `wp fbmsl` command group.
 */
class FBMSL_CLI {

	/**
	 * Register commands.
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		WP_CLI::add_command( 'fbmsl sync', array( __CLASS__, 'sync' ) );
		WP_CLI::add_command( 'fbmsl sync-status', array( __CLASS__, 'status' ) );
	}

	/**
	 * Run sync batches until complete (or once with --batch).
	 *
	 * ## OPTIONS
	 *
	 * [--batch]
	 * : Process a single batch only.
	 *
	 * [--mock=<scenario>]
	 * : Use a mocked API client. Scenarios: catalog, rate_limit, auth, rename_variant.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fbmsl sync
	 *     wp fbmsl sync --batch
	 *     wp fbmsl sync --mock=catalog
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public static function sync( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$client = null;
		if ( ! empty( $assoc_args['mock'] ) ) {
			$client = self::mock_client( (string) $assoc_args['mock'] );
			if ( is_wp_error( $client ) ) {
				WP_CLI::error( $client->get_error_message() );
			}
		}

		$batch_only = ! empty( $assoc_args['batch'] );
		$guard      = 0;
		do {
			$result = FBMSL_Sync::process_batch( true, $client, 'cli' );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			WP_CLI::log( wp_json_encode( $result ) );
			++$guard;
			if ( $batch_only ) {
				break;
			}
		} while ( empty( $result['done'] ) && $guard < 500 );

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Print sync status JSON.
	 */
	public static function status(): void {
		WP_CLI::print_value( FBMSL_Settings::get_status(), array( 'format' => 'json' ) );
	}

	/**
	 * Build a mock API client for a scenario.
	 *
	 * @param string $scenario Scenario key.
	 * @return FBMSL_API_Client|WP_Error
	 */
	private static function mock_client( string $scenario ) {
		switch ( $scenario ) {
			case 'auth':
				$handler = static function () {
					return array(
						'response' => array( 'code' => 401 ),
						'body'     => wp_json_encode( array( 'message' => 'Unauthorized' ) ),
						'headers'  => array(),
					);
				};
				break;
			case 'rate_limit':
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
						'body'     => wp_json_encode( array( 'categories' => array() ) ),
						'headers'  => array(),
					);
				};
				break;
			case 'catalog':
				$handler = self::large_catalog_handler( 10000 );
				break;
			case 'rename_variant':
				$handler = static function ( $url ) {
					if ( str_contains( $url, '/categories' ) ) {
						return array(
							'response' => array( 'code' => 200 ),
							'body'     => wp_json_encode(
								array(
									'categories' => array(
										array(
											'id'   => 'c1',
											'name' => 'Drinks',
										),
									),
								)
							),
							'headers'  => array(),
						);
					}
					if ( str_contains( $url, '/items' ) ) {
						return array(
							'response' => array( 'code' => 200 ),
							'body'     => wp_json_encode(
								array(
									'items' => array(
										array(
											'id'          => 'i1',
											'item_name'   => 'Coffee',
											'description' => 'Hot',
											'category_id' => 'c1',
											'variants'    => array(
												array(
													'variant_id'    => 'v1',
													'option1_value' => 'Large',
													'default_price' => 3.5,
												),
											),
										),
									),
								)
							),
							'headers'  => array(),
						);
					}
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( array( 'inventory_levels' => array() ) ),
						'headers'  => array(),
					);
				};
				break;
			default:
				return new WP_Error( 'fbmsl_cli_bad_mock', 'Unknown mock scenario.' );
		}

		$client = new FBMSL_API_Client( 'mock-token', $handler );
		$client->set_skip_sleep( true );
		return $client;
	}

	/**
	 * Handler that pages a large catalog.
	 *
	 * @param int $total Total items.
	 * @return callable
	 */
	private static function large_catalog_handler( int $total ): callable {
		$page_size = 250;
		return static function ( $url ) use ( $total, $page_size ) {
			if ( str_contains( $url, '/categories' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'categories' => array() ) ),
					'headers'  => array(),
				);
			}
			if ( str_contains( $url, '/inventory' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'inventory_levels' => array() ) ),
					'headers'  => array(),
				);
			}
			$offset = 0;
			if ( preg_match( '/cursor=(\d+)/', $url, $m ) ) {
				$offset = (int) $m[1];
			}
			$items = array();
			$end   = min( $total, $offset + $page_size );
			for ( $i = $offset; $i < $end; $i++ ) {
				$items[] = array(
					'id'          => 'item-' . $i,
					'item_name'   => 'Item ' . $i,
					'description' => '',
					'variants'    => array(
						array(
							'variant_id'    => 'var-' . $i,
							'default_price' => 1,
						),
					),
				);
			}
			$body = array( 'items' => $items );
			if ( $end < $total ) {
				$body['cursor'] = (string) $end;
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( $body ),
				'headers'  => array(),
			);
		};
	}
}
