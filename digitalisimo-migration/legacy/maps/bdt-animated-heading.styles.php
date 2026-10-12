<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-animated-heading` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'animated_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-heading *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'animated_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-animated-heading',
	), array(
		'name' => 'animated_heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-animated-heading *',
	), array(
		'name' => 'animated_heading_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-animated-heading',
	), array(
		'name' => 'animated_heading_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'animated_heading_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}}.digi-animated-heading__phrase-gradient-yes .digi-animated-heading__phrase',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'pre_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-heading__before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pre_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-animated-heading__before',
	), array(
		'name' => 'pre_heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-animated-heading__before',
	), array(
		'name' => 'pre_heading_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-animated-heading__before',
	), array(
		'name' => 'post_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-animated-heading__after' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'post_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-animated-heading__after',
	), array(
		'name' => 'post_heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-animated-heading__after',
	), array(
		'name' => 'post_heading_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-animated-heading__after',
	) );
