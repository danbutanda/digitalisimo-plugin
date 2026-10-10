<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-scroll-button` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'scroll_button_offset',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button' => 'margin: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
		'condition' => array(
			'scroll_button_position!' => '',
		),
	), array(
		'name' => 'scroll_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-scroll-button__link svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'scroll_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'scroll_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-scroll-button__link',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'scroll_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'scroll_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-scroll-button__link',
	), array(
		'name' => 'scroll_button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-scroll-button__link',
	), array(
		'name' => 'scroll_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-scroll-button__link:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'scroll_button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'scroll_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button__link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'scroll_button_border_border!' => '',
		),
	), array(
		'name' => 'fancy_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button .digi-scroll-button__link:before, {{WRAPPER}} .digi-scroll-button .digi-scroll-button__link:after' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'show_fancy_animation' => 'yes',
			'fancy_animation' => 'line-bounce',
		),
	), array(
		'name' => 'button_shadow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-scroll-button .digi-scroll-button__link' => '--box-shadow-color: {{VALUE}};',
		),
		'condition' => array(
			'show_fancy_animation' => 'yes',
			'fancy_animation!' => 'line-bounce',
		),
	) );
