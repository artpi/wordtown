<?php

/**
 * Replicate API integration for WordPress.
 *
 * @package WordTown
 */

class Replicate {
	/**
	 * Initialize the class and set up hooks.
	 */
	public function __construct() {
		add_action( 'replicate_prediction', array( $this, 'handle_prediction' ), 10, 4 );
	}

	/**
	 * Get the Replicate API key.
	 *
	 * @return string The API key or empty string if not set.
	 */
	public function get_api_key(): string {
		return get_option( 'replicate_api_key', '' );
	}

	/**
	 * Create a prediction using Replicate API and upload the result to media library.
	 *
	 * @param string $model_version The model version to use for prediction.
	 * @param array  $input         The input parameters for the model.
	 * @param array  $media_data    Optional. Data for the media attachment. Default empty array.
	 * @return int|WP_Error         The attachment ID on success, WP_Error on failure.
	 */
	public function create_prediction( string $model_version, array $input, array $meta = array() ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'Replicate API key is not set.', 'wordtown' ) );
		}

		// Prepare the request data
		$request_data = array(
			'version' => $model_version,
			'stream'  => false,
			'input'   => $input,
		);

		// Make the initial prediction request
		$response = $this->make_api_request(
			'https://api.replicate.com/v1/predictions',
			'POST',
			$request_data
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $body->urls->stream ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Replicate API.', 'wordtown' ) );
		}

		do_action( 'replicate_prediction', $body->urls->get, 5, $meta, $body );
	}

	public function handle_prediction( $url, $remaining_attempts = 5, $meta = array(), $body = false ) {
		if ( $remaining_attempts <= 0 ) {
			error_log( 'WordTown tile generation error: Prediction timed out. ' . print_r( $url, true ) );
			return;
		}

		if ( ! $body ) {
			$newbody = $this->make_api_request( $url, 'GET' );
			if ( is_wp_error( $newbody ) ) {
				error_log( 'WordTown tile generation error: ' . print_r( $newbody, true ) );
				return;
			}
			$body = json_decode( wp_remote_retrieve_body( $newbody ) );
		}

		if ( $body->status === 'succeeded' && is_array( $body->output ) ) {
			$uploads = array_map( array( $this, 'upload_image' ), $body->output );
			do_action( 'replicate_prediction_uploaded', $uploads, $meta );
			return;
		}

		if ( in_array( $body->status, array( 'starting', 'processing', 'pending' ) ) ) {
			error_log( 'Replicate: Polling for prediction ' . print_r( $body, true ) );
			if ( isset( $meta['blocking'] ) && $meta['blocking'] === true ) {
				sleep( 15 );
				do_action( 'replicate_prediction', $body->urls->get, $remaining_attempts - 1, $meta, false );
			} else {
				wp_schedule_single_event( time() + 30, 'replicate_prediction', array( $body->urls->get, $remaining_attempts - 1, $meta, false ) );
			}
		}
	}

	/**
	 * Make a request to the Replicate API.
	 *
	 * @param string $url    The API endpoint URL.
	 * @param string $method The HTTP method (GET or POST).
	 * @param array  $data   Optional. The data to send with the request. Default empty array.
	 * @param int    $timeout Optional. Request timeout in seconds. Default 30.
	 * @param bool   $wait Optional. Whether to wait for the response. Default false.
	 * @return array|WP_Error The response or WP_Error on failure.
	 */
	private function make_api_request( string $url, string $method = 'GET', array $data = array(), int $timeout = 30, bool $wait = false ) {
		$api_key = $this->get_api_key();

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => $timeout,
		);

		if ( $wait ) {
			$args['headers']['Prefer'] = 'wait=' . $timeout;
		}

		if ( ! empty( $data ) && $method === 'POST' ) {
			$args['body'] = wp_json_encode( $data );
		}

		if ( $method === 'GET' ) {
			return wp_remote_get( $url, $args );
		} else {
			return wp_remote_post( $url, $args );
		}
	}

	public function text_completion( $user_prompt, $system_prompt = 'You are a helpful assistant.' ) {
		$response = $this->make_api_request(
			'https://api.replicate.com/v1/models/meta/meta-llama-3-70b-instruct/predictions',
			'POST',
			array(
				'input'  => array(
					'prompt'        => $user_prompt,
					'system_prompt' => $system_prompt,
				),
				'stream' => false,
			),
			60,
			true
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return trim( join( '', json_decode( wp_remote_retrieve_body( $response ) )->output ) );
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
		$file = array(
			'name'     => $file_name,
			'type'     => wp_check_filetype( $file_name )['type'] ?: 'image/png',
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize( $temp_file ),
		);

		// Set default media data
		$media_data = array(
			'post_title'   => pathinfo( $file_name, PATHINFO_FILENAME ),
			'post_content' => sprintf(
				/* translators: %s: Image URL */
				__( 'Image generated using Replicate API from %s', 'wordtown' ),
				esc_url( $image_url )
			),
			'post_status'  => 'inherit',
		);

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
