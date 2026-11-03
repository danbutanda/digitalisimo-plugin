<?php
/**
 * Prime Slider Lite 4.7.0 · `prime-slider-isolate` («Isolate», familia A · héroe UIkit) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'       => 'A',
	'decision'     => 'APROXIMAR',
	'target'       => 'digitalisimo-nested-slider',
	'fallback'     => 'slides',
	'reason'       => 'Héroe UIkit con la imagen delante del texto: cada fila pasa a un contenedor con título, extracto, botón e imagen; el botón de reproducción pasa a un botón con enlace.',
	'plugin'       => 'lite',
	'rows'         => 'slides',
	'fields'       => array(
		'sub_title'   => 'sub_title',
		'title'       => 'title',
		'title_link'  => 'title_link',
		'text'        => 'excerpt',
		'button_text' => 'slide_button_text',
		'button_link' => 'button_link',
		'image'       => 'image',
	),
	'image'        => 'front',
	'play'         => array(
		'show'    => 'show_play_button',
		'type'    => 'image_link_type',
		'default' => 'youtube',
		'keys'    => array(
			'video'      => 'image_link_video',
			'youtube'    => 'lightbox_link',
			'vimeo'      => 'image_link_vimeo',
			'google_map' => 'image_link_google_map',
			'website'    => 'image_link_website',
		),
	),
	'effect'       => 'fade',
	'autoplay'     => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval' ),
		'hover' => 'pause_on_hover',
		'loop'  => 'finite',
	),
	'nav'          => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'height'       => array( 'ratio' => true, 'min' => 600 ),
	'align'        => 'content_alignment',
	'overlay'      => true,
	'show'         => array( 'sub_title' => 'show_sub_title', 'title' => 'show_title', 'text' => 'show_excerpt', 'button' => 'show_button_text' ),
	'tags'         => array( 'title' => 'title_html_tag', 'sub_title' => 'sub_title_html_tag' ),
	'tag_defaults' => array( 'title' => 'h1', 'sub_title' => 'h4' ),
	'social'       => array( 'show' => 'show_social_icon', 'list' => 'social_link_list' ),
	'scroll'       => array( 'show' => 'show_scroll_button' ),
	'losses'       => array(
		__( 'paralaje del contenido', 'digitalisimo-migration' ),
		__( 'velocidad de la animación de UIkit', 'digitalisimo-migration' ),
		__( 'igualar la altura de la imagen', 'digitalisimo-migration' ),
		__( 'diseño propio de los estilos locate y slice', 'digitalisimo-migration' ),
		__( 'estilo de la primera palabra del título', 'digitalisimo-migration' ),
	),
);
