<?php
/**
 * Shortcode renderer.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * [fbmsl_menu] shortcode.
 */
class FBMSL_Shortcode {

	/**
	 * Register shortcode.
	 */
	public static function init(): void {
		add_shortcode( 'fbmsl_menu', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render menu markup.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ): string {
		$settings = FBMSL_Settings::get_settings();
		$atts     = shortcode_atts(
			array(
				'category'          => '',
				'layout'            => $settings['layout'],
				'columns'           => $settings['columns'],
				'show_images'       => $settings['show_images'],
				'show_descriptions' => $settings['show_descriptions'],
				'show_prices'       => $settings['show_prices'],
				'show_variants'     => $settings['show_variants'],
			),
			$atts,
			'fbmsl_menu'
		);

		$layout  = in_array( $atts['layout'], array( 'grid', 'list' ), true ) ? $atts['layout'] : 'grid';
		$columns = FBMSL_Settings::sanitize_columns( $atts['columns'] );

		$query_args = array(
			'post_type'      => FBMSL_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $atts['category'] ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => FBMSL_CPT::TAXONOMY,
					'field'    => is_numeric( $atts['category'] ) ? 'term_id' : 'slug',
					'terms'    => $atts['category'],
				),
			);
		}

		$query = new WP_Query( $query_args );

		$context = array(
			'query'             => $query,
			'layout'            => $layout,
			'columns'           => $columns,
			'show_images'       => (bool) $atts['show_images'],
			'show_descriptions' => (bool) $atts['show_descriptions'],
			'show_prices'       => (bool) $atts['show_prices'],
			'show_variants'     => (bool) $atts['show_variants'],
			'group_by_category' => '' === $atts['category'],
		);

		ob_start();
		FBMSL_Templates::load( 'menu-list', $context );
		return (string) ob_get_clean();
	}
}
