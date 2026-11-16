<?php
/**
 * Revisión del lote corto · R3: un archivo subido (desde 2026-11-15, en la lista con su fila «Subido») o «sin registro»
 * (copiado al almacén por SSH) no ocupa un hueco de la retención global ni de una automatización, ni hace borrar
 * respaldos reales, y la retención nunca lo borra: el subido se borra a mano desde su fila y el suelto desde «Restaurar»
 * (capability, nonce, sólo archivos sin fila; un respaldo registrado nunca se borra por ahí).
 */
$root = sys_get_temp_dir() . '/digitalisimo-upload-retention-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function size_format( $bytes ) { return $bytes . ' B'; }
function wp_delete_file( $file ) { return @unlink( $file ); }

$B = 'Digitalisimo_Backups';
$store = $root . '/private';
$available = function( $file ) use ( $B ) { foreach ( $B::service_backups() as $row ) if ( $file === $row['file'] ) return $row['available']; return null; };
$age = function( $file, $seconds ) use ( $store ) { touch( $store . '/' . $file, time() - $seconds ); };

try {
	update_option( $B::OPTION, array( 'retention' => '2' ) );
	// El respaldo «de otro servidor» que se subirá: hecho antes que todos y retirado de este sitio (archivo y fila).
	$x = digi_backup();
	$bytes = file_get_contents( $store . '/' . $x['file'] );
	unlink( $store . '/' . $x['file'] );
	update_option( $B::HISTORY, array() );
	$a = digi_backup();
	$age( $a['file'], 300 );
	$b = digi_backup();
	$age( $b['file'], 200 );
	// Una subida (de «otro servidor») y un archivo copiado por SSH, los dos más nuevos que A y B.
	$session = $B::service_upload_start( array( 'name' => 'mi-respaldo-de-otro-servidor.zip', 'size' => strlen( $bytes ) ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) $B::service_upload_chunk( $session['id'], $offset, array( 'data' => substr( $bytes, $offset, $session['chunk_size'] ) ) );
	$uploaded = $B::service_upload_finish( $session['id'] );
	digi_assert( ! is_wp_error( $uploaded ) && 0 === strpos( $uploaded['file'], 'digitalisimo-upload-' ), 'No se subió el respaldo.' );
	copy( $store . '/' . $a['file'], $store . '/digitalisimo-backup-copiado-por-ssh.zip' );
	$age( $uploaded['file'], 100 );
	$age( 'digitalisimo-backup-copiado-por-ssh.zip', 100 );

	// C: la retención (2) conserva C y B, borra A y no cuenta ni toca los dos archivos sin registro.
	$c = digi_backup();
	digi_assert( true === $available( $c['file'] ) && true === $available( $b['file'] ), 'Un archivo sin registro hizo borrar un respaldo real (B o C).' );
	// Desde 1.0.75 la fila de A, sin ninguna copia tras borrar su ZIP, sale del historial (antes quedaba «Sin copias»).
	digi_assert( ! is_file( $store . '/' . $a['file'] ) && null === $available( $a['file'] ), 'La retención global no borró el respaldo más antiguo (A) o dejó su fila sin copias: ' . json_encode( $available( $a['file'] ) ) );
	digi_assert( is_file( $store . '/' . $uploaded['file'] ) && true === $available( $uploaded['file'] ) && is_file( $store . '/digitalisimo-backup-copiado-por-ssh.zip' ), 'La retención borró el subido o un archivo sin registro.' );
	// Muchos archivos sin registro tampoco desplazan nada.
	for ( $i = 0; $i < 4; $i++ ) copy( $store . '/' . $c['file'], $store . '/digitalisimo-backup-suelto-' . $i . '.zip' );
	$d = digi_backup();
	digi_assert( true === $available( $d['file'] ) && true === $available( $c['file'] ) && ! is_file( $store . '/' . $b['file'] ) && null === $available( $b['file'] ) && 4 === count( glob( $store . '/digitalisimo-backup-suelto-*.zip' ) ) && true === $available( $uploaded['file'] ), 'Los archivos sin registro o el subido cambiaron lo que conserva la retención.' );
	// La retención de una automatización tampoco lo toca, aunque su fila dijera su misma política.
	$prune = new ReflectionMethod( $B, 'prune_policy' );
	if ( PHP_VERSION_ID < 80100 ) $prune->setAccessible( true );
	$prune->invoke( null, array( 'id' => 'uploaded', 'retain' => 1 ) );
	$prune->invoke( null, array( 'id' => 'manual', 'retain' => 1 ) );
	digi_assert( true === $available( $uploaded['file'] ) && is_file( $store . '/' . $uploaded['file'] ), 'La retención de una automatización borró un respaldo subido.' );

	// «Restaurar» los lista con «Eliminar».
	$panel = new ReflectionMethod( $B, 'restore_panel' );
	if ( PHP_VERSION_ID < 80100 ) $panel->setAccessible( true );
	ob_start();
	$panel->invoke( null, (array) get_option( $B::HISTORY, array() ) );
	$html = (string) ob_get_clean();
	digi_assert( false !== strpos( $html, 'Archivos del almacén sin registro</h3>' ) && false !== strpos( $html, 'name="action" value="' . $B::ACTION_DELETE_LOOSE . '"><input type="hidden" name="file" value="digitalisimo-backup-copiado-por-ssh.zip"' ) && false === strpos( $html, 'name="file" value="' . $uploaded['file'] . '"' ), 'La pantalla no ofrece eliminar los archivos sin registro, o lista el subido como suelto.' );
	// Eliminar a mano: un archivo sin registro sí; un respaldo registrado (también el subido), otro nombre o una ruta, nunca.
	$query = digi_admin_post( 'delete_loose', $B::ACTION_DELETE_LOOSE, array( 'file' => 'digitalisimo-backup-copiado-por-ssh.zip' ) );
	digi_assert( 'ok' === $query['backup'] && ! is_file( $store . '/digitalisimo-backup-copiado-por-ssh.zip' ), 'No se eliminó el archivo sin registro a mano: ' . json_encode( $query ) );
	$query = digi_admin_post( 'delete_loose', $B::ACTION_DELETE_LOOSE, array( 'file' => $uploaded['file'] ) );
	digi_assert( 'error' === $query['backup'] && is_file( $store . '/' . $uploaded['file'] ), 'El subido (con su fila) se borró por la vía de los sueltos.' );
	// El subido se borra desde su fila, como cualquier otro: archivo y fila.
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => $uploaded['file'] ) );
	digi_assert( 'ok' === $query['backup'] && ! is_file( $store . '/' . $uploaded['file'] ) && null === $available( $uploaded['file'] ), '«Borrar» de su fila no borró el respaldo subido: ' . json_encode( $query ) );
	$query = digi_admin_post( 'delete_loose', $B::ACTION_DELETE_LOOSE, array( 'file' => $d['file'] ) );
	digi_assert( 'error' === $query['backup'] && false !== strpos( rawurldecode( (string) $query['message'] ), 'está en el historial' ), 'Un respaldo registrado no se rechazó con su motivo: ' . json_encode( $query ) );
	// Nada que no sea un respaldo del almacén: la protección del almacén (index.php), una ruta, un cerrojo, una carpeta.
	digi_assert( is_file( $store . '/index.php' ), 'El almacén no tiene su index.php de protección.' );
	foreach ( array( 'index.php', '../public/wp-load.php', '.operation.lock', 'jobs' ) as $bad ) {
		$query = digi_admin_post( 'delete_loose', $B::ACTION_DELETE_LOOSE, array( 'file' => $bad ) );
		digi_assert( 'error' === $query['backup'], 'Se eliminó por la vía de los archivos sin registro: ' . $bad );
	}
	digi_assert( is_file( $store . '/' . $d['file'] ) && is_file( ABSPATH . 'wp-load.php' ) && is_file( $store . '/index.php' ), 'Se borró un respaldo registrado, un archivo del sitio o la protección del almacén.' );
	try { digi_admin_post( 'delete_loose', 'otra-accion', array( 'file' => 'digitalisimo-backup-suelto-0.zip' ) ); throw new RuntimeException( 'FALLA: se eliminó con un nonce ajeno.' ); } catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true );
	try { digi_admin_post( 'delete_loose', $B::ACTION_DELETE_LOOSE, array( 'file' => 'digitalisimo-backup-suelto-0.zip' ) ); throw new RuntimeException( 'FALLA: se eliminó sin capability.' ); } catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	digi_assert( is_file( $store . '/digitalisimo-backup-suelto-0.zip' ), 'Un archivo sin registro se borró sin nonce o sin capability.' );

	echo "DIGITALÍSIMO Backups: la retención (global o de una automatización) sólo cuenta y borra respaldos hechos aquí; los subidos y los archivos sin registro se eliminan a mano.\n";
} finally {
	digi_rmtree( $root );
}
