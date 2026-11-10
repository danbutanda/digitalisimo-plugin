<?php
/**
 * Funciones de PHP 8 que WordPress trae en wp-includes/compat.php desde la 5.9 (los plugins exigen la 6.0), con el
 * mismo comportamiento. Las pruebas no cargan WordPress y la CI también las corre con PHP 7.4, el mínimo declarado;
 * con PHP 8 no hace nada. Lo incluyen tests/fixtures/f8-wp.php y las pruebas de Migración con su propio entorno.
 */

if ( ! function_exists( 'str_contains' ) ) {
	function str_contains( $haystack, $needle ) {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}
if ( ! function_exists( 'str_starts_with' ) ) {
	function str_starts_with( $haystack, $needle ) {
		return '' === $needle || 0 === strpos( $haystack, $needle );
	}
}
if ( ! function_exists( 'str_ends_with' ) ) {
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack ) {
			return '' === $needle;
		}
		$len = strlen( $needle );
		return substr( $haystack, -$len, $len ) === $needle;
	}
}
