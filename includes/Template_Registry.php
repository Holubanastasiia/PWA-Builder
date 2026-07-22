<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class Template_Registry
{
    public const META_KEY = '_pwa_template';

    /**
     * @return array<string, array{name: string, file: string, niches: array<int, string>, styles: array<int, string>, scripts: array<int, string>, dir: string, url: string}>
     */
    public static function templates(): array
    {
        $templates = [];
        $template_dirs = glob(WP_PWA_BUILDER_DIR . 'templates/*', GLOB_ONLYDIR);

        if (!is_array($template_dirs)) {
            return apply_filters('wp_pwa_builder_templates', []);
        }

        foreach ($template_dirs as $template_dir) {
            $template_key = basename($template_dir);
            $template = self::load_template_config($template_key, $template_dir);

            if ($template !== null) {
                $templates[$template_key] = $template;
            }
        }

        ksort($templates);

        return apply_filters('wp_pwa_builder_templates', $templates);
    }

    /**
     * @return array<string, array{name: string, file: string, niches: array<int, string>, styles: array<int, string>, scripts: array<int, string>, dir: string, url: string}>
     */
    public static function templates_for_niche(string $niche): array
    {
        return array_filter(
            self::templates(),
            static function (array $template) use ($niche): bool {
                return in_array($niche, $template['niches'], true);
            }
        );
    }

    /**
     * @return array<string, array<int, array{key: string, name: string}>>
     */
    public static function template_options_by_niche(): array
    {
        $options = [];

        foreach (Niche_Registry::niches() as $niche => $label) {
            $options[$niche] = [];

            foreach (self::templates_for_niche($niche) as $template_key => $template) {
                $options[$niche][] = [
                    'key' => $template_key,
                    'name' => $template['name'],
                ];
            }
        }

        return $options;
    }

    public static function template_file(string $template_key): string
    {
        $templates = self::templates();

        if (isset($templates[$template_key]['file']) && is_string($templates[$template_key]['file'])) {
            return $templates[$template_key]['file'];
        }

        return $templates['default']['file'] ?? '';
    }

    /**
     * @return array{name: string, file: string, niches: array<int, string>, styles: array<int, string>, scripts: array<int, string>, dir: string, url: string}|null
     */
    public static function selected_template_config(int $post_id): ?array
    {
        $selected = self::selected_template($post_id);
        $templates = self::templates();

        return $templates[$selected] ?? null;
    }

    public static function selected_template(int $post_id): string
    {
        $selected = (string) get_post_meta($post_id, self::META_KEY, true);
        $templates = self::templates_for_niche(Niche_Registry::selected_niche($post_id));

        if (isset($templates[$selected])) {
            return $selected;
        }

        if (array_key_exists('default', $templates)) {
            return 'default';
        }

        $first_key = array_key_first($templates);

        return is_string($first_key) ? $first_key : 'default';
    }

    /**
     * @return array{name: string, file: string, niches: array<int, string>, styles: array<int, string>, scripts: array<int, string>, dir: string, url: string}|null
     */
    private static function load_template_config(string $template_key, string $template_dir): ?array
    {
        $config_path = $template_dir . '/template.json';

        if (!is_readable($config_path)) {
            return null;
        }

        $raw_config = file_get_contents($config_path);
        $config = is_string($raw_config) ? json_decode($raw_config, true) : null;

        if (!is_array($config)) {
            return null;
        }

        $template_file = isset($config['template']) && is_string($config['template']) ? $config['template'] : 'template.php';
        $template_path = wp_normalize_path($template_dir . '/' . ltrim($template_file, '/'));

        if (!is_readable($template_path)) {
            return null;
        }

        $template_url = trailingslashit(WP_PWA_BUILDER_URL . 'templates/' . rawurlencode($template_key));

        return [
            'name' => isset($config['name']) && is_string($config['name']) ? $config['name'] : ucwords(str_replace(['-', '_'], ' ', $template_key)),
            'file' => $template_path,
            'niches' => self::string_list($config['niches'] ?? ['igaming', 'insurance']),
            'styles' => self::string_list($config['styles'] ?? []),
            'scripts' => self::string_list($config['scripts'] ?? []),
            'dir' => wp_normalize_path($template_dir),
            'url' => $template_url,
        ];
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private static function string_list($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn($item): bool => is_string($item) && $item !== ''));
    }
}
