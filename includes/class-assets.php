<?php
/**
 * Frontend assets.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue menu CSS with settings as CSS variables.
 */
class FBMSL_Assets {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ) );
	}

	/**
	 * Frontend styles.
	 */
	public static function enqueue(): void {
		if ( ! self::should_load() ) {
			return;
		}

		$settings = FBMSL_Settings::get_settings();
		wp_enqueue_style(
			'fbmsl-menu',
			FBMSL_PLUGIN_URL . 'assets/css/menu.css',
			array(),
			FBMSL_VERSION
		);

		$accent  = FBMSL_Settings::sanitize_accent_color( $settings['accent_color'] ?? '' );
		$gap     = FBMSL_Settings::sanitize_gap( $settings['gap'] ?? '' );
		$columns = FBMSL_Settings::sanitize_columns( $settings['columns'] ?? 2 );

		// Values are validated above; sprintf only interpolates safe hex / unit / int.
		$custom = sprintf(
			':root{--fbmsl-accent:%1$s;--fbmsl-gap:%2$s;--fbmsl-columns:%3$d;}',
			$accent,
			$gap,
			$columns
		);
		wp_add_inline_style( 'fbmsl-menu', $custom );
	}

	/**
	 * Admin script for settings page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function admin_enqueue( string $hook ): void {
		if ( 'settings_page_fullbl-menu-sync-for-loyverse' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'fbmsl-admin',
			FBMSL_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			FBMSL_VERSION
		);
		wp_enqueue_script(
			'fbmsl-admin',
			FBMSL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-color-picker', 'jquery' ),
			FBMSL_VERSION,
			true
		);
	}

	/**
	 * Whether current request needs menu CSS.
	 *
	 * @return bool
	 */
	private static function should_load(): bool {
		if ( is_singular( FBMSL_CPT::POST_TYPE ) || is_post_type_archive( FBMSL_CPT::POST_TYPE ) || is_tax( FBMSL_CPT::TAXONOMY ) ) {
			return true;
		}
		global $post;
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'fbmsl_menu' ) ) {
			return true;
		}
		return (bool) apply_filters( 'fbmsl_enqueue_assets', false );
	}
}
