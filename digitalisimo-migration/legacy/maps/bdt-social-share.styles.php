<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-social-share` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-buttons' => '{{VALUE}};',
		),
		'desktop_default' => '0',
		'tablet_default' => '0',
		'mobile_default' => '0',
		'selectors_dictionary' => array(
			'0' => 'display: flex; flex-wrap: wrap;',
			'1' => 'grid-template-columns: repeat(1, 1fr); display: grid;',
			'2' => 'grid-template-columns: repeat(2, 1fr); display: grid;',
			'3' => 'grid-template-columns: repeat(3, 1fr); display: grid;',
			'4' => 'grid-template-columns: repeat(4, 1fr); display: grid;',
			'5' => 'grid-template-columns: repeat(5, 1fr); display: grid;',
			'6' => 'grid-template-columns: repeat(6, 1fr); display: grid;',
		),
		'options' => array(
			'0' => 'Auto',
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		),
	), array(
		'name' => 'column_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-buttons' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-buttons' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-buttons' => '{{VALUE}};',
		),
		'selectors_dictionary' => array(
			'left' => 'justify-content: flex-start;',
			'center' => 'justify-content: center;',
			'right' => 'justify-content: flex-end;',
			'justify' => 'justify-content: space-between;',
		),
		'condition' => array(
			'columns' => '0',
		),
		'options' => array(
			'left' => array(
				'title' => 'Start',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'End',
				'icon' => 'eicon-text-align-right',
			),
			'justify' => array(
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn' => 'font-size: calc({{SIZE}}{{UNIT}} * 10);',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn__icon i' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'em',
		),
		'tablet_default' => array(
			'unit' => 'em',
		),
		'mobile_default' => array(
			'unit' => 'em',
		),
		'condition' => array(
			'view!' => 'text',
		),
		'size_units' => array( 'em', 'px' ),
	), array(
		'name' => 'button_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'em',
		),
		'tablet_default' => array(
			'unit' => 'em',
		),
		'mobile_default' => array(
			'unit' => 'em',
		),
		'size_units' => array( 'em', 'px' ),
	), array(
		'name' => 'border_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-share-btn' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 2,
		),
		'condition' => array(
			'style' => array( 'framed', 'boxed' ),
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'view' => 'text',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-share-btn__title',
		'exclude' => array( 'line_height' ),
	), array(
		'name' => 'primary_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}.elementor-share-btns-style-flat .elementor-share-btn, {{WRAPPER}}.elementor-share-btns-style-gradient .elementor-share-btn' => 'background-color: {{VALUE}}',
			'{{WRAPPER}}.elementor-share-btns-style-framed .elementor-share-btn, {{WRAPPER}}.elementor-share-btns-style-minimal .elementor-share-btn, {{WRAPPER}}.elementor-share-btns-style-boxed .elementor-share-btn' => 'color: {{VALUE}}; border-color: {{VALUE}}',
		),
		'condition' => array(
			'color_source' => 'custom',
		),
	), array(
		'name' => 'secondary_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}.elementor-share-btns-style-flat .elementor-share-buttons-text, {{WRAPPER}}.elementor-share-btns-style-gradient .elementor-share-buttons-text' => 'color: {{VALUE}}',
			'{{WRAPPER}}.elementor-share-btns-style-framed .elementor-share-btn' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'color_source' => 'custom',
		),
	), array(
		'name' => 'border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}.elementor-share-btns-style-boxed .elementor-share-btn, {{WRAPPER}}.elementor-share-btns-style-framed .elementor-share-btn' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'color_source' => 'custom',
			'style' => array( 'framed', 'boxed' ),
		),
	), array(
		'name' => 'primary_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}.elementor-share-btns-style-flat .elementor-share-btn:hover, {{WRAPPER}}.elementor-share-btns-style-gradient .elementor-share-btn:hover' => 'background-color: {{VALUE}}',
			'{{WRAPPER}}.elementor-share-btns-style-framed .elementor-share-btn:hover, {{WRAPPER}}.elementor-share-btns-style-minimal .elementor-share-btn:hover, {{WRAPPER}}.elementor-share-btns-style-boxed .elementor-share-btn:hover' => 'color: {{VALUE}}; border-color: {{VALUE}}',
		),
		'condition' => array(
			'color_source' => 'custom',
		),
	), array(
		'name' => 'secondary_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}.elementor-share-btns-style-flat .elementor-share-btn:hover .elementor-share-buttons-text, {{WRAPPER}}.elementor-share-btns-style-gradient .elementor-share-btn:hover .elementor-share-buttons-text' => 'color: {{VALUE}}',
			'{{WRAPPER}}.elementor-share-btns-style-framed .elementor-share-btn:hover' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'color_source' => 'custom',
		),
	), array(
		'name' => 'border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}.elementor-share-btns-style-boxed .elementor-share-btn:hover, {{WRAPPER}}.elementor-share-btns-style-framed .elementor-share-btn:hover' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'color_source' => 'custom',
			'style' => array( 'framed', 'boxed' ),
		),
	) );
