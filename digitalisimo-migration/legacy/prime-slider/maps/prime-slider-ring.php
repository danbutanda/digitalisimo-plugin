<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-ring` («Ring», familia I · efecto de autor) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'I',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Anillo de imágenes: cada imagen pasa a un contenedor del slider anidado con sus etiquetas como texto.',
	'plugin'       => 'pro',
	'rows'         => 'ring_slides',
	'fields'       => array( 'image' => 'image', 'title' => 'overlay_label_author', 'text' => 'overlay_label_time' ),
	'image'        => 'background',
	'effect'       => 'fade',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval', 'autoplay_speed' ),
		'loop'  => false,
	),
	'nav'          => array( 'arrows' => true ),
	'align'        => 'content_alignment',
	'show'         => array(),
	'tags'         => array(),
	'tag_defaults' => array( 'title' => 'h3' ),
	'losses'       => array(
		__( 'anillo giratorio con máscara', 'digitalisimo-migration' ),
		__( 'etiquetas superpuestas al pasar el puntero', 'digitalisimo-migration' ),
	),
);
