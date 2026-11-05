<?php
/**
 * Price List de PRO → Lista de precios (`digitalisimo-price-list`, modules/digitalisimo-content/widgets/class-price-list.php).
 *
 * El widget propio repite los controles del de PRO (también los del repetidor) con el mismo nombre, los mismos valores y
 * las mismas condiciones, así que el mapa es `keep`, `groups` y el repetidor. El marcado y las clases son propios
 * (`digi-price-list*`). Diferencias sin clave propia: el texto alternativo de la imagen es el de Medios (PRO ponía siempre el
 * título; queda como respaldo), la imagen lleva `srcset` y una imagen guardada sólo con URL se pinta (PRO la dejaba sin
 * `src`). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pl_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};
$digi_pl_lorem = $digi_pl_t( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' );

return array(
	'source'    => 'price-list',
	'target'    => 'digitalisimo-price-list',
	'status'    => 'ready',
	'panel'     => 'hide',
	'keep'      => array(
		'title_tag', 'description_tag', 'vertical_align', 'row_gap', 'image_spacing', 'content_spacing', 'heading_color', 'price_color',
		'description_color', 'separator_style', 'separator_weight', 'separator_color', 'separator_spacing', 'border_radius',
	),
	// Grupos con el mismo prefijo: tamaño de imagen, tipografías y contorno.
	'groups'    => array(
		'image_size'             => 'image_size',
		'heading_typography'     => 'heading_typography',
		'text_stroke'            => 'text_stroke',
		'price_typography'       => 'price_typography',
		'description_typography' => 'description_typography',
	),
	'repeaters' => array(
		'price_list' => array(
			'target'       => 'price_list',
			'defaults'     => array(
				'title'            => '',
				'item_description' => '',
				'link'             => array( 'url' => '#' ),
			),
			// Las tres filas por defecto de PRO, con sus textos.
			'default_rows' => array(
				array( 'title' => $digi_pl_t( 'First item on the list' ), 'item_description' => $digi_pl_lorem, 'price' => '$20', 'link' => array( 'url' => '#' ) ),
				array( 'title' => $digi_pl_t( 'Second item on the list' ), 'item_description' => $digi_pl_lorem, 'price' => '$9', 'link' => array( 'url' => '#' ) ),
				array( 'title' => $digi_pl_t( 'Third item on the list' ), 'item_description' => $digi_pl_lorem, 'price' => '$32', 'link' => array( 'url' => '#' ) ),
			),
			'keep'         => array( 'price', 'title', 'item_description', 'image', 'link' ),
		),
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading__title', 'price_title', 'heading_item_description', 'heading_separator' ),
	'classes'   => array(
		'.elementor-widget-price-list'      => '.elementor-widget-digitalisimo-price-list',
		'.elementor-price-list-item'        => '.digi-price-list__item',
		'.elementor-price-list-image'       => '.digi-price-list__image',
		'.elementor-price-list-text'        => '.digi-price-list__text',
		'.elementor-price-list-header'      => '.digi-price-list__header',
		'.elementor-price-list-title'       => '.digi-price-list__title',
		'.elementor-price-list-separator'   => '.digi-price-list__separator',
		'.elementor-price-list-price'       => '.digi-price-list__price',
		'.elementor-price-list-description' => '.digi-price-list__description',
		'.elementor-price-list'             => '.digi-price-list',
	),
);
