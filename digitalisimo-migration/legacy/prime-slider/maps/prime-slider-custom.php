<?php
/**
 * Prime Slider Pro 4.7.0 · `prime-slider-custom` («Custom», familia D · plantillas) → `digitalisimo-nested-slider` (respaldo: `slides`).
 *
 * Mapa del frente E de F8 (§3.E.1, V.10.7): lo leen `Maps::get()` y `Static_Families`. Sin clase adaptadora (F8-D18).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'     => 'D',
	'decision'   => 'ADAPTAR',
	'target'     => 'digitalisimo-nested-slider',
	'fallback'   => 'slides',
	'reason'     => 'Plantillas por diapositiva: cada fila pasa a un contenedor del slider anidado con un widget Plantilla; en el respaldo, Diapositivas con su plantilla.',
	'plugin'     => 'pro',
	'rows'       => 'sliders',
	'fields'     => array( 'label' => 'tab_title', 'template_id' => 'template_id' ),
	'image'      => 'none',
	'templates'  => true,
	'effect_key' => 'slider_animations',
	'autoplay'   => array(
		'on'    => 'autoplay',
		'delay' => array( 'autoplay_interval' ),
		'hover' => 'pause_on_hover',
		'loop'  => 'finite',
	),
	'nav'        => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
	'arrows'     => 'positions',
	'height'     => array( 'ratio' => true, 'min' => 480 ),
	'social'     => array( 'show' => 'show_social_icon', 'list' => 'social_link_list' ),
	'scroll'     => array( 'show' => 'scroll_to_section', 'section' => 'section_id', 'icon' => 'slider_scroll_to_section_icon' ),
	'losses'     => array(
		__( 'estilos propios de las flechas', 'digitalisimo-migration' ),
		__( 'velocidad de la animación de UIkit', 'digitalisimo-migration' ),
	),
);
