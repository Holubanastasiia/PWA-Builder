<?php

declare(strict_types=1);

namespace WP_PWA_Builder\Endpoints;

use WP_PWA_Builder\Template_Shell;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Start_Endpoint {

	public function serve( \WP_Post $app ): void {
		status_header( 200 );
		nocache_headers();

		$fallback_url = (string) get_post_meta( $app->ID, '_pwa_cta_url', true );
		$title        = sprintf(
			/* translators: %s: PWA app title. */
			__( 'Opening %s', 'wp-pwa-builder' ),
			get_the_title( $app )
		);

		Template_Shell::header( $title );
		?>
		<style>
			.wp-pwa-builder-start {
				align-items: center;
				background: #0f172a;
				box-sizing: border-box;
				color: #ffffff;
				display: flex;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				justify-content: center;
				min-height: 100vh;
				padding: 24px;
				text-align: center;
			}

			.wp-pwa-builder-start__fallback {
				opacity: 0;
				pointer-events: none;
				transform: translateY(8px);
				transition: opacity 180ms ease, transform 180ms ease;
			}

			.wp-pwa-builder-start.is-fallback-visible .wp-pwa-builder-start__fallback {
				opacity: 1;
				pointer-events: auto;
				transform: translateY(0);
			}

			.wp-pwa-builder-start__message {
				font-size: 16px;
				margin: 0 0 16px;
			}

			.wp-pwa-builder-start__button {
				background: #ffffff;
				border-radius: 6px;
				color: #0f172a;
				display: inline-flex;
				font-size: 15px;
				font-weight: 700;
				line-height: 1;
				padding: 14px 18px;
				text-decoration: none;
			}
		</style>
		<main
			class="wp-pwa-builder-start"
			data-pwa-start
			data-app-id="<?php echo esc_attr( (string) $app->ID ); ?>"
			data-app-slug="<?php echo esc_attr( $app->post_name ); ?>"
			data-fallback-url="<?php echo esc_url( $fallback_url ); ?>"
		>
			<div class="wp-pwa-builder-start__fallback" aria-live="polite">
				<p class="wp-pwa-builder-start__message">
					<?php esc_html_e( 'Opening...', 'wp-pwa-builder' ); ?>
				</p>

				<?php if ( $fallback_url !== '' ) : ?>
					<a
						class="wp-pwa-builder-start__button analytic-url"
						data-pwa-launch="1"
						data-pwa-track="installed_launch"
						href="<?php echo esc_url( $fallback_url ); ?>"
					>
						<?php esc_html_e( 'Continue', 'wp-pwa-builder' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</main>
		<?php
		Template_Shell::footer();
		exit;
	}
}
