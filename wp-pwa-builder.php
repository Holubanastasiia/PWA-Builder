<?php
/**
 * Plugin Name: WP PWA Builder
 * Description: Builder foundation for serving multiple PWA experiences from WordPress.
 * Version: 0.1.3
 * Author: Anastasiia
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Text Domain: wp-pwa-builder
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_PWA_BUILDER_VERSION', '0.1.3' );
define( 'WP_PWA_BUILDER_FILE', __FILE__ );
define( 'WP_PWA_BUILDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_PWA_BUILDER_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( WP_PWA_BUILDER_DIR . 'vendor/autoload.php' ) ) {
	require_once WP_PWA_BUILDER_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'WP_PWA_Builder\\';

			if ( ! str_starts_with( $class, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class, strlen( $prefix ) );
			$path           = WP_PWA_BUILDER_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	);
}

register_activation_hook( __FILE__, array( 'WP_PWA_Builder\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_PWA_Builder\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		WP_PWA_Builder\Plugin::instance()->boot();
	}
);
