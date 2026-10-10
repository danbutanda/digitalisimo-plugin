<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-bbpress-topic-tags` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'tags_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => 'text-align: {{VALUE}}',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'topic_tags_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'topic_tags_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} a.tag-cloud-link',
	), array(
		'name' => 'topic_tags_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} a.tag-cloud-link',
	), array(
		'name' => 'topic_tags_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'topic_tags_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; display: inline-block;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'topic_tags_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'topic_tags_typography',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'topic_tags_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} a.tag-cloud-link',
	), array(
		'name' => 'topic_tags_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'topic_tags_background_color_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} a.tag-cloud-link:hover',
	), array(
		'name' => 'topic_tags_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.tag-cloud-link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'topic_tags_border_border!' => '',
		),
	) );
