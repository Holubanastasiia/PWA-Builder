<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class Media
{
    public function hooks(): void
    {
        add_action('after_setup_theme', [$this, 'register_image_sizes']);
    }

    public function register_image_sizes(): void
    {
        add_image_size('wp-pwa-icon-192', 192, 192, true);
        add_image_size('wp-pwa-icon-512', 512, 512, true);
        add_image_size('wp-pwa-screenshot-wide', 1280, 720, true);
        add_image_size('wp-pwa-screenshot-narrow', 390, 844, true);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function manifest_icons(\WP_Post $app): array
    {
        return [
            [
                'src' => PWA_Endpoints::asset_url($app, 'icon', 192),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => PWA_Endpoints::asset_url($app, 'icon', 512),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function manifest_screenshots(\WP_Post $app): array
    {
        $wide_id = self::field_id($app->ID, 'pwa_screenshot_wide');
        $narrow_id = self::field_id($app->ID, 'pwa_screenshot_narrow');
        $screenshots = [];

        if ($wide_id > 0) {
            $wide_url = wp_get_attachment_image_url($wide_id, 'wp-pwa-screenshot-wide');

            if ($wide_url) {
                $screenshots[] = [
                    'src' => $wide_url,
                    'sizes' => '1280x720',
                    'type' => self::mime_type($wide_id),
                    'form_factor' => 'wide',
                ];
            }
        }

        if ($narrow_id > 0) {
            $narrow_url = wp_get_attachment_image_url($narrow_id, 'wp-pwa-screenshot-narrow');

            if ($narrow_url) {
                $screenshots[] = [
                    'src' => $narrow_url,
                    'sizes' => '390x844',
                    'type' => self::mime_type($narrow_id),
                    'form_factor' => 'narrow',
                ];
            }
        }

        if ($screenshots !== []) {
            return $screenshots;
        }

        return [
            [
                'src' => PWA_Endpoints::asset_url($app, 'screenshot-wide'),
                'sizes' => '1280x720',
                'type' => 'image/png',
                'form_factor' => 'wide',
            ],
            [
                'src' => PWA_Endpoints::asset_url($app, 'screenshot-narrow'),
                'sizes' => '390x844',
                'type' => 'image/png',
                'form_factor' => 'narrow',
            ],
        ];
    }

    private static function field_id(int $post_id, string $field_name): int
    {
        if (function_exists('get_field')) {
            $acf_value = absint(get_field($field_name, $post_id, false));

            if ($acf_value > 0) {
                return $acf_value;
            }
        }

        return absint(get_post_meta($post_id, $field_name, true));
    }

    public static function app_icon_id(\WP_Post $app): int
    {
        return self::field_id($app->ID, 'pwa_app_icon');
    }

    private static function mime_type(int $attachment_id): string
    {
        $mime_type = get_post_mime_type($attachment_id);

        return is_string($mime_type) && $mime_type !== '' ? $mime_type : 'image/png';
    }
}
