<?php
/**
 * Table of Contents de PRO → Tabla de contenido (`digitalisimo-table-of-contents`,
 * modules/digitalisimo-content/widgets/class-table-of-contents.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones
 * (también las opciones de «Minimizada en», que salen de los puntos de corte activos), así que el mapa es `keep` y
 * `groups`. El marcado, las clases, las variables y el script son propios; la lista se construye en el navegador con los
 * mismos selectores, exclusiones e ids de ancla que PRO. Aproximación: la sombra de la caja se aplica a la caja (con su
 * radio) y no al envoltorio del widget. Diferencias sin clave propia: `<nav>` con nombre, un solo botón real para
 * minimizar, indicador de carga propio y sin la animación de deslizamiento. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_toc_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_toc_classes = array(
	'.elementor-widget-table-of-contents'       => '.elementor-widget-digitalisimo-table-of-contents',
	'.elementor-toc__header-title'              => '.digi-toc__title',
	'.elementor-toc__header'                    => '.digi-toc__header',
	'.elementor-toc__toggle-button--expand'     => '.digi-toc__toggle-icon--expand',
	'.elementor-toc__toggle-button--collapse'   => '.digi-toc__toggle-icon--collapse',
	'.elementor-toc__toggle-button'             => '.digi-toc__toggle',
	'.elementor-toc__spinner-container'         => '.digi-toc__body',
	'.elementor-toc__spinner'                   => '.digi-toc__spinner',
	'.elementor-toc__body'                      => '.digi-toc__body',
	'.elementor-toc__list-items--collapsible'   => '.digi-toc__body--collapsible',
	'.elementor-toc__list-wrapper'              => '.digi-toc__list',
	'.elementor-toc__list-item-text-wrapper'    => '.digi-toc__row',
	'.elementor-toc__list-item-text'            => '.digi-toc__link',
	'.elementor-toc__list-item'                 => '.digi-toc__item',
	'.elementor-toc__top-level'                 => '.digi-toc__link--top',
	'.elementor-item-active'                    => '.digi-toc__link--active',
	'.elementor-toc--collapsed'                 => '.digi-toc--collapsed',
	'.elementor-toc--content-ellipsis'          => '.digi-toc--content-ellipsis',
);
foreach ( array( 'mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop' ) as $digi_toc_device ) {
	$digi_toc_classes[ '.elementor-toc--minimized-on-' . $digi_toc_device ] = '.digi-toc--minimized-on-' . $digi_toc_device;
}

return array(
	'source'   => 'table-of-contents',
	'target'   => 'digitalisimo-table-of-contents',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO que el widget propio escribe en español: los textos que PRO pintaba traducidos.
	'defaults' => array(
		'title'               => $digi_toc_t( 'Table of Contents' ),
		'no_headings_message' => $digi_toc_t( 'No headings were found on this page.' ),
	),
	'keep'     => array(
		'title',
		'html_tag',
		'headings_by_tags',
		'container',
		'exclude_headings_by_selector',
		'marker_view',
		'icon',
		'no_headings_message',
		'word_wrap',
		'minimize_box',
		'expand_icon',
		'collapse_icon',
		'minimized_on',
		'hierarchical_view',
		'collapse_subitems',
		'background_color',
		'border_color',
		'loader_color',
		'border_width',
		'border_radius',
		'header_separator_width',
		'padding',
		'min_height',
		'header_text_align',
		'header_background_color',
		'header_text_color',
		'toggle_button_color',
		'toggle_button_position',
		'heading_gap',
		'max_height',
		'list_indent',
		'item_text_color_normal',
		'item_text_underline_normal',
		'item_text_color_hover',
		'item_text_underline_hover',
		'item_text_hover_transition_duration',
		'item_text_color_active',
		'item_text_underline_active',
		'marker_color',
		'marker_size',
	),
	// Grupos con el mismo prefijo: sombra de la caja y tipografías.
	'groups'   => array(
		'box_shadow'        => 'box_shadow',
		'header_typography' => 'header_typography',
		'list_typography'   => 'list_typography',
	),
	'approx'   => array(
		'box_shadow_box_shadow_type' => 'La sombra se aplica a la caja de la tabla (redondeada con su radio); PRO la aplicaba al envoltorio del widget.',
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_marker' ),
	'classes'  => $digi_toc_classes,
);
