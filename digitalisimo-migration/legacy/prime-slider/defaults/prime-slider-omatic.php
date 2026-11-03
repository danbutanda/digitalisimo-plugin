<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-omatic` (bdthemes-prime-slider-lite/modules/omatic/widgets/omatic.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-omatic',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_text' => 'yes',
		'show_navigation_arrows' => 'yes',
		'thumbnail_size_size' => 'full',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'swiper_effect' => 'cube',
		'creative_effect' => 'creative-1',
		'overlay_type' => 'none',
		'overlay_color_background' => 'classic',
		'blend_type' => 'multiply',
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'nav_arrows_icon' => '4',
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
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Ps Item One',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Ps Item Two',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Ps Item Three',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.png',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-img-wrap' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_vertical_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-slide-content' => 'top: {{SIZE}}%;',
			),
		),
		'overlay_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-img-wrap::before',
		),
		'blend_type' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-img-wrap::before' => 'mix-blend-mode: {{VALUE}};',
			),
		),
		'glassmorphism_blur_level' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-img-wrap::before' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
			),
		),
		'slider_image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-img-wrap',
		),
		'slider_image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-img-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slider_image_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .swiper-carousel' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-title, {{WRAPPER}} .bdt-omatic-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-title:hover, {{WRAPPER}} .bdt-omatic-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-title .frist-word' => 'color: {{VALUE}};',
			),
		),
		'title_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-title',
		),
		'title_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-title',
		),
		'title_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-title',
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-title',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title',
		),
		'sub_title_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title',
		),
		'sub_title_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title',
		),
		'sub_title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title',
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title',
		),
		'sub_title_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-title-wrap' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-line' => 'background-color: {{VALUE}};',
			),
		),
		'sub_title_line_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-line' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_line_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-line' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_line_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-sub-line' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-desc' => 'color: {{VALUE}};',
			),
		),
		'text_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-desc, {{WRAPPER}} .bdt-omatic-slider .bdt-desc > *',
		),
		'text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-inner-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-desc',
		),
		'text_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-line' => 'background-color: {{VALUE}};',
			),
		),
		'text_line_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-line' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'text_line_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-line' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'text_line_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-line' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'text_line_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-line' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button i' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button',
		),
		'nav_arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button',
		),
		'border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button:hover i' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button:hover',
		),
		'nav_arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-omatic-slider .bdt-navigation-wrap .bdt-nav-button:hover',
		),
	),
);
