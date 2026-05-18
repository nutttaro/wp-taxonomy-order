=== WP Taxonomy Order ===
Contributors: nutttaro
Donate link: https://www.buymeacoffee.com/nutttaro
Tags: taxonomy, order
Requires at least: 4.7
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WP Taxonomy Order is a plugin that allows for the ordering of taxonomy or category for WordPress.

== Description ==

WP Taxonomy Order is a plugin that allows for the ordering of taxonomy or category for WordPress.

Features:

* Easy for ordering taxonomy by Drag and Drop.
* Support custom taxonomy.
* Child taxonomy supports the ordering also.
* Can choose to enable order for each taxonomy.
* The plugin is lightweight and has no custom coding.
* Compatible with WPML.

== Installation ==
1. Upload `wp-taxonomy-order.zip` to the install plugin page
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to *Taxonomy Order* in the left-hand menu to start setting the plugin

== Frequently Asked Questions ==

= How many terms support it? =

Should be maxed 100 terms for easy management of the ordering

= WooCommerce support ? =

No, support Product Category.
WooCommerce already has the ordering by himself.

== Screenshots ==

1. Screenshot of the menu page for WP Taxonomy Order Setting page.
You can select taxonomy for enable order feature
1. Drag and Drop for order taxonomy. It easy!

== Changelog ==

= 1.1.0 =
* New: "Reset Order" button on taxonomy list pages to clear custom ordering
* New: REST API endpoints under `wp-taxonomy-order/v1` (`/reorder`, `/reset`)
* New: Uninstall cleanup — removes all plugin data when deleted
* Improved: Namespaced term meta key (`_wpto_order`) to prevent collisions with other plugins
* Improved: Automatic migration from legacy `order` meta key on update
* Fixed: Replaced deprecated `wpspin_light.gif` spinner with WordPress CSS spinner
* Fixed: Translation template text domain corrected to `wp-taxonomy-order`

= 1.0.6 =
* Tested up to WordPress 6.9
* Security: Added nonce verification for AJAX requests
* Security: Improved capability checks (changed from 'edit_pages' to 'manage_categories')
* Security: Enhanced input sanitization and validation
* Security: Added direct file access protection
* Fixed: Text domain inconsistency (now 'wp-taxonomy-order' throughout)
* Fixed: Deprecated get_terms() syntax - now using array parameters
* Fixed: Proper escaping for all output
* Improved: Better error handling and WP_Error checks
* Improved: Code formatting and WordPress coding standards compliance
* Improved: PHPDoc comments for better documentation

= 1.0.5 =
* Tested up to WordPress 6.1.1
* Add Tip me on Ko-fi

= 1.0.4 =
* Tested up to WordPress 5.9

= 1.0.3 =
* Tested up to WordPress 5.8.1

= 1.0.2 =
* Add buy me a coffee

= 1.0.1 =
* Add taxonomy slug to setting

= 1.0.0 =
* Initial Release
