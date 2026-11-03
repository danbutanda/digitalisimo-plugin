<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-panorama` (bdthemes-prime-slider/modules/panorama/widgets/panorama.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-panorama',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'slider_height' => array(
			'size' => 280,
			'unit' => 'px',
		),
		'show_title' => '',
		'title_tag' => 'h3',
		'show_text' => '',
		'show_pagination' => 'yes',
		'slides_per_view' => array(
			'size' => 4,
		),
		'slides_per_view_tablet' => array(
			'size' => 3,
		),
		'slides_per_view_mobile' => array(
			'size' => 2,
		),
		'loop' => 'yes',
		'centered_slides' => 'yes',
		'grab_cursor' => 'yes',
		'autoplay' => '',
		'autoplay_delay' => 5000,
		'speed' => array(
			'size' => 600,
		),
		'panorama_depth' => array(
			'size' => 150,
		),
		'panorama_rotate' => array(
			'size' => 45,
		),
		'image_overlay' => '',
		'content_glassmorphism' => '',
		'content_backdrop_blur' => array(
			'unit' => 'px',
			'size' => 0,
		),
		'content_alignment' => 'flex-start',
		'pagination_offset' => array(
			'size' => -10,
		),
		'dynamic_main_bullets' => 3,
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'slide_image' => array(
					'url' => $placeholder,
				),
				'slide_title' => '',
				'slide_text' => '',
			),
			'rows' => array( array(
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
					'slide_title' => array(
						'__t' => 'Slide #1',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_text' => array(
						'__t' => 'Meta Content for Slider',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.svg',
					),
					'slide_title' => array(
						'__t' => 'Slide #2',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_text' => array(
						'__t' => 'Meta Content for Slider',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.svg',
					),
					'slide_title' => array(
						'__t' => 'Slide #3',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_text' => array(
						'__t' => 'Meta Content for Slider',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
					'slide_title' => array(
						'__t' => 'Slide #4',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_text' => array(
						'__t' => 'Meta Content for Slider',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
					'slide_title' => array(
						'__t' => 'Slide #5',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_text' => array(
						'__t' => 'Meta Content for Slider',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
					'slide_title' => array(
						'__t' => 'Slide #6',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_text' => array(
						'__t' => 'Meta Content for Slider',
						'd' => 'bdthemes-prime-slider',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-swiper' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'vertical_padding' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider' => 'padding: {{SIZE}}{{UNIT}} 0;',
			),
		),
		'wrapper_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider',
		),
		'wrapper_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'wrapper_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider',
		),
		'wrapper_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'wrapper_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider',
		),
		'image_overlay_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-slide-wrap::after',
		),
		'image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-slide-image, {{WRAPPER}} .bdt-panorama-slider .bdt-panorama-slide-wrap::after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'image_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-slide-image',
		),
		'image_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .swiper-slide',
		),
		'content_backdrop_blur' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content' => 'backdrop-filter: blur({{SIZE}}{{UNIT}}); -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}});',
			),
		),
		'content_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content',
		),
		'content_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content',
		),
		'content_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-content' => 'align-items: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-title' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-title',
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-text' => 'color: {{VALUE}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-panorama-slider .bdt-panorama-text',
		),
		'pagination_bullet_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .swiper-pagination-bullet' => 'background: {{VALUE}};',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .swiper-pagination-bullet-active' => 'background: {{VALUE}};',
			),
		),
		'pagination_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-panorama-slider .swiper-pagination' => 'bottom: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
