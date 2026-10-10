<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-iframe` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-legacy-iframe iframe' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 640,
		),
		'condition' => array(
			'auto_height!' => 'yes',
			'show_responsive_ratio!' => 'yes',
			'show_iframe_device' => '',
		),
		'size_units' => array( 'px', 'vh' ),
	) );
