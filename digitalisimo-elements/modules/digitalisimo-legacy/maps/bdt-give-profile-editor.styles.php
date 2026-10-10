<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-profile-editor` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'give_profile_editor_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form > fieldset' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'give_profile_editor_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form > fieldset',
	), array(
		'name' => 'give_profile_editor_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form > fieldset' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'give_profile_editor_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form > fieldset' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'main_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'main_title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form legend',
	), array(
		'name' => 'main_title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'main_title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'main_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'main_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form legend',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give-section-break' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give-section-break' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give-section-break' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .give-section-break',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form label',
	), array(
		'name' => 'input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=email], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=password], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=tel], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=text], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=url], {{WRAPPER}} .elementor-shortcode .give-form .form-row select, {{WRAPPER}} .elementor-shortcode .give-form .form-row textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=email], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=password], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=tel], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=text], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=url], {{WRAPPER}} .elementor-shortcode .give-form .form-row select, {{WRAPPER}} .elementor-shortcode .give-form .form-row textarea' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=email], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=password], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=tel], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=text], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=url], {{WRAPPER}} .elementor-shortcode .give-form .form-row select, {{WRAPPER}} .elementor-shortcode .give-form .form-row textarea',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=email], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=password], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=tel], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=text], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=url], {{WRAPPER}} .elementor-shortcode .give-form .form-row select, {{WRAPPER}} .elementor-shortcode .give-form .form-row textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=email], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=password], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=tel], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=text], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=url], {{WRAPPER}} .elementor-shortcode .give-form .form-row select, {{WRAPPER}} .elementor-shortcode .give-form .form-row textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=email], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=password], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=tel], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=text], {{WRAPPER}} .elementor-shortcode .give-form .form-row input[type=url], {{WRAPPER}} .elementor-shortcode .give-form .form-row select, {{WRAPPER}} .elementor-shortcode .give-form .form-row textarea',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give_submit' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .give_submit',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .give_submit',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give_submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give_submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .give_submit',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .give_submit',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give_submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form .give_submit:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form .give_submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'password_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give_password_change_notice' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'password_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give_password_change_notice',
	) );
