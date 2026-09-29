<?php
/**
 * Template loader with theme override support.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locates templates in theme then plugin.
 */
class LM_Templates {

	/**
	 * Hook template includes for CPT/taxonomy.
	 */
	public static function init(): void {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
	}

	/**
	 * Swap theme template for plugin templates when missing overrides.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function template_include( string $template ): string {
		if ( is_singular( LM_CPT::POST_TYPE ) ) {
			$custom = self::locate( 'single-lm_item.php' );
			return $custom ? $custom : $template;
		}
		if ( is_post_type_archive( LM_CPT::POST_TYPE ) ) {
			$custom = self::locate( 'archive-lm_item.php' );
			return $custom ? $custom : $template;
		}
		if ( is_tax( LM_CPT::TAXONOMY ) ) {
			$custom = self::locate( 'taxonomy-lm_category.php' );
			return $custom ? $custom : $template;
		}
		return $template;
	}

	/**
	 * Locate a template file.
	 *
	 * Theme path: your-theme/loyverse-menu/{name}
	 *
	 * @param string $name Template filename.
	 * @return string Empty if not found.
	 */
	public static function locate( string $name ): string {
		$theme = locate_template( array( 'loyverse-menu/' . $name ) );
		if ( $theme ) {
			return $theme;
		}
		$path = LM_PLUGIN_DIR . 'templates/' . $name;
		return file_exists( $path ) ? $path : '';
	}

	/**
	 * Load a template with extractable vars.
	 *
	 * @param string $slug Template slug without .php (supports partials/foo).
	 * @param array  $vars Variables.
	 */
	public static function load( string $slug, array $vars = array() ): void {
		$file = self::locate( $slug . '.php' );
		if ( ! $file ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Controlled template vars.
		extract( $vars, EXTR_SKIP );
		include $file;
	}

	/**
	 * Format price for display.
	 *
	 * @param float|null $price Price.
	 * @return string
	 */
	public static function format_price( $price ): string {
		if ( null === $price || '' === $price ) {
			return '';
		}
		$formatted = number_format_i18n( (float) $price, 2 );
		/**
		 * Filter displayed price HTML/text.
		 *
		 * @param string $formatted Formatted number.
		 * @param float  $price     Raw price.
		 */
		return (string) apply_filters( 'lm_format_price', $formatted, (float) $price );
	}

	/**
	 * Decode variant meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_variants( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_lm_variant_data', true );
		if ( ! $raw ) {
			return array();
		}
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}
}
