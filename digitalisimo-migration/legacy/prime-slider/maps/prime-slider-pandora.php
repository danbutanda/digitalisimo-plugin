<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-pandora` («Pandora», familia J · paneles) → `digitalisimo-fancy-tabs`.
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'J',
	'decision' => 'APROXIMAR',
	'target'   => 'digitalisimo-fancy-tabs',
	'fallback' => '',
	'reason'   => 'Paneles al pasar el puntero: pasan a Pestañas destacadas (pestaña = título; panel = imagen y contenido).',
	'plugin'   => 'pro',
	'rows'     => 'items',
	'fields'   => array( 'sub_title' => 'sub_title', 'title' => 'title', 'title_link' => 'title_link', 'image' => 'image' ),
	'image'    => 'front',
	'active'   => 'hover_active_item',
	'losses'   => array(
		__( 'activación al pasar el puntero (pasa a pestañas)', 'digitalisimo-migration' ),
		__( 'fondo a pantalla completa', 'digitalisimo-migration' ),
	),
);
