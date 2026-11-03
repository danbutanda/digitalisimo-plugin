<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-mount` («Mount», familia A · héroe UIkit) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'A',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Héroe UIkit sin extracto ni botón: cada fila pasa a un contenedor del slider anidado; las redes van junto al slider.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array( 'sub_title' => 'sub_title', 'title' => 'title', 'title_link' => 'title_link', 'image' => 'image' ),
	'image'        => 'background',
	'effect'       => 'fade',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval' ),
		'hover' => 'pause_on_hover',
		'loop'  => 'finite',
	),
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'height'       => array( 'ratio' => true, 'min' => 460 ),
	'align'        => 'content_alignment',
	'overlay'      => true,
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'social'       => array( 'show' => 'show_social_share', 'list' => 'social_link_list' ),
	'losses'       => array(
		__( 'velocidad de la animación de UIkit', 'digitalisimo-migration' ),
		__( 'estilo de la primera palabra del título', 'digitalisimo-migration' ),
		__( 'navegación numerada', 'digitalisimo-migration' ),
	),
);
