<?php
/**
 * Progress Tracker de PRO (`progress-tracker`) → Indicador de progreso (`digitalisimo-progress-tracker`,
 * modules/digitalisimo-content/widgets/class-progress-tracker.php).
 *
 * El Indicador de progreso repite los controles del widget de PRO con el mismo nombre, los mismos valores por defecto,
 * las mismas condiciones y las mismas variables CSS, así que el mapa es todo `keep` y `groups`. Formato: cabecera de
 * `class-translator.php`.
 *
 * Diferencias que no son de clave (no generan código): con «Scroll Snap» se mide el desplazamiento del `body` sin
 * envolver la página, un selector que no existe deja el indicador a cero y el indicador va con `aria-hidden`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'progress-tracker',
	'target'   => 'digitalisimo-progress-tracker',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de PRO que el traductor necesita para saber si el sitio los cambió.
	'defaults' => array(
		'type'                            => 'horizontal',
		'relative_to'                     => 'entire_page',
		'percentage'                      => 'no',
		'horizontal_border_style'         => 'none',
		'horizontal_tracker_border_style' => 'none',
	),
	'keep'     => array(
		'type',
		'relative_to',
		'selector',
		'direction',
		'percentage',
		'percentage_position',
		'circular_size',
		'circular_color',
		'circular_width',
		'align',
		'horizontal_border_style',
		'horizontal_border_width',
		'horizontal_border_color',
		'horizontal_border_radius',
		'circular_background_color',
		'circular_background_width',
		'horizontal_height',
		'horizontal_tracker_border_style',
		'horizontal_tracker_border_width',
		'horizontal_tracker_border_color',
		'horizontal_tracker_border_radius',
		'horizontal_padding',
		'percentage_color',
	),
	// Fondos (del avance y de la pista), sombra de la barra y tipografía y sombra del porcentaje.
	'groups'   => array(
		'horizontal_color'            => 'horizontal_color',
		'horizontal_background_color' => 'horizontal_background_color',
		'box_shadow'                  => 'box_shadow',
		'percentage_typography'       => 'percentage_typography',
		'percentage_text_shadow'      => 'percentage_text_shadow',
	),
	// La alineación pasa tal cual (`keep`); sólo se informa la de un dispositivo distinto del escritorio.
	'approx'   => array_fill_keys(
		array( 'align_widescreen', 'align_laptop', 'align_tablet_extra', 'align_tablet', 'align_mobile_extra', 'align_mobile' ),
		'En PRO sólo contaba la alineación del escritorio (como clase, en todos los tamaños); el indicador propio aplica la de cada dispositivo.'
	),
	// Encabezados y nota del panel: no guardan valor.
	'ui'       => array( 'relative_to_description', 'heading_progress_style', 'heading_tracker_background_style', 'heading_percentage_style' ),
	'classes'  => array(
		'.elementor-widget-progress-tracker'           => '.elementor-widget-digitalisimo-progress-tracker',
		'.elementor-scrolling-tracker'                 => '.digi-progress-tracker',
		'.elementor-scrolling-tracker-horizontal'      => '.digi-progress-tracker--horizontal',
		'.elementor-scrolling-tracker-circular'        => '.digi-progress-tracker--circular',
		'.current-progress-percentage'                 => '.digi-progress-tracker__percentage',
		'.current-progress'                            => '.digi-progress-tracker__progress',
		'.circle'                                      => '.digi-progress-tracker__track',
	),
);
