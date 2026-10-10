<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-slider` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'slider_container_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-content-wrap' => 'max-width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-posts-container-content' => 'max-width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-pagination' => 'max-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => '',
		),
	), array(
		'name' => 'content_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-content' => 'max-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => '',
		),
	), array(
		'name' => 'bottom_part_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-content' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'right_part_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-post__thumbnail ~ div' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_nav_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_nav_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_nav_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_nav_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_nav_arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a svg polyline' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_nav_hover_arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a:hover svg polyline' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'right_part_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner a:first-child:after' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .skin-hazel .elementor-posts-container-navigation-inner' => 'border-top-color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-tag-wrap span a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-tag-wrap span' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tag_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container-tag-wrap span',
	), array(
		'name' => 'tag_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-tag-wrap span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tag_border_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-tag-wrap span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'tag_space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-tag-wrap span+span' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'tag_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container-tag-wrap span',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post__title',
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__title' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt',
	), array(
		'name' => 'text_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'text_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt p' => 'width: {{SIZE}}%;',
		),
	), array(
		'name' => 'meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data a, {{WRAPPER}} .elementor-posts-container .elementor-post__meta-data span, {{WRAPPER}} .elementor-posts-container .elementor-post__meta-data .elementor-post-author' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'meta_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data a:hover span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data',
	), array(
		'name' => 'meta_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more-wrap' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'pagination_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-pagination .thumb-title-default-skin' => 'color: {{VALUE}}',
			'{{WRAPPER}} .elementor-pagination span' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumb_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-thumb-wrap' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'show_pagination_thumb' => 'yes',
		),
	), array(
		'name' => 'thumb_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-thumb-wrap img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'show_pagination_thumb' => 'yes',
		),
	), array(
		'name' => 'thumb_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container-thumb-wrap',
	), array(
		'name' => 'thumb_border_radius',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container-thumb-wrap' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
		),
		'condition' => array(
			'show_pagination_thumb' => 'yes',
		),
	), array(
		'name' => 'thumb_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-pagination .elementor-posts-container-thumb-wrap img' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'show_pagination_thumb' => 'yes',
		),
	), array(
		'name' => 'arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'arrows_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a',
	), array(
		'name' => 'arrows_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a',
	), array(
		'name' => 'arrows_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'arrows_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a:hover',
	), array(
		'name' => 'arrows_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-vast .elementor-posts-container-navigation a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'arrows_border_border!' => '',
		),
	) );
