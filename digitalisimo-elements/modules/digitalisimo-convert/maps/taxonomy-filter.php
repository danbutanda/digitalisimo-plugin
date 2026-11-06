<?php
/**
 * Filtro de taxonomías de PRO (`taxonomy-filter`, modules/loop-filter/widgets/taxonomy-filter.php) → Filtro de
 * taxonomías (`digitalisimo-taxonomy-filter`, modules/digitalisimo-loop/widgets/class-taxonomy-filter.php).
 *
 * El widget propio repite los 22 controles (y los grupos) del de PRO con el mismo nombre, valor por defecto y
 * condiciones: el mapa es todo `keep` y `groups`. `selected_element` es el ID del Loop Grid del mismo documento, que la
 * conversión no cambia (el ID del elemento se conserva; sólo cambia el widget), así que el filtro sigue apuntando al
 * mismo bucle, propio o de PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_tf_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'taxonomy-filter',
	'target'   => 'digitalisimo-taxonomy-filter',
	'status'   => 'ready',
	'panel'    => 'hide',
	'defaults' => array(
		'first_item_title' => $digi_tf_t( 'All' ),
	),
	'keep'     => array(
		// Disposición.
		'selected_element',
		'taxonomy',
		'direction',
		'item_alignment_horizontal',
		'title_alignment_horizontal',
		'item_alignment_vertical',
		'title_alignment_vertical',
		// Ajustes.
		'multiple_selection',
		'logical_combination',
		'show_empty_items',
		'show_child_taxonomy',
		'child_taxonomy_depth',
		'show_first_item',
		'first_item_title',
		'number_of_taxonomies',
		'horizontal_scroll',
		// Elementos.
		'taxonomy_filter_items_space_between',
		'taxonomy_filter_normal_text_color',
		'taxonomy_filter_hover_text_color',
		'taxonomy_filter_active_text_color',
		'taxonomy_filter_border_radius',
		'taxonomy_filter_padding',
	),
	'groups'   => array(
		'taxonomy_filter_typography'         => 'taxonomy_filter_typography',
		'taxonomy_filter_normal_text_shadow' => 'taxonomy_filter_normal_text_shadow',
		'taxonomy_filter_normal_background'  => 'taxonomy_filter_normal_background',
		'taxonomy_filter_normal_border'      => 'taxonomy_filter_normal_border',
		'taxonomy_filter_normal_box_shadow'  => 'taxonomy_filter_normal_box_shadow',
		'taxonomy_filter_hover_text_shadow'  => 'taxonomy_filter_hover_text_shadow',
		'taxonomy_filter_hover_background'   => 'taxonomy_filter_hover_background',
		'taxonomy_filter_hover_border'       => 'taxonomy_filter_hover_border',
		'taxonomy_filter_hover_box_shadow'   => 'taxonomy_filter_hover_box_shadow',
		'taxonomy_filter_active_text_shadow' => 'taxonomy_filter_active_text_shadow',
		'taxonomy_filter_active_background'  => 'taxonomy_filter_active_background',
		'taxonomy_filter_active_border'      => 'taxonomy_filter_active_border',
		'taxonomy_filter_active_box_shadow'  => 'taxonomy_filter_active_box_shadow',
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array(
		'heading_filter_logic',
		'heading_displayed_elements',
	),
	// Clases de PRO en el CSS personalizado del elemento.
	'classes'  => array(
		'.elementor-widget-taxonomy-filter' => '.elementor-widget-digitalisimo-taxonomy-filter',
		'.e-filter-item'                    => '.digi-tfilter__item',
		'.e-filter-empty'                   => '.digi-tfilter__empty',
		'.e-filter'                         => '.digi-tfilter',
	),
);
