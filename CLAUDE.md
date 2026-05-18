# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WP Taxonomy Order is a WordPress plugin that adds drag-and-drop sorting to taxonomy term list tables in wp-admin. It stores sort order as term meta and modifies `get_terms` queries to respect that order. Published on wordpress.org as `wp-taxonomy-order`.

## Development

No build tools — plain PHP, vanilla JS (jQuery), and CSS. Assets in `assets/` are hand-written. The `.min.js` and `.min.css` files must be manually minified when changing the source files.

Text domain: `wp-taxonomy-order`. Translations template: `languages/wp-taxonomy-order.pot`.

The `svn/` directory is for WordPress.org plugin repository deployment (not part of the plugin runtime).

## Architecture

Two classes, both instantiated in `wp-taxonomy-order.php`:

- **`WP_Taxonomy_Order`** (`wp-taxonomy-order.php`) — Core ordering logic. Hooks into `get_terms_defaults`, `pre_get_terms`, and `terms_clauses` to rewrite term queries so they sort by `meta_value_num` on the `WPTO_META_KEY` (`_wpto_order`) term meta. Uses a LEFT JOIN (not INNER JOIN) so terms without order meta still appear, falling back to name sort. Provides AJAX endpoints (`wpto_term_ordering`, `wpto_reset_ordering`) and REST API routes under `wp-taxonomy-order/v1`.

- **`WP_Taxonomy_Order_Setting`** (`inc/wp-taxonomy-order-setting.php`) — Admin settings page under the "Taxonomy Order" menu item. Settings stored in option `wp_taxonomy_order_settings` with shape `{ enable: int, taxonomies: string[] }`.

The JS (`assets/js/wp-taxonomy-order.js`) uses jQuery UI Sortable on the `table.wp-list-table` in taxonomy admin pages, posting reorder events to the `wpto_term_ordering` AJAX action. It enforces same-parent-level sorting only.

## Key Extension Points

- **`wpto_sortable_taxonomies`** filter — controls which taxonomies get custom ordering (defaults to the settings page selection)
- **`wpto_after_set_term_order`** action — fires after each term's order meta is updated during a reorder

## Constants

`WPTO_PATH`, `WPTO_BASENAME`, `WPTO_PLUGIN_URL`, `WPTO_VERSION`, `WPTO_META_KEY` — defined in `wp-taxonomy-order.php`.

## Conventions

- PHP function/class prefixes: `WP_Taxonomy_Order`, `WPTO_`
- Capability gate: `manage_categories`
- AJAX nonces: `wpto_term_ordering_nonce`, `wpto_reset_ordering_nonce`
- Term order stored as term meta with key `_wpto_order` (integer values, via `WPTO_META_KEY` constant)
- REST API namespace: `wp-taxonomy-order/v1` (routes: `/reorder`, `/reset`)
- Uninstall cleanup in `uninstall.php` removes options and all `_wpto_order` term meta
- Migration from legacy `order` meta key runs once on `plugins_loaded` (copies, does not rename)
