<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-woolamp` (bdthemes-prime-slider-lite/modules/woolamp/widgets/woolamp.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-woolamp',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_category' => 'yes',
		'show_excerpt' => 'yes',
		'strip_shortcode' => 'yes',
		'show_price' => 'yes',
		'show_cart' => 'yes',
		'show_navigation_dots' => 'yes',
		'show_social_share' => 'yes',
		'posts_source' => 'product',
		'posts_limit' => 3,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'finite' => 'yes',
	),
	'repeaters' => array(
		'share_buttons' => array(
			'fields' => array(
				'button' => 'facebook',
			),
			'rows' => array( array(
					'button' => 'facebook',
				), array(
					'button' => 'linkedin',
				), array(
					'button' => 'twitter',
				) ),
		),
	),
	'styles' => array(
		'viewport_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow .bdt-slideshow-items' => 'min-height: {{SIZE}}{{UNIT}} !important;',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-content-inner' => 'text-align: {{VALUE}};',
			),
		),
		'image_overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'(mobile){{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-wc-product-img:before' => 'background: {{VALUE}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-content-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-title a' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-title',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a' => 'color: {{VALUE}};',
			),
		),
		'category_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a:before' => 'background: {{VALUE}};',
			),
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a',
		),
		'prime_slider_category_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_hover_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-category a:hover:before' => 'background: {{VALUE}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-text' => 'color: {{VALUE}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-text',
		),
		'prime_slider_excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-text' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'old_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price del span' => 'color: {{VALUE}};',
			),
		),
		'old_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price del > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'old_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price del span',
		),
		'sale_price_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price ins, {{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price > span' => 'color: {{VALUE}};',
			),
		),
		'sale_price_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price ins, {{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sale_price_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price ins, {{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price .price > span',
		),
		'sale_price_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-price' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button' => 'background-color: {{VALUE}};',
			),
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button',
		),
		'button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button',
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button',
		),
		'button_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button:before' => 'background-color: {{VALUE}};',
			),
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button:hover' => 'color: {{VALUE}};',
			),
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .button:hover' => 'border-color: {{VALUE}};',
			),
		),
		'quantity_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text' => 'color: {{VALUE}};',
			),
		),
		'quantity_button_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text' => 'background-color: {{VALUE}};',
			),
		),
		'quantity_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text',
		),
		'quantity_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'quantity_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'quantity_button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text',
		),
		'quantity_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-content-wrapper .bdt-ps-add-to-cart .input-text',
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn' => 'color: {{VALUE}};',
			),
		),
		'social_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn',
		),
		'social_icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn',
		),
		'social_icon_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'social_offset_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share' => 'left: -{{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn',
		),
		'social_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn:hover' => 'color: {{VALUE}};',
			),
		),
		'social_icon_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn:hover',
		),
		'icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-social-share .bdt-social-share-item .bdt-ps-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'dot_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-dotnav li a' => 'color: {{VALUE}}',
			),
		),
		'dot_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-dotnav li a:hover' => 'color: {{VALUE}}',
			),
		),
		'active_dot_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-dotnav li.bdt-active a' => 'color: {{VALUE}}',
			),
		),
		'dots_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-dotnav li a',
		),
		'dots_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-dotnav li' => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'dots_offset_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-woolamp .bdt-ps-dotnav' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
	),
);
