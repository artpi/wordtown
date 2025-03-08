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
require_once plugin_dir_path( __FILE__ ) . 'replicate.php';

// Initialize the Replicate class.
// $replicate = new Replicate();

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
	// Register the cron event if it's not already scheduled
	// if ( ! wp_next_scheduled( 'wordtown_generate_tile_event' ) ) {
	// 	wp_schedule_event( time(), 'hourly', 'wordtown_generate_tile_event' );
	// }

	// Hook the function to the event
	//add_action( 'wordtown_generate_tile_event', 'wordtown_process_posts_for_tiles' );

	// Add a hook for single post processing
	add_action( 'wordtown_generate_tile_for_post', 'wordtown_generate_tile_for_post' );
	
	// Add hook for post publishing
	add_action( 'transition_post_status', 'wordtown_schedule_tile_on_publish', 10, 3 );
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
	wp_schedule_single_event( time() + 10, 'wordtown_generate_tile_for_post', array( $post->ID ) );
	
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
function wordtown_generate_tile_for_post( int $post_id ): void {
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
	$replicate = new Replicate();

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

	// Media data for the attachment
	$media_data = [
		'post_title'  => sprintf( 'WordTown Tile for: %s', $title ),
		'post_status' => 'private',
	];

	// Run the prediction
	$result = $replicate->create_prediction( $model_version, $input, $media_data );

	if ( is_wp_error( $result ) ) {
		// Log error and exit
		error_log( 'WordTown tile generation error: ' . $result->get_error_message() );
		return;
	}

	// The result should be an array of media IDs
	if ( is_array( $result ) && ! empty( $result ) ) {
		// Use the first media ID
		$media_id = $result[0];
		
		// Update post meta with the media ID
		update_post_meta( $post_id, 'wordtown_tile', $media_id );
		
		// Also store the prompt used to generate the tile
		update_post_meta( $post_id, 'wordtown_tile_prompt', $enhanced_prompt );
	}
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
		 * Run a Replicate prediction and upload the result to the media library.
		 *
		 * ## OPTIONS
		 *
		 * [--model-version=<model_version>]
		 * : The model version to use for prediction.
		 * ---
		 * default: ca8350ff748d56b3ebbd5a12bd3436c2214262a4ff8619de9890ecc41751a008
		 * ---
		 *
		 * [--mask=<mask_url>]
		 * : URL to the mask image.
		 * ---
		 * default: https://github.com/artpi/wordtown/raw/refs/heads/main/assets/mask.png
		 * ---
		 *
		 * [--image=<image_url>]
		 * : URL to the source image.
		 * ---
		 * default: https://github.com/artpi/wordtown/raw/refs/heads/main/assets/tiletree.png
		 * ---
		 *
		 * [--prompt=<prompt>]
		 * : The text prompt for image generation.
		 * ---
		 * default: isometric game tile, building covered with vines, red alert style. Detailed construction with a satellite dish like in an isometric game
		 * ---
		 *
		 * [--strength=<strength>]
		 * : The strength parameter for the model.
		 * ---
		 * default: 1
		 * ---
		 *
		 * [--output-quality=<output_quality>]
		 * : The output quality parameter for the model.
		 * ---
		 * default: 90
		 * ---
		 *
		 * [--title=<title>]
		 * : Title for the uploaded media.
		 * ---
		 * default: Replicate Generated Image
		 * ---
		 *
		 * [--status=<status>]
		 * : Post status for the uploaded media.
		 * ---
		 * default: private
		 * options:
		 *   - private
		 *   - publish
		 *   - draft
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     # Run prediction with default parameters
		 *     $ wp wordtown run_prediction
		 *
		 *     # Run prediction with custom prompt
		 *     $ wp wordtown run_prediction --prompt="beautiful sunset over mountains"
		 *
		 *     # Run prediction with custom model version and title
		 *     $ wp wordtown run_prediction --model-version=abc123 --title="My Custom Image"
		 *
		 * @param array $args       Command arguments.
		 * @param array $assoc_args Command options.
		 */
		public function run_prediction( $args, $assoc_args ) {
			$replicate = new Replicate();

			// Parse command arguments
			$model_version = $assoc_args['model-version'];
			$title = $assoc_args['title'];
			$status = $assoc_args['status'];

			// Build input parameters for the model
			$input = [
				'mask'           => $assoc_args['mask'],
				'image'          => $assoc_args['image'],
				'prompt'         => $assoc_args['prompt'],
				'height'         => 512,
				'width'          => 1024,
				'output_format'  => 'png',
				'strength'       => (float) 0.82,
				'output_quality' => (int) 90,
			];

			// Media data for the attachment
			$media_data = [
				'post_title'  => $title,
				'post_status' => $status,
			];

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar, WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
			\WP_CLI::log( sprintf( 'Starting prediction with model version: %s', $model_version ) );
			\WP_CLI::log( sprintf( 'Using prompt: "%s"', $input['prompt'] ) );


			// Run the prediction
			$result = $replicate->create_prediction( $model_version, $input, $media_data );
			\WP_CLI::log( print_r( $result, true ) );
		}

		/**
		 * Crop an image using a mask, making black parts of the mask transparent.
		 *
		 * ## OPTIONS
		 *
		 * [--image=<image_path>]
		 * : Path to the source image.
		 * ---
		 * default: wp-content/uploads/2025/03/output_0-1.png
		 * ---
		 *
		 * [--mask=<mask_path>]
		 * : Path to the mask image.
		 * ---
		 * default: assets/mask.png
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     # Crop image with default parameters
		 *     $ wp wordtown crop_image
		 *
		 *     # Crop image with custom paths
		 *     $ wp wordtown crop_image --image=wp-content/uploads/my-image.png --mask=assets/custom-mask.png
		 *
		 * @param array $args       Command arguments.
		 * @param array $assoc_args Command options.
		 */
		public function crop_image( $args, $assoc_args ) {
			$replicate = new Replicate();

			// Get the image and mask paths
			$image_path = ABSPATH . $assoc_args['image'];
			$mask_path = plugin_dir_path( __FILE__ ) . $assoc_args['mask'];

			// Log the paths
			\WP_CLI::log( sprintf( 'Image path: %s', $image_path ) );
			\WP_CLI::log( sprintf( 'Mask path: %s', $mask_path ) );

			// Check if files exist
			if ( ! file_exists( $image_path ) ) {
				\WP_CLI::error( sprintf( 'Image file not found: %s', $image_path ) );
				return;
			}

			if ( ! file_exists( $mask_path ) ) {
				\WP_CLI::error( sprintf( 'Mask file not found: %s', $mask_path ) );
				return;
			}

			// Check image format
			$image_info = getimagesize( $image_path );
			if ( ! $image_info ) {
				\WP_CLI::error( 'Could not determine image format. The file may be corrupted.' );
				return;
			}
			\WP_CLI::log( sprintf( 'Image format: %s', image_type_to_mime_type( $image_info[2] ) ) );

			// Check for ImageMagick/GMagick support
			$has_imagick = extension_loaded( 'imagick' );
			$has_gmagick = extension_loaded( 'gmagick' );
			
			\WP_CLI::log( sprintf( 'ImageMagick extension available: %s', $has_imagick ? 'Yes' : 'No' ) );
			\WP_CLI::log( sprintf( 'GMagick extension available: %s', $has_gmagick ? 'Yes' : 'No' ) );
			
			// Check ImageMagick formats if available
			if ( $has_imagick ) {
				try {
					$imagick = new \Imagick();
					$formats = $imagick->queryFormats();
					print_r( $formats );
					\WP_CLI::log( sprintf( 'ImageMagick supported formats: %s', implode( ', ', array_slice( $formats, 0, 10 ) ) . '...' ) );
					
					if ( ! in_array( 'PNG', $formats, true ) ) {
						\WP_CLI::warning( 'ImageMagick does not have PNG support. This may cause issues.' );
					}
				} catch ( \Exception $e ) {
					\WP_CLI::warning( sprintf( 'Error checking ImageMagick formats: %s', $e->getMessage() ) );
				}
			}

			\WP_CLI::log( 'Cropping image with mask...' );

			// Crop the image
			$result = $replicate->crop_image_with_mask( $image_path, $mask_path );

			if ( is_wp_error( $result ) ) {
				$error_message = $result->get_error_message();
				
				// Check for specific error about PNG format
				if ( strpos( $error_message, 'NoDecodeDelegateForThisImageFormat' ) !== false && 
					 strpos( $error_message, 'PNG' ) !== false ) {
					\WP_CLI::error( 
						"ImageMagick cannot process PNG images because it was compiled without PNG support.\n" .
						"To fix this:\n" .
						"1. Make sure libpng is installed on your system\n" .
						"2. Reinstall ImageMagick with PNG support\n" .
						"3. Or convert your images to JPEG format before processing\n\n" .
						"Original error: " . $error_message
					);
				} else {
					\WP_CLI::error( $error_message );
				}
				return;
			}

			\WP_CLI::success( sprintf( 'Image successfully cropped. Output saved to: %s', $result ) );
		}

		public function text_completion( $args, $assoc_args ) {
			$replicate = new Replicate();
			$result = $replicate->text_completion( "What is the capitol of france?" );
			\WP_CLI::log( print_r( $result, true ) );
		}

		/**
		 * Generate an isometric tile for a specific post.
		 *
		 * ## OPTIONS
		 *
		 * <post_id>
		 * : The ID of the post to generate a tile for.
		 *
		 * [--force]
		 * : Force regeneration even if the post already has a tile.
		 *
		 * ## EXAMPLES
		 *
		 *     # Generate a tile for post with ID 123
		 *     $ wp wordtown generate_tile 123
		 *
		 *     # Force regeneration of a tile for post with ID 123
		 *     $ wp wordtown generate_tile 123 --force
		 *
		 * @param array $args       Command arguments.
		 * @param array $assoc_args Command options.
		 */
		public function generate_tile( $args, $assoc_args ) {
			if ( empty( $args[0] ) ) {
				\WP_CLI::error( 'Post ID is required.' );
				return;
			}

			$post_id = (int) $args[0];
			$force = isset( $assoc_args['force'] );

			// Check if post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				\WP_CLI::error( sprintf( 'Post with ID %d does not exist.', $post_id ) );
				return;
			}

			// Check if post already has a tile
			$existing_tile = get_post_meta( $post_id, 'wordtown_tile', true );
			if ( ! empty( $existing_tile ) && ! $force ) {
				\WP_CLI::warning( sprintf( 'Post %d already has a tile (Media ID: %d). Use --force to regenerate.', $post_id, $existing_tile ) );
				return;
			}

			\WP_CLI::log( sprintf( 'Generating tile for post: %s (ID: %d)', $post->post_title, $post_id ) );

			// If forcing regeneration, delete the existing meta
			if ( $force && ! empty( $existing_tile ) ) {
				delete_post_meta( $post_id, 'wordtown_tile' );
				delete_post_meta( $post_id, 'wordtown_tile_prompt' );
				\WP_CLI::log( 'Removed existing tile metadata.' );
			}

			// Generate the tile
			wordtown_generate_tile_for_post( $post_id );

			// Check if generation was successful
			$new_tile = get_post_meta( $post_id, 'wordtown_tile', true );
			if ( ! empty( $new_tile ) ) {
				\WP_CLI::success( sprintf( 'Tile generated successfully. Media ID: %d', $new_tile ) );
				
				// Get the prompt used
				$prompt = get_post_meta( $post_id, 'wordtown_tile_prompt', true );
				if ( ! empty( $prompt ) ) {
					\WP_CLI::log( sprintf( 'Prompt used: "%s"', $prompt ) );
				}
			} else {
				\WP_CLI::error( 'Failed to generate tile. Check error logs for details.' );
			}
		}
	}

	\WP_CLI::add_command( 'wordtown', 'WordTown_CLI' );
}
// phpcs:enable

