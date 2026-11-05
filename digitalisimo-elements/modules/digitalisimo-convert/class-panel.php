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
	 * Widgets de PRO que la plantilla de Elemento de bucle (`loop-item`) quita de su panel (`Loop::WIDGETS_TO_HIDE`, un
	 * bucle no va dentro de otro): sin PRO cargado, la misma lista.
	 */
	const LOOP_ITEM_HIDE = array( 'loop-grid', 'woocommerce-product-data-tabs', 'loop-carousel' );

	/**
	 * `elementor/document/config`: `show_in_panel` en falso para cada widget oculto. Las plantillas individuales de PRO
	 * vuelven a mostrar algunos widgets con `panel.widgets_settings` (C4: el contenido de la entrada), que el editor
	 * aplica después de `widgets`: también ahí se ocultan. En una plantilla de Elemento de bucle, además, los widgets
	 * propios que sustituyen a los que PRO quita de ese panel (C5: la Cuadrícula y el Carrusel de bucle), con la misma
	 * clave; lo que `Loop::get_initial_config()` escribe después del filtro (sus recomendados) no se puede cambiar aquí.
	 */
	public static function document_config( $config, $post_id = 0 ) {
		$config = is_array( $config ) ? $config : array();
		foreach ( self::hidden() as $type ) {
			$config['widgets'][ $type ]['show_in_panel']                     = false;
			$config['panel']['widgets_settings'][ $type ]['show_in_panel'] = false;
		}
		if ( (int) $post_id > 0 && function_exists( 'get_post_meta' ) && 'loop-item' === get_post_meta( (int) $post_id, '_elementor_template_type', true ) ) {
			$list    = class_exists( '\ElementorPro\Modules\LoopBuilder\Documents\Loop', false ) && defined( '\ElementorPro\Modules\LoopBuilder\Documents\Loop::WIDGETS_TO_HIDE' ) ? (array) \ElementorPro\Modules\LoopBuilder\Documents\Loop::WIDGETS_TO_HIDE : self::LOOP_ITEM_HIDE;
			$manager = class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ? \Elementor\Plugin::$instance->widgets_manager : null;
			foreach ( $list as $type ) {
				$map    = Registry::map( (string) $type, true );
				$target = null !== $map ? (string) $map['target'] : '';
				if ( '' !== $target && ( ! is_object( $manager ) || ! method_exists( $manager, 'get_widget_types' ) || $manager->get_widget_types( $target ) ) ) {
					$config['panel']['widgets_settings'][ $target ]['show_in_panel'] = false;
				}
			}
		}
		return $config;
	}
}
