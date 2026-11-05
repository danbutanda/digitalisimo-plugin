<?php
/**
 * Animated Headline de PRO → Titular animado (`digitalisimo-animated-headline`,
 * modules/digitalisimo-content/widgets/class-animated-headline.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones, así
 * que el mapa es `keep` y `groups`. Los textos por defecto que PRO pintaba en el idioma del sitio se completan aquí
 * (Elementor no guarda lo que coincide con el valor por defecto). El marcado y las clases son propios (`digi-ah…`); las
 * de PRO pasan a las propias en el CSS personalizado del elemento. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_ah_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_ah_classes = array(
	'.elementor-widget-animated-headline'   => '.elementor-widget-digitalisimo-animated-headline',
	'.elementor-headline'                   => '.digi-ah',
	'.elementor-headline-plain-text'        => '.digi-ah__plain',
	'.elementor-headline-text-wrapper'      => '.digi-ah__wrap',
	'.elementor-headline-dynamic-wrapper'   => '.digi-ah__dyn',
	'.elementor-headline-dynamic-text'      => '.digi-ah__word',
	'.elementor-headline-text-active'       => '.digi-ah__word--on',
	'.elementor-headline-text-inactive'     => '.digi-ah__word--off',
	'.elementor-headline-dynamic-letter'    => '.digi-ah__letter',
	'.elementor-headline-animation-in'      => '.digi-ah__letter--in',
	'.elementor-headline-letters'           => '.digi-ah--letters',
	'.elementor-headline-typing-selected'   => '.digi-ah--sel',
	'.elementor-headline--style-highlight'  => '.digi-ah--style-highlight',
	'.elementor-headline--style-rotate'     => '.digi-ah--style-rotate',
	'.e-animated'                           => '.digi-ah--drawn',
	'.e-hide-highlight'                     => '.digi-ah--hide',
);
foreach ( array( 'typing', 'clip', 'flip', 'swirl', 'blinds', 'drop-in', 'wave', 'slide', 'slide-down' ) as $digi_ah_type ) {
	$digi_ah_classes[ '.elementor-headline-animation-type-' . $digi_ah_type ] = '.digi-ah--' . $digi_ah_type;
}

return array(
	'source'   => 'animated-headline',
	'target'   => 'digitalisimo-animated-headline',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Textos por defecto de PRO (los propios están en español).
	'defaults' => array(
		'before_text'      => $digi_ah_t( 'This page is' ),
		'highlighted_text' => $digi_ah_t( 'Amazing' ),
		'rotating_text'    => "Better\nBigger\nFaster",
	),
	'keep'     => array(
		'headline_style',
		'animation_type',
		'marker',
		'before_text',
		'highlighted_text',
		'rotating_text',
		'after_text',
		'loop',
		'highlight_animation_duration',
		'highlight_iteration_delay',
		'rotate_iteration_delay',
		'link',
		'tag',
		'alignment',
		'title_color',
		'words_color',
		'highlighted_text_background_color',
		'highlighted_text_color',
		'marker_color',
		'stroke_width',
		'above_content',
		'rounded_edges',
	),
	// Grupos con el mismo prefijo: tipografías, contornos y sombras del texto.
	'groups'   => array(
		'title_typography'     => 'title_typography',
		'text_stroke'          => 'text_stroke',
		'title_text_shadow'    => 'title_text_shadow',
		'words_typography'     => 'words_typography',
		'animated_text_stroke' => 'animated_text_stroke',
		'animated_text_shadow' => 'animated_text_shadow',
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_style', 'heading_words_style', 'typing_animation_highlight_colors', 'highlight_animation_shape_colors' ),
	'classes'  => $digi_ah_classes,
);
