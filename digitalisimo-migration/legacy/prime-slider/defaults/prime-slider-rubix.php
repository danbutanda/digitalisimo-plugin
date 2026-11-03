<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-rubix` (bdthemes-prime-slider-lite/modules/rubix/widgets/rubix.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-rubix',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'columns' => 2,
		'columns_tablet' => 1,
		'columns_mobile' => 1,
		'thumbs_max_width' => array(
			'unit' => '%',
		),
		'thumbs_position' => 'bdt-slide-style-1',
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
		'show_date' => 'yes',
		'show_read_more' => 'yes',
		'read_more_text' => array(
			'__t' => 'Read More',
			'd' => 'bdthemes-prime-slider-lite',
		),
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
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'thumbs_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'Background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-item',
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-item.swiper-slide-active .bdt-slider-progress' => 'background: {{VALUE}};',
			),
		),
		'line_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-item .bdt-slider-progress' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-title a',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-text' => 'max-width: {{SIZE}}px;',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-text',
		),
		'date_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time' => 'color: {{VALUE}};',
			),
		),
		'date_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time',
		),
		'date_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar' => 'color: {{VALUE}};',
			),
		),
		'date_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar',
		),
		'date_icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar',
		),
		'date_icon_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'date_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'date_icon_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'date_icon_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar',
		),
		'date_icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-meta .bdt-date-time i.ps-wi-calendar',
		),
		'category_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a' => 'color: {{VALUE}};',
			),
		),
		'category_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a',
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a+a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'category_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a',
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a',
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a:hover',
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'read_more_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a' => 'color: {{VALUE}}',
			),
		),
		'icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i' => 'color: {{VALUE}}',
			),
		),
		'icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i',
		),
		'icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i',
		),
		'icon_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i, {{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'read_more_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'icon_font_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'read_more_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i',
		),
		'Read_more_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a',
		),
		'read_more_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a:hover' => 'color: {{VALUE}}',
			),
		),
		'read_more_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a:hover i' => 'color: {{VALUE}}',
			),
		),
		'read_more_icon_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a i:after',
		),
		'read_icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a:hover i' => 'border-color: {{VALUE}};',
			),
		),
		'read_more_icon_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more a:hover i',
		),
		'thumbs_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item',
		),
		'thumbs_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item',
		),
		'thumbs_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'thumbs_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'thumb_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-content:after' => 'background: {{VALUE}};',
			),
		),
		'line_border_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-content:before, {{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-content:after' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'thumb_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'thumbs_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-title' => 'padding-top: {{SIZE}}{{UNIT}};',
			),
		),
		'thumb_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-title',
		),
		'meta_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-meta, {{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-meta .bdt-author a' => 'color: {{VALUE}};',
			),
		),
		'meta_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-meta',
		),
		'thumbs_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item:hover',
		),
		'thumbs_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item:hover' => 'border-color: {{VALUE}};',
			),
		),
		'thumb_line_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item:hover .bdt-content:before' => 'background: {{VALUE}};',
			),
		),
		'thumb_title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'meta_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-meta .bdt-author a:hover' => 'color: {{VALUE}};',
			),
		),
		'thumbs_active_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item.swiper-slide-thumb-active',
		),
		'thumbs_active_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item.swiper-slide-thumb-active' => 'border-color: {{VALUE}};',
			),
		),
		'thumb_line_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-thumb-slider .bdt-item.swiper-slide-thumb-active .bdt-content:before' => 'background: {{VALUE}};',
			),
		),
	),
);
