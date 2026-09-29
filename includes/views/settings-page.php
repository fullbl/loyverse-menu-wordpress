<?php
/**
 * Settings page markup.
 *
 * @package FullBLMenuSyncLoyverse
 *
 * @var array  $settings
 * @var array  $status
 * @var array  $stores
 * @var string $token_display
 * @var string $webhook_url
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap fbmsl-settings">
	<h1><?php echo esc_html__( 'FullBL Menu Sync for Loyverse', 'fullbl-menu-sync-for-loyverse' ); ?></h1>

	<?php if ( ! empty( $_GET['fbmsl_notice'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$notice      = sanitize_key( wp_unslash( $_GET['fbmsl_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice_type = 'info';
		$notice_text = '';

		switch ( $notice ) {
			case 'sync_ok':
				$notice_type = 'success';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Sync completed.', 'fullbl-menu-sync-for-loyverse' );
				break;
			case 'sync_error':
				$notice_type = 'error';
				$notice_text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Sync failed: %s', 'fullbl-menu-sync-for-loyverse' ),
						(string) $status['last_error']
					)
					: __( 'Sync failed.', 'fullbl-menu-sync-for-loyverse' );
				break;
			case 'test_ok':
				$notice_type = 'success';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Connection successful.', 'fullbl-menu-sync-for-loyverse' );
				break;
			case 'test_error':
				$notice_type = 'error';
				$notice_text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Connection failed: %s', 'fullbl-menu-sync-for-loyverse' ),
						(string) $status['last_error']
					)
					: __( 'Connection failed.', 'fullbl-menu-sync-for-loyverse' );
				break;
			case 'webhook_ok':
				$notice_type = 'success';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Webhook registered.', 'fullbl-menu-sync-for-loyverse' );
				break;
			case 'webhook_error':
				$notice_type = 'error';
				$notice_text = $status['last_error']
					? sprintf(
						/* translators: %s: error message */
						__( 'Webhook registration failed: %s', 'fullbl-menu-sync-for-loyverse' ),
						(string) $status['last_error']
					)
					: __( 'Webhook registration failed.', 'fullbl-menu-sync-for-loyverse' );
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

	<div class="fbmsl-status-card">
		<h2><?php echo esc_html__( 'Status', 'fullbl-menu-sync-for-loyverse' ); ?></h2>
		<ul>
			<li><strong><?php echo esc_html__( 'Connection:', 'fullbl-menu-sync-for-loyverse' ); ?></strong> <?php echo esc_html( (string) $status['connection'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Last sync:', 'fullbl-menu-sync-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_sync'] ? (string) $status['last_sync'] : '—' ); ?></li>
			<li><strong><?php echo esc_html__( 'Webhook:', 'fullbl-menu-sync-for-loyverse' ); ?></strong> <?php echo esc_html( (string) $status['webhook_status'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Last error:', 'fullbl-menu-sync-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_error'] ? (string) $status['last_error'] : '—' ); ?></li>
			<li><strong><?php echo esc_html__( 'Message:', 'fullbl-menu-sync-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_message'] ? (string) $status['last_message'] : '—' ); ?></li>
		</ul>

		<p class="fbmsl-actions">
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fbmsl_sync_now' ), 'fbmsl_sync_now' ) ); ?>">
				<?php echo esc_html__( 'Sync now', 'fullbl-menu-sync-for-loyverse' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fbmsl_test_connection' ), 'fbmsl_test_connection' ) ); ?>">
				<?php echo esc_html__( 'Test connection', 'fullbl-menu-sync-for-loyverse' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fbmsl_register_webhook' ), 'fbmsl_register_webhook' ) ); ?>">
				<?php echo esc_html__( 'Register webhook', 'fullbl-menu-sync-for-loyverse' ); ?>
			</a>
		</p>
		<p class="description">
			<?php echo esc_html__( 'Webhook URL (needs a public HTTPS URL; use a tunnel for local testing):', 'fullbl-menu-sync-for-loyverse' ); ?>
			<code><?php echo esc_html( $webhook_url ); ?></code>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'fbmsl_settings_group' ); ?>

		<h2><?php echo esc_html__( 'Connection', 'fullbl-menu-sync-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fbmsl_api_token"><?php echo esc_html__( 'API token', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="fbmsl_api_token" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[api_token]" value="<?php echo esc_attr( $token_display ); ?>" autocomplete="off" />
					<p class="description"><?php echo esc_html__( 'Personal access token from Loyverse Back Office → Integrations → Access Tokens. Leave blank (or ********) to keep the current token.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
				</td>
			</tr>
			<?php if ( ! empty( $settings['api_token'] ) ) : ?>
			<tr>
				<th scope="row"><label for="fbmsl_store_id"><?php echo esc_html__( 'Store', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<?php if ( $stores ) : ?>
						<select id="fbmsl_store_id" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[store_id]">
							<option value=""><?php echo esc_html__( '— Select store —', 'fullbl-menu-sync-for-loyverse' ); ?></option>
							<?php foreach ( $stores as $store ) : ?>
								<option value="<?php echo esc_attr( (string) ( $store['id'] ?? '' ) ); ?>" <?php selected( $settings['store_id'], (string) ( $store['id'] ?? '' ) ); ?>>
									<?php echo esc_html( (string) ( $store['name'] ?? $store['id'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" class="regular-text" id="fbmsl_store_id" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[store_id]" value="<?php echo esc_attr( $settings['store_id'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Save the token and run Test connection to load stores, or paste the store ID.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="fbmsl_cron_interval"><?php echo esc_html__( 'Automatic sync', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<select id="fbmsl_cron_interval" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[cron_interval]">
						<option value="hourly" <?php selected( $settings['cron_interval'], 'hourly' ); ?>><?php echo esc_html__( 'Hourly', 'fullbl-menu-sync-for-loyverse' ); ?></option>
						<option value="twicedaily" <?php selected( $settings['cron_interval'], 'twicedaily' ); ?>><?php echo esc_html__( 'Twice daily', 'fullbl-menu-sync-for-loyverse' ); ?></option>
						<option value="daily" <?php selected( $settings['cron_interval'], 'daily' ); ?>><?php echo esc_html__( 'Daily', 'fullbl-menu-sync-for-loyverse' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'URLs', 'fullbl-menu-sync-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fbmsl_permalink_base"><?php echo esc_html__( 'Permalink base', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="fbmsl_permalink_base" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[permalink_base]" value="<?php echo esc_attr( $settings['permalink_base'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'Default: menu → /menu/, /menu/antipasti/, /menu/margherita/', 'fullbl-menu-sync-for-loyverse' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Archives', 'fullbl-menu-sync-for-loyverse' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[enable_singles]" value="1" <?php checked( $settings['enable_singles'] ); ?> /> <?php echo esc_html__( 'Enable single item pages', 'fullbl-menu-sync-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[enable_category_archives]" value="1" <?php checked( $settings['enable_category_archives'] ); ?> /> <?php echo esc_html__( 'Enable category archive pages', 'fullbl-menu-sync-for-loyverse' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Display', 'fullbl-menu-sync-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fbmsl_layout"><?php echo esc_html__( 'Layout', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<select id="fbmsl_layout" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[layout]">
						<option value="grid" <?php selected( $settings['layout'], 'grid' ); ?>><?php echo esc_html__( 'Grid', 'fullbl-menu-sync-for-loyverse' ); ?></option>
						<option value="list" <?php selected( $settings['layout'], 'list' ); ?>><?php echo esc_html__( 'List', 'fullbl-menu-sync-for-loyverse' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fbmsl_columns"><?php echo esc_html__( 'Columns', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<select id="fbmsl_columns" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[columns]">
						<?php foreach ( range( 1, 6 ) as $col ) : ?>
							<option value="<?php echo esc_attr( (string) $col ); ?>" <?php selected( (int) $settings['columns'], $col ); ?>><?php echo esc_html( (string) $col ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Show', 'fullbl-menu-sync-for-loyverse' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[show_images]" value="1" <?php checked( $settings['show_images'] ); ?> /> <?php echo esc_html__( 'Images', 'fullbl-menu-sync-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[show_descriptions]" value="1" <?php checked( $settings['show_descriptions'] ); ?> /> <?php echo esc_html__( 'Descriptions', 'fullbl-menu-sync-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[show_prices]" value="1" <?php checked( $settings['show_prices'] ); ?> /> <?php echo esc_html__( 'Prices', 'fullbl-menu-sync-for-loyverse' ); ?></label><br />
					<label><input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[show_variants]" value="1" <?php checked( $settings['show_variants'] ); ?> /> <?php echo esc_html__( 'Variants', 'fullbl-menu-sync-for-loyverse' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fbmsl_accent_color"><?php echo esc_html__( 'Accent color', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<input type="text" class="fbmsl-color-picker" id="fbmsl_accent_color" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" data-default-color="#1a1a1a" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fbmsl_gap"><?php echo esc_html__( 'Gap', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<input type="text" id="fbmsl_gap" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[gap]" value="<?php echo esc_attr( $settings['gap'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'Number plus unit: px, rem, em, or % (for example 1.5rem).', 'fullbl-menu-sync-for-loyverse' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'fullbl-menu-sync-for-loyverse' ) ); ?>
	</form>

	<p class="description">
		<?php
		echo esc_html__( 'Shortcode:', 'fullbl-menu-sync-for-loyverse' );
		echo ' ';
		?>
		<code>[fbmsl_menu]</code>
		<code>[fbmsl_menu category="antipasti" layout="list" columns="1"]</code>
	</p>
</div>
