<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-super-flow` («Super Flow», familia H · galería de imágenes) → `media-carousel`.
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
	'fields'     => array( 'image' => 'image', 'title' => 'slide_title', 'title_link' => 'title_link' ),
	'image'      => 'front',
	'speed'      => 'speed',
	'autoplay'   => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_delay', 'autoplay_speed' ),
		'hover' => 'pause_on_hover',
		'loop'  => 'loop',
	),
	'nav'        => array( 'dots' => 'show_pagination' ),
	'show'       => array( 'title' => 'show_title' ),
	'skin'       => 'carousel',
	'losses'     => array(
		__( 'fragmentos de la imagen (efecto propio)', 'digitalisimo-migration' ),
	),
);
