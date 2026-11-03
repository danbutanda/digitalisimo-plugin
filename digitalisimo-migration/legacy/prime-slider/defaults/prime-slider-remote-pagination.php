<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-remote-pagination` (bdthemes-prime-slider/modules/remote-pagination/widgets/remote-pagination.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-remote-pagination',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'loop_status' => 'yes',
		'pagination_type' => 'manual',
		'thumbnail_size_size' => 'medium',
	),
	'repeaters' => array(
		'pagination_list' => array(
			'fields' => array(
				'pagination_number' => array(
					'__t' => 'Adam Smith',
					'd' => 'bdthemes-prime-slider',
				),
			),
			'rows' => array( array(
					'pagination_number' => 1,
				), array(
					'pagination_number' => 2,
				), array(
					'pagination_number' => 3,
				), array(
					'pagination_number' => 4,
				), array(
					'pagination_number' => 5,
				) ),
		),
	),
	'styles' => array(
		'pagination_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagination-wrapper' => 'justify-content: {{VALUE}};',
			),
		),
		'pagination_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagination-wrapper' => 'grid-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item',
		),
		'pagination_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item',
		),
		'pagination_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item' => 'color: {{VALUE}}',
			),
		),
		'pagination_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item',
		),
		'pagination_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-pagination-wrapper' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item',
		),
		'active_pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active' => 'color: {{VALUE}}',
			),
		),
		'active_pagination_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item.bdt-active',
		),
		'active_pagination_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'active_pagination_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'active_pagination_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.bdt-active' => 'border-color: {{VALUE}};',
			),
		),
		'active_pagination_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-item.bdt-active',
		),
	),
);
