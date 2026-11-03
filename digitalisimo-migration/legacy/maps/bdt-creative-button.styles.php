<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-creative-button` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'creative_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'surtur' ),
		),
	), array(
		'name' => 'creative_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'hati', 'surtur', 'reklo' ),
		),
	), array(
		'name' => 'creative_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-creative-button',
	), array(
		'name' => 'creative_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'janus', 'surtur', 'narvi', 'reklo', 'glitch' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'creative_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'janus', 'surtur', 'pandora', 'rhea', 'reklo' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'creative_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-creative-button',
	), array(
		'name' => 'creative_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-creative-button',
	), array(
		'name' => 'creative_button_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button:hover' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'surtur' ),
		),
	), array(
		'name' => 'creative_button_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button:hover' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'hati', 'surtur', 'reklo' ),
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'creative_button_border_border!' => '',
			'button_style!' => array( 'fenrir', 'janus', 'surtur', 'narvi', 'reklo', 'glitch' ),
		),
	), array(
		'name' => 'creative_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-creative-button:hover',
	) );
