<?php
/**
 * Restauración interrumpida: si el proceso muere mientras corre el SQL (exit(), que no ejecuta ningún finally), la
 * siguiente operación aplica el diario del almacén: historial con la copia previa, seguridad nunca más débil y llavero
 * con la misma huella, que pide la frase. Mientras tanto el cifrado falla cerrado (nada sale en claro) y escribir la
 * frase devuelve la misma clave. El diario no guarda la frase, la clave ni su envoltura; sin diario no se restaura.
 */
require __DIR__ . '/lib/backups-env.php';
$K = 'Digitalisimo_Backups_Keys';
$S = 'Digitalisimo_Backups_Security';
$B = 'Digitalisimo_Backups';
$passphrase = 'frase de la restauración interrumpida';
$key_id = $K::set_passphrase( $passphrase )['key_id'];
$box = $GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_id ]['wrap']['box'];
$root = $K::resolve_by_id( $key_id );
$storage = bk_storage();
$journal = $storage . '/' . $B::RESTORE_JOURNAL;

// Respaldo firmado sin cifrar; después se activan el cifrado y «sólo firmados», que su SQL no trae.
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
$backup = bk_backup();
bk_call( $B, 'remember', $backup, 'local', 'installation', array( 'id' => 'manual', 'type' => 'full' ) );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => true );

// Sin diario no se restaura: nada del sitio cambia.
mkdir( $journal . '.tmp' );
$GLOBALS['wpdb']->queries = array();
$report = $S::open( $backup, bk_call( $B, 'history_item', basename( $backup ) ), '' );
try { bk_expect_failure( 'sin diario', function() use ( $B, $report ) { bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report ); }, 'diario de la restauración' ); }
finally { $S::close( $report ); rmdir( $journal . '.tmp' ); }
if ( preg_grep( '/^(INSERT|DROP|CREATE)/', $GLOBALS['wpdb']->queries ) || file_exists( $journal ) ) throw new RuntimeException( 'Sin diario se cambió el sitio o quedó un diario.' );
// Una restauración completa no deja diario.
$report = $S::open( $backup, bk_call( $B, 'history_item', basename( $backup ) ), '' );
try { bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report ); } finally { $S::close( $report ); }
if ( file_exists( $journal ) || ( $GLOBALS['bk_options'][ $K::KEYRING ]['active'] ?? '' ) !== $key_id ) throw new RuntimeException( 'Una restauración terminada dejó su diario o perdió el llavero.' );
$history = array_column( $GLOBALS['bk_options'][ $B::HISTORY ], 'file' );

register_shutdown_function( function() use ( $K, $S, $B, $passphrase, $key_id, $box, $root, $storage, $journal, $backup, $history ) {
	bk_close_dead_locks();
	$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
	// Lo que dejó el proceso muerto: el SQL reemplazó wp_options, sin llavero y con la seguridad del respaldo.
	$check( ! isset( $GLOBALS['bk_options'][ $K::KEYRING ] ) && empty( $GLOBALS['bk_options'][ $S::OPTION ]['encrypt'] ), 'La simulación no dejó el sitio como lo deja el SQL recién cambiado.' );
	$check( is_file( $journal ), 'La restauración interrumpida no dejó su diario.' );
	$text = file_get_contents( $journal );
	foreach ( array( $passphrase, $box, bin2hex( $root ), base64_encode( $root ), $K::b64url_encode( $root ), AUTH_KEY ) as $secret ) $check( false === strpos( $text, $secret ), 'El diario guarda un secreto.' );
	$check( 0600 === ( fileperms( $journal ) & 0777 ), 'El diario no es privado.' );
	// La siguiente operación toma el bloqueo y aplica el diario.
	$lock = bk_call( $B, 'acquire_operation_lock' );
	bk_call( $B, 'release_operation_lock', $lock );
	$files = array_column( (array) ( $GLOBALS['bk_options'][ $B::HISTORY ] ?? array() ), 'file' );
	$check( ! file_exists( $journal ), 'El diario no se aplicó.' );
	$check( in_array( basename( $backup ), $files, true ) && preg_grep( '/^safety-before-restore-/', $files ) && count( $files ) === count( $history ) + 1, 'El historial no volvió con la copia previa.' );
	$security = $GLOBALS['bk_options'][ $S::OPTION ] ?? array();
	$check( ! empty( $security['encrypt'] ) && ! empty( $security['require_signed'] ), 'Tras la interrupción la seguridad quedó más débil.' );
	$ring = $K::keyring();
	$check( $key_id === $ring['active'] && 'needs_passphrase' === $K::entry_state( $key_id, $ring['keys'][ $key_id ], $ring ), 'El llavero no volvió con la misma huella pidiendo la frase.' );
	// Mientras falta la frase, el cifrado falla cerrado y no deja nada en el almacén.
	$before = glob( $storage . '/*' );
	bk_expect_failure( 'sin frase tras la interrupción', function() use ( $B ) { bk_backup(); }, 'No se creó un respaldo sin cifrar' );
	$check( glob( $storage . '/*' ) === $before, 'El fallo cerrado dejó archivos.' );
	// Escribir la frase devuelve la misma clave y lo nuevo se cifra con ella.
	$check( 'rewrapped' === $K::set_passphrase( $passphrase )['result'] && $K::resolve_by_id( $key_id ) === $root, 'Volver a escribir la frase no devolvió la misma clave.' );
	$sealed = bk_backup();
	$check( $key_id === Digitalisimo_Backups_Encrypted_File::read_header( $sealed )['header']['key']['id'], 'Lo nuevo no se cifró con la misma clave.' );
	// El diario de otra instalación que comparte el almacén no se aplica ni se borra.
	file_put_contents( $journal, json_encode( array( 'version' => 1, 'wordpress_root' => '/otra/instalacion', 'history' => array(), 'keys' => array(), 'active' => '', 'security' => array( 'encrypt' => false, 'require_signed' => false ) ) ) );
	$history_now = $GLOBALS['bk_options'][ $B::HISTORY ];
	$lock = bk_call( $B, 'acquire_operation_lock' );
	bk_call( $B, 'release_operation_lock', $lock );
	$check( file_exists( $journal ) && $GLOBALS['bk_options'][ $B::HISTORY ] === $history_now, 'Se aplicó o se borró el diario de otra instalación.' );
	// Un diario dañado se descarta sin tocar nada.
	file_put_contents( $journal, '{no es json' );
	$lock = bk_call( $B, 'acquire_operation_lock' );
	bk_call( $B, 'release_operation_lock', $lock );
	$check( ! file_exists( $journal ) && $K::keyring()['active'] === $key_id, 'Un diario dañado cambió algo o no se descartó.' );
	bk_cleanup();
	echo "DIGITALÍSIMO Backups: restauración interrumpida en el SQL: historial, seguridad y llavero recuperados del diario, cifrado cerrado hasta la frase y misma clave.\n";
} );

// El proceso muere con el SQL ya en las tablas del sitio: desde la restauración por pasos, el SQL va a tablas temporales
// y el momento crítico es justo después del RENAME TABLE que las pone en su lugar, antes de reponer historial y llavero.
$GLOBALS['wpdb']->exit_after = 'RENAME TABLE ';
$report = $S::open( $backup, bk_call( $B, 'history_item', basename( $backup ) ), '' );
bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report );
throw new RuntimeException( 'La restauración no se interrumpió.' );
