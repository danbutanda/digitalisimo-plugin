<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-reveal` («Reveal», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'I',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Efecto de autor: cada fila pasa a un contenedor del slider anidado; el contenido fijo de la cabecera pasa a un título y un texto antes del slider.',
	'plugin'       => 'pro',
	'rows'         => 'slides',
	'fields'       => array(
		'sub_title'   => 'sub_title',
		'title'       => 'title',
		'title_link'  => 'title_link',
		'text'        => 'text',
		'button_text' => 'slide_button_text',
		'button_link' => 'button_link',
		'image'       => 'image',
	),
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
	'overlay'      => true,
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_text', 'button' => 'show_readmore' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'static'       => array( 'show' => 'show_head_static_content', 'title' => 'head_static_content_title', 'text' => 'head_static_content_text' ),
	'drop_fields'  => array( 'image_caption' ),
	'losses'       => array(
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
		__( 'pie de cada imagen', 'digitalisimo-migration' ),
	),
);
