<?php
namespace Digitalisimo\Elements\Floating;

defined( 'ABSPATH' ) || exit;

/**
 * Sonda de una presentación (C8): la usa una clase que hereda la base del núcleo de su familia para construir, en una pila
 * de controles aparte, los controles de una sola variante; `Variants::collect()` fija la presentación, toma la pila y la
 * suelta. La sonda nunca se registra como widget.
 */
trait Probe {
	/** @var string Presentación que se está construyendo ('' fuera de `Variants::collect()`). */
	private static $presentation = '';

	public static function use_presentation( string $presentation ): void {
		self::$presentation = $presentation;
	}

	public static function presentation(): string {
		return self::$presentation;
	}

	/** Nombre único por presentación: el gestor de controles guarda una pila por nombre. */
	public function get_name(): string {
		return 'digitalisimo-probe-' . static::FAMILY . '-' . ( '' === self::$presentation ? 'none' : self::$presentation );
	}

	public function show_in_panel(): bool {
		return false;
	}

	protected function render(): void {}
}
