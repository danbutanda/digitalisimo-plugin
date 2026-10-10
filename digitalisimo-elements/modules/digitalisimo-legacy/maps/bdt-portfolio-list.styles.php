<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-portfolio-list` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'pagination_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a, {{WRAPPER}} ul.elementor-pagination li span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a',
	), array(
		'name' => 'pagination_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a',
	), array(
		'name' => 'pagination_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-pagination' => 'margin-top: {{SIZE}}px;',
		),
	), array(
		'name' => 'pagination_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-pagination' => 'margin-left: {{SIZE}}px;',
			'{{WRAPPER}} .elementor-pagination > *' => 'padding-left: {{SIZE}}px;',
		),
	), array(
		'name' => 'pagination_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a' => 'padding: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'pagination_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a' => 'border-radius: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'pagination_arrow_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a svg' => 'height: {{SIZE}}px; width: auto;',
		),
	), array(
		'name' => 'pagination_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a, {{WRAPPER}} ul.elementor-pagination li span',
	), array(
		'name' => 'pagination_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a:hover' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a:hover',
	) );
