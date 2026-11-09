<?php
/**
 * S4 · revisión adversaria de las credenciales de los destinos: un caso por hallazgo, cada uno falla con d4b79f83.
 *   F1  en una red, un subsitio no lee ni escribe el almacén de la red (ni con un secreto viejo en claro en su opción,
 *       ni por OAuth, ni al migrar), y su pantalla no anuncia los secretos de la red;
 *   F2  lo que 1.0.60 reescribe en claro tras volver desde rollback/ se cifra al cargar y, sobre todo, ningún respaldo
 *       lleva un secreto en claro si hay salts propias (tampoco el de la opción de un subsitio);
 *   F3  el diario conserva la clave del origen aunque su huella sea toda decimal;
 *   F4  convertir un sitio individual en red conserva las credenciales;
 *   F5  un wp-config.php de más de 64 KiB no se analiza y uno hostil de ese tamaño no agota la memoria;
 *   F6  «Olvidar» retira las credenciales de un destino que ya no se usa, también las bloqueadas;
 *   F7  un almacén con objetos u otros tipos no rompe los ajustes, el estado ni la pantalla: pide volver a escribir;
 *   AD  el dato adicional ata cada secreto a su conexión: una escritura directa del host o del Client ID no lo desvía
 *       (deja de abrirse), y cambiar el host desde el formulario lo vuelve a atar sin escribirlo otra vez.
 */
require __DIR__ . '/lib/backups-env.php';
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$K = 'Digitalisimo_Backups_Keys';
$GLOBALS['bk_transients'] = array();
$GLOBALS['bk_http'] = array();
function get_current_user_id() { return 1; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bk_transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['bk_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['bk_transients'][ $key ] ); return true; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce-' . $action; }
function wp_remote_post( $url, $args = array() ) { $GLOBALS['bk_http'][] = array( $url, $args ); return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'refresh_token' => 'refresh-de-un-subsitio-0000', 'access_token' => 'acceso' ) ) ); }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }

$failures = array();
$done = array();
$case = function( $label, callable $body ) use ( &$failures, &$done ) {
	try { $body(); $done[] = $label; }
	catch ( Throwable $e ) { $failures[] = $label; echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; }
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$fields = array( 'destination' => 'local', 'retention' => '100', 'sftp_name' => 'SFTP', 'sftp_host' => 'sftp.red.test', 'sftp_port' => '22', 'sftp_user' => 'red', 'sftp_path' => '/r', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'google_name' => 'Google Drive', 'google_client_id' => 'cliente-google', 'onedrive_name' => 'OneDrive', 'onedrive_client_id' => 'cliente-onedrive', 'onedrive_folder' => 'Respaldos' );
$secrets = array( 'sftp_password' => 'Clave-de-la-RED_aa11', 'google_client_secret' => 'GOCSPX-red-77aa', 'google_refresh_token' => '1//refresh-red-88bb', 'onedrive_client_secret' => 'onedrive~red~99cc', 'onedrive_refresh_token' => 'M.R3_BAY.red-00dd' );
$save = function( array $post_fields ) use ( $B ) { return bk_post( array( $B, 'save' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $post_fields ) ); };
$settings = function() use ( $B ) { return bk_call( $B, 'settings' ); };
$secret_values = function( array $s ) use ( $C ) { return array_intersect_key( $s, array_flip( $C::FIELDS ) ); };
$sql_of = function( $path ) { $zip = new ZipArchive(); $zip->open( $path ); $sql = (string) $zip->getFromName( 'database.sql' ); $zip->close(); return $sql; };
$panel = function( $s, array $locked = array() ) use ( $B ) { ob_start(); try { bk_call( $B, 'servers_panel', $s, $locked ); } finally { $html = ob_get_clean(); } return $html; };
/** Cambia de sitio en una red: cada sitio con sus propias opciones (las de la red, en bk_site_options, son comunes). */
$on = function( $blog ) {
	static $stash = array();
	$stash[ $GLOBALS['bk_blog_id'] ?? 1 ] = $GLOBALS['bk_options'];
	$GLOBALS['bk_blog_id'] = $blog;
	$GLOBALS['bk_main_site'] = 1 === $blog;
	$GLOBALS['bk_options'] = $stash[ $blog ] ?? array( 'siteurl' => 'https://example.test/sitio-' . $blog, 'blogname' => 'Sitio ' . $blog );
};

// F1 · En una red, un subsitio no toca el almacén de la red.
$case( 'F1', function() use ( $B, $C, $K, $check, $save, $settings, $secret_values, $panel, $on, $fields, $secrets ) {
	$GLOBALS['bk_multisite'] = true;
	try {
		list( $ok, $message ) = $save( $fields + $secrets );
		$check( $ok && $secrets === $secret_values( $settings() ), 'El sitio principal no guardó las credenciales de la red: ' . $message );
		$network = $K::get( $C::OPTION, null );
		$on( 2 );
		$sub = $settings();
		$check( ! array_filter( $secret_values( $sub ) ), 'Un subsitio lee los secretos de la red.' );
		$check( false === strpos( $panel( $sub ), 'placeholder="Guardado' ), 'La pantalla de un subsitio anuncia los secretos de la red como guardados.' );
		$check( 'none' === bk_call( $B, 'credentials_status' )['state'], 'El estado de un subsitio habla de las credenciales de la red.' );
		// A) Guardar una conexión desde el subsitio: se rechaza y la red no cambia.
		list( $ok, $message ) = $save( array( 'destination' => 'sftp', 'sftp_host' => 'sftp.subsitio.test', 'sftp_user' => 'sub', 'sftp_port' => '22', 'sftp_path' => '/', 'sftp_fingerprint' => str_repeat( 'B', 40 ), 'sftp_password' => 'Clave-del-SUBSITIO_bb22' ) );
		$check( ! $ok && false !== strpos( $message, 'administración de red' ) && $network === $K::get( $C::OPTION, null ), 'El formulario de un subsitio cambió el almacén de la red: ' . $message );
		// B) Un subsitio con una contraseña vieja en claro en su propia opción: ni guardar, ni migrar, ni la carga la imponen.
		$GLOBALS['bk_options'][ $B::OPTION ] = array( 'sftp_host' => 'viejo.example.test', 'sftp_user' => 'viejo', 'sftp_password' => 'Clave-VIEJA-del-subsitio_cc33' );
		list( $ok ) = $save( array( 'destination' => 'local', 'sftp_host' => 'viejo.example.test', 'sftp_user' => 'viejo' ) );
		$check( ! $ok && 'skipped' === $B::migrate_credentials() && '' === $settings()['sftp_password'], 'Un subsitio guardó, migró o leyó su contraseña vieja.' );
		$B::credentials_on_load();
		$check( $network === $K::get( $C::OPTION, null ), 'La contraseña vieja de un subsitio llegó al almacén de la red.' );
		// OAuth desde un subsitio: rechazado sin escribir.
		$GLOBALS['bk_transients']['digitalisimo_backup_oauth_1'] = array( 'state' => 'e', 'provider' => 'google' );
		$_GET = array( 'code' => 'c', 'state' => 'e' );
		$_REQUEST = array( 'provider' => 'google' ) + $_GET;
		try { $B::oauth_callback(); } catch ( Bk_Redirect $redirect ) { $url = rawurldecode( $redirect->url ); } finally { $_GET = array(); $_REQUEST = array(); }
		$check( isset( $url ) && false !== strpos( $url, 'backup=error' ) && $network === $K::get( $C::OPTION, null ), 'El retorno de OAuth en un subsitio escribió en la red.' );
		$on( 1 );
		$check( $secrets === $secret_values( $settings() ) && 'sftp.red.test' === $settings()['sftp_host'], 'El sitio principal perdió las credenciales de la red.' );
	} finally {
		$on( 1 );
		$GLOBALS['bk_multisite'] = false;
		$GLOBALS['bk_site_options'] = array();
	}
} );

// F2 · Volver a 1.0.60 (que guarda en claro, sin autoload) y actualizar otra vez: ningún respaldo lleva el secreto en claro.
$case( 'F2', function() use ( $B, $C, $check, $save, $settings, $sql_of, $fields, $secrets ) {
	$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Sitio de prueba' );
	list( $ok ) = $save( $fields + $secrets );
	$rewritten = 'Clave-reescrita-en-1.0.60_9f3a';
	// Lo que hace save() de 1.0.60: los ajustes con la contraseña en claro (y el resto del sitio sin cambios).
	$GLOBALS['bk_options'][ $B::OPTION ] = array_merge( (array) get_option( $B::OPTION ), array( 'sftp_password' => $rewritten ) );
	// Antes de que nada cargue el escritorio, el cron hace un respaldo: el volcado nunca lleva el texto en claro.
	$backup = bk_backup( true );
	$sql = $sql_of( $backup );
	$check( $ok && false === strpos( $sql, $rewritten ), 'El database.sql de un respaldo lleva la contraseña reescrita en claro.' );
	$check( ! isset( get_option( $B::OPTION )['sftp_password'] ) && $rewritten === $settings()['sftp_password'], 'Antes de volcar el SQL no se cifró la contraseña reescrita.' );
	// Y ese respaldo la devuelve al restaurarlo (cifrada en el almacén que viaja).
	update_option( $B::OPTION, $fields );
	$C::put( array( 'sftp_password' => 'otra-despues' ), $settings() );
	$report = Digitalisimo_Backups_Security::open( $backup, bk_call( $B, 'history_item', basename( $backup ) ), '' );
	try { bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report ); } finally { Digitalisimo_Backups_Security::close( $report ); }
	$check( $rewritten === $settings()['sftp_password'], 'El respaldo no devuelve la contraseña reescrita.' );
	// La carga del escritorio también la cifra, sin marca que la engañe.
	$GLOBALS['bk_options'][ $B::OPTION ] = array_merge( (array) get_option( $B::OPTION ), array( 'sftp_password' => 'otra-vez-en-claro_77' ) );
	$B::credentials_on_load();
	$check( ! isset( get_option( $B::OPTION )['sftp_password'] ) && 'otra-vez-en-claro_77' === $settings()['sftp_password'], 'La carga del escritorio no cifró lo que 1.0.60 reescribió en claro.' );
	// La opción de un subsitio (sin efecto) con secretos en claro de una versión anterior: el respaldo no los lleva.
	$row = array( 'option_id' => '9', 'option_name' => $B::OPTION, 'option_value' => serialize( array( 'sftp_host' => 'viejo.example.test', 'sftp_password' => 'Clave-VIEJA-de-subsitio_dd44', 'google_client_secret' => 'secreto-viejo_ee55' ) ) );
	$exported = bk_call( $B, 'export_row', 'wp_2_options', $row );
	$check( false === strpos( $exported['option_value'], 'Clave-VIEJA' ) && false === strpos( $exported['option_value'], 'secreto-viejo' ) && false !== strpos( $exported['option_value'], 'viejo.example.test' ), 'La fila de un subsitio sale con secretos en claro.' );
	$check( $row === bk_call( $B, 'export_row', 'wp_2_posts', $row ), 'Se alteró una fila que no es de opciones.' );
} );

// F3 · Una huella del origen toda decimal (salts calculadas con hashlib de Python) no se pierde en el diario.
$case( 'F3', function() use ( $C, $check ) {
	$origin = $C::key_from( 'origen-decimal-auth-000752-0123456789', 'origen-decimal-secure-0123456789abcdef' );
	$check( '3318148515709315' === $C::key_id( $origin ), 'La huella de prueba no es la decimal calculada.' );
	$journal = json_decode( json_encode( $C::journal( array( $origin ) ) ), true );
	$keys = $C::from_journal( $journal );
	$check( array( $origin ) === array_values( $keys ), 'El diario perdió la clave del origen con huella decimal: ' . count( $keys ) . ' clave(s).' );
	$check( false === strpos( json_encode( $journal ), bin2hex( $origin ) ) && false === strpos( json_encode( $journal ), Digitalisimo_Backups_Keys::b64url_encode( $origin ) ), 'El diario guarda la clave del origen en claro.' );
} );

// F4 · Un sitio individual convertido en red conserva sus credenciales.
$case( 'F4', function() use ( $B, $C, $K, $check, $save, $settings, $secret_values, $fields, $secrets ) {
	$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Sitio de prueba' );
	$GLOBALS['bk_site_options'] = array();
	list( $ok ) = $save( $fields + $secrets );
	$check( $ok && isset( $GLOBALS['bk_options'][ $C::OPTION ] ), 'El sitio individual no guardó su almacén.' );
	$GLOBALS['bk_multisite'] = true;
	try {
		$check( $secrets === $secret_values( $settings() ), 'Tras convertir en red se perdieron las credenciales.' );
		$check( isset( $GLOBALS['bk_site_options'][ $C::OPTION ] ) && ! isset( $GLOBALS['bk_options'][ $C::OPTION ] ) && 'encrypted' === bk_call( $B, 'credentials_status' )['state'], 'El almacén del sitio no pasó a la red.' );
	} finally {
		$GLOBALS['bk_multisite'] = false;
		$GLOBALS['bk_site_options'] = array();
	}
} );

// F5 · Tope del parser: un wp-config.php de más de 64 KiB no se analiza y uno hostil no agota la memoria.
$case( 'F5', function() use ( $C, $check ) {
	$salts = "define( 'AUTH_KEY', 'salt-auth-del-origen-0123456789' );\ndefine( 'SECURE_AUTH_KEY', 'salt-secure-del-origen-0123456789' );\n";
	$hostile = "<?php\n" . str_repeat( 'a ', 150000 ) . "\n" . $salts;
	$base = memory_get_peak_usage();
	$found = $C::config_salts( $hostile );
	$grown = memory_get_peak_usage() - $base;
	$check( array() === $found['AUTH_KEY'] && $grown < 8 * 1048576, 'Un wp-config.php de ' . strlen( $hostile ) . ' bytes se analizó (memoria +' . round( $grown / 1048576 ) . ' MiB).' );
	$check( 65536 === $C::CONFIG_MAX, 'El tope del parser no es 64 KiB.' );
	$near = "<?php\n" . str_repeat( 'a ', ( 65536 - strlen( $salts ) - 8 ) >> 1 ) . "\n" . $salts;
	$found = $C::config_salts( $near );
	$check( strlen( $near ) <= 65536 && array( 'salt-auth-del-origen-0123456789' ) === $found['AUTH_KEY'] && array( 'salt-secure-del-origen-0123456789' ) === $found['SECURE_AUTH_KEY'], 'Un wp-config.php de casi 64 KiB no se leyó.' );
} );

// F6 · «Olvidar» las credenciales de un destino (también las bloqueadas) sin tocar las demás.
$case( 'F6', function() use ( $B, $C, $K, $check, $save, $settings, $panel, $fields, $secrets ) {
	$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Sitio de prueba' );
	list( $ok ) = $save( $fields + $secrets );
	$store = $K::get( $C::OPTION );
	$other = $C::key_from( 'salts-que-ya-no-estan-0123456789', 'salts-que-ya-no-estan-secure-0123' );
	$store['items']['google_refresh_token'] = $C::seal( $other, 'google_refresh_token', 'viejo', $settings() );
	$K::put( $C::OPTION, $store );
	$s = $settings();
	$locked = $C::locked( $s );
	$check( array( 'google_refresh_token' ) === $locked && 'needs_reentry' === bk_call( $B, 'credentials_status' )['state'], 'No se preparó una credencial bloqueada.' );
	$html = $panel( $s, $locked );
	$check( false !== strpos( $html, 'name="digitalisimo_backups[forget][google]"' ) && false !== strpos( $html, 'name="digitalisimo_backups[forget][sftp]"' ), 'La pantalla no ofrece olvidar las credenciales guardadas.' );
	list( $ok, $message ) = $save( $fields + array( 'forget' => array( 'google' => '1' ) ) );
	$s = $settings();
	$check( $ok && false !== strpos( $message, 'Se olvidaron las credenciales de Google Drive' ) && ! isset( $K::get( $C::OPTION )['items']['google_refresh_token'] ) && ! isset( $K::get( $C::OPTION )['items']['google_client_secret'] ), 'No se olvidaron las credenciales de Google Drive: ' . $message );
	$check( 'encrypted' === bk_call( $B, 'credentials_status' )['state'] && $secrets['sftp_password'] === $s['sftp_password'] && $secrets['onedrive_refresh_token'] === $s['onedrive_refresh_token'], 'Olvidar Google tocó los demás destinos o el aviso sigue.' );
	// Olvidar y escribir a la vez: lo escrito se guarda.
	list( $ok ) = $save( $fields + array( 'forget' => array( 'sftp' => '1' ), 'sftp_password' => 'Clave-nueva-tras-olvidar' ) );
	$check( $ok && 'Clave-nueva-tras-olvidar' === $settings()['sftp_password'], 'Al olvidar y escribir a la vez se perdió lo escrito.' );
	// Sin la casilla, un campo vacío sigue conservando lo guardado.
	list( $ok ) = $save( $fields );
	$check( $ok && $secrets['onedrive_client_secret'] === $settings()['onedrive_client_secret'], 'Un campo vacío dejó de conservar lo guardado.' );
} );

// F7 · Un almacén con objetos u otros tipos: los ajustes, el estado y la pantalla siguen funcionando y piden escribir.
$case( 'F7', function() use ( $B, $C, $K, $check, $settings, $panel ) {
	$K::put( $C::OPTION, array( 'v' => 1, 'items' => array( 'sftp_password' => array( 'kw' => new stdClass(), 'c' => 'x' ), 'google_client_secret' => array( 'kw' => 'abc', 'c' => array( 1 ) ), 'onedrive_client_secret' => 'texto' ) ) );
	$s = $settings();
	$status = $B::service_status();
	$html = $panel( $s, $C::locked( $s ) );
	$check( '' === $s['sftp_password'] && 'needs_reentry' === $status['credentials'] && false !== strpos( $status['credentials_notice'], 'contraseña de SFTP' ) && false !== strpos( $html, 'vuelve a escribirlo' ), 'Un almacén hostil no se leyó como «vuelve a escribir»: ' . json_encode( $status ) );
	$K::put( $C::OPTION, array( 'v' => 1, 'items' => new stdClass() ) );
	$check( is_array( $settings() ) && ! is_wp_error( $B::service_status() ), 'Un almacén con items que no son una lista rompió los ajustes.' );
	$K::put( $C::OPTION, new stdClass() );
	$check( is_array( $settings() ) && 'none' === $B::service_status()['credentials'], 'Un almacén que es un objeto rompió los ajustes.' );
} );

// AD · El dato adicional ata cada secreto a su conexión.
$case( 'AD', function() use ( $B, $C, $K, $check, $save, $settings, $fields, $secrets ) {
	$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Sitio de prueba' );
	list( $ok ) = $save( $fields + $secrets );
	$file = bk_storage() . '/digitalisimo-ad.zip';
	file_put_contents( $file, 'ZIP de prueba' );
	// Una escritura directa en la base de datos cambia el host SFTP: la contraseña deja de abrirse y nunca se envía.
	$GLOBALS['bk_options'][ $B::OPTION ]['sftp_host'] = 'atacante.example.test';
	$s = $settings();
	$check( '' === $s['sftp_password'] && in_array( 'sftp_password', $C::locked( $s ), true ), 'La contraseña SFTP sigue abriéndose con otro host.' );
	try { bk_call( $B, 'job_transfer', $file, 'sftp' ); throw new LogicException( 'SFTP transfirió con otro host.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'vuelve a escribir la contraseña de SFTP' ), 'El error no pide volver a escribir la contraseña: ' . $e->getMessage() ); }
	// Volver a dejar el host abre otra vez (nada se perdió); cambiarlo desde el formulario lo vuelve a atar sin escribirlo.
	$GLOBALS['bk_options'][ $B::OPTION ]['sftp_host'] = 'sftp.red.test';
	$check( $secrets['sftp_password'] === $settings()['sftp_password'], 'Con el host original la contraseña no volvió a abrirse.' );
	list( $ok ) = $save( array( 'sftp_host' => 'sftp.nuevo.test' ) + $fields );
	$s = $settings();
	$check( $ok && 'sftp.nuevo.test' === $s['sftp_host'] && $secrets['sftp_password'] === $s['sftp_password'] && isset( bk_call( $B, 'manual_destinations', $s )['sftp'] ), 'Cambiar el host desde el formulario perdió la contraseña.' );
	// El Client ID ata los secretos de Google Drive.
	$GLOBALS['bk_options'][ $B::OPTION ]['google_client_id'] = 'cliente-del-atacante';
	$s = $settings();
	$check( '' === $s['google_client_secret'] && '' === $s['google_refresh_token'] && $secrets['onedrive_client_secret'] === $s['onedrive_client_secret'], 'Los secretos de Google siguen abriéndose con otro Client ID.' );
	@unlink( $file );
} );

bk_cleanup();
if ( $failures ) throw new RuntimeException( 'Revisión de las credenciales: fallaron ' . implode( ', ', $failures ) . '.' );
echo 'DIGITALÍSIMO Backups: credenciales de los destinos endurecidas tras la revisión (' . implode( ', ', $done ) . ").\n";
