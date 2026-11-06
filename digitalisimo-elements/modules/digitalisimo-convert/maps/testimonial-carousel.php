<?php
/**
 * Testimonial Carousel de PRO → Carrusel de testimonios (`digitalisimo-testimonial-carousel`,
 * modules/digitalisimo-carousel/widgets/class-testimonial-carousel.php).
 *
 * El widget propio repite los controles del de PRO y los de su base (`modules/carousel/widgets/base.php`) con el mismo
 * nombre, los mismos valores y las mismas condiciones, y quita los mismos que PRO (efecto, altura y posición de la
 * paginación), así que el mapa es `keep` y `groups`. El marcado y las clases son propios (motor `digi-crs`, sin
 * Swiper): las clases de presentación de PRO (`elementor-testimonial--skin-*`, `--layout-*`, `--align-*` con su
 * dispositivo) pasan a sus equivalentes `digi-testimonial--*`, en el mismo nodo (el envoltorio del widget).
 *
 * Aproximaciones: las del motor (el bucle rebobina; la carga diferida es la nativa). Diferencia sin clave propia: con la
 * piel «Burbuja» y la alineación a la derecha, el pico apunta a la imagen (PRO lo dejaba a la izquierda salvo que se fijara
 * el tamaño de la imagen). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_tc_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};
$digi_tc_placeholder = class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'get_placeholder_image_src' ) ? (string) \Elementor\Utils::get_placeholder_image_src() : '';
$digi_tc_row         = array(
	'content' => $digi_tc_t( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.' ),
	'name'    => $digi_tc_t( 'John Doe' ),
	'title'   => $digi_tc_t( 'CEO' ),
	'image'   => array( 'url' => $digi_tc_placeholder ),
);

$digi_tc_classes = array(
	'.elementor-widget-testimonial-carousel' => '.elementor-widget-digitalisimo-testimonial-carousel',
	'.elementor-main-swiper'                 => '.digi-testimonials',
	'.elementor-testimonial'                 => '.digi-testimonial',
	'.elementor-testimonial__content'        => '.digi-testimonial__content',
	'.elementor-testimonial__text'           => '.digi-testimonial__text',
	'.elementor-testimonial__footer'         => '.digi-testimonial__footer',
	'.elementor-testimonial__image'          => '.digi-testimonial__image',
	'.elementor-testimonial__cite'           => '.digi-testimonial__cite',
	'.elementor-testimonial__name'           => '.digi-testimonial__name',
	'.elementor-testimonial__title'          => '.digi-testimonial__title',
	'.elementor-testimonial--skin-default'   => '.digi-testimonial--skin-default',
	'.elementor-testimonial--skin-bubble'    => '.digi-testimonial--skin-bubble',
);
foreach ( array( 'image_inline', 'image_stacked', 'image_above', 'image_left', 'image_right' ) as $digi_tc_layout ) {
	$digi_tc_classes[ '.elementor-testimonial--layout-' . $digi_tc_layout ] = '.digi-testimonial--layout-' . $digi_tc_layout;
}
foreach ( array( '', '-widescreen', '-laptop', '-tablet_extra', '-tablet', '-mobile_extra', '-mobile' ) as $digi_tc_device ) {
	foreach ( array( 'left', 'center', 'right' ) as $digi_tc_align ) {
		$digi_tc_classes[ '.elementor-testimonial-' . $digi_tc_device . '-align-' . $digi_tc_align ] = '.digi-testimonial-' . $digi_tc_device . '-align-' . $digi_tc_align;
	}
}
// Clases comunes de los carruseles de PRO (Swiper, flechas y paginación) → motor propio (frente E).
$digi_tc_common = is_file( __DIR__ . '/_carousel-classes.php' ) ? (array) require __DIR__ . '/_carousel-classes.php' : array();

return array(
	'source'    => 'testimonial-carousel',
	'target'    => 'digitalisimo-testimonial-carousel',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Valores por defecto de PRO: los textos que PRO pinta traducidos y los de los controles aproximados.
	'defaults'  => array(
		'slides_name' => $digi_tc_t( 'Slides' ),
		'loop'        => 'yes',
		'lazyload'    => '',
	),
	'keep'      => array(
		// Base de los carruseles de PRO (modules/carousel/widgets/base.php).
		'slides_name',
		'slides_per_view',
		'slides_to_scroll',
		'width',
		'show_arrows',
		'pagination',
		'speed',
		'autoplay',
		'autoplay_speed',
		'loop',
		'pause_on_hover',
		'pause_on_interaction',
		'lazyload',
		'space_between',
		'slide_background_color',
		'slide_border_size',
		'slide_border_radius',
		'slide_border_color',
		'slide_padding',
		'arrows_size',
		'arrows_color',
		'pagination_gap',
		'pagination_size',
		'pagination_color_inactive',
		'pagination_color',
		// Carrusel de testimonios.
		'skin',
		'layout',
		'alignment',
		'background_color',
		'text_padding',
		'border_radius',
		'border',
		'border_color',
		'border_width',
		'content_gap',
		'content_color',
		'name_color',
		'title_color',
		'image_size',
		'image_gap',
		'image_border',
		'image_border_color',
		'image_border_width',
		'image_border_radius',
	),
	// Grupos con el mismo prefijo: tamaño de la imagen (base), tipografías, contorno y sombra del texto.
	'groups'    => array(
		'image_size'          => 'image_size',
		'content_typography'  => 'content_typography',
		'text_stroke'         => 'text_stroke',
		'content_text_shadow' => 'content_text_shadow',
		'name_typography'     => 'name_typography',
		'title_typography'    => 'title_typography',
	),
	'drop'      => array(
		'effect'              => 'PRO quita el efecto de este widget (siempre desliza): un valor guardado no tenía efecto.',
		'height'              => 'PRO quita la altura de este widget: un valor guardado no tenía efecto.',
		'pagination_position' => 'PRO quita la posición de la paginación de este widget (va siempre debajo): un valor guardado no tenía efecto.',
	),
	'approx'    => array(
		'lazyload' => 'La carga diferida es la nativa del navegador (`loading="lazy"`) en lugar de la de Swiper, sin el indicador de carga.',
	),
	'repeaters' => array(
		'slides' => array(
			'target'       => 'slides',
			'defaults'     => array( 'name' => $digi_tc_t( 'John Doe' ), 'title' => $digi_tc_t( 'CEO' ) ),
			'default_rows' => array( $digi_tc_row, $digi_tc_row, $digi_tc_row ),
			'keep'         => array( 'content', 'image', 'name', 'title' ),
		),
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading_arrows', 'heading_pagination', 'name_title_style', 'heading_title_style' ),
	'classes'   => array_merge( $digi_tc_common, $digi_tc_classes ),
);
