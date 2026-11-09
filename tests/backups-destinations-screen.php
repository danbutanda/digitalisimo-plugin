<?php
/**
 * Destinos en el plugin (WordPress simulado, motor real de trabajos y almacén cifrado de S4):
 *   (a) «Guardar conexiones» con todos los tipos: ningún secreto en digitalisimo_backups_settings ni en el database.sql de
 *       un respaldo real; todos cifrados en el almacén; un secreto vacío conserva el guardado; una opción insegura sin
 *       su casilla se rechaza sin cambiar nada;
 *   (b) la pantalla «Servidores» pinta los campos guiados de cada tipo (selects, condiciones, «Probar conexión», OAuth)
 *       sin ningún secreto;
 *   (c) conexiones adicionales con nombre del mismo tipo: secretos cifrados por conexión (el dato adicional impide
 *       moverlos a otra), destino propio en Backups, un trabajo real las usa, y borrar retira sus secretos;
 *   (d) OAuth: Dropbox con PKCE (S256, offline, sin App secret) y pCloud con su región; la vuelta del proveedor sin
 *       `provider` en la URL (como la mandan Google, Microsoft, Dropbox y pCloud) funciona;
 *   (e) «Probar conexión» con lo escrito sin guardarlo, por AJAX y por admin-post, sin secretos en la respuesta;
 *   (f) trabajos: una transferencia S3 interrumpida se reanuda en el siguiente intento subiendo sólo las partes
 *       pendientes, y cancelar un trabajo en «transfer» aborta su subida por partes en el destino;
 *   (g) «Traer al almacén», descarga y borrado de una copia remota del historial, verificada por SHA-256;
 *   (i) el dato adicional ata cada secreto nuevo a su conexión: una escritura directa en la base que pasa FTP a «sin
 *       cifrar» o cambia el endpoint de una conexión adicional deja el secreto sin abrir, con el aviso que la nombra;
 *   (j) REST y WP-CLI: destinos sin secretos y «Probar conexión» de uno guardado; los temporales de un proceso muerto
 *       se retiran pasadas 24 horas;
 *   (h) red: los secretos nuevos viven en sitemeta y la pantalla conserva el contexto de red.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/destination-fakes.php';
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$K = 'Digitalisimo_Backups_Keys';
$GLOBALS['bk_transients'] = array();
$GLOBALS['bk_redirects'] = array();
function get_current_user_id() { return 1; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bk_transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['bk_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['bk_transients'][ $key ] ); return true; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return 'nonce-' . $action === $nonce ? 1 : false; }
function wp_redirect( $url ) { throw new Bk_Redirect( $url ); }
function nocache_headers() {}
class Bk_Json extends Error { public $payload; public $success; public function __construct( $success, $payload ) { parent::__construct( 'json' ); $this->success = $success; $this->payload = $payload; } }
function wp_send_json_success( $data = null ) { throw new Bk_Json( true, $data ); }
function wp_send_json_error( $data = null, $code = null ) { throw new Bk_Json( false, $data ); }
function wp_remote_post( $url, $args = array() ) { return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'refresh_token' => 'refresh-google-oauth', 'access_token' => 'a' ) ) ); }
function wp_remote_retrieve_body( $response ) { return (string) ( $response['body'] ?? '' ); }

$GLOBALS['bk_fail'] = 0;
$case = function( $label, callable $test ) {
	try { $test(); echo "ok ($label)\n"; }
	catch ( Throwable $e ) { $GLOBALS['bk_fail']++; echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; }
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$settings = function() use ( $B ) { return bk_call( $B, 'settings' ); };
$secrets = array( 'sftp_password' => 'Sftp-Clave-Unica-1', 'ftp_password' => 'Ftp-Clave-Unica-2', 'webdav_password' => 'WebDav-Clave-Unica-3', 's3_secret_key' => 'S3-Secreta-Unica-4', 's3compat_secret_key' => 'Compat-Secreta-Unica-5', 'dropbox_refresh_token' => 'Dropbox-Refresh-Unico-6', 'pcloud_client_secret' => 'Pcloud-Secret-Unico-7', 'pcloud_access_token' => 'Pcloud-Token-Unico-8', 'google_client_secret' => 'Google-Secret-Unico-9', 'onedrive_client_secret' => 'OneDrive-Secret-Unico-10' );
$leak = function( $text ) use ( &$secrets ) {
	foreach ( $secrets as $value ) if ( false !== strpos( (string) $text, $value ) || false !== strpos( (string) $text, trim( json_encode( $value ), '"' ) ) ) return $value;
	return null;
};
$fields = array( 'destination' => 'local', 'retention' => '5', 'sftp_host' => 'sftp.example.test', 'sftp_user' => 'u', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'sftp_path' => '/r', 'ftp_host' => 'ftp.example.test', 'ftp_user' => 'u', 'ftp_security' => 'explicit', 'webdav_url' => 'https://nube.ejemplo.test/remote.php/dav/files/ana/', 'webdav_user' => 'ana', 'webdav_path' => '/respaldos', 's3_region' => 'eu-west-1', 's3_bucket' => 'mi-bucket', 's3_access_key' => 'AKIDPRUEBA', 's3compat_provider' => 'other', 's3compat_endpoint' => 'https://minio.ejemplo.test:9443', 's3compat_bucket' => 'compat-bucket', 's3compat_access_key' => 'minioadmin', 's3compat_path' => 'sitio', 'dropbox_app_key' => 'appkey1234', 'pcloud_client_id' => 'cid', 'google_client_id' => 'g', 'onedrive_client_id' => 'o' );
$save = function( array $values ) use ( $B ) { return bk_post( array( $B, 'save' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $values ) ); };
$sql_of = function( $path ) { $zip = new ZipArchive(); $zip->open( $path ); $sql = $zip->getFromName( 'database.sql' ); $zip->close(); return (string) $sql; };

// Servidores simulados: S3 compatible principal y el de una conexión adicional, Dropbox, pCloud y WebDAV.
$compat = new Bk_Fake_S3( 'compat-bucket', 'Compat-Secreta-Unica-5', 'us-east-1' );
$second = new Bk_Fake_S3( 'segundo-bucket', 'Segunda-Secreta-11', 'eu-1' );
$dropbox = new Bk_Fake_Dropbox();
$pcloud = new Bk_Fake_Pcloud();
$webdav = new Bk_Fake_Webdav( 'https://nube.ejemplo.test/remote.php/dav/files/ana/', 'ana', 'WebDav-Clave-Unica-3' );
Digitalisimo_Backups_Http::$transport = function( array $request ) use ( $compat, $second, $dropbox, $pcloud, $webdav ) {
	$host = parse_url( $request['url'], PHP_URL_HOST );
	if ( 'minio.ejemplo.test' === $host ) return $compat( $request );
	if ( 'segundo.ejemplo.test' === $host ) return $second( $request );
	if ( preg_match( '/dropbox(api)?\.com$/', $host ) ) return $dropbox( $request );
	if ( preg_match( '/pcloud\.com$/', $host ) ) return $pcloud( $request );
	if ( 'nube.ejemplo.test' === $host ) return $webdav( $request );
	throw new Exception( 'Servidor no simulado: ' . $host );
};

$case( 'a', function() use ( $B, $C, $check, $leak, $save, $fields, $secrets, $settings, $sql_of ) {
	list( $ok, $message ) = $save( $fields + $secrets );
	$check( $ok, 'No se guardó: ' . $message );
	$raw = get_option( $B::OPTION );
	$check( null === $leak( serialize( $raw ) ), 'digitalisimo_backups_settings guarda un secreto en claro.' );
	$stored = Digitalisimo_Backups_Keys::get( $C::OPTION, array() );
	foreach ( $secrets as $field => $value ) $check( isset( $stored['items'][ $field ] ) && false === strpos( json_encode( $stored ), $value ), 'El secreto ' . $field . ' no está cifrado en el almacén.' );
	foreach ( $secrets as $field => $value ) $check( $value === $settings()[ $field ], 'El secreto ' . $field . ' no se abre igual.' );
	// Un respaldo real: el SQL lleva el almacén cifrado y ningún secreto en claro.
	$path = bk_backup();
	$sql = $sql_of( $path );
	$check( null === $leak( $sql ) && false !== strpos( $sql, $C::OPTION ), 'El database.sql de un respaldo lleva un secreto en claro o no lleva el almacén.' );
	unlink( $path );
	// Un secreto vacío conserva el guardado; los demás campos se actualizan.
	list( $ok ) = $save( array( 'webdav_path' => '/otra' ) + $fields );
	$check( $ok && 'WebDav-Clave-Unica-3' === $settings()['webdav_password'] && '/otra' === $settings()['webdav_path'], 'Un secreto vacío no conservó el guardado.' );
	// Una opción insegura sin su casilla: error y nada cambia.
	$before = get_option( $B::OPTION );
	list( $ok, $message ) = $save( array( 'webdav_url' => 'http://nube.ejemplo.test/' ) + $fields );
	$check( ! $ok && false !== strpos( $message, 'http://' ) && $before === get_option( $B::OPTION ), 'Se guardó una URL WebDAV http:// sin la casilla.' );
	list( $ok ) = $save( array( 'webdav_path' => '/respaldos' ) + $fields );
	$destinations = bk_call( $B, 'manual_destinations', $settings() );
	foreach ( array( 'sftp', 'ftp', 'webdav', 's3', 's3compat', 'dropbox', 'google' => false, 'onedrive' => false ) as $key => $type ) if ( false !== $type ) $check( isset( $destinations[ $type ] ), 'El destino ' . $type . ' no aparece listo en Backups.' );
	$check( isset( $destinations['pcloud'] ) && ! isset( $destinations['google'] ) && ! isset( $destinations['onedrive'] ), 'pCloud con su token no aparece, o Google y OneDrive sin conectar aparecen como listos.' );
} );

$case( 'b', function() use ( $B, $check, $leak, $settings ) {
	ob_start();
	try { bk_call( $B, 'servers_panel', $settings(), array() ); } finally { $html = ob_get_clean(); }
	$check( null === $leak( $html ), 'La pantalla de Servidores muestra un secreto: ' . $leak( $html ) );
	foreach ( array( 's3_region', 's3_style', 's3_storage_class', 's3compat_provider', 's3compat_do_region', 's3compat_wasabi_region', 's3compat_jurisdiction', 'ftp_security', 'ftp_passive', 'sftp_auth', 'pcloud_host' ) as $select ) $check( false !== strpos( $html, '<select id="' . $select . '" name="digitalisimo_backups[' . $select . ']">' ), 'El campo ' . $select . ' no es un desplegable guiado.' );
	foreach ( array( 'sftp', 'ftp', 'webdav', 'google', 'onedrive', 'dropbox', 's3', 's3compat', 'pcloud' ) as $type ) $check( false !== strpos( $html, 'name="digitalisimo_backups_test" value="' . $type . '"' ), 'Falta «Probar conexión» de ' . $type . '.' );
	foreach ( array( 'google', 'onedrive', 'dropbox', 'pcloud' ) as $type ) $check( false !== strpos( $html, 'provider=' . $type ), 'Falta el botón de conectar ' . $type . '.' );
	$check( false !== strpos( $html, 'data-show="ftp_security=explicit|implicit"' ) && false !== strpos( $html, 'data-show="s3compat_provider=digitalocean"' ) && false !== strpos( $html, 'data-show="sftp_auth=key"' ), 'Los campos dependientes no declaran su condición.' );
	$check( substr_count( $html, 'placeholder="Guardado · escribe sólo para reemplazar"' ) >= 10 && false !== strpos( $html, 'Añadir otra conexión' ), 'La pantalla no anuncia los secretos guardados o no ofrece otra conexión.' );
	$check( false !== strpos( $html, 'digitalisimo-backups-warning' ) && false !== strpos( $html, 'FTP sin cifrar (inseguro)' ), 'Las opciones inseguras no llevan advertencia.' );
} );

$GLOBALS['bk_connection'] = '';
$case( 'c', function() use ( $B, $C, $K, $check, $leak, $second, $settings ) {
	$post = array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE_CONNECTION, 'connection_type' => 's3compat', 'connection_name' => 'Segundo MinIO', 'digitalisimo_backups_connection' => array( 's3compat_provider' => 'other', 's3compat_endpoint' => 'https://segundo.ejemplo.test', 's3compat_signing_region' => 'eu-1', 's3compat_bucket' => 'segundo-bucket', 's3compat_access_key' => 'segunda', 's3compat_secret_key' => 'Segunda-Secreta-11', 's3compat_path' => 'otra' ) );
	list( $ok, $message ) = bk_post( array( $B, 'save_connection' ), $post );
	$check( $ok, 'No se creó la conexión: ' . $message );
	$connections = Digitalisimo_Backups_Destinations::connections();
	$id = (string) key( $connections );
	$GLOBALS['bk_connection'] = $id;
	$check( Digitalisimo_Backups_Destinations::is_connection_id( $id ) && 's3compat' === $connections[ $id ]['type'] && 'Segundo MinIO' === $connections[ $id ]['name'], 'La conexión no quedó registrada: ' . json_encode( $connections ) );
	$check( false === strpos( serialize( get_option( Digitalisimo_Backups_Destinations::CONNECTIONS ) ), 'Segunda-Secreta-11' ), 'La fila de la conexión guarda su secreto en claro.' );
	$store = $K::get( $C::OPTION, array() );
	$check( isset( $store['items'][ $id . '/s3compat_secret_key' ] ) && 'Segunda-Secreta-11' === bk_call( $B, 'connection_settings', $id )['s3compat_secret_key'], 'El secreto de la conexión no va cifrado por conexión.' );
	$check( 'Compat-Secreta-Unica-5' === $settings()['s3compat_secret_key'], 'La conexión adicional pisó la principal.' );
	// El dato adicional ata el valor a su conexión: movido a la principal no abre.
	$moved = $store;
	$moved['items']['s3compat_secret_key'] = $store['items'][ $id . '/s3compat_secret_key' ];
	$K::put( $C::OPTION, $moved );
	$check( ! isset( $C::read( bk_call( $B, 'credential_settings' ) )['s3compat_secret_key'] ), 'Un secreto de una conexión se abrió en otra.' );
	$K::put( $C::OPTION, $store );
	$destinations = bk_call( $B, 'manual_destinations', $settings() );
	$check( 'Segundo MinIO' === ( $destinations[ $id ] ?? '' ) && 'Segundo MinIO' === $B::destination_name( $id ), 'La conexión no aparece como destino con su nombre.' );
	// Un trabajo real hacia la conexión adicional usa su bucket y sus credenciales.
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => $id, 'retention' => 'none', 'via' => 'test' ) ) );
	for ( $i = 0; $i < 20; $i++ ) { Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX ); $job = Digitalisimo_Backups_Jobs::get( $queued['job']['id'] ); if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break; }
	$check( 'success' === $job['status'], 'El trabajo hacia la conexión adicional no terminó: ' . json_encode( $job['error'] ) );
	$key = 'otra/' . basename( $job['spec']['file'] );
	$check( isset( $second->objects[ $key ] ) && hash_file( 'sha256', bk_storage() . '/' . basename( $job['spec']['file'] ) ) === hash( 'sha256', $second->objects[ $key ] ), 'El respaldo no llegó al bucket de la conexión adicional.' );
	$row = bk_call( $B, 'history_item', basename( $job['spec']['file'] ) );
	$check( $id === $row['destination'] && $id === $row['remote']['connection'] && 'ok' === $row['transfer'] && null === $leak( json_encode( $row ) ), 'El historial no registra la conexión o lleva un secreto: ' . json_encode( $row ) );
	// No se borra mientras es el destino de una automatización; después sí, con sus secretos.
	update_option( $B::POLICIES, array( array( 'id' => 'p1', 'label' => '', 'type' => 'full', 'schedule' => 'weekly', 'destination' => $id, 'retain' => 4, 'enabled' => true ) ) );
	list( $ok, $message ) = bk_post( array( $B, 'delete_connection' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE_CONNECTION, 'connection_id' => $id ) );
	$check( ! $ok && false !== strpos( $message, 'automatización' ), 'Se borró la conexión de una automatización.' );
	update_option( $B::POLICIES, array() );
	// Otra conexión del mismo tipo, para borrarla.
	list( $ok ) = bk_post( array( $B, 'save_connection' ), array( 'connection_name' => 'Para borrar' ) + $post );
	$other = (string) key( array_diff_key( Digitalisimo_Backups_Destinations::connections(), array( $id => 1 ) ) );
	list( $ok, $message ) = bk_post( array( $B, 'delete_connection' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE_CONNECTION, 'connection_id' => $other ) );
	$check( $ok && ! isset( Digitalisimo_Backups_Destinations::connections()[ $other ] ) && ! isset( $K::get( $C::OPTION, array() )['items'][ $other . '/s3compat_secret_key' ] ), 'Borrar la conexión no retiró su fila y su secreto.' );
	$check( ! Digitalisimo_Backups_Destinations::exists( $other ) && 'local' === Digitalisimo_Backups_Jobs::spec( array( 'destination' => $other ) )['destination'], 'Una conexión borrada se sigue aceptando como destino.' );
} );

$case( 'd', function() use ( $B, $check, $dropbox, $settings ) {
	// Dropbox: inicio con PKCE.
	$_GET = array( 'provider' => 'dropbox', '_wpnonce' => 'nonce-' . $B::ACTION_OAUTH );
	$_REQUEST = $_GET; $_POST = $_GET;
	try { $B::oauth_callback(); throw new LogicException( 'No redirigió a Dropbox.' ); } catch ( Bk_Redirect $redirect ) { $url = $redirect->url; }
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
	$saved = $GLOBALS['bk_transients']['digitalisimo_backup_oauth_1'];
	$check( 0 === strpos( $url, 'https://www.dropbox.com/oauth2/authorize?' ) && 'S256' === $query['code_challenge_method'] && 'offline' === $query['token_access_type'] && 'appkey1234' === $query['client_id'] && ! isset( $query['client_secret'] ), 'La autorización de Dropbox no usa PKCE S256 offline: ' . $url );
	$check( Digitalisimo_Backups_Destination_Dropbox::code_challenge( $saved['verifier'] ) === $query['code_challenge'] && $saved['state'] === $query['state'], 'El verificador guardado no corresponde al desafío.' );
	// Vuelta del proveedor: sólo code y state (sin provider).
	$dropbox->faults['challenge'] = $query['code_challenge'];
	$_GET = array( 'code' => 'codigo-dropbox', 'state' => $saved['state'] );
	$_REQUEST = $_GET; $_POST = array();
	try { $B::oauth_callback(); } catch ( Bk_Redirect $redirect ) { parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $back ); }
	$check( 'ok' === $back['backup'] && 'refresh-dropbox-0123' === $settings()['dropbox_refresh_token'] && ! isset( $GLOBALS['bk_transients']['digitalisimo_backup_oauth_1'] ), 'Dropbox no quedó conectado: ' . rawurldecode( $back['message'] ?? '' ) );
	$check( false === strpos( serialize( get_option( $B::OPTION ) ), 'refresh-dropbox-0123' ), 'El refresh token de Dropbox quedó en claro en los ajustes.' );
	// pCloud: región que indica pCloud al volver.
	bk_post( array( $B, 'save' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => array( 'pcloud_client_id' => 'cid', 'pcloud_client_secret' => 'csecret' ) + array_intersect_key( $settings(), array_flip( array( 'sftp_host', 'sftp_user', 'sftp_fingerprint', 'sftp_path', 'ftp_host', 'ftp_user', 'webdav_url', 'webdav_user', 'webdav_path', 's3_region', 's3_bucket', 's3_access_key', 's3compat_provider', 's3compat_endpoint', 's3compat_bucket', 's3compat_access_key', 's3compat_path', 'dropbox_app_key', 'google_client_id', 'onedrive_client_id' ) ) ) ) );
	$GLOBALS['bk_transients']['digitalisimo_backup_oauth_1'] = array( 'state' => 'estado-pcloud', 'provider' => 'pcloud', 'connection' => '' );
	$_GET = array( 'code' => 'codigo-pcloud', 'state' => 'estado-pcloud', 'hostname' => 'eapi.pcloud.com', 'locationid' => '2' );
	$_REQUEST = $_GET;
	try { $B::oauth_callback(); } catch ( Bk_Redirect $redirect ) { parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $back ); }
	$check( 'ok' === $back['backup'] && 'pcloud-token-1' === $settings()['pcloud_access_token'] && 'eapi.pcloud.com' === $settings()['pcloud_host'], 'pCloud no quedó conectado en su región: ' . rawurldecode( $back['message'] ?? '' ) );
	// Google sin `provider` en la vuelta (antes respondía «Proveedor no válido»).
	$GLOBALS['bk_transients']['digitalisimo_backup_oauth_1'] = array( 'state' => 'estado-google', 'provider' => 'google', 'connection' => '' );
	$_GET = array( 'code' => 'codigo-google', 'state' => 'estado-google' );
	$_REQUEST = $_GET;
	try { $B::oauth_callback(); } catch ( Bk_Redirect $redirect ) { parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $back ); }
	$check( 'ok' === $back['backup'] && 'refresh-google-oauth' === $settings()['google_refresh_token'], 'Google no se conectó al volver sin provider: ' . rawurldecode( $back['message'] ?? '' ) );
	$_GET = array(); $_REQUEST = array();
} );

$case( 'e', function() use ( $B, $check, $leak, $webdav, $settings ) {
	$before = get_option( $B::OPTION );
	$store = Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Credentials::OPTION, array() );
	$result = $B::service_test_destination( 'webdav', array( 'webdav_url' => 'https://nube.ejemplo.test/remote.php/dav/files/ana/', 'webdav_user' => 'ana', 'webdav_password' => '', 'webdav_path' => '/prueba-conexion' ) );
	$check( $result['ok'] && count( $result['steps'] ) >= 4 && ! array_filter( array_keys( $webdav->files ), function( $path ) { return 0 === strpos( $path, 'prueba-conexion/' ); } ), 'Probar WebDAV con la contraseña guardada falló o dejó archivos: ' . $result['message'] );
	$check( $before === get_option( $B::OPTION ) && $store === Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Credentials::OPTION, array() ), 'Probar la conexión guardó algo.' );
	$result = $B::service_test_destination( 'webdav', array( 'webdav_url' => 'https://nube.ejemplo.test/remote.php/dav/files/ana/', 'webdav_user' => 'ana', 'webdav_password' => 'Contraseña-Mala-99', 'webdav_path' => '/x' ) );
	$check( ! $result['ok'] && false !== strpos( $result['message'], 'rechazó el usuario' ) && false === strpos( $result['message'], 'Contraseña-Mala-99' ), 'La prueba con otra contraseña no falló con un mensaje limpio: ' . $result['message'] );
	// Por AJAX (como el botón con JavaScript): JSON sin secretos.
	$_POST = array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups_test' => 's3compat', 'digitalisimo_backups' => array( 's3compat_secret_key' => '' ) + array_intersect_key( $settings(), array_flip( array( 's3compat_provider', 's3compat_endpoint', 's3compat_bucket', 's3compat_access_key', 's3compat_path' ) ) ) );
	try { $B::ajax_test_destination(); throw new LogicException( 'El AJAX no respondió.' ); } catch ( Bk_Json $json ) { $check( $json->success && null === $leak( json_encode( $json->payload ) ), 'El AJAX de prueba no fue correcto o lleva un secreto: ' . json_encode( $json->payload ) ); }
	$_POST['_wpnonce'] = 'otro';
	try { $B::ajax_test_destination(); } catch ( Bk_Json $json ) { $check( ! $json->success && false !== strpos( $json->payload['message'], 'caducó' ), 'El AJAX aceptó un nonce falso.' ); }
	// Sin JavaScript: admin-post y vuelta con el resultado.
	list( $ok, $message ) = bk_post( array( $B, 'test_destination' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups_test' => 'dropbox', 'digitalisimo_backups' => array( 'dropbox_app_key' => 'appkey1234', 'dropbox_folder' => '/Prueba' ) ) );
	$check( $ok && false !== strpos( $message, 'Dropbox' ), 'La prueba sin JavaScript no funcionó: ' . $message );
	$_POST = array();
} );

$case( 'f', function() use ( $B, $check, $compat ) {
	// Un respaldo de más de 8 MiB (datos aleatorios, no se comprimen) para que S3 use varias partes.
	file_put_contents( ABSPATH . 'wp-content/uploads/grande.bin', random_bytes( 3 * 8388608 + 1000 ) );
	$filters = $GLOBALS['bk_filters'];
	$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
	try {
		$compat->faults['fail_part'] = 2;
		$compat->log = array();
		$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 's3compat', 'retention' => 'none', 'via' => 'test' ) ) );
		$id = $queued['job']['id'];
		$phases = array();
		for ( $i = 0; $i < 30; $i++ ) { Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX ); $job = Digitalisimo_Backups_Jobs::get( $id ); $phases[] = $job['phase'] . ':' . $job['status']; if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break; }
		$check( 'success' === $job['status'] && 1 === (int) ( $job['attempts']['transfer'] ?? 0 ), 'La transferencia interrumpida no terminó tras un reintento: ' . json_encode( array( $job['status'], $job['attempts'], $job['error'] ) ) );
		$parts = array_map( function( $entry ) { return $entry['query']['partNumber']; }, array_values( array_filter( $compat->log, function( $entry ) { return 'PUT' === $entry['method'] && isset( $entry['query']['partNumber'] ) && ! isset( $entry['headers']['x-amz-copy-source'] ); } ) ) );
		$check( array( '1', '2', '2', '3', '4' ) === $parts, 'El reintento no subió sólo las partes pendientes: ' . json_encode( $parts ) );
		$check( 1 === count( array_filter( $compat->log, function( $entry ) { return 'POST' === $entry['method'] && array_key_exists( 'uploads', $entry['query'] ); } ) ), 'El reintento empezó otra subida por partes.' );
		$file = basename( $job['spec']['file'] );
		$check( hash_file( 'sha256', bk_storage() . '/' . $file ) === hash( 'sha256', $compat->objects[ 'sitio/' . $file ] ) && ! $compat->uploads, 'La copia publicada no es idéntica o quedó una subida abierta.' );
		$GLOBALS['bk_f_file'] = $file;
		// Cancelar un trabajo que espera para repetir «transfer»: su subida por partes se aborta en el destino.
		$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 3600;
		$compat->faults['fail_part'] = 3;
		$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'cancelar', 'destination' => 's3compat', 'retention' => 'none', 'via' => 'test' ) ) );
		for ( $i = 0; $i < 20; $i++ ) { Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX ); $job = Digitalisimo_Backups_Jobs::get( $queued['job']['id'] ); if ( 'transfer' === $job['phase'] && 'queued' === $job['status'] ) break; }
		$check( 'transfer' === $job['phase'] && 1 === count( $compat->uploads ), 'No quedó una transferencia a medias que cancelar: ' . json_encode( array( $job['phase'], $job['status'], count( $compat->uploads ) ) ) );
		$result = Digitalisimo_Backups_Jobs::cancel( $job['id'] );
		$job = Digitalisimo_Backups_Jobs::get( $job['id'] );
		$check( $result['ok'] && 'cancelled' === $job['status'] && ! $compat->uploads, 'Cancelar no abortó la subida por partes en el destino.' );
		$check( 'cancelled' === bk_call( $B, 'history_item', basename( $job['spec']['file'] ) )['transfer'], 'El historial no marca la copia cancelada.' );
		// Cancelar mientras sube (la petición llega con la parte 2 en vuelo): se detiene antes de la siguiente parte.
		$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
		unset( $compat->faults['fail_part'] );
		$transport = Digitalisimo_Backups_Http::$transport;
		Digitalisimo_Backups_Http::$transport = function( array $request ) use ( $transport ) {
			if ( 'PUT' === $request['method'] && false !== strpos( $request['url'], 'partNumber=2&' ) && '' !== Digitalisimo_Backups_Jobs::current_id() ) Digitalisimo_Backups_Jobs::cancel( Digitalisimo_Backups_Jobs::current_id() );
			return $transport( $request );
		};
		try {
			$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'cancelar-en-vuelo', 'destination' => 's3compat', 'retention' => 'none', 'via' => 'test' ) ) );
			$compat->log = array();
			for ( $i = 0; $i < 20; $i++ ) { Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX ); $job = Digitalisimo_Backups_Jobs::get( $queued['job']['id'] ); if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break; }
		} finally {
			Digitalisimo_Backups_Http::$transport = $transport;
		}
		$parts = array_map( function( $entry ) { return $entry['query']['partNumber']; }, array_values( array_filter( $compat->log, function( $entry ) { return 'PUT' === $entry['method'] && isset( $entry['query']['partNumber'] ); } ) ) );
		$check( 'cancelled' === $job['status'] && array( '1', '2' ) === $parts && ! $compat->uploads && ! array_filter( array_keys( $compat->objects ), function( $key ) { return false !== strpos( $key, 'cancelar' ) || false !== strpos( $key, '.part-' ); } ), 'Cancelar durante la subida no la detuvo en la siguiente parte o no abortó el multipart: ' . json_encode( array( $job['status'], $parts, count( $compat->uploads ) ) ) );
	} finally {
		$GLOBALS['bk_filters'] = $filters;
		@unlink( ABSPATH . 'wp-content/uploads/grande.bin' );
	}
} );

$case( 'g', function() use ( $B, $check, $compat ) {
	$file = $GLOBALS['bk_f_file'];
	$local = bk_storage() . '/' . $file;
	$sha = hash_file( 'sha256', $local );
	unlink( $local );
	$result = $B::service_fetch( $file );
	$check( ! is_wp_error( $result ) && $result['fetched'] && $sha === hash_file( 'sha256', $local ), 'Traer al almacén no dejó la copia verificada: ' . json_encode( is_wp_error( $result ) ? $result->get_error_message() : $result ) );
	$check( ! glob( bk_storage() . '/' . $file . '.part-*' ), 'Traer al almacén dejó un temporal.' );
	// Una copia remota alterada no se trae.
	unlink( $local );
	$key = 'sitio/' . $file;
	$good = $compat->objects[ $key ];
	$compat->objects[ $key ][100] = chr( ord( $compat->objects[ $key ][100] ) ^ 1 );
	$result = $B::service_fetch( $file );
	$check( is_wp_error( $result ) && false !== strpos( $result->get_error_message(), 'SHA-256' ) && ! is_file( $local ) && ! glob( bk_storage() . '/' . $file . '.part-*' ), 'Se trajo una copia remota alterada.' );
	$compat->objects[ $key ] = $good;
	// Descarga al navegador: temporal verificado y streaming.
	// En la prueba ya se imprimió texto: las cabeceras de la descarga avisarían «headers already sent».
	set_error_handler( function( $number, $text ) { return false !== strpos( $text, 'Cannot modify header information' ); } );
	ob_start();
	try { bk_call( $B, 'download_remote', bk_call( $B, 'history_item', $file ) ); } finally { $out = ob_get_clean(); restore_error_handler(); }
	$check( $sha === hash( 'sha256', $out ), 'La descarga remota no es el respaldo: ' . strlen( $out ) . ' bytes, empieza por ' . json_encode( substr( $out, 0, 120 ) ) );
	$check( ! glob( bk_storage() . '/.download-*' ), 'La descarga remota dejó el temporal: ' . json_encode( glob( bk_storage() . '/.download-*' ) ) );
	// Borrar la copia remota por el contrato.
	bk_call( $B, 'delete_remote', bk_call( $B, 'history_item', $file ) );
	$check( ! isset( $compat->objects[ $key ] ), 'delete_remote no borró la copia del destino.' );
} );

$case( 'i', function() use ( $B, $check, $settings ) {
	// Una escritura directa en la base que pasa FTP a «sin cifrar»: la contraseña ya no se abre (no viaja en claro).
	$saved = get_option( $B::OPTION );
	$check( 'Ftp-Clave-Unica-2' === $settings()['ftp_password'], 'La contraseña de FTP no estaba guardada para el caso.' );
	update_option( $B::OPTION, array( 'ftp_security' => 'none' ) + $saved );
	$check( '' === $settings()['ftp_password'], 'Con el cifrado FTP cambiado en la base, la contraseña se siguió abriendo.' );
	$problem = $B::destination_problem( 'ftp' );
	$check( false !== strpos( $problem, 'vuelve a escribir la contraseña de FTP' ), 'El destino FTP no pide volver a escribir la contraseña: ' . $problem );
	try { $B::job_transfer( __FILE__, 'ftp' ); throw new LogicException( 'La transferencia FTP no falló.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'contraseña de FTP' ), 'El trabajo no falla de forma permanente con el motivo: ' . $e->getMessage() ); }
	update_option( $B::OPTION, $saved );
	$check( 'Ftp-Clave-Unica-2' === $settings()['ftp_password'], 'Al volver el cifrado, la contraseña no volvió a abrirse.' );
	// Lo mismo con el servidor de una conexión adicional: el aviso nombra la conexión.
	$id = $GLOBALS['bk_connection'];
	$connections = get_option( Digitalisimo_Backups_Destinations::CONNECTIONS );
	$moved = $connections;
	$moved[ $id ]['settings']['s3compat_endpoint'] = 'https://atacante.example';
	update_option( Digitalisimo_Backups_Destinations::CONNECTIONS, $moved );
	$check( '' === bk_call( $B, 'connection_settings', $id )['s3compat_secret_key'], 'La Secret key de la conexión se abrió con otro endpoint.' );
	$problem = $B::destination_problem( $id );
	$check( false !== strpos( $problem, '«Segundo MinIO»' ) && false !== strpos( $problem, 'Secret key' ), 'El aviso no nombra la conexión adicional: ' . $problem );
	$status = $B::service_status();
	$check( 'needs_reentry' === $status['credentials'] && false !== strpos( $status['credentials_notice'], 'Segundo MinIO' ), 'status no avisa de la conexión adicional que hay que volver a escribir.' );
	update_option( Digitalisimo_Backups_Destinations::CONNECTIONS, $connections );
	$check( 'Segunda-Secreta-11' === bk_call( $B, 'connection_settings', $id )['s3compat_secret_key'], 'Al volver el endpoint, la Secret key no volvió a abrirse.' );
} );

$case( 'j', function() use ( $B, $check, $leak ) {
	// REST y WP-CLI usan los mismos casos de uso: destinos y su estado sin secretos, y «Probar conexión» de uno guardado.
	$rows = $B::service_destinations();
	$check( ! is_wp_error( $rows ) && null === $leak( json_encode( $rows ) ), 'service_destinations falló o lleva un secreto.' );
	$ids = array_column( $rows, 'id' );
	foreach ( array( 'sftp', 'ftp', 'webdav', 'google', 'onedrive', 'dropbox', 's3', 's3compat', 'pcloud', $GLOBALS['bk_connection'] ) as $id ) $check( in_array( $id, $ids, true ), 'service_destinations no lista ' . $id . '.' );
	$test = $B::service_test_saved( 's3compat' );
	$check( ! is_wp_error( $test ) && $test['ok'], 'service_test_saved no probó la conexión guardada.' );
	$missing = $B::service_test_saved( 'ftp-00000000' );
	$check( is_wp_error( $missing ) && 404 === ( $missing->get_error_data()['status'] ?? 0 ), 'Probar una conexión que no existe no responde 404.' );
	// Los temporales de los destinos que deja un proceso muerto se retiran pasadas 24 horas.
	$old = array( '.verify-AbC123', '.download-Zz9Yy8', '.sftp-key-Qq1Ww2', 'digitalisimo-backup-x-20261109-120000-abcd1234.zip.part-0123456789ab' );
	foreach ( $old as $name ) { file_put_contents( bk_storage() . '/' . $name, 'resto' ); touch( bk_storage() . '/' . $name, time() - 90000 ); }
	file_put_contents( bk_storage() . '/.verify-Nuevo1', 'reciente' );
	Digitalisimo_Backups_Jobs::cleanup_orphans();
	$check( ! array_filter( $old, function( $name ) { return file_exists( bk_storage() . '/' . $name ); } ) && is_file( bk_storage() . '/.verify-Nuevo1' ), 'La limpieza no retiró los temporales viejos de los destinos o retiró uno reciente.' );
	unlink( bk_storage() . '/.verify-Nuevo1' );
} );

$case( 'h', function() use ( $B, $C, $check, $fields, $secrets, $save ) {
	$GLOBALS['bk_multisite'] = true;
	$_REQUEST['digitalisimo_backups_network'] = '1';
	try {
		list( $ok ) = $save( $fields + $secrets );
		$check( $ok && isset( $GLOBALS['bk_site_options'][ $C::OPTION ]['items']['ftp_password'] ) && isset( $GLOBALS['bk_site_options'][ $C::OPTION ]['items']['pcloud_access_token'] ), 'En una red los secretos nuevos no viven en sitemeta.' );
		$_REQUEST['digitalisimo_backups_network'] = '1';
		ob_start();
		try { bk_call( $B, 'servers_panel', bk_call( $B, 'settings' ), array() ); } finally { $html = ob_get_clean(); }
		$check( substr_count( $html, '<input type="hidden" name="digitalisimo_backups_network" value="1">' ) >= 2, 'Los formularios de la red no conservan el contexto de red.' );
	} finally {
		$GLOBALS['bk_multisite'] = false;
		unset( $_REQUEST['digitalisimo_backups_network'] );
	}
} );

bk_cleanup();
if ( $GLOBALS['bk_fail'] ) { echo 'FALLAN ' . $GLOBALS['bk_fail'] . " casos de la pantalla y los trabajos de destinos.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: destinos guardados cifrados, pantalla guiada, conexiones adicionales, OAuth con PKCE, «Probar conexión», transferencia reanudable y copias remotas verificadas.\n";
