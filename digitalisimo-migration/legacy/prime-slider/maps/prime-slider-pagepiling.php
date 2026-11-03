<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-pagepiling` («Pagepiling», familia K · pantalla completa) → `container` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'K',
	'decision'     => 'APROXIMAR',
	'target'       => 'container',
	'fallback'     => 'slides',
	'alt_target'   => 'digitalisimo-nested-slider',
	'reason'       => 'Pantalla completa: en un contenedor, un contenedor de 100 vh por fila con desplazamiento nativo; en una columna, el slider anidado.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array(
		'sub_title'   => 'sub_title',
		'title'       => 'title',
		'title_link'  => 'title_link',
		'text'        => 'excerpt',
		'button_text' => 'slide_button_text',
		'button_link' => 'button_link',
		'image'       => 'slide_image',
	),
	'image'        => 'background',
	'play'         => array( 'show' => 'show_play_button', 'key' => 'lightbox_link' ),
	'effect'       => 'slide',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_speed', 'autoplay_duration' ),
		'loop'  => false,
	),
	'nav'          => array( 'dots' => true ),
	'align'        => 'slide_text_align',
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_excerpt', 'button' => 'show_button_text' ),
	'tags'         => array( 'title' => 'title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'social'       => array( 'show' => 'show_social_icon', 'list' => 'social_link_list' ),
	'losses'       => array(
		__( 'secuestro del desplazamiento de la página (se gana el nativo)', 'digitalisimo-migration' ),
		__( 'reproducción automática por secciones', 'digitalisimo-migration' ),
		__( 'estilo de la primera palabra del título', 'digitalisimo-migration' ),
	),
);
