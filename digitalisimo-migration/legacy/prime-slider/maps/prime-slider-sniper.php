<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-sniper` («Sniper», familia B · héroe Swiper de una por vista) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'B',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Héroe Swiper de una por vista: cada fila pasa a un contenedor del slider anidado con su imagen; sin miniaturas.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array( 'sub_title' => 'sub_title', 'title' => 'title', 'title_link' => 'title_link', 'image' => 'image' ),
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
		'keys' => array( 'item_height' ),
	),
	'align'        => 'content_alignment',
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_text', 'button' => 'show_button_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'losses'       => array(
		__( 'miniaturas', 'digitalisimo-migration' ),
		__( 'diapositiva centrada con las vecinas visibles', 'digitalisimo-migration' ),
		__( 'barra de desplazamiento', 'digitalisimo-migration' ),
	),
);
