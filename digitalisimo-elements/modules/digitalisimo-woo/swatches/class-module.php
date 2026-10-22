<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-data.php';

/**
 * Muestras de variación (frente D de F4-Woo, V.6 de la especificación).
 *
 * - **Datos** (`Data`): las metas de término `color` e `image` de WooCommerce y la opción por atributo
 *   `digitalisimo_elements_swatches`.
 * - **Pantallas** (`Admin`): «Tipo de muestra», forma y tamaño en Productos → Atributos; color e imagen en los
 *   términos de un atributo que no es `wc-visual` (en uno `wc-visual` escriben los campos de WooCommerce).
 * - **Formulario** (`Form`): el `<select>` de WooCommerce queda intacto y oculto, y un grupo de radios lo maneja.
 * - **Bucle** (`Loop`, `Loop_Widget`): enlaces que preseleccionan la variación en la ficha.
 * - **Estilos** (`Atc_Styles`): sección opt-in «Muestras» en los dos widgets «Añadir al carrito».
 *
 * Los ganchos de renombrado y borrado de atributos se registran siempre (también en REST, CSV, WP-CLI y K): mueven
 * o borran la entrada, nunca la reescriben. Escribir el tipo sólo ocurre con el nonce propio del formulario.
 */
final class Module {
	/** Símbolos de WooCommerce que usa el submódulo. */
	const SYMBOLS = array( 'wc_get_attribute_taxonomies()', 'wc_get_product_terms()', 'wc_attribute_label()', 'WC_Product' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! self::guard() ) {
			return;
		}
		require_once __DIR__ . '/class-form.php';
		require_once __DIR__ . '/class-admin.php';
		require_once __DIR__ . '/class-atc-styles.php';
		Form::init();
		Admin::init();
		Atc_Styles::init();
	}

	/** Guarda del frente A (`Woo\Module::requires()`); sin él, la comprobación de símbolos local. */
	private static function guard(): bool {
		$module = '\\Digitalisimo\\Elements\\Woo\\Module';
		if ( class_exists( $module ) && method_exists( $module, 'requires' ) ) {
			return (bool) $module::requires( 'swatches', self::SYMBOLS );
		}
		foreach ( self::SYMBOLS as $symbol ) {
			$exists = '()' === substr( $symbol, -2 ) ? function_exists( substr( $symbol, 0, -2 ) ) : class_exists( $symbol );
			if ( ! $exists ) {
				return false;
			}
		}
		return true;
	}
}
