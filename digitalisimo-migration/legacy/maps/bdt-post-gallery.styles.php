<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-gallery` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_ratio',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'height: {{SIZE}}px',
		),
		'default' => array(
			'size' => 250,
		),
		'condition' => array(
			'masonry!' => 'yes',
		),
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail, {{WRAPPER}} .elementor-posts-container .elementor-posts-container-inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_skin_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} 0 0;',
		),
		'condition' => array(
			'_skin!' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'overlay_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__text' => 'background: -webkit-linear-gradient(top, rgba(0,0,0,0) 0%,{{VALUE)}} 70%); background: linear-gradient(to bottom, rgba(0,0,0,0) 0%,{{VALUE)}} 70%);',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_title' => 'yes',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post .elementor-post__title',
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'excerpt_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'margin: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post-link',
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post-link',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post-link',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'category_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a',
	), array(
		'name' => 'tag_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tag_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a',
	), array(
		'name' => 'tag_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'tag_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__terms--post_tag a',
	), array(
		'name' => 'pagination_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a, {{WRAPPER}} ul.elementor-pagination li span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a',
	), array(
		'name' => 'pagination_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a',
	), array(
		'name' => 'pagination_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-pagination' => 'margin-top: {{SIZE}}px;',
		),
	), array(
		'name' => 'pagination_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-pagination' => 'margin-left: {{SIZE}}px;',
			'{{WRAPPER}} .elementor-pagination > *' => 'padding-left: {{SIZE}}px;',
		),
	), array(
		'name' => 'pagination_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a' => 'padding: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'pagination_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a' => 'border-radius: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'pagination_arrow_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a svg' => 'height: {{SIZE}}px; width: auto;',
		),
	), array(
		'name' => 'pagination_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a, {{WRAPPER}} ul.elementor-pagination li span',
	), array(
		'name' => 'pagination_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ul.elementor-pagination li a:hover' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} ul.elementor-pagination li a:hover',
	) );
