<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Conversión de elementos guardados: un widget de PRO con mapa listo pasa a su widget propio, con el mismo `id`, el
 * mismo `elType` y sus hijos; todo lo demás queda igual. Es pura (no lee ni escribe la base de datos, no crea medios
 * ni registros), así que la pueden usar DIGITALÍSIMO Migración, que guarda, respalda y revierte, y el importador de
 * kits. Los códigos del informe salen de `Translator`, con el widget de origen y el elemento. `external_css()` y
 * `tree_css()` llevan las clases de los widgets convertidos al CSS que los nombra desde fuera (contenedores, página, kit).
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
		// En el idioma del sitio: los `filter`/`values` del mapa completan textos de PRO al traducir.
		list( $settings, $codes ) = Registry::in_site_locale(
			static function () use ( $settings, $map ) {
				return Translator::translate( $settings, $map );
			}
		);
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
		// Un solo cambio de idioma por árbol (ver `Registry::in_site_locale()`).
		return Registry::in_site_locale(
			static function () use ( $elements, &$log, $drafts ) {
				return self::walk( $elements, $log, $drafts );
			}
		);
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
	 * Juegos de clases (`classes` y `class_patterns`) de los mapas listos, por widget de PRO; con `$sources`, sólo los de
	 * esos widgets. Para `external_css()` y `tree_css()`.
	 *
	 * @param string[]|null $sources Widgets de PRO.
	 * @return array<string, array{classes: array, class_patterns: array}>
	 */
	public static function class_sets( ?array $sources = null ): array {
		$sets = array();
		foreach ( Registry::all() as $source => $map ) {
			if ( ( null !== $sources && ! in_array( (string) $source, $sources, true ) ) || ( empty( $map['classes'] ) && empty( $map['class_patterns'] ) ) ) {
				continue;
			}
			$sets[ (string) $source ] = array(
				'classes'        => (array) ( $map['classes'] ?? array() ),
				'class_patterns' => (array) ( $map['class_patterns'] ?? array() ),
			);
		}
		return $sets;
	}

	/**
	 * CSS que nombra widgets de PRO desde fuera de ellos (un contenedor, la página, el kit, el Personalizador): cada regla
	 * que nombra sus clases gana a continuación su copia con las clases de los widgets propios
	 * (`Translator::rewrite_external_css()`). Sin `$sets`, los de todos los mapas listos.
	 */
	public static function external_css( string $css, ?array $sets = null ): string {
		return Translator::rewrite_external_css( $css, null === $sets ? self::class_sets() : $sets );
	}

	/**
	 * `external_css()` en el CSS personalizado de cada elemento de un árbol que no es un widget convertido (los
	 * convertidos ya traducen el suyo con su mapa, `Translator::MARKER`), a cualquier profundidad.
	 *
	 * @param int $changed Recibe cuántos elementos cambiaron.
	 */
	public static function tree_css( array $elements, ?array $sets = null, ?int &$changed = null ): array {
		$changed = 0;
		$sets    = null === $sets ? self::class_sets() : $sets;
		return $sets ? self::walk_css( $elements, $sets, $changed, 0 ) : $elements;
	}

	private static function walk_css( array $elements, array $sets, int &$changed, int $depth ): array {
		if ( $depth > 256 ) {
			return $elements;
		}
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( ! isset( $settings[ Translator::MARKER ] ) && isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) && '' !== $settings['custom_css'] ) {
				$css = Translator::rewrite_external_css( $settings['custom_css'], $sets );
				if ( $css !== $settings['custom_css'] ) {
					$element['settings']['custom_css'] = $css;
					++$changed;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk_css( $element['elements'], $sets, $changed, $depth + 1 );
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
