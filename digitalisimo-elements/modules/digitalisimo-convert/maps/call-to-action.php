<?php
/**
 * Call to Action de PRO → Llamada a la acción con imagen (`digitalisimo-call-to-action`,
 * modules/digitalisimo-content/widgets/class-call-to-action.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones, así
 * que el mapa es `keep` y `groups`; sólo cambia el valor del interruptor «Animación en secuencia», que en PRO era el nombre
 * de su clase (`elementor-cta--sequenced-animation`) y en el propio es la clase propia. El marcado y las clases son propios
 * (`digi-cta*`, `digi-ribbon*`); las del icono y el botón son las del núcleo (`elementor-icon`, `elementor-button`), como en
 * PRO. Diferencias sin clave propia: la imagen de fondo es un `<img>` con `srcset` (PRO, un fondo CSS con `role="img"`), los
 * efectos al pasar el ratón también se ven con el foco del teclado dentro, el botón sin enlace es un `span` y, con
 * `prefers-reduced-motion`, no hay movimiento. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_cta_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_cta_classes = array(
	'.elementor-widget-call-to-action'    => '.elementor-widget-digitalisimo-call-to-action',
	'.elementor-cta__bg-wrapper'          => '.digi-cta__bg-wrapper',
	'.elementor-cta__bg-overlay'          => '.digi-cta__bg-overlay',
	'.elementor-cta__bg'                  => '.digi-cta__bg',
	'.elementor-cta__content-item'        => '.digi-cta__content-item',
	'.elementor-cta__content'             => '.digi-cta__content',
	'.elementor-cta__title'               => '.digi-cta__title',
	'.elementor-cta__description'         => '.digi-cta__description',
	'.elementor-cta__button-wrapper'      => '.digi-cta__button-wrapper',
	'.elementor-cta__button'              => '.digi-cta__button',
	'.elementor-cta__image'               => '.digi-cta__image',
	'.elementor-cta__icon'                => '.digi-cta__icon',
	'.elementor-cta--skin-classic'        => '.digi-cta--skin-classic',
	'.elementor-cta--skin-cover'          => '.digi-cta--skin-cover',
	'.elementor-cta--valign-top'          => '.digi-cta--valign-top',
	'.elementor-cta--valign-middle'       => '.digi-cta--valign-middle',
	'.elementor-cta--valign-bottom'       => '.digi-cta--valign-bottom',
	'.elementor-cta--sequenced-animation' => '.digi-cta--sequenced-animation',
	'.elementor-cta'                      => '.digi-cta',
	'.elementor-animated-content'         => '.digi-cta--animated-content',
	'.elementor-bg-transform'             => '.elementor-widget-digitalisimo-call-to-action',
	'.elementor-bg'                       => '.digi-cta__bg',
	'.elementor-content-item'             => '.digi-cta__content-item',
	'.elementor-ribbon-inner'             => '.digi-ribbon__inner',
	'.elementor-ribbon-left'              => '.digi-ribbon--left',
	'.elementor-ribbon-right'             => '.digi-ribbon--right',
	'.elementor-ribbon'                   => '.digi-ribbon',
);
foreach ( array( 'zoom-in', 'zoom-out', 'move-left', 'move-right', 'move-up', 'move-down' ) as $digi_cta_x ) {
	$digi_cta_classes[ '.elementor-bg-transform-' . $digi_cta_x ] = '.digi-cta--bg-' . $digi_cta_x;
}
foreach ( array( 'enter-from-right', 'enter-from-left', 'enter-from-top', 'enter-from-bottom', 'enter-zoom-in', 'enter-zoom-out', 'fade-in', 'grow', 'shrink', 'move-right', 'move-left', 'move-up', 'move-down', 'exit-to-right', 'exit-to-left', 'exit-to-top', 'exit-to-bottom', 'exit-zoom-in', 'exit-zoom-out', 'fade-out' ) as $digi_cta_x ) {
	$digi_cta_classes[ '.elementor-animated-item--' . $digi_cta_x ] = '.digi-cta__item--' . $digi_cta_x;
}

return array(
	'source'   => 'call-to-action',
	'target'   => 'digitalisimo-call-to-action',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Textos por defecto de PRO (el propio tiene los suyos, en español).
	'defaults' => array(
		'title'       => $digi_cta_t( 'This is the heading' ),
		'description' => $digi_cta_t( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' ),
		'button'      => $digi_cta_t( 'Click Here' ),
	),
	'keep'     => array(
		'skin', 'layout', 'bg_image', 'graphic_element', 'graphic_image', 'selected_icon', 'icon', 'title', 'title_tag', 'description',
		'description_tag', 'button', 'link', 'link_click', 'ribbon_title', 'ribbon_horizontal_position', 'min-height', 'alignment',
		'vertical_position', 'padding', 'image_min_width', 'image_min_height', 'graphic_image_spacing', 'graphic_image_width',
		'graphic_image_border_radius', 'icon_view', 'icon_shape', 'icon_spacing', 'icon_primary_color', 'icon_secondary_color',
		'icon_size', 'icon_padding', 'icon_border_width', 'icon_border_radius', 'title_spacing', 'description_spacing',
		'content_bg_color', 'title_color', 'description_color', 'button_color', 'content_bg_color_hover', 'title_color_hover',
		'description_color_hover', 'button_color_hover', 'button_size', 'button_text_color', 'button_background_color',
		'button_border_color', 'button_hover_text_color', 'button_hover_background_color', 'button_hover_border_color',
		'button_border_width', 'button_border_radius', 'button_padding', 'ribbon_bg_color', 'ribbon_text_color', 'ribbon_distance',
		'content_animation', 'animation_class', 'content_animation_duration', 'sequenced_animation', 'transformation',
		'overlay_color', 'overlay_blend_mode', 'overlay_color_hover', 'effect_duration',
	),
	// Grupos con el mismo prefijo: tamaños de imagen, borde, tipografías, sombras, contorno y filtros.
	'groups'   => array(
		'bg_image'               => 'bg_image',
		'graphic_image'          => 'graphic_image',
		'graphic_image_border'   => 'graphic_image_border',
		'title_typography'       => 'title_typography',
		'text_stroke'            => 'text_stroke',
		'title_text_shadow'      => 'title_text_shadow',
		'description_typography' => 'description_typography',
		'button_typography'      => 'button_typography',
		'button_text_shadow'     => 'button_text_shadow',
		'button_box_shadow'      => 'button_box_shadow',
		'ribbon_typography'      => 'ribbon_typography',
		'box_shadow'             => 'box_shadow',
		'bg_filters'             => 'bg_filters',
		'bg_filters_hover'       => 'bg_filters_hover',
	),
	'values'   => array(
		'sequenced_animation' => array( 'elementor-cta--sequenced-animation' => 'digi-cta--sequenced-animation' ),
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_bg_image_style', 'heading_style_title', 'heading_style_description', 'heading_content_colors', 'content_hover_heading', 'background_hover_heading' ),
	'classes'  => $digi_cta_classes,
);
