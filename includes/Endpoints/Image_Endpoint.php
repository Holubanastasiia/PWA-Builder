<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Endpoints;

use WP_PWA_Builder\Image_Assets\PWA_Image_Generator;
use WP_PWA_Builder\Media;
use WP_PWA_Builder\Value_Objects\PWA_App_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Image_Endpoint {

	private PWA_Image_Generator $image_generator;

	public function __construct() {
		$this->image_generator = new PWA_Image_Generator();
	}

	public function serve_icon( \WP_Post $app, int $size ): void {
		if ( ! in_array( $size, array( 192, 512 ), true ) ) {
			status_header( 404 );
			exit;
		}

		if ( $this->serve_file( $this->image_generator->icon_path( $app, $size ) ) ) {
			exit;
		}

		if ( $this->image_generator->generate_icon( $app, $size ) && $this->serve_file( $this->image_generator->icon_path( $app, $size ) ) ) {
			exit;
		}

		if ( $this->serve_uploaded_icon( $app, $size ) ) {
			exit;
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			status_header( 404 );
			exit;
		}

		$settings         = PWA_App_Settings::from_post( $app );
		$background_color = $this->hex_to_rgb( $settings->theme_color );
		$accent_color     = $this->hex_to_rgb( '#22c55e' );

		nocache_headers();
		header( 'Content-Type: image/png' );

		$image = imagecreatetruecolor( $size, $size );
		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		$background = imagecolorallocate( $image, $background_color[0], $background_color[1], $background_color[2] );
		$accent     = imagecolorallocate( $image, $accent_color[0], $accent_color[1], $accent_color[2] );
		$white      = imagecolorallocate( $image, 255, 255, 255 );

		imagefilledrectangle( $image, 0, 0, $size, $size, $background );
		imagefilledellipse( $image, (int) ( $size * 0.72 ), (int) ( $size * 0.72 ), (int) ( $size * 0.2 ), (int) ( $size * 0.2 ), $accent );

		$letter      = $this->icon_letter( get_the_title( $app ) );
		$font        = 5;
		$text_width  = imagefontwidth( $font ) * strlen( $letter );
		$text_height = imagefontheight( $font );
		imagestring( $image, $font, (int) ( ( $size - $text_width ) / 2 ), (int) ( ( $size - $text_height ) / 2 ), $letter, $white );

		imagepng( $image );
		imagedestroy( $image );
		exit;
	}

	public function serve_screenshot( \WP_Post $app, string $asset ): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			status_header( 404 );
			exit;
		}

		$is_wide = $asset === 'screenshot-wide';
		$width   = $is_wide ? 1280 : 390;
		$height  = $is_wide ? 720 : 844;

		if ( $this->serve_file( $this->image_generator->screenshot_path( $app, $asset ) ) ) {
			exit;
		}

		if ( $this->image_generator->generate_screenshot( $app, $asset ) && $this->serve_file( $this->image_generator->screenshot_path( $app, $asset ) ) ) {
			exit;
		}

		$settings         = PWA_App_Settings::from_post( $app );
		$background_color = $this->hex_to_rgb( $settings->theme_color );
		$panel_color      = $this->hex_to_rgb( '#ffffff' );
		$accent_color     = $this->hex_to_rgb( '#22c55e' );

		nocache_headers();
		header( 'Content-Type: image/png' );

		$image = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		$background = imagecolorallocate( $image, $background_color[0], $background_color[1], $background_color[2] );
		$panel      = imagecolorallocate( $image, $panel_color[0], $panel_color[1], $panel_color[2] );
		$accent     = imagecolorallocate( $image, $accent_color[0], $accent_color[1], $accent_color[2] );
		$text       = imagecolorallocate( $image, 24, 24, 27 );

		imagefilledrectangle( $image, 0, 0, $width, $height, $background );

		$margin = $is_wide ? 96 : 28;
		imagefilledrectangle( $image, $margin, $margin, $width - $margin, $height - $margin, $panel );
		imagefilledrectangle( $image, $margin, $margin, $width - $margin, $margin + ( $is_wide ? 12 : 8 ), $accent );

		imagestring( $image, 5, $margin + 32, $margin + 44, $this->screenshot_title( get_the_title( $app ) ), $text );
		imagestring( $image, 3, $margin + 32, $margin + 88, 'PWA preview screenshot', $text );

		imagepng( $image );
		imagedestroy( $image );
		exit;
	}

	private function serve_uploaded_icon( \WP_Post $app, int $size ): bool {
		$icon_id = Media::app_icon_id( $app );

		if ( $icon_id <= 0 ) {
			return false;
		}

		$icon_path = get_attached_file( $icon_id );

		if ( ! is_string( $icon_path ) || ! is_readable( $icon_path ) ) {
			return false;
		}

		$editor = wp_get_image_editor( $icon_path );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$resized = $editor->resize( $size, $size, true );

		if ( is_wp_error( $resized ) ) {
			return false;
		}

		$current_size = $editor->get_size();
        // @phpstan-ignore function.alreadyNarrowedType (defensive: WP_Image_Editor implementations aren't guaranteed to match the core stub shape exactly)
        if ( ! is_array( $current_size ) ) {
            return false;
        }

        // @phpstan-ignore nullCoalesce.offset, nullCoalesce.offset (defensive: see above)
        if ( (int) ( $current_size['width'] ?? 0 ) !== $size || (int) ( $current_size['height'] ?? 0 ) !== $size ) {
            return false;
        }

		nocache_headers();
		header( 'Content-Type: image/png' );

		$streamed = $editor->stream( 'image/png' );

		return ! is_wp_error( $streamed );
	}
    /**
     * @phpstan-impure
     */
	private function serve_file( string $path ): bool {
		if ( $path === '' || ! is_readable( $path ) ) {
			return false;
		}

		clearstatcache( true, $path );

		nocache_headers();
		header( 'Content-Type: image/png' );

		$file_size = filesize( $path );

		if ( is_int( $file_size ) ) {
			header( 'Content-Length: ' . $file_size );
		}

		readfile( $path );

		return true;
	}

	/**
	 * @return array{0: int, 1: int, 2: int}
	 */
	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) !== 6 ) {
			return array( 18, 18, 18 );
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	private function icon_letter( string $title ): string {
		if ( preg_match( '/[A-Za-z0-9]/', $title, $matches ) === 1 ) {
			return strtoupper( $matches[0] );
		}

		return 'P';
	}

	private function screenshot_title( string $title ): string {
		$title = trim( wp_strip_all_tags( $title ) );

		if ( $title === '' ) {
			return 'PWA App';
		}

		return strlen( $title ) > 42 ? substr( $title, 0, 39 ) . '...' : $title;
	}
}
