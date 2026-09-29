<?php
/**
 * Custom post type and taxonomy.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers lm_item CPT and lm_category taxonomy.
 */
class LM_CPT {

	public const POST_TYPE = 'lm_item';
	public const TAXONOMY  = 'lm_category';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register CPT and taxonomy.
	 */
	public static function register(): void {
		$settings   = LM_Settings::get_settings();
		$slug       = ! empty( $settings['permalink_base'] ) ? sanitize_title( $settings['permalink_base'] ) : 'menu';
		$enable_single = ! empty( $settings['enable_singles'] );
		$enable_cats   = ! empty( $settings['enable_category_archives'] );

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Menu Items', 'loyverse-menu' ),
					'singular_name' => __( 'Menu Item', 'loyverse-menu' ),
					'add_new_item'  => __( 'Add New Menu Item', 'loyverse-menu' ),
					'edit_item'     => __( 'Edit Menu Item', 'loyverse-menu' ),
					'view_item'     => __( 'View Menu Item', 'loyverse-menu' ),
					'search_items'  => __( 'Search Menu Items', 'loyverse-menu' ),
					'not_found'     => __( 'No menu items found.', 'loyverse-menu' ),
				),
				'public'              => true,
				'has_archive'         => $slug,
				'publicly_queryable'  => $enable_single,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-food',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'rewrite'             => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capability_type'     => 'post',
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Menu Categories', 'loyverse-menu' ),
					'singular_name' => __( 'Menu Category', 'loyverse-menu' ),
					'search_items'  => __( 'Search Menu Categories', 'loyverse-menu' ),
					'all_items'     => __( 'All Menu Categories', 'loyverse-menu' ),
					'edit_item'     => __( 'Edit Menu Category', 'loyverse-menu' ),
					'update_item'   => __( 'Update Menu Category', 'loyverse-menu' ),
					'add_new_item'  => __( 'Add New Menu Category', 'loyverse-menu' ),
					'new_item_name' => __( 'New Menu Category Name', 'loyverse-menu' ),
				),
				'public'            => $enable_cats,
				'publicly_queryable' => $enable_cats,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => $enable_cats
					? array(
						'slug'         => $slug,
						'with_front'   => false,
						'hierarchical' => true,
					)
					: false,
			)
		);
	}
}
