<?php
/**
 * Loyverse webhook REST endpoint.
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Near-realtime updates via webhook + secret token.
 */
class MFL_Webhook {

	public const ROUTE_NAMESPACE = 'menu-for-loyverse/v1';
	public const ROUTE           = '/webhook';

	/**
	 * Register REST route.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Ensure webhook secret exists.
	 *
	 * @return string
	 */
	public static function get_secret(): string {
		$secret = get_option( 'mfl_webhook_secret', '' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 32, false, false );
			update_option( 'mfl_webhook_secret', $secret, false );
		}
		return (string) $secret;
	}

	/**
	 * Public webhook URL including secret.
	 *
	 * @return string
	 */
	public static function get_endpoint_url(): string {
		return add_query_arg(
			'token',
			self::get_secret(),
			rest_url( self::ROUTE_NAMESPACE . self::ROUTE )
		);
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
			)
		);
	}

	/**
	 * Validate secret token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function permission_check( WP_REST_Request $request ) {
		$token = (string) $request->get_param( 'token' );
		if ( ! $token || ! hash_equals( self::get_secret(), $token ) ) {
			return new WP_Error( 'mfl_forbidden', __( 'Invalid webhook token.', 'menu-for-loyverse' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Handle webhook payload — trigger reconciliation sync.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Full sync keeps catalog consistent; cheap enough for restaurant catalogs.
		$result = MFL_Sync::run();
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'ok'    => false,
					'error' => $result->get_error_message(),
				),
				500
			);
		}
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'summary' => $result,
			),
			200
		);
	}

	/**
	 * Register items.update webhook with Loyverse.
	 *
	 * @return true|WP_Error
	 */
	public static function register() {
		$client = MFL_API_Client::from_settings();
		if ( is_wp_error( $client ) ) {
			MFL_Settings::update_status(
				array(
					'webhook_status' => 'error',
					'last_error'     => $client->get_error_message(),
				)
			);
			return $client;
		}

		$url    = self::get_endpoint_url();
		$status = MFL_Settings::get_status();
		$body   = array(
			'url'    => $url,
			'type'   => 'items.update',
			'status' => 'ENABLED',
		);
		if ( ! empty( $status['webhook_id'] ) ) {
			$body['id'] = $status['webhook_id'];
		}

		$result = $client->upsert_webhook( $body );
		if ( is_wp_error( $result ) ) {
			MFL_Settings::update_status(
				array(
					'webhook_status' => 'error',
					'last_error'     => $result->get_error_message(),
				)
			);
			return $result;
		}

		$webhook_id = isset( $result['id'] ) ? (string) $result['id'] : ( $status['webhook_id'] ?? '' );
		MFL_Settings::update_status(
			array(
				'webhook_status' => 'registered',
				'webhook_id'     => $webhook_id,
				'last_error'     => '',
				'last_message'   => __( 'Webhook registered.', 'menu-for-loyverse' ),
			)
		);

		return true;
	}
}
