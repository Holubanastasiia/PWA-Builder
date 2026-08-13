<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Image_Assets;

use WP_PWA_Builder\Media;
use WP_PWA_Builder\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PWA_Image_Generator {

	public const ICON_SIZES = array(
		192 => array( 192, 192 ),
		512 => array( 512, 512 ),
	);

	public const SCREENSHOT_SIZES = array(
		'screenshot-wide'   => array( 1280, 720 ),
		'screenshot-narrow' => array( 390, 844 ),
	);

	public function hooks(): void {
		add_action( 'save_post_' . Post_Types::APP_POST_TYPE, array( $this, 'generate_for_post' ), 20 );
	}

	public function generate_for_post( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$app = get_post( $post_id );

		if ( ! $app instanceof \WP_Post || $app->post_type !== Post_Types::APP_POST_TYPE ) {
			return;
		}

		$this->generate_icons( $app );
		$this->generate_screenshots( $app );
	}

	public function icon_path( \WP_Post $app, int $size ): string {
		if ( ! isset( self::ICON_SIZES[ $size ] ) ) {
			return '';
		}

		$app_dir = $this->app_dir( $app );

		return $app_dir !== '' ? $app_dir . '/icon-' . $size . '.png' : '';
	}

	public function screenshot_path( \WP_Post $app, string $asset ): string {
		if ( ! isset( self::SCREENSHOT_SIZES[ $asset ] ) ) {
			return '';
		}

		$app_dir = $this->app_dir( $app );

		return $app_dir !== '' ? $app_dir . '/' . $asset . '.png' : '';
	}

	public function generate_icon( \WP_Post $app, int $size ): bool {
		if ( ! isset( self::ICON_SIZES[ $size ] ) ) {
			return false;
		}

		$icon_id = Media::app_icon_id( $app );
		$path    = $this->icon_path( $app, $size );

		if ( $icon_id <= 0 ) {
			$this->delete_file( $path );
			return false;
		}

		[$width, $height] = self::ICON_SIZES[ $size ];

		return $this->generate_from_attachment( $icon_id, $path, $width, $height );
	}

	public function generate_screenshot( \WP_Post $app, string $asset ): bool {
		if ( ! isset( self::SCREENSHOT_SIZES[ $asset ] ) ) {
			return false;
		}

		$attachment_id = $asset === 'screenshot-wide'
			? Media::app_screenshot_wide_id( $app )
			: Media::app_screenshot_narrow_id( $app );
		$path          = $this->screenshot_path( $app, $asset );

		if ( $attachment_id <= 0 ) {
			$this->delete_file( $path );
			return false;
		}

		[$width, $height] = self::SCREENSHOT_SIZES[ $asset ];

		return $this->generate_from_attachment( $attachment_id, $path, $width, $height );
	}

	private function generate_icons( \WP_Post $app ): void {
		foreach ( array_keys( self::ICON_SIZES ) as $size ) {
			$this->generate_icon( $app, (int) $size );
		}
	}

	private function generate_screenshots( \WP_Post $app ): void {
		foreach ( array_keys( self::SCREENSHOT_SIZES ) as $asset ) {
			$this->generate_screenshot( $app, (string) $asset );
		}
	}

	private function generate_from_attachment( int $attachment_id, string $destination, int $width, int $height ): bool {
		$source = get_attached_file( $attachment_id );

		if ( ! is_string( $source ) || ! is_readable( $source ) ) {
			$this->delete_file( $destination );
			return false;
		}

		if ( ! $this->ensure_dir( dirname( $destination ) ) ) {
			return false;
		}

		if ( ! $this->save_cover_crop( $source, $destination, $width, $height ) ) {
			$this->delete_file( $destination );
			return false;
		}

		return true;
	}

	private function app_dir( \WP_Post $app ): string {
		$upload_dir = wp_upload_dir( null, false );
		$base_dir   = is_string( $upload_dir['basedir'] ?? null ) ? $upload_dir['basedir'] : '';

		if ( $base_dir === '' ) {
			return '';
		}

		return trailingslashit( $base_dir ) . 'wp-pwa-builder/' . $app->ID;
	}

	private function ensure_dir( string $dir ): bool {
		return $dir !== '' && ( is_dir( $dir ) || wp_mkdir_p( $dir ) );
	}

	private function save_cover_crop( string $source, string $destination, int $width, int $height ): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$source_image = $this->create_image_resource( $source );

		if ( ! $this->is_gd_image( $source_image ) ) {
			return false;
		}

		$source_width  = imagesx( $source_image );
		$source_height = imagesy( $source_image );

		if ( $source_width <= 0 || $source_height <= 0 ) {
			imagedestroy( $source_image );
			return false;
		}

		$target_ratio = $width / $height;
		$source_ratio = $source_width / $source_height;

		if ( $source_ratio > $target_ratio ) {
			$crop_height = $source_height;
			$crop_width  = (int) round( $source_height * $target_ratio );
			$source_x    = (int) floor( ( $source_width - $crop_width ) / 2 );
			$source_y    = 0;
		} else {
			$crop_width  = $source_width;
			$crop_height = (int) round( $source_width / $target_ratio );
			$source_x    = 0;
			$source_y    = (int) floor( ( $source_height - $crop_height ) / 2 );
		}

		$target_image = imagecreatetruecolor( $width, $height );

		if ( ! $this->is_gd_image( $target_image ) ) {
			imagedestroy( $source_image );
			return false;
		}

		imagealphablending( $target_image, false );
		imagesavealpha( $target_image, true );

		$transparent = imagecolorallocatealpha( $target_image, 0, 0, 0, 127 );
		imagefilledrectangle( $target_image, 0, 0, $width, $height, $transparent );

		imagecopyresampled(
			$target_image,
			$source_image,
			0,
			0,
			$source_x,
			$source_y,
			$width,
			$height,
			$crop_width,
			$crop_height
		);

		$saved = imagepng( $target_image, $destination );

		imagedestroy( $source_image );
		imagedestroy( $target_image );

		return $saved && $this->has_exact_dimensions( $destination, $width, $height );
	}

	/**
	 * @return resource|\GdImage|false
	 */
	private function create_image_resource( string $source ) {
		$mime_type = wp_get_image_mime( $source );

		if ( $mime_type === 'image/jpeg' ) {
			return imagecreatefromjpeg( $source );
		}

		if ( $mime_type === 'image/png' ) {
			return imagecreatefrompng( $source );
		}

		if ( $mime_type === 'image/webp' && function_exists( 'imagecreatefromwebp' ) ) {
			return imagecreatefromwebp( $source );
		}

		return false;
	}

	/**
	 * @param mixed $image
	 */
	private function is_gd_image( $image ): bool {
		return is_resource( $image ) || ( class_exists( 'GdImage' ) && $image instanceof \GdImage );
	}

	private function has_exact_dimensions( string $path, int $width, int $height ): bool {
		if ( ! is_readable( $path ) ) {
			return false;
		}

		$image_size = getimagesize( $path );

		return is_array( $image_size )
			&& (int) ( $image_size[0] ?? 0 ) === $width
			&& (int) ( $image_size[1] ?? 0 ) === $height;
	}

	private function delete_file( string $path ): void {
		if ( $path !== '' && is_file( $path ) ) {
			@unlink( $path );
		}
	}
}
