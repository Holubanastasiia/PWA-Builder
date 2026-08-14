<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Value_Objects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PWA_App_Settings {

	public const DEFAULT_THEME_COLOR      = '#121212';
	public const DEFAULT_BACKGROUND_COLOR = '#ffffff';

	public function __construct(
		public readonly string $theme_color,
		public readonly string $background_color,
		public readonly string $short_name,
		public readonly string $cta_url
	) {}

	public static function from_post( \WP_Post $app ): self {
		$theme_color      = (string) get_post_meta( $app->ID, '_pwa_theme_color', true );
		$background_color = (string) get_post_meta( $app->ID, '_pwa_background_color', true );

		return new self(
			$theme_color !== '' ? $theme_color : self::DEFAULT_THEME_COLOR,
			$background_color !== '' ? $background_color : self::DEFAULT_BACKGROUND_COLOR,
			(string) get_post_meta( $app->ID, '_pwa_short_name', true ),
			(string) get_post_meta( $app->ID, '_pwa_cta_url', true )
		);
	}
}
