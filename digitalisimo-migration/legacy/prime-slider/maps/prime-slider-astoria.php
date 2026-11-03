<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-astoria` («Astoria», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'I',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Efecto de autor: el contenido de cada fila pasa a un contenedor del slider anidado con fundido; la animación propia se pierde.',
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
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'height'       => array(
		'keys' => array( 'slider_height' ),
	),
	'align'        => 'content_alignment',
	'overlay'      => true,
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'social'       => array( 'show' => 'show_social_icon', 'list' => 'social_link_list' ),
	'scroll'       => array( 'show' => 'show_scroll_button' ),
	'losses'       => array(
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
		__( 'estilo de la primera palabra del título', 'digitalisimo-migration' ),
	),
);
