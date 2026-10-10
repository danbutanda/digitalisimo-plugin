<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-tags-cloud` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'basic_tags_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'basic_tags_margin',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'basic_tags_typo',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-tags-cloud__link',
	), array(
		'name' => 'basic_tags_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link' => 'color: {{VALUE}}  !important;',
		),
	), array(
		'name' => 'basic_tags_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-tags-cloud__link',
	), array(
		'name' => 'basic_tags_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-tags-cloud__link',
	), array(
		'name' => 'basic_tags_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'basic_tags_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link:hover' => 'color: {{VALUE}}  !important;',
		),
	), array(
		'name' => 'basic_tags_text_shadow_hover',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-tags-cloud__link:hover',
	), array(
		'name' => 'basic_tags_hover_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link:hover' => 'background-color: {{VALUE}}  !important;',
		),
	), array(
		'name' => 'basic_tags_border_hover',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-tags-cloud__link:hover',
	), array(
		'name' => 'basic_tags_hover_effect',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-tags-cloud__link:hover' => 'transform: {{VALUE}}  !important;',
		),
		'default' => 'scale(1.1)',
		'options' => array(
			'scale(1.1)' => 'Basic',
			'rotate(5deg)' => 'Rotate ',
			'rotate(10deg)' => 'Rotate 1x',
			'rotate(20deg)' => 'Rotate 2x',
			'rotate(360deg)' => 'Rotate 360',
			'translate3d(7px, 14px, 10px)' => 'Translate',
			'skew(8deg, -9deg)' => 'Skew',
		),
	) );
