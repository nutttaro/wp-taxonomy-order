<?php
/**
 * WP Taxonomy Order Uninstall
 *
 * Removes all plugin data on uninstall.
 *
 * @package WP_Taxonomy_Order
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin options.
delete_option( 'wp_taxonomy_order_settings' );
delete_option( 'wpto_meta_migrated' );

// Remove all term meta created by this plugin (both old and new keys).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key IN (%s, %s)",
		'_wpto_order',
		'order'
	)
);
