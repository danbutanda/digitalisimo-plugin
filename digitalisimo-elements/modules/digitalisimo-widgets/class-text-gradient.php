<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Degradado de texto opcional para Heading clásico, generado por Elementor. */
final class Text_Gradient_Extension {
	public static function init() {
		add_action( 'elementor/element/heading/section_title_style/after_section_start', array( __CLASS__, 'controls' ), 30, 2 );
	}

	public static function controls( $element, $args ) {
		$c = '\\Elementor\\Controls_Manager';
		$selector = '{{WRAPPER}} .elementor-heading-title';
		$element->add_control( 'digitalisimo_text_gradient_enable', array(
			'label' => 'Degradado del texto',
			'type' => $c::SWITCHER,
			'return_value' => 'yes',
			'default' => '',
			'separator' => 'before',
			'selectors' => array(
				$selector => 'background-image:linear-gradient(var(--digi-text-gradient-angle,90deg),var(--digi-text-gradient-start,#3f51ef),var(--digi-text-gradient-end,#b34ac4));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;',
			),
		) );
		$condition = array( 'digitalisimo_text_gradient_enable' => 'yes' );
		$element->add_control( 'digitalisimo_text_gradient_start', array(
			'label' => 'Color inicial',
			'type' => $c::COLOR,
			'default' => '#3f51ef',
			'condition' => $condition,
			'selectors' => array( $selector => '--digi-text-gradient-start:{{VALUE}};' ),
		) );
		$element->add_control( 'digitalisimo_text_gradient_end', array(
			'label' => 'Color final',
			'type' => $c::COLOR,
			'default' => '#b34ac4',
			'condition' => $condition,
			'selectors' => array( $selector => '--digi-text-gradient-end:{{VALUE}};' ),
		) );
		$element->add_control( 'digitalisimo_text_gradient_angle', array(
			'label' => 'Ángulo (grados)',
			'type' => $c::NUMBER,
			'min' => 0,
			'max' => 360,
			'default' => 90,
			'condition' => $condition,
			'selectors' => array( $selector => '--digi-text-gradient-angle:{{VALUE}}deg;' ),
		) );
	}
}
