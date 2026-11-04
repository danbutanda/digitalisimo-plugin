<?php
/**
 * Carrusel anidado de PRO (`nested-carousel`) → Carrusel anidado (`digitalisimo-nested-carousel`,
 * modules/digitalisimo-carousel/widgets/class-nested-carousel.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto y las mismas
 * condiciones: los 7 suyos (más `slide_title` en el repetidor) y los de `Nested_Controls`, que repite el trait de
 * carrusel de PRO (`base/base-carousel-trait.php`). Por eso el mapa es todo `keep` y `groups`. Los contenedores hijos
 * (una diapositiva cada uno) no se tocan: `Converter::tree()` sólo cambia `widgetType` y los ajustes del widget y
 * recorre los hijos, que no son widgets de PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_nc_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_nc_classes = is_file( __DIR__ . '/_carousel-classes.php' ) ? (array) require __DIR__ . '/_carousel-classes.php' : array();

return array(
	'source'    => 'nested-carousel',
	'target'    => 'digitalisimo-nested-carousel',
	'status'    => 'draft',
	'panel'     => 'hide',
	// Valores por defecto de PRO que el traductor necesita para saber si el sitio los cambió.
	'defaults'  => array(
		'carousel_name'         => $digi_nc_t( 'Carousel' ),
		'autoplay'              => 'yes',
		'infinite'              => 'yes',
		'offset_sides'          => 'none',
		'navigation_previous_icon' => array( 'value' => 'eicon-chevron-left', 'library' => 'eicons' ),
		'navigation_next_icon'     => array( 'value' => 'eicon-chevron-right', 'library' => 'eicons' ),
	),
	'keep'      => array(
		// Propios del carrusel anidado.
		'carousel_name',
		'image_spacing_custom',
		'border_radius',
		'content_padding',
		// Diseño (trait).
		'slides_to_show',
		'slides_to_scroll',
		'equal_height',
		// Ajustes.
		'autoplay',
		'autoplay_speed',
		'pause_on_hover',
		'pause_on_interaction',
		'infinite',
		'speed',
		'direction',
		'offset_sides',
		'offset_width',
		// Navegación.
		'arrows',
		'navigation_previous_icon',
		'navigation_previous_icon_horizontal_orientation',
		'navigation_previous_icon_horizontal_position',
		'navigation_previous_icon_vertical_orientation',
		'navigation_previous_icon_vertical_position',
		'navigation_next_icon',
		'navigation_next_icon_horizontal_orientation',
		'navigation_next_icon_horizontal_position',
		'navigation_next_icon_vertical_orientation',
		'navigation_next_icon_vertical_position',
		'arrows_size',
		'arrow_normal_color',
		'arrow_hover_color',
		'arrows_border_radius',
		'arrows_padding',
		'arrows_position',
		// Paginación.
		'pagination',
		'dots_gap',
		'dots_size',
		'dots_normal_color',
		'dots_hover_color',
		'dots_custom_position',
		'dots_horizontal_position',
		'dots_horizontal_offset',
		'dots_vertical_position',
		'dots_vertical_offset',
		'dots_position',
		'dots_pagination_spacing',
		'fraction_color',
		'fraction_custom_position',
		'fraction_horizontal_position',
		'fraction_horizontal_offset',
		'fraction_vertical_position',
		'fraction_vertical_offset',
		'fraction_position',
		'fraction_pagination_spacing',
		'progressbar_height',
		'progressbar_normal_color',
		'progressbar_hover_color',
	),
	// Controles de grupo con el mismo prefijo en el widget propio.
	'groups'    => array(
		'content_background'        => 'content_background',
		'content_border'            => 'content_border',
		'arrows_normal_background'  => 'arrows_normal_background',
		'arrows_normal_border_type' => 'arrows_normal_border_type',
		'arrows_normal_box_shadow'  => 'arrows_normal_box_shadow',
		'arrows_hover_background'   => 'arrows_hover_background',
		'arrows_hover_border_type'  => 'arrows_hover_border_type',
		'arrows_hover_box_shadow'   => 'arrows_hover_box_shadow',
		'typography_fraction'       => 'typography_fraction',
	),
	// Las filas del repetidor: una por contenedor hijo, con su título del navegador y su `_id`.
	'repeaters' => array(
		'carousel_items' => array(
			'target'       => 'carousel_items',
			'keep'         => array( 'slide_title' ),
			'default_rows' => array(
				array( 'slide_title' => $digi_nc_t( 'Slide #1' ) ),
				array( 'slide_title' => $digi_nc_t( 'Slide #2' ) ),
				array( 'slide_title' => $digi_nc_t( 'Slide #3' ) ),
			),
		),
	),
	'approx'    => array(
		'infinite'     => 'El bucle rebobina al llegar al final o al principio, sin clonar diapositivas como hacía PRO.',
		'offset_sides' => 'El asomo lateral reserva el ancho indicado a cada lado de la pista; PRO, además, dejaba asomar una milésima de la diapositiva siguiente en la última.',
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array(
		'heading_previous_arrow',
		'heading_previous_arrow_horizontal',
		'heading_previous_arrow_vertical',
		'heading_next_arrow',
		'heading_next_arrow_horizontal',
		'heading_next_arrow_vertical',
		'heading_icons',
		'heading_pagination_dots',
		'heading_dots_pagination_horizontal',
		'heading_dotspagination_vertical',
		'heading_pagination_fraction',
		'heading_fraction_pagination_horizontal',
		'heading_fractionpagination_vertical',
		'heading_pagination_progress',
	),
	// Clases de PRO en el CSS personalizado del elemento: las comunes de los carruseles más las del anidado.
	'classes'   => array_merge(
		$digi_nc_classes,
		array(
			'.elementor-widget-n-carousel' => '.elementor-widget-digitalisimo-nested-carousel',
			'.e-n-carousel'                => '.digi-ncarousel',
		)
	),
);
