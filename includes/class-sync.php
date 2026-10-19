<?php
/**
 * Idempotent Loyverse → WordPress sync (batched).
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syncs categories and items in resumable batches.
 */
class FBMSL_Sync {

	public const CHECKPOINT_KEY = 'fbmsl_sync_checkpoint';

	public const PHASE_CATEGORIES = 'categories';
	public const PHASE_ITEMS      = 'items';
	public const PHASE_INVENTORY  = 'inventory';
	public const PHASE_CLEANUP    = 'cleanup';
	public const PHASE_DONE       = 'done';

	/**
	 * Legacy entry point — enqueue and run batches until complete (or error).
	 *
	 * Prefer FBMSL_Sync_Job::enqueue() / process_batch() for production paths.
	 *
	 * @param FBMSL_API_Client|null $client Optional client (tests).
	 * @return array|WP_Error Summary or error.
	 */
	public static function run( ?FBMSL_API_Client $client = null ) {
		$guard = 0;
		$last  = null;
		do {
			$last = self::process_batch( true, $client, 'run' );
			if ( is_wp_error( $last ) ) {
				return $last;
			}
			++$guard;
		} while ( empty( $last['done'] ) && $guard < 500 );

		return $last;
	}

	/**
	 * Process one capped sync batch under the store lock.
	 *
	 * @param bool                  $inline Run immediately (manual UX); still uses the same lock.
	 * @param FBMSL_API_Client|null $client Optional client.
	 * @param string                $source Trigger source label.
	 * @return array|WP_Error
	 */
	public static function process_batch( bool $inline = false, ?FBMSL_API_Client $client = null, string $source = 'scheduled' ) {
		$owner = wp_generate_password( 12, false, false );

		if ( ! FBMSL_Sync_Lock::acquire( $owner ) ) {
			FBMSL_Sync_Lock::set_pending();
			FBMSL_Settings::update_status(
				array(
					'sync_pending' => 1,
					'last_message' => __( 'Sync is already running; a follow-up was queued.', 'fullbl-menu-sync-for-loyverse' ),
				)
			);
			FBMSL_Sync_Job::schedule_now( 'pending' );
			return array(
				'done'    => false,
				'locked'  => true,
				'pending' => true,
				'source'  => $source,
			);
		}

		try {
			if ( null === $client ) {
				$client = FBMSL_API_Client::from_settings();
				if ( is_wp_error( $client ) ) {
					self::fail_auth_or_config( $client );
					return $client;
				}
			}

			$checkpoint = self::get_checkpoint();
			if ( empty( $checkpoint['phase'] ) || self::PHASE_DONE === $checkpoint['phase'] ) {
				$checkpoint = self::fresh_checkpoint();
			}

			$limits  = self::get_limits();
			$started = microtime( true );
			$stats   = array(
				'pages'   => 0,
				'items'   => 0,
				'images'  => 0,
				'skipped' => 0,
			);

			FBMSL_Settings::update_status(
				array(
					'sync_in_progress' => 1,
					'sync_phase'       => $checkpoint['phase'],
					'sync_items_done'  => (int) ( $checkpoint['items_done'] ?? 0 ),
					'sync_pending'     => FBMSL_Sync_Lock::is_pending() ? 1 : 0,
					'last_message'     => sprintf(
						/* translators: 1: phase, 2: source */
						__( 'Sync in progress (%1$s, via %2$s)…', 'fullbl-menu-sync-for-loyverse' ),
						$checkpoint['phase'],
						$source
					),
				)
			);

			$result = self::advance( $client, $checkpoint, $limits, $started, $stats );
			if ( is_wp_error( $result ) ) {
				return self::handle_batch_error( $result, $checkpoint, $owner );
			}

			$checkpoint = $result;
			self::save_checkpoint( $checkpoint );

			$done = ( self::PHASE_DONE === $checkpoint['phase'] );

			if ( $done ) {
				$was_pending = FBMSL_Sync_Lock::is_pending();
				self::clear_checkpoint();
				FBMSL_Sync_Lock::clear_pending();
				$summary = array(
					'done'       => true,
					'categories' => (int) ( $checkpoint['categories_done'] ?? 0 ),
					'items'      => (int) ( $checkpoint['items_done'] ?? 0 ),
					'images'     => (int) ( $checkpoint['images_done'] ?? 0 ),
					'skipped'    => (int) ( $checkpoint['skipped'] ?? 0 ),
					'source'     => $source,
					'inline'     => $inline,
				);
				FBMSL_Settings::update_status(
					array(
						'connection'       => 'ok',
						'last_sync'        => gmdate( 'c' ),
						'last_error'       => '',
						'auth_error'       => 0,
						'sync_in_progress' => 0,
						'sync_phase'       => '',
						'sync_items_done'  => $summary['items'],
						'sync_pending'     => $was_pending ? 1 : 0,
						'last_message'     => sprintf(
							/* translators: 1: category count, 2: item count */
							__( 'Synced %1$d categories and %2$d items.', 'fullbl-menu-sync-for-loyverse' ),
							$summary['categories'],
							$summary['items']
						),
					)
				);
				if ( $was_pending ) {
					FBMSL_Sync_Job::schedule_now( 'pending' );
				}
				return $summary;
			}

			FBMSL_Settings::update_status(
				array(
					'connection'       => 'ok',
					'sync_in_progress' => 1,
					'sync_phase'       => $checkpoint['phase'],
					'sync_items_done'  => (int) ( $checkpoint['items_done'] ?? 0 ),
					'sync_pending'     => FBMSL_Sync_Lock::is_pending() ? 1 : 0,
					'last_error'       => '',
					'last_message'     => sprintf(
						/* translators: 1: phase, 2: items done */
						__( 'Sync progress: %1$s — %2$d items processed.', 'fullbl-menu-sync-for-loyverse' ),
						$checkpoint['phase'],
						(int) ( $checkpoint['items_done'] ?? 0 )
					),
				)
			);

			FBMSL_Sync_Lock::release( $owner );
			FBMSL_Sync_Job::schedule_now( 'followup' );

			return array(
				'done'       => false,
				'phase'      => $checkpoint['phase'],
				'items_done' => (int) ( $checkpoint['items_done'] ?? 0 ),
				'source'     => $source,
				'inline'     => $inline,
			);
		} finally {
			FBMSL_Sync_Lock::release( $owner );
		}
	}

	/**
	 * Handle API / sync errors from a batch.
	 *
	 * @param WP_Error $error      Error.
	 * @param array    $checkpoint Checkpoint to persist for resume.
	 * @param string   $owner      Lock owner.
	 * @return WP_Error|array
	 */
	private static function handle_batch_error( WP_Error $error, array $checkpoint, string $owner ) {
		$code = $error->get_error_code();
		self::save_checkpoint( $checkpoint );

		if ( 'fbmsl_api_auth' === $code ) {
			self::fail_auth_or_config( $error );
			FBMSL_Sync_Lock::release( $owner );
			return $error;
		}

		if ( in_array( $code, array( 'fbmsl_api_rate_limit', 'fbmsl_api_server' ), true ) ) {
			$data        = $error->get_error_data();
			$retry_after = is_array( $data ) && isset( $data['retry_after'] ) ? (int) $data['retry_after'] : 30;
			$retry_after = max( 15, min( 300, $retry_after > 0 ? $retry_after : 30 ) );

			FBMSL_Settings::update_status(
				array(
					'connection'       => 'ok',
					'last_error'       => $error->get_error_message(),
					'sync_in_progress' => 1,
					'sync_phase'       => $checkpoint['phase'] ?? '',
					'sync_items_done'  => (int) ( $checkpoint['items_done'] ?? 0 ),
					'last_message'     => sprintf(
						/* translators: %d: seconds */
						__( 'Temporary API issue — retrying in %d seconds.', 'fullbl-menu-sync-for-loyverse' ),
						$retry_after
					),
				)
			);

			FBMSL_Sync_Lock::release( $owner );

			$retry_args = array(
				array(
					'source' => 'retry',
				),
			);
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + $retry_after, FBMSL_Sync_Job::HOOK, $retry_args, 'fbmsl' );
			} else {
				wp_schedule_single_event( time() + $retry_after, FBMSL_Sync_Job::HOOK, $retry_args );
			}

			return array(
				'done'    => false,
				'retry'   => true,
				'message' => $error->get_error_message(),
			);
		}

		FBMSL_Settings::update_status(
			array(
				'connection'       => 'error',
				'last_error'       => $error->get_error_message(),
				'sync_in_progress' => 0,
				'last_message'     => $error->get_error_message(),
			)
		);
		FBMSL_Sync_Lock::release( $owner );
		return $error;
	}

	/**
	 * Persist auth/config failure and flag admin notice.
	 *
	 * @param WP_Error $error Error.
	 */
	private static function fail_auth_or_config( WP_Error $error ): void {
		FBMSL_Settings::update_status(
			array(
				'connection'       => 'error',
				'last_error'       => $error->get_error_message(),
				'sync_in_progress' => 0,
				'auth_error'       => 1,
				'last_message'     => $error->get_error_message(),
			)
		);
	}

	/**
	 * Advance checkpoint through phases until limits hit or done.
	 *
	 * @param FBMSL_API_Client $client     Client.
	 * @param array            $checkpoint State.
	 * @param array            $limits     Caps.
	 * @param float            $started    microtime start.
	 * @param array            $stats      Run counters.
	 * @return array|WP_Error Updated checkpoint.
	 */
	private static function advance( FBMSL_API_Client $client, array $checkpoint, array $limits, float $started, array &$stats ) {
		$settings = FBMSL_Settings::get_settings();
		$store_id = (string) $settings['store_id'];

		while ( self::PHASE_DONE !== $checkpoint['phase'] ) {
			if ( self::limits_exhausted( $limits, $started, $stats ) ) {
				break;
			}

			switch ( $checkpoint['phase'] ) {
				case self::PHASE_CATEGORIES:
					$result = self::phase_categories( $client, $checkpoint, $limits, $started, $stats );
					break;
				case self::PHASE_ITEMS:
					$result = self::phase_items( $client, $checkpoint, $store_id, $limits, $started, $stats );
					break;
				case self::PHASE_INVENTORY:
					$result = self::phase_inventory( $client, $checkpoint, $store_id, $limits, $started, $stats );
					break;
				case self::PHASE_CLEANUP:
					$result = self::phase_cleanup( $checkpoint );
					break;
				default:
					$checkpoint['phase'] = self::PHASE_DONE;
					$result              = $checkpoint;
					break;
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$checkpoint = $result;
		}

		return $checkpoint;
	}

	/**
	 * Categories phase.
	 *
	 * @param FBMSL_API_Client $client     Client.
	 * @param array            $checkpoint State.
	 * @param array            $limits     Caps.
	 * @param float            $started    Start time.
	 * @param array            $stats      Counters.
	 * @return array|WP_Error
	 */
	private static function phase_categories( FBMSL_API_Client $client, array $checkpoint, array $limits, float $started, array &$stats ) {
		while ( ! self::limits_exhausted( $limits, $started, $stats ) ) {
			$page = $client->get_page( '/categories', 'categories', $checkpoint['cursor'] );
			if ( is_wp_error( $page ) ) {
				return $page;
			}
			++$stats['pages'];

			$term_map = isset( $checkpoint['term_map'] ) && is_array( $checkpoint['term_map'] ) ? $checkpoint['term_map'] : array();
			$mapped   = self::sync_categories( $page['items'] );
			$term_map = array_merge( $term_map, $mapped );

			$checkpoint['term_map']        = $term_map;
			$checkpoint['categories_done'] = count( $term_map );
			$checkpoint['cursor']          = $page['cursor'];

			if ( ! $page['cursor'] ) {
				$checkpoint['phase']  = self::PHASE_ITEMS;
				$checkpoint['cursor'] = null;
				break;
			}
		}
		return $checkpoint;
	}

	/**
	 * Items phase.
	 *
	 * @param FBMSL_API_Client $client     Client.
	 * @param array            $checkpoint State.
	 * @param string           $store_id   Store.
	 * @param array            $limits     Caps.
	 * @param float            $started    Start.
	 * @param array            $stats      Counters.
	 * @return array|WP_Error
	 */
	private static function phase_items( FBMSL_API_Client $client, array $checkpoint, string $store_id, array $limits, float $started, array &$stats ) {
		$term_map      = isset( $checkpoint['term_map'] ) && is_array( $checkpoint['term_map'] ) ? $checkpoint['term_map'] : array();
		$inventory_map = isset( $checkpoint['inventory_map'] ) && is_array( $checkpoint['inventory_map'] ) ? $checkpoint['inventory_map'] : array();
		$seen          = isset( $checkpoint['seen_item_ids'] ) && is_array( $checkpoint['seen_item_ids'] ) ? $checkpoint['seen_item_ids'] : array();
		$buffer        = isset( $checkpoint['item_buffer'] ) && is_array( $checkpoint['item_buffer'] ) ? $checkpoint['item_buffer'] : array();

		while ( ! self::limits_exhausted( $limits, $started, $stats ) ) {
			if ( empty( $buffer ) ) {
				$page = $client->get_page( '/items', 'items', $checkpoint['cursor'] );
				if ( is_wp_error( $page ) ) {
					return $page;
				}
				++$stats['pages'];
				$buffer                    = $page['items'];
				$checkpoint['cursor']      = $page['cursor'];
				$checkpoint['item_buffer'] = $buffer;

				if ( empty( $buffer ) && ! $page['cursor'] ) {
					$checkpoint['phase']         = self::PHASE_INVENTORY;
					$checkpoint['cursor']        = null;
					$checkpoint['item_buffer']   = array();
					$checkpoint['seen_item_ids'] = $seen;
					return $checkpoint;
				}
			}

			while ( ! empty( $buffer ) && ! self::limits_exhausted( $limits, $started, $stats ) ) {
				$item                      = array_shift( $buffer );
				$checkpoint['item_buffer'] = $buffer;

				if ( empty( $item['id'] ) ) {
					continue;
				}

				$item_id          = (string) $item['id'];
				$seen[ $item_id ] = $item_id;

				$result = self::upsert_item( $item, $term_map, $store_id, $inventory_map );
				if ( is_wp_error( $result ) ) {
					continue;
				}

				++$stats['items'];
				$checkpoint['items_done'] = (int) ( $checkpoint['items_done'] ?? 0 ) + 1;

				if ( ! empty( $result['skipped'] ) ) {
					++$stats['skipped'];
					$checkpoint['skipped'] = (int) ( $checkpoint['skipped'] ?? 0 ) + 1;
				}
				if ( ! empty( $result['image_synced'] ) ) {
					++$stats['images'];
					$checkpoint['images_done'] = (int) ( $checkpoint['images_done'] ?? 0 ) + 1;
				}
				if ( ! empty( $result['image_attempted'] ) ) {
					// Count toward image download cap even on failure (retry next run).
					++$stats['images'];
				}
			}

			if ( empty( $buffer ) && empty( $checkpoint['cursor'] ) ) {
				$checkpoint['phase']         = self::PHASE_INVENTORY;
				$checkpoint['cursor']        = null;
				$checkpoint['item_buffer']   = array();
				$checkpoint['seen_item_ids'] = $seen;
				return $checkpoint;
			}
		}

		$checkpoint['seen_item_ids'] = $seen;
		$checkpoint['item_buffer']   = $buffer;
		return $checkpoint;
	}

	/**
	 * Inventory phase — refresh stock then apply availability.
	 *
	 * @param FBMSL_API_Client $client     Client.
	 * @param array            $checkpoint State.
	 * @param string           $store_id   Store.
	 * @param array            $limits     Caps.
	 * @param float            $started    Start.
	 * @param array            $stats      Counters.
	 * @return array|WP_Error
	 */
	private static function phase_inventory( FBMSL_API_Client $client, array $checkpoint, string $store_id, array $limits, float $started, array &$stats ) {
		if ( ! $store_id ) {
			$checkpoint['phase']  = self::PHASE_CLEANUP;
			$checkpoint['cursor'] = null;
			return $checkpoint;
		}

		$inventory_map = isset( $checkpoint['inventory_map'] ) && is_array( $checkpoint['inventory_map'] ) ? $checkpoint['inventory_map'] : array();

		while ( ! self::limits_exhausted( $limits, $started, $stats ) ) {
			$page = $client->get_page(
				'/inventory_levels',
				'inventory_levels',
				$checkpoint['cursor'],
				array( 'store_ids' => $store_id )
			);
			if ( is_wp_error( $page ) ) {
				// Inventory is optional — skip to cleanup on hard non-retryable errors other than auth/rate.
				if ( in_array( $page->get_error_code(), array( 'fbmsl_api_auth', 'fbmsl_api_rate_limit', 'fbmsl_api_server' ), true ) ) {
					return $page;
				}
				$checkpoint['phase']  = self::PHASE_CLEANUP;
				$checkpoint['cursor'] = null;
				return $checkpoint;
			}
			++$stats['pages'];

			foreach ( $page['items'] as $level ) {
				if ( empty( $level['variant_id'] ) ) {
					continue;
				}
				$inventory_map[ (string) $level['variant_id'] ] = isset( $level['in_stock'] ) ? (float) $level['in_stock'] : null;
			}
			$checkpoint['inventory_map'] = $inventory_map;
			$checkpoint['cursor']        = $page['cursor'];

			if ( ! $page['cursor'] ) {
				self::apply_inventory_to_posts( $inventory_map, $store_id );
				$checkpoint['phase']  = self::PHASE_CLEANUP;
				$checkpoint['cursor'] = null;
				return $checkpoint;
			}
		}

		return $checkpoint;
	}

	/**
	 * Cleanup phase — hide items removed upstream (only after a complete pass).
	 *
	 * @param array $checkpoint State.
	 * @return array
	 */
	private static function phase_cleanup( array $checkpoint ): array {
		$seen = isset( $checkpoint['seen_item_ids'] ) && is_array( $checkpoint['seen_item_ids'] )
			? array_values( $checkpoint['seen_item_ids'] )
			: array();
		self::handle_missing_items( $seen );
		$checkpoint['phase'] = self::PHASE_DONE;
		return $checkpoint;
	}

	/**
	 * Apply inventory availability to existing posts for a store.
	 *
	 * @param array  $inventory_map variant_id => stock.
	 * @param string $store_id      Store ID.
	 */
	private static function apply_inventory_to_posts( array $inventory_map, string $store_id ): void {
		if ( empty( $inventory_map ) ) {
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'      => FBMSL_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- lookup by indexed store meta.
				'meta_query'     => array(
					array(
						'key'   => '_fbmsl_store_id',
						'value' => $store_id,
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$raw  = (string) get_post_meta( $post_id, '_fbmsl_variant_data', true );
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$changed   = false;
			$available = false;
			foreach ( $data as &$variant ) {
				$vid = isset( $variant['variant_id'] ) ? (string) $variant['variant_id'] : '';
				if ( ! $vid || ! array_key_exists( $vid, $inventory_map ) ) {
					$variant['available'] = true;
					$available            = true;
					continue;
				}
				$stock         = $inventory_map[ $vid ];
				$new_available = null === $stock || $stock > 0;
				if ( (bool) ( $variant['available'] ?? true ) !== $new_available ) {
					$changed = true;
				}
				$variant['available'] = $new_available;
				if ( $new_available ) {
					$available = true;
				}
			}
			unset( $variant );

			if ( $changed ) {
				update_post_meta( $post_id, '_fbmsl_variant_data', wp_json_encode( $data ) );
			}
			update_post_meta( $post_id, '_fbmsl_available', $available ? 1 : 0 );
			$post = get_post( $post_id );
			// Do not auto-publish drafts; only demote published items that are out of stock.
			if ( $post && ! $available && 'publish' === $post->post_status ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
			}
		}
	}

	/**
	 * Default / filterable per-run limits.
	 *
	 * @return array{max_pages:int,max_items:int,max_images:int,max_seconds:int}
	 */
	public static function get_limits(): array {
		return array(
			'max_pages'   => (int) apply_filters( 'fbmsl_sync_max_pages_per_run', 5 ),
			'max_items'   => (int) apply_filters( 'fbmsl_sync_max_items_per_run', 50 ),
			'max_images'  => (int) apply_filters( 'fbmsl_sync_max_images_per_run', 10 ),
			'max_seconds' => (int) apply_filters( 'fbmsl_sync_max_seconds_per_run', 20 ),
		);
	}

	/**
	 * Whether batch caps are exhausted.
	 *
	 * @param array $limits  Caps.
	 * @param float $started Start microtime.
	 * @param array $stats   Counters.
	 * @return bool
	 */
	public static function limits_exhausted( array $limits, float $started, array $stats ): bool {
		if ( $stats['pages'] >= $limits['max_pages'] ) {
			return true;
		}
		if ( $stats['items'] >= $limits['max_items'] ) {
			return true;
		}
		if ( $stats['images'] >= $limits['max_images'] ) {
			return true;
		}
		if ( ( microtime( true ) - $started ) >= $limits['max_seconds'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Fresh checkpoint.
	 *
	 * @return array
	 */
	public static function fresh_checkpoint(): array {
		return array(
			'phase'           => self::PHASE_CATEGORIES,
			'cursor'          => null,
			'term_map'        => array(),
			'inventory_map'   => array(),
			'seen_item_ids'   => array(),
			'item_buffer'     => array(),
			'items_done'      => 0,
			'categories_done' => 0,
			'images_done'     => 0,
			'skipped'         => 0,
			'started_at'      => gmdate( 'c' ),
		);
	}

	/**
	 * Load checkpoint.
	 *
	 * @return array
	 */
	public static function get_checkpoint(): array {
		$stored = get_option( self::CHECKPOINT_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Persist checkpoint.
	 *
	 * @param array $checkpoint State.
	 */
	public static function save_checkpoint( array $checkpoint ): void {
		update_option( self::CHECKPOINT_KEY, $checkpoint, false );
	}

	/**
	 * Clear checkpoint.
	 */
	public static function clear_checkpoint(): void {
		delete_option( self::CHECKPOINT_KEY );
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
				$existing = get_term( $term_id, FBMSL_CPT::TAXONOMY );
				if ( $existing && ! is_wp_error( $existing ) && $existing->name !== $name ) {
					wp_update_term(
						$term_id,
						FBMSL_CPT::TAXONOMY,
						array(
							'name' => $name,
						)
					);
				}
			} else {
				$created = wp_insert_term(
					$name,
					FBMSL_CPT::TAXONOMY,
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
				update_term_meta( $term_id, '_fbmsl_category_id', $loyverse_id );
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
		$post_id = self::find_post_by_loyverse_id( $item_id, $store_id );

		/**
		 * Allow skipping sync for a post (e.g. translations).
		 *
		 * @param bool  $skip    Whether to skip.
		 * @param int   $post_id Existing post ID or 0.
		 * @param array $item    Loyverse item payload.
		 */
		if ( apply_filters( 'fbmsl_sync_skip_post', false, $post_id ? $post_id : 0, $item ) ) {
			return array(
				'post_id'      => $post_id,
				'image_synced' => false,
				'skipped'      => true,
			);
		}

		$title       = isset( $item['item_name'] ) ? sanitize_text_field( (string) $item['item_name'] ) : '';
		$description = isset( $item['description'] ) ? wp_kses_post( (string) $item['description'] ) : '';
		$variants    = isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array();

		$price     = self::resolve_price( $variants, $store_id );
		$available = empty( $item['deleted_at'] );
		if ( $available && $store_id && ! empty( $inventory_map ) ) {
			$available = self::is_available_from_inventory( $variants, $inventory_map );
		}

		$variant_data = self::build_variant_data( $variants, $store_id, $inventory_map, $post_id );
		$image_url    = self::extract_image_url( $item );
		$category_id  = ! empty( $item['category_id'] ) ? (string) $item['category_id'] : '';

		$lock_title   = $post_id && FBMSL_Locks::is_locked( $post_id, 'title' );
		$lock_content = $post_id && FBMSL_Locks::is_locked( $post_id, 'content' );
		$lock_image   = $post_id && FBMSL_Locks::is_locked( $post_id, 'image' );

		$hash = self::compute_sync_hash(
			array(
				'title'       => $lock_title ? '' : $title,
				'description' => $lock_content ? '' : $description,
				'price'       => $price,
				'available'   => $available,
				'variants'    => $variant_data,
				'category_id' => $category_id,
				'image_url'   => $lock_image ? '' : $image_url,
				'store_id'    => $store_id,
				'item_id'     => $item_id,
			)
		);

		$previous_hash = $post_id ? (string) get_post_meta( $post_id, '_fbmsl_sync_hash', true ) : '';
		$image_needs   = ! $lock_image && $image_url && (
			(string) get_post_meta( $post_id, '_fbmsl_image_url', true ) !== $image_url
			|| ! $post_id
			|| ! has_post_thumbnail( $post_id )
		);

		if ( $post_id && $previous_hash === $hash && ! $image_needs ) {
			update_post_meta( $post_id, '_fbmsl_synced_at', gmdate( 'c' ) );
			return array(
				'post_id'      => $post_id,
				'image_synced' => false,
				'skipped'      => true,
			);
		}

		$postarr = array(
			'post_type'   => FBMSL_CPT::POST_TYPE,
			'post_status' => $available ? 'publish' : 'draft',
		);

		if ( ! $lock_title ) {
			$postarr['post_title'] = $title;
		}
		if ( ! $lock_content ) {
			$postarr['post_content'] = $description;
		}

		$needs_post_write = ! $post_id;
		if ( $post_id ) {
			$existing = get_post( $post_id );
			if ( $existing ) {
				if ( ! $lock_title && $existing->post_title !== $title ) {
					$needs_post_write = true;
				}
				if ( ! $lock_content && $existing->post_content !== $description ) {
					$needs_post_write = true;
				}
				$desired_status = $available ? 'publish' : 'draft';
				if ( $existing->post_status !== $desired_status && in_array( $existing->post_status, array( 'publish', 'draft' ), true ) ) {
					$needs_post_write = true;
				}
			}
		}

		if ( $needs_post_write ) {
			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				$result        = wp_update_post( $postarr, true );
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
		}

		update_post_meta( $post_id, '_fbmsl_item_id', $item_id );
		update_post_meta( $post_id, '_fbmsl_store_id', $store_id );
		update_post_meta( $post_id, '_fbmsl_remote_key', self::remote_key( $store_id, $item_id ) );
		update_post_meta( $post_id, '_fbmsl_variant_data', wp_json_encode( $variant_data ) );
		update_post_meta( $post_id, '_fbmsl_variant_ids', wp_json_encode( self::extract_variant_ids( $variant_data ) ) );
		update_post_meta( $post_id, '_fbmsl_price', $price );
		update_post_meta( $post_id, '_fbmsl_available', $available ? 1 : 0 );
		update_post_meta( $post_id, '_fbmsl_synced_at', gmdate( 'c' ) );
		update_post_meta( $post_id, '_fbmsl_sync_hash', $hash );

		if ( $category_id && isset( $term_map[ $category_id ] ) ) {
			wp_set_object_terms( $post_id, array( (int) $term_map[ $category_id ] ), FBMSL_CPT::TAXONOMY, false );
		}

		$image_synced    = false;
		$image_attempted = false;
		if ( ! $lock_image && $image_needs ) {
			$image_attempted = (bool) $image_url;
			$image_synced    = self::sync_image( $post_id, $item );
		}

		return array(
			'post_id'         => $post_id,
			'image_synced'    => $image_synced,
			'image_attempted' => $image_attempted && ! $image_synced,
			'skipped'         => false,
		);
	}

	/**
	 * Build variant rows, matching existing rows by variant_id (renames).
	 *
	 * @param array  $variants      Loyverse variants.
	 * @param string $store_id      Store.
	 * @param array  $inventory_map Inventory.
	 * @param int    $post_id       Existing post.
	 * @return array
	 */
	public static function build_variant_data( array $variants, string $store_id, array $inventory_map = array(), int $post_id = 0 ): array {
		$existing_by_id = array();
		if ( $post_id ) {
			$raw = (string) get_post_meta( $post_id, '_fbmsl_variant_data', true );
			$old = json_decode( $raw, true );
			if ( is_array( $old ) ) {
				foreach ( $old as $row ) {
					if ( ! empty( $row['variant_id'] ) ) {
						$existing_by_id[ (string) $row['variant_id'] ] = $row;
					}
				}
			}
		}

		$variant_data = array();
		foreach ( $variants as $variant ) {
			$vid = isset( $variant['variant_id'] ) ? (string) $variant['variant_id'] : '';
			$row = array(
				'variant_id'    => $vid,
				'sku'           => isset( $variant['sku'] ) ? (string) $variant['sku'] : '',
				'option1_value' => isset( $variant['option1_value'] ) ? (string) $variant['option1_value'] : '',
				'option2_value' => isset( $variant['option2_value'] ) ? (string) $variant['option2_value'] : '',
				'option3_value' => isset( $variant['option3_value'] ) ? (string) $variant['option3_value'] : '',
				'price'         => self::variant_price( $variant, $store_id ),
				'available'     => self::variant_available( $variant, $inventory_map ),
			);
			// Renamed variant: keep identity via variant_id; option labels come from API.
			if ( $vid && isset( $existing_by_id[ $vid ] ) ) {
				$row['_matched'] = true;
			}
			$variant_data[] = $row;
		}

		// Strip internal flag before persist.
		foreach ( $variant_data as &$row ) {
			unset( $row['_matched'] );
		}
		unset( $row );

		return $variant_data;
	}

	/**
	 * Extract variant IDs from variant data rows.
	 *
	 * @param array $variant_data Rows.
	 * @return array
	 */
	public static function extract_variant_ids( array $variant_data ): array {
		$ids = array();
		foreach ( $variant_data as $row ) {
			if ( ! empty( $row['variant_id'] ) ) {
				$ids[] = (string) $row['variant_id'];
			}
		}
		return $ids;
	}

	/**
	 * Composite remote key unique per store + item.
	 *
	 * @param string $store_id Store.
	 * @param string $item_id  Item.
	 * @return string
	 */
	public static function remote_key( string $store_id, string $item_id ): string {
		return $store_id . ':' . $item_id;
	}

	/**
	 * Hash of synced fields for skip-unchanged.
	 *
	 * @param array $fields Fields.
	 * @return string
	 */
	public static function compute_sync_hash( array $fields ): string {
		return md5( (string) wp_json_encode( $fields ) );
	}

	/**
	 * Extract image URL from item payload.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public static function extract_image_url( array $item ): string {
		if ( ! empty( $item['image_url'] ) ) {
			return (string) $item['image_url'];
		}
		if ( ! empty( $item['image']['url'] ) ) {
			return (string) $item['image']['url'];
		}
		return '';
	}

	/**
	 * Hide posts whose Loyverse items disappeared (draft or trash).
	 *
	 * @param array $seen_item_ids Seen Loyverse item IDs.
	 */
	public static function handle_missing_items( array $seen_item_ids ): void {
		$settings = FBMSL_Settings::get_settings();
		$action   = isset( $settings['removed_items_action'] ) ? (string) $settings['removed_items_action'] : 'draft';
		if ( ! in_array( $action, array( 'draft', 'trash' ), true ) ) {
			$action = 'draft';
		}
		$delete_image = ! empty( $settings['delete_imported_image_on_remove'] );

		$query = new WP_Query(
			array(
				'post_type'      => FBMSL_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- synced items only.
				'meta_query'     => array(
					array(
						'key'     => '_fbmsl_item_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$loyverse_id = (string) get_post_meta( $post_id, '_fbmsl_item_id', true );
			if ( ! $loyverse_id || in_array( $loyverse_id, $seen_item_ids, true ) ) {
				continue;
			}

			if ( 'trash' === $action ) {
				if ( $delete_image ) {
					self::maybe_delete_imported_image( (int) $post_id );
				}
				wp_trash_post( (int) $post_id );
			} else {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
			}
			update_post_meta( $post_id, '_fbmsl_available', 0 );
		}
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @param array $seen_item_ids Seen IDs.
	 */
	public static function draft_missing_items( array $seen_item_ids ): void {
		self::handle_missing_items( $seen_item_ids );
	}

	/**
	 * Delete only the plugin-imported featured image, never manual attachments.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function maybe_delete_imported_image( int $post_id ): void {
		$imported_id = (int) get_post_meta( $post_id, '_fbmsl_imported_attachment_id', true );
		$thumb_id    = (int) get_post_thumbnail_id( $post_id );
		if ( $imported_id && $thumb_id && $imported_id === $thumb_id ) {
			wp_delete_attachment( $imported_id, true );
			delete_post_meta( $post_id, '_fbmsl_imported_attachment_id' );
			delete_post_meta( $post_id, '_fbmsl_image_url' );
		}
	}

	/**
	 * Find post by Loyverse item ID (and store when set). Never by title.
	 *
	 * @param string $item_id  Loyverse ID.
	 * @param string $store_id Optional store scope.
	 * @return int
	 */
	public static function find_post_by_loyverse_id( string $item_id, string $store_id = '' ): int {
		$meta_query = array(
			array(
				'key'   => '_fbmsl_item_id',
				'value' => $item_id,
			),
		);
		if ( '' !== $store_id ) {
			$meta_query[] = array(
				'key'   => '_fbmsl_store_id',
				'value' => $store_id,
			);
		}

		$query = new WP_Query(
			array(
				'post_type'              => FBMSL_CPT::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- match by Loyverse IDs, never title.
				'meta_query'             => $meta_query,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
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
				'taxonomy'   => FBMSL_CPT::TAXONOMY,
				'hide_empty' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- remote category id.
				'meta_key'   => '_fbmsl_category_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- remote category id.
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
		$term = get_term_by( 'slug', $slug, FBMSL_CPT::TAXONOMY );
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
	 * Download / attach image to post. Re-download only when URL changes.
	 * On failure keep the existing image and retry next run.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $item    Loyverse item.
	 * @return bool Whether image was (re)synced successfully.
	 */
	public static function sync_image( int $post_id, array $item ): bool {
		$image_url = self::extract_image_url( $item );
		if ( ! $image_url ) {
			return false;
		}

		$previous_url = (string) get_post_meta( $post_id, '_fbmsl_image_url', true );
		if ( $previous_url === $image_url && has_post_thumbnail( $post_id ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			// Keep existing featured image; leave source URL unchanged so the next run retries.
			return false;
		}

		$old_imported = (int) get_post_meta( $post_id, '_fbmsl_imported_attachment_id', true );
		set_post_thumbnail( $post_id, (int) $attachment_id );
		update_post_meta( $post_id, '_fbmsl_image_url', esc_url_raw( $image_url ) );
		update_post_meta( $post_id, '_fbmsl_imported_attachment_id', (int) $attachment_id );
		if ( ! empty( $item['image_id'] ) ) {
			update_post_meta( $post_id, '_fbmsl_image_id', sanitize_text_field( (string) $item['image_id'] ) );
		}

		// Remove previous plugin-imported attachment only (never user media).
		if ( $old_imported && $old_imported !== (int) $attachment_id ) {
			$thumb = (int) get_post_thumbnail_id( $post_id );
			if ( $old_imported !== $thumb ) {
				wp_delete_attachment( $old_imported, true );
			}
		}

		return true;
	}
}
