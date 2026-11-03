<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-super-flow` (bdthemes-prime-slider/modules/super-flow/widgets/super-flow.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-super-flow',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_tags' => 'h3',
		'thumbnail_size_size' => 'full',
		'show_pagination' => 'yes',
		'autoplay' => 'yes',
		'autoplay_delay' => 3000,
		'loop' => 'yes',
		'grab_cursor' => 'yes',
		'speed' => array(
			'size' => 1000,
		),
		'observer' => '',
		'fragments' => array(
			'size' => 3,
		),
		'fragment_border_width' => array(
			'size' => 1,
		),
		'fragment_blur' => 'no',
		'fragment_blur_level' => array(
			'size' => 8,
		),
		'scale_duration' => array(
			'size' => 4000,
		),
		'content_offset' => array(
			'size' => 0,
		),
		'content_scale' => array(
			'size' => 1.2,
		),
		'main_image_scale' => array(
			'size' => 1.1,
		),
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'slide_title' => array(
					'__t' => 'Slide Title',
					'd' => 'bdthemes-prime-slider',
				),
				'title_link' => array(
					'url' => '',
					'is_external' => false,
					'nofollow' => false,
				),
				'image' => array(
					'url' => $placeholder,
				),
			),
			'rows' => array( array(
					'slide_title' => array(
						'__t' => 'Super Flow',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_title' => array(
						'__t' => 'Static Rush',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_title' => array(
						'__t' => 'Still Motion',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_title' => array(
						'__t' => 'Vertical Tension',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'slide_title' => array(
						'__t' => 'Dynamic Twist',
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
				'{{WRAPPER}} .bdt-prime-slider-super-flow' => '--ps-super-flow-height: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-super-flow',
		),
		'slide_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-super-flow',
		),
		'slide_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-super-flow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-super-flow' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-super-flow',
		),
		'slide_image_object_fit' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-super-flow .swiper-slide img' => 'object-fit: {{VALUE}};',
			),
		),
		'slide_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-super-flow:hover',
		),
		'slide_shadow_hover' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-super-flow:hover',
		),
		'slide_border_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-super-flow:hover' => 'border-color: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .super-flow-content' => 'color: {{VALUE}};',
				'{{WRAPPER}} .super-flow-content a' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .super-flow-content, {{WRAPPER}} .super-flow-content a',
		),
		'pagination_wrapper_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-super-flow-swiper.swiper-horizontal > .swiper-pagination-bullets, {{WRAPPER}} .bdt-super-flow-swiper .swiper-pagination-bullets.swiper-pagination-horizontal',
		),
		'pagination_wrapper_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-super-flow-swiper.swiper-horizontal > .swiper-pagination-bullets, {{WRAPPER}} .bdt-super-flow-swiper .swiper-pagination-bullets.swiper-pagination-horizontal',
		),
		'pagination_wrapper_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-super-flow-swiper.swiper-horizontal > .swiper-pagination-bullets, {{WRAPPER}} .bdt-super-flow-swiper .swiper-pagination-bullets.swiper-pagination-horizontal' => 'border-radius: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
			),
		),
		'pagination_wrapper_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-super-flow-swiper.swiper-horizontal > .swiper-pagination-bullets, {{WRAPPER}} .bdt-super-flow-swiper .swiper-pagination-bullets.swiper-pagination-horizontal' => 'padding: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
			),
		),
		'pagination_wrapper_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-super-flow-swiper.swiper-horizontal > .swiper-pagination-bullets, {{WRAPPER}} .bdt-super-flow-swiper .swiper-pagination-bullets.swiper-pagination-horizontal' => 'margin: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-super-flow-swiper' => '--swiper-pagination-bullet-inactive-color: {{VALUE}}; --swiper-pagination-bullet-inactive-opacity: 1;',
				'{{WRAPPER}} .swiper-pagination-bullet:not(.swiper-pagination-bullet-active)' => 'background-color: {{VALUE}}; opacity: 1;',
			),
		),
		'pagination_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .swiper-pagination-bullet' => 'width: {{SIZE}}px;',
			),
		),
		'pagination_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .swiper-pagination-bullet' => 'height: {{SIZE}}px;',
			),
		),
		'pagination_border_radius' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .swiper-pagination-bullet' => 'border-radius: {{SIZE}}px;',
			),
		),
		'pagination_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .swiper-pagination-bullet' => 'margin-left: {{SIZE}}px;',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'background-color: {{VALUE}};',
			),
		),
	),
);
