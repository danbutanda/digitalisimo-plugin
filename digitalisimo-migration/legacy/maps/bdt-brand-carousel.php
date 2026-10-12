<?php
/** Element Pack Pro 9.9.1 `bdt-brand-carousel` → `digitalisimo-brand-carousel` sobre el motor de carrusel propio. */
defined( 'ABSPATH' ) || exit;

$grid = require __DIR__ . '/bdt-brand-grid.php';

return array(
	'target'    => 'digitalisimo-brand-carousel',
	'classes'   => array(
		'.bdt-navigation-prev' => '.digi-carousel__button[data-digi-carousel-prev]',
		'.bdt-navigation-next' => '.digi-carousel__button[data-digi-carousel-next]',
		'.swiper-carousel'     => '.digi-carousel',
		'.bdt-ep-brand-carousel-image img' => '.digi-brand-carousel__image img',
		'.bdt-ep-brand-carousel-image' => '.digi-brand-carousel__image',
		'.bdt-ep-brand-carousel-item'  => '.digi-brand-carousel__card',
		'.bdt-ep-brand-carousel-name'  => '.digi-brand-carousel__name',
		'.bdt-ep-brand-carousel-link'  => '.digi-brand-carousel__website',
	),
	'defaults'  => array(
		'thumbnail_size'    => 'medium',
		'columns'           => 3,
		'columns_tablet'    => 2,
		'columns_mobile'    => 1,
		'item_gap'          => array( 'size' => 35 ),
		'item_gap_tablet'   => array( 'size' => 20 ),
		'item_gap_mobile'   => array( 'size' => 20 ),
		'item_match_height' => 'yes',
		'show_brand_name'   => 'yes',
		'brand_html_tag'    => 'h3',
		'show_website_link' => 'yes',
		'navigation'        => 'arrows',
	),
	'rename'    => array( 'thumbnail_size' => 'image_size' ),
	// Sin puntos ni barra de progreso propios: cualquier navegación visible pasa a flechas.
	'values'    => array(
		'image_size'     => $grid['values']['image_size'],
		'brand_html_tag' => $grid['values']['brand_html_tag'],
		'navigation'     => static function ( $value ) {
			return 'none' === $value ? 'none' : 'arrows';
		},
	),
	'drop'      => array( 'brand_event', 'icon_position', 'thumbnail_custom_dimension', 'skin', 'coverflow_toggle', 'centered_slides', 'grab_cursor', 'free_mode', 'observer', 'mousewheel', 'show_scrollbar', 'dynamic_bullets' ),
	'filter'    => static function ( array $out ) {
		// Element Pack sólo enlazaba el texto del sitio web, y sólo si estaba visible.
		if ( 'yes' !== ( $out['show_website_link'] ?? '' ) ) {
			foreach ( $out['brand_items'] as $index => $item ) {
				unset( $out['brand_items'][ $index ]['link'] );
			}
		}
		return $out;
	},
	'repeaters' => $grid['repeaters'],
);
