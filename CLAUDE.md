# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WP Taxonomy Order is a WordPress plugin that adds drag-and-drop sorting to taxonomy term list tables in wp-admin. It stores sort order as term meta and modifies `get_terms` queries to respect that order. Published on wordpress.org as `wp-taxonomy-order`.

## Development

No build tools — plain PHP, vanilla JS (jQuery), and CSS. Assets in `assets/` are hand-written. The `.min.js` and `.min.css` files must be manually minified when changing the source files (no build script; use any external minifier).

Text domain: `wp-taxonomy-order`. Translations template: `languages/wp-taxonomy-order.pot`.

`readme.txt` is the WordPress.org plugin directory listing; `README.md` is the GitHub mirror. Keep both in sync when updating changelog or metadata.

The `svn/` directory is for WordPress.org plugin repository deployment (not part of the plugin runtime, gitignored).

## Architecture

Two classes, both instantiated in `wp-taxonomy-order.php`:

- **`WP_Taxonomy_Order`** (`wp-taxonomy-order.php`) — Core ordering logic: query rewriting, AJAX endpoints, REST API routes, and admin UI for the drag handle and reset button.

- **`WP_Taxonomy_Order_Setting`** (`inc/wp-taxonomy-order-setting.php`) — Admin settings page (top-level menu, `dashicons-list-view`). Settings stored in option `wp_taxonomy_order_settings` with shape `{ enable: int, taxonomies: string[] }`. The taxonomies field excludes `nav_menu`, `link_category`, and `post_format` from the UI.

### Query Rewriting Pipeline

The plugin rewrites term queries through a 3-hook chain:

1. **`get_terms_defaults`** — If the queried taxonomy is in the allowed list, sets `orderby` to `menu_order` (a synthetic value, not a real WP orderby).
2. **`pre_get_terms`** — Detects `menu_order` orderby, converts it to `meta_value_num` with `meta_key = _wpto_order`, and sets a `force_menu_order_sort` flag.
3. **`terms_clauses`** — When `force_menu_order_sort` is set, rewrites the SQL JOIN from INNER to LEFT (so terms without order meta still appear) and appends `t.name` as a fallback sort.

### Ordering Activation Guard

Scripts and drag-and-drop only load when ALL conditions are met:
- On a taxonomy list page (`$_GET['taxonomy']` is set)
- Plugin is enabled in settings
- The taxonomy is in the allowed list
- No `?orderby=` parameter in the URL (column-header sorting overrides custom order)

### JS Behavior

The JS (`assets/js/wp-taxonomy-order.js`) uses jQuery UI Sortable on `table.wp-list-table`. It appends a `.column-handle` cell to each row for the drag grip. Sorting is constrained to same-parent-level terms only — cross-level drags are cancelled. After a drop, it posts to the `wpto_term_ordering` AJAX action; if the moved term has children, the page reloads to reflect reordered descendants.

## Key Extension Points

- **`wpto_sortable_taxonomies`** filter — controls which taxonomies get custom ordering (defaults to the settings page selection)
- **`wpto_after_set_term_order`** action — fires after each term's order meta is updated during a reorder

## Constants

`WPTO_PATH`, `WPTO_BASENAME`, `WPTO_PLUGIN_URL`, `WPTO_VERSION`, `WPTO_META_KEY` — defined in `wp-taxonomy-order.php`.

## Conventions

- PHP function/class prefixes: `WP_Taxonomy_Order`, `WPTO_`, `wpto_`
- Capability gate: `manage_categories`
- AJAX nonces: `wpto_term_ordering_nonce`, `wpto_reset_ordering_nonce`
- Term order stored as term meta with key `_wpto_order` (integer values, via `WPTO_META_KEY` constant)
- REST API namespace: `wp-taxonomy-order/v1` (routes: `/reorder`, `/reset`)
- Options: `wp_taxonomy_order_settings` (plugin config), `wpto_meta_migrated` (one-time migration flag)
- Uninstall cleanup in `uninstall.php` removes both options and all `_wpto_order` term meta
- Migration from legacy `order` meta key runs once on `plugins_loaded` (copies values, does not delete originals)
