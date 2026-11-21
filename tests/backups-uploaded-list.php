<?php
/**
 * Pedido del propietario (2026-11-15): «el respaldo que se sube debe quedar en el listado de respaldos ya cargado, como
 * si se hubiera hecho ahí mismo, para usarlo cuando se quiera, exactamente igual […], y borrarse y todo igual».
 *
 * Con el motor real (REST, AJAX de la pantalla, admin-post, WP-CLI y la cola de trabajos):
 *   (a) un respaldo del «sitio A» subido por fragmentos queda en la lista (REST `GET /backups`, WP-CLI `list` y la
 *       pantalla) con su fecha de creación, su tamaño, su tipo y su origen «Subido» (de sitio-a.test); «Último respaldo»
 *       sigue siendo el último de este sitio;
 *   (b) se verifica y se restaura (completo y por partes) escribiendo RESTAURAR, sin la frase de sin firma, y sigue
 *       siendo «de otra instalación» para H23 y «Conservar mi acceso»;
 *   (c) un archivo cambiado en el almacén después de registrarse (también por otro respaldo válido) se rechaza;
 *   (d) se descarga (el handler real, en otro proceso) con los bytes exactos de su fila;
 *   (e) se envía a un destino (Google Drive simulado), su copia remota queda en la fila, se trae de vuelta verificada
 *       por su SHA-256 y la retención nunca borra esa copia; «Borrar» quita el ZIP, la copia remota y la fila;
 *   (f) «Respaldo reciente válido» no cuenta un subido de otro sitio ni uno de este sitio sin firma; sí uno de este mismo
 *       sitio firmado con su clave; «Restaurar sólo respaldos firmados» sigue bloqueando el subido sin firma;
 *   (g) «Agregar al listado» (REST, admin-post y WP-CLI) pasa a la lista un archivo «sin registro» del almacén después de
 *       validarlo; uno hostil o repetido no entra y sigue en el almacén;
 *   (h) si el proceso muere después de mover el archivo al almacén, cerrar otra vez la subida termina la fila;
 *   (i) en una red, lo subido queda en la lista de la red; un respaldo de un sitio individual se lista pero no se
 *       restaura en la red (y la pantalla dice por qué).
 */
$root = sys_get_temp_dir() . '/digitalisimo-uploaded-list-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
file_put_contents( $root . '/public/wp-content/uploads-foto.bin', random_bytes( 200000 ) );
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce-' . $action; }
function wp_delete_file( $file ) { return @unlink( $file ); }
function size_format( $bytes ) { return $bytes . ' B'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function disabled( $a, $b = true, $echo = true ) { return $a === $b ? ' disabled="disabled"' : ''; }
function wp_max_upload_size() { return 204800; }
function delete_site_option( $key ) { unset( $GLOBALS['digi']['site_options'][ $key ] ); return true; }
function check_ajax_referer( $action, $field ) { if ( ( $_REQUEST[ $field ] ?? '' ) !== 'nonce-' . $action ) throw new Digi_Die( 'Nonce inválido para ' . $action ); return 1; }
if ( ! function_exists( 'wp_schedule_event' ) ) { function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { return true; } }
if ( ! function_exists( 'wp_unschedule_event' ) ) { function wp_unschedule_event( $timestamp, $hook, $args = array() ) { return true; } }
class Digi_Json extends Exception { public $data; public $status; public $ok; public function __construct( $ok, $data, $status ) { parent::__construct( 'json' ); $this->ok = $ok; $this->data = $data; $this->status = $status; } }
function wp_send_json_success( $data = null ) { throw new Digi_Json( true, $data, 200 ); }
function wp_send_json_error( $data = null, $status = 400 ) { throw new Digi_Json( false, $data, $status ); }
if ( class_exists( 'Digitalisimo_Backups_Uploads' ) ) Digitalisimo_Backups_Uploads::$is_uploaded = function( $path ) { return 0 === strpos( (string) $path, $GLOBALS['tmp_uploads'] ); };
// Google Drive simulado (como tests/backups-remote-retention.php): cada subida recibe un identificador; GET y DELETE.
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
		$bytes = $GLOBALS['drive'][ $id ];
		if ( false !== strpos( $url, 'fields=' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'id' => $id, 'name' => 'remoto.zip', 'size' => (string) strlen( $bytes ) ) ) );
		// Un archivo pequeño cabe en el primer fragmento: el destino responde entero (200), como Google Drive sin `Range`.
		if ( ! empty( $args['filename'] ) ) { file_put_contents( $args['filename'], $bytes ); return array( 'response' => array( 'code' => 200 ), 'body' => '' ); }
		return array( 'response' => array( 'code' => 200 ), 'body' => $bytes );
	}
	if ( 'DELETE' === $method ) {
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
function wp_remote_retrieve_headers( $response ) { return is_array( $response ) ? (array) ( $response['headers'] ?? array() ) : array(); }

$B = 'Digitalisimo_Backups';
$U = 'Digitalisimo_Backups_Uploads';
$ns = 'digitalisimo-backups/v1';
$store = $root . '/private';
$GLOBALS['tmp_uploads'] = $root . '/php-tmp';
mkdir( $GLOBALS['tmp_uploads'], 0700 );
$call = function( $method, ...$args ) use ( $B ) {
	$reflection = new ReflectionMethod( $B, $method );
	if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true );
	return $reflection->invoke( null, ...$args );
};
$row_of = function( $file ) use ( $B ) { foreach ( (array) get_option( $B::HISTORY, array() ) as $row ) if ( $file === ( $row['file'] ?? '' ) ) return $row; return null; };
$summary_of = function( $file ) use ( $ns ) { list( , $rows ) = digi_rest( 'GET', $ns . '/backups' ); foreach ( (array) $rows as $row ) if ( $file === $row['file'] ) return $row; return null; };
/** Un respaldo hecho aquí y retirado de este sitio (archivo y fila): sus bytes, como si vinieran de otro servidor. */
$fresh = function( array $args = array() ) use ( $B, $store ) {
	$made = digi_backup( $args );
	digi_assert( ! is_wp_error( $made ), 'No se creó el respaldo de partida: ' . ( is_wp_error( $made ) ? $made->get_error_message() : '' ) );
	$content = file_get_contents( $store . '/' . $made['file'] );
	unlink( $store . '/' . $made['file'] );
	update_option( $B::HISTORY, array_values( array_filter( (array) get_option( $B::HISTORY, array() ), function( $row ) use ( $made ) { return $row['file'] !== $made['file']; } ) ) );
	return array( $made['file'], $content );
};
/** El mismo respaldo con la dirección de otro sitio en su manifiesto (sin firma: nada más que cambiar). */
$elsewhere = function( $bytes, $home ) use ( $root ) {
	$path = $root . '/otro-' . bin2hex( random_bytes( 3 ) ) . '.zip';
	file_put_contents( $path, $bytes );
	$zip = new ZipArchive();
	digi_assert( true === $zip->open( $path ), 'No se abrió el respaldo de partida.' );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$manifest['home_url'] = $home;
	$manifest['site_url'] = $home;
	$manifest['origin']['home'] = $home;
	$manifest['origin']['siteurl'] = $home;
	$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
	$zip->close();
	$out = file_get_contents( $path );
	unlink( $path );
	return $out;
};
/** Sube unos bytes por REST, por fragmentos del tamaño de la sesión, y la cierra. */
$upload = function( $name, $bytes, array $finish = array() ) use ( $ns ) {
	list( $code, $session ) = digi_rest( 'POST', $ns . '/uploads', array( 'name' => $name, 'size' => strlen( $bytes ) ) );
	digi_assert( 201 === $code, 'POST /uploads no empezó la subida: ' . json_encode( $session ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) {
		$result = Digitalisimo_Backups_Rest::upload_chunk( new Digi_Raw_Request( array( 'id' => $session['id'], 'offset' => $offset ), substr( $bytes, $offset, $session['chunk_size'] ) ) );
		digi_assert( ! is_wp_error( $result ), 'Un fragmento por REST falló.' );
	}
	list( $code, $result ) = digi_rest( 'POST', $ns . '/uploads/' . $session['id'] . '/finish', $finish );
	return array( $code, $result, $session['id'] );
};
class Digi_Raw_Request extends WP_REST_Request {
	private $raw;
	public function __construct( array $params, $raw ) { parent::__construct( $params ); $this->raw = $raw; }
	public function get_body() { return $this->raw; }
	public function get_file_params() { return array(); }
}
/** La lista de la pestaña «Respaldos» y su bloque de estado, como los pinta la pantalla. */
$screen = function() use ( $call, $B ) {
	$all = (array) get_option( $B::HISTORY, array() );
	ob_start();
	$call( 'status_block', $all );
	$call( 'history_panel', array_slice( $all, 0, 10 ), is_multisite(), count( $all ) );
	return (string) ob_get_clean();
};
$restore = function( $file, $confirm, array $more = array() ) use ( $ns ) {
	// Un subido de otro sitio: su dominio de origen, escrito a mano como en la pantalla.
	return digi_rest( 'POST', $ns . '/backups/' . $file . '/restore', array( 'confirm' => $confirm, 'origin_domain' => Digitalisimo_Backups::restore_origin_host( $file ) ) + $more );
};

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	update_option( $B::OPTION, array( 'retention' => '100' ) );

	// (a) El «sitio A»: un respaldo de sitio-a.test, subido por fragmentos. Antes, uno propio de este sitio.
	list( , $a_bytes ) = $fresh();
	$a_bytes = $elsewhere( $a_bytes, 'https://sitio-a.test' );
	$own = digi_backup();
	list( $code, $result ) = $upload( 'digitalisimo-backup-sitio-a-test-20261114-101500-0a1b2c3d.zip', $a_bytes );
	$a = (string) ( $result['file'] ?? '' );
	digi_assert( 200 === $code && 'digitalisimo-backup-sitio-a-test-20261114-101500-0a1b2c3d.zip' === $a && true === $result['listed'] && 'RESTAURAR' === $result['confirmation'] && 'uploaded' === $result['vouched'] && true === $result['restorable'] && 'https://sitio-a.test' === $result['uploaded']['home'], 'La subida no quedó en la lista lista para restaurar: ' . json_encode( $result ) );
	$zip = new ZipArchive();
	$zip->open( $store . '/' . $a );
	$created = json_decode( (string) $zip->getFromName( 'manifest.json' ), true )['created_at'];
	$zip->close();
	$row = $row_of( $a );
	digi_assert( 'uploaded' === $row['origin'] && 'uploaded' === $row['policy'] && 'full' === $row['backup_type'] && 'installation' === $row['scope'] && strlen( $a_bytes ) === $row['size'] && hash( 'sha256', $a_bytes ) === $row['sha256'] && gmdate( 'Y-m-d H:i:s', strtotime( $created ) ) === $row['date'] && 'rest' === $row['upload']['via'] && empty( $row['installation'] ), 'La fila del subido no tiene su fecha de creación, tamaño, SHA-256, tipo u origen: ' . json_encode( $row ) );
	// REST y WP-CLI lo listan igual que uno hecho aquí, con su origen.
	$summary = $summary_of( $a );
	digi_assert( $summary && true === $summary['restorable'] && true === $summary['available'] && 'uploaded' === $summary['origin'] && 'https://sitio-a.test' === $summary['uploaded']['home'] && strlen( $a_bytes ) === $summary['size'] && 'full' === $summary['type'], 'GET /backups no lista el subido como uno hecho aquí: ' . json_encode( $summary ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'list', array(), array( 'format' => 'table' ) );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), $a ) && false !== strpos( str_replace( '\\/', '/', implode( "\n", $out ) ), 'subido de https://sitio-a.test' ), 'wp … list no lista el subido con su origen: ' . implode( "\n", $out ) );
	$html = $screen();
	digi_assert( false !== strpos( $html, 'data-file="' . $a . '"' ) && false !== strpos( $html, 'is-uploaded' ) && false !== strpos( $html, 'de https://sitio-a.test' ) && false !== strpos( $html, 'data-restore-toggle' ) && false !== strpos( $html, 'action=digitalisimo_backups_download&file=' . rawurlencode( $a ) ), 'La pantalla no lista el subido con «Subido», Restaurar y Descargar.' );
	digi_assert( preg_match( '#Último respaldo</dt><dd>([^<]*)</dd>#', $html, $m ) && false !== strpos( $m[1], (string) $own['size'] ) && $own['file'] === $B::service_status()['last_backup']['file'], '«Último respaldo» tomó el subido en vez del último de este sitio: ' . ( $m[1] ?? '' ) );
	digi_assert( ! array_key_exists( $a, array_flip( glob( $store . '/incoming/*/data.bin' ) ?: array() ) ), 'Quedaron los bytes de la subida en su sesión.' );

	// (b) Verificar y restaurar, completo y por partes, escribiendo RESTAURAR.
	list( $code, $verified ) = digi_rest( 'GET', $ns . '/backups/' . $a . '/verify' );
	digi_assert( 200 === $code && true === $verified['registered'] && 'untrusted' === $verified['trust'] && 'uploaded' === $verified['vouched'] && 'RESTAURAR' === $verified['confirmation'], 'verify no da RESTAURAR para el subido: ' . json_encode( $verified ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'verify', array( $a ) );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), 'subido y en la lista' ), 'wp … verify no dice que es un subido: ' . implode( "\n", $out ) );
	$GLOBALS['wpdb']->set( 'blogname', 'Cambiado después del respaldo' );
	list( $code, $restored ) = $restore( $a, 'RESTAURAR' );
	digi_assert( 200 === $code && true === $restored['restored'] && 'untrusted' === $restored['trust'] && 'uploaded' === $restored['vouched'] && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ), 'El subido no se restauró con RESTAURAR: ' . json_encode( $restored ) );
	digi_assert( false === $call( 'keep_config_same_installation', $a ) && true === $call( 'keep_config_same_installation', $own['file'] ), 'Para H23 y «Conservar mi acceso», el subido pasó a ser de esta instalación (o el propio dejó de serlo).' );
	$GLOBALS['wpdb']->set( 'blogname', 'Cambiado otra vez' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	list( $code, $parts ) = $restore( $a, 'RESTAURAR', array( 'parts' => 'database' ) );
	digi_assert( 200 === $code && true === $parts['restored'] && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ) && "<?php // cambiado\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'Restaurar por partes el subido no devolvió sólo la base de datos: ' . json_encode( $parts ) );
	// La pantalla (admin-post) también, con RESTAURAR.
	$query = digi_admin_post( 'restore_request', $B::ACTION_RESTORE, array( 'backup_file' => $a, 'origin_domain' => Digitalisimo_Backups::restore_origin_host( $a ), 'confirmation' => 'RESTAURAR' ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), '«Restaurar» de la pantalla no restauró el subido con RESTAURAR: ' . json_encode( $query ) );

	// (c) Un archivo cambiado en el almacén después de registrarse no hereda la confianza de su fila.
	list( , $impostor ) = $fresh();
	file_put_contents( $store . '/' . $a, $impostor );
	list( $code, $error ) = $restore( $a, 'RESTAURAR' );
	digi_assert( 422 === $code && false !== strpos( $error['message'], 'SHA-256' ), 'Un archivo cambiado después de registrarse se restauró con la confianza de su fila: ' . json_encode( $error ) );
	list( $code, $error ) = $restore( $a, 'RESTAURAR SIN FIRMA' );
	digi_assert( 422 === $code, 'Un archivo cambiado después de registrarse se restauró con RESTAURAR SIN FIRMA.' );
	list( $code ) = digi_rest( 'GET', $ns . '/backups/' . $a . '/verify' );
	digi_assert( 422 === $code, 'verify aceptó un archivo cambiado después de registrarse.' );
	file_put_contents( $store . '/' . $a, $a_bytes );

	// (d) Descargar: el handler real («Descargar» de la fila) en otro proceso, con los bytes exactos de su fila.
	file_put_contents( $root . '/options.ser', serialize( $GLOBALS['digi']['options'] ) );
	file_put_contents( $root . '/download.php', '<?php
define( "ABSPATH", ' . var_export( ABSPATH, true ) . ' ); define( "WP_CONTENT_DIR", ' . var_export( WP_CONTENT_DIR, true ) . ' ); define( "WP_PLUGIN_DIR", ' . var_export( WP_PLUGIN_DIR, true ) . ' ); define( "DIGITALISIMO_BACKUPS_STORAGE_DIR", ' . var_export( $store, true ) . ' );
require ' . var_export( __DIR__ . '/backups-coverage/env.php', true ) . ';
function nocache_headers() {} function esc_html( $v ) { return (string) $v; }
$GLOBALS["digi"]["options"] = unserialize( file_get_contents( ' . var_export( $root . '/options.ser', true ) . ' ) );
$_GET = $_REQUEST = array( "file" => $argv[1], "_wpnonce" => "nonce-" . Digitalisimo_Backups::ACTION_DOWNLOAD );
Digitalisimo_Backups::download();' );
	$downloaded = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/download.php' ) . ' ' . escapeshellarg( $a ) . ' 2>/dev/null' );
	digi_assert( is_string( $downloaded ) && hash( 'sha256', $downloaded ) === $row_of( $a )['sha256'], '«Descargar» no envió los bytes exactos del subido (' . strlen( (string) $downloaded ) . ' bytes).' );

	// (e) Enviar a un destino (Google Drive simulado), traerlo de vuelta y borrarlo.
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => array( 'destination' => 'local', 'retention' => '100', 'google_client_id' => 'cliente-google', 'google_client_secret' => 'secreto-google', 'google_refresh_token' => 'renovar-google' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardó la conexión de Google Drive: ' . json_encode( $query ) );
	$html = $screen();
	digi_assert( preg_match( '#data-file="' . preg_quote( $a, '#' ) . '".*?</tr>#s', $html, $m ) && false !== strpos( $m[0], 'name="action" value="digitalisimo_backups_send"' ) && false !== strpos( $m[0], '<option value="google">' ), 'La fila del subido no ofrece «Enviar a» un destino.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $a . '/send', array( 'destination' => 'nada' ) );
	digi_assert( 404 === $code, 'Se envió a un destino que no existe: ' . json_encode( $error ) );
	$sent = $B::service_send( $a, array( 'destination' => 'google', 'drive_cli' => true ) );
	$row = $row_of( $a );
	$remote_id = (string) ( $row['remote']['id'] ?? '' );
	digi_assert( ! is_wp_error( $sent ) && true === $sent['sent'] && 'ok' === $row['transfer'] && 'google' === $row['transfer_destination'] && '' !== $remote_id && $a_bytes === ( $GLOBALS['drive'][ $remote_id ] ?? '' ), 'El subido no se envió a Google Drive con su copia en la fila: ' . json_encode( is_wp_error( $sent ) ? $sent->get_error_message() : array( $sent, $row ) ) );
	digi_assert( in_array( 'google', array_column( $summary_of( $a )['copies'], 'destination' ), true ), 'GET /backups no dice que el subido tiene copia en Google Drive.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $a . '/send', array( 'destination' => 'google' ) );
	digi_assert( 409 === $code && 'digitalisimo_backups_send_copy' === $error['code'], 'Se envió otra vez un respaldo que ya tiene su copia remota: ' . json_encode( $error ) );
	digi_assert( false === $call( 'remote_owned', $row ), 'La retención podría borrar la copia remota de un respaldo subido.' );
	// Sin su ZIP local, se trae del destino verificado por el SHA-256 de su fila, y se restaura igual.
	unlink( $store . '/' . $a );
	$fetched = $B::service_fetch( $a, array( 'drive_cli' => true ) );
	digi_assert( ! is_wp_error( $fetched ) && hash( 'sha256', $a_bytes ) === hash_file( 'sha256', $store . '/' . $a ) && true === $summary_of( $a )['restorable'], 'El subido no volvió del destino verificado por su SHA-256: ' . json_encode( is_wp_error( $fetched ) ? $fetched->get_error_message() : $fetched ) );
	list( $code ) = $restore( $a, 'RESTAURAR' );
	digi_assert( 200 === $code, 'El subido traído del destino no se restauró con RESTAURAR.' );
	// Uno hecho aquí también se envía (la misma acción en las dos superficies: REST y WP-CLI).
	list( $code, $out ) = digi_wp( 'digitalisimo-backups destinations', 'send', array( $own['file'] ), array( 'to' => 'google' ) );
	digi_assert( 0 === $code && 'ok' === $row_of( $own['file'] )['transfer'], 'wp … destinations send no envió un respaldo hecho aquí: ' . implode( "\n", $out ) );
	// M4 de la revisión: cancelar «Enviar a un destino» en cualquier fase deja la fila como estaba y se puede volver a enviar.
	list( , $send_bytes ) = $fresh();
	list( , $to_send ) = $upload( 'digitalisimo-backup-para-enviar.zip', $send_bytes );
	$to_send = $to_send['file'];
	$plain = function( $file ) use ( $row_of ) { return array_intersect_key( (array) $row_of( $file ), array_flip( array( 'transfer', 'transfer_destination', 'transfer_error', 'transfer_job', 'remote' ) ) ); };
	$before = $plain( $to_send );
	foreach ( array( 'preflight', 'deliver' ) as $phase ) {
		Digitalisimo_Backups_Jobs::$phase_runner = function( $now, $job ) use ( $phase ) {
			if ( $phase === $now ) Digitalisimo_Backups_Jobs::cancel( $job['id'] );
			return Digitalisimo_Backups_Backup_Job::run_phase( $now, $job );
		};
		$cancelled = $B::service_send( $to_send, array( 'destination' => 'google', 'drive_cli' => true ) );
		Digitalisimo_Backups_Jobs::$phase_runner = null;
		digi_assert( is_wp_error( $cancelled ) && 0 === count( Digitalisimo_Backups_Jobs::active() ) && $before == $plain( $to_send ) && '' === $call( 'send_problem', $row_of( $to_send ) ), 'Cancelar el envío en «' . $phase . '» no dejó la fila como estaba o no deja volver a enviarla: ' . json_encode( array( is_wp_error( $cancelled ) ? $cancelled->get_error_code() : $cancelled, $plain( $to_send ) ) ) );
	}
	$html = $screen();
	digi_assert( preg_match( '#data-file="' . preg_quote( $to_send, '#' ) . '".*?</tr>#s', $html, $m ) && false !== strpos( $m[0], 'name="action" value="digitalisimo_backups_send"' ), 'Tras cancelar, la fila no ofrece «Enviar a» otra vez.' );
	// Una marca «en camino» que quedó sin trabajo (el proceso murió) no bloquea otro envío.
	$history = (array) get_option( $B::HISTORY );
	foreach ( $history as &$item ) if ( $to_send === $item['file'] ) $item = array_merge( $item, array( 'transfer' => 'pending', 'transfer_destination' => 'google', 'transfer_job' => '0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e' ) );
	unset( $item );
	update_option( $B::HISTORY, $history );
	digi_assert( '' === $call( 'send_problem', $row_of( $to_send ) ), 'Una copia «en camino» sin trabajo bloquea volver a enviar.' );
	// B3 y N5: el SHA-256 entero se calcula una vez al empezar; antes de cada tramo se compara la identidad barata del
	// archivo (inodo, tamaño y fecha) y, al final, el recibo del destino. (1) El archivo crece después de comprobarlo: no
	// sale nada. (2) Cambia con el mismo tamaño y la misma fecha: el recibo lo delata, la copia remota se retira y no se
	// registra. En los dos casos la fila no queda con copia remota y se puede volver a enviar.
	$original = file_get_contents( $store . '/' . $to_send );
	foreach ( array( 'crece' => 'otro inodo, tamaño o fecha', 'misma-huella' => 'durante el envío' ) as $how => $expected ) {
		file_put_contents( $store . '/' . $to_send, $original );
		Digitalisimo_Backups_Jobs::$phase_runner = function( $now, $job ) use ( $store, $to_send, $how ) {
			if ( 'deliver' === $now && empty( $GLOBALS['digi_changed'] ) ) {
				$GLOBALS['digi_changed'] = true;
				$path = $store . '/' . $to_send;
				clearstatcache( true, $path );
				$mtime = filemtime( $path );
				if ( 'crece' === $how ) file_put_contents( $path, 'X', FILE_APPEND );
				else { $h = fopen( $path, 'r+b' ); fseek( $h, 100 ); fwrite( $h, 'X' ); fclose( $h ); touch( $path, $mtime ); }
				clearstatcache( true, $path );
			}
			return Digitalisimo_Backups_Backup_Job::run_phase( $now, $job );
		};
		$GLOBALS['digi_changed'] = false;
		$uploads_before = count( $GLOBALS['drive'] );
		$changed = $B::service_send( $to_send, array( 'destination' => 'google', 'drive_cli' => true ) );
		Digitalisimo_Backups_Jobs::$phase_runner = null;
		digi_assert( is_wp_error( $changed ) && false !== strpos( $changed->get_error_message(), $expected ) && $uploads_before === count( $GLOBALS['drive'] ) && 'ok' !== ( $row_of( $to_send )['transfer'] ?? '' ) && '' === $call( 'send_problem', $row_of( $to_send ) ), 'Un archivo cambiado en el almacén después de comprobarlo (' . $how . ') se envió o no lo detectó lo que debía: ' . json_encode( array( is_wp_error( $changed ) ? $changed->get_error_message() : $changed, $plain( $to_send ), $uploads_before, count( $GLOBALS['drive'] ) ) ) );
	}
	$history = (array) get_option( $B::HISTORY );
	update_option( $B::HISTORY, array_values( array_filter( $history, function( $r ) use ( $to_send ) { return $to_send !== $r['file']; } ) ) );
	unlink( $store . '/' . $to_send );
	// B4: una fila subida nunca cede su copia remota a la retención, aunque traiga un trabajo y la identidad de aquí.
	$forged = array_merge( $row_of( $a ), array( 'file' => 'digitalisimo-backup-example-test-20261101-000000-0a0b0c0d.zip', 'job' => 'trabajo-de-otra-fila', 'installation' => $call( 'installation_stamp' ) ) );
	$forged['remote']['sha256'] = $forged['sha256'];
	digi_assert( false === $call( 'remote_owned', $forged ) && true === $call( 'remote_owned', array_merge( $forged, array( 'origin' => 'created' ) ) ), 'La retención podría borrar la copia remota de una fila subida con trabajo e identidad de aquí (o el control no es propio).' );
	// «Borrar» de su fila: el ZIP, la copia remota y la fila.
	$GLOBALS['http'] = array();
	$query = digi_admin_post( 'delete_backup', $B::ACTION_DELETE, array( 'file' => $a ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && ! is_file( $store . '/' . $a ) && null === $row_of( $a ) && ! isset( $GLOBALS['drive'][ $remote_id ] ), '«Borrar» no quitó del almacén, del destino y de la lista el subido: ' . json_encode( $query ) );

	// (f) «Respaldo reciente válido» y «Restaurar sólo respaldos firmados».
	update_option( $B::HISTORY, array() );
	list( , $other ) = $upload( 'digitalisimo-backup-otro-reciente.zip', $elsewhere( $fresh()[1], 'https://sitio-c.test' ) );
	list( , $mine_unsigned ) = $upload( 'digitalisimo-backup-propio-sin-firma.zip', $fresh()[1] );
	digi_assert( null === Digitalisimo_Backups_Pre_Update::fresh( 24 ), 'Un subido (de otro sitio, o de éste sin firma) contó como respaldo reciente válido: ' . json_encode( Digitalisimo_Backups_Pre_Update::fresh( 24 ) ) );
	digi_assert( false === $row_of( $mine_unsigned['file'] )['upload']['same_site'], 'Un subido sin firma se dio por de este mismo sitio.' );
	Digitalisimo_Backups_Keys::set_passphrase( 'frase de este sitio para la prueba de subidos' );
	list( $signed_name, $signed_bytes ) = $fresh();
	list( $code, $signed ) = $upload( $signed_name, $signed_bytes );
	$recent = Digitalisimo_Backups_Pre_Update::fresh( 24 );
	digi_assert( 200 === $code && 'signed' === $signed['trust'] && true === $row_of( $signed['file'] )['upload']['same_site'] && $recent && $signed['file'] === $recent['file'], 'Un respaldo de este mismo sitio, firmado con su clave y vuelto a subir, no cuenta como reciente válido: ' . json_encode( array( $signed, $recent ) ) );
	digi_assert( false !== strpos( $screen(), 'de este mismo sitio (firmado con su clave)' ), 'La pantalla no dice que el subido es de este mismo sitio.' );
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => false, 'require_signed' => true ) );
	list( $code, $error ) = $restore( $other['file'], 'RESTAURAR' );
	digi_assert( 403 === $code && 'digitalisimo_backups_signed_only' === $error['code'], '«Sólo firmados» no bloqueó un subido sin firma: ' . json_encode( $error ) );
	digi_assert( preg_match( '#id="digitalisimo-backups-restore-\d+"[^>]*>.*?</tr>#s', $screen(), $m ) !== false && false !== strpos( $screen(), 'este respaldo no tiene una firma válida de una clave de este sitio: no se restaurará' ), 'La pantalla no avisa que «sólo firmados» bloqueará el subido sin firma.' );
	list( $code ) = $restore( $signed['file'], 'RESTAURAR' );
	digi_assert( 200 === $code, 'Con «sólo firmados», el subido firmado con la clave de este sitio no se restauró.' );
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => false, 'require_signed' => false ) );
	delete_option( Digitalisimo_Backups_Keys::KEYRING );

	// (g) «Agregar al listado»: un archivo «sin registro» del almacén (copiado por FTP, subido con 1.0.77).
	list( , $loose_bytes ) = $fresh();
	file_put_contents( $store . '/digitalisimo-backup-por-ftp.zip', $loose_bytes );
	$panel = new ReflectionMethod( $B, 'restore_panel' );
	if ( PHP_VERSION_ID < 80100 ) $panel->setAccessible( true );
	ob_start();
	$panel->invoke( null, (array) get_option( $B::HISTORY, array() ) );
	$html = (string) ob_get_clean();
	digi_assert( false !== strpos( $html, 'name="action" value="' . $B::ACTION_REGISTER_LOOSE . '"><input type="hidden" name="file" value="digitalisimo-backup-por-ftp.zip"' ) && false !== strpos( $html, 'Agregar al listado' ) && false !== strpos( $html, 'name="confirmation" placeholder="AGREGAR SIN FIRMA"' ) && false !== strpos( $html, 'No sabemos quién puso este archivo en el almacén' ), '«Restaurar un archivo suelto» no ofrece «Agregar al listado» con su confirmación y su aviso.' );
	list( $code, $error ) = $restore( 'digitalisimo-backup-por-ftp.zip', 'RESTAURAR' );
	digi_assert( 400 === $code && false !== strpos( $error['message'], 'RESTAURAR SIN FIRMA' ), 'Un archivo suelto sin agregar se restauró sin la frase de sin firma.' );
	// M2 de la revisión: su origen no se conoce. Sin confirmación, o con AGREGAR para uno sin firma, no entra.
	foreach ( array( array(), array( 'confirm' => 'AGREGAR' ), array( 'confirm' => 'RESTAURAR' ) ) as $body ) {
		list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-por-ftp.zip/register', $body );
		digi_assert( in_array( $code, array( 400 ), true ) && null === $row_of( 'digitalisimo-backup-por-ftp.zip' ) && ( ! isset( $error['code'] ) || in_array( $error['code'], array( 'digitalisimo_backups_register_confirm', 'rest_missing_callback_param' ), true ) ), 'Un archivo suelto sin firma se agregó sin escribir AGREGAR SIN FIRMA: ' . json_encode( array( $body, $code, $error ) ) );
	}
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-por-ftp.zip/register', array( 'confirm' => 'AGREGAR' ) );
	digi_assert( false !== strpos( (string) ( $error['message'] ?? '' ), 'No sabemos quién puso este archivo' ), 'El rechazo no avisa de que el origen es desconocido: ' . json_encode( $error ) );
	list( $code, $registered ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-por-ftp.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( 201 === $code && true === $registered['listed'] && 'RESTAURAR' === $registered['confirmation'] && 'added' === $registered['origin'] && 'added' === $row_of( 'digitalisimo-backup-por-ftp.zip' )['origin'] && 'rest' === $row_of( 'digitalisimo-backup-por-ftp.zip' )['upload']['via'], 'POST …/register no agregó el archivo suelto a la lista como «Agregado»: ' . json_encode( $registered ) );
	$summary = $summary_of( 'digitalisimo-backup-por-ftp.zip' );
	digi_assert( 'added' === $summary['origin'] && 'Agregado' === $summary['uploaded']['label'], 'GET /backups no dice «Agregado»: ' . json_encode( $summary ) );
	list( , $out ) = digi_wp( 'digitalisimo-backups', 'list', array(), array( 'format' => 'table' ) );
	digi_assert( false !== strpos( implode( "\n", $out ), 'agregado a mano' ), 'wp … list no dice «agregado a mano».' );
	digi_assert( preg_match( '#data-file="digitalisimo-backup-por-ftp\.zip".*?</tr>#s', $screen(), $m ) && false !== strpos( $m[0], '>Agregado</span>' ) && false === strpos( $m[0], '>Subido</span>' ) && false !== strpos( $m[0], 'origen desconocido' ), 'La pantalla no etiqueta «Agregado» (origen desconocido) el archivo agregado a mano.' );
	list( , $csv ) = digi_wp( 'digitalisimo-backups', 'list', array(), array( 'format' => 'csv' ) );
	digi_assert( false === strpos( implode( "\n", $csv ), '"origin"' ), 'wp … list --format=csv añadió la columna origin a las de siempre.' );
	list( $code ) = $restore( 'digitalisimo-backup-por-ftp.zip', 'RESTAURAR' );
	digi_assert( 200 === $code, 'El archivo agregado al listado no se restauró con RESTAURAR (AGREGAR SIN FIRMA sustituye a la frase de sin firma).' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-por-ftp.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( 409 === $code && 'digitalisimo_backups_register_listed' === $error['code'], 'Se agregó dos veces el mismo archivo.' );
	// Una copia del mismo contenido no entra (ya está en la lista) y sigue en el almacén.
	file_put_contents( $store . '/digitalisimo-backup-copia-igual.zip', $loose_bytes );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-copia-igual.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( 409 === $code && 'digitalisimo_backups_register_duplicate' === $error['code'] && is_file( $store . '/digitalisimo-backup-copia-igual.zip' ) && null === $row_of( 'digitalisimo-backup-copia-igual.zip' ), 'Una copia del mismo contenido entró en la lista: ' . json_encode( $error ) );
	// Uno hostil no entra, no se borra y lo dice.
	$hostile = $root . '/hostil.zip';
	file_put_contents( $hostile, $fresh()[1] );
	$zip = new ZipArchive();
	$zip->open( $hostile );
	$zip->addFromString( 'wordpress/wp-load.php', 'contenido cambiado' );
	$zip->close();
	copy( $hostile, $store . '/digitalisimo-backup-hostil.zip' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-hostil.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( 422 === $code && 'digitalisimo_backups_register_invalid' === $error['code'] && false !== strpos( $error['message'], 'no se agregó a la lista' ) && is_file( $store . '/digitalisimo-backup-hostil.zip' ) && null === $row_of( 'digitalisimo-backup-hostil.zip' ), 'Un archivo hostil entró en la lista o se borró: ' . json_encode( $error ) );
	foreach ( array( '../public/wp-load.php', 'index.php', $a ) as $bad ) {
		list( $code ) = digi_rest( 'POST', $ns . '/backups/' . rawurlencode( $bad ) . '/register', array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
		digi_assert( in_array( $code, array( 400, 404 ), true ), 'Se agregó al listado algo que no es un archivo suelto del almacén: ' . $bad );
	}
	// La pantalla (admin-post) y WP-CLI.
	list( , $screen_bytes ) = $fresh();
	file_put_contents( $store . '/digitalisimo-backup-pantalla.zip', $screen_bytes );
	$query = digi_admin_post( 'register_loose_request', $B::ACTION_REGISTER_LOOSE, array( 'file' => 'digitalisimo-backup-pantalla.zip' ) );
	digi_assert( 'error' === ( $query['backup'] ?? '' ) && null === $row_of( 'digitalisimo-backup-pantalla.zip' ) && false !== strpos( rawurldecode( (string) $query['message'] ), 'AGREGAR SIN FIRMA' ), '«Agregar al listado» de la pantalla agregó sin la confirmación escrita: ' . json_encode( $query ) );
	$query = digi_admin_post( 'register_loose_request', $B::ACTION_REGISTER_LOOSE, array( 'file' => 'digitalisimo-backup-pantalla.zip', 'confirmation' => 'agregar  sin firma' ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && 'added' === $row_of( 'digitalisimo-backup-pantalla.zip' )['origin'], '«Agregar al listado» de la pantalla no agregó el archivo: ' . json_encode( $query ) );
	try { digi_admin_post( 'register_loose_request', 'otra-accion', array( 'file' => 'digitalisimo-backup-copia-igual.zip' ) ); throw new RuntimeException( 'FALLA: se agregó al listado con un nonce ajeno.' ); } catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	list( , $cli_bytes ) = $fresh();
	file_put_contents( $store . '/digitalisimo-backup-terminal.zip', $cli_bytes );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'register_backup', array( 'digitalisimo-backup-terminal.zip' ) );
	digi_assert( 1 === $code && null === $row_of( 'digitalisimo-backup-terminal.zip' ), 'wp … register agregó sin --confirm.' );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'register_backup', array( 'digitalisimo-backup-terminal.zip' ), array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), '--confirm="RESTAURAR"' ) && 'cli' === $row_of( 'digitalisimo-backup-terminal.zip' )['upload']['via'], 'wp … register no agregó el archivo: ' . implode( "\n", $out ) );

	// (h) El proceso muere después de mover el archivo al almacén (antes o después de su fila): cerrar otra vez lo termina.
	list( , $crash_bytes ) = $fresh();
	list( $code, $done, $session_id ) = $upload( 'digitalisimo-backup-se-corta.zip', $crash_bytes );
	digi_assert( 200 === $code, 'No se subió el respaldo de la prueba del corte.' );
	$state = $U::read( $session_id );
	$file = $done['file'];
	$placing = array( 'file' => $file, 'sha256' => hash( 'sha256', $crash_bytes ), 'info' => array( 'container' => 'zip', 'verified' => true, 'trust' => 'untrusted', 'signature' => 'unsigned', 'compatible' => true, 'problems' => array(), 'scope' => 'installation', 'multisite' => false, 'created_at' => '2026-11-14T10:15:00+00:00', 'via' => 'rest', 'name' => 'digitalisimo-backup-se-corta.zip', 'user_id' => 7 ) );
	foreach ( array( 'with_row', 'without_row' ) as $when ) {
		if ( 'without_row' === $when ) update_option( $B::HISTORY, array_values( array_filter( (array) get_option( $B::HISTORY ), function( $row ) use ( $file ) { return $row['file'] !== $file; } ) ) );
		$broken = $state;
		unset( $broken['result'] );
		$broken['phase'] = 'validating';
		$broken['attempts'] = 1;
		$broken['placing'] = $placing;
		$U::write( $broken );
		list( $code, $again ) = digi_rest( 'POST', $ns . '/uploads/' . $session_id . '/finish' );
		$row = $row_of( $file );
		digi_assert( 200 === $code && $file === $again['file'] && true === $again['listed'] && $row && 'uploaded' === $row['origin'] && hash( 'sha256', $crash_bytes ) === $row['sha256'] && 1 === count( array_filter( (array) get_option( $B::HISTORY ), function( $r ) use ( $file ) { return $r['file'] === $file; } ) ) && 'done' === $U::read( $session_id )['phase'] && ! isset( $U::read( $session_id )['placing'] ), 'Tras un corte (' . $when . '), cerrar otra vez no terminó la fila: ' . json_encode( array( $code, $again, $row ) ) );
	}

	// (i) Una red: lo subido queda en la lista de la red; uno de un sitio individual se lista y se importa como un sitio de
	// la red (pedido del propietario del 2026-11-16: ya no dice «No se restaura aquí»; ver backups-site-import.php).
	$single = $fresh()[1];
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	update_option( $B::HISTORY, array() );
	list( , $network_bytes ) = $fresh( array( 'network' => true ) );
	list( $code, $network ) = $upload( 'digitalisimo-backup-red.zip', $network_bytes );
	$row = $row_of( $network['file'] );
	digi_assert( 200 === $code && 'network' === $row['scope'] && 'uploaded' === $row['origin'] && true === $summary_of( $network['file'] )['restorable'], 'En una red, lo subido no quedó en la lista de la red: ' . json_encode( array( $network, $row ) ) );
	list( $code ) = $restore( $network['file'], 'RESTAURAR' );
	digi_assert( 400 === $code, 'En una red se restauró sin confirmar el alcance de red.' );
	list( $code, $restored ) = $restore( $network['file'], 'RESTAURAR', array( 'network' => true ) );
	digi_assert( 200 === $code && true === $restored['restored'], 'En una red, el subido no se restauró con RESTAURAR: ' . json_encode( $restored ) );
	list( $code, $single_up ) = $upload( 'digitalisimo-backup-individual.zip', $single );
	$summary = $summary_of( $single_up['file'] );
	digi_assert( 200 === $code, 'En una red, la subida del respaldo de un sitio individual falló: ' . json_encode( $single_up ) );
	digi_assert( 200 === $code && $summary && 'installation' === $row_of( $single_up['file'] )['scope'] && true === $summary['restorable'] && true === $summary['uploaded']['restorable_here'], 'En una red, el subido de un sitio individual no se puede importar: ' . json_encode( $summary ) );
	$html = $screen();
	digi_assert( false === strpos( $html, 'No se restaura aquí' ) && false !== strpos( $html, 'Importar en la red' ) && false !== strpos( $html, 'Como sitio nuevo de la red' ), 'La pantalla no ofrece importar en la red el subido de un sitio individual.' );
	// El sitio principal de la red nunca se reemplaza con el respaldo de un sitio.
	list( $code ) = $restore( $single_up['file'], 'RESTAURAR', array( 'network' => true, 'into' => '1' ) );
	digi_assert( 400 === $code, 'En una red se reemplazó el sitio principal con el respaldo de un sitio individual.' );
	// B2 de la revisión: en una red, «Agregar al listado» escribe en la lista de la red y pide su alcance, como «Traer» o
	// «Enviar»; un administrador de un sitio (sin la capability de la red) no puede.
	list( , $network_loose ) = $fresh( array( 'network' => true ) );
	file_put_contents( $store . '/digitalisimo-backup-suelto-red.zip', $network_loose );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	list( $code_site ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-suelto-red.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA', 'network' => true ) );
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	list( $code_scope, $error ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-suelto-red.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( 403 === $code_site && 400 === $code_scope && 'digitalisimo_backups_scope' === $error['code'] && null === $row_of( 'digitalisimo-backup-suelto-red.zip' ), 'En una red se agregó al listado sin la capability o sin el alcance de red: ' . json_encode( array( $code_site, $code_scope, $error ) ) );
	list( $code, $added ) = digi_rest( 'POST', $ns . '/backups/digitalisimo-backup-suelto-red.zip/register', array( 'confirm' => 'AGREGAR SIN FIRMA', 'network' => true ) );
	digi_assert( 201 === $code && 'network' === $row_of( 'digitalisimo-backup-suelto-red.zip' )['scope'], 'En una red, con el alcance de red, no se agregó al listado: ' . json_encode( $added ) );
	$query = digi_admin_post( 'register_loose_request', $B::ACTION_REGISTER_LOOSE, array( 'file' => 'digitalisimo-backup-no-existe.zip', 'confirmation' => 'AGREGAR SIN FIRMA' ) );
	digi_assert( false === strpos( rawurldecode( (string) ( $query['message'] ?? '' ) ), 'alcance de red' ), 'La pantalla de la red no pasa el alcance de red a «Agregar al listado».' );

	echo "DIGITALÍSIMO Backups: lo subido queda en la lista de respaldos con su fecha, tamaño, tipo y origen; se restaura (completo y por partes) con RESTAURAR atado a su SHA-256, se verifica, se descarga, se envía a un destino y se borra como uno hecho aquí, sin pasar por la retención ni por «reciente válido» salvo que sea de este mismo sitio firmado; «Agregar al listado» pasa los archivos sueltos validados; en una red, la lista es la de la red.\n";
} finally {
	digi_rmtree( $root );
}
