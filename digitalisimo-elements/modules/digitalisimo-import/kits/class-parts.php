<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de partes del kit (§5.5 y §11.1).
 *
 * Las partes de K van primero y en el orden de `BUILTIN`, que es el orden de importación de §7.3; después, las
 * del filtro `digitalisimo_elements_kit_parts`, ordenadas por clave. El filtro recibe las partes de K indexadas por
 * clave y devuelve un array de partes: instancias de `Part` o nombres de clase que se instancian sin argumentos.
 * Sustituir una parte de K es reemplazar su entrada; añadir otra con la misma clave es un error.
 *
 * Se rechaza, con `_doing_it_wrong()`, lo que no implementa `Part`, una clave inválida, reservada o repetida, una
 * versión menor que 1 y las metas del kit con un nombre inválido. Se instancia una vez por petición: hay que
 * engancharse al filtro antes de importar o exportar (en `init` o antes).
 */
final class Parts {
	/** Clave de parte: `[a-z0-9-]+`, sin guiones al principio, al final ni seguidos. */
	const KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	const KEY_MAX     = 64;

	/** Archivos de `digitalisimo/` que no son partes y ninguna parte puede pisar (§5.2). */
	const RESERVED = array( 'urls' );

	/** Meta del kit: lo que `update_post_meta()` guarda sin tocar. */
	const META_KEY_PATTERN = '/^[A-Za-z0-9_.:-]{1,191}$/';

	/** Partes de K, en el orden en que se importan (§7.3). Las implementa el frente B en `kits/parts/`. */
	const BUILTIN = array(
		'origin'           => Parts\Origin::class,
		'element-defaults' => Parts\Element_Defaults::class,
		'kit-settings'     => Parts\Kit_Settings::class,
		'files'            => Parts\Files::class,
		'media'            => Parts\Media::class,
		'theme-builder'    => Parts\Theme_Builder::class,
		'term-meta'        => Parts\Term_Meta::class,
	);

	/** @var Part[]|null */
	private static $parts = null;

	/** @var array[] Clave => metas del kit válidas. */
	private static $meta = array();

	/**
	 * Partes registradas, por clave y en orden de importación.
	 *
	 * @return Part[]
	 */
	public static function all(): array {
		if ( null !== self::$parts ) {
			return self::$parts;
		}
		self::$parts = array();
		self::$meta  = array();

		$builtin = array();
		foreach ( self::BUILTIN as $key => $class ) {
			if ( class_exists( $class ) ) {
				$part = self::resolve( $class );
				if ( null !== $part ) {
					$builtin[ $key ] = $part;
				}
			}
		}
		$entries = function_exists( 'apply_filters' ) ? apply_filters( Module::FILTER_PARTS, $builtin ) : $builtin;
		if ( ! is_array( $entries ) ) {
			Module::doing_it_wrong( __METHOD__, 'El filtro de partes debe devolver un array.' );
			$entries = $builtin;
		}

		$parts = array();
		foreach ( $entries as $entry ) {
			$part = self::resolve( $entry );
			if ( null === $part ) {
				continue;
			}
			try {
				$key     = $part->key();
				$version = $part->version();
				$metas   = $part->kit_meta_keys();
			} catch ( \Throwable $error ) {
				Module::doing_it_wrong( __METHOD__, 'Una parte falló al describirse: ' . get_class( $part ) . ': ' . $error->getMessage() );
				continue;
			}
			if ( ! self::is_valid_key( $key ) ) {
				Module::doing_it_wrong( __METHOD__, 'Clave de parte inválida o reservada en ' . get_class( $part ) . ': ' . Module::describe( $key ) );
				continue;
			}
			if ( isset( $parts[ $key ] ) ) {
				Module::doing_it_wrong( __METHOD__, 'Clave de parte repetida: «' . $key . '» (' . get_class( $parts[ $key ] ) . ' y ' . get_class( $part ) . '). Se conserva la primera.' );
				continue;
			}
			if ( $version < 1 ) {
				Module::doing_it_wrong( __METHOD__, 'La parte «' . $key . '» debe tener una versión de 1 o más.' );
				continue;
			}
			$parts[ $key ]      = $part;
			self::$meta[ $key ] = self::valid_meta_keys( $key, $metas );
		}

		$ordered = array();
		foreach ( array_keys( self::BUILTIN ) as $key ) {
			if ( isset( $parts[ $key ] ) ) {
				$ordered[ $key ] = $parts[ $key ];
				unset( $parts[ $key ] );
			}
		}
		ksort( $parts, SORT_STRING );
		self::$parts = $ordered + $parts;
		return self::$parts;
	}

	/** Una parte por su clave, o null. */
	public static function get( string $key ): ?Part {
		$parts = self::all();
		return isset( $parts[ $key ] ) ? $parts[ $key ] : null;
	}

	/** @return string[] Claves en orden de importación. */
	public static function keys(): array {
		return array_keys( self::all() );
	}

	/**
	 * Partes que también corren en el runner `plugins` (CC-15).
	 *
	 * @return Early_Part[]
	 */
	public static function early(): array {
		return array_filter(
			self::all(),
			function ( $part ) {
				return $part instanceof Early_Part;
			}
		);
	}

	/** @return int[] Clave => versión, para `manifest.digitalisimo.parts`. */
	public static function versions(): array {
		$versions = array();
		foreach ( self::all() as $key => $part ) {
			$versions[ $key ] = $part->version();
		}
		return $versions;
	}

	/** ¿Puede la parte importar datos exportados con esa versión? Sí si existe y `1 ≤ $version ≤ version()`. */
	public static function accepts( string $key, int $version ): bool {
		$part = self::get( $key );
		return null !== $part && $version >= 1 && $version <= $part->version();
	}

	/** @return string[] Metas del kit de todas las partes, sin repetir. */
	public static function kit_meta_keys(): array {
		self::all();
		$keys = array();
		foreach ( self::$meta as $metas ) {
			foreach ( $metas as $meta ) {
				$keys[ $meta ] = true;
			}
		}
		return array_keys( $keys );
	}

	/**
	 * Callback de `elementor/kit/meta_to_preserve_on_kit_import`: añade las metas del kit de las partes.
	 *
	 * @param mixed $keys Lo que ya traía el filtro.
	 *
	 * @return string[]
	 */
	public static function meta_to_preserve( $keys ): array {
		$keys = is_array( $keys ) ? $keys : array();
		return array_values( array_unique( array_merge( $keys, self::kit_meta_keys() ) ) );
	}

	/** Ruta del archivo de una parte dentro del kit: `digitalisimo/<key>.json`. */
	public static function file( string $key ): string {
		return Module::KIT_FOLDER . $key . '.json';
	}

	/** ¿Es una clave de parte válida y no reservada? */
	public static function is_valid_key( string $key ): bool {
		return strlen( $key ) <= self::KEY_MAX
			&& 1 === preg_match( self::KEY_PATTERN, $key )
			&& ! in_array( $key, self::RESERVED, true );
	}

	/** @return string[] Claves de `BUILTIN` cuya clase aún no existe. */
	public static function missing_builtin(): array {
		$missing = array();
		foreach ( self::BUILTIN as $key => $class ) {
			if ( ! class_exists( $class ) ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

	/**
	 * Olvida las partes instanciadas.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$parts = null;
		self::$meta  = array();
	}

	/** Instancia o comprueba una entrada del filtro. */
	private static function resolve( $entry ): ?Part {
		if ( is_string( $entry ) && '' !== $entry ) {
			if ( ! class_exists( $entry ) || ! is_subclass_of( $entry, Part::class ) ) {
				Module::doing_it_wrong( __METHOD__, 'La clase no existe o no implementa la interfaz de partes: ' . Module::describe( $entry ) );
				return null;
			}
			try {
				$entry = new $entry();
			} catch ( \Throwable $error ) {
				Module::doing_it_wrong( __METHOD__, 'No se pudo crear la parte ' . Module::describe( $entry ) . ': ' . $error->getMessage() );
				return null;
			}
		}
		if ( ! $entry instanceof Part ) {
			Module::doing_it_wrong( __METHOD__, 'Entrada de parte que no implementa la interfaz: ' . ( is_object( $entry ) ? get_class( $entry ) : Module::describe( $entry ) ) );
			return null;
		}
		return $entry;
	}

	/** Metas del kit con nombre válido; las demás se descartan con un aviso. */
	private static function valid_meta_keys( string $key, $metas ): array {
		$valid = array();
		foreach ( is_array( $metas ) ? $metas : array( $metas ) as $meta ) {
			if ( is_string( $meta ) && 1 === preg_match( self::META_KEY_PATTERN, $meta ) ) {
				$valid[] = $meta;
			} else {
				Module::doing_it_wrong( __METHOD__, 'Meta del kit inválida en la parte «' . $key . '»: ' . Module::describe( $meta ) );
			}
		}
		return array_values( array_unique( $valid ) );
	}
}
