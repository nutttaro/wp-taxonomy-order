<?php
/**
 * Plugin Name:       WP Taxonomy Order
 * Plugin URI:        https://wordpress.org/plugins/wp-taxonomy-order/
 * Description:       Order Taxonomy and child with a Drag and Drop Sortable. Compatible with WPML.
 * Version:           1.0.6
 * Requires at least: 4.7
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            NuttTaro
 * Author URI:        https://nutttaro.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-taxonomy-order
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'WPTO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPTO_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPTO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPTO_VERSION', '1.0.6' );

/**
 * Class WP_Taxonomy_Order
 */
class WP_Taxonomy_Order
{
	/**
	 * Array of custom settings
	 **/
	private $settings;

	/**
	 * Array of custom allow_taxonomy
	 **/
	private $allow_taxonomy = [];

	/**
	 * WP_Taxonomy_Order constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 100 );
		add_filter( 'get_terms_defaults', array( $this, 'get_terms_defaults' ), 10, 2 );
		add_action( 'pre_get_terms', array( $this, 'pre_get_terms' ), 10, 1 );
		add_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 99, 3 );
		add_action( 'wp_ajax_wpto_term_ordering', array( $this, 'ajax_term_ordering' ) );
	}

	/**
	 * Admin enqueue script
	 */
	public function admin_enqueue_scripts() {
		$default = array(
			'enable'     => 0,
			'taxonomies' => array(),
		);

		$this->settings       = get_option( 'wp_taxonomy_order_settings', $default );
		$this->allow_taxonomy = apply_filters( 'wpto_sortable_taxonomies', $this->settings['taxonomies'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( ! empty( $_GET['taxonomy'] ) && $this->settings['enable'] && in_array( wp_unslash( $_GET['taxonomy'] ), $this->allow_taxonomy, true ) ) && ! isset( $_GET['orderby'] ) ) {

			wp_enqueue_style( 'wp-taxonomy-order-style', WPTO_PLUGIN_URL . '/assets/css/wp-taxonomy-order.min.css', array(), WPTO_VERSION );

			wp_register_script( 'wp-taxonomy-order', WPTO_PLUGIN_URL . '/assets/js/wp-taxonomy-order.min.js', array( 'jquery-ui-sortable' ), WPTO_VERSION, true );
			wp_enqueue_script( 'wp-taxonomy-order' );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$taxonomy = isset( $_GET['taxonomy'] ) ? $this->clean( wp_unslash( $_GET['taxonomy'] ) ) : '';

			$wpto_term_order_params = array(
				'taxonomy' => $taxonomy,
				'nonce'    => wp_create_nonce( 'wpto_term_ordering_nonce' ),
			);

			wp_localize_script( 'wp-taxonomy-order', 'wpto_term_ordering_params', $wpto_term_order_params );
		}
	}

	/**
	 * Clean variables using sanitize_text_field
	 *
	 * @param mixed $var Variable to clean.
	 * @return array|mixed|string
	 */
	private function clean( $var ) {
		if ( is_array( $var ) ) {
			return array_map( array( $this, 'clean' ), $var );
		} else {
			return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
		}
	}

	/**
	 * Change get terms defaults for sorting
	 *
	 * @param array        $defaults   Default query arguments.
	 * @param array|string $taxonomies Taxonomy or array of taxonomies.
	 * @return array
	 */
	public function get_terms_defaults( $defaults, $taxonomies ) {
		if ( is_array( $taxonomies ) && 1 < count( $taxonomies ) ) {
			return $defaults;
		}

		$taxonomy = is_array( $taxonomies ) ? (string) current( $taxonomies ) : $taxonomies;
		$orderby  = 'name';

		if ( in_array( $taxonomy, apply_filters( 'wpto_sortable_taxonomies', $this->allow_taxonomy ), true ) ) {
			$orderby = 'menu_order';
		}

		switch ( $orderby ) {
			case 'menu_order':
			case 'name_num':
			case 'parent':
				$defaults['orderby'] = $orderby;
				break;
		}

		return $defaults;
	}

	/**
	 * Add menu_order for get_terms
	 *
	 * @param WP_Term_Query $terms_query Term query object.
	 */
	public function pre_get_terms( $terms_query ) {
		$args = &$terms_query->query_vars;

		if ( 'menu_order' === $args['orderby'] ) {
			$args['orderby']               = 'name';
			$args['force_menu_order_sort'] = true;
		}

		if ( 'name_num' === $args['orderby'] ) {
			$args['orderby']           = 'name';
			$args['force_numeric_name'] = true;
		}

		if ( 'count' === $args['fields'] ) {
			return;
		}

		$args['order']                 = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$args['force_menu_order_sort'] = true;

		if ( ! empty( $args['force_menu_order_sort'] ) ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'order';
			$terms_query->meta_query->parse_query_vars( $args );
		}
	}

	/**
	 * Adjust term query for custom sorting
	 *
	 * @param array        $clauses    SQL clauses.
	 * @param array|string $taxonomies Taxonomies.
	 * @param array        $args       Query arguments.
	 * @return array
	 */
	public function terms_clauses( $clauses, $taxonomies, $args ) {
		global $wpdb;

		if ( false !== strpos( $clauses['fields'], 'COUNT(*)' ) ) {
			return $clauses;
		}

		if ( ! empty( $args['force_numeric_name'] ) ) {
			$clauses['orderby'] = str_replace( 'ORDER BY t.name', 'ORDER BY t.name+0', $clauses['orderby'] );
		}

		if ( ! empty( $args['force_menu_order_sort'] ) ) {
			$clauses['join']    = str_replace( "INNER JOIN {$wpdb->termmeta} ON ( t.term_id = {$wpdb->termmeta}.term_id )", "LEFT JOIN {$wpdb->termmeta} ON ( t.term_id = {$wpdb->termmeta}.term_id AND {$wpdb->termmeta}.meta_key='order')", $clauses['join'] );
			$clauses['where']   = str_replace( "{$wpdb->termmeta}.meta_key = 'order'", "( {$wpdb->termmeta}.meta_key = 'order' OR {$wpdb->termmeta}.meta_key IS NULL )", $clauses['where'] );
			$clauses['orderby'] = 'DESC' === $args['order'] ? str_replace( 'meta_value+0', 'meta_value+0 DESC, t.name', $clauses['orderby'] ) : str_replace( 'meta_value+0', 'meta_value+0 ASC, t.name', $clauses['orderby'] );
		}

		return $clauses;
	}

	/**
	 * Reorder a term of hierarchy
	 *
	 * @param WP_Term     $the_term The term to reorder.
	 * @param int|null    $next_id  Next term ID.
	 * @param string      $taxonomy Taxonomy name.
	 * @param int         $index    Current index.
	 * @param array|null  $terms    Terms array.
	 * @return int
	 */
	private function reorder_terms( $the_term, $next_id, $taxonomy, $index = 0, $terms = null ) {
		if ( ! $terms ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'parent'     => 0,
					'orderby'    => 'menu_order',
					'order'      => 'ASC',
				)
			);
		}
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $index;
		}

		$id = absint( $the_term->term_id );

		$term_in_level = false;

		foreach ( $terms as $term ) {
			$term_id = absint( $term->term_id );

			if ( $term_id === $id ) {
				$term_in_level = true;
				continue;
			}

			if ( null !== $next_id && $term_id === $next_id ) {
				++$index;
				$index = $this->set_term_order( $id, $index, $taxonomy, true );
			}

			++$index;
			$index = $this->set_term_order( $term_id, $index, $taxonomy );

			do_action( 'wpto_after_set_term_order', $term, $index, $taxonomy );

			$children = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'parent'     => $term_id,
					'hide_empty' => false,
					'orderby'    => 'menu_order',
					'order'      => 'ASC',
				)
			);
			if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
				$index = $this->reorder_terms( $the_term, $next_id, $taxonomy, $index, $children );
			}
		}

		// No nextid meaning our term is in last position.
		if ( $term_in_level && null === $next_id ) {
			$index = $this->set_term_order( $id, $index + 1, $taxonomy, true );
		}

		return $index;
	}

	/**
	 * Set the sort order of a term.
	 *
	 * @param int    $term_id   Term ID.
	 * @param int    $index     Index.
	 * @param string $taxonomy  Taxonomy.
	 * @param bool   $recursive Recursive (default: false).
	 * @return int
	 */
	private function set_term_order( $term_id, $index, $taxonomy, $recursive = false ) {

		$term_id = absint( $term_id );
		$index   = absint( $index );

		update_term_meta( $term_id, 'order', $index );

		if ( ! $recursive ) {
			return $index;
		}

		$children = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'parent'     => $term_id,
				'hide_empty' => false,
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
			)
		);

		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $term ) {
				++$index;
				$index = $this->set_term_order( $term->term_id, $index, $taxonomy, true );
			}
		}

		clean_term_cache( $term_id, $taxonomy );

		return $index;
	}

	/**
	 * Ajax term ordering
	 */
	public function ajax_term_ordering() {
		// Verify nonce.
		check_ajax_referer( 'wpto_term_ordering_nonce', 'security' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wp-taxonomy-order' ) ) );
		}

		// Validate and sanitize input.
		if ( empty( $_POST['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid term ID.', 'wp-taxonomy-order' ) ) );
		}

		$id       = absint( $_POST['id'] );
		$next_id  = isset( $_POST['nextid'] ) && absint( $_POST['nextid'] ) ? absint( $_POST['nextid'] ) : null;
		$taxonomy = isset( $_POST['thetaxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['thetaxonomy'] ) ) : '';

		if ( empty( $taxonomy ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid taxonomy.', 'wp-taxonomy-order' ) ) );
		}

		$term = get_term_by( 'id', $id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Term not found.', 'wp-taxonomy-order' ) ) );
		}

		$this->reorder_terms( $term, $next_id, $taxonomy );

		$children = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'child_of'   => $id,
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
				'hide_empty' => false,
			)
		);

		if ( $term && ! is_wp_error( $children ) && count( $children ) ) {
			wp_send_json_success( array( 'message' => 'children' ) );
		}

		wp_send_json_success();
	}


}

new WP_Taxonomy_Order();

require WPTO_PATH . '/inc/wp-taxonomy-order-setting.php';
new WP_Taxonomy_Order_Setting();


