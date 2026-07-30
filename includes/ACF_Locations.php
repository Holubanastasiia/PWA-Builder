<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

use WP_PWA_Builder\ACF\Location_PWA_Template;

if (!defined('ABSPATH')) {
    exit;
}

final class ACF_Locations
{
    public function hooks(): void
    {
        add_action('acf/init', [$this, 'register_location_types']);
    }

    public function register_location_types(): void
    {
        if (!function_exists('acf_register_location_type') || !class_exists('\ACF_Location')) {
            return;
        }

        acf_register_location_type(Location_PWA_Template::class);
    }
}
