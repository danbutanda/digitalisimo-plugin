<?php
/**
 * Revisión B2 de Backups 1.0.79: sin AUTH_KEY y SECURE_AUTH_KEY propias en wp-config.php (las de ejemplo de WordPress), el
 * diario de la restauración guardaría sin cifrar las credenciales de las cuentas que se conservan (hashes, sesiones,
 * contraseñas de aplicación de todos los administradores de este sitio). La confirmación lo avisa antes: las casillas de
 * la pantalla, la línea de quiénes siguen entrando y `verify` (`access.unsealed` y su texto). Con claves propias, no
 * (tests/backups-accounts-review.php). Con el código de aebdc90f falla. Escribe «FALLA ·».
 */
define( 'AUTH_KEY', 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY', 'put your unique phrase here' );
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['rvk_user'] = 0;
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) $GLOBALS['rvk_user']; } }
$failures = 0;
function rvk_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function rvk_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
function rvk_db( $name, $prefix, $home ) {
	$path = $GLOBALS['bk_root'] . '-' . $name . '.sqlite';
	@unlink( $path );
	$db = new Bk_Sqlite_DB( $path, $prefix );
	$db->pdo->exec( "CREATE TABLE `{$prefix}users` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60) NOT NULL DEFAULT '', `user_pass` varchar(255) NOT NULL DEFAULT '', `user_nicename` varchar(50) NOT NULL DEFAULT '', `user_email` varchar(100) NOT NULL DEFAULT '', `user_url` varchar(100) NOT NULL DEFAULT '', `user_registered` datetime NOT NULL DEFAULT '2020-01-01 00:00:00', `user_activation_key` varchar(255) NOT NULL DEFAULT '', `user_status` int(11) NOT NULL DEFAULT 0, `display_name` varchar(250) NOT NULL DEFAULT '', `spam` tinyint(2) NOT NULL DEFAULT 0, `deleted` tinyint(2) NOT NULL DEFAULT 0)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}usermeta` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	foreach ( array( 'siteurl' => $home, 'home' => $home, 'blogname' => $name, 'active_plugins' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' ) ) ) as $option => $value ) $db->seed( $prefix . 'options', array( array( 'option_name' => $option, 'option_value' => $value ) ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = $home;
	return $db;
}
function rvk_admin( Bk_Sqlite_DB $db, $prefix, $id, $login ) {
	$db->seed( $prefix . 'users', array( array( 'ID' => $id, 'user_login' => $login, 'user_pass' => '$P$B' . str_repeat( $login[0], 31 ), 'user_nicename' => $login, 'user_email' => $login . '@ejemplo.test' ) ) );
	$db->seed( $prefix . 'usermeta', array( array( 'user_id' => $id, 'meta_key' => $prefix . 'capabilities', 'meta_value' => serialize( array( 'administrator' => true ) ) ) ) );
}

// El respaldo de otra instalación (otro.test) y este sitio (aqui.test) con dos administradores.
$GLOBALS['bk_options'] = array( 'blogname' => 'Origen' );
$origin = rvk_db( 'origen', 'wp_', 'https://otro.test' );
rvk_admin( $origin, 'wp_', 1, 'dueno' );
$file = basename( bk_backup() );
@unlink( bk_storage() . '/.installation-id' );
$here = rvk_db( 'aqui', 'wpaq_', 'https://aqui.test' );
rvk_admin( $here, 'wpaq_', 1, 'admin' );
rvk_admin( $here, 'wpaq_', 2, 'soporte' );
$GLOBALS['rvk_user'] = 1;

rvk_case( 'B2 · la pantalla avisa que el diario guardaría sin cifrar las credenciales de las cuentas que se conservan', function() use ( $file ) {
	rvk_check( ! Digitalisimo_Backups_Credentials::can_encrypt(), 'La prueba necesita un sitio sin claves propias.' );
	$fields = Digitalisimo_Backups::keep_access_fields();
	$summary = bk_call( 'Digitalisimo_Backups', 'keep_access_summary', $file );
	rvk_check( false !== strpos( $fields, 'AUTH_KEY y SECURE_AUTH_KEY propias' ) && false !== strpos( $fields, 'sin cifrar' ), 'Las casillas no avisan del diario sin cifrar: ' . strip_tags( $fields ) );
	rvk_check( false !== strpos( $summary, '«soporte»' ) && false !== strpos( $summary, 'sin cifrar' ), 'La confirmación de la fila no lo avisa: ' . $summary );
} );

rvk_case( 'B2 · verify lo dice (`access.unsealed` y su texto)', function() use ( $file ) {
	$verify = Digitalisimo_Backups::service_verify( $file, array() );
	$access = is_wp_error( $verify ) ? array() : (array) $verify['access'];
	rvk_check( 'other' === ( $access['identity'] ?? '' ) && true === ( $access['unsealed'] ?? null ) && false !== strpos( (string) ( $access['text'] ?? '' ), 'sin cifrar' ), 'verify no avisa del diario sin cifrar: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $access ) );
} );

bk_cleanup();
foreach ( glob( $GLOBALS['bk_root'] . '-*.sqlite' ) ?: array() as $sqlite ) @unlink( $sqlite );
echo $failures ? "Sin claves propias: {$failures} caso(s) con fallos.\n" : "DIGITALÍSIMO Backups: sin claves propias en wp-config.php, la confirmación avisa que el diario de la restauración guardaría sin cifrar las credenciales de las cuentas que se conservan.\n";
exit( $failures ? 1 : 0 );
