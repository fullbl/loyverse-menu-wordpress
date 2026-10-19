<?php
/**
 * Non-blocking store-level sync lock.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Atomic lock via add_option (fails if the option already exists).
 */
class FBMSL_Sync_Lock {

	public const OPTION_KEY  = 'fbmsl_sync_lock';
	public const PENDING_KEY = 'fbmsl_resync_pending';
	public const DEFAULT_TTL = 900; // 15 minutes.

	/**
	 * Attempt to acquire the lock.
	 *
	 * @param string $owner Unique owner token.
	 * @param int    $ttl   Lock TTL in seconds.
	 * @return bool True if acquired.
	 */
	public static function acquire( string $owner, int $ttl = 0 ): bool {
		if ( $ttl <= 0 ) {
			$ttl = (int) apply_filters( 'fbmsl_sync_lock_ttl', self::DEFAULT_TTL );
		}

		self::release_if_stale();

		$payload = array(
			'owner'       => $owner,
			'expires'     => time() + $ttl,
			'acquired_at' => time(),
		);

		// add_option is atomic: returns false when the option already exists.
		$added = add_option( self::OPTION_KEY, $payload, '', false );
		if ( $added ) {
			return true;
		}

		// Race: another process may have left a stale lock between release and add.
		self::release_if_stale();
		return (bool) add_option( self::OPTION_KEY, $payload, '', false );
	}

	/**
	 * Release lock if owned by $owner (or force).
	 *
	 * @param string $owner Owner token; empty forces release.
	 * @return bool
	 */
	public static function release( string $owner = '' ): bool {
		$current = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $current ) ) {
			delete_option( self::OPTION_KEY );
			return true;
		}
		if ( '' !== $owner && ( $current['owner'] ?? '' ) !== $owner ) {
			return false;
		}
		delete_option( self::OPTION_KEY );
		return true;
	}

	/**
	 * Whether the lock is currently held (and not stale).
	 *
	 * @return bool
	 */
	public static function is_locked(): bool {
		self::release_if_stale();
		$current = get_option( self::OPTION_KEY, null );
		return is_array( $current ) && ! empty( $current['owner'] );
	}

	/**
	 * Drop expired locks.
	 */
	public static function release_if_stale(): void {
		$current = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $current ) ) {
			return;
		}
		$expires = isset( $current['expires'] ) ? (int) $current['expires'] : 0;
		if ( $expires > 0 && $expires < time() ) {
			delete_option( self::OPTION_KEY );
		}
	}

	/**
	 * Mark that another sync should run after the current one.
	 */
	public static function set_pending(): void {
		update_option( self::PENDING_KEY, 1, false );
	}

	/**
	 * Clear the resync-pending flag.
	 */
	public static function clear_pending(): void {
		delete_option( self::PENDING_KEY );
	}

	/**
	 * Whether a resync is pending.
	 *
	 * @return bool
	 */
	public static function is_pending(): bool {
		return (bool) get_option( self::PENDING_KEY, 0 );
	}
}
