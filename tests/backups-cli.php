<?php
/**
 * `wp digitalisimo-backups`: estado, historial, crear, verificar, restaurar, automatizaciones y la cola de trabajos
 * (`jobs list`, `jobs cancel`, `jobs run`) con los casos de uso de la pantalla. Corre con la cuenta del sistema (sin
 * usuario de WordPress) y, en una red, exige `--network`. Crear encola y hace avanzar la cola hasta terminar (salvo con
 * `--async`); la frase de un respaldo cifrado llega por `--secret-file` o `--secret-stdin`, nunca por la línea de
 * órdenes, y nunca se imprime.
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
// La rotación ordena por fecha de modificación y estos respaldos nacen en el mismo segundo: sin rotar, cada prueba decide qué existe.
update_option( Digitalisimo_Backups::OPTION, array( 'retention' => '100' ) );
$GLOBALS['digi']['caps'] = array(); // WP-CLI sin --user: ningún usuario de WordPress.
$cmd = 'digitalisimo-backups';
$waits = array();
Digitalisimo_Backups_Cli::$sleep = function( $seconds ) use ( &$waits ) { $waits[] = $seconds; if ( count( $waits ) > 50 ) throw new RuntimeException( 'FALLA: WP-CLI esperó sin fin.' ); };

try {
	Digitalisimo_Backups_Cli::register();
	digi_assert( array( $cmd => 'Digitalisimo_Backups_Cli', $cmd . ' automation' => 'Digitalisimo_Backups_Cli_Automation', $cmd . ' jobs' => 'Digitalisimo_Backups_Cli_Jobs', $cmd . ' destinations' => 'Digitalisimo_Backups_Cli_Destinations', $cmd . ' upload' => 'Digitalisimo_Backups_Cli_Upload', $cmd . ' backuply' => 'Digitalisimo_Backups_Cli_Backuply', $cmd . ' migration' => 'Digitalisimo_Backups_Cli_Migration', $cmd . ' pre-update' => 'Digitalisimo_Backups_Cli_Pre_Update', $cmd . ' restore-config' => 'Digitalisimo_Backups_Cli_Restore_Config' ) === WP_CLI::$commands, 'Los comandos registrados no son los documentados.' );
	// WP-CLI arma la ayuda y valida los argumentos con el PHPDoc de cada subcomando.
	foreach ( array( 'Digitalisimo_Backups_Cli' => array( 'status', 'list_', 'create', 'verify', 'restore' ), 'Digitalisimo_Backups_Cli_Automation' => array( 'list_', 'run' ), 'Digitalisimo_Backups_Cli_Jobs' => array( 'list_', 'cancel' ) ) as $class => $methods ) {
		foreach ( $methods as $method ) digi_assert( false !== strpos( (string) ( new ReflectionMethod( $class, $method ) )->getDocComment(), '## OPTIONS' ), 'Falta la ayuda de ' . $class . '::' . $method );
	}
	$restore_doc = (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'restore' ) )->getDocComment();
	digi_assert( false !== strpos( $restore_doc, "\n\t * --confirm=<confirm>\n" ) && false !== strpos( $restore_doc, '[--secret-file=<path>]' ) && false !== strpos( $restore_doc, '[--secret-stdin]' ) && false === strpos( $restore_doc, '[--secret=' ), 'restore debe declarar --confirm y la frase sólo por archivo o STDIN.' );

	list( $code, $out ) = digi_wp( $cmd, 'status' );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), '"campo":"ready","valor":"sí"' ), 'status no informó una instalación lista: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'status', array(), array( 'format' => 'json' ) );
	$status = json_decode( $out[0], true );
	digi_assert( 0 === $code && true === $status['ready'] && 0 === $status['jobs'] && false === strpos( $out[0], $root ), 'status --format=json no es el estado del motor sin rutas.' );

	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'network' => true ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'no es una red' ), 'create --network se aceptó en una instalación individual.' );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'destination' => 'onedrive' ) );
	digi_assert( 1 === $code && 'Error: El destino seleccionado no está conectado o no es válido.' === end( $out ), 'create aceptó un destino sin conexión.' );
	// create encola y hace avanzar la cola en este proceso: --porcelain imprime sólo el archivo terminado.
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'porcelain' => true ) );
	$file = $out[0] ?? '';
	digi_assert( 0 === $code && 1 === count( $out ) && is_file( $root . '/private/' . $file ), 'create --porcelain no imprimió sólo el archivo creado: ' . implode( "\n", $out ) );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	digi_assert( $file === $history[0]['file'] && 'manual' === $history[0]['policy'] && hash_file( 'sha256', $root . '/private/' . $file ) === $history[0]['sha256'], 'WP-CLI no registró el respaldo como la pantalla.' );
	digi_assert( 'cli' === Digitalisimo_Backups_Jobs::all()[0]['spec']['origin']['via'] && array() === Digitalisimo_Backups_Jobs::active(), 'El trabajo de WP-CLI no anota su origen o no terminó.' );
	list( $code, $out ) = digi_wp( $cmd, 'create' );
	digi_assert( 0 === $code && in_array( 'Pendiente · Comprobaciones (0 %)', $out, true ) && in_array( 'Completo (100 %)', $out, true ) && 0 === strpos( end( $out ), 'Success: Respaldo completo creado y verificado: ' ), 'create no mostró el avance ni confirmó el respaldo: ' . implode( "\n", $out ) );

	// Con el almacén tomado por otra operación, create espera (sin fin no) y termina cuando se libera.
	$lock = digi_hold_lock();
	$waits = array();
	Digitalisimo_Backups_Cli::$sleep = function( $seconds ) use ( &$waits, &$lock ) { $waits[] = $seconds; if ( $lock ) { digi_release_lock( $lock ); $lock = null; } };
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'porcelain' => true ) );
	digi_assert( 0 === $code && array( 5 ) === $waits && null === $lock && is_file( $root . '/private/' . $out[0] ), 'create no esperó a que terminara la otra operación: ' . implode( "\n", $out ) . ' ' . json_encode( $waits ) );
	Digitalisimo_Backups_Cli::$sleep = function( $seconds ) use ( &$waits ) { $waits[] = $seconds; if ( count( $waits ) > 50 ) throw new RuntimeException( 'FALLA: WP-CLI esperó sin fin.' ); };

	// --async sólo encola; `jobs list`, `jobs cancel` y `jobs run` operan sobre la misma cola que la pantalla.
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'async' => true, 'porcelain' => true ) );
	$id = $out[0] ?? '';
	digi_assert( 0 === $code && Digitalisimo_Backups_Job_Store::valid_id( $id ) && 'queued' === Digitalisimo_Backups_Jobs::get( $id )['status'], 'create --async no dejó el trabajo en cola: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'create' );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'Ya hay un respaldo manual pendiente' ), 'create duplicó un respaldo manual pendiente.' );
	list( $code, $out ) = digi_wp( $cmd . ' jobs', 'list', array(), array( 'format' => 'json' ) );
	$rows = json_decode( $out[0], true );
	digi_assert( 0 === $code && $id === $rows[0]['id'] && 'Pendiente' === $rows[0]['status_label'] && false === strpos( $out[0], $root ), 'jobs list no mostró el trabajo en cola: ' . $out[0] );
	list( $code, $out ) = digi_wp( $cmd . ' jobs', 'cancel', array( $id ) );
	digi_assert( 0 === $code && 'Success: Trabajo cancelado.' === end( $out ) && 'cancelled' === Digitalisimo_Backups_Jobs::get( $id )['status'], 'jobs cancel no canceló el trabajo: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd . ' jobs', 'cancel', array( $id ) );
	digi_assert( 1 === $code && 'Error: El trabajo ya terminó.' === end( $out ), 'jobs cancel aceptó un trabajo terminado.' );
	foreach ( array( '../jobs', '0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e', '' ) as $bad ) {
		list( $code ) = digi_wp( $cmd . ' jobs', 'cancel', array( $bad ) );
		digi_assert( 1 === $code, 'jobs cancel aceptó ' . $bad );
	}
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'async' => true, 'porcelain' => true ) );
	$id = $out[0];
	list( $code, $out ) = digi_wp( $cmd . ' jobs', 'run' );
	digi_assert( 0 === $code && 'Success: Cola avanzada.' === end( $out ) && 'success' === Digitalisimo_Backups_Jobs::get( $id )['status'], 'jobs run no terminó el trabajo en cola: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd . ' jobs', 'run' );
	digi_assert( 0 === $code && 'Success: No había trabajo listo.' === end( $out ), 'jobs run no informó una cola vacía.' );

	list( $code, $out ) = digi_wp( $cmd, 'list', array(), array( 'format' => 'json' ) );
	$rows = json_decode( $out[0], true );
	digi_assert( 0 === $code && 4 === count( $rows ) && $file === $rows[3]['file'] && true === $rows[3]['restorable'] && 'zip' === $rows[3]['container'], 'list no devolvió el historial.' );
	list( $code, $out ) = digi_wp( $cmd, 'list', array(), array( 'fields' => 'file,locked' ) );
	digi_assert( 0 === $code && false !== strpos( $out[0], '"locked":"no"' ) && false === strpos( $out[0], 'sha256' ), 'list --fields no limitó las columnas.' );

	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $file ) );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Respaldo íntegro: ' . $file ) && false !== strpos( end( $out ), 'registrado en este sitio' ), 'verify no confirmó el respaldo íntegro: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( 'no-existe.zip' ) );
	digi_assert( 1 === $code && 'Error: El respaldo local solicitado no está disponible.' === end( $out ), 'verify no falló con un archivo inexistente.' );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $file ), array( 'secret' => 'frase en la línea de órdenes' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'nunca va en la línea de órdenes' ) && false === strpos( implode( "\n", $out ), 'frase en la línea' ), 'verify aceptó --secret en la línea de órdenes.' );

	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	foreach ( array( 'restore', 'verify' ) as $method ) digi_assert( false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', $method ) )->getDocComment(), "[--addresses=<addresses>]\n\t * : " ), $method . ' debe declarar --addresses (E1).' );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR', 'addresses' => 'otra' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'addresses debe ser current' ) && "<?php // cambiado\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore con un --addresses inválido no se detuvo: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'restaurar no' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'RESTAURAR' ) && "<?php // cambiado\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore corrió sin la confirmación.' );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 0 === $code && false !== strpos( end( $out ), 'Copia previa bloqueada: safety-before-restore-' ) && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore no restauró: ' . implode( "\n", $out ) );

	$lock = digi_hold_lock();
	try {
		list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR' ) );
		digi_assert( 1 === $code && false !== strpos( end( $out ), 'Ya hay un respaldo' ), 'WP-CLI restauró sobre otra operación.' );
	} finally {
		digi_release_lock( $lock );
	}

	list( $code, $out ) = digi_wp( $cmd . ' automation', 'list', array(), array( 'format' => 'json' ) );
	digi_assert( 0 === $code && 'monthly-full' === json_decode( $out[0], true )[0]['id'], 'automation list no listó la automatización.' );
	list( $code, $out ) = digi_wp( $cmd . ' automation', 'run', array( 'monthly-full' ) );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Respaldo completo creado y verificado: ' ) && 'monthly-full' === get_option( Digitalisimo_Backups::HISTORY )[0]['policy'], 'automation run no ejecutó la automatización: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd . ' automation', 'run', array( 'monthly-full' ), array( 'async' => true ) );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Respaldo en cola (trabajo ' ), 'automation run --async no encoló.' );
	digi_wp( $cmd . ' jobs', 'run' );
	list( $code ) = digi_wp( $cmd . ' automation', 'run', array( 'no-existe' ) );
	digi_assert( 1 === $code, 'automation run no falló con una automatización inexistente.' );

	// Seguridad: un respaldo cifrado con la frase del sitio y el mismo archivo en «otro servidor» (sin llavero).
	$passphrase = 'frase de WP-CLI con espacios, "comillas" y $variables';
	$key = Digitalisimo_Backups_Keys::set_passphrase( $passphrase )['key_id'];
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true, 'require_signed' => false ) );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'porcelain' => true ) );
	$sealed = $out[0] ?? '';
	digi_assert( 0 === $code && '.dgbk' === substr( $sealed, -5 ) && is_file( $root . '/private/' . $sealed ), 'create no dejó un respaldo cifrado: ' . implode( "\n", $out ) );
	$keyring = get_option( Digitalisimo_Backups_Keys::KEYRING );
	delete_option( Digitalisimo_Backups_Keys::KEYRING );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $sealed ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'cifrado con la clave ' . Digitalisimo_Backups_Keys::short( $key ) ) && false !== strpos( end( $out ), '--secret-file=<ruta> o --secret-stdin' ), 'verify de un .dgbk sin clave no explicó cómo pasar la frase: ' . implode( "\n", $out ) );
	$secret_file = $root . '/frase.txt';
	file_put_contents( $secret_file, $passphrase . "\n" );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $sealed ), array( 'secret-file' => $secret_file ) );
	digi_assert( 0 === $code && false !== strpos( end( $out ), 'cifrado, ' ) && false !== strpos( end( $out ), 'firmado con la clave ' . Digitalisimo_Backups_Keys::short( $key ) ), 'verify --secret-file no comprobó el .dgbk: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $sealed ), array( 'secret-file' => $root . '/no-existe.txt' ) );
	digi_assert( 1 === $code && 'Error: No se puede leer el archivo de --secret-file.' === end( $out ), 'verify aceptó un --secret-file inexistente.' );
	list( $code, $out ) = digi_wp( $cmd, 'verify', array( $sealed ), array( 'secret-file' => $secret_file, 'secret-stdin' => true ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'no los dos' ), 'verify aceptó dos fuentes de la frase.' );
	file_put_contents( $root . '/equivocada.txt', "otra frase equivocada pero larga\n" );
	Digitalisimo_Backups_Cli::$stdin = $root . '/equivocada.txt';
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado antes del .dgbk\n" );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $sealed ), array( 'confirm' => 'RESTAURAR', 'secret-stdin' => true ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'no corresponde' ) && false === strpos( implode( "\n", $out ), 'equivocada' ) && "<?php // cambiado antes del .dgbk\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore con una frase equivocada no se rechazó sin repetirla.' );
	Digitalisimo_Backups_Cli::$stdin = $secret_file;
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $sealed ), array( 'confirm' => 'RESTAURAR', 'secret-stdin' => true, 'adopt-secret' => true ) );
	digi_assert( 0 === $code && false !== strpos( end( $out ), 'La frase escrita firma ahora los respaldos nuevos (clave ' . Digitalisimo_Backups_Keys::short( $key ) . ')' ) && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore --secret-stdin --adopt-secret no restauró el .dgbk: ' . implode( "\n", $out ) );
	digi_assert( false === strpos( implode( "\n", $out ), 'comillas' ) && false === strpos( implode( "\n", $out ), $key ), 'WP-CLI imprimió la frase o la huella completa.' );
	// En una terminal, --secret-stdin oculta el eco y lee la línea tal cual, como por una tubería: la pantalla guarda la
	// frase sin recortar, y \cli\prompt() la recortaría (trim), así que una frase con espacios en los extremos no se abriría.
	file_put_contents( $root . '/terminal.txt', "  frase con espacios en los extremos \t\n" );
	Digitalisimo_Backups_Cli::$stdin = $root . '/terminal.txt';
	Digitalisimo_Backups_Cli::$tty = true;
	\cli\Shell::$calls = array();
	try {
		digi_assert( "  frase con espacios en los extremos \t" === Digitalisimo_Backups_Cli::secret( array( 'secret-stdin' => true ) ) && array( true, false ) === \cli\Shell::$calls, 'En una terminal, --secret-stdin recortó la frase o no ocultó el eco: ' . json_encode( \cli\Shell::$calls ) );
		file_put_contents( $root . '/terminal.txt', "\n" );
		\cli\Shell::$calls = array();
		try {
			Digitalisimo_Backups_Cli::secret( array( 'secret-stdin' => true ) );
			throw new RuntimeException( 'FALLA: una frase vacía en la terminal se aceptó.' );
		} catch ( Digi_Cli_Exit $exit ) {
			digi_assert( 'La frase o clave del respaldo está vacía.' === $exit->getMessage() && array( true, false ) === \cli\Shell::$calls, 'Una frase vacía en la terminal no dio un error claro o dejó el eco oculto.' );
		}
	} finally {
		Digitalisimo_Backups_Cli::$tty = null;
	}
	digi_assert( Digitalisimo_Backups_Keys::can_open( $key ), '--adopt-secret no guardó la frase en el llavero.' );
	list( $code, $out ) = digi_wp( $cmd, 'list', array(), array( 'format' => 'json' ) );
	digi_assert( false === strpos( $out[0], 'comillas' ) && false === strpos( $out[0], '"wrap"' ), 'list expuso la frase o su envoltura.' );
	update_option( Digitalisimo_Backups_Keys::KEYRING, $keyring );
	delete_option( Digitalisimo_Backups_Security::OPTION );

	// Red: --network obligatorio y sólo en el sitio principal (--url).
	$GLOBALS['digi']['multisite'] = true;
	list( $code, $out ) = digi_wp( $cmd, 'create' );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'alcance de red' ), 'En una red, create corrió sin --network.' );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'alcance de red' ), 'En una red, restore corrió sin --network.' );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'network' => true, 'porcelain' => true ) );
	digi_assert( 0 === $code && 'network' === get_option( Digitalisimo_Backups::HISTORY )[0]['scope'], 'create --network no respaldó la red.' );
	$network_file = $out[0];
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado en la red\n" );
	list( $code, $out ) = digi_wp( $cmd, 'restore', array( $network_file ), array( 'confirm' => 'RESTAURAR', 'network' => true ) );
	digi_assert( 0 === $code && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'restore --network no restauró la red: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd . ' automation', 'run', array( 'monthly-full' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'alcance de red' ), 'En una red, automation run corrió sin --network.' );
	list( $code, $out ) = digi_wp( $cmd . ' automation', 'run', array( 'monthly-full' ), array( 'network' => true ) );
	digi_assert( 0 === $code && 'network' === get_option( Digitalisimo_Backups::HISTORY )[0]['scope'] && 'monthly-full' === get_option( Digitalisimo_Backups::HISTORY )[0]['policy'], 'automation run --network no respaldó la red: ' . implode( "\n", $out ) );
	$GLOBALS['digi']['main'] = false;
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'network' => true ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'sitio principal' ), 'WP-CLI respaldó la red desde un subsitio.' );
	foreach ( array( array( 'list', array() ), array( 'run', array() ), array( 'cancel', array( '0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e' ) ) ) as $call ) {
		list( $code, $out ) = digi_wp( $cmd . ' jobs', $call[0], $call[1] );
		digi_assert( 1 === $code && false !== strpos( end( $out ), 'sitio principal' ), 'jobs ' . $call[0] . ' operó desde un subsitio.' );
	}
	list( $code, $out ) = digi_wp( $cmd, 'status' );
	digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Warning: Esta instalación todavía no puede respaldarse completa: ' ), 'status de un subsitio no avisó dónde respaldar.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: WP-CLI crea (en cola hasta terminar), lista, verifica, restaura, ejecuta automatizaciones y gestiona la cola con el alcance de red explícito y la frase fuera de la línea de órdenes.\n";
