<?php
/**
 * `wp digitalisimo-backups`: estado, historial, crear, verificar, restaurar y automatizaciones con los casos de uso de
 * la pantalla. Corre con la cuenta del sistema (sin usuario de WordPress) y, en una red, exige `--network`.
 */
$root = sys_get_temp_dir() . '/digitalisimo-cli-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
$GLOBALS['digi']['caps'] = array(); // WP-CLI sin --user: ningún usuario de WordPress.
$cmd = 'digitalisimo-backups';

try {
	Digitalisimo_Backups_Cli::register();
	digi_assert( array( $cmd => 'Digitalisimo_Backups_Cli', $cmd . ' automation' => 'Digitalisimo_Backups_Cli_Automation' ) === WP_CLI::$commands, 'Los comandos registrados no son los documentados.' );
	// WP-CLI arma la ayuda y valida los argumentos con el PHPDoc de cada subcomando.
	foreach ( array( 'status', 'list_', 'create', 'verify', 'restore' ) as $method ) digi_assert( false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', $method ) )->getDocComment(), '## OPTIONS' ), 'Falta la ayuda de ' . $method );
	digi_assert( false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'restore' ) )->getDocComment(), "\n\t * --confirm=<confirm>\n" ), 'restore debe declarar --confirm como obligatorio.' );

	list( $code, $out ) = digi_wp( $cmd, 'status' );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), '"campo":"ready","valor":"sí"' ), 'status no informó una instalación lista: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'status', array(), array( 'format' => 'json' ) );
	$status = json_decode( $out[0], true );
	digi_assert( 0 === $code && true === $status['ready'] && false === strpos( $out[0], $root ), 'status --format=json no es el estado del motor sin rutas.' );

	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'network' => true ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'no es una red' ), 'create --network se aceptó en una instalación individual.' );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'destination' => 'onedrive' ) );
	digi_assert( 1 === $code && 'Error: El destino seleccionado no está conectado o no es válido.' === end( $out ), 'create aceptó un destino sin conexión.' );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'porcelain' => true ) );
	$file = $out[0] ?? '';
	digi_assert( 0 === $code && 1 === count( $out ) && is_file( $root . '/private/' . $file ), 'create --porcelain no imprimió sólo el archivo creado: ' . implode( "\n", $out ) );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	digi_assert( $file === $history[0]['file'] && 'manual' === $history[0]['policy'] && hash_file( 'sha256', $root . '/private/' . $file ) === $history[0]['sha256'], 'WP-CLI no registró el respaldo como la pantalla.' );
	list( $code, $out ) = digi_wp( $cmd, 'create' );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Respaldo completo creado y verificado: ' ), 'create no confirmó el respaldo.' );

	list( $code, $out ) = digi_wp( $cmd, 'list', array(), array( 'format' => 'json' ) );
	$rows = json_decode( $out[0], true );
	digi_assert( 0 === $code && 2 === count( $rows ) && $file === $rows[1]['file'] && true === $rows[1]['restorable'], 'list no devolvió el historial.' );
	list( $code, $out ) = digi_wp( $cmd, 'list', array(), array( 'fields' => 'file,locked' ) );
	digi_assert( 0 === $code && false !== strpos( $out[0], '"locked":"no"' ) && false === strpos( $out[0], 'sha256' ), 'list --fields no limitó las columnas.' );

	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $file ) );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Respaldo íntegro: ' . $file ), 'verify no confirmó el respaldo íntegro.' );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( 'no-existe.zip' ) );
	digi_assert( 1 === $code && 'Error: El respaldo local solicitado no está disponible.' === end( $out ), 'verify no falló con un archivo inexistente.' );

	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'restaurar no' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'RESTAURAR' ) && "<?php // cambiado\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore corrió sin la confirmación.' );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 0 === $code && false !== strpos( end( $out ), 'Copia previa bloqueada: safety-before-restore-' ) && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore no restauró: ' . implode( "\n", $out ) );

	$lock = digi_hold_lock();
	try {
		list( $code, $out ) = digi_wp( $cmd, 'create' );
		digi_assert( 1 === $code && false !== strpos( end( $out ), 'Ya hay un respaldo' ), 'WP-CLI se solapó con otra operación.' );
	} finally {
		digi_release_lock( $lock );
	}

	list( $code, $out ) = digi_wp( $cmd . ' automation', 'list', array(), array( 'format' => 'json' ) );
	digi_assert( 0 === $code && 'monthly-full' === json_decode( $out[0], true )[0]['id'], 'automation list no listó la automatización.' );
	list( $code, $out ) = digi_wp( $cmd . ' automation', 'run', array( 'monthly-full' ) );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Automatización ejecutada: ' ), 'automation run no ejecutó la automatización.' );
	list( $code ) = digi_wp( $cmd . ' automation', 'run', array( 'no-existe' ) );
	digi_assert( 1 === $code, 'automation run no falló con una automatización inexistente.' );

	// Red: --network obligatorio y sólo en el sitio principal (--url).
	$GLOBALS['digi']['multisite'] = true;
	list( $code, $out ) = digi_wp( $cmd, 'create' );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'alcance de red' ), 'En una red, create corrió sin --network.' );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'alcance de red' ), 'En una red, restore corrió sin --network.' );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'network' => true, 'porcelain' => true ) );
	digi_assert( 0 === $code && 'network' === get_option( Digitalisimo_Backups::HISTORY )[0]['scope'], 'create --network no respaldó la red.' );
	$GLOBALS['digi']['main'] = false;
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'network' => true ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'sitio principal' ), 'WP-CLI respaldó la red desde un subsitio.' );
	list( $code, $out ) = digi_wp( $cmd, 'status' );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Warning: Esta instalación todavía no puede respaldarse completa: ' ), 'status de un subsitio no avisó dónde respaldar.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: WP-CLI crea, lista, verifica, restaura y ejecuta automatizaciones con el alcance de red explícito.\n";
