<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-animated-link` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'link_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'link_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'link_style_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-link:before, {{WRAPPER}} .digi-animated-link:after' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'link_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-animated-link',
	) );
