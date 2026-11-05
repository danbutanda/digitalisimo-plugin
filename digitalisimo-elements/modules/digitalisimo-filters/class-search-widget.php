<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Buscador de un Loop Grid (`digitalisimo-loop-search`, F7b V.5 y C2-30).
 *
 * - Destino Loop Grid: `e-filter-{bucle}-digi_s_{widget}`, tipo `digitalisimo_search`; el servidor aplica `s` (de 2 a
 *   100 caracteres) y conserva el orden del bucle. Destino «Archivo»: el buscador nativo hacia la página de resultados
 *   (`s` en un archivo de término cambiaría la jerarquía de plantillas, C1-14).
 * - Accesible: `form[role=search]` con nombre, etiqueta visible (u oculta a la vista, nunca sólo el marcador de
 *   posición), envío con botón (no se busca al teclear) y «Limpiar» con nombre propio. El anuncio y `aria-busy` del
 *   refresco son los del coordinador.
 */
class Search_Widget extends Filter_Widget {
	const TYPE = Query::SEARCH;

	public function get_name() {
		return 'digitalisimo-loop-search';
	}

	public function get_title() {
		return 'Buscador del Loop Grid';
	}

	public function get_icon() {
		return 'eicon-site-search';
	}

	public function get_keywords() {
		return array( 'buscar', 'búsqueda', 'buscador', 'filtro', 'loop grid', 'digitalisimo' );
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_filter', array( 'label' => 'Buscador' ) );
		$this->register_target_controls( 'Resultados de búsqueda del sitio', 'Envía la búsqueda a la página de resultados del sitio. Un archivo de categoría o de etiqueta no se puede buscar sin cambiar de página: para buscar dentro de una lista, usa el destino Loop Grid.' );
		$this->text_control( 'label', 'Etiqueta', 'Buscar', array( 'separator' => 'before' ) );
		$this->add_control( 'show_label', array( 'label' => 'Mostrar la etiqueta', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => 'Oculta, la siguen leyendo los lectores de pantalla.', 'style_transfer' => false ) );
		$this->text_control( 'placeholder', 'Texto de ejemplo', '' );
		$this->text_control( 'button_text', 'Texto del botón', 'Buscar' );
		$this->text_control( 'clear_text', 'Texto para limpiar', 'Limpiar', array( 'condition' => array( 'destination' => 'loop' ) ) );
		$this->end_controls_section();
		$this->register_style_controls();
	}

	protected function render() {
		if ( ! $this->ready() ) {
			return;
		}
		$loop  = 'loop' === $this->destination();
		$label = $this->setting_text( 'label', 'Buscar' );
		$name  = $loop ? $this->param_name() : 's';
		$value = self::param( $name );
		$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, Query::SEARCH_MAX, 'UTF-8' ) : substr( $value, 0, Query::SEARCH_MAX );
		$extra = array(
			'role'          => 'search',
			'data-type'     => Query::SEARCH,
			'data-key'      => $this->key(),
			'data-multiple' => '0',
		);
		if ( ! $loop ) {
			$extra['action'] = esc_url( home_url( '/' ) );
		} else {
			$this->require_pro();
		}
		$id          = 'digi-s-' . $this->fid();
		$placeholder = $this->setting_text( 'placeholder', '' );
		echo '<form' . $this->form_attributes( 'search', $label, $extra ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en form_attributes().
		if ( $loop ) {
			echo $this->helper_span(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<div class="digi-filter__search">';
		echo '<label class="digi-filter__label' . ( 'yes' === $this->get_settings_for_display( 'show_label' ) ? '' : ' digi-filter__sr' ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input class="digi-filter__input" type="search" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" minlength="' . (int) Query::SEARCH_MIN . '" maxlength="' . (int) Query::SEARCH_MAX . '"' . ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . '>';
		echo '<button type="submit" class="digi-filter__submit">' . esc_html( $this->setting_text( 'button_text', 'Buscar' ) ) . '</button>';
		echo '</div>';
		if ( $loop ) {
			$own = array( $name );
			echo $this->hidden_fields( $own ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="digi-filter__actions"><a class="digi-filter__clear" rel="nofollow" href="' . esc_url( $this->url_with( $this->kept_params( $own ) ) ) . '" aria-label="' . esc_attr( 'Limpiar la búsqueda' ) . '">' . esc_html( $this->setting_text( 'clear_text', 'Limpiar' ) ) . '</a></div>';
		}
		echo '</form>';
	}

	/** Con destino «Archivo» basta un documento: el buscador nativo no lleva `digi_f`. */
	protected function ready(): bool {
		if ( 'archive' === $this->destination() ) {
			return '' !== $this->fid();
		}
		return parent::ready();
	}
}
