<?php
/**
 * Uninstall Menu for Loyverse.
 *
 * @package MenuForLoyverse
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mfl_settings' );
delete_option( 'mfl_status' );
delete_option( 'mfl_webhook_secret' );
delete_option( 'mfl_api_token' );

// Legacy option keys from pre-release development builds.
delete_option( 'lm_settings' );
delete_option( 'lm_status' );
delete_option( 'lm_webhook_secret' );

wp_clear_scheduled_hook( 'mfl_cron_sync' );
wp_clear_scheduled_hook( 'lm_cron_sync' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_mfl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_mfl_' ) . '%',
		$wpdb->esc_like( '_transient_lm_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_lm_' ) . '%'
	)
);
