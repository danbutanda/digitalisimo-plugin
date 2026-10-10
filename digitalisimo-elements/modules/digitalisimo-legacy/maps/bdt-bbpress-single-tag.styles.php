<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-bbpress-single-tag` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'breadcrumb_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'breadcrumb_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'breadcrumb_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'breadcrumb_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-breadcrumb',
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
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-search-form .button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .bbp-search-form .button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .bbp-search-form .button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-search-form .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-search-form .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-search-form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-search-form .button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-search-form .button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .bbp-search-form .button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-search-form .button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
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
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-topics',
	), array(
		'name' => 'forum_body_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topics' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'forum_body_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topics' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_body_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topics' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-permalink' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-permalink:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-permalink' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-topic-permalink',
	), array(
		'name' => 'forum_count_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-topic-voice-count, {{WRAPPER}} .bbp-topic-reply-count' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_count_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-topic-voice-count, {{WRAPPER}} .bbp-topic-reply-count' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_count_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-topic-voice-count, {{WRAPPER}} .bbp-topic-reply-count',
	), array(
		'name' => 'forum_meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-meta *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_meta_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-freshness a:hover, {{WRAPPER}} #bbpress-forums .bbp-topic-meta a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_meta_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-topic-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-meta *' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-topic-freshness a, {{WRAPPER}} #bbpress-forums .bbp-topic-meta *',
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
	) );
