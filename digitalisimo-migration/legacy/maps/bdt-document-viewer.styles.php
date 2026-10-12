<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-document-viewer` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'document_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-document-viewer iframe' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 800,
		),
	) );
