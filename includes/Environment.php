<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class Environment
{
    public static function type(): string
    {
        return (string) apply_filters('wp_pwa_builder_environment_type', wp_get_environment_type());
    }

    public static function use_pretty_asset_urls(): bool
    {
        if (defined('WP_PWA_BUILDER_USE_PRETTY_ASSET_URLS')) {
            return (bool) WP_PWA_BUILDER_USE_PRETTY_ASSET_URLS;
        }

        return (bool) apply_filters(
            'wp_pwa_builder_use_pretty_asset_urls',
            self::type() !== 'local'
        );
    }

    public static function asset_url(\WP_Post $app, string $asset, int $size = 0): string
    {
        $asset = sanitize_key($asset);

        if (self::use_pretty_asset_urls()) {
            if ($asset === 'manifest') {
                return home_url('/apps/' . $app->post_name . '/manifest.webmanifest');
            }

            if ($asset === 'sw') {
                return home_url('/apps/' . $app->post_name . '/sw.js');
            }

            if ($asset === 'icon' && in_array($size, [192, 512], true)) {
                return home_url('/apps/' . $app->post_name . '/icon-' . $size . '.png');
            }

            if (in_array($asset, ['screenshot-wide', 'screenshot-narrow'], true)) {
                return home_url('/apps/' . $app->post_name . '/' . $asset . '.png');
            }
        }

        $query_args = [
            'pwa_app_slug' => $app->post_name,
            'pwa_asset' => $asset,
        ];

        if ($asset === 'icon' && in_array($size, [192, 512], true)) {
            $query_args['pwa_icon_size'] = $size;
        }

        return add_query_arg($query_args, home_url('/'));
    }

    public static function start_url(\WP_Post $app): string
    {
        return home_url('/apps/' . $app->post_name . '/start/');
    }
}
