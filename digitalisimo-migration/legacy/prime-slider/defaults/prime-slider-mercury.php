<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-mercury` (bdthemes-prime-slider-lite/modules/mercury/widgets/mercury.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-mercury',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'primary_thumbnail_size' => 'full',
		'posts_source' => 'post',
		'posts_limit' => 5,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h3',
		'show_excerpt' => 'yes',
		'excerpt_length' => 30,
		'strip_shortcode' => 'yes',
		'show_category' => 'yes',
		'show_author' => 'yes',
		'meta_separator' => '//',
		'show_date' => 'yes',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'rewind' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'effect' => 'slide',
		'creative_effect' => 'creative-1',
		'show_navigation' => 'yes',
		'category_border_border' => 'solid',
		'category_border_width' => array(
			'top' => '1',
			'right' => '1',
			'bottom' => '1',
			'left' => '1',
			'isLinked' => false,
		),
		'category_border_color' => '#e7e7e7',
	),
	'repeaters' => array(),
	'styles' => array(
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-item' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'Background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}}  .bdt-mercury-slider',
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-title a',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-desc' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-desc' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-desc' => 'max-width: {{SIZE}}px;',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-desc',
		),
		'meta_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-meta, {{WRAPPER}} .bdt-mercury-content-slider .bdt-author a' => 'color: {{VALUE}};',
			),
		),
		'meta_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-author a:hover' => 'color: {{VALUE}};',
			),
		),
		'meta_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-separator' => 'margin: 0 {{SIZE}}{{UNIT}};',
			),
		),
		'meta_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-meta',
		),
		'category_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a' => 'color: {{VALUE}};',
			),
		),
		'category_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a',
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a+a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'category_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a',
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a',
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a:hover',
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-content-slider .bdt-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev',
		),
		'arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev',
		),
		'arrows_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_horizontal_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next' => 'right: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev' => 'left: {{SIZE}}px;',
			),
		),
		'arrows_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev',
		),
		'arrows_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next:hover, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev:hover' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next:hover, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev:hover',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next:hover, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-next:hover, {{WRAPPER}} .bdt-mercury-image-slider .bdt-navigation-wrap .bdt-button-prev:hover',
		),
	),
);
