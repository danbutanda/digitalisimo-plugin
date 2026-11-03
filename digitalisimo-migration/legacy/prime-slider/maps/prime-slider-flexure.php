<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-flexure` («Flexure», familia J · paneles) → `digitalisimo-fancy-tabs`.
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'J',
	'decision' => 'APROXIMAR',
	'target'   => 'digitalisimo-fancy-tabs',
	'fallback' => '',
	'reason'   => 'Paneles al pasar el puntero: pasan a Pestañas destacadas con sus columnas; las redes van junto a ellas.',
	'plugin'   => 'pro',
	'rows'     => 'ps_slider',
	'fields'   => array( 'title' => 'ps_flexure_title', 'image' => 'slide_image', 'title_link' => 'title_link', 'text' => 'ps_flexure_content' ),
	'image'    => 'front',
	'columns'  => array( 'key' => 'columns' ),
	'active'   => '_active_item',
	'social'   => array( 'show' => 'show_social_share', 'list' => 'social_link_list' ),
	'losses'   => array(
		__( 'activación al pasar el puntero (pasa a pestañas)', 'digitalisimo-migration' ),
		__( 'fondo a pantalla completa', 'digitalisimo-migration' ),
		__( 'efectos de la imagen', 'digitalisimo-migration' ),
	),
);
