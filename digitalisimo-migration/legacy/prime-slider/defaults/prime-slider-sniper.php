<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-sniper` (bdthemes-prime-slider-lite/modules/sniper/widgets/sniper.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-sniper',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'thumbnail_size_size' => 'full',
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
		'swiper_effect' => 'slide',
		'creative_effect' => 'creative-1',
		'image_background_background' => 'gradient',
		'image_background_color' => '#111019',
		'image_background_color_b' => '#00000000',
		'image_background_gradient_type' => 'linear',
		'image_background_gradient_angle' => array(
			'unit' => 'deg',
			'size' => 45,
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
						'__t' => 'Sub Title 1',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sniper Slide 1',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Sub Title 2',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sniper Slide 2',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Sub Title 3',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sniper Slide 3',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Sub Title 4',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sniper Slide 4',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Sub Title 5',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sniper Slide 5',
						'd' => 'bdthemes-prime-slider-lite',
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
				'{{WRAPPER}} .bdt-sniper-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider' => 'background-color: {{VALUE}};',
			),
		),
		'image_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-sniper-slider .bdt-ps-iamge-overlay::before',
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-title, {{WRAPPER}} .bdt-sniper-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-title:hover, {{WRAPPER}} .bdt-sniper-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .swiper-slide-active .bdt-title, {{WRAPPER}} .bdt-sniper-slider .swiper-slide-active .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-title .frist-word' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .swiper-slide-active .bdt-title .frist-word' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-sniper-slider .bdt-title',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-sniper-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-sniper-slider .bdt-title',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-sniper-slider .bdt-sub-title',
		),
		'prime_slider_sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-sub-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'grid_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-grid-line span' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-navigation-wrap .bdt-nav-btn' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-sniper-slider .bdt-navigation-wrap .bdt-nav-btn::before' => 'background: {{VALUE}}',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-navigation-wrap .bdt-nav-btn:hover' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-sniper-slider .bdt-navigation-wrap .bdt-nav-btn:hover::before' => 'background: {{VALUE}}',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-pagination-wrap .bdt-pagination' => 'color: {{VALUE}}',
			),
		),
		'pagination_total_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-pagination-wrap .swiper-pagination-total' => 'color: {{VALUE}}',
			),
		),
		'pagination_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-sniper-slider .bdt-pagination-wrap .bdt-pagination',
		),
		'scrollbar_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-pagination-wrap .swiper-scrollbar' => 'background: {{VALUE}}',
			),
		),
		'scrollbar_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-sniper-slider .bdt-pagination-wrap .swiper-scrollbar::before' => 'background: {{VALUE}}',
			),
		),
	),
);
