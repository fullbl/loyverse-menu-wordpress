<?php
/**
 * Main plugin bootstrap.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires all plugin components.
 */
class LM_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var LM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): LM_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		LM_CPT::init();
		LM_Settings::init();
		LM_Cron::init();
		LM_Webhook::init();
		LM_Shortcode::init();
		LM_Assets::init();
		LM_Locks::init();
		LM_Templates::init();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'loyverse-menu', false, dirname( LM_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Activation tasks.
	 */
	public static function activate(): void {
		LM_CPT::register();
		flush_rewrite_rules();

		$settings = LM_Settings::get_settings();
		if ( empty( $settings ) ) {
			LM_Settings::update_settings( LM_Settings::defaults() );
		}

		if ( ! get_option( 'lm_webhook_secret' ) ) {
			update_option( 'lm_webhook_secret', wp_generate_password( 32, false, false ), false );
		}

		LM_Cron::schedule();
	}

	/**
	 * Deactivation tasks.
	 */
	public static function deactivate(): void {
		LM_Cron::unschedule();
		flush_rewrite_rules();
	}
}
