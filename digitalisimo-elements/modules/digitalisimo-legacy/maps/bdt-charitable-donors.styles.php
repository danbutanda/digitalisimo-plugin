<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-charitable-donors` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list' => 'text-align: {{VALUE}}',
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
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '3',
		'tablet_default' => '2',
		'mobile_default' => '1',
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		),
	), array(
		'name' => 'items_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list' => 'grid-gap: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'item_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor',
	), array(
		'name' => 'item_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor:hover',
	), array(
		'name' => 'name_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor:hover .donor-name' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'location_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor:hover .donor-location' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'amount_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor:hover .donor-donation-amount' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'avatar_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor .avatar',
	), array(
		'name' => 'iamge_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'avatar_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .avatar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'avatar_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor .avatar',
	), array(
		'name' => 'avatar_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'name_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-name' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'name_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-name' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'name_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-name',
	), array(
		'name' => 'location_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-location' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'location_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-location' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'location_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-location',
	), array(
		'name' => 'amount_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-donation-amount' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'amount_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-donation-amount' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'amount_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .donors-list .donor .donor-donation-amount',
	) );
