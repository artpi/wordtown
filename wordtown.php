<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Plugin Name:     WordTown
 * Description:     A game where you build town by posting on your blog.
 * Version:         0.5.1
 * Author:          Artur Piszek (artpi)
 * Author URI:      https://piszek.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     wordtown
 *
 * @package         artpi
 */

// Include the Replicate class.
$replicate = false;

/**
 * Add settings page to the WordPress admin menu.
 *
 * @return void
 */
function wordtown_add_settings_page(): void {
	add_options_page(
		__( 'WordTown Settings', 'wordtown' ),
		__( 'WordTown', 'wordtown' ),
		'manage_options',
		'wordtown-settings',
		'wordtown_render_settings_page'
	);
}
add_action( 'admin_menu', 'wordtown_add_settings_page' );

/**
 * Register settings for WordTown.
 *
 * @return void
 */
function wordtown_register_settings(): void {
	// Register replicate_api_key setting
	register_setting(
		'wordtown_settings_group',
		'replicate_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	// Register wordtown_system_prompt setting
	register_setting(
		'wordtown_settings_group',
		'wordtown_system_prompt',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	// Register wordtown_tile_image setting
	register_setting(
		'wordtown_settings_group',
		'wordtown_tile_image',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	// Add settings section
	add_settings_section(
		'wordtown_settings_section',
		__( 'WordTown Configuration', 'wordtown' ),
		'wordtown_render_settings_section',
		'wordtown-settings'
	);

	// Add settings fields
	add_settings_field(
		'replicate_api_key_field',
		__( 'Replicate API Key', 'wordtown' ),
		'wordtown_render_api_key_field',
		'wordtown-settings',
		'wordtown_settings_section'
	);

	add_settings_field(
		'wordtown_system_prompt_field',
		__( 'System Prompt', 'wordtown' ),
		'wordtown_render_system_prompt_field',
		'wordtown-settings',
		'wordtown_settings_section'
	);

	add_settings_field(
		'wordtown_tile_image_field',
		__( 'Base Tile Image URL', 'wordtown' ),
		'wordtown_render_tile_image_field',
		'wordtown-settings',
		'wordtown_settings_section'
	);
}
add_action( 'admin_init', 'wordtown_register_settings' );

/**
 * Render the settings section description.
 *
 * @return void
 */
function wordtown_render_settings_section(): void {
	echo '<p>' . esc_html__( 'Configure your WordTown settings below.', 'wordtown' ) . '</p>';
}

/**
 * Render the API key field.
 *
 * @return void
 */
function wordtown_render_api_key_field(): void {
	$api_key = get_option( 'replicate_api_key', '' );
	?>
	<input type="password" 
		id="replicate_api_key" 
		name="replicate_api_key" 
		value="<?php echo esc_attr( $api_key ); ?>" 
		class="regular-text"
	/>
	<p class="description">
		<?php esc_html_e( 'Enter your Replicate API key to enable tile generation.', 'wordtown' ); ?>
	</p>
	<?php
}

/**
 * Render the system prompt field.
 *
 * @return void
 */
function wordtown_render_system_prompt_field(): void {
	$system_prompt = get_option( 'wordtown_system_prompt', 'You are a creative assistant that creates prompts for generating isometric game tiles. Create a detailed prompt for an isometric tile that represents the content of a blog post. The prompt should describe a building or structure in an isometric game style. Make sure it is isometric game tile style, detailed pixel art, red alert style, high quality, sharp details.' );
	?>
	<textarea 
		id="wordtown_system_prompt" 
		name="wordtown_system_prompt" 
		class="large-text" 
		rows="5"
	><?php echo esc_textarea( $system_prompt ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'Enter the system prompt for generating tile descriptions.', 'wordtown' ); ?>
	</p>
	<?php
}

/**
 * Render the tile image field.
 *
 * @return void
 */
function wordtown_render_tile_image_field(): void {
	$tile_image = get_option( 'wordtown_tile_image', 'https://github.com/artpi/wordtown/raw/refs/heads/main/assets/tiletree.png' );
	?>
	<input type="url" 
		id="wordtown_tile_image" 
		name="wordtown_tile_image" 
		value="<?php echo esc_url( $tile_image ); ?>" 
		class="regular-text"
	/>
	<p class="description">
		<?php esc_html_e( 'Enter the URL of the base tile image to use for generation.', 'wordtown' ); ?>
	</p>
	<?php
}

/**
 * Render the settings page.
 *
 * @return void
 */
function wordtown_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'wordtown_settings_group' );
			do_settings_sections( 'wordtown-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Register WP-Cron job for generating isometric tiles.
 */
add_action( 'init', 'wordtown_register_cron_job' );

/**
 * Register the WP-Cron job for generating isometric tiles.
 *
 * @return void
 */
function wordtown_register_cron_job(): void {
	global $replicate;
	// Add a hook for single post processing
	add_action( 'wordtown_generate_tile_for_post', 'wordtown_generate_tile_for_post' );

	// Add hook for post publishing
	if ( get_option( 'replicate_api_key', false ) ) {
		add_action( 'transition_post_status', 'wordtown_schedule_tile_on_publish', 10, 3 );
	}
	require_once plugin_dir_path( __FILE__ ) . 'replicate.php';
	$replicate = new Replicate();
}

/**
 * Schedule tile generation when a post is published.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 * @return void
 */
function wordtown_schedule_tile_on_publish( string $new_status, string $old_status, WP_Post $post ): void {
	// Only proceed if this is a post being published for the first time
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
		return;
	}

	// Check if the post already has a tile
	$existing_tile = get_post_meta( $post->ID, 'wordtown_tile', true );
	if ( ! empty( $existing_tile ) ) {
		return; // Skip if already has a tile
	}

	// Schedule an immediate single event to generate a tile
	$job_id = wp_schedule_single_event( time() + 10, 'wordtown_generate_tile_for_post', array( $post->ID ) );
	if ( ! $job_id ) {
		return;
	}

	// Add a post meta to indicate that tile generation has been scheduled
	update_post_meta( $post->ID, 'wordtown_tile_scheduled', current_time( 'mysql' ) );
}

/**
 * Generate an isometric tile for a specific post.
 *
 * @param int $post_id The ID of the post to generate a tile for.
 * @return void
 */
function wordtown_generate_tile_for_post( int $post_id, $blocking = false ): void {
	global $replicate;
	// Check if the post already has a tile
	$existing_tile = get_post_meta( $post_id, 'wordtown_tile', true );
	if ( ! empty( $existing_tile ) ) {
		return; // Skip if already has a tile
	}

	// Get post data
	$post = get_post( $post_id );
	if ( ! $post ) {
		return; // Post doesn't exist
	}

	// Get post title, content, and categories
	$title = $post->post_title;
	$content = wp_strip_all_tags( $post->post_content );
	$content = substr( $content, 0, 8000 ); // Limit content length

	// Get categories
	$categories = array();
	$terms = wp_get_post_terms( $post_id, 'category' );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$categories[] = $term->name;
		}
	}
	$categories_str = implode( ', ', $categories );

	// Initialize Replicate

	// Create a prompt for the isometric tile
	$system_prompt = get_option( 'wordtown_system_prompt' );

	$user_prompt = sprintf(
		"Create a prompt for an isometric game tile based on this blog post:\nTitle: %s\nCategories: %s\nContent: %s",
		$title,
		$categories_str,
		$content
	);

	// Get the tile prompt from the text completion API
	$tile_prompt = $replicate->text_completion( $user_prompt, $system_prompt );

	if ( is_wp_error( $tile_prompt ) ) {
		// Log error and exit
		error_log( 'WordTown tile generation error: ' . $tile_prompt->get_error_message() );
		return;
	}

	$enhanced_prompt = $tile_prompt;

	// Model version for image generation
	$model_version = 'ca8350ff748d56b3ebbd5a12bd3436c2214262a4ff8619de9890ecc41751a008';

	// Build input parameters for the model
	$input = array(
		'mask'           => 'https://github.com/artpi/wordtown/raw/refs/heads/main/assets/mask.png',
		'image'          => get_option( 'wordtown_tile_image' ),
		'prompt'         => $enhanced_prompt,
		'height'         => 512,
		'width'          => 1024,
		'output_format'  => 'png',
		'strength'       => (float) 0.82,
		'output_quality' => (int) 90,
	);

	update_post_meta( $post_id, 'wordtown_tile_prompt', $enhanced_prompt );
	// Run the prediction
	$replicate->create_prediction(
		$model_version,
		$input,
		array(
			'post_id'  => $post_id,
			'blocking' => $blocking,
		)
	);
}

/**
 * Register WP-CLI commands.
 *
 * Note: Linter errors related to WP_CLI classes and functions are expected
 * and can be ignored. These classes are only available when running WordPress
 * with WP-CLI installed.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * WordTown CLI commands.
	 */
	class WordTown_CLI {
		/**
		 * Generate a tile for a specific post.
		 *
		 * ## OPTIONS
		 *
		 * <post_id>
		 * : The ID of the post to generate a tile for.
		 *
		 * ## EXAMPLES
		 *
		 *     wp wordtown generate 123
		 *
		 * @param array $args       Command arguments.
		 * @param array $assoc_args Command associated arguments.
		 * @return void
		 */
		public function generate( array $args, array $assoc_args ): void {
			$post_id = (int) $args[0];
			$post = get_post( $post_id );

			if ( ! $post ) {
				\WP_CLI::error( "Post with ID {$post_id} not found." );
				return;
			}

			if ( strlen( $post->post_content ) < 20 ) {
				\WP_CLI::error( 'Post content is too short for tile generation.' );
				return;
			}

			// Remove any existing tile data
			delete_post_meta( $post_id, 'wordtown_tile' );
			delete_post_meta( $post_id, 'wordtown_tile_prompt' );
			delete_post_meta( $post_id, 'wordtown_tile_scheduled' );

			\WP_CLI::log( "Generating tile for post: {$post->post_title} (ID: {$post_id})" );

			// Generate the tile immediately (not via cron)
			wordtown_generate_tile_for_post( $post_id, true );

			\WP_CLI::success( "Tile generation process started for post ID: {$post_id}" );
		}
	}

	\WP_CLI::add_command( 'wordtown', 'WordTown_CLI' );
}
// phpcs:enable

/**
 * Register REST API endpoints.
 */
add_action( 'rest_api_init', 'wordtown_register_rest_routes' );

add_action( 'replicate_prediction_uploaded', 'wordtown_handle_prediction', 10, 2 );

function wordtown_handle_prediction( $result, $meta ) {

	error_log( 'Replicate: Prediction uploaded ' . print_r( array( $meta, $result ), true ) );
	if ( is_array( $result ) && ! empty( $result ) ) {
		// Use the first media ID
		$media_id = $result[0];

		// Update post meta with the media ID
		update_post_meta( $meta['post_id'], 'wordtown_tile', $media_id );

		// Clean up the job ID
		delete_post_meta( $meta['post_id'], 'wordtown_tile_scheduled' );
	}
}

/**
 * Register the REST API routes for WordTown.
 *
 * @return void
 */
function wordtown_register_rest_routes(): void {
	register_rest_route(
		'wordtown/v1',
		'/tiles',
		array(
			'methods'             => 'GET',
			'callback'            => 'wordtown_get_tiles',
			'permission_callback' => '__return_true', // Unauthenticated endpoint
		)
	);
	register_rest_route(
		'wordtown/v1',
		'/posts/(?P<post_id>\d+)/generate',
		array(
			'methods'             => 'POST',
			'callback'            => function( \WP_REST_Request $request ) {
				$post_id = $request->get_param( 'post_id' );
				$post = get_post( $post_id );
				if ( ! $post ) {
					return new \WP_REST_Response( 'Post not found', 404 );
				}
				if ( ! get_option( 'replicate_api_key', false ) ) {
					return new \WP_REST_Response( 'Replicate API key not set', 400 );
				}
				if ( strlen( $post->post_content ) < 20 ) {
					return new \WP_REST_Response( 'Post content is too short', 400 );
				}
				// Check if the post already has a tile
				delete_post_meta( $post->ID, 'wordtown_tile' );
				delete_post_meta( $post->ID, 'wordtown_tile_prompt' );
				delete_post_meta( $post->ID, 'wordtown_tile_scheduled' );
				// Schedule an immediate single event to generate a tile
				$job_id = wp_schedule_single_event( time() + 10, 'wordtown_generate_tile_for_post', array( $post->ID ) );
				if ( ! $job_id ) {
					return new \WP_REST_Response( 'Failed to schedule tile generation', 500 );
				}

				// Add a post meta to indicate that tile generation has been scheduled
				update_post_meta( $post->ID, 'wordtown_tile_scheduled', current_time( 'mysql' ) );
				return array(
					'success'                 => true,
					'wordtown_tile_scheduled' => current_time( 'mysql' ),
				);
			},
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

/**
 * Get posts with WordTown tiles.
 *
 * @param \WP_REST_Request $request The request object.
 * @return \WP_REST_Response The response object.
 */
function wordtown_get_tiles( \WP_REST_Request $request ): \WP_REST_Response {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => 'wordtown_tile',
				'compare' => 'EXISTS',
			),
		),
	);

	$query = new \WP_Query( $args );
	$tiles = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$tile_id = get_post_meta( $post_id, 'wordtown_tile', true );

			// Only include posts that actually have a tile ID
			if ( ! empty( $tile_id ) ) {
				$tile_image = wp_get_attachment_url( $tile_id );

				//for ( $i = 0; $i < 100; $i++ ) {
				$tiles[] = array(
					'post_id'      => $post_id,
					'post_title'   => get_the_title(),
					'post_date'    => get_the_date( 'c' ),
					'tile_id'      => (int) $tile_id,
					'tile_url'     => $tile_image ? $tile_image : '',
					'post_url'     => get_the_permalink(),
					'post_excerpt' => get_the_excerpt(),
				);
				//}
			}
		}
		wp_reset_postdata();
	}
	$response = new \WP_REST_Response( $tiles, 200 );

	return $response;
}

add_action(
	'parse_request',
	function ( $wp ) {
		if ( $wp->request === 'wordtown' ) {
			status_header( 200 );
			header( 'Content-Type: text/html' );
			wp_enqueue_script( 'wordtown-frontend', plugin_dir_url( __FILE__ ) . 'scripts/wordtown.js', array( 'wp-api-fetch' ), '1.0.0', true );
			include plugin_dir_path( __FILE__ ) . 'wordtown-frontend.php';
			exit;
		}
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script(
			'wordtown-sidebar-js',
			plugin_dir_url( __FILE__ ) . 'scripts/wordtown-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
			null,
			true
		);
	}
);

/**
 * Register post meta for REST API access.
 */
add_action( 'init', 'wordtown_register_post_meta' );

/**
 * Register the wordtown_tile post meta field for REST API access.
 *
 * @return void
 */
function wordtown_register_post_meta(): void {
	register_post_meta(
		'post',
		'wordtown_tile',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'integer',
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	// Also register the prompt meta if you want to access it directly
	register_post_meta(
		'post',
		'wordtown_tile_prompt',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		)
	);
	register_post_meta(
		'post',
		'wordtown_tile_scheduled',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
