<?php
/**
 * Revisión adversarial de Backups 1.0.79 (2026-11-17), cuentas y acceso: los casos de la revisión (rev-pruebas.php) como
 * pruebas del repositorio.
 *
 *   A3 · un respaldo de ESTE sitio subido otra vez, agregado o sin fila no cuenta como de otra instalación: lleva la
 *        identidad de la instalación en su manifiesto firmado y se restaura como el mismo sitio (tras un hackeo vuelven
 *        las cuentas del respaldo y no se conserva la que puso el atacante). Uno que sólo lo parece (misma dirección,
 *        prefijo y carpeta, sin la identidad) no conserva ninguna cuenta de ahora salvo que se pida, y la confirmación lo
 *        dice con los administradores creados después del respaldo;
 *   A4 · al fundir una cuenta, lo del respaldo (contraseñas de aplicación, claves de API) nunca abre más de lo que tenía
 *        allí y el nivel de superadministrador sólo lo da este sitio;
 *   M5 · mismo correo → la misma persona (una cuenta, con el segundo factor y el inicio social de aquí); mismo usuario y
 *        otro correo → otra persona (la del respaldo cambia de usuario y conserva su segundo factor), con Two Factor y
 *        Wordfence 2FA; una cuenta que se crea no hereda lo que el respaldo dejó con su ID;
 *   M2 · «Restaurar un archivo suelto» pinta una sola vez las casillas de conservar acceso y administradores.
 * Con el código de aebdc90f fallan todos. Base de datos SQLite de verdad (tests/lib/backups-sqlite.php). Escribe «FALLA ·».
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['rva_user'] = 0;
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) $GLOBALS['rva_user']; } }
if ( ! function_exists( 'get_main_network_id' ) ) { function get_main_network_id() { return 1; } }
if ( ! function_exists( 'get_network_option' ) ) { function get_network_option( $id, $key, $default = false ) { return is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default ); } }
if ( ! function_exists( 'update_network_option' ) ) { function update_network_option( $id, $key, $value ) { return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value ); } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route( $namespace, $route, $args ) { $GLOBALS['rva_routes'][ $route ] = $args; return true; } }
/** Una red: el prefijo de cada sitio (wp_N_). */
class Rva_Network_DB extends Bk_Sqlite_DB {
	public function get_blog_prefix( $blog_id = null ) { $blog_id = null === $blog_id ? 1 : (int) $blog_id; return 1 === $blog_id ? $this->base_prefix : $this->base_prefix . $blog_id . '_'; }
}

$A = 'Digitalisimo_Backups_Restore_Access';
$failures = 0;
function rva_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function rva_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	} finally {
		$GLOBALS['bk_multisite'] = false;
		$GLOBALS['rva_user'] = 0;
	}
}
function rva_db( $name, $prefix, $network = false ) {
	$path = $GLOBALS['bk_root'] . '-' . $name . '-' . bin2hex( random_bytes( 3 ) ) . '.sqlite';
	$db = $network ? new Rva_Network_DB( $path, $prefix ) : new Bk_Sqlite_DB( $path, $prefix );
	rva_schema( $db, $prefix );
	return $db;
}
function rva_schema( Bk_Sqlite_DB $db, $prefix, $wordfence = false ) {
	$db->pdo->exec( "CREATE TABLE `{$prefix}users` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60) NOT NULL DEFAULT '', `user_pass` varchar(255) NOT NULL DEFAULT '', `user_nicename` varchar(50) NOT NULL DEFAULT '', `user_email` varchar(100) NOT NULL DEFAULT '', `user_url` varchar(100) NOT NULL DEFAULT '', `user_registered` datetime NOT NULL DEFAULT '2020-01-01 00:00:00', `user_activation_key` varchar(255) NOT NULL DEFAULT '', `user_status` int(11) NOT NULL DEFAULT 0, `display_name` varchar(250) NOT NULL DEFAULT '', `spam` tinyint(2) NOT NULL DEFAULT 0, `deleted` tinyint(2) NOT NULL DEFAULT 0)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}usermeta` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	if ( $wordfence ) $db->pdo->exec( "CREATE TABLE `{$prefix}wfls_2fa_secrets` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint NOT NULL, `secret` blob NOT NULL, `recovery` blob NOT NULL, `ctime` int NOT NULL DEFAULT 0, `vtime` int NOT NULL DEFAULT 0, `mode` varchar(20) NOT NULL DEFAULT 'authenticator')" );
}
/** Tablas de un sitio después del cambio (el SQL del respaldo): se vacían y se crean otra vez. */
function rva_swap( Bk_Sqlite_DB $db, $prefix, $wordfence = false, array $extra = array() ) {
	foreach ( array_merge( array( 'users', 'usermeta', 'options' ), $wordfence ? array( 'wfls_2fa_secrets' ) : array(), $extra ) as $table ) $db->pdo->exec( 'DROP TABLE IF EXISTS `' . $prefix . $table . '`' );
	rva_schema( $db, $prefix, $wordfence );
}
function rva_user( Bk_Sqlite_DB $db, $prefix, $id, $login, $pass, $email, $role, array $meta = array(), array $columns = array() ) {
	$db->seed( $prefix . 'users', array( $columns + array( 'ID' => $id, 'user_login' => $login, 'user_pass' => $pass, 'user_nicename' => $login, 'user_email' => $email, 'display_name' => ucfirst( $login ) ) ) );
	if ( null !== $role ) $meta = array( $prefix . 'capabilities' => serialize( array( $role => true ) ), $prefix . 'user_level' => 'administrator' === $role ? '10' : '0' ) + $meta;
	foreach ( $meta as $key => $value ) $db->seed( $prefix . 'usermeta', array( array( 'user_id' => $id, 'meta_key' => $key, 'meta_value' => $value ) ) );
}
function rva_meta( Bk_Sqlite_DB $db, $prefix, $id, $key ) {
	$rows = $db->rows( $prefix . 'usermeta', 'user_id = ' . (int) $id . " AND meta_key = '" . $key . "'" );
	return $rows ? $rows[0]['meta_value'] : null;
}
function rva_row( Bk_Sqlite_DB $db, $prefix, $login ) {
	$rows = $db->rows( $prefix . 'users', "user_login = '" . $login . "'" );
	return $rows ? $rows[0] : null;
}
function rva_admin( Bk_Sqlite_DB $db, $prefix, $id ) {
	$caps = @unserialize( (string) rva_meta( $db, $prefix, $id, $prefix . 'capabilities' ) );
	return is_array( $caps ) && ! empty( $caps['administrator'] );
}
function rva_app( $uuid ) { return array( 'uuid' => $uuid, 'app_id' => '', 'name' => 'App ' . $uuid, 'password' => '$generic$' . hash( 'sha256', $uuid ), 'created' => 1762900000, 'last_used' => null, 'last_ip' => null ); }
function rva_apps( array $uuids ) { return serialize( array_map( 'rva_app', $uuids ) ); }
function rva_uuids( Bk_Sqlite_DB $db, $prefix, $id ) { $list = array_column( (array) @unserialize( (string) rva_meta( $db, $prefix, $id, '_application_passwords' ) ), 'uuid' ); sort( $list ); return $list; }
function rva_sessions( $verifier ) { return serialize( array( $verifier => array( 'expiration' => time() + 86400, 'ip' => '127.0.0.1', 'ua' => 'prueba', 'login' => time() ) ) ); }
function rva_secrets( Bk_Sqlite_DB $db, $prefix, $id ) { return array_column( $db->rows( $prefix . 'wfls_2fa_secrets', 'user_id = ' . (int) $id ), 'secret' ); }
/** Lo que el motor apunta antes del cambio (quien restaura: el usuario $restorer) y lo que hace tras el SQL. */
function rva_capture( $restorer = 1, $prefix = null ) {
	$A = 'Digitalisimo_Backups_Restore_Access';
	return $A::seal( array( 'users' => $A::capture_accounts( $restorer, true, $prefix ), 'login' => $A::login_capture(), 'salts' => $A::salts_capture() ) );
}
function rva_apply( $sealed ) {
	$A = 'Digitalisimo_Backups_Restore_Access';
	return $A::apply( array( 'access' => 'on', 'admins' => 'on' ), $A::open( $sealed ), true );
}
const RVA_AQUI = '$wp$2y$10$aquiAquiAquiAquiAquiAquiAquiAquiAquiAquiAquiAquiAquiA';
const RVA_SOPORTE = '$wp$2y$10$soporteSoporteSoporteSoporteSoporteSoporteSoporteSo';
const RVA_ATACANTE = '$wp$2y$10$atacanteAtacanteAtacanteAtacanteAtacanteAtacanteAt';

/* ------------------------------------------------------------------------------- A3 · de qué instalación es */

// El sitio «mismo.test» (prefijo wp_) hace sus respaldos con el motor real.
$GLOBALS['bk_options'] = array( 'home' => 'https://mismo.test', 'siteurl' => 'https://mismo.test', 'blogname' => 'Mismo' );
$origin = rva_db( 'origen', 'wp_' );
rva_user( $origin, 'wp_', 1, 'dueno', '$P$Bdueno.del.respaldo.limpio.lim', 'dueno@mismo.test', 'administrator' );
rva_user( $origin, 'wp_', 2, 'editora', '$P$Beditora.editora.editora.edito', 'editora@mismo.test', 'editor' );
foreach ( array( 'siteurl' => 'https://mismo.test', 'home' => 'https://mismo.test', 'blogname' => 'Mismo', 'active_plugins' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' ) ) ) as $name => $value ) $origin->seed( 'wp_options', array( array( 'option_name' => $name, 'option_value' => $value ) ) );
$GLOBALS['wpdb'] = $origin;
$created = basename( bk_backup( true ) );   // Su fila «hecha aquí».
$loose = basename( bk_backup() );           // Sin fila (como traído de un destino o copiado a mano).
$uploaded = basename( bk_backup() );        // Subido otra vez: su fila dice «Subido».
$history = (array) get_option( Digitalisimo_Backups::HISTORY, array() );
$history[] = array( 'file' => $uploaded, 'date' => current_time( 'mysql' ), 'origin' => 'uploaded', 'scope' => 'installation', 'size' => filesize( bk_storage() . '/' . $uploaded ), 'sha256' => hash_file( 'sha256', bk_storage() . '/' . $uploaded ), 'upload' => array( 'home' => 'https://mismo.test', 'site_key' => false, 'same_site' => false ) );
update_option( Digitalisimo_Backups::HISTORY, $history );
$stamp = Digitalisimo_Backups::installation_stamp();

/**
 * Restaurar por el motor (service_restore) en «mismo.test»: «admin» (1, quien restaura), «soporte» (2) y «x-admin» (3), el
 * administrador que creó el atacante después del respaldo.
 */
$engine = function( $file, array $args = array(), $home = 'https://mismo.test', $prefix = 'wp_' ) {
	$db = rva_db( 'motor', $prefix );
	rva_user( $db, $prefix, 1, 'admin', RVA_AQUI, 'admin@mismo.test', 'administrator', array( 'session_tokens' => rva_sessions( str_repeat( 'a', 64 ) ) ) );
	rva_user( $db, $prefix, 2, 'soporte', RVA_SOPORTE, 'soporte@mismo.test', 'administrator' );
	rva_user( $db, $prefix, 3, 'x-admin', RVA_ATACANTE, 'x@atacante.test', 'administrator', array(), array( 'user_registered' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) ) );
	foreach ( array( 'siteurl' => $home, 'home' => $home, 'blogname' => 'Ahora' ) as $name => $value ) $db->seed( $prefix . 'options', array( array( 'option_name' => $name, 'option_value' => $value ) ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $home;
	$GLOBALS['bk_options']['siteurl'] = $home;
	$GLOBALS['rva_user'] = 1;
	try {
		$result = Digitalisimo_Backups::service_restore( $file, $args + array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => false, 'sync' => true, 'drive_cli' => true ) );
	} finally {
		$GLOBALS['rva_user'] = 0;
	}
	rva_check( ! is_wp_error( $result ) && ! empty( $result['restored'] ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	return array( $db, $result );
};

rva_case( 'A3 · el manifiesto firmado lleva la identidad de la instalación (id, dirección y prefijo)', function() use ( $loose, $stamp ) {
	$zip = new ZipArchive();
	rva_check( true === $zip->open( bk_storage() . '/' . $loose ), 'No se abrió el respaldo.' );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	$identity = (array) ( $manifest['installation'] ?? array() );
	rva_check( ! empty( $stamp['id'] ) && $stamp['id'] === ( $identity['id'] ?? null ) && 'wp_' === ( $identity['prefix'] ?? null ) && '' !== (string) ( $identity['home'] ?? '' ), 'El manifiesto no lleva la identidad de esta instalación: ' . json_encode( $identity ) );
	rva_check( ! empty( $manifest['auth'] ) || ! empty( $manifest['backup_id'] ), 'El manifiesto no es el del motor.' );
} );

rva_case( 'A3 · subido otra vez, agregado o sin fila, el respaldo de este sitio es de esta instalación (caso 5 de la revisión)', function() use ( $created, $loose, $uploaded ) {
	$A = 'Digitalisimo_Backups_Restore_Access';
	$identity = function( $file ) { return bk_call( 'Digitalisimo_Backups', 'restore_identity', $file ); };
	rva_check( 'same' === $identity( $created ), 'El respaldo hecho aquí no es de esta instalación.' );
	rva_check( 'same' === $identity( $uploaded ), 'El mismo respaldo subido otra vez se trata como de otra instalación: ' . $identity( $uploaded ) );
	rva_check( 'same' === $identity( $loose ), 'El mismo respaldo sin fila se trata como de otra instalación: ' . $identity( $loose ) );
	rva_check( 'same_site' === $A::decide_admins( true, 'same' !== $identity( $uploaded ), false ), 'decide_admins() conservaría a los administradores de ahora con el respaldo propio subido.' );
} );

rva_case( 'A3 · tras un hackeo, restaurar el respaldo propio subido otra vez no conserva al administrador del atacante', function() use ( $engine, $uploaded ) {
	list( $db, $result ) = $engine( $uploaded );
	$access = (array) ( $result['access'] ?? array() );
	rva_check( null === rva_row( $db, 'wp_', 'x-admin' ), 'El administrador que puso el atacante sigue entrando tras restaurar el respaldo limpio: ' . json_encode( $access ) );
	rva_check( null === rva_row( $db, 'wp_', 'admin' ) && null === rva_row( $db, 'wp_', 'soporte' ) && '$P$Bdueno.del.respaldo.limpio.lim' === ( rva_row( $db, 'wp_', 'dueno' )['user_pass'] ?? null ), 'No volvieron las cuentas del respaldo (como al restaurar el mismo sitio).' );
	rva_check( 'same_site' === ( $access['state'] ?? '' ) && 'same_site' === ( $access['admins_state'] ?? '' ), 'El informe no dice que es el mismo sitio: ' . json_encode( $access ) );
} );

// Otra identidad: el almacén privado se borró (su identificador cambia). Los respaldos de antes ya no la llevan, pero
// tienen la misma dirección, prefijo y carpeta: parecen de este mismo sitio.
@unlink( bk_storage() . '/.installation-id' );

rva_case( 'A3 · sin la identidad de aquí pero con la misma dirección, prefijo y carpeta: parece este sitio y por defecto no conserva cuentas de ahora', function() use ( $engine, $loose ) {
	rva_check( 'likely' === bk_call( 'Digitalisimo_Backups', 'restore_identity', $loose ), 'Un respaldo con la dirección, el prefijo y la carpeta de aquí no parece de este sitio.' );
	list( $db, $result ) = $engine( $loose );
	$access = (array) ( $result['access'] ?? array() );
	rva_check( null === rva_row( $db, 'wp_', 'x-admin' ) && null === rva_row( $db, 'wp_', 'admin' ) && null === rva_row( $db, 'wp_', 'soporte' ), 'Por defecto se conservaron cuentas de ahora con un respaldo que parece de este mismo sitio: ' . json_encode( $access ) );
	rva_check( 'off' === ( $access['state'] ?? '' ) && 'off' === ( $access['admins_state'] ?? '' ) && ! empty( $access['likely_same'] ) && false !== strpos( (string) ( $access['text'] ?? '' ), 'parece de este mismo sitio' ), 'El informe no dice que parecía de este mismo sitio: ' . json_encode( $access ) );
	// Pidiéndolo (es de otra instalación en la misma dirección), sí.
	list( $db, $result ) = $engine( $loose, array( 'keep_access' => true, 'keep_admins' => true ) );
	rva_check( RVA_AQUI === ( rva_row( $db, 'wp_', 'admin' )['user_pass'] ?? null ) && RVA_SOPORTE === ( rva_row( $db, 'wp_', 'soporte' )['user_pass'] ?? null ) && 'on' === ( $result['access']['admins_state'] ?? '' ), 'Pedido explícitamente, no se conservaron las cuentas de ahora.' );
	// En otra dirección: otra instalación, se conservan por defecto.
	list( $db, $result ) = $engine( $loose, array(), 'https://otro.test' );
	rva_check( RVA_SOPORTE === ( rva_row( $db, 'wp_', 'soporte' )['user_pass'] ?? null ) && 'on' === ( $result['access']['admins_state'] ?? '' ), 'Con el respaldo de otra dirección no se conservaron los administradores por defecto.' );
} );

rva_case( 'A3 · la confirmación (pantalla, verify, REST y WP-CLI) pide decidir y nombra a los administradores creados después del respaldo', function() use ( $loose ) {
	$db = rva_db( 'confirmacion', 'wp_' );
	rva_user( $db, 'wp_', 1, 'admin', RVA_AQUI, 'admin@mismo.test', 'administrator' );
	rva_user( $db, 'wp_', 3, 'x-admin', RVA_ATACANTE, 'x@atacante.test', 'administrator', array(), array( 'user_registered' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = 'https://mismo.test';
	$GLOBALS['rva_user'] = 1;
	$verify = Digitalisimo_Backups::service_verify( $loose, array() );
	$summary = bk_call( 'Digitalisimo_Backups', 'keep_access_summary', $loose );
	$likely = Digitalisimo_Backups::keep_access_fields( '', 'likely' );
	$fields = Digitalisimo_Backups::keep_access_fields();
	$access = is_wp_error( $verify ) ? array() : (array) $verify['access'];
	rva_check( 'likely' === ( $access['identity'] ?? '' ) && false === ( $access['default'] ?? null ) && array( 'x-admin' ) === ( $access['newer'] ?? null ) && false !== strpos( (string) ( $access['text'] ?? '' ), 'Parece un respaldo de este mismo sitio' ), 'verify no dice que parece de este mismo sitio ni quién se creó después: ' . json_encode( $access ) );
	rva_check( false !== strpos( $summary, 'Parece un respaldo de este mismo sitio' ) && false !== strpos( $summary, 'creados después del respaldo: «x-admin»' ), 'La pantalla no lo dice: ' . $summary );
	rva_check( false !== strpos( $likely, 'name="keep_admins" value="1">' ) && false === strpos( $likely, 'checked' ), 'Con un respaldo que parece de este sitio las casillas salen marcadas: ' . strip_tags( $likely ) );
	rva_check( false !== strpos( $fields, 'name="keep_admins" value="auto" checked' ) && false !== strpos( $fields, 'name="keep_access" value="auto" checked' ), 'Las casillas de serie no dejan decidir a la restauración (`auto`): ' . $fields );
	rva_check( false === strpos( $fields . $summary . json_encode( $access ), 'sin cifrar' ) && false === ( $access['unsealed'] ?? null ), 'Con claves propias se avisa de un diario sin cifrar (revisión B2).' );
	require_once dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups-rest.php';
	$GLOBALS['rva_routes'] = array();
	Digitalisimo_Backups_Rest::routes();
	$restore = null;
	foreach ( (array) $GLOBALS['rva_routes'] as $route => $args ) if ( preg_match( '#/restore$#', $route ) ) $restore = $args[0]['args'] ?? null;
	rva_check( is_array( $restore ) && ! array_key_exists( 'default', $restore['keep_admins'] ) && ! array_key_exists( 'default', $restore['keep_access'] ), 'La REST fija keep_access o keep_admins por defecto en restore (no deja decidir a la restauración).' );
	require_once dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups-cli.php';
	rva_check( null === Digitalisimo_Backups_Cli::flag( array(), 'keep-admins', null ) && false === Digitalisimo_Backups_Cli::flag( array( 'keep-admins' => false ), 'keep-admins', null ), 'WP-CLI no distingue «sin decir» de --no-keep-admins.' );
} );

/* ------------------------------------------------------------------------------- A4 · lo del respaldo no abre más */

rva_case( 'A4 · red → red de otra instalación: «admin» del respaldo (otra persona, sólo administradora del sitio) no queda superadministradora con sus contraseñas de aplicación (caso 4)', function() {
	$GLOBALS['bk_multisite'] = true;
	$db = rva_db( 'red-a4', 'wp_', true );
	rva_user( $db, 'wp_', 1, 'admin', RVA_AQUI, 'y@destino.test', 'administrator' );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'admin' ) );
	$sealed = rva_capture( 1, 'wp_' );
	rva_swap( $db, 'wp_' );
	rva_user( $db, 'wp_', 1, 'admin', '$P$Borigen.origen.origen.origen.o', 'x@origen.test', 'administrator', array( '_application_passwords' => rva_apps( array( 'app-de-x-en-el-origen' ) ) ) );
	rva_user( $db, 'wp_', 2, 'root', '$P$Broot.root.root.root.root.roo', 'root@origen.test', 'administrator' );
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'root' ) );
	$report = rva_apply( $sealed );
	$admin = rva_row( $db, 'wp_', 'admin' );
	$super = (array) $GLOBALS['bk_site_options']['site_admins'];
	sort( $super );
	rva_check( $admin && RVA_AQUI === $admin['user_pass'] && array() === rva_uuids( $db, 'wp_', (int) $admin['ID'] ), 'La cuenta «admin» superadministradora tiene las contraseñas de aplicación de la otra persona: ' . json_encode( array( $admin['ID'] ?? null, rva_uuids( $db, 'wp_', (int) ( $admin['ID'] ?? 0 ) ) ) ) );
	rva_check( array( 'admin', 'root' ) === $super, 'site_admins no es el de esta red y el del respaldo: ' . json_encode( $super ) );
	$x = rva_row( $db, 'wp_', 'admin-respaldo' );
	rva_check( $x && 1 === (int) $x['ID'] && array( 'app-de-x-en-el-origen' ) === rva_uuids( $db, 'wp_', 1 ) && ! in_array( 'admin-respaldo', $super, true ), 'La cuenta de la otra persona no conservó lo suyo con otro usuario, o ganó la red: ' . json_encode( $x ) );
	rva_check( array( array( 'from' => 'admin', 'to' => 'admin-respaldo' ) ) === $report['renamed_backup'], 'El informe no dice que la cuenta del respaldo cambió de usuario.' );
} );

rva_case( 'A4 · fundida por el mismo correo, lo del respaldo de una cuenta que no era superadministradora se retira (contraseñas de aplicación y claves de API)', function() {
	$GLOBALS['bk_multisite'] = true;
	$db = rva_db( 'red-a4b', 'wp_', true );
	rva_user( $db, 'wp_', 1, 'admin', RVA_AQUI, 'y@destino.test', 'administrator', array( '_application_passwords' => rva_apps( array( 'u-aqui' ) ) ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'admin' ) );
	$sealed = rva_capture( 1, 'wp_' );
	rva_swap( $db, 'wp_', false, array( 'woocommerce_api_keys' ) );
	$db->pdo->exec( 'CREATE TABLE `wp_woocommerce_api_keys` (`key_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint NOT NULL, `description` varchar(200), `permissions` varchar(10), `consumer_key` char(64))' );
	rva_user( $db, 'wp_', 1, 'root', '$P$Broot.root.root.root.root.roo', 'root@origen.test', 'administrator' );
	rva_user( $db, 'wp_', 4, 'jefe', '$P$Bjefe.jefe.jefe.jefe.jefe.jefe', 'y@destino.test', 'administrator', array( '_application_passwords' => rva_apps( array( 'u-jefe' ) ) ) );
	$db->seed( 'wp_woocommerce_api_keys', array( array( 'user_id' => 4, 'description' => 'tienda', 'permissions' => 'read_write', 'consumer_key' => str_repeat( 'c', 64 ) ) ) );
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'root' ) );
	$report = rva_apply( $sealed );
	$admin = rva_row( $db, 'wp_', 'admin' );
	rva_check( $admin && 4 === (int) $admin['ID'] && RVA_AQUI === $admin['user_pass'] && null === rva_row( $db, 'wp_', 'jefe' ), 'No quedó una sola cuenta (la del respaldo, con el usuario y la contraseña de aquí): ' . json_encode( $admin ) );
	rva_check( array( 'u-aqui' ) === rva_uuids( $db, 'wp_', 4 ) && 0 === count( $db->rows( 'wp_woocommerce_api_keys', 'user_id = 4' ) ), 'Las contraseñas de aplicación o las claves de API de una cuenta que no era superadministradora siguen en una que ahora lo es.' );
	rva_check( in_array( 'admin', (array) $GLOBALS['bk_site_options']['site_admins'], true ) && array( array( 'login' => 'admin', 'backup' => 'jefe' ) ) === $report['merged_with'] && false !== strpos( $report['text'], 'Se retiraron 1 contraseña(s) de aplicación' ), 'El informe no dice la fusión ni lo retirado: ' . $report['text'] );
} );

rva_case( 'A4 · el nivel de superadministrador sólo lo da este sitio: una del respaldo que lo era, fundida con una de aquí que no lo es, deja de serlo', function() {
	$GLOBALS['bk_multisite'] = true;
	$db = rva_db( 'red-a4c', 'wp_', true );
	rva_user( $db, 'wp_', 1, 'red-admin', RVA_AQUI, 'red@destino.test', 'administrator' );
	rva_user( $db, 'wp_', 2, 'sitio-admin', RVA_SOPORTE, 'sitio@destino.test', 'administrator' );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'red-admin' ) );
	$sealed = rva_capture( 1, 'wp_' );
	rva_swap( $db, 'wp_' );
	rva_user( $db, 'wp_', 1, 'sitio-admin', '$P$Bsuper.del.respaldo.respaldo.r', 'sitio@destino.test', 'administrator', array( '_application_passwords' => rva_apps( array( 'u-super-respaldo' ) ) ) );
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'sitio-admin' ) );
	$report = rva_apply( $sealed );
	$super = (array) $GLOBALS['bk_site_options']['site_admins'];
	rva_check( ! in_array( 'sitio-admin', $super, true ) && in_array( 'red-admin', $super, true ), '«sitio-admin» (administrador del sitio aquí) quedó superadministrador porque lo era en el respaldo: ' . json_encode( $super ) );
	rva_check( RVA_SOPORTE === ( rva_row( $db, 'wp_', 'sitio-admin' )['user_pass'] ?? null ) && array( 'u-super-respaldo' ) === rva_uuids( $db, 'wp_', 1 ) && false !== strpos( $report['text'], 'sólo lo da esta red' ), 'La cuenta fundida no quedó como la de aquí o el informe no lo dice: ' . $report['text'] );
} );

/* ------------------------------------------------------------------------------- M5 · la regla de fusión */

/** Un sitio individual con Wordfence: «admin» (quien restaura) con su Two Factor y su Wordfence 2FA. */
$here = function( $name ) {
	$db = rva_db( $name, 'wpkx_' );
	rva_swap( $db, 'wpkx_', true );
	rva_user( $db, 'wpkx_', 1, 'admin', RVA_AQUI, 'y@destino.test', 'administrator', array( 'session_tokens' => rva_sessions( str_repeat( 'a', 64 ) ), '_two_factor_enabled_providers' => serialize( array( 'Two_Factor_Totp' ) ), '_two_factor_totp_key' => 'TOTP-DE-AQUI' ) );
	$db->seed( 'wpkx_wfls_2fa_secrets', array( array( 'user_id' => 1, 'secret' => 'WF-AQUI', 'recovery' => 'R-AQUI' ) ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options'] = array( 'home' => 'https://destino.test', 'siteurl' => 'https://destino.test' );
	return $db;
};

rva_case( 'M5 · mismo usuario y otro correo: otra persona; la del respaldo cambia de usuario con su segundo factor y la de aquí sigue con el suyo (caso 3, Two Factor y Wordfence)', function() use ( $here ) {
	$db = $here( 'm5-rename' );
	$sealed = rva_capture( 1 );
	rva_swap( $db, 'wpkx_', true );
	rva_user( $db, 'wpkx_', 1, 'admin', '$P$Borigen.origen.origen.origen.o', 'x@origen.test', 'administrator', array( '_two_factor_enabled_providers' => serialize( array( 'Two_Factor_Totp' ) ), '_two_factor_totp_key' => 'SECRETO-TOTP-DEL-ORIGEN' ) );
	$db->seed( 'wpkx_wfls_2fa_secrets', array( array( 'user_id' => 1, 'secret' => 'WF-ORIGEN', 'recovery' => 'R-ORIGEN' ) ) );
	$report = rva_apply( $sealed );
	$mine = rva_row( $db, 'wpkx_', 'admin' );
	rva_check( $mine && 2 === (int) $mine['ID'] && RVA_AQUI === $mine['user_pass'] && 'TOTP-DE-AQUI' === rva_meta( $db, 'wpkx_', 2, '_two_factor_totp_key' ) && array( 'WF-AQUI' ) === rva_secrets( $db, 'wpkx_', 2 ), '«admin» de aquí no sigue con su contraseña y su propio segundo factor (Two Factor y Wordfence): ' . json_encode( array( $mine['ID'] ?? null, rva_meta( $db, 'wpkx_', 2, '_two_factor_totp_key' ), rva_secrets( $db, 'wpkx_', 2 ) ) ) );
	$other = rva_row( $db, 'wpkx_', 'admin-respaldo' );
	rva_check( $other && 1 === (int) $other['ID'] && '$P$Borigen.origen.origen.origen.o' === $other['user_pass'] && 'SECRETO-TOTP-DEL-ORIGEN' === rva_meta( $db, 'wpkx_', 1, '_two_factor_totp_key' ) && array( 'WF-ORIGEN' ) === rva_secrets( $db, 'wpkx_', 1 ), 'La cuenta de la otra persona no conservó su contraseña y su segundo factor con otro usuario.' );
	rva_check( array( 'admin' ) === $report['created_accounts'] && array( array( 'from' => 'admin', 'to' => 'admin-respaldo' ) ) === $report['renamed_backup'] && false !== strpos( $report['text'], 'con otro correo (otra persona)' ), 'El informe no lo dice: ' . $report['text'] );
} );

rva_case( 'M5 · mismo correo y otro usuario: la misma persona; una cuenta con el ID del respaldo y el usuario, la contraseña y el segundo factor de aquí', function() use ( $here ) {
	$db = $here( 'm5-merge' );
	$sealed = rva_capture( 1 );
	rva_swap( $db, 'wpkx_', true );
	rva_user( $db, 'wpkx_', 1, 'dueno', '$P$Bdueno.del.respaldo.respaldo.re', 'y@destino.test', 'administrator', array( 'description' => 'Escribe desde 2019', 'session_tokens' => rva_sessions( str_repeat( 'z', 64 ) ), '_two_factor_totp_key' => 'TOTP-DEL-RESPALDO', '_two_factor_backup_codes' => 'codigos-del-respaldo', 'nsl_google_id' => 'google-del-respaldo' ) );
	rva_user( $db, 'wpkx_', 2, 'admin', '$P$Botra.persona.otra.persona.otr', 'otra@origen.test', 'editor' );
	$db->seed( 'wpkx_wfls_2fa_secrets', array( array( 'user_id' => 1, 'secret' => 'WF-DEL-RESPALDO', 'recovery' => 'R' ) ) );
	$report = rva_apply( $sealed );
	$admin = rva_row( $db, 'wpkx_', 'admin' );
	rva_check( $admin && 1 === (int) $admin['ID'] && RVA_AQUI === $admin['user_pass'] && 'y@destino.test' === $admin['user_email'] && null === rva_row( $db, 'wpkx_', 'dueno' ) && 'Escribe desde 2019' === rva_meta( $db, 'wpkx_', 1, 'description' ), 'No quedó una sola cuenta con el ID y los datos del respaldo y el usuario y la contraseña de aquí: ' . json_encode( $admin ) );
	rva_check( 'TOTP-DE-AQUI' === rva_meta( $db, 'wpkx_', 1, '_two_factor_totp_key' ) && null === rva_meta( $db, 'wpkx_', 1, '_two_factor_backup_codes' ) && null === rva_meta( $db, 'wpkx_', 1, 'nsl_google_id' ) && array( 'WF-AQUI' ) === rva_secrets( $db, 'wpkx_', 1 ), 'La cuenta fundida conserva el segundo factor o el inicio social del respaldo: ' . json_encode( array( rva_meta( $db, 'wpkx_', 1, '_two_factor_totp_key' ), rva_meta( $db, 'wpkx_', 1, 'nsl_google_id' ), rva_secrets( $db, 'wpkx_', 1 ) ) ) );
	rva_check( array( str_repeat( 'a', 64 ) ) === array_keys( (array) @unserialize( (string) rva_meta( $db, 'wpkx_', 1, 'session_tokens' ) ) ) && rva_admin( $db, 'wpkx_', 1 ), 'La cuenta fundida no tiene las sesiones y el rol de aquí.' );
	$other = rva_row( $db, 'wpkx_', 'admin-respaldo' );
	rva_check( $other && 2 === (int) $other['ID'] && ! rva_admin( $db, 'wpkx_', 2 ), 'La otra persona con el usuario «admin» no cedió su usuario o ganó permisos.' );
	rva_check( 'merged' === $report['account'] && array( array( 'login' => 'admin', 'backup' => 'dueno' ) ) === $report['merged_with'] && false !== strpos( $report['text'], 'como «dueno» (la misma persona)' ), 'El informe no dice la fusión por correo: ' . $report['text'] );
} );

rva_case( 'A4 · una cuenta que se crea no hereda lo que el respaldo dejó con su ID (metas y Wordfence de una cuenta borrada)', function() use ( $here ) {
	$db = $here( 'huerfanos' );
	$db->pdo->exec( "DELETE FROM `wpkx_usermeta` WHERE meta_key LIKE '_two_factor%'" );
	$db->pdo->exec( 'DELETE FROM `wpkx_wfls_2fa_secrets`' );
	$sealed = rva_capture( 1 );
	rva_swap( $db, 'wpkx_', true );
	rva_user( $db, 'wpkx_', 1, 'dueno', '$P$Bdueno.del.respaldo.respaldo.re', 'dueno@origen.test', 'administrator' );
	// La cuenta 2 se borró en el origen, pero sus metas de otros plugins y su fila de Wordfence siguen ahí.
	foreach ( array( 'nsl_google_id' => 'google-huerfano', '_two_factor_totp_key' => 'TOTP-HUERFANO', 'wpkx_2_capabilities' => serialize( array( 'administrator' => true ) ) ) as $key => $value ) $db->seed( 'wpkx_usermeta', array( array( 'user_id' => 2, 'meta_key' => $key, 'meta_value' => $value ) ) );
	$db->seed( 'wpkx_wfls_2fa_secrets', array( array( 'user_id' => 2, 'secret' => 'WF-HUERFANO', 'recovery' => 'R' ) ) );
	rva_apply( $sealed );
	$admin = rva_row( $db, 'wpkx_', 'admin' );
	rva_check( $admin && 2 === (int) $admin['ID'], 'La cuenta de quien restaura no tomó el ID libre 2: ' . json_encode( $admin ) );
	rva_check( null === rva_meta( $db, 'wpkx_', 2, 'nsl_google_id' ) && null === rva_meta( $db, 'wpkx_', 2, '_two_factor_totp_key' ) && null === rva_meta( $db, 'wpkx_', 2, 'wpkx_2_capabilities' ) && array() === rva_secrets( $db, 'wpkx_', 2 ), 'La cuenta creada heredó lo que el respaldo dejó con su ID.' );
} );

/* ------------------------------------------------------------------------------- M2 · la pantalla */

rva_case( 'M2 · «Restaurar un archivo suelto» pinta una sola vez las casillas de conservar acceso y administradores', function() {
	$db = rva_db( 'pantalla', 'wp_' );
	rva_user( $db, 'wp_', 1, 'admin', RVA_AQUI, 'admin@mismo.test', 'administrator' );
	rva_user( $db, 'wp_', 2, 'soporte', RVA_SOPORTE, 'soporte@mismo.test', 'administrator' );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['rva_user'] = 1;
	ob_start();
	bk_call( 'Digitalisimo_Backups', 'restore_panel', array() );
	$html = (string) ob_get_clean();
	$form = substr( $html, (int) strpos( $html, 'digitalisimo-backups-restore-file' ) );
	$form = substr( $form, 0, (int) strpos( $form, '</form>' ) );
	rva_check( 1 === substr_count( $form, 'name="keep_access"' ) && 1 === substr_count( $form, 'name="keep_admins"' ) && 1 === substr_count( $form, 'name="keep_admins_shown"' ), 'El formulario lleva ' . substr_count( $form, 'name="keep_access"' ) . ' casilla(s) keep_access y ' . substr_count( $form, 'name="keep_admins"' ) . ' keep_admins.' );
} );

bk_cleanup();
foreach ( glob( $GLOBALS['bk_root'] . '-*.sqlite' ) ?: array() as $sqlite ) @unlink( $sqlite );
echo $failures ? "Cuentas y acceso (revisión de 1.0.79): {$failures} caso(s) con fallos.\n" : "DIGITALÍSIMO Backups: el respaldo de este sitio subido otra vez se restaura como el mismo sitio (y uno que sólo lo parece no conserva cuentas sin pedirlo), mismo correo es la misma persona y mismo usuario con otro correo es otra, lo del respaldo nunca abre más de lo que tenía y las casillas salen una vez.\n";
exit( $failures ? 1 : 0 );
