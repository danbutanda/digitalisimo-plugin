<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Efecto CSS opcional para widgets y contenedores; no añade assets públicos. */
final class Backdrop_Filter_Extension {
	public static function init() {
		foreach ( array(
			'elementor/element/common/_section_background/before_section_end',
			'elementor/element/container/section_background/before_section_end',
			'elementor/element/section/section_background/before_section_end',
			'elementor/element/column/section_style/before_section_end',
		) as $hook ) {
			add_action( $hook, array( __CLASS__, 'controls' ), 10, 2 );
		}
	}

	public static function controls( $element, $args ) {
		$c = '\\Elementor\\Controls_Manager';
		$filter = 'blur(var(--digi-backdrop-blur, 0px)) brightness(var(--digi-backdrop-brightness, 100%)) saturate(var(--digi-backdrop-saturation, 100%))';
		$element->add_control( 'digitalisimo_backdrop_enable', array(
			'label' => 'Filtro de fondo',
			'type' => $c::SWITCHER,
			'return_value' => 'yes',
			'default' => '',
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}}' => 'backdrop-filter:' . $filter . ';-webkit-backdrop-filter:' . $filter . ';' ),
		) );
		$element->add_control( 'digitalisimo_backdrop_blur', array(
			'label' => 'Desenfoque',
			'type' => $c::SLIDER,
			'range' => array( 'px' => array( 'min' => 0, 'max' => 25, 'step' => 1 ) ),
			'condition' => array( 'digitalisimo_backdrop_enable' => 'yes' ),
			'selectors' => array( '{{WRAPPER}}' => '--digi-backdrop-blur:{{SIZE}}{{UNIT}};' ),
		) );
		$element->add_control( 'digitalisimo_backdrop_brightness', array(
			'label' => 'Brillo (%)',
			'type' => $c::NUMBER,
			'min' => 0,
			'max' => 200,
			'default' => 100,
			'condition' => array( 'digitalisimo_backdrop_enable' => 'yes' ),
			'selectors' => array( '{{WRAPPER}}' => '--digi-backdrop-brightness:{{VALUE}}%;' ),
		) );
		$element->add_control( 'digitalisimo_backdrop_saturation', array(
			'label' => 'Saturación (%)',
			'type' => $c::NUMBER,
			'min' => 0,
			'max' => 200,
			'default' => 100,
			'condition' => array( 'digitalisimo_backdrop_enable' => 'yes' ),
			'selectors' => array( '{{WRAPPER}}' => '--digi-backdrop-saturation:{{VALUE}}%;' ),
		) );
	}
}
