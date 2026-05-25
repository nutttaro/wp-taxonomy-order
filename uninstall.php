<?php
/**
 * WP Taxonomy Order Uninstall
 *
 * Removes all plugin data on uninstall.
 *
 * @package WP_Taxonomy_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data for the current site.
 */
function wpto_uninstall_site() {
	global $wpdb;

	delete_option( 'wp_taxonomy_order_settings' );
	delete_option( 'wpto_meta_migrated' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
			'_wpto_order'
		)
	);
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		wpto_uninstall_site();
		restore_current_blog();
	}
} else {
	wpto_uninstall_site();
}
