<?php
/**
 * Settings page markup.
 *
 * @package LoyverseMenu
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
	<h1><?php echo esc_html__( 'Loyverse Menu', 'loyverse-menu' ); ?></h1>

	<?php if ( ! empty( $_GET['lm_notice'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$notice = sanitize_key( wp_unslash( $_GET['lm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type   = 'info';
		$text   = '';

		switch ( $notice ) {
			case 'sync_ok':
				$type = 'success';
				$text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Sync completed.', 'loyverse-menu' );
				break;
			case 'sync_error':
				$type = 'error';
				$text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Sync failed: %s', 'loyverse-menu' ),
						(string) $status['last_error']
					)
					: __( 'Sync failed.', 'loyverse-menu' );
				break;
			case 'test_ok':
				$type = 'success';
				$text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Connection successful.', 'loyverse-menu' );
				break;
			case 'test_error':
				$type = 'error';
				$text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Connection failed: %s', 'loyverse-menu' ),
						(string) $status['last_error']
					)
					: __( 'Connection failed.', 'loyverse-menu' );
				break;
			case 'webhook_ok':
				$type = 'success';
				$text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Webhook registered.', 'loyverse-menu' );
				break;
			case 'webhook_error':
				$type = 'error';
				$text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Webhook registration failed: %s', 'loyverse-menu' ),
						(string) $status['last_error']
					)
					: __( 'Webhook registration failed.', 'loyverse-menu' );
				break;
		}

		if ( $text ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( $text )
			);
		}
		?>
	<?php endif; ?>

	<div class="lm-status-card">
		<h2><?php echo esc_html__( 'Status', 'loyverse-menu' ); ?></h2>
		<ul>
			<li><strong><?php echo esc_html__( 'Connection:', 'loyverse-menu' ); ?></strong> <?php echo esc_html( (string) $status['connection'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Last sync:', 'loyverse-menu' ); ?></strong> <?php echo esc_html( $status['last_sync'] ? (string) $status['last_sync'] : '—' ); ?></li>
			<li><strong><?php echo esc_html__( 'Webhook:', 'loyverse-menu' ); ?></strong> <?php echo esc_html( (string) $status['webhook_status'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Last error:', 'loyverse-menu' ); ?></strong> <?php echo esc_html( $status['last_error'] ? (string) $status['last_error'] : '—' ); ?></li>
			<li><strong><?php echo esc_html__( 'Message:', 'loyverse-menu' ); ?></strong> <?php echo esc_html( $status['last_message'] ? (string) $status['last_message'] : '—' ); ?></li>
		</ul>

		<p class="lm-actions">
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lm_sync_now' ), 'lm_sync_now' ) ); ?>">
				<?php echo esc_html__( 'Sync now', 'loyverse-menu' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lm_test_connection' ), 'lm_test_connection' ) ); ?>">
				<?php echo esc_html__( 'Test connection', 'loyverse-menu' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lm_register_webhook' ), 'lm_register_webhook' ) ); ?>">
				<?php echo esc_html__( 'Register webhook', 'loyverse-menu' ); ?>
			</a>
		</p>
		<p class="description">
			<?php echo esc_html__( 'Webhook URL (needs a public HTTPS URL; use a tunnel for local testing):', 'loyverse-menu' ); ?>
			<code><?php echo esc_html( $webhook_url ); ?></code>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'lm_settings_group' ); ?>

		<h2><?php echo esc_html__( 'Connection', 'loyverse-menu' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lm_api_token"><?php echo esc_html__( 'API token', 'loyverse-menu' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="lm_api_token" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[api_token]" value="<?php echo esc_attr( $token_display ); ?>" autocomplete="off" />
					<p class="description"><?php echo esc_html__( 'Personal access token from Loyverse Back Office → Integrations → Access Tokens. Leave blank (or ********) to keep the current token.', 'loyverse-menu' ); ?></p>
				</td>
			</tr>
			<?php if ( ! empty( $settings['api_token'] ) ) : ?>
			<tr>
				<th scope="row"><label for="lm_store_id"><?php echo esc_html__( 'Store', 'loyverse-menu' ); ?></label></th>
				<td>
					<?php if ( $stores ) : ?>
						<select id="lm_store_id" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[store_id]">
							<option value=""><?php echo esc_html__( '— Select store —', 'loyverse-menu' ); ?></option>
							<?php foreach ( $stores as $store ) : ?>
								<option value="<?php echo esc_attr( (string) ( $store['id'] ?? '' ) ); ?>" <?php selected( $settings['store_id'], (string) ( $store['id'] ?? '' ) ); ?>>
									<?php echo esc_html( (string) ( $store['name'] ?? $store['id'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" class="regular-text" id="lm_store_id" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[store_id]" value="<?php echo esc_attr( $settings['store_id'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Save the token and run Test connection to load stores, or paste the store ID.', 'loyverse-menu' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="lm_cron_interval"><?php echo esc_html__( 'Automatic sync', 'loyverse-menu' ); ?></label></th>
				<td>
					<select id="lm_cron_interval" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[cron_interval]">
						<option value="hourly" <?php selected( $settings['cron_interval'], 'hourly' ); ?>><?php echo esc_html__( 'Hourly', 'loyverse-menu' ); ?></option>
						<option value="twicedaily" <?php selected( $settings['cron_interval'], 'twicedaily' ); ?>><?php echo esc_html__( 'Twice daily', 'loyverse-menu' ); ?></option>
						<option value="daily" <?php selected( $settings['cron_interval'], 'daily' ); ?>><?php echo esc_html__( 'Daily', 'loyverse-menu' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'URLs', 'loyverse-menu' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lm_permalink_base"><?php echo esc_html__( 'Permalink base', 'loyverse-menu' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="lm_permalink_base" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[permalink_base]" value="<?php echo esc_attr( $settings['permalink_base'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'Default: menu → /menu/, /menu/antipasti/, /menu/margherita/', 'loyverse-menu' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Archives', 'loyverse-menu' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[enable_singles]" value="1" <?php checked( $settings['enable_singles'] ); ?> /> <?php echo esc_html__( 'Enable single item pages', 'loyverse-menu' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[enable_category_archives]" value="1" <?php checked( $settings['enable_category_archives'] ); ?> /> <?php echo esc_html__( 'Enable category archive pages', 'loyverse-menu' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Display', 'loyverse-menu' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lm_layout"><?php echo esc_html__( 'Layout', 'loyverse-menu' ); ?></label></th>
				<td>
					<select id="lm_layout" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[layout]">
						<option value="grid" <?php selected( $settings['layout'], 'grid' ); ?>><?php echo esc_html__( 'Grid', 'loyverse-menu' ); ?></option>
						<option value="list" <?php selected( $settings['layout'], 'list' ); ?>><?php echo esc_html__( 'List', 'loyverse-menu' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_columns"><?php echo esc_html__( 'Columns', 'loyverse-menu' ); ?></label></th>
				<td>
					<select id="lm_columns" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[columns]">
						<?php foreach ( array( 1, 2, 3 ) as $col ) : ?>
							<option value="<?php echo esc_attr( (string) $col ); ?>" <?php selected( (int) $settings['columns'], $col ); ?>><?php echo esc_html( (string) $col ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Show', 'loyverse-menu' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[show_images]" value="1" <?php checked( $settings['show_images'] ); ?> /> <?php echo esc_html__( 'Images', 'loyverse-menu' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[show_descriptions]" value="1" <?php checked( $settings['show_descriptions'] ); ?> /> <?php echo esc_html__( 'Descriptions', 'loyverse-menu' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[show_prices]" value="1" <?php checked( $settings['show_prices'] ); ?> /> <?php echo esc_html__( 'Prices', 'loyverse-menu' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[show_variants]" value="1" <?php checked( $settings['show_variants'] ); ?> /> <?php echo esc_html__( 'Variants', 'loyverse-menu' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_accent_color"><?php echo esc_html__( 'Accent color', 'loyverse-menu' ); ?></label></th>
				<td>
					<input type="text" class="lm-color-picker" id="lm_accent_color" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" data-default-color="#1a1a1a" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_gap"><?php echo esc_html__( 'Gap', 'loyverse-menu' ); ?></label></th>
				<td><input type="text" id="lm_gap" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[gap]" value="<?php echo esc_attr( $settings['gap'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lm_custom_css"><?php echo esc_html__( 'Custom CSS', 'loyverse-menu' ); ?></label></th>
				<td><textarea class="large-text code" rows="6" id="lm_custom_css" name="<?php echo esc_attr( LM_Settings::OPTION_KEY ); ?>[custom_css]"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'loyverse-menu' ) ); ?>
	</form>

	<p class="description">
		<?php
		echo esc_html__( 'Shortcode:', 'loyverse-menu' );
		echo ' ';
		?>
		<code>[loyverse_menu]</code>
		<code>[loyverse_menu category="antipasti" layout="list" columns="1"]</code>
	</p>
</div>
