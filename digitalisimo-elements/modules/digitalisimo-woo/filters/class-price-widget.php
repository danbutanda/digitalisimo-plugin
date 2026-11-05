<?php
namespace Digitalisimo\Elements\Woo\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Filtro de precio (`digitalisimo-woo-price-filter`, F4-Woo v1 §5.5 a y V.8).
 *
 * Con destino Loop Grid, los nombres son `e-filter-{bucle}-digi_price_min` y `…-digi_price_max` (tipo
 * `digitalisimo_price`); con «Tienda», `min_price` y `max_price`. Dos campos numéricos, un deslizador doble o ambos; con
 * «Ambos», los `range` llevan `aria-hidden` y `tabindex="-1"` y los campos son el control accesible (V.8.6). Límites
 * automáticos (`Bounds::price_display()`) o fijos.
 */
class Price_Widget extends Filter_Widget {
	public function get_name() {
		return 'digitalisimo-woo-price-filter';
	}

	public function get_title() {
		return 'Filtro de precio';
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_filter', array( 'label' => 'Filtro' ) );
		$this->register_target_controls();
		$this->add_control( 'bounds', array( 'label' => 'Límites', 'type' => $c::SELECT, 'default' => 'auto', 'separator' => 'before', 'options' => array( 'auto' => 'Automáticos (los precios de la tienda)', 'fixed' => 'Fijos' ) ) );
		$this->add_control( 'bound_min', array( 'label' => 'Mínimo', 'type' => $c::NUMBER, 'default' => 0, 'min' => 0, 'condition' => array( 'bounds' => 'fixed' ) ) );
		$this->add_control( 'bound_max', array( 'label' => 'Máximo', 'type' => $c::NUMBER, 'default' => 1000, 'min' => 0, 'condition' => array( 'bounds' => 'fixed' ) ) );
		$this->add_control( 'step', array( 'label' => 'Paso', 'type' => $c::NUMBER, 'default' => 1, 'min' => 0.01, 'step' => 0.01 ) );
		$this->add_control( 'display', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'both', 'options' => array( 'fields' => 'Dos campos', 'slider' => 'Deslizador doble', 'both' => 'Campos y deslizador' ) ) );
		$this->add_control( 'apply', array( 'label' => 'Aplicar', 'type' => $c::SELECT, 'default' => 'button', 'options' => array( 'button' => 'Con un botón', 'change' => 'Al cambiar' ), 'description' => 'Al cambiar, el filtro espera a que termines de escribir o de mover el deslizador.' ) );
		$this->add_control( 'legend', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Precio', 'separator' => 'before' ) );
		$this->add_control( 'show_legend', array( 'label' => 'Mostrar el título', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'label_min', array( 'label' => 'Texto del mínimo', 'type' => $c::TEXT, 'default' => 'Mínimo' ) );
		$this->add_control( 'label_max', array( 'label' => 'Texto del máximo', 'type' => $c::TEXT, 'default' => 'Máximo' ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => 'Aplicar' ) );
		$this->add_control( 'clear_text', array( 'label' => 'Texto para quitar el filtro', 'type' => $c::TEXT, 'default' => 'Quitar filtro' ) );
		$this->end_controls_section();
		$this->register_style_controls();
	}

	/** Nombres de los dos extremos en la URL, según el destino. */
	public function names(): array {
		if ( 'shop' === $this->destination() ) {
			return array( 'min' => 'min_price', 'max' => 'max_price' );
		}
		return array( 'min' => $this->loop_prefix() . Query::PRICE_NAMES['min'], 'max' => $this->loop_prefix() . Query::PRICE_NAMES['max'] );
	}

	/** Límites del control: automáticos o fijos (mínimo ≥ 0 y máximo > mínimo). */
	public function bounds( float $step ): array {
		$s = $this->get_settings_for_display();
		if ( 'fixed' === ( isset( $s['bounds'] ) ? $s['bounds'] : 'auto' ) ) {
			$min = max( 0.0, (float) ( isset( $s['bound_min'] ) ? $s['bound_min'] : 0 ) );
			$max = (float) ( isset( $s['bound_max'] ) ? $s['bound_max'] : 0 );
			return array( 'min' => $min, 'max' => $max > $min ? $max : $min + $step );
		}
		return Bounds::price_display( $step );
	}

	/** Número para un atributo HTML, sin ceros de más. */
	private static function number( float $value ): string {
		$text = rtrim( rtrim( number_format( $value, 8, '.', '' ), '0' ), '.' );
		return '' === $text || '-0' === $text ? '0' : $text;
	}

	/** Importe en texto plano con el formato de la tienda (para `aria-valuetext`). */
	private static function money( float $value ): string {
		return function_exists( 'wc_price' ) ? trim( html_entity_decode( wp_strip_all_tags( wc_price( $value ) ), ENT_QUOTES, 'UTF-8' ) ) : self::number( $value );
	}

	/** Formato de la tienda para el script: símbolo, decimales, separadores y orden. */
	private static function currency(): array {
		$decode = function ( $text ) {
			return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		};
		return array(
			's'  => function_exists( 'get_woocommerce_currency_symbol' ) ? $decode( get_woocommerce_currency_symbol() ) : '',
			'd'  => Query::decimals(),
			'ds' => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.',
			'ts' => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : '',
			'f'  => function_exists( 'get_woocommerce_price_format' ) ? $decode( get_woocommerce_price_format() ) : '%1$s%2$s',
		);
	}

	protected function render_widget() {
		$s = $this->get_settings_for_display();
		if ( 'loop' === $this->destination() && '' === $this->loop_id() ) {
			$this->editor_message( 'Elige el Loop Grid que filtra este widget.' );
			return;
		}
		$this->require_pro();
		$names   = $this->names();
		$step    = max( 0.01, (float) ( isset( $s['step'] ) ? $s['step'] : 1 ) );
		$bounds  = $this->bounds( $step );
		$display = in_array( isset( $s['display'] ) ? $s['display'] : 'both', array( 'fields', 'slider', 'both' ), true ) ? $s['display'] : 'both';
		$current = array();
		foreach ( $names as $edge => $name ) {
			$value            = Query::price_value( self::param( $name ) );
			$current[ $edge ] = null === $value ? '' : self::number( $value );
		}
		$legend = trim( (string) ( isset( $s['legend'] ) ? $s['legend'] : '' ) );
		$label  = '' !== $legend ? $legend : 'Precio';
		$extra  = array(
			'data-apply'    => 'change' === ( isset( $s['apply'] ) ? $s['apply'] : 'button' ) ? 'change' : 'button',
			'data-display'  => $display,
			'data-min'      => self::number( $bounds['min'] ),
			'data-max'      => self::number( $bounds['max'] ),
			'data-key-min'  => Query::PRICE_NAMES['min'],
			'data-key-max'  => Query::PRICE_NAMES['max'],
			'data-currency' => wp_json_encode( self::currency() ),
		);
		$texts = array(
			'min' => trim( (string) ( isset( $s['label_min'] ) ? $s['label_min'] : '' ) ) ?: 'Mínimo',
			'max' => trim( (string) ( isset( $s['label_max'] ) ? $s['label_max'] : '' ) ) ?: 'Máximo',
		);
		$attrs = ' min="' . esc_attr( $extra['data-min'] ) . '" max="' . esc_attr( $extra['data-max'] ) . '" step="' . esc_attr( self::number( $step ) ) . '"';
		echo '<form' . $this->form_attributes( 'price', 'Filtrar por ' . self::lower( $label ), $extra ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en form_attributes().
		echo $this->helper_span(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<fieldset class="digi-filter__set"><legend class="digi-filter__legend' . ( 'yes' === ( isset( $s['show_legend'] ) ? $s['show_legend'] : 'yes' ) ? '' : ' digi-filter__sr' ) . '">' . esc_html( $label ) . '</legend>';
		if ( 'slider' !== $display ) {
			echo '<div class="digi-filter__fields">';
			foreach ( $names as $edge => $name ) {
				echo '<label class="digi-filter__field"><span class="digi-filter__label">' . esc_html( $texts[ $edge ] ) . '</span><input class="digi-filter__input" type="number" inputmode="decimal" name="' . esc_attr( $name ) . '" data-digi-edge="' . esc_attr( $edge ) . '" value="' . esc_attr( $current[ $edge ] ) . '"' . $attrs . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
		}
		if ( 'fields' !== $display ) {
			echo '<div class="digi-filter__range">';
			foreach ( $names as $edge => $name ) {
				$value = '' !== $current[ $edge ] ? (float) $current[ $edge ] : $bounds[ $edge ];
				$value = min( $bounds['max'], max( $bounds['min'], $value ) );
				$a11y  = 'both' === $display ? ' aria-hidden="true" tabindex="-1"' : ' name="' . esc_attr( $name ) . '"';
				echo '<input class="digi-filter__slider" type="range" data-digi-edge="' . esc_attr( $edge ) . '" value="' . esc_attr( self::number( $value ) ) . '" aria-label="' . esc_attr( ( 'min' === $edge ? 'Precio mínimo' : 'Precio máximo' ) ) . '" aria-valuetext="' . esc_attr( self::money( $value ) ) . '"' . $attrs . $a11y . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
		}
		echo '</fieldset>';
		echo $this->hidden_fields( array_values( $names ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$button = trim( (string) ( isset( $s['button_text'] ) ? $s['button_text'] : '' ) ) ?: 'Aplicar';
		$clear  = trim( (string) ( isset( $s['clear_text'] ) ? $s['clear_text'] : '' ) ) ?: 'Quitar filtro';
		echo '<div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">' . esc_html( $button ) . '</button>';
		echo '<a class="digi-filter__clear" rel="nofollow" href="' . esc_url( $this->url_with( $this->kept_params( array_values( $names ) ) ) ) . '">' . esc_html( $clear ) . '</a></div>';
		echo '</form>';
	}
}
