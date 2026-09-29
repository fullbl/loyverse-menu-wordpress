<?php
/**
 * Plugin settings and admin page.
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage and admin UI.
 */
class MFL_Settings {

	public const OPTION_KEY = 'mfl_settings';
	public const STATUS_KEY = 'mfl_status';

	/**
	 * Hook admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_mfl_sync_now', array( __CLASS__, 'handle_sync_now' ) );
		add_action( 'admin_post_mfl_test_connection', array( __CLASS__, 'handle_test_connection' ) );
		add_action( 'admin_post_mfl_register_webhook', array( __CLASS__, 'handle_register_webhook' ) );
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
		$settings = array_merge( self::defaults(), $stored );

		$token = get_option( 'mfl_api_token', '' );
		if ( is_string( $token ) && '' !== $token ) {
			$settings['api_token'] = $token;
		} elseif ( ! empty( $stored['api_token'] ) && is_string( $stored['api_token'] ) ) {
			$settings['api_token'] = $stored['api_token'];
			update_option( 'mfl_api_token', $settings['api_token'], false );
		}

		return $settings;
	}

	/**
	 * Persist settings.
	 *
	 * @param array $settings Settings.
	 */
	public static function update_settings( array $settings ): void {
		self::persist_settings( self::sanitize( $settings ) );
	}

	/**
	 * Save sanitized settings; API token is stored in a separate non-autoloaded option.
	 *
	 * @param array $sanitized Sanitized settings from self::sanitize().
	 */
	private static function persist_settings( array $sanitized ): void {
		$token = isset( $sanitized['api_token'] ) ? (string) $sanitized['api_token'] : '';
		update_option( 'mfl_api_token', $token, false );

		$stored              = $sanitized;
		$stored['api_token'] = '';
		update_option( self::OPTION_KEY, $stored, false );
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
			__( 'Menu for Loyverse', 'menu-for-loyverse' ),
			__( 'Menu for Loyverse', 'menu-for-loyverse' ),
			'manage_options',
			'menu-for-loyverse',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register setting.
	 */
	public static function register_settings(): void {
		register_setting(
			'mfl_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_for_option' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
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

		$out['store_id']                 = array_key_exists( 'store_id', $input )
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
	 * Sanitize callback for register_setting (persists token separately).
	 *
	 * @param mixed $input Raw input.
	 * @return array Settings without api_token in the main option.
	 */
	public static function sanitize_for_option( $input ): array {
		$sanitized = self::sanitize( $input );
		self::persist_settings( $sanitized );
		$stored              = $sanitized;
		$stored['api_token'] = '';
		return $stored;
	}

	/**
	 * Flush rewrites when permalink-related settings change.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function maybe_flush_rewrites( $old, $new ): void {
		$old  = is_array( $old ) ? $old : array();
		$new  = is_array( $new ) ? $new : array();
		$keys = array( 'permalink_base', 'enable_singles', 'enable_category_archives' );
		foreach ( $keys as $key ) {
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) {
				flush_rewrite_rules();
				MFL_Cron::reschedule();
				return;
			}
		}
		if ( ( $old['cron_interval'] ?? '' ) !== ( $new['cron_interval'] ?? '' ) ) {
			MFL_Cron::reschedule();
		}
	}

	/**
	 * Handle Sync Now.
	 */
	public static function handle_sync_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'menu-for-loyverse' ) );
		}
		check_admin_referer( 'mfl_sync_now' );

		$result = MFL_Sync::run();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'menu-for-loyverse',
					'mfl_notice' => is_wp_error( $result ) ? 'sync_error' : 'sync_ok',
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
			wp_die( esc_html__( 'Forbidden.', 'menu-for-loyverse' ) );
		}
		check_admin_referer( 'mfl_test_connection' );

		$ok     = false;
		$client = MFL_API_Client::from_settings();
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
							__( 'Connection successful. %d store(s) found.', 'menu-for-loyverse' ),
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
					'page'       => 'menu-for-loyverse',
					'mfl_notice' => $ok ? 'test_ok' : 'test_error',
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
			wp_die( esc_html__( 'Forbidden.', 'menu-for-loyverse' ) );
		}
		check_admin_referer( 'mfl_register_webhook' );

		$result = MFL_Webhook::register();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'menu-for-loyverse',
					'mfl_notice' => is_wp_error( $result ) ? 'webhook_error' : 'webhook_ok',
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
		$stores   = array();
		$client   = MFL_API_Client::from_settings();
		if ( ! is_wp_error( $client ) ) {
			$fetched = $client->get_stores();
			if ( ! is_wp_error( $fetched ) ) {
				$stores = $fetched;
			}
		}

		$token_display = $settings['api_token'] ? '********' : '';
		$webhook_url   = MFL_Webhook::get_endpoint_url();

		include MFL_PLUGIN_DIR . 'includes/views/settings-page.php';
	}
}
