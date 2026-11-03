<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-monster` (bdthemes-prime-slider/modules/monster/widgets/monster.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-monster',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'columns' => 3,
		'columns_tablet' => 3,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 0,
		),
		'item_gap_tablet' => array(
			'size' => 0,
		),
		'item_gap_mobile' => array(
			'size' => 0,
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'show_social_share' => 'yes',
		'show_navigation_arrows' => 'yes',
		'navigation_center_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'hide_on_mobile' => 'yes',
		'thumbnail_size_size' => 'full',
		'follow_us_text' => array(
			'__t' => 'Follow Us',
			'd' => 'bdthemes-prime-slider',
		),
		'skin' => 'coverflow',
		'coverflow_rotate' => array(
			'size' => 0,
		),
		'coverflow_stretch' => array(
			'size' => 180,
		),
		'coverflow_modifier' => array(
			'size' => 1,
		),
		'coverflow_depth' => array(
			'size' => 100,
		),
		'slide_shadows' => 'yes',
		'show_lightbox' => 'yes',
		'lightbox_animation' => 'slide',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'item_shadow_padding' => array(
			'size' => 10,
		),
		'image_opacity' => array(
			'size' => 1,
		),
		'image_hover_opacity' => array(
			'size' => 1,
		),
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'title_link' => array(
					'url' => '',
				),
				'image' => array(
					'url' => $placeholder,
				),
			),
			'rows' => array( array(
					'sub_title' => array(
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Monster Slider Item One',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Monster Slider Item Two',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Monster Slider Item Three',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Monster Slider Item Four',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
				) ),
		),
		'social_link_list' => array(
			'fields' => array(),
			'rows' => array( array(
					'social_icon_link' => array(
						'url' => 'http://www.facebook.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'Fb',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.twitter.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'Tw',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.instagram.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'In',
						'd' => 'bdthemes-prime-slider',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content' => 'text-align: {{VALUE}};',
			),
		),
		'item_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item' => 'background-color: {{VALUE}};',
			),
		),
		'item_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item',
		),
		'item_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item',
		),
		'item_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'item_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'item_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item:hover' => 'background-color: {{VALUE}};',
			),
		),
		'item_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item:hover' => 'border-color: {{VALUE}};',
			),
		),
		'item_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item:hover',
		),
		'item_shadow_padding' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .swiper-monster' => 'padding: {{SIZE}}{{UNIT}}; margin: 0 -{{SIZE}}{{UNIT}};',
			),
		),
		'image_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image' => 'background-color: {{VALUE}};',
			),
		),
		'image_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'image_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'image_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image img' => 'opacity: {{SIZE}};',
			),
		),
		'image_hover_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image:hover img' => 'opacity: {{SIZE}};',
			),
		),
		'image_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slide-image' => 'margin-bottom: {{SIZE}}{{UNIT}}',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag>a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag:hover, {{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag>a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-main-title' => 'padding-top: {{SIZE}}{{UNIT}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag>a',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-content .bdt-title-tag>a',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label' => 'color: {{VALUE}};',
			),
		),
		'sub_title_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label',
		),
		'sub_title_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label',
		),
		'sub_title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label',
		),
		'sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label' => 'left: {{SIZE}}{{UNIT}}; top: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label' => '{{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-slider-item .bdt-slide-label',
		),
		'social_icon_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-social-icon h3' => 'color: {{VALUE}};',
			),
		),
		'social_icon_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-prime-slider-social-icon h3',
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'color: {{VALUE}};',
			),
		),
		'social_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_size' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover' => 'color: {{VALUE}};',
			),
		),
		'social_icon_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover',
		),
		'icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_acx_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next' => 'right: {{SIZE}}px;',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev',
		),
		'arrows_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev:hover' => 'color: {{VALUE}}',
			),
		),
		'arrows_background_hover' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev:hover',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-monster .bdt-navigation-arrows .bdt-navigation-prev:hover' => 'border-color: {{VALUE}}',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .swiper-pagination .swiper-pagination-bullet' => 'background: {{VALUE}}',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .swiper-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'background: {{VALUE}}',
			),
		),
		'pagination_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-monster .swiper-pagination .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
