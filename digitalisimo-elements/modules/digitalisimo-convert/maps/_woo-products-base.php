<?php
/**
 * Controles de la cuadrícula de productos de PRO (`modules/woocommerce/widgets/products-base.php`) que heredan los
 * Productos relacionados y las Ventas dirigidas de PRO, y los propios (`Store\Products_Base`) con el mismo nombre. No es
 * un mapa (`Registry` ignora `maps/_*.php`): los mapas de `woocommerce-product-related` y `-upsell` lo suman a los suyos,
 * con las clases de `_woo-products-classes.php` (frente N). `tests/elements-pro-woo-single.php` comprueba que la lista
 * sigue cubriendo los controles de la base de PRO.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'keep'   => array( 'columns', 'products_class', 'column_gap', 'row_gap', 'align', 'image_border_radius', 'image_spacing', 'title_color', 'title_spacing', 'star_color', 'empty_star_color', 'star_size', 'rating_spacing', 'price_color', 'old_price_color', 'button_text_color', 'button_background_color', 'button_border_color', 'button_hover_color', 'button_hover_background_color', 'button_hover_border_color', 'button_border_radius', 'button_text_padding', 'button_spacing', 'automatically_align_buttons', 'view_cart_color', 'view_cart_spacing', 'box_border_width', 'box_border_radius', 'box_padding', 'box_bg_color', 'box_border_color', 'box_bg_color_hover', 'box_border_color_hover', 'pagination_spacing', 'show_pagination_border', 'pagination_border_color', 'pagination_padding', 'pagination_link_color', 'pagination_link_bg_color', 'pagination_link_color_hover', 'pagination_link_bg_color_hover', 'pagination_link_color_active', 'pagination_link_bg_color_active', 'show_onsale_flash', 'onsale_text_color', 'onsale_text_background_color', 'onsale_border_radius', 'onsale_width', 'onsale_height', 'onsale_horizontal_position', 'onsale_distance' ),
	'groups' => array(
		'image_border'          => 'image_border',
		'title_typography'      => 'title_typography',
		'price_typography'      => 'price_typography',
		'old_price_typography'  => 'old_price_typography',
		'button_typography'     => 'button_typography',
		'button_border'         => 'button_border',
		'view_cart_typography'  => 'view_cart_typography',
		'box_shadow'            => 'box_shadow',
		'box_shadow_hover'      => 'box_shadow_hover',
		'pagination_typography' => 'pagination_typography',
		'onsale_typography'     => 'onsale_typography',
	),
	'ui'     => array( 'wc_style_warning', 'heading_image_style', 'heading_title_style', 'heading_rating_style', 'heading_price_style', 'heading_old_price_style', 'heading_button_style', 'heading_view_cart_style' ),
);
