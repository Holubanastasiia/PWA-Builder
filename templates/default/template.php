<?php
/**
 * Default PWA app template.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WP_PWA_Builder\Template_Shell;

Template_Shell::header();
?>

<main id="primary" class="wp-pwa-builder-app">
    <?php
    while (have_posts()) :
        the_post();
        $cta_url = (string) get_post_meta(get_the_ID(), '_pwa_cta_url', true);
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            </header>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php if ($cta_url !== '') : ?>
                <p class="wp-pwa-builder-cta">
                    <a
                        class="analytic-url"
                        href="<?php echo esc_url($cta_url); ?>"
                        data-pwa-track="default_cta"
                        data-pwa-install
                    >
                        <?php esc_html_e('Continue', 'wp-pwa-builder'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </article>
        <?php
    endwhile;
    ?>
</main>

<?php
Template_Shell::footer();
