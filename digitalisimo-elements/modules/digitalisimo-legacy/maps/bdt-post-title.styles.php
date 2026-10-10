<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-title` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'bdt_title_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => 'text-align: {{VALUE}};',
		),
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
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-heading-title-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text',
	), array(
		'name' => 'title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-heading-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text',
	), array(
		'name' => 'title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text',
	), array(
		'name' => 'title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text',
	), array(
		'name' => 'title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-heading-title-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text',
	), array(
		'name' => 'title_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-heading-title-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-heading-title-text:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-heading-title-text:hover' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
			'title_border_border!' => '',
		),
	), array(
		'name' => 'title_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-heading-title-text:hover',
	) );
