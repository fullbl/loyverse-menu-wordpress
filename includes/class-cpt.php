<?php
/**
 * Custom post type and taxonomy.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers fbmsl_item CPT and fbmsl_category taxonomy.
 */
class FBMSL_CPT {

	public const POST_TYPE = 'fbmsl_item';
	public const TAXONOMY  = 'fbmsl_category';

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
		$settings      = FBMSL_Settings::get_settings();
		$slug          = ! empty( $settings['permalink_base'] ) ? sanitize_title( $settings['permalink_base'] ) : 'menu';
		$enable_single = ! empty( $settings['enable_singles'] );
		$enable_cats   = ! empty( $settings['enable_category_archives'] );

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Menu Items', 'fullbl-menu-sync-for-loyverse' ),
					'singular_name' => __( 'Menu Item', 'fullbl-menu-sync-for-loyverse' ),
					'add_new_item'  => __( 'Add New Menu Item', 'fullbl-menu-sync-for-loyverse' ),
					'edit_item'     => __( 'Edit Menu Item', 'fullbl-menu-sync-for-loyverse' ),
					'view_item'     => __( 'View Menu Item', 'fullbl-menu-sync-for-loyverse' ),
					'search_items'  => __( 'Search Menu Items', 'fullbl-menu-sync-for-loyverse' ),
					'not_found'     => __( 'No menu items found.', 'fullbl-menu-sync-for-loyverse' ),
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
					'name'          => __( 'Menu Categories', 'fullbl-menu-sync-for-loyverse' ),
					'singular_name' => __( 'Menu Category', 'fullbl-menu-sync-for-loyverse' ),
					'search_items'  => __( 'Search Menu Categories', 'fullbl-menu-sync-for-loyverse' ),
					'all_items'     => __( 'All Menu Categories', 'fullbl-menu-sync-for-loyverse' ),
					'edit_item'     => __( 'Edit Menu Category', 'fullbl-menu-sync-for-loyverse' ),
					'update_item'   => __( 'Update Menu Category', 'fullbl-menu-sync-for-loyverse' ),
					'add_new_item'  => __( 'Add New Menu Category', 'fullbl-menu-sync-for-loyverse' ),
					'new_item_name' => __( 'New Menu Category Name', 'fullbl-menu-sync-for-loyverse' ),
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
