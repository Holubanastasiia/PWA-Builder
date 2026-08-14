<?php

declare(strict_types=1);

namespace WP_PWA_Builder\ACF;

use WP_PWA_Builder\Niche_Registry;
use WP_PWA_Builder\Post_Types;
use WP_PWA_Builder\Template_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Location_PWA_Template extends \ACF_Location {

	public function initialize(): void {
		$this->name        = 'pwa_template';
		$this->label       = __( 'PWA Template', 'wp-pwa-builder' );
		$this->category    = __( 'PWA Builder', 'wp-pwa-builder' );
		$this->object_type = 'post';
	}

	/**
	 * @param array<string, string> $rule
	 * @param array<string, mixed> $screen
	 * @param array<string, mixed> $field_group
	 */
	public function match( $rule, $screen, $field_group ): bool {
		unset( $field_group );

		$post_id = absint( $screen['post_id'] ?? 0 );

		if ( $post_id <= 0 ) {
			return $this->matches_operator( false, (string) ( $rule['operator'] ?? '==' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || $post->post_type !== Post_Types::APP_POST_TYPE ) {
			return $this->matches_operator( false, (string) ( $rule['operator'] ?? '==' ) );
		}

		$selected_template = Template_Registry::selected_template( $post_id );
		$expected_template = sanitize_key( (string) ( $rule['value'] ?? '' ) );
		$matches           = $expected_template !== '' && $selected_template === $expected_template;

		return $this->matches_operator( $matches, (string) ( $rule['operator'] ?? '==' ) );
	}

	/**
	 * @param array<string, string> $rule
	 * @return array<string, string>
	 */
	public function get_values( $rule ): array {
		unset( $rule );

		$values = array();

		foreach ( Template_Registry::templates() as $template_key => $template ) {
			$niches = array_map(
				static fn( string $niche ): string => Niche_Registry::niches()[ $niche ] ?? $niche,
				$template['niches']
			);
			$suffix = $niches !== array() ? ' (' . implode( ', ', $niches ) . ')' : '';

			$values[ $template_key ] = $template['name'] . $suffix;
		}

		return $values;
	}

    /**
     * @param array<string, string> $rule
     */
	public function get_object_subtype( $rule ): string {
		unset( $rule );

		return Post_Types::APP_POST_TYPE;
	}

	private function matches_operator( bool $matches, string $operator ): bool {
		return $operator === '!=' ? ! $matches : $matches;
	}
}
