<?php
/**
 * Facebook Comments de PRO → `digitalisimo-facebook-comments` (modules/digitalisimo-services/widgets/class-facebook-comments.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto, opciones y
 * condiciones, así que el mapa es `keep`. Los avisos del panel (`ui`) no guardan valor. El plugin del servicio lleva
 * las mismas clases y `data-*` que en PRO, pero detrás de una fachada propia: nada se pide a Facebook ni se carga su SDK
 * hasta que la persona pulsa (contrato C9). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'facebook-comments',
	'target'   => 'digitalisimo-facebook-comments',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos); los del widget propio son los mismos.
	'defaults' => array(
		'comments_number' => '10',
		'order_by'        => 'social',
		'url_type'        => 'current_page',
		'url_format'      => 'plain',
	),
	'keep'     => array( 'comments_number', 'order_by', 'url_type', 'url_format', 'url' ),
	// Avisos del panel sin valor guardado.
	'ui'       => array( 'app_deprecated' ),
	'classes'  => array(
		'.elementor-widget-facebook-comments'    => '.elementor-widget-digitalisimo-facebook-comments',
		'.elementor-facebook-widget' => '.digi-fb__plugin',
	),
);
