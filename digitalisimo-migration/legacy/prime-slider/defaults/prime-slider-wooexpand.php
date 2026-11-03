<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-wooexpand` (bdthemes-prime-slider/modules/wooexpand/widgets/wooexpand.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-wooexpand',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_rating' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_length' => 30,
		'strip_shortcode' => 'yes',
		'show_price' => 'yes',
		'show_cart' => 'yes',
		'show_meta' => 'yes',
		'show_badge' => 'yes',
		'image_expand_event' => 'click',
		'active_item_number' => 1,
		'show_scrollspy' => 'yes',
		'scrollspy_delay' => 500,
		'scrollspy_animation' => 'fade',
		'posts_source' => 'product',
		'posts_limit' => 4,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
	),
	'repeaters' => array(),
	'styles' => array(
		'image_expand_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'image_expand_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-content' => 'width: {{SIZE}}%;',
			),
		),
		'items_content_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-content' => 'text-align: {{VALUE}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-item' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-item' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-item' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-item' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'image_overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-item:before' => 'background: {{VALUE}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-item .bdt-wooexpand-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-ps-title',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-ps-title a',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'meta_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-single-meta span *' => 'color: {{VALUE}};',
			),
		),
		'meta_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-single-meta span a:hover' => 'color: {{VALUE}};',
			),
		),
		'meta_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-single-meta span',
		),
		'meta_left_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-single-meta .bdt-meta-name',
		),
		'meta_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-single-meta > span' => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-text' => 'color: {{VALUE}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-ps-text',
		),
		'prime_slider_excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-text' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'old_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price del span' => 'color: {{VALUE}};',
			),
		),
		'old_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price del > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'old_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price del span',
		),
		'sale_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price ins, {{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price > span' => 'color: {{VALUE}};',
			),
		),
		'sale_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price ins, {{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sale_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price ins, {{WRAPPER}} .bdt-wooexpand .bdt-ps-price .price > span',
		),
		'sale_price_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-price' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'badge_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-badge-wrapper' => 'color: {{VALUE}};',
			),
		),
		'badge_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-meta-text-wrapper' => 'border-top-color: {{VALUE}};',
			),
		),
		'badge_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-badge-wrapper',
		),
		'prime_slider_badge_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-meta-text-wrapper' => 'padding-top: {{SIZE}}{{UNIT}}; margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'rating_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-rating .star-rating span' => 'color: {{VALUE}};',
			),
		),
		'rating_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-rating .woocommerce-review-link' => 'color: {{VALUE}};',
			),
		),
		'prime_slider_rating_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-rating .woocommerce-product-rating' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart svg' => 'fill: {{VALUE}};',
			),
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart:hover i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'button_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart' => 'border-color: {{VALUE}};',
			),
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart:hover' => 'border-color: {{VALUE}};',
			),
		),
		'button_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart:after, {{WRAPPER}} .bdt-wooexpand .bdt-ps-add-to-cart-btn .bdt-ps-add-to-cart:before' => 'background: {{VALUE}};',
			),
		),
		'counter_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-counter' => 'color: {{VALUE}};',
			),
		),
		'counter_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-wooexpand-counter',
		),
		'plus_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-plus-title-wrap span' => 'color: {{VALUE}};',
			),
		),
		'plus_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-plus-title-wrap a' => 'color: {{VALUE}};',
			),
		),
		'plus_title_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-wooexpand .bdt-plus-title-wrap .bdt-ps-title-inner:before' => 'background: {{VALUE}};',
			),
		),
		'plus_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-wooexpand .bdt-plus-title-wrap span, {{WRAPPER}} .bdt-wooexpand .bdt-plus-title-wrap .bdt-ps-title-inner',
		),
	),
);
