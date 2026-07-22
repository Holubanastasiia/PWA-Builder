<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class Niche_Registry
{
    public const META_KEY = '_pwa_niche';

    /**
     * @return array<string, string>
     */
    public static function niches(): array
    {
        return apply_filters('wp_pwa_builder_niches', [
            'igaming' => __('iGaming', 'wp-pwa-builder'),
            'dating' => __('Dating', 'wp-pwa-builder'),
        ]);
    }

    public static function selected_niche(int $post_id): string
    {
        $selected = (string) get_post_meta($post_id, self::META_KEY, true);
        $niches = self::niches();

        return isset($niches[$selected]) ? $selected : 'igaming';
    }
}
