<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-fortune` («Fortune», familia B · héroe Swiper de una por vista) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'B',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Héroe Swiper: cada fila pasa a un contenedor del slider anidado; la fracción pasa al contador y la dirección vertical a horizontal.',
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
	'image'        => 'front',
	'effect_key'   => 'swiper_effect',
	'speed'        => 'speed',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_speed' ),
		'hover' => 'pauseonhover',
		'loop'  => 'loop',
	),
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots', 'fraction' => 'show_navigation_fraction' ),
	'height'       => array(
		'keys' => array( 'item_height' ),
	),
	'align'        => 'content_alignment',
	'overlay'      => true,
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_text', 'button' => 'show_button_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'losses'       => array(
		__( 'dirección vertical (pasa a horizontal)', 'digitalisimo-migration' ),
		__( 'transiciones con licencia de terceros (se usan deslizar o fundido)', 'digitalisimo-migration' ),
	),
);
