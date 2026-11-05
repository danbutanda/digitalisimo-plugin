<?php
/**
 * Custom Add To Cart de PRO → Añadir al carrito personalizado (`digitalisimo-woo-add-to-cart`,
 * modules/digitalisimo-woo/single/class-add-to-cart.php).
 *
 * Los dos heredan el Botón del núcleo y añaden producto, cantidad y disposición con el mismo nombre: todo `keep` y
 * `groups` (los del botón del núcleo, con la lista de `Button_Trait`). El texto por defecto de PRO («Add to Cart», en el
 * idioma del sitio) se completa antes de traducir, porque el propio tiene el suyo en español. Disposición y alineación
 * cambian las clases de PRO por las propias. Los estilos opcionales de las muestras (F4-Woo, `digitalisimo_swatch_*`)
 * pasan tal cual: el widget propio tiene la misma sección. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_atc_classes = (array) require __DIR__ . '/_woo-add-to-cart.php';

return array(
	'source'   => 'wc-add-to-cart',
	'target'   => 'digitalisimo-woo-add-to-cart',
	'status'   => 'ready',
	'panel'    => 'hide',
	'defaults' => array(
		// Texto por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
		'text' => function_exists( '__' ) ? __( 'Add to Cart', 'elementor-pro' ) : 'Add to Cart', // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
		// Vista antigua de los botones (la escribe F8 al convertir el «Añadir al carrito» de otros orígenes).
		'view' => 'traditional',
	),
	'keep'     => array(
		// Producto, cantidad y disposición (PRO).
		'product_id',
		'show_quantity',
		'quantity',
		'layout',
		// Botón del núcleo (`elementor/includes/widgets/traits/button-trait.php`).
		'button_type',
		'text',
		'link',
		'size',
		'selected_icon',
		'icon',
		'icon_align',
		'icon_indent',
		'button_css_id',
		'align',
		'content_align',
		'button_text_color',
		'hover_color',
		'button_hover_border_color',
		'button_hover_transition_duration',
		'hover_animation',
		'border_radius',
		'text_padding',
	),
	'groups'   => array(
		'typography'              => 'typography',
		'text_shadow'             => 'text_shadow',
		'background'              => 'background',
		'button_box_shadow'       => 'button_box_shadow',
		'button_background_hover' => 'button_background_hover',
		'button_hover_box_shadow' => 'button_hover_box_shadow',
		'border'                  => 'border',
	),
	'drop'     => array(
		'view' => 'Vista antigua de los botones: el botón actual del núcleo no la usa (tampoco el de PRO).',
	),
	'classes'  => array_merge( $digi_atc_classes, array(
		'.elementor-widget-wc-add-to-cart'      => '.elementor-widget-digitalisimo-woo-add-to-cart',
	) ),
);
