<?php
/**
 * WP Taxonomy Order Settings Class
 *
 * @package WP_Taxonomy_Order
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Taxonomy_Order_Setting
 */
class WP_Taxonomy_Order_Setting {
	/**
	 * Array of custom settings/options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add settings page
	 * The page will appear in Admin menu
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Taxonomy Order Setting', 'wp-taxonomy-order' ), // Page title.
			__( 'Taxonomy Order', 'wp-taxonomy-order' ), // Title.
			'manage_categories', // Capability.
			'wp-taxonomy-order-settings-page', // Url slug.
			array( $this, 'create_admin_page' ), // Callback.
			'dashicons-list-view'
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {

		$default = array(
			'enable'     => 0,
			'taxonomies' => array(),
		);

		// Set class property.
		$this->options = get_option( 'wp_taxonomy_order_settings', $default );
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Select the taxonomy for sortable.', 'wp-taxonomy-order' ); ?></h2>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields.
				settings_fields( 'wp_taxonomy_order_settings_group' );
				do_settings_sections( 'wp-taxonomy-order-settings-page' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'wp_taxonomy_order_settings_group', // Option group.
			'wp_taxonomy_order_settings', // Option name.
			array( $this, 'sanitize' ) // Sanitize.
		);

		add_settings_section(
			'wp_taxonomy_order_settings_section', // ID.
			'', // Title.
			array( $this, 'wp_taxonomy_order_settings_section' ), // Callback.
			'wp-taxonomy-order-settings-page' // Page.
		);

		add_settings_field(
			'wp-taxonomy-order-enable', // ID.
			__( 'Enable', 'wp-taxonomy-order' ), // Title.
			array( $this, 'enable_field' ), // Callback.
			'wp-taxonomy-order-settings-page', // Page.
			'wp_taxonomy_order_settings_section'
		);

		add_settings_field(
			'wp-taxonomy-order', // ID.
			__( 'Taxonomies', 'wp-taxonomy-order' ), // Title.
			array( $this, 'taxonomies_field' ), // Callback.
			'wp-taxonomy-order-settings-page', // Page.
			'wp_taxonomy_order_settings_section'
		);
	}

	/**
	 * Sanitize POST data from custom settings form
	 *
	 * @param array $input Contains custom settings which are passed when saving the form.
	 * @return array
	 */
	public function sanitize( $input ) {
		$sanitized_input = array();
		if ( isset( $input['enable'] ) ) {
			$sanitized_input['enable'] = absint( $input['enable'] );
		} else {
			$sanitized_input['enable'] = 0;
		}

		if ( isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			$sanitized_input['taxonomies'] = array_map( 'sanitize_text_field', $input['taxonomies'] );
		} else {
			$sanitized_input['taxonomies'] = array();
		}

		return $sanitized_input;
	}

	/**
	 * Custom settings section text
	 */
	public function wp_taxonomy_order_settings_section() {
		// Section description (if needed).
	}

	/**
	 * Enable field
	 */
	public function enable_field() {
		printf(
			'<label for="wp-taxonomy-order-enable"><input type="checkbox" id="wp-taxonomy-order-enable" name="wp_taxonomy_order_settings[enable]" value="1" %s> %s</label>',
			checked( $this->options['enable'], 1, false ),
			esc_html__( 'Enable', 'wp-taxonomy-order' )
		);
	}

	/**
	 * Taxonomies Field
	 */
	public function taxonomies_field() {
		$taxonomies = isset( $this->options['taxonomies'] ) ? $this->options['taxonomies'] : array();

		$get_taxonomies = get_taxonomies( array(), 'objects' );
		if ( $get_taxonomies ) {
			foreach ( $get_taxonomies as $taxonomy ) {
				if ( ! in_array( $taxonomy->name, array( 'nav_menu', 'link_category', 'post_format' ), true ) ) {
					$is_checked = in_array( $taxonomy->name, $taxonomies, true );
					printf(
						'<p><label for="wp-taxonomy-order-%s"><input name="wp_taxonomy_order_settings[taxonomies][]" type="checkbox" id="wp-taxonomy-order-%s" value="%s" %s> %s</label></p>',
						esc_attr( $taxonomy->name ),
						esc_attr( $taxonomy->name ),
						esc_attr( $taxonomy->name ),
						checked( $is_checked, true, false ),
						esc_html( $taxonomy->label . ' (' . $taxonomy->name . ')' )
					);
				}
			}
		}
	}
}
