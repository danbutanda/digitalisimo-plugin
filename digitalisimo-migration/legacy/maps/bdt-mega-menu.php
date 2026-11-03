<?php
/**
 * Element Pack Pro 10.0.1 `bdt-mega-menu` → `nav-menu` (F8, V.11).
 *
 * Se conserva la navegación: el mismo menú de WordPress (`navbar` guarda el slug del menú, como el control `menu` del
 * destino: `element_pack_get_menu()` devuelve `$menu->slug => $menu->name`), la dirección y el punto de corte del menú
 * móvil. Los paneles con contenido de cada elemento (plantillas por elemento de menú) no caben en este widget: la
 * plataforma los copia a plantillas sin condiciones y el informe pide rehacerlos (`plat.mega.rebuild`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'nav-menu',
	// Marcado de Element Pack (mega-menu.php y su Mega_Menu_Walker) → menú de navegación de PRO. El panel lateral móvil
	// y la variante vertical con cabecera no tienen equivalente: sus selectores se omiten.
	'classes'  => array(
		'.ep-megamenu .megamenu-header-default .bdt-navbar-nav li .bdt-megamenu-indicator' => '.elementor-nav-menu--main .elementor-item .sub-arrow',
		'.ep-megamenu .megamenu-header-default .bdt-navbar-nav > li > a'                   => '.elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
		'.ep-megamenu .megamenu-header-default .bdt-navbar-nav'                            => '.elementor-nav-menu--main > .elementor-nav-menu',
		'.ep-megamenu.ep-megamenu-horizontal .bdt-navbar-nav .bdt-megamenu-indicator'      => '.elementor-nav-menu--main .elementor-item .sub-arrow',
		'.ep-megamenu.ep-megamenu-horizontal .bdt-navbar-nav'                              => '.elementor-nav-menu--main > .elementor-nav-menu',
		'.ep-megamenu .bdt-navbar-nav .bdt-megamenu-indicator'                             => '.elementor-nav-menu--main .elementor-item .sub-arrow',
		'.ep-megamenu .bdt-navbar-nav > li > a.active'                                     => '.elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active',
		'.ep-megamenu .bdt-navbar-nav > li > a'                                            => '.elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
		'.ep-megamenu .menu-item-has-children .ep-megamenu-panel > li:hover > a'           => '.elementor-nav-menu--main .sub-menu > li:hover > .elementor-sub-item',
		'.ep-megamenu .menu-item-has-children .ep-megamenu-panel > li > a'                 => '.elementor-nav-menu--main .sub-menu > li > .elementor-sub-item',
		'.ep-megamenu .menu-item-has-children .bdt-drop li a'                              => '.elementor-nav-menu--main .sub-menu li > .elementor-sub-item',
		'.ep-megamenu .menu-item-has-children .bdt-drop'                                   => '.elementor-nav-menu--main .sub-menu',
		'.ep-megamenu .ep-megamenu-panel.bdt-drop'                                         => '.elementor-nav-menu--main .sub-menu',
		'.ep-megamenu .ep-default-submenu-panel'                                           => '.elementor-nav-menu--main .sub-menu',
		'.ep-megamenu .megamenu-header-mobile .bdt-navbar-toggle'                          => '.elementor-menu-toggle',
		'.ep-megamenu .bdt-navbar-toggle'                                                  => '.elementor-menu-toggle',
	),
	// Elementor no guarda lo que coincide con el valor por defecto de Element Pack.
	'defaults' => array(
		'navbar'                => 0,
		'ep_megamenu_direction' => 'horizontal',
		'show_hamburger_menu'   => 'mobile',
	),
	'rename'   => array( 'navbar' => 'menu', 'ep_megamenu_direction' => 'layout', 'show_hamburger_menu' => 'dropdown' ),
	// Element Pack no tiene puntero animado en los elementos del menú.
	'set'      => array( 'pointer' => 'none' ),
	'filter'   => static function ( array $out ) {
		$menu        = (string) ( $out['menu'] ?? '' );
		$out['menu'] = '0' === $menu || ! preg_match( '/^[a-z0-9_%-]{1,200}$/i', $menu ) ? '' : $menu;
		$out['layout'] = 'vertical' === ( $out['layout'] ?? '' ) ? 'vertical' : 'horizontal';
		// El punto de corte es una clave de breakpoint de Elementor (mobile, tablet…) o «none»: el mismo vocabulario.
		$dropdown        = (string) ( $out['dropdown'] ?? 'mobile' );
		$out['dropdown'] = preg_match( '/^[a-z_]{1,32}$/', $dropdown ) ? $dropdown : 'mobile';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(?:ep_megamenu_(?!dropdown_arrows)|offcanvas_(?:logo_image|overlay|animations|flip|close_button|bg_close|esc_close)$|mobile_menu_type$|template_id$)/', (string) $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
