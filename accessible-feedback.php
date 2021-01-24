<?php
/**
 * Plugin Name: Accessible Feedback
 * Plugin URI: https://example.com
 * Description: A plugin to collect feedback. Compatible with screen readers.
 * Author: Alex Stine
 * Author URI: https://example.com
 * Version: 1.0
 */

defined( 'ABSPATH' ) || exit;

class Accessible_Feedback_Plugin {

	/**
	 * Holds our plugin instance.
	 */
	private static $instance;

	/**
	 * Holds the rest namespace.
	 */
	private $namespace;

	/**
	 * Holds the rest version.
	 */
	private $version;

	/**
	 * Holds the plugin text domain.
	 */
	private $td;

	/**
	 * Holds the plugin option name.
	 */
	private $option_name;

	/**
	 * Holds the WordPress capability required to access admin page.
	 */
	private $admin_cap;

	/**
	 * Holds the WordPress capability required to delete feedback.
	 */
	private $feedback_delete_cap;

	/**
	 * Holds the admin URL of feedback page.
	 */
	private $admin_url;

	/**
	 * Holds the shortcode name of the plugin feedback form output.
	 */
	private $shortcode_name;

	/**
	 * Holds the plugin dir url.
	 */
	private $plugin_url;

	/**
	 * Holds the assets version.
	 */
	private $cache_ver;

	/**
	 * Minify the assets?
	 */
	private $minify;

	/**
	 * Singleton.
	 *
	 * @return Accessible_Feedback_Plugin.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'admin_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		/**
		 * Filters the shortcode name that is used to output the feedback form.
		 *
		 * @param string $shortcode The shortcode name, default is 'accessible_feedback'.
		 */
		$this->shortcode_name = apply_filters( 'accessible_feedback_override_shortcode_name', 'accessible_feedback' );

		add_shortcode( $this->shortcode_name, array( $this, 'render_feedback_form' ) );

		$this->namespace = 'accessible-feedback';
		$this->version = 1;

$this->td = 'accessible-feedback';

		$this->option_name = 'accessible_feedback';

		/**
		 * Filter to override capability that is required to access the admin page.
		 *
		 * @param string $capability The capability, default is 'manage_options'.
		 */
		$this->admin_cap = apply_filters( 'accessible_feedback_override_admin_cap', 'manage_options' );
		/**
		 * Filter to override capability that is required to delete feedback.
		 *
		 * @param string $capability The capability, default is 'manage_options'.
		 */
		$this->feedback_delete_cap = apply_filters( 'accessible_feedback_override_feedback_delete_cap', 'manage_options' );

		$this->admin_url = admin_url( 'options-general.php?page=accessible-feedback' );

		$this->plugin_url = plugin_dir_url( __FILE__ );

		$this->cache_ver = '1.0';

		/**
		 * Whether to minify scripts or not.
		 *
		 * @param bool $minify True to minify, false to not.
		 */
		$this->minify = apply_filters( 'accessible_content_override_minify_scripts', true );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace . '/v' . $this->version, '/process-data', array(
			'methods' => 'POST',
			'callback' => array( $this, 'process_data_callback' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'feedback_item' => array(
					'required' => true,
					'sanitize_callback' => function( $value ) {
						return sanitize_text_field( wp_unslash( $value ) );
					},
					'validate_callback' => function( $value ) {
						if ( empty( $value ) ) {
							return new WP_Error( 'missing_feedback_entry', __( 'Missing form data: feedback_entry.', $this->td ), array( 'status' => 500 ) );
						}
					},
				),
			),
		) );

		register_rest_route( $this->namespace . '/v' . $this->version, '/view-data', array(
			'methods' => 'GET',
			'callback' => array( $this, 'view_data_callback' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'feedback_item_index' => array(
					'default' => '',
					'sanitize_callback' => function( $value ) {
						return sanitize_text_field( wp_unslash( $value ) );
					},
					'validate_callback' => function() {
						$option = get_option( $this->option_name );
						if ( empty( $option ) ) {
							return __( 'There is no feedback to display.', $this->td );
						}
					},
				),
			),
		) );
	}

	/**
	 * Callback function for process_data.
	 *
	 * @param object $request WP_REST_Request object.
	 *
	 * @return object WP_REST_Response.
	 */
	public function process_data_callback( WP_REST_Request $request ) {
		$option = get_option( $this->option_name );
		if ( false === $option ) {
			$option = array();
		} else {
			$option[] = $request['feedback_item'];
			$add = update_option( $this->option_name, $option );
		}
		if ( true !== $add ) {
			return new WP_Error( 'save_error', __( 'Something went wrong saving your entry. Please try again later.', $this->td ), array( 'status' => 500 ) );
		}
		return __( 'Feedback collected, thank you!', $this->td );
	}

	/**
	 * Callback function for view_data.
	 *
	 * @param object $request WP_REST_Request object.
	 *
	 * @return object WP_REST_Response.
	 */
	public function view_data_callback( WP_REST_Request $request ) {
		$option = get_option( $this->option_name );
		if ( '' == $request['feedback_item_index'] ) { // Let's return all.
			return $option;
		} else { // Let's return one.
			if ( ! is_numeric( $request['feedback_item_index'] ) ) {
				return new WP_Error( 'non_int', __( 'Please pass a numerical integer.', $this->td ), array( 'status' => 500 ) );
			}
			if ( ! array_key_exists( $request['feedback_item_index'], $option ) ) {
				return new WP_Error( 'missing_data', __( 'It is not possible to GET an option that does not exist.', $this->td ), array( 'status' => 500 ) );
			}
			return $option[$request['feedback_item_index']];
		}
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		if ( true == $this->minify ) {
			wp_register_script( 'accessible_feedback', $this->plugin_url . 'public/js/form-submit.min.js', array( 'jquery' ), $this->cache_ver );
		} else {
			wp_register_script( 'accessible_feedback', $this->plugin_url . 'public/js/form-submit.js', array( 'jquery' ), $this->cache_ver );
		}
	}

	/**
	 * Output feedback form, shortcode callback.
	 */
	public function render_feedback_form() {
		wp_enqueue_script( 'accessible_feedback' );
		$submit_url = get_rest_url( null, $this->namespace . '/v' . $this->version . '/process-data' );
		$output = '<div class="accessible-feedback-form-response"></div>';
		$output .= '<form class="accessible-feedback-form" action="' . esc_url( $submit_url ) . '" method="POST" />';
			$output .= '<label for="feedback_item">' . esc_html( __( 'What would you like to see change about our site?', $this->td ) ) . '</label>';
			$output .= '<input type="text" id="feedback_item" name="feedback_item" required/>';
			$output .= '<input type="submit" id="feedback_submit" value="' . esc_attr( __( 'Send Feedback Now!', $this->td ) ) . '" />';
		$output .= '</form>';
		return $output;
		}

	/**
	 * Register admin page.
	 */
	public function admin_page() {
		if ( ! current_user_can( $this->admin_cap ) ) {
			return;
		}
		add_submenu_page( 'options-general.php', __( 'Accessible Feedback', $this->td ), __( 'Accessible Feedback', $this->td ), $this->admin_cap, 'accessible-feedback', array( $this, 'admin_page_output' ) );
	}

	/**
	 * Register admin page callback.
	 */
	public function admin_page_output() {
		if ( ! current_user_can( $this->admin_cap ) ) {
			return;
		}
		$option = get_option( $this->option_name );
		?>
		<div id="wrap">
			<h1><?php echo esc_html( __( 'Accessible Feedback', $this->td ) ); ?></h1>
			<?php
			if ( ! empty( $_GET['action'] ) && 'delete' == $_GET['action'] ) {
				if ( current_user_can( $this->feedback_delete_cap ) ) {
					$option_index = sanitize_text_field( wp_unslash( $_GET['option_index'] ) );
					if ( is_numeric( $option_index ) && array_key_exists( $option_index, $option ) ) {
						unset( $option[$option_index] );
						$option = array_values( $option ); // Required to make sure array starts at key 0.
						if ( update_option( $this->option_name, $option ) ) {
							echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( __( 'Successfully removed feedback entry.', $this->td ) ) . '</p></div>';
						} else {
							echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( __( 'There was an error removing the feedback entry.', $this->td ) ) . '</p></div>';
						}
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( __( 'You cannot delete a feedback entry that doesn\'t exist.', $this->td ) ) . '</p></div>';
					}
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( __( 'You do not have permission to delete this feedback item.', $this->td ) ) . '</p></div>';
				}
			}
			if ( empty( $option) ) {
				echo '<p>' . esc_html( __( 'No Feedback to display.', $this->td ) ) . '</p>';
			} else {
				?>
				<table style="width:100%;">
					<tbody>
						<tr>
							<th>Feedback</th>
							<th>Actions</th>
						</tr>
						<?php
						$option_count = count( $option );
						for ( $index = 0; $index < $option_count; $index++ ) {
							$delete_url = $this->admin_url . '&action=delete&option_index=' . $index;
							echo '<tr>';
								echo '<td>' . esc_html( $option[$index] ) . '</td>';
								echo '<td><a href="' . esc_url( $delete_url ) . '">' . esc_html( __( 'Delete', $this->td ) ) . '</a>';
							echo '</tr>';
						}
						?>
					</tbody>
				</table>
			<?php
			}
			?>
		</div>
		<?php
	}

}

$accessible_feedback = Accessible_Feedback_Plugin::get_instance();
