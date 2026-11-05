<?php
/**
 * Facebook Embed de PRO → `digitalisimo-facebook-embed` (modules/digitalisimo-services/widgets/class-facebook-embed.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto, opciones y
 * condiciones, así que el mapa es `keep`. Los avisos del panel (`ui`) no guardan valor. El plugin del servicio lleva
 * las mismas clases y `data-*` que en PRO, pero detrás de una fachada propia: nada se pide a Facebook ni se carga su SDK
 * hasta que la persona pulsa (contrato C9). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'facebook-embed',
	'target'   => 'digitalisimo-facebook-embed',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos); los del widget propio son los mismos.
	'defaults' => array(
		'type' => 'post',
	),
	'keep'     => array( 'type', 'post_url', 'video_url', 'comment_url', 'include_parent', 'show_text', 'video_allowfullscreen', 'video_autoplay', 'video_show_captions' ),
	// Avisos del panel sin valor guardado.
	'ui'       => array( 'app_id', 'app_eu_message', 'widget_exclusively_web' ),
	'classes'  => array(
		'.elementor-widget-facebook-embed'    => '.elementor-widget-digitalisimo-facebook-embed',
		'.elementor-facebook-widget' => '.digi-fb__plugin',
	),
);
