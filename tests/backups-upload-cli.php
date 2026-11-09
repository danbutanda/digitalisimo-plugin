<?php
/**
 * Revisión del lote corto · P1 y P3 en WP-CLI: `wp digitalisimo-backups upload list|finish|cancel`. Una subida cuya
 * comprobación se cortó tres veces en la web (el tiempo límite del servidor o del proxy) se termina con WP-CLI, que no
 * tiene ese límite ni el tope de intentos, y entra en el almacén como «sin registro»; las subidas de cualquier cuenta
 * se listan y se descartan.
 */
$root = sys_get_temp_dir() . '/digitalisimo-upload-cli-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );

$B = 'Digitalisimo_Backups';
$U = 'Digitalisimo_Backups_Uploads';
$cmd = 'digitalisimo-backups';
$store = $root . '/private';
try {
	Digitalisimo_Backups_Cli::register();
	update_option( $B::OPTION, array( 'retention' => '100' ) );
	$created = digi_backup();
	$bytes = file_get_contents( $store . '/' . $created['file'] );
	unlink( $store . '/' . $created['file'] );
	update_option( $B::HISTORY, array() );
	$session = $B::service_upload_start( array( 'name' => 'digitalisimo-desde-la-web.zip', 'size' => strlen( $bytes ) ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) $B::service_upload_chunk( $session['id'], $offset, array( 'data' => substr( $bytes, $offset, $session['chunk_size'] ) ) );
	// La comprobación se cortó tres veces en la web (de otra cuenta, la que la subió desde el navegador).
	$state = $U::read( $session['id'] );
	$state = array_merge( $state, array( 'phase' => 'validating', 'attempts' => $U::MAX_ATTEMPTS, 'user_id' => 99 ) );
	$U::write( $state );
	list( $code, $out ) = digi_wp( $cmd . ' upload', 'list', array(), array( 'format' => 'json' ) );
	$rows = json_decode( (string) end( $out ), true );
	digi_assert( 0 === $code && is_array( $rows ) && $session['id'] === $rows[0]['id'] && 'validating' === $rows[0]['phase'] && $U::MAX_ATTEMPTS === $rows[0]['attempts'] && false === strpos( json_encode( $rows ), $root ), 'wp … upload list no lista la subida: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd . ' upload', 'finish', array( $session['id'] ) );
	$out = implode( "\n", $out );
	digi_assert( 0 === $code && false !== strpos( $out, 'Success: Respaldo subido y comprobado: digitalisimo-desde-la-web.zip' ) && false !== strpos( $out, '--confirm="RESTAURAR SIN FIRMA"' ) && is_file( $store . '/digitalisimo-desde-la-web.zip' ), 'wp … upload finish no terminó la subida cortada: ' . $out );
	// Descartar una subida de otra cuenta.
	$other = $B::service_upload_start( array( 'name' => 'digitalisimo-abandonada.zip', 'size' => 100 ) );
	$state = $U::read( $other['id'] );
	$state['user_id'] = 99;
	$U::write( $state );
	list( $code, $out ) = digi_wp( $cmd . ' upload', 'cancel', array( $other['id'] ) );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), 'Success: Subida descartada' ) && ! is_dir( $store . '/incoming/' . $other['id'] ), 'wp … upload cancel no descartó la subida de otra cuenta.' );
	list( $code, $out ) = digi_wp( $cmd . ' upload', 'finish', array( 'no-es-un-id' ) );
	digi_assert( 1 === $code, 'wp … upload finish aceptó un identificador inválido.' );
	echo "DIGITALÍSIMO Backups: WP-CLI lista las subidas, termina sin límite de tiempo una cuya comprobación se cortó en la web y descarta las de cualquier cuenta.\n";
} finally {
	digi_rmtree( $root );
}
