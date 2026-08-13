<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ACF_JSON {

	public function hooks(): void {
		add_filter( 'acf/settings/load_json', array( $this, 'add_load_path' ) );
		add_filter( 'acf/settings/save_json', array( $this, 'save_path' ) );
	}

	/**
	 * @param array<int, string> $paths
	 * @return array<int, string>
	 */
	public function add_load_path( array $paths ): array {
		$paths[] = WP_PWA_BUILDER_DIR . 'acf-json';

		return $paths;
	}

	public function save_path( string $path ): string {
		unset( $path );

		return WP_PWA_BUILDER_DIR . 'acf-json';
	}
}
