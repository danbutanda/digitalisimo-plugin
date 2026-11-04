<?php
/**
 * Flip Box de PRO → Tarjeta giratoria (`digitalisimo-flip-box`, modules/digitalisimo-content/widgets/class-flip-box.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones, así
 * que el mapa es `keep` y `groups`; sólo cambia el valor del interruptor «Profundidad 3D», que en PRO era el nombre de su
 * clase (`elementor-flip-box--3d`) y en el propio es la clase propia. El marcado y las clases son propios
 * (`digi-flip-box*`); las del icono y el botón son las del núcleo (`elementor-icon`, `elementor-button`), como en PRO.
 * Diferencias sin clave propia: sin enlace, el botón es un `span` (PRO pintaba un `a` con `role="button"` y `tabindex`
 * que no hacía nada) y la tarjeta es el grupo enfocable con el título del anverso como nombre; con un enlace, la tarjeta
 * gira al llegar a él con el teclado. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_fb_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_fb_classes = array(
	'.elementor-widget-flip-box'                => '.elementor-widget-digitalisimo-flip-box',
	'.elementor-flip-box__layer__overlay'       => '.digi-flip-box__overlay',
	'.elementor-flip-box__layer__inner'         => '.digi-flip-box__inner',
	'.elementor-flip-box__layer__title'         => '.digi-flip-box__title',
	'.elementor-flip-box__layer__description'   => '.digi-flip-box__description',
	'.elementor-flip-box__layer'                => '.digi-flip-box__layer',
	'.elementor-flip-box__front'                => '.digi-flip-box__front',
	'.elementor-flip-box__back'                 => '.digi-flip-box__back',
	'.elementor-flip-box__button'               => '.digi-flip-box__button',
	'.elementor-flip-box__image'                => '.digi-flip-box__image',
	'.elementor-flip-box--3d'                   => '.digi-flip-box--3d',
	'.elementor-flip-box--flipped'              => '.digi-flip-box--flipped',
	'.elementor-flip-box'                       => '.digi-flip-box',
);
foreach ( array( 'flip', 'slide', 'push', 'zoom-in', 'zoom-out', 'fade' ) as $digi_fb_x ) {
	$digi_fb_classes[ '.elementor-flip-box--effect-' . $digi_fb_x ] = '.digi-flip-box--effect-' . $digi_fb_x;
}
foreach ( array( 'left', 'right', 'up', 'down' ) as $digi_fb_x ) {
	$digi_fb_classes[ '.elementor-flip-box--direction-' . $digi_fb_x ] = '.digi-flip-box--direction-' . $digi_fb_x;
}

return array(
	'source'   => 'flip-box',
	'target'   => 'digitalisimo-flip-box',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Textos por defecto de PRO (el propio tiene los suyos, en español).
	'defaults' => array(
		'title_text_a'       => $digi_fb_t( 'This is the heading' ),
		'description_text_a' => $digi_fb_t( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' ),
		'title_text_b'       => $digi_fb_t( 'This is the heading' ),
		'description_text_b' => $digi_fb_t( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' ),
		'button_text'        => $digi_fb_t( 'Click Here' ),
	),
	'keep'     => array(
		'graphic_element', 'image', 'selected_icon', 'icon', 'title_text_a', 'description_text_a', 'title_text_b', 'description_text_b',
		'button_text', 'link', 'link_click', 'title_tag', 'description_tag', 'height', 'border_radius', 'flip_effect', 'flip_direction',
		'flip_3d', 'background_overlay_a', 'background_overlay_a_blend_mode', 'padding_a', 'alignment_a', 'vertical_position_a',
		'image_spacing', 'image_width', 'image_opacity', 'image_border_radius', 'icon_view', 'icon_shape', 'icon_spacing',
		'icon_primary_color', 'icon_secondary_color', 'icon_size', 'icon_padding', 'icon_rotate', 'icon_border_width',
		'icon_border_radius', 'title_spacing_a', 'title_color_a', 'description_color_a', 'background_overlay_b',
		'background_overlay_b_blend_mode', 'padding_b', 'alignment_b', 'vertical_position_b', 'title_spacing_b', 'title_color_b',
		'description_spacing_b', 'description_color_b', 'button_size', 'button_text_color', 'button_border_color',
		'button_hover_text_color', 'button_hover_border_color', 'button_hover_transition_duration', 'button_border_width',
		'button_border_radius',
	),
	// Grupos con el mismo prefijo: tamaño de imagen, fondos, filtros, bordes, sombras, tipografías y contornos.
	'groups'   => array(
		'image'                        => 'image',
		'background_a'                 => 'background_a',
		'background_overlay_a_filters' => 'background_overlay_a_filters',
		'border_a'                     => 'border_a',
		'shadow_a'                     => 'shadow_a',
		'image_border'                 => 'image_border',
		'title_typography_a'           => 'title_typography_a',
		'text_stroke'                  => 'text_stroke',
		'description_typography_a'     => 'description_typography_a',
		'background_b'                 => 'background_b',
		'background_overlay_b_filters' => 'background_overlay_b_filters',
		'border_b'                     => 'border_b',
		'shadow_b'                     => 'shadow_b',
		'title_typography_b'           => 'title_typography_b',
		'text_stroke_b'                => 'text_stroke_b',
		'description_typography_b'     => 'description_typography_b',
		'button_typography'            => 'button_typography',
		'button_background'            => 'button_background',
		'button_hover_background'      => 'button_hover_background',
	),
	'values'   => array(
		'flip_3d' => array( 'elementor-flip-box--3d' => 'digi-flip-box--3d' ),
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_image_style', 'heading_icon_style', 'heading_title_style_a', 'heading_description_style_a', 'heading_title_style_b', 'heading_description_style_b', 'heading_button' ),
	'classes'  => $digi_fb_classes,
);
