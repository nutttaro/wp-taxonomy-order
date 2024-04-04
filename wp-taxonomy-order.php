<?php
/**
 * Plugin Name:       WP Taxonomy Order
 * Plugin URI:        https://wordpress.org/plugins/wp-taxonomy-order/
 * Description:       Order Taxonomy and child with a Drag and Drop Sortable. Compatible with WPML.
 * Version:           1.0.5
 * Requires at least: 4.7
 * Requires PHP:      7.4
 * Tested up to:	  6.5
 * Author:            NuttTaro
 * Author URI:        https://nutttaro.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-term-order
 * Domain Path:       /languages
 */

// Define constants.
define('WPTO_PATH', plugin_dir_path(__FILE__));
define('WPTO_BASENAME', plugin_basename(__FILE__));
define('WPTO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPTO_VERSION', '1.0.5');

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
	public function __construct()
	{
		add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 100);
		add_filter('get_terms_defaults', [$this, 'get_terms_defaults'], 10, 2);
		add_action('pre_get_terms', [$this, 'pre_get_terms'], 10, 1);
		add_filter('terms_clauses', [$this, 'terms_clauses'], 99, 3);
		add_action('wp_ajax_wpto_term_ordering', [$this, 'ajax_term_ordering']);
	}

	/**
	 * Admin enqueue script
	 */
	public function admin_enqueue_scripts()
	{
		$default = [
			'enable' => 0,
			'taxonomies' => [],
		];

		$this->settings = get_option('wp_taxonomy_order_settings', $default);
		$this->allow_taxonomy = apply_filters('wpto_sortable_taxonomies', $this->settings['taxonomies']);

		if ((!empty($_GET['taxonomy']) && $this->settings['enable'] && in_array(wp_unslash($_GET['taxonomy']), $this->allow_taxonomy)) && !isset($_GET['orderby'])) {

			wp_enqueue_style('wp-taxonomy-order-style', WPTO_PLUGIN_URL . '/assets/css/wp-taxonomy-order.min.css', [], WPTO_VERSION);

			wp_register_script('wp-taxonomy-order', WPTO_PLUGIN_URL . '/assets/js/wp-taxonomy-order.min.js', ['jquery-ui-sortable'], WPTO_VERSION);
			wp_enqueue_script('wp-taxonomy-order');

			$taxonomy = isset($_GET['taxonomy']) ? $this->clean(wp_unslash($_GET['taxonomy'])) : '';

			$wpto_term_order_params = [
				'taxonomy' => $taxonomy,
			];

			wp_localize_script('wp-taxonomy-order', 'wpto_term_ordering_params', $wpto_term_order_params);
		}
	}

	/**
	 * Clean variables using sanitize_text_field
	 *
	 * @param $var
	 * @return array|mixed|string
	 */
	function clean($var)
	{
		if (is_array($var)) {
			return array_map('wc_clean', $var);
		} else {
			return is_scalar($var) ? sanitize_text_field($var) : $var;
		}
	}

	/**
	 * Change get terms defaults for sorting
	 *
	 * @param $defaults
	 * @param $taxonomies
	 * @return mixed
	 */
	public function get_terms_defaults($defaults, $taxonomies)
	{
		if (is_array($taxonomies) && 1 < count($taxonomies)) {
			return $defaults;
		}

		$taxonomy = is_array($taxonomies) ? (string)current($taxonomies) : $taxonomies;
		$orderby = 'name';

		if (in_array($taxonomy, apply_filters('wpto_sortable_taxonomies', $this->allow_taxonomy), true)) {
			$orderby = 'menu_order';
		}

		switch ($orderby) {
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
	 * @param $terms_query
	 */
	public function pre_get_terms($terms_query)
	{
		$args = &$terms_query->query_vars;

		if ('menu_order' === $args['orderby']) {
			$args['orderby'] = 'name';
			$args['force_menu_order_sort'] = true;
		}

		if ('name_num' === $args['orderby']) {
			$args['orderby'] = 'name';
			$args['force_numeric_name'] = true;
		}

		if ('count' === $args['fields']) {
			return;
		}

		$args['order'] = 'DESC' === strtoupper($args['order']) ? 'DESC' : 'ASC';
		$args['force_menu_order_sort'] = true;

		if (!empty($args['force_menu_order_sort'])) {
			$args['orderby'] = 'meta_value_num';
			$args['meta_key'] = 'order';
			$terms_query->meta_query->parse_query_vars($args);
		}
	}

	/**
	 * Adjust term query for custom sorting
	 *
	 * @param $clauses
	 * @param $taxonomies
	 * @param $args
	 * @return mixed
	 */
	public function terms_clauses($clauses, $taxonomies, $args)
	{
		global $wpdb;

		if (strpos($clauses['fields'], 'COUNT(*)') !== false) {
			return $clauses;
		}

		if (!empty($args['force_numeric_name'])) {
			$clauses['orderby'] = str_replace('ORDER BY t.name', 'ORDER BY t.name+0', $clauses['orderby']);
		}

		if (!empty($args['force_menu_order_sort'])) {
			$clauses['join'] = str_replace("INNER JOIN {$wpdb->termmeta} ON ( t.term_id = {$wpdb->termmeta}.term_id )", "LEFT JOIN {$wpdb->termmeta} ON ( t.term_id = {$wpdb->termmeta}.term_id AND {$wpdb->termmeta}.meta_key='order')", $clauses['join']);
			$clauses['where'] = str_replace("{$wpdb->termmeta}.meta_key = 'order'", "( {$wpdb->termmeta}.meta_key = 'order' OR {$wpdb->termmeta}.meta_key IS NULL )", $clauses['where']);
			$clauses['orderby'] = 'DESC' === $args['order'] ? str_replace('meta_value+0', 'meta_value+0 DESC, t.name', $clauses['orderby']) : str_replace('meta_value+0', 'meta_value+0 ASC, t.name', $clauses['orderby']);
		}

		return $clauses;
	}

	/**
	 * Reorder a term of hierarchy
	 *
	 * @param $the_term
	 * @param $next_id
	 * @param $taxonomy
	 * @param int $index
	 * @param null $terms
	 * @return int|mixed
	 */
	public function reorder_terms($the_term, $next_id, $taxonomy, $index = 0, $terms = null)
	{
		if (!$terms) {
			$terms = get_terms($taxonomy, 'hide_empty=0&parent=0&menu_order=ASC');
		}
		if (empty($terms)) {
			return $index;
		}

		$id = intval($the_term->term_id);

		$term_in_level = false;

		foreach ($terms as $term) {
			$term_id = intval($term->term_id);

			if ($term_id === $id) {
				$term_in_level = true;
				continue;
			}

			if (null !== $next_id && $term_id === $next_id) {
				$index++;
				$index = $this->set_term_order($id, $index, $taxonomy, true);
			}

			$index++;
			$index = $this->set_term_order($term_id, $index, $taxonomy);

			do_action('wpto_after_set_term_order', $term, $index, $taxonomy);

			$children = get_terms($taxonomy, "parent={$term_id}&hide_empty=0&menu_order=ASC");
			if (!empty($children)) {
				$index = $this->reorder_terms($the_term, $next_id, $taxonomy, $index, $children);
			}
		}

		// No nextid meaning our term is in last position.
		if ($term_in_level && null === $next_id) {
			$index = $this->set_term_order($id, $index + 1, $taxonomy, true);
		}

		return $index;
	}

	/**
	 * Set the sort order of a term.
	 *
	 * @param int $term_id Term ID.
	 * @param int $index Index.
	 * @param string $taxonomy Taxonomy.
	 * @param bool $recursive Recursive (default: false).
	 * @return int
	 */
	public function set_term_order($term_id, $index, $taxonomy, $recursive = false)
	{

		$term_id = (int)$term_id;
		$index = (int)$index;

		update_term_meta($term_id, 'order', $index);

		if (!$recursive) {
			return $index;
		}

		$children = get_terms($taxonomy, "parent=$term_id&hide_empty=0&menu_order=ASC");

		foreach ($children as $term) {
			$index++;
			$index = $this->set_term_order($term->term_id, $index, $taxonomy, true);
		}

		clean_term_cache($term_id, $taxonomy);

		return $index;
	}

	/**
	 * Ajax term ordering
	 */
	function ajax_term_ordering()
	{
		if (!current_user_can('edit_posts') || empty($_POST['id'])) {
			wp_die(-1);
		}

		$id = (int)$_POST['id'];
		$next_id = isset($_POST['nextid']) && (int)$_POST['nextid'] ? (int)$_POST['nextid'] : null;
		$taxonomy = isset($_POST['thetaxonomy']) ? esc_attr(wp_unslash($_POST['thetaxonomy'])) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$term = get_term_by('id', $id, $taxonomy);

		if (!$id || !$term || !$taxonomy) {
			wp_die(0);
		}

		$this->reorder_terms($term, $next_id, $taxonomy);

		$children = get_terms($taxonomy, "child_of=$id&menu_order=ASC&hide_empty=0");

		if ($term && count($children)) {
			echo 'children';
			wp_die();
		}

	}


}

new WP_Taxonomy_Order();

require WPTO_PATH . '/inc/wp-taxonomy-order-setting.php';
new WP_Taxonomy_Order_Setting();


