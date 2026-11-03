<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-elysium` («Elysium», familia C · carrusel de varias por vista) → `digitalisimo-logos`.
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
	'reason'       => 'Carrusel de varias por vista con contenido: pasa al carrusel de Logotipos (imagen, nombre, descripción y enlace) sin Swiper; si alguna fila no tiene imagen, al slider anidado.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array( 'title' => 'title', 'title_link' => 'title_link', 'text' => 'text', 'image' => 'image' ),
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
	'columns'      => array( 'key' => 'slides_per_view', 'gap' => 'space_between' ),
	'losses'       => array(
		__( '1,8 diapositivas por vista (se redondea a 2 columnas)', 'digitalisimo-migration' ),
		__( 'desenfoque y escala de las diapositivas inactivas', 'digitalisimo-migration' ),
	),
);
