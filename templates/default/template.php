<?php
/**
 * Default install/redirect PWA funnel template.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_PWA_Builder\PWA_Endpoints;
use WP_PWA_Builder\Template_Shell;

$app_post_id = get_queried_object_id();

$field = static function ( string $name, $default = '' ) use ( $app_post_id ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}

	$value = get_field( $name, $app_post_id );

	return $value !== null && $value !== false && $value !== '' ? $value : $default;
};

$group_value = static function ( array $group, string $key, string $default = '' ): string {
	$value = $group[ $key ] ?? '';

	return is_string( $value ) && $value !== '' ? $value : $default;
};

$css_color = static function ( $value, string $default ): string {
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( $value === '' ) {
		return $default;
	}

	if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) === 1 ) {
		return $value;
	}

	if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/', $value ) === 1 ) {
		return $value;
	}

	return $default;
};

$button_settings = $field( 'button_settings', array() );
$button_settings = is_array( $button_settings ) ? $button_settings : array();
$button_start    = isset( $button_settings['button_start_mode'] ) && is_array( $button_settings['button_start_mode'] ) ? $button_settings['button_start_mode'] : array();
$button_loading  = isset( $button_settings['button_loading_mode'] ) && is_array( $button_settings['button_loading_mode'] ) ? $button_settings['button_loading_mode'] : array();
$button_ready    = isset( $button_settings['button_ready_mode'] ) && is_array( $button_settings['button_ready_mode'] ) ? $button_settings['button_ready_mode'] : array();

$background_color          = $css_color( $field( 'background_color' ), '#f5f7fb' );
$main_text_color           = $css_color( $field( 'main_text_color' ), '#171923' );
$main_description_color    = $css_color( $field( 'main_description_color' ), '#4a5568' );
$button_start_text         = $group_value( $button_start, 'button_start_text', __( 'Continue', 'wp-pwa-builder' ) );
$button_start_color        = $css_color( $group_value( $button_start, 'button_start_color' ), '#111827' );
$button_start_text_color   = $css_color( $group_value( $button_start, 'button_start_text_color' ), '#ffffff' );
$button_loading_text       = $group_value( $button_loading, 'button_loading_text' );
$button_loading_color      = $css_color( $group_value( $button_loading, 'button_loading_color' ), $button_start_color );
$button_loading_text_color = $css_color( $group_value( $button_loading, 'button_loading_text_color' ), $button_start_text_color );
$button_ready_text         = $group_value( $button_ready, 'button_ready_text', __( 'Open', 'wp-pwa-builder' ) );
$button_ready_color        = $css_color( $group_value( $button_ready, 'button_ready_color' ), $button_start_color );
$button_ready_text_color   = $css_color( $group_value( $button_ready, 'button_ready_text_color' ), $button_start_text_color );

Template_Shell::header();
?>

<main
	class="wp-pwa-default-funnel"
	style="
	<?php
	echo esc_attr(
		sprintf(
			'--pwa-bg:%s;--pwa-title-color:%s;--pwa-description-color:%s;--pwa-button-bg:%s;--pwa-button-color:%s;--pwa-button-loading-bg:%s;--pwa-button-loading-color:%s;--pwa-button-ready-bg:%s;--pwa-button-ready-color:%s;',
			$background_color,
			$main_text_color,
			$main_description_color,
			$button_start_color,
			$button_start_text_color,
			$button_loading_color,
			$button_loading_text_color,
			$button_ready_color,
			$button_ready_text_color
		)
	);
	?>
	"
>
	<?php
	while ( have_posts() ) :
		the_post();

		$app      = get_post();
		$app_id   = get_the_ID();
		$cta_url  = (string) get_post_meta( $app_id, '_pwa_cta_url', true );
		$icon_url = $app instanceof \WP_Post ? PWA_Endpoints::asset_url( $app, 'icon', 192 ) : '';
		?>
		<section class="wp-pwa-default-funnel__panel" data-pwa-template="default">
			<?php if ( $icon_url ) : ?>
				<img class="wp-pwa-default-funnel__icon" src="<?php echo esc_url( $icon_url ); ?>" alt="">
			<?php endif; ?>

			<h1 class="wp-pwa-default-funnel__title"><?php the_title(); ?></h1>

			<?php if ( has_excerpt() ) : ?>
				<p class="wp-pwa-default-funnel__description"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>

			<?php if ( $cta_url !== '' ) : ?>
				<a
					class="wp-pwa-default-funnel__button analytic-url"
					href="<?php echo esc_url( $cta_url ); ?>"
					data-pwa-track="default_cta"
					data-pwa-install
					data-pwa-loading-label="<?php echo esc_attr( $button_loading_text ); ?>"
					data-pwa-open-label="<?php echo esc_attr( $button_ready_text ); ?>"
				>
					<?php echo esc_html( $button_start_text ); ?>
				</a>
			<?php endif; ?>
		</section>
		<?php
	endwhile;
	?>
</main>

<?php
Template_Shell::footer();
