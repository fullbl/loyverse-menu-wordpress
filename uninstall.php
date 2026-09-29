<?php
/**
 * Uninstall Loyverse Menu.
 *
 * @package LoyverseMenu
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'lm_settings' );
delete_option( 'lm_status' );
delete_option( 'lm_webhook_secret' );

wp_clear_scheduled_hook( 'lm_cron_sync' );
