<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Plugin Name:     WordTown
 * Description:     A game where you build town by posting on your blog.
 * Version:         0.1.0
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
	add_action( 'transition_post_status', 'wordtown_schedule_tile_on_publish', 10, 3 );
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
 * Process posts that need isometric tiles generated.
 *
 * @return void
 */
// function wordtown_process_posts_for_tiles(): void {
// 	// Query for posts that don't have a tile yet
// 	$args = array(
// 		'post_type'      => 'post',
// 		'post_status'    => 'publish',
// 		'posts_per_page' => 5, // Process 5 posts at a time to avoid timeouts
// 		'meta_query'     => array(
// 			array(
// 				'key'     => 'wordtown_tile',
// 				'compare' => 'NOT EXISTS',
// 			),
// 		),
// 	);

// 	$query = new WP_Query( $args );

// 	if ( $query->have_posts() ) {
// 		while ( $query->have_posts() ) {
// 			$query->the_post();
// 			// Schedule an immediate single event for each post
// 			wp_schedule_single_event( time(), 'wordtown_generate_tile_for_post', array( get_the_ID() ) );
// 		}
// 	}

// 	wp_reset_postdata();
// }

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
	$system_prompt = 'You are a creative assistant that creates prompts for generating isometric game tiles. Create a detailed prompt for an isometric tile that represents the content of a blog post. The prompt should describe a building or structure in an isometric game style.';
	
	$user_prompt = sprintf(
		"Create a prompt for an isometric game tile based on this blog post:\nTitle: %s\nCategories: %s\nContent summary: %s\n\nThe prompt should describe a detailed isometric building or structure that represents the theme and content of this post. Make it specific and visual, suitable for an AI image generator.",
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

	// Enhance the prompt with isometric style requirements
	$enhanced_prompt = $tile_prompt . ' Isometric game tile style, detailed pixel art, red alert style, high quality, sharp details.';

	// Model version for image generation
	$model_version = 'ca8350ff748d56b3ebbd5a12bd3436c2214262a4ff8619de9890ecc41751a008';

	// Build input parameters for the model
	$input = [
		'mask'           => 'https://github.com/artpi/wordtown/raw/refs/heads/main/assets/mask.png',
		'image'          => 'https://github.com/artpi/wordtown/raw/refs/heads/main/assets/tiletree.png',
		'prompt'         => $enhanced_prompt,
		'height'         => 512,
		'width'          => 1024,
		'output_format'  => 'png',
		'strength'       => (float) 0.82,
		'output_quality' => (int) 90,
	];

	update_post_meta( $post_id, 'wordtown_tile_prompt', $enhanced_prompt );
	// Run the prediction
	$replicate->create_prediction( $model_version, $input, [
		'post_id' => $post_id,
		'blocking' => $blocking,
	] );
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

	error_log( 'Replicate: Prediction uploaded ' . print_r( [$meta, $result], true ) );
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
			'args'                => array(
				'page'     => array(
					'description'       => 'Current page of the collection.',
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
				'per_page' => array(
					'description'       => 'Maximum number of items to be returned in result set.',
					'type'              => 'integer',
					'default'           => 10,
					'sanitize_callback' => 'absint',
				),
				'orderby'  => array(
					'description'       => 'Sort collection by object attribute.',
					'type'              => 'string',
					'default'           => 'date',
					'enum'              => array( 'date', 'title', 'id' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				'order'    => array(
					'description'       => 'Order sort attribute ascending or descending.',
					'type'              => 'string',
					'default'           => 'DESC',
					'enum'              => array( 'ASC', 'DESC' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				'after'    => array(
					'description'       => 'Limit response to posts published after a given ISO8601 compliant date.',
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'before'   => array(
					'description'       => 'Limit response to posts published before a given ISO8601 compliant date.',
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
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
				return [
					'success' => true,
					'wordtown_tile_scheduled' => current_time( 'mysql' ),
				];
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
	// Get pagination parameters
	$per_page = isset( $request['per_page'] ) ? (int) $request['per_page'] : 10;
	$page = isset( $request['page'] ) ? (int) $request['page'] : 1;
	
	// Get sorting parameters
	$orderby = isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date';
	$order = isset( $request['order'] ) ? strtoupper( sanitize_text_field( $request['order'] ) ) : 'DESC';
	
	// Validate order and orderby
	$valid_orderby = array( 'date', 'title', 'id' );
	$valid_order = array( 'ASC', 'DESC' );
	
	if ( ! in_array( $orderby, $valid_orderby, true ) ) {
		$orderby = 'date';
	}
	
	if ( ! in_array( $order, $valid_order, true ) ) {
		$order = 'DESC';
	}
	
	// Limit per_page to a reasonable number
	$per_page = min( 100, max( 1, $per_page ) );
	
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'orderby'        => $orderby,
		'order'          => $order,
		'meta_query'     => array(
			array(
				'key'     => 'wordtown_tile',
				'compare' => 'EXISTS',
			),
		),
	);
	
	// Add date filtering if provided
	if ( isset( $request['after'] ) ) {
		$args['date_query'][] = array(
			'after'     => sanitize_text_field( $request['after'] ),
			'inclusive' => true,
		);
	}
	
	if ( isset( $request['before'] ) ) {
		$args['date_query'][] = array(
			'before'    => sanitize_text_field( $request['before'] ),
			'inclusive' => true,
		);
	}

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
					'post_id'    => $post_id,
					'post_title' => get_the_title(),
					'post_date'  => get_the_date( 'c' ),
					'tile_id'    => (int) $tile_id,
					'tile_url'   => $tile_image ? $tile_image : '',
					'post_url'   => get_the_permalink(),
					'post_excerpt' => get_the_excerpt(),
				);
			//}
			}
		}
		wp_reset_postdata();
	}

	// Prepare pagination headers
	$total_posts = $query->found_posts;
	$total_pages = ceil( $total_posts / $per_page );
	
	$response = new \WP_REST_Response( $tiles, 200 );
	$response->header( 'X-WP-Total', $total_posts );
	$response->header( 'X-WP-TotalPages', $total_pages );

	return $response;
}

add_action('parse_request', function ($wp) {
    if ($wp->request === 'wordtown') {
        status_header(200);
        header('Content-Type: text/html');
		wp_enqueue_script( 'wordtown-frontend', plugin_dir_url( __FILE__ ) . 'scripts/wordtown.js', array( 'wp-api-fetch' ), '1.0.0', true );
		include plugin_dir_path( __FILE__ ) . 'wordtown-frontend.php';
        exit;
    }
});

add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'wordtown-sidebar-js',
        plugin_dir_url(__FILE__) . 'scripts/wordtown-sidebar.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
        null,
        true
    );
});

/**
 * Register post meta for REST API access.
 */
add_action('init', 'wordtown_register_post_meta');

/**
 * Register the wordtown_tile post meta field for REST API access.
 *
 * @return void
 */
function wordtown_register_post_meta(): void {
	register_post_meta(
		'post',
		'wordtown_tile',
		[
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'integer',
			'auth_callback' => function() {
				return current_user_can('edit_posts');
			}
		]
	);
	
	// Also register the prompt meta if you want to access it directly
	register_post_meta(
		'post',
		'wordtown_tile_prompt',
		[
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function() {
				return current_user_can('edit_posts');
			}
		]
	);
	register_post_meta(
		'post',
		'wordtown_tile_scheduled',
		[
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function() {
				return current_user_can('edit_posts');
			}
		]
	);
}