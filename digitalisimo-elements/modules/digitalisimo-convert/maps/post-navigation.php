<?php
/**
 * Post Navigation de PRO → Navegación entre entradas (`digitalisimo-post-navigation`,
 * modules/digitalisimo-theme-elements/widgets/class-post-navigation.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones, así
 * que el mapa es `keep` y `groups`. La taxonomía de «En el mismo término» es un control por tipo de entrada
 * (`<tipo>_taxonomy`) que los dos widgets arman con los mismos tipos públicos del sitio: el mapa declara los de este
 * sitio (y `post`, siempre); la clave de un tipo que el sitio ya no tiene queda en «Pendiente de revisar», donde
 * tampoco tenía efecto. Los textos por defecto de PRO («Previous», «Next», en el idioma del sitio) se completan antes de
 * traducir, porque el widget propio tiene los suyos en español. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pn_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

// `<tipo>_taxonomy`: los tipos de entrada del sitio (los dos widgets sólo pintan los públicos con taxonomías).
$digi_pn_types = array( 'post' );
if ( class_exists( '\ElementorPro\Core\Utils' ) ) {
	$digi_pn_types = array_merge( $digi_pn_types, array_keys( (array) \ElementorPro\Core\Utils::get_public_post_types() ) );
}
if ( function_exists( 'get_post_types' ) ) {
	$digi_pn_types = array_merge( $digi_pn_types, array_values( (array) get_post_types() ) );
}
$digi_pn_taxonomy_keys = array();
foreach ( array_unique( array_map( 'strval', $digi_pn_types ) ) as $digi_pn_type ) {
	$digi_pn_taxonomy_keys[] = $digi_pn_type . '_taxonomy';
}

return array(
	'source'   => 'post-navigation',
	'target'   => 'digitalisimo-post-navigation',
	'status'   => 'draft',
	'panel'    => 'hide',
	'defaults' => array(
		'prev_label' => $digi_pn_t( 'Previous' ),
		'next_label' => $digi_pn_t( 'Next' ),
	),
	'keep'     => array_merge(
		array(
			'show_label',
			'prev_label',
			'next_label',
			'show_arrow',
			'arrow_previous_icon',
			'arrow_next_icon',
			'show_title',
			'show_borders',
			'in_same_term',
			'label_color',
			'label_hover_color',
			'label_hover_color_transition_duration',
			'text_color',
			'hover_color',
			'hover_color_transition_duration',
			'arrow_color',
			'arrow_hover_color',
			'arrow_hover_color_transition_duration',
			'arrow_size',
			'arrow_padding',
			'sep_color',
			'borders_width',
			'borders_spacing',
		),
		$digi_pn_taxonomy_keys
	),
	'groups'   => array(
		'label_typography' => 'label_typography',
		'title_typography' => 'title_typography',
	),
	'classes'  => array(
		'.elementor-widget-post-navigation'                => '.elementor-widget-digitalisimo-post-navigation',
		'.elementor-post-navigation-borders-yes'           => '.digi-post-navigation--borders-yes',
		'.elementor-post-navigation'                       => '.digi-post-navigation',
		'.elementor-post-navigation__prev'                 => '.digi-post-navigation__prev',
		'.elementor-post-navigation__next'                 => '.digi-post-navigation__next',
		'.elementor-post-navigation__link'                 => '.digi-post-navigation__link',
		'.elementor-post-navigation__link__prev'           => '.digi-post-navigation__text--prev',
		'.elementor-post-navigation__link__next'           => '.digi-post-navigation__text--next',
		'.elementor-post-navigation__separator-wrapper'    => '.digi-post-navigation__separator-wrapper',
		'.elementor-post-navigation__separator'            => '.digi-post-navigation__separator',
		'.post-navigation__arrow-wrapper'                  => '.digi-post-navigation__arrow',
		'.post-navigation__arrow-prev'                     => '.digi-post-navigation__arrow--prev',
		'.post-navigation__arrow-next'                     => '.digi-post-navigation__arrow--next',
		'.post-navigation__prev--label'                    => '.digi-post-navigation__label--prev',
		'.post-navigation__next--label'                    => '.digi-post-navigation__label--next',
		'.post-navigation__prev--title'                    => '.digi-post-navigation__title--prev',
		'.post-navigation__next--title'                    => '.digi-post-navigation__title--next',
	),
);
