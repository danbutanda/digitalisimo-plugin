<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-tablepress` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'header_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'text-align: {{VALUE}};',
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
			'{{WRAPPER}} .elementor-shortcode table.tablepress tr td' => 'text-align: {{VALUE}};',
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
		'name' => 'navigation_hide',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_length' => 'display: none;',
		),
	), array(
		'name' => 'search_hide',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_filter' => 'display: none;',
		),
	), array(
		'name' => 'footer_info_hide',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_info' => 'display: none;',
		),
	), array(
		'name' => 'pagination_hide',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_paginate' => 'display: none;',
		),
	), array(
		'name' => 'table_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_length, {{WRAPPER}} .elementor-shortcode .dataTables_filter, {{WRAPPER}} .elementor-shortcode .dataTables_info, {{WRAPPER}} .elementor-shortcode .paginate_button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'table_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode table.tablepress' => 'border-style: {{VALUE}};',
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
			'{{WRAPPER}} .elementor-shortcode table.tablepress' => 'border-width: {{SIZE}}{{UNIT}};',
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
			'{{WRAPPER}} .elementor-shortcode table.tablepress' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'table_header_tools_gap',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_length, {{WRAPPER}} .elementor-shortcode .dataTables_filter' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'table_footer_tools_gap',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_info, {{WRAPPER}} .elementor-shortcode .dataTables_paginate' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'header_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'background-color: {{VALUE}};',
		),
		'default' => '#dfe3e6',
	), array(
		'name' => 'header_active_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .sorting:hover, {{WRAPPER}} .elementor-shortcode .tablepress .sorting_asc, {{WRAPPER}} .elementor-shortcode .tablepress .sorting_desc' => 'background-color: {{VALUE}};',
		),
		'default' => '#ccd3d8',
	), array(
		'name' => 'header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'color: {{VALUE}};',
		),
		'default' => '#333',
	), array(
		'name' => 'header_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'border-style: {{VALUE}};',
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
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'border-width: {{SIZE}}{{UNIT}};',
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
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
			'{{WRAPPER}} .elementor-shortcode .tablepress td' => 'border-style: {{VALUE}};',
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
			'{{WRAPPER}} .elementor-shortcode .tablepress td' => 'border-width: {{SIZE}}{{UNIT}};',
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
			'{{WRAPPER}} .elementor-shortcode .tablepress td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
			'{{WRAPPER}} .elementor-shortcode .tablepress .odd td' => 'background-color: {{VALUE}};',
		),
		'default' => '#fff',
	), array(
		'name' => 'normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .odd td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .odd td' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'stripe_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .even td' => 'background-color: {{VALUE}};',
		),
		'default' => '#f7f7f7',
	), array(
		'name' => 'stripe_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .even td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'stripe_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .even td' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'body_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tablepress .row-hover tr:hover td' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'search_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_filter input, {{WRAPPER}} .elementor-shortcode .dataTables_length select' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'search_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_filter input, {{WRAPPER}} .elementor-shortcode .dataTables_length select' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'search_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_filter input, {{WRAPPER}} .elementor-shortcode .dataTables_length select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'search_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .dataTables_filter input, {{WRAPPER}} .elementor-shortcode .dataTables_length select',
	), array(
		'name' => 'search_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .dataTables_filter input, {{WRAPPER}} .elementor-shortcode .dataTables_length select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'search_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .dataTables_filter input, {{WRAPPER}} .elementor-shortcode .dataTables_length select',
	) );
