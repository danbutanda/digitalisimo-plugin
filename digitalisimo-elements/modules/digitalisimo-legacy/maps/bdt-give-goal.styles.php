<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-goal` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'income_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.income' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'income_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.income' => 'margin-right: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'income_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode span.income',
	), array(
		'name' => 'goal_amount_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .raised' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'goal_amount_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .raised',
	), array(
		'name' => 'progress_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar>span' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'progress_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'progress_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar, {{WRAPPER}} .elementor-shortcode .give-progress-bar>span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'progress_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'progress_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar' => 'margin-top: {{SIZE}}{{UNIT}} !important;',
		),
	) );
