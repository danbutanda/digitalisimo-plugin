<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Contexto de una reversión: lo que reciben las partes en `Part::revert()`.
 *
 * Revertir nunca pisa lo que el administrador cambió después de importar: la parte compara con `unchanged()` la
 * huella actual con la que guardó al importar (`Part_Context::hash()`), y si no coincide quita sólo lo que añadió
 * y lo anota en el informe. Lo que reutilizó una reimportación (D7) no es de la sesión y no se toca.
 */
final class Revert_Context extends Part_Context {
	const MODE_ADD   = Module::MODE_ADD;
	const MODE_FRESH = Module::MODE_FRESH;

	/** @var string */
	private $session;

	/** @var string */
	private $mode;

	/** @var int */
	private $user;

	/** @var int */
	private $kit_id;

	/**
	 * @param array $args {
	 *     @type string $session ID de la sesión del núcleo que se revierte (obligatorio).
	 *     @type string $mode    Modo con que se importó: `add` o `fresh`.
	 *     @type int    $user    Usuario que revierte.
	 *     @type int    $kit_id  Kit activo del sitio.
	 * }
	 *
	 * @throws \InvalidArgumentException Sin una sesión válida.
	 */
	public function __construct( array $args ) {
		$session = isset( $args['session'] ) && is_string( $args['session'] ) ? $args['session'] : '';
		if ( 1 !== preg_match( Import_Context::SESSION_PATTERN, $session ) ) {
			throw new \InvalidArgumentException( 'La reversión necesita una sesión válida.' );
		}
		$this->session = $session;
		$this->mode    = isset( $args['mode'] ) && self::MODE_FRESH === $args['mode'] ? self::MODE_FRESH : self::MODE_ADD;
		$this->user    = isset( $args['user'] ) ? max( 0, (int) $args['user'] ) : 0;
		$this->kit_id  = isset( $args['kit_id'] ) ? max( 0, (int) $args['kit_id'] ) : 0;
	}

	public function session(): string {
		return $this->session;
	}

	/** Modo con que se importó: `add` o `fresh`. */
	public function mode(): string {
		return $this->mode;
	}

	public function is_fresh(): bool {
		return self::MODE_FRESH === $this->mode;
	}

	public function is_add(): bool {
		return self::MODE_ADD === $this->mode;
	}

	public function user(): int {
		return $this->user;
	}

	public function kit_id(): int {
		return $this->kit_id;
	}

	/**
	 * ¿El valor actual sigue siendo el que dejó la importación?
	 *
	 * @param mixed  $current Valor que hay ahora en el sitio.
	 * @param string $hash    Huella guardada al importar (`Part_Context::hash()` del valor que se escribió).
	 */
	public function unchanged( $current, string $hash ): bool {
		return '' !== $hash && hash_equals( $hash, self::hash( $current ) );
	}
}
