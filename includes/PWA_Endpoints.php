<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

use WP_PWA_Builder\Endpoints\Image_Endpoint;
use WP_PWA_Builder\Endpoints\Manifest_Endpoint;
use WP_PWA_Builder\Endpoints\Service_Worker_Endpoint;
use WP_PWA_Builder\Endpoints\Start_Endpoint;

if (!defined('ABSPATH')) {
    exit;
}

final class PWA_Endpoints
{
    private static ?\WP_Post $endpoint_app = null;
    private static string $endpoint_asset = '';

    private Manifest_Endpoint $manifest_endpoint;
    private Start_Endpoint $start_endpoint;
    private Service_Worker_Endpoint $service_worker_endpoint;
    private Image_Endpoint $image_endpoint;

    public function __construct()
    {
        $this->manifest_endpoint = new Manifest_Endpoint();
        $this->start_endpoint = new Start_Endpoint();
        $this->service_worker_endpoint = new Service_Worker_Endpoint();
        $this->image_endpoint = new Image_Endpoint();
    }

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
            $this->start_endpoint->serve($app);
        }

        if ($asset === 'manifest') {
            $this->manifest_endpoint->serve($app);
        }

        if ($asset === 'sw') {
            $this->service_worker_endpoint->serve($app);
        }

        if ($asset === 'icon') {
            $this->image_endpoint->serve_icon($app, absint(get_query_var('pwa_icon_size')));
        }

        if (in_array($asset, ['screenshot-wide', 'screenshot-narrow'], true)) {
            $this->image_endpoint->serve_screenshot($app, $asset);
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
}
