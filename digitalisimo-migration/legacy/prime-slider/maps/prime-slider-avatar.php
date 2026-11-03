<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-avatar` («Avatar», familia B · héroe Swiper de una por vista) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'B',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Héroe Swiper: cada fila pasa a un contenedor del slider anidado; la reproducción automática «no» por defecto se mantiene apagada.',
	'plugin'       => 'pro',
	'rows'         => 'slides',
	'fields'       => array(
		'title'       => 'title',
		'title_link'  => 'title_link',
		'text'        => 'excerpt',
		'button_text' => 'slide_button_text',
		'button_link' => 'button_link',
		'image'       => 'image',
	),
	'image'        => 'front',
	'effect'       => 'slide',
	'speed'        => 'speed',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_speed' ),
		'hover' => 'pauseonhover',
		'loop'  => 'loop',
	),
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'height'       => array(
		'keys' => array( 'slider_min_height' ),
	),
	'align'        => 'content_alignment',
	'show'         => array( 'title' => 'show_title', 'text' => 'show_excerpt', 'button' => 'show_button_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'losses'       => array(
		__( 'animación del texto letra a letra (Splitting)', 'digitalisimo-migration' ),
		__( 'miniaturas de las diapositivas', 'digitalisimo-migration' ),
	),
);
