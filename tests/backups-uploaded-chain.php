<?php
/**
 * Un punto incremental subido queda en la lista con su cadena (pedido del propietario del 2026-11-15), con el motor de
 * trabajos, la reconstrucción de la cadena y la restauración por pasos reales (tests/lib/backups-env.php):
 *
 *   C1  subido sin su cadena: queda en la lista como incremental, dice qué archivos de su cadena faltan y no se
 *       restaura (ni con RESTAURAR SIN FIRMA) hasta tenerlos; el sitio no cambia;
 *   C2  al subir la base y el incremental intermedio (con su nombre), el punto se restaura escribiendo RESTAURAR y deja
 *       el sitio exactamente como en ese punto; la verificación reconstruye la cadena y pide RESTAURAR;
 *   C3  borrar la base de la que dependen los puntos subidos se niega;
 *   C4  un eslabón que cambia en el almacén después de subirse rompe la cadena (su SHA-256 no es el de su fila);
 *   C5  si un eslabón no se subió (está suelto, sin su fila «Subido»), la cadena no hereda la confianza: pide RESTAURAR
 *       SIN FIRMA, como antes.
 *
 * Con el código anterior (1.0.77) el punto subido entraba «sin registro»: no estaba en la lista y pedía RESTAURAR SIN FIRMA.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/backups-incremental/helpers.php';
$root = rtrim( ABSPATH, '/' );
$B = 'Digitalisimo_Backups';
$failures = 0;

foreach ( array(
	'index.php' => "<?php // portada\n",
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 30000 ),
) as $name => $content ) inc_put( $name, $content );
$GLOBALS['bk_options']['blogname'] = 'Punto 0';

/** Sube unos bytes por los casos de uso de la subida (los de la pantalla y REST), por fragmentos. */
function upc_upload( $name, $bytes ) {
	$session = Digitalisimo_Backups::service_upload_start( array( 'name' => $name, 'size' => strlen( $bytes ) ) );
	inc_check( ! is_wp_error( $session ), 'No empezó la subida: ' . ( is_wp_error( $session ) ? $session->get_error_message() : '' ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) {
		$chunk = Digitalisimo_Backups::service_upload_chunk( $session['id'], $offset, array( 'data' => substr( $bytes, $offset, $session['chunk_size'] ) ) );
		inc_check( ! is_wp_error( $chunk ), 'Un fragmento falló: ' . ( is_wp_error( $chunk ) ? $chunk->get_error_message() : '' ) );
	}
	return Digitalisimo_Backups::service_upload_finish( $session['id'], array( 'via' => 'rest' ) );
}
function upc_summary( $file ) { foreach ( Digitalisimo_Backups::service_backups() as $row ) if ( $file === $row['file'] ) return $row; return null; }

// La cadena de «otro sitio»: base, incremental 1 e incremental 2.
inc_put( 'wp-content/uploads/2026/11/a.txt', 'a 0' );
$base = inc_path( inc_run( 'cadena-de-otro-sitio' ) );
inc_put( 'wp-content/uploads/2026/11/a.txt', 'a 1' );
inc_put( 'wp-content/plugins/demo/nuevo.php', "<?php // punto 1\n" );
$GLOBALS['bk_options']['blogname'] = 'Punto 1';
$i1 = inc_path( inc_run( 'cadena-de-otro-sitio' ) );
unlink( $root . '/wp-content/plugins/demo/nuevo.php' );
inc_put( 'wp-content/uploads/2026/11/a.txt', 'a 2' );
$GLOBALS['bk_options']['blogname'] = 'Punto 2';
$i2 = inc_path( inc_run( 'cadena-de-otro-sitio' ) );
$tree = inc_files();
$db = inc_db();
$bytes = array( 'base' => file_get_contents( $base ), 'i1' => file_get_contents( $i1 ), 'i2' => file_get_contents( $i2 ) );
$names = array( 'base' => basename( $base ), 'i1' => basename( $i1 ), 'i2' => basename( $i2 ) );
// Este sitio no los tiene: ni sus archivos, ni sus filas, ni sus índices.
foreach ( array( $base, $i1, $i2 ) as $path ) unlink( $path );
$GLOBALS['bk_options'][ $B::HISTORY ] = array();
bk_remove_tree( rtrim( bk_storage(), '/' ) . '/chains' );
inc_put( 'wp-content/plugins/demo/de-este-sitio.php', "<?php // este sitio\n" );
$GLOBALS['bk_options']['blogname'] = 'Este sitio';

inc_case( 'C1 subido sin su cadena: en la lista como incremental, dice qué falta y no se restaura hasta tenerla', function() use ( $bytes, $names ) {
	$up = upc_upload( $names['i2'], $bytes['i2'] );
	inc_check( ! is_wp_error( $up ) && $names['i2'] === $up['file'] && true === $up['listed'] && false === $up['restorable'], 'El punto subido no quedó en la lista: ' . ( is_wp_error( $up ) ? $up->get_error_message() : json_encode( $up ) ) );
	$row = inc_row( $names['i2'] );
	inc_check( 'uploaded' === $row['origin'] && 'incremental' === $row['backup_type'] && 2 === (int) $row['chain']['sequence'] && array( $names['base'], $names['i1'] ) === array_column( $row['chain']['links'], 'file' ), 'La fila del punto subido no lleva su cadena: ' . json_encode( $row ) );
	$summary = upc_summary( $names['i2'] );
	inc_check( false === $summary['restorable'] && 2 === $summary['chain']['sequence'] && array( $names['base'], $names['i1'] ) === $summary['chain']['missing'], 'La lista no dice qué falta de la cadena del punto subido: ' . json_encode( $summary ) );
	inc_check( false !== strpos( (string) bk_call( 'Digitalisimo_Backups', 'uploaded_blocked_text', $row ), 'súbelos con ese mismo nombre' ), 'La pantalla no dice qué hacer con la cadena que falta.' );
	$before = bk_tree();
	foreach ( array( 'RESTAURAR', 'RESTAURAR SIN FIRMA' ) as $confirm ) {
		$result = inc_restore( $names['i2'], $confirm );
		inc_check( is_wp_error( $result ) && 'digitalisimo_backups_chain_incomplete' === $result->get_error_code() && false !== strpos( $result->get_error_message(), 'Súbelos' ) && bk_tree() === $before, 'Sin su cadena, el punto subido no se negó con qué subir (' . $confirm . '): ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'aceptado' ) );
	}
} );

inc_case( 'C2 con su cadena subida, el punto se verifica y se restaura con RESTAURAR, idéntico a su momento', function() use ( $bytes, $names, $tree, $db ) {
	foreach ( array( 'base', 'i1' ) as $link ) {
		$up = upc_upload( $names[ $link ], $bytes[ $link ] );
		inc_check( ! is_wp_error( $up ) && $names[ $link ] === $up['file'] && 'uploaded' === inc_row( $names[ $link ] )['origin'], 'El eslabón ' . $link . ' no se subió con su nombre: ' . ( is_wp_error( $up ) ? $up->get_error_message() : json_encode( $up ) ) );
	}
	inc_check( 'full' === inc_row( $names['base'] )['backup_type'] && true === upc_summary( $names['base'] )['restorable'], 'La base subida no quedó como respaldo completo restaurable.' );
	$summary = upc_summary( $names['i2'] );
	inc_check( true === $summary['restorable'] && array() === $summary['chain']['missing'], 'Con su cadena en el almacén, el punto subido sigue sin restaurarse: ' . json_encode( $summary['chain'] ) );
	$verify = Digitalisimo_Backups::service_verify( $names['i2'] );
	inc_check( ! is_wp_error( $verify ) && 3 === $verify['chain']['links'] && 'untrusted' === $verify['trust'] && 'uploaded' === $verify['vouched'] && 'RESTAURAR' === $verify['confirmation'], 'La verificación del punto subido no reconstruyó su cadena o pide otra frase: ' . ( is_wp_error( $verify ) ? $verify->get_error_message() : json_encode( array( $verify['chain'], $verify['confirmation'] ) ) ) );
	$result = inc_restore( $names['i2'], 'RESTAURAR' );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok(), 'El punto subido no se restauró con RESTAURAR: ' . ( is_wp_error( $result ) ? $result->get_error_code() . ' ' . $result->get_error_message() : json_encode( $result ) ) );
	inc_check( $tree === inc_files() && $db === inc_db(), 'El punto subido restaurado no es el sitio de su momento.' );
	inc_check( 'uploaded' === ( inc_last_restore_ok()['spec']['source']['vouched'] ?? '' ) && 'untrusted' === ( inc_last_restore_ok()['spec']['source']['trust'] ?? '' ), 'La restauración del punto reconstruido no se validó como no confiable (subido).' );
} );

inc_case( 'C3 borrar la base de la que dependen los puntos subidos se niega', function() use ( $names, $B ) {
	list( $ok, $message ) = bk_post( array( $B, 'delete_backup' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE, 'file' => $names['base'] ) );
	inc_check( ! $ok && false !== strpos( $message, 'dependen' ) && is_file( rtrim( bk_storage(), '/' ) . '/' . $names['base'] ), 'Se borró la base de la que dependen los puntos subidos: ' . $message );
} );

inc_case( 'C4 un eslabón cambiado en el almacén después de subirse rompe la cadena', function() use ( $bytes, $names ) {
	$path = rtrim( bk_storage(), '/' ) . '/' . $names['i1'];
	file_put_contents( $path, $bytes['i1'] . 'x' );
	$before = bk_tree();
	$result = inc_restore( $names['i2'], 'RESTAURAR' );
	inc_check( is_wp_error( $result ) && false !== strpos( $result->get_error_message(), 'SHA-256' ) && bk_tree() === $before, 'Un eslabón cambiado después de subirse no rompió la cadena: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'aceptado' ) );
	file_put_contents( $path, $bytes['i1'] );
} );

inc_case( 'C5 un eslabón suelto (no subido) no hereda la confianza: la cadena pide RESTAURAR SIN FIRMA', function() use ( $names, $B ) {
	$GLOBALS['bk_options'][ $B::HISTORY ] = array_values( array_filter( (array) $GLOBALS['bk_options'][ $B::HISTORY ], function( $row ) use ( $names ) { return $names['i1'] !== ( $row['file'] ?? '' ); } ) );
	$result = inc_restore( $names['i2'], 'RESTAURAR' );
	inc_check( is_wp_error( $result ) && 'digitalisimo_backups_confirm_unsigned' === $result->get_error_code(), 'Con un eslabón suelto, el punto subido no pidió RESTAURAR SIN FIRMA: ' . ( is_wp_error( $result ) ? $result->get_error_code() : 'aceptado' ) );
	$result = inc_restore( $names['i2'], 'RESTAURAR SIN FIRMA' );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok(), 'Con RESTAURAR SIN FIRMA, el punto con un eslabón suelto no se restauró: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
} );

bk_cleanup();
if ( $failures ) { echo "FALLA: {$failures} caso(s) de la cadena subida.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: un punto incremental subido queda en la lista con su cadena, dice qué le falta, se restaura con RESTAURAR cuando la cadena subida está completa y una cadena cambiada o con un eslabón suelto no hereda la confianza.\n";
