<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-data.php';
require_once __DIR__ . '/class-loop.php';

/**
 * Widget «Muestras de variación» (`digitalisimo-woo-swatches`) para una plantilla de bucle de productos o la ficha:
 * enlaces que abren la ficha con la variación preseleccionada (`Loop::markup()`).
 *
 * Las opciones de atributos se construyen sólo con WooCommerce y fuera de la pila optimizada (V.12.1): en el front el
 * render valida el atributo contra los registrados, nunca contra los controles. Sin WooCommerce, el registro oculto
 * del editor (frente A) usa estos mismos controles: `register_controls()` no llama a WooCommerce.
 */
class Loop_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-woo-swatches'; }
	public function get_title() { return 'Muestras de variación'; }
	public function get_icon() { return 'eicon-product-meta'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'muestras', 'variación', 'color', 'talla', 'atributo', 'woocommerce' ); }
	public function get_style_depends() { return array( 'digitalisimo-woo-swatches' ); }

	/** El script sólo hace falta para «Cambiar la imagen al pasar el cursor». */
	public function get_script_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) {
			return array( 'digitalisimo-woo-swatches' );
		}
		$s = (array) $this->get_settings();
		return 'yes' === ( $s['hover_image'] ?? '' ) ? array( 'digitalisimo-woo-swatches' ) : array();
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_swatches', array( 'label' => 'Muestras' ) );
		$this->add_control( 'attribute', array( 'label' => 'Atributo', 'type' => $c::SELECT, 'default' => '', 'options' => array( '' => 'El primero con muestras' ) + self::attribute_options(), 'description' => 'Sólo los atributos con muestras (Productos → Atributos → Tipo de muestra).' ) );
		$this->add_control( 'max_visible', array( 'label' => 'Máximo visible', 'type' => $c::NUMBER, 'default' => 5, 'min' => 0, 'max' => 30, 'description' => 'Con más opciones, un enlace «+N» lleva a la ficha. 0 muestra todas.' ) );
		$this->add_control( 'shape', array( 'label' => 'Forma', 'type' => $c::SELECT, 'default' => '', 'options' => array( '' => 'La del atributo', 'circle' => 'Círculo', 'rounded' => 'Cuadrado redondeado', 'pill' => 'Píldora' ) ) );
		$this->add_control( 'hover_image', array( 'label' => 'Cambiar la imagen al pasar el cursor', 'type' => $c::SWITCHER, 'default' => '', 'description' => 'Muestra la imagen de la variación en la ficha del bucle. Lee las variaciones de cada producto.' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_swatches_style', array( 'label' => 'Muestras', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => 'Inicio', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => 'Final', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-swatches' => 'justify-content: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'size', array( 'label' => 'Tamaño', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 12, 'max' => 96 ) ), 'selectors' => array( '{{WRAPPER}} .digi-swatches' => '--digi-swatch-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .digi-swatches' => '--digi-swatch-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .digi-swatches' => '--digi-swatch-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'border_color', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-swatches' => '--digi-swatch-border: {{VALUE}};' ) ) );
		$this->add_control( 'hover_color', array( 'label' => 'Color al pasar el cursor', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-swatches' => '--digi-swatch-selected: {{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-swatch--text' => 'color: {{VALUE}};' ) ) );
		if ( class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .digi-swatch--text' ) );
		}
		$this->end_controls_section();
	}

	/**
	 * Atributos globales con muestras, sólo con WooCommerce y fuera de la pila optimizada del front.
	 *
	 * @return array<string,string>
	 */
	private static function attribute_options(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ( class_exists( '\\Elementor\\Core\\Frontend\\Performance' ) && \Elementor\Core\Frontend\Performance::should_optimize_controls() ) ) {
			return array();
		}
		$options = array();
		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			if ( ! is_object( $attribute ) || empty( $attribute->attribute_name ) ) {
				continue;
			}
			$taxonomy = 'pa_' . $attribute->attribute_name;
			if ( 'select' !== Data::attribute( $taxonomy )['type'] ) {
				$options[ $taxonomy ] = (string) ( $attribute->attribute_label ?: $attribute->attribute_name );
			}
		}
		return $options;
	}

	private function editing(): bool {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	protected function render() {
		$settings  = (array) $this->get_settings_for_display();
		$requested = is_string( $settings['attribute'] ?? '' ) ? (string) ( $settings['attribute'] ?? '' ) : '';
		if ( '' !== $requested && ( 0 !== strpos( $requested, 'pa_' ) || ! taxonomy_exists( $requested ) ) ) {
			if ( $this->editing() ) {
				echo '<p class="digi-swatches__notice">El atributo ya no existe: elige otro en «Atributo».</p>';
			}
			return;
		}
		global $product;
		$current = $product instanceof \WC_Product ? $product : ( function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null );
		$html    = $current instanceof \WC_Product ? Loop::markup(
			$current,
			array(
				'attribute' => $requested,
				'max'       => isset( $settings['max_visible'] ) && '' !== $settings['max_visible'] ? (int) $settings['max_visible'] : 5,
				'shape'     => (string) ( $settings['shape'] ?? '' ),
				'hover'     => 'yes' === ( $settings['hover_image'] ?? '' ),
			)
		) : '';
		if ( '' === $html && $this->editing() ) {
			echo '<p class="digi-swatches__notice">Las muestras se ven en los productos variables con un atributo de muestras.</p>';
			return;
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en Loop::markup().
	}
}
