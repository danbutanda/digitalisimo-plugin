<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-tango` (bdthemes-prime-slider-lite/modules/tango/widgets/tango.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-tango',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'columns' => 3,
		'columns_tablet' => 3,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 40,
		),
		'item_gap_tablet' => array(
			'size' => 30,
		),
		'item_gap_mobile' => array(
			'size' => 0,
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'show_navigation_arrows' => 'yes',
		'navigation_center_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'hide_on_mobile' => 'yes',
		'thumbnail_size_size' => 'full',
		'skin' => 'carousel',
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
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'image_overlay_background' => 'gradient',
		'image_overlay_color' => '#000',
		'image_overlay_color_b' => '#00000000',
		'image_overlay_gradient_type' => 'linear',
		'image_overlay_gradient_angle' => array(
			'unit' => 'deg',
			'size' => 360,
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
						'__t' => 'Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Item One',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Item Two',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Item Three',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Item Four',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-item' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'slider_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-tango .swiper-pagination' => 'transform: translateX(-50%) translateY({{SIZE}}{{UNIT}});',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-content-wrap' => 'text-align: {{VALUE}};',
			),
		),
		'item_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-content-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'image_overlay' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-image-wrap::before',
		),
		'image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; clip-path: inset(10% 0 10% 0 round {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}});',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-title, {{WRAPPER}} .bdt-prime-slider-tango .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-title:hover, {{WRAPPER}} .bdt-prime-slider-tango .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-title, {{WRAPPER}} .bdt-prime-slider-tango .bdt-title a',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-subtitle' => 'color: {{VALUE}};',
			),
		),
		'sub_title_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-subtitle',
		),
		'sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-subtitle' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-subtitle',
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev' => 'color: {{VALUE}}',
			),
		),
		'arrows_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev',
		),
		'arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev',
		),
		'arrows_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev',
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_acx_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next' => 'right: {{SIZE}}px;',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev:hover' => 'color: {{VALUE}}',
			),
		),
		'arrows_background_color_hover' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev:hover',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-next:hover, {{WRAPPER}} .bdt-prime-slider-tango .bdt-navigation-arrows .bdt-navigation-prev:hover' => 'border-color: {{VALUE}};',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .swiper-pagination .swiper-pagination-bullet' => 'background: {{VALUE}}',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .swiper-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'background: {{VALUE}}',
			),
		),
		'pagination_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-tango .swiper-pagination .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
