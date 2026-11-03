<?php
/**
 * Controles de estilo de Element Pack Pro 10.0.1 `bdt-advanced-progress-bar` con selectores del widget propio, más los que sólo
 * tenía 9.9.1 y siguen vivos. Generado con scripts/legacy-styles.py --merge; no editar a mano.
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
			'{{WRAPPER}} .digi-progress__track' => 'height: {{SIZE}}{{UNIT}};',
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
			'{{WRAPPER}} .digi-progress__track' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		'selector' => '{{WRAPPER}} .digi-progress__track',
		'exclude' => array( 'box_shadow_position' ),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__head' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'progress_base_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__track' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'progress_fill_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-progress__fill',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'info_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__head',
	), array(
		'name' => 'info_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-progress__head',
	), array(
		'name' => 'percentage_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__value' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'percentage_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-progress__value::before',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'percentage_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__value' => 'height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-progress__value::before' => 'height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'percentage_vertical_position',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__value' => 'top: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'percentage_horizontal_position',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__value' => 'right: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'percentage_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__value, {{WRAPPER}} .digi-progress__value::before',
	), array(
		'name' => 'percentage_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__value::before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	) );
