<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que comparten los contextos de exportación, importación y reversión: el informe y la huella.
 *
 * Los contextos no dependen del núcleo del constructor: sólo guardan datos de la petición en curso. Sólo
 * contienen arrays y escalares, así que se pueden serializar, pero viven en una sola petición: lo que deba
 * sobrevivir lo persiste el runner (por ejemplo, `Import_Context::stored_all()` y `get_report()`).
 *
 * El informe tiene una entrada por aviso, con la parte que lo emitió, un código, un nivel y datos escalares.
 * Cada código guarda como mucho `MAX_ENTRIES_PER_CODE` entradas; las demás sólo suman en `counts` y en
 * `dropped`, para que el informe de la sesión quepa en su opción (§5.3).
 */
abstract class Part_Context {
	const INFO    = 'info';
	const WARNING = 'warning';
	const ERROR   = 'error';
	const LEVELS  = array( self::INFO, self::WARNING, self::ERROR );

	const MAX_ENTRIES_PER_CODE = 200;
	const MAX_STRING           = 500;
	const MAX_ITEMS            = 50;
	const MAX_DEPTH            = 3;

	/** Códigos del informe: `[a-z0-9]` seguido de `[a-z0-9_.:-]`, por ejemplo `unresolved` o `note:form`. */
	const CODE_PATTERN = '/^[a-z0-9][a-z0-9_.:-]{0,63}$/';

	/** @var string Parte que está corriendo; '' fuera de una parte. */
	protected $part = '';

	/** @var array[] */
	protected $entries = array();

	/** @var int[] Código => número de avisos, incluidos los que ya no caben. */
	protected $counts = array();

	/** @var int */
	protected $dropped = 0;

	/** El runner la llama antes de `Part::export()`, `import()`, `import_early()` o `revert()`. */
	public function begin_part( string $key ): void {
		if ( ! Parts::is_valid_key( $key ) ) {
			Module::doing_it_wrong( __METHOD__, 'Clave de parte inválida: ' . Module::describe( $key ) );
			$key = '';
		}
		$this->part = $key;
	}

	/** El runner la llama al terminar la parte. */
	public function end_part(): void {
		$this->part = '';
	}

	/** Parte en curso, o '' si el aviso es del propio runner. */
	public function current_part(): string {
		return $this->part;
	}

	/**
	 * Añade un aviso al informe.
	 *
	 * @param string $code  Código estable (`CODE_PATTERN`). La pantalla «Kits» agrupa por código.
	 * @param array  $data  Datos del aviso: escalares y arrays cortos. Lo demás se recorta (`MAX_*`).
	 * @param string $level `info`, `warning` o `error`. Un nivel desconocido cuenta como `warning`.
	 */
	public function report( string $code, array $data = array(), string $level = self::INFO ): void {
		$code = $this->checked_code( $code, __METHOD__ );
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = self::WARNING;
		}
		$this->counts[ $code ] = ( isset( $this->counts[ $code ] ) ? $this->counts[ $code ] : 0 ) + 1;
		if ( $this->counts[ $code ] > self::MAX_ENTRIES_PER_CODE ) {
			++$this->dropped;
			return;
		}
		$this->entries[] = array(
			'part'  => $this->part,
			'code'  => $code,
			'level' => $level,
			'data'  => self::clean( $data, 0 ),
		);
	}

	/** Suma a un contador del informe sin añadir una entrada (por ejemplo, `media.embedded`). */
	public function tally( string $code, int $n = 1 ): void {
		$code                  = $this->checked_code( $code, __METHOD__ );
		$this->counts[ $code ] = ( isset( $this->counts[ $code ] ) ? $this->counts[ $code ] : 0 ) + $n;
	}

	/** @return array{entries: array[], counts: int[], dropped: int} */
	public function get_report(): array {
		return array(
			'entries' => $this->entries,
			'counts'  => $this->counts,
			'dropped' => $this->dropped,
		);
	}

	/** ¿Hay algún aviso de nivel `error`? */
	public function has_errors(): bool {
		foreach ( $this->entries as $entry ) {
			if ( self::ERROR === $entry['level'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Huella estable de un valor: sha1 del JSON con las claves de cada mapa ordenadas.
	 *
	 * El orden de una lista sí cuenta. Se usa para «no pisar» (`hash_after`) y para la procedencia (D7).
	 */
	public static function hash( $value ): string {
		$json = json_encode( self::canonical( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		return sha1( is_string( $json ) ? $json : '' );
	}

	/** El valor con las claves de cada mapa ordenadas, recursivamente. Los objetos pasan a arrays. */
	public static function canonical( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonical( $item );
		}
		if ( ! self::is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}
		return $value;
	}

	/** ¿Es un array con claves 0..n-1 en orden? (`array_is_list()` llega en PHP 8.1). */
	public static function is_list( array $value ): bool {
		$i = 0;
		foreach ( $value as $key => $unused ) {
			if ( $key !== $i ) {
				return false;
			}
			++$i;
		}
		return true;
	}

	private function checked_code( string $code, string $method ): string {
		if ( 1 === preg_match( self::CODE_PATTERN, $code ) ) {
			return $code;
		}
		Module::doing_it_wrong( $method, 'Código de informe inválido: ' . Module::describe( $code ) );
		return 'invalid-code';
	}

	/** Recorta un valor para el informe: escalares, cadenas de `MAX_STRING` bytes y arrays de `MAX_ITEMS`. */
	protected static function clean( $value, int $depth ) {
		if ( null === $value || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			if ( strlen( $value ) <= self::MAX_STRING ) {
				return $value;
			}
			return ( function_exists( 'mb_strcut' ) ? mb_strcut( $value, 0, self::MAX_STRING, 'UTF-8' ) : substr( $value, 0, self::MAX_STRING ) ) . '…';
		}
		if ( ! is_array( $value ) ) {
			return gettype( $value );
		}
		if ( $depth >= self::MAX_DEPTH ) {
			return '[' . count( $value ) . ']';
		}
		$out = array();
		$i   = 0;
		foreach ( $value as $key => $item ) {
			if ( ++$i > self::MAX_ITEMS ) {
				$out['_more'] = count( $value ) - self::MAX_ITEMS;
				break;
			}
			$out[ $key ] = self::clean( $item, $depth + 1 );
		}
		return $out;
	}
}
