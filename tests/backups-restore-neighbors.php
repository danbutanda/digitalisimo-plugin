<?php
/**
 * Otra instalación de WordPress en la misma base de datos, con un prefijo que empieza por el de este sitio (`wp_` y
 * `wp_dev_`; en una red, `wp_<número>_` son sus propios subsitios): la reconoce su `<prefijo>options` con `siteurl`.
 *   (a) el respaldo de `wp_` no lleva como propias las tablas de `wp_dev_` (con la 1.0.64 las llevaba: el respaldo
 *       exporta toda la base);
 *   (b) «Restaurar» nunca toca las tablas de `wp_dev_`, ni siquiera con un respaldo que las trae (como los de la 1.0.64),
 *       y su informe lo dice; las del sitio sí vuelven al respaldo (con la 1.0.64, las de `wp_dev_` volvían al estado del
 *       respaldo y se perdía lo que ese otro sitio escribió después);
 *   (c) el criterio: `wp_dev_` es otra instalación; `wp_2_` también en un sitio individual, pero en una red es un
 *       subsitio propio; una tabla `…options` sin `siteurl` no cuenta.
 * Base de datos SQLite de verdad (tests/lib/backups-sqlite.php) y el motor real de respaldo y de restauración por pasos.
 * Escribe «FALLA ·» si falla.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;

$failures = 0;
function nb_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function nb_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
function nb_options_table( Bk_Sqlite_DB $db, $table, array $rows ) {
	$db->pdo->exec( 'CREATE TABLE `' . $table . "` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$seed = array();
	foreach ( $rows as $name => $value ) $seed[] = array( 'option_name' => $name, 'option_value' => $value );
	$db->seed( $table, $seed );
}
function nb_posts_table( Bk_Sqlite_DB $db, $table, array $posts ) {
	$db->pdo->exec( 'CREATE TABLE `' . $table . "` (`ID` INTEGER PRIMARY KEY, `post_content` longtext NOT NULL DEFAULT '', `guid` varchar(255) NOT NULL DEFAULT '')" );
	$seed = array();
	foreach ( $posts as $id => $content ) $seed[] = array( 'ID' => $id, 'post_content' => $content );
	$db->seed( $table, $seed );
}
function nb_option( Bk_Sqlite_DB $db, $table, $name ) { return $db->value( $table, 'option_value', "option_name = '" . $name . "'" ); }

$db = new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-vecinos.sqlite', 'wp_' );
nb_options_table( $db, 'wp_options', array( 'siteurl' => 'https://sitio.test', 'home' => 'https://sitio.test', 'blogname' => 'Sitio' ) );
nb_posts_table( $db, 'wp_posts', array( 1 => 'entrada del sitio' ) );
// El vecino: otro WordPress con prefijo wp_dev_ en la misma base.
nb_options_table( $db, 'wp_dev_options', array( 'siteurl' => 'https://dev.sitio.test', 'home' => 'https://dev.sitio.test', 'blogname' => 'Dev' ) );
nb_posts_table( $db, 'wp_dev_posts', array( 1 => 'entrada del vecino' ) );
$GLOBALS['wpdb'] = $db;
$GLOBALS['bk_options']['home'] = 'https://sitio.test';
$GLOBALS['bk_options']['siteurl'] = 'https://sitio.test';

nb_case( 'a · el respaldo de wp_ no lleva como propias las tablas de otra instalación (wp_dev_)', function() use ( $db ) {
	$file = bk_backup();
	$zip = new ZipArchive();
	nb_check( true === $zip->open( $file ), 'No se abrió el respaldo.' );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$zip->close();
	$tables = (array) ( $manifest['database_tables'] ?? array() );
	nb_check( in_array( 'wp_options', $tables, true ) && in_array( 'wp_posts', $tables, true ) && false !== strpos( $sql, 'entrada del sitio' ), 'El respaldo no lleva las tablas del sitio: ' . implode( ', ', $tables ) );
	nb_check( ! in_array( 'wp_dev_options', $tables, true ) && ! in_array( 'wp_dev_posts', $tables, true ) && false === strpos( $sql, 'entrada del vecino' ), 'El respaldo de wp_ lleva las tablas de otra instalación (wp_dev_): ' . implode( ', ', $tables ) );
	@unlink( $file );
} );

nb_case( 'b · «Restaurar» nunca toca las tablas de otra instalación, aunque el respaldo las traiga', function() use ( $db ) {
	// Un respaldo que sí trae las tablas de wp_dev_ (como los de la 1.0.64): hecho mientras el vecino aún no se reconocía.
	$db->pdo->exec( "UPDATE wp_dev_options SET option_name = 'siteurl_oculto' WHERE option_name = 'siteurl'" );
	$file = bk_backup( true );
	$db->pdo->exec( "UPDATE wp_dev_options SET option_name = 'siteurl' WHERE option_name = 'siteurl_oculto'" );
	$zip = new ZipArchive();
	$zip->open( $file );
	$carried = false !== strpos( (string) $zip->getFromName( 'database.sql' ), 'entrada del vecino' );
	$zip->close();
	nb_check( $carried, 'La prueba necesita un respaldo que traiga las tablas del vecino.' );
	// Después del respaldo, los dos sitios siguen trabajando.
	$db->pdo->exec( "UPDATE wp_options SET option_value = 'Sitio cambiado' WHERE option_name = 'blogname'" );
	$db->pdo->exec( "UPDATE wp_dev_options SET option_value = 'Dev después del respaldo' WHERE option_name = 'blogname'" );
	$db->pdo->exec( "INSERT INTO wp_dev_posts (ID, post_content) VALUES (2, 'escrita por el vecino después del respaldo')" );
	$result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => false, 'sync' => true, 'drive_cli' => true ) );
	nb_check( ! is_wp_error( $result ) && ! empty( $result['restored'] ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	nb_check( 'Sitio' === nb_option( $db, 'wp_options', 'blogname' ), 'Las tablas del sitio no volvieron al respaldo: ' . var_export( nb_option( $db, 'wp_options', 'blogname' ), true ) );
	nb_check( 'Dev después del respaldo' === nb_option( $db, 'wp_dev_options', 'blogname' ) && 'escrita por el vecino después del respaldo' === $db->value( 'wp_dev_posts', 'post_content', 'ID = 2' ), 'La restauración devolvió al respaldo las tablas de otra instalación (wp_dev_): ' . var_export( nb_option( $db, 'wp_dev_options', 'blogname' ), true ) );
	nb_check( false !== strpos( (string) ( $result['notice'] ?? '' ), 'wp_dev_*' ), 'El informe no dice que se dejaron las tablas de la otra instalación: ' . (string) ( $result['notice'] ?? '' ) );
	nb_check( ! preg_grep( '/dgbk[ro]_/', $db->names() ), 'Quedaron tablas temporales o viejas: ' . implode( ', ', preg_grep( '/dgbk[ro]_/', $db->names() ) ) );
} );

nb_case( 'c · el criterio: otra instalación por su options con siteurl; en una red, wp_<número>_ es un subsitio propio', function() use ( $db ) {
	nb_options_table( $db, 'wp_2_options', array( 'siteurl' => 'https://sitio.test/dos' ) );
	nb_options_table( $db, 'wp_cache_options', array( 'ttl' => '60' ) );
	$names = $db->names();
	$single = Digitalisimo_Backups::other_installation_prefixes( $names, 'wp_' );
	$GLOBALS['bk_multisite'] = true;
	try {
		$network = Digitalisimo_Backups::other_installation_prefixes( $names, 'wp_' );
	} finally {
		$GLOBALS['bk_multisite'] = false;
	}
	sort( $single );
	nb_check( array( 'wp_2_', 'wp_dev_' ) === $single, 'En un sitio individual, las otras instalaciones no son wp_2_ y wp_dev_: ' . json_encode( $single ) );
	nb_check( array( 'wp_dev_' ) === $network, 'En una red, wp_2_ (un subsitio) se tomó por otra instalación: ' . json_encode( $network ) );
	$db->pdo->exec( 'DROP TABLE `wp_2_options`' );
	$db->pdo->exec( 'DROP TABLE `wp_cache_options`' );
} );

bk_cleanup();
@unlink( $GLOBALS['bk_root'] . '-vecinos.sqlite' );
echo $failures ? "Otra instalación en la misma base de datos: {$failures} caso(s) con fallos.\n" : "DIGITALÍSIMO Backups: el respaldo y «Restaurar» nunca toman como propias ni tocan las tablas de otra instalación de WordPress de la misma base con un prefijo que empieza igual.\n";
exit( $failures ? 1 : 0 );
