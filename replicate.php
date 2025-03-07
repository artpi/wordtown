<?php

/**
 * Replicate API integration for WordPress.
 *
 * @package WordTown
 */

use Imagick;
use Gmagick;
use Exception;
use WP_Error;

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

	/**
	 * Create a prediction using Replicate API and upload the result to media library.
	 *
	 * @param string $model_version The model version to use for prediction.
	 * @param array  $input         The input parameters for the model.
	 * @param array  $media_data    Optional. Data for the media attachment. Default empty array.
	 * @return int|WP_Error         The attachment ID on success, WP_Error on failure.
	 */
	public function create_prediction( string $model_version, array $input, array $media_data = [] ) {
		$api_key = $this->get_api_key();
		$output_images = [];
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'Replicate API key is not set.', 'wordtown' ) );
		}

		// Prepare the request data
		$request_data = [
			'version' => $model_version,
			'stream'  => true,
			'input'   => $input,			
		];

		// Make the initial prediction request
		$response = wp_remote_post(
			'https://api.replicate.com/v1/predictions',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		
		if ( ! isset( $body->urls->stream ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Replicate API.', 'wordtown' ) );
		}

		// Get the stream URL
		$stream_url = $body->urls->get;
		
		// Poll the stream until we get the output
		$max_attempts = 30;
		$attempt = 0;

		$status = 'pending';
		while ( $attempt < $max_attempts && in_array( $status, ['starting', 'processing', 'pending'] ) ) {
			sleep( 5 );
			$polling_response = wp_remote_get(
				$stream_url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					],
				]
			);
			if ( is_wp_error( $polling_response ) ) {
				return $polling_response;
			}
			$body = json_decode( wp_remote_retrieve_body( $polling_response ) );
			$status = $body->status;
			$attempt++;
		}

		if ( $status === 'succeeded' && is_array( $body->output ) ) {
			return array_map( [ $this, 'upload_image' ], $body->output );
		}

	}

	/**
	 * Upload an image from a URL to the WordPress media library.
	 *
	 * @param string $image_url The URL of the image to upload.
	 * @return int|WP_Error The attachment ID on success, WP_Error on failure.
	 */
	public function upload_image( $image_url ): int|WP_Error {
		// Download the image and upload to media library
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		
		// Download the image from the URL
		$downloaded = download_url( $image_url );
		
		if ( is_wp_error( $downloaded ) ) {
			return $downloaded;
		}
		$mask_path = __DIR__ . '/assets/mask.png';
		$temp_file = $this->crop_image_with_mask( $downloaded, $mask_path );
		
		// Get the filename from the URL
		$file_name = basename( parse_url( $image_url, PHP_URL_PATH ) );
		if ( empty( $file_name ) ) {
			$file_name = 'replicate-image-' . wp_hash( $image_url ) . '.png';
		}
		
		// Prepare file data for media_handle_sideload
		$file = [
			'name'     => $file_name,
			'type'     => wp_check_filetype( $file_name )['type'] ?: 'image/png',
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize( $temp_file ),
		];
		
		// Set default media data
		$media_data = [
			'post_title'   => pathinfo( $file_name, PATHINFO_FILENAME ),
			'post_content' => sprintf(
				/* translators: %s: Image URL */
				__( 'Image generated using Replicate API from %s', 'wordtown' ),
				esc_url( $image_url )
			),
			'post_status'  => 'inherit',
		];
		
		// Upload the image to the media library
		$media_id = media_handle_sideload( $file, 0, '', $media_data );
		
		// Clean up the temporary file if it still exists
		@unlink( $temp_file );
		
		return $media_id;
	}

	/**
	 * Crop an image using a mask, making black parts of the mask transparent.
	 *
	 * This method will use either ImageMagick or GMagick, depending on what's available.
	 * White parts of the mask will remain unchanged, while black parts will become transparent.
	 *
	 * @param string $image_path The file path of the image to crop.
	 * @param string $mask_path  The file path of the mask image.
	 * @return string|WP_Error   The path to the cropped image or WP_Error on failure.
	 * @phpstan-ignore-next-line
	 */
	public function crop_image_with_mask( string $image_path, string $mask_path ): string|WP_Error {
		// Check if the files exist
		if ( ! file_exists( $image_path ) ) {
			return new WP_Error( 'missing_image', __( 'The source image file does not exist.', 'wordtown' ) );
		}

		if ( ! file_exists( $mask_path ) ) {
			return new WP_Error( 'missing_mask', __( 'The mask image file does not exist.', 'wordtown' ) );
		}

		// Create output filename
		$path_info = pathinfo( $image_path );
		$output_path = $path_info['dirname'] . '/' . $path_info['filename'] . '-masked.' . $path_info['extension'];

		// Try to use Imagick if available
		if ( extension_loaded( 'imagick' ) ) {
			try {
				// @phpstan-ignore-next-line
				$image = new \Imagick( $image_path );
				
				// @phpstan-ignore-next-line
				$mask = new \Imagick( $mask_path );
				
				// Convert mask to grayscale if it's not already
				// @phpstan-ignore-next-line
				if ( $mask->getImageColorspace() !== \Imagick::COLORSPACE_GRAY ) {
					// @phpstan-ignore-next-line
					$mask->transformImageColorspace( \Imagick::COLORSPACE_GRAY );
				}
				
				// Resize mask to match the image dimensions if needed
				if ( $mask->getImageWidth() !== $image->getImageWidth() || 
					 $mask->getImageHeight() !== $image->getImageHeight() ) {
					$mask->resizeImage(
						$image->getImageWidth(),
						$image->getImageHeight(),
						// @phpstan-ignore-next-line
						\Imagick::FILTER_LANCZOS,
						1
					);
				}
				
				// Set the image's alpha channel based on the mask
				// White in the mask (255) = opaque, Black (0) = transparent
				// @phpstan-ignore-next-line
				$image->compositeImage( $mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0 );
				
				// Save the result
				$image->writeImage( $output_path );
				
				// Clean up
				$image->clear();
				$mask->clear();
				
				return $output_path;
			} catch ( Exception $e ) {
				return new WP_Error( 'imagick_error', $e->getMessage() );
			}
		} 
		// Try to use Gmagick if available
		elseif ( extension_loaded( 'gmagick' ) ) {
			try {
				// @phpstan-ignore-next-line
				$image = new \Gmagick( $image_path );
				
				// @phpstan-ignore-next-line
				$mask = new \Gmagick( $mask_path );
				
				// Convert mask to grayscale if needed
				// @phpstan-ignore-next-line
				if ( $mask->getimagecolorspace() !== \Gmagick::COLORSPACE_GRAY ) {
					// @phpstan-ignore-next-line
					$mask->setimagetype( \Gmagick::IMGTYPE_GRAYSCALE );
				}
				
				// Resize mask to match the image dimensions if needed
				if ( $mask->getimagewidth() !== $image->getimagewidth() || 
					 $mask->getimageheight() !== $image->getimageheight() ) {
					$mask->resizeimage(
						$image->getimagewidth(),
						$image->getimageheight(),
						// @phpstan-ignore-next-line
						\Gmagick::FILTER_LANCZOS,
						1
					);
				}
				
				// Set the image's alpha channel based on the mask
				// @phpstan-ignore-next-line
				$image->compositeimage( $mask, \Gmagick::COMPOSITE_COPYOPACITY, 0, 0 );
				
				// Save the result
				$image->writeimage( $output_path );
				
				// Clean up
				$image->destroy();
				$mask->destroy();
				
				return $output_path;
			} catch ( Exception $e ) {
				return new WP_Error( 'gmagick_error', $e->getMessage() );
			}
		} 
		// If neither extension is available, return an error
		else {
			return new WP_Error(
				'missing_image_library',
				__( 'Neither ImageMagick nor GMagick is available on this system.', 'wordtown' )
			);
		}
	}

}
