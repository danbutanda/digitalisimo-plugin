<?php
/**
 * Lo que las pruebas necesitan para correr también con PHP 7.4, el mínimo que declaran los plugins (el trabajo php74 de
 * la CI). No cambia nada con PHP 8.
 *
 * - Los polyfills de `str_contains()` y similares que WordPress trae en wp-includes/compat.php (tests/fixtures/wp-compat.php):
 *   las pruebas no cargan WordPress.
 * - `digi_test_accessible()`: abre un `ReflectionMethod` o `ReflectionProperty` sobre un miembro no público. PHP 7.4 exige
 *   `setAccessible( true )` para invocarlo o leerlo; desde PHP 8.1 no hace falta y la llamada no hace nada.
 */
require_once __DIR__ . '/wp-compat.php';

if ( ! function_exists( 'digi_test_accessible' ) ) {
	function digi_test_accessible( $reflector ) {
		$reflector->setAccessible( true );
		return $reflector;
	}
}
