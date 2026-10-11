<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-wc-products` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'search_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .dataTables_filter' => 'margin-bottom: {{SIZE}}px;',
		),
	), array(
		'name' => 'pagination_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .dataTables_paginate' => 'margin-top: {{SIZE}}px;',
		),
	), array(
		'name' => 'pagination_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .paginate_button' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'active_pagination_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .paginate_button.current' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'pagination_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .paginate_button' => 'margin: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'pagination_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .dataTables_paginate',
	), array(
		'name' => 'info_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .dataTables_info' => 'margin-top: {{SIZE}}px;',
		),
		'condition' => array(
			'_skin' => 'bdt-table',
		),
	), array(
		'name' => 'info_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .dataTables_info' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin' => 'bdt-table',
		),
	), array(
		'name' => 'info_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .dataTables_info',
	) );
