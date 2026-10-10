<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-bbpress-single-forum` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'breadcrumb_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb *, {{WRAPPER}} #bbpress-forums a.subscription-toggle' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'breadcrumb_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb a:hover, {{WRAPPER}} #bbpress-forums a.subscription-toggle:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'breadcrumb_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb, {{WRAPPER}} #bbpress-forums a.subscription-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'breadcrumb_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-breadcrumb, {{WRAPPER}} #bbpress-forums a.subscription-toggle',
	), array(
		'name' => 'header_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header li' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-header',
	), array(
		'name' => 'header_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-header',
	), array(
		'name' => 'header_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-header li',
	), array(
		'name' => 'forum_body_odd_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_body_even_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_body_list_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-body ul.forum' => 'border-top-color: {{VALUE}};',
		),
	), array(
		'name' => 'odd_even_forum_body_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even, {{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_body_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums ul.bbp-forums, {{WRAPPER}} #bbpress-forums ul.bbp-topics',
	), array(
		'name' => 'forum_body_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums ul.bbp-forums, {{WRAPPER}} #bbpress-forums ul.bbp-topics' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'forum_body_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums ul.bbp-forums, {{WRAPPER}} #bbpress-forums ul.bbp-topics' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_body_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums ul.bbp-forums, {{WRAPPER}} #bbpress-forums ul.bbp-topics' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-title, {{WRAPPER}} #bbpress-forums .bbp-topic-permalink' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-title:hover, {{WRAPPER}} #bbpress-forums .bbp-topic-permalink:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-title, {{WRAPPER}} #bbpress-forums .bbp-topic-permalink' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-forum-title, {{WRAPPER}} #bbpress-forums .bbp-topic-permalink',
	), array(
		'name' => 'forum_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-content' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-forum-content',
	), array(
		'name' => 'forum_title_list_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-forums-list a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_list_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-forums-list a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_list_divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forums-list' => 'border-left-color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_list_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-forums-list a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_title_list_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-forums-list a',
	), array(
		'name' => 'forum_count_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-forum-topic-count, {{WRAPPER}} .bbp-forum-reply-count, {{WRAPPER}} .bbp-topic-voice-count, {{WRAPPER}} .bbp-topic-reply-count' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_count_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-forum-topic-count, {{WRAPPER}} .bbp-forum-reply-count, {{WRAPPER}} .bbp-topic-voice-count, {{WRAPPER}} .bbp-topic-reply-count' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_count_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-forum-topic-count, {{WRAPPER}} .bbp-forum-reply-count, {{WRAPPER}} .bbp-topic-voice-count, {{WRAPPER}} .bbp-topic-reply-count',
	), array(
		'name' => 'forum_meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-meta *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_meta_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-freshness a:hover, {{WRAPPER}} #bbpress-forums .bbp-topic-freshness a:hover, {{WRAPPER}} #bbpress-forums .bbp-topic-meta a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_meta_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-forum-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-meta *' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-forum-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-meta *',
	), array(
		'name' => 'main_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form legend' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'form_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-topic-form .bbp-form',
	), array(
		'name' => 'form_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form .bbp-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form .bbp-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'form_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form .bbp-form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'main_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-topic-form legend',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-topic-form label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form textarea::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea',
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea',
	), array(
		'name' => 'input_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select' => 'height: {{SIZE}}{{UNIT}}; min-height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-form textarea#bbp_forum_content' => 'height: {{SIZE}}{{UNIT}}; width: 100%;',
		),
		'default' => array(
			'size' => 200,
		),
	), array(
		'name' => 'input_textarea_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums input[type="text"], {{WRAPPER}} #bbpress-forums input[type="date"], {{WRAPPER}} #bbpress-forums input[type="email"], {{WRAPPER}} #bbpress-forums input[type="number"], {{WRAPPER}} #bbpress-forums input[type="password"], {{WRAPPER}} #bbpress-forums input[type="search"], {{WRAPPER}} #bbpress-forums input[type="tel"], {{WRAPPER}} #bbpress-forums input[type="url"], {{WRAPPER}} #bbpress-forums select, {{WRAPPER}} #bbpress-forums textarea' => 'width: {{SIZE}}%; max-width: {{SIZE}}%;',
		),
	), array(
		'name' => 'button_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper' => '{{VALUE}};',
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button' => '{{VALUE}};',
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
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-submit-wrapper button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'pagination_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-pagination-count' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-pagination-count',
	), array(
		'name' => 'pagination_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links a' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'pagination_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-pagination-links a',
	), array(
		'name' => 'pagination_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-pagination-links a, {{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current',
	), array(
		'name' => 'pagination_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links a, {{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'pagination_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links a, {{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'pagination_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links a, {{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'pagination_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-pagination-links a, {{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current',
	), array(
		'name' => 'pagination_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-pagination-links a, {{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current',
	), array(
		'name' => 'pagination_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_background_color_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-pagination-links a:hover:hover',
	), array(
		'name' => 'pagination_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links a:hover:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'pagination_border_border!' => '',
		),
	), array(
		'name' => 'pagination_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-pagination-links span.current' => 'color: {{VALUE}};',
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
