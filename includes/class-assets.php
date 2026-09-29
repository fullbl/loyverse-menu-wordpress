<?php
/**
 * Frontend assets.
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue menu CSS with settings as CSS variables.
 */
class MFL_Assets {

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

		$settings = MFL_Settings::get_settings();
		wp_enqueue_style(
			'menu-for-loyverse',
			MFL_PLUGIN_URL . 'assets/css/menu.css',
			array(),
			MFL_VERSION
		);

		$custom = sprintf(
			':root{--lm-accent:%1$s;--lm-gap:%2$s;--lm-columns:%3$d;}',
			esc_attr( $settings['accent_color'] ),
			esc_attr( $settings['gap'] ),
			(int) $settings['columns']
		);
		if ( ! empty( $settings['custom_css'] ) ) {
			$custom .= "\n" . $settings['custom_css'];
		}
		wp_add_inline_style( 'menu-for-loyverse', $custom );
	}

	/**
	 * Admin script for settings page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function admin_enqueue( string $hook ): void {
		if ( 'settings_page_menu-for-loyverse' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'menu-for-loyverse-admin',
			MFL_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			MFL_VERSION
		);
		wp_enqueue_script(
			'menu-for-loyverse-admin',
			MFL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-color-picker', 'jquery' ),
			MFL_VERSION,
			true
		);
	}

	/**
	 * Whether current request needs menu CSS.
	 *
	 * @return bool
	 */
	private static function should_load(): bool {
		if ( is_singular( MFL_CPT::POST_TYPE ) || is_post_type_archive( MFL_CPT::POST_TYPE ) || is_tax( MFL_CPT::TAXONOMY ) ) {
			return true;
		}
		global $post;
		if ( $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'loyverse_menu' ) || has_shortcode( $post->post_content, 'menu_for_loyverse' ) ) ) {
			return true;
		}
		return (bool) apply_filters( 'mfl_enqueue_assets', false );
	}
}
