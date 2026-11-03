<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-woocircle` (bdthemes-prime-slider-lite/modules/woocircle/widgets/woocircle.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-woocircle',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_excerpt' => 'yes',
		'show_price' => 'yes',
		'show_cart' => 'yes',
		'posts_source' => 'product',
		'posts_limit' => 3,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'modal_text__max_width' => array(
			'unit' => '%',
		),
	),
	'repeaters' => array(),
	'styles' => array(
		'slider_item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider' => 'height: {{SIZE}}vh;',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content' => 'text-align: {{VALUE}};',
			),
		),
		'active_circle_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .deco--circle' => 'background: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title-preview' => 'color: {{VALUE}};',
			),
		),
		'title_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title-preview',
		),
		'title_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title-preview',
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title-preview',
		),
		'title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title-preview' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'old_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price del span' => 'color: {{VALUE}};',
			),
		),
		'old_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price del > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'old_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price del span',
		),
		'sale_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price ins, {{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price > span' => 'color: {{VALUE}};',
			),
		),
		'sale_price_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}}  .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price ins' => 'background: {{VALUE}};',
			),
		),
		'sale_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price ins, {{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sale_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price ins, {{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price > span',
		),
		'sale_price_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-slide-content .bdt-elastic-price .price' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'open_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--open' => 'color: {{VALUE}};',
			),
		),
		'open_button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--open',
		),
		'open_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--open',
		),
		'open_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--open' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'open_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--open' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'open_button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--open' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'open_button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--open',
		),
		'open_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--open',
		),
		'open_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--open:focus,
						 {{WRAPPER}} .bdt-elastic-action--open:hover' => 'color: {{VALUE}};',
			),
		),
		'open_button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--open:focus, {{WRAPPER}} .bdt-elastic-action--open:hover',
		),
		'open_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--open:focus, {{WRAPPER}} .bdt-elastic-action--open:hover' => 'border-color: {{VALUE}};',
			),
		),
		'modal_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-modal-wrap',
		),
		'modal_text__max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-modal-details' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'modal_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title--main' => 'color: {{VALUE}};',
			),
		),
		'modal_title_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title--main',
		),
		'modal_title_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title--main',
		),
		'modal_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title--main',
		),
		'modal_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-title--main' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'modal_old_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price del span' => 'color: {{VALUE}};',
			),
		),
		'modal_old_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price del > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'modal_old_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price del span',
		),
		'modal_sale_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price ins, {{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price > span' => 'color: {{VALUE}};',
			),
		),
		'modal_sale_price_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price ins' => 'background: {{VALUE}};',
			),
		),
		'modal_sale_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price ins, {{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'modal_sale_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price ins, {{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price > span',
		),
		'modal_sale_price_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-price.bdt-elastic-price--large .price' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-description' => 'color: {{VALUE}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-description',
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'close_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--close' => 'color: {{VALUE}};',
			),
		),
		'close_button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--close',
		),
		'close_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--close',
		),
		'close_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'close_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'close_button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--close' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'close_button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--close',
		),
		'close_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--close',
		),
		'close_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--close:hover' => 'color: {{VALUE}};',
			),
		),
		'close_button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-action--close:hover',
		),
		'close_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-action--close:hover' => 'border-color: {{VALUE}};',
			),
		),
		'button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button' => 'background-color: {{VALUE}};',
			),
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button',
		),
		'button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .cart' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button',
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button',
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button:hover' => 'color: {{VALUE}};',
			),
		),
		'button_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button::before' => 'background-color: {{VALUE}};',
			),
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .button:hover' => 'border-color: {{VALUE}};',
			),
		),
		'quantity_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text' => 'color: {{VALUE}};',
			),
		),
		'quantity_button_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text' => 'background-color: {{VALUE}};',
			),
		),
		'quantity_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text',
		),
		'quantity_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'quantity_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'quantity_button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text',
		),
		'quantity_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-elastic-slider .bdt-elastic-button--buy .input-text',
		),
		'arrows_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .navbutton .navbutton__line' => 'stroke: {{VALUE}}',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .navbutton:hover .navbutton__line' => 'stroke: {{VALUE}}',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-elastic-slider .navbutton' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
