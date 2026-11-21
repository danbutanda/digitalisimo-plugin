<?php
/**
 * DIGITALÍSIMO: compatibilidad con el PHP típico de un hijo de Hello, sólo en paridad (el tema sustituye a Hello o a su
 * hijo). Declara, si no existen, la versión de Hello 3.5.1 y sus funciones públicas más usadas por los hijos, con un
 * comportamiento equivalente y seguro (escrito aquí, sin código de Hello), para que el functions.php del hijo en Código
 * PHP de DIGITALÍSIMO Tools corra igual con este tema. La lista está en digitalisimo_theme_child_compat_names()
 * (inc/child.php), que también lee el lector de riesgos.
 *
 * Todo se declara en `after_setup_theme` justo después de Código PHP de Tools (que corre en PHP_INT_MIN): así un hijo que
 * reemplaza una función de Hello (Hello las declara con function_exists) la declara primero y no choca. Con Hello, el
 * functions.php de un hijo también se carga antes que el de Hello, así que en su primer nivel tampoco tenía nada de Hello.
 * Nunca con Hello activo ni fuera de paridad. Filtrable con `digitalisimo_theme_hello_compat`.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'digitalisimo_theme_hello_compat', PHP_INT_MIN + 1 );

/** Declara las constantes y funciones de Hello que falten (sólo en paridad y sin Hello cargado). */
function digitalisimo_theme_hello_compat() {
	if ( 'hello-elementor' === get_template() || ! digitalisimo_theme_parity() || ! apply_filters( 'digitalisimo_theme_hello_compat', true ) ) {
		return;
	}
	// Sólo la versión. Las rutas de Hello (HELLO_THEME_PATH…) apuntarían a archivos que este tema no tiene y EHP_THEME_SLUG
	// la usa Hello Plus para saber si hay un tema Hello activo: no se declaran (el lector las deja como riesgo).
	if ( ! defined( 'HELLO_ELEMENTOR_VERSION' ) ) {
		define( 'HELLO_ELEMENTOR_VERSION', '3.5.1' );
	}
	require __DIR__ . '/hello-compat-functions.php';
}
