<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-woohotspot` (bdthemes-prime-slider/modules/woohotspot/widgets/woohotspot.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-woohotspot',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'image' => array(
			'url' => $placeholder,
		),
		'two_image' => array(
			'url' => $placeholder,
		),
		'three_image' => array(
			'url' => $placeholder,
		),
		'marker_tooltip_animation' => 'shift-toward',
		'marker_tooltip_x_offset' => array(
			'size' => 0,
		),
		'marker_tooltip_y_offset' => array(
			'size' => 0,
		),
		'thumbnail_size' => 'full',
		'show_title' => 'yes',
		'title_tag' => 'h3',
		'show_price' => 'yes',
		'show_text' => 'yes',
		'show_button' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'mousewheel' => 'yes',
		'speed' => array(
			'size' => 900,
		),
		'transition' => 'slide',
		'creative_effect' => 'creative-1',
		'space' => array(
			'size' => 100,
			'unit' => '%',
		),
		'space_tablet' => array(
			'unit' => '%',
		),
		'space_mobile' => array(
			'unit' => '%',
		),
		'opacity' => array(
			'size' => 1,
		),
		'marker_opacity' => array(
			'size' => 1,
		),
		'content_alignment' => 'left',
		'arrow_direction' => 'right',
		'nav_arrows_icon' => '8',
		'pagination_direction' => 'left',
	),
	'repeaters' => array(
		'markers' => array(
			'fields' => array(
				'select_type' => 'icon',
				'text' => 'Marker',
				'image' => array(
					'url' => $placeholder,
				),
				'marker_invisible_height' => array(
					'size' => 20,
				),
				'marker_invisible_height_tablet' => array(
					'size' => 20,
				),
				'marker_invisible_height_mobile' => array(
					'size' => 20,
				),
				'marker_invisible_width' => array(
					'size' => 20,
				),
				'marker_invisible_width_tablet' => array(
					'size' => 20,
				),
				'marker_invisible_width_mobile' => array(
					'size' => 20,
				),
				'marker_tooltip_placement' => 'top',
				'product_title' => array(
					'__t' => 'Title Here',
					'd' => 'bdthemes-prime-slider',
				),
				'product_text' => array(
					'__t' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
					'd' => 'bdthemes-prime-slider',
				),
				'product_price' => array(
					'__t' => '$124.00',
					'd' => 'bdthemes-prime-slider',
				),
				'product_link_text' => array(
					'__t' => 'Buy Now',
					'd' => 'bdthemes-prime-slider',
				),
				'product_link' => array(
					'url' => '#',
				),
			),
			'rows' => array( array(
					'product_title' => array(
						'__t' => 'Product 01',
						'd' => 'bdthemes-prime-slider',
					),
					'marker_x_position' => array(
						'size' => 50,
						'unit' => '%',
					),
					'marker_y_position' => array(
						'size' => 75,
						'unit' => '%',
					),
				), array(
					'product_title' => array(
						'__t' => 'Product 02',
						'd' => 'bdthemes-prime-slider',
					),
					'marker_x_position' => array(
						'size' => 20,
						'unit' => '%',
					),
					'marker_y_position' => array(
						'size' => 30,
						'unit' => '%',
					),
				), array(
					'product_title' => array(
						'__t' => 'Product 03',
						'd' => 'bdthemes-prime-slider',
					),
					'marker_x_position' => array(
						'size' => 65,
						'unit' => '%',
					),
					'marker_y_position' => array(
						'size' => 20,
						'unit' => '%',
					),
				) ),
		),
		'two_markers' => array(
			'fields' => array(
				'two_select_type' => 'icon',
				'two_text' => 'Marker',
				'two_image' => array(
					'url' => $placeholder,
				),
				'two_marker_invisible_height' => array(
					'size' => 20,
				),
				'two_marker_invisible_height_tablet' => array(
					'size' => 20,
				),
				'two_marker_invisible_height_mobile' => array(
					'size' => 20,
				),
				'two_marker_invisible_width' => array(
					'size' => 20,
				),
				'two_marker_invisible_width_tablet' => array(
					'size' => 20,
				),
				'two_marker_invisible_width_mobile' => array(
					'size' => 20,
				),
				'two_marker_tooltip_placement' => 'top',
				'two_product_title' => array(
					'__t' => 'Title Here',
					'd' => 'bdthemes-prime-slider',
				),
				'two_product_text' => array(
					'__t' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
					'd' => 'bdthemes-prime-slider',
				),
				'two_product_price' => array(
					'__t' => '$124.00',
					'd' => 'bdthemes-prime-slider',
				),
				'two_product_link_text' => array(
					'__t' => 'Buy Now',
					'd' => 'bdthemes-prime-slider',
				),
				'two_product_link' => array(
					'url' => '#',
				),
			),
			'rows' => array( array(
					'two_product_title' => array(
						'__t' => 'Product 01',
						'd' => 'bdthemes-prime-slider',
					),
					'two_marker_x_position' => array(
						'size' => 20,
						'unit' => '%',
					),
					'two_marker_y_position' => array(
						'size' => 20,
						'unit' => '%',
					),
				), array(
					'two_product_title' => array(
						'__t' => 'Product 02',
						'd' => 'bdthemes-prime-slider',
					),
					'two_marker_x_position' => array(
						'size' => 65,
						'unit' => '%',
					),
					'two_marker_y_position' => array(
						'size' => 29,
						'unit' => '%',
					),
				), array(
					'two_product_title' => array(
						'__t' => 'Product 03',
						'd' => 'bdthemes-prime-slider',
					),
					'two_marker_x_position' => array(
						'size' => 40,
						'unit' => '%',
					),
					'two_marker_y_position' => array(
						'size' => 80,
						'unit' => '%',
					),
				) ),
		),
		'three_markers' => array(
			'fields' => array(
				'three_select_type' => 'icon',
				'three_text' => 'Marker',
				'three_image' => array(
					'url' => $placeholder,
				),
				'three_marker_invisible_height' => array(
					'size' => 20,
				),
				'three_marker_invisible_height_tablet' => array(
					'size' => 20,
				),
				'three_marker_invisible_height_mobile' => array(
					'size' => 20,
				),
				'three_marker_invisible_width' => array(
					'size' => 20,
				),
				'three_marker_invisible_width_tablet' => array(
					'size' => 20,
				),
				'three_marker_invisible_width_mobile' => array(
					'size' => 20,
				),
				'three_marker_tooltip_placement' => 'top',
				'three_product_title' => array(
					'__t' => 'Title Here',
					'd' => 'bdthemes-prime-slider',
				),
				'three_product_text' => array(
					'__t' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
					'd' => 'bdthemes-prime-slider',
				),
				'three_product_price' => array(
					'__t' => '$124.00',
					'd' => 'bdthemes-prime-slider',
				),
				'three_product_link_text' => array(
					'__t' => 'Buy Now',
					'd' => 'bdthemes-prime-slider',
				),
				'three_product_link' => array(
					'url' => '#',
				),
			),
			'rows' => array( array(
					'three_product_title' => array(
						'__t' => 'Product 01',
						'd' => 'bdthemes-prime-slider',
					),
					'three_marker_x_position' => array(
						'size' => 50,
						'unit' => '%',
					),
					'three_marker_y_position' => array(
						'size' => 50,
						'unit' => '%',
					),
				), array(
					'three_product_title' => array(
						'__t' => 'Product 02',
						'd' => 'bdthemes-prime-slider',
					),
					'three_marker_x_position' => array(
						'size' => 30,
						'unit' => '%',
					),
					'three_marker_y_position' => array(
						'size' => 30,
						'unit' => '%',
					),
				), array(
					'three_product_title' => array(
						'__t' => 'Product 03',
						'd' => 'bdthemes-prime-slider',
					),
					'three_marker_x_position' => array(
						'size' => 80,
						'unit' => '%',
					),
					'three_marker_y_position' => array(
						'size' => 20,
						'unit' => '%',
					),
				) ),
		),
	),
	'styles' => array(
		'space' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap > img' => 'opacity: {{SIZE}};',
			),
		),
		'image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-woohotspot-wrap > img',
		),
		'image_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap > img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'image_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-woohotspot-wrap > img',
		),
		'marker_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot svg' => 'fill: {{VALUE}};',
			),
		),
		'marker_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot',
		),
		'marker_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot',
		),
		'marker_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-woohotspot-animated .bdt-woohotspot:before, {{WRAPPER}} .bdt-woohotspot-animated .bdt-woohotspot:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'marker_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'marker_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot > img' => 'width: calc({{SIZE}}{{UNIT}} - 12px); height: auto;',
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot > i' => 'font-size: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'marker_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot' => 'opacity: {{SIZE}};',
			),
		),
		'marker_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot',
		),
		'marker_pulse_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-animated .bdt-woohotspot:before, {{WRAPPER}} .bdt-woohotspot-animated .bdt-woohotspot:after' => 'border-color: {{VALUE}};',
			),
		),
		'marker_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'marker_hover_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot:hover',
		),
		'marker_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-wrap .bdt-woohotspot:hover' => 'border-color: {{VALUE}};',
			),
		),
		'content_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-content',
		),
		'content_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-content',
		),
		'content_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-content',
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-content' => 'text-align: {{VALUE}}',
			),
		),
		'marker_tooltip_arrow_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .tippy-popper[x-placement^=left] .tippy-arrow' => 'border-left-color: {{VALUE}}',
				'{{WRAPPER}} .tippy-popper[x-placement^=right] .tippy-arrow' => 'border-right-color: {{VALUE}}',
				'{{WRAPPER}} .tippy-popper[x-placement^=top] .tippy-arrow' => 'border-top-color: {{VALUE}}',
				'{{WRAPPER}} .tippy-popper[x-placement^=bottom] .tippy-arrow' => 'border-bottom-color: {{VALUE}}',
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .tippy-arrow' => 'color: {{VALUE}}',
			),
		),
		'marker_tooltip_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"]' => 'width: {{SIZE}}{{UNIT}} !important; max-width: {{SIZE}}{{UNIT}} !important;',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-title' => 'color: {{VALUE}};',
			),
		),
		'title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-title',
		),
		'title_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-title',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-text' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-text',
		),
		'price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-price' => 'color: {{VALUE}};',
			),
		),
		'price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-price' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-price',
		),
		'readmore_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link' => 'color: {{VALUE}};',
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link svg' => 'fill: {{VALUE}};',
			),
		),
		'readmore_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link',
		),
		'readmore_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link',
		),
		'readmore_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'readmore_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'readmore_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'readmore_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link',
		),
		'readmore_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link',
		),
		'readmore_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link:hover' => 'color: {{VALUE}};',
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'readmore_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link:hover',
		),
		'readmore_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.tippy-box[data-theme="bdt-tippy-woohotspot-{{ID}}"] .bdt-woohotspot-link:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrow_direction' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows' => '{{VALUE}};',
			),
		),
		'arrows_font_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woohotspot .bdt-navigation-arrows' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav',
		),
		'arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav',
		),
		'arrows_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav:hover' => 'color: {{VALUE}};',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-woohotspot-slidenav::before' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav:hover' => 'background-color: {{VALUE}};',
			),
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-woohotspot-slidenav:hover' => 'border-color: {{VALUE}};',
			),
		),
		'pagination_direction' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woohotspot .bdt-navigation-dots .swiper-pagination' => '{{VALUE}};',
			),
		),
		'pagination_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-dots .swiper-pagination .swiper-pagination-bullet' => 'background-color: {{VALUE}}',
			),
		),
		'pagination_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-dots .swiper-pagination .swiper-pagination-bullet:hover' => 'background-color: {{VALUE}}',
			),
		),
		'pagination_active_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .swiper-pagination-bullet::before' => 'background-color: {{VALUE}}',
			),
		),
		'pagination_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-dots .swiper-pagination .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-dots .swiper-pagination .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-dots .swiper-pagination .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'pagination_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-dots .swiper-pagination' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_horizontal_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woohotspot .bdt-navigation-dots .swiper-pagination' => 'margin: 0 {{SIZE}}{{UNIT}};',
			),
		),
	),
);
