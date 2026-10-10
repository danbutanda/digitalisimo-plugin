<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-table` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'header_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'text-align: {{VALUE}};',
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
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table' => 'text-align: {{VALUE}}; justify-content: {{VALUE}};',
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
			'{{WRAPPER}} .digi-table' => 'border-style: {{VALUE}};',
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
			'{{WRAPPER}} .digi-table' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'table_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
	), array(
		'name' => 'header_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'background-color: {{VALUE}};',
		),
		'default' => '#e7ebef',
	), array(
		'name' => 'header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'color: {{VALUE}};',
		),
		'default' => '#333',
	), array(
		'name' => 'header_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'border-style: {{VALUE}};',
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
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
		'condition' => array(
			'header_border_style!' => 'none',
		),
	), array(
		'name' => 'header_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
		'condition' => array(
			'header_border_style!' => 'none',
		),
	), array(
		'name' => 'header_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th:first-child' => 'border-radius: {{TOP}}{{UNIT}} 0 0 {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .digi-table-wrap th:last-child' => 'border-radius: 0 {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} 0;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		'selector' => '{{WRAPPER}} .digi-table-wrap th',
	), array(
		'name' => 'normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap td' => 'background-color: {{VALUE}};',
		),
		'default' => '#fff',
	), array(
		'name' => 'cell_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap td' => 'border-style: {{VALUE}};',
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
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap td' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
		'condition' => array(
			'cell_border_style!' => 'none',
		),
	), array(
		'name' => 'normal_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap td' => 'border-color: {{VALUE}};',
		),
		'default' => '#ccc',
		'condition' => array(
			'cell_border_style!' => 'none',
		),
	), array(
		'name' => 'cell_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap tr:first-child td:first-child' => 'border-radius: {{TOP}}{{UNIT}} 0 0 0;',
			'{{WRAPPER}} .digi-table-wrap tr:first-child td:last-child' => 'border-radius: 0 {{RIGHT}}{{UNIT}} 0 0;',
			'{{WRAPPER}} .digi-table-wrap tr:last-child td:first-child' => 'border-radius: 0 0 0 {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .digi-table-wrap tr:last-child td:last-child' => 'border-radius: 0 0 {{BOTTOM}}{{UNIT}} 0;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'cell_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		'name' => 'body_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-table-wrap td',
	), array(
		'name' => 'stripe_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap tr:nth-child(even) td' => 'background-color: {{VALUE}};',
		),
		'default' => '#f5f5f5',
		'condition' => array(
			'stripe_style' => 'yes',
		),
	), array(
		'name' => 'stripe_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap tr:nth-child(even) td' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'stripe_style' => 'yes',
		),
	), array(
		'name' => 'datatable_header_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_length label, {{WRAPPER}} .digi-table-wrap .dataTables_filter label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'datatable_header_input_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_filter input, {{WRAPPER}} .digi-table-wrap .dataTables_length select' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'datatable_header_input_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_filter input, {{WRAPPER}} .digi-table-wrap .dataTables_length select' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'datatable_header_input_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_filter input, {{WRAPPER}} .digi-table-wrap .dataTables_length select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'datatable_header_input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-table-wrap .dataTables_filter input, {{WRAPPER}} .digi-table-wrap .dataTables_length select',
	), array(
		'name' => 'datatable_header_input_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_filter input, {{WRAPPER}} .digi-table-wrap .dataTables_length select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'datatable_header_input_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-table-wrap .dataTables_filter input, {{WRAPPER}} .digi-table-wrap .dataTables_length select',
	), array(
		'name' => 'datatable_header_space',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_filter' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'datatable_footer_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_info, {{WRAPPER}} .digi-table-wrap .dataTables_paginate' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'datatable_footer_pagination_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_paginate a' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'datatable_footer_pagination_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table-wrap .dataTables_paginate a.current' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'datatable_footer_space',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-table' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
	) );
