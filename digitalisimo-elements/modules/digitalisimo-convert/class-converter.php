<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Conversión de elementos guardados: un widget de PRO con mapa listo pasa a su widget propio, con el mismo `id`, el
 * mismo `elType` y sus hijos; todo lo demás queda igual. Es pura (no lee ni escribe la base de datos, no crea medios
 * ni registros), así que la pueden usar DIGITALÍSIMO Migración, que guarda, respalda y revierte, y el importador de
 * kits. Los códigos del informe salen de `Translator`, con el widget de origen y el elemento.
 */
final class Converter {
	/**
	 * Convierte un elemento (no sus hijos). null si no es un widget de PRO con mapa listo.
	 *
	 * @param array      $element Elemento guardado.
	 * @param array|null $codes   Recibe los códigos del informe del elemento.
	 */
	public static function element( array $element, ?array &$codes = null, bool $drafts = false ): ?array {
		$codes = array();
		$type  = (string) ( $element['widgetType'] ?? '' );
		if ( 'widget' !== ( $element['elType'] ?? '' ) || '' === $type ) {
			return null;
		}
		$map = Registry::map( $type, $drafts );
		if ( null === $map ) {
			return null;
		}
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		list( $settings, $codes ) = Translator::translate( $settings, $map );
		$element['widgetType'] = (string) $map['target'];
		$element['settings']   = $settings;
		foreach ( $codes as &$code ) {
			$code += array( 'source' => $type, 'target' => (string) $map['target'], 'element' => (string) ( $element['id'] ?? '' ) );
		}
		return $element;
	}

	/**
	 * Convierte un árbol de elementos a cualquier profundidad (contenedores, columnas, anidados).
	 *
	 * @param array      $elements Árbol.
	 * @param array|null $log      Recibe un registro por elemento convertido: `source`, `target`, `element`, `codes`.
	 */
	public static function tree( array $elements, ?array &$log = null, bool $drafts = false ): array {
		$log = array();
		return self::walk( $elements, $log, $drafts );
	}

	private static function walk( array $elements, array &$log, bool $drafts ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$codes     = array();
			$converted = self::element( $element, $codes, $drafts );
			if ( null !== $converted ) {
				$log[]   = array( 'source' => (string) $element['widgetType'], 'target' => (string) $converted['widgetType'], 'element' => (string) ( $element['id'] ?? '' ), 'codes' => $codes );
				$element = $converted;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'], $log, $drafts );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/**
	 * Recuento de widgets de PRO en un árbol: tipo => elementos (también los que no tienen mapa). Lo usa el inventario.
	 *
	 * @return array<string,int>
	 */
	public static function count( array $elements ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type = (string) ( $element['widgetType'] ?? '' );
			if ( 'widget' === ( $element['elType'] ?? '' ) && Catalog::is_pro( $type ) ) {
				$out[ $type ] = ( $out[ $type ] ?? 0 ) + 1;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				foreach ( self::count( $element['elements'] ) as $child => $n ) {
					$out[ $child ] = ( $out[ $child ] ?? 0 ) + $n;
				}
			}
		}
		return $out;
	}
}
