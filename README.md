# FullBL Menu Sync for Loyverse

WordPress plugin that syncs a [Loyverse](https://loyverse.com/) POS catalog directly into WordPress (custom post type + taxonomy). No intermediary backend.

This plugin is not affiliated with or endorsed by Loyverse.

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
npx wp-env run cli wp plugin activate fullbl-menu-sync-for-loyverse
```

- Site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin — user `admin` / password `password`
- Activate **FullBL Menu Sync for Loyverse** under Plugins (`wp-content/plugins/fullbl-menu-sync-for-loyverse`) if not already activated
- Settings → FullBL Menu Sync for Loyverse → paste your Loyverse token → Test connection → select store → **Sync now**
- Frontend: http://localhost:8888/menu/

If `wp-env start` fails while building images, free Docker disk (`docker system prune`) and retry.
Stop with `npx wp-env stop`. Never commit API tokens.

Webhook registration against localhost needs a public tunnel pointing at the webhook URL shown in settings.

## Shortcode

```
[fbmsl_menu]
[fbmsl_menu category="antipasti" layout="grid" columns="2" show_images="1"]
```

## Theme overrides

Copy templates from `templates/` into `your-theme/fullbl-menu-sync-for-loyverse/`.

## Automated tests

```bash
composer install
composer test
```

## Coding standards

```bash
composer phpcs
composer phpcbf
```

## Release zip (WordPress.org upload)

Build a clean distribution package (excludes dev files via `.distignore`):

```bash
composer build
# or: bash bin/build-release.sh
```

Output: `dist/fullbl-menu-sync-for-loyverse.zip`

## Plugin Check (PCP)

With [wp-env](https://www.npmjs.com/package/@wordpress/env) running:

```bash
npx wp-env run cli wp plugin install plugin-check --activate
npx wp-env run cli wp plugin check fullbl-menu-sync-for-loyverse
```

Run checks against the built zip by extracting it into a test plugins folder if you want to validate the exact upload artifact.

## WordPress.org deploy

1. Put icons/banners/screenshots in `.wordpress-org/` (see `docs/wporg-assets.md`).
2. Set repo secrets `SVN_USERNAME` and `SVN_PASSWORD`.
3. Push a version tag matching the plugin Version / Stable tag, e.g. `git tag 0.1.0 && git push origin 0.1.0`.

The workflow `.github/workflows/wordpress-plugin-deploy.yml` builds the release and deploys via [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy).

## License

GPL-2.0-or-later
