<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-bbpress-reply-form` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'main_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form .bbp-form legend' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'form_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .bbp-reply-form .bbp-form',
	), array(
		'name' => 'form_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form .bbp-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form .bbp-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'form_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form .bbp-form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'main_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-reply-form .bbp-form legend',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-reply-form label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} .bbp-reply-form textarea::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} input[type="text"], {{WRAPPER}} input[type="date"], {{WRAPPER}} input[type="email"], {{WRAPPER}} input[type="number"], {{WRAPPER}} input[type="password"], {{WRAPPER}} input[type="search"], {{WRAPPER}} input[type="tel"], {{WRAPPER}} input[type="url"], {{WRAPPER}} select, {{WRAPPER}} textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} input[type="text"], {{WRAPPER}} input[type="date"], {{WRAPPER}} input[type="email"], {{WRAPPER}} input[type="number"], {{WRAPPER}} input[type="password"], {{WRAPPER}} input[type="search"], {{WRAPPER}} input[type="tel"], {{WRAPPER}} input[type="url"], {{WRAPPER}} select, {{WRAPPER}} textarea',
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} input[type="text"], {{WRAPPER}} input[type="date"], {{WRAPPER}} input[type="email"], {{WRAPPER}} input[type="number"], {{WRAPPER}} input[type="password"], {{WRAPPER}} input[type="search"], {{WRAPPER}} input[type="tel"], {{WRAPPER}} input[type="url"], {{WRAPPER}} select, {{WRAPPER}} textarea',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} input[type="text"], {{WRAPPER}} input[type="date"], {{WRAPPER}} input[type="email"], {{WRAPPER}} input[type="number"], {{WRAPPER}} input[type="password"], {{WRAPPER}} input[type="search"], {{WRAPPER}} input[type="tel"], {{WRAPPER}} input[type="url"], {{WRAPPER}} select, {{WRAPPER}} textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} input[type="text"], {{WRAPPER}} input[type="date"], {{WRAPPER}} input[type="email"], {{WRAPPER}} input[type="number"], {{WRAPPER}} input[type="password"], {{WRAPPER}} input[type="search"], {{WRAPPER}} input[type="tel"], {{WRAPPER}} input[type="url"], {{WRAPPER}} select, {{WRAPPER}} textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} input[type="text"], {{WRAPPER}} input[type="date"], {{WRAPPER}} input[type="email"], {{WRAPPER}} input[type="number"], {{WRAPPER}} input[type="password"], {{WRAPPER}} input[type="search"], {{WRAPPER}} input[type="tel"], {{WRAPPER}} input[type="url"], {{WRAPPER}} select, {{WRAPPER}} textarea',
	), array(
		'name' => 'input_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form fieldset.bbp-form select, {{WRAPPER}} .bbp-reply-form fieldset.bbp-form input[type="text"], {{WRAPPER}} .bbp-reply-form fieldset.bbp-form input[type="password"], {{WRAPPER}} fieldset.bbp-form select, {{WRAPPER}} fieldset.bbp-form input[type="text"], {{WRAPPER}} fieldset.bbp-form input[type="password"]' => 'height: {{SIZE}}{{UNIT}}; min-height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form textarea#bbp_reply_content, {{WRAPPER}} .bbp-reply-form textarea#bbp_topic_content, {{WRAPPER}} .bbp-reply-form textarea#bbp_forum_content' => 'height: {{SIZE}}{{UNIT}}; width: 100%;',
		),
		'default' => array(
			'size' => 200,
		),
	), array(
		'name' => 'input_textarea_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-reply-form fieldset.bbp-form select, {{WRAPPER}} .bbp-reply-form fieldset.bbp-form input[type="text"], {{WRAPPER}} .bbp-reply-form fieldset.bbp-form input[type="password"], {{WRAPPER}} fieldset.bbp-form select, {{WRAPPER}} fieldset.bbp-form input[type="text"], {{WRAPPER}} fieldset.bbp-form input[type="password"], {{WRAPPER}} .bbp-reply-form textarea#bbp_reply_content, {{WRAPPER}} .bbp-reply-form textarea#bbp_topic_content, {{WRAPPER}} .bbp-reply-form textarea#bbp_forum_content' => 'width: {{SIZE}}%; max-width: {{SIZE}}%;',
		),
	), array(
		'name' => 'button_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper' => '{{VALUE}};',
			'{{WRAPPER}} .bbp-submit-wrapper button' => '{{VALUE}};',
		),
		'selectors_dictionary' => array(
			'left' => 'text-align: left; float: inherit;',
			'right' => 'text-align: right; float: inherit;',
			'center' => 'text-align: center; float: inherit;',
			'justify' => 'text-align: justify; float: inherit; width: 100%;',
		),
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
			'justify' => array(
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .bbp-submit-wrapper button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .bbp-submit-wrapper button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-submit-wrapper button',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .bbp-submit-wrapper button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .bbp-submit-wrapper button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-submit-wrapper button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'notice_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} div.bbp-template-notice, {{WRAPPER}} div.indicator-hint' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'notice_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} div.bbp-template-notice, {{WRAPPER}} div.indicator-hint',
	), array(
		'name' => 'notice_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} div.bbp-template-notice, {{WRAPPER}} div.indicator-hint',
	), array(
		'name' => 'notice_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} div.bbp-template-notice, {{WRAPPER}} div.indicator-hint' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'notice_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} div.bbp-template-notice, {{WRAPPER}} div.indicator-hint' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'notice_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} div.bbp-template-notice, {{WRAPPER}} div.indicator-hint' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'notice_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} div.bbp-template-notice p, {{WRAPPER}} div.bbp-template-notice li',
	) );
