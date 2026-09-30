<?php
/**
 * Template loader with theme override support.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locates templates in theme then plugin.
 */
class FBMSL_Templates {

	/**
	 * Hook template includes for CPT/taxonomy.
	 */
	public static function init(): void {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
	}

	/**
	 * Swap theme template for plugin templates when missing overrides.
	 *
	 * Block themes: only use an explicit theme override under
	 * fullbl-menu-sync-for-loyverse/; otherwise let the theme render so we do
	 * not call get_header()/get_footer() against a block theme.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function template_include( string $template ): string {
		if ( is_singular( FBMSL_CPT::POST_TYPE ) ) {
			$name = 'single-fbmsl_item.php';
		} elseif ( is_post_type_archive( FBMSL_CPT::POST_TYPE ) ) {
			$name = 'archive-fbmsl_item.php';
		} elseif ( is_tax( FBMSL_CPT::TAXONOMY ) ) {
			$name = 'taxonomy-fbmsl_category.php';
		} else {
			return $template;
		}

		// Theme override always wins (classic and block).
		$theme = locate_template( array( 'fullbl-menu-sync-for-loyverse/' . $name ) );
		if ( $theme ) {
			return $theme;
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return $template;
		}

		$path = FBMSL_PLUGIN_DIR . 'templates/' . $name;
		return file_exists( $path ) ? $path : $template;
	}

	/**
	 * Locate a template file.
	 *
	 * Theme path: your-theme/fullbl-menu-sync-for-loyverse/{name}
	 *
	 * @param string $name Template filename.
	 * @return string Empty if not found.
	 */
	public static function locate( string $name ): string {
		$theme = locate_template( array( 'fullbl-menu-sync-for-loyverse/' . $name ) );
		if ( $theme ) {
			return $theme;
		}
		$path = FBMSL_PLUGIN_DIR . 'templates/' . $name;
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
	 * Format price for display (currency symbol from settings).
	 *
	 * @param float|null $price Price.
	 * @return string
	 */
	public static function format_price( $price ): string {
		if ( null === $price || '' === $price ) {
			return '';
		}
		$settings  = FBMSL_Settings::get_settings();
		$formatted = number_format_i18n( (float) $price, 2 );
		$symbol    = isset( $settings['currency_symbol'] ) ? (string) $settings['currency_symbol'] : '';
		$position  = isset( $settings['currency_position'] ) ? (string) $settings['currency_position'] : 'before';

		if ( '' !== $symbol ) {
			$formatted = ( 'after' === $position )
				? $formatted . ' ' . $symbol
				: $symbol . ' ' . $formatted;
		}

		/**
		 * Filter displayed price HTML/text.
		 *
		 * @param string $formatted Formatted number with optional currency.
		 * @param float  $price     Raw price.
		 */
		return (string) apply_filters( 'fbmsl_format_price', $formatted, (float) $price );
	}

	/**
	 * Format a stored UTC/ISO datetime for admin display (site timezone + date/time formats).
	 *
	 * @param string $datetime Datetime string (e.g. gmdate( 'c' )).
	 * @return string Empty if unparseable.
	 */
	public static function format_datetime( string $datetime ): string {
		$datetime = trim( $datetime );
		if ( '' === $datetime ) {
			return '';
		}
		$timestamp = strtotime( $datetime );
		if ( false === $timestamp ) {
			return '';
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return (string) wp_date( $format, $timestamp );
	}

	/**
	 * Decode variant meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_variants( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_fbmsl_variant_data', true );
		if ( ! $raw ) {
			return array();
		}
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}
}
