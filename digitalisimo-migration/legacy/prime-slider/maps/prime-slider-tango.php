<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-tango` («Tango», familia C · carrusel de varias por vista) → `digitalisimo-logos`.
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'C',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-logos',
	'fallback'     => '',
	'alt_target'   => 'digitalisimo-nested-slider',
	'reason'       => 'Carrusel de varias por vista con contenido: pasa al carrusel de Logotipos con sus columnas; si alguna fila no tiene imagen, al slider anidado.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array( 'sub_title' => 'sub_title', 'title' => 'title', 'title_link' => 'title_link', 'image' => 'image' ),
	'image'        => 'front',
	'effect'       => 'slide',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_speed' ),
		'hover' => 'pauseonhover',
		'loop'  => 'loop',
	),
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'align'        => 'content_alignment',
	'show'         => array( 'title' => 'show_title', 'sub_title' => 'show_sub_title', 'text' => 'show_text' ),
	'tags'         => array( 'title' => 'title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h3', 'sub_title' => 'h4' ),
	'columns'      => array( 'key' => 'columns', 'gap' => 'item_gap' ),
	'losses'       => array(
		__( 'efecto coverflow', 'digitalisimo-migration' ),
		__( 'sombras de las diapositivas', 'digitalisimo-migration' ),
	),
);
