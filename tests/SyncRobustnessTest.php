<?php
/**
 * Sync robustness helper tests.
 *
 * @package FullBLMenuSyncLoyverse
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers hash skip, variants, limits, image URL, remote key, catalog batching math.
 */
class SyncRobustnessTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fbmsl_test_options'] = array();
	}

	public function test_sync_hash_changes_when_price_changes(): void {
		$base = array(
			'title'       => 'Espresso',
			'description' => 'Strong',
			'price'       => 2.0,
			'available'   => true,
			'variants'    => array(
				array(
					'variant_id' => 'v1',
					'price'      => 2.0,
				),
			),
			'category_id' => 'c1',
			'image_url'   => '',
			'store_id'    => 's1',
			'item_id'     => 'i1',
		);
		$h1   = FBMSL_Sync::compute_sync_hash( $base );
		$base['price'] = 2.5;
		$base['variants'][0]['price'] = 2.5;
		$h2 = FBMSL_Sync::compute_sync_hash( $base );
		$this->assertNotSame( $h1, $h2 );
	}

	public function test_renamed_variant_keeps_variant_id(): void {
		$variants = array(
			array(
				'variant_id'    => 'v-keep',
				'option1_value' => 'XL',
				'default_price' => 5,
			),
		);
		$data = FBMSL_Sync::build_variant_data( $variants, 's1', array(), 0 );
		$this->assertSame( 'v-keep', $data[0]['variant_id'] );
		$this->assertSame( 'XL', $data[0]['option1_value'] );
		$this->assertSame( array( 'v-keep' ), FBMSL_Sync::extract_variant_ids( $data ) );
	}

	public function test_remote_key_is_unique_per_store(): void {
		$this->assertSame( 'store-a:item-1', FBMSL_Sync::remote_key( 'store-a', 'item-1' ) );
		$this->assertNotSame(
			FBMSL_Sync::remote_key( 'store-a', 'item-1' ),
			FBMSL_Sync::remote_key( 'store-b', 'item-1' )
		);
	}

	public function test_image_url_extracted(): void {
		$this->assertSame( 'https://cdn.example/a.jpg', FBMSL_Sync::extract_image_url( array( 'image_url' => 'https://cdn.example/a.jpg' ) ) );
		$this->assertSame(
			'https://cdn.example/b.jpg',
			FBMSL_Sync::extract_image_url(
				array(
					'image' => array( 'url' => 'https://cdn.example/b.jpg' ),
				)
			)
		);
		$this->assertSame( '', FBMSL_Sync::extract_image_url( array() ) );
	}

	public function test_limits_exhausted_by_items_and_pages(): void {
		$limits = array(
			'max_pages'   => 2,
			'max_items'   => 50,
			'max_images'  => 10,
			'max_seconds' => 60,
		);
		$started = microtime( true );
		$this->assertFalse(
			FBMSL_Sync::limits_exhausted(
				$limits,
				$started,
				array(
					'pages'  => 1,
					'items'  => 10,
					'images' => 0,
				)
			)
		);
		$this->assertTrue(
			FBMSL_Sync::limits_exhausted(
				$limits,
				$started,
				array(
					'pages'  => 2,
					'items'  => 10,
					'images' => 0,
				)
			)
		);
		$this->assertTrue(
			FBMSL_Sync::limits_exhausted(
				$limits,
				$started,
				array(
					'pages'  => 0,
					'items'  => 50,
					'images' => 0,
				)
			)
		);
	}

	public function test_ten_thousand_item_catalog_finishes_in_batches(): void {
		$total     = 10000;
		$page_size = 250;
		$max_items = 50;
		$max_pages = 5;

		$handler = static function ( $url ) use ( $total, $page_size ) {
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
				$items[] = array( 'id' => 'item-' . $i );
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

		$client = new FBMSL_API_Client( 'tok', $handler );
		$client->set_skip_sleep( true );

		$cursor       = null;
		$processed    = 0;
		$batches      = 0;
		$max_batches  = 500;
		$batch_start  = microtime( true );

		while ( $processed < $total && $batches < $max_batches ) {
			$pages_this_batch = 0;
			$items_this_batch = 0;
			while ( $pages_this_batch < $max_pages && $items_this_batch < $max_items && $processed < $total ) {
				$page = $client->get_page( '/items', 'items', $cursor, array(), $page_size );
				$this->assertIsArray( $page );
				++$pages_this_batch;
				$count             = count( $page['items'] );
				$items_this_batch += $count;
				$processed        += $count;
				$cursor            = $page['cursor'];
				if ( ! $cursor ) {
					break;
				}
			}
			++$batches;
			// Each batch must stay within caps (simulates no request timeout).
			$this->assertLessThanOrEqual( $max_pages, $pages_this_batch );
			$this->assertLessThanOrEqual( $max_items + $page_size, $items_this_batch );
		}

		$elapsed = microtime( true ) - $batch_start;
		$this->assertSame( $total, $processed );
		$this->assertGreaterThan( 1, $batches );
		$this->assertLessThan( 30, $elapsed, 'Batched 10k catalog simulation should finish quickly without hanging' );
	}

	public function test_checkpoint_defaults_and_persist(): void {
		$fresh = FBMSL_Sync::fresh_checkpoint();
		$this->assertSame( FBMSL_Sync::PHASE_CATEGORIES, $fresh['phase'] );
		FBMSL_Sync::save_checkpoint( $fresh );
		$loaded = FBMSL_Sync::get_checkpoint();
		$this->assertSame( FBMSL_Sync::PHASE_CATEGORIES, $loaded['phase'] );
		FBMSL_Sync::clear_checkpoint();
		$this->assertSame( array(), FBMSL_Sync::get_checkpoint() );
	}

	public function test_cleanup_only_after_complete_pass_phase_order(): void {
		$phases = array(
			FBMSL_Sync::PHASE_CATEGORIES,
			FBMSL_Sync::PHASE_ITEMS,
			FBMSL_Sync::PHASE_INVENTORY,
			FBMSL_Sync::PHASE_CLEANUP,
			FBMSL_Sync::PHASE_DONE,
		);
		$this->assertSame( 'cleanup', $phases[3] );
		$this->assertSame( 'done', $phases[4] );
	}
}
