<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `fooevents-calendar` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'fooevents_buttons_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_buttons_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_buttons_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default' => 'border-color: {{VALUE}} !important;',
			'{{WRAPPER}} .fc-state-default.fc-state-hover' => 'border-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_buttons_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default' => 'text-transform: {{VALUE}};',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_buttons_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fooevents_buttons_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default.fc-state-hover' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_buttons_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default.fc-state-hover' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-unthemed th, {{WRAPPER}} .fc-unthemed td, {{WRAPPER}} .fc-unthemed thead, {{WRAPPER}} .fc-unthemed tbody, {{WRAPPER}} .fc-unthemed .fc-divider, {{WRAPPER}} .fc-unthemed .fc-row, {{WRAPPER}} .fc-unthemed .fc-content, {{WRAPPER}} .fc-unthemed .fc-popover, {{WRAPPER}} .fc-unthemed .fc-list-view, {{WRAPPER}} .fc-unthemed .fc-list-heading td' => 'border-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_border_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .fc th, {{WRAPPER}} .fc td' => 'border-width: {{SIZE}}{{UNIT}} !important;',
			'{{WRAPPER}} .fc-list-view' => 'border-width: {{SIZE}}{{UNIT}} !important;',
			'{{WRAPPER}} .fc-list-table tr:first-child td' => 'border-top-width: 0 !important;',
			'{{WRAPPER}} .fc-list-table td' => 'border-width: {{SIZE}}{{UNIT}} 0 0 !important;',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'fooevents_table_body_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc th, {{WRAPPER}} .fc td, {{WRAPPER}} .fc hr, {{WRAPPER}} .fc thead, {{WRAPPER}} .fc tbody, {{WRAPPER}} .fc-row' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc th, {{WRAPPER}} .fc td, {{WRAPPER}} .fc hr, {{WRAPPER}} .fc thead, {{WRAPPER}} .fc tbody, {{WRAPPER}} .fc-row' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc th, {{WRAPPER}} .fc td, {{WRAPPER}} .fc hr, {{WRAPPER}} .fc thead, {{WRAPPER}} .fc tbody, {{WRAPPER}} .fc-row' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .fc th, {{WRAPPER}} .fc td, {{WRAPPER}} .fc hr, {{WRAPPER}} .fc thead, {{WRAPPER}} .fc tbody, {{WRAPPER}} .fc-row' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
