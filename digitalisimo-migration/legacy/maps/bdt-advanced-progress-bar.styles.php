<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-advanced-progress-bar` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'animation_speed',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__fill' => '-webkit-transition: width {{SIZE}}s ease;  -o-transition: width {{SIZE}}s ease; transition: width {{SIZE}}s ease;',
		),
	), array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-level' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'border_radius_level',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-level' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__fill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-progress-level',
		'exclude' => array( 'box_shadow_position' ),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-content' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'progress_base_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-level' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'progress_fill_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-progress__fill',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'info_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress-content',
	), array(
		'name' => 'info_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-progress-content',
	), array(
		'name' => 'percentage_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-parcentage' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'percentage_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-progress-parcentage::before',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'percentage_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-parcentage' => 'height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-progress-parcentage::before' => 'height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'percentage_vertical_position',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-parcentage' => 'top: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'percentage_horizontal_position',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-parcentage' => 'right: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'percentage_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress-parcentage, {{WRAPPER}} .digi-progress-parcentage::before',
	), array(
		'name' => 'percentage_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress-parcentage::before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	) );
