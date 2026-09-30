<?php
/**
 * Main plugin bootstrap.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires all plugin components.
 */
class FBMSL_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var FBMSL_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): FBMSL_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );

		FBMSL_CPT::init();
		FBMSL_Settings::init();
		FBMSL_Cron::init();
		FBMSL_Webhook::init();
		FBMSL_Shortcode::init();
		FBMSL_Assets::init();
		FBMSL_Locks::init();
		FBMSL_Templates::init();
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'fullbl-menu-sync-for-loyverse',
			false,
			dirname( FBMSL_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Flush rewrites when the stored plugin version changes.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( FBMSL_Settings::VERSION_KEY, '' );
		if ( FBMSL_VERSION === $stored ) {
			return;
		}
		FBMSL_CPT::register();
		flush_rewrite_rules();
		update_option( FBMSL_Settings::VERSION_KEY, FBMSL_VERSION, false );
	}

	/**
	 * Activation tasks.
	 */
	public static function activate(): void {
		FBMSL_CPT::register();
		flush_rewrite_rules();
		update_option( FBMSL_Settings::VERSION_KEY, FBMSL_VERSION, false );

		if ( false === get_option( FBMSL_Settings::OPTION_KEY, false ) ) {
			FBMSL_Settings::update_settings( FBMSL_Settings::defaults() );
		}

		if ( ! get_option( 'fbmsl_webhook_secret' ) ) {
			update_option( 'fbmsl_webhook_secret', wp_generate_password( 32, false, false ), false );
		}

		FBMSL_Cron::schedule();
	}

	/**
	 * Deactivation tasks.
	 */
	public static function deactivate(): void {
		FBMSL_Cron::unschedule();
		flush_rewrite_rules();
	}
}
