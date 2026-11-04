<?php
/**
 * Lottie de PRO → Animación Lottie (`digitalisimo-lottie`, modules/digitalisimo-content/widgets/class-lottie.php).
 *
 * La Animación Lottie repite los controles del Lottie de PRO con el mismo nombre, los mismos valores y las mismas
 * condiciones (y las mismas variables CSS en el envoltorio), así que todo pasa con `keep`; el marcado, las clases y el
 * script son propios (la misma librería local, cargada al acercarse a la pantalla). La descripción y el botón de
 * DIGITALÍSIMO (`digitalisimo_lottie_*`) pasan como ajustes comunes y el widget propio los pinta igual. Sin archivo
 * elegido, el widget propio muestra su animación de muestra neutra en lugar de la de PRO. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'lottie',
	'target'   => 'digitalisimo-lottie',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos).
	'defaults' => array(
		'source'              => 'media_file',
		'source_external_url' => array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ),
		'align'               => 'center',
		'caption_source'      => 'none',
		'link_to'             => 'none',
		'custom_link'         => array( 'url' => '' ),
		'trigger'             => 'arriving_to_viewport',
		'viewport'            => array( 'sizes' => array( 'start' => 0, 'end' => 100 ), 'unit' => '%' ),
		'effects_relative_to' => 'viewport',
		'loop'                => '',
		'on_hover_out'        => 'default',
		'hover_area'          => 'animation',
		'play_speed'          => array( 'size' => 1 ),
		'start_point'         => array( 'size' => 0, 'unit' => '%' ),
		'end_point'           => array( 'size' => 100, 'unit' => '%' ),
		'reverse_animation'   => '',
		'renderer'            => 'svg',
		'lazyload'            => '',
		'caption_align'       => 'center',
	),
	'keep'     => array(
		'source',
		'source_external_url',
		'source_json',
		'align',
		'caption_source',
		'caption',
		'link_to',
		'custom_link',
		'trigger',
		'viewport',
		'effects_relative_to',
		'loop',
		'number_of_times',
		'link_timeout',
		'on_hover_out',
		'hover_area',
		'play_speed',
		'start_point',
		'end_point',
		'reverse_animation',
		'renderer',
		'lazyload',
		'width',
		'space',
		'opacity',
		'opacity_hover',
		'background_hover_transition',
		'caption_align',
		'text_color',
		'caption_space',
	),
	// Grupos con el mismo prefijo: filtros CSS (normal y con el cursor) y tipografía de la leyenda.
	'groups'   => array(
		'css_filters'        => 'css_filters',
		'css_filters_hover'  => 'css_filters_hover',
		'caption_typography' => 'caption_typography',
	),
	'classes'  => array(
		'.elementor-widget-lottie'    => '.elementor-widget-digitalisimo-lottie',
		'.e-lottie__container__link'  => '.digi-lottie__link',
		'.e-lottie__container'        => '.digi-lottie__container',
		'.e-lottie__animation'        => '.digi-lottie__animation',
		'.e-lottie__caption'          => '.digi-lottie__caption',
	),
);
