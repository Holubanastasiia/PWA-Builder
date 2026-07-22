<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class PWA_Endpoints
{
    private static ?\WP_Post $endpoint_app = null;
    private static string $endpoint_asset = '';

    public function hooks(): void
    {
        add_action('init', [self::class, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('redirect_canonical', [$this, 'disable_canonical_redirect_for_assets']);
        add_action('template_redirect', [$this, 'serve_dynamic_assets']);
    }

    public static function add_rewrite_rules(): void
    {
        add_rewrite_rule('^apps/([^/]+)/start/?$', 'index.php?pwa_app_slug=$matches[1]&pwa_asset=start', 'top');
        add_rewrite_rule('^apps/([^/]+)/manifest\.webmanifest$', 'index.php?pwa_app_slug=$matches[1]&pwa_asset=manifest', 'top');
        add_rewrite_rule('^apps/([^/]+)/sw\.js$', 'index.php?pwa_app_slug=$matches[1]&pwa_asset=sw', 'top');
        add_rewrite_rule('^apps/([^/]+)/icon-(192|512)\.png$', 'index.php?pwa_app_slug=$matches[1]&pwa_asset=icon&pwa_icon_size=$matches[2]', 'top');
        add_rewrite_rule('^apps/([^/]+)/(screenshot-wide|screenshot-narrow)\.png$', 'index.php?pwa_app_slug=$matches[1]&pwa_asset=$matches[2]', 'top');
    }

    public static function asset_url(\WP_Post $app, string $asset, int $size = 0): string
    {
        return Environment::asset_url($app, $asset, $size);
    }

    public static function start_url(\WP_Post $app): string
    {
        return Environment::start_url($app);
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function add_query_vars(array $vars): array
    {
        $vars[] = 'pwa_app_slug';
        $vars[] = 'pwa_asset';
        $vars[] = 'pwa_icon_size';

        return $vars;
    }

    public function serve_dynamic_assets(): void
    {
        $asset = (string) get_query_var('pwa_asset');

        if ($asset === '') {
            return;
        }

        $app = self::app_by_slug((string) get_query_var('pwa_app_slug'));

        if (!$app instanceof \WP_Post) {
            status_header(404);
            exit;
        }

        self::$endpoint_app = $app;
        self::$endpoint_asset = $asset;

        if ($asset === 'start') {
            $this->serve_start_page($app);
        }

        if ($asset === 'manifest') {
            $this->serve_manifest($app);
        }

        if ($asset === 'sw') {
            $this->serve_service_worker($app);
        }

        if ($asset === 'icon') {
            $this->serve_icon($app, absint(get_query_var('pwa_icon_size')));
        }

        if (in_array($asset, ['screenshot-wide', 'screenshot-narrow'], true)) {
            $this->serve_screenshot($app, $asset);
        }
    }

    public function disable_canonical_redirect_for_assets(string|false $redirect_url): string|false
    {
        return (string) get_query_var('pwa_asset') !== '' ? false : $redirect_url;
    }

    public static function current_app(): ?\WP_Post
    {
        if (self::$endpoint_app instanceof \WP_Post) {
            return self::$endpoint_app;
        }

        if (!is_singular(Post_Types::APP_POST_TYPE)) {
            return null;
        }

        $post = get_queried_object();

        return $post instanceof \WP_Post ? $post : null;
    }

    public static function is_launch_request(): bool
    {
        return self::$endpoint_asset === 'start' || (string) get_query_var('pwa_asset') === 'start';
    }

    public static function app_by_slug(string $slug): ?\WP_Post
    {
        $slug = sanitize_title($slug);

        if ($slug === '') {
            return null;
        }

        $posts = get_posts([
            'name' => $slug,
            'post_type' => Post_Types::APP_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        return $posts[0] ?? null;
    }

    private function serve_manifest(\WP_Post $app): void
    {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');

        $theme_color = (string) get_post_meta($app->ID, '_pwa_theme_color', true);
        $background_color = (string) get_post_meta($app->ID, '_pwa_background_color', true);
        $start_url = self::start_url($app);
        $scope_url = home_url('/apps/' . $app->post_name . '/');

        echo wp_json_encode([
            'id' => $scope_url,
            'name' => get_the_title($app),
            'short_name' => $this->app_short_name($app),
            'description' => wp_strip_all_tags((string) get_the_excerpt($app)),
            'start_url' => $start_url,
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

    private function serve_start_page(\WP_Post $app): void
    {
        status_header(200);
        nocache_headers();

        $fallback_url = $this->fallback_url($app);
        $title = sprintf(
            /* translators: %s: PWA app title. */
            __('Opening %s', 'wp-pwa-builder'),
            get_the_title($app)
        );

        Template_Shell::header($title);
        ?>
        <main
            class="wp-pwa-builder-start"
            data-pwa-start
            data-app-id="<?php echo esc_attr((string) $app->ID); ?>"
            data-app-slug="<?php echo esc_attr($app->post_name); ?>"
            data-fallback-url="<?php echo esc_url($fallback_url); ?>"
        >
            <p class="wp-pwa-builder-start__message">
                <?php esc_html_e('Opening...', 'wp-pwa-builder'); ?>
            </p>

            <?php if ($fallback_url !== '') : ?>
                <a
                    class="analytic-url"
                    data-pwa-launch="1"
                    data-pwa-track="installed_launch"
                    href="<?php echo esc_url($fallback_url); ?>"
                >
                    <?php esc_html_e('Continue', 'wp-pwa-builder'); ?>
                </a>
            <?php endif; ?>
        </main>
        <?php
        Template_Shell::footer();
        exit;
    }

    private function serve_icon(\WP_Post $app, int $size): void
    {
        if (!in_array($size, [192, 512], true)) {
            status_header(404);
            exit;
        }

        if ($this->serve_uploaded_icon($app, $size)) {
            exit;
        }

        if (!function_exists('imagecreatetruecolor')) {
            status_header(404);
            exit;
        }

        $theme_color = (string) get_post_meta($app->ID, '_pwa_theme_color', true);
        $background_color = $this->hex_to_rgb($theme_color ?: '#121212');
        $accent_color = $this->hex_to_rgb('#22c55e');

        nocache_headers();
        header('Content-Type: image/png');

        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $background = imagecolorallocate($image, $background_color[0], $background_color[1], $background_color[2]);
        $accent = imagecolorallocate($image, $accent_color[0], $accent_color[1], $accent_color[2]);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $size, $size, $background);
        imagefilledellipse($image, (int) ($size * 0.72), (int) ($size * 0.72), (int) ($size * 0.2), (int) ($size * 0.2), $accent);

        $letter = $this->icon_letter(get_the_title($app));
        $font = 5;
        $text_width = imagefontwidth($font) * strlen($letter);
        $text_height = imagefontheight($font);
        imagestring($image, $font, (int) (($size - $text_width) / 2), (int) (($size - $text_height) / 2), $letter, $white);

        imagepng($image);
        imagedestroy($image);
        exit;
    }

    private function serve_uploaded_icon(\WP_Post $app, int $size): bool
    {
        $icon_id = Media::app_icon_id($app);

        if ($icon_id <= 0) {
            return false;
        }

        $icon_path = get_attached_file($icon_id);

        if (!is_string($icon_path) || !is_readable($icon_path)) {
            return false;
        }

        $editor = wp_get_image_editor($icon_path);

        if (is_wp_error($editor)) {
            return false;
        }

        $resized = $editor->resize($size, $size, true);

        if (is_wp_error($resized)) {
            return false;
        }

        nocache_headers();
        header('Content-Type: image/png');

        $streamed = $editor->stream('image/png');

        return !is_wp_error($streamed);
    }

    private function serve_screenshot(\WP_Post $app, string $asset): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            status_header(404);
            exit;
        }

        $is_wide = $asset === 'screenshot-wide';
        $width = $is_wide ? 1280 : 390;
        $height = $is_wide ? 720 : 844;
        $theme_color = (string) get_post_meta($app->ID, '_pwa_theme_color', true);
        $background_color = $this->hex_to_rgb($theme_color ?: '#121212');
        $panel_color = $this->hex_to_rgb('#ffffff');
        $accent_color = $this->hex_to_rgb('#22c55e');

        nocache_headers();
        header('Content-Type: image/png');

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $background = imagecolorallocate($image, $background_color[0], $background_color[1], $background_color[2]);
        $panel = imagecolorallocate($image, $panel_color[0], $panel_color[1], $panel_color[2]);
        $accent = imagecolorallocate($image, $accent_color[0], $accent_color[1], $accent_color[2]);
        $text = imagecolorallocate($image, 24, 24, 27);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $margin = $is_wide ? 96 : 28;
        imagefilledrectangle($image, $margin, $margin, $width - $margin, $height - $margin, $panel);
        imagefilledrectangle($image, $margin, $margin, $width - $margin, $margin + ($is_wide ? 12 : 8), $accent);

        imagestring($image, 5, $margin + 32, $margin + 44, $this->screenshot_title(get_the_title($app)), $text);
        imagestring($image, 3, $margin + 32, $margin + 88, 'PWA preview screenshot', $text);

        imagepng($image);
        imagedestroy($image);
        exit;
    }

    private function serve_service_worker(\WP_Post $app): void
    {
        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');

        $scope = home_url('/apps/' . $app->post_name . '/');
        $scope_path = wp_parse_url($scope, PHP_URL_PATH) ?: '/apps/' . $app->post_name . '/';
        $offline_url = get_permalink($app);

        header('Service-Worker-Allowed: ' . $scope_path);

        printf(
            "self.WP_PWA_BUILDER = %s;\n",
            wp_json_encode([
                'appId' => $app->ID,
                'cacheName' => 'wp-pwa-builder-' . $app->ID . '-' . WP_PWA_BUILDER_VERSION,
                'scope' => $scope,
                'offlineUrl' => $offline_url,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        readfile(WP_PWA_BUILDER_DIR . 'assets/public/service-worker.js');
        exit;
    }

    private function fallback_url(\WP_Post $app): string
    {
        return (string) get_post_meta($app->ID, '_pwa_cta_url', true);
    }

    private function app_short_name(\WP_Post $app): string
    {
        $short_name = (string) get_post_meta($app->ID, '_pwa_short_name', true);

        return $short_name !== '' ? $short_name : wp_trim_words(get_the_title($app), 3, '');
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hex_to_rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return [18, 18, 18];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function icon_letter(string $title): string
    {
        if (preg_match('/[A-Za-z0-9]/', $title, $matches) === 1) {
            return strtoupper($matches[0]);
        }

        return 'P';
    }

    private function screenshot_title(string $title): string
    {
        $title = trim(wp_strip_all_tags($title));

        if ($title === '') {
            return 'PWA App';
        }

        return strlen($title) > 42 ? substr($title, 0, 39) . '...' : $title;
    }
}
