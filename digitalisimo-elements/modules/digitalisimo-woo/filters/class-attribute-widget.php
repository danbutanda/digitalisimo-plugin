<?php
namespace Digitalisimo\Elements\Woo\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Filtro de atributo (`digitalisimo-woo-attribute-filter`, F4-Woo v1 §5.5 a y V.8).
 *
 * Con destino Loop Grid, el nombre es `e-filter-{bucle}-digiattr_<taxonomía>` (tipo `digitalisimo_attribute`, que da
 * el mismo conjunto que la tienda nativa, con «Ocultar agotados» y con o sin la tabla de atributos); con «Tienda»,
 * `filter_<atributo>` (términos separados por comas) y `query_type_<atributo>`. Casillas (radios con una sola
 * selección), botones, muestras (los datos de `Swatches\Data`, los de WooCommerce) o desplegable. Botones y muestras
 * son enlaces con `rel="nofollow"` que funcionan sin JS (V.13).
 *
 * Sin JS, con casillas y destino Loop Grid sólo vale un término (los nombres repetidos no caben en `e-filter-…`; gana
 * el último): se declara. Los botones y las muestras sí combinan varios, porque cada enlace lleva la selección entera.
 */
class Attribute_Widget extends Filter_Widget {
	const MAX_TERMS = 100;
	const DISPLAYS  = array( 'checkboxes', 'buttons', 'swatches', 'dropdown' );

	public function get_name() {
		return 'digitalisimo-woo-attribute-filter';
	}

	public function get_title() {
		return 'Filtro de atributo';
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	/**
	 * Atributos globales para el control: sólo con WooCommerce y fuera de la pila optimizada del front (V.12.1, V-M18).
	 * Los que no caben en la URL (nombres no ASCII) no se ofrecen (V.8.1).
	 */
	private static function attribute_options(): array {
		$options  = array( '' => 'Elige un atributo' );
		$optimize = class_exists( '\Elementor\Core\Frontend\Performance' ) && \Elementor\Core\Frontend\Performance::should_optimize_controls();
		if ( $optimize || ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return $options;
		}
		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			if ( preg_match( '/^pa_[a-z0-9_\-]{1,29}$/', $taxonomy ) ) {
				$options[ $taxonomy ] = (string) $attribute->attribute_label;
			}
		}
		return $options;
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_filter', array( 'label' => 'Filtro' ) );
		$this->register_target_controls();
		$this->add_control( 'attribute', array( 'label' => 'Atributo', 'type' => $c::SELECT, 'options' => self::attribute_options(), 'default' => '', 'separator' => 'before', 'label_block' => true ) );
		$this->add_control( 'display', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'checkboxes', 'options' => array( 'checkboxes' => 'Casillas', 'buttons' => 'Botones', 'swatches' => 'Muestras', 'dropdown' => 'Desplegable' ), 'description' => 'Las muestras usan el color o la imagen de cada término (pantalla de atributos de WooCommerce).' ) );
		$this->add_control( 'selection', array( 'label' => 'Selección', 'type' => $c::SELECT, 'default' => 'multiple', 'options' => array( 'multiple' => 'Varios términos', 'single' => 'Un término' ), 'condition' => array( 'display!' => 'dropdown' ) ) );
		$this->add_control( 'logic', array( 'label' => 'Con varios términos', 'type' => $c::SELECT, 'default' => 'OR', 'options' => array( 'OR' => 'Cualquiera de ellos', 'AND' => 'Todos a la vez' ), 'condition' => array( 'display!' => 'dropdown', 'selection' => 'multiple' ) ) );
		$this->add_control( 'hide_empty', array( 'label' => 'Ocultar términos sin productos', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => 'Con «Ocultar productos agotados» y la tabla de atributos de WooCommerce, también los que sólo tienen productos agotados.' ) );
		$this->add_control( 'legend', array( 'label' => 'Título', 'type' => $c::TEXT, 'placeholder' => 'El nombre del atributo', 'separator' => 'before' ) );
		$this->add_control( 'show_legend', array( 'label' => 'Mostrar el título', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'any_text', array( 'label' => 'Texto de «cualquiera»', 'type' => $c::TEXT, 'default' => 'Cualquiera', 'conditions' => array( 'relation' => 'or', 'terms' => array( array( 'name' => 'display', 'value' => 'dropdown' ), array( 'name' => 'selection', 'value' => 'single' ) ) ) ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => 'Aplicar', 'description' => 'Sólo se ve sin JavaScript o con destino «Tienda».', 'condition' => array( 'display' => array( 'checkboxes', 'dropdown' ) ) ) );
		$this->add_control( 'clear_text', array( 'label' => 'Texto para limpiar', 'type' => $c::TEXT, 'default' => 'Limpiar' ) );
		$this->end_controls_section();
		$this->register_style_controls();
	}

	/** Atributo elegido si es un atributo global válido de este sitio, o ''. */
	public function taxonomy(): string {
		$taxonomy = (string) $this->get_settings_for_display( 'attribute' );
		return Query::is_attribute( $taxonomy ) ? $taxonomy : '';
	}

	/**
	 * Términos que se ofrecen, en el orden del atributo (WooCommerce lo aplica a `get_terms()`). Con «Ocultar términos
	 * sin productos», la tabla de atributos y «Ocultar agotados», sólo los que tienen algo en existencia (R-M17).
	 *
	 * @return \WP_Term[]
	 */
	public function terms( string $taxonomy ): array {
		$hide     = 'yes' === $this->get_settings_for_display( 'hide_empty' );
		$in_stock = $hide && Query::lookup_active() && Query::hide_out_of_stock();
		$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => $hide && ! $in_stock, 'number' => self::MAX_TERMS ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		if ( $in_stock ) {
			$ids   = array_flip( Bounds::terms_in_stock( $taxonomy ) );
			$terms = array_filter(
				$terms,
				function ( $term ) use ( $ids ) {
					return isset( $ids[ (int) $term->term_id ] );
				}
			);
		}
		return array_values(
			array_filter(
				$terms,
				function ( $term ) {
					return $term instanceof \WP_Term;
				}
			)
		);
	}

	/** Nombre del atributo sin `pa_` (el de los parámetros de la tienda). */
	private static function attribute_slug( string $taxonomy ): string {
		return substr( $taxonomy, 3 );
	}

	/**
	 * Nombres y separador según el destino: `name` (el parámetro), `key` (lo que sigue a `e-filter-{bucle}-`), `type`
	 * (`query_type_*`, sólo en la tienda) y `glue`.
	 */
	public function names( string $taxonomy, bool $multiple, string $logic ): array {
		if ( 'shop' === $this->destination() ) {
			$slug = self::attribute_slug( $taxonomy );
			return array( 'name' => 'filter_' . $slug, 'key' => '', 'type' => 'query_type_' . $slug, 'glue' => ',' );
		}
		$key = Types::ATTRIBUTE_PREFIX . $taxonomy;
		return array( 'name' => $this->loop_prefix() . $key, 'key' => $key, 'type' => '', 'glue' => $multiple && 'AND' === $logic ? '+' : '~' );
	}

	/** Términos elegidos en la URL, saneados como el servidor. */
	public function selected( array $names ): array {
		$value = self::param( $names['name'] );
		if ( '' === $value ) {
			return array();
		}
		if ( ',' === $names['glue'] ) {
			$parts = explode( ',', $value );
		} else {
			$parts = explode( false !== strpos( $value, '~' ) ? '~' : ' ', $value );
		}
		return Query::slugs( $parts );
	}

	protected function render_widget() {
		$s = $this->get_settings_for_display();
		if ( 'loop' === $this->destination() && '' === $this->loop_id() ) {
			$this->editor_message( 'Elige el Loop Grid que filtra este widget.' );
			return;
		}
		$taxonomy = $this->taxonomy();
		if ( '' === $taxonomy ) {
			$this->editor_message( 'Elige un atributo global de WooCommerce.' );
			return;
		}
		$terms = $this->terms( $taxonomy );
		if ( ! $terms ) {
			$this->editor_message( 'Este atributo no tiene términos que mostrar.' );
			return;
		}
		$this->require_pro();
		$display  = in_array( isset( $s['display'] ) ? $s['display'] : '', self::DISPLAYS, true ) ? $s['display'] : 'checkboxes';
		$swatches = 'swatches' === $display && class_exists( '\Digitalisimo\Elements\Woo\Swatches\Data' );
		if ( 'swatches' === $display && ! $swatches ) {
			$display = 'buttons';
		}
		$multiple = 'dropdown' !== $display && 'single' !== ( isset( $s['selection'] ) ? $s['selection'] : 'multiple' );
		$logic    = $multiple && 'AND' === ( isset( $s['logic'] ) ? $s['logic'] : 'OR' ) ? 'AND' : 'OR';
		$names    = $this->names( $taxonomy, $multiple, $logic );
		$selected = $this->selected( $names );
		$own      = array_values( array_filter( array( $names['name'], $names['type'] ) ) );
		$kept     = $this->kept_params( $own );
		$legend   = trim( (string) ( isset( $s['legend'] ) ? $s['legend'] : '' ) );
		$label    = '' !== $legend ? $legend : ( function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $taxonomy ) : $taxonomy );
		$any      = trim( (string) ( isset( $s['any_text'] ) ? $s['any_text'] : '' ) ) ?: 'Cualquiera';
		$extra    = array(
			'class'         => 'digi-filter digi-filter--attribute digi-filter--' . $display,
			'data-key'      => $names['key'],
			'data-logic'    => $logic,
			'data-multiple' => $multiple ? '1' : '0',
			'data-display'  => $display,
		);
		echo '<form' . $this->form_attributes( 'attribute', 'Filtrar por ' . self::lower( $label ), $extra ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en form_attributes().
		echo $this->helper_span(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<fieldset class="digi-filter__set"><legend class="digi-filter__legend' . ( 'yes' === ( isset( $s['show_legend'] ) ? $s['show_legend'] : 'yes' ) ? '' : ' digi-filter__sr' ) . '">' . esc_html( $label ) . '</legend>';
		if ( 'dropdown' === $display ) {
			echo '<select class="digi-filter__select" name="' . esc_attr( $names['name'] ) . '" aria-label="' . esc_attr( $label ) . '"><option value="">' . esc_html( $any ) . '</option>';
			foreach ( $terms as $term ) {
				echo '<option value="' . esc_attr( $term->slug ) . '"' . ( in_array( $term->slug, $selected, true ) ? ' selected' : '' ) . '>' . esc_html( $term->name ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checkboxes' === $display ) {
			$type = $multiple ? 'checkbox' : 'radio';
			echo '<ul class="digi-filter__list" role="list">';
			if ( ! $multiple ) {
				echo '<li><label class="digi-filter__option"><input type="radio" name="' . esc_attr( $names['name'] ) . '" value=""' . ( $selected ? '' : ' checked' ) . '><span>' . esc_html( $any ) . '</span></label></li>';
			}
			foreach ( $terms as $term ) {
				echo '<li><label class="digi-filter__option"><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $names['name'] ) . '" value="' . esc_attr( $term->slug ) . '"' . ( in_array( $term->slug, $selected, true ) ? ' checked' : '' ) . '><span>' . esc_html( $term->name ) . '</span></label></li>';
			}
			echo '</ul>';
		} else {
			echo $this->links( $terms, $taxonomy, $names, $selected, $kept, $multiple, $logic, $swatches ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en links().
		}
		echo '</fieldset>';
		if ( 'shop' === $this->destination() && $multiple ) {
			echo '<input type="hidden" name="' . esc_attr( $names['type'] ) . '" value="' . esc_attr( strtolower( $logic ) ) . '">';
		}
		echo $this->hidden_fields( $own ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$button = trim( (string) ( isset( $s['button_text'] ) ? $s['button_text'] : '' ) ) ?: 'Aplicar';
		$clear  = trim( (string) ( isset( $s['clear_text'] ) ? $s['clear_text'] : '' ) ) ?: 'Limpiar';
		echo '<div class="digi-filter__actions">';
		if ( in_array( $display, array( 'checkboxes', 'dropdown' ), true ) ) {
			echo '<button type="submit" class="digi-filter__submit">' . esc_html( $button ) . '</button>';
		}
		echo '<a class="digi-filter__clear" rel="nofollow" href="' . esc_url( $this->url_with( $kept ) ) . '">' . esc_html( $clear ) . '</a></div>';
		echo '</form>';
	}

	/**
	 * Botones o muestras: enlaces con la selección que resultaría de pulsarlos (funcionan sin JS), `role="button"` y
	 * `aria-pressed`; con JS y destino Loop Grid, el coordinador los alterna sin recargar.
	 */
	private function links( array $terms, string $taxonomy, array $names, array $selected, array $kept, bool $multiple, string $logic, bool $swatches ): string {
		$style = '';
		if ( $swatches ) {
			$data   = \Digitalisimo\Elements\Woo\Swatches\Data::class;
			$config = $data::attribute( $taxonomy );
			$data::prime( wp_list_pluck( $terms, 'term_id' ) );
			$radius = array( 'circle' => '50%', 'rounded' => '.35em', 'pill' => '1em' );
			$style  = ' style="--digi-swatch-size:' . (int) $config['size'] . 'px;--digi-swatch-radius:' . esc_attr( isset( $radius[ $config['shape'] ] ) ? $radius[ $config['shape'] ] : '50%' ) . '"';
		}
		$html = '<ul class="digi-filter__list" role="list"' . $style . '>';
		foreach ( $terms as $term ) {
			$on   = in_array( $term->slug, $selected, true );
			$next = $multiple ? ( $on ? array_values( array_diff( $selected, array( $term->slug ) ) ) : array_merge( $selected, array( $term->slug ) ) ) : ( $on ? array() : array( $term->slug ) );
			$raw  = array();
			if ( $next ) {
				$raw[ $names['name'] ] = self::join_terms( $next, $names['glue'] );
				if ( '' !== $names['type'] && $multiple ) {
					$raw[ $names['type'] ] = strtolower( $logic );
				}
			}
			$inner = '<span>' . esc_html( $term->name ) . '</span>';
			if ( $swatches ) {
				$inner = $this->swatch( $term ) . '<span class="digi-filter__sr">' . esc_html( $term->name ) . '</span>';
			}
			$html .= '<li><a class="digi-filter__term" rel="nofollow" role="button" aria-pressed="' . ( $on ? 'true' : 'false' ) . '" data-value="' . esc_attr( $term->slug ) . '" href="' . esc_url( $this->url_with( $kept, $raw ) ) . '">' . $inner . '</a></li>';
		}
		return $html . '</ul>';
	}

	/** Marca de una muestra: la imagen del término (por `Image_Markup`) o su color; sin datos, la inicial. */
	private function swatch( \WP_Term $term ): string {
		$values = \Digitalisimo\Elements\Woo\Swatches\Data::term( $term );
		if ( ! empty( $values['image'] ) ) {
			if ( ! class_exists( '\Digitalisimo\Elements\Image_Markup' ) ) {
				require_once dirname( __DIR__, 2 ) . '/digitalisimo-widgets/class-image-markup.php';
			}
			return '<span class="digi-filter__swatch" aria-hidden="true">' . \Digitalisimo\Elements\Image_Markup::img( array( 'id' => (int) $values['image'] ), array( 'size' => 'thumbnail', 'decorative' => true ) ) . '</span>';
		}
		if ( '' !== $values['color'] ) {
			return '<span class="digi-filter__swatch" aria-hidden="true" style="--digi-swatch:' . esc_attr( $values['color'] ) . '"></span>';
		}
		$initial = function_exists( 'mb_substr' ) ? mb_substr( (string) $term->name, 0, 1, 'UTF-8' ) : substr( (string) $term->name, 0, 1 );
		return '<span class="digi-filter__swatch" aria-hidden="true">' . esc_html( $initial ) . '</span>';
	}
}
