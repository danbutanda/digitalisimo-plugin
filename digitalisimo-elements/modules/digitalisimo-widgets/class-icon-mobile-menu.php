<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-icon-nav.php';

/** Menú móvil con iconos: alias oculto de Navegación con iconos en barra horizontal para el contenido guardado. */
class Icon_Mobile_Menu_Widget extends Icon_Nav_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-icon-mobile-menu';
	const LEGACY_KEYS    = array( 'menu_items' => false );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-icon-mobile-menu', 'elements' => array( 'links' => 'list', 'link' => 'item', 'rail' => false, 'brand' => false, 'menu' => false, 'panel' => false ) );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['iconnavs'] ) && ! empty( $s['menu_items'] ) ) {
			$s['iconnavs'] = Settings_Upgrade::rows( $s['menu_items'], array( 'menu_icon' => 'iconnav_icon', 'menu_text' => 'iconnav_title', 'link' => 'iconnav_link' ) );
		}
		unset( $s['menu_items'] );
		Settings_Upgrade::fill( $s, array( 'layout' => 'bar' ) );
		return $s;
	}
}
