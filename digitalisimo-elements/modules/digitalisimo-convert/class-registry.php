<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Mapas de conversión de los widgets de PRO a widgets propios: `maps/<widget de PRO>.php` devuelve el mapa que lee
 * `Translator`. El nombre del archivo es el del widget de PRO y sólo se aceptan los del catálogo, así que nada que
 * llegue de un documento decide una ruta. Sólo un mapa `ready` (validado con su A/B en Playground) lo usan Migración y
 * los kits; un `draft` sólo lo usan las pruebas (`all()` con `$drafts`). Los archivos `maps/_*.php` no son mapas: son
 * piezas comunes que los mapas incluyen (`_carousel-classes.php`), y nunca se cargan como mapa. Los mapas se cargan en el
 * idioma del sitio (`in_site_locale()`), como sus textos en el front de PRO.
 */
final class Registry {
	/** @var array<string, array|null> */
	private static $maps = array();

	/** @var int Profundidad de `in_site_locale()` (sólo la llamada exterior cambia de idioma). */
	private static $locale_depth = 0;

	/**
	 * Ejecuta `$fn` en el idioma del sitio. Los mapas completan los textos que PRO pintaba en el front con el idioma del
	 * sitio (`esc_html__( …, 'elementor-pro' )`), al cargarse y en sus `filter`/`values`; «Migrar» por admin-ajax y la
	 * importación de un kit corren en la administración, donde WordPress usa el idioma del perfil de quien la lanza.
	 * Sin cambio de idioma disponible (o si ya es el del sitio), `$fn` corre tal cual.
	 *
	 * @return mixed Lo que devuelva `$fn`.
	 */
	public static function in_site_locale( callable $fn ) {
		$switched = 0 === self::$locale_depth && function_exists( 'switch_to_locale' ) && function_exists( 'restore_previous_locale' ) && function_exists( 'get_locale' ) && switch_to_locale( (string) get_locale() );
		++self::$locale_depth;
		try {
			return $fn();
		} finally {
			--self::$locale_depth;
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/** Mapa de un widget de PRO, o null si no hay (o, sin `$drafts`, si no está listo). */
	public static function map( $type, bool $drafts = false ): ?array {
		if ( ! Catalog::is_pro( $type ) ) {
			return null;
		}
		if ( ! array_key_exists( $type, self::$maps ) ) {
			$file                 = __DIR__ . '/maps/' . $type . '.php';
			$map                  = is_file( $file ) ? self::in_site_locale(
				static function () use ( $file ) {
					return require $file;
				}
			) : null;
			self::$maps[ $type ] = is_array( $map ) && ( $map['source'] ?? '' ) === $type && '' !== (string) ( $map['target'] ?? '' ) ? $map : null;
		}
		$map = self::$maps[ $type ];
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
		// Un solo cambio de idioma para todos los mapas que falten por leer.
		self::in_site_locale(
			static function () use ( $names, $drafts, &$out ) {
				foreach ( array_unique( $names ) as $name ) {
					$map = self::map( $name, $drafts );
					if ( null !== $map ) {
						$out[ (string) $map['source'] ] = $map;
					}
				}
			}
		);
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
