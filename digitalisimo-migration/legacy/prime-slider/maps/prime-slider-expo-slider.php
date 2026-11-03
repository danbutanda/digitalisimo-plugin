<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-expo-slider` («Expo», familia H · galería de imágenes) → `media-carousel`.
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
	'rows'       => 'expo_slides',
	'fields'     => array( 'image' => 'expo_image', 'title' => 'expo_title', 'title_link' => 'expo_link' ),
	'image'      => 'front',
	'speed'      => 'expo_speed',
	'autoplay'   => array(
		'on'    => 'expo_autoplay',
		'delay' => array( 'expo_autoplay_delay' ),
		'loop'  => 'expo_loop',
	),
	'nav'        => array( 'arrows' => 'expo_show_demo_nav' ),
	'skin'       => 'carousel',
	'per_view'   => 'expo_slides_per_view',
	'losses'     => array(
		__( 'efecto Expo (UI Initiative)', 'digitalisimo-migration' ),
		__( 'dirección vertical', 'digitalisimo-migration' ),
		__( 'escala, gris y giro de las imágenes', 'digitalisimo-migration' ),
	),
);
