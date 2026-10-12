<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-progress-pie` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'progress_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__circle svg ellipse' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'progress_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__circle svg path' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'before_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-before' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'before!' => '',
		),
	), array(
		'name' => 'before_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__circle-before',
	), array(
		'name' => 'middle_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-text' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'text!' => '',
		),
	), array(
		'name' => 'middle_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__circle-text',
	), array(
		'name' => 'number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-number' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'text' => '',
		),
	), array(
		'name' => 'percentage_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__circle-number',
	), array(
		'name' => 'after_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-after' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'after!' => '',
		),
	), array(
		'name' => 'after_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__circle-after',
	), array(
		'name' => 'progress_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__circle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__label' => 'background-color: {{VALUE}};  border-top: none;',
		),
		'condition' => array(
			'title!' => '',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__label' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'title!' => '',
		),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-progress__circle-wrapper .digi-progress__label',
	) );
