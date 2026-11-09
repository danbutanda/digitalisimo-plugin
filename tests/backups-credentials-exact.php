<?php
/**
 * S4 · los secretos de los destinos se guardan exactamente como se escribieron: el formulario de «Servidores» (con las
 * barras que WordPress añade a $_POST) y el refresh token del retorno de OAuth no pasan por ningún saneado ni recorte,
 * así que comillas, barra invertida, <…>, %xx, espacios al borde y texto no ASCII llegan intactos al almacén y al
 * proveedor. Un campo vacío (o sólo con espacios) conserva lo guardado; NUL, los caracteres de control y más de 8192
 * bytes se rechazan con un mensaje claro que no repite el valor, sin cambiar nada. Los campos que no son secretos
 * (host, usuario, ruta) mantienen su saneado. REST y WP-CLI no reciben secretos de destinos.
 */
require __DIR__ . '/lib/backups-env.php';
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$K = 'Digitalisimo_Backups_Keys';
$GLOBALS['bk_transients'] = array();
$GLOBALS['bk_http'] = array();
$GLOBALS['bk_http_reply'] = array();
function get_current_user_id() { return 1; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bk_transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['bk_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['bk_transients'][ $key ] ); return true; }
function wp_remote_post( $url, $args = array() ) { $GLOBALS['bk_http'][] = array( $url, $args ); return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $GLOBALS['bk_http_reply'] ) ); }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
/** Lo que hace WordPress con $_POST antes de llamar al handler (wp_magic_quotes). */
$slash = function( $value ) use ( &$slash ) { return is_array( $value ) ? array_map( $slash, $value ) : addslashes( (string) $value ); };
$save = function( array $fields ) use ( $B, $slash ) { return bk_post( array( $B, 'save' ), $slash( array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $fields ) ) ); };
$settings = function() use ( $B ) { return bk_call( $B, 'settings' ); };
$secret_values = function( array $settings ) use ( $C ) { return array_intersect_key( $settings, array_flip( $C::FIELDS ) ); };
$fields = array( 'destination' => 'local', 'retention' => '100', 'sftp_host' => '  sftp.example.test <b>x</b> ', 'sftp_port' => '22', 'sftp_user' => ' respaldos ', 'sftp_path' => '/backups', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'google_client_id' => 'cliente-google', 'onedrive_client_id' => 'cliente-onedrive', 'onedrive_folder' => 'Respaldos' );
$secrets = array(
	'sftp_password' => "  O'Brien \"clave\" \\ barra\\\\doble  ",
	'google_client_secret' => '<b>secreto</b> & <script>alert(1)</script>',
	'google_refresh_token' => '1//tok%41%2Fen%zz+más ñ',
	'onedrive_client_secret' => 'a\\\'b\\"c%0A',
	'onedrive_refresh_token' => str_repeat( 'R', 8192 ),
);
$google_sent = function() use ( $B, $settings ) {
	$GLOBALS['bk_http'] = array();
	$GLOBALS['bk_http_reply'] = array( 'access_token' => 'acceso' );
	bk_call( $B, 'token', 'https://oauth2.googleapis.com/token', $settings(), 'google' );
	$body = $GLOBALS['bk_http'][0][1]['body'] ?? array();
	return array( (string) ( $body['client_secret'] ?? '' ), (string) ( $body['refresh_token'] ?? '' ) );
};

// 1. Guardados por el formulario (con barras de WordPress): llegan exactos a los ajustes en memoria, al almacén y al proveedor.
list( $ok, $message ) = $save( $fields + $secrets );
$check( $ok, 'No se guardaron las conexiones: ' . $message );
$check( $secrets === $secret_values( $settings() ), 'Un secreto no se guardó exactamente como se escribió: ' . json_encode( array_diff_assoc( $secret_values( $settings() ), $secrets ) ) );
$check( array( $secrets['google_client_secret'], $secrets['google_refresh_token'] ) === $google_sent(), 'Google no recibió los secretos exactos.' );
$store = $K::get( $C::OPTION, array() );
$key = $C::key();
foreach ( $secrets as $field => $value ) $check( $value === $C::open( $key, $field, $store['items'][ $field ] ?? null, $settings() ), 'El almacén no guarda ' . $field . ' exacto.' );
$check( ! array_intersect_key( (array) get_option( $B::OPTION ), $secrets ), 'Un secreto quedó en los ajustes.' );
// Los campos que no son secretos conservan su saneado.
$s = $settings();
$check( 'sftp.example.test x' === $s['sftp_host'] && 'respaldos' === $s['sftp_user'], 'Los campos que no son secretos perdieron su saneado: ' . json_encode( array( $s['sftp_host'], $s['sftp_user'] ) ) );

// 2. Un campo vacío o sólo con espacios conserva lo guardado.
$blank = array_fill_keys( array_keys( $secrets ), '' );
$blank['sftp_password'] = '   ';
list( $ok ) = $save( $fields + $blank );
$check( $ok && $secrets === $secret_values( $settings() ), 'Un campo vacío o con espacios cambió lo guardado.' );

// 3. NUL, caracteres de control (C0, DEL y C1), más de 8192 bytes o algo que no es texto: mensaje claro, sin el valor y sin cambios.
$before = array( $K::get( $C::OPTION, array() ), get_option( $B::OPTION ) );
$rejected = array(
	array( 'sftp_password', "clave\nsecreta-xyz", 'La contraseña de SFTP no se guardó', 'caracteres de control' ),
	array( 'sftp_password', "clave\tsecreta-xyz", 'La contraseña de SFTP no se guardó', 'caracteres de control' ),
	array( 'google_client_secret', "clave\0secreta-xyz", 'El Client secret de Google Drive no se guardó', 'caracteres de control' ),
	array( 'google_refresh_token', "clave\x7Fsecreta-xyz", 'El Refresh token de Google Drive no se guardó', 'caracteres de control' ),
	array( 'onedrive_client_secret', "clave\u{85}secreta-xyz", 'El Client secret de OneDrive no se guardó', 'caracteres de control' ),
	array( 'onedrive_refresh_token', 'secreta-xyz' . str_repeat( 'R', 8182 ), 'El Refresh token de OneDrive no se guardó', 'pasa de 8192 bytes' ),
	array( 'sftp_password', array( 'secreta-xyz' ), 'La contraseña de SFTP no se guardó', 'no es texto' ),
);
foreach ( $rejected as list( $field, $value, $who, $why ) ) {
	list( $ok, $message, $url ) = $save( $fields + array( $field => $value ) );
	$check( ! $ok && false !== strpos( $message, $who ) && false !== strpos( $message, $why ) && false !== strpos( $message, 'no se cambió nada' ), 'Un ' . $field . ' no válido no se rechazó con un mensaje claro: ' . $message );
	$check( false === strpos( $message . rawurldecode( $url ), 'secreta-xyz' ), 'El mensaje de rechazo repite el valor.' );
	$check( array( $K::get( $C::OPTION, array() ), get_option( $B::OPTION ) ) === $before, 'Un rechazo cambió lo guardado.' );
}
$check( $secrets === $secret_values( $settings() ), 'Tras los rechazos los secretos no siguen como estaban.' );

// 4. El refresh token del retorno de OAuth se guarda exacto; uno con caracteres de control se rechaza sin cambiar nada.
$oauth = function( $token ) use ( $B ) {
	$GLOBALS['bk_transients']['digitalisimo_backup_oauth_1'] = array( 'state' => 'estado', 'provider' => 'onedrive' );
	$GLOBALS['bk_http'] = array();
	$GLOBALS['bk_http_reply'] = array( 'refresh_token' => $token );
	$_GET = array( 'code' => 'codigo', 'state' => 'estado' );
	$_REQUEST = array( 'provider' => 'onedrive' ) + $_GET;
	try { $B::oauth_callback(); }
	catch ( Bk_Redirect $redirect ) { parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $query ); return array( 'ok' === ( $query['backup'] ?? '' ), rawurldecode( $query['message'] ?? '' ) ); }
	finally { $_GET = array(); $_REQUEST = array(); }
	throw new RuntimeException( 'OAuth no redirigió.' );
};
$token = " M.R3_BAY.tok'en\"con\\barra <x> %2F ñ ";
list( $ok, $message ) = $oauth( $token );
$check( $ok && $token === $settings()['onedrive_refresh_token'], 'El refresh token de OAuth no se guardó exacto: ' . $message );
$check( $secrets['onedrive_client_secret'] === ( $GLOBALS['bk_http'][0][1]['body']['client_secret'] ?? null ), 'OAuth no envió el Client secret exacto.' );
$before = array( $K::get( $C::OPTION, array() ), get_option( $B::OPTION ) );
list( $ok, $message ) = $oauth( "tok\nen-secreta-xyz" );
$check( ! $ok && false !== strpos( $message, 'caracteres de control' ) && false === strpos( $message, 'secreta-xyz' ) && array( $K::get( $C::OPTION, array() ), get_option( $B::OPTION ) ) === $before, 'Un refresh token con caracteres de control se guardó o el mensaje lo repite: ' . $message );

bk_cleanup();
echo "DIGITALÍSIMO Backups: los secretos de los destinos se guardan exactamente como se escribieron, con límite y sin caracteres de control.\n";
