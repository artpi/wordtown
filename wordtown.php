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
	}

	\WP_CLI::add_command( 'wordtown', 'WordTown_CLI' );
}
// phpcs:enable

