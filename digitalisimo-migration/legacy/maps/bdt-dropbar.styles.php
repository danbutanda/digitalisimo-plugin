<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-dropbar` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'btn_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-btn-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'button_position!' => '',
		),
	), array(
		'name' => 'btn_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-btn-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'button_position!' => '',
		),
	), array(
		'name' => 'button_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => '-webkit-transform: translate(var(--ep-btn-h-offset, 0), var(--ep-btn-v-offset, 0)) rotate({{SIZE}}deg); transform: translate(var(--ep-btn-h-offset, 0), var(--ep-btn-v-offset, 0)) rotate({{SIZE}}deg);',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'button_position!' => '',
		),
	), array(
		'name' => 'drop_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-offcanvas__button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-offcanvas__button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-offcanvas__button',
	), array(
		'name' => 'dropbar_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button .digi-offcanvas__button-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-offcanvas__button .digi-offcanvas__button-icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'button_icon[value]!' => '',
		),
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'dropbar_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover .digi-offcanvas__button-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-offcanvas__button:hover .digi-offcanvas__button-icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'button_icon[value]!' => '',
		),
	) );
