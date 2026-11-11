<?php
/**
 * Lote corto · B1: un respaldo copiado a un destino remoto sigue restaurándose desde su ZIP local verificado.
 *
 * Antes, la fase «transfer» cambiaba el `destination` de la fila del historial al proveedor remoto: el respaldo
 * desaparecía de «Restaurar» (y no salía como «sin registro», porque tiene fila), REST y WP-CLI lo daban por no
 * restaurable aunque su ZIP siguiera en el almacén, la descarga iba al remoto y «Eliminar» borraba sólo la copia remota
 * y dejaba el ZIP local huérfano. Ahora la fila distingue la copia local (el ZIP del almacén) de la remota
 * (`transfer_destination`, `transfer`, `remote`), y las filas antiguas, con el proveedor en `destination`, se leen
 * igual: mientras el ZIP local exista, se restaura desde él, en la pantalla, en REST y en WP-CLI.
 */
$root = sys_get_temp_dir() . '/digitalisimo-remote-copy-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
// Lo que la pantalla necesita y el entorno de cobertura no trae.
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function wp_delete_file( $file ) { return @unlink( $file ); }
function size_format( $bytes ) { return $bytes . ' B'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function disabled( $a, $b = true, $echo = true ) { return $a === $b ? ' disabled="disabled"' : ''; }
// Google Drive simulado: acepta la subida, devuelve la copia íntegra al verificar y anota cada petición.
$GLOBALS['http'] = array();
$GLOBALS['uploaded'] = '';
function digi_google( $method, $url, $args ) {
	$GLOBALS['http'][] = $method . ' ' . $url;
	if ( 'POST' === $method && false !== strpos( $url, 'oauth2' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'access_token' => 'token-de-prueba' ) ) );
	if ( 'POST' === $method ) { $GLOBALS['uploaded'] = ''; return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'location' => 'https://upload.test/session/1' ), 'body' => '' ); }
	if ( 'PUT' === $method ) { $GLOBALS['uploaded'] .= $args['body']; return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'id' => 'id-remoto-de-google' ) ) ); }
	if ( 'GET' === $method ) { file_put_contents( $args['filename'], $GLOBALS['uploaded'] ); return array( 'response' => array( 'code' => 200 ) ); }
	return array( 'response' => array( 'code' => empty( $GLOBALS['delete_fails'] ) ? 204 : 500 ), 'body' => '' );
}
function wp_remote_post( $url, $args = array() ) { return digi_google( 'POST', $url, $args ); }
function wp_remote_get( $url, $args = array() ) { return digi_google( 'GET', $url, $args ); }
function wp_remote_request( $url, $args = array() ) { return digi_google( $args['method'] ?? 'GET', $url, $args ); }
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $response, $header ) { return is_array( $response ) ? (string) ( $response['headers'][ $header ] ?? '' ) : ''; }

$B = 'Digitalisimo_Backups';
$ns = 'digitalisimo-backups/v1';
$store = $root . '/private';
/** Lista de «Restaurar» de la pantalla: los archivos del grupo «Historial» y los del grupo «sin registro». */
$restore_list = function() use ( $B ) {
	$panel = new ReflectionMethod( $B, 'restore_panel' );
	if ( PHP_VERSION_ID < 80100 ) $panel->setAccessible( true );
	ob_start();
	$panel->invoke( null, array_slice( (array) get_option( $B::HISTORY, array() ), 0, 10 ) );
	$html = (string) ob_get_clean();
	$groups = array( 'history' => array(), 'loose' => array() );
	if ( preg_match( '#<optgroup label="Historial">(.*?)</optgroup>#s', $html, $m ) ) { preg_match_all( '#<option value="([^"]+)"#', $m[1], $o ); $groups['history'] = $o[1]; }
	if ( preg_match( '#<optgroup label="Archivos del almacén sin registro">(.*?)</optgroup>#s', $html, $m ) ) { preg_match_all( '#<option value="([^"]+)"#', $m[1], $o ); $groups['loose'] = $o[1]; }
	return $groups;
};
$row_of = function( $file ) use ( $B ) { foreach ( (array) get_option( $B::HISTORY, array() ) as $row ) if ( $file === ( $row['file'] ?? '' ) ) return $row; return null; };
$summary_of = function( $file ) use ( $B ) { foreach ( $B::service_backups() as $row ) if ( $file === $row['file'] ) return $row; return null; };
/** El mismo respaldo desde las tres superficies: resumen del caso de uso, `GET /backups` y `wp … list --format=json`. */
$surfaces = function( $file ) use ( $summary_of, $ns ) {
	list( $code, $rest ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( 200 === $code, 'GET /backups no respondió.' );
	$from_rest = null;
	foreach ( $rest as $row ) if ( $file === $row['file'] ) $from_rest = $row;
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'list', array(), array( 'format' => 'json', 'fields' => 'file,restorable,destination' ) );
	$from_cli = null;
	foreach ( (array) json_decode( (string) end( $out ), true ) as $row ) if ( $file === $row['file'] ) $from_cli = $row;
	return array( $summary_of( $file ), $from_rest, $from_cli );
};

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	// Google Drive conectado por el formulario de la pantalla; la retención no interviene en esta prueba.
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => array( 'destination' => 'local', 'retention' => '100', 'google_client_id' => 'cliente-google', 'google_client_secret' => 'secreto-google', 'google_refresh_token' => 'renovar-google' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardó la conexión de Google Drive: ' . json_encode( $query ) );

	// 1. Respaldo con copia remota verificada: la fila guarda la copia local y la remota por separado.
	$created = digi_backup( array( 'destination' => 'google' ) );
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo hacia Google Drive: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	$file = $created['file'];
	digi_assert( is_file( $store . '/' . $file ) && $GLOBALS['uploaded'] === file_get_contents( $store . '/' . $file ), 'La copia remota no es el ZIP local o el ZIP local no se conservó.' );
	list( $summary, $rest, $cli ) = $surfaces( $file );
	digi_assert( true === $summary['available'] && true === $summary['restorable'] && 'google' === $summary['destination'] && 'google' === $summary['remote'] && 'ok' === $summary['transfer'], 'El caso de uso no da por restaurable un respaldo copiado a Google Drive con su ZIP local: ' . json_encode( $summary ) );
	digi_assert( array( array( 'destination' => 'local', 'state' => 'ok' ), array( 'destination' => 'google', 'state' => 'ok' ) ) === $summary['copies'], 'El resumen no enumera la copia local y la remota: ' . json_encode( $summary ) );
	digi_assert( $rest && true === $rest['restorable'] && $cli && true === $cli['restorable'], 'REST o WP-CLI no dan por restaurable el respaldo copiado: ' . json_encode( array( $rest, $cli ) ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'list' );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), '"copies":"local, google"' ), 'wp … list no muestra las copias como texto: ' . implode( "\n", $out ) );
	$list = $restore_list();
	digi_assert( in_array( $file, $list['history'], true ) && ! in_array( $file, $list['loose'], true ), 'El respaldo copiado no aparece en «Restaurar» con su fila: ' . json_encode( $list ) );
	$row = $row_of( $file );
	digi_assert( 'local' === $row['destination'] && 'google' === $row['transfer_destination'] && 'ok' === $row['transfer'] && 'google' === $row['remote']['provider'], 'La fila no distingue la copia local de la remota: ' . json_encode( $row ) );

	// 2. Restaurar desde el ZIP local: es un respaldo registrado aquí (RESTAURAR), sin descargar nada del remoto.
	$GLOBALS['http'] = array();
	$GLOBALS['wpdb']->set( 'blogname', 'Cambiado después del respaldo' );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $file . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 200 === $code && true === $restored['restored'] && 'provenance' === $restored['trust'], 'POST …/restore no restauró el respaldo copiado: ' . json_encode( $restored ) );
	digi_assert( 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ), 'La restauración no devolvió la base de datos del respaldo.' );
	digi_assert( array() === $GLOBALS['http'], 'Restaurar con el ZIP local presente pidió algo al remoto: ' . json_encode( $GLOBALS['http'] ) );
	$row = $row_of( $file );
	digi_assert( $row && 'ok' === $row['transfer'] && 'google' === $row['remote']['provider'], 'Tras restaurar, la fila perdió su copia remota.' );

	// 3. Filas antiguas: el proveedor en `destination` (1.0.59 a 1.0.61, y antes sin `transfer`). Con el ZIP local, restaurables.
	$history = (array) get_option( $B::HISTORY, array() );
	$legacy = array();
	foreach ( array( 'digitalisimo-backup-s4.zip' => array( 'destination' => 'google', 'transfer' => 'ok', 'transfer_destination' => 'google' ), 'digitalisimo-backup-anterior.zip' => array( 'destination' => 'sftp', 'remote' => array( 'provider' => 'sftp', 'path' => '/srv/respaldos/x.zip' ) ) ) as $name => $changes ) {
		copy( $store . '/' . $file, $store . '/' . $name );
		$old = array_merge( $row, array( 'file' => $name ), $changes );
		if ( ! isset( $changes['transfer'] ) ) unset( $old['transfer'], $old['transfer_destination'], $old['transfer_error'] );
		$legacy[ $name ] = $old;
	}
	update_option( $B::HISTORY, array_merge( array_values( $legacy ), $history ) );
	$list = $restore_list();
	foreach ( $legacy as $name => $old ) {
		list( $summary, $rest, $cli ) = $surfaces( $name );
		digi_assert( true === $summary['restorable'] && $old['destination'] === $summary['destination'] && 'ok' === end( $summary['copies'] )['state'] && $rest['restorable'] && $cli['restorable'], 'Una fila antigua con el ZIP local no es restaurable: ' . json_encode( $summary ) );
		digi_assert( in_array( $name, $list['history'], true ) && ! in_array( $name, $list['loose'], true ), 'Una fila antigua con el ZIP local no aparece en «Restaurar»: ' . $name );
	}
	$report = $B::service_verify( 'digitalisimo-backup-s4.zip' );
	digi_assert( ! is_wp_error( $report ) && 'provenance' === $report['trust'] && true === $report['registered'], 'Una fila antigua no conserva la confianza de un respaldo registrado aquí.' );
	// Sin el ZIP local sólo queda la copia remota: no es restaurable desde el almacén, ni en la pantalla ni en REST.
	unlink( $store . '/digitalisimo-backup-anterior.zip' );
	list( $summary, $rest ) = $surfaces( 'digitalisimo-backup-anterior.zip' );
	digi_assert( false === $summary['available'] && false === $summary['restorable'] && false === $rest['restorable'] && array( array( 'destination' => 'sftp', 'state' => 'ok' ) ) === $summary['copies'], 'Una fila sin ZIP local se dio por restaurable: ' . json_encode( $summary ) );
	digi_assert( ! in_array( 'digitalisimo-backup-anterior.zip', $restore_list()['history'], true ), 'Una fila sin ZIP local aparece en «Restaurar».' );
	digi_assert( false === strpos( json_encode( $B::service_backups() ), '/srv/respaldos' ) && false === strpos( json_encode( $B::service_backups() ), 'id-remoto-de-google' ), 'El historial público expuso la ruta o el identificador remoto.' );

	// 4. Una copia pendiente o fallida no cambia nada: el ZIP local sigue restaurable y la copia anuncia su estado.
	$history = (array) get_option( $B::HISTORY, array() );
	foreach ( $history as &$item ) if ( 'digitalisimo-backup-s4.zip' === $item['file'] ) $item = array_merge( $item, array( 'destination' => 'local', 'transfer' => 'failed', 'transfer_error' => 'mensaje-del-proveedor', 'remote' => array() ) );
	unset( $item );
	update_option( $B::HISTORY, $history );
	$summary = $summary_of( 'digitalisimo-backup-s4.zip' );
	digi_assert( true === $summary['restorable'] && 'local' === $summary['destination'] && 'google' === $summary['transfer_destination'] && 'failed' === $summary['transfer'] && array( array( 'destination' => 'local', 'state' => 'ok' ) ) === $summary['copies'], 'Una copia remota fallida cambió la restaurabilidad o se anunció como una copia que existe: ' . json_encode( $summary ) );
	// R4: la retención borra además su ZIP local: no queda ninguna copia. Se rotula así, REST no anuncia ninguna y se
	// puede eliminar la fila (sin pedir nada al remoto).
	rename( $store . '/digitalisimo-backup-s4.zip', $store . '/apartado.zip' );
	$summary = $summary_of( 'digitalisimo-backup-s4.zip' );
	digi_assert( false === $summary['restorable'] && 'local' === $summary['destination'] && array() === $summary['copies'] && 'failed' === $summary['transfer'], 'REST anuncia una copia que no existe: ' . json_encode( $summary ) );
	$_GET = array( 'tab' => 'backups' );
	ob_start();
	try { $B::page(); } catch ( Throwable $e ) { echo "\n[render interrumpido: ", $e->getMessage(), ']'; }
	$html = (string) ob_get_clean();
	$_GET = array();
	// Desde 1.0.74 la fila es <tr … data-file="…"> con «Borrar» (el nombre del archivo va en «⋯»).
	$at = strpos( $html, 'data-file="digitalisimo-backup-s4.zip"' );
	$tr = false === $at ? '' : substr( $html, $at, (int) strpos( substr( $html, $at ), '</tr>' ) );
	digi_assert( '' !== $tr && false !== strpos( $tr, "onsubmit=\"return confirm('¿Borrar este respaldo?')\">" ) && false !== strpos( $tr, 'name="action" value="' . $B::ACTION_DELETE . '"' ) && false === strpos( $tr, 'Descargar' ) && false === strpos( $tr, 'sin identificador' ), 'La fila sin copias no ofrece «Eliminar» en la pantalla (o ofrece descargar): ' . strip_tags( $tr ) );
	$destination = new ReflectionMethod( $B, 'history_destination' );
	if ( PHP_VERSION_ID < 80100 ) $destination->setAccessible( true );
	$label = $destination->invoke( null, $row_of( 'digitalisimo-backup-s4.zip' ) );
	digi_assert( 0 === strpos( $label, 'Sin copias: el ZIP ya no está en el almacén y la copia a Google Drive falló' ), 'La fila sin copias no se rotula así: ' . $label );
	$GLOBALS['http'] = array();
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => 'digitalisimo-backup-s4.zip' ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && null === $row_of( 'digitalisimo-backup-s4.zip' ) && array() === $GLOBALS['http'], 'La fila sin copias no se pudo eliminar o se pidió algo al remoto: ' . json_encode( $query ) );
	rename( $store . '/apartado.zip', $store . '/digitalisimo-backup-s4.zip' );

	// 5. «Eliminar» un respaldo copiado retira las dos copias y su fila; nunca deja el ZIP local como «sin registro».
	$GLOBALS['http'] = array();
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => $file ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se eliminó el respaldo copiado: ' . json_encode( $query ) );
	digi_assert( ! is_file( $store . '/' . $file ) && null === $row_of( $file ), 'Eliminar dejó el ZIP local o la fila del respaldo copiado.' );
	digi_assert( (bool) preg_grep( '#^DELETE https://www\.googleapis\.com/drive/v3/files/id-remoto-de-google$#', $GLOBALS['http'] ), 'Eliminar no retiró la copia de Google Drive: ' . json_encode( $GLOBALS['http'] ) );
	digi_assert( ! in_array( $file, $restore_list()['loose'], true ), 'El ZIP local de un respaldo eliminado quedó como «sin registro».' );
	// Si el remoto no confirma la eliminación, no se cambia nada: ni el ZIP local ni la fila.
	$keep = digi_backup( array( 'destination' => 'google' ) );
	$GLOBALS['delete_fails'] = true;
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => $keep['file'] ) );
	unset( $GLOBALS['delete_fails'] );
	digi_assert( 'error' === ( $query['backup'] ?? '' ) && is_file( $store . '/' . $keep['file'] ) && null !== $row_of( $keep['file'] ), 'Un borrado remoto fallido cambió el ZIP local o la fila: ' . json_encode( $query ) );
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => $keep['file'] ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && ! is_file( $store . '/' . $keep['file'] ) && null === $row_of( $keep['file'] ), 'No se eliminó el respaldo copiado al repetir.' );
	// Una fila antigua sin identificador remoto: se borra el ZIP local y la fila, y el aviso dice dónde queda la copia remota.
	copy( $store . '/digitalisimo-backup-s4.zip', $store . '/digitalisimo-backup-sin-id.zip' );
	$history = (array) get_option( $B::HISTORY, array() );
	array_unshift( $history, array_merge( $row, array( 'file' => 'digitalisimo-backup-sin-id.zip', 'destination' => 'onedrive', 'remote' => array() ) ) );
	update_option( $B::HISTORY, array_map( function( $item ) { if ( 'digitalisimo-backup-sin-id.zip' === $item['file'] ) unset( $item['transfer'], $item['transfer_destination'], $item['transfer_error'] ); return $item; }, $history ) );
	digi_assert( true === $summary_of( 'digitalisimo-backup-sin-id.zip' )['restorable'], 'Una fila sin identificador remoto pero con ZIP local no es restaurable.' );
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => 'digitalisimo-backup-sin-id.zip' ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && ! is_file( $store . '/digitalisimo-backup-sin-id.zip' ) && null === $row_of( 'digitalisimo-backup-sin-id.zip' ) && false !== strpos( (string) $query['message'], 'OneDrive' ), 'Una fila antigua sin identificador remoto no se pudo eliminar o no avisó de su copia remota: ' . json_encode( $query ) );

	echo "DIGITALÍSIMO Backups: un respaldo copiado a un destino remoto sigue restaurable desde su ZIP local (pantalla, REST y WP-CLI), también con filas antiguas, y «Eliminar» retira las dos copias.\n";
} finally {
	digi_rmtree( $root );
}
