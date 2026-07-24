<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class Assets
{
    private const ASSET_VERSION = null;

    public function hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_head', [$this, 'print_pwa_head_tags'], 1);
        add_filter('show_admin_bar', [$this, 'hide_admin_bar']);
    }

    public function hide_admin_bar(bool $show): bool
    {
        return PWA_Endpoints::current_app() instanceof \WP_Post ? false : $show;
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen instanceof \WP_Screen || $screen->post_type !== Post_Types::APP_POST_TYPE) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'wp-pwa-builder-admin',
            WP_PWA_BUILDER_URL . 'assets/admin/pwa-admin.js',
            [],
            self::ASSET_VERSION,
            true
        );

        wp_localize_script('wp-pwa-builder-admin', 'wpPwaBuilderAdmin', [
            'templatesByNiche' => Template_Registry::template_options_by_niche(),
        ]);
    }

    public function enqueue_public_assets(): void
    {
        $app = PWA_Endpoints::current_app();

        if (!$app instanceof \WP_Post) {
            return;
        }

        $is_launch = PWA_Endpoints::is_launch_request();
        $handle = $is_launch ? 'wp-pwa-builder-start' : 'wp-pwa-builder-public';
        $script = $is_launch ? 'assets/public/pwa-start.js' : 'assets/public/pwa-client.js';

        wp_enqueue_script(
            $handle,
            WP_PWA_BUILDER_URL . $script,
            [],
            self::ASSET_VERSION,
            true
        );

        wp_localize_script($handle, 'wpPwaBuilder', [
            'appId' => $app->ID,
            'appSlug' => $app->post_name,
            'manifestUrl' => PWA_Endpoints::asset_url($app, 'manifest'),
            'serviceWorkerUrl' => PWA_Endpoints::asset_url($app, 'sw'),
            'serviceWorkerScope' => home_url('/apps/' . $app->post_name . '/'),
            'startUrl' => PWA_Endpoints::start_url($app),
            'fallbackUrl' => (string) get_post_meta($app->ID, '_pwa_cta_url', true),
            'flow' => 'redirect_or_install',
            'isPreview' => is_preview(),
            'isLaunch' => $is_launch,
            'redirectDelay' => (int) apply_filters('wp_pwa_builder_redirect_delay', 1200, $app),
        ]);

        if (!$is_launch) {
            $this->enqueue_template_assets($app);
        }
    }

    public function print_pwa_head_tags(): void
    {
        $app = PWA_Endpoints::current_app();

        if (!$app instanceof \WP_Post) {
            return;
        }

        $theme_color = (string) get_post_meta($app->ID, '_pwa_theme_color', true);
        $manifest_url = PWA_Endpoints::asset_url($app, 'manifest');

        printf('<link rel="manifest" href="%s">' . "\n", esc_url($manifest_url));
        printf('<meta name="theme-color" content="%s">' . "\n", esc_attr($theme_color ?: '#121212'));
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        printf('<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr($this->app_short_name($app)));
    }

    private function app_short_name(\WP_Post $app): string
    {
        $short_name = (string) get_post_meta($app->ID, '_pwa_short_name', true);

        return $short_name !== '' ? $short_name : wp_trim_words(get_the_title($app), 3, '');
    }

    private function enqueue_template_assets(\WP_Post $app): void
    {
        $template = Template_Registry::selected_template_config($app->ID);

        if ($template === null) {
            return;
        }

        foreach ($template['styles'] as $index => $style) {
            wp_enqueue_style(
                'wp-pwa-builder-template-' . sanitize_key((string) $index),
                $this->template_asset_url($template, $style),
                [],
                self::ASSET_VERSION
            );
        }

        foreach ($template['scripts'] as $index => $script) {
            wp_enqueue_script(
                'wp-pwa-builder-template-' . sanitize_key((string) $index),
                $this->template_asset_url($template, $script),
                [],
                self::ASSET_VERSION,
                true
            );
        }
    }

    /**
     * @param array{url: string} $template
     */
    private function template_asset_url(array $template, string $asset): string
    {
        return trailingslashit($template['url']) . ltrim($asset, '/');
    }
}
