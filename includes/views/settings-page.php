<?php
/**
 * Settings page markup.
 *
 * @package MenuForLoyverse
 *
 * @var array  $settings
 * @var array  $status
 * @var array  $stores
 * @var string $token_display
 * @var string $webhook_url
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lm-settings">
	<h1><?php echo esc_html__( 'Menu for Loyverse', 'menu-for-loyverse' ); ?></h1>

	<?php if ( ! empty( $_GET['mfl_notice'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$notice      = sanitize_key( wp_unslash( $_GET['mfl_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice_type = 'info';
		$notice_text = '';

		switch ( $notice ) {
			case 'sync_ok':
				$notice_type = 'success';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Sync completed.', 'menu-for-loyverse' );
				break;
			case 'sync_error':
				$notice_type = 'error';
				$notice_text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Sync failed: %s', 'menu-for-loyverse' ),
						(string) $status['last_error']
					)
					: __( 'Sync failed.', 'menu-for-loyverse' );
				break;
			case 'test_ok':
				$notice_type = 'success';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Connection successful.', 'menu-for-loyverse' );
				break;
			case 'test_error':
				$notice_type = 'error';
				$notice_text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Connection failed: %s', 'menu-for-loyverse' ),
						(string) $status['last_error']
					)
					: __( 'Connection failed.', 'menu-for-loyverse' );
				break;
			case 'webhook_ok':
				$notice_type = 'success';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Webhook registered.', 'menu-for-loyverse' );
				break;
			case 'webhook_error':
				$notice_type = 'error';
				$notice_text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Webhook registration failed: %s', 'menu-for-loyverse' ),
						(string) $status['last_error']
					)
					: __( 'Webhook registration failed.', 'menu-for-loyverse' );
				break;
		}

		if ( $notice_text ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice_type ),
				esc_html( $notice_text )
			);
		}
		?>
	<?php endif; ?>

	<div class="lm-status-card">
		<h2><?php echo esc_html__( 'Status', 'menu-for-loyverse' ); ?></h2>
		<ul>
			<li><strong><?php echo esc_html__( 'Connection:', 'menu-for-loyverse' ); ?></strong> <?php echo esc_html( (string) $status['connection'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Last sync:', 'menu-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_sync'] ? (string) $status['last_sync'] : '—' ); ?></li>
			<li><strong><?php echo esc_html__( 'Webhook:', 'menu-for-loyverse' ); ?></strong> <?php echo esc_html( (string) $status['webhook_status'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Last error:', 'menu-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_error'] ? (string) $status['last_error'] : '—' ); ?></li>
			<li><strong><?php echo esc_html__( 'Message:', 'menu-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_message'] ? (string) $status['last_message'] : '—' ); ?></li>
		</ul>

		<p class="lm-actions">
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lm_sync_now' ), 'mfl_sync_now' ) ); ?>">
				<?php echo esc_html__( 'Sync now', 'menu-for-loyverse' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lm_test_connection' ), 'mfl_test_connection' ) ); ?>">
				<?php echo esc_html__( 'Test connection', 'menu-for-loyverse' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lm_register_webhook' ), 'mfl_register_webhook' ) ); ?>">
				<?php echo esc_html__( 'Register webhook', 'menu-for-loyverse' ); ?>
			</a>
		</p>
		<p class="description">
			<?php echo esc_html__( 'Webhook URL (needs a public HTTPS URL; use a tunnel for local testing):', 'menu-for-loyverse' ); ?>
			<code><?php echo esc_html( $webhook_url ); ?></code>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'mfl_settings_group' ); ?>

		<h2><?php echo esc_html__( 'Connection', 'menu-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lm_api_token"><?php echo esc_html__( 'API token', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="lm_api_token" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[api_token]" value="<?php echo esc_attr( $token_display ); ?>" autocomplete="off" />
					<p class="description"><?php echo esc_html__( 'Personal access token from Loyverse Back Office → Integrations → Access Tokens. Leave blank (or ********) to keep the current token.', 'menu-for-loyverse' ); ?></p>
				</td>
			</tr>
			<?php if ( ! empty( $settings['api_token'] ) ) : ?>
			<tr>
				<th scope="row"><label for="lm_store_id"><?php echo esc_html__( 'Store', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<?php if ( $stores ) : ?>
						<select id="lm_store_id" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[store_id]">
							<option value=""><?php echo esc_html__( '— Select store —', 'menu-for-loyverse' ); ?></option>
							<?php foreach ( $stores as $store ) : ?>
								<option value="<?php echo esc_attr( (string) ( $store['id'] ?? '' ) ); ?>" <?php selected( $settings['store_id'], (string) ( $store['id'] ?? '' ) ); ?>>
									<?php echo esc_html( (string) ( $store['name'] ?? $store['id'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" class="regular-text" id="lm_store_id" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[store_id]" value="<?php echo esc_attr( $settings['store_id'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Save the token and run Test connection to load stores, or paste the store ID.', 'menu-for-loyverse' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="lm_cron_interval"><?php echo esc_html__( 'Automatic sync', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<select id="lm_cron_interval" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[cron_interval]">
						<option value="hourly" <?php selected( $settings['cron_interval'], 'hourly' ); ?>><?php echo esc_html__( 'Hourly', 'menu-for-loyverse' ); ?></option>
						<option value="twicedaily" <?php selected( $settings['cron_interval'], 'twicedaily' ); ?>><?php echo esc_html__( 'Twice daily', 'menu-for-loyverse' ); ?></option>
						<option value="daily" <?php selected( $settings['cron_interval'], 'daily' ); ?>><?php echo esc_html__( 'Daily', 'menu-for-loyverse' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'URLs', 'menu-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lm_permalink_base"><?php echo esc_html__( 'Permalink base', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="lm_permalink_base" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[permalink_base]" value="<?php echo esc_attr( $settings['permalink_base'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'Default: menu → /menu/, /menu/antipasti/, /menu/margherita/', 'menu-for-loyverse' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Archives', 'menu-for-loyverse' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[enable_singles]" value="1" <?php checked( $settings['enable_singles'] ); ?> /> <?php echo esc_html__( 'Enable single item pages', 'menu-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[enable_category_archives]" value="1" <?php checked( $settings['enable_category_archives'] ); ?> /> <?php echo esc_html__( 'Enable category archive pages', 'menu-for-loyverse' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Display', 'menu-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lm_layout"><?php echo esc_html__( 'Layout', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<select id="lm_layout" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[layout]">
						<option value="grid" <?php selected( $settings['layout'], 'grid' ); ?>><?php echo esc_html__( 'Grid', 'menu-for-loyverse' ); ?></option>
						<option value="list" <?php selected( $settings['layout'], 'list' ); ?>><?php echo esc_html__( 'List', 'menu-for-loyverse' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_columns"><?php echo esc_html__( 'Columns', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<select id="lm_columns" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[columns]">
						<?php foreach ( array( 1, 2, 3 ) as $col ) : ?>
							<option value="<?php echo esc_attr( (string) $col ); ?>" <?php selected( (int) $settings['columns'], $col ); ?>><?php echo esc_html( (string) $col ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Show', 'menu-for-loyverse' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[show_images]" value="1" <?php checked( $settings['show_images'] ); ?> /> <?php echo esc_html__( 'Images', 'menu-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[show_descriptions]" value="1" <?php checked( $settings['show_descriptions'] ); ?> /> <?php echo esc_html__( 'Descriptions', 'menu-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[show_prices]" value="1" <?php checked( $settings['show_prices'] ); ?> /> <?php echo esc_html__( 'Prices', 'menu-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[show_variants]" value="1" <?php checked( $settings['show_variants'] ); ?> /> <?php echo esc_html__( 'Variants', 'menu-for-loyverse' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_accent_color"><?php echo esc_html__( 'Accent color', 'menu-for-loyverse' ); ?></label></th>
				<td>
					<input type="text" class="lm-color-picker" id="lm_accent_color" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" data-default-color="#1a1a1a" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_gap"><?php echo esc_html__( 'Gap', 'menu-for-loyverse' ); ?></label></th>
				<td><input type="text" id="lm_gap" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[gap]" value="<?php echo esc_attr( $settings['gap'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_custom_css"><?php echo esc_html__( 'Custom CSS', 'menu-for-loyverse' ); ?></label></th>
				<td><textarea class="large-text code" rows="6" id="lm_custom_css" name="<?php echo esc_attr( MFL_Settings::OPTION_KEY ); ?>[custom_css]"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'menu-for-loyverse' ) ); ?>
	</form>

	<p class="description">
		<?php
		echo esc_html__( 'Shortcode:', 'menu-for-loyverse' );
		echo ' ';
		?>
		<code>[loyverse_menu]</code>
		<code>[loyverse_menu category="antipasti" layout="list" columns="1"]</code>
	</p>
</div>
