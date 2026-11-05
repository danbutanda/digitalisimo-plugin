<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Oculta del panel del editor los widgets de PRO ya sustituidos: los que tienen un mapa `ready` con `panel => hide` y
 * cuyo widget propio está registrado. Siguen registrados (el contenido guardado se pinta y se edita igual); sólo dejan
 * de ofrecerse para contenido nuevo. Se hace en la configuración del documento (`elementor/document/config`), sin
 * tocar los archivos de PRO.
 */
final class Panel {
	/** Widgets de PRO que se ocultan, con el widget propio registrado. */
	public static function hidden(): array {
		$out     = array();
		$manager = class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ? \Elementor\Plugin::$instance->widgets_manager : null;
		foreach ( Registry::all() as $type => $map ) {
			if ( 'hide' !== ( $map['panel'] ?? '' ) ) {
				continue;
			}
			if ( is_object( $manager ) && method_exists( $manager, 'get_widget_types' ) && ! $manager->get_widget_types( (string) $map['target'] ) ) {
				continue;
			}
			$out[] = $type;
		}
		return $out;
	}

	/**
	 * `elementor/document/config`: `show_in_panel` en falso para cada widget oculto. Las plantillas individuales de PRO
	 * vuelven a mostrar algunos widgets con `panel.widgets_settings` (C4: el contenido de la entrada), que el editor
	 * aplica después de `widgets`: también ahí se ocultan.
	 */
	public static function document_config( $config, $post_id = 0 ) {
		$config = is_array( $config ) ? $config : array();
		foreach ( self::hidden() as $type ) {
			$config['widgets'][ $type ]['show_in_panel']                     = false;
			$config['panel']['widgets_settings'][ $type ]['show_in_panel'] = false;
		}
		return $config;
	}
}
