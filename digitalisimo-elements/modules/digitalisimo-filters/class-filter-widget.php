<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los cuatro filtros ampliados de F7b (V.5): búsqueda, campo, orden y por página. Neutra: sin WooCommerce.
 *
 * - **Destino** «Loop Grid de este documento» (por defecto) o «Archivo». En el primero, los nombres son
 *   `e-filter-{bucle}-{prefijo}{widget}` (`digi_s_`, `digi_m_`, `digi_o_`, `digi_pp_` y el ID de este widget): el
 *   servidor los lee al pintar y el coordinador de F4-Woo (`filters.js`) los manda en el refresco; en los dos casos
 *   `Query` sólo aplica lo que este widget guardado declara. En «Archivo», `{prefijo}{widget}` y `digi_f=<documento>-
 *   <widget>` sobre la consulta principal (C1-14); la búsqueda con este destino es el buscador nativo del sitio.
 * - **Marcado:** el de F4-Woo (`Filter_Form`): `<form method="get">`, `fieldset` y `legend`, el `<span class="e-filter"
 *   hidden>` del bucle, campos ocultos con los demás parámetros y enlaces con `rel="nofollow"`; mismo `filters-form.js`
 *   y mismo coordinador (`data-type` dice el tipo del registro).
 * - Hoja `digitalisimo-filters` (lo que `filters.css` no cubre) además de la de F4-Woo. Sin JavaScript propio en el front.
 * - Todo control que no es de estilo lleva `style_transfer => false` (V.12): «Pegar estilo», «Aplicar a todos» y los
 *   presets no copian el destino, el bucle ni la clave.
 */
abstract class Filter_Widget extends \Elementor\Widget_Base {
	use \ElementorPro\Modules\Posts\Traits\Pagination_Trait;
	use Filter_Form;

	const COORDINATOR = Module::COORDINATOR;
	const FORM_HANDLE = Module::FORM_HANDLE;
	const STYLE       = Module::STYLE;

	/** Parámetros de paginación que un filtro nuevo descarta. */
	const PAGINATION = array( 'paged', 'page' );

	/** Tipo del registro (`Query::SEARCH`…): lo define cada widget. */
	const TYPE = '';

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_style_depends() {
		return array( 'digitalisimo-woo-filters', 'digitalisimo-filters' );
	}

	public function get_script_depends() {
		return array( 'digitalisimo-woo-filters', 'digitalisimo-woo-filters-form' );
	}

	public function get_keywords() {
		return array( 'filtro', 'loop grid', 'archivo', 'digitalisimo' );
	}

	/**
	 * Destino, bucle y los avisos del editor, al principio de la sección `section_filter`.
	 *
	 * @param string $archive_label Texto del destino «Archivo».
	 * @param string $archive_note  Aviso del destino «Archivo».
	 */
	protected function register_target_controls( string $archive_label, string $archive_note ): void {
		$c = '\\Elementor\\Controls_Manager';
		$this->add_control( 'destination', array( 'label' => 'Destino', 'type' => $c::SELECT, 'default' => 'loop', 'options' => array( 'loop' => 'Loop Grid de este documento', 'archive' => $archive_label ), 'style_transfer' => false ) );
		$this->add_control( 'selected_element', array( 'label' => 'Loop Grid', 'type' => $c::SELECT, 'options' => array( '' => 'Elige un Loop Grid' ), 'label_block' => true, 'condition' => array( 'destination' => 'loop' ), 'style_transfer' => false ) );
		$this->add_control( 'loop_note', array( 'type' => $c::RAW_HTML, 'raw' => 'Filtra un Loop Grid de este mismo documento sin recargar la página. El Loop Carousel no se puede filtrar, y un Loop Grid con «Consulta actual» tampoco: dale su propia consulta.', 'content_classes' => 'elementor-descriptor', 'condition' => array( 'destination' => 'loop' ), 'style_transfer' => false ) );
		$this->add_control( 'archive_note', array( 'type' => $c::RAW_HTML, 'raw' => $archive_note, 'content_classes' => 'elementor-descriptor', 'condition' => array( 'destination' => 'archive' ), 'style_transfer' => false ) );
	}

	/** Texto con su valor por defecto. */
	protected function text_control( string $id, string $label, string $default, array $extra = array() ): void {
		$this->add_control( $id, array_merge( array( 'label' => $label, 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $default, 'style_transfer' => false ), $extra ) );
	}

	/** `loop` o `archive`. */
	protected function destination(): string {
		return 'archive' === $this->get_settings_for_display( 'destination' ) ? 'archive' : 'loop';
	}

	/** Textos de la región viva del coordinador. */
	protected function status_texts(): array {
		return array( 'updated' => 'Resultados actualizados', 'none' => 'Ningún resultado con estos filtros', 'one' => '1 resultado', 'many' => '%d resultados' );
	}

	/** Motivo con el que se piden los paquetes de PRO (`Pro_Gate::require()`). */
	protected function gate_reason(): string {
		return 'f7b-filter';
	}

	/** ID de este widget (el `<fid>` de los nombres), o ''. */
	protected function fid(): string {
		$id = (string) $this->get_id();
		return preg_match( '/^[a-z0-9]{7,8}$/', $id ) ? $id : '';
	}

	/** Clave en el almacén del bucle: `{prefijo}{widget}{sufijo}`. */
	protected function key( string $suffix = '' ): string {
		return Query::PREFIXES[ static::TYPE ] . $this->fid() . $suffix;
	}

	/** Nombre en la URL: con el prefijo del bucle (destino Loop Grid) o tal cual (destino «Archivo»). */
	protected function param_name( string $suffix = '' ): string {
		return ( 'loop' === $this->destination() ? $this->loop_prefix() : '' ) . $this->key( $suffix );
	}

	/** Separador de una lista en la URL: `~` en el bucle (el «O» de PRO) y `,` en el archivo (el de un formulario GET). */
	protected function glue(): string {
		return 'loop' === $this->destination() ? '~' : ',';
	}

	/** Ajustes normalizados (los mismos que aplica el servidor). */
	protected function filter_settings(): array {
		return Query::normalize( $this->get_name(), $this->get_settings_for_display() );
	}

	/** Documento que se pinta (el ID que lleva `digi_f`). */
	protected function document_id(): int {
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		$docs   = is_object( $plugin ) && isset( $plugin->documents ) ? $plugin->documents : null;
		$doc    = is_object( $docs ) && method_exists( $docs, 'get_current' ) ? $docs->get_current() : null;
		return is_object( $doc ) && method_exists( $doc, 'get_main_id' ) ? (int) $doc->get_main_id() : 0;
	}

	/** Valor de `digi_f`, o ''. */
	protected function archive_reference(): string {
		$doc = $this->document_id();
		return $doc > 0 && '' !== $this->fid() ? $doc . '-' . $this->fid() : '';
	}

	/** ¿Puede pintarse? Con destino Loop Grid, un bucle elegido; con «Archivo», un documento. Si no, el aviso del editor. */
	protected function ready(): bool {
		if ( '' === $this->fid() ) {
			return false;
		}
		if ( 'loop' === $this->destination() ) {
			if ( '' === $this->loop_id() ) {
				$this->editor_message( 'Elige el Loop Grid que filtra este widget.' );
				return false;
			}
			return true;
		}
		if ( '' === $this->archive_reference() ) {
			$this->editor_message( 'Guarda el documento para usar este destino.' );
			return false;
		}
		return true;
	}

	/** Nombres propios del formulario (y `digi_f` con destino «Archivo»). */
	protected function own( array $names ): array {
		return 'archive' === $this->destination() ? array_merge( $names, array( Query::ARCHIVE_PARAM ) ) : $names;
	}

	/** Parámetros conservados de un enlace, con `digi_f` si hace falta. */
	protected function link_params( array $kept ): array {
		if ( 'archive' === $this->destination() ) {
			$kept[ Query::ARCHIVE_PARAM ] = $this->archive_reference();
		}
		return $kept;
	}

	/** Campos ocultos: los conservados y, con «Archivo», `digi_f`. */
	protected function form_hidden( array $own ): string {
		$html = $this->hidden_fields( $own );
		if ( 'archive' === $this->destination() ) {
			$html .= '<input type="hidden" name="' . esc_attr( Query::ARCHIVE_PARAM ) . '" value="' . esc_attr( $this->archive_reference() ) . '">';
		}
		return $html;
	}

	/** Texto de un ajuste, o el de respaldo si está vacío. */
	protected function setting_text( string $key, string $fallback ): string {
		$value = $this->get_settings_for_display( $key );
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return '' !== $value ? $value : $fallback;
	}

	/** `<legend>` (oculto a la vista, pero no al lector, si el ajuste lo pide). */
	protected function legend( string $label ): string {
		$show = 'yes' === $this->get_settings_for_display( 'show_legend' );
		return '<legend class="digi-filter__legend' . ( $show ? '' : ' digi-filter__sr' ) . '">' . esc_html( $label ) . '</legend>';
	}

	/**
	 * Valores elegidos en la URL (fichas), sólo los de `$options`.
	 *
	 * @param string $name    Parámetro.
	 * @param array  $options `ficha => texto`.
	 * @return string[]
	 */
	protected function selected( string $name, array $options ): array {
		$value = self::param( $name );
		if ( '' === $value ) {
			return array();
		}
		$parts = 'archive' === $this->destination() ? explode( ',', $value ) : explode( false !== strpos( $value, '~' ) ? '~' : ' ', $value );
		$out   = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( isset( $options[ $part ] ) && ! in_array( $part, $out, true ) ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * Opciones de un filtro de lista: casillas (o radios), botones o desplegable, con su `fieldset`, campos ocultos y
	 * acciones. Botones = enlaces con la selección que resultaría de pulsarlos, `role="button"`, `aria-pressed` y
	 * `rel="nofollow"` (funcionan sin JavaScript); con el coordinador, se alternan sin recargar.
	 *
	 * @param array $a `kind`, `label`, `options` (ficha => texto), `multiple`, `display`, `any`, `button`, `clear`,
	 *                 `clear_label` y `extra` (atributos del formulario).
	 */
	protected function render_choices( array $a ): void {
		$name     = $this->param_name();
		$own      = $this->own( array( $name ) );
		$kept     = $this->kept_params( $own );
		$options  = $a['options'];
		$multiple = ! empty( $a['multiple'] ) && 'dropdown' !== $a['display'];
		$selected = $this->selected( $name, $options );
		if ( ! $multiple ) {
			$selected = array_slice( $selected, 0, 1 );
		}
		$extra = array_merge(
			array(
				'class'         => 'digi-filter digi-filter--' . $a['kind'] . ' digi-filter--' . $a['display'],
				'data-type'     => static::TYPE,
				'data-key'      => $this->key(),
				'data-logic'    => 'OR',
				'data-multiple' => $multiple ? '1' : '0',
				'data-display'  => $a['display'],
			),
			isset( $a['extra'] ) ? $a['extra'] : array()
		);
		echo '<form' . $this->form_attributes( $a['kind'], $a['form_label'], $extra ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en form_attributes().
		if ( 'loop' === $this->destination() ) {
			echo $this->helper_span(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<fieldset class="digi-filter__set">' . $this->legend( $a['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'dropdown' === $a['display'] ) {
			echo '<select class="digi-filter__select" name="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $a['label'] ) . '"><option value="">' . esc_html( $a['any'] ) . '</option>';
			foreach ( $options as $value => $text ) {
				echo '<option value="' . esc_attr( (string) $value ) . '"' . ( in_array( (string) $value, $selected, true ) ? ' selected' : '' ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checkboxes' === $a['display'] ) {
			$type = $multiple ? 'checkbox' : 'radio';
			echo '<ul class="digi-filter__list" role="list">';
			if ( ! $multiple ) {
				echo '<li><label class="digi-filter__option"><input type="radio" name="' . esc_attr( $name ) . '" value=""' . ( $selected ? '' : ' checked' ) . '><span>' . esc_html( $a['any'] ) . '</span></label></li>';
			}
			foreach ( $options as $value => $text ) {
				echo '<li><label class="digi-filter__option"><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( in_array( (string) $value, $selected, true ) ? ' checked' : '' ) . '><span>' . esc_html( $text ) . '</span></label></li>';
			}
			echo '</ul>';
		} else {
			echo '<ul class="digi-filter__list" role="list">';
			foreach ( $options as $value => $text ) {
				$value = (string) $value;
				$on    = in_array( $value, $selected, true );
				$next  = $multiple ? ( $on ? array_values( array_diff( $selected, array( $value ) ) ) : array_merge( $selected, array( $value ) ) ) : ( $on ? array() : array( $value ) );
				$raw   = $next ? array( $name => self::join_terms( $next, $this->glue() ) ) : array();
				$href  = $this->url_with( $next ? $this->link_params( $kept ) : $kept, $raw );
				echo '<li><a class="digi-filter__term" rel="nofollow" role="button" aria-pressed="' . ( $on ? 'true' : 'false' ) . '" data-value="' . esc_attr( $value ) . '" href="' . esc_url( $href ) . '"><span>' . esc_html( $text ) . '</span></a></li>';
			}
			echo '</ul>';
		}
		echo '</fieldset>';
		echo $this->form_hidden( $own ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="digi-filter__actions">';
		if ( 'buttons' !== $a['display'] ) {
			echo '<button type="submit" class="digi-filter__submit">' . esc_html( $a['button'] ) . '</button>';
		}
		echo '<a class="digi-filter__clear" rel="nofollow" href="' . esc_url( $this->url_with( $kept ) ) . '" aria-label="' . esc_attr( $a['clear_label'] ) . '">' . esc_html( $a['clear'] ) . '</a></div>';
		echo '</form>';
	}

	/** Controles comunes del final de la sección: título, mostrar el título, botón y limpiar. */
	protected function register_text_controls( string $legend, string $button, string $clear ): void {
		$this->text_control( 'legend', 'Título', $legend, array( 'separator' => 'before' ) );
		$this->add_control( 'show_legend', array( 'label' => 'Mostrar el título', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'style_transfer' => false ) );
		$this->text_control( 'button_text', 'Texto del botón', $button, array( 'description' => 'Con destino Loop Grid sólo se ve sin JavaScript; con «Archivo», siempre.' ) );
		$this->text_control( 'clear_text', 'Texto para quitar el filtro', $clear );
	}
}
