<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-business-hours` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'business_day_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .heading-date',
	), array(
		'name' => 'business_timings_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .heading-time',
	), array(
		'name' => 'business_hours_striped_odd_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .border-divider:nth-child(odd)' => 'background: {{VALUE}};',
		),
		'default' => '#eaeaea',
		'condition' => array(
			'business_hours_striped' => 'yes',
		),
	), array(
		'name' => 'striped_effect_even',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .border-divider:nth-child(even)' => 'background: {{VALUE}};',
		),
		'default' => '#FFFFFF',
		'condition' => array(
			'business_hours_striped' => 'yes',
		),
	), array(
		'name' => 'dynamic_separator_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .dynamic-separator' => 'color: {{VALUE}};',
		),
	) );
