<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-image-compare` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'overlay_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .digi-compare-overlay:before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'no_overlay' => 'yes',
		),
	), array(
		'name' => 'before_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .icv__label.icv__label-before' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'before_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .icv__label.icv__label-before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'after_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .icv__label.icv__label-after' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'after_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .icv__label.icv__label-after' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'after_before_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .icv__label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'after_before_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-compare .icv__label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'after_before_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-compare .icv__label',
	) );
