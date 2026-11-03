<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-social-share` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn' => 'font-size: calc({{SIZE}}{{UNIT}} * 10);',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn__icon i' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'em',
		),
		'tablet_default' => array(
			'unit' => 'em',
		),
		'mobile_default' => array(
			'unit' => 'em',
		),
		'condition' => array(
			'view!' => 'text',
		),
		'size_units' => array( 'em', 'px' ),
	), array(
		'name' => 'button_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'em',
		),
		'tablet_default' => array(
			'unit' => 'em',
		),
		'mobile_default' => array(
			'unit' => 'em',
		),
		'size_units' => array( 'em', 'px' ),
	), array(
		'name' => 'border_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 2,
		),
		'condition' => array(
			'style' => array( 'framed', 'boxed' ),
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'view' => 'text',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-share-btn__title',
		'exclude' => array( 'line_height' ),
	) );
