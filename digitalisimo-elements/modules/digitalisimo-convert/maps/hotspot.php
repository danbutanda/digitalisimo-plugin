<?php
/**
 * Hotspot de PRO → Puntos de interés (`digitalisimo-hotspot`,
 * modules/digitalisimo-content/widgets/class-hotspot.php).
 *
 * Los Puntos de interés repiten los controles del Hotspot y los que éste hereda del widget Imagen del núcleo (los que
 * PRO no quita) con el mismo nombre, los mismos valores (también los de las animaciones, que PRO guarda como nombres de
 * clase) y las mismas condiciones, así que todo pasa con `keep`; el marcado y las clases son propios. Formato: cabecera
 * de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_hs_classes = array(
	'.elementor-widget-hotspot'   => '.elementor-widget-digitalisimo-hotspot',
	'.elementor-widget-container' => '.digi-hotspots',
	'.e-hotspot'                  => '.digi-hs',
	'.e-hotspot__button'          => '.digi-hs__button',
	'.e-hotspot__outer-circle'    => '.digi-hs__outer',
	'.e-hotspot__inner-circle'    => '.digi-hs__inner',
	'.e-hotspot__icon'            => '.digi-hs__icon',
	'.e-hotspot__label'           => '.digi-hs__label',
	'.e-hotspot__tooltip'         => '.digi-hs__tip',
	'.e-hotspot__direction-mask'  => '.digi-hs__mask',
	'.e-hotspot--active'          => '.digi-hs--active',
	'.e-hotspot--link'            => '.digi-hs--link',
	'.e-hotspot--circle'          => '.digi-hs--circle',
	'.e-hotspot--icon'            => '.digi-hs--icon',
	'.e-hotspot--no-tooltip'      => '.digi-hs--no-tip',
	'.e-hotspot--sequenced'       => '.digi-hs--sequenced',
	'.e-hotspot--soft-beat'       => '.digi-hs--soft-beat',
	'.e-hotspot--expand'          => '.digi-hs--expand',
	'.e-hotspot--overlay'         => '.digi-hs--overlay',
	'.e-hotspot--fade-in-out'     => '.digi-hs--fade-in-out',
	'.e-hotspot--fade-grow'       => '.digi-hs--fade-grow',
	'.e-hotspot--fade-direction'  => '.digi-hs--fade-direction',
	'.e-hotspot--slide-direction' => '.digi-hs--slide-direction',
	'.e-hotspot--tooltip-position' => '.digi-hs--tip-pos',
	'.e-hotspot--show-tooltip'    => '.digi-hs--show-tip',
);
foreach ( array( 'left', 'right', 'top', 'bottom' ) as $digi_hs_side ) {
	$digi_hs_classes[ '.e-hotspot--position-' . $digi_hs_side ]                      = '.digi-hs--pos-' . $digi_hs_side;
	$digi_hs_classes[ '.e-hotspot--tooltip-animation-from-' . $digi_hs_side ]          = '.digi-hs--from-' . $digi_hs_side;
	$digi_hs_classes[ '.e-hotspot--override-tooltip-animation-from-' . $digi_hs_side ] = '.digi-hs--from-' . $digi_hs_side;
}

return array(
	'source'    => 'hotspot',
	'target'    => 'digitalisimo-hotspot',
	'status'    => 'draft',
	'panel'     => 'hide',
	// Valores por defecto de PRO: el texto de la información emergente, que PRO traduce al pintarlo, y los que el
	// widget propio también tiene (para que una fila sin guardar llegue igual).
	'defaults'  => array(
		'hotspot_animation' => 'e-hotspot--expand',
		'tooltip_trigger'   => 'click',
		'tooltip_animation' => 'e-hotspot--fade-in-out',
		'tooltip_position'  => 'top',
	),
	'keep'      => array(
		// Del widget Imagen del núcleo (los que PRO no quita).
		'image',
		'align',
		'width',
		'space',
		'height',
		'object-fit',
		'object-position',
		'opacity',
		'opacity_hover',
		'background_hover_transition',
		'image_border_radius',
		// Hotspot.
		'hotspot_animation',
		'hotspot_sequenced_animation',
		'hotspot_sequenced_animation_duration',
		'tooltip_position',
		'tooltip_trigger',
		'tooltip_animation',
		'tooltip_animation_duration',
		'style_hotspot_color',
		'style_hotspot_size',
		'style_hotspot_width',
		'style_hotspot_height',
		'style_hotspot_box_color',
		'style_hotspot_padding',
		'style_hotspot_border_radius',
		'style_tooltip_text_color',
		'style_tooltip_align',
		'style_tooltip_width',
		'style_tooltip_max_width',
		'style_tooltip_padding',
		'style_tooltip_color',
		'style_tooltip_border_radius',
	),
	// Grupos con el mismo prefijo: tamaño, filtros, borde y sombra de la imagen; tipografías y sombras.
	'groups'    => array(
		'image'                    => 'image',
		'css_filters'              => 'css_filters',
		'css_filters_hover'        => 'css_filters_hover',
		'image_border'             => 'image_border',
		'image_box_shadow'         => 'image_box_shadow',
		'style_typography'         => 'style_typography',
		'style_hotspot_box_shadow' => 'style_hotspot_box_shadow',
		'style_tooltip_typography' => 'style_tooltip_typography',
		'style_tooltip_box_shadow' => 'style_tooltip_box_shadow',
	),
	'repeaters' => array(
		'hotspot' => array(
			'target'       => 'hotspot',
			'defaults'     => array(
				'hotspot_tooltip_content' => function_exists( 'esc_html__' ) ? esc_html__( 'Add Your Tooltip Text Here', 'elementor-pro' ) : 'Add Your Tooltip Text Here',
			),
			// Repetidor sin guardar: la fila de muestra de PRO (un círculo con la información emergente de muestra).
			'default_rows' => array( array() ),
			'keep'         => array(
				'hotspot_label',
				'hotspot_link',
				'hotspot_icon',
				'hotspot_icon_position',
				'hotspot_icon_spacing',
				'hotspot_custom_size',
				'hotspot_width',
				'hotspot_height',
				'hotspot_tooltip_content',
				'hotspot_horizontal',
				'hotspot_offset_x',
				'hotspot_vertical',
				'hotspot_offset_y',
				'hotspot_tooltip_position',
				'hotspot_position',
				'hotspot_tooltip_width',
				'hotspot_tooltip_text_wrap',
			),
		),
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'hotspot_heading', 'style_tooltip_heading' ),
	'classes'   => $digi_hs_classes,
);
