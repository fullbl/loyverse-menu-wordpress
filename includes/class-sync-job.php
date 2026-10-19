<?php
/**
 * Idempotent sync job enqueue / dispatch.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single entry point for manual, cron, and webhook sync triggers.
 */
class FBMSL_Sync_Job {

	public const HOOK = 'fbmsl_run_sync_batch';

	/**
	 * Register the job runner hook.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once FBMSL_PLUGIN_DIR . 'includes/class-cli.php';
			FBMSL_CLI::register();
		}
	}

	/**
	 * Enqueue an idempotent sync job and return immediately.
	 *
	 * Coalesces bursts: if a job is already scheduled, only sets the pending flag.
	 *
	 * @param string $source webhook|cron|manual|followup.
	 * @return array{enqueued:bool,pending:bool,source:string}
	 */
	public static function enqueue( string $source = 'manual' ): array {
		$already = self::is_scheduled();

		if ( $already ) {
			FBMSL_Sync_Lock::set_pending();
			FBMSL_Settings::update_status(
				array(
					'sync_pending' => 1,
					'last_message' => __( 'Sync already queued; another run is pending.', 'fullbl-menu-sync-for-loyverse' ),
				)
			);
			return array(
				'enqueued' => false,
				'pending'  => true,
				'source'   => $source,
			);
		}

		self::schedule_now( $source );

		FBMSL_Settings::update_status(
			array(
				'sync_pending' => FBMSL_Sync_Lock::is_pending() ? 1 : 0,
				'last_message' => sprintf(
					/* translators: %s: trigger source */
					__( 'Sync queued (%s).', 'fullbl-menu-sync-for-loyverse' ),
					$source
				),
			)
		);

		return array(
			'enqueued' => true,
			'pending'  => FBMSL_Sync_Lock::is_pending(),
			'source'   => $source,
		);
	}

	/**
	 * Schedule the batch hook ASAP (Action Scheduler preferred).
	 *
	 * @param string $source Trigger source.
	 */
	public static function schedule_now( string $source = 'manual' ): void {
		// Single callback argument: array( 'source' => ... ).
		$callback_args = array(
			array(
				'source' => $source,
			),
		);

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, null, 'fbmsl' ) ) {
				return;
			}
			as_enqueue_async_action( self::HOOK, $callback_args, 'fbmsl' );
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK, $callback_args );
		}
	}

	/**
	 * Whether a batch job is already scheduled.
	 *
	 * @return bool
	 */
	public static function is_scheduled(): bool {
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, null, 'fbmsl' ) ) {
			return true;
		}
		return (bool) wp_next_scheduled( self::HOOK );
	}

	/**
	 * Action callback: process one batch.
	 *
	 * @param array|string $args Optional source args from the scheduler.
	 */
	public static function run_batch( $args = array() ): void {
		$source = 'scheduled';
		if ( is_array( $args ) && ! empty( $args['source'] ) ) {
			$source = sanitize_key( (string) $args['source'] );
		} elseif ( is_string( $args ) && '' !== $args ) {
			$source = sanitize_key( $args );
		}
		FBMSL_Sync::process_batch( false, null, $source ? $source : 'scheduled' );
	}

	/**
	 * Run the first batch inline (Sync now UX), then schedule follow-ups via the same job/lock.
	 *
	 * @param FBMSL_API_Client|null $client Optional client.
	 * @return array|WP_Error
	 */
	public static function enqueue_and_run_first( ?FBMSL_API_Client $client = null ) {
		return FBMSL_Sync::process_batch( true, $client, 'manual' );
	}
}
