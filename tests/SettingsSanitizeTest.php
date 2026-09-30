<?php
/**
 * Settings sanitize tests.
 *
 * @package FullBLMenuSyncLoyverse
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-settings.php';

/**
 * Tests FBMSL_Settings::sanitize.
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
		$result = FBMSL_Settings::sanitize(
			array(
				'api_token' => 'abc',
				'columns'   => 9,
				'layout'    => 'grid',
			)
		);

		$this->assertSame( 6, $result['columns'] );
		$this->assertSame( 'grid', $result['layout'] );
		$this->assertSame( 'abc', $result['api_token'] );
		$this->assertArrayNotHasKey( 'custom_css', $result );
	}

	public function test_sanitize_keeps_token_when_masked(): void {
		$result = FBMSL_Settings::sanitize(
			array(
				'api_token' => '********',
				'layout'    => 'list',
			)
		);

		$this->assertSame( '', $result['api_token'] );
		$this->assertSame( 'list', $result['layout'] );
	}

	public function test_sanitize_gap_allows_whitelisted_units_only(): void {
		$this->assertSame( '1.5rem', FBMSL_Settings::sanitize_gap( '1.5rem' ) );
		$this->assertSame( '12px', FBMSL_Settings::sanitize_gap( '12px' ) );
		$this->assertSame( '2em', FBMSL_Settings::sanitize_gap( '2em' ) );
		$this->assertSame( '10%', FBMSL_Settings::sanitize_gap( '10%' ) );
		$this->assertSame( '1.5rem', FBMSL_Settings::sanitize_gap( '1.5vw; color:red' ) );
		$this->assertSame( '1.5rem', FBMSL_Settings::sanitize_gap( 'expression(alert(1))' ) );
	}

	public function test_sanitize_accent_color_falls_back(): void {
		$this->assertSame( '#ff00aa', FBMSL_Settings::sanitize_accent_color( '#ff00aa' ) );
		$this->assertSame( '#1a1a1a', FBMSL_Settings::sanitize_accent_color( 'red' ) );
		$this->assertSame( '#1a1a1a', FBMSL_Settings::sanitize_accent_color( '#xyz' ) );
	}

	public function test_sanitize_currency_defaults(): void {
		$result = FBMSL_Settings::sanitize(
			array(
				'api_token'         => 'tok',
				'currency_symbol'   => '€',
				'currency_position' => 'after',
			)
		);

		$this->assertSame( '€', $result['currency_symbol'] );
		$this->assertSame( 'after', $result['currency_position'] );
		$this->assertSame( 'before', FBMSL_Settings::sanitize_currency_position( 'nope' ) );
	}
}
