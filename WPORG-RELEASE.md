# WordPress.org Release (SVN)

Plugin slug: `wb-smart-order-tracking-for-woocommerce`  
Current version: `1.0.0`

## One-time setup

```bash
export WPORG_USER="your_wporg_username"
export WPORG_SLUG="wb-smart-order-tracking-for-woocommerce"
export WPORG_VERSION="1.0.0"
export WPORG_WORKDIR="$HOME/.wporg-svn/$WPORG_SLUG"
```

## Release commands

Run from plugin root:

```bash
bash scripts/wporg-release.sh
```

The script will:
- checkout `https://plugins.svn.wordpress.org/$WPORG_SLUG/`
- sync plugin files to `trunk/` (excluding dev/test folders)
- copy `trunk/` to `tags/$WPORG_VERSION/`
- show `svn status`
- commit `trunk` and `tags/$WPORG_VERSION`

## Notes

- Use the same version in:
  - `wb-smart-order-tracking-for-woocommerce.php` (`Version`)
  - `readme.txt` (`Stable tag`)
- If you have banner/icon assets, add them under `assets/` in the SVN checkout before commit.
