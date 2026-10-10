<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-search` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'search_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form-container .elementor-search-form-default' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'skin!' => array( 'modal' ),
		),
	), array(
		'name' => 'button_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit' => 'right: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'search_button' => 'yes',
			'show_ajax_search' => '',
		),
	), array(
		'name' => 'icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit i, {{WRAPPER}} .elementor-search-form__submit svg' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'search_button' => 'yes',
			'show_ajax_search' => '',
		),
	), array(
		'name' => 'toggle_icon_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__toggle' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'toggle_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__toggle' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-search-form__toggle svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'toggle_icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__toggle' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'toggle_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'toggle_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-search-form__toggle',
	), array(
		'name' => 'toggle_icon_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'toggle_icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-search-form__toggle',
	), array(
		'name' => 'search_container_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form-container .elementor-search-form:not(.elementor-search-form-navbar)' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'search_container_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form-container .elementor-search-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'search_container_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form-container .elementor-search-form:not(.elementor-search-form-navbar)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'search_container_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-search-form-container .elementor-search-form:not(.elementor-search-form-navbar)',
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form .elementor-search-form-icon svg' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'skin' => 'default',
		),
	), array(
		'name' => 'modal_search_icon_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'#modal-search-{{ID}} .elementor-search-form-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: auto;',
		),
		'condition' => array(
			'skin' => 'modal',
		),
	), array(
		'name' => 'search_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form .elementor-search-form-icon svg' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'skin' => 'default',
		),
	), array(
		'name' => 'search_icon_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form .elementor-search-form-icon' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'skin' => 'default',
		),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-search-form__input, #modal-search-{{ID}} .elementor-search-form__input',
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input, #modal-search-{{ID}} .elementor-search-form-icon svg' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'input_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form-container .elementor-search-form .elementor-search-form__input' => 'background-color: {{VALUE}}',
			'#modal-search-{{ID}} .elementor-search-form-container .elementor-search-form .elementor-search-form__input' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'skin!' => 'modal',
		),
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input::placeholder' => 'color: {{VALUE}}',
			'#modal-search-{{ID}} .elementor-search-form__input::placeholder' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'input_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input' => 'border-color: {{VALUE}}',
			'#modal-search-{{ID}} .elementor-search-form__input' => 'border-color: {{VALUE}}',
		),
	), array(
		'name' => 'button_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'#modal-search-{{ID}} .elementor-search-form__input' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'border_radius',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input' => 'border-radius: {{SIZE}}{{UNIT}}',
			'#modal-search-{{ID}} .elementor-search-form__input' => 'border-radius: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => 3,
			'unit' => 'px',
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form .elementor-search-form__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'#modal-search-{{ID}} .elementor-search-form__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-search-form__input',
	), array(
		'name' => 'input_text_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input:focus' => 'color: {{VALUE}}',
			'#modal-search-{{ID}} .elementor-search-form__input:focus' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'input_background_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form-container .elementor-search-form .elementor-search-form__input:focus' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'skin!' => 'modal',
		),
	), array(
		'name' => 'input_border_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__input:focus' => 'border-color: {{VALUE}}',
			'#modal-search-{{ID}} .elementor-search-form__input:focus' => 'border-color: {{VALUE}}',
		),
	), array(
		'name' => 'input_shadow_focus',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-search-form__input:focus',
	), array(
		'name' => 'search_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-search-form__submit svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'search_button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-search-form__submit',
	), array(
		'name' => 'search_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-search-form__submit',
	), array(
		'name' => 'search_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'search_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-search-form__submit',
	), array(
		'name' => 'search_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'search_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-search-form__submit',
	), array(
		'name' => 'search_button_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-search-form__submit:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'search_button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-search-form__submit:hover',
	), array(
		'name' => 'search_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-search-form__submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'search_button_border_border!' => '',
		),
	), array(
		'name' => 'search_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-search-form__submit:hover',
	) );
