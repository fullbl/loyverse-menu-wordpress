<?php
/**
 * Plugin settings and admin page.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage and admin UI.
 */
class LM_Settings {

	public const OPTION_KEY = 'lm_settings';
	public const STATUS_KEY = 'lm_status';

	/**
	 * Hook admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_lm_sync_now', array( __CLASS__, 'handle_sync_now' ) );
		add_action( 'admin_post_lm_test_connection', array( __CLASS__, 'handle_test_connection' ) );
		add_action( 'admin_post_lm_register_webhook', array( __CLASS__, 'handle_register_webhook' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_flush_rewrites' ), 10, 2 );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'api_token'                => '',
			'store_id'                 => '',
			'permalink_base'           => 'menu',
			'enable_singles'           => 1,
			'enable_category_archives' => 1,
			'cron_interval'            => 'hourly',
			'layout'                   => 'grid',
			'columns'                  => 2,
			'show_images'              => 1,
			'show_descriptions'        => 1,
			'show_prices'              => 1,
			'show_variants'            => 1,
			'accent_color'             => '#1a1a1a',
			'gap'                      => '1.5rem',
			'custom_css'               => '',
		);
	}

	/**
	 * Get settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Persist settings.
	 *
	 * @param array $settings Settings.
	 */
	public static function update_settings( array $settings ): void {
		update_option( self::OPTION_KEY, self::sanitize( $settings ), false );
	}

	/**
	 * Get sync/connection status.
	 *
	 * @return array
	 */
	public static function get_status(): array {
		$status = get_option( self::STATUS_KEY, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}
		return array_merge(
			array(
				'connection'     => 'unknown',
				'last_sync'      => '',
				'last_error'     => '',
				'webhook_status' => 'unregistered',
				'webhook_id'     => '',
				'last_message'   => '',
			),
			$status
		);
	}

	/**
	 * Update status fields.
	 *
	 * @param array $patch Partial status.
	 */
	public static function update_status( array $patch ): void {
		update_option( self::STATUS_KEY, array_merge( self::get_status(), $patch ), false );
	}

	/**
	 * Register options page.
	 */
	public static function register_menu(): void {
		add_options_page(
			__( 'Loyverse Menu', 'loyverse-menu' ),
			__( 'Loyverse Menu', 'loyverse-menu' ),
			'manage_options',
			'loyverse-menu',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register setting.
	 */
	public static function register_settings(): void {
		register_setting(
			'lm_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$defaults = self::defaults();
		$current  = self::get_settings();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		$token = isset( $input['api_token'] ) ? trim( (string) $input['api_token'] ) : '';
		if ( '' === $token || '********' === $token ) {
			$out['api_token'] = $current['api_token'];
		} else {
			$out['api_token'] = sanitize_text_field( $token );
		}

		$out['store_id'] = array_key_exists( 'store_id', $input )
			? sanitize_text_field( (string) $input['store_id'] )
			: (string) ( $current['store_id'] ?? '' );
		$out['permalink_base']           = isset( $input['permalink_base'] ) ? sanitize_title( (string) $input['permalink_base'] ) : 'menu';
		$out['enable_singles']           = empty( $input['enable_singles'] ) ? 0 : 1;
		$out['enable_category_archives'] = empty( $input['enable_category_archives'] ) ? 0 : 1;
		$out['cron_interval']            = isset( $input['cron_interval'] ) && in_array( $input['cron_interval'], array( 'hourly', 'twicedaily', 'daily' ), true )
			? $input['cron_interval']
			: 'hourly';
		$out['layout']                   = isset( $input['layout'] ) && in_array( $input['layout'], array( 'grid', 'list' ), true )
			? $input['layout']
			: 'grid';
		$columns                         = isset( $input['columns'] ) ? (int) $input['columns'] : 2;
		$out['columns']                  = max( 1, min( 3, $columns ) );
		$out['show_images']              = empty( $input['show_images'] ) ? 0 : 1;
		$out['show_descriptions']        = empty( $input['show_descriptions'] ) ? 0 : 1;
		$out['show_prices']              = empty( $input['show_prices'] ) ? 0 : 1;
		$out['show_variants']            = empty( $input['show_variants'] ) ? 0 : 1;
		$out['accent_color']             = isset( $input['accent_color'] ) ? sanitize_hex_color( (string) $input['accent_color'] ) : $defaults['accent_color'];
		if ( ! $out['accent_color'] ) {
			$out['accent_color'] = $defaults['accent_color'];
		}
		$out['gap']        = isset( $input['gap'] ) ? sanitize_text_field( (string) $input['gap'] ) : $defaults['gap'];
		$out['custom_css'] = isset( $input['custom_css'] ) ? wp_strip_all_tags( (string) $input['custom_css'] ) : '';

		if ( empty( $out['permalink_base'] ) ) {
			$out['permalink_base'] = 'menu';
		}

		return $out;
	}

	/**
	 * Flush rewrites when permalink-related settings change.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function maybe_flush_rewrites( $old, $new ): void {
		$old = is_array( $old ) ? $old : array();
		$new = is_array( $new ) ? $new : array();
		$keys = array( 'permalink_base', 'enable_singles', 'enable_category_archives' );
		foreach ( $keys as $key ) {
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) {
				flush_rewrite_rules();
				LM_Cron::reschedule();
				return;
			}
		}
		if ( ( $old['cron_interval'] ?? '' ) !== ( $new['cron_interval'] ?? '' ) ) {
			LM_Cron::reschedule();
		}
	}

	/**
	 * Handle Sync Now.
	 */
	public static function handle_sync_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'loyverse-menu' ) );
		}
		check_admin_referer( 'lm_sync_now' );

		$result = LM_Sync::run();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'loyverse-menu',
					'lm_notice' => is_wp_error( $result ) ? 'sync_error' : 'sync_ok',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle connection test.
	 */
	public static function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'loyverse-menu' ) );
		}
		check_admin_referer( 'lm_test_connection' );

		$ok     = false;
		$client = LM_API_Client::from_settings();
		if ( is_wp_error( $client ) ) {
			self::update_status(
				array(
					'connection'   => 'error',
					'last_error'   => $client->get_error_message(),
					'last_message' => $client->get_error_message(),
				)
			);
		} else {
			$result = $client->test_connection();
			if ( is_wp_error( $result ) ) {
				self::update_status(
					array(
						'connection'   => 'error',
						'last_error'   => $result->get_error_message(),
						'last_message' => $result->get_error_message(),
					)
				);
			} else {
				$stores = isset( $result['stores'] ) && is_array( $result['stores'] ) ? $result['stores'] : array();
				self::update_status(
					array(
						'connection'   => 'ok',
						'last_error'   => '',
						'last_message' => sprintf(
							/* translators: %d: number of stores */
							__( 'Connection successful. %d store(s) found.', 'loyverse-menu' ),
							count( $stores )
						),
					)
				);
				$ok = true;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'loyverse-menu',
					'lm_notice' => $ok ? 'test_ok' : 'test_error',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Register Loyverse webhook.
	 */
	public static function handle_register_webhook(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'loyverse-menu' ) );
		}
		check_admin_referer( 'lm_register_webhook' );

		$result = LM_Webhook::register();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'loyverse-menu',
					'lm_notice' => is_wp_error( $result ) ? 'webhook_error' : 'webhook_ok',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$status   = self::get_status();
		$stores = array();
		$client = LM_API_Client::from_settings();
		if ( ! is_wp_error( $client ) ) {
			$fetched = $client->get_stores();
			if ( ! is_wp_error( $fetched ) ) {
				$stores = $fetched;
			}
		}

		$token_display = $settings['api_token'] ? '********' : '';
		$webhook_url   = LM_Webhook::get_endpoint_url();

		include LM_PLUGIN_DIR . 'includes/views/settings-page.php';
	}
}
