<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Mapas de conversión de los widgets de PRO a widgets propios: `maps/<widget de PRO>.php` devuelve el mapa que lee
 * `Translator`. El nombre del archivo es el del widget de PRO y sólo se aceptan los del catálogo, así que nada que
 * llegue de un documento decide una ruta. Sólo un mapa `ready` (validado con su A/B en Playground) lo usan Migración y
 * los kits; un `draft` sólo lo usan las pruebas (`all()` con `$drafts`). Los archivos `maps/_*.php` no son mapas: son
 * piezas comunes que los mapas incluyen (`_carousel-classes.php`), y nunca se cargan como mapa.
 */
final class Registry {
	/** @var array<string, array|null> */
	private static $maps = array();

	/** Mapa de un widget de PRO, o null si no hay (o, sin `$drafts`, si no está listo). */
	public static function map( $type, bool $drafts = false ): ?array {
		if ( ! Catalog::is_pro( $type ) ) {
			return null;
		}
		if ( array_key_exists( $type, self::$maps ) ) {
			$map = self::$maps[ $type ];
		} else {
			$file = __DIR__ . '/maps/' . $type . '.php';
			$map  = is_file( $file ) ? require $file : null;
			$map  = is_array( $map ) && ( $map['source'] ?? '' ) === $type && '' !== (string) ( $map['target'] ?? '' ) ? $map : null;
			// Un mapa leído antes de que el constructor estuviera listo (`retry`, el del Formulario sin las acciones de
			// terceros) no se guarda: la siguiente lectura lo completa.
			if ( null === $map || empty( $map['retry'] ) ) {
				self::$maps[ $type ] = $map;
			}
		}
		return null !== $map && ( $drafts || 'ready' === ( $map['status'] ?? 'draft' ) ) ? $map : null;
	}

	/** Mapas por widget de PRO (sólo los listos, o también los borradores). */
	public static function all( bool $drafts = false ): array {
		$out   = array();
		$names = array_keys( self::$maps );
		foreach ( glob( __DIR__ . '/maps/*.php' ) ?: array() as $file ) {
			// `maps/_*.php` son piezas comunes que los mapas incluyen (p. ej. `_carousel-classes.php`), no mapas.
			if ( '_' !== basename( $file )[0] ) {
				$names[] = basename( $file, '.php' );
			}
		}
		foreach ( array_unique( $names ) as $name ) {
			$map = self::map( $name, $drafts );
			if ( null !== $map ) {
				$out[ (string) $map['source'] ] = $map;
			}
		}
		ksort( $out );
		return $out;
	}

	/** Widget propio de un widget de PRO con mapa listo, o ''. */
	public static function target( $type ): string {
		$map = self::map( $type );
		return null !== $map ? (string) $map['target'] : '';
	}

	/** Para las pruebas: fija (o con null, quita) el mapa de un widget de PRO sin leer el disco. */
	public static function set( string $type, ?array $map ): void {
		self::$maps[ $type ] = $map;
	}

	/** Para las pruebas: olvida los mapas leídos. */
	public static function reset(): void {
		self::$maps = array();
	}
}
