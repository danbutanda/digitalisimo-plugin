<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-elysium` (bdthemes-prime-slider-lite/modules/elysium/widgets/elysium.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-elysium',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_text' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'thumbnail_size_size' => 'full',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'slides_per_view' => array(
			'size' => 1.8,
		),
		'slides_per_view_tablet' => array(
			'size' => 1.5,
		),
		'space_between' => array(
			'size' => 200,
		),
		'space_between_tablet' => array(
			'size' => 100,
		),
		'navigation_previous_text' => array(
			'__t' => 'Prev',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'navigation_next_text' => array(
			'__t' => 'Next',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'text_reveal_enable' => 'off',
		'text_reveal_stagger' => 30,
		'text_reveal_text_delay' => 280,
		'text_reveal_translate_y' => 10,
		'text_reveal_blur' => 6,
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
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
					'title' => array(
						'__t' => 'Item One',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'text' => array(
						'__t' => 'Prime Slider Addons for elementor!',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Item Two',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'text' => array(
						'__t' => 'Prime Slider Addons for elementor!',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Item Three',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'text' => array(
						'__t' => 'Prime Slider Addons for elementor!',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Item Four',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'text' => array(
						'__t' => 'Prime Slider Addons for elementor!',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'slider_image_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-image-wrap .bdt-img' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium',
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'overlay_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-item.swiper-slide-active .bdt-image-wrap::before',
		),
		'inactive_slide_blur' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-item:not(.swiper-slide-active) .bdt-image-wrap .bdt-img' => 'filter: blur({{SIZE}}{{UNIT}});',
			),
		),
		'inactive_slide_brightness' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-item:not(.swiper-slide-active) .bdt-image-wrap' => 'filter: brightness( calc({{SIZE}} / 100) ) saturate(.92);',
			),
		),
		'inactive_slide_scale' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-item:not(.swiper-slide-active) .bdt-image-wrap' => 'transform: scale( calc({{SIZE}} / 100) );',
			),
		),
		'image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-image-wrap',
		),
		'image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-image-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'image_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-image-wrap',
		),
		'custom_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-image-wrap .bdt-img',
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-item.swiper-slide-active .bdt-title-wrap .bdt-title, 
					{{WRAPPER}}  .bdt-prime-slider-elysium .bdt-item.swiper-slide-active .bdt-title-wrap .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-item.swiper-slide-active .bdt-title-wrap .bdt-title:hover, {{WRAPPER}} .bdt-prime-slider-elysium .bdt-item.swiper-slide-active .bdt-title-wrap .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-title-wrap .bdt-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-title-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-title-wrap .bdt-title',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-title-wrap .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-title-wrap .bdt-title',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-text',
		),
		'text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-text',
		),
		'pagi_nav_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-navigation-pagi-wrap .bdt-navigation-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'navigation_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-navigation-btn .bdt-link, 
					 {{WRAPPER}} .bdt-prime-slider-elysium .bdt-navigation-btn .bdt-link--arrowed g' => 'color: {{VALUE}}',
			),
		),
		'navigation_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-navigation-btn .bdt-link:hover, 
					 {{WRAPPER}}  .bdt-prime-slider-elysium .bdt-navigation-btn .bdt-link--arrowed:hover g' => 'color: {{VALUE}}',
			),
		),
		'navigation_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-navigation-btn .bdt-link',
		),
		'pagination_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-navigation-pagi-wrap .bdt-pagination' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet' => 'background: {{VALUE}}',
			),
		),
		'pagination_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet',
		),
		'pagination_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'background: {{VALUE}}',
			),
		),
		'pagination_width_active' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_height_active' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-elysium .bdt-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_border_active' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .swiper-pagination-bullet.swiper-pagination-bullet-active',
		),
	),
);
