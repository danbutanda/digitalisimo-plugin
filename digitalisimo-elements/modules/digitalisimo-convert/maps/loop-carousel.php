<?php
/**
 * Loop Carousel de PRO (`loop-carousel`, modules/loop-builder/widgets/loop-carousel.php) → Carrusel de bucle
 * (`digitalisimo-loop-carousel`, modules/digitalisimo-loop/widgets/class-loop-carousel.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, valor por defecto y condiciones: los suyos, los
 * de su base (`loop-builder/widgets/base.php`: plantilla y pieles con su prefijo `post_query_*`, `post_taxonomy_*`,
 * `product_query_*`, `product_taxonomy_*`) y los del rasgo de carrusel de PRO (`base/base-carousel-trait.php`) a través
 * de `Nested_Controls` (C1). Como en PRO, la paginación es la del carrusel, no la de Entradas. Por eso el mapa es
 * `keep` y `groups`. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_lc_classes = is_file( __DIR__ . '/_carousel-classes.php' ) ? (array) require __DIR__ . '/_carousel-classes.php' : array();
$digi_lc_paging  = is_file( __DIR__ . '/_loop-pagination.php' ) ? (array) require __DIR__ . '/_loop-pagination.php' : array();

return array(
	'source'  => 'loop-carousel',
	'target'  => 'digitalisimo-loop-carousel',
	'status'  => 'ready',
	'panel'   => 'hide',
	'defaults' => array(
		'autoplay'                 => 'yes',
		'infinite'                 => 'yes',
		'offset_sides'             => 'none',
		'navigation_previous_icon' => array( 'value' => 'eicon-chevron-left', 'library' => 'eicons' ),
		'navigation_next_icon'     => array( 'value' => 'eicon-chevron-right', 'library' => 'eicons' ),
	),
	'keep'    => array(
		// Disposición.
		'template_id',
		'posts_per_page',
		'slides_to_show',
		'slides_to_scroll',
		'equal_height',
		'edit_handle_selector',
		'image_spacing_custom',
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
	'groups'  => array(
		'arrows_normal_background'  => 'arrows_normal_background',
		'arrows_normal_border_type' => 'arrows_normal_border_type',
		'arrows_normal_box_shadow'  => 'arrows_normal_box_shadow',
		'arrows_hover_background'   => 'arrows_hover_background',
		'arrows_hover_border_type'  => 'arrows_hover_border_type',
		'arrows_hover_box_shadow'   => 'arrows_hover_box_shadow',
		'typography_fraction'       => 'typography_fraction',
		// Pieles: el grupo de consulta y los controles de cada una llevan su prefijo.
		'post_query'                => 'post_query',
		'post_taxonomy'             => 'post_taxonomy',
		'product_query'             => 'product_query',
		'product_taxonomy'          => 'product_taxonomy',
	),
	'approx'  => array(
		'offset_sides' => 'El asomo lateral reserva el ancho indicado a cada lado de la pista; PRO, además, dejaba asomar una milésima de la diapositiva siguiente en la última.',
	),
	// Encabezados y avisos del panel: no guardan valor. Los de la paginación de Entradas están en la base del bucle de
	// PRO, pero el Loop Carousel los sustituye por la paginación del carrusel: nunca se registran ni se guardan.
	'ui'      => array_merge(
		array(
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
			'archive_query_note',
			'product_related_products_note',
			'product_upsells_products_note',
			'product_cross_sells_products_note',
			'load_more_button_align',
			'pagination_load_type',
			'auto_scroll',
			'auto_scroll_offset',
			'align',
		),
		(array) ( $digi_lc_paging['ui'] ?? array() ),
		(array) ( $digi_lc_paging['keep'] ?? array() ),
		array_keys( (array) ( $digi_lc_paging['drop'] ?? array() ) ),
		array_keys( (array) ( $digi_lc_paging['groups'] ?? array() ) )
	),
	'classes' => array_merge(
		$digi_lc_classes,
		array(
			'.elementor-widget-loop-carousel' => '.elementor-widget-digitalisimo-loop-carousel',
			'.elementor-loop-container'       => '.digi-crs',
		)
	),
);
