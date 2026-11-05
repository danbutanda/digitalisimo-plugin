<?php
/**
 * Facebook Page de PRO → `digitalisimo-facebook-page` (modules/digitalisimo-services/widgets/class-facebook-page.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto, opciones y
 * condiciones, así que el mapa es `keep`. Los avisos del panel (`ui`) no guardan valor. El plugin del servicio lleva
 * las mismas clases y `data-*` que en PRO, pero detrás de una fachada propia: nada se pide a Facebook ni se carga su SDK
 * hasta que la persona pulsa (contrato C9). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'facebook-page',
	'target'   => 'digitalisimo-facebook-page',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos); los del widget propio son los mismos.
	'defaults' => array(
		'tabs'          => array( 'timeline' ),
		'show_cover'    => 'yes',
		'show_facepile' => 'yes',
		'show_cta'      => 'yes',
		'height'        => array( 'unit' => 'px', 'size' => 500 ),
	),
	'keep'     => array( 'url', 'tabs', 'small_header', 'show_cover', 'show_facepile', 'show_cta', 'height' ),
	// Avisos del panel sin valor guardado.
	'ui'       => array( 'app_id', 'app_eu_message', 'widget_exclusively_web' ),
	'classes'  => array(
		'.elementor-widget-facebook-page'    => '.elementor-widget-digitalisimo-facebook-page',
		'.elementor-facebook-widget' => '.digi-fb__plugin',
	),
);
