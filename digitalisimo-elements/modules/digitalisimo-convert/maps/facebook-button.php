<?php
/**
 * Facebook Button de PRO → `digitalisimo-facebook-button` (modules/digitalisimo-services/widgets/class-facebook-button.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto, opciones y
 * condiciones, así que el mapa es `keep`. Los avisos del panel (`ui`) no guardan valor. El plugin del servicio lleva
 * las mismas clases y `data-*` que en PRO, pero detrás de una fachada propia: nada se pide a Facebook ni se carga su SDK
 * hasta que la persona pulsa (contrato C9). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'facebook-button',
	'target'   => 'digitalisimo-facebook-button',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos); los del widget propio son los mismos.
	'defaults' => array(
		'type'         => 'like',
		'layout'       => 'standard',
		'size'         => 'small',
		'color_scheme' => 'light',
		'url_type'     => 'current_page',
		'url_format'   => 'plain',
	),
	'keep'     => array( 'type', 'layout', 'size', 'color_scheme', 'show_share', 'show_faces', 'url_type', 'url_format', 'url' ),
	// Avisos del panel sin valor guardado.
	'ui'       => array( 'app_deprecated' ),
	'classes'  => array(
		'.elementor-widget-facebook-button'    => '.elementor-widget-digitalisimo-facebook-button',
		'.elementor-facebook-widget' => '.digi-fb__plugin',
	),
);
