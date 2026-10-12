<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-bbpress-single-reply` con selectores del widget propio.
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
		'name' => 'header_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-reply-header .bbp-meta *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #bbpress-forums div.bbp-reply-header',
	), array(
		'name' => 'header_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums div.bbp-reply-header',
	), array(
		'name' => 'header_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-reply-header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-reply-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.bbp-reply-header' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #bbpress-forums div.bbp-reply-header .bbp-meta *',
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
			'{{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd, {{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even' => 'border-top-color: {{VALUE}};',
		),
	), array(
		'name' => 'forum_body_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd, {{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even',
	), array(
		'name' => 'forum_body_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd, {{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'forum_body_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd, {{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'forum_body_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #bbpress-forums div.odd, {{WRAPPER}} #bbpress-forums ul.odd, {{WRAPPER}} #bbpress-forums div.even, {{WRAPPER}} #bbpress-forums ul.even' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
			'{{WRAPPER}} .bbp-reply-author a:hover' => 'color: {{VALUE}};',
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
	) );
