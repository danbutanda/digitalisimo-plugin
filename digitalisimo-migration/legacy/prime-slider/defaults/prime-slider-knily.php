<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-knily` (bdthemes-prime-slider/modules/knily/widgets/knily.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-knily',
	'plugin' => 'pro',
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
		'show_button' => 'yes',
		'button_text' => array(
			'__t' => 'More Details',
			'd' => 'bdthemes-prime-slider',
		),
		'show_social_share' => 'yes',
		'button_previous_text' => array(
			'__t' => 'Prev',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'button_next_text' => array(
			'__t' => 'Next',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'speed' => array(
			'size' => 900,
		),
		'loop' => 'yes',
		'rewind' => 'yes',
		'swiper_effect' => 'fade',
		'gl_shader' => 'random',
		'creative_effect' => 'creative-1',
		'show_navigation_dots' => 'yes',
		'item_overlay_color_background' => 'classic',
		'item_overlay_color_color' => 'rgba(0, 0, 0, 0.438)',
	),
	'repeaters' => array(
		'share_buttons' => array(
			'fields' => array(
				'button' => 'facebook',
			),
			'rows' => array( array(
					'button' => 'facebook',
				), array(
					'button' => 'linkedin',
				), array(
					'button' => 'twitter',
				) ),
		),
	),
	'styles' => array(
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .swiper-knily .bdt-item' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'item_overlay_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-img-wrap:before',
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-title a',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-desc' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-desc' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-desc' => 'max-width: {{SIZE}}px;',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-desc',
		),
		'meta_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-meta, {{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-meta
                        .bdt-author-name-wrap .bdt-author-name' => 'color: {{VALUE}};',
			),
		),
		'meta_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-meta .bdt-author-name-wrap .bdt-author-name:hover' => 'color: {{VALUE}};',
			),
		),
		'meta_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-meta' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'meta_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-meta .bdt-ps-separator' => 'margin: 0 {{SIZE}}{{UNIT}};',
			),
		),
		'meta_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-meta',
		),
		'button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a',
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a',
		),
		'button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a',
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a',
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a:hover' => 'color: {{VALUE}};',
			),
		),
		'button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a::before',
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'category_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a' => 'color: {{VALUE}};',
			),
		),
		'category_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a',
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a+a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'category_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a',
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a',
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a:hover',
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'social_share_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn' => 'color: {{VALUE}};',
			),
		),
		'social_share_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn',
		),
		'social_share_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn',
		),
		'social_share_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_share_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_share_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_share_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn',
		),
		'social_share_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn',
		),
		'social_share_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn:hover' => 'color: {{VALUE}};',
			),
		),
		'social_share_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-social-share .bdt-ps-btn:hover',
		),
		'social_share_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-btn-wrap a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'thumbs_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'thumbs_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'item_thumb_overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item .bdt-thumbs-img-wrap:before' => 'background: {{VALUE}};',
			),
		),
		'thumbs_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-knily-thumbs .bdt-item',
		),
		'thumbs_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'thumbs_content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item .bdt-knily-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'counter_horizontal_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item .bdt-knily-content .bdt-knily-counter' => 'left: {{SIZE}}{{UNIT}};',
			),
		),
		'counter_vertical_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item .bdt-knily-content .bdt-knily-counter' => 'top: {{SIZE}}{{UNIT}};',
			),
		),
		'thumb_counter_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item .bdt-knily-content .bdt-knily-counter' => 'color: {{VALUE}};',
			),
		),
		'thumb_counter_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-knily-thumbs .bdt-item .bdt-knily-content .bdt-knily-counter',
		),
		'thumb_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-thumbs-title a' => 'color: {{VALUE}};',
			),
		),
		'thumb_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-knily-thumbs .bdt-thumbs-title',
		),
		'thumbs_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item:hover' => 'border-color: {{VALUE}};',
			),
		),
		'thumb_counter_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item:hover .bdt-knily-content .bdt-knily-counter' => 'color: {{VALUE}};',
			),
		),
		'thumb_title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-knily-thumbs .bdt-item:hover .bdt-thumbs-title a' => 'color: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation .bdt-navigation-prev' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation .bdt-navigation-prev:hover' => 'color: {{VALUE}}',
			),
		),
		'arrows_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'arrow_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-navigation .bdt-navigation-prev',
		),
		'dots_nny_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination' => 'bottom: {{SIZE}}px !important;',
			),
		),
		'dots_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet' => 'background-color: {{VALUE}}',
			),
		),
		'dots_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'dots_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
			),
		),
		'advanced_dots_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'advanced_dots_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'advanced_dots_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'dots_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet',
		),
		'active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'background-color: {{VALUE}}',
			),
		),
		'active_dots_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet-active' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}}' => '--ps-swiper-dots-active-height: {{SIZE}}{{UNIT}};',
			),
		),
		'active_advanced_dots_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet-active' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'active_advanced_dots_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet-active' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}}' => '--ps-swiper-dots-active-height: {{SIZE}}{{UNIT}};',
			),
		),
		'active_advanced_dots_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet-active' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'active_advanced_dots_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-swiper-dots-align: {{VALUE}};',
			),
		),
		'dots_active_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-knily .bdt-knily-pagination .swiper-pagination .swiper-pagination-bullet-active',
		),
	),
);
