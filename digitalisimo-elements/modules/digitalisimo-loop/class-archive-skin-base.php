<?php
namespace Digitalisimo\Elements\Loop;

defined( 'ABSPATH' ) || exit;

/**
 * Pintado de las pieles de Entradas del archivo: sin entradas, el mensaje «Nada encontrado» dentro del contenedor (sin
 * etiquetas HTML salvo que `elementor_pro/theme_builder/archive/escape_nothing_found_message` lo permita, como PRO).
 *
 * Derivado de PRO Elements (GPL): `modules/theme-builder/skins/posts-archive-skin-base.php`.
 */
trait Archive_Skin_Base {

	public function render() {
		$this->parent->query_posts();

		$wp_query = $this->parent->get_query();

		if ( ! $wp_query || ! $wp_query->found_posts ) {
			$this->render_loop_header();

			/** Mismo filtro que PRO: `false` permite etiquetas HTML en el mensaje. */
			$should_escape = apply_filters( 'elementor_pro/theme_builder/archive/escape_nothing_found_message', true );

			$message = (string) $this->parent->get_settings_for_display( 'nothing_found_message' );
			if ( $should_escape ) {
				$message = esc_html( $message );
			}

			echo '<div class="digi-posts__nothing">' . $message . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba salvo que el filtro lo impida.

			$this->render_loop_footer();

			return;
		}

		parent::render();
	}
}
