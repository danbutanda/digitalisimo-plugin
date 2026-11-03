<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-tabs` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
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
	) );
