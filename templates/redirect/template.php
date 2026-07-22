<?php
/**
 * Minimal redirect/install PWA template.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WP_PWA_Builder\PWA_Endpoints;
use WP_PWA_Builder\Template_Shell;

Template_Shell::header();
?>

<main class="wp-pwa-redirect">
    <?php
    while (have_posts()) :
        the_post();

        $app = get_post();
        $app_id = get_the_ID();
        $cta_url = (string) get_post_meta($app_id, '_pwa_cta_url', true);
        $icon_url = $app instanceof \WP_Post ? PWA_Endpoints::asset_url($app, 'icon', 192) : '';
        ?>
        <section class="wp-pwa-redirect__panel" data-pwa-template="redirect">
            <?php if ($icon_url) : ?>
                <img class="wp-pwa-redirect__icon" src="<?php echo esc_url($icon_url); ?>" alt="">
            <?php endif; ?>

            <h1 class="wp-pwa-redirect__title"><?php the_title(); ?></h1>

            <?php if (has_excerpt()) : ?>
                <p class="wp-pwa-redirect__description"><?php echo esc_html(get_the_excerpt()); ?></p>
            <?php endif; ?>

            <?php if ($cta_url !== '') : ?>
                <a
                    class="wp-pwa-redirect__button analytic-url"
                    href="<?php echo esc_url($cta_url); ?>"
                    data-pwa-track="redirect_cta"
                    data-pwa-install
                >
                    <?php esc_html_e('Continue', 'wp-pwa-builder'); ?>
                </a>
            <?php endif; ?>
        </section>
        <?php
    endwhile;
    ?>
</main>

<?php
Template_Shell::footer();
