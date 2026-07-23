<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Endpoints;

use WP_PWA_Builder\Media;
use WP_PWA_Builder\PWA_Endpoints;

if (!defined('ABSPATH')) {
    exit;
}

final class Manifest_Endpoint
{
    public function serve(\WP_Post $app): void
    {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');

        $theme_color = (string) get_post_meta($app->ID, '_pwa_theme_color', true);
        $background_color = (string) get_post_meta($app->ID, '_pwa_background_color', true);
        $scope_url = home_url('/apps/' . $app->post_name . '/');

        echo wp_json_encode([
            'id' => $scope_url,
            'name' => get_the_title($app),
            'short_name' => $this->app_short_name($app),
            'description' => wp_strip_all_tags((string) get_the_excerpt($app)),
            'start_url' => PWA_Endpoints::start_url($app),
            'scope' => $scope_url,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => $background_color ?: '#ffffff',
            'theme_color' => $theme_color ?: '#121212',
            'icons' => apply_filters('wp_pwa_builder_manifest_icons', Media::manifest_icons($app), $app),
            'screenshots' => apply_filters('wp_pwa_builder_manifest_screenshots', Media::manifest_screenshots($app), $app),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function app_short_name(\WP_Post $app): string
    {
        $short_name = (string) get_post_meta($app->ID, '_pwa_short_name', true);

        return $short_name !== '' ? $short_name : wp_trim_words(get_the_title($app), 3, '');
    }
}
