<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-prism` («Prism», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'I',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Efecto de autor: el contenido de cada fila pasa a un contenedor del slider anidado con fundido.',
	'plugin'       => 'pro',
	'rows'         => 'slides',
	'fields'       => array( 'title' => 'title', 'title_link' => 'title_link', 'text' => 'text', 'image' => 'image' ),
	'image'        => 'background',
	'effect'       => 'fade',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval', 'autoplay_speed' ),
		'loop'  => false,
	),
	'nav'          => array( 'arrows' => true ),
	'height'       => array(
		'keys' => array( 'slider_height' ),
	),
	'align'        => 'content_alignment',
	'show'         => array( 'title' => 'show_title', 'text' => 'show_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'losses'       => array(
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
		__( 'cuenta de las diapositivas', 'digitalisimo-migration' ),
		__( 'título de vista previa', 'digitalisimo-migration' ),
	),
);
