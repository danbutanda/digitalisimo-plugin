<?php
namespace Digitalisimo\Elements\F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Regla `[hidden]` del CSS del documento (C2-2, V.3, V.9).
 *
 * La hoja del núcleo da `display` a contenedores (`.e-con{display:var(--display)}`) y columnas
 * (`.elementor-column{display:flex}`) sin ninguna regla `[hidden]`, así que el atributo no los oculta. Los frentes que
 * ocultan (acciones de B, formularios condicionales de J) llaman a `add()` desde su `elementor/element/parse_css` para
 * el elemento que lo necesita: la regla (≈ 70 B) entra una sola vez en el CSS de ese documento, como los presets de F7a,
 * sin peticiones nuevas. Un documento que no oculta nada no la lleva.
 */
final class Hidden_Rule {
	const RULE = ':is(.elementor-element,.elementor-column)[hidden]{display:none!important}';

	/** @var array<int,\WeakReference> Hojas que ya la llevan, por `spl_object_id()`; la referencia débil evita confundir un ID reutilizado. */
	private static $added = array();

	/**
	 * Añade la regla una sola vez a la hoja de ese CSS de documento (`Elementor\Core\Files\CSS\Base`).
	 *
	 * @param object $post_css CSS del documento (`Post`, `Post_Preview`…).
	 */
	public static function add( $post_css ): void {
		if ( ! is_object( $post_css ) || ! method_exists( $post_css, 'get_stylesheet' ) ) {
			return;
		}
		$sheet = $post_css->get_stylesheet();
		if ( ! is_object( $sheet ) || ! method_exists( $sheet, 'add_raw_css' ) ) {
			return;
		}
		$key = spl_object_id( $sheet );
		if ( isset( self::$added[ $key ] ) && self::$added[ $key ]->get() === $sheet ) {
			return;
		}
		$sheet->add_raw_css( self::RULE );
		if ( count( self::$added ) >= 256 ) {
			// Al regenerar el CSS de todo el sitio se crean miles de hojas: se olvidan las que ya no existen.
			self::$added = array_filter(
				self::$added,
				static function ( $ref ) {
					return null !== $ref->get();
				}
			);
		}
		self::$added[ $key ] = \WeakReference::create( $sheet );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$added = array();
	}
}
