<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `fooevents-calendar` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'fooevents_header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'fooevents_header_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar',
	), array(
		'name' => 'fooevents_header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
	), array(
		'name' => 'fooevents_header_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
	), array(
		'name' => 'fooevents_header_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar h2' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_header_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar h2' => 'text-transform: {{VALUE}};',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_header_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar h2' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_buttons_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fc-state-default' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-toolbar .fc-button-group .fc-button.fc-state-hover' => 'background-position: 0px !important;',
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
		'name' => 'fooevents_table_header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fc-view-container .fc-basic-view table thead tr td .fc-widget-header' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fc-view-container .fc-basic-view table thead tr td .fc-widget-header' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-basic-view table .fc-head .fc-head-container .fc-row table thead tr th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_header_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-basic-view table .fc-head .fc-head-container .fc-row table thead tr th' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_header_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-basic-view table .fc-head .fc-head-container .fc-row table thead tr th' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
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
	), array(
		'name' => 'fooevents_table_body_today_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fc-view-container .fc-basic-view table tbody tr td .fc-widget-content.fc-today' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_event_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-basic-view .fc-body .fc-row .fc-content-skeleton .fc-event-container .fc-content .fc-title' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_event_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-basic-view .fc-body .fc-row .fc-content-skeleton .fc-event-container .fc-content' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_day_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-heading td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_day_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-heading td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_day_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-heading td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_header_list_day_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-heading td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_header_list_day_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-heading td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fooevents_table_body_list_day_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_day_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_day_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_body_list_day_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_list_day_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fooevents_table_body_list_day_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_body_list_day_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item:hover td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_day_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item:hover td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_day_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listDay-view .fc-list-table .fc-list-item:hover .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_header_list_week_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-heading td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_week_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-heading td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_week_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-heading td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_header_list_week_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-heading td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_header_list_week_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-heading td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fooevents_table_body_list_week_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_week_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_week_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_body_list_week_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_list_week_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fooevents_table_body_list_week_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_body_list_week_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item:hover td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_week_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item:hover td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_week_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listWeek-view .fc-list-table .fc-list-item:hover .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_header_list_month_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-heading td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_month_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-heading td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_month_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-heading td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_header_list_month_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-heading td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fooevents_table_header_list_month_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-heading td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item td' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item .fc-list-item-title a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_body_list_month_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_body_list_month_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item:hover td' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item:hover .fc-list-item-title a' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item:hover td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_month_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listMonth-view .fc-list-table .fc-list-item:hover .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_header_list_year_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-heading td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_year_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-heading td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_header_list_year_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-heading td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_header_list_year_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-heading td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_header_list_year_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-heading td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item td' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item' => 'background: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item td' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_body_list_year_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item td' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%', 'em', 'rem' ),
	), array(
		'name' => 'fooevents_table_body_list_year_text_transform',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item td' => 'text-transform: {{VALUE}} !important;',
		),
		'options' => array(
			'' => 'None',
			'uppercase' => 'UPPERCASE',
			'lowercase' => 'lowercase',
			'capitalize' => 'Capitalize',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item:hover td' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item:hover .fc-list-item-title a' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item:hover td' => 'background: {{VALUE}} !important;',
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item:hover .fc-list-item-title a' => 'background: {{VALUE}} !important;',
		),
	), array(
		'name' => 'fooevents_table_body_list_year_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ep-fooevents-calendar .fooevents_calendar .fc-view-container .fc-listYear-view .fc-list-table .fc-list-item:hover .fc-list-item-marker .fc-event-dot' => 'background-color: {{VALUE}};',
		),
	) );
