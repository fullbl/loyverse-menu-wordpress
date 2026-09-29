# Loyverse Menu

WordPress plugin that syncs a [Loyverse](https://loyverse.com/) POS catalog directly into WordPress (custom post type + taxonomy). No intermediary backend.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- A Loyverse API personal access token
- Docker (only for local development with `@wordpress/env`)

## Local development (manual testing)

```bash
npm install
npx wp-env start
npx wp-env run cli wp rewrite structure '/%postname%/' --hard
npx wp-env run cli wp plugin activate loyverse-menu
```

- Site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin — user `admin` / password `password`
- Activate **Loyverse Menu** under Plugins (`wp-content/plugins/loyverse-menu`) if not already activated
- Settings → Loyverse Menu → paste your Loyverse token → Test connection → select store → **Sync now**
- Frontend: http://localhost:8888/menu/

If `wp-env start` fails while building images, free Docker disk (`docker system prune`) and retry.
Stop with `npx wp-env stop`. Never commit API tokens.

Webhook registration against localhost needs a public tunnel pointing at the webhook URL shown in settings.

## Shortcode

```
[loyverse_menu]
[loyverse_menu category="antipasti" layout="grid" columns="2" show_images="1"]
```

## Theme overrides

Copy templates from `templates/` into `your-theme/loyverse-menu/`.

## Automated tests

```bash
composer install
composer test
```

## License

GPL-2.0-or-later
