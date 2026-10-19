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
	define( 'FBMSL_VERSION', '0.2.0' );
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
	/**
	 * Test double for WP_Error.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

		/**
		 * Message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Data.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array  $args Args.
	 * @param string $url  URL.
	 * @return string
	 */
	function add_query_arg( $args, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}

/**
 * In-memory options store for lock/job/checkpoint tests.
 *
 * @var array<string,mixed>
 */
$GLOBALS['fbmsl_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['fbmsl_test_options'] )
			? $GLOBALS['fbmsl_test_options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key        Key.
	 * @param mixed  $value      Value.
	 * @param bool   $autoload   Autoload.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['fbmsl_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * @param string $key        Key.
	 * @param mixed  $value      Value.
	 * @param string $deprecated Deprecated.
	 * @param bool   $autoload   Autoload.
	 * @return bool
	 */
	function add_option( $key, $value = '', $deprecated = '', $autoload = 'yes' ) {
		unset( $deprecated, $autoload );
		if ( array_key_exists( $key, $GLOBALS['fbmsl_test_options'] ) ) {
			return false;
		}
		$GLOBALS['fbmsl_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['fbmsl_test_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $tag   Tag.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		unset( $tag );
		$args = func_get_args();
		return $args[1];
	}
}

require_once dirname( __DIR__ ) . '/includes/class-api-client.php';
require_once dirname( __DIR__ ) . '/includes/class-sync-lock.php';
require_once dirname( __DIR__ ) . '/includes/class-sync.php';

register_shutdown_function(
	static function () {
		Brain\Monkey\tearDown();
	}
);
