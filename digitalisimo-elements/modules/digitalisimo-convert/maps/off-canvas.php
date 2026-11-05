<?php
/**
 * Off-Canvas anidado de PRO (`off-canvas`) → Panel fuera de lienzo (`digitalisimo-nested-off-canvas`,
 * modules/digitalisimo-navigation/widgets/class-nested-off-canvas.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto y las mismas
 * condiciones, así que el mapa es todo `keep` y `groups`. El contenedor hijo (el contenido del panel) no se toca:
 * `Converter::tree()` sólo cambia `widgetType` y los ajustes del widget, y conserva los hijos con su `id`. El `id` del
 * elemento tampoco cambia, así que los enlaces y botones con la etiqueta dinámica «Off-Canvas» (o el enlace de acción
 * `off_canvas:open|close|toggle` que imprime) y las acciones F7b que lo nombraban siguen abriéndolo. Formato: cabecera
 * de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_oc_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'off-canvas',
	'target'   => 'digitalisimo-nested-off-canvas',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de PRO que el traductor necesita (el nombre es el nombre accesible del diálogo).
	'defaults' => array(
		'off_canvas_name' => $digi_oc_t( 'Off-Canvas' ),
	),
	'keep'     => array(
		// Diseño.
		'editing_mode',
		'off_canvas_name',
		'horizontal_position',
		'vertical_position',
		'width',
		'height',
		'custom_height',
		// Ajustes.
		'entrance_animation',
		'exit_animation',
		'offcanvas_animation_duration',
		'is_not_close_on_overlay',
		'is_not_close_on_esc_overlay',
		'prevent_scroll',
		'wrapper_html_tag',
		// Estilo.
		'border_radius',
		'has_overlay',
	),
	// Controles de grupo con el mismo prefijo en el widget propio.
	'groups'   => array(
		'background'         => 'background',
		'border'             => 'border',
		'box_shadow'         => 'box_shadow',
		'overlay_background' => 'overlay_background',
	),
	// Aviso y encabezados del panel: no guardan valor.
	'ui'       => array(
		'learn_more_alert',
		'animation',
		'interactions',
	),
	// Clases de PRO en el CSS personalizado del elemento.
	'classes'  => array(
		'.elementor-widget-off-canvas' => '.elementor-widget-digitalisimo-nested-off-canvas',
		'.e-off-canvas__overlay'       => '.digi-noc__overlay',
		'.e-off-canvas__main'          => '.digi-noc__main',
		'.e-off-canvas__content'       => '.digi-noc__content',
		'.e-off-canvas'                => '.digi-noc',
		'.e-off-canvas__no-scroll'     => '.digi-noc-lock',
	),
);
