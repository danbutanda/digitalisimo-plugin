<?php
/**
 * Woo - Products- (obsoleto) de PRO (`wc-products`) → Productos (clásico) (`digitalisimo-woo-products-classic`, modules/digitalisimo-woo/store/class-products-classic.php).
 *
 * El widget propio repite los controles del de PRO y los que hereda (`products-base.php`, `base-widget.php`, el trait de
 * consulta, la presentación «Classic») con el mismo nombre, valor por defecto, opciones y condiciones: el mapa es `keep` y
 * `groups` (el grupo de consulta incluido). Los textos por defecto que PRO pintaba sin guardarlos se completan con los
 * de PRO, así lo convertido dice lo mismo. La consulta (`posts_*`) es el mismo grupo del control de consulta y la presentación «Clásico»
 * (`_skin`, `classic_columns`) es la propia. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_wp_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'wc-products',
	'target'   => 'digitalisimo-woo-products-classic',
	'status'   => 'ready',
	'panel'    => 'hide',
		'keep'     => array(
		'posts_per_page',
		'filter_by',
		'orderby',
		'order',
		'exclude',
		'exclude_ids',
		'avoid_duplicates',
		'products_class',
		'column_gap',
		'row_gap',
		'align',
		'image_border_radius',
		'image_spacing',
		'title_color',
		'title_spacing',
		'star_color',
		'empty_star_color',
		'star_size',
		'rating_spacing',
		'price_color',
		'old_price_color',
		'button_text_color',
		'button_background_color',
		'button_border_color',
		'button_hover_color',
		'button_hover_background_color',
		'button_hover_border_color',
		'button_border_radius',
		'button_text_padding',
		'button_spacing',
		'automatically_align_buttons',
		'view_cart_color',
		'view_cart_spacing',
		'box_border_width',
		'box_border_radius',
		'box_padding',
		'box_bg_color',
		'box_border_color',
		'box_bg_color_hover',
		'box_border_color_hover',
		'pagination_spacing',
		'show_pagination_border',
		'pagination_border_color',
		'pagination_padding',
		'pagination_link_color',
		'pagination_link_bg_color',
		'pagination_link_color_hover',
		'pagination_link_bg_color_hover',
		'pagination_link_color_active',
		'pagination_link_bg_color_active',
		'show_onsale_flash',
		'onsale_text_color',
		'onsale_text_background_color',
		'onsale_border_radius',
		'onsale_width',
		'onsale_height',
		'onsale_horizontal_position',
		'onsale_distance',
		'classic_columns',
	),
	'groups'   => array(
		'posts' => 'posts',
		'image_border' => 'image_border',
		'title_typography' => 'title_typography',
		'price_typography' => 'price_typography',
		'old_price_typography' => 'old_price_typography',
		'button_typography' => 'button_typography',
		'button_border' => 'button_border',
		'view_cart_typography' => 'view_cart_typography',
		'box_shadow' => 'box_shadow',
		'box_shadow_hover' => 'box_shadow_hover',
		'pagination_typography' => 'pagination_typography',
		'onsale_typography' => 'onsale_typography',
	),
	// Avisos y encabezados del panel, sin valor.
	'ui'       => array(
		'deprecated_notice',
		'advanced',
		'wc_style_warning',
		'heading_image_style',
		'heading_title_style',
		'heading_rating_style',
		'heading_price_style',
		'heading_old_price_style',
		'heading_button_style',
		'heading_view_cart_style',
	),
	'classes'  => is_file( __DIR__ . '/_woo-products-classes.php' ) ? (array) require __DIR__ . '/_woo-products-classes.php' : array(),
);
