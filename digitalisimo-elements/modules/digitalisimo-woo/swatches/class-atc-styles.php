<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

/**
 * Rasgo `Atc_Styles`: sección de estilo «Muestras» en los widgets «Añadir al carrito» de producto
 * (`woocommerce-product-add-to-cart`, tras «Variations», `section_atc_variations_style`) y personalizado
 * (`wc-add-to-cart`, tras el estilo del botón, `section_style`). V.12.1 y V.12.2.
 *
 * - Opt-in: el interruptor `digitalisimo_swatch_styles` está apagado y ningún control tiene `default`, así que un
 *   widget existente genera el mismo CSS que en 4.3.0.164 (el núcleo incluye el `default` de todos los controles al
 *   generar el CSS del documento). Cada control de estilo lleva `condition` sobre el interruptor.
 * - Los estilos son variables CSS sobre `.digi-swatches` del propio widget; la hoja de las muestras las lee.
 * - Recursos que no se pierden con la caché de elementos: el interruptor declara la hoja y el script de las muestras
 *   en `assets` del control, con su condición (patrón de `sticky-state`).
 */
final class Atc_Styles {
	const SWITCH = 'digitalisimo_swatch_styles';
	const HANDLE = 'digitalisimo-woo-swatches';

	/** Widget => sección del núcleo tras la que va «Muestras». */
	const TARGETS = array(
		'woocommerce-product-add-to-cart' => 'section_atc_variations_style',
		'wc-add-to-cart'                  => 'section_style',
	);

	public static function init(): void {
		foreach ( self::TARGETS as $widget => $section ) {
			add_action( "elementor/element/{$widget}/{$section}/after_section_end", array( __CLASS__, 'register_controls' ), 10, 2 );
		}
	}

	/**
	 * Sección «Muestras». Una sola vez por pila.
	 *
	 * @param object $element Widget.
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::SWITCH ) ) ) {
			return;
		}
		$c    = '\\Elementor\\Controls_Manager';
		$on   = array( self::SWITCH => 'yes' );
		$wrap = '{{WRAPPER}} .digi-swatches';
		$item = '{{WRAPPER}} .digi-swatch';
		$element->start_controls_section( 'digitalisimo_swatch_section', array( 'label' => 'Muestras', 'tab' => $c::TAB_STYLE ) );
		$element->add_control(
			self::SWITCH,
			array(
				'label'       => 'Estilo propio de las muestras',
				'type'        => $c::SWITCHER,
				'description' => 'Para los atributos con muestras (Productos → Atributos → Tipo de muestra). Sin activarlo, las muestras usan su estilo por defecto.',
				'assets'      => array(
					'styles'  => array( array( 'name' => self::HANDLE, 'conditions' => array( 'terms' => array( array( 'name' => self::SWITCH, 'operator' => '===', 'value' => 'yes' ) ) ) ) ),
					'scripts' => array( array( 'name' => self::HANDLE, 'conditions' => array( 'terms' => array( array( 'name' => self::SWITCH, 'operator' => '===', 'value' => 'yes' ) ) ) ) ),
				),
			)
		);
		$element->add_responsive_control( 'digitalisimo_swatch_size', array( 'label' => 'Tamaño', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 16, 'max' => 96 ) ), 'condition' => $on, 'selectors' => array( $wrap => '--digi-swatch-size: {{SIZE}}{{UNIT}};' ) ) );
		$element->add_responsive_control( 'digitalisimo_swatch_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'condition' => $on, 'selectors' => array( $wrap => '--digi-swatch-gap: {{SIZE}}{{UNIT}};' ) ) );
		$element->add_control( 'digitalisimo_swatch_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ), 'condition' => $on, 'selectors' => array( $wrap => '--digi-swatch-radius: {{SIZE}}{{UNIT}};' ) ) );
		$element->add_control( 'digitalisimo_swatch_border', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'condition' => $on, 'selectors' => array( $wrap => '--digi-swatch-border: {{VALUE}};' ) ) );
		$element->add_control( 'digitalisimo_swatch_selected', array( 'label' => 'Color de la elegida', 'type' => $c::COLOR, 'condition' => $on, 'selectors' => array( $wrap => '--digi-swatch-selected: {{VALUE}};' ) ) );
		$element->add_control( 'digitalisimo_swatch_text', array( 'label' => 'Color del texto de las etiquetas', 'type' => $c::COLOR, 'condition' => $on, 'selectors' => array( $item . '--text' => 'color: {{VALUE}};' ) ) );
		if ( class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
			$element->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'digitalisimo_swatch_typography', 'label' => 'Tipografía de las etiquetas', 'selector' => $item . '--text', 'condition' => $on ) );
		}
		$element->end_controls_section();
	}
}
