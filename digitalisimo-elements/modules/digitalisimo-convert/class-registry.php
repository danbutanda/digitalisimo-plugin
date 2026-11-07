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
		if ( ! array_key_exists( $type, self::$maps ) ) {
			$file = __DIR__ . '/maps/' . $type . '.php';
			// Los textos por defecto de PRO que un mapa completa (`esc_html__( …, 'elementor-pro' )`) se guardan traducidos:
			// en el idioma del sitio, el que veían sus visitantes con PRO, y no en el de quien convierte (en el
			// administrador, `determine_locale()` da el idioma de la persona). Dentro de `all()` ya está en él y no cambia.
			$switched = self::site_locale();
			try {
				$map = is_file( $file ) ? require $file : null;
			} finally {
				self::restore_locale( $switched );
			}
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
		$names = array_unique( $names );
		// Un solo cambio al idioma del sitio para todos los mapas que faltan, no uno por mapa: cada cambio recarga todas
		// las traducciones cargadas, y el panel del editor (`Panel::hidden()`) lee los ~100 mapas en cada documento.
		$pending  = array_filter( array_diff( $names, array_keys( self::$maps ) ), array( Catalog::class, 'is_pro' ) );
		$switched = $pending ? self::site_locale() : false;
		try {
			foreach ( $names as $name ) {
				$map = self::map( $name, $drafts );
				if ( null !== $map ) {
					$out[ (string) $map['source'] ] = $map;
				}
			}
		} finally {
			self::restore_locale( $switched );
		}
		ksort( $out );
		return $out;
	}

	/** Pasa al idioma del sitio si quien convierte usa otro (`switch_to_locale()` no hace nada si ya está en él). */
	private static function site_locale(): bool {
		return function_exists( 'switch_to_locale' ) && function_exists( 'get_locale' ) && switch_to_locale( get_locale() );
	}

	/** Vuelve al idioma anterior si `site_locale()` cambió. */
	private static function restore_locale( bool $switched ): void {
		if ( $switched && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}
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
