<?php
/**
 * S4 · credenciales de los destinos cifradas que viajan en el respaldo (decisión del propietario del 2026-11-08: un
 * respaldo restaura el sitio completo, también sus conexiones). Con el motor real de trabajos y de restauración:
 *   (a) tras guardar por admin-post, ni digitalisimo_backups_settings ni el database.sql de un respaldo real llevan un
 *       secreto en claro, y el almacén cifrado sí viaja en el SQL;
 *   (b) migración desde claro: la de un sitio actualizado (idempotente y sin consultas con la marca) y la de un
 *       respaldo antiguo (creado por la clase publicada de 1.0.58) al restaurarlo;
 *   (c) restaurar en el mismo servidor deja los destinos operativos (SFTP se autentica y Google renueva con los
 *       secretos del respaldo, que mandan sobre los del servidor) y cifrados;
 *   (d) restaurar con otras salts vuelve a cifrar con las de este servidor usando las del wp-config.php del respaldo;
 *   (e) salts no legibles (getenv) → «vuelve a escribir», sin texto en claro en ninguna parte;
 *   (f) salts cambiadas en el mismo servidor → un trabajo con destino remoto falla de forma permanente con el mensaje;
 *   (g) el refresh token de OAuth se guarda cifrado;
 *   (h) red: el almacén vive en sitemeta, viaja en el SQL y se vuelve a cifrar al restaurar;
 *   (i) ningún secreto en `status` ni en la pantalla de Servidores;
 *   (j) el dato adicional impide mover un valor a otro campo (y cualquier alteración se rechaza).
 * Al final, (k): una restauración con otras salts que muere en mitad del SQL termina de cifrar con el diario.
 * Vectores fijos de la clave, la huella y el AEAD contrastados con hashlib y cryptography de Python.
 * Cada caso escribe «FALLA (x)» si falla; la prueba falla si alguno lo hizo.
 */
require __DIR__ . '/lib/backups-env.php';
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$K = 'Digitalisimo_Backups_Keys';

// Lo que el OAuth, la pantalla y SFTP necesitan y el entorno común no trae.
$GLOBALS['bk_transients'] = array();
$GLOBALS['bk_http'] = array();
$GLOBALS['bk_http_reply'] = array();
function get_current_user_id() { return 1; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bk_transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['bk_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['bk_transients'][ $key ] ); return true; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce-' . $action; }
function wp_remote_post( $url, $args = array() ) { $GLOBALS['bk_http'][] = array( $url, $args ); return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $GLOBALS['bk_http_reply'] ) ); }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
define( 'SSH2_FINGERPRINT_SHA1', 1 );
define( 'SSH2_FINGERPRINT_HEX', 2 );
$GLOBALS['bk_sftp_root'] = sys_get_temp_dir() . '/digitalisimo-cred-sftp-' . bin2hex( random_bytes( 4 ) );
mkdir( $GLOBALS['bk_sftp_root'] . '/backups', 0700, true );
$GLOBALS['bk_sftp_logins'] = array();
function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_fingerprint( $connection, $flags ) { return str_repeat( 'A', 40 ); }
function ssh2_auth_password( $connection, $user, $password ) { $GLOBALS['bk_sftp_logins'][] = $password; return hash_equals( $GLOBALS['bk_sftp_expected'] ?? '', $password ); }
function ssh2_sftp( $connection ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_sftp_rename( $sftp, $from, $to ) { return rename( $GLOBALS['bk_sftp_root'] . $from, $GLOBALS['bk_sftp_root'] . $to ); }
function ssh2_sftp_unlink( $sftp, $path ) { return unlink( $GLOBALS['bk_sftp_root'] . $path ); }
class Bk_Credentials_Sftp_Stream {
	public $context;
	private $handle;
	public function stream_open( $path, $mode, $options, &$opened ) { $this->handle = fopen( $GLOBALS['bk_sftp_root'] . parse_url( $path, PHP_URL_PATH ), $mode ); return (bool) $this->handle; }
	public function stream_read( $count ) { return fread( $this->handle, $count ); }
	public function stream_write( $data ) { return fwrite( $this->handle, $data ); }
	public function stream_eof() { return feof( $this->handle ); }
	public function stream_flush() { return fflush( $this->handle ); }
	public function stream_stat() { return fstat( $this->handle ); }
	public function stream_close() { fclose( $this->handle ); }
}
if ( in_array( 'ssh2.sftp', stream_get_wrappers(), true ) ) throw new RuntimeException( 'La prueba requiere un runtime sin la extensión SSH2 real.' );
stream_wrapper_register( 'ssh2.sftp', 'Bk_Credentials_Sftp_Stream' );

/** La base de datos en memoria con wp_sitemeta (en una red), que refleja las opciones de red simuladas. */
class Bk_Credentials_DB extends Bk_Test_DB {
	public function tables() { $tables = parent::tables(); if ( $GLOBALS['bk_multisite'] ) $tables[] = 'wp_sitemeta'; return $tables; }
	public function get_row( $query, $format ) {
		if ( false !== strpos( $query, 'SHOW CREATE TABLE `wp_sitemeta`' ) ) return array( 'wp_sitemeta', "CREATE TABLE `wp_sitemeta` (\n  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `site_id` bigint(20) unsigned NOT NULL DEFAULT '0',\n  `meta_key` varchar(255) DEFAULT NULL,\n  `meta_value` longtext,\n  PRIMARY KEY (`meta_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
		return parent::get_row( $query, $format );
	}
	public function get_results( $query, $format ) {
		if ( false !== strpos( $query, 'SHOW INDEX FROM `wp_sitemeta`' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => 'meta_id', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( false === strpos( $query, '`wp_sitemeta`' ) || false !== strpos( $query, 'SHOW INDEX' ) ) return parent::get_results( $query, $format );
		$rows = array();
		$id = 0;
		foreach ( $GLOBALS['bk_site_options'] as $name => $value ) $rows[] = array( 'meta_id' => (string) ++$id, 'site_id' => '1', 'meta_key' => $name, 'meta_value' => is_scalar( $value ) ? (string) $value : serialize( $value ) );
		return bk_page( $rows, $query, 'meta_id' );
	}
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'DROP TABLE IF EXISTS `wp_sitemeta`' ) ) { $this->queries[] = $sql; $GLOBALS['bk_site_options'] = array(); return 1; }
		if ( 0 === strpos( $sql, 'INSERT INTO `wp_sitemeta`' ) && preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'|NULL/s", substr( $sql, strpos( $sql, 'VALUES' ) ), $m ) && count( $m[1] ) >= 4 ) {
			$this->queries[] = $sql;
			$value = bk_sql_unescape( $m[1][3] );
			$unserialized = @unserialize( $value );
			$GLOBALS['bk_site_options'][ bk_sql_unescape( $m[1][2] ) ] = false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
			return 1;
		}
		return parent::query( $sql );
	}
}
$GLOBALS['wpdb'] = new Bk_Credentials_DB();

$failures = array();
$done = array();
$case = function( $label, callable $body ) use ( &$failures, &$done ) {
	try {
		$body();
		$done[] = $label;
	} catch ( Throwable $e ) {
		$failures[] = $label;
		echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
	}
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$secrets = array( 'sftp_password' => 'Sftp-Clave.Unica_7c1f9e2a', 'google_client_secret' => 'GOCSPX-cliente-secreto-91ab4d', 'google_refresh_token' => '1//refresh-google-55de0b7f', 'onedrive_client_secret' => 'onedrive~secreto~3f2a6c', 'onedrive_refresh_token' => 'M.R3_BAY.refresh-onedrive-8e0d' );
$plain_settings = array( 'destination' => 'local', 'retention' => '100', 'sftp_name' => 'SFTP', 'sftp_host' => 'sftp.example.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_path' => '/backups', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'google_name' => 'Google Drive', 'google_client_id' => 'cliente-google', 'google_folder_id' => '', 'onedrive_name' => 'OneDrive', 'onedrive_client_id' => 'cliente-onedrive', 'onedrive_folder' => 'Respaldos', 'full_schedule' => 'disabled', 'database_schedule' => 'disabled' );
/** El primer secreto que aparece en claro en $text (también escapado en JSON o serializado), o null. */
$leak = function( $text, $values = null ) use ( &$secrets ) {
	foreach ( $values ?? $secrets as $value ) if ( false !== strpos( (string) $text, $value ) || false !== strpos( (string) $text, trim( json_encode( $value ), '"' ) ) ) return $value;
	return null;
};
$settings = function() use ( $B ) { return bk_call( $B, 'settings' ); };
$sql_of = function( $path ) { $zip = new ZipArchive(); $zip->open( $path ); $sql = $zip->getFromName( 'database.sql' ); $zip->close(); return (string) $sql; };
$save = function( array $fields ) use ( $B ) { return bk_post( array( $B, 'save' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $fields ) ); };
$restore = function( $path ) use ( $B ) {
	$report = Digitalisimo_Backups_Security::open( $path, bk_call( $B, 'history_item', basename( $path ) ), '' );
	try { bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report ); } finally { Digitalisimo_Backups_Security::close( $report ); }
};
$store = function() use ( $C, $K ) { return $K::get( $C::OPTION, null ); };
$current = function() use ( $C ) { return $C::key_id( $C::key() ); };
/** SFTP con los ajustes en vigor: true si se autenticó con $password y publicó la copia verificada. */
$sftp_works = function( $password ) use ( $B ) {
	$GLOBALS['bk_sftp_expected'] = $password;
	$GLOBALS['bk_sftp_logins'] = array();
	$file = bk_storage() . '/digitalisimo-transferencia.zip';
	file_put_contents( $file, 'ZIP de prueba para la transferencia' );
	try { $remote = bk_call( $B, 'job_transfer', $file, 'sftp' ); } finally { @unlink( $file ); }
	@unlink( $GLOBALS['bk_sftp_root'] . '/backups/digitalisimo-transferencia.zip' );
	return 'sftp' === ( $remote['provider'] ?? '' ) && array( $password ) === $GLOBALS['bk_sftp_logins'];
};
/** Google con los ajustes en vigor: el client secret y el refresh token que se envían al renovar el acceso. */
$google_sent = function() use ( $B, $settings ) {
	$GLOBALS['bk_http'] = array();
	$GLOBALS['bk_http_reply'] = array( 'access_token' => 'acceso' );
	bk_call( $B, 'token', 'https://oauth2.googleapis.com/token', $settings(), 'google' );
	$body = $GLOBALS['bk_http'][0][1]['body'] ?? array();
	return array( (string) ( $body['client_secret'] ?? '' ), (string) ( $body['refresh_token'] ?? '' ) );
};
/** Un «wp-config.php» de otro servidor: además de las salts, comentarios, otro define en un comentario y una comilla escapada. */
$origin_auth = "origen-auth-key-'comilla'-0123456789abcdef";
$origin_secure = 'origen-secure-auth-key-0123456789abcdef-~!#';
$config_with = function( $auth_code, $secure_code ) {
	return "<?php\n/** Configuración del servidor de origen. */\ndefine( 'DB_NAME', 'origen' );\n// define( 'AUTH_KEY', 'un valor comentado que no cuenta' );\n/* define( 'SECURE_AUTH_KEY', 'tampoco este' ); */\ndefine( 'AUTH_KEY', " . $auth_code . " );\n\\define(\n\t'SECURE_AUTH_KEY',\n\t" . $secure_code . "\n);\ndefine( 'NONCE_KEY', 'otra cosa' );\n\$table_prefix = 'wp_';\nrequire_once ABSPATH . 'wp-settings.php';\n";
};
$original_config = file_get_contents( ABSPATH . 'wp-config.php' );
/** Respaldo de un servidor con otras salts: su almacén está cifrado con ellas y su wp-config.php es $config. */
$origin_backup = function( $config, $origin_key ) use ( $C, $K, $secrets, $plain_settings, $original_config ) {
	$items = array();
	foreach ( $secrets as $field => $value ) $items[ $field ] = $C::seal( $origin_key, $field, $value );
	$K::put( $C::OPTION, array( 'v' => 1, 'items' => $items ) );
	update_option( Digitalisimo_Backups::OPTION, $plain_settings );
	file_put_contents( ABSPATH . 'wp-config.php', $config );
	try { return bk_backup( true ); } finally { file_put_contents( ABSPATH . 'wp-config.php', $original_config ); }
};

// Vectores fijos: la clave, su huella y un elemento cifrado, calculados también con hashlib y cryptography de Python.
$case( 'vectores', function() use ( $C, $K, $check ) {
	$key = $C::key_from( 'clave-auth-de-prueba-kat-0123456789', 'clave-secure-de-prueba-kat-0123456789' );
	$check( '9f9939e78a105b509183e1b68ee649688188cf8ddaf6cc871c399db8be59a3b4' === bin2hex( (string) $key ) && '1c8beaa02f39d494' === $C::key_id( $key ), 'Cambió la derivación de la clave de las credenciales o su huella.' );
	$item = array( 'kw' => '1c8beaa02f39d494', 'c' => 'AQIDBAUGBwgJCgsMDQ4PEBESExQVFhcYpx5LOQwg0PtyUzC7x8Suhvp6G7pc6MNhT8wWcD8rZv4sLjr6COsIaJ19G2p-CD4' );
	$check( 'secreto-kat-de-las-credenciales' === $C::open( $key, 'sftp_password', $item ), 'El elemento del vector conocido no se abre.' );
	$check( false === $C::key_from( 'put your unique phrase here', 'clave-secure-de-prueba-kat-0123456789' ) && false === $C::key_from( 'corta', 'clave-secure-de-prueba-kat-0123456789' ), 'Se aceptaron salts de ejemplo o cortas.' );
	// Separación de dominio: nunca la clave del llavero.
	$check( ! hash_equals( (string) $K::wrap_key(), (string) $C::key() ), 'Las credenciales usan la misma clave que el llavero.' );
} );

// (a) Guardar por admin-post: ningún secreto en claro en los ajustes ni en el SQL; el almacén sí viaja.
$backup_a = null;
$case( 'a', function() use ( $B, $C, $check, $leak, $save, $secrets, $plain_settings, $settings, $sql_of, $store, $current, &$backup_a ) {
	list( $ok, $message ) = $save( $plain_settings + $secrets );
	$check( $ok, 'No se guardaron las conexiones: ' . $message );
	$check( null === $leak( serialize( get_option( $B::OPTION ) ) ), 'digitalisimo_backups_settings guarda un secreto en claro.' );
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ) ) && null === $leak( serialize( $GLOBALS['bk_site_options'] ) ), 'Alguna opción guarda un secreto en claro.' );
	$saved = $store();
	$check( is_array( $saved ) && 1 === $saved['v'] && array_keys( $secrets ) === array_keys( $saved['items'] ) && ! array_filter( $saved['items'], function( $item ) use ( $current ) { return $item['kw'] !== $current(); } ), 'El almacén cifrado no tiene los cinco secretos con la clave de este servidor.' );
	$check( array_intersect_key( $settings(), $secrets ) == $secrets, 'Los ajustes no devuelven los secretos descifrados.' );
	$check( '1' === get_option( $B::CREDENTIALS_CLEAN ), 'Falta la marca de ajustes sin secretos en claro.' );
	$backup_a = bk_backup( true );
	$sql = $sql_of( $backup_a );
	$check( null === $leak( $sql ), 'El database.sql del respaldo contiene un secreto en claro.' );
	$check( false !== strpos( $sql, "'digitalisimo_backups_credentials'" ) && false !== strpos( $sql, $saved['items']['sftp_password']['c'] ), 'El almacén cifrado no viaja en el database.sql.' );
	$check( false !== strpos( $sql, "'digitalisimo_backups_settings'" ), 'Los ajustes no viajan en el database.sql.' );
} );

// (i) La pantalla de Servidores y el estado no llevan ningún secreto ni la clave.
$case( 'i', function() use ( $B, $C, $check, $leak, $settings ) {
	ob_start();
	try { bk_call( $B, 'servers_panel', $settings(), array() ); } finally { $html = ob_get_clean(); }
	$check( null === $leak( $html ), 'La pantalla de Servidores muestra un secreto.' );
	$check( 5 === substr_count( $html, 'placeholder="Guardado · escribe sólo para reemplazar"' ) && 5 === substr_count( $html, 'type="password"' ), 'La pantalla no anuncia los cinco secretos guardados con el campo vacío.' );
	$status = $B::service_status();
	$json = json_encode( $status );
	$check( ! is_wp_error( $status ) && 'encrypted' === $status['credentials'] && '' === $status['credentials_notice'], 'El estado no dice que las credenciales están cifradas: ' . $json );
	$check( null === $leak( $json ) && false === strpos( $json, bin2hex( $C::key() ) ) && false === strpos( $json, Digitalisimo_Backups_Keys::b64url_encode( $C::key() ) ), 'El estado lleva un secreto o la clave.' );
} );

// (j) El dato adicional ata cada valor a su campo: moverlo o alterarlo no lo abre, y nunca se lee el valor de otro campo.
$case( 'j', function() use ( $B, $C, $K, $check, $store, $settings, $secrets ) {
	$saved = $store();
	$key = $C::key();
	$check( $secrets['sftp_password'] === $C::open( $key, 'sftp_password', $saved['items']['sftp_password'] ) && false === $C::open( $key, 'google_client_secret', $saved['items']['sftp_password'] ), 'El elemento de SFTP se abre como otro campo.' );
	$moved = $saved;
	$moved['items']['google_client_secret'] = $saved['items']['sftp_password'];
	$K::put( $C::OPTION, $moved );
	$s = $settings();
	$check( '' === $s['google_client_secret'] && $secrets['sftp_password'] === $s['sftp_password'] && in_array( 'google_client_secret', $C::locked(), true ), 'Un valor movido a otro campo se leyó: ' . $s['google_client_secret'] );
	$tampered = $saved;
	$raw = Digitalisimo_Backups_Keys::b64url_decode( $saved['items']['onedrive_refresh_token']['c'] );
	$raw[ strlen( $raw ) - 1 ] = chr( ord( $raw[ strlen( $raw ) - 1 ] ) ^ 1 );
	$tampered['items']['onedrive_refresh_token']['c'] = Digitalisimo_Backups_Keys::b64url_encode( $raw );
	$K::put( $C::OPTION, $tampered );
	$check( '' === $settings()['onedrive_refresh_token'] && in_array( 'onedrive_refresh_token', $C::locked(), true ), 'Un elemento alterado se aceptó.' );
	$K::put( $C::OPTION, array( 'v' => 2 ) + $saved );
	$check( '' === $settings()['sftp_password'] && 5 === count( $C::locked() ), 'Un almacén de otro formato se leyó como v1.' );
	$K::put( $C::OPTION, $saved );
	$check( array_intersect_key( $settings(), $secrets ) == $secrets && ! $C::locked(), 'El almacén original no volvió a abrirse.' );
} );

// (c) Restaurar en el mismo servidor: las credenciales del respaldo mandan sobre las de ahora, funcionan y siguen cifradas.
$case( 'c', function() use ( $B, $C, $check, $leak, $save, $restore, $secrets, $settings, $sftp_works, $google_sent, $store, $current, &$backup_a ) {
	$check( is_string( $backup_a ) && is_file( $backup_a ), 'No hay respaldo de (a).' );
	list( $ok ) = $save( array( 'sftp_password' => 'otra-clave-sftp-posterior', 'google_client_secret' => 'otro-secreto-google-posterior' ) + array_diff_key( bk_call( $B, 'settings' ), $secrets ) );
	$check( $ok && 'otra-clave-sftp-posterior' === $settings()['sftp_password'], 'No se cambió la contraseña después del respaldo.' );
	$restore( $backup_a );
	$s = $settings();
	$check( array_intersect_key( $s, $secrets ) == $secrets, 'La restauración no devolvió las credenciales del respaldo.' );
	$check( isset( bk_call( $B, 'manual_destinations', $s )['sftp'], bk_call( $B, 'manual_destinations', $s )['google'], bk_call( $B, 'manual_destinations', $s )['onedrive'] ), 'Tras restaurar, los destinos no están conectados.' );
	$check( $sftp_works( $secrets['sftp_password'] ), 'SFTP no se autenticó con la contraseña del respaldo: ' . json_encode( $GLOBALS['bk_sftp_logins'] ) );
	$check( array( $secrets['google_client_secret'], $secrets['google_refresh_token'] ) === $google_sent(), 'Google no renovó el acceso con los secretos del respaldo.' );
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ), $secrets + array( 'x' => 'otra-clave-sftp-posterior' ) ) && $current() === $store()['items']['sftp_password']['kw'], 'Tras restaurar, un secreto quedó en claro o con otra clave.' );
} );

// (f) Las salts de este servidor cambiaron: el almacén no abre, la pantalla y el estado lo dicen, y un trabajo con destino
// remoto falla de forma permanente con el mensaje; nunca se usa ni se escribe un texto en claro.
$case( 'f', function() use ( $B, $C, $K, $check, $leak, $save, $secrets, $settings, $store, $current ) {
	$old = $C::key_from( 'salt-anterior-auth-0123456789abcdef', 'salt-anterior-secure-0123456789abcdef' );
	$items = array();
	foreach ( $secrets as $field => $value ) $items[ $field ] = $C::seal( $old, $field, $value );
	$K::put( $C::OPTION, array( 'v' => 1, 'items' => $items ) );
	$s = $settings();
	$check( '' === $s['sftp_password'] && '' === $s['google_refresh_token'] && 5 === count( $C::locked() ), 'Con otras salts se leyeron credenciales.' );
	$status = $B::service_status();
	$check( 'needs_reentry' === $status['credentials'] && false !== strpos( $status['credentials_notice'], 'vuelve a escribir la contraseña de SFTP' ) && false !== strpos( $status['credentials_notice'], 'Google Drive' ) && null === $leak( json_encode( $status ) ), 'El estado no pide volver a escribir las credenciales: ' . json_encode( $status ) );
	ob_start();
	try { bk_call( $B, 'servers_panel', $s, $C::locked() ); } finally { $html = ob_get_clean(); }
	$check( 5 === substr_count( $html, 'vuelve a escribirlo' ) && null === $leak( $html ), 'La pantalla no marca los campos que hay que volver a escribir.' );
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 'sftp', 'retention' => 'none', 'via' => 'test' ) ) );
	$job = $queued['job'];
	for ( $i = 0; $i < 20 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) { Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX ); $job = Digitalisimo_Backups_Jobs::get( $job['id'] ); }
	$check( 'failed' === $job['status'] && ! empty( $job['error']['permanent'] ) && false !== strpos( $job['error']['message'], 'vuelve a escribir la contraseña de SFTP' ) && false !== strpos( $job['error']['message'], 'AUTH_KEY y SECURE_AUTH_KEY' ), 'El trabajo remoto no falló de forma permanente con el mensaje: ' . json_encode( $job['error'] ) );
	$row = bk_call( $B, 'history_item', basename( $job['spec']['file'] ) );
	$check( is_file( bk_storage() . '/' . basename( $job['spec']['file'] ) ) && 'failed' === ( $row['transfer'] ?? '' ) && false !== strpos( (string) ( $row['transfer_error'] ?? '' ), 'vuelve a escribir la contraseña de SFTP' ), 'El respaldo local verificado no se conservó con el motivo de la copia fallida: ' . json_encode( $row ) );
	try { bk_call( $B, 'token', 'https://oauth2.googleapis.com/token', $s, 'google' ); throw new LogicException( 'Google renovó sin credenciales.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'Google Drive' ) && false !== strpos( $e->getMessage(), 'conéctalo de nuevo' ), 'Google no explica que hay que volver a conectarlo: ' . $e->getMessage() ); }
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ) ), 'Con las salts cambiadas apareció un secreto en claro.' );
	// Volver a escribir la contraseña de SFTP la cifra con las salts actuales; Google y OneDrive siguen pidiéndose.
	list( $ok ) = $save( array( 'sftp_password' => $secrets['sftp_password'] ) + array_diff_key( $settings(), $secrets ) );
	$saved = $store();
	$check( $ok && $current() === $saved['items']['sftp_password']['kw'] && $items['google_refresh_token'] === $saved['items']['google_refresh_token'] && array( 'google_client_secret', 'google_refresh_token', 'onedrive_client_secret', 'onedrive_refresh_token' ) === $C::locked(), 'Volver a escribir un secreto no lo recuperó o tocó los demás.' );
	$destinations = bk_call( $B, 'manual_destinations', $settings() );
	$check( isset( $destinations['sftp'] ) && ! isset( $destinations['google'] ) && ! isset( $destinations['onedrive'] ), 'Tras volver a escribir la contraseña, SFTP no volvió a estar disponible o Google y OneDrive sí.' );
	// Los demás se vuelven a escribir y todo queda como en (a).
	list( $ok ) = $save( $secrets + array_diff_key( $settings(), $secrets ) );
	$check( $ok && ! $C::locked() && array_intersect_key( $settings(), $secrets ) == $secrets, 'No se recuperaron las credenciales al volver a escribirlas.' );
	foreach ( Digitalisimo_Backups_Jobs::active() as $active ) Digitalisimo_Backups_Jobs::cancel( $active['id'] );
} );

// (g) El refresh token que devuelve OAuth se guarda cifrado, nunca en los ajustes.
$case( 'g', function() use ( $B, $C, $check, $leak, $settings, $store, $current ) {
	$token = '1//refresh-de-oauth-nuevo-a1b2c3d4';
	$GLOBALS['bk_http'] = array();
	$GLOBALS['bk_transients'][ 'digitalisimo_backup_oauth_1' ] = array( 'state' => 'estado-oauth', 'provider' => 'google' );
	$GLOBALS['bk_http_reply'] = array( 'refresh_token' => $token );
	$_GET = array( 'code' => 'codigo-de-google', 'state' => 'estado-oauth' );
	$_REQUEST = array( 'provider' => 'google' ) + $_GET;
	try { $B::oauth_callback(); throw new LogicException( 'OAuth no redirigió.' ); }
	catch ( Bk_Redirect $redirect ) { parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $query ); $message = rawurldecode( $query['message'] ?? '' ); $check( 'ok' === ( $query['backup'] ?? '' ) && false !== strpos( $message, 'Google Drive conectado' ), 'OAuth no conectó Google Drive: ' . $message ); }
	finally { $_GET = array(); $_REQUEST = array(); }
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ), array( $token ) ) && null === $leak( $redirect->url . $message, array( $token ) ), 'El refresh token quedó en claro.' );
	$check( $token === $settings()['google_refresh_token'] && $current() === $store()['items']['google_refresh_token']['kw'], 'El refresh token no quedó cifrado en el almacén.' );
	$check( 'GOCSPX-cliente-secreto-91ab4d' === ( $GLOBALS['bk_http'][0][1]['body']['client_secret'] ?? '' ), 'OAuth no usó el Client secret descifrado.' );
	// Los casos siguientes parten del refresh token de (a).
	$C::put( array( 'google_refresh_token' => '1//refresh-google-55de0b7f' ) );
} );

// (b) Migración desde claro: un sitio actualizado desde 1.0.60 y un respaldo antiguo con los secretos en los ajustes.
$case( 'b', function() use ( $B, $C, $K, $check, $leak, $secrets, $plain_settings, $settings, $store, $current, $restore ) {
	delete_option( $C::OPTION );
	delete_option( $B::CREDENTIALS_CLEAN );
	update_option( $B::OPTION, $plain_settings + $secrets );
	$check( 'migrated' === $B::migrate_credentials(), 'No se migraron los secretos en claro.' );
	$saved = $store();
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ) ) && 5 === count( $saved['items'] ) && array_intersect_key( $settings(), $secrets ) == $secrets && '1' === get_option( $B::CREDENTIALS_CLEAN ), 'La migración perdió un secreto o lo dejó en claro.' );
	$check( 'clean' === $B::migrate_credentials() && $saved === $store(), 'La migración no es idempotente (volvió a cifrar).' );
	// Con la marca, la comprobación al cargar no lee los ajustes; la única lectura (settings) migra lo que aparezca.
	$options = $GLOBALS['bk_options'];
	$GLOBALS['bk_options'][ $B::OPTION ]['sftp_password'] = 'escrito-por-fuera-en-claro';
	$B::credentials_on_load();
	$check( 'escrito-por-fuera-en-claro' === $GLOBALS['bk_options'][ $B::OPTION ]['sftp_password'], 'Con la marca, la carga del escritorio leyó los ajustes.' );
	$check( 'escrito-por-fuera-en-claro' === $settings()['sftp_password'] && ! isset( $GLOBALS['bk_options'][ $B::OPTION ]['sftp_password'] ), 'La lectura de los ajustes no cifró un secreto en claro.' );
	$GLOBALS['bk_options'] = $options;
	// Sin la marca (un SQL de 1.0.60 la borra), la carga del escritorio migra.
	update_option( $B::OPTION, $plain_settings + $secrets );
	delete_option( $B::CREDENTIALS_CLEAN );
	$B::credentials_on_load();
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ) ) && '1' === get_option( $B::CREDENTIALS_CLEAN ), 'La carga del escritorio no migró sin la marca.' );
	// Un respaldo creado por la clase publicada de 1.0.58 con los secretos en claro se migra al restaurarlo.
	$legacy = 'Digitalisimo_Backups_1_0_58_Cred';
	if ( ! class_exists( $legacy, false ) ) {
		$path = dirname( __DIR__ ) . '/rollback/digitalisimo-backups-1.0.58.zip';
		$zip = new ZipArchive();
		$check( true === $zip->open( $path ), 'Falta rollback/digitalisimo-backups-1.0.58.zip.' );
		$code = (string) $zip->getFromName( 'digitalisimo-backups/includes/class-backups.php' );
		$zip->close();
		$code = str_replace( "defined( 'ABSPATH' ) || exit;", '', preg_replace( '/^<\?php\s*/', '', $code ) );
		$code = preg_replace( '/\bfinal class Digitalisimo_Backups\b/', 'final class ' . $legacy, $code, 1 );
		eval( $code ); // phpcs:ignore Squiz.PHP.Eval
	}
	delete_option( $C::OPTION );
	delete_option( $B::CREDENTIALS_CLEAN );
	update_option( $B::OPTION, $plain_settings + $secrets );
	$old = bk_call( $legacy, 'create_network' );
	$path = bk_storage() . '/digitalisimo-antiguo-1.0.58.zip';
	rename( $old, $path );
	$zip = new ZipArchive();
	$zip->open( $path );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$zip->close();
	$check( $secrets['sftp_password'] === $leak( $sql, array( $secrets['sftp_password'] ) ), 'El respaldo de 1.0.58 no lleva los secretos en claro (no es la situación de partida).' );
	$now = array( 'sftp_password' => 'clave-de-ahora-que-no-gana' );
	update_option( $B::OPTION, $plain_settings );
	$C::put( $now );
	bk_call( $B, 'remember', $path, 'local', 'installation', array( 'id' => 'manual', 'type' => 'full' ) );
	$restore( $path );
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ), $secrets + $now ) && array_intersect_key( $settings(), $secrets ) == $secrets && $current() === $store()['items']['sftp_password']['kw'], 'Restaurar un respaldo antiguo con secretos en claro no los cifró.' );
	@unlink( $path );
} );

// (d) Restaurar con otras salts: el almacén del respaldo se vuelve a cifrar con las de este servidor, con las del
// wp-config.php que viaja en el respaldo (literales leídos con el tokenizador, sin ejecutar nada).
$case( 'd', function() use ( $B, $C, $check, $leak, $secrets, $settings, $store, $current, $restore, $origin_backup, $config_with, $origin_auth, $origin_secure, $sftp_works ) {
	$auth_literal = "'" . str_replace( "'", "\\'", $origin_auth ) . "'";
	$secure_literal = '"' . $origin_secure . '"';
	$config = $config_with( $auth_literal, $secure_literal );
	$origin = $C::key_from( $origin_auth, $origin_secure );
	$found = $C::config_salts( $config );
	$check( array( $origin_auth ) === $found['AUTH_KEY'] && array( $origin_secure ) === $found['SECURE_AUTH_KEY'], 'No se leyeron las salts literales del wp-config.php: ' . json_encode( $found ) );
	$backup = $origin_backup( $config, $origin );
	$check( false === strpos( (string) file_get_contents( ABSPATH . 'wp-config.php' ), 'origen-auth-key' ), 'El wp-config.php del servidor cambió.' );
	// El servidor tenía otras credenciales: las del respaldo mandan.
	update_option( $B::OPTION, array() );
	$C::put( array( 'sftp_password' => 'la-del-servidor-antes' ) );
	$restore( $backup );
	$saved = $store();
	$check( ! array_filter( $saved['items'], function( $item ) use ( $current ) { return $item['kw'] !== $current(); } ) && array_intersect_key( $settings(), $secrets ) == $secrets && ! $C::locked(), 'Las credenciales del respaldo no se volvieron a cifrar con las salts de este servidor.' );
	$check( null === $leak( serialize( $GLOBALS['bk_options'] ), $secrets + array( 'x' => 'la-del-servidor-antes' ) ) && 'encrypted' === $B::service_status()['credentials'], 'Tras restaurar con otras salts quedó un secreto en claro o el estado no es «cifradas».' );
	$check( $sftp_works( $secrets['sftp_password'] ), 'SFTP no funciona tras restaurar desde otro servidor.' );
	$check( file_get_contents( ABSPATH . 'wp-config.php' ) !== $config, 'La restauración escribió el wp-config.php del respaldo.' );
} );

// (e) Las salts del respaldo no se pueden leer (getenv): «vuelve a escribir», sin texto en claro, con aviso al restaurar.
$case( 'e', function() use ( $B, $C, $check, $leak, $secrets, $settings, $store, $origin_backup, $config_with ) {
	$origin = $C::key_from( 'salts-por-entorno-auth-0123456789ab', 'salts-por-entorno-secure-0123456789ab' );
	$config = $config_with( "'parte-literal-de-la-salt-0123456789' . getenv( 'WP_AUTH_KEY' )", "getenv( 'WP_SECURE_AUTH_KEY' )" );
	$check( array( 'AUTH_KEY' => array(), 'SECURE_AUTH_KEY' => array() ) === $C::config_salts( $config ), 'Se tomó una expresión por un literal.' );
	$backup = $origin_backup( $config, $origin );
	$items = $store()['items'];
	update_option( $B::OPTION, array() );
	$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
	$result = $B::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR' ) );
	$check( ! is_wp_error( $result ) && true === $result['restored'], 'La restauración falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	$check( false !== strpos( $result['notice'], 'vuelve a escribir la contraseña de SFTP' ), 'La restauración no avisó de las credenciales que hay que volver a escribir: ' . $result['notice'] );
	$s = $settings();
	$check( '' === $s['sftp_password'] && '' === $s['google_client_secret'] && 5 === count( $C::locked() ) && $items === $store()['items'], 'Con salts no legibles se leyó o se perdió el almacén.' );
	$status = $B::service_status();
	$check( 'needs_reentry' === $status['credentials'] && false !== strpos( $status['credentials_notice'], 'OneDrive' ), 'El estado no pide volver a escribir las credenciales.' );
	$journal = bk_storage() . '/' . $B::RESTORE_JOURNAL;
	$check( ! file_exists( $journal ) && null === $leak( serialize( $GLOBALS['bk_options'] ) . json_encode( $status ) . json_encode( $result ) ), 'Con salts no legibles apareció un secreto en claro.' );
	try { bk_call( $B, 'job_transfer', bk_storage() . '/' . basename( $backup ), 'onedrive' ); throw new LogicException( 'OneDrive transfirió sin credenciales.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'OneDrive' ) && false !== strpos( $e->getMessage(), 'respaldo local verificado se conserva' ), 'El error del trabajo no explica qué hacer: ' . $e->getMessage() ); }
	$created = $B::service_create( array( 'destination' => 'sftp' ) );
	$check( is_wp_error( $created ) && false !== strpos( $created->get_error_message(), 'vuelve a escribir la contraseña de SFTP' ), 'Crear un respaldo hacia SFTP no explica que falta la contraseña.' );
} );

// (h) Red: el almacén vive en sitemeta (nunca en las opciones del sitio), viaja en el SQL y se vuelve a cifrar al restaurar.
$case( 'h', function() use ( $B, $C, $K, $check, $leak, $save, $secrets, $plain_settings, $settings, $sql_of, $restore, $current, $origin_backup, $config_with, $origin_auth, $origin_secure ) {
	$GLOBALS['bk_multisite'] = true;
	try {
		$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Red de prueba' );
		$GLOBALS['bk_site_options'] = array();
		list( $ok, $message ) = $save( $plain_settings + $secrets );
		$check( $ok, 'No se guardaron las conexiones en la red: ' . $message );
		$check( isset( $GLOBALS['bk_site_options'][ $C::OPTION ] ) && ! isset( $GLOBALS['bk_options'][ $C::OPTION ] ), 'En una red el almacén no está en sitemeta.' );
		$check( null === $leak( serialize( $GLOBALS['bk_options'] ) . serialize( $GLOBALS['bk_site_options'] ) ) && array_intersect_key( $settings(), $secrets ) == $secrets, 'En la red un secreto quedó en claro o no se lee.' );
		$backup = bk_backup( true );
		$sql = $sql_of( $backup );
		$check( null === $leak( $sql ) && preg_match( "/^INSERT INTO `wp_sitemeta` .*'digitalisimo_backups_credentials'/m", $sql ), 'El SQL de la red lleva un secreto en claro o no lleva el almacén de sitemeta.' );
		// Restaurar en «otro servidor» de la red: el almacén de sitemeta se vuelve a cifrar con las salts de éste.
		$auth_literal = "'" . str_replace( "'", "\\'", $origin_auth ) . "'";
		$network_backup = $origin_backup( $config_with( $auth_literal, "'" . $origin_secure . "'" ), $C::key_from( $origin_auth, $origin_secure ) );
		$check( $C::key_id( $C::key_from( $origin_auth, $origin_secure ) ) === $GLOBALS['bk_site_options'][ $C::OPTION ]['items']['sftp_password']['kw'], 'El almacén de la red no quedó con las salts del origen.' );
		unset( $GLOBALS['bk_site_options'][ $C::OPTION ] );
		$restore( $network_backup );
		$saved = $GLOBALS['bk_site_options'][ $C::OPTION ] ?? array();
		$check( 5 === count( $saved['items'] ?? array() ) && ! array_filter( $saved['items'], function( $item ) use ( $current ) { return $item['kw'] !== $current(); } ) && ! isset( $GLOBALS['bk_options'][ $C::OPTION ] ), 'En la red el almacén restaurado no se volvió a cifrar en sitemeta.' );
		$check( array_intersect_key( $settings(), $secrets ) == $secrets && null === $leak( serialize( $GLOBALS['bk_options'] ) . serialize( $GLOBALS['bk_site_options'] ) ), 'En la red las credenciales restauradas no se leen o quedaron en claro.' );
	} finally {
		$GLOBALS['bk_multisite'] = false;
	}
} );

// (k) Una restauración con otras salts que muere en mitad del SQL (exit(), que no ejecuta ningún finally): el diario lleva
// la clave del origen cifrada con la de este servidor (nunca en claro) y la operación siguiente termina el nuevo cifrado.
$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Sitio de prueba' );
$GLOBALS['bk_site_options'] = array();
$auth_literal = "'" . str_replace( "'", "\\'", $origin_auth ) . "'";
$interrupted = null;
$case( 'k', function() use ( $origin_backup, $config_with, $auth_literal, $origin_secure, $origin_auth, $C, &$interrupted ) {
	$interrupted = $origin_backup( $config_with( $auth_literal, "'" . $origin_secure . "'" ), $C::key_from( $origin_auth, $origin_secure ) );
} );
register_shutdown_function( function() use ( $B, $C, $check, $leak, $secrets, $settings, $store, $current, $origin_auth, $origin_secure, &$failures, &$done, $case, &$interrupted ) {
	if ( null !== $interrupted ) {
		$case( 'k', function() use ( $B, $C, $check, $leak, $secrets, $settings, $store, $current, $origin_auth, $origin_secure ) {
			$origin = $C::key_from( $origin_auth, $origin_secure );
			$journal = bk_storage() . '/' . $B::RESTORE_JOURNAL;
			$check( is_file( $journal ) && $C::key_id( $origin ) === ( $store()['items']['sftp_password']['kw'] ?? '' ), 'La simulación no dejó el SQL a medias con el almacén del origen.' );
			$text = (string) file_get_contents( $journal );
			foreach ( array( bin2hex( $origin ), base64_encode( $origin ), Digitalisimo_Backups_Keys::b64url_encode( $origin ), $origin_secure, 'origen-auth-key' ) as $secret ) $check( false === strpos( $text, $secret ), 'El diario guarda la clave o las salts del origen en claro.' );
			$check( null === $leak( $text ), 'El diario guarda un secreto en claro.' );
			$lock = bk_call( $B, 'acquire_operation_lock' );
			bk_call( $B, 'release_operation_lock', $lock );
			$check( ! file_exists( $journal ) && $current() === $store()['items']['sftp_password']['kw'] && array_intersect_key( $settings(), $secrets ) == $secrets && null === $leak( serialize( $GLOBALS['bk_options'] ) ), 'La operación siguiente no terminó de cifrar las credenciales con las salts de este servidor.' );
		} );
	}
	bk_cleanup();
	bk_remove_tree( $GLOBALS['bk_sftp_root'] );
	if ( $failures ) throw new RuntimeException( 'Credenciales de los destinos: fallaron ' . implode( ', ', array_unique( $failures ) ) . '.' );
	echo 'DIGITALÍSIMO Backups: credenciales de los destinos cifradas que viajan en el respaldo (' . implode( ', ', array_unique( $done ) ) . ").\n";
} );
if ( null !== $interrupted ) {
	unset( $GLOBALS['bk_options'][ $C::OPTION ] );
	$GLOBALS['wpdb']->exit_on = 'INSERT INTO `wp_posts`';
	$restore( $interrupted );
	$failures[] = 'k';
	echo "FALLA (k): la restauración no se interrumpió.\n";
}
