<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-cards-stack` («Cards Stack», familia H · galería de imágenes) → `media-carousel`.
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'      => 'H',
	'decision'    => 'APROXIMAR',
	'target'      => 'media-carousel',
	'fallback'    => '',
	'alt_target'  => 'digitalisimo-logos',
	'reason'      => 'Galería de imágenes: sólo imágenes → Carrusel multimedia; con títulos o textos visibles → carrusel de Logotipos.',
	'plugin'      => 'pro',
	'rows'        => 'slides',
	'fields'      => array( 'image' => 'image_front', 'title_link' => 'slide_link' ),
	'image'       => 'front',
	'speed'       => 'speed',
	'autoplay'    => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_delay', 'autoplay_speed' ),
		'hover' => 'pause_on_hover',
		'loop'  => 'loop',
	),
	'nav'         => array(),
	'skin'        => 'carousel',
	'drop_fields' => array( 'image_back' ),
	'losses'      => array(
		__( 'cara trasera de cada tarjeta', 'digitalisimo-migration' ),
		__( 'efecto de pila de tarjetas (UI Initiative)', 'digitalisimo-migration' ),
		__( 'dirección de la pila', 'digitalisimo-migration' ),
	),
);
