<?php

declare(strict_types=1);

namespace WP_PWA_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Types {

	public const APP_POST_TYPE = 'pwa_app';

	private const IMAGE_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
	);

	public function hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::APP_POST_TYPE, array( $this, 'save_app_meta' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	public static function register(): void {
		register_post_type(
			self::APP_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'PWA Apps', 'wp-pwa-builder' ),
					'singular_name' => __( 'PWA App', 'wp-pwa-builder' ),
					'add_new_item'  => __( 'Add New PWA App', 'wp-pwa-builder' ),
					'edit_item'     => __( 'Edit PWA App', 'wp-pwa-builder' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-smartphone',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'revisions', 'excerpt', 'custom-fields' ),
				'rewrite'      => array(
					'slug'       => 'apps',
					'with_front' => false,
				),
				'has_archive'  => false,
			)
		);
	}

	public function add_meta_boxes(): void {
		add_meta_box(
			'wp-pwa-builder-settings',
			__( 'PWA Settings', 'wp-pwa-builder' ),
			array( $this, 'render_app_meta_box' ),
			self::APP_POST_TYPE,
			'side'
		);
	}

	public function render_app_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'wp_pwa_builder_save_app', 'wp_pwa_builder_nonce' );

		$short_name           = (string) get_post_meta( $post->ID, '_pwa_short_name', true );
		$theme_color          = (string) get_post_meta( $post->ID, '_pwa_theme_color', true );
		$background_color     = (string) get_post_meta( $post->ID, '_pwa_background_color', true );
		$cta_url              = (string) get_post_meta( $post->ID, '_pwa_cta_url', true );
		$app_icon_id          = absint( get_post_meta( $post->ID, 'pwa_app_icon', true ) );
		$screenshot_wide_id   = absint( get_post_meta( $post->ID, 'pwa_screenshot_wide', true ) );
		$screenshot_narrow_id = absint( get_post_meta( $post->ID, 'pwa_screenshot_narrow', true ) );
		$selected_niche       = Niche_Registry::selected_niche( $post->ID );
		$niches               = Niche_Registry::niches();
		$selected_template    = Template_Registry::selected_template( $post->ID );
		$templates            = Template_Registry::templates_for_niche( $selected_niche );

		?>
		<p>
			<label for="wp-pwa-niche"><?php esc_html_e( 'Niche', 'wp-pwa-builder' ); ?></label>
			<select class="widefat" id="wp-pwa-niche" name="wp_pwa_niche">
				<?php foreach ( $niches as $niche_key => $niche_label ) : ?>
					<option value="<?php echo esc_attr( $niche_key ); ?>" <?php selected( $selected_niche, $niche_key ); ?>>
						<?php echo esc_html( $niche_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wp-pwa-template"><?php esc_html_e( 'Template', 'wp-pwa-builder' ); ?></label>
			<select class="widefat" id="wp-pwa-template" name="wp_pwa_template">
				<?php foreach ( $templates as $template_key => $template_config ) : ?>
					<option value="<?php echo esc_attr( $template_key ); ?>" <?php selected( $selected_template, $template_key ); ?>>
						<?php echo esc_html( $template_config['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wp-pwa-short-name"><?php esc_html_e( 'Short name', 'wp-pwa-builder' ); ?></label>
			<input class="widefat" id="wp-pwa-short-name" name="wp_pwa_short_name" value="<?php echo esc_attr( $short_name ); ?>" maxlength="24">
		</p>
		<p>
			<label for="wp-pwa-theme-color"><?php esc_html_e( 'Manifest theme color', 'wp-pwa-builder' ); ?></label>
			<input class="widefat" id="wp-pwa-theme-color" name="wp_pwa_theme_color" value="<?php echo esc_attr( $theme_color ?: '#121212' ); ?>" pattern="^#[0-9a-fA-F]{6}$">
		</p>
		<p>
			<label for="wp-pwa-background-color"><?php esc_html_e( 'Manifest background color', 'wp-pwa-builder' ); ?></label>
			<input class="widefat" id="wp-pwa-background-color" name="wp_pwa_background_color" value="<?php echo esc_attr( $background_color ?: '#ffffff' ); ?>" pattern="^#[0-9a-fA-F]{6}$">
		</p>
		<p>
			<label for="wp-pwa-cta-url"><?php esc_html_e( 'Default CTA URL', 'wp-pwa-builder' ); ?></label>
			<input class="widefat" id="wp-pwa-cta-url" name="wp_pwa_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" type="url">
		</p>
		<?php
		$this->render_media_field(
			'pwa_app_icon',
			__( 'App icon', 'wp-pwa-builder' ),
			$app_icon_id,
			__( 'Use PNG, JPG, or WebP. SVG is not supported for PWA icons because the plugin crops and exports 192x192 and 512x512 PNG icons.', 'wp-pwa-builder' )
		);
		$this->render_media_field(
			'pwa_screenshot_wide',
			__( 'Wide screenshot', 'wp-pwa-builder' ),
			$screenshot_wide_id,
			__( 'Use PNG, JPG, or WebP. Recommended size: 1280x720.', 'wp-pwa-builder' )
		);
		$this->render_media_field(
			'pwa_screenshot_narrow',
			__( 'Mobile screenshot', 'wp-pwa-builder' ),
			$screenshot_narrow_id,
			__( 'Use PNG, JPG, or WebP. Recommended size: 390x844.', 'wp-pwa-builder' )
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Icon and screenshots are used in the PWA manifest install UI.', 'wp-pwa-builder' ); ?>
		</p>
		<?php
	}

	public function save_app_meta( int $post_id ): void {
		if ( ! isset( $_POST['wp_pwa_builder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_pwa_builder_nonce'] ) ), 'wp_pwa_builder_save_app' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$short_name           = isset( $_POST['wp_pwa_short_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_pwa_short_name'] ) ) : '';
		$theme_color          = isset( $_POST['wp_pwa_theme_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['wp_pwa_theme_color'] ) ) : '';
		$background_color     = isset( $_POST['wp_pwa_background_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['wp_pwa_background_color'] ) ) : '';
		$cta_url              = isset( $_POST['wp_pwa_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['wp_pwa_cta_url'] ) ) : '';
		$app_icon_id          = $this->validated_image_id( isset( $_POST['pwa_app_icon'] ) ? absint( $_POST['pwa_app_icon'] ) : 0, 'App icon' );
		$screenshot_wide_id   = $this->validated_image_id( isset( $_POST['pwa_screenshot_wide'] ) ? absint( $_POST['pwa_screenshot_wide'] ) : 0, 'Wide screenshot' );
		$screenshot_narrow_id = $this->validated_image_id( isset( $_POST['pwa_screenshot_narrow'] ) ? absint( $_POST['pwa_screenshot_narrow'] ) : 0, 'Mobile screenshot' );
		$niche                = isset( $_POST['wp_pwa_niche'] ) ? sanitize_key( wp_unslash( $_POST['wp_pwa_niche'] ) ) : 'igaming';
		$template             = isset( $_POST['wp_pwa_template'] ) ? sanitize_key( wp_unslash( $_POST['wp_pwa_template'] ) ) : 'default';

		if ( ! isset( Niche_Registry::niches()[ $niche ] ) ) {
			$niche = 'igaming';
		}

		$available_templates = Template_Registry::templates_for_niche( $niche );

		if ( ! isset( $available_templates[ $template ] ) ) {
			$first_template = array_key_first( $available_templates );
			$template       = array_key_exists( 'default', $available_templates ) ? 'default' : ( is_string( $first_template ) ? $first_template : 'default' );
		}

		update_post_meta( $post_id, Niche_Registry::META_KEY, $niche );
		update_post_meta( $post_id, Template_Registry::META_KEY, $template );
		update_post_meta( $post_id, '_pwa_short_name', $short_name );
		update_post_meta( $post_id, '_pwa_theme_color', $theme_color ?: '#121212' );
		update_post_meta( $post_id, '_pwa_background_color', $background_color ?: '#ffffff' );
		update_post_meta( $post_id, '_pwa_cta_url', $cta_url );
		update_post_meta( $post_id, 'pwa_app_icon', $app_icon_id );
		update_post_meta( $post_id, 'pwa_screenshot_wide', $screenshot_wide_id );
		update_post_meta( $post_id, 'pwa_screenshot_narrow', $screenshot_narrow_id );
	}

	public function render_admin_notices(): void {
		$notice = get_transient( 'wp_pwa_builder_media_notice' );

		if ( ! is_string( $notice ) || $notice === '' ) {
			return;
		}

		delete_transient( 'wp_pwa_builder_media_notice' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
		<?php
	}

	private function render_media_field( string $field_name, string $label, int $attachment_id, string $description = '' ): void {
		$preview = $attachment_id > 0 ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'style' => 'max-width:100%;height:auto;margin-top:8px;' ) ) : '';

		?>
		<p class="wp-pwa-media-field">
			<label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="hidden" id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
			<button type="button" class="button wp-pwa-media-select" data-target="<?php echo esc_attr( $field_name ); ?>">
				<?php esc_html_e( 'Choose image', 'wp-pwa-builder' ); ?>
			</button>
			<button type="button" class="button-link-delete wp-pwa-media-remove" data-target="<?php echo esc_attr( $field_name ); ?>" <?php disabled( $attachment_id, 0 ); ?>>
				<?php esc_html_e( 'Remove', 'wp-pwa-builder' ); ?>
			</button>
			<span class="wp-pwa-media-preview" data-preview="<?php echo esc_attr( $field_name ); ?>">
				<?php echo wp_kses_post( $preview ); ?>
			</span>
			<?php if ( $description !== '' ) : ?>
				<span class="description" style="display:block;margin-top:6px;">
					<?php echo esc_html( $description ); ?>
				</span>
			<?php endif; ?>
		</p>
		<?php
	}

	private function validated_image_id( int $attachment_id, string $label ): int {
		if ( $attachment_id <= 0 ) {
			return 0;
		}

		$mime_type = get_post_mime_type( $attachment_id );

		if ( is_string( $mime_type ) && in_array( $mime_type, self::IMAGE_MIME_TYPES, true ) ) {
			return $attachment_id;
		}

		set_transient(
			'wp_pwa_builder_media_notice',
			sprintf(
				/* translators: 1: field label, 2: allowed file extensions. */
				__( '%1$s was not saved. Please use one of these raster image formats: %2$s.', 'wp-pwa-builder' ),
				$label,
				'PNG, JPG, WebP'
			),
			30
		);

		return 0;
	}
}
