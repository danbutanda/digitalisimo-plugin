<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-remote-arrows` (bdthemes-prime-slider/modules/remote-arrows/widgets/remote-arrows.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-remote-arrows',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'nav_arrows_icon' => '0',
		'next_text' => 'Next',
		'next_icon' => array(
			'value' => 'fas fa-angle-right',
			'library' => 'fa-solid',
		),
		'prev_text' => 'Prev',
		'prev_icon' => array(
			'value' => 'fas fa-angle-left',
			'library' => 'fa-solid',
		),
		'arrows_space' => array(
			'size' => 10,
		),
		'icon_text_space' => array(
			'size' => 10,
		),
	),
	'repeaters' => array(),
	'styles' => array(
		'arrows_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-remote-arrows' => 'justify-content: {{VALUE}};',
			),
		),
		'arrows_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next',
		),
		'arrows_space' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-remote-arrows' => 'grid-gap: {{SIZE}}px;',
			),
		),
		'icon_text_space' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-remote-arrows .bdt-button' => 'grid-gap: {{SIZE}}px;',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prev svg, {{WRAPPER}} .bdt-next svg' => 'fill: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next',
		),
		'nav_arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next',
		),
		'border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prev, {{WRAPPER}} .bdt-next',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prev:hover, {{WRAPPER}} .bdt-next:hover' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prev:hover svg, {{WRAPPER}} .bdt-next:hover svg' => 'fill: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prev:hover, {{WRAPPER}} .bdt-next:hover',
		),
		'nav_arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prev:hover, {{WRAPPER}} .bdt-next:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prev:hover, {{WRAPPER}} .bdt-next:hover',
		),
	),
);
