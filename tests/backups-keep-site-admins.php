<?php
/**
 * Al restaurar el sitio de otra instalación, los administradores de este sitio siguen entrando (pedido del propietario del
 * 2026-11-16: «respeta los usuarios existentes para no romper accesos admin»; 1.0.76 sólo conservaba a quien restaura).
 *
 *   a · la decisión (`keep_admins`): los mismos casos que `keep_access` (otra instalación, encendido por defecto) y nunca
 *       en el mismo sitio ni en la vuelta a la copia previa;
 *   b · dos administradores de aquí («admin», quien restaura, y «soporte») y un editor: los dos administradores siguen con
 *       su usuario, su hash, su correo, sus sesiones y sus contraseñas de aplicación, como administradores; el editor no
 *       se conserva; las cuentas del respaldo quedan intactas; el informe dice quiénes (sólo usuarios, sin secretos);
 *   c · tres administradores: IDs nuevos consecutivos desde el siguiente libre;
 *   d · choque por usuario con una cuenta administradora del respaldo: una sola cuenta con el ID del respaldo (la autoría no
 *       cambia), sus datos y metas, y las credenciales de aquí; sus contraseñas de aplicación se suman;
 *   e · choque sólo por correo: no se fusionan (la del respaldo sigue entrando con su contraseña) y el informe avisa;
 *   f · dos administradores de aquí que chocan con dos usuarios distintos del respaldo (uno administrador, otro no), y un
 *       usuario cedido que no pisa el de otra cuenta que se conserva;
 *   g · un usuario del respaldo que NO es administrador y coincide con un administrador de aquí (por usuario o por correo):
 *       el de aquí no pierde su acceso y el del respaldo no gana privilegios (ni su segundo factor, inicio social ni
 *       contraseñas de aplicación pasan a la cuenta administradora);
 *   h · nunca se conserva una cuenta que aquí no es administradora (un editor, una marcada como spam); con `keep_access`
 *       apagado, quien restaura tampoco entra como administrador;
 *   i · red: los superadministradores y los administradores del sitio desde el que se restaura (no los de otro sitio);
 *   j · el motor: con el respaldo de otra instalación los administradores siguen entrando por defecto; `keep_admins` =
 *       false los deja fuera; con `keep_access` = false sigue el otro administrador y no quien restaura; con el respaldo de
 *       esta misma instalación no cambia nada (vuelven las cuentas del respaldo);
 *   k · el proceso muere entre medias: la segunda pasada sigue la decisión anotada (cifrada) en el diario, sin duplicar ni
 *       renombrar dos veces; y si muere justo tras el cambio de tablas, el diario repone a todos;
 *   l · nada sensible en registros, eventos de los trabajos, informes ni respuestas (ningún hash, correo, sesión ni
 *       contraseña de aplicación), y el diario lo lleva cifrado;
 *   m · la confirmación (pantalla y `verify`), REST y WP-CLI: quiénes conservarán su acceso y la opción `keep_admins`.
 * Con el código anterior (1.0.78) fallan todos: no existen decide_admins()/capture_accounts()/preview() y el motor sólo
 * conserva a quien restaura. Base de datos SQLite de verdad (tests/lib/backups-sqlite.php). Escribe «FALLA ·».
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['ksa_user'] = 0;
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) $GLOBALS['ksa_user']; } }
if ( ! function_exists( 'get_main_network_id' ) ) { function get_main_network_id() { return 1; } }
if ( ! function_exists( 'get_network_option' ) ) { function get_network_option( $id, $key, $default = false ) { return is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default ); } }
if ( ! function_exists( 'update_network_option' ) ) { function update_network_option( $id, $key, $value ) { return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value ); } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route( $namespace, $route, $args ) { $GLOBALS['ksa_routes'][ $route ] = $args; return true; } }
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
// Los registros de PHP de esta prueba (error_log) van a un archivo que se revisa en «l».
$GLOBALS['ksa_log'] = $GLOBALS['bk_root'] . '-php.log';
ini_set( 'log_errors', '1' );
ini_set( 'error_log', $GLOBALS['ksa_log'] );

$A = 'Digitalisimo_Backups_Restore_Access';
// Con el código anterior la clase existe pero no conserva administradores: sus casos fallan diciéndolo y los del motor
// (j, k, l) corren igual.
$GLOBALS['ksa_have'] = class_exists( $A ) && method_exists( $A, 'capture_accounts' ) && method_exists( $A, 'decide_admins' );
$failures = 0;
function ksa_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function ksa_case( $label, callable $test, $engine = false ) {
	try {
		if ( ! $engine && ! $GLOBALS['ksa_have'] ) throw new RuntimeException( 'Digitalisimo_Backups_Restore_Access no conserva a los administradores de este sitio (código anterior al arreglo).' );
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	} finally {
		$GLOBALS['bk_multisite'] = false;
	}
}
function ksa_db( $name, $prefix ) {
	@unlink( $GLOBALS['bk_root'] . '-' . $name . '.sqlite' );
	$db = new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-' . $name . '.sqlite', $prefix );
	ksa_schema( $db, $prefix );
	return $db;
}
function ksa_schema( Bk_Sqlite_DB $db, $prefix ) {
	$db->pdo->exec( "CREATE TABLE `{$prefix}users` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60) NOT NULL DEFAULT '', `user_pass` varchar(255) NOT NULL DEFAULT '', `user_nicename` varchar(50) NOT NULL DEFAULT '', `user_email` varchar(100) NOT NULL DEFAULT '', `user_url` varchar(100) NOT NULL DEFAULT '', `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `user_activation_key` varchar(255) NOT NULL DEFAULT '', `user_status` int(11) NOT NULL DEFAULT 0, `display_name` varchar(250) NOT NULL DEFAULT '', `spam` tinyint(2) NOT NULL DEFAULT 0, `deleted` tinyint(2) NOT NULL DEFAULT 0)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}usermeta` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
}
/** Un usuario: $role es el rol del sitio con el prefijo $prefix (o null), $meta las demás metas. */
function ksa_user( Bk_Sqlite_DB $db, $prefix, $id, $login, $pass, $email, $role, array $meta = array(), array $columns = array() ) {
	$db->seed( $prefix . 'users', array( $columns + array( 'ID' => $id, 'user_login' => $login, 'user_pass' => $pass, 'user_nicename' => $login, 'user_email' => $email, 'display_name' => ucfirst( $login ) ) ) );
	if ( null !== $role ) $meta = array( $prefix . 'capabilities' => serialize( array( $role => true ) ), $prefix . 'user_level' => 'administrator' === $role ? '10' : '0' ) + $meta;
	foreach ( $meta as $key => $value ) $db->seed( $prefix . 'usermeta', array( array( 'user_id' => $id, 'meta_key' => $key, 'meta_value' => $value ) ) );
}
function ksa_meta( Bk_Sqlite_DB $db, $prefix, $id, $key ) {
	$rows = $db->rows( $prefix . 'usermeta', 'user_id = ' . (int) $id . " AND meta_key = '" . $key . "'" );
	return $rows ? $rows[0]['meta_value'] : null;
}
function ksa_row( Bk_Sqlite_DB $db, $prefix, $login ) {
	$rows = $db->rows( $prefix . 'users', "user_login = '" . $login . "'" );
	return $rows ? $rows[0] : null;
}
function ksa_is_admin( Bk_Sqlite_DB $db, $prefix, $id, $blog = null ) {
	$caps = @unserialize( (string) ksa_meta( $db, $prefix, $id, ( null === $blog ? $prefix : $blog ) . 'capabilities' ) );
	return is_array( $caps ) && ! empty( $caps['administrator'] );
}
function ksa_app( $uuid ) { return array( 'uuid' => $uuid, 'app_id' => '', 'name' => 'App ' . $uuid, 'password' => '$generic$' . hash( 'sha256', $uuid ), 'created' => 1762900000, 'last_used' => null, 'last_ip' => null ); }
function ksa_apps( array $uuids ) { return serialize( array_map( 'ksa_app', $uuids ) ); }
function ksa_sessions( $verifier ) { return serialize( array( $verifier => array( 'expiration' => time() + 86400, 'ip' => '127.0.0.1', 'ua' => 'prueba', 'login' => time() ) ) ); }
function ksa_uuids( Bk_Sqlite_DB $db, $prefix, $id ) { $list = array_column( (array) @unserialize( (string) ksa_meta( $db, $prefix, $id, '_application_passwords' ) ), 'uuid' ); sort( $list ); return $list; }
function ksa_session_keys( Bk_Sqlite_DB $db, $prefix, $id ) { return array_keys( (array) @unserialize( (string) ksa_meta( $db, $prefix, $id, 'session_tokens' ) ) ); }

// Los hashes, correos y sesiones de las cuentas de este sitio: nunca en un informe, un registro ni una respuesta.
const KSA_ADMIN = '$wp$2y$10$adminAdminAdminAdminAdminAdminAdminAdminAdminAdminAd';
const KSA_SOPORTE = '$wp$2y$10$soporteSoporteSoporteSoporteSoporteSoporteSoporteSo';
const KSA_TECNICO = '$wp$2y$10$tecnicoTecnicoTecnicoTecnicoTecnicoTecnicoTecnicoTe';
const KSA_EDITOR = '$wp$2y$10$editorEditorEditorEditorEditorEditorEditorEditorEdi';
const KSA_DUENO = '$P$Bdueno.dueno.dueno.dueno.dueno';
const KSA_EDITORA = '$P$Beditora.editora.editora.edito';
$secrets = array( KSA_ADMIN, KSA_SOPORTE, KSA_TECNICO, KSA_EDITOR, 'admin@destino.test', 'soporte@destino.test', 'tecnico@destino.test', str_repeat( 'a', 64 ), str_repeat( 'b', 64 ), 'u-admin', 'u-soporte', '$generic$' );

/** Este sitio: «admin» (1, quien restaura), «soporte» (2) y un editor «colaborador» (3), con prefijo $prefix. */
$destination = function( $prefix = 'wpkx_', array $extra = array() ) {
	$db = ksa_db( 'destino-' . $prefix . md5( json_encode( $extra ) . microtime() ), $prefix );
	ksa_user( $db, $prefix, 1, 'admin', KSA_ADMIN, 'admin@destino.test', 'administrator', array( 'nickname' => 'admin', 'locale' => 'es_MX', 'session_tokens' => ksa_sessions( str_repeat( 'a', 64 ) ), '_application_passwords' => ksa_apps( array( 'u-admin' ) ) ) );
	ksa_user( $db, $prefix, 2, 'soporte', KSA_SOPORTE, 'soporte@destino.test', 'administrator', array( 'nickname' => 'soporte', 'first_name' => 'Equipo', 'session_tokens' => ksa_sessions( str_repeat( 'b', 64 ) ), '_application_passwords' => ksa_apps( array( 'u-soporte' ) ) ) );
	ksa_user( $db, $prefix, 3, 'colaborador', KSA_EDITOR, 'colaborador@destino.test', 'editor', array( 'session_tokens' => ksa_sessions( str_repeat( 'c', 64 ) ) ) );
	foreach ( $extra as $user ) ksa_user( $db, $prefix, $user[0], $user[1], $user[2], $user[3], $user[4], $user[5] ?? array(), $user[6] ?? array() );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options'] = array( 'home' => 'https://destino.test', 'siteurl' => 'https://destino.test', 'using_application_passwords' => true );
	return $db;
};
/** Lo que deja el cambio de tablas con el respaldo del sitio A: «dueno» (administrador, 1) y «editora» (2), más $users. */
$package = function( Bk_Sqlite_DB $db, array $users = array(), $prefix = 'wpkx_' ) {
	foreach ( array( 'users', 'usermeta', 'options' ) as $table ) $db->pdo->exec( 'DROP TABLE `' . $prefix . $table . '`' );
	ksa_schema( $db, $prefix );
	ksa_user( $db, $prefix, 1, 'dueno', KSA_DUENO, 'dueno@origen.test', 'administrator', array( '_application_passwords' => ksa_apps( array( 'u-dueno' ) ) ) );
	ksa_user( $db, $prefix, 2, 'editora', KSA_EDITORA, 'editora@origen.test', 'editor' );
	foreach ( $users as $user ) ksa_user( $db, $prefix, $user[0], $user[1], $user[2], $user[3], $user[4], $user[5] ?? array(), $user[6] ?? array() );
	$GLOBALS['bk_options'] = array( 'home' => 'https://destino.test', 'siteurl' => 'https://destino.test' );
};
/** Lo que el motor apunta en el diario antes del cambio (quien restaura: el usuario 1; los administradores, si $admins). */
$capture = function( $restorer = 1, $admins = true, $exclude = 0 ) use ( $A ) {
	return $A::seal( array( 'users' => $A::capture_accounts( $restorer, $admins, null, $exclude ), 'login' => $A::login_capture(), 'salts' => $A::salts_capture() ) );
};
$on = array( 'access' => 'on', 'admins' => 'on' );

ksa_case( 'a · se conservan los administradores con un respaldo de otra instalación, encendido por defecto, y nunca con uno de esta misma ni en la vuelta a la copia previa', function() use ( $A ) {
	ksa_check( 'on' === $A::decide_admins( true, true, false ), 'Con un respaldo de otra instalación no se conservan los administradores.' );
	ksa_check( 'same_site' === $A::decide_admins( true, false, false ), 'Con un respaldo de esta misma instalación se conservarían las cuentas de ahora (credenciales que pueden estar comprometidas).' );
	ksa_check( 'off' === $A::decide_admins( false, true, false ), 'Apagado no se respeta.' );
	ksa_check( 'rollback' === $A::decide_admins( true, true, true ), 'La vuelta a la copia previa conservaría cuentas.' );
} );

ksa_case( 'b · dos administradores de este sitio siguen entrando con todo lo suyo; el editor no se conserva y las cuentas del respaldo quedan intactas', function() use ( $A, $destination, $package, $capture, $on, $secrets ) {
	$db = $destination();
	$sealed = $capture();
	$package( $db );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$admin = ksa_row( $db, 'wpkx_', 'admin' );
	$soporte = ksa_row( $db, 'wpkx_', 'soporte' );
	ksa_check( $admin && 3 === (int) $admin['ID'] && KSA_ADMIN === $admin['user_pass'] && 'admin@destino.test' === $admin['user_email'], '«admin» (quien restaura) no siguió con su ID libre, su hash o su correo: ' . json_encode( $admin ? array( $admin['ID'], $admin['user_email'] ) : null ) );
	ksa_check( $soporte && 4 === (int) $soporte['ID'] && KSA_SOPORTE === $soporte['user_pass'] && 'soporte@destino.test' === $soporte['user_email'], '«soporte» (administrador de este sitio) no sigue con su hash y su correo: ' . json_encode( $soporte ? array( $soporte['ID'], $soporte['user_email'] ) : null ) );
	ksa_check( ksa_is_admin( $db, 'wpkx_', 3 ) && ksa_is_admin( $db, 'wpkx_', 4 ) && '10' === ksa_meta( $db, 'wpkx_', 4, 'wpkx_user_level' ), 'Los administradores conservados no quedaron como administradores del sitio restaurado.' );
	ksa_check( array( str_repeat( 'b', 64 ) ) === ksa_session_keys( $db, 'wpkx_', 4 ) && array( 'u-soporte' ) === ksa_uuids( $db, 'wpkx_', 4 ), 'Las sesiones o las contraseñas de aplicación de «soporte» no volvieron (su navegador o su cliente REST dejarían de entrar).' );
	ksa_check( 'Equipo' === ksa_meta( $db, 'wpkx_', 4, 'first_name' ), 'El perfil de «soporte» no viajó.' );
	ksa_check( null === ksa_row( $db, 'wpkx_', 'colaborador' ), 'Se conservó «colaborador», que en este sitio no era administrador.' );
	ksa_check( KSA_DUENO === ksa_row( $db, 'wpkx_', 'dueno' )['user_pass'] && 1 === (int) ksa_row( $db, 'wpkx_', 'dueno' )['ID'] && KSA_EDITORA === ksa_row( $db, 'wpkx_', 'editora' )['user_pass'] && ksa_is_admin( $db, 'wpkx_', 1 ) && ! ksa_is_admin( $db, 'wpkx_', 2 ), 'Las cuentas del respaldo cambiaron.' );
	ksa_check( array( 'admin', 'soporte' ) === $report['kept_accounts'] && array( 'admin', 'soporte' ) === $report['created_accounts'] && array() === $report['merged_accounts'] && 'on' === $report['admins_state'] && 'on' === $report['state'], 'El informe no dice quiénes conservaron su acceso: ' . json_encode( array( $report['kept_accounts'], $report['created_accounts'], $report['admins_state'] ) ) );
	ksa_check( false !== strpos( $report['text'], 'los administradores de este sitio: «soporte»' ) && false !== strpos( $report['text'], '«admin»' ), 'El texto no nombra a quienes conservaron su acceso: ' . $report['text'] );
	$json = json_encode( $report );
	foreach ( $secrets as $secret ) ksa_check( false === strpos( $json, $secret ), 'El informe lleva un secreto o un correo: ' . substr( $secret, 0, 14 ) );
} );

ksa_case( 'c · tres administradores de este sitio: todos siguen, con IDs nuevos consecutivos', function() use ( $A, $destination, $package, $capture, $on ) {
	$db = $destination( 'wpkx_', array( array( 9, 'tecnico', KSA_TECNICO, 'tecnico@destino.test', 'administrator' ) ) );
	$sealed = $capture();
	$package( $db );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$ids = array();
	foreach ( array( 'admin', 'soporte', 'tecnico' ) as $login ) { $row = ksa_row( $db, 'wpkx_', $login ); $ids[ $login ] = $row ? (int) $row['ID'] : 0; }
	ksa_check( array( 'admin' => 3, 'soporte' => 4, 'tecnico' => 5 ) === $ids, 'Los tres administradores no tienen IDs libres consecutivos: ' . json_encode( $ids ) );
	ksa_check( KSA_TECNICO === ksa_row( $db, 'wpkx_', 'tecnico' )['user_pass'] && ksa_is_admin( $db, 'wpkx_', 5 ), '«tecnico» no sigue con su contraseña como administrador.' );
	ksa_check( array( 'admin', 'soporte', 'tecnico' ) === $report['kept_accounts'] && 5 === count( $db->rows( 'wpkx_users' ) ), 'El informe o las cuentas no son las esperadas: ' . json_encode( $report['kept_accounts'] ) );
} );

ksa_case( 'd · mismo usuario que una cuenta administradora del respaldo: una sola cuenta con el ID y los datos del respaldo y las credenciales de este sitio', function() use ( $A, $destination, $package, $capture, $on ) {
	$db = $destination();
	$sealed = $capture();
	$package( $db, array( array( 7, 'soporte', '$P$Bsoporte.del.origen.origen.or', 'soporte@origen.test', 'administrator', array( 'description' => 'Escribe en el blog desde 2019', 'session_tokens' => ksa_sessions( str_repeat( 'z', 64 ) ), '_application_passwords' => ksa_apps( array( 'u-sop-origen' ) ) ), array( 'display_name' => 'Soporte del origen', 'user_nicename' => 'soporte-origen', 'user_registered' => '2019-01-02 03:04:05', 'spam' => 1 ) ) ) );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$row = ksa_row( $db, 'wpkx_', 'soporte' );
	ksa_check( $row && 7 === (int) $row['ID'] && 1 === count( $db->rows( 'wpkx_users', "user_login = 'soporte'" ) ), 'No quedó una sola cuenta «soporte» con el ID del respaldo (la autoría cambiaría): ' . json_encode( $row ? $row['ID'] : null ) );
	ksa_check( KSA_SOPORTE === $row['user_pass'] && 'soporte@destino.test' === $row['user_email'] && array( str_repeat( 'b', 64 ) ) === ksa_session_keys( $db, 'wpkx_', 7 ), 'La cuenta fusionada no tiene la contraseña, el correo y las sesiones de este sitio.' );
	ksa_check( 'Soporte del origen' === $row['display_name'] && 'soporte-origen' === $row['user_nicename'] && '2019-01-02 03:04:05' === $row['user_registered'] && 'Escribe en el blog desde 2019' === ksa_meta( $db, 'wpkx_', 7, 'description' ), 'La cuenta fusionada perdió los datos del respaldo (nombre, URL de autor, alta, biografía).' );
	ksa_check( array( 'u-sop-origen', 'u-soporte' ) === ksa_uuids( $db, 'wpkx_', 7 ) && ksa_is_admin( $db, 'wpkx_', 7 ) && 0 === (int) $row['spam'], 'La cuenta fusionada no suma las contraseñas de aplicación, no es administradora o quedó bloqueada.' );
	ksa_check( array( 'soporte' ) === $report['merged_accounts'] && in_array( 'soporte', $report['kept_accounts'], true ) && false !== strpos( $report['text'], 'se fusionaron' ), 'El informe no dice que «soporte» se fusionó con la del respaldo: ' . json_encode( $report['merged_accounts'] ) . ' ' . $report['text'] );
} );

ksa_case( 'e · sólo el mismo correo que una cuenta del respaldo: no se fusionan, las dos siguen entrando y el informe avisa', function() use ( $A, $destination, $package, $capture, $on ) {
	$db = $destination();
	$sealed = $capture();
	$package( $db, array( array( 7, 'equipo', '$P$Bequipo.del.origen.origen.orig', 'soporte@destino.test', 'administrator' ) ) );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$soporte = ksa_row( $db, 'wpkx_', 'soporte' );
	$equipo = ksa_row( $db, 'wpkx_', 'equipo' );
	ksa_check( $soporte && 9 === (int) $soporte['ID'] && KSA_SOPORTE === $soporte['user_pass'], '«soporte» no se creó aparte con su contraseña.' );
	ksa_check( $equipo && 7 === (int) $equipo['ID'] && '$P$Bequipo.del.origen.origen.orig' === $equipo['user_pass'] && ksa_is_admin( $db, 'wpkx_', 7 ), 'La cuenta del respaldo con el mismo correo perdió su contraseña o su usuario.' );
	ksa_check( array( 'equipo' ) === ( $report['shared_emails']['soporte'] ?? null ) && false !== strpos( $report['text'], 'El correo de «soporte» también es el de «equipo»' ), 'El informe no avisa del correo compartido: ' . json_encode( $report['shared_emails'] ) );
	ksa_check( false === strpos( json_encode( $report ), 'soporte@destino.test' ), 'El informe lleva el correo.' );
} );

ksa_case( 'f · dos administradores que chocan con dos usuarios distintos del respaldo, y un usuario cedido que no pisa otra cuenta que se conserva', function() use ( $A, $destination, $package, $capture, $on ) {
	$db = $destination( 'wpkx_', array( array( 5, 'soporte-respaldo', KSA_TECNICO, 'tecnico@destino.test', 'administrator' ) ) );
	$sealed = $capture();
	$package( $db, array( array( 7, 'admin', '$P$Badmin.del.origen.origen.origen', 'admin@origen.test', 'administrator' ), array( 8, 'soporte', '$P$Bcliente.cliente.cliente.client', 'cliente@origen.test', 'subscriber' ) ) );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$logins = array_column( $db->rows( 'wpkx_users' ), 'user_login', 'ID' );
	ksa_check( array( 1 => 'dueno', 2 => 'editora', 7 => 'admin', 8 => 'soporte-respaldo-2', 9 => 'soporte', 10 => 'soporte-respaldo' ) === $logins, 'Las cuentas no quedaron como se esperaba: ' . json_encode( $logins ) );
	ksa_check( KSA_ADMIN === $db->value( 'wpkx_users', 'user_pass', 'ID = 7' ) && KSA_SOPORTE === $db->value( 'wpkx_users', 'user_pass', 'ID = 9' ) && KSA_TECNICO === $db->value( 'wpkx_users', 'user_pass', 'ID = 10' ), 'Algún administrador de este sitio no sigue con su contraseña.' );
	ksa_check( ! ksa_is_admin( $db, 'wpkx_', 8 ) && '$P$Bcliente.cliente.cliente.client' === $db->value( 'wpkx_users', 'user_pass', 'ID = 8' ), 'La cuenta del respaldo que cedió su usuario ganó permisos o cambió.' );
	ksa_check( array( 'admin' ) === $report['merged_accounts'] && array( array( 'from' => 'soporte', 'to' => 'soporte-respaldo-2' ) ) === $report['renamed_backup'], 'El informe no dice la fusión y el usuario cedido: ' . json_encode( array( $report['merged_accounts'], $report['renamed_backup'] ) ) );
} );

ksa_case( 'g · un usuario del respaldo que no es administrador y coincide con un administrador de este sitio no gana privilegios y el de aquí no pierde su acceso', function() use ( $A, $destination, $package, $capture, $on ) {
	$db = $destination();
	$sealed = $capture();
	$package( $db, array(
		array( 7, 'soporte', '$P$Bcliente.cliente.cliente.client', 'cliente@origen.test', 'subscriber', array( '_two_factor_totp_key' => 'SECRETO-DEL-CLIENTE', 'nsl_google_id' => 'google-del-cliente', '_application_passwords' => ksa_apps( array( 'u-cliente' ) ), 'session_tokens' => ksa_sessions( str_repeat( 'y', 64 ) ) ) ),
		array( 8, 'compras', '$P$Bcompras.compras.compras.compr', 'admin@destino.test', 'customer', array( 'nsl_google_id' => 'google-de-compras' ) ),
	) );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$client = ksa_row( $db, 'wpkx_', 'soporte-respaldo' );
	$soporte = ksa_row( $db, 'wpkx_', 'soporte' );
	ksa_check( $client && 7 === (int) $client['ID'] && '$P$Bcliente.cliente.cliente.client' === $client['user_pass'] && ! ksa_is_admin( $db, 'wpkx_', 7 ) && array( 'subscriber' => true ) === @unserialize( (string) ksa_meta( $db, 'wpkx_', 7, 'wpkx_capabilities' ) ), 'La cuenta del respaldo que no era administradora ganó permisos o perdió su contraseña.' );
	ksa_check( 'SECRETO-DEL-CLIENTE' === ksa_meta( $db, 'wpkx_', 7, '_two_factor_totp_key' ) && array( 'u-cliente' ) === ksa_uuids( $db, 'wpkx_', 7 ) && array( str_repeat( 'y', 64 ) ) === ksa_session_keys( $db, 'wpkx_', 7 ), 'La cuenta del respaldo perdió su segundo factor, sus contraseñas de aplicación o sus sesiones.' );
	ksa_check( $soporte && 10 === (int) $soporte['ID'] && KSA_SOPORTE === $soporte['user_pass'] && ksa_is_admin( $db, 'wpkx_', 10 ) && array( 'u-soporte' ) === ksa_uuids( $db, 'wpkx_', 10 ), '«soporte» de este sitio perdió su acceso.' );
	ksa_check( null === ksa_meta( $db, 'wpkx_', 10, '_two_factor_totp_key' ) && null === ksa_meta( $db, 'wpkx_', 10, 'nsl_google_id' ) && array( str_repeat( 'b', 64 ) ) === ksa_session_keys( $db, 'wpkx_', 10 ), 'La cuenta administradora heredó un modo de entrar de la del respaldo (inicio social, segundo factor o sesiones).' );
	// Por correo: «compras» (cliente del respaldo con el correo de «admin») sigue siendo cliente; «admin» se crea aparte.
	ksa_check( ! ksa_is_admin( $db, 'wpkx_', 8 ) && 'compras' === $db->value( 'wpkx_users', 'user_login', 'ID = 8' ) && KSA_ADMIN === ksa_row( $db, 'wpkx_', 'admin' )['user_pass'] && 8 !== (int) ksa_row( $db, 'wpkx_', 'admin' )['ID'], 'La cuenta del respaldo con el correo de «admin» ganó permisos o se fundió con él.' );
	ksa_check( array( array( 'from' => 'soporte', 'to' => 'soporte-respaldo' ) ) === $report['renamed_backup'] && false !== strpos( $report['text'], '«soporte-respaldo»' ), 'El informe no dice que la cuenta del respaldo cedió su usuario: ' . $report['text'] );
} );

ksa_case( 'h · nunca una cuenta que aquí no es administradora (un editor, una marcada como spam); con keep_access apagado, quien restaura tampoco', function() use ( $A, $destination, $package, $on ) {
	$db = $destination( 'wpkx_', array( array( 6, 'bloqueado', KSA_TECNICO, 'tecnico@destino.test', 'administrator', array(), array( 'spam' => 1 ) ) ) );
	$GLOBALS['ksa_user'] = 1;
	try {
		$preview = $A::preview( 1 );
	} finally {
		$GLOBALS['ksa_user'] = 0;
	}
	ksa_check( 'admin' === $preview['you'] && array( 'soporte' ) === $preview['admins'] && 1 === $preview['admins_total'], 'La vista previa no nombra sólo a los administradores que pueden entrar: ' . json_encode( $preview ) );
	$logins = array_map( function( $one ) { return $one['user']['user_login']; }, $A::capture_accounts( 0, true, null, 1 ) );
	ksa_check( array( 'soporte' ) === $logins, 'Con keep_access apagado se apuntó a quien restaura, a un editor o a una cuenta bloqueada: ' . json_encode( $logins ) );
	$sealed = $A::seal( array( 'users' => $A::capture_accounts( 0, true, null, 1 ), 'login' => $A::login_capture() ) );
	// Un diario alterado que dijera que el editor es administrador tampoco lo conserva: sólo valen las marcas de la captura.
	$forged = $A::open( $sealed );
	$editor = $A::capture( 3 );
	$editor['restorer'] = false;
	$forged['users'][] = $editor;
	$package( $db );
	$report = $A::apply( array( 'access' => 'off', 'admins' => 'on' ), $forged, true );
	ksa_check( null === ksa_row( $db, 'wpkx_', 'admin' ) && null === ksa_row( $db, 'wpkx_', 'colaborador' ) && null === ksa_row( $db, 'wpkx_', 'bloqueado' ) && KSA_SOPORTE === ksa_row( $db, 'wpkx_', 'soporte' )['user_pass'], 'Se conservó una cuenta que no debía (quien restaura sin keep_access, el editor o la bloqueada), o no «soporte».' );
	ksa_check( 'off' === $report['state'] && array( 'soporte' ) === $report['kept_accounts'] && false !== strpos( $report['text'], 'No se pidió conservar el acceso de quien restauró.' ) && false === strpos( $report['text'], 'entra con un usuario del respaldo' ), 'El informe no dice bien qué pasó con keep_access apagado: ' . $report['text'] );
} );

ksa_case( 'i · red: siguen los superadministradores y los administradores del sitio desde el que se restaura, no los de otro sitio', function() use ( $A, $on ) {
	$GLOBALS['bk_multisite'] = true;
	$db = ksa_db( 'red', 'wp_' );
	ksa_user( $db, 'wp_', 1, 'red-admin', KSA_ADMIN, 'admin@destino.test', null, array( 'wp_capabilities' => serialize( array( 'administrator' => true ) ), 'primary_blog' => '1' ) );
	ksa_user( $db, 'wp_', 2, 'sitio-admin', KSA_SOPORTE, 'soporte@destino.test', 'administrator', array( 'primary_blog' => '1' ) );
	ksa_user( $db, 'wp_', 3, 'otro-sitio', KSA_TECNICO, 'tecnico@destino.test', null, array( 'wp_2_capabilities' => serialize( array( 'administrator' => true ) ), 'primary_blog' => '2' ) );
	ksa_user( $db, 'wp_', 4, 'super-sin-rol', KSA_EDITOR, 'super@destino.test', null, array( 'primary_blog' => '1' ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'red-admin', 'super-sin-rol' ) );
	$sealed = $A::seal( array( 'users' => $A::capture_accounts( 0, true, 'wp_' ), 'login' => $A::login_capture() ) );
	// La red del respaldo: su superadministrador «red-origen» y su sitemeta.
	foreach ( array( 'users', 'usermeta', 'options' ) as $table ) $db->pdo->exec( 'DROP TABLE `wp_' . $table . '`' );
	ksa_schema( $db, 'wp_' );
	ksa_user( $db, 'wp_', 1, 'red-origen', KSA_DUENO, 'red@origen.test', 'administrator' );
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'red-origen' ) );
	$report = $A::apply( $on, $A::open( $sealed ), true );
	$admins = (array) $GLOBALS['bk_site_options']['site_admins'];
	sort( $admins );
	ksa_check( array( 'red-admin', 'red-origen', 'super-sin-rol' ) === $admins, 'Los superadministradores de esta red no siguen siéndolo (o se perdió el del respaldo): ' . json_encode( $admins ) );
	foreach ( array( 'red-admin', 'sitio-admin', 'super-sin-rol' ) as $login ) { $row = ksa_row( $db, 'wp_', $login ); ksa_check( $row && ksa_is_admin( $db, 'wp_', (int) $row['ID'] ) && '1' === ksa_meta( $db, 'wp_', (int) $row['ID'], 'primary_blog' ), '«' . $login . '» no sigue como administrador del sitio principal.' ); }
	ksa_check( null === ksa_row( $db, 'wp_', 'otro-sitio' ), 'Se conservó a un administrador de otro sitio de la red.' );
	ksa_check( ! in_array( 'sitio-admin', $admins, true ), 'Un administrador del sitio se volvió superadministrador.' );
	ksa_check( array( 'red-admin', 'sitio-admin', 'super-sin-rol' ) === $report['kept_accounts'], 'El informe de la red no dice quiénes siguen: ' . json_encode( $report['kept_accounts'] ) );
	$GLOBALS['bk_site_options'] = array();
} );

/* ------------------------------------------------------------------------------------------------- j · el motor */

$GLOBALS['bk_options'] = array( 'home' => 'https://origen.test', 'siteurl' => 'https://origen.test', 'blogname' => 'Origen' );
$origin = ksa_db( 'origen', 'wp_' );
ksa_user( $origin, 'wp_', 1, 'dueno', KSA_DUENO, 'dueno@origen.test', 'administrator' );
ksa_user( $origin, 'wp_', 2, 'editora', KSA_EDITORA, 'editora@origen.test', 'editor' );
ksa_options( $origin, 'wp_', array( 'siteurl' => 'https://origen.test', 'home' => 'https://origen.test', 'blogname' => 'Origen', 'active_plugins' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' ) ) ) );
function ksa_options( Bk_Sqlite_DB $db, $prefix, array $rows ) { foreach ( $rows as $name => $value ) $db->seed( $prefix . 'options', array( array( 'option_name' => $name, 'option_value' => is_scalar( $value ) ? (string) $value : serialize( $value ) ) ) ); }
$GLOBALS['wpdb'] = $origin;
$file = basename( bk_backup() );
$own = basename( bk_backup( true ) );
/** Restaurar por el motor (service_restore) en este sitio, con «admin» (1) lanzándola. */
$engine = function( array $args, $home = 'https://destino.test', $prefix = 'wpkx_', $backup = null ) use ( $file ) {
	$db = ksa_db( 'motor-' . md5( json_encode( $args ) . $home . $prefix . (string) $backup ), $prefix );
	ksa_user( $db, $prefix, 1, 'admin', KSA_ADMIN, 'admin@destino.test', 'administrator', array( 'session_tokens' => ksa_sessions( str_repeat( 'a', 64 ) ), '_application_passwords' => ksa_apps( array( 'u-admin' ) ) ) );
	ksa_user( $db, $prefix, 2, 'soporte', KSA_SOPORTE, 'soporte@destino.test', 'administrator', array( 'session_tokens' => ksa_sessions( str_repeat( 'b', 64 ) ), '_application_passwords' => ksa_apps( array( 'u-soporte' ) ) ) );
	ksa_user( $db, $prefix, 3, 'colaborador', KSA_EDITOR, 'colaborador@destino.test', 'editor' );
	ksa_options( $db, $prefix, array( 'siteurl' => $home, 'home' => $home, 'blogname' => 'Destino' ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $home;
	$GLOBALS['bk_options']['siteurl'] = $home;
	$GLOBALS['ksa_user'] = 1;
	try {
		$result = Digitalisimo_Backups::service_restore( null === $backup ? $file : $backup, $args + array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => false, 'sync' => true, 'drive_cli' => true ) );
	} finally {
		$GLOBALS['ksa_user'] = 0;
	}
	ksa_check( ! is_wp_error( $result ) && ! empty( $result['restored'] ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	return array( $db, $result );
};
ksa_case( 'j · el motor: con el respaldo de otra instalación, los administradores de este sitio siguen entrando (por defecto); keep_admins = false los deja fuera', function() use ( $engine, $secrets ) {
	list( $db, $result ) = $engine( array() );
	$soporte = ksa_row( $db, 'wpkx_', 'soporte' );
	ksa_check( $soporte && KSA_SOPORTE === $soporte['user_pass'] && ksa_is_admin( $db, 'wpkx_', (int) $soporte['ID'] ), 'Tras restaurar el sitio de otra instalación, «soporte» (administrador de este sitio) ya no entra.' );
	ksa_check( KSA_ADMIN === ksa_row( $db, 'wpkx_', 'admin' )['user_pass'] && null === ksa_row( $db, 'wpkx_', 'colaborador' ) && 'dueno' === $db->value( 'wpkx_users', 'user_login', 'ID = 1' ), 'Quien restaura, el editor o la cuenta del respaldo no quedaron como debían.' );
	$access = (array) ( $result['access'] ?? array() );
	ksa_check( ! empty( $access['kept'] ) && ! empty( $access['admins_kept'] ) && array( 'admin', 'soporte' ) === ( $access['kept_accounts'] ?? null ), 'La respuesta no dice quiénes conservaron su acceso: ' . json_encode( $access ) );
	ksa_check( false !== strpos( (string) $result['notice'], 'los administradores de este sitio: «soporte»' ), 'El aviso final no nombra a los administradores que siguen: ' . substr( (string) $result['notice'], 0, 400 ) );
	$view = Digitalisimo_Backups::service_job_follow( $result['follow']['job'], $result['follow']['token'] );
	ksa_check( ! is_wp_error( $view ) && array( 'admin', 'soporte' ) === ( $view['restore']['result']['access']['kept_accounts'] ?? null ), 'El estado del trabajo no dice quiénes conservaron su acceso.' );
	$json = json_encode( $result ) . json_encode( $view );
	foreach ( $secrets as $secret ) ksa_check( false === strpos( $json, $secret ), 'La respuesta lleva un secreto o un correo: ' . substr( $secret, 0, 14 ) );
	list( $db, $result ) = $engine( array( 'keep_admins' => false ), 'https://otro.test' );
	ksa_check( null === ksa_row( $db, 'wpkx_', 'soporte' ) && null !== ksa_row( $db, 'wpkx_', 'admin' ) && 'off' === ( $result['access']['admins_state'] ?? '' ), 'Con keep_admins = false se conservó a «soporte» (o no a quien restaura).' );
}, true );

ksa_case( 'j · el motor: con keep_access = false sigue el otro administrador y no quien restaura; con el respaldo de esta misma instalación no cambia nada', function() use ( $engine, $own ) {
	list( $db, $result ) = $engine( array( 'keep_access' => false ), 'https://tercero.test' );
	ksa_check( null === ksa_row( $db, 'wpkx_', 'admin' ) && null !== ksa_row( $db, 'wpkx_', 'soporte' ) && 'off' === ( $result['access']['state'] ?? '' ) && array( 'soporte' ) === ( $result['access']['kept_accounts'] ?? null ), 'Con keep_access = false no quedó sólo «soporte»: ' . json_encode( $result['access'] ?? null ) );
	// Un respaldo de esta misma instalación (su fila con la identidad de aquí), también con otro prefijo: nada se conserva.
	list( $db, $result ) = $engine( array(), 'https://origen.test', 'wpkx_', $own );
	ksa_check( null === ksa_row( $db, 'wpkx_', 'soporte' ) && null === ksa_row( $db, 'wpkx_', 'admin' ) && 'same_site' === ( $result['access']['admins_state'] ?? '' ) && 'same_site' === ( $result['access']['state'] ?? '' ), 'Con un respaldo de esta misma instalación se conservaron cuentas de ahora: ' . json_encode( $result['access'] ?? null ) );
	list( $db, $result ) = $engine( array(), 'https://origen.test', 'wp_', $own );
	ksa_check( null === ksa_row( $db, 'wp_', 'soporte' ) && 'same_site' === ( $result['access']['admins_state'] ?? '' ), 'El respaldo propio en la misma dirección conservó a «soporte».' );
}, true );

/* ------------------------------------------------------------------------------------------------- k · muertes */

/** Un trabajo de restauración con su tables.json ya cambiado ('after'). */
function ksa_job_after( Bk_Sqlite_DB $db ) {
	$id = Digitalisimo_Backups_Job_Store::new_id();
	$tag = substr( md5( $id ), 0, 6 );
	file_put_contents( Digitalisimo_Backups_Job_Store::staging( $id ) . '/tables.json', json_encode( array( array( 'source' => 'wp_users', 'dest' => 'wpkx_users', 'temp' => 'wpkx_dgbkr_' . $tag . '_000', 'old' => 'wpkx_dgbko_' . $tag . '_000' ) ) ) );
	return $id;
}
function ksa_journal( array $entry ) {
	$property = new ReflectionProperty( 'Digitalisimo_Backups', 'access_kept' );
	if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
	$property->setValue( null, $entry );
	bk_call( 'Digitalisimo_Backups', 'write_restore_journal' );
	$property->setValue( null, null );
}
ksa_case( 'k · el proceso muere a mitad de las cuentas: la segunda pasada sigue la decisión anotada, sin duplicar ni renombrar dos veces', function() use ( $destination, $package, $capture, $secrets ) {
	$db = $destination();
	$entry = array( 'v' => 1, 'job' => '', 'state' => 'on', 'admins' => 'on', 'relocated' => true, 'secret' => $capture() );
	ksa_journal( $entry );
	$path = bk_storage() . '/' . Digitalisimo_Backups::RESTORE_JOURNAL;
	$journal = (string) file_get_contents( $path );
	foreach ( $secrets as $secret ) ksa_check( false === strpos( $journal, $secret ), 'El diario lleva en claro: ' . substr( $secret, 0, 14 ) );
	$package( $db, array( array( 7, 'soporte', '$P$Bcliente.cliente.cliente.client', 'cliente@origen.test', 'subscriber' ) ) );
	// La primera pasada muere al escribir las sesiones de «soporte» (después de crear a «admin» y de renombrar la del respaldo).
	$db->fail_on = str_repeat( 'b', 64 );
	bk_call( 'Digitalisimo_Backups', 'restore_access_apply', $entry );
	$journal = json_decode( (string) file_get_contents( $path ), true );
	ksa_check( is_array( $journal['access']['plan'] ?? null ), 'La decisión sobre las cuentas no quedó anotada en el diario antes de cambiar nada.' );
	ksa_check( 'soporte-respaldo' === $db->value( 'wpkx_users', 'user_login', 'ID = 7' ), 'La prueba necesita que la primera pasada haya renombrado la cuenta del respaldo.' );
	$entry['plan'] = $journal['access']['plan'];
	bk_call( 'Digitalisimo_Backups', 'restore_access_apply', $entry );
	bk_call( 'Digitalisimo_Backups', 'remove_restore_journal' );
	$logins = array_column( $db->rows( 'wpkx_users' ), 'user_login', 'ID' );
	ksa_check( array( 1 => 'dueno', 2 => 'editora', 7 => 'soporte-respaldo', 8 => 'admin', 9 => 'soporte' ) === $logins, 'La segunda pasada renombró o creó otra vez: ' . json_encode( $logins ) );
	ksa_check( array( str_repeat( 'b', 64 ) ) === ksa_session_keys( $db, 'wpkx_', 9 ) && ksa_is_admin( $db, 'wpkx_', 9 ) && ! ksa_is_admin( $db, 'wpkx_', 7 ) && array( 'u-soporte' ) === ksa_uuids( $db, 'wpkx_', 9 ), 'La segunda pasada no terminó la cuenta de «soporte» o mezcló permisos.' );
} );

ksa_case( 'k · si el proceso muere justo tras el cambio de tablas, el diario repone a quien restaura y a los administradores', function() use ( $destination, $package, $capture ) {
	$db = $destination();
	ksa_journal( array( 'v' => 1, 'job' => ksa_job_after( $db ), 'state' => 'on', 'admins' => 'on', 'relocated' => true, 'secret' => $capture() ) );
	$package( $db );
	bk_close_dead_locks();
	Digitalisimo_Backups::release_operation_lock( Digitalisimo_Backups::try_operation_lock() );
	ksa_check( ! file_exists( bk_storage() . '/' . Digitalisimo_Backups::RESTORE_JOURNAL ), 'El diario no se aplicó.' );
	ksa_check( KSA_ADMIN === ( ksa_row( $db, 'wpkx_', 'admin' )['user_pass'] ?? null ) && KSA_SOPORTE === ( ksa_row( $db, 'wpkx_', 'soporte' )['user_pass'] ?? null ), 'Tras una muerte justo después del cambio, los administradores no volvieron.' );
} );

ksa_case( 'k · un diario de 1.0.78 (sin la decisión de los administradores) sólo repone a quien restaura', function() use ( $A, $destination, $package ) {
	$db = $destination();
	$sealed = $A::seal( array( 'user' => $A::capture( 1 ), 'login' => $A::login_capture() ) );
	ksa_journal( array( 'v' => 1, 'job' => ksa_job_after( $db ), 'state' => 'on', 'relocated' => true, 'secret' => $sealed ) );
	$package( $db );
	bk_close_dead_locks();
	Digitalisimo_Backups::release_operation_lock( Digitalisimo_Backups::try_operation_lock() );
	ksa_check( null !== ksa_row( $db, 'wpkx_', 'admin' ) && null === ksa_row( $db, 'wpkx_', 'soporte' ), 'El diario de antes del cambio no se aplicó como entonces.' );
} );

/* ------------------------------------------------------------------------------------------------- l · registros */

ksa_case( 'l · nada sensible en registros, eventos, informes ni respuestas', function() use ( $engine, $secrets ) {
	list( $db, $result ) = $engine( array(), 'https://registros.test' );
	ksa_check( null !== ksa_row( $db, 'wpkx_', 'soporte' ), 'La prueba necesita que «soporte» se haya conservado.' );
	$texts = array( 'error_log' => is_file( $GLOBALS['ksa_log'] ) ? (string) file_get_contents( $GLOBALS['ksa_log'] ) : '' );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( bk_storage(), FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $item ) {
		if ( ! $item->isFile() || preg_match( '/\.(zip|dgbk|sqlite|sql|part|gz)$/i', $item->getFilename() ) ) continue;
		if ( $item->getSize() > 8 * 1048576 ) continue;
		$texts[ substr( $item->getPathname(), strlen( bk_storage() ) ) ] = (string) file_get_contents( $item->getPathname() );
	}
	$texts['respuesta'] = json_encode( $result );
	$texts['opciones'] = json_encode( array( $GLOBALS['bk_options'], $GLOBALS['bk_site_options'] ?? array() ) );
	$found = array();
	foreach ( $texts as $where => $text ) foreach ( $secrets as $secret ) if ( false !== strpos( $text, $secret ) ) $found[] = $where . ': ' . substr( $secret, 0, 14 );
	ksa_check( ! $found, 'Hay datos sensibles de las cuentas en: ' . implode( '; ', array_slice( $found, 0, 5 ) ) );
	ksa_check( count( $texts ) > 3, 'La prueba no encontró registros que revisar.' );
}, true );

/* ------------------------------------------------------------------------------------------------- m · superficies */

ksa_case( 'm · la confirmación (pantalla y verify), REST y WP-CLI: quiénes conservarán su acceso y keep_admins', function() use ( $destination, $file ) {
	$db = $destination();
	$GLOBALS['ksa_user'] = 1;
	try {
		$fields = Digitalisimo_Backups::keep_access_fields();
		$summary = bk_call( 'Digitalisimo_Backups', 'keep_access_summary', $file );
		$verify = Digitalisimo_Backups::service_verify( $file, array() );
	} finally {
		$GLOBALS['ksa_user'] = 0;
	}
	ksa_check( false !== strpos( $fields, 'name="keep_admins" value="1" checked' ) && false !== strpos( $fields, 'keep_admins_shown' ) && false !== strpos( $fields, '«soporte»' ) && false !== strpos( $fields, 'name="keep_access" value="1" checked' ), 'La pantalla no ofrece «Conservar los administradores de este sitio» con sus nombres: ' . strip_tags( $fields ) );
	ksa_check( false !== strpos( $summary, '«admin» (tú)' ) && false !== strpos( $summary, '«soporte»' ), 'La confirmación no dice quiénes siguen entrando: ' . $summary );
	ksa_check( ! is_wp_error( $verify ) && ! empty( $verify['access']['applies'] ) && 'admin' === ( $verify['access']['you'] ?? '' ) && array( 'soporte' ) === ( $verify['access']['admins'] ?? null ), 'verify no dice quiénes conservarían su acceso: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : ( $verify['access'] ?? null ) ) );
	ksa_check( false === strpos( json_encode( $verify ), 'soporte@destino.test' ), 'verify lleva un correo.' );
	require_once dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups-rest.php';
	$GLOBALS['ksa_routes'] = array();
	Digitalisimo_Backups_Rest::routes();
	$found = array();
	foreach ( (array) $GLOBALS['ksa_routes'] as $route => $args ) foreach ( (array) $args as $one ) if ( is_array( $one ) && isset( $one['args']['keep_admins'] ) && true === ( $one['args']['keep_admins']['default'] ?? null ) ) $found[] = preg_replace( '/\(\?P<[^)]+\)/', '<x>', $route );
	ksa_check( 2 === count( $found ), 'La REST no ofrece keep_admins (activo por defecto) en restore y migration/pull: ' . json_encode( $found ) );
	$rest = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups-rest.php' );
	ksa_check( 2 === substr_count( $rest, "'keep_admins' => null === \$request['keep_admins'] ? true : (bool) \$request['keep_admins']" ), 'La REST no pasa keep_admins al caso de uso.' );
	$cli = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups-cli.php' );
	ksa_check( 2 === substr_count( $cli, '[--[no-]keep-admins]' ) && 2 === substr_count( $cli, "'keep_admins' => self::flag( \$assoc_args, 'keep-admins', true )" ), 'WP-CLI no ofrece --[no-]keep-admins en restore y migrate.' );
	foreach ( array( 'class-backups.php', 'class-backups-migrate.php' ) as $name ) ksa_check( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-backups/includes/' . $name ), "\$keep_admins = empty( \$_POST['keep_admins_shown'] ) || ! empty( \$_POST['keep_admins'] );" ), $name . ' no lee la casilla de los administradores.' );
} );

bk_cleanup();
foreach ( glob( $GLOBALS['bk_root'] . '-*.sqlite' ) ?: array() as $sqlite ) @unlink( $sqlite );
@unlink( $GLOBALS['ksa_log'] );
echo $failures ? "Conservar a los administradores de este sitio: {$failures} caso(s) con fallos.\n" : "DIGITALÍSIMO Backups: al restaurar el sitio de otra instalación, quien restaura y los administradores de este sitio siguen entrando con su usuario, su contraseña, sus sesiones y sus contraseñas de aplicación (una cuenta administradora del respaldo con el mismo usuario se funde con la suya, una que no lo era cede su usuario), sin conservar nunca una cuenta que aquí no es administradora ni dejar secretos en registros.\n";
exit( $failures ? 1 : 0 );
