<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-totals` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .raised' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .raised' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'title_typography',
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
	), array(
		'name' => 'message_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-totals-shortcode-wrap' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'message_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-totals-shortcode-wrap',
	), array(
		'name' => 'link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.give-totals-text-link' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.give-totals-text-link:hover' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'link_margin',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.give-totals-text-link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'link_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} a.give-totals-text-link',
	) );
