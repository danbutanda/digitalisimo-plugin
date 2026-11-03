<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-ring` (bdthemes-prime-slider/modules/ring/widgets/ring.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-ring',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'auto_velocity' => 'yes',
		'wrapper_height' => array(
			'unit' => 'px',
			'size' => 700,
		),
		'overlay_backdrop_blur' => array(
			'unit' => 'px',
			'size' => 26,
		),
	),
	'repeaters' => array(
		'ring_slides' => array(
			'fields' => array(
				'image' => array(
					'url' => $placeholder,
				),
				'overlay_label_author' => 'Author Name',
				'overlay_label_time' => '2h',
			),
			'rows' => array( array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				), array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				), array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				), array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				), array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				), array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				), array(
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.svg',
					),
					'overlay_label_author' => 'Author Name',
					'overlay_label_time' => '2h',
				) ),
		),
	),
	'styles' => array(
		'wrapper_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ring-slider' => '--bdt-ring-height: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; min-height: {{SIZE}}{{UNIT}};',
			),
		),
		'wrapper_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ring-slider' => '--bdt-ring-bg: {{VALUE}};',
			),
		),
		'wrapper_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ring-slider',
		),
		'wrapper_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ring-slider' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'overlay_backdrop_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}}' => '--bdt-ring-overlay-bg: {{VALUE}};',
			),
		),
		'overlay_backdrop_blur' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}}' => 'backdrop-filter: blur({{SIZE}}{{UNIT}}) saturate(0.7); -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}) saturate(0.7);',
			),
		),
		'overlay_avatar_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_avatar_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'overlay_avatar_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-avatar',
		),
		'overlay_author_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-label' => 'color: {{VALUE}};',
			),
		),
		'overlay_author_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-label',
		),
		'overlay_time_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-time' => 'color: {{VALUE}};',
			),
		),
		'overlay_time_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-time',
		),
		'overlay_reaction_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_reaction_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button .bdt-ring-svg-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_reaction_spacing' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-list' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_reaction_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button',
		),
		'overlay_reaction_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button',
		),
		'overlay_reaction_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'overlay_reaction_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button',
		),
		'overlay_reaction_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'overlay_reaction_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:hover, #bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:focus-visible',
		),
		'overlay_reaction_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:hover, #bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:focus-visible',
		),
		'overlay_reaction_hover_translate_y' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:hover, #bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:focus-visible' => '--bdt-ring-reaction-translate-y: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_reaction_hover_scale' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:hover, #bdt-ring-detail-overlay-{{ID}} .bdt-ring-reaction-button:focus-visible' => '--bdt-ring-reaction-scale: {{SIZE}};',
			),
		),
		'overlay_close_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_close_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close .bdt-ring-svg-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_close_offset_top' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close' => 'top: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_close_offset_right' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
		'overlay_close_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close' => 'color: {{VALUE}};',
			),
		),
		'overlay_close_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close',
		),
		'overlay_close_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close',
		),
		'overlay_close_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'overlay_close_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'overlay_close_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close:hover' => 'color: {{VALUE}};',
			),
		),
		'overlay_close_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close:hover',
		),
		'overlay_close_hover_scale' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'#bdt-ring-detail-overlay-{{ID}} .bdt-ring-detail-close:hover' => '--bdt-ring-close-scale: {{SIZE}};',
			),
		),
	),
);
