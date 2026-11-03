<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-motion` («Motion», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'        => 'I',
	'decision'      => 'APROXIMAR',
	'target'        => 'digitalisimo-nested-slider',
	'fallback'      => 'slides',
	'reason'        => 'Disposición circular: cada elemento de la lista pasa a un contenedor del slider anidado con imagen, título, texto y botón.',
	'plugin'        => 'pro',
	'rows'          => 'list',
	'fields'        => array( 'image' => 'list_image', 'title' => 'list_title', 'text' => 'list_content', 'button_link' => 'button_link' ),
	'widget_fields' => array( 'button_text' => 'info_button_text' ),
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
	'losses'        => array(
		__( 'disposición circular y giro automático', 'digitalisimo-migration' ),
		__( 'animación de autor (GSAP, anime.js o Codrops)', 'digitalisimo-migration' ),
	),
);
