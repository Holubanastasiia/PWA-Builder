<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Endpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Service_Worker_Endpoint {

	public function serve( \WP_Post $app ): void {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );

		$scope      = home_url( '/apps/' . $app->post_name . '/' );
		$scope_path = wp_parse_url( $scope, PHP_URL_PATH ) ?: '/apps/' . $app->post_name . '/';

		header( 'Service-Worker-Allowed: ' . $scope_path );

		printf(
			"self.WP_PWA_BUILDER = %s;\n",
			wp_json_encode(
				array(
					'appId'     => $app->ID,
					'cacheName' => 'wp-pwa-builder-' . $app->ID . '-' . WP_PWA_BUILDER_VERSION,
					'scope'     => $scope,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		readfile( WP_PWA_BUILDER_DIR . 'assets/public/service-worker.js' );
		exit;
	}
}
