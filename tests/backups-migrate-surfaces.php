<?php
/**
 * Migración: pantalla (pestaña «Migrar» y sus acciones admin-post) y WP-CLI (`wp digitalisimo-backups migrate` y
 * `wp digitalisimo-backups migration …`) sobre los mismos casos de uso (Digitalisimo_Backups_Service), con los dos
 * sitios de tests/backups-migrate/env.php:
 *   (k) WP-CLI registra los comandos con su ayuda; la clave nunca va en la línea de órdenes (`--from-key=` es un error)
 *       sino en un archivo o en STDIN; `create-key --key-file` la deja en un archivo nuevo 0600 sin imprimirla;
 *   (l) `migrate --from-key-file` pide confirmación (sin `--yes` no hace nada), migra hasta terminar y no imprime la
 *       clave; `migration status|keys|revoke|undo`;
 *   (m) pantalla: «Generar clave» la muestra una sola vez en una respuesta propia (no en una redirección), la pestaña
 *       nunca la repite, «Migrar» con una clave mal formada no la devuelve, «Revocar», «Permitir HTTP», «Migrar» y
 *       «Deshacer la migración» por admin-post con nonce y capability.
 */
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-migrate/env.php';
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';

mig_site( 'origen', 'origen.test', 'wp_', array( 'wp-content/uploads/2026/11/foto.jpg' => random_bytes( 2048 ) ) );
mig_site( 'destino', 'destino.test', 'dst_' );
$GLOBALS['mig']['sites']['origen']['db']->posts = array( 7 => array( 'ID' => '7', 'post_content' => '<a href="https://origen.test/">inicio</a>', 'guid' => 'https://origen.test/?p=7' ) );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['bk_filters']['digitalisimo_backups_migration_poll'] = 0;
$failures = array();
function mig_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok   ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures'][] = $label;
		echo 'FALLA (' . $label . '): ' . $e->getMessage() . "\n";
		if ( getenv( 'MIG_TRACE' ) ) echo $e->getTraceAsString() . "\n";
	}
}
$cmd = 'digitalisimo-backups';
mig_use( 'origen' );
Digitalisimo_Backups_Cli::register();
if ( ! isset( WP_CLI::$commands[ $cmd . ' migration' ] ) || ! method_exists( 'Digitalisimo_Backups_Cli', 'migrate' ) ) {
	echo "FALLA (k): WP-CLI no tiene `wp digitalisimo-backups migrate` ni `wp digitalisimo-backups migration`.\n";
	exit( 1 );
}
Digitalisimo_Backups_Cli::$sleep = function() {};
$secrets = array();

mig_case( 'k · WP-CLI: comandos, ayuda y la clave nunca en la línea de órdenes', function() use ( $cmd, &$secrets ) {
	$doc = (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'migrate' ) )->getDocComment();
	mig_assert( false !== strpos( $doc, '[--from-key-file=<path>]' ) && false !== strpos( $doc, '[--from-key-stdin]' ) && false !== strpos( $doc, '[--network]' ) && false === strpos( $doc, '[--from-key=' ), 'La ayuda de migrate no documenta la clave por archivo o STDIN.' );
	foreach ( array( 'status', 'create_key', 'keys', 'revoke', 'undo' ) as $method ) mig_assert( false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli_Migration', $method ) )->getDocComment(), '/**' ), 'Falta la ayuda de migration ' . $method );
	mig_assert( false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli_Migration', 'create_key' ) )->getDocComment(), '@subcommand create-key' ), 'create_key no se anuncia como create-key.' );
	// La ayuda de restore sigue siendo la suya.
	mig_assert( false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'restore' ) )->getDocComment(), '--confirm=<confirm>' ), 'restore perdió su ayuda.' );
	mig_use( 'destino' );
	foreach ( array( array( 'from-key' => 'DGMIG1.x' ), array( 'key' => 'DGMIG1.x' ) ) as $assoc ) {
		list( $code, $out ) = digi_wp( $cmd, 'migrate', array(), $assoc + array( 'yes' => true ) );
		mig_assert( 1 === $code && false !== strpos( end( $out ), '--from-key-file' ) && false === strpos( implode( "\n", $out ), 'DGMIG1.x' ), 'Una clave en la línea de órdenes no se rechazó: ' . implode( "\n", $out ) );
	}
	list( $code, $out ) = digi_wp( $cmd, 'migrate', array(), array( 'yes' => true ) );
	mig_assert( 1 === $code && false !== strpos( end( $out ), 'Falta la clave' ), 'migrate sin clave no explicó cómo pasarla.' );
	// create-key --key-file: archivo nuevo 0600, sin imprimir la clave; uno que ya existe se rechaza y revoca la clave.
	mig_use( 'origen' );
	$file = $GLOBALS['mig_root'] . '/clave.txt';
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'create_key', array(), array( 'key-file' => $file, 'hours' => 6 ) );
	$key = trim( (string) @file_get_contents( $file ) );
	$secrets[] = $key;
	mig_assert( 0 === $code && 0 === strpos( $key, 'DGMIG1.' ) && 0600 === ( fileperms( $file ) & 0777 ) && false === strpos( implode( "\n", $out ), $key ) && false !== strpos( end( $out ), 'Guardada en' ), 'create-key --key-file no dejó la clave sólo en el archivo: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'create_key', array(), array( 'key-file' => $file ) );
	mig_assert( 1 === $code && false !== strpos( end( $out ), 'archivo nuevo' ) && trim( (string) file_get_contents( $file ) ) === $key, 'create-key sobrescribió un archivo existente.' );
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'keys', array(), array( 'format' => 'json' ) );
	$rows = json_decode( substr( $out[0], strpos( $out[0], '[' ) ), true );
	mig_assert( 0 === $code && 2 === count( $rows ) && 1 === count( array_filter( $rows, function( $row ) { return 'revoked' === $row['state']; } ) ), 'migration keys no lista las claves (una revocada por el archivo existente): ' . implode( "\n", $out ) );
	// Sin --key-file la imprime una sola vez (STDOUT) y el aviso va aparte.
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'create_key' );
	mig_assert( 0 === $code && 1 === count( $out ) && 0 === strpos( $out[0], 'DGMIG1.' ), 'create-key no imprimió sólo la clave.' );
	$secrets[] = $out[0];
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'revoke', array( json_decode( Digitalisimo_Backups_Keys::b64url_decode( substr( $out[0], 7 ) ), true )['i'] ) );
	mig_assert( 0 === $code && false !== strpos( end( $out ), 'Revocada' ), 'migration revoke no revocó la clave: ' . implode( "\n", $out ) );
	$GLOBALS['mig_key_file'] = $file;
} );

mig_case( 'l · WP-CLI: migrate con confirmación, status y undo', function() use ( $cmd, &$secrets ) {
	mig_use( 'destino' );
	$file = $GLOBALS['mig_key_file'];
	list( $code, $out ) = digi_wp( $cmd, 'migrate', array(), array( 'from-key-file' => $file ) );
	mig_assert( 1 === $code && false !== strpos( implode( "\n", $out ), '[y/n] n' ) && ! Digitalisimo_Backups_Jobs::all(), 'migrate sin --yes no pidió confirmación o encoló algo.' );
	list( $code, $out ) = digi_wp( $cmd, 'migrate', array(), array( 'from-key-file' => $file, 'yes' => true ) );
	$text = implode( "\n", $out );
	// WP-CLI hace avanzar la cola sin límite de tiempo: la migración entera puede ir en un solo paso.
	mig_assert( 0 === $code && 0 === strpos( $out[0], 'Pendiente · Conexión con el origen' ) && 0 === strpos( end( $out ), 'Success: Migración terminada: este sitio es ahora una copia de origen.test' ), 'migrate no terminó la migración: ' . $text );
	foreach ( $secrets as $secret ) mig_assert( false === strpos( $text, $secret ), 'migrate imprimió la clave.' );
	mig_assert( '<a href="https://destino.test/">inicio</a>' === $GLOBALS['wpdb']->posts[7]['post_content'], 'El contenido migrado no tiene la dirección del destino.' );
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'status', array(), array( 'format' => 'json' ) );
	$status = json_decode( $out[0], true );
	mig_assert( 0 === $code && 'https://origen.test/' === $status['undo']['from'] && null === $status['active'], 'migration status no ofrece deshacer la migración: ' . $out[0] );
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'undo' );
	mig_assert( 1 === $code && null !== Digitalisimo_Backups_Migrate::undo_target(), 'undo sin --yes deshizo la migración.' );
	list( $code, $out ) = digi_wp( $cmd . ' migration', 'undo', array(), array( 'yes' => true ) );
	mig_assert( 0 === $code && false !== strpos( end( $out ), 'deshecha' ) && 'Destino' === get_option( 'blogname' ), 'undo no deshizo la migración: ' . implode( "\n", $out ) );
	mig_use( 'origen' );
} );

mig_case( 'm · pantalla: generar, mostrar una vez, revocar, laboratorio, migrar y deshacer', function() use ( &$secrets ) {
	$M = 'Digitalisimo_Backups_Migrate';
	mig_use( 'origen' );
	// Sin nonce o sin permiso, nada.
	try { mig_post( array( $M, 'create_request' ), array( 'hours' => 24 ) ); throw new RuntimeException( 'FALLA: se generó una clave sin nonce.' ); } catch ( Mig_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['bk_caps'] = array();
	try { mig_post( array( $M, 'create_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CREATE ) ); throw new RuntimeException( 'FALLA: se generó una clave sin permiso.' ); } catch ( Mig_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
	$keys_before = count( Digitalisimo_Backups_Migrate::keys() );
	list( $ok, $page ) = mig_post( array( $M, 'create_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CREATE, 'hours' => 1 ) );
	mig_assert( null === $ok && preg_match( '/>(DGMIG1\.[A-Za-z0-9_-]+)</', $page, $m ) && false !== strpos( $page, 'Se muestra una sola vez' ) && 1 === substr_count( $page, $m[1] ), 'La clave no se mostró una vez en su propia página.' );
	$key = $m[1];
	$secrets[] = $key;
	mig_assert( count( Digitalisimo_Backups_Migrate::keys() ) === $keys_before + 1, 'La pantalla no generó la clave.' );
	ob_start();
	Digitalisimo_Backups_Migrate::panel( false, mig_call( 'Digitalisimo_Backups', 'migration_status_data' ) );
	$html = ob_get_clean();
	mig_assert( false !== strpos( $html, 'Generar clave de migración' ) && false !== strpos( $html, 'Migrar desde otro sitio' ) && false !== strpos( $html, 'Registro de la migración' ) && false !== strpos( $html, 'Copia de pruebas (staging)' ), 'La pestaña «Migrar» no tiene sus tarjetas.' );
	foreach ( $secrets as $secret ) mig_assert( false === strpos( $html, $secret ), 'La pestaña repitió una clave.' );
	// Revocar y «Permitir HTTP» por admin-post.
	$id = json_decode( Digitalisimo_Backups_Keys::b64url_decode( substr( $key, 7 ) ), true )['i'];
	list( $ok, $message, $url ) = mig_post( array( $M, 'revoke_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_REVOKE, 'key' => $id ) );
	mig_assert( $ok && false !== strpos( $message, 'revocada' ) && false !== strpos( $url, 'tab=migrate' ) && 'revoked' === get_option( 'digitalisimo_backups_vault_migration' )['keys'][ $id ]['state'], 'No se revocó por la pantalla: ' . $message );
	list( $ok, $message ) = mig_post( array( $M, 'settings_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_SETTINGS, 'allow_http' => '1' ) );
	mig_assert( $ok && Digitalisimo_Backups_Migrate::allow_http() && false !== strpos( $message, 'laboratorio' ), '«Permitir HTTP» no se activó.' );
	mig_post( array( $M, 'settings_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_SETTINGS ) );
	mig_assert( ! Digitalisimo_Backups_Migrate::allow_http(), '«Permitir HTTP» no se desactivó.' );
	// Migrar desde la pantalla del destino: una clave mal formada no vuelve en la redirección; una buena encola.
	list( $ok, $page ) = mig_post( array( $M, 'create_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CREATE ) );
	preg_match( '/>(DGMIG1\.[A-Za-z0-9_-]+)</', $page, $m );
	$secrets[] = $m[1];
	mig_use( 'destino' );
	list( $ok, $message, $url ) = mig_post( array( $M, 'pull_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_PULL, 'migration_key' => 'DGMIG1.mal-formada-123' ) );
	mig_assert( ! $ok && false !== strpos( $message, 'no es válida' ) && false === strpos( $url, 'mal-formada' ), 'Una clave mal formada no se rechazó sin repetirla: ' . $url );
	list( $ok, $message, $url ) = mig_post( array( $M, 'pull_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_PULL, 'migration_key' => "\n  " . chunk_split( $m[1], 60, "\n" ) ) );
	mig_assert( $ok && false !== strpos( $message, 'Migración en cola desde origen.test' ) && false === strpos( $url, 'DGMIG1' ), 'La pantalla no encoló la migración (una clave pegada con saltos de línea sirve): ' . $message );
	$active = Digitalisimo_Backups_Jobs::active();
	mig_assert( 1 === count( $active ) && 'admin-post' === $active[0]['spec']['origin']['via'], 'La migración de la pantalla no está en la cola.' );
	ob_start();
	Digitalisimo_Backups_Migrate::panel( false, mig_call( 'Digitalisimo_Backups', 'migration_status_data' ) );
	$html = ob_get_clean();
	mig_assert( false !== strpos( $html, 'Migración en curso' ), 'La pestaña no muestra la migración en curso.' );
	$job = mig_drive( $active[0]['id'] );
	mig_assert( 'success' === $job['status'], 'La migración de la pantalla no terminó: ' . json_encode( $job['error'] ) );
	ob_start();
	Digitalisimo_Backups_Migrate::panel( false, mig_call( 'Digitalisimo_Backups', 'migration_status_data' ) );
	$html = ob_get_clean();
	mig_assert( false !== strpos( $html, 'Deshacer la migración' ) && false !== strpos( $html, 'https://origen.test/' ), 'La pestaña no ofrece deshacer la migración.' );
	foreach ( $secrets as $secret ) mig_assert( false === strpos( $html, $secret ), 'La pestaña del destino repitió una clave.' );
	list( $ok, $message ) = mig_post( array( $M, 'undo_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_UNDO ) );
	mig_assert( $ok && false !== strpos( $message, 'Migración deshecha' ) && 'Destino' === get_option( 'blogname' ), 'La pantalla no deshizo la migración: ' . $message );
	mig_use( 'origen' );
} );

mig_case( 'n · en una red, la pantalla de la red genera la clave (alcance de red) y REST lo exige', function() {
	$M = 'Digitalisimo_Backups_Migrate';
	$GLOBALS['mig']['sites']['origen']['multisite'] = true;
	mig_use( 'destino' );
	mig_use( 'origen' );
	try {
		list( $ok, $page ) = mig_post( array( $M, 'create_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CREATE, 'hours' => 1 ) );
		mig_assert( null === $ok && preg_match( '/>(DGMIG1\.[A-Za-z0-9_-]+)</', $page ), 'La pantalla de la red no generó la clave: ' . substr( (string) $page, 0, 200 ) );
		$reply = mig_rest( 'POST', 'digitalisimo-backups/v1/migration/keys', array() );
		mig_assert( 400 === $reply[0] && 'digitalisimo_backups_scope' === $reply[1]['code'], 'REST generó una clave de la red sin el alcance de red.' );
		$reply = mig_rest( 'POST', 'digitalisimo-backups/v1/migration/keys', array( 'network' => true ) );
		mig_assert( 201 === $reply[0], 'REST no generó la clave de la red con el alcance de red.' );
	} finally {
		$GLOBALS['mig']['sites']['origen']['multisite'] = false;
		mig_use( 'destino' );
		mig_use( 'origen' );
	}
} );

echo $failures ? 'Superficies de la migración con fallos: ' . implode( ', ', $failures ) . "\n" : "Superficies de la migración: todo correcto.\n";
mig_remove_tree( $GLOBALS['mig_root'] );
exit( $failures ? 1 : 0 );
