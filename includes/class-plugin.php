<?php
/**
 * Main plugin bootstrap.
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires all plugin components.
 */
class MFL_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MFL_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): MFL_Plugin {
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

		MFL_CPT::init();
		MFL_Settings::init();
		MFL_Cron::init();
		MFL_Webhook::init();
		MFL_Shortcode::init();
		MFL_Assets::init();
		MFL_Locks::init();
		MFL_Templates::init();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'menu-for-loyverse', false, dirname( MFL_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Activation tasks.
	 */
	public static function activate(): void {
		self::maybe_migrate_legacy_options();

		MFL_CPT::register();
		flush_rewrite_rules();

		if ( false === get_option( MFL_Settings::OPTION_KEY, false ) ) {
			MFL_Settings::update_settings( MFL_Settings::defaults() );
		}

		if ( ! get_option( 'mfl_webhook_secret' ) ) {
			update_option( 'mfl_webhook_secret', wp_generate_password( 32, false, false ), false );
		}

		MFL_Cron::schedule();
	}

	/**
	 * Deactivation tasks.
	 */
	public static function deactivate(): void {
		MFL_Cron::unschedule();
		flush_rewrite_rules();
	}

	/**
	 * Migrate options from the pre–WordPress.org development prefix (lm_*).
	 */
	private static function maybe_migrate_legacy_options(): void {
		$legacy_settings = get_option( 'lm_settings', null );
		if ( is_array( $legacy_settings ) && null === get_option( MFL_Settings::OPTION_KEY, null ) ) {
			$sanitized = MFL_Settings::sanitize( $legacy_settings );
			if ( ! empty( $sanitized['api_token'] ) ) {
				update_option( 'mfl_api_token', (string) $sanitized['api_token'], false );
				$sanitized['api_token'] = '';
			}
			update_option( MFL_Settings::OPTION_KEY, $sanitized, false );
			delete_option( 'lm_settings' );
		}

		$legacy_status = get_option( 'lm_status', null );
		if ( is_array( $legacy_status ) && null === get_option( MFL_Settings::STATUS_KEY, null ) ) {
			update_option( MFL_Settings::STATUS_KEY, $legacy_status, false );
			delete_option( 'lm_status' );
		}

		$legacy_secret = get_option( 'lm_webhook_secret', null );
		if ( is_string( $legacy_secret ) && $legacy_secret && ! get_option( 'mfl_webhook_secret', '' ) ) {
			update_option( 'mfl_webhook_secret', $legacy_secret, false );
			delete_option( 'lm_webhook_secret' );
		}
	}
}
