<?php
/**
 * Post Excerpt de PRO → Extracto (`digitalisimo-post-excerpt`,
 * modules/digitalisimo-theme-elements/widgets/class-post-excerpt.php).
 *
 * El Extracto repite los controles del de PRO con el mismo nombre, valor por defecto y selectores (texto con la etiqueta
 * dinámica `post-excerpt` por defecto), así que todo pasa con `keep`. El envoltorio cambia
 * `elementor-widget-theme-post-excerpt` por `elementor-widget-digitalisimo-post-excerpt`. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'theme-post-excerpt',
	'target'   => 'digitalisimo-post-excerpt',
	'status'   => 'draft',
	'panel'    => 'hide',
	'keep'     => array( 'excerpt', 'align', 'paragraph_spacing', 'title_color', 'link_color', 'link_hover_color', 'link_hover_color_transition_duration' ),
	'groups'   => array(
		'typography'  => 'typography',
		'text_shadow' => 'text_shadow',
	),
	// Controles sin valor (separador).
	'ui'       => array( 'separator' ),
	'classes'  => array( '.elementor-widget-theme-post-excerpt' => '.elementor-widget-digitalisimo-post-excerpt' ),
);
