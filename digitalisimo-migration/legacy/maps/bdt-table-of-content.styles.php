<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-table-of-content` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'fixed_index_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-fixed-index-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'layout' => 'fixed',
			'fixed_index_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'fixed_index_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-fixed-index-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'layout' => 'fixed',
			'fixed_index_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'btn_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-btn-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'button_offset_toggle' => 'yes',
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
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-btn-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'toc_sub_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-toc-sub-indent: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 5,
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-toc__toggle-button svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-toc__toggle-button',
	), array(
		'name' => 'button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-toc__toggle-button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-toc__toggle-button',
	), array(
		'name' => 'ofc_btn_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-toc__toggle-button:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'ofc_btn_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'ofc_btn_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-toc__toggle-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'ofc_btn_border_border!' => '',
		),
	) );
