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
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
	}

	/**
	 * Register indexed remote-ID meta for lookups (never match by title).
	 */
	public static function register_meta(): void {
		$string_meta = array(
			'_fbmsl_item_id',
			'_fbmsl_store_id',
			'_fbmsl_remote_key',
			'_fbmsl_variant_ids',
			'_fbmsl_image_url',
			'_fbmsl_sync_hash',
		);

		foreach ( $string_meta as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			'_fbmsl_imported_attachment_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'absint',
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'_fbmsl_category_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'auth_callback'     => static function () {
					return current_user_can( 'manage_categories' );
				},
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
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

		// Distinct base so /{base}/{term}/ does not collide with item rewrites under /{base}/{item}/.
		$category_slug = $slug . '-category';

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
						'slug'         => $category_slug,
						'with_front'   => false,
						'hierarchical' => true,
					)
					: false,
			)
		);
	}
}
