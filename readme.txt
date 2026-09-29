=== Loyverse Menu ===
Contributors: fullbl
Tags: loyverse, menu, restaurant, pos, food
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync your Loyverse POS catalog to WordPress as real menu posts and categories.

== Description ==

Loyverse Menu connects WordPress directly to the Loyverse API (no intermediary SaaS). It imports categories, items, descriptions, images, prices, availability, and variants into a custom post type and taxonomy, then keeps them updated via manual sync, WP-Cron, and optional webhooks.

= Features =

* Direct Loyverse API connection with token + store selection
* Custom post type for dishes and taxonomy for categories
* Idempotent sync (no duplicates)
* Manual sync, WP-Cron reconciliation, and webhook endpoint
* Shortcode `[loyverse_menu]`
* Responsive list/grid layouts with simple style settings
* Editorial locks so sync does not overwrite locked title, content, or image
* Multilingual-friendly (works alongside WPML / Polylang / TranslatePress; no proprietary translation system)

= Shortcode =

`[loyverse_menu]`
`[loyverse_menu category="antipasti" layout="list" columns="1"]`

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/loyverse-menu` or install via ZIP.
2. Activate the plugin.
3. Go to Settings → Loyverse Menu, enter your Loyverse API token, select a store, and click Sync now.
4. Visit `/menu/` (or your configured permalink base).

== Frequently Asked Questions ==

= Does this create orders or use WooCommerce? =

No. The MVP is a public menu only.

= Will sync overwrite my edits? =

By default yes for title, description, and image. Use the “Loyverse Sync Locks” metabox on an item to preserve local edits.

= How do webhooks work locally? =

The webhook URL must be publicly reachable over HTTPS. For local development use a tunnel (ngrok, cloudflared). WP-Cron and Sync now work without a tunnel.

== Changelog ==

= 0.1.0 =
* Initial MVP release.
