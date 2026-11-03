<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-cloud` («Cloud», familia H · galería de imágenes) → `media-carousel`.
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'     => 'H',
	'decision'   => 'APROXIMAR',
	'target'     => 'media-carousel',
	'fallback'   => '',
	'alt_target' => 'digitalisimo-logos',
	'reason'     => 'Galería de imágenes: sólo imágenes → Carrusel multimedia; con títulos o textos visibles → carrusel de Logotipos.',
	'plugin'     => 'pro',
	'rows'       => 'slides',
	'fields'     => array( 'image' => 'image', 'title' => 'title', 'title_link' => 'link_url', 'text' => 'description' ),
	'image'      => 'front',
	'speed'      => 'speed',
	'autoplay'   => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_speed' ),
		'hover' => 'pauseonhover',
		'loop'  => 'loop',
	),
	'nav'        => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'show'       => array( 'title' => 'show_title', 'text' => 'show_text' ),
	'skin'       => 'coverflow',
	'losses'     => array(
		__( 'efecto de nube con opacidad y escala de las laterales', 'digitalisimo-migration' ),
	),
);
