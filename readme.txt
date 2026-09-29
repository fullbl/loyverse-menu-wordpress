=== FullBL Menu Sync for Loyverse ===
Contributors: fullbl
Tags: menu, restaurant, pos, food, sync
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync your Loyverse POS catalog to WordPress as menu posts and categories—no WooCommerce.

== Description ==

FullBL Menu Sync for Loyverse connects WordPress directly to the Loyverse API (no intermediary SaaS). It imports categories, items, descriptions, images, prices, availability, and variants into a custom post type and taxonomy, then keeps them updated via manual sync, WP-Cron, and optional webhooks.

This plugin is not affiliated with or endorsed by Loyverse.

= Features =

* Direct Loyverse API connection with token and store selection
* Custom post type for dishes and taxonomy for categories
* Idempotent sync (no duplicates)
* Manual sync, WP-Cron reconciliation, and webhook endpoint
* Shortcode `[fbmsl_menu]`
* Responsive list/grid layouts with simple style settings
* Editorial locks so sync does not overwrite locked title, content, or image
* Translation-ready (works alongside WPML, Polylang, or TranslatePress)

= Shortcode =

`[fbmsl_menu]`
`[fbmsl_menu category="antipasti" layout="list" columns="1"]`

= External services =

This plugin communicates with **Loyverse** when you configure an API token and use sync, connection test, webhook registration, or scheduled sync.

* **What is sent:** Your Loyverse personal access token (Bearer) in the `Authorization` header; store ID and webhook URL when registering webhooks; standard HTTP request metadata. Loyverse returns catalog data (categories, items, images, prices, inventory, store list).
* **When:** On “Test connection”, “Sync now”, WP-Cron sync, webhook-triggered sync, and “Register webhook” in Settings → FullBL Menu Sync for Loyverse.
* **Where:** `https://api.loyverse.com/v1.0` (Loyverse REST API). Product images may be downloaded from URLs provided by Loyverse during sync.
* **Terms:** [Loyverse Terms of Use](https://loyverse.com/terms-use)
* **Privacy:** [Loyverse Privacy Policy](https://loyverse.com/privacy-policy)

No other third-party analytics or tracking services are used by this plugin.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/fullbl-menu-sync-for-loyverse` or install via ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → FullBL Menu Sync for Loyverse**, enter your Loyverse API token, select a store, and click **Sync now**.
4. Visit `/menu/` (or your configured permalink base).

== Frequently Asked Questions ==

= Does this create orders or use WooCommerce? =

No. This plugin displays a public menu only.

= Will sync overwrite my edits? =

By default, yes for title, description, and image. Use the “Sync Locks” metabox on an item to preserve local edits.

= How do webhooks work locally? =

The webhook URL must be publicly reachable over HTTPS. For local development use a tunnel (ngrok, cloudflared). WP-Cron and Sync now work without a tunnel.

= What happens when I uninstall? =

Plugin options, the webhook secret, and scheduled cron events are removed. Synced menu posts and media remain in your database unless you delete them manually.

== Screenshots ==

1. Settings page with connection status and sync actions.
2. Frontend menu grid via shortcode.
3. Single menu item page.

== Changelog ==

= 0.1.0 =
* Initial public release.

== Upgrade Notice ==

= 0.1.0 =
First release.
