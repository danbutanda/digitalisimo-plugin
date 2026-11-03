<?php
/**
 * Preparación común de f8.sh (F8, frente J). Pasos del blueprint, cada uno en su petición:
 *
 * - `DIGI_F8_PHASE=activate`: instala la sonda y el registro de errores (mu-plugins cargados desde la carpeta montada
 *   de la corrida), el mu-plugin *clean-room* de la cabecera (P2, P3 y P9) y, con WP-Cron apagado, deja que el archivo
 *   del escenario (`pN.php` → `digi_f8_activate()`) active sus plugins y escriba sus opciones.
 * - `DIGI_F8_PHASE=content`: el escenario crea su contenido (`digi_f8_content()`) con los plugins ya cargados.
 *   Después, `ready.txt`.
 *
 * Los registros de errores (`fatal.log`, `php-errors.log`) nunca se vacían durante la corrida: el arnés los conserva por
 * tanda en `$OUT/logs/`.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once WP_CONTENT_DIR . '/probe/lib.php';

$mode  = digi_f8_mode();
$phase = (string) getenv( 'DIGI_F8_PHASE' );
$file  = DIGI_F8_DIR . '/' . basename( (string) ( $mode['scenario_file'] ?? '' ) );
if ( ! is_file( $file ) ) {
	digi_f8_fail( 'Falta el archivo del escenario: ' . $file );
	return;
}
require_once $file;

register_shutdown_function(
	static function () use ( $phase ) {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			digi_f8_fail( 'Error fatal en la preparación (' . $phase . '): ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] );
		}
	}
);

if ( 'activate' === $phase ) {
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-f8-probe.php', "<?php\n// Sólo Playground (f8.sh).\nrequire WP_CONTENT_DIR . '/probe/probe.php';\n" );
	if ( ! empty( $mode['ep_header'] ) ) {
		file_put_contents( WPMU_PLUGIN_DIR . '/digi-f8-ep-header.php', "<?php\n// Sólo Playground (f8.sh): cabecera simulada del origen (clean-room).\nrequire WP_CONTENT_DIR . '/probe/mu-ep-header.php';\n" );
	}
	// Fatales de cualquier petición a fatal.log; avisos de PHP de Migración, de Elements (sin el núcleo) y del tema a
	// php-errors.log. Los dos se acumulan: nunca se vacían durante la corrida.
	file_put_contents(
		WPMU_PLUGIN_DIR . '/digi-f8-errors.php',
		'<?php
// Sólo Playground (f8.sh).
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", gmdate( "c" ) . " " . ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " " . ( $_POST["action"] ?? "" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );
$GLOBALS["digi_f8_prev_handler"] = set_error_handler( static function ( $no, $str, $file = "", $line = 0 ) {
	$own = ( false !== strpos( (string) $file, "/plugins/digitalisimo-" ) && false === strpos( (string) $file, "/digitalisimo-elements/elementor/" ) ) || false !== strpos( (string) $file, "/themes/digitalisimo-theme/" );
	if ( $own && ( $no & ( E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED ) ) ) {
		file_put_contents( "/wordpress/wp-content/probe/php-errors.log", gmdate( "c" ) . " " . ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " [" . $no . "] " . $str . " en " . $file . ":" . $line . "\n", FILE_APPEND );
	}
	$prev = $GLOBALS["digi_f8_prev_handler"];
	return $prev ? call_user_func( $prev, $no, $str, $file, $line ) : false;
} );
'
	);
	if ( ! function_exists( 'digi_f8_activate' ) ) {
		digi_f8_fail( 'El escenario no define digi_f8_activate()' );
		return;
	}
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'elementor_onboarded', true );
	update_option( 'elementor_google_font', '0' );
	update_option( 'elementor_element_cache_ttl', 'disable' );
	digi_f8_activate( $mode );
	return;
}

if ( 'content' === $phase ) {
	if ( ! function_exists( 'digi_f8_content' ) ) {
		digi_f8_fail( 'El escenario no define digi_f8_content()' );
		return;
	}
	wp_set_current_user( 1 );
	digi_f8_content( $mode );
	flush_rewrite_rules( false );
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $site ) {
			switch_to_blog( $site );
			update_option( 'digi_f8_ready', 1 );
			restore_current_blog();
		}
	} else {
		update_option( 'digi_f8_ready', 1 );
	}
	file_put_contents( DIGI_F8_DIR . '/ready.txt', gmdate( 'c' ) );
	return;
}

digi_f8_fail( 'DIGI_F8_PHASE desconocida: ' . $phase );
