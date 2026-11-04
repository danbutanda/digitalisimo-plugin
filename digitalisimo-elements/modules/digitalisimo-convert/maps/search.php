<?php
/**
 * Search de PRO (`search`) → Búsqueda (`digitalisimo-search`, modules/digitalisimo-navigation/widgets/class-search.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto y las mismas
 * condiciones, también el grupo de consulta (`search_query_*`, el mismo control de las funciones avanzadas) y la
 * plantilla de bucle de los resultados en vivo, así que el mapa es todo `keep` y `groups`. Los textos por defecto de PRO
 * (en el idioma del sitio) pasan a lo convertido para que se vea lo mismo. El campo oculto `e_search_props` del
 * formulario se conserva, así que la página de resultados sigue aplicando la consulta del widget. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_se_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'search',
	'target'   => 'digitalisimo-search',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Textos por defecto de PRO: el widget propio tiene los suyos en español.
	'defaults' => array(
		'search_input_placeholder_text' => $digi_se_t( 'Type to start searching...' ),
		'submit_button_text'            => $digi_se_t( 'Search' ),
		'nothing_found_message_text'    => $digi_se_t( 'It seems we can’t find what you’re looking for.' ),
		'pagination_prev_label'         => $digi_se_t( 'Previous' ),
		'pagination_next_label'         => $digi_se_t( 'Next' ),
	),
	'keep'     => array(
		// Campo de búsqueda.
		'search_input_placeholder_text',
		'icon_search',
		'autocomplete',
		'icon_clear',
		'submit_trigger',
		'submit_button_text',
		'icon_submit',
		// Resultados.
		'live_results',
		'template_id',
		'minimum_search_characters',
		'number_of_columns',
		'number_of_items',
		'equal_height',
		'enable_loader',
		'enable_nothing_found_message',
		'nothing_found_message_text',
		'nothing_found_message_html_tag',
		// Ajustes adicionales.
		'pagination_type_options',
		'pagination_prev_label',
		'pagination_next_label',
		'page_limit_settings',
		'pagination_shorten_settings',
		// Estilo del campo.
		'placeholder_color',
		'search_field_input_text_color_normal',
		'search_field_icon_search_normal_color_normal',
		'search_field_input_text_color_focus',
		'search_field_icon_search_normal_color_focus',
		'search_field_search_input_transition',
		'search_field_border_radius',
		'search_field_padding',
		'search_field_icon_label_size',
		'search_field_icon_gap',
		'search_field_submit_gap',
		// Borrar.
		'icon_clear_size',
		'icon_clear_normal_color',
		'icon_clear_hover_color',
		'icon_clear_hover_transition',
		// Botón de envío.
		'icon_submit_position',
		'icon_submit_size',
		'icon_submit_gap',
		'submit_text_color_normal',
		'submit_icon_color_normal',
		'submit_text_color_hover',
		'submit_icon_color_hover',
		'submit_hover_animation',
		'submit_hover_transition',
		'submit_border_radius',
		'submit_padding',
		// Resultados (estilo).
		'results_border_radius',
		'results_padding',
		'results_distance_from_search_field',
		'results_is_dropdown_width',
		'results_is_custom_width',
		'results_custom_width',
		'results_max_height',
		'results_alignment',
		'search_result_column_gap',
		'search_result_row_gap',
		'search_result_loader_icon_color',
		'search_result_loader_icon_size',
		// Paginación (estilo).
		'style_additional_settings_alignment',
		'style_additional_settings_vertical_position',
		'style_additional_settings_normal_color',
		'style_additional_settings_hover_color',
		'style_button_color_icon_active',
		'page_numbers_space_between',
		'top_spacing',
		'bottom_spacing',
		// Mensaje sin resultados (estilo).
		'nothing_found_message_space_from_top',
		'nothing_found_message_space_from_bottom',
		'nothing_found_message_alignment',
		'nothing_found_message_color',
	),
	// Controles de grupo con el mismo prefijo en el widget propio.
	'groups'   => array(
		'search_query'                      => 'search_query',
		'search_field_typography'           => 'search_field_typography',
		'search_field_background_normal'    => 'search_field_background_normal',
		'search_field_border_normal'        => 'search_field_border_normal',
		'search_field_box_shadow__normal'   => 'search_field_box_shadow__normal',
		'search_field_background_focus'     => 'search_field_background_focus',
		'search_field_border_focus'         => 'search_field_border_focus',
		'search_field_box_shadow__focus'    => 'search_field_box_shadow__focus',
		'typography_title'                  => 'typography_title',
		'submit_typography'                 => 'submit_typography',
		'submit_background_normal'          => 'submit_background_normal',
		'submit_border_normal'              => 'submit_border_normal',
		'submit_box_shadow_normal'          => 'submit_box_shadow_normal',
		'submit_background_hover'           => 'submit_background_hover',
		'submit_border_hover'               => 'submit_border_hover',
		'submit_box_shadow_hover'           => 'submit_box_shadow_hover',
		'results_background'                => 'results_background',
		'results_border_type'               => 'results_border_type',
		'results_box_shadow'                => 'results_box_shadow',
		'nothing_found_message_typography'  => 'nothing_found_message_typography',
		'nothing_found_message_text_shadow' => 'nothing_found_message_text_shadow',
		'nothing_found_message_text_stroke' => 'nothing_found_message_text_stroke',
	),
	// Encabezados y descripciones del panel: no guardan valor.
	'ui'       => array(
		'heading_input',
		'heading_clear',
		'heading_submit',
		'nothing_found_message_description',
		'heading_pagination',
		'heading_style_additional_settings',
		'heading_style_additional_settings_colors',
		'heading_search_reasult_loader',
	),
	// Clases de PRO en el CSS personalizado del elemento (`.e-focus`, la clase del foco, pasa a `:has()` del campo).
	'classes'  => array(
		'.elementor-widget-search'        => '.elementor-widget-digitalisimo-search',
		'.e-search-form'                  => '.digi-search__form',
		'.e-search-label'                 => '.digi-search__label',
		'.e-search-input-wrapper'         => '.digi-search__field',
		'.e-search-input'                 => '.digi-search__input',
		'.e-search-submit'                => '.digi-search__submit',
		'.e-search-results-container'     => '.digi-search__results',
		'.e-search-results-list'          => '.digi-search__list',
		'.e-search-results'               => '.digi-search__box',
		'.e-search-loader'                => '.digi-search__loader',
		'.e-search-nothing-found-message' => '.digi-search__nothing',
		'.e-search'                       => '.digi-search',
		'.e-focus'                        => ':has(.digi-search__input:focus)',
		'.elementor-pagination'           => '.digi-search__pages',
		'.hide-pagination-numbers'        => '.digi-search--prevnext',
		'.no-results'                     => '.digi-search--none',
	),
);
