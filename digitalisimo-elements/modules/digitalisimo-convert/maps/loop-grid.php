<?php
/**
 * Loop Grid de PRO (`loop-grid`, modules/loop-builder/widgets/loop-grid.php) → Cuadrícula de bucle
 * (`digitalisimo-loop-grid`, modules/digitalisimo-loop/widgets/class-loop-grid.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, valor por defecto y condiciones: los suyos, los
 * de su base (`loop-builder/widgets/base.php`), los de Entradas (`posts/widgets/posts-base.php`, con el botón de
 * `Button_Widget_Trait`) y los de cada piel (`_skin`), con su prefijo: `post_query_*` (entradas), `post_taxonomy_*`
 * (términos) y, con WooCommerce, `product_query_*` y `product_taxonomy_*`. Por eso el mapa es `keep` y `groups` (los
 * prefijos de piel cubren el grupo de consulta y los controles sueltos de cada piel). `template_id` (y las plantillas
 * alternas) sigue apuntando al mismo Elemento de bucle. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_lg_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_lg_pagination = is_file( __DIR__ . '/_loop-pagination.php' ) ? (array) require __DIR__ . '/_loop-pagination.php' : array();

return array(
	'source'    => 'loop-grid',
	'target'    => 'digitalisimo-loop-grid',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Textos por defecto de PRO: lo convertido conserva el texto que el sitio veía.
	'defaults'  => array(
		'pagination_prev_label'             => $digi_lg_t( 'Previous' ),
		'pagination_next_label'             => $digi_lg_t( 'Next' ),
		'text'                              => $digi_lg_t( 'Load More' ),
		'load_more_no_posts_custom_message' => $digi_lg_t( 'No more posts to show' ),
		'nothing_found_message_text'        => $digi_lg_t( 'It seems we can’t find what you’re looking for.' ),
	),
	'keep'      => array_merge(
		array(
			// Disposición.
			'template_id',
			'columns',
			'posts_per_page',
			'masonry',
			'equal_height',
			'alternate_template',
			'edit_handle_selector',
			// Opciones adicionales.
			'enable_nothing_found_message',
			'nothing_found_message_text',
			'nothing_found_message_align',
			'nothing_found_message_html_tag',
			// Estilo.
			'column_gap',
			'row_gap',
			'nothing_found_message_space_from_top',
			'nothing_found_message_space_from_bottom',
			'nothing_found_message_color',
			// Paginación de la base del bucle.
			'load_more_button_align',
			'pagination_load_type',
			'auto_scroll',
			'auto_scroll_offset',
		),
		(array) ( $digi_lg_pagination['keep'] ?? array() )
	),
	'groups'    => array_merge(
		array(
			'nothing_found_message_typography'  => 'nothing_found_message_typography',
			'nothing_found_message_text_shadow' => 'nothing_found_message_text_shadow',
			'nothing_found_message_text_stroke' => 'nothing_found_message_text_stroke',
			// Pieles: el grupo de consulta y los controles de cada una llevan su prefijo.
			'post_query'                        => 'post_query',
			'post_taxonomy'                     => 'post_taxonomy',
			'product_query'                     => 'product_query',
			'product_taxonomy'                  => 'product_taxonomy',
		),
		(array) ( $digi_lg_pagination['groups'] ?? array() )
	),
	'drop'      => (array) ( $digi_lg_pagination['drop'] ?? array() ),
	'repeaters' => array(
		'alternate_templates' => array(
			'target' => 'alternate_templates',
			'keep'   => array( 'template_id', 'repeat_template', 'show_once', 'column_span', 'static_position' ),
		),
	),
	// Avisos, encabezados y controles que PRO registra y quita: no guardan valor.
	'ui'        => array_merge(
		array(
			'repeat_template_note',
			'column_span_note',
			'column_span_masonry_note',
			'static_position_note',
			'align',
			'archive_query_note',
			'product_related_products_note',
			'product_upsells_products_note',
			'product_cross_sells_products_note',
		),
		(array) ( $digi_lg_pagination['ui'] ?? array() )
	),
	// Clases de PRO en el CSS personalizado del elemento.
	'classes'   => array_merge(
		array(
			'.elementor-widget-loop-grid'           => '.elementor-widget-digitalisimo-loop-grid',
			'.elementor-loop-container'             => '.digi-loop-grid__items',
			'.elementor-grid'                       => '.digi-loop-grid__items',
			'.e-loop-nothing-found-message__text'   => '.digi-loop-grid__nothing-text',
			'.e-loop-nothing-found-message'         => '.digi-loop-grid__nothing',
			'.e-loop__load-more'                    => '.digi-loop__more',
		),
		(array) ( $digi_lg_pagination['classes'] ?? array() )
	),
);
