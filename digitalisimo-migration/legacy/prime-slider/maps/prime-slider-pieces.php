<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-pieces` («Pieces», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'I',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Efecto de autor: cada fila pasa a un contenedor del slider anidado con su imagen y su título.',
	'plugin'       => 'pro',
	'rows'         => 'slides',
	'fields'       => array( 'title' => 'title', 'title_link' => 'title_link', 'image' => 'image' ),
	'image'        => 'front',
	'effect'       => 'fade',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval', 'autoplay_speed' ),
		'loop'  => false,
	),
	'nav'          => array( 'arrows' => true ),
	'align'        => 'content_alignment',
	'show'         => array( 'title' => 'show_title' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'losses'       => array(
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
		__( 'alto y ancho propios de la imagen', 'digitalisimo-migration' ),
	),
);
