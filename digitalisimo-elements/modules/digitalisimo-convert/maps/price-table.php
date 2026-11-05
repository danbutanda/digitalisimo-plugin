<?php
/**
 * Price Table de PRO → Tabla de precios (`digitalisimo-price-table`, modules/digitalisimo-content/widgets/class-price-table.php).
 *
 * El widget propio repite los controles del de PRO (también los del repetidor de características) con el mismo nombre,
 * los mismos valores y las mismas condiciones, así que el mapa es `keep`, `groups` y el repetidor (también el icono FA4
 * heredado de cada fila, `item_icon`). El marcado y las clases son propios (`digi-price-table*`, `digi-ribbon*`); las
 * variables de PRO del fondo de la cabecera y del color de la lista pasan a las propias (`--digi-pt-*`). Diferencias sin
 * clave propia: el precio se anuncia completo con un texto sólo para lectores de pantalla (sus partes visibles quedan
 * ocultas a ellos), el precio original va en `del`, los símbolos de moneda se imprimen como caracteres y el botón sin
 * enlace es un `span`. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pt_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};
$digi_pt_icon = array( 'value' => 'far fa-check-circle', 'library' => 'fa-regular' );

return array(
	'source'    => 'price-table',
	'target'    => 'digitalisimo-price-table',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Textos por defecto de PRO (el propio tiene los suyos, en español).
	'defaults'  => array(
		'heading'                => $digi_pt_t( 'Enter your title' ),
		'sub_heading'            => $digi_pt_t( 'Enter your description' ),
		'period'                 => $digi_pt_t( 'Monthly' ),
		'button_text'            => $digi_pt_t( 'Click Here' ),
		'footer_additional_info' => $digi_pt_t( 'This is text element' ),
		'ribbon_title'           => $digi_pt_t( 'Popular' ),
	),
	'keep'      => array(
		'heading', 'sub_heading', 'heading_tag', 'currency_symbol', 'currency_symbol_custom', 'price', 'currency_format', 'sale',
		'original_price', 'period', 'button_text', 'link', 'footer_additional_info', 'show_ribbon', 'ribbon_title',
		'ribbon_horizontal_position', 'header_bg_color', 'header_padding', 'heading_color', 'sub_heading_color',
		'pricing_element_bg_color', 'pricing_element_padding', 'price_color', 'currency_size', 'currency_position',
		'currency_vertical_position', 'fractional-part_size', 'fractional_part_vertical_position', 'original_price_color',
		'original_price_vertical_position', 'period_color', 'period_position', 'features_list_bg_color', 'features_list_padding',
		'features_list_color', 'features_list_alignment', 'item_width', 'list_divider', 'divider_style', 'divider_color',
		'divider_weight', 'divider_width', 'divider_gap', 'footer_bg_color', 'footer_padding', 'button_size', 'button_text_color',
		'button_border_radius', 'button_text_padding', 'button_hover_color', 'button_hover_border_color', 'button_hover_animation',
		'additional_info_color', 'additional_info_margin', 'ribbon_bg_color', 'ribbon_distance', 'ribbon_text_color',
	),
	// Grupos con el mismo prefijo: tipografías, fondos y borde del botón, y sombra de la cinta.
	'groups'    => array(
		'heading_typography'         => 'heading_typography',
		'sub_heading_typography'     => 'sub_heading_typography',
		'price_typography'           => 'price_typography',
		'original_price_typography'  => 'original_price_typography',
		'period_typography'          => 'period_typography',
		'features_list_typography'   => 'features_list_typography',
		'button_typography'          => 'button_typography',
		'button_background'          => 'button_background',
		'button_border'              => 'button_border',
		'button_background_hover'    => 'button_background_hover',
		'additional_info_typography' => 'additional_info_typography',
		'ribbon_typography'          => 'ribbon_typography',
		'box_shadow'                 => 'box_shadow',
	),
	'repeaters' => array(
		'features_list' => array(
			'target'       => 'features_list',
			'defaults'     => array(
				'item_text'          => $digi_pt_t( 'List Item' ),
				'selected_item_icon' => $digi_pt_icon,
			),
			// Las tres filas por defecto de PRO, con sus textos.
			'default_rows' => array(
				array( 'item_text' => $digi_pt_t( 'List Item #1' ), 'selected_item_icon' => $digi_pt_icon ),
				array( 'item_text' => $digi_pt_t( 'List Item #2' ), 'selected_item_icon' => $digi_pt_icon ),
				array( 'item_text' => $digi_pt_t( 'List Item #3' ), 'selected_item_icon' => $digi_pt_icon ),
			),
			// `item_icon`: el icono FA4 que guardaban las versiones antiguas (fa4compatibility), que el widget propio lee igual.
			'keep'         => array( 'item_text', 'selected_item_icon', 'item_icon', 'item_icon_color' ),
		),
	),
	// Las variables de PRO en el CSS personalizado del elemento pasan a las propias.
	'filter'    => static function ( array $out ) {
		if ( isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) ) {
			$out['custom_css'] = str_replace( array( '--e-price-table-header-background-color', '--e-price-table-features-list-color' ), array( '--digi-pt-header-bg', '--digi-pt-features-color' ), $out['custom_css'] );
		}
		return $out;
	},
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading_heading_style', 'heading_sub_heading_style', 'heading_currency_style', 'fractional_part_style', 'heading_original_price_style', 'heading_period_style', 'heading_footer_button', 'heading_additional_info' ),
	'classes'   => array(
		'.elementor-widget-price-table'             => '.elementor-widget-digitalisimo-price-table',
		'.elementor-price-table__header'            => '.digi-price-table__header',
		'.elementor-price-table__heading'           => '.digi-price-table__heading',
		'.elementor-price-table__subheading'        => '.digi-price-table__subheading',
		'.elementor-price-table__price'             => '.digi-price-table__price',
		'.elementor-price-table__original-price'    => '.digi-price-table__original-price',
		'.elementor-price-table__currency'          => '.digi-price-table__currency',
		'.elementor-price-table__integer-part'      => '.digi-price-table__integer-part',
		'.elementor-price-table__after-price'       => '.digi-price-table__after-price',
		'.elementor-price-table__fractional-part'   => '.digi-price-table__fractional-part',
		'.elementor-price-table__period'            => '.digi-price-table__period',
		'.elementor-price-table__features-list'     => '.digi-price-table__features-list',
		'.elementor-price-table__feature-inner'     => '.digi-price-table__feature-inner',
		'.elementor-price-table__footer'            => '.digi-price-table__footer',
		'.elementor-price-table__button'            => '.digi-price-table__button',
		'.elementor-price-table__additional_info'   => '.digi-price-table__additional-info',
		'.elementor-price-table'                    => '.digi-price-table',
		'.elementor-typo-excluded'                  => '.digi-price-table__plain',
		'.elementor-ribbon-inner'                   => '.digi-ribbon__inner',
		'.elementor-ribbon-left'                    => '.digi-ribbon--left',
		'.elementor-ribbon-right'                   => '.digi-ribbon--right',
		'.elementor-ribbon'                         => '.digi-ribbon',
	),
);
