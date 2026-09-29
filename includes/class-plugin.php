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
	 * Data schema version for upgrades/migrations.
	 */
	public const DB_VERSION = 2;

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
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 5 );

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
	 * Activation tasks.
	 */
	public static function activate(): void {
		self::maybe_migrate_legacy_data();

		FBMSL_CPT::register();
		flush_rewrite_rules();

		if ( false === get_option( FBMSL_Settings::OPTION_KEY, false ) ) {
			FBMSL_Settings::update_settings( FBMSL_Settings::defaults() );
		}

		if ( ! get_option( 'fbmsl_webhook_secret' ) ) {
			update_option( 'fbmsl_webhook_secret', wp_generate_password( 32, false, false ), false );
		}

		FBMSL_Cron::schedule();
		update_option( 'fbmsl_db_version', self::DB_VERSION, false );
	}

	/**
	 * Deactivation tasks.
	 */
	public static function deactivate(): void {
		FBMSL_Cron::unschedule();
		flush_rewrite_rules();
	}

	/**
	 * Run migrations when the stored schema version is behind.
	 */
	public static function maybe_upgrade(): void {
		$installed = (int) get_option( 'fbmsl_db_version', 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		self::maybe_migrate_legacy_data();
		update_option( 'fbmsl_db_version', self::DB_VERSION, false );
	}

	/**
	 * Migrate options, meta, CPT and taxonomy from lm_* / mfl_* prefixes.
	 */
	private static function maybe_migrate_legacy_data(): void {
		self::migrate_options();
		self::migrate_post_types_and_taxonomies();
		self::migrate_meta_keys();
		self::migrate_cron_hooks();
		self::strip_custom_css_setting();
	}

	/**
	 * Copy lm_* and mfl_* options onto fbmsl_* keys.
	 */
	private static function migrate_options(): void {
		$option_map = array(
			'lm_settings'        => FBMSL_Settings::OPTION_KEY,
			'mfl_settings'       => FBMSL_Settings::OPTION_KEY,
			'lm_status'          => FBMSL_Settings::STATUS_KEY,
			'mfl_status'         => FBMSL_Settings::STATUS_KEY,
			'lm_webhook_secret'  => 'fbmsl_webhook_secret',
			'mfl_webhook_secret' => 'fbmsl_webhook_secret',
			'lm_api_token'       => 'fbmsl_api_token',
			'mfl_api_token'      => 'fbmsl_api_token',
		);

		foreach ( $option_map as $old_key => $new_key ) {
			$legacy = get_option( $old_key, null );
			if ( null === $legacy || false === $legacy ) {
				continue;
			}

			if ( null === get_option( $new_key, null ) ) {
				if ( FBMSL_Settings::OPTION_KEY === $new_key && is_array( $legacy ) ) {
					$sanitized = FBMSL_Settings::sanitize( $legacy );
					if ( ! empty( $sanitized['api_token'] ) ) {
						update_option( 'fbmsl_api_token', (string) $sanitized['api_token'], false );
						$sanitized['api_token'] = '';
					}
					unset( $sanitized['custom_css'] );
					update_option( $new_key, $sanitized, false );
				} else {
					update_option( $new_key, $legacy, false );
				}
			}

			delete_option( $old_key );
		}
	}

	/**
	 * Rename legacy CPT and taxonomy slugs to fbmsl_*.
	 */
	private static function migrate_post_types_and_taxonomies(): void {
		global $wpdb;

		$post_types = array( 'lm_item', 'mfl_item' );
		foreach ( $post_types as $old_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->posts,
				array( 'post_type' => FBMSL_CPT::POST_TYPE ),
				array( 'post_type' => $old_type )
			);
		}

		$taxonomies = array( 'lm_category', 'mfl_category' );
		foreach ( $taxonomies as $old_tax ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->term_taxonomy,
				array( 'taxonomy' => FBMSL_CPT::TAXONOMY ),
				array( 'taxonomy' => $old_tax )
			);
		}
	}

	/**
	 * Rename legacy post/term meta keys to _fbmsl_*.
	 */
	private static function migrate_meta_keys(): void {
		global $wpdb;

		$replacements = array(
			array( '_mfl_', '_fbmsl_' ),
			array( '_lm_', '_fbmsl_' ),
		);

		foreach ( $replacements as $pair ) {
			list( $old_prefix, $new_prefix ) = $pair;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
					$old_prefix,
					$new_prefix,
					$wpdb->esc_like( $old_prefix ) . '%'
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->termmeta} SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
					$old_prefix,
					$new_prefix,
					$wpdb->esc_like( $old_prefix ) . '%'
				)
			);
		}
	}

	/**
	 * Move scheduled sync from legacy cron hooks.
	 */
	private static function migrate_cron_hooks(): void {
		foreach ( array( 'lm_cron_sync', 'mfl_cron_sync' ) as $legacy_hook ) {
			$timestamp = wp_next_scheduled( $legacy_hook );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $legacy_hook );
				$timestamp = wp_next_scheduled( $legacy_hook );
			}
		}

		FBMSL_Cron::schedule();
	}

	/**
	 * Drop removed custom_css setting from stored options.
	 */
	private static function strip_custom_css_setting(): void {
		$settings = get_option( FBMSL_Settings::OPTION_KEY, null );
		if ( ! is_array( $settings ) || ! array_key_exists( 'custom_css', $settings ) ) {
			return;
		}
		unset( $settings['custom_css'] );
		update_option( FBMSL_Settings::OPTION_KEY, $settings, false );
	}
}
