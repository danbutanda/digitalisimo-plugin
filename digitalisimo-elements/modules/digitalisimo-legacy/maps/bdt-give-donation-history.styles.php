<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-donation-history` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'header_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'background-color: {{VALUE}};',
		),
		'default' => '#e7ebef',
	), array(
		'name' => 'header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'color: {{VALUE}};',
		),
		'default' => '#333',
	), array(
		'name' => 'header_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'options' => array(
			'none' => 'None',
			'solid' => 'Solid',
			'double' => 'Double',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'header_border_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'header_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 1,
			'right' => 2,
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-table th',
	), array(
		'name' => 'header_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table th' => 'text-align: {{VALUE}}',
		),
		'default' => 'center',
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
		'name' => 'cell_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'options' => array(
			'none' => 'None',
			'solid' => 'Solid',
			'double' => 'Double',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'cell_border_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'cell_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'default' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 2,
			'right' => 2,
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'body_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-table td',
	), array(
		'name' => 'body_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'text-align: {{VALUE}}',
		),
		'default' => 'center',
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
		'name' => 'normal_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'background-color: {{VALUE}} !important;',
		),
		'default' => '#fff',
	), array(
		'name' => 'normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'row_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table tr:hover td' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'row_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table tr:hover td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'stripe_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table tr:nth-child(even) td' => 'background-color: {{VALUE}} !important;',
		),
		'default' => '#f5f5f5',
	), array(
		'name' => 'stripe_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table tr:nth-child(even) td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-table td a:hover' => 'color: {{VALUE}};',
		),
	) );
