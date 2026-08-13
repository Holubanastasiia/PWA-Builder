<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

use WP_PWA_Builder\Image_Assets\PWA_Image_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Media {

	private PWA_Image_Generator $image_generator;

	public function __construct() {
		$this->image_generator = new PWA_Image_Generator();
	}

	public function hooks(): void {
		$this->image_generator->hooks();
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public static function manifest_icons( \WP_Post $app ): array {
		return array(
			array(
				'src'     => PWA_Endpoints::asset_url( $app, 'icon', 192 ),
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => PWA_Endpoints::asset_url( $app, 'icon', 512 ),
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public static function manifest_screenshots( \WP_Post $app ): array {
		return array(
			array(
				'src'         => PWA_Endpoints::asset_url( $app, 'screenshot-wide' ),
				'sizes'       => '1280x720',
				'type'        => 'image/png',
				'form_factor' => 'wide',
			),
			array(
				'src'         => PWA_Endpoints::asset_url( $app, 'screenshot-narrow' ),
				'sizes'       => '390x844',
				'type'        => 'image/png',
				'form_factor' => 'narrow',
			),
		);
	}

	private static function field_id( int $post_id, string $field_name ): int {
		return absint( get_post_meta( $post_id, $field_name, true ) );
	}

	public static function app_icon_id( \WP_Post $app ): int {
		return self::field_id( $app->ID, 'pwa_app_icon' );
	}

	public static function app_screenshot_wide_id( \WP_Post $app ): int {
		return self::field_id( $app->ID, 'pwa_screenshot_wide' );
	}

	public static function app_screenshot_narrow_id( \WP_Post $app ): int {
		return self::field_id( $app->ID, 'pwa_screenshot_narrow' );
	}
}
