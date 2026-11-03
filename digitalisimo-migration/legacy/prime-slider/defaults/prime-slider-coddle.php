<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-coddle` (bdthemes-prime-slider/modules/coddle/widgets/coddle.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-coddle',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'content_left_spacing' => array(
			'unit' => '%',
		),
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_text' => 'yes',
		'show_button_text' => 'yes',
		'show_play_button' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_fraction' => 'yes',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'swiper_effect' => 'slide',
		'creative_effect' => 'creative-1',
		'button_type' => 'outline',
		'nav_arrows_icon' => '8',
		'pagination_direction' => 'right',
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'slide_button_text' => array(
					'__t' => 'Read More',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'button_link' => array(
					'url' => '#',
				),
				'image' => array(
					'url' => $placeholder,
				),
				'lightbox_link' => array(
					'url' => 'https://www.youtube.com/watch?v=YE7VzlLtp-4',
				),
				'title_link' => array(
					'url' => '',
				),
				'text' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
			),
			'rows' => array( array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Slider Item One',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Slider Item Two',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Slider Item Three',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'slider_item_content_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_left_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-content' => 'left: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'wrapper_spacing' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-coddle-mt-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-item, {{WRAPPER}} .bdt-coddle-slider .bdt-main-slider' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'custom_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-image-wrap img',
		),
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-coddle-mt-wrap:before' => 'background-image: linear-gradient(275deg, transparent 0%, {{VALUE}} 90%);',
			),
		),
		'shape_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-content:before' => 'background-image: radial-gradient(circle at 1px 1px, {{VALUE}} 1px, transparent 0);',
			),
		),
		'wrapper_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider',
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-title, {{WRAPPER}} .bdt-coddle-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-title:hover, {{WRAPPER}} .bdt-coddle-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-title .frist-word' => 'color: {{VALUE}};',
			),
		),
		'title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-title',
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-title',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-sub-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-sub-title',
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-sub-title',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-text',
		),
		'link_btn_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a' => 'color: {{VALUE}};',
			),
		),
		'link_btn_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a' => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:after' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
			),
		),
		'link_btn_border_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:after' => 'border-color: {{VALUE}};',
			),
		),
		'link_btn_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a, {{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a',
		),
		'link_btn_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:hover' => 'color: {{VALUE}};',
			),
		),
		'link_btn_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:hover' => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:hover:after' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
			),
		),
		'link_btn_hover_border_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:hover' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-coddle-slider .bdt-link-btn a:hover:after' => 'border-color: {{VALUE}};',
			),
		),
		'play_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a' => 'color: {{VALUE}};',
			),
		),
		'play_button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a',
		),
		'play_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a',
		),
		'play_button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'play_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'play_button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'play_button_font_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'play_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a:hover' => 'color: {{VALUE}};',
			),
		),
		'play_button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a:hover',
		),
		'play_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-play-button a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'thumb_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-thumbs-slider .bdt-single-title, {{WRAPPER}} .bdt-coddle-slider .bdt-thumbs-slider .bdt-single-title a' => 'color: {{VALUE}};',
			),
		),
		'thumb_title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-thumbs-slider .bdt-single-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'thumb_title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-thumbs-slider .bdt-single-title',
		),
		'thumb_title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-thumbs-slider .bdt-single-title',
		),
		'thumb_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-thumbs-slider .bdt-single-title',
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn i' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn',
		),
		'nav_arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn',
		),
		'border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-navigation-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-navigation-wrap' => 'grid-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-nav-btn',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-prev:hover i, {{WRAPPER}} .bdt-navigation-next:hover i' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-prev:hover, {{WRAPPER}} .bdt-navigation-next:hover',
		),
		'nav_arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-prev:hover, {{WRAPPER}} .bdt-navigation-next:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-prev:hover, {{WRAPPER}} .bdt-navigation-next:hover',
		),
		'pagination_direction' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap' => '{{VALUE}};',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet' => 'color: {{VALUE}}',
			),
		),
		'pagination_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet' => 'background-color: {{VALUE}};',
			),
		),
		'pagination_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet',
		),
		'pagination_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet:hover' => 'color: {{VALUE}}',
			),
		),
		'pagination_hover_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet:hover' => 'background-color: {{VALUE}} !important;',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet::after' => 'background-color: {{VALUE}}',
			),
		),
		'pagination_active_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-coddle-slider .bdt-pagination-wrap .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'background-color: {{VALUE}} !important;',
			),
		),
	),
);
