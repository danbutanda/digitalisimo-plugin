<?php
/**
 * Retención en el destino remoto (B6) y «sólo en el remoto» (I4), por el motor de trabajos y las tres superficies:
 *   (a) retención global de los manuales (Conservar 2): el tercer respaldo retira el ZIP local del primero **y su copia
 *       remota** (DELETE en el destino), y la fila queda «la retención borró la copia»;
 *   (b) un respaldo bloqueado, una fila importada y una fila sin trabajo de este sitio nunca se borran del remoto, y la
 *       retención nunca lista el destino para borrar;
 *   (c) un borrado remoto que falla queda pendiente con su motivo y se reintenta (tick o vigilante) hasta confirmarse;
 *       uno permanente (conexión eliminada) queda a la vista sin llamar al destino;
 *   (d) la retención de una automatización (Conservar 1) borra su copia remota anterior y no toca las de los manuales;
 *   (e) «sólo en el remoto» manual (pantalla, REST `remote_only` y WP-CLI `--remote-only`): el ZIP local se borra tras
 *       verificar la copia remota; la fila dice que restaurar exige traerla, y `fetch` la trae y la vuelve restaurable;
 *       una copia remota que no se verificó nunca deja el respaldo sin ZIP local;
 *   (f) «sólo en el remoto» en una automatización (pantalla y REST de automatizaciones), nunca con destino local;
 *   (g) I7: el catálogo «Servidores creados» anterior pasa a conexiones con nombre sin perder ninguna.
 */
$root = sys_get_temp_dir() . '/digitalisimo-remote-retention-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
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
// Google Drive simulado: cada subida recibe su identificador; guarda el contenido por identificador y anota cada petición.
$GLOBALS['http'] = array();
$GLOBALS['drive'] = array();
$GLOBALS['next_id'] = 0;
$GLOBALS['uploading'] = '';
function digi_google( $method, $url, $args ) {
	$GLOBALS['http'][] = $method . ' ' . $url;
	if ( 'POST' === $method && false !== strpos( $url, 'oauth2' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'access_token' => 'token-de-prueba' ) ) );
	if ( 'POST' === $method ) { $GLOBALS['uploading'] = ''; return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'location' => 'https://upload.test/session/' . ( $GLOBALS['next_id'] + 1 ) ), 'body' => '' ); }
	if ( 'PUT' === $method ) {
		$GLOBALS['uploading'] .= $args['body'];
		$id = 'id-remoto-' . ( ++$GLOBALS['next_id'] );
		$GLOBALS['drive'][ $id ] = $GLOBALS['uploading'];
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'id' => $id ) ) );
	}
	if ( 'GET' === $method ) {
		$id = preg_match( '#/files/([^/?]+)#', $url, $m ) ? $m[1] : '';
		if ( ! isset( $GLOBALS['drive'][ $id ] ) ) return array( 'response' => array( 'code' => 404 ), 'body' => '' );
		file_put_contents( $args['filename'], $GLOBALS['drive'][ $id ] );
		return array( 'response' => array( 'code' => 200 ) );
	}
	if ( 'DELETE' === $method ) {
		if ( ! empty( $GLOBALS['delete_fails'] ) ) return array( 'response' => array( 'code' => 500 ), 'body' => '' );
		$id = preg_match( '#/files/([^/?]+)#', $url, $m ) ? $m[1] : '';
		unset( $GLOBALS['drive'][ $id ] );
		return array( 'response' => array( 'code' => 204 ), 'body' => '' );
	}
	return array( 'response' => array( 'code' => 400 ), 'body' => '' );
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
$row_of = function( $file ) use ( $B ) { foreach ( (array) get_option( $B::HISTORY, array() ) as $row ) if ( $file === ( $row['file'] ?? '' ) ) return $row; return null; };
$summary_of = function( $file ) use ( $B ) { foreach ( $B::service_backups() as $row ) if ( $file === $row['file'] ) return $row; return null; };
$deletes = function() { return array_values( preg_grep( '#^DELETE #', $GLOBALS['http'] ) ); };
$label_of = function( $file ) use ( $B, $row_of ) {
	$method = new ReflectionMethod( $B, 'history_destination' );
	if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
	return $method->invoke( null, $row_of( $file ) );
};
$set_row = function( $file, array $changes ) use ( $B ) {
	$history = (array) get_option( $B::HISTORY, array() );
	foreach ( $history as &$item ) if ( $file === ( $item['file'] ?? '' ) ) $item = array_merge( $item, $changes );
	unset( $item );
	update_option( $B::HISTORY, $history );
};
$backup = function( array $args ) {
	$created = digi_backup( $args );
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	return $created['file'];
};

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => array( 'destination' => 'local', 'retention' => '2', 'google_client_id' => 'cliente-google', 'google_client_secret' => 'secreto-google', 'google_refresh_token' => 'renovar-google' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardó la conexión de Google Drive: ' . json_encode( $query ) );

	// (a) Conservar 2 entre los manuales: el tercero retira el primero también del remoto.
	$first = $backup( array( 'destination' => 'google' ) );
	$first_id = (string) $row_of( $first )['remote']['id'];
	$second = $backup( array( 'destination' => 'google' ) );
	$GLOBALS['http'] = array();
	$third = $backup( array( 'destination' => 'google' ) );
	digi_assert( ! is_file( $store . '/' . $first ) && is_file( $store . '/' . $second ) && is_file( $store . '/' . $third ), 'La retención local no retiró sólo el primero.' );
	digi_assert( array( 'DELETE https://www.googleapis.com/drive/v3/files/' . $first_id ) === $deletes() && ! isset( $GLOBALS['drive'][ $first_id ] ), 'La retención no borró la copia remota del primero (o borró otra): ' . json_encode( $deletes() ) );
	digi_assert( 'pruned' === $row_of( $first )['transfer'] && array() === $row_of( $first )['remote'] && ! isset( $row_of( $first )['remote_prune'] ) && 'ok' === $row_of( $second )['transfer'], 'La fila del primero no quedó como copia remota retirada: ' . json_encode( $row_of( $first ) ) );
	digi_assert( 0 === strpos( $label_of( $first ), 'Sin copias: el ZIP ya no está en el almacén y la retención borró la copia en Google Drive' ), 'Rótulo de la fila retirada: ' . $label_of( $first ) );
	$summary = $summary_of( $first );
	digi_assert( array() === $summary['copies'] && 'pruned' === $summary['transfer'] && false === $summary['restorable'], 'REST anuncia una copia retirada: ' . json_encode( $summary ) );
	digi_assert( ! preg_grep( '#\?q=|/files\?#', $GLOBALS['http'] ), 'La retención listó el destino: ' . json_encode( $GLOBALS['http'] ) );
	// «Eliminar» de la fila retirada sólo la quita: no pide nada al destino.
	$GLOBALS['http'] = array();
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => $first ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && null === $row_of( $first ) && ! $deletes(), 'Eliminar la fila retirada llamó al destino: ' . json_encode( array( $query, $GLOBALS['http'] ) ) );

	// (b) Bloqueado, importado y sin trabajo de este sitio: nunca se borran del remoto.
	$query = digi_admin_post( 'toggle_lock', $B::ACTION_LOCK, array( 'file' => $second, 'locked' => '1' ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se bloqueó el segundo.' );
	$second_id = (string) $row_of( $second )['remote']['id'];
	$third_id = (string) $row_of( $third )['remote']['id'];
	$history = (array) get_option( $B::HISTORY, array() );
	$template = $row_of( $third );
	foreach ( array( 'digitalisimo-backup-importado.zip' => array( 'origin' => 'imported' ), 'digitalisimo-backup-sin-trabajo.zip' => array( 'job' => '' ), 'digitalisimo-backup-otro-sha.zip' => array() ) as $name => $changes ) {
		$id = 'ajeno-' . md5( $name );
		$GLOBALS['drive'][ $id ] = 'contenido ajeno';
		$row = array_merge( $template, array( 'file' => $name, 'remote' => array_merge( $template['remote'], array( 'id' => $id ) ) ), $changes );
		if ( 'digitalisimo-backup-otro-sha.zip' === $name ) $row['remote']['sha256'] = str_repeat( 'b', 64 );
		$history[] = $row; // Más antiguas que todas: la retención las retiraría.
	}
	update_option( $B::HISTORY, $history );
	$GLOBALS['http'] = array();
	$fourth = $backup( array( 'destination' => 'google' ) );
	$fifth = $backup( array( 'destination' => 'google' ) );
	digi_assert( isset( $GLOBALS['drive'][ $second_id ] ) && 'ok' === $row_of( $second )['transfer'], 'La retención borró del remoto un respaldo bloqueado.' );
	foreach ( array( 'digitalisimo-backup-importado.zip', 'digitalisimo-backup-sin-trabajo.zip', 'digitalisimo-backup-otro-sha.zip' ) as $name ) digi_assert( isset( $GLOBALS['drive'][ 'ajeno-' . md5( $name ) ] ) && ! isset( $row_of( $name )['remote_prune'] ), 'La retención tocó una copia remota que no registró este sitio: ' . $name );
	digi_assert( ! isset( $GLOBALS['drive'][ $third_id ] ) && 'pruned' === $row_of( $third )['transfer'], 'El tercero (sin bloquear, fuera de los 2 más recientes) conserva su copia remota.' );
	digi_assert( isset( $GLOBALS['drive'][ (string) $row_of( $fourth )['remote']['id'] ] ) && isset( $GLOBALS['drive'][ (string) $row_of( $fifth )['remote']['id'] ] ), 'La retención borró una de las 2 copias más recientes.' );

	// (c) Fallo pasajero: queda pendiente con su motivo, se reintenta y se confirma.
	$GLOBALS['delete_fails'] = true;
	$sixth = $backup( array( 'destination' => 'google' ) );
	$fourth_id = (string) $row_of( $fourth )['remote']['id'];
	$prune = (array) ( $row_of( $fourth )['remote_prune'] ?? array() );
	digi_assert( 'pending' === ( $prune['state'] ?? '' ) && 1 === (int) $prune['attempts'] && '' !== (string) $prune['error'] && (int) $prune['next'] > time() && isset( $GLOBALS['drive'][ $fourth_id ] ), 'Un borrado remoto fallido no quedó pendiente con su motivo: ' . json_encode( $prune ) );
	digi_assert( ! is_file( $store . '/' . $fourth ) && 'ok' === $row_of( $fourth )['transfer'], 'Con el borrado remoto pendiente, la fila perdió su copia remota o conservó el ZIP local.' );
	digi_assert( false !== strpos( $label_of( $fourth ), 'la retención la borrará (reintento 1' ), 'Rótulo del borrado pendiente: ' . $label_of( $fourth ) );
	digi_assert( 'pending' === $summary_of( $fourth )['remote_retention'] && '' !== $summary_of( $fourth )['remote_retention_error'], 'REST no informa el borrado remoto pendiente.' );
	// Antes de su hora no se reintenta.
	$GLOBALS['http'] = array();
	$B::job_remote_retention_pending();
	digi_assert( ! $deletes(), 'Se reintentó antes de la espera.' );
	unset( $GLOBALS['delete_fails'] );
	$set_row( $fourth, array( 'remote_prune' => array_merge( $prune, array( 'next' => time() - 1 ) ) ) );
	Digitalisimo_Backups_Jobs::watchdog();
	digi_assert( ! isset( $GLOBALS['drive'][ $fourth_id ] ) && 'pruned' === $row_of( $fourth )['transfer'] && ! isset( $row_of( $fourth )['remote_prune'] ), 'El vigilante no completó el borrado remoto pendiente: ' . json_encode( $row_of( $fourth ) ) );
	// Permanente: la conexión de la copia ya no existe; no se llama al destino y queda a la vista.
	$set_row( $fifth, array( 'remote' => array_merge( $row_of( $fifth )['remote'], array( 'connection' => 'google-0a1b2c3d' ) ), 'remote_prune' => array( 'state' => 'pending', 'attempts' => 0, 'next' => 0, 'error' => '', 'at' => time() ) ) );
	$GLOBALS['http'] = array();
	$B::job_remote_retention_pending();
	$prune = (array) $row_of( $fifth )['remote_prune'];
	digi_assert( 'failed' === $prune['state'] && false !== strpos( $prune['error'], 'se eliminó' ) && ! $deletes(), 'Una conexión eliminada no dejó el borrado como fallido sin llamar al destino: ' . json_encode( array( $prune, $GLOBALS['http'] ) ) );
	digi_assert( false !== strpos( $label_of( $fifth ), 'la retención no pudo borrarla' ), 'Rótulo del borrado fallido: ' . $label_of( $fifth ) );
	$set_row( $fifth, array( 'remote' => array_merge( $row_of( $fifth )['remote'], array( 'connection' => 'google' ) ) ) );
	// Bloqueado después de anotarlo: no se borra.
	$set_row( $fifth, array( 'locked' => true, 'remote_prune' => array( 'state' => 'pending', 'attempts' => 0, 'next' => 0, 'error' => '', 'at' => time() ) ) );
	$B::job_remote_retention_pending();
	digi_assert( isset( $GLOBALS['drive'][ (string) $row_of( $fifth )['remote']['id'] ] ) && ! isset( $row_of( $fifth )['remote_prune'] ), 'Se borró del remoto un respaldo bloqueado después de anotarlo.' );
	$set_row( $fifth, array( 'locked' => false ) );

	// (d) Automatización con Google Drive y Conservar 1: su retención borra su copia anterior, no las de los manuales.
	$query = digi_admin_post( 'create_automation', $B::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'weekly', 'destination' => 'google', 'retain' => '1' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se creó la automatización: ' . json_encode( $query ) );
	$policies = array_values( array_filter( $B::service_automations(), function( $policy ) { return 'google' === $policy['destination']; } ) );
	$policy = $policies[0]['id'];
	$run = function() use ( $B, $policy ) { $queued = $B::service_run_automation( $policy, array( 'spawn' => false ) ); digi_assert( ! is_wp_error( $queued ), 'No se encoló la automatización.' ); $done = digi_drive( $queued['id'] ); digi_assert( 'success' === $done['status'], 'La automatización falló: ' . $done['error'] ); return $done['file']; };
	$manual_ids = array_filter( array_map( function( $row ) { return 'manual' === ( $row['policy'] ?? '' ) && 'ok' === ( $row['transfer'] ?? '' ) ? (string) $row['remote']['id'] : ''; }, (array) get_option( $B::HISTORY, array() ) ) );
	$auto1 = $run();
	$auto1_id = (string) $row_of( $auto1 )['remote']['id'];
	$auto2 = $run();
	digi_assert( ! isset( $GLOBALS['drive'][ $auto1_id ] ) && 'pruned' === $row_of( $auto1 )['transfer'] && isset( $GLOBALS['drive'][ (string) $row_of( $auto2 )['remote']['id'] ] ), 'La retención de la automatización no borró su copia remota anterior.' );
	foreach ( $manual_ids as $id ) digi_assert( isset( $GLOBALS['drive'][ $id ] ), 'La retención de una automatización borró la copia remota de un manual.' );

	// (e) «Sólo en el remoto» manual: pantalla (casilla), REST y WP-CLI.
	$_GET = array();
	ob_start();
	try { $B::page(); } catch ( Throwable $e ) { echo "\n[render interrumpido: ", $e->getMessage(), ']'; }
	$html = (string) ob_get_clean();
	digi_assert( false !== strpos( $html, 'name="digitalisimo_backup_remote_only"' ) && false !== strpos( $html, 'Restaurar exigirá descargarla antes' ), 'La pantalla no ofrece «Sólo en el remoto» con su aviso.' );
	$only = $backup( array( 'destination' => 'google', 'remote_only' => true ) );
	$summary = $summary_of( $only );
	digi_assert( ! is_file( $store . '/' . $only ) && 'remote-only' === $row_of( $only )['local'] && true === $summary['remote_only'] && false === $summary['restorable'] && array( array( 'destination' => 'google', 'state' => 'ok' ) ) === $summary['copies'], '«Sólo en el remoto» conservó el ZIP local o no lo anunció: ' . json_encode( $summary ) );
	digi_assert( false !== strpos( $label_of( $only ), 'sólo en el remoto (restaurar exige traerla al almacén)' ), 'Rótulo de «sólo en el remoto»: ' . $label_of( $only ) );
	list( $code, $fetched ) = digi_rest( 'POST', $ns . '/backups/' . $only . '/fetch' );
	digi_assert( 200 === $code && true === $fetched['fetched'] && is_file( $store . '/' . $only ) && true === $summary_of( $only )['restorable'], 'fetch no trajo la copia remota de «sólo en el remoto»: ' . json_encode( $fetched ) );
	list( $code, $queued ) = digi_rest( 'POST', $ns . '/backups', array( 'destination' => 'google', 'remote_only' => true ) );
	digi_assert( 202 === $code && ! empty( $queued['id'] ), 'REST no encoló con remote_only: ' . json_encode( array( $code, $queued ) ) );
	$done = digi_drive( $queued['id'] );
	digi_assert( 'success' === $done['status'] && ! is_file( $store . '/' . $done['file'] ) && 'remote-only' === $row_of( $done['file'] )['local'], 'REST remote_only conservó el ZIP local.' );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'create', array(), array( 'destination' => 'google', 'remote-only' => true, 'porcelain' => true ) );
	$cli_file = trim( (string) end( $out ) );
	digi_assert( 0 === $code && '' !== $cli_file && ! is_file( $store . '/' . $cli_file ) && 'remote-only' === ( $row_of( $cli_file )['local'] ?? '' ), 'WP-CLI --remote-only conservó el ZIP local: ' . implode( "\n", $out ) );
	// Sin destino remoto no aplica; y una copia remota que no se verificó nunca deja el respaldo sin ZIP local.
	$local = $backup( array( 'destination' => 'local', 'remote_only' => true ) );
	digi_assert( is_file( $store . '/' . $local ), '«Sólo en el remoto» con destino local borró el ZIP.' );
	$set_row( $local, array( 'transfer' => 'failed', 'transfer_destination' => 'google', 'remote' => array() ) );
	digi_assert( '' === $B::job_remote_only( array( 'remote_only' => true, 'destination' => 'google', 'file' => $local ) ) && is_file( $store . '/' . $local ), 'Con la copia remota fallida se borró el ZIP local.' );

	// (f) Automatización «sólo en el remoto»; con destino local no se guarda.
	$query = digi_admin_post( 'create_automation', $B::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'google', 'retain' => '3', 'remote_only' => '1' ) ) );
	$query2 = digi_admin_post( 'create_automation', $B::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => '3', 'remote_only' => '1' ) ) );
	$automations = $B::service_automations();
	$remote_auto = array_values( array_filter( $automations, function( $policy ) { return 'google' === $policy['destination'] && 'daily' === $policy['schedule']; } ) );
	$local_auto = array_values( array_filter( $automations, function( $policy ) { return 'local' === $policy['destination'] && 'daily' === $policy['schedule']; } ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && 'ok' === ( $query2['backup'] ?? '' ) && $remote_auto && true === $remote_auto[0]['remote_only'] && $local_auto && false === $local_auto[0]['remote_only'], 'La automatización no guardó «sólo en el remoto» (o lo guardó con destino local): ' . json_encode( $automations ) );
	$queued = $B::service_run_automation( $remote_auto[0]['id'], array( 'spawn' => false ) );
	$done = digi_drive( $queued['id'] );
	digi_assert( 'success' === $done['status'] && ! is_file( $store . '/' . $done['file'] ) && 'remote-only' === $row_of( $done['file'] )['local'], 'La automatización «sólo en el remoto» conservó el ZIP local.' );
	$_GET = array( 'tab' => 'automations' );
	ob_start();
	try { $B::page(); } catch ( Throwable $e ) { echo "\n[render interrumpido: ", $e->getMessage(), ']'; }
	$html = (string) ob_get_clean();
	$_GET = array();
	digi_assert( false !== strpos( $html, 'name="digitalisimo_backup_automation[remote_only]" value="1" checked' ), 'La automatización no muestra «Sólo en el remoto» marcado.' );

	// (g) I7: el catálogo «Servidores creados» anterior se retira sin perder nada: su `legacy-ftp` es la conexión SFTP
	// principal (sigue en los ajustes) y cualquier otra entrada pasa a ser una conexión con nombre del mismo tipo.
	update_option( 'digitalisimo_backups_servers', array( array( 'id' => 'legacy-ftp', 'type' => 'sftp', 'name' => 'SFTP', 'host' => 'sftp.test', 'verified' => false ), array( 'id' => 'server-1', 'type' => 'sftp', 'name' => 'Copia en Hetzner', 'verified' => false ), array( 'id' => 'server-2', 'type' => 'nada', 'name' => 'Tipo desconocido' ) ) );
	$before = count( Digitalisimo_Backups_Destinations::connections() );
	$listed = $B::service_destinations();
	$named = array_values( array_filter( Digitalisimo_Backups_Destinations::connections(), function( $connection ) { return 'Copia en Hetzner' === $connection['name']; } ) );
	digi_assert( ! is_wp_error( $listed ) && 1 === count( $named ) && 'sftp' === $named[0]['type'] && $before + 1 === count( Digitalisimo_Backups_Destinations::connections() ) && false === get_option( 'digitalisimo_backups_servers', false ), 'El catálogo anterior no pasó a conexiones con nombre: ' . json_encode( Digitalisimo_Backups_Destinations::connections() ) );
	digi_assert( (bool) array_filter( $listed, function( $row ) { return 'Copia en Hetzner' === $row['name']; } ), 'destinations list no muestra la conexión migrada.' );
	$B::service_destinations();
	digi_assert( $before + 1 === count( Digitalisimo_Backups_Destinations::connections() ), 'Migrar el catálogo dos veces duplicó la conexión.' );
	$_GET = array( 'tab' => 'servers' );
	ob_start();
	try { $B::page(); } catch ( Throwable $e ) { echo "\n[render interrumpido: ", $e->getMessage(), ']'; }
	$html = (string) ob_get_clean();
	$_GET = array();
	digi_assert( false === strpos( $html, 'Servidores creados' ) && false !== strpos( $html, 'Copia en Hetzner' ), '«Servidores» sigue mostrando el catálogo anterior o no la conexión migrada.' );

	echo "DIGITALÍSIMO Backups: la retención borra también las copias remotas que registró este sitio (nunca las bloqueadas ni las ajenas, con reintento) y «sólo en el remoto» deja el respaldo sólo en el destino tras verificarlo.\n";
} finally {
	digi_rmtree( $root );
}
