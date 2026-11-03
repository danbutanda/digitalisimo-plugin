<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-pandora` (bdthemes-prime-slider/modules/pandora/widgets/pandora.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-pandora',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'item_hover_event' => 'mouseover',
		'hover_active_item' => '1',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
	),
	'repeaters' => array(
		'items' => array(
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
						'__t' => '01',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Amalthea',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => '02',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Miranda',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => '03',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Titania',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => '04',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Enceladus',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => '05',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Bebhion',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-item-wrap' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-item-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-img' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-img' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-img' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-img' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'wrapper_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora' => 'background-color: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-title' => 'color: {{VALUE}};',
			),
		),
		'title_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-title:after' => '-webkit-text-fill-color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-pandora .bdt-title',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-pandora .bdt-title',
		),
		'title_x_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-title' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-pandora .bdt-title:after' => 'left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-pandora .bdt-item.active .bdt-title::after' => 'width: calc(100% - calc({{SIZE}}{{UNIT}} * 2));',
				'{{WRAPPER}} .bdt-ps-pandora .bdt-line' => 'margin-left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-pandora .bdt-item.active .bdt-subtitle' => 'transform: translateX(calc(calc({{SIZE}}{{UNIT}} * 2) - 5px));',
			),
		),
		'title_y_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-title' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-pandora .bdt-title:after' => 'bottom: {{SIZE}}{{UNIT}}; height: calc(100% - calc({{SIZE}}{{UNIT}} * 2));',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-subtitle' => 'color: {{VALUE}};',
			),
		),
		'sub_title_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-item.active .bdt-subtitle' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-pandora .bdt-subtitle',
		),
		'sub_title_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-subtitle' => 'opacity: {{SIZE}};',
			),
		),
		'line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-line' => 'background-color: {{VALUE}};',
			),
		),
		'line_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-line' => 'opacity: {{SIZE}};',
			),
		),
		'line_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-line' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'line_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-pandora .bdt-line' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
