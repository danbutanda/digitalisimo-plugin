<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-device-slider` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'slider_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider' => 'max-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'device_buttons_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider:before' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'buttons_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider:before' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'left_button_vertical',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider:before' => 'top: {{SIZE}}%;',
		),
		'condition' => array(
			'device_type' => 'custom',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider__title' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-device-slider__title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-device-slider__title',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-device-slider__title',
	), array(
		'name' => 'arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'arrows_dots' ),
		),
	), array(
		'name' => 'arrows_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button:hover' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'arrows_dots' ),
		),
	) );
