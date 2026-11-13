<?php
/**
 * Quien restaura en otro sitio sigue entrando (incidente del 2026-11-12: el sitio 1 de la red de digitalisimo.mx
 * extraído y restaurado en /wp dejó fuera a quien lo restauró).
 *
 *   a · la decisión: sólo al reubicar, con un sitio extraído o en una migración, encendido por defecto, nunca en el mismo
 *       sitio (tras un hackeo no se reviven las credenciales de ahora) ni en la vuelta a la copia previa;
 *   b · el caso del incidente: el paquete trae «digitalisimo» (su contraseña de aplicación y la ruta privada de la red) y
 *       no trae «admin». Después: «admin» existe con su mismo hash, sus sesiones y su contraseña de aplicación, como
 *       administrador; «digitalisimo» sigue igual; `using_application_passwords` queda activo (sin él WordPress no mira
 *       ninguna contraseña de aplicación: la causa del 401 `rest_not_logged_in`); la ruta de acceso es la de este sitio
 *       (wp-login.php) y el informe dice la URL efectiva sin la ruta del respaldo ni ningún secreto;
 *   c · aplicarlo dos veces (el diario tras una muerte) deja lo mismo;
 *   d · el paquete tiene el mismo usuario sin ser administrador: esa cuenta pasa a ser la de quien restaura (mismo ID, así
 *       la autoría no cambia) y sus contraseñas de aplicación se retiran (ganarían permisos de administrador);
 *   e · el paquete tiene el mismo usuario y era administrador: se conservan las suyas junto a las de quien restaura;
 *   f · la ruta privada de este sitio se conserva aunque el respaldo no traiga opciones de Tools ni de SEO;
 *   g · el diario lleva todo cifrado con la clave de este servidor (ningún hash en claro) y lo alterado no abre;
 *   h · la ficha de lectura: sólo la suya (o la heredada), sin caducar;
 *   i · el motor: una restauración con reubicación por service_restore() conserva la cuenta y su informe lo dice; la
 *       misma restauración con keep_access = false no; restaurar en el mismo sitio no la toca; y si el proceso muere
 *       justo después del cambio de tablas, el diario la repone igual;
 *   k · tras la puerta, el plugin sólo responde a GET /jobs/<id> (503 a todo lo demás) y la ficha en la URL es un 400;
 *   j · la puerta del mantenimiento deja pasar la ficha sólo para leer el estado de su trabajo (al final: define
 *       WordPress mínimo para el resto del proceso).
 * Con el código anterior fallan b (no hay cuenta, ni `using_application_passwords`, y la ruta es la del respaldo), c–i
 * y j (la puerta no conoce fichas). Base de datos SQLite de verdad (tests/lib/backups-sqlite.php). Escribe «FALLA ·».
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['ka_user'] = 0;
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) $GLOBALS['ka_user']; } }
// Como WordPress: en un sitio individual, la opción «de red» es la del sitio.
if ( ! function_exists( 'get_main_network_id' ) ) { function get_main_network_id() { return 1; } }
if ( ! function_exists( 'get_network_option' ) ) { function get_network_option( $id, $key, $default = false ) { return is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default ); } }
if ( ! function_exists( 'update_network_option' ) ) { function update_network_option( $id, $key, $value ) { return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value ); } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );

$A = 'Digitalisimo_Backups_Restore_Access';
// Con el código anterior la clase no existe: sus casos fallan diciéndolo y los del motor (i, j) corren igual.
$GLOBALS['ka_have'] = class_exists( $A );
$failures = 0;
function ka_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function ka_case( $label, callable $test, $engine = false ) {
	try {
		if ( ! $engine && ! $GLOBALS['ka_have'] ) throw new RuntimeException( 'No existe Digitalisimo_Backups_Restore_Access (código anterior al arreglo).' );
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
/** Tablas de usuarios y opciones de un WordPress con $prefix (los usuarios con el ID que se pida). */
function ka_db( $name, $prefix ) {
	@unlink( $GLOBALS['bk_root'] . '-' . $name . '.sqlite' );
	$db = new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-' . $name . '.sqlite', $prefix );
	ka_schema( $db, $prefix );
	return $db;
}
function ka_schema( Bk_Sqlite_DB $db, $prefix ) {
	$db->pdo->exec( "CREATE TABLE `{$prefix}users` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60) NOT NULL DEFAULT '', `user_pass` varchar(255) NOT NULL DEFAULT '', `user_nicename` varchar(50) NOT NULL DEFAULT '', `user_email` varchar(100) NOT NULL DEFAULT '', `user_url` varchar(100) NOT NULL DEFAULT '', `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `user_activation_key` varchar(255) NOT NULL DEFAULT '', `user_status` int(11) NOT NULL DEFAULT 0, `display_name` varchar(250) NOT NULL DEFAULT '', `spam` tinyint(2) NOT NULL DEFAULT 0, `deleted` tinyint(2) NOT NULL DEFAULT 0)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}usermeta` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
}
function ka_user( Bk_Sqlite_DB $db, $prefix, $id, $login, $pass, $email, array $meta ) {
	$db->seed( $prefix . 'users', array( array( 'ID' => $id, 'user_login' => $login, 'user_pass' => $pass, 'user_nicename' => $login, 'user_email' => $email, 'display_name' => ucfirst( $login ) ) ) );
	foreach ( $meta as $key => $value ) $db->seed( $prefix . 'usermeta', array( array( 'user_id' => $id, 'meta_key' => $key, 'meta_value' => $value ) ) );
}
function ka_meta( Bk_Sqlite_DB $db, $prefix, $id, $key ) {
	$rows = $db->rows( $prefix . 'usermeta', 'user_id = ' . (int) $id . " AND meta_key = '" . $key . "'" );
	return $rows ? $rows[0]['meta_value'] : null;
}
function ka_app( $uuid, $name ) { return array( 'uuid' => $uuid, 'app_id' => '', 'name' => $name, 'password' => '$generic$' . hash( 'sha256', $uuid ), 'created' => 1762900000, 'last_used' => null, 'last_ip' => null ); }
function ka_sessions( $verifier ) { return serialize( array( $verifier => array( 'expiration' => time() + 86400, 'ip' => '127.0.0.1', 'ua' => 'prueba', 'login' => time() ) ) ); }
function ka_options( Bk_Sqlite_DB $db, $prefix, array $rows ) { foreach ( $rows as $name => $value ) $db->seed( $prefix . 'options', array( array( 'option_name' => $name, 'option_value' => is_scalar( $value ) ? (string) $value : serialize( $value ) ) ) ); }

// El destino: /wp con prefijo wpkx_, el usuario «admin» con su contraseña de aplicación y su sesión.
const KA_HASH = '$wp$2y$10$destinoDestinoDestinoDestinoDestinoDestinoDestinoDe';
const KA_RED = '$P$Bred.red.red.red.red.red.red.';
$destination = function() {
	$db = ka_db( 'destino', 'wpkx_' );
	ka_user( $db, 'wpkx_', 1, 'admin', KA_HASH, 'dueno@ejemplo.test', array( 'wpkx_capabilities' => serialize( array( 'administrator' => true ) ), 'wpkx_user_level' => '10', 'nickname' => 'admin', 'locale' => 'es_MX', 'session_tokens' => ka_sessions( str_repeat( 'a', 64 ) ), '_application_passwords' => serialize( array( ka_app( 'u-admin', 'Cliente REST' ) ) ) ) );
	ka_options( $db, 'wpkx_', array( 'active_plugins' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' ) ) ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options'] = array( 'home' => 'https://digitalisimo.mx/wp', 'siteurl' => 'https://digitalisimo.mx/wp', 'using_application_passwords' => true );
	return $db;
};
/** Lo que deja el cambio de tablas con el paquete extraído del sitio 1 de la red: «digitalisimo» y la ruta privada de la red. */
$package = function( Bk_Sqlite_DB $db, array $users = array() ) {
	foreach ( array( 'users', 'usermeta', 'options' ) as $table ) $db->pdo->exec( 'DROP TABLE `wpkx_' . $table . '`' );
	ka_schema( $db, 'wpkx_' );
	ka_user( $db, 'wpkx_', 1, 'digitalisimo', KA_RED, 'dueno@ejemplo.test', array( 'wpkx_capabilities' => serialize( array( 'administrator' => true ) ), 'wpkx_user_level' => '10', '_application_passwords' => serialize( array( ka_app( 'u-red', 'MCP de la red' ) ) ) ) );
	foreach ( $users as $user ) ka_user( $db, 'wpkx_', $user[0], $user[1], $user[2], $user[3], $user[4] );
	ka_options( $db, 'wpkx_', array( 'active_plugins' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) ) ) );
	// get_option() de la prueba lee $GLOBALS['bk_options']: las opciones del paquete (sin using_application_passwords, que
	// en la red vivía en sitemeta).
	$GLOBALS['bk_options'] = array( 'home' => 'https://digitalisimo.mx/wp', 'siteurl' => 'https://digitalisimo.mx/wp', 'permalink_structure' => '/%postname%/', 'digitalisimo_tools_site_options' => array( 'values' => array( 'login_slug' => 'acceso-red', 'redirect_attachments' => 1 ), 'inherit' => array( 'login_slug' => true ) ), 'digitalisimo_integrations_options' => array( 'seo_hide_login_slug' => 'acceso-red', 'seo_title' => 'x' ) );
};
$capture = function( $user_id = 1 ) use ( $A ) {
	return $A::seal( array( 'user' => $A::capture( $user_id ), 'login' => $A::login_capture(), 'salts' => $A::salts_capture() ) );
};

ka_case( 'a · se conserva sólo con un respaldo de otra instalación, encendido por defecto, y nunca con uno de esta misma ni en la vuelta a la copia previa', function() use ( $A ) {
	ka_check( 'on' === $A::decide( true, 5, true, false ), 'Con un respaldo de otra instalación y usuario no se conserva.' );
	ka_check( 'same_site' === $A::decide( true, 5, false, false ), 'Con un respaldo de esta misma instalación se conservaría el acceso de ahora (credenciales que pueden estar comprometidas).' );
	ka_check( 'off' === $A::decide( false, 5, true, false ), 'Apagado no se respeta.' );
	ka_check( 'no_user' === $A::decide( true, 0, true, false ), 'Sin usuario (WP-CLI sin --user) no se dice.' );
	ka_check( 'rollback' === $A::decide( true, 5, true, true ), 'La vuelta a la copia previa conservaría acceso.' );
} );

ka_case( 'b · el caso de /wp: «admin» sigue entrando, la contraseña de aplicación de «digitalisimo» vale y la ruta es la de este sitio', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	// Un wp-config.php sin LOGGED_IN_KEY ni LOGGED_IN_SALT: WordPress las guarda en la base (la cookie depende de ellas).
	$GLOBALS['bk_site_options']['logged_in_key'] = 'sal-del-destino-0123456789';
	$GLOBALS['bk_site_options']['logged_in_salt'] = 'otra-sal-del-destino-0123456789';
	$sealed = $capture( 1 );
	$package( $db );
	$GLOBALS['bk_site_options']['logged_in_key'] = 'sal-del-origen';
	unset( $GLOBALS['bk_site_options']['logged_in_salt'] );
	ka_check( 'acceso-red' === $A::effective_slug(), 'La prueba necesita la ruta privada de la red en el paquete.' );
	$report = $A::apply( 'on', $A::open( $sealed ), true );
	$admin = $db->rows( 'wpkx_users', "user_login = 'admin'" );
	ka_check( 1 === count( $admin ), 'La cuenta de quien restauró no existe tras restaurar.' );
	$id = (int) $admin[0]['ID'];
	ka_check( 2 === $id && KA_HASH === $admin[0]['user_pass'] && 'dueno@ejemplo.test' === $admin[0]['user_email'], 'La cuenta no tiene su ID libre, su hash o su correo: ' . json_encode( array( $id, $admin[0]['user_email'] ) ) );
	ka_check( KA_RED === $db->value( 'wpkx_users', 'user_pass', 'ID = 1' ) && 'digitalisimo' === $db->value( 'wpkx_users', 'user_login', 'ID = 1' ), '«digitalisimo» del paquete cambió.' );
	ka_check( array( 'administrator' => true ) === unserialize( (string) ka_meta( $db, 'wpkx_', $id, 'wpkx_capabilities' ) ) && '10' === ka_meta( $db, 'wpkx_', $id, 'wpkx_user_level' ), '«admin» no quedó como administrador con el prefijo de este sitio.' );
	ka_check( ka_sessions( str_repeat( 'a', 64 ) ) !== null && array_keys( (array) unserialize( (string) ka_meta( $db, 'wpkx_', $id, 'session_tokens' ) ) ) === array( str_repeat( 'a', 64 ) ), 'Las sesiones de «admin» no volvieron (la cookie de su navegador dejaría de valer).' );
	$apps = (array) unserialize( (string) ka_meta( $db, 'wpkx_', $id, '_application_passwords' ) );
	ka_check( 1 === count( $apps ) && 'u-admin' === $apps[0]['uuid'], 'La contraseña de aplicación de «admin» no volvió: ' . json_encode( $apps ) );
	ka_check( 'es_MX' === ka_meta( $db, 'wpkx_', $id, 'locale' ), 'El perfil de la cuenta creada no viajó.' );
	ka_check( 'sal-del-destino-0123456789' === $GLOBALS['bk_site_options']['logged_in_key'] && 'otra-sal-del-destino-0123456789' === $GLOBALS['bk_site_options']['logged_in_salt'], 'Las claves de la base de este sitio no volvieron (la cookie de «admin» dejaría de valer).' );
	ka_check( false === strpos( json_encode( $report ), 'sal-del-destino' ), 'El informe lleva una clave.' );
	ka_check( true === ( $GLOBALS['bk_options']['using_application_passwords'] ?? null ), 'using_application_passwords no quedó activo: WordPress no comprobaría ninguna contraseña de aplicación (401 rest_not_logged_in).' );
	ka_check( ! empty( $report['app_passwords']['enabled_here'] ) && 2 === (int) $report['app_passwords']['users_with'], 'El informe no dice que las contraseñas de aplicación se activaron: ' . json_encode( $report['app_passwords'] ) );
	ka_check( '' === $A::effective_slug() && '' === $GLOBALS['bk_options']['digitalisimo_tools_site_options']['values']['login_slug'] && '' === $GLOBALS['bk_options']['digitalisimo_integrations_options']['seo_hide_login_slug'], 'La ruta privada de la red quedó (wp-login.php daría 404).' );
	ka_check( 1 === $GLOBALS['bk_options']['digitalisimo_tools_site_options']['values']['redirect_attachments'] && 'x' === $GLOBALS['bk_options']['digitalisimo_integrations_options']['seo_title'], 'Se perdieron otros ajustes de Tools o SEO.' );
	ka_check( 'created' === $report['account'] && 'admin' === $report['user'] && 'standard' === $report['login_path']['kept'] && ! empty( $report['login_path']['backup_differed'] ), 'El informe no dice qué pasó: ' . json_encode( $report ) );
	ka_check( array( 'digitalisimo' ) === ( $report['shared_email'] ?? null ), 'El informe no avisa del correo compartido con «digitalisimo».' );
	ka_check( in_array( 'admin', $report['admins'], true ) && in_array( 'digitalisimo', $report['admins'], true ), 'El informe no dice quién puede entrar: ' . json_encode( $report['admins'] ) );
	$text = (string) $report['text'];
	ka_check( 0 === strpos( $text, 'Cómo entrar ahora: ' ) && false !== strpos( $text, '«admin»' ), 'El texto no empieza por cómo entrar: ' . $text );
	ka_check( false === strpos( json_encode( $report ), 'acceso-red' ) && false === strpos( json_encode( $report ), KA_HASH ) && false === strpos( json_encode( $report ), '$generic$' ), 'El informe lleva la ruta del respaldo o un secreto.' );
} );

ka_case( 'c · aplicarlo otra vez (el diario tras una muerte) deja lo mismo', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	$sealed = $capture( 1 );
	$package( $db );
	$A::apply( 'on', $A::open( $sealed ), true );
	$second = $A::apply( 'on', $A::open( $sealed ), true );
	ka_check( 1 === count( $db->rows( 'wpkx_users', "user_login = 'admin'" ) ) && 2 === count( $db->rows( 'wpkx_users' ) ), 'Repetirlo creó otra cuenta.' );
	ka_check( 1 === count( $db->rows( 'wpkx_usermeta', "user_id = 2 AND meta_key = '_application_passwords'" ) ) && 1 === count( (array) unserialize( (string) ka_meta( $db, 'wpkx_', 2, '_application_passwords' ) ) ), 'Repetirlo duplicó metas o contraseñas.' );
	ka_check( 0 === (int) ( $second['app_passwords']['dropped_from_backup'] ?? 0 ), 'Repetirlo retiró contraseñas propias: ' . json_encode( $second['app_passwords'] ) );
} );

ka_case( 'd · M1: el mismo usuario en el paquete sin ser administrador conserva todo y cambia de usuario; la de quien restaura se crea aparte', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	$sealed = $capture( 1 );
	$package( $db, array( array( 7, 'admin', '$P$suscriptor', 'otra@ejemplo.test', array( 'wpkx_capabilities' => serialize( array( 'subscriber' => true ) ), '_application_passwords' => serialize( array( ka_app( 'u-ajena', 'Ajena' ) ) ), 'session_tokens' => ka_sessions( str_repeat( 'b', 64 ) ), '_two_factor_totp_key' => 'SECRETO-DEL-CLIENTE', 'nsl_google_id' => 'google-del-cliente' ) ) ) );
	$report = $A::apply( 'on', $A::open( $sealed ), true );
	$admin = $db->rows( 'wpkx_users', "user_login = 'admin'" );
	ka_check( 1 === count( $admin ) && 8 === (int) $admin[0]['ID'] && KA_HASH === $admin[0]['user_pass'] && 'dueno@ejemplo.test' === $admin[0]['user_email'], 'La cuenta de quien restaura no se creó aparte con su usuario: ' . json_encode( $admin ) );
	$client = $db->rows( 'wpkx_users', 'ID = 7' )[0];
	ka_check( 'admin-respaldo' === $client['user_login'] && '$P$suscriptor' === $client['user_pass'] && 'otra@ejemplo.test' === $client['user_email'] && 'admin' === $client['user_nicename'], 'La cuenta del paquete no conservó todo salvo su usuario: ' . json_encode( $client ) );
	ka_check( array( 'subscriber' => true ) === unserialize( (string) ka_meta( $db, 'wpkx_', 7, 'wpkx_capabilities' ) ) && 'SECRETO-DEL-CLIENTE' === ka_meta( $db, 'wpkx_', 7, '_two_factor_totp_key' ) && 'google-del-cliente' === ka_meta( $db, 'wpkx_', 7, 'nsl_google_id' ), 'La cuenta del paquete cambió de rol o perdió su segundo factor o su inicio social.' );
	ka_check( array( 'u-ajena' ) === array_column( (array) unserialize( (string) ka_meta( $db, 'wpkx_', 7, '_application_passwords' ) ), 'uuid' ) && array( 'u-admin' ) === array_column( (array) unserialize( (string) ka_meta( $db, 'wpkx_', 8, '_application_passwords' ) ), 'uuid' ), 'Se mezclaron las contraseñas de aplicación de las dos cuentas.' );
	ka_check( null === ka_meta( $db, 'wpkx_', 8, '_two_factor_totp_key' ) && null === ka_meta( $db, 'wpkx_', 8, 'nsl_google_id' ) && array( str_repeat( 'a', 64 ) ) === array_keys( (array) unserialize( (string) ka_meta( $db, 'wpkx_', 8, 'session_tokens' ) ) ), 'La cuenta de quien restaura heredó credenciales del paquete.' );
	ka_check( 'renamed' === $report['account'] && 'admin-respaldo' === ( $report['renamed'] ?? '' ) && false !== strpos( $report['text'], '«admin-respaldo»' ), 'El informe no dice que la cuenta del paquete cambió de usuario: ' . json_encode( $report ) );
} );

ka_case( 'd · B1: una muerte a mitad sigue la decisión anotada en el diario y nunca suma contraseñas ajenas ni renombra dos veces', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	$entry = array( 'v' => 1, 'job' => '', 'state' => 'on', 'relocated' => true, 'secret' => $capture( 1 ) );
	$property = new ReflectionProperty( 'Digitalisimo_Backups', 'access_kept' );
	if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
	$property->setValue( null, $entry );
	bk_call( 'Digitalisimo_Backups', 'write_restore_journal' );
	$property->setValue( null, null );
	$package( $db, array( array( 7, 'admin', '$P$suscriptor', 'otra@ejemplo.test', array( 'wpkx_capabilities' => serialize( array( 'subscriber' => true ) ), '_application_passwords' => serialize( array( ka_app( 'u-ajena', 'Ajena' ) ) ) ) ) ) );
	// La primera pasada muere (una sentencia falla) tras dejar la cuenta nueva sin su rol.
	$db->fail_on = 'wpkx_capabilities';
	bk_call( 'Digitalisimo_Backups', 'restore_access_apply', $entry );
	$journal = json_decode( (string) file_get_contents( bk_storage() . '/' . Digitalisimo_Backups::RESTORE_JOURNAL ), true );
	ka_check( is_array( $journal['access']['plan'] ?? null ) && false === strpos( json_encode( $journal['access']['plan'] ), 'u-admin' ), 'La decisión sobre la cuenta no quedó anotada (cifrada) en el diario antes de cambiar nada.' );
	// La segunda pasada, la del diario.
	$entry['plan'] = $journal['access']['plan'];
	bk_call( 'Digitalisimo_Backups', 'restore_access_apply', $entry );
	bk_call( 'Digitalisimo_Backups', 'remove_restore_journal' );
	$logins = array_column( $db->rows( 'wpkx_users' ), 'user_login', 'ID' );
	ka_check( array( 1 => 'digitalisimo', 7 => 'admin-respaldo', 8 => 'admin' ) === $logins, 'La segunda pasada renombró o creó otra vez: ' . json_encode( $logins ) );
	ka_check( array( 'u-admin' ) === array_column( (array) unserialize( (string) ka_meta( $db, 'wpkx_', 8, '_application_passwords' ) ), 'uuid' ) && array( 'administrator' => true ) === unserialize( (string) ka_meta( $db, 'wpkx_', 8, 'wpkx_capabilities' ) ), 'La segunda pasada sumó contraseñas ajenas o no terminó: ' . json_encode( array( ka_meta( $db, 'wpkx_', 8, '_application_passwords' ) ) ) );
	ka_check( array( 'subscriber' => true ) === unserialize( (string) ka_meta( $db, 'wpkx_', 7, 'wpkx_capabilities' ) ), 'La cuenta del paquete ganó permisos.' );
} );

ka_case( 'e · el mismo usuario en el paquete y administrador: fusión con sus contraseñas de aplicación y sin marcas de bloqueo (B9)', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	$sealed = $capture( 1 );
	$package( $db, array( array( 7, 'admin', '$P$otroadmin', 'dueno@ejemplo.test', array( 'wpkx_capabilities' => serialize( array( 'administrator' => true ) ), '_application_passwords' => serialize( array( ka_app( 'u-suya', 'Suya' ), ka_app( 'u-admin', 'Misma' ) ) ) ) ) ) );
	$db->pdo->exec( 'UPDATE wpkx_users SET user_status = 1, spam = 1, deleted = 1 WHERE ID = 7' );
	$report = $A::apply( 'on', $A::open( $sealed ), true );
	$uuids = array_column( (array) unserialize( (string) ka_meta( $db, 'wpkx_', 7, '_application_passwords' ) ), 'uuid' );
	sort( $uuids );
	ka_check( 'merged' === $report['account'] && 7 === (int) $report['user_id'] && array( 'u-admin', 'u-suya' ) === $uuids && 1 === (int) $report['app_passwords']['kept_from_backup'], 'No se fusionó con sus contraseñas de aplicación sin repetir: ' . json_encode( $uuids ) );
	$row = $db->rows( 'wpkx_users', 'ID = 7' )[0];
	ka_check( 0 === (int) $row['user_status'] && 0 === (int) $row['spam'] && 0 === (int) $row['deleted'] && KA_HASH === $row['user_pass'], 'La cuenta fusionada quedó bloqueada (user_status, spam o deleted): ' . json_encode( $row ) );
} );

ka_case( 'f · la ruta privada de este sitio se conserva aunque el respaldo no traiga opciones de Tools ni de SEO', function() use ( $A, $destination, $package ) {
	$db = $destination();
	$db->pdo->exec( "UPDATE wpkx_options SET option_value = '" . serialize( array( 'digitalisimo-tools/digitalisimo-tools.php' ) ) . "' WHERE option_name = 'active_plugins'" );
	$GLOBALS['bk_options']['digitalisimo_tools_site_options'] = array( 'values' => array( 'login_slug' => 'mi-acceso' ), 'inherit' => array() );
	ka_check( 'mi-acceso' === $A::effective_slug(), 'La prueba necesita la ruta privada de este sitio.' );
	$sealed = $A::seal( array( 'user' => null, 'login' => $A::login_capture() ) );
	$package( $db );
	unset( $GLOBALS['bk_options']['digitalisimo_tools_site_options'], $GLOBALS['bk_options']['digitalisimo_integrations_options'] );
	$report = $A::apply( 'off', $A::open( $sealed ), true );
	ka_check( 'mi-acceso' === $A::effective_slug(), 'La ruta privada de este sitio no se conservó: ' . var_export( $A::effective_slug(), true ) );
	ka_check( 'https://example.test/mi-acceso/' === $report['login_url'] && 'private' === $report['login_path']['kept'], 'El informe no dice la URL de acceso efectiva: ' . $report['login_url'] );
	ka_check( 'off' === $report['state'] && 1 === count( $db->rows( 'wpkx_users' ) ), 'Apagado se tocó alguna cuenta.' );
	define( 'DIGITALISIMO_HIDE_LOGIN_DISABLE', true );
	ka_check( '' === $A::effective_slug() && 'mi-acceso' === $A::effective_slug( false ), 'La salida de emergencia no cuenta para la URL efectiva.' );
} );

ka_case( 'g · el diario lo lleva cifrado con la clave de este servidor y lo alterado no abre', function() use ( $A, $destination, $capture ) {
	$destination();
	$sealed = $capture( 1 );
	$json = json_encode( $sealed );
	ka_check( 1 === ( $sealed['v'] ?? null ), 'Con AUTH_KEY y SECURE_AUTH_KEY propias no se cifró.' );
	$GLOBALS['bk_site_options']['nonce_salt'] = 'sal-de-nonces-0123456789';
	$sealed = $capture( 1 );
	$json = json_encode( $sealed );
	foreach ( array( KA_HASH, 'u-admin', 'dueno@ejemplo.test', str_repeat( 'a', 64 ), 'sal-de-nonces-0123456789' ) as $secret ) ka_check( false === strpos( $json, $secret ), 'El diario lleva en claro: ' . substr( $secret, 0, 12 ) );
	unset( $GLOBALS['bk_site_options']['nonce_salt'] );
	ka_check( KA_HASH === $A::open( $sealed )['user']['user']['user_pass'], 'Lo sellado no abre.' );
	$bad = $sealed;
	$bad['box'] = substr( $bad['box'], 0, -4 ) . 'AAAA';
	ka_check( null === $A::open( $bad ), 'Abrió un diario alterado.' );
} );

ka_case( 'h · la ficha de lectura: sólo la suya o la heredada, y caduca', function() use ( $A ) {
	$reader = $A::new_reader();
	$other = $A::new_reader();
	$job = array( 'created_at' => time(), 'finished_at' => 0, 'spec' => array( 'reader' => array( 'hash' => $reader['hash'], 'issued' => time() ), 'readers' => array( $other['hash'] ) ) );
	ka_check( $A::reader_ok( $job, $reader['token'] ) && $A::reader_ok( $job, $other['token'] ), 'La ficha propia o la heredada no sirven.' );
	ka_check( ! $A::reader_ok( $job, $A::new_reader()['token'] ) && ! $A::reader_ok( $job, $reader['hash'] ) && ! $A::reader_ok( $job, '' ), 'Sirve una ficha ajena o su huella.' );
	$job['finished_at'] = time() - 90000;
	ka_check( ! $A::reader_ok( $job, $reader['token'] ), 'La ficha sigue sirviendo 25 h después de terminar.' );
	$job['finished_at'] = 0;
	$job['spec']['reader']['issued'] = time() - 8 * 86400;
	ka_check( ! $A::reader_ok( $job, $reader['token'] ), 'La ficha sigue sirviendo después de 7 días.' );
	ka_check( 64 === strlen( $reader['hash'] ) && false === strpos( json_encode( $job ), $reader['token'] ), 'El trabajo guarda la ficha en claro.' );
} );

/* ------------------------------------------------------------------------------------------------- i · el motor */

$GLOBALS['bk_options'] = array( 'home' => 'https://origen.test', 'siteurl' => 'https://origen.test', 'blogname' => 'Origen' );
$origin = ka_db( 'origen', 'wp_' );
ka_user( $origin, 'wp_', 1, 'digitalisimo', KA_RED, 'red@ejemplo.test', array( 'wp_capabilities' => serialize( array( 'administrator' => true ) ), 'wp_user_level' => '10', '_application_passwords' => serialize( array( ka_app( 'u-red', 'MCP' ) ) ) ) );
ka_options( $origin, 'wp_', array( 'siteurl' => 'https://origen.test', 'home' => 'https://origen.test', 'blogname' => 'Origen', 'active_plugins' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' ) ) ) );
$GLOBALS['wpdb'] = $origin;
$file = basename( bk_backup() );
// Con su fila y la identidad de esta instalación (H23): un respaldo de este mismo sitio.
$own = basename( bk_backup( true ) );
$engine = function( $keep, $home = 'https://digitalisimo.mx/wp', $prefix = 'wpkx_', $backup = null, $drive = true ) use ( $file ) {
	$db = ka_db( 'motor-' . $prefix . md5( $home . (string) $backup . (string) $keep . (string) $drive ), $prefix );
	ka_user( $db, $prefix, 1, 'admin', KA_HASH, 'dueno@ejemplo.test', array( $prefix . 'capabilities' => serialize( array( 'administrator' => true ) ), '_application_passwords' => serialize( array( ka_app( 'u-admin', 'Cliente REST' ) ) ) ) );
	ka_options( $db, $prefix, array( 'siteurl' => $home, 'home' => $home, 'blogname' => 'Destino' ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $home;
	$GLOBALS['bk_options']['siteurl'] = $home;
	$GLOBALS['ka_user'] = 1;
	// Las opciones que deja el SQL de un sitio extraído no traen using_application_passwords (vivía en sitemeta).
	unset( $GLOBALS['bk_options']['using_application_passwords'] );
	$args = array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => false, 'sync' => true, 'drive_cli' => $drive );
	if ( null !== $keep ) $args['keep_access'] = $keep;
	$result = Digitalisimo_Backups::service_restore( null === $backup ? $file : $backup, $args );
	$GLOBALS['ka_user'] = 0;
	if ( $drive ) ka_check( ! is_wp_error( $result ) && ! empty( $result['restored'] ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	return array( $db, $result );
};
/** Un trabajo de restauración con su tables.json: 'after' (las temporales ya no están: el cambio se hizo) o 'before'. */
function ka_job_tables( Bk_Sqlite_DB $db, $state ) {
	$id = Digitalisimo_Backups_Job_Store::new_id();
	$tag = substr( md5( $id ), 0, 6 );
	$row = array( 'source' => 'wp_users', 'dest' => 'wpkx_users', 'temp' => 'wpkx_dgbkr_' . $tag . '_000', 'old' => 'wpkx_dgbko_' . $tag . '_000' );
	if ( 'before' === $state ) $db->pdo->exec( 'CREATE TABLE `' . $row['temp'] . '` (`ID` INTEGER PRIMARY KEY)' );
	file_put_contents( Digitalisimo_Backups_Job_Store::staging( $id ) . '/tables.json', json_encode( array( $row ) ) );
	return $id;
}
ka_case( 'i · el motor conserva la cuenta con un respaldo de otra instalación (por defecto) y no con keep_access = false', function() use ( $engine, $file ) {
	list( $db, $result ) = $engine( null );
	$admin = $db->rows( 'wpkx_users', "user_login = 'admin'" );
	ka_check( 1 === count( $admin ) && KA_HASH === $admin[0]['user_pass'], 'Tras restaurar en otro sitio, «admin» no existe o no tiene su contraseña.' );
	ka_check( 'digitalisimo' === $db->value( 'wpkx_users', 'user_login', 'ID = 1' ), 'El usuario del respaldo no está.' );
	ka_check( true === ( $GLOBALS['bk_options']['using_application_passwords'] ?? null ), 'Tras restaurar, using_application_passwords no está activo: las contraseñas de aplicación del respaldo responderían 401 rest_not_logged_in.' );
	$access = (array) ( $result['access'] ?? array() );
	ka_check( ! empty( $access['kept'] ) && 'admin' === $access['user'] && ! empty( $access['relocated'] ), 'La respuesta no dice que se conservó el acceso: ' . json_encode( $access ) );
	ka_check( 0 === strpos( (string) $result['notice'], 'Cómo entrar ahora: ' ), 'El aviso no empieza por cómo entrar: ' . substr( (string) $result['notice'], 0, 200 ) );
	ka_check( is_array( $result['follow'] ?? null ) && 48 === strlen( (string) $result['follow']['token'] ) && 'X-Digitalisimo-Backups-Job-Token' === $result['follow']['header'], 'La respuesta no entrega la ficha de lectura.' );
	ka_check( Digitalisimo_Backups::service_job_reader_ok( $result['follow']['job'], $result['follow']['token'] ), 'La ficha no lee su trabajo.' );
	$view = Digitalisimo_Backups::service_job_follow( $result['follow']['job'], $result['follow']['token'] );
	ka_check( ! is_wp_error( $view ) && 'success' === $view['status'] && ! empty( $view['restore']['result']['access']['kept'] ), 'Con la ficha no se lee el estado final con su informe.' );
	ka_check( ! file_exists( bk_storage() . '/' . Digitalisimo_Backups::RESTORE_JOURNAL ), 'Quedó el diario.' );
	ka_check( false === strpos( json_encode( $result ), KA_HASH ), 'La respuesta lleva un hash.' );
	list( $db, $result ) = $engine( false, 'https://otro.test' );
	ka_check( 0 === count( $db->rows( 'wpkx_users', "user_login = 'admin'" ) ) && 'off' === ( $result['access']['state'] ?? '' ), 'Con keep_access = false se conservó la cuenta.' );
}, true );

ka_case( 'i · B6: «mismo sitio» es la identidad de la instalación, no la dirección ni el prefijo', function() use ( $engine, $own ) {
	// Un respaldo propio (su fila con la identidad de aquí) restaurado con otro prefijo: mismo sitio, no se conserva nada.
	list( $db, $result ) = $engine( true, 'https://origen.test', 'wpkx_', $own );
	ka_check( 0 === count( $db->rows( 'wpkx_users', "user_login = 'admin'" ) ) && 'same_site' === ( $result['access']['state'] ?? '' ), 'Un respaldo de esta instalación restaurado con otro prefijo conservó la cuenta de ahora: ' . json_encode( $result['access'] ?? null ) );
	// Uno de otra instalación (sin fila) en la misma dirección y con el mismo prefijo (otro servidor): se conserva.
	list( $db, $result ) = $engine( true, 'https://origen.test', 'wp_' );
	ka_check( 1 === count( $db->rows( 'wp_users', "user_login = 'admin'" ) ) && 'on' === ( $result['access']['state'] ?? '' ), 'Un respaldo de otra instalación en la misma dirección dejó fuera a quien restaura: ' . json_encode( $result['access'] ?? null ) );
	// Y el propio en la misma dirección y prefijo: mismo sitio.
	list( $db, $result ) = $engine( true, 'https://origen.test', 'wp_', $own );
	ka_check( 0 === count( $db->rows( 'wp_users', "user_login = 'admin'" ) ) && 'same_site' === ( $result['access']['state'] ?? '' ), 'El respaldo propio conservó la cuenta de ahora.' );
}, true );

ka_case( 'i · B8: en la web, la respuesta con la ficha sale pronto y la restauración sigue por ticks', function() use ( $engine ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = 0;
	try {
		list( $db, $result ) = $engine( null, 'https://lento.test', 'wpkx_', null, false );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] );
	}
	ka_check( ! is_wp_error( $result ) && ! empty( $result['restoring'] ) && empty( $result['restored'] ) && 48 === strlen( (string) ( $result['follow']['token'] ?? '' ) ), 'La respuesta esperó a la restauración en vez de salir pronto con la ficha: ' . json_encode( is_wp_error( $result ) ? $result->get_error_message() : array_keys( $result ) ) );
	for ( $i = 0; $i < 2000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$view = Digitalisimo_Backups::service_job_follow( $result['follow']['job'], $result['follow']['token'] );
	ka_check( ! is_wp_error( $view ) && 'success' === $view['status'] && ! empty( $view['restore']['result']['access']['kept'] ), 'Con la ficha no se leyó el final de la restauración seguida por ticks.' );
}, true );

ka_case( 'i · B7: una migración lanzada sin usuario no conserva a quien da después un tick', function() use ( $file ) {
	$GLOBALS['ka_user'] = 1;
	$report = Digitalisimo_Backups_Security::open( bk_storage() . '/' . $file, array(), '' );
	try {
		$job = Digitalisimo_Backups::migration_enqueue( $report, array( 'job' => 'x', 'from' => 'https://origen.test', 'at' => time(), 'state' => 'restoring' ), Digitalisimo_Backups_Job_Store::new_id() );
	} finally {
		Digitalisimo_Backups_Security::close( $report );
		$GLOBALS['ka_user'] = 0;
	}
	Digitalisimo_Backups_Jobs::cancel( (string) $job['id'] );
	for ( $i = 0; $i < 50 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	ka_check( 0 === (int) ( $job['spec']['origin']['user_id'] ?? -1 ), 'La restauración de una migración sin usuario tomó al usuario de quien dio el tick: ' . json_encode( $job['spec']['origin'] ?? null ) );
}, true );

ka_case( 'i · si el proceso muere justo tras el cambio de tablas, el diario repone la cuenta', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	$sealed = $capture( 1 );
	$entry = array( 'v' => 1, 'job' => ka_job_tables( $db, 'after' ), 'state' => 'on', 'relocated' => true, 'secret' => $sealed );
	$property = new ReflectionProperty( 'Digitalisimo_Backups', 'access_kept' );
	if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
	$property->setValue( null, $entry );
	bk_call( 'Digitalisimo_Backups', 'write_restore_journal' );
	$property->setValue( null, null );
	$path = bk_storage() . '/' . Digitalisimo_Backups::RESTORE_JOURNAL;
	ka_check( DIRECTORY_SEPARATOR !== '/' || 0600 === ( fileperms( $path ) & 0777 ), 'El diario no es privado.' );
	ka_check( false === strpos( (string) file_get_contents( $path ), KA_HASH ), 'El diario lleva el hash en claro.' );
	$package( $db );
	bk_close_dead_locks();
	Digitalisimo_Backups::release_operation_lock( Digitalisimo_Backups::try_operation_lock() );
	ka_check( ! file_exists( $path ), 'El diario no se aplicó.' );
	$admin = $db->rows( 'wpkx_users', "user_login = 'admin'" );
	ka_check( 1 === count( $admin ) && KA_HASH === $admin[0]['user_pass'], 'Tras una muerte justo después del cambio, «admin» no volvió.' );
	ka_check( '' === $A::effective_slug(), 'Tras una muerte, la ruta privada del respaldo quedó.' );
} );

ka_case( 'i · B3: si el proceso muere antes del cambio de tablas, el diario no aplica nada y el informe es el de después del cambio', function() use ( $A, $destination, $package, $capture ) {
	$db = $destination();
	$entry = array( 'v' => 1, 'job' => ka_job_tables( $db, 'before' ), 'state' => 'on', 'relocated' => true, 'secret' => $capture( 1 ) );
	$property = new ReflectionProperty( 'Digitalisimo_Backups', 'access_kept' );
	if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
	$property->setValue( null, $entry );
	bk_call( 'Digitalisimo_Backups', 'write_restore_journal' );
	$property->setValue( null, null );
	bk_close_dead_locks();
	Digitalisimo_Backups::release_operation_lock( Digitalisimo_Backups::try_operation_lock() );
	$report = Digitalisimo_Backups_Job_Store::staging( $entry['job'] ) . '/access.json';
	ka_check( ! file_exists( $report ), 'El diario aplicó el acceso sobre las tablas sin cambiar y dejó su informe.' );
	// El tick siguiente vuelve a apuntar, cambia las tablas y aplica.
	$entry['secret'] = $capture( 1 );
	$package( $db );
	bk_call( 'Digitalisimo_Backups', 'restore_access_apply', $entry );
	$final = json_decode( (string) file_get_contents( $report ), true );
	ka_check( 'created' === ( $final['account'] ?? '' ) && in_array( 'digitalisimo', (array) ( $final['admins'] ?? array() ), true ) && 2 === (int) ( $final['users_total'] ?? 0 ), 'El informe no es el de después del cambio de tablas: ' . json_encode( $final ) );
} );

ka_case( 'l · B5: la casilla, la REST y WP-CLI sólo hablan de la cuenta; la ruta de acceso se conserva siempre', function() {
	$dir = dirname( __DIR__ ) . '/digitalisimo-backups/includes/';
	$sources = array( 'class-backups-rest.php', 'class-backups-cli.php', 'trait-backups-screen.php', 'class-backups-migrate.php' );
	foreach ( $sources as $name ) {
		$text = (string) file_get_contents( $dir . $name );
		foreach ( array( 'y el sitio, su ruta de acceso', 'y el sitio su ruta de acceso', 'y este sitio su ruta de acceso', 'y este sitio conserva su ruta de acceso' ) as $phrase ) ka_check( false === strpos( $text, $phrase ), $name . ' dice que «Conservar mi acceso» cubre la ruta de acceso («' . $phrase . '»).' );
	}
	ka_check( false !== strpos( (string) file_get_contents( $dir . 'class-backups-rest.php' ), 'La ruta de acceso de este sitio se conserva siempre al restaurar en otro sitio, con o sin esta opción.' ), 'La REST no dice que la ruta de acceso se conserva siempre.' );
} );

ka_case( 'k · tras la puerta, con la ficha sólo responde GET /jobs/<id>; la ficha en la URL se rechaza', function() {
	require_once dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups-rest.php';
	$request = function( $method, $route, array $query = array() ) {
		return new class( $method, $route, $query ) {
			public $m; public $r; public $q;
			public function __construct( $m, $r, $q ) { $this->m = $m; $this->r = $r; $this->q = $q; }
			public function get_method() { return $this->m; }
			public function get_route() { return $this->r; }
			public function get_query_params() { return $this->q; }
			public function get_header( $name ) { return ''; }
		};
	};
	$job = '/digitalisimo-backups/v1/jobs/0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e';
	ka_check( 'pasa' === Digitalisimo_Backups_Rest::reader_only( 'pasa', null, $request( 'GET', $job ) ), 'Tras la puerta no se lee el estado del trabajo.' );
	foreach ( array( array( 'POST', $job ), array( 'POST', $job . '/cancel' ), array( 'GET', '/digitalisimo-backups/v1/status' ), array( 'GET', '/wp/v2/users' ), array( 'POST', '/digitalisimo-backups/v1/jobs/tick' ) ) as $pair ) {
		$out = Digitalisimo_Backups_Rest::reader_only( null, null, $request( $pair[0], $pair[1] ) );
		ka_check( $out instanceof WP_Error && 503 === ( $out->get_error_data()['status'] ?? 0 ), 'Tras la puerta, la ficha abre ' . $pair[0] . ' ' . $pair[1] . '.' );
	}
	$GLOBALS['bk_caps'] = array();
	$out = Digitalisimo_Backups_Rest::allowed_job( $request( 'GET', $job, array( 'job_token' => str_repeat( 'a', 48 ) ) ) );
	$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
	ka_check( $out instanceof WP_Error && 400 === ( $out->get_error_data()['status'] ?? 0 ), 'La ficha en la URL no se rechaza con 400.' );
} );

ka_case( 'j · B2: la puerta del mantenimiento deja pasar la ficha sólo al index.php de WordPress con la ruta exacta de su trabajo', function() {
	if ( ! function_exists( 'add_filter' ) ) { function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return true; } }
	$token = Digitalisimo_Backups_Restore_Maintenance::new_token();
	$new_reader = function() { $token = bin2hex( random_bytes( 24 ) ); return array( 'token' => $token, 'hash' => hash( 'sha256', $token ) ); };
	$reader = $new_reader();
	$id = '00000000-0000-4000-8000-000000000000';
	Digitalisimo_Backups_Restore_Maintenance::hold( $token, true, $id, array( $reader['hash'] ) );
	$path = Digitalisimo_Backups_Restore_Maintenance::path();
	ka_check( false === strpos( (string) file_get_contents( $path ), $reader['token'] ), 'El .maintenance lleva la ficha en claro.' );
	$passes = function( $method, $uri, array $get, $header, $script = 'index.php', $info = '' ) use ( $path ) {
		$saved = array( $_SERVER, $_COOKIE, $_GET, $_REQUEST );
		$_SERVER = array( 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_FILENAME' => ABSPATH . $script, 'HTTP_X_DIGITALISIMO_BACKUPS_JOB_TOKEN' => $header ) + $_SERVER;
		unset( $_SERVER['PATH_INFO'] );
		if ( '' !== $info ) $_SERVER['PATH_INFO'] = $info;
		$_COOKIE = array(); $_GET = $get; $_REQUEST = $get;
		$run = function() use ( $path ) { $upgrading = null; require $path; return $upgrading; };
		$upgrading = $run();
		list( $_SERVER, $_COOKIE, $_GET, $_REQUEST ) = $saved;
		return ( time() - $upgrading ) >= 600;
	};
	$rest = '/digitalisimo-backups/v1/jobs/' . $id;
	$route = '/wp-json' . $rest;
	ka_check( ! $passes( 'GET', $route, array(), $new_reader()['token'] ), 'Una ficha ajena pasa el mantenimiento.' );
	ka_check( ! $passes( 'POST', $route, array(), $reader['token'] ), 'La ficha pasa para escribir.' );
	ka_check( ! $passes( 'GET', '/wp-json/digitalisimo-backups/v1/status', array(), $reader['token'] ), 'La ficha pasa para otra ruta.' );
	ka_check( ! $passes( 'GET', $route, array( 'rest_route' => '/wp/v2/users' ), $reader['token'] ), 'La ficha pasa con otra rest_route en la consulta.' );
	ka_check( ! $passes( 'GET', '/wp-login.php' . $route, array(), $reader['token'] ), 'La ficha pasa por wp-login.php/….' );
	ka_check( ! $passes( 'GET', '/wp-login.php' . $route, array(), $reader['token'], 'wp-login.php', $route ), 'La ficha pasa por el script wp-login.php.' );
	ka_check( ! $passes( 'GET', '/wp-admin/admin-ajax.php', array( 'rest_route' => $rest, 'action' => 'x' ), $reader['token'], 'wp-admin/admin-ajax.php' ), 'La ficha pasa por admin-ajax.php con rest_route.' );
	ka_check( ! $passes( 'GET', '/wp-admin/index.php' . $route, array(), $reader['token'], 'wp-admin/index.php', $route ), 'La ficha pasa por wp-admin/index.php/… (PATH_INFO).' );
	ka_check( ! $passes( 'GET', '/index.php' . $route, array(), $reader['token'], 'index.php', $route ), 'La ficha pasa con PATH_INFO en el index.php.' );
	ka_check( ! $passes( 'GET', '/otra/carpeta' . $route, array(), $reader['token'] ), 'La ficha pasa con una ruta que no es la del sitio.' );
	ka_check( ! defined( 'DIGITALISIMO_BACKUPS_RESTORE_READER' ), 'Una petición rechazada dejó pasar a un lector.' );
	ka_check( $passes( 'GET', '/?rest_route=' . rawurlencode( $rest ), array( 'rest_route' => $rest ), $reader['token'] ), 'La ficha no pasa por ?rest_route= en la portada.' );
	ka_check( $passes( 'GET', $route, array(), $reader['token'] ) && defined( 'DIGITALISIMO_BACKUPS_RESTORE_READER' ) && defined( 'DIGITALISIMO_BACKUPS_RESTORE_MINIMAL' ), 'La ficha no pasa por /wp-json/… con WordPress mínimo y sólo de lectura.' );
	Digitalisimo_Backups_Restore_Maintenance::release();
}, true );

bk_cleanup();
foreach ( glob( $GLOBALS['bk_root'] . '-*.sqlite' ) ?: array() as $sqlite ) @unlink( $sqlite );
echo $failures ? "Conservar el acceso de quien restaura: {$failures} caso(s) con fallos.\n" : "DIGITALÍSIMO Backups: quien restaura en otro sitio sigue entrando (su cuenta, su contraseña, sus sesiones y sus contraseñas de aplicación), las contraseñas de aplicación del respaldo valen, la ruta de acceso es la de este sitio y el estado del trabajo se lee con su ficha hasta el final.\n";
exit( $failures ? 1 : 0 );
