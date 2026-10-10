<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-portfolio-gallery` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_ratio',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'height: {{SIZE}}px',
		),
		'default' => array(
			'size' => 250,
		),
		'condition' => array(
			'masonry!' => 'yes',
		),
	) );
