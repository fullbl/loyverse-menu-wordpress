<?php
/**
 * Sync helper unit tests.
 *
 * @package FullBLMenuSyncLoyverse
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests FBMSL_Sync pricing helpers.
 */
class SyncHelpersTest extends TestCase {

	public function test_variant_price_uses_store_override(): void {
		$variant = array(
			'default_price' => 10,
			'stores'        => array(
				array(
					'store_id' => 's1',
					'price'    => 12.5,
				),
			),
		);

		$this->assertSame( 12.5, FBMSL_Sync::variant_price( $variant, 's1' ) );
	}

	public function test_variant_price_falls_back_to_default(): void {
		$variant = array(
			'default_price' => 9,
			'stores'        => array(),
		);

		$this->assertSame( 9.0, FBMSL_Sync::variant_price( $variant, 'missing' ) );
	}

	public function test_variant_price_without_default_returns_null(): void {
		$this->assertNull( FBMSL_Sync::variant_price( array(), 's1' ) );
	}
}
