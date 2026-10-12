<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-svg-image` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'svg_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-image svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'svg_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-image svg *' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'caption_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'text-align: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'caption_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'caption_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'caption_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .widget-image-caption',
	), array(
		'name' => 'caption_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .widget-image-caption',
	), array(
		'name' => 'caption_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	) );
