<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-omatic` («Omatic», familia B · héroe Swiper de una por vista) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'B',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Héroe Swiper de una por vista: cada fila pasa a un contenedor del slider anidado con su imagen; los efectos 3D pasan a deslizar.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array(
		'sub_title'  => 'sub_title',
		'title'      => 'title',
		'title_link' => 'title_link',
		'text'       => 'text',
		'image'      => 'image',
	),
	'image'        => 'front',
	'effect_key'   => 'swiper_effect',
	'speed'        => 'speed',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_speed' ),
		'hover' => 'pauseonhover',
		'loop'  => 'loop',
	),
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'height'       => array(
		'keys' => array( 'slider_height' ),
	),
	'align'        => 'content_alignment',
	'overlay'      => true,
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_text', 'button' => 'show_button_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'losses'       => array(
		__( 'efecto cubo por defecto (se desliza)', 'digitalisimo-migration' ),
		__( 'efecto de vidrio esmerilado', 'digitalisimo-migration' ),
		__( 'líneas decorativas del subtítulo y del texto', 'digitalisimo-migration' ),
	),
);
