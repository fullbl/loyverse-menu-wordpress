# WordPress.org SVN assets

After your plugin is approved, WordPress.org hosts plugin code in SVN. **Marketing assets** (icons, banners, screenshots) live in a separate `assets/` directory in that SVN repository—not inside the plugin zip.

## Required files

Place the following in the SVN `assets/` folder (commit alongside `trunk/`, not inside it):

| File | Size |
|------|------|
| `icon-128x128.png` | 128×128 |
| `icon-256x256.png` | 256×256 |
| `banner-772x250.png` | 772×250 |
| `banner-1544x500.png` | 1544×500 (retina banner) |
| `screenshot-1.png` … `screenshot-N.png` | Any reasonable size; match readme.txt `== Screenshots ==` |

## Workflow

1. Add PNG placeholders or final artwork under this directory in Git for reference (optional).
2. On first SVN checkout: `svn co https://plugins.svn.wordpress.org/fullbl-menu-sync-for-loyverse`
3. Copy icons/banners/screenshots into `assets/` and commit.
4. Plugin source goes in `trunk/`; tag releases as `tags/<version>/`.

The GitHub Actions workflow (`.github/workflows/wordpress-plugin-deploy.yml`) deploys tagged releases to `trunk/` and `tags/` when `SVN_USERNAME` and `SVN_PASSWORD` secrets are configured. You still upload assets to SVN manually unless you extend the workflow.
