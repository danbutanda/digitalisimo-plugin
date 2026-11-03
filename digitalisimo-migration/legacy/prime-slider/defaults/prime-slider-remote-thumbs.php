<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-remote-thumbs` (bdthemes-prime-slider/modules/remote-thumbs/widgets/remote-thumbs.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-remote-thumbs',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'loop_status' => 'yes',
		'thumbs_type' => 'manual',
		'thumbnail_size_size' => 'medium',
	),
	'repeaters' => array(
		'thumbs_list' => array(
			'fields' => array(
				'image' => array(
					'url' => $placeholder,
				),
			),
			'rows' => array( array(
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'image' => array(
						'url' => $placeholder,
					),
				) ),
		),
	),
	'styles' => array(
		'thumbs_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-thumbs-wrapper' => 'justify-content: {{VALUE}};',
			),
		),
		'thumbs_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-thumbs-wrapper' => 'grid-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'img_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item img',
		),
		'img_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'img_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'img_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item img' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-thumbs-wrapper' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'img_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item img' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'img_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item img',
		),
		'img_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item img',
		),
		'active_img_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active img' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'active_img_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active img' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'active_img_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active img' => 'border-color: {{VALUE}};',
			),
		),
		'active_img_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item.bdt-active img',
		),
		'active_img_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item.bdt-active img',
		),
	),
);
