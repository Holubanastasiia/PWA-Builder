<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Endpoints;

use WP_PWA_Builder\Template_Shell;

if (!defined('ABSPATH')) {
    exit;
}

final class Start_Endpoint
{
    public function serve(\WP_Post $app): void
    {
        status_header(200);
        nocache_headers();

        $fallback_url = (string) get_post_meta($app->ID, '_pwa_cta_url', true);
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
}
