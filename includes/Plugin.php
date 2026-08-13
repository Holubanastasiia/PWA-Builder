<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	private Post_Types $post_types;
	private Media $media;
	private ACF_JSON $acf_json;
	private ACF_Locations $acf_locations;
	private Assets $assets;
	private PWA_Endpoints $pwa_endpoints;
	private Template_Loader $template_loader;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		Post_Types::register();
		PWA_Endpoints::add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	private function __construct() {
		$this->post_types      = new Post_Types();
		$this->media           = new Media();
		$this->acf_json        = new ACF_JSON();
		$this->acf_locations   = new ACF_Locations();
		$this->assets          = new Assets();
		$this->pwa_endpoints   = new PWA_Endpoints();
		$this->template_loader = new Template_Loader();
	}

	public function boot(): void {
		$this->post_types->hooks();
		$this->media->hooks();
		$this->acf_json->hooks();
		$this->acf_locations->hooks();
		$this->assets->hooks();
		$this->pwa_endpoints->hooks();
		$this->template_loader->hooks();
	}
}
