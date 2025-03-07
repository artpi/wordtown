<?php

/**
 * Replicate API integration for WordPress.
 *
 * @package WordTown
 */

class Replicate {
	/**
	 * Option name for storing the API key.
	 *
	 * @var string
	 */
	private $option_name = 'replicate_api_key';

	/**
	 * Initialize the class and set up hooks.
	 */
	public function __construct() {
		// Add admin menu and settings.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to the WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Replicate API Settings', 'wordtown' ),
			__( 'Replicate API', 'wordtown' ),
			'manage_options',
			'replicate-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings for the Replicate API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'replicate_settings_group',
			$this->option_name,
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section(
			'replicate_settings_section',
			__( 'API Configuration', 'wordtown' ),
			array( $this, 'render_settings_section' ),
			'replicate-settings'
		);

		add_settings_field(
			'replicate_api_key_field',
			__( 'API Key', 'wordtown' ),
			array( $this, 'render_api_key_field' ),
			'replicate-settings',
			'replicate_settings_section'
		);
	}

	/**
	 * Render the settings section description.
	 *
	 * @return void
	 */
	public function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Enter your Replicate API key to enable integration with Replicate services.', 'wordtown' ) . '</p>';
	}

	/**
	 * Render the API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$api_key = get_option( $this->option_name, '' );
		?>
		<input type="password" 
			id="<?php echo esc_attr( $this->option_name ); ?>" 
			name="<?php echo esc_attr( $this->option_name ); ?>" 
			value="<?php echo esc_attr( $api_key ); ?>" 
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'You can find your API key in your Replicate account settings.', 'wordtown' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'replicate_settings_group' );
				do_settings_sections( 'replicate-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the Replicate API key.
	 *
	 * @return string The API key or empty string if not set.
	 */
	public function get_api_key(): string {
		return get_option( $this->option_name, '' );
	}
}
