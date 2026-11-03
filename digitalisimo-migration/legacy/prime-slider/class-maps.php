<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

defined( 'ABSPATH' ) || exit;

/**
 * Mapas de Prime Slider (F8-D18): uno por widget en `prime-slider/maps/`, fuera de `maps/`, sin clase adaptadora.
 *
 * Los 38 de las familias A, B, C, D, H, I, J, K y M son del frente E; los 19 de E, F, G y L, del frente F (V.10.7).
 * El archivo se llama como el `widgetType` (`prime-slider-general.php`); también se acepta el nombre corto
 * (`general.php`), y `get()` admite las dos formas del ID. Cada mapa declara al menos:
 *
 * - `family`   — letra de la familia (`research-ps.md` §2.4);
 * - `decision` — `ADAPTAR`, `APROXIMAR` o `DESCARTAR`;
 * - `target`   — widget de destino (vacío en un descarte);
 * - `fallback` — respaldo cuando el destino no está disponible (vacío si no hay);
 * - `reason`   — motivo legible de la decisión.
 *
 * El resto (filas, campos, alturas, pérdidas declaradas…) lo leen los constructores de cada familia (`Static_Families`
 * de E, `Dynamic` de F). Un mapa es PHP del propio plugin: nada de la petición elige el archivo (el ID se valida).
 */
final class Maps {
	const PREFIX   = 'prime-slider-';
	const FAMILIES = array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M' );
	const DECISIONS = array( 'ADAPTAR', 'APROXIMAR', 'DESCARTAR' );

	/** @var array<string, array|null> */
	private static $cache = array();

	/** Carpeta de los mapas (con barra final). */
	public static function dir(): string {
		return __DIR__ . '/maps/';
	}

	/** `prime-slider-general` y `general` → `general` (o '' si no tiene forma de ID). */
	public static function short( string $id ): string {
		$id = strtolower( trim( $id ) );
		if ( 0 === strpos( $id, self::PREFIX ) ) {
			$id = substr( $id, strlen( self::PREFIX ) );
		}
		return 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id ) ? $id : '';
	}

	/** `general` → `prime-slider-general`. */
	public static function widget( string $id ): string {
		$short = self::short( $id );
		return '' === $short ? '' : self::PREFIX . $short;
	}

	/** Mapa de un widget, o null si no existe o no es válido. */
	public static function get( string $id ): ?array {
		$short = self::short( $id );
		if ( '' === $short ) {
			return null;
		}
		if ( ! array_key_exists( $short, self::$cache ) ) {
			self::$cache[ $short ] = null;
			foreach ( array( self::PREFIX . $short, $short ) as $name ) {
				$file = self::dir() . $name . '.php';
				if ( is_file( $file ) ) {
					$map = require $file;
					if ( is_array( $map ) && self::valid( $map ) ) {
						$map['id']             = self::PREFIX . $short;
						self::$cache[ $short ] = $map;
					}
					break;
				}
			}
		}
		return self::$cache[ $short ];
	}

	/**
	 * Todos los mapas presentes, por `widgetType`, ordenados.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		$out = array();
		foreach ( glob( self::dir() . '*.php' ) ?: array() as $file ) {
			$map = self::get( basename( $file, '.php' ) );
			if ( $map ) {
				$out[ $map['id'] ] = $map;
			}
		}
		ksort( $out );
		return $out;
	}

	/** ¿Tiene el mapa los campos obligatorios con valores válidos? */
	public static function valid( array $map ): bool {
		return in_array( $map['family'] ?? '', self::FAMILIES, true )
			&& in_array( $map['decision'] ?? '', self::DECISIONS, true )
			&& is_string( $map['target'] ?? null )
			&& is_string( $map['fallback'] ?? null )
			&& is_string( $map['reason'] ?? null );
	}

	/** Olvida los mapas cargados (pruebas). */
	public static function reset(): void {
		self::$cache = array();
	}
}
