<?php
/**
 * Sync lock unit tests.
 *
 * @package FullBLMenuSyncLoyverse
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests FBMSL_Sync_Lock.
 */
class SyncLockTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fbmsl_test_options'] = array();
	}

	public function test_acquire_is_exclusive(): void {
		$this->assertTrue( FBMSL_Sync_Lock::acquire( 'owner-a', 60 ) );
		$this->assertFalse( FBMSL_Sync_Lock::acquire( 'owner-b', 60 ) );
		$this->assertTrue( FBMSL_Sync_Lock::is_locked() );
	}

	public function test_release_by_owner(): void {
		FBMSL_Sync_Lock::acquire( 'owner-a', 60 );
		$this->assertFalse( FBMSL_Sync_Lock::release( 'other' ) );
		$this->assertTrue( FBMSL_Sync_Lock::release( 'owner-a' ) );
		$this->assertFalse( FBMSL_Sync_Lock::is_locked() );
		$this->assertTrue( FBMSL_Sync_Lock::acquire( 'owner-b', 60 ) );
	}

	public function test_stale_lock_is_recovered(): void {
		update_option(
			FBMSL_Sync_Lock::OPTION_KEY,
			array(
				'owner'   => 'stale',
				'expires' => time() - 10,
			)
		);
		$this->assertTrue( FBMSL_Sync_Lock::acquire( 'fresh', 60 ) );
	}

	public function test_pending_flag_coalesces_bursts(): void {
		$this->assertFalse( FBMSL_Sync_Lock::is_pending() );
		FBMSL_Sync_Lock::set_pending();
		$this->assertTrue( FBMSL_Sync_Lock::is_pending() );
		FBMSL_Sync_Lock::clear_pending();
		$this->assertFalse( FBMSL_Sync_Lock::is_pending() );
	}

	public function test_concurrent_webhook_and_cron_set_pending_when_locked(): void {
		$this->assertTrue( FBMSL_Sync_Lock::acquire( 'cron-run', 60 ) );
		// Simulate webhook arriving while cron holds the lock.
		FBMSL_Sync_Lock::set_pending();
		$this->assertTrue( FBMSL_Sync_Lock::is_locked() );
		$this->assertTrue( FBMSL_Sync_Lock::is_pending() );
		FBMSL_Sync_Lock::release( 'cron-run' );
		$this->assertTrue( FBMSL_Sync_Lock::is_pending() );
	}
}
