<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Endpoints;

use WP_PWA_Builder\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class Image_Endpoint
{
    public function serve_icon(\WP_Post $app, int $size): void
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

    public function serve_screenshot(\WP_Post $app, string $asset): void
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

        $current_size = $editor->get_size();

        if (
            !is_array($current_size)
            || (int) ($current_size['width'] ?? 0) !== $size
            || (int) ($current_size['height'] ?? 0) !== $size
        ) {
            return false;
        }

        nocache_headers();
        header('Content-Type: image/png');

        $streamed = $editor->stream('image/png');

        return !is_wp_error($streamed);
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
