<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-vertex` (bdthemes-prime-slider-lite/modules/vertex/widgets/vertex.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-vertex',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'columns' => 3,
		'columns_tablet' => 3,
		'columns_mobile' => 1,
		'primary_thumbnail_size' => 'full',
		'show_title' => 'yes',
		'title_tags' => 'h3',
		'show_category' => 'yes',
		'posts_source' => 'post',
		'posts_limit' => 5,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'centered_slides' => 'yes',
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
	),
	'repeaters' => array(),
	'styles' => array(
		'slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-item' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-vertex-slider .bdt-img-wrap .bdt-img' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'slider_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-img-wrap .bdt-img' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'vertical_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .swiper-vertex' => 'padding: {{SIZE}}{{UNIT}} 0;',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'item_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-img-wrap .bdt-img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'border_style_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .swiper-vertex::before' => 'background: {{VALUE}};',
				'{{WRAPPER}} .bdt-vertex-slider .bdt-img-wrap .bdt-img' => 'border-color: {{VALUE}};',
			),
		),
		'border_width_type' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .swiper-vertex::before' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-vertex-slider .bdt-img-wrap .bdt-img' => 'border-width: {{SIZE}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-title a',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-title a',
		),
		'category_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category a' => 'color: {{VALUE}};',
			),
		),
		'category_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-category a',
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category a+a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'category_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-category a',
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-category a',
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-category a:hover',
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev',
		),
		'arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev',
		),
		'arrows_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev',
		),
		'arrows_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev',
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next:hover, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev:hover' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next::before, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev::before',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next:hover, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-next:hover, {{WRAPPER}} .bdt-vertex-slider .bdt-navigation-wrap .bdt-navigation-prev:hover',
		),
	),
);
