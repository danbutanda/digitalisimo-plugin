<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-the-newsletter` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'input_labels_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp-subscription div.tnp-field label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'the_news_letter_type' => 'standard',
		),
	), array(
		'name' => 'input_fields_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp-subscription div.tnp-field' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-shortcode .tnp.tnp-subscription-minimal .tnp-email' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_fields_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp label' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp label',
	), array(
		'name' => 'input_fields_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])' => 'color: {{VALUE}} ',
			'{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])::placeholder' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'input_fields_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])',
	), array(
		'name' => 'input_fields_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])',
	), array(
		'name' => 'input_fields_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
		),
	), array(
		'name' => 'input_fields_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])',
	), array(
		'name' => 'input_fields_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_fields_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"])',
	), array(
		'name' => 'input_fields_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"]):focus' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'input_fields_background_active',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"]):focus',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'input_fields_shadow_active',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"]):focus',
	), array(
		'name' => 'input_fields_border_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input:not([type="submit"]):focus' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'input_fields_border_border!' => '',
		),
	), array(
		'name' => 'submit_button_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]' => 'width: calc({{SIZE}}% / 3.39);',
		),
		'condition' => array(
			'submit_button_full_width' => '',
		),
	), array(
		'name' => 'submit_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'submit_button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submit_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]',
	), array(
		'name' => 'submit_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'submit_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]',
	), array(
		'name' => 'submit_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submit_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]',
	), array(
		'name' => 'submit_button_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]:hover' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'submit_button_background_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submit_button_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]:hover',
	), array(
		'name' => 'submit_button_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .tnp input[type=submit]:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'submit_button_border_border!' => '',
		),
	) );
