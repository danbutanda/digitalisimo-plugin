<?php
/**
 * Add To Cart de PRO → Añadir al carrito (`digitalisimo-woo-product-add-to-cart`,
 * modules/digitalisimo-woo/single/class-product-add-to-cart.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto, condiciones y selectores: todo `keep` y `groups`. Disposición,
 * alineación y cantidad cambian las clases de PRO por las propias, igual que las envolturas de la cantidad y el botón y
 * el contenedor del bucle. Los estilos opcionales de las muestras (F4-Woo, `digitalisimo_swatch_*`) pasan tal cual: el
 * widget propio tiene la misma sección. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_atc_classes = (array) require __DIR__ . '/_woo-add-to-cart.php';

return array(
	'source'  => 'woocommerce-product-add-to-cart',
	'target'  => 'digitalisimo-woo-product-add-to-cart',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array(
		'layout',
		'alignment',
		'button_border_radius',
		'button_padding',
		'button_text_color',
		'button_bg_color',
		'button_border_color',
		'button_text_color_hover',
		'button_bg_color_hover',
		'button_border_color_hover',
		'button_transition',
		'view_cart_color',
		'view_cart_spacing',
		'show_quantity',
		'spacing',
		'quantity_border_radius',
		'quantity_padding',
		'quantity_text_color',
		'quantity_bg_color',
		'quantity_border_color',
		'quantity_text_color_focus',
		'quantity_bg_color_focus',
		'quantity_border_color_focus',
		'quantity_transition',
		'variations_width',
		'variations_spacing',
		'variations_space_between',
		'variations_label_color_focus',
		'variations_select_color',
		'variations_select_bg_color',
		'variations_select_border_color',
		'variations_select_border_radius',
	),
	'groups'  => array(
		'button_typography'            => 'button_typography',
		'button_border'                => 'button_border',
		'view_cart_typography'         => 'view_cart_typography',
		'quantity_typography'          => 'quantity_typography',
		'quantity_border'              => 'quantity_border',
		'variations_label_typography'  => 'variations_label_typography',
		'variations_select_typography' => 'variations_select_typography',
	),
	'ui'      => array( 'wc_style_warning', 'heading_view_cart_style', 'heading_variations_label_style', 'heading_variations_select_style' ),
	'classes' => array_merge( $digi_atc_classes, array(
		'.elementor-widget-woocommerce-product-add-to-cart' => '.elementor-widget-digitalisimo-woo-product-add-to-cart',
		'.e-add-to-cart--show-quantity-yes'                 => '.digi-atc--show-quantity-yes',
		'.e-loop-add-to-cart-form-container'                => '.digi-atc__loop',
		'.e-loop-add-to-cart-form'                          => '.digi-atc__loop-form',
		'.elementor-add-to-cart'                            => '.digi-atc__wrap',
	) ),
);
