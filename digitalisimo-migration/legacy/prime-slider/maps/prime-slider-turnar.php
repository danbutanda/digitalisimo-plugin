<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-turnar` («Turnar», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'        => 'I',
	'decision'      => 'APROXIMAR',
	'target'        => 'digitalisimo-nested-slider',
	'fallback'      => 'slides',
	'reason'        => 'Disposición circular: cada elemento pasa a un contenedor del slider anidado con imagen, título, texto y botón; la imagen central se pierde.',
	'plugin'        => 'pro',
	'rows'          => 'turnar_items',
	'fields'        => array( 'image' => 'turnar_image', 'title' => 'turnar_title', 'text' => 'turnar_description', 'button_link' => 'turnar_url' ),
	'widget_fields' => array( 'button_text' => 'turnar_button_text' ),
	'image'         => 'front',
	'effect'        => 'fade',
	'autoplay'      => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval', 'autoplay_speed' ),
		'loop'  => false,
	),
	'nav'           => array( 'arrows' => true ),
	'align'         => 'content_alignment',
	'show'          => array(),
	'tags'          => array(),
	'tag_defaults'  => array( 'title' => 'h3' ),
	'drop'          => array( 'turnar_center_image' ),
	'losses'        => array(
		__( 'círculo giratorio', 'digitalisimo-migration' ),
		__( 'imagen central', 'digitalisimo-migration' ),
		__( 'ventana modal', 'digitalisimo-migration' ),
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
	),
);
