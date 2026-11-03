<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-remote-fraction` (bdthemes-prime-slider/modules/remote-fraction/widgets/remote-fraction.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-remote-fraction',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'digit_pad' => 2,
	),
	'repeaters' => array(),
	'styles' => array(
		'vertical_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fraction-wrapper' => '{{VALUE}};',
			),
		),
		'fraction_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fraction-wrapper' => 'justify-content: {{VALUE}};',
			),
		),
		'fraction_spacing' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fraction-wrapper' => 'grid-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-current' => 'color: {{VALUE}};',
			),
		),
		'text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-current',
		),
		'fraction_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-current',
		),
		'text_color_total' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-total' => 'color: {{VALUE}};',
			),
		),
		'text_shadow_total' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-total',
		),
		'fraction_typography_total' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-total',
		),
		'separator_total' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fr-separator' => 'color: {{VALUE}};',
			),
		),
		'separator_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fr-separator' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
