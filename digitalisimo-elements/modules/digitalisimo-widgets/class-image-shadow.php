<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Sombra de silueta opcional para el widget Imagen clásico. */
final class Image_Shadow_Extension {
	public static function init() {
		add_action( 'elementor/element/common/_section_style/after_section_end', array( __CLASS__, 'controls' ), 10, 2 );
	}

	public static function controls( $element, $args ) {
		if ( ! method_exists( $element, 'get_name' ) || 'image' !== $element->get_name() ) { return; }
		$c = '\\Elementor\\Controls_Manager';
		$selector = '{{WRAPPER}} .elementor-widget-container img';
		$element->start_controls_section( 'digitalisimo_image_shadow_section', array(
			'label' => 'Sombra de silueta',
			'tab' => $c::TAB_STYLE,
		) );
		$element->add_control( 'digitalisimo_image_shadow_enable', array(
			'label' => 'Activar sombra',
			'type' => $c::SWITCHER,
			'return_value' => 'yes',
			'default' => '',
			'selectors' => array( $selector => 'filter:drop-shadow(var(--digi-shadow-x,0px) var(--digi-shadow-y,10px) var(--digi-shadow-blur,20px) var(--digi-shadow-color,rgba(0,0,0,.25)));' ),
		) );
		$condition = array( 'digitalisimo_image_shadow_enable' => 'yes' );
		foreach ( array( 'x' => array( 'Desplazamiento horizontal (px)', -100, 100, 0 ), 'y' => array( 'Desplazamiento vertical (px)', -100, 100, 10 ), 'blur' => array( 'Desenfoque (px)', 0, 100, 20 ) ) as $key => $field ) {
			$element->add_control( 'digitalisimo_image_shadow_' . $key, array(
				'label' => $field[0],
				'type' => $c::NUMBER,
				'min' => $field[1],
				'max' => $field[2],
				'default' => $field[3],
				'condition' => $condition,
				'selectors' => array( $selector => '--digi-shadow-' . $key . ':{{VALUE}}px;' ),
			) );
		}
		$element->add_control( 'digitalisimo_image_shadow_color', array(
			'label' => 'Color',
			'type' => $c::COLOR,
			'default' => 'rgba(0,0,0,0.25)',
			'condition' => $condition,
			'selectors' => array( $selector => '--digi-shadow-color:{{VALUE}};' ),
		) );
		$element->end_controls_section();
	}
}
