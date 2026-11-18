<?php
/**
 * Escritorio con DIGITALÍSIMO Migración (auditoría del 2026-11-18): las opciones que Migración lee en cada petición
 * (estado de las copias del Theme Builder y alias de iconos; en el escritorio, además, la recuperación de condiciones)
 * se piden juntas con wp_prime_option_caches() al arrancar, antes que cualquier lectura, en lugar de una consulta cada
 * una. Los nombres coinciden con las constantes de sus clases. La medida está en tests/playground/admin-perf.sh.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_MIGRATION_DIR', __DIR__ . '/../digitalisimo-migration/' );
	$checks = 0;
	function check( $ok, $message ) {
		global $checks;
		if ( ! $ok ) {
			fwrite( STDERR, "FALLA: $message\n" );
			exit( 1 );
		}
		++$checks;
	}
	require __DIR__ . '/../digitalisimo-migration/includes/class-plugin.php';
	$P = 'Digitalisimo\\Migration\\Plugin';
	$constant = static function ( $file, $name ) {
		$src = (string) file_get_contents( DIGITALISIMO_MIGRATION_DIR . $file );
		return preg_match( '/const\s+' . $name . '\s*=\s*\'([^\']+)\'/', $src, $m ) ? $m[1] : null;
	};
	$front = $P::option_names( false );
	$admin = $P::option_names( true );
	check( array( $constant( 'legacy/platform/class-publisher.php', 'OPTION' ), $constant( 'legacy/platform/class-icons.php', 'ALIAS' ) ) === $front, 'Fuera del escritorio se piden juntas las dos opciones que se leen al arrancar (Publisher::OPTION e Icons::ALIAS).' );
	check( array_merge( $front, array( $constant( 'legacy/class-migration.php', 'REPAIRED' ), $constant( 'legacy/class-migration.php', 'REPAIR_TOTALS' ) ) ) === $admin, 'En el escritorio se suman las de la recuperación de condiciones (Migration::REPAIRED y REPAIR_TOTALS).' );
	check( ! in_array( null, $admin, true ) && count( array_unique( $admin ) ) === 4, 'Los cuatro nombres existen como constantes y no se repiten.' );

	// El arranque pide las opciones antes de iniciar los módulos que las leen.
	$boot  = (string) file_get_contents( DIGITALISIMO_MIGRATION_DIR . 'includes/class-plugin.php' );
	$prime = strpos( $boot, 'wp_prime_option_caches( self::option_names( is_admin() ) )' );
	check( false !== $prime && $prime < strpos( $boot, "self::call( 'Platform\\\\Publisher', 'init' )" ) && $prime < strpos( $boot, "self::call( 'Platform\\\\Icons', 'init' )" ) && $prime < strpos( $boot, 'Migration::init()' ), 'Plugin::boot() pide las opciones antes de iniciar Publisher, Icons y la herramienta.' );
	check( $prime > strpos( $boot, "if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) )" ), 'Sin DIGITALÍSIMO Elements (Migración no arranca) no se pide nada.' );
	echo "DIGITALÍSIMO Migración: lectura agrupada de sus opciones validada ($checks comprobaciones).\n";
}
