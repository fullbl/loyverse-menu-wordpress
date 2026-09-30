<?php
/**
 * Uninstall FullBL Menu Sync for Loyverse.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'fbmsl_settings' );
delete_option( 'fbmsl_status' );
delete_option( 'fbmsl_webhook_secret' );
delete_option( 'fbmsl_api_token' );
delete_option( 'fbmsl_version' );
delete_option( 'fbmsl_db_version' );

wp_clear_scheduled_hook( 'fbmsl_cron_sync' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_fbmsl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_fbmsl_' ) . '%'
	)
);
