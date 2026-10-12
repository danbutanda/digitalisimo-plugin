<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-tabs` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'tabs_title_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tab' => 'justify-content: {{VALUE}};',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'flex-end' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher .digi-content-switcher-item:hover .digi-content-switcher__tab i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-content-switcher .digi-content-switcher-item:hover .digi-content-switcher__tab svg' => 'fill: {{VALUE}};',
		),
	) );
