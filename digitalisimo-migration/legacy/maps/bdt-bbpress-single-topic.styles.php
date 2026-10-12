<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-bbpress-single-topic` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'breadcrumb_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb *, {{WRAPPER}} #bbpress-forums a.subscription-toggle, {{WRAPPER}} #bbpress-forums a.favorite-toggle' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'breadcrumb_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb a:hover, {{WRAPPER}} #bbpress-forums a.subscription-toggle:hover, {{WRAPPER}} #bbpress-forums a.favorite-toggle:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'breadcrumb_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .bbp-breadcrumb, {{WRAPPER}} #bbpress-forums a.subscription-toggle, {{WRAPPER}} #bbpress-forums a.favorite-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'breadcrumb_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bbp-breadcrumb, {{WRAPPER}} #bbpress-forums a.subscription-toggle, {{WRAPPER}} #bbpress-forums a.favorite-toggle',
	), array(
		'name' => 'header_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header>div, {{WRAPPER}} #bbpress-forums li.bbp-footer>div' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-header, {{WRAPPER}} #bbpress-forums li.bbp-footer',
	), array(
		'name' => 'header_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-header, {{WRAPPER}} #bbpress-forums li.bbp-footer',
	), array(
		'name' => 'header_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header, {{WRAPPER}} #bbpress-forums li.bbp-footer' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header, {{WRAPPER}} #bbpress-forums li.bbp-footer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-header, {{WRAPPER}} #bbpress-forums li.bbp-footer' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-header, {{WRAPPER}} #bbpress-forums li.bbp-footer li',
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
			'{{WRAPPER}} #bbpress-forums li.bbp-body .odd' => 'border-top: 2px solid {{VALUE}};',
			'{{WRAPPER}} #bbpress-forums li.bbp-body .even' => 'border-top: 2px solid {{VALUE}};',
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
		'selector' => '{{WRAPPER}} #bbpress-forums li.bbp-body',
	), array(
		'name' => 'forum_body_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-body' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'forum_body_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_body_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums li.bbp-body' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'author_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-author *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'author_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-author .bbp-author-link .bbp-author-name:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'author_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-author' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'author_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-reply-author *',
	), array(
		'name' => 'author_avatar',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-reply-author img.avatar' => 'max-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'author_avatar_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-reply-author img.avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-content' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-reply-content',
	), array(
		'name' => 'forum_meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-meta *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_meta_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-meta a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_meta_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-meta' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-meta *',
	), array(
		'name' => 'main_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form legend' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'form_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-reply-form .bbp-form',
	), array(
		'name' => 'form_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form .bbp-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form .bbp-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'form_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form .bbp-form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'main_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-reply-form legend',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums .bbp-reply-form label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #bbpress-forums .bbp-reply-form textarea::placeholder' => 'color: {{VALUE}};',
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
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-the-content-wrapper textarea.bbp-the-content' => 'height: {{SIZE}}{{UNIT}}; width: 100%;',
		),
		'default' => array(
			'size' => 200,
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
			'{{WRAPPER}} div.bbp-template-notice *, {{WRAPPER}} div.indicator-hint' => 'color: {{VALUE}};',
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
		'selector' => '{{WRAPPER}} div.bbp-template-notice *, {{WRAPPER}} div.bbp-template-notice li',
	) );
