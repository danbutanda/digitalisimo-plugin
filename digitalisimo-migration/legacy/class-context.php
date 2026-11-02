<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Contexto de la conversión en curso: qué documento se convierte y en qué modo.
 *
 * - `run`: la tarea de Migrar (etapas del runner, WP-CLI). Puede crear medios, plantillas, Loop Items, filas del
 *   registro y elementos del informe.
 * - `analysis`: el análisis previo. No escribe nada.
 * - `editor`: el filtro de K en el editor (marcadores). La conversión es **pura**: ningún convertidor crea medios,
 *   Loop Items, plantillas, fragmentos, filas del registro ni elementos del informe; si los necesitaría, devuelve null
 *   y el marcador queda para la etapa `markers` (V.7, V.12).
 *
 * Es una pila: `begin()` y `end()` se anidan (una etapa que convierte un marcador dentro de un documento). Sin nada
 * empezado, el modo es `run` con el documento 0, el comportamiento de 1.0.20.
 */
final class Context {
	const MODES = array( 'run', 'analysis', 'editor' );

	/** @var array<int, array{post_id:int, mode:string}> */
	private static $stack = array();

	/** Empieza la conversión de un documento en un modo (`run`, `analysis` o `editor`; otro valor cuenta como `editor`). */
	public static function begin( int $post_id, string $mode ): void {
		self::$stack[] = array(
			'post_id' => max( 0, $post_id ),
			// Un modo desconocido nunca escribe: falla cerrado.
			'mode'    => in_array( $mode, self::MODES, true ) ? $mode : 'editor',
		);
	}

	/** Termina el último begin(). */
	public static function end(): void {
		array_pop( self::$stack );
	}

	/** Documento en curso (0 si no se sabe). */
	public static function post_id(): int {
		$top = end( self::$stack );
		return is_array( $top ) ? (int) $top['post_id'] : 0;
	}

	/** Modo en curso (`run` sin nada empezado). */
	public static function mode(): string {
		$top = end( self::$stack );
		return is_array( $top ) ? (string) $top['mode'] : 'run';
	}

	/** ¿La conversión debe ser pura (sin efectos)? En el editor y en el análisis. */
	public static function pure(): bool {
		return 'run' !== self::mode();
	}

	/** Profundidad de la pila (pruebas y diagnóstico). */
	public static function depth(): int {
		return count( self::$stack );
	}

	/** Vacía la pila (pruebas). */
	public static function reset(): void {
		self::$stack = array();
	}
}
