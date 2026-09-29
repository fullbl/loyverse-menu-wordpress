<?php
/**
 * Custom post type and taxonomy.
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers mfl_item CPT and mfl_category taxonomy.
 */
class MFL_CPT {

	public const POST_TYPE = 'mfl_item';
	public const TAXONOMY  = 'mfl_category';

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
		$settings      = MFL_Settings::get_settings();
		$slug          = ! empty( $settings['permalink_base'] ) ? sanitize_title( $settings['permalink_base'] ) : 'menu';
		$enable_single = ! empty( $settings['enable_singles'] );
		$enable_cats   = ! empty( $settings['enable_category_archives'] );

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Menu Items', 'menu-for-loyverse' ),
					'singular_name' => __( 'Menu Item', 'menu-for-loyverse' ),
					'add_new_item'  => __( 'Add New Menu Item', 'menu-for-loyverse' ),
					'edit_item'     => __( 'Edit Menu Item', 'menu-for-loyverse' ),
					'view_item'     => __( 'View Menu Item', 'menu-for-loyverse' ),
					'search_items'  => __( 'Search Menu Items', 'menu-for-loyverse' ),
					'not_found'     => __( 'No menu items found.', 'menu-for-loyverse' ),
				),
				'public'             => true,
				'has_archive'        => $slug,
				'publicly_queryable' => $enable_single,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-food',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'rewrite'            => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capability_type'    => 'post',
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Menu Categories', 'menu-for-loyverse' ),
					'singular_name' => __( 'Menu Category', 'menu-for-loyverse' ),
					'search_items'  => __( 'Search Menu Categories', 'menu-for-loyverse' ),
					'all_items'     => __( 'All Menu Categories', 'menu-for-loyverse' ),
					'edit_item'     => __( 'Edit Menu Category', 'menu-for-loyverse' ),
					'update_item'   => __( 'Update Menu Category', 'menu-for-loyverse' ),
					'add_new_item'  => __( 'Add New Menu Category', 'menu-for-loyverse' ),
					'new_item_name' => __( 'New Menu Category Name', 'menu-for-loyverse' ),
				),
				'public'             => $enable_cats,
				'publicly_queryable' => $enable_cats,
				'hierarchical'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rewrite'            => $enable_cats
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
