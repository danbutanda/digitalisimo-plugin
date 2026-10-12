<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-grid` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post *' => 'text-align: {{VALUE}};',
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
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'post_grid_icon[value]!' => '',
		),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'content_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => array( 'bdt-carmie', 'bdt-trosia' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-post',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post',
	), array(
		'name' => 'item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post',
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_box_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post:hover',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-post__thumbnail',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => array( 'bdt-harold', 'bdt-reverse', 'bdt-alter', 'bdt-alite' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .elementor-post__thumbnail a',
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post:hover .elementor-post__thumbnail' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
			'_skin' => array( 'bdt-harold', 'bdt-reverse', 'bdt-alter', 'bdt-alite' ),
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .elementor-post:hover .elementor-post__thumbnail a',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'_skin!' => 'bdt-carmie',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__title a',
	), array(
		'name' => 'title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title a',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title a',
	), array(
		'name' => 'title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title a',
	), array(
		'name' => 'title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title a',
	), array(
		'name' => 'title_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__title a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'author_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-author a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'author_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-author a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'author_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post-author a',
	), array(
		'name' => 'date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-posts-container-date' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-posts-container-date',
	), array(
		'name' => 'comments_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post-avatar *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comments_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post-avatar',
	), array(
		'name' => 'category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'category_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'category_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a',
	), array(
		'name' => 'category_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'category_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'category_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__terms--category a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'category_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--category a' => 'gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a',
	), array(
		'name' => 'category_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a',
	), array(
		'name' => 'category_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'category_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'category_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__terms--category a a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'category_border_border!' => '',
		),
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'excerpt_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt',
	), array(
		'name' => 'readmore_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'readmore_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'readmore_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'tag_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a',
	), array(
		'name' => 'tag_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tag_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'tag_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__terms--post_tag' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'tag_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'tag_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a',
	), array(
		'name' => 'tag_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a',
	), array(
		'name' => 'tag_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__terms--post_tag .elementor-post__terms--post_tag a li a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tag_border_border!' => '',
		),
	), array(
		'name' => 'carmie_desc_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__text' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'_skin' => 'bdt-carmie',
		),
	), array(
		'name' => 'overlay_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__text' => 'background: -webkit-linear-gradient(top, rgba(0,0,0,0) 0%,{{VALUE)}} 70%);
					                                                     background: linear-gradient(to bottom, rgba(0,0,0,0) 0%,{{VALUE)}} 70%);',
		),
		'condition' => array(
			'_skin' => 'bdt-trosia',
		),
	), array(
		'name' => 'alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .ep-pagination .elementor-pagination' => 'justify-content: {{VALUE}}',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'flex-end' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
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
		'types' => array( 'classic', 'gradient' ),
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
		'types' => array( 'classic', 'gradient' ),
	) );
