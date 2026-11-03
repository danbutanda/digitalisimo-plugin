<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de lo que la migración crea en la plataforma del sitio (V.14 C6): una opción por tipo,
 * `digitalisimo_migration_reg_<kind>`, sin carga automática, con una fila por origen:
 *
 *     source => { dest:int, hash:string, state:'pending'|'draft'|'done'|'reverted'|'failed', extra:array }
 *
 * `kind` ∈ `tb`, `font`, `icons`, `snippet`, `mega`, `sidebar`, `swatch`, `loop`, `demo`, `css` (u otro nombre corto en
 * minúsculas). `source` es el ID de origen o una huella sha256. Cada paso consulta el registro antes de crear: reanudar
 * no duplica. Los respaldos no van aquí: van en metas u opciones propias, escritos antes de modificar.
 *
 * Sólo lo escribe la tarea: en una conversión pura (editor, análisis) put() y forget() no hacen nada.
 */
final class Registry {
	const PREFIX = 'digitalisimo_migration_reg_';
	const STATES = array( 'pending', 'draft', 'done', 'reverted', 'failed' );
	/** Tipos conocidos (para listar y para que --revert los recorra). */
	const KINDS = array( 'tb', 'font', 'icons', 'snippet', 'css', 'mega', 'sidebar', 'swatch', 'loop', 'demo' );

	/** @var array<string, array> caché de la petición, por sitio y tipo. */
	private static $cache = array();

	/** Fila de un origen, o null. */
	public static function get( string $kind, string $source ): ?array {
		$all = self::all( $kind );
		return $all[ self::source( $source ) ] ?? null;
	}

	/** Guarda (o sustituye) la fila de un origen. */
	public static function put( string $kind, string $source, array $row ): void {
		if ( ! self::valid_kind( $kind ) || self::pure() ) {
			return;
		}
		$all                            = self::all( $kind );
		$all[ self::source( $source ) ] = self::row( $row );
		self::write( $kind, $all );
	}

	/** Todas las filas de un tipo: source => fila. */
	public static function all( string $kind ): array {
		if ( ! self::valid_kind( $kind ) ) {
			return array();
		}
		$key = self::key( $kind );
		if ( ! array_key_exists( $key, self::$cache ) ) {
			$stored             = get_option( self::PREFIX . $kind, array() );
			$rows               = array();
			foreach ( is_array( $stored ) ? $stored : array() as $source => $row ) {
				if ( is_array( $row ) ) {
					$rows[ (string) $source ] = self::row( $row );
				}
			}
			self::$cache[ $key ] = $rows;
		}
		return self::$cache[ $key ];
	}

	/** Borra la fila de un origen (lo creado se revirtió o se perdió). */
	public static function forget( string $kind, string $source ): void {
		if ( ! self::valid_kind( $kind ) || self::pure() ) {
			return;
		}
		$all = self::all( $kind );
		unset( $all[ self::source( $source ) ] );
		self::write( $kind, $all );
	}

	/** Busca la fila cuyo destino es un ID: array( source, fila ) o null. */
	public static function by_dest( string $kind, int $dest ): ?array {
		foreach ( self::all( $kind ) as $source => $row ) {
			if ( (int) $row['dest'] === $dest && $dest > 0 ) {
				return array( (string) $source, $row );
			}
		}
		return null;
	}

	/** ¿Hay alguna fila de algún tipo? */
	public static function any(): bool {
		foreach ( self::KINDS as $kind ) {
			if ( self::all( $kind ) ) {
				return true;
			}
		}
		return false;
	}

	/** Borra todas las filas de un tipo (--revert, tras deshacerlo todo). */
	public static function clear( string $kind ): void {
		if ( ! self::valid_kind( $kind ) || self::pure() ) {
			return;
		}
		delete_option( self::PREFIX . $kind );
		unset( self::$cache[ self::key( $kind ) ] );
	}

	/** Vacía la caché de la petición (pruebas, cambio de sitio). */
	public static function flush_cache(): void {
		self::$cache = array();
	}

	private static function write( string $kind, array $rows ): void {
		if ( $rows ) {
			update_option( self::PREFIX . $kind, $rows, false );
		} else {
			delete_option( self::PREFIX . $kind );
		}
		self::$cache[ self::key( $kind ) ] = $rows;
	}

	/** Fila normalizada. */
	private static function row( array $row ): array {
		$state = (string) ( $row['state'] ?? 'pending' );
		return array(
			'dest'  => (int) ( $row['dest'] ?? 0 ),
			'hash'  => (string) ( $row['hash'] ?? '' ),
			'state' => in_array( $state, self::STATES, true ) ? $state : 'pending',
			'extra' => is_array( $row['extra'] ?? null ) ? $row['extra'] : array(),
		);
	}

	private static function source( string $source ): string {
		$source = trim( $source );
		// Un origen largo (una ruta, un nombre) se guarda por su huella.
		return strlen( $source ) > 100 ? hash( 'sha256', $source ) : $source;
	}

	private static function valid_kind( string $kind ): bool {
		return 1 === preg_match( '/^[a-z0-9_]{1,20}$/', $kind );
	}

	private static function key( string $kind ): string {
		return ( function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0 ) . ':' . $kind;
	}

	private static function pure(): bool {
		$context = '\\Digitalisimo\\Elements\\Legacy\\Context';
		return class_exists( $context, false ) && $context::pure();
	}
}
