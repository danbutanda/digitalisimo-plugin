<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Filtro por campo (`digitalisimo-loop-meta-filter`, F7b V.5): un `meta_query` sobre una clave pública.
 *
 * - **Un valor** o **varios valores** de la lista que escribe el administrador (casillas o radios, botones o
 *   desplegable): en la URL viaja la ficha de cada valor (`Query::options()`), nunca el valor libre; el servidor sólo
 *   compara con valores de la lista (`=` o `IN`).
 * - **Rango** (números o fechas): dos campos, `digi_m_{widget}` y `digi_m_{widget}-max`, como el filtro de precio de
 *   F4-Woo; el servidor los valida por tipo, los lleva a los límites del widget y usa `BETWEEN`, `>=` o `<=`.
 * - La clave nunca es protegida (`_*`) ni de la lista negra de V.8, aquí ni en el servidor. La lista de claves del
 *   editor sale de la acción AJAX `digitalisimo_filters_meta_keys` (`Editor`), con `edit_post`.
 */
class Meta_Widget extends Filter_Widget {
	const TYPE = Query::META;

	public function get_name() {
		return 'digitalisimo-loop-meta-filter';
	}

	public function get_title() {
		return 'Filtro por campo';
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	public function get_keywords() {
		return array( 'filtro', 'campo', 'meta', 'rango', 'loop grid', 'digitalisimo' );
	}

	/** Tipos de contenido públicos para la lista de claves del editor (fuera de la pila optimizada del front). */
	public static function post_type_options(): array {
		$options  = array( 'post' => 'Entradas' );
		$optimize = class_exists( '\Elementor\Core\Frontend\Performance' ) && \Elementor\Core\Frontend\Performance::should_optimize_controls();
		if ( $optimize || ! function_exists( 'get_post_types' ) ) {
			return $options;
		}
		foreach ( (array) get_post_types( array( 'public' => true ), 'objects' ) as $name => $type ) {
			if ( is_string( $name ) && 'attachment' !== $name ) {
				$options[ $name ] = is_object( $type ) && isset( $type->label ) ? (string) $type->label : $name;
			}
		}
		return $options;
	}

	/** Selector de clave rellenado por el editor, con «Otra clave…». */
	public static function key_controls( $widget, string $id, string $label ): void {
		$c = '\\Elementor\\Controls_Manager';
		$widget->add_control( $id, array( 'label' => $label, 'type' => $c::SELECT, 'options' => array( '' => 'Elige un campo', '__custom' => 'Otra clave…' ), 'default' => '', 'label_block' => true, 'description' => 'Sólo campos públicos: nunca los que empiezan por guion bajo ni los que parecen datos personales o secretos.', 'style_transfer' => false ) );
		$widget->add_control( $id . '_custom', array( 'label' => 'Clave del campo', 'type' => $c::TEXT, 'default' => '', 'condition' => array( $id => '__custom' ), 'style_transfer' => false ) );
		$widget->add_control( 'fields_post_type', array( 'label' => 'Tipo de contenido de los campos', 'type' => $c::SELECT, 'options' => self::post_type_options(), 'default' => 'post', 'description' => 'Sólo sirve para la lista de campos de este panel.', 'condition' => array( 'destination' => 'archive' ), 'style_transfer' => false ) );
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_filter', array( 'label' => 'Filtro por campo' ) );
		$this->register_target_controls( 'Archivo (la consulta principal)', 'Filtra la consulta principal del archivo que muestra este widget: entradas, categorías, búsqueda o tienda. La dirección lleva la referencia de este widget; sin ella, el filtro no se aplica.' );
		self::key_controls( $this, 'meta_key', 'Campo' );
		$this->add_control( 'value_type', array( 'label' => 'Tipo de dato', 'type' => $c::SELECT, 'default' => 'CHAR', 'options' => array( 'CHAR' => 'Texto', 'NUMERIC' => 'Número entero', 'DECIMAL' => 'Número con decimales', 'DATE' => 'Fecha (AAAA-MM-DD)' ), 'style_transfer' => false ) );
		$this->add_control( 'mode', array( 'label' => 'Comparación', 'type' => $c::SELECT, 'default' => 'equal', 'options' => array( 'equal' => 'Un valor de la lista', 'in' => 'Varios valores de la lista', 'range' => 'Rango (números o fechas)' ), 'style_transfer' => false ) );
		$this->add_control(
			'options',
			array(
				'label'          => 'Valores',
				'type'           => $c::REPEATER,
				'fields'         => array(
					array( 'name' => 'value', 'label' => 'Valor guardado', 'type' => $c::TEXT, 'default' => '', 'label_block' => true ),
					array( 'name' => 'label', 'label' => 'Texto visible', 'type' => $c::TEXT, 'default' => '', 'label_block' => true ),
				),
				'default'        => array(),
				'title_field'    => '{{{ label || value }}}',
				'description'    => 'Sólo estos valores se pueden elegir (como mucho 50); cualquier otro se ignora.',
				'condition'      => array( 'mode!' => 'range' ),
				'style_transfer' => false,
			)
		);
		$this->add_control( 'display', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'checkboxes', 'options' => array( 'checkboxes' => 'Casillas', 'buttons' => 'Botones', 'dropdown' => 'Desplegable' ), 'condition' => array( 'mode!' => 'range' ), 'style_transfer' => false ) );
		$this->add_control( 'range_min', array( 'label' => 'Límite inferior', 'type' => $c::TEXT, 'default' => '', 'description' => 'Opcional. Un número o una fecha AAAA-MM-DD, según el tipo de dato.', 'condition' => array( 'mode' => 'range' ), 'style_transfer' => false ) );
		$this->add_control( 'range_max', array( 'label' => 'Límite superior', 'type' => $c::TEXT, 'default' => '', 'condition' => array( 'mode' => 'range' ), 'style_transfer' => false ) );
		$this->add_control( 'step', array( 'label' => 'Paso', 'type' => $c::NUMBER, 'default' => 1, 'min' => 0.000001, 'condition' => array( 'mode' => 'range', 'value_type' => array( 'NUMERIC', 'DECIMAL' ) ), 'style_transfer' => false ) );
		$this->add_control( 'apply', array( 'label' => 'Aplicar', 'type' => $c::SELECT, 'default' => 'button', 'options' => array( 'button' => 'Con un botón', 'change' => 'Al cambiar' ), 'condition' => array( 'mode' => 'range' ), 'style_transfer' => false ) );
		$this->text_control( 'any_text', 'Texto de «cualquiera»', 'Cualquiera', array( 'condition' => array( 'mode!' => 'range' ) ) );
		$this->text_control( 'label_min', 'Texto del mínimo', 'Desde', array( 'condition' => array( 'mode' => 'range' ) ) );
		$this->text_control( 'label_max', 'Texto del máximo', 'Hasta', array( 'condition' => array( 'mode' => 'range' ) ) );
		$this->register_text_controls( '', 'Aplicar', 'Limpiar' );
		$this->end_controls_section();
		$this->register_style_controls();
	}

	protected function render() {
		if ( ! $this->ready() ) {
			return;
		}
		$s = $this->filter_settings();
		if ( '' === $s['key'] ) {
			$this->editor_message( 'Elige un campo público para filtrar.' );
			return;
		}
		if ( 'range' !== $s['mode'] && ! $s['options'] ) {
			$this->editor_message( 'Añade los valores que se pueden elegir.' );
			return;
		}
		$this->require_pro();
		$label = $this->setting_text( 'legend', $s['key'] );
		if ( 'range' === $s['mode'] ) {
			$this->render_range( $s, $label );
			return;
		}
		$options = array();
		foreach ( $s['options'] as $token => $option ) {
			$options[ $token ] = $option['label'];
		}
		$display = (string) $this->get_settings_for_display( 'display' );
		$this->render_choices(
			array(
				'kind'        => 'meta',
				'label'       => $label,
				'form_label'  => 'Filtrar por ' . self::lower( $label ),
				'options'     => $options,
				'multiple'    => 'in' === $s['mode'],
				'display'     => in_array( $display, array( 'checkboxes', 'buttons', 'dropdown' ), true ) ? $display : 'checkboxes',
				'any'         => $this->setting_text( 'any_text', 'Cualquiera' ),
				'button'      => $this->setting_text( 'button_text', 'Aplicar' ),
				'clear'       => $this->setting_text( 'clear_text', 'Limpiar' ),
				'clear_label' => 'Quitar el filtro por ' . self::lower( $label ),
			)
		);
	}

	/** Dos campos (número o fecha) con los nombres de los dos extremos. */
	private function render_range( array $s, string $label ): void {
		$names = array( 'min' => $this->param_name(), 'max' => $this->param_name( Query::MAX_SUFFIX ) );
		$own   = $this->own( array_values( $names ) );
		$date  = 'DATE' === $s['value_type'];
		$step  = (float) $this->get_settings_for_display( 'step' );
		$attrs = ( null !== $s['min'] ? ' min="' . esc_attr( $s['min'] ) . '"' : '' ) . ( null !== $s['max'] ? ' max="' . esc_attr( $s['max'] ) . '"' : '' );
		if ( ! $date ) {
			$attrs .= ' step="' . esc_attr( 'NUMERIC' === $s['value_type'] ? (string) max( 1, (int) round( $step ) ) : ( $step > 0 ? rtrim( rtrim( number_format( $step, 6, '.', '' ), '0' ), '.' ) : 'any' ) ) . '"';
		}
		$extra = array(
			'class'        => 'digi-filter digi-filter--range',
			'data-type'    => Query::META,
			'data-key-min' => $this->key(),
			'data-key-max' => $this->key( Query::MAX_SUFFIX ),
			'data-apply'   => 'change' === $this->get_settings_for_display( 'apply' ) ? 'change' : 'button',
			'data-min'     => null !== $s['min'] ? $s['min'] : '',
			'data-max'     => null !== $s['max'] ? $s['max'] : '',
		);
		$texts = array( 'min' => $this->setting_text( 'label_min', 'Desde' ), 'max' => $this->setting_text( 'label_max', 'Hasta' ) );
		echo '<form' . $this->form_attributes( 'range', 'Filtrar por ' . self::lower( $label ), $extra ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en form_attributes().
		if ( 'loop' === $this->destination() ) {
			echo $this->helper_span(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<fieldset class="digi-filter__set">' . $this->legend( $label ) . '<div class="digi-filter__fields">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $names as $edge => $name ) {
			$value = Query::typed( self::param( $name ), $s['value_type'] );
			echo '<label class="digi-filter__field"><span class="digi-filter__label">' . esc_html( $texts[ $edge ] ) . '</span><input class="digi-filter__input" type="' . ( $date ? 'date' : 'number' ) . '"' . ( $date ? '' : ' inputmode="decimal"' ) . ' name="' . esc_attr( $name ) . '" data-digi-edge="' . esc_attr( $edge ) . '" value="' . esc_attr( null === $value ? '' : $value ) . '"' . $attrs . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></fieldset>';
		echo $this->form_hidden( $own ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">' . esc_html( $this->setting_text( 'button_text', 'Aplicar' ) ) . '</button>';
		echo '<a class="digi-filter__clear" rel="nofollow" href="' . esc_url( $this->url_with( $this->kept_params( $own ) ) ) . '" aria-label="' . esc_attr( 'Quitar el filtro por ' . self::lower( $label ) ) . '">' . esc_html( $this->setting_text( 'clear_text', 'Limpiar' ) ) . '</a></div>';
		echo '</form>';
	}
}
