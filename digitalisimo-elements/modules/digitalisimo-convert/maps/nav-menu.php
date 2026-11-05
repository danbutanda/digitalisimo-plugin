<?php
/**
 * Menú de WordPress de PRO (`nav-menu`) → Menú de WordPress (`digitalisimo-nav-menu`,
 * modules/digitalisimo-navigation/widgets/class-nav-menu.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto y las mismas
 * condiciones, así que el mapa es todo `keep` y `groups`. `menu_id` no es un control: es el id del menú que la
 * exportación de un kit guarda junto al slug (`on_export()`), y el widget propio lo traduce al importar igual que PRO.
 * Las clases de PRO del CSS personalizado del elemento pasan a las propias (`classes`). Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_nm_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_nm_classes = array(
	'.elementor-widget-nav-menu'           => '.elementor-widget-digitalisimo-nav-menu',
	'.elementor-nav-menu--main'            => '.digi-nav__main',
	'.elementor-nav-menu__container'       => '.digi-nav__container',
	'.elementor-nav-menu--dropdown'        => '.digi-nav__dropdown',
	'.elementor-nav-menu--layout-horizontal' => '.digi-nav--layout-horizontal',
	'.elementor-nav-menu--layout-vertical' => '.digi-nav--layout-vertical',
	'.sm-vertical'                         => '.digi-nav__list',
	'.elementor-nav-menu'                  => '.digi-nav__list',
	'.elementor-item'                      => '.digi-nav__link',
	'.elementor-sub-item'                  => '.digi-nav__sublink',
	'.elementor-item-active'               => '.digi-nav__active',
	'.elementor-item-anchor'               => '.digi-nav__anchor',
	'.highlighted'                         => '.digi-nav__open',
	'.sub-arrow'                           => '.digi-nav__arrow',
	'.elementor-menu-toggle'               => '.digi-nav__toggle',
	'.elementor-menu-toggle__icon--open'   => '.digi-nav__toggle-open',
	'.elementor-menu-toggle__icon--close'  => '.digi-nav__toggle-close',
	'.elementor-active'                    => '[aria-expanded=true]',
	'.elementor-nav-menu--toggle'          => '.digi-nav--toggle',
	'.elementor-nav-menu--burger'          => '.digi-nav--burger',
	'.elementor-nav-menu--stretch'         => '.digi-nav--stretch',
	'.elementor-nav-menu__text-align-aside' => '.digi-nav--text-aside',
	'.elementor-nav-menu__text-align-center' => '.digi-nav--text-center',
);
foreach ( array( 'mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'none' ) as $digi_nm_bp ) {
	$digi_nm_classes[ '.elementor-nav-menu--dropdown-' . $digi_nm_bp ] = '.digi-nav--dropdown-' . $digi_nm_bp;
}
foreach ( array( 'start', 'center', 'end', 'justify', 'left', 'right' ) as $digi_nm_align ) {
	$digi_nm_classes[ '.elementor-nav-menu__align-' . $digi_nm_align ] = '.digi-nav--align-' . $digi_nm_align;
}
foreach ( array( 'none', 'underline', 'overline', 'double-line', 'framed', 'background', 'text' ) as $digi_nm_pointer ) {
	$digi_nm_classes[ '.e--pointer-' . $digi_nm_pointer ] = '.digi-nav--p-' . $digi_nm_pointer;
}
foreach ( array( 'fade', 'slide', 'grow', 'drop-in', 'drop-out', 'none', 'shrink', 'draw', 'corners', 'sweep-left', 'sweep-right', 'sweep-up', 'sweep-down', 'shutter-in-vertical', 'shutter-out-vertical', 'shutter-in-horizontal', 'shutter-out-horizontal', 'sink', 'float', 'skew', 'rotate' ) as $digi_nm_animation ) {
	$digi_nm_classes[ '.e--animation-' . $digi_nm_animation ] = '.digi-nav--a-' . $digi_nm_animation;
}

return array(
	'source'   => 'nav-menu',
	'target'   => 'digitalisimo-nav-menu',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de PRO que el traductor necesita para saber si el sitio los cambió.
	'defaults' => array(
		'menu_name'                        => $digi_nm_t( 'Menu' ),
		'layout'                           => 'horizontal',
		'pointer'                          => 'underline',
		'animation_line'                   => 'fade',
		'animation_framed'                 => 'fade',
		'animation_background'             => 'fade',
		'animation_text'                   => 'grow',
		'submenu_icon'                     => array(
			'value'   => 'fas fa-caret-down',
			'library' => 'fa-solid',
		),
		'dropdown'                         => 'tablet',
		'text_align'                       => 'aside',
		'toggle'                           => 'burger',
		'toggle_align'                     => 'center',
		'color_menu_item_hover_pointer_bg' => '#fff',
		'nav_menu_divider_style'           => 'solid',
	),
	'keep'     => array(
		// Diseño.
		'menu_name',
		'menu',
		'menu_id',
		'layout',
		'align_items',
		'pointer',
		'animation_line',
		'animation_framed',
		'animation_background',
		'animation_text',
		'submenu_icon',
		'dropdown',
		'full_width',
		'text_align',
		'toggle',
		'toggle_icon_normal',
		'toggle_icon_hover_animation',
		'toggle_icon_active',
		'toggle_align',
		// Menú principal.
		'color_menu_item',
		'color_menu_item_hover',
		'color_menu_item_hover_pointer_bg',
		'pointer_color_menu_item_hover',
		'color_menu_item_active',
		'pointer_color_menu_item_active',
		'nav_menu_divider',
		'nav_menu_divider_style',
		'nav_menu_divider_weight',
		'nav_menu_divider_height',
		'nav_menu_divider_color',
		'pointer_width',
		'padding_horizontal_menu_item',
		'padding_vertical_menu_item',
		'menu_space_between',
		'border_radius_menu_item',
		// Desplegable.
		'color_dropdown_item',
		'background_color_dropdown_item',
		'color_dropdown_item_hover',
		'background_color_dropdown_item_hover',
		'color_dropdown_item_active',
		'background_color_dropdown_item_active',
		'dropdown_border_radius',
		'padding_horizontal_dropdown_item',
		'padding_vertical_dropdown_item',
		'dropdown_divider_width',
		'dropdown_top_distance',
		// Botón del menú.
		'toggle_color',
		'toggle_background_color',
		'toggle_color_hover',
		'toggle_background_color_hover',
		'toggle_size',
		'toggle_border_width',
		'toggle_border_radius',
	),
	// Controles de grupo con el mismo prefijo en el widget propio.
	'groups'   => array(
		'menu_typography'     => 'menu_typography',
		'dropdown_typography' => 'dropdown_typography',
		'dropdown_border'     => 'dropdown_border',
		'dropdown_box_shadow' => 'dropdown_box_shadow',
		'dropdown_divider'    => 'dropdown_divider',
	),
	// Encabezados, separadores y descripciones del panel: no guardan valor.
	'ui'       => array(
		'heading_mobile_dropdown',
		'hr',
		'dropdown_description',
		'heading_dropdown_divider',
	),
	// Variables de PRO en el CSS personalizado del elemento (las clases, en `classes`).
	'filter'   => static function ( array $out ) {
		if ( isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) ) {
			$out['custom_css'] = str_replace( array( '--e-nav-menu-horizontal-menu-item-margin', '--e-nav-menu-divider-', '--nav-menu-icon-size' ), array( '--digi-nav-gap', '--digi-nav-divider-', '--digi-nav-toggle-size' ), $out['custom_css'] );
		}
		return $out;
	},
	'classes'  => $digi_nm_classes,
);
