<?php
/**
 * PHPUnit bootstrap (Brain Monkey unit tests).
 *
 * @package FullBLMenuSyncLoyverse
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

Brain\Monkey\setUp();

// Stub WordPress helpers used at load time.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'FBMSL_VERSION' ) ) {
	define( 'FBMSL_VERSION', '0.1.0' );
}
if ( ! defined( 'FBMSL_PLUGIN_FILE' ) ) {
	define( 'FBMSL_PLUGIN_FILE', dirname( __DIR__ ) . '/fullbl-menu-sync-for-loyverse.php' );
}
if ( ! defined( 'FBMSL_PLUGIN_DIR' ) ) {
	define( 'FBMSL_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'FBMSL_PLUGIN_URL' ) ) {
	define( 'FBMSL_PLUGIN_URL', 'http://example.test/wp-content/plugins/fullbl-menu-sync-for-loyverse/' );
}
if ( ! defined( 'FBMSL_PLUGIN_BASENAME' ) ) {
	define( 'FBMSL_PLUGIN_BASENAME', 'fullbl-menu-sync-for-loyverse/fullbl-menu-sync-for-loyverse.php' );
}

/**
 * Minimal WP_Error for unit tests.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-api-client.php';
require_once dirname( __DIR__ ) . '/includes/class-sync.php';

register_shutdown_function(
	static function () {
		Brain\Monkey\tearDown();
	}
);
