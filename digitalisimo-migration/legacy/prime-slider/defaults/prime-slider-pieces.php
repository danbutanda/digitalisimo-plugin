<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-pieces` (bdthemes-prime-slider/modules/pieces/widgets/pieces.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-pieces',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'thumbnail_size_size' => 'full',
		'button_previous_text' => array(
			'__t' => 'Prev',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'button_next_text' => array(
			'__t' => 'Next',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'title_font_size' => array(
			'size' => 50,
			'unit' => 'px',
		),
		'title_font_size_tablet' => array(
			'size' => 30,
			'unit' => 'px',
		),
		'number_font_size' => array(
			'size' => 50,
			'unit' => 'px',
		),
		'number_font_size_tablet' => array(
			'size' => 30,
			'unit' => 'px',
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
					'title' => array(
						'__t' => 'Pastiche Skin',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.png',
					),
				), array(
					'title' => array(
						'__t' => 'Enfilade Trees',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.png',
					),
				), array(
					'title' => array(
						'__t' => 'Vernacular Blue',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.png',
					),
				) ),
		),
	),
	'styles' => array(
		'image_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__image' => 'max-height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__canvas' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-pieces-slider' => 'min-height: {{SIZE}}{{UNIT}};',
			),
		),
		'image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__image' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'font_family' => array(
			'type' => 'font',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} canvas' => 'font-family: {{VALUE}}',
			),
		),
		'number_font_family' => array(
			'type' => 'font',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} canvas' => 'font-family: {{VALUE}}',
			),
		),
		'navigation_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button' => 'color: {{VALUE}}',
			),
		),
		'navigation_button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button',
		),
		'navigation_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button',
		),
		'navigation_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button',
		),
		'navigation_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button',
		),
		'navigation_butoon_text_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button:hover' => 'color: {{VALUE}}',
			),
		),
		'navigation_button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button:hover',
		),
		'navigation_button_border_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button:hover' => 'border-color: {{VALUE}};',
			),
		),
		'navigation_button_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pieces-slider .pieces-slider__button:hover',
		),
	),
);
