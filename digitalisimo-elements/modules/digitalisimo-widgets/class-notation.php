<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Notación tipográfica opcional para el título del widget Heading clásico. */
final class Notation_Extension {
	public static function init() {
		add_action( 'elementor/element/heading/section_title_style/after_section_start', array( __CLASS__, 'controls' ), 10, 2 );
	}

	public static function controls( $element, $args ) {
		$c = '\\Elementor\\Controls_Manager';
		$selector = '{{WRAPPER}} .elementor-heading-title';
		$element->add_control( 'digitalisimo_notation_line', array(
			'label' => 'Notación del título',
			'type' => $c::SELECT,
			'default' => '',
			'options' => array(
				'' => 'Sin notación',
				'underline' => 'Subrayado',
				'overline' => 'Línea superior',
				'line-through' => 'Tachado',
			),
			'separator' => 'before',
			'selectors' => array( $selector => 'text-decoration-line:{{VALUE}};' ),
		) );
		$element->add_control( 'digitalisimo_notation_color', array(
			'label' => 'Color de la línea',
			'type' => $c::COLOR,
			'default' => '',
			'condition' => array( 'digitalisimo_notation_line!' => '' ),
			'selectors' => array( $selector => 'text-decoration-color:{{VALUE}};' ),
		) );
		$element->add_control( 'digitalisimo_notation_thickness', array(
			'label' => 'Grosor de la línea',
			'type' => $c::SLIDER,
			'range' => array( 'px' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) ),
			'condition' => array( 'digitalisimo_notation_line!' => '' ),
			'selectors' => array( $selector => 'text-decoration-thickness:{{SIZE}}{{UNIT}};' ),
		) );
	}
}
