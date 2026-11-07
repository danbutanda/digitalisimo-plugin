<?php
/**
 * Search Form de PRO → Formulario de búsqueda (`digitalisimo-search-form`,
 * modules/digitalisimo-theme-elements/widgets/class-search-form.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, valores por defecto, opciones y condiciones, así
 * que el mapa es `keep` y `groups`. Marcado y clases propios: la pantalla completa es un `<dialog>` modal con botones
 * reales, el estado «Foco» de los colores sale de `:has(…:focus)` (PRO lo ponía con una clase desde su script) y los
 * iconos son los de PRO, del núcleo. En el CSS personalizado, las clases de estado de PRO (`--focus`, `--full-screen`)
 * pasan a esos selectores. Los textos que PRO pintaba por defecto en el idioma del sitio («Search...», «Search») se
 * completan antes de traducir, para que lo convertido diga lo mismo. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_sf_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_sf_classes = array(
	'.elementor-widget-search-form'          => '.elementor-widget-digitalisimo-search-form',
	'.elementor-search-form'                 => '.digi-search-form',
	'.elementor-search-form__container'      => '.digi-search-form__container',
	'.elementor-search-form__input'          => '.digi-search-form__input',
	'.elementor-search-form__submit'         => '.digi-search-form__submit',
	'.elementor-search-form__icon'           => '.digi-search-form__icon',
	'.elementor-search-form__toggle'         => '.digi-search-form__toggle',
	'.dialog-lightbox-close-button'          => '.digi-search-form__close',
	'.dialog-close-button'                   => '.digi-search-form__close',
	'.elementor-search-form--skin-classic'   => '.digi-search-form--skin-classic',
	'.elementor-search-form--skin-minimal'   => '.digi-search-form--skin-minimal',
	'.elementor-search-form--skin-full_screen' => '.digi-search-form--skin-full_screen',
	'.elementor-search-form--button-type-icon' => '.digi-search-form--button-type-icon',
	'.elementor-search-form--button-type-text' => '.digi-search-form--button-type-text',
	'.elementor-search-form--icon-search'    => '.digi-search-form--icon-search',
	'.elementor-search-form--icon-arrow'     => '.digi-search-form--icon-arrow',
	// Estados que PRO ponía con su script: el foco del campo, en el formulario (clásica y mínima), y la pantalla completa
	// abierta, en el contenedor (el `<dialog>` propio, `[open]`).
	'.elementor-search-form--focus'          => '.digi-search-form:has(.digi-search-form__input:focus)',
	'.elementor-search-form--full-screen'    => '.digi-search-form__container[open]',
);

return array(
	'source'  => 'search-form',
	'target'  => 'digitalisimo-search-form',
	'status'  => 'ready',
	'panel'   => 'hide',
	// Textos por defecto de PRO (Elementor no guarda lo que coincide con el default).
	'defaults' => array(
		'placeholder' => $digi_sf_t( 'Search' ) . '...',
		'button_text' => $digi_sf_t( 'Search' ),
	),
	'keep'    => array(
		'skin',
		'placeholder',
		'button_type',
		'button_text',
		'icon',
		'size',
		'toggle_align',
		'toggle_size',
		'icon_size_minimal',
		'overlay_background_color',
		'input_text_color',
		'input_background_color',
		'input_border_color',
		'input_text_color_focus',
		'input_background_color_focus',
		'input_border_color_focus',
		'button_border_width',
		'border_radius',
		'button_text_color',
		'button_background_color',
		'button_text_color_hover',
		'button_background_color_hover',
		'icon_size',
		'button_width',
		'toggle_color',
		'toggle_background_color',
		'toggle_color_hover',
		'toggle_background_color_hover',
		'toggle_icon_size',
		'toggle_border_width',
		'toggle_border_radius',
	),
	'groups'  => array(
		'input_typography'       => 'input_typography',
		'input_box_shadow'       => 'input_box_shadow',
		'input_box_shadow_focus' => 'input_box_shadow_focus',
		'button_typography'      => 'button_typography',
	),
	// Encabezados del panel: no guardan valor.
	'ui'      => array( 'heading_button_content', 'toggle_button_content' ),
	'classes' => $digi_sf_classes,
);
