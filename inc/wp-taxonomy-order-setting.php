<?php

/**
 * Class WP_Taxonomy_Order_Setting
 */
class WP_Taxonomy_Order_Setting
{
	/**
	 * Array of custom settings/options
	 **/
	private $options;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'page_init']);
	}

	/**
	 * Add settings page
	 * The page will appear in Admin menu
	 */
	public function add_settings_page()
	{
		add_menu_page(
			__('Taxonomy Order Setting', 'wp-term-order'), // Page title
			__('Taxonomy Order', 'wp-term-order'), // Title
			'edit_pages', // Capability
			'wp-taxonomy-order-settings-page', // Url slug
			[$this, 'create_admin_page'], // Callback
			'dashicons-list-view'
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page()
	{

		$default = [
			'enable' => 0,
			'taxonomies' => [],
		];

		// Set class property
		$this->options = get_option('wp_taxonomy_order_settings', $default);
		?>
		<div class="wrap">
			<h2><?php _e('Select the taxonomy for sortable.', 'wp-term-order'); ?></h2>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields('wp_taxonomy_order_settings_group');
				do_settings_sections('wp-taxonomy-order-settings-page');
				submit_button();
				?>
			</form>
		</div>
		<hr>
		<a href='https://ko-fi.com/J3J6HM43W' target='_blank'><img height='36' style='border:0px;height:36px;' src='<?php echo WPTO_PLUGIN_URL; ?>assets/images/kofi1.webp' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init()
	{
		register_setting(
			'wp_taxonomy_order_settings_group', // Option group
			'wp_taxonomy_order_settings', // Option name
			[$this, 'sanitize'] // Sanitize
		);

		add_settings_section(
			'wp_taxonomy_order_settings_section', // ID
			'', // Title
			[$this, 'wp_taxonomy_order_settings_section'], // Callback
			'wp-taxonomy-order-settings-page' // Page
		);

		add_settings_field(
			'wp-taxonomy-order-enable', // ID
			__('Enable', 'wp-term-order'), // Title
			[$this, 'enable_field'], // Callback
			'wp-taxonomy-order-settings-page', // Page
			'wp_taxonomy_order_settings_section'
		);

		add_settings_field(
			'wp-taxonomy-order', // ID
			__('Taxonomies', 'wp-term-order'), // Title
			[$this, 'taxonomies_field'], // Callback
			'wp-taxonomy-order-settings-page', // Page
			'wp_taxonomy_order_settings_section'
		);
	}

	/**
	 * Sanitize POST data from custom settings form
	 *
	 * @param array $input Contains custom settings which are passed when saving the form
	 */
	public function sanitize($input)
	{
		$sanitized_input = [];
		if (isset($input['enable'])) {
			$sanitized_input['enable'] = sanitize_text_field($input['enable']);
		} else {
			$sanitized_input['enable'] = 0;
		}

		if (isset($input['taxonomies']))
			$sanitized_input['taxonomies'] = $input['taxonomies'];

		return $sanitized_input;
	}

	/**
	 * Custom settings section text
	 */
	public function wp_taxonomy_order_settings_section()
	{

	}

	/**
	 * Enable field
	 */
	public function enable_field()
	{
		echo '<label for="wp-taxonomy-order-enable"><input type="checkbox" id="wp-taxonomy-order-enable" name="wp_taxonomy_order_settings[enable]" value="1" ' . checked($this->options['enable'], 1, false) . ' > ' . __('Enable', 'wp-term-order') . '</label>';
	}

	/**
	 * Taxonomies Field
	 */
	public function taxonomies_field()
	{
		$taxonomies = $this->options['taxonomies'] ?? [];

		$get_taxonomies = get_taxonomies([], 'object_type');
		if ($get_taxonomies) {
			foreach ($get_taxonomies as $taxonomy) {
				if (!in_array($taxonomy->name, ['nav_menu', 'link_category', 'post_format'])) {
					$is_checked = in_array($taxonomy->name, $taxonomies);
					printf(
						'<p><label for="wp-taxonomy-order-%s"><input name="wp_taxonomy_order_settings[taxonomies][]" type="checkbox" id="wp-taxonomy-order-%s" value="%s" %s> %s</label></p>',
						$taxonomy->name, $taxonomy->name, $taxonomy->name, checked($is_checked, true, false), $taxonomy->label . ' (' . $taxonomy->name . ')'
					);
				}
			}
		}
	}

}
