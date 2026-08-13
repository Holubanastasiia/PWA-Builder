<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Template_Loader {

	public function hooks(): void {
		add_filter( 'single_template', array( $this, 'load_app_template' ) );
	}

	public function load_app_template( string $template ): string {
		if ( ! is_singular( Post_Types::APP_POST_TYPE ) ) {
			return $template;
		}

		$app = get_queried_object();

		if ( ! $app instanceof \WP_Post ) {
			return $template;
		}

		return Template_Registry::template_file( Template_Registry::selected_template( $app->ID ) );
	}
}
