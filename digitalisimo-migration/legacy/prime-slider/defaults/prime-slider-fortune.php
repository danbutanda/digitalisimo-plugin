<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-fortune` (bdthemes-prime-slider/modules/fortune/widgets/fortune.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-fortune',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'style' => 'default',
		'thumbnail_size_size' => 'full',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_text' => 'yes',
		'show_button_text' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_fraction' => 'yes',
		'content_position' => 'center',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'swiper_effect' => 'slide',
		'creative_effect' => 'creative-1',
		'direction' => 'horizontal',
		'overlay_type' => 'none',
		'overlay_color_background' => 'classic',
		'overlay_color_color' => 'rgba(255, 255, 255, 0.3)',
		'blend_type' => 'multiply',
		'image_width' => array(
			'unit' => '%',
		),
		'button_type' => 'outline',
		'nav_arrows_icon' => '3',
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
						'__t' => 'Massive',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Vibrant',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Wallow',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.png',
					),
				) ),
		),
	),
	'styles' => array(
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-thumbs-slider' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_position' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-content' => 'justify-content: {{VALUE}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'overlay_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-ps-iamge-overlay:before',
		),
		'blend_type' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-ps-iamge-overlay:before' => 'mix-blend-mode: {{VALUE}};',
			),
		),
		'custom_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-image-wrap img',
		),
		'image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-main-slider' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'image_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-main-slider, {{WRAPPER}} .bdt-fortune-slider .bdt-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-title, {{WRAPPER}} .bdt-fortune-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-title:hover, {{WRAPPER}} .bdt-fortune-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-title .frist-word' => 'color: {{VALUE}};',
			),
		),
		'title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-title',
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-title',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-sub-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-sub-title',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-text',
		),
		'link_btn_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a' => 'color: {{VALUE}};',
			),
		),
		'link_btn_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a' => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:after' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
			),
		),
		'link_btn_border_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:after' => 'border-color: {{VALUE}};',
			),
		),
		'link_btn_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a, {{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a',
		),
		'link_btn_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:hover' => 'color: {{VALUE}};',
			),
		),
		'link_btn_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:hover' => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:hover:after' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
			),
		),
		'link_btn_hover_border_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:hover' => 'border-color: {{VALUE}}; background-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-fortune-slider .bdt-link-btn a:hover:after' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn i' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn',
		),
		'nav_arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn',
		),
		'border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-navigation-wrap' => 'grid-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-nav-btn',
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
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-pagination .swiper-pagination-bullet' => 'color: {{VALUE}}',
			),
		),
		'pagination_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-pagination .swiper-pagination-bullet:hover' => 'color: {{VALUE}}',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .bdt-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-fortune-slider .bdt-pagination .swiper-pagination-bullet::after' => 'background-color: {{VALUE}}',
			),
		),
		'pagination_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-fortune-slider .bdt-pagination .swiper-pagination-bullet',
		),
		'arrows_ncx_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-fortune-default .bdt-navigation-wrap' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .ps-fortune-reverse .bdt-navigation-wrap' => 'right: {{SIZE}}px;',
			),
		),
		'arrows_ncy_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-wrap' => 'top: {{SIZE}}px;',
			),
		),
		'fraction_ncx_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-fortune-default .bdt-pagination' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .ps-fortune-reverse .bdt-pagination' => 'right: {{SIZE}}px;',
			),
		),
		'fraction_ncy_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-fortune-slider .horizontal .bdt-pagination' => 'bottom: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-fortune-slider .vertical .bdt-pagination' => 'top: calc(100% - {{SIZE}}px);',
			),
		),
	),
);
