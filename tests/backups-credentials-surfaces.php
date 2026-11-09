<?php
/**
 * S4 · credenciales de los destinos en REST y WP-CLI (caso «i» del encargo): con las credenciales guardadas por la
 * pantalla, `GET /status` y `wp digitalisimo-backups status` (tabla y JSON) dicen que están cifradas sin dar ningún
 * valor; si no se abren con las claves de este wp-config.php, las dos superficies lo dicen con qué hacer, y crear un
 * respaldo hacia ese destino (`POST /backups`, `wp … create --destination`) responde con el mismo motivo. Ninguna
 * respuesta lleva un secreto, la clave de las credenciales ni el almacén cifrado.
 */
$root = sys_get_temp_dir() . '/digitalisimo-cred-surfaces-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
// Lo que el guardado de la pantalla necesita y el entorno de cobertura no trae.
function absint( $value ) { return abs( (int) $value ); }
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$K = 'Digitalisimo_Backups_Keys';
$ns = 'digitalisimo-backups/v1';
$cmd = 'digitalisimo-backups';
$secrets = array( 'sftp_password' => 'Sftp-Superficies.9d3e1a', 'google_client_secret' => 'GOCSPX-superficies-77c2', 'google_refresh_token' => '1//refresh-superficies-04ab', 'onedrive_client_secret' => 'onedrive~superficies~e5f1', 'onedrive_refresh_token' => 'M.R3_BAY.superficies-6a90' );
$fields = array( 'destination' => 'local', 'retention' => '100', 'sftp_host' => 'sftp.example.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_path' => '/backups', 'sftp_fingerprint' => str_repeat( 'B', 40 ), 'google_client_id' => 'cliente-google', 'onedrive_client_id' => 'cliente-onedrive', 'onedrive_folder' => 'Respaldos' );
/** Lo que nunca debe aparecer en una respuesta: los secretos, la clave de las credenciales y los elementos cifrados. */
$forbidden = function() use ( $secrets, $C, $K ) {
	$key = $C::key();
	$out = array_values( $secrets );
	$out[] = bin2hex( $key );
	$out[] = $K::b64url_encode( $key );
	foreach ( (array) ( $K::get( $C::OPTION, array() )['items'] ?? array() ) as $item ) $out[] = $item['c'];
	return $out;
};
$clean = function( $label, $text ) use ( $forbidden ) {
	foreach ( $forbidden() as $value ) digi_assert( false === strpos( (string) $text, $value ) && false === strpos( (string) $text, trim( json_encode( $value ), '"' ) ), $label . ' lleva un secreto, la clave o el almacén cifrado.' );
};

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();

	// Guardado por el formulario de la pantalla (admin-post): los secretos sólo cifrados.
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => $fields + $secrets ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardaron las conexiones: ' . json_encode( $query ) );
	$clean( 'Las opciones del sitio', serialize( array_diff_key( $GLOBALS['digi']['options'], array( $C::OPTION => true ) ) ) );
	digi_assert( 5 === count( $K::get( $C::OPTION, array() )['items'] ?? array() ), 'El almacén cifrado no tiene los cinco secretos.' );

	// Cifradas: REST y WP-CLI lo dicen sin ningún valor y los tres destinos están conectados.
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && 'encrypted' === $status['credentials'] && '' === $status['credentials_notice'] && isset( $status['destinations']['sftp'], $status['destinations']['google'], $status['destinations']['onedrive'] ), 'GET /status no informa las credenciales cifradas: ' . json_encode( $status ) );
	$clean( 'GET /status', json_encode( $status ) );
	list( $code, $out ) = digi_wp( $cmd, 'status' );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), '"campo":"credentials","valor":"encrypted"' ), 'wp … status no informa las credenciales cifradas: ' . implode( "\n", $out ) );
	$clean( 'wp … status', implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( $cmd, 'status', array(), array( 'format' => 'json' ) );
	digi_assert( 0 === $code && 'encrypted' === ( json_decode( $out[0], true )['credentials'] ?? '' ), 'wp … status --format=json no informa las credenciales cifradas.' );
	$clean( 'wp … status --format=json', implode( "\n", $out ) );

	// Las claves de wp-config.php cambiaron (el almacén está cifrado con otras): «vuelve a escribir» en las dos superficies.
	$other = $C::key_from( 'salts-antiguas-auth-0123456789abcdef', 'salts-antiguas-secure-0123456789abcdef' );
	$items = array();
	foreach ( $secrets as $field => $value ) $items[ $field ] = $C::seal( $other, $field, $value );
	$K::put( $C::OPTION, array( 'v' => 1, 'items' => $items ) );
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && 'needs_reentry' === $status['credentials'] && false !== strpos( $status['credentials_notice'], 'vuelve a escribir la contraseña de SFTP' ) && false !== strpos( $status['credentials_notice'], 'OneDrive' ) && ! isset( $status['destinations']['sftp'] ) && true === $status['ready'], 'GET /status no pide volver a escribir las credenciales: ' . json_encode( $status ) );
	$clean( 'GET /status con las salts cambiadas', json_encode( $status ) );
	list( $code, $out ) = digi_wp( $cmd, 'status' );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), '"campo":"credentials","valor":"needs_reentry"' ) && false !== strpos( implode( "\n", $out ), 'vuelve a escribir la contraseña de SFTP' ), 'wp … status no pide volver a escribir las credenciales: ' . implode( "\n", $out ) );
	$clean( 'wp … status con las salts cambiadas', implode( "\n", $out ) );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups', array( 'destination' => 'sftp' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_destination' === $error['code'] && false !== strpos( $error['message'], 'vuelve a escribir la contraseña de SFTP' ), 'POST /backups hacia SFTP no explica que hay que volver a escribir la contraseña: ' . json_encode( $error ) );
	$clean( 'POST /backups', json_encode( $error ) );
	list( $code, $out ) = digi_wp( $cmd, 'create', array(), array( 'destination' => 'google' ) );
	digi_assert( 1 === $code && false !== strpos( end( $out ), 'Google Drive' ) && false !== strpos( end( $out ), 'conéctalo de nuevo' ), 'wp … create hacia Google Drive no explica qué hacer: ' . implode( "\n", $out ) );
	$clean( 'wp … create', implode( "\n", $out ) );
	// Un respaldo local sigue funcionando: las credenciales sólo afectan a su destino.
	list( $code, $queued ) = digi_rest( 'POST', $ns . '/backups', array( 'destination' => 'local' ) );
	digi_assert( 202 === $code && 'local' === $queued['destination'], 'Las credenciales bloqueadas impidieron un respaldo local: ' . json_encode( $queued ) );
	digi_assert( 'success' === digi_drive( $queued['id'] )['status'], 'El respaldo local no terminó.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: REST y WP-CLI informan las credenciales de los destinos (cifradas o por volver a escribir) sin ningún secreto.\n";
