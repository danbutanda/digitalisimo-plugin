<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-charitable-campaigns` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop' => 'display: grid;
					grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '3',
		'tablet_default' => '2',
		'mobile_default' => '1',
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
		),
	), array(
		'name' => 'items_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop' => 'grid-gap: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'item_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign',
	), array(
		'name' => 'content_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign' => 'text-align: {{VALUE}}',
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
		'name' => 'item_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign:hover',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .wp-post-image, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .wp-post-image',
	), array(
		'name' => 'image_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .wp-post-image, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .wp-post-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'iamge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .wp-post-image, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .wp-post-image' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .wp-post-image, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .wp-post-image' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'image_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .wp-post-image, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .wp-post-image',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry h3, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign h3' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry h3:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign h3:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry h3, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign h3' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry h3, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign h3',
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-description, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-description, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-description, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-description',
	), array(
		'name' => 'amount_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-donation-stats, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-donation-stats' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'amount_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-donation-stats, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-donation-stats' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'amount_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-donation-stats, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-donation-stats',
	), array(
		'name' => 'progress_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-progress-bar .bar, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-progress-bar .bar' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'progress_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-progress-bar, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-progress-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'progress_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-progress-bar, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-progress-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'progress_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-progress-bar, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'progress_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry .campaign-progress-bar, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign .campaign-progress-bar' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .campaign-loop .hentry a.button:hover, {{WRAPPER}} .elementor-shortcode .campaign-loop li.campaign a.button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	) );
