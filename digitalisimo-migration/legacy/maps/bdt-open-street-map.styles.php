<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-open-street-map` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'marker_tooltip_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .leaflet-popup-content-wrapper' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'marker_tooltip_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .leaflet-popup-close-button' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'marker_tooltip_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .leaflet-popup-close-button:hover' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'marker_tooltip_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .leaflet-popup-content-wrapper, {{WRAPPER}} .leaflet-popup-tip',
	), array(
		'name' => 'marker_tooltip_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .leaflet-popup-content-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'marker_tooltip_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .leaflet-popup-content-wrapper',
	), array(
		'name' => 'marker_tooltip_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .leaflet-popup-content-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'marker_tooltip_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .leaflet-popup-content-wrapper',
	), array(
		'name' => 'marker_tooltip_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .leaflet-popup-content-wrapper',
	) );
