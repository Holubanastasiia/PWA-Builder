<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class Template_Shell
{
    public static function header(string $title = ''): void
    {
        $language_attributes = get_language_attributes();
        $title = $title !== '' ? $title : wp_get_document_title();

        ?>
        <!doctype html>
        <html <?php echo $language_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
            <?php wp_head(); ?>
        </head>
        <body <?php body_class('wp-pwa-builder-shell'); ?>>
        <?php wp_body_open(); ?>
        <?php
    }

    public static function footer(): void
    {
        wp_footer();
        ?>
        </body>
        </html>
        <?php
    }
}
