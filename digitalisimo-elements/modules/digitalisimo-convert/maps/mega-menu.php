<?php
/**
 * Menú anidado de PRO (`mega-menu`) → Menú con submenús de contenido (`digitalisimo-mega-menu`,
 * modules/digitalisimo-navigation/widgets/class-mega-menu.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto y las mismas
 * condiciones, así que el mapa es todo `keep` y `groups`. Los contenedores hijos (el contenido de cada submenú) no se
 * tocan: `Converter::tree()` sólo cambia `widgetType` y los ajustes del widget y recorre los hijos. Las clases de PRO
 * del CSS personalizado del elemento pasan a las propias. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_mm_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'    => 'mega-menu',
	'target'    => 'digitalisimo-mega-menu',
	'status'    => 'draft',
	'panel'     => 'hide',
	// Valores por defecto de PRO que el traductor necesita para saber si el sitio los cambió.
	'defaults'  => array(
		'menu_name'                   => $digi_mm_t( 'Menu' ),
		'content_width'               => 'full_width',
		'content_horizontal_position' => 'center',
		'item_layout'                 => 'horizontal',
		'menu_item_icon'              => array(
			'value'   => 'fas fa-caret-down',
			'library' => 'fa-solid',
		),
		'menu_item_icon_active'       => array(
			'value'   => 'fas fa-caret-up',
			'library' => 'fa-solid',
		),
		'open_on'                     => 'hover',
		'open_animation'              => '',
		'horizontal_scroll'           => 'disable',
		'breakpoint_selector'         => 'tablet',
		'menu_divider_style'          => 'solid',
	),
	'keep'      => array(
		// Diseño.
		'menu_name',
		'content_width',
		'content_horizontal_position',
		'item_layout',
		'item_position_horizontal',
		'item_position_dropdown',
		'menu_item_icon',
		'menu_item_icon_active',
		// Efecto del submenú.
		'open_on',
		'open_animation',
		'open_animation_duration',
		// Botón del menú.
		'toggle_align',
		'menu_toggle_icon_normal',
		'menu_toggle_icon_hover_animation',
		'menu_toggle_animation_duration',
		'menu_toggle_icon_active',
		// Ajustes adicionales.
		'horizontal_scroll',
		'breakpoint_selector',
		// Estilo de los elementos.
		'menu_item_title_space_between',
		'menu_item_title_distance_from_content',
		'menu_item_title_text_color_normal',
		'menu_divider',
		'menu_divider_style',
		'menu_divider_width',
		'menu_divider_height',
		'menu_divider_color',
		'menu_item_title_text_color_hover',
		'hover_animation',
		'menu_item_title_transition_duration',
		'menu_item_title_text_color_active',
		'menu_item_title_border_radius',
		'menu_item_title_padding',
		// Icono.
		'icon_position',
		'icon_size',
		'icon_spacing',
		'icon_color',
		'icon_color_hover',
		'icon_color_active',
		// Indicador de submenú.
		'style_dropdown_indicator_size',
		'style_dropdown_indicator_rotate',
		'style_dropdown_indicator_space',
		'menu_dropdown_indicator_color_normal',
		'menu_dropdown_indicator_color_hover',
		'menu_dropdown_indicator_color_active',
		// Botón del menú (estilo).
		'style_menu_toggle_icon_size',
		'menu_toggle_icon_color_normal',
		'menu_toggle_icon_color_hover',
		'menu_toggle_icon_animation_duration',
		'menu_toggle_icon_color_active',
		'menu_toggle_icon_border_radius',
		'menu_toggle_icon__padding',
		'menu_toggle_icon_distance_from_dropdown',
		// Contenido.
		'content_border_radius',
		'content_padding',
		// Menú desplegable.
		'dropdown_menu_item_text_color_normal',
		'dropdown_menu_item_text_color_active',
		'menu_dropdown_box_border_radius',
	),
	// Controles de grupo con el mismo prefijo en el widget propio.
	'groups'    => array(
		'menu_item_title_typography'                 => 'menu_item_title_typography',
		'menu_item_title_text_shadow_normal'         => 'menu_item_title_text_shadow_normal',
		'menu_item_title_background_color'           => 'menu_item_title_background_color',
		'menu_item_title_box_border'                 => 'menu_item_title_box_border',
		'menu_item_title_box_shadow'                 => 'menu_item_title_box_shadow',
		'menu_item_title_text_shadow_hover'          => 'menu_item_title_text_shadow_hover',
		'menu_item_title_background_color_hover'     => 'menu_item_title_background_color_hover',
		'menu_item_title_box_border_hover'           => 'menu_item_title_box_border_hover',
		'menu_item_title_box_shadow_hover'           => 'menu_item_title_box_shadow_hover',
		'menu_item_title_text_shadow_active'         => 'menu_item_title_text_shadow_active',
		'menu_item_title_background_color_active'    => 'menu_item_title_background_color_active',
		'menu_item_title_box_border_active'          => 'menu_item_title_box_border_active',
		'menu_item_title_box_shadow_active'          => 'menu_item_title_box_shadow_active',
		'menu_toggle_icon_background_color_normal'   => 'menu_toggle_icon_background_color_normal',
		'menu_toggle_icon_border_normal'             => 'menu_toggle_icon_border_normal',
		'menu_toggle_icon_box_shadow_normal'         => 'menu_toggle_icon_box_shadow_normal',
		'menu_toggle_icon_background_color_hover'    => 'menu_toggle_icon_background_color_hover',
		'menu_toggle_icon_border_hover'              => 'menu_toggle_icon_border_hover',
		'menu_toggle_icon_box_shadow_hover'          => 'menu_toggle_icon_box_shadow_hover',
		'menu_toggle_icon_background_color_active'   => 'menu_toggle_icon_background_color_active',
		'menu_toggle_icon_border_active'             => 'menu_toggle_icon_border_active',
		'menu_toggle_icon_box_shadow_active'         => 'menu_toggle_icon_box_shadow_active',
		'content_background_color'                   => 'content_background_color',
		'content_border'                             => 'content_border',
		'content_shadow'                             => 'content_shadow',
		'dropdown_menu_item_background_color_normal' => 'dropdown_menu_item_background_color_normal',
		'dropdown_menu_item_box_shadow_normal'       => 'dropdown_menu_item_box_shadow_normal',
		'dropdown_menu_item_background_color_active' => 'dropdown_menu_item_background_color_active',
		'dropdown_menu_item_box_shadow_active'       => 'dropdown_menu_item_box_shadow_active',
		'menu_dropdown_box_border'                   => 'menu_dropdown_box_border',
		'menu_dropdown_box_shadow'                   => 'menu_dropdown_box_shadow',
	),
	// Las filas del repetidor: una por contenedor hijo, con su `_id`.
	'repeaters' => array(
		'menu_items' => array(
			'target'       => 'menu_items',
			'defaults'     => array(
				'item_title'            => $digi_mm_t( 'Item Title' ),
				'item_dropdown_content' => 'no',
			),
			'keep'         => array( 'item_title', 'item_link', 'item_dropdown_content', 'item_icon', 'item_icon_active', 'element_id' ),
			'default_rows' => array(
				array( 'item_title' => $digi_mm_t( 'Item #1' ) ),
				array( 'item_title' => $digi_mm_t( 'Item #2' ) ),
				array( 'item_title' => $digi_mm_t( 'Item #3' ) ),
			),
		),
	),
	'approx'    => array(
		'horizontal_scroll' => 'Con «Desplazamiento horizontal», la fila de elementos se desplaza con la rueda, el teclado o el dedo; el arrastre con el ratón de PRO no se repite.',
	),
	// Encabezados y descripciones del panel: no guardan valor.
	'ui'        => array(
		'submenu_indicator_title',
		'open_on_hover_description',
		'style_menu_toggle_icon_heading',
		'dropdown_menu_items_title',
		'dropdown_menu_items_description',
		'menu_dropdown_box_title',
		'menu_dropdown_box_description',
	),
	// Variables de PRO en el CSS personalizado del elemento (las clases, en `classes`).
	'filter'    => static function ( array $out ) {
		if ( isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) ) {
			$out['custom_css'] = str_replace( '--n-menu-', '--dmm-', $out['custom_css'] );
		}
		return $out;
	},
	// Clases de PRO en el CSS personalizado del elemento.
	'classes'   => array(
		'.elementor-widget-n-menu'           => '.elementor-widget-digitalisimo-mega-menu',
		'.e-n-menu'                          => '.digi-mm',
		'.e-n-menu-wrapper'                  => '.digi-mm__wrapper',
		'.e-n-menu-heading'                  => '.digi-mm__heading',
		'.e-n-menu-item'                     => '.digi-mm__item',
		'.e-n-menu-title'                    => '.digi-mm__title',
		'.e-n-menu-title-container'          => '.digi-mm__title-container',
		'.e-n-menu-title-text'               => '.digi-mm__title-text',
		'.e-n-menu-icon'                     => '.digi-mm__icon',
		'.icon-active'                       => '.digi-mm__icon-active',
		'.icon-inactive'                     => '.digi-mm__icon-inactive',
		'.e-n-menu-dropdown-icon'            => '.digi-mm__dropdown-icon',
		'.e-n-menu-dropdown-icon-opened'     => '.digi-mm__dropdown-opened',
		'.e-n-menu-dropdown-icon-closed'     => '.digi-mm__dropdown-closed',
		'.e-n-menu-content'                  => '.digi-mm__content',
		'.e-n-menu-toggle'                   => '.digi-mm__toggle',
		'.e-n-menu-toggle-icon'              => '.digi-mm__toggle-icon',
		'.e-open'                            => '.digi-mm__toggle-open',
		'.e-close'                           => '.digi-mm__toggle-close',
		'.e-current'                         => '.digi-mm__current',
		'.e-active'                          => '.digi-mm__active',
		'.e-click'                           => '.digi-mm__click',
		'.e-link'                            => '.digi-mm__link',
		'.e-anchor'                          => '.digi-mm__anchor',
		'.content-above'                     => '.digi-mm--above',
		'.e-n-menu-layout-horizontal'        => '.digi-mm--layout-horizontal',
		'.e-n-menu-layout-dropdown'          => '.digi-mm--layout-dropdown',
		'.e-full_width'                      => '.digi-mm--full_width',
		'.e-fit_to_content'                  => '.digi-mm--fit_to_content',
		'.e-n-menu-mobile'                   => '.digi-mm--bp-mobile',
		'.e-n-menu-mobile_extra'             => '.digi-mm--bp-mobile_extra',
		'.e-n-menu-tablet'                   => '.digi-mm--bp-tablet',
		'.e-n-menu-tablet_extra'             => '.digi-mm--bp-tablet_extra',
		'.e-n-menu-laptop'                   => '.digi-mm--bp-laptop',
		'.e-n-menu-none'                     => '.digi-mm--bp-none',
	),
);
