<?php
/**
 * Settings sanitize tests.
 *
 * @package LoyverseMenu
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-settings.php';

/**
 * Tests LM_Settings::sanitize.
 */
class SettingsSanitizeTest extends TestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! function_exists( 'sanitize_text_field' ) ) {
			/**
			 * @param string $str Input.
			 * @return string
			 */
			function sanitize_text_field( $str ) {
				return trim( strip_tags( (string) $str ) );
			}
		}
		if ( ! function_exists( 'sanitize_title' ) ) {
			/**
			 * @param string $title Title.
			 * @return string
			 */
			function sanitize_title( $title ) {
				return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $title ) ) );
			}
		}
		if ( ! function_exists( 'sanitize_hex_color' ) ) {
			/**
			 * @param string $color Color.
			 * @return string|null
			 */
			function sanitize_hex_color( $color ) {
				return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null;
			}
		}
		if ( ! function_exists( 'wp_strip_all_tags' ) ) {
			/**
			 * @param string $str Input.
			 * @return string
			 */
			function wp_strip_all_tags( $str ) {
				return strip_tags( (string) $str );
			}
		}
		if ( ! function_exists( 'get_option' ) ) {
			/**
			 * @param string $key     Option key.
			 * @param mixed  $default Default.
			 * @return mixed
			 */
			function get_option( $key, $default = false ) {
				return $default;
			}
		}
	}

	public function test_sanitize_clamps_columns_and_keeps_layout(): void {
		$result = LM_Settings::sanitize(
			array(
				'api_token' => 'abc',
				'columns'   => 9,
				'layout'    => 'grid',
			)
		);

		$this->assertSame( 3, $result['columns'] );
		$this->assertSame( 'grid', $result['layout'] );
		$this->assertSame( 'abc', $result['api_token'] );
	}

	public function test_sanitize_keeps_token_when_masked(): void {
		$result = LM_Settings::sanitize(
			array(
				'api_token' => '********',
				'layout'    => 'list',
			)
		);

		$this->assertSame( '', $result['api_token'] );
		$this->assertSame( 'list', $result['layout'] );
	}
}
