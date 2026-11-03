<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-cards-stack` (bdthemes-prime-slider/modules/cards-stack/widgets/cards-stack.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-cards-stack',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'thumbnail_size_size' => 'full',
		'stack_direction' => 'horizontal',
		'transition_speed' => 400,
		'touch_ratio' => array(
			'size' => 1.5,
		),
		'grab_cursor' => 'yes',
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(),
			'rows' => array( array(
					'image_back' => array(
						'url' => $placeholder,
					),
					'image_front' => array(
						'url' => $placeholder,
					),
				), array(
					'image_back' => array(
						'url' => $placeholder,
					),
					'image_front' => array(
						'url' => $placeholder,
					),
				), array(
					'image_back' => array(
						'url' => $placeholder,
					),
					'image_front' => array(
						'url' => $placeholder,
					),
				) ),
		),
	),
	'styles' => array(
		'wrapper_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack-slider' => 'min-height: {{SIZE}}{{UNIT}};',
			),
		),
		'container_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack__container' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'wrapper_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-cards-stack-slider',
		),
		'wrapper_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-cards-stack-slider',
		),
		'wrapper_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack-slider' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			),
		),
		'wrapper_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack-slider' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'wrapper_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack-slider' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'wrapper_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-cards-stack-slider',
		),
		'slide_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide .cards-stack-front img, {{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide .cards-stack-back img',
		),
		'slide_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide',
		),
		'slide_image_object_fit' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide img' => 'object-fit: {{VALUE}};',
			),
		),
		'slide_image_css' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-cards-stack__swiper .swiper-slide img',
		),
	),
);
