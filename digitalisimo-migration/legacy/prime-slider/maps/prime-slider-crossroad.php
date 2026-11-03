<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-crossroad` («Crossroad», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'I',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Efecto de autor: cada fila pasa a un contenedor del slider anidado; la ventana modal y el contador se pierden.',
	'plugin'       => 'pro',
	'rows'         => 'slides',
	'fields'       => array(
		'sub_title'   => 'meta_text',
		'title'       => 'title',
		'text'        => 'excerpt',
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
	'show'         => array( 'sub_title' => 'show_meta_text', 'title' => 'show_title', 'text' => 'show_excerpt', 'button' => 'show_button' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'drop_fields'  => array( 'modal_title', 'modal_meta_text', 'modal_image' ),
	'losses'       => array(
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
		__( 'ventana modal con el detalle', 'digitalisimo-migration' ),
		__( 'contador de las diapositivas', 'digitalisimo-migration' ),
	),
);
