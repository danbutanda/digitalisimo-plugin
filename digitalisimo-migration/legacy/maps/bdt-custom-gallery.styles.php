<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-custom-gallery` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__list' => 'margin-left: -{{SIZE}}px',
			'{{WRAPPER}} .digi-custom-gallery__item' => 'padding-left: {{SIZE}}px',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__list' => 'margin-top: -{{SIZE}}px',
			'{{WRAPPER}} .digi-custom-gallery__item' => 'margin-top: {{SIZE}}px',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_skin_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .digi-custom-gallery__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} 0 0;',
		),
		'condition' => array(
			'_skin!' => '',
		),
		'size_units' => array( 'px', '%' ),
	) );
