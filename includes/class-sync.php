<?php
/**
 * Idempotent Loyverse → WordPress sync.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syncs categories and items.
 */
class LM_Sync {

	/**
	 * Run a full sync.
	 *
	 * @param LM_API_Client|null $client Optional client (tests).
	 * @return array|WP_Error Summary or error.
	 */
	public static function run( ?LM_API_Client $client = null ) {
		if ( null === $client ) {
			$client = LM_API_Client::from_settings();
			if ( is_wp_error( $client ) ) {
				LM_Settings::update_status(
					array(
						'connection' => 'error',
						'last_error' => $client->get_error_message(),
					)
				);
				return $client;
			}
		}

		$settings = LM_Settings::get_settings();
		$store_id = (string) $settings['store_id'];

		$categories = $client->get_all_categories();
		if ( is_wp_error( $categories ) ) {
			LM_Settings::update_status(
				array(
					'connection' => 'error',
					'last_error' => $categories->get_error_message(),
				)
			);
			return $categories;
		}

		$items = $client->get_all_items();
		if ( is_wp_error( $items ) ) {
			LM_Settings::update_status(
				array(
					'connection' => 'error',
					'last_error' => $items->get_error_message(),
				)
			);
			return $items;
		}

		$inventory_map = array();
		if ( $store_id ) {
			$levels = $client->get_inventory_levels( $store_id );
			if ( ! is_wp_error( $levels ) ) {
				foreach ( $levels as $level ) {
					if ( empty( $level['variant_id'] ) ) {
						continue;
					}
					$inventory_map[ (string) $level['variant_id'] ] = isset( $level['in_stock'] ) ? (float) $level['in_stock'] : null;
				}
			}
		}

		$term_map        = self::sync_categories( $categories );
		$seen_item_ids   = array();
		$items_upserted  = 0;
		$images_synced   = 0;

		foreach ( $items as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}

			$item_id = (string) $item['id'];
			$seen_item_ids[] = $item_id;

			$result = self::upsert_item( $item, $term_map, $store_id, $inventory_map );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			++$items_upserted;
			if ( ! empty( $result['image_synced'] ) ) {
				++$images_synced;
			}
		}

		self::draft_missing_items( $seen_item_ids );

		$summary = array(
			'categories' => count( $term_map ),
			'items'      => $items_upserted,
			'images'     => $images_synced,
		);

		LM_Settings::update_status(
			array(
				'connection'   => 'ok',
				'last_sync'    => gmdate( 'c' ),
				'last_error'   => '',
				'last_message' => sprintf(
					/* translators: 1: category count, 2: item count */
					__( 'Synced %1$d categories and %2$d items.', 'loyverse-menu' ),
					$summary['categories'],
					$summary['items']
				),
			)
		);

		return $summary;
	}

	/**
	 * Sync categories to terms.
	 *
	 * @param array $categories Loyverse categories.
	 * @return array Map of loyverse category id => term_id.
	 */
	public static function sync_categories( array $categories ): array {
		$map = array();

		foreach ( $categories as $category ) {
			if ( empty( $category['id'] ) || empty( $category['name'] ) ) {
				continue;
			}

			$loyverse_id = (string) $category['id'];
			$term_id     = self::find_term_by_loyverse_id( $loyverse_id );
			$name        = sanitize_text_field( (string) $category['name'] );
			$slug        = sanitize_title( $name );

			if ( $term_id ) {
				wp_update_term(
					$term_id,
					LM_CPT::TAXONOMY,
					array(
						'name' => $name,
					)
				);
			} else {
				$created = wp_insert_term(
					$name,
					LM_CPT::TAXONOMY,
					array(
						'slug' => $slug,
					)
				);
				if ( is_wp_error( $created ) ) {
					if ( 'term_exists' === $created->get_error_code() ) {
						$term_id = (int) $created->get_error_data();
					} else {
						continue;
					}
				} else {
					$term_id = (int) $created['term_id'];
				}
				update_term_meta( $term_id, '_lm_category_id', $loyverse_id );
			}

			$map[ $loyverse_id ] = $term_id;
		}

		return $map;
	}

	/**
	 * Upsert a single item post.
	 *
	 * @param array  $item          Loyverse item.
	 * @param array  $term_map      Category map.
	 * @param string $store_id      Selected store.
	 * @param array  $inventory_map variant_id => stock.
	 * @return array|WP_Error
	 */
	public static function upsert_item( array $item, array $term_map, string $store_id, array $inventory_map = array() ) {
		$item_id = (string) $item['id'];
		$post_id = self::find_post_by_loyverse_id( $item_id );

		/**
		 * Allow skipping sync for a post (e.g. translations).
		 *
		 * @param bool  $skip    Whether to skip.
		 * @param int   $post_id Existing post ID or 0.
		 * @param array $item    Loyverse item payload.
		 */
		if ( apply_filters( 'lm_sync_skip_post', false, $post_id ? $post_id : 0, $item ) ) {
			return array( 'post_id' => $post_id, 'image_synced' => false );
		}

		$title       = isset( $item['item_name'] ) ? sanitize_text_field( (string) $item['item_name'] ) : '';
		$description = isset( $item['description'] ) ? wp_kses_post( (string) $item['description'] ) : '';
		$variants    = isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array();

		$price     = self::resolve_price( $variants, $store_id );
		$available = empty( $item['deleted_at'] );
		if ( $available && $store_id && ! empty( $inventory_map ) ) {
			$available = self::is_available_from_inventory( $variants, $inventory_map );
		}

		$variant_data = array();
		foreach ( $variants as $variant ) {
			$variant_data[] = array(
				'variant_id'   => isset( $variant['variant_id'] ) ? (string) $variant['variant_id'] : '',
				'sku'          => isset( $variant['sku'] ) ? (string) $variant['sku'] : '',
				'option1_value'=> isset( $variant['option1_value'] ) ? (string) $variant['option1_value'] : '',
				'option2_value'=> isset( $variant['option2_value'] ) ? (string) $variant['option2_value'] : '',
				'option3_value'=> isset( $variant['option3_value'] ) ? (string) $variant['option3_value'] : '',
				'price'        => self::variant_price( $variant, $store_id ),
				'available'    => self::variant_available( $variant, $inventory_map ),
			);
		}

		$postarr = array(
			'post_type'   => LM_CPT::POST_TYPE,
			'post_status' => $available ? 'publish' : 'draft',
		);

		$lock_title   = $post_id && LM_Locks::is_locked( $post_id, 'title' );
		$lock_content = $post_id && LM_Locks::is_locked( $post_id, 'content' );
		$lock_image   = $post_id && LM_Locks::is_locked( $post_id, 'image' );

		if ( ! $lock_title ) {
			$postarr['post_title'] = $title;
		}
		if ( ! $lock_content ) {
			$postarr['post_content'] = $description;
		}

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$slug          = get_post_field( 'post_name', $post_id );
			// Keep existing slug on update.
			$result = wp_update_post( $postarr, true );
		} else {
			$slug = sanitize_title( $title );
			if ( self::slug_conflicts_with_term( $slug ) ) {
				$slug .= '-item';
			}
			$postarr['post_name'] = $slug;
			$result               = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;

		update_post_meta( $post_id, '_lm_item_id', $item_id );
		update_post_meta( $post_id, '_lm_variant_data', wp_json_encode( $variant_data ) );
		update_post_meta( $post_id, '_lm_price', $price );
		update_post_meta( $post_id, '_lm_available', $available ? 1 : 0 );
		update_post_meta( $post_id, '_lm_store_id', $store_id );
		update_post_meta( $post_id, '_lm_synced_at', gmdate( 'c' ) );

		if ( ! empty( $item['category_id'] ) && isset( $term_map[ (string) $item['category_id'] ] ) ) {
			wp_set_object_terms( $post_id, array( (int) $term_map[ (string) $item['category_id'] ] ), LM_CPT::TAXONOMY, false );
		}

		$image_synced = false;
		if ( ! $lock_image ) {
			$image_synced = self::sync_image( $post_id, $item );
		}

		return array(
			'post_id'      => $post_id,
			'image_synced' => $image_synced,
		);
	}

	/**
	 * Draft posts whose Loyverse items disappeared.
	 *
	 * @param array $seen_item_ids Seen Loyverse item IDs.
	 */
	public static function draft_missing_items( array $seen_item_ids ): void {
		$query = new WP_Query(
			array(
				'post_type'      => LM_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_lm_item_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$loyverse_id = (string) get_post_meta( $post_id, '_lm_item_id', true );
			if ( $loyverse_id && ! in_array( $loyverse_id, $seen_item_ids, true ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				update_post_meta( $post_id, '_lm_available', 0 );
			}
		}
	}

	/**
	 * Find post by Loyverse item ID.
	 *
	 * @param string $item_id Loyverse ID.
	 * @return int
	 */
	public static function find_post_by_loyverse_id( string $item_id ): int {
		$query = new WP_Query(
			array(
				'post_type'      => LM_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_lm_item_id',
				'meta_value'     => $item_id,
			)
		);
		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Find term by Loyverse category ID.
	 *
	 * @param string $category_id Loyverse ID.
	 * @return int
	 */
	public static function find_term_by_loyverse_id( string $category_id ): int {
		$terms = get_terms(
			array(
				'taxonomy'   => LM_CPT::TAXONOMY,
				'hide_empty' => false,
				'meta_key'   => '_lm_category_id',
				'meta_value' => $category_id,
				'number'     => 1,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}
		return (int) $terms[0]->term_id;
	}

	/**
	 * Check slug collision with taxonomy term.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	private static function slug_conflicts_with_term( string $slug ): bool {
		$term = get_term_by( 'slug', $slug, LM_CPT::TAXONOMY );
		return (bool) $term;
	}

	/**
	 * Resolve display price for an item.
	 *
	 * @param array  $variants Variants.
	 * @param string $store_id Store ID.
	 * @return float|null
	 */
	private static function resolve_price( array $variants, string $store_id ): ?float {
		if ( empty( $variants[0] ) ) {
			return null;
		}
		return self::variant_price( $variants[0], $store_id );
	}

	/**
	 * Price for one variant (store override or default).
	 *
	 * @param array  $variant  Variant.
	 * @param string $store_id Store ID.
	 * @return float|null
	 */
	public static function variant_price( array $variant, string $store_id ): ?float {
		if ( $store_id && ! empty( $variant['stores'] ) && is_array( $variant['stores'] ) ) {
			foreach ( $variant['stores'] as $store ) {
				if ( isset( $store['store_id'] ) && (string) $store['store_id'] === $store_id && isset( $store['price'] ) ) {
					return (float) $store['price'];
				}
			}
		}
		return isset( $variant['default_price'] ) ? (float) $variant['default_price'] : null;
	}

	/**
	 * Whether variant is available based on inventory map.
	 *
	 * @param array $variant       Variant.
	 * @param array $inventory_map Map.
	 * @return bool
	 */
	private static function variant_available( array $variant, array $inventory_map ): bool {
		if ( empty( $inventory_map ) || empty( $variant['variant_id'] ) ) {
			return true;
		}
		$vid = (string) $variant['variant_id'];
		if ( ! array_key_exists( $vid, $inventory_map ) ) {
			return true;
		}
		$stock = $inventory_map[ $vid ];
		return null === $stock || $stock > 0;
	}

	/**
	 * Item available if any variant has stock (when tracking inventory).
	 *
	 * @param array $variants      Variants.
	 * @param array $inventory_map Map.
	 * @return bool
	 */
	private static function is_available_from_inventory( array $variants, array $inventory_map ): bool {
		if ( empty( $variants ) ) {
			return true;
		}
		foreach ( $variants as $variant ) {
			if ( self::variant_available( $variant, $inventory_map ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Download / attach image to post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $item    Loyverse item.
	 * @return bool Whether image was (re)synced.
	 */
	public static function sync_image( int $post_id, array $item ): bool {
		$image_url = '';
		if ( ! empty( $item['image_url'] ) ) {
			$image_url = (string) $item['image_url'];
		} elseif ( ! empty( $item['image']['url'] ) ) {
			$image_url = (string) $item['image']['url'];
		}

		$previous_url = (string) get_post_meta( $post_id, '_lm_image_url', true );
		if ( ! $image_url ) {
			return false;
		}
		if ( $previous_url === $image_url && has_post_thumbnail( $post_id ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );
		update_post_meta( $post_id, '_lm_image_url', esc_url_raw( $image_url ) );
		if ( ! empty( $item['image_id'] ) ) {
			update_post_meta( $post_id, '_lm_image_id', sanitize_text_field( (string) $item['image_id'] ) );
		}

		return true;
	}
}
