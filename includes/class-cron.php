<?php
/**
 * WP-Cron reconciliation sync.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedules automatic sync.
 */
class LM_Cron {

	public const HOOK = 'lm_cron_sync';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( 'LM_Sync', 'run' ) );
	}

	/**
	 * Schedule event from settings.
	 */
	public static function schedule(): void {
		$settings = LM_Settings::get_settings();
		$interval = $settings['cron_interval'] ?? 'hourly';
		if ( ! in_array( $interval, array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$interval = 'hourly';
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, self::HOOK );
		}
	}

	/**
	 * Clear schedule.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Reschedule with current interval.
	 */
	public static function reschedule(): void {
		self::unschedule();
		self::schedule();
	}
}
