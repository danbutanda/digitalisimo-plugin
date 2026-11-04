<?php
/**
 * Author Box de PRO → Caja de autor (`digitalisimo-author-box`,
 * modules/digitalisimo-theme-elements/widgets/class-author-box.php).
 *
 * El widget propio repite cada control de PRO con el mismo nombre, valor por defecto, opciones y condiciones, así que el
 * mapa es `keep` y `groups`. Los ajustes del «Perfil ampliado» (`digitalisimo_author_*`) pasan sin cambios: el widget
 * propio integra esa sección. Las clases de presentación (`--layout-image-`, `--align-`, `--avatar-`…) van en el
 * envoltorio como en PRO, con el prefijo propio. Diferencias sin clave propia (textos alternativos en español, avatar con
 * medidas, imagen de la biblioteca con srcset, `rel="noopener"` en la web del autor, foco visible): cabecera del widget.
 * Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_ab_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_ab_classes = array(
	'.elementor-widget-author-box'          => '.elementor-widget-digitalisimo-author-box',
	'.elementor-author-box__avatar'         => '.digi-author-box__avatar',
	'.elementor-author-box__text'           => '.digi-author-box__text',
	'.elementor-author-box__name'           => '.digi-author-box__name',
	'.elementor-author-box__bio'            => '.digi-author-box__bio',
	'.elementor-author-box__button'         => '.digi-author-box__button',
);
foreach ( array( 'avatar-yes', 'name-yes', 'biography-yes', 'link-yes', 'layout-image-left', 'layout-image-above', 'layout-image-right', 'align-left', 'align-center', 'align-right', 'image-valign-top', 'image-valign-middle' ) as $digi_ab_mod ) {
	$digi_ab_classes[ '.elementor-author-box--' . $digi_ab_mod ] = '.digi-author-box--' . $digi_ab_mod;
}
// La caja interior (el traductor sustituye clases completas, de la más larga a la más corta).
$digi_ab_classes['.elementor-author-box'] = '.digi-author-box';

return array(
	'source'   => 'author-box',
	'target'   => 'digitalisimo-author-box',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO: los textos que PRO pinta traducidos y la imagen de muestra.
	'defaults' => array(
		'author_name' => $digi_ab_t( 'John Doe' ),
		'author_bio'  => $digi_ab_t( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' ),
		'link_text'   => $digi_ab_t( 'All Posts' ),
	),
	'keep'     => array(
		'source',
		'show_avatar',
		'avatar_size',
		'author_avatar',
		'show_name',
		'author_name',
		'author_name_tag',
		'link_to',
		'show_biography',
		'show_link',
		'author_website',
		'author_bio',
		'posts_url',
		'link_text',
		'layout',
		'alignment',
		'image_vertical_align',
		'image_size',
		'image_gap',
		'image_border',
		'image_border_color',
		'image_border_width',
		'image_border_radius',
		'name_color',
		'name_gap',
		'bio_color',
		'bio_gap',
		'button_text_color',
		'button_background_color',
		'button_hover_color',
		'button_background_hover_color',
		'button_hover_transition_duration',
		'button_hover_animation',
		'button_border_width',
		'button_border_radius',
		'button_text_padding',
	),
	'groups'   => array(
		'input_box_shadow'  => 'input_box_shadow',
		'name_typography'   => 'name_typography',
		'bio_typography'    => 'bio_typography',
		'button_typography' => 'button_typography',
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_name_style', 'heading_bio_style' ),
	'classes'  => $digi_ab_classes,
);
