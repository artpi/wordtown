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
		 * default: https://replicate.delivery/pbxt/HtGQBqO9MtVbPm0G0K43nsvvjBB0E0PaWOhuNRrRBBT4ttbf/mask.png
		 * ---
		 *
		 * [--image=<image_url>]
		 * : URL to the source image.
		 * ---
		 * default: https://replicate.delivery/pbxt/HtGQBfA5TrqFYZBf0UL18NTqHrzt8UiSIsAkUuMHtjvFDO6p/overture-creations-5sI6fQgYIuo.png
		 * ---
		 *
		 * [--prompt=<prompt>]
		 * : The text prompt for image generation.
		 * ---
		 * default: small cute cat sat on a park bench
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
				'output_format'  => 'png',
				'strength'       => (float) $assoc_args['strength'],
				'output_quality' => (int) $assoc_args['output-quality'],
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
	}

	\WP_CLI::add_command( 'wordtown', 'WordTown_CLI' );
}
// phpcs:enable

