<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-easy-digital-download-history` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'header_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history th' => 'text-align: {{VALUE}};',
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
		'name' => 'body_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history td' => 'text-align: {{VALUE}};',
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
		'name' => 'table_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history' => 'border-style: {{VALUE}};',
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
		'name' => 'table_border_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'min' => 0,
			'max' => 20,
			'size' => 1,
		),
	), array(
		'name' => 'table_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'header_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history th' => 'background-color: {{VALUE}};',
		),
		'default' => '#dfe3e6',
	), array(
		'name' => 'header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history th' => 'color: {{VALUE}};',
		),
		'default' => '#333',
	), array(
		'name' => 'header_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history th' => 'border-style: {{VALUE}};',
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
			'{{WRAPPER}} #edd_user_history th' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'min' => 0,
			'max' => 20,
			'size' => 1,
		),
	), array(
		'name' => 'header_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history th' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'cell_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history td' => 'border-style: {{VALUE}};',
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
			'{{WRAPPER}} #edd_user_history td' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'min' => 0,
			'max' => 20,
			'size' => 1,
		),
	), array(
		'name' => 'cell_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'normal_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history tr:nth-child(odd) td' => 'background-color: {{VALUE}};',
		),
		'default' => '#fff',
	), array(
		'name' => 'normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history tr:nth-child(odd) td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history tr:nth-child(odd) td' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'stripe_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history tr:nth-child(even) td' => 'background-color: {{VALUE}};',
		),
		'default' => '#f7f7f7',
	), array(
		'name' => 'stripe_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history tr:nth-child(even) td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'stripe_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_user_history tr:nth-child(even) td' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	) );
