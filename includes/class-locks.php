<?php
/**
 * Editorial field locks.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metabox to lock title/content/image from sync overwrite.
 */
class LM_Locks {

	/**
	 * Register metabox hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'save_post_' . LM_CPT::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Whether a field is locked.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   title|content|image.
	 * @return bool
	 */
	public static function is_locked( int $post_id, string $field ): bool {
		$key = '_lm_lock_' . $field;
		return (bool) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Add metabox.
	 */
	public static function add_metabox(): void {
		add_meta_box(
			'lm_locks',
			__( 'Loyverse Sync Locks', 'loyverse-menu' ),
			array( __CLASS__, 'render' ),
			LM_CPT::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'lm_save_locks', 'lm_locks_nonce' );
		$fields = array(
			'title'   => __( 'Lock title (keep WordPress title on sync)', 'loyverse-menu' ),
			'content' => __( 'Lock description (keep WordPress content on sync)', 'loyverse-menu' ),
			'image'   => __( 'Lock image (keep featured image on sync)', 'loyverse-menu' ),
		);
		foreach ( $fields as $key => $label ) {
			$checked = self::is_locked( (int) $post->ID, $key );
			printf(
				'<p><label><input type="checkbox" name="lm_lock_%1$s" value="1" %2$s /> %3$s</label></p>',
				esc_attr( $key ),
				checked( $checked, true, false ),
				esc_html( $label )
			);
		}

		$item_id = get_post_meta( $post->ID, '_lm_item_id', true );
		$price   = get_post_meta( $post->ID, '_lm_price', true );
		$synced  = get_post_meta( $post->ID, '_lm_synced_at', true );
		echo '<hr />';
		if ( $item_id ) {
			printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Loyverse ID:', 'loyverse-menu' ), esc_html( (string) $item_id ) );
		}
		if ( '' !== $price && null !== $price ) {
			printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Price:', 'loyverse-menu' ), esc_html( (string) $price ) );
		}
		if ( $synced ) {
			printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Last sync:', 'loyverse-menu' ), esc_html( (string) $synced ) );
		}
	}

	/**
	 * Save locks.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['lm_locks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_locks_nonce'] ) ), 'lm_save_locks' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array( 'title', 'content', 'image' ) as $field ) {
			$key   = '_lm_lock_' . $field;
			$value = ! empty( $_POST[ 'lm_lock_' . $field ] ) ? 1 : 0;
			update_post_meta( $post_id, $key, $value );
		}
	}
}
