<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-fancy-tabs.php';

/** Alternador de contenido: alias oculto de Pestañas con botones en línea para el contenido guardado. */
class Content_Switcher_Widget extends Fancy_Tabs_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-content-switcher';
	const LEGACY_KEYS    = array( 'switcher_items' => false );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-content-switcher', 'elements' => array( 'panels' => false, 'label' => false, 'icon' => false, 'button' => false ) );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['tabs'] ) && ! empty( $s['switcher_items'] ) ) {
			$rows = array();
			foreach ( Settings_Upgrade::rows( $s['switcher_items'], array( 'title' => 'tab_title', 'content' => 'tab_content', 'switcher_active' => 'active', 'switcher_icon' => 'selected_icon' ) ) as $row ) {
				$row['icon_type'] = ! empty( $row['selected_icon']['value'] ) ? 'icon' : 'none';
				if ( '' === trim( wp_strip_all_tags( (string) ( $row['tab_title'] ?? '' ) ) ) ) { $row['tab_title'] = 'Opción ' . ( count( $rows ) + 1 ); }
				$rows[] = $row;
			}
			$s['tabs'] = $rows;
		}
		unset( $s['switcher_items'] );
		Settings_Upgrade::fill( $s, array( 'style' => 'switcher' ) );
		return $s;
	}
}
