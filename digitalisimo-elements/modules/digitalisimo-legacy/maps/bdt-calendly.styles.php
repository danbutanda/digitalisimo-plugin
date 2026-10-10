<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-calendly` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .calendly-inline-widget' => 'height: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .calendly-wrapper' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'px',
			'size' => '680',
		),
		'size_units' => array( 'px', '%' ),
	) );
