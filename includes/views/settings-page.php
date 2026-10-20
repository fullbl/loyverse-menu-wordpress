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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- view vars from include scope.
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
			case 'sync_progress':
				$notice_type = 'info';
				$notice_text = $status['last_message']
					? (string) $status['last_message']
					: __( 'Sync started in the background. This page shows live progress.', 'fullbl-menu-sync-for-loyverse' );
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
			<li><strong><?php echo esc_html__( 'Last sync:', 'fullbl-menu-sync-for-loyverse' ); ?></strong> <?php echo esc_html( $status['last_sync'] ? FBMSL_Templates::format_datetime( (string) $status['last_sync'] ) : '—' ); ?></li>
			<li>
				<strong><?php echo esc_html__( 'Sync progress:', 'fullbl-menu-sync-for-loyverse' ); ?></strong>
				<?php
				if ( ! empty( $status['sync_in_progress'] ) ) {
					printf(
						/* translators: 1: phase name, 2: items processed */
						esc_html__( 'In progress — phase %1$s, %2$d items done', 'fullbl-menu-sync-for-loyverse' ),
						esc_html( (string) ( $status['sync_phase'] ? $status['sync_phase'] : '—' ) ),
						(int) $status['sync_items_done']
					);
				} else {
					echo esc_html__( 'Idle', 'fullbl-menu-sync-for-loyverse' );
				}
				?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Resync pending:', 'fullbl-menu-sync-for-loyverse' ); ?></strong>
				<?php
				$pending = ! empty( $status['sync_pending'] ) || FBMSL_Sync_Lock::is_pending();
				echo esc_html( $pending ? __( 'Yes', 'fullbl-menu-sync-for-loyverse' ) : __( 'No', 'fullbl-menu-sync-for-loyverse' ) );
				?>
			</li>
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
			<tr>
				<th scope="row"><label for="fbmsl_store_id"><?php echo esc_html__( 'Store', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<?php if ( empty( $settings['api_token'] ) ) : ?>
						<input type="hidden" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[store_id]" value="<?php echo esc_attr( (string) $settings['store_id'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Save an API token first, then Test connection to choose a store.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					<?php elseif ( $stores ) : ?>
						<select id="fbmsl_store_id" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[store_id]">
							<option value=""><?php echo esc_html__( '— Select store —', 'fullbl-menu-sync-for-loyverse' ); ?></option>
							<?php foreach ( $stores as $store ) : ?>
								<option value="<?php echo esc_attr( (string) ( $store['id'] ?? '' ) ); ?>" <?php selected( (string) $settings['store_id'], (string) ( $store['id'] ?? '' ) ); ?>>
									<?php echo esc_html( (string) ( $store['name'] ?? $store['id'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html__( 'Required for inventory/availability. Saved with the settings form.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					<?php else : ?>
						<input type="text" class="regular-text" id="fbmsl_store_id" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[store_id]" value="<?php echo esc_attr( (string) $settings['store_id'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Run Test connection to load stores, or paste the store ID.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
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
			<tr>
				<th scope="row"><label for="fbmsl_removed_items_action"><?php echo esc_html__( 'Items removed upstream', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<select id="fbmsl_removed_items_action" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[removed_items_action]">
						<option value="draft" <?php selected( $settings['removed_items_action'] ?? 'draft', 'draft' ); ?>><?php echo esc_html__( 'Set to draft (default)', 'fullbl-menu-sync-for-loyverse' ); ?></option>
						<option value="trash" <?php selected( $settings['removed_items_action'] ?? 'draft', 'trash' ); ?>><?php echo esc_html__( 'Move to trash', 'fullbl-menu-sync-for-loyverse' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'Applied only after a complete sync pass. Never matches items by title — only by Loyverse ID.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[delete_imported_image_on_remove]" value="1" <?php checked( ! empty( $settings['delete_imported_image_on_remove'] ) ); ?> />
						<?php echo esc_html__( 'When trashing, delete the plugin-imported featured image (never deletes images you attached manually)', 'fullbl-menu-sync-for-loyverse' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php
		$items_url_on = ! empty( $settings['enable_singles'] ) || ! empty( $settings['enable_items_archive'] );
		?>
		<h2><?php echo esc_html__( 'URLs', 'fullbl-menu-sync-for-loyverse' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Menu items', 'fullbl-menu-sync-for-loyverse' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="fbmsl_enable_singles" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[enable_singles]" value="1" <?php checked( $settings['enable_singles'] ); ?> />
						<?php echo esc_html__( 'Enable single item pages', 'fullbl-menu-sync-for-loyverse' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" id="fbmsl_enable_items_archive" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[enable_items_archive]" value="1" <?php checked( ! empty( $settings['enable_items_archive'] ) ); ?> />
						<?php echo esc_html__( 'Enable items archive', 'fullbl-menu-sync-for-loyverse' ); ?>
					</label>
					<div id="fbmsl_items_permalink_wrap" class="fbmsl-conditional<?php echo $items_url_on ? '' : ' fbmsl-is-hidden'; ?>">
						<label for="fbmsl_items_base" class="fbmsl-inline-label"><?php echo esc_html__( 'Permalink base', 'fullbl-menu-sync-for-loyverse' ); ?></label>
						<input type="text" class="regular-text" id="fbmsl_items_base" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[items_base]" value="<?php echo esc_attr( $settings['items_base'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Shared by archive and singles. Example: menu → /menu/ and /menu/pizza-margherita/', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Category archives', 'fullbl-menu-sync-for-loyverse' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="fbmsl_enable_category_archives" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[enable_category_archives]" value="1" <?php checked( $settings['enable_category_archives'] ); ?> />
						<?php echo esc_html__( 'Enable category archive pages', 'fullbl-menu-sync-for-loyverse' ); ?>
					</label>
					<div class="fbmsl-conditional<?php echo empty( $settings['enable_category_archives'] ) ? ' fbmsl-is-hidden' : ''; ?>" data-fbmsl-show-when="fbmsl_enable_category_archives">
						<label for="fbmsl_category_base" class="fbmsl-inline-label"><?php echo esc_html__( 'Permalink base', 'fullbl-menu-sync-for-loyverse' ); ?></label>
						<input type="text" class="regular-text" id="fbmsl_category_base" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[category_base]" value="<?php echo esc_attr( $settings['category_base'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Example: menu-category → /menu-category/antipasti/. Must differ from the items base.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
					</div>
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
			<tr class="fbmsl-columns-row<?php echo ( 'list' === $settings['layout'] ) ? ' fbmsl-is-hidden' : ''; ?>">
				<th scope="row"><label for="fbmsl_columns"><?php echo esc_html__( 'Columns', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<select id="fbmsl_columns" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[columns]">
						<?php foreach ( range( 1, 6 ) as $col ) : ?>
							<option value="<?php echo esc_attr( (string) $col ); ?>" <?php selected( (int) $settings['columns'], $col ); ?>><?php echo esc_html( (string) $col ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php echo esc_html__( 'Used only with the grid layout.', 'fullbl-menu-sync-for-loyverse' ); ?></p>
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
			<tr>
				<th scope="row"><label for="fbmsl_currency_symbol"><?php echo esc_html__( 'Currency symbol', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<input type="text" class="small-text" id="fbmsl_currency_symbol" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[currency_symbol]" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" maxlength="8" />
					<p class="description"><?php echo esc_html__( 'Shown next to prices (for example €, $, or leave empty for number only).', 'fullbl-menu-sync-for-loyverse' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fbmsl_currency_position"><?php echo esc_html__( 'Currency position', 'fullbl-menu-sync-for-loyverse' ); ?></label></th>
				<td>
					<select id="fbmsl_currency_position" name="<?php echo esc_attr( FBMSL_Settings::OPTION_KEY ); ?>[currency_position]">
						<option value="before" <?php selected( $settings['currency_position'], 'before' ); ?>><?php echo esc_html__( 'Before price', 'fullbl-menu-sync-for-loyverse' ); ?></option>
						<option value="after" <?php selected( $settings['currency_position'], 'after' ); ?>><?php echo esc_html__( 'After price', 'fullbl-menu-sync-for-loyverse' ); ?></option>
					</select>
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
