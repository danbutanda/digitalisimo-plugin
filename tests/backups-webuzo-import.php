<?php
/**
 * Importar un WordPress de un respaldo de cuenta de Webuzo (`<usuario>_backup_full_remote_<fecha>.tar.gz` del panel): el
 * trabajo `import` (formato `webuzo`) lo analiza, elige el WordPress (el único, o el que dice la persona) y lo convierte
 * en un ZIP de Backups que entra en la lista como «Importado» y se restaura con la restauración por pasos, con una base de
 * datos de verdad (SQLite por tests/lib/backups-sqlite.php).
 *
 * Los respaldos son sintéticos con la forma exacta del panel (comprobada en respaldos reales, que nunca entran al repo):
 * tar GNU («ustar  ») en un solo miembro gzip, con dos tramos que empiezan cada uno por la carpeta `./`: primero
 * `backuply-data/` (info y domains en JSON con rutas absolutas, ssl/ con una llave, shadow, grant_priv.json y
 * `mysql/<base>.sql.gz` con un mysqldump de MariaDB 11: línea sandbox, CREATE DATABASE, USE, LOCK TABLES, DISABLE KEYS,
 * SET AUTOCOMMIT, COMMIT, vistas, disparadores y rutinas), después el home (public_html con varios WordPress, otro dominio,
 * .acme.sh, ssl, mail, .softaculous, un respaldo anidado, enlaces, nombres largos `././@LongLink`). Comprueba:
 *   W1  reconocer el formato (Webuzo, Backuply o ninguno) y la lista (home de la cuenta y almacén), sin rutas;
 *   W2  analizar una cuenta con varios WordPress: carpeta, dominio, versión, prefijo, base y si viene; elegir;
 *   W3  importar el elegido por tramos: sólo sus archivos, sin wp-config.php, sin otros sitios, respaldos anidados ni nada
 *       de la cuenta (backuply-data, ssl, .acme.sh, correo); el SQL sin sandbox, CREATE DATABASE ni USE y sólo su prefijo;
 *       fila «Importado» y verify «sin firma»;
 *   W4  una cuenta con un solo WordPress (prefijo `dig`) va sola y se restaura en otro dominio y otro prefijo;
 *   W5  base ausente: no se ofrece ni se importa un WordPress sin su base;
 *   W6  respaldos hostiles o que no son de Webuzo fallan sin dejar nada (rutas, enlaces duros, dispositivos, truncado);
 *   W7  una muerte a mitad de cada tramo (también a mitad de una entrada) no cambia el ZIP convertido;
 *   W8  el traductor con el dialecto de mysqldump: sandbox, CREATE DATABASE y USE de otra base, vistas, rutinas, tablas
 *       de otro prefijo, opciones home y siteurl;
 *   W9  que no se confundan: la importación de Backuply rechaza uno de Webuzo y al revés; subir uno de Webuzo lo lleva a
 *       su almacén aunque parezca de Backuply;
 *   W10 REST, WP-CLI, pantalla y panel de avance.
 * Deben fallar con el código anterior (1.0.81, 446a8770: no existe la importación de Webuzo).
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-rest.php';

if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
if ( ! function_exists( 'get_network' ) ) { function get_network() { return null; } }
if ( ! function_exists( 'is_subdomain_install' ) ) { function is_subdomain_install() { return false; } }
if ( ! function_exists( 'switch_to_blog' ) ) { function switch_to_blog( $id ) { return true; } }
if ( ! function_exists( 'restore_current_blog' ) ) { function restore_current_blog() { return true; } }
if ( ! function_exists( 'do_action' ) ) { function do_action( $hook ) { return null; } }
if ( ! function_exists( 'wp_max_upload_size' ) ) { function wp_max_upload_size() { return 204800; } }
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response { public $data; public $status = 200; public function __construct( $data = null ) { $this->data = $data; } public function set_status( $status ) { $this->status = (int) $status; } public function get_status() { return $this->status; } public function get_data() { return $this->data; } }
}
if ( ! function_exists( 'rest_ensure_response' ) ) { function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); } }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route( $namespace, $route, $args ) { $GLOBALS['wz_routes'][ $route ] = $args; return true; } }
$GLOBALS['bk_caps']['manage_options'] = true;
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
// El home de la cuenta: la carpeta de arriba de este WordPress de pruebas (sólo ella, no el home del usuario de la PC).
$GLOBALS['bk_filters']['digitalisimo_backups_webuzo_home_directories'] = function() { return array( dirname( rtrim( ABSPATH, '/' ) ) ); };

$failures = 0;
function wz_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function wz_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
wz_check( class_exists( 'Digitalisimo_Backups' ) && method_exists( 'Digitalisimo_Backups', 'service_webuzo_import' ), 'No existe la importación de Webuzo (service_webuzo_import).' );

/* ------------------------------------------------------------------ El formato del panel de Webuzo */

/** Cabecera GNU («ustar  ») de 512 bytes con su suma. */
function wz_raw_header( $name, $type, $size, $link = '', $mode = 0644 ) {
	$h = str_pad( substr( $name, 0, 100 ), 100, "\0" ) . sprintf( '%07o', '5' === $type ? 0755 : $mode ) . "\0" . sprintf( '%07o', 1000 ) . "\0" . sprintf( '%07o', 1000 ) . "\0" . sprintf( '%011o', $size ) . "\0" . sprintf( '%011o', 1763000000 ) . "\0" . '        ' . $type . str_pad( $link, 100, "\0" ) . "ustar  \0" . str_pad( 'cuenta', 32, "\0" ) . str_pad( 'cuenta', 32, "\0" ) . str_repeat( "\0", 183 );
	$sum = 0;
	for ( $i = 0; $i < 512; $i++ ) $sum += ord( $h[ $i ] );
	return substr_replace( $h, sprintf( '%06o', $sum ) . "\0 ", 148, 8 );
}
function wz_pad( $bytes ) { return $bytes . str_repeat( "\0", ( 512 - strlen( $bytes ) % 512 ) % 512 ); }
/** Una entrada: nombre largo con `././@LongLink` (L) como GNU tar, cabecera y datos. */
function wz_entry( $name, $type = '0', $data = '', $link = '' ) {
	$out = '';
	if ( strlen( $name ) > 99 ) $out .= wz_raw_header( '././@LongLink', 'L', strlen( $name ) + 1 ) . wz_pad( $name . "\0" );
	return $out . wz_raw_header( $name, $type, in_array( $type, array( '0', "\0" ), true ) ? strlen( $data ) : 0, $link ) . ( in_array( $type, array( '0', "\0" ), true ) ? wz_pad( $data ) : '' );
}
/** El tar entero: entradas y fin (dos bloques a cero, relleno hasta 10 KB como GNU tar). */
function wz_tar( array $entries ) {
	$tar = implode( '', $entries ) . str_repeat( "\0", 1024 );
	return $tar . str_repeat( "\0", ( 10240 - strlen( $tar ) % 10240 ) % 10240 );
}
/** Cadena como mysqldump (mysql_real_escape_string). */
function wz_str( $v ) { return null === $v ? 'NULL' : ( is_int( $v ) ? (string) $v : "'" . strtr( (string) $v, array( '\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z' ) ) . "'" ); }
/** Una tabla como la escribe mysqldump de MariaDB 11 (CREATE que SQLite entiende, con la forma del de MariaDB). */
function wz_table( $table, $columns, array $rows ) {
	$sql = "--\n-- Table structure for table `" . $table . "`\n--\n\nDROP TABLE IF EXISTS `" . $table . "`;\n/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!40101 SET character_set_client = utf8mb4 */;\nCREATE TABLE `" . $table . "` (\n" . $columns . "\n);\n/*!40101 SET character_set_client = @saved_cs_client */;\n\n";
	$sql .= "--\n-- Dumping data for table `" . $table . "`\n--\n\nLOCK TABLES `" . $table . "` WRITE;\n/*!40000 ALTER TABLE `" . $table . "` DISABLE KEYS */;\nSET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;\n";
	if ( $rows ) {
		$values = array();
		foreach ( $rows as $row ) $values[] = '(' . implode( ',', array_map( 'wz_str', $row ) ) . ')';
		$sql .= 'INSERT INTO `' . $table . '` VALUES ' . implode( ',', $values ) . ";\n";
	}
	return $sql . "/*!40000 ALTER TABLE `" . $table . "` ENABLE KEYS */;\nUNLOCK TABLES;\nCOMMIT;\nSET AUTOCOMMIT=@OLD_AUTOCOMMIT;\n\n";
}
/** Las tablas de un WordPress de prueba. */
function wz_wp_tables( $p, $url, $abspath = '/home/cuenta/public_html', $title = 'Sitio de O\'Reilly; con -- y /* y # dentro' ) {
	$widget = serialize( array( 2 => array( 'title' => 'Año ñandú €', 'text' => '<a href="' . $url . '/contacto/">Contacto</a>' ), '_multiwidget' => 1 ) );
	$t = wz_table( $p . 'options', "  `option_id` INTEGER PRIMARY KEY,\n  `option_name` varchar(191) NOT NULL UNIQUE,\n  `option_value` longtext NOT NULL,\n  `autoload` varchar(20) NOT NULL DEFAULT 'yes'", array(
		array( 1, 'siteurl', $url, 'yes' ), array( 2, 'home', $url, 'yes' ), array( 3, 'blogname', $title, 'yes' ), array( 4, 'widget_text', $widget, 'yes' ),
		array( 5, 'upload_path', $abspath . '/wp-content/uploads', 'yes' ), array( 6, $p . 'user_roles', serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ) ) ), 'yes' ),
		array( 7, 'digitalisimo_backups_vault_keyring', 'no-debe-viajar', 'no' ),
	) );
	$tricky = "Texto con 'comillas', \"dobles\", ; punto y coma, \\ barra, salto\nde línea, \r retorno, \x1a ctrl-Z y \0 nulo -- no es comentario /* tampoco */ # ni esto; <a href=\"" . $url . "/blog/\">blog</a>";
	$t .= wz_table( $p . 'posts', "  `ID` INTEGER PRIMARY KEY,\n  `post_title` text NOT NULL,\n  `post_content` longtext NOT NULL,\n  `guid` varchar(255) NOT NULL DEFAULT '',\n  `post_type` varchar(20) NOT NULL DEFAULT 'post'", array( array( 1, 'Inicio', $tricky, $url . '/?p=1', 'page' ), array( 2, 'Grande', str_repeat( 'Lorem ipsum ñ ', 4300 ), $url . '/?p=2', 'post' ) ) );
	$t .= wz_table( $p . 'users', "  `ID` INTEGER PRIMARY KEY,\n  `user_login` varchar(60) NOT NULL,\n  `user_email` varchar(100) NOT NULL,\n  `user_url` varchar(100) NOT NULL DEFAULT ''", array( array( 1, 'admin', 'admin@ejemplo.test', $url ) ) );
	$t .= wz_table( $p . 'usermeta', "  `umeta_id` INTEGER PRIMARY KEY,\n  `user_id` bigint(20) NOT NULL DEFAULT 0,\n  `meta_key` varchar(255),\n  `meta_value` longtext", array( array( 1, 1, $p . 'capabilities', serialize( array( 'administrator' => true ) ) ), array( 2, 1, $p . 'user_level', '10' ), array( 3, 1, '_nulo', null ) ) );
	return $t;
}
/** El volcado de una base como lo hace el panel (mysqldump de MariaDB 11 con --databases): vistas, disparadores y rutinas. */
function wz_dump( $db, array $wps, $extra = '', $abspath = '/home/cuenta/public_html' ) {
	$sql = "/*M!999999\\- enable the sandbox mode */ \n-- MariaDB dump 10.19-11.8.8-MariaDB, for linux-systemd (x86_64)\n--\n-- Host: localhost    Database: " . $db . "\n-- ------------------------------------------------------\n-- Server version\t11.8.8-MariaDB\n\n";
	$sql .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET NAMES utf8mb4 */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;\n\n";
	$sql .= "--\n-- Current Database: `" . $db . "`\n--\n\nCREATE DATABASE /*!32312 IF NOT EXISTS*/ `" . $db . "` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;\n\nUSE `" . $db . "`;\n\n";
	foreach ( $wps as $p => $url ) $sql .= wz_wp_tables( $p, $url, $abspath );
	$sql .= $extra;
	$first = (string) key( $wps );
	// La vista: su tabla temporal y, al final, la vista de verdad (varios ejecutables seguidos).
	$sql .= "--\n-- Temporary table structure for view `{$first}vista`\n--\n\nDROP TABLE IF EXISTS `{$first}vista`;\n/*!50001 DROP VIEW IF EXISTS `{$first}vista`*/;\nSET @saved_cs_client     = @@character_set_client;\nSET character_set_client = utf8mb4;\n/*!50001 CREATE VIEW `{$first}vista` AS SELECT\n 1 AS `ID` */;\nSET character_set_client = @saved_cs_client;\n\n";
	$sql .= "/*!50003 SET @saved_cs_client      = @@character_set_client */ ;\n/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;\n/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE' */ ;\nDELIMITER ;;\n/*!50003 CREATE*/ /*!50017 DEFINER=`cuenta_u`@`localhost`*/ /*!50003 TRIGGER `{$first}trig` BEFORE INSERT ON `{$first}posts` FOR EACH ROW SET NEW.post_title = 'x; DROP TABLE y' */;;\nDELIMITER ;\n/*!50003 SET sql_mode              = @saved_sql_mode */ ;\n\n";
	$sql .= "--\n-- Dumping routines for database '" . $db . "'\n--\n/*!50003 DROP PROCEDURE IF EXISTS `{$first}proc` */;\n/*!50003 SET @saved_cs_client      = @@character_set_client */ ;\nDELIMITER ;;\nCREATE DEFINER=`cuenta_u`@`localhost` PROCEDURE `{$first}proc`()\nBEGIN SELECT 1; DELETE FROM `{$first}posts`; END ;;\nDELIMITER ;\n/*!50003 ALTER DATABASE `" . $db . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ ;\n\n";
	$sql .= "--\n-- Final view structure for view `{$first}vista`\n--\n\n/*!50001 DROP VIEW IF EXISTS `{$first}vista`*/;\n/*!50001 SET @saved_cs_client          = @@character_set_client */;\n/*!50001 CREATE ALGORITHM=UNDEFINED */\n/*!50013 DEFINER=`cuenta_u`@`localhost` SQL SECURITY DEFINER */\n/*!50001 VIEW `{$first}vista` AS select `{$first}posts`.`ID` AS `ID` from `{$first}posts` */;\n/*!50001 SET character_set_client      = @saved_cs_client */;\n";
	return $sql . "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;\n\n-- Dump completed on 2026-11-18  4:05:26\n";
}
$GLOBALS['wz_foto'] = random_bytes( 70000 );
$GLOBALS['wz_long'] = 'wp-content/uploads/2026/11/' . str_repeat( 'nombre-muy-largo-', 7 ) . 'ñ.txt';
/** Los archivos de un WordPress en `$root`, con su wp-config.php (con secretos que nunca deben viajar). */
function wz_wp( $root, $prefix, $db, $version = '6.8.3', $config = true ) {
	$r = './' . $root . '/';
	$out = array( wz_entry( $r, '5' ) );
	$files = array( 'index.php' => "<?php // portada de $root\n", 'wp-load.php' => "<?php // wp-load\n", 'wp-settings.php' => "<?php // wp-settings\n", 'wp-admin/index.php' => "<?php // escritorio\n", 'wp-includes/version.php' => "<?php\n\$wp_version = '$version';\n", '.htaccess' => "# BEGIN WordPress\n", 'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n", 'wp-content/plugins/hola/hola.php' => "<?php /* Plugin Name: Hola */\n" );
	if ( $config ) $files['wp-config.php'] = "<?php\n// define( 'DB_NAME', 'comentada' );\ndefine( 'DB_NAME', '$db' );\ndefine( 'DB_USER', 'usuario_db' );\ndefine( 'DB_PASSWORD', 'clave-secreta-no-viaja' );\ndefine( 'AUTH_KEY', 'llave-no-viaja' );\n\$table_prefix = '$prefix';\nrequire_once ABSPATH . 'wp-settings.php';\n";
	foreach ( $files as $name => $data ) $out[] = wz_entry( $r . $name, '0', $data );
	return $out;
}
/**
 * Una cuenta de Webuzo: `$o['wps']` carpeta => array( prefijo, base, versión ), `$o['dumps']` base => SQL, `$o['domains']`
 * dominio => carpeta, `$o['extra']` entradas de más en el home y `$o['data']` en backuply-data.
 */
function wz_account( array $o ) {
	$o += array( 'user' => 'cuenta', 'wps' => array(), 'dumps' => array(), 'domains' => array( 'cuenta.com' => 'public_html' ), 'extra' => array(), 'data' => array(), 'first' => null );
	$home = '/home/' . $o['user'];
	$domains = array();
	foreach ( $o['domains'] as $domain => $path ) $domains[ $domain ] = array( 'path' => $home . '/' . $path, 'type' => 'public_html' === $path ? 'main' : 'addon', 'force_https' => 1 );
	$e = null === $o['first'] ? array( wz_entry( './', '5' ) ) : $o['first'];
	$e[] = wz_entry( './backuply-data/', '5' );
	$e[] = wz_entry( './backuply-data/ssl/', '5' );
	$e[] = wz_entry( './backuply-data/ssl/cuenta.com-combined.pem', '0', "-----BEGIN PRIVATE KEY-----\nLLAVE-PRIVADA-NO-VIAJA\n-----END PRIVATE KEY-----\n" );
	$e[] = wz_entry( './backuply-data/mysql/', '5' );
	foreach ( $o['dumps'] as $db => $sql ) $e[] = wz_entry( './backuply-data/mysql/' . $db . '.sql.gz', '0', gzencode( $sql, 6 ) );
	$e[] = wz_entry( './backuply-data/grant_priv.json', '0', json_encode( array( 'assigned_users' => array( array( 'db' => 'x', 'dbuser' => 'y', 'dbuser_hash_pass' => '*HASH-NO-VIAJA', 'host' => 'localhost', 'grant_priv' => array( 'ALL' ) ) ) ) ) );
	$e[] = wz_entry( './backuply-data/info', '0', str_replace( '/', '\\/', json_encode( array( 'domain' => 'cuenta.com', 'user' => $o['user'], 'email' => 'dueno@ejemplo.test', 'homedir' => $home, 'domains' => $domains ), JSON_UNESCAPED_SLASHES ) ) );
	$e[] = wz_entry( './backuply-data/shadow', '0', "cuenta:\$6\$SALT\$HASH-NO-VIAJA:20000::::::\n" );
	$e[] = wz_entry( './backuply-data/domains', '0', json_encode( $domains, JSON_UNESCAPED_SLASHES ) );
	foreach ( $o['data'] as $entry ) $e[] = $entry;
	// El segundo tramo: el home de la cuenta.
	$e[] = wz_entry( './', '5' );
	$e[] = wz_entry( './.acme.sh/', '5' );
	$e[] = wz_entry( './.acme.sh/cuenta.com/cuenta.com.key', '0', "ACME-NO-VIAJA\n" );
	$e[] = wz_entry( './ssl/keys/llave.key', '0', "SSL-NO-VIAJA\n" );
	$e[] = wz_entry( './mail/cuenta.com/dueno/cur/1:2,S', '0', "Correo que no viaja\n" );
	$e[] = wz_entry( './.softaculous/installations.php', '0', "<?php exit(); ?>a:0:{}\n" );
	$e[] = wz_entry( './error_log', '0', "error\n" );
	$e[] = wz_entry( './www', '2', '', '/home/' . $o['user'] . '/public_html' );
	foreach ( $o['wps'] as $root => $wp ) foreach ( wz_wp( $root, $wp[0], $wp[1], $wp[2] ?? '6.8.3', $wp[3] ?? true ) as $entry ) $e[] = $entry;
	foreach ( $o['extra'] as $entry ) $e[] = $entry;
	return gzencode( wz_tar( $e ), 6 );
}
/** La cuenta con varios WordPress: public_html (wp_), public_html/tienda (wp_tienda_, misma base) y otro.com (sin base). */
function wz_multi() {
	$extra = array(
		wz_entry( './public_html/wp-content/uploads/2026/11/foto.jpg', '0', $GLOBALS['wz_foto'] ),
		wz_entry( './public_html/' . $GLOBALS['wz_long'], '0', "nombre largo\n" ),
		wz_entry( './public_html/wp-content/cache/pagina.html', '0', "cacheada\n" ),
		wz_entry( './public_html/wp-content/debug.log', '0', "registro\n" ),
		wz_entry( './public_html/cuenta_backup_full_remote_2025-01-01_00-00-00.tar.gz', '0', "respaldo anidado\n" ),
		wz_entry( './public_html/blog/index.html', '0', "otro dominio dentro\n" ),
		wz_entry( './public_html/sub/wp-config.php', '0', "<?php define( 'DB_PASSWORD', 'otra-clave' );\n" ),
		wz_entry( './public_html/enlace', '2', '', '../ssl' ),
		wz_entry( './cuenta_backup_full_remote_2025-06-01_00-00-00.tar.gz', '0', "respaldo viejo en el home\n" ),
	);
	$wps = array( 'public_html' => array( 'wp_', 'cuenta_wp' ), 'public_html/tienda' => array( 'wp_tienda_', 'cuenta_wp', '6.7.1' ), 'otro.com' => array( 'dig', 'cuenta_otro', '6.8.1' ) );
	$app = wz_table( 'otra_app', "  `id` INTEGER PRIMARY KEY,\n  `v` text", array( array( 1, 'de otra aplicación' ) ) );
	return wz_account( array( 'wps' => $wps, 'dumps' => array( 'cuenta_wp' => wz_dump( 'cuenta_wp', array( 'wp_' => 'https://cuenta.com', 'wp_tienda_' => 'https://cuenta.com/tienda' ), $app ) ), 'domains' => array( 'cuenta.com' => 'public_html', 'blog.cuenta.com' => 'public_html/blog', 'otro.com' => 'otro.com' ), 'extra' => $extra ) );
}
/** La cuenta con un solo WordPress, prefijo `dig` (sin guion bajo, como hay en cuentas reales). */
function wz_single( $dump = true ) {
	return wz_account( array( 'user' => 'unico', 'wps' => array( 'public_html' => array( 'dig', 'unico_db' ) ), 'dumps' => $dump ? array( 'unico_db' => wz_dump( 'unico_db', array( 'dig' => 'https://unico.mx' ), '', '/home/unico/public_html' ) ) : array(), 'domains' => array( 'unico.mx' => 'public_html' ), 'extra' => array( wz_entry( './public_html/wp-content/uploads/2026/11/foto.jpg', '0', $GLOBALS['wz_foto'] ) ) ) );
}
function wz_home() { return dirname( rtrim( ABSPATH, '/' ) ); }
function wz_store() { Digitalisimo_Backups::load_webuzo_import(); return Digitalisimo_Backups_Webuzo_Import::store_directory( true ); }
function wz_put( $dir, $name, $bytes ) { file_put_contents( $dir . '/' . $name, $bytes ); touch( $dir . '/' . $name, time() - 3600 ); return $name; }
function wz_drive( $id, $one = false, $between = null ) {
	for ( $i = 0; $i < 20000; $i++ ) {
		Digitalisimo_Backups_Jobs::tick( $one ? 0.0 : PHP_INT_MAX );
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $job;
		if ( $between ) $between( $job );
	}
	throw new RuntimeException( 'El trabajo ' . $id . ' no terminó.' );
}
function wz_import( $name, array $args = array(), $one = false, $between = null ) {
	$queued = Digitalisimo_Backups::service_webuzo_import( $name, $args + array( 'via' => 'test' ) );
	wz_check( ! is_wp_error( $queued ), 'No se encoló la importación: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	wz_check( 'import' === $queued['kind'] && 'Importación de Webuzo' === $queued['origin_label'], 'La vista del trabajo no es la de una importación de Webuzo: ' . json_encode( $queued ) );
	return wz_drive( $queued['id'], $one, $between );
}
function wz_zip_map( $path ) {
	$zip = new ZipArchive();
	wz_check( true === $zip->open( $path ), 'No se abrió el ZIP convertido.' );
	$out = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( 'manifest.json' !== $name ) $out[ $name ] = hash( 'sha256', (string) $zip->getFromIndex( $i ) );
	}
	$zip->close();
	ksort( $out );
	return $out;
}
function wz_staging_left() {
	$root = Digitalisimo_Backups_Job_Store::root();
	$left = array();
	foreach ( (array) scandir( $root ) as $id ) if ( Digitalisimo_Backups_Job_Store::valid_id( $id ) && is_dir( $root . '/' . $id . '/staging' ) && array_diff( scandir( $root . '/' . $id . '/staging' ), array( '.', '..' ) ) ) $left[] = $id;
	return $left;
}
function wz_row( $file ) { foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) if ( ( $row['file'] ?? '' ) === $file ) return $row; return null; }
/** Lo que nunca debe salir de la cuenta: secretos de backuply-data, del home y del wp-config.php. */
const WZ_SECRETS = array( 'LLAVE-PRIVADA-NO-VIAJA', 'HASH-NO-VIAJA', 'ACME-NO-VIAJA', 'SSL-NO-VIAJA', 'Correo que no viaja', 'clave-secreta-no-viaja', 'llave-no-viaja', 'otra-clave', 'dueno@ejemplo.test', 'respaldo anidado', 'respaldo viejo', 'otro dominio dentro', 'cacheada', 'no-debe-viajar' );
function wz_no_secrets( $path ) {
	$zip = new ZipArchive();
	wz_check( true === $zip->open( $path ), 'No se abrió el ZIP convertido.' );
	try {
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			wz_check( 'manifest.json' === $name || 'database.sql' === $name || 0 === strpos( $name, 'wordpress/' ), 'El ZIP trae una entrada fuera de wordpress/: ' . $name );
			foreach ( array( 'backuply-data', '.acme.sh', 'ssl/', 'mail/', '.softaculous', 'shadow', 'grant_priv', 'error_log' ) as $bad ) wz_check( false === strpos( $name, $bad ), 'El ZIP trae ' . $name );
			$bytes = (string) $zip->getFromIndex( $i );
			foreach ( WZ_SECRETS as $secret ) wz_check( false === strpos( $bytes, $secret ), 'El ZIP trae «' . $secret . '» en ' . $name );
		}
	} finally {
		$zip->close();
	}
}

/* ------------------------------------------------------------------ Destino: un WordPress con prefijo wp_ en otro dominio */
$abspath = rtrim( ABSPATH, '/' );
$dest = new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-destino.sqlite', 'wp_' );
$dest->pdo->exec( "CREATE TABLE `wp_options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
$dest->pdo->exec( "CREATE TABLE `wp_posts` (`ID` INTEGER PRIMARY KEY, `post_title` text NOT NULL, `post_content` longtext NOT NULL, `guid` varchar(255) NOT NULL DEFAULT '', `post_type` varchar(20) NOT NULL DEFAULT 'post')" );
$dest->seed( 'wp_options', array( array( 'option_name' => 'siteurl', 'option_value' => 'https://destino.test/sitio' ), array( 'option_name' => 'home', 'option_value' => 'https://destino.test/sitio' ), array( 'option_name' => 'blogname', 'option_value' => 'Destino' ) ) );
$GLOBALS['wpdb'] = $dest;
$GLOBALS['bk_options']['home'] = 'https://destino.test/sitio';
$GLOBALS['bk_options']['siteurl'] = 'https://destino.test/sitio';
$GLOBALS['bk_multisite'] = false;
$config_before = file_get_contents( $abspath . '/wp-config.php' );
$multi = 'cuenta_backup_full_remote_2026-11-18_03-37-08.tar.gz';
$single = 'unico_backup_full_auto_remote_2026-11-17_04-05-26.tar.gz';

wz_case( 'W1 reconocer el formato y la lista (home de la cuenta y almacén), sin rutas', function() use ( $multi ) {
	Digitalisimo_Backups::load_webuzo_import();
	$none = Digitalisimo_Backups::service_webuzo();
	wz_check( ! is_wp_error( $none ) && array() === $none['backups'], 'Sin respaldos, la lista no está vacía: ' . json_encode( $none ) );
	wz_put( wz_home(), $multi, wz_multi() );
	file_put_contents( wz_home() . '/' . $multi . '.json', json_encode( array( 'user' => 'cuenta', 'backup_contains' => 'panel_conf,home,databases,mail', 'tech' => 'tar' ) ) );
	wz_check( 'webuzo' === Digitalisimo_Backups_Webuzo_Import::sniff( wz_home() . '/' . $multi ), 'No se reconoce un respaldo de cuenta de Webuzo.' );
	$backuply = wz_home() . '/backuply-falso.tar.gz';
	file_put_contents( $backuply, gzencode( wz_tar( array( wz_entry( 'softsql.sql', '0', "-- Softaculous SQL Dump\n" ), wz_entry( 'wp-load.php', '0', "<?php\n" ) ) ) ) );
	wz_check( 'backuply' === Digitalisimo_Backups_Webuzo_Import::sniff( $backuply ), 'Un respaldo de Backuply no se reconoce como tal.' );
	file_put_contents( $backuply, 'no es gzip' );
	wz_check( '' === Digitalisimo_Backups_Webuzo_Import::sniff( $backuply ), 'Un archivo cualquiera se reconoce como un respaldo.' );
	unlink( $backuply );
	$list = Digitalisimo_Backups::service_webuzo();
	wz_check( 1 === count( $list['backups'] ), 'La lista no tiene el respaldo del home: ' . json_encode( $list['backups'] ) );
	$item = $list['backups'][0];
	wz_check( $multi === $item['name'] && 'home' === $item['source'] && 'cuenta' === $item['account'] && '2026-11-18 03:37' === $item['date'] && array( 'panel_conf', 'home', 'databases', 'mail' ) === $item['contains'] && false === $item['analyzed'] && true === $item['importable'], 'La lista no describe el respaldo: ' . json_encode( $item ) );
	wz_check( false === strpos( json_encode( $list ), $GLOBALS['bk_root'] ) && false === strpos( json_encode( $list ), wz_home() ), 'La lista revela rutas del servidor.' );
	foreach ( array( '../' . $multi, 'cuenta_backup_x.tar', "cuenta\0_backup_x.tar.gz", 'a/b_backup_c.tar.gz', '.oculto_backup_x.tar.gz' ) as $bad ) wz_check( is_wp_error( Digitalisimo_Backups::service_webuzo_import( $bad ) ), 'Se aceptó el nombre ' . json_encode( $bad ) );
	foreach ( array( '../x', '/abs', 'public_html/../x', "a\0b" ) as $bad ) wz_check( is_wp_error( Digitalisimo_Backups::service_webuzo_import( $multi, array( 'site' => $bad ) ) ), 'Se aceptó la carpeta ' . json_encode( $bad ) );
	$missing = Digitalisimo_Backups::service_webuzo_import( 'otra_backup_full_remote_2026-01-01_00-00-00.tar.gz' );
	wz_check( is_wp_error( $missing ) && 404 === $missing->get_error_data()['status'], 'Un respaldo que no existe no responde 404.' );
	// Un archivo del home que no es de la cuenta (sin «_backup_») no se lista ni se importa.
	file_put_contents( wz_home() . '/cualquiera.tar.gz', wz_multi() );
	wz_check( 1 === count( Digitalisimo_Backups::service_webuzo()['backups'] ) && is_wp_error( Digitalisimo_Backups::service_webuzo_import( 'cualquiera.tar.gz', array( 'source' => 'home' ) ) ), 'Se lista o importa un .tar.gz del home que no es un respaldo de la cuenta.' );
	unlink( wz_home() . '/cualquiera.tar.gz' );
	$GLOBALS['bk_caps'] = array();
	wz_check( is_wp_error( Digitalisimo_Backups::service_webuzo() ) && is_wp_error( Digitalisimo_Backups::service_webuzo_import( $multi ) ), 'Sin capability se listó o importó.' );
	$GLOBALS['bk_caps']['manage_options'] = true;
} );

wz_case( 'W2 una cuenta con varios WordPress se analiza y se elige cuál', function() use ( $multi ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	try {
		$job = wz_import( $multi, array(), true );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	}
	wz_check( 'success' === $job['status'] && ! empty( $job['result']['webuzo']['choose'] ), 'El análisis no terminó pidiendo elegir: ' . json_encode( array( $job['status'], $job['error'], $job['result'] ) ) );
	$steps = 0;
	foreach ( Digitalisimo_Backups_Job_Store::events( $job['id'] ) as $event ) if ( 'phase' === ( $event['type'] ?? '' ) && 'unpack' === ( $event['phase'] ?? '' ) && 'unpack' === ( $event['next'] ?? '' ) ) $steps++;
	wz_check( $steps >= 3, 'El análisis no avanzó por tramos: ' . $steps );
	$sites = array();
	foreach ( $job['result']['webuzo']['sites'] as $site ) $sites[ $site['root'] ] = $site;
	wz_check( array( 'otro.com', 'public_html', 'public_html/tienda' ) === array_keys( $sites ), 'Los WordPress del respaldo: ' . json_encode( array_keys( $sites ) ) );
	$main = $sites['public_html'];
	wz_check( 'cuenta.com' === $main['domain'] && '6.8.3' === $main['version'] && 'wp_' === $main['prefix'] && 'cuenta_wp' === $main['db'] && true === $main['database'] && $main['database_size'] > 0 && true === $main['importable'] && $main['size'] > 70000, 'public_html no se describe bien: ' . json_encode( $main ) );
	wz_check( 'cuenta.com/tienda' === $sites['public_html/tienda']['domain'] && 'wp_tienda_' === $sites['public_html/tienda']['prefix'] && '6.7.1' === $sites['public_html/tienda']['version'] && true === $sites['public_html/tienda']['importable'], 'La tienda no se describe bien: ' . json_encode( $sites['public_html/tienda'] ) );
	wz_check( 'otro.com' === $sites['otro.com']['domain'] && 'dig' === $sites['otro.com']['prefix'] && false === $sites['otro.com']['database'] && false === $sites['otro.com']['importable'] && false !== strpos( $sites['otro.com']['problem'], 'no viene en el respaldo' ), 'Un WordPress sin su base se ofrece para importar: ' . json_encode( $sites['otro.com'] ) );
	$view = Digitalisimo_Backups_Jobs::view( $job );
	wz_check( true === $view['choose'] && '' === $view['file'], 'La vista del análisis no lleva a elegir: ' . json_encode( $view ) );
	wz_check( array() === wz_staging_left(), 'El análisis dejó su área de trabajo.' );
	// El análisis queda para la pantalla: la lista ya dice qué trae.
	$item = Digitalisimo_Backups::service_webuzo()['backups'][0];
	wz_check( true === $item['analyzed'] && 3 === count( $item['sites'] ), 'La lista no muestra el análisis: ' . json_encode( $item ) );
	wz_check( false === strpos( json_encode( $item ), 'clave-secreta' ) && false === strpos( json_encode( $job ), 'clave-secreta' ), 'El análisis guarda secretos del wp-config.php.' );
	// Elegir uno que no se puede importar se rechaza con el porqué.
	$job = wz_import( $multi, array( 'site' => 'otro.com' ) );
	wz_check( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'no viene en el respaldo' ), 'Se importó un WordPress sin su base: ' . json_encode( $job['error'] ) );
	$job = wz_import( $multi, array( 'site' => 'public_html/no-existe' ) );
	wz_check( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'no trae un WordPress completo' ), 'Se importó una carpeta que no es un WordPress: ' . json_encode( $job['error'] ) );
} );

$converted = '';
wz_case( 'W3 el WordPress elegido se importa por tramos: sólo sus archivos, sin secretos, y su base con su prefijo', function() use ( $multi, &$converted ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	try {
		$job = wz_import( $multi, array( 'site' => 'public_html' ), true );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	}
	wz_check( 'success' === $job['status'], 'La importación falló: ' . json_encode( $job['error'] ) );
	$phases = array();
	foreach ( Digitalisimo_Backups_Job_Store::events( $job['id'] ) as $event ) if ( 'phase' === ( $event['type'] ?? '' ) && ( $event['phase'] ?? '' ) === ( $event['next'] ?? '' ) ) $phases[ $event['phase'] ] = ( $phases[ $event['phase'] ] ?? 0 ) + 1;
	wz_check( ( $phases['unpack'] ?? 0 ) >= 3 && ( $phases['database'] ?? 0 ) >= 5 && ( $phases['files'] ?? 0 ) >= 5, 'Las fases no avanzaron por tramos: ' . json_encode( $phases ) );
	$converted = $job['result']['import']['file'];
	wz_check( 'digitalisimo-webuzo-cuenta_backup_full_remote_2026-11-18_03-37-08-public_html.zip' === $converted && is_file( bk_storage() . '/' . $converted ) && $converted === sanitize_file_name( $converted ), 'El ZIP convertido no está en el almacén con su nombre: ' . $converted );
	wz_check( 'RESTAURAR SIN FIRMA' === $job['result']['import']['confirmation'] && 'wp_' === $job['result']['import']['prefix'] && 4 === $job['result']['import']['tables'] && true === $job['result']['import']['listed'] && 'public_html' === $job['result']['import']['site'], 'El resultado no describe la importación: ' . json_encode( $job['result'] ) );
	wz_check( array() === wz_staging_left(), 'Quedó el área de trabajo de la importación.' );
	wz_no_secrets( bk_storage() . '/' . $converted );
	$zip = new ZipArchive();
	wz_check( true === $zip->open( bk_storage() . '/' . $converted ), 'No se abre el ZIP convertido.' );
	wz_check( '' === (string) $zip->getArchiveComment(), 'El ZIP convertido lleva firma.' );
	Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
	$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
	wz_check( 'digitalisimo-backup/v3' === $manifest['format'] && 'wp_' === $manifest['table_prefix'] && 'https://cuenta.com' === $manifest['home_url'] && '/home/cuenta/public_html' === $manifest['origin']['abspath'] && '/home/cuenta/public_html/wp-content/uploads' === $manifest['origin']['uploads_dir'] && 'webuzo' === $manifest['imported_from']['format'] && 'cuenta' === $manifest['imported_from']['account'] && 'public_html' === $manifest['imported_from']['site'] && '6.8.3' === $manifest['wordpress_version'], 'Manifiesto inesperado: ' . json_encode( $manifest ) );
	wz_check( array( 'wp_options', 'wp_posts', 'wp_users', 'wp_usermeta' ) === $manifest['database_tables'], 'Tablas del manifiesto: ' . json_encode( $manifest['database_tables'] ) );
	$raw = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$notices = implode( ' ', (array) ( $raw['notices'] ?? array() ) );
	foreach ( array( '1 vista', '2 disparador', '5 tabla(s) de la base que no son del prefijo wp_', 'wp-config.php', 'otros sitios', '1 respaldo(s) anidado', 'enlace(s) simbólico', 'correo' ) as $needle ) wz_check( false !== strpos( $notices, $needle ), 'Falta el aviso «' . $needle . '»: ' . $notices );
	foreach ( array( 'index.php', '.htaccess', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/themes/tema/style.css', 'wp-content/plugins/hola/hola.php', 'wp-content/uploads/2026/11/foto.jpg', $GLOBALS['wz_long'] ) as $present ) wz_check( false !== $zip->locateName( 'wordpress/' . $present ), 'Falta wordpress/' . $present . ' en el ZIP.' );
	wz_check( $GLOBALS['wz_foto'] === $zip->getFromName( 'wordpress/wp-content/uploads/2026/11/foto.jpg' ), 'El binario no llegó idéntico.' );
	wz_check( false !== strpos( (string) $zip->getFromName( 'wordpress/wp-config.php' ), 'Marcador del ZIP convertido' ), 'wp-config.php no es el marcador.' );
	foreach ( array( 'wordpress/tienda/index.php', 'wordpress/tienda/wp-config.php', 'wordpress/blog/index.html', 'wordpress/sub/wp-config.php', 'wordpress/enlace', 'wordpress/wp-content/cache/pagina.html', 'wordpress/wp-content/debug.log', 'wordpress/cuenta_backup_full_remote_2025-01-01_00-00-00.tar.gz', 'wordpress/public_html/index.php' ) as $absent ) wz_check( false === $zip->locateName( $absent ), 'El ZIP trae ' . $absent );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$zip->close();
	wz_check( 0 === strpos( $sql, "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n" ), 'El SQL no tiene la forma del exportador.' );
	foreach ( array( 'sandbox', 'CREATE DATABASE', 'USE `', 'cuenta_wp', 'wp_tienda_', 'otra_app', 'VIEW', 'TRIGGER', 'PROCEDURE', 'DELIMITER', 'LOCK TABLES', 'DISABLE KEYS', 'AUTOCOMMIT', 'digitalisimo_backups_vault_keyring', 'NOTE_VERBOSITY' ) as $absent ) wz_check( false === strpos( $sql, $absent ), 'El SQL convertido trae ' . $absent );
	wz_check( false !== strpos( $sql, "INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('3','blogname','Sitio de O\\'Reilly; con -- y /* y # dentro','yes');" ), 'La cadena de mysqldump no pasó a la del exportador.' );
	wz_check( false !== strpos( $sql, "\\\\ barra, salto\\nde línea, \\r retorno, \\Z ctrl-Z y \\0 nulo" ) && false !== strpos( $sql, "('3','1','_nulo',NULL)" ), 'Barras, saltos, ^Z, NUL o NULL no pasaron bien.' );
	$row = wz_row( $converted );
	wz_check( is_array( $row ) && 'imported' === $row['origin'] && 'webuzo' === $row['import']['format'] && false !== strpos( $row['import']['source'], 'public_html' ) && 'installation' === $row['scope'] && hash_equals( (string) $row['sha256'], hash_file( 'sha256', bk_storage() . '/' . $converted ) ), 'La fila de la lista no es la de un importado: ' . json_encode( $row ) );
	wz_check( Digitalisimo_Backups::is_uploaded_row( $row ) && 'Importado' === bk_call( 'Digitalisimo_Backups', 'uploaded_summary', $row )['label'], 'La fila «Importado» no se trata como una de fuera.' );
	$verify = Digitalisimo_Backups::service_verify( $converted );
	wz_check( ! is_wp_error( $verify ) && true === $verify['verified'] && 'untrusted' === $verify['trust'] && 'RESTAURAR SIN FIRMA' === $verify['confirmation'] && true === $verify['compatible'], 'verify no da el ZIP por «sin firma» y restaurable: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $verify ) );
	wz_check( array( $converted ) === Digitalisimo_Backups::service_webuzo()['backups'][0]['imported'], 'La lista de Webuzo no dice que ya se importó.' );
	// El otro WordPress de la misma base sale con sus tablas, no las del principal.
	$job = wz_import( $multi, array( 'site' => 'public_html/tienda' ) );
	wz_check( 'success' === $job['status'] && 'wp_tienda_' === $job['result']['import']['prefix'] && 4 === $job['result']['import']['tables'], 'La tienda no se importó con su prefijo: ' . json_encode( $job['error'] ?? $job['result'] ) );
	wz_no_secrets( bk_storage() . '/' . $job['result']['import']['file'] );
} );

wz_case( 'W4 una cuenta con un solo WordPress va sola y se restaura en otro dominio y otro prefijo', function() use ( $single, $dest, $abspath, $config_before ) {
	wz_put( wz_store(), $single, wz_single() );
	$job = wz_import( $single, array( 'source' => 'store' ) );
	wz_check( 'success' === $job['status'] && empty( $job['result']['webuzo']['choose'] ) && 'dig' === $job['result']['import']['prefix'] && 'https://unico.mx' === $job['result']['import']['site_url'], 'Un solo WordPress no se importó solo: ' . json_encode( array( $job['error'], $job['result'] ) ) );
	$file = $job['result']['import']['file'];
	wz_no_secrets( bk_storage() . '/' . $file );
	wz_check( is_wp_error( Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR' ) ) ), 'Se restauró un importado sin firma con RESTAURAR.' );
	$GLOBALS['bk_options']['active_plugins'] = array( Digitalisimo_Backups_Restore_Maintenance::plugin() );
	$result = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	wz_check( ! is_wp_error( $result ), 'La restauración falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	$opt = function( $option ) use ( $dest ) { return $dest->value( 'wp_options', 'option_value', "option_name = '" . $option . "'" ); };
	wz_check( 'https://destino.test/sitio' === $opt( 'home' ) && 'https://destino.test/sitio' === $opt( 'siteurl' ), 'home/siteurl: ' . $opt( 'home' ) );
	wz_check( "Sitio de O'Reilly; con -- y /* y # dentro" === $opt( 'blogname' ) && 'no-debe-viajar' !== $opt( 'digitalisimo_backups_vault_keyring' ), 'blogname o las claves de Backups del origen.' );
	wz_check( null !== $opt( 'wp_user_roles' ) && null === $opt( 'diguser_roles' ), 'Las claves con el prefijo dig no pasaron a wp_.' );
	$widget = unserialize( $opt( 'widget_text' ), array( 'allowed_classes' => false ) );
	wz_check( is_array( $widget ) && 'Año ñandú €' === $widget[2]['title'] && false !== strpos( $widget[2]['text'], 'https://destino.test/sitio/contacto/' ), 'El serializado no se reubicó: ' . $opt( 'widget_text' ) );
	wz_check( $abspath . '/wp-content/uploads' === $opt( 'upload_path' ), 'La ruta del home de la cuenta no se reubicó: ' . $opt( 'upload_path' ) );
	wz_check( str_repeat( 'Lorem ipsum ñ ', 4300 ) === $dest->value( 'wp_posts', 'post_content', 'ID = 2' ), 'El contenido grande no llegó.' );
	wz_check( ! in_array( 'digoptions', $dest->names(), true ) && ! in_array( 'wp_vista', $dest->names(), true ), 'Quedaron tablas del origen o la vista: ' . json_encode( $dest->names() ) );
	$capabilities = $dest->value( 'wp_usermeta', 'meta_key', "meta_key = 'wp_capabilities'" );
	wz_check( 'wp_capabilities' === $capabilities, 'El administrador no conserva su rol con el prefijo nuevo.' );
	wz_check( $GLOBALS['wz_foto'] === file_get_contents( $abspath . '/wp-content/uploads/2026/11/foto.jpg' ) && is_file( $abspath . '/wp-content/themes/tema/style.css' ), 'Los archivos del WordPress no se restauraron.' );
	wz_check( $config_before === file_get_contents( $abspath . '/wp-config.php' ), 'La restauración escribió wp-config.php.' );
	foreach ( array( 'backuply-data', '.acme.sh', 'ssl', 'mail', 'public_html' ) as $absent ) wz_check( ! file_exists( $abspath . '/' . $absent ), 'La restauración dejó ' . $absent );
	wz_check( is_array( $result['relocation'] ) && 0 === $result['relocation']['check']['urls'] && 0 === $result['relocation']['check']['paths'], 'Quedaron URL o rutas del origen: ' . json_encode( $result['relocation']['check'] ?? null ) );
} );

wz_case( 'W5 base ausente: un WordPress sin su base no se importa', function() {
	$name = wz_put( wz_store(), 'sinbase_backup_full_auto_remote_2026-02-01_04-31-33.tar.gz', wz_account( array( 'user' => 'sinbase', 'wps' => array( 'public_html' => array( 'wp_', 'sinbase_wp' ) ) ) ) );
	$job = wz_import( $name );
	wz_check( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'no viene en el respaldo' ) && array() === wz_staging_left(), 'Se importó un WordPress sin base o quedó su área: ' . json_encode( $job['error'] ) );
	$item = null;
	foreach ( Digitalisimo_Backups::service_webuzo()['backups'] as $candidate ) if ( $name === $candidate['name'] ) $item = $candidate;
	wz_check( $item && 1 === count( $item['sites'] ) && false === $item['sites'][0]['importable'], 'El análisis no dice que su base no viene: ' . json_encode( $item ) );
	ob_start();
	bk_call( 'Digitalisimo_Backups', 'webuzo_panel', false, array( $item ) );
	$html = (string) ob_get_clean();
	wz_check( false !== strpos( $html, 'No viene en el respaldo' ) && false === strpos( $html, 'Importar este WordPress' ), 'La tarjeta ofrece importar un WordPress sin base.' );
	// Sin home (sólo los datos de la cuenta): nada que importar.
	$tar = wz_tar( array( wz_entry( './', '5' ), wz_entry( './backuply-data/', '5' ), wz_entry( './backuply-data/info', '0', '{"user":"x","homedir":"\/home\/x"}' ) ) );
	$name = wz_put( wz_store(), 'solodatos_backup_full_remote_2026-02-01_00-00-00.tar.gz', gzencode( $tar ) );
	$job = wz_import( $name );
	wz_check( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'no trae el home' ), 'Un respaldo sin home no se explica: ' . json_encode( $job['error'] ) );
} );

wz_case( 'W6 respaldos hostiles o que no son de Webuzo fallan sin dejar nada', function() {
	$base = array( 'user' => 'mal', 'wps' => array( 'public_html' => array( 'wp_', 'mal_wp' ) ), 'dumps' => array( 'mal_wp' => wz_dump( 'mal_wp', array( 'wp_' => 'https://mal.test' ) ) ), 'domains' => array( 'mal.test' => 'public_html' ) );
	$cases = array(
		'ruta no permitida' => wz_account( $base + array( 'extra' => array( wz_entry( './public_html/../../etc/passwd', '0', 'x' ) ) ) ),
		'ruta no permitida ' => wz_account( $base + array( 'extra' => array( wz_entry( '/etc/cron.d/x', '0', 'x' ) ) ) ),
		'ruta no permitida  ' => wz_account( $base + array( 'extra' => array( wz_entry( './public_html/a\\b.php', '0', 'x' ) ) ) ),
		'enlace duro' => wz_account( $base + array( 'extra' => array( wz_entry( './public_html/duro.php', '1', '', 'public_html/index.php' ) ) ) ),
		'dispositivo' => wz_account( $base + array( 'extra' => array( wz_entry( './tmp/tuberia', '6' ) ) ) ),
		'primer tramo' => wz_account( $base + array( 'first' => array( wz_entry( './', '5' ), wz_entry( './public_html/colado.php', '0', 'x' ) ) ) ),
		'no empieza por la carpeta' => gzencode( wz_tar( array( wz_entry( 'backuply-data/info', '0', '{}' ) ) ) ),
		'sólo sus datos' => gzencode( wz_tar( array( wz_entry( './', '5' ), wz_entry( './backuply-data/', '5' ) ) ) ),
	);
	$truncated = wz_account( $base );
	$tar = gzdecode( $truncated );
	$cases['incompleto'] = gzencode( substr( $tar, 0, (int) ( strlen( $tar ) / 2 ) ) );
	$cases['dañado'] = substr( $truncated, 0, (int) ( strlen( $truncated ) * 0.7 ) );
	$i = 0;
	foreach ( $cases as $needle => $bytes ) {
		$name = wz_put( wz_store(), 'hostil' . ( ++$i ) . '_backup_full_remote_2026-03-01_00-00-00.tar.gz', $bytes );
		$job = wz_import( $name, array( 'site' => 'public_html' ) );
		wz_check( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], trim( $needle ) ) && array() === wz_staging_left(), 'El caso «' . trim( $needle ) . '» no falló como debía: ' . json_encode( $job['error'] ) );
		unlink( wz_store() . '/' . $name );
	}
	// Una bomba: el tar se descomprime a mucho más que su tamaño.
	$GLOBALS['bk_filters']['digitalisimo_backups_import_max_ratio'] = 1;
	$GLOBALS['bk_filters']['digitalisimo_backups_import_max_size'] = 200000;
	try {
		$name = wz_put( wz_store(), 'bomba_backup_full_remote_2026-03-01_00-00-00.tar.gz', wz_account( $base + array( 'extra' => array( wz_entry( './public_html/ceros.bin', '0', str_repeat( "\0", 400000 ) ) ) ) ) );
		$job = wz_import( $name );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_import_max_ratio'], $GLOBALS['bk_filters']['digitalisimo_backups_import_max_size'] );
	}
	$why = (string) $job['error']['message'];
	wz_check( 'failed' === $job['status'] && ( false !== strpos( $why, 'supera' ) || false !== strpos( $why, 'tamaño no válido' ) ) && array() === wz_staging_left(), 'La bomba no se detuvo: ' . json_encode( $job['error'] ) );
} );

wz_case( 'W7 una muerte a mitad de cada tramo no cambia el ZIP convertido', function() use ( $multi, &$converted ) {
	wz_check( '' !== $converted, 'No hay ZIP convertido (falló W3).' );
	$reference = wz_zip_map( bk_storage() . '/' . $converted );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 2;
	$partials = array();
	$mid = 0;
	try {
		$job = wz_import( $multi, array( 'site' => 'public_html' ), true, function( $job ) use ( &$partials, &$mid ) {
			$partial = $job['checkpoint']['files']['partial'] ?? null;
			if ( is_array( $partial ) ) $partials[ (string) $partial['src'] ] = true;
			$stream = $job['checkpoint']['unpack']['stream'] ?? null;
			if ( is_array( $stream ) && 'd' === ( $stream['mode'] ?? '' ) && 'ext' === ( $stream['act'] ?? '' ) ) $mid++;
			$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
			foreach ( array( 'files.bin', 'index.jsonl', 'database.sql.gz', 'source.sql', 'database.sql', 'converted.zip', 'entries.jsonl' ) as $file ) if ( is_file( $staging . '/' . $file ) ) file_put_contents( $staging . '/' . $file, 'basura de un proceso muerto', FILE_APPEND );
		} );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	}
	wz_check( 'success' === $job['status'], 'La importación con muertes falló: ' . json_encode( $job['error'] ) );
	wz_check( $mid > 0, 'Ningún tramo de la extracción terminó a mitad de un archivo.' );
	$again = wz_zip_map( bk_storage() . '/' . $job['result']['import']['file'] );
	wz_check( array_keys( $reference ) === array_keys( $again ), 'El ZIP con muertes no tiene las mismas entradas.' );
	foreach ( $reference as $name => $hash ) wz_check( $hash === $again[ $name ], 'La entrada ' . $name . ' cambió con las muertes.' );
} );

wz_case( 'W8 el traductor con el dialecto de mysqldump: base, prefijo, vistas, rutinas y opciones', function() {
	$run = function( $sql, array $opts ) {
		$in = fopen( 'php://memory', 'w+b' );
		fwrite( $in, $sql );
		$out = fopen( 'php://memory', 'w+b' );
		$t = new Digitalisimo_Backups_Backuply_Sql( $in, 0, strlen( $sql ), $out, array(), $opts + array( 'label' => 'Webuzo', 'dialect' => 'mysqldump' ) );
		$t->run( INF );
		rewind( $out );
		return array( stream_get_contents( $out ), $t->state() );
	};
	$opts = array( 'database' => 'cuenta_wp', 'prefix' => 'wp_', 'exclude' => array( 'wp_tienda_' ), 'capture' => array( 'home', 'siteurl' ) );
	list( $sql, $state ) = $run( wz_dump( 'cuenta_wp', array( 'wp_' => 'https://cuenta.com', 'wp_tienda_' => 'https://cuenta.com/tienda' ) ), $opts );
	wz_check( array( 'wp_options', 'wp_posts', 'wp_users', 'wp_usermeta' ) === $state['order'] && 4 === $state['notes']['foreign'] && 1 === $state['notes']['views'] && 2 === $state['notes']['routines'], 'Tablas o avisos: ' . json_encode( array( $state['order'], $state['notes'] ) ) );
	wz_check( array( 'siteurl' => 'https://cuenta.com', 'home' => 'https://cuenta.com' ) === $state['captured'], 'home y siteurl no se anotaron: ' . json_encode( $state['captured'] ) );
	wz_check( false === strpos( $sql, 'sandbox' ) && false === strpos( $sql, 'wp_tienda_' ) && false === strpos( $sql, 'cuenta_wp' ), 'El SQL trae la línea sandbox, otra base u otro prefijo.' );
	foreach ( array(
		'otra base de datos' => "CREATE DATABASE /*!32312 IF NOT EXISTS*/ `otra` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;\n",
		'otra base de datos ' => "USE `otra`;\n",
		'USE no reconocido' => "USE `cuenta_wp` extra;\n",
		'comentario ejecutable' => "/*!50003 DROP DATABASE `cuenta_wp` */;\n",
		'sin otra base' => "CREATE TABLE `cuenta_wp`.`wp_x` (`a` int);\n",
		'tipo de sentencia' => "GRANT ALL ON *.* TO 'x'@'%';\n",
	) as $needle => $bad ) {
		try {
			$run( wz_dump( 'cuenta_wp', array( 'wp_' => 'https://cuenta.com' ), $bad ), $opts );
			$ok = false;
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			$ok = false !== strpos( $e->getMessage(), trim( $needle ) );
		}
		wz_check( $ok, 'Se aceptó: ' . trim( $bad ) );
	}
	// Una tabla con la sintaxis completa de MariaDB pasa por el guardián tal cual (sin su AUTO_INCREMENT).
	$maria = "CREATE TABLE `wp_maria` (\n  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos`)),\n  `cuando` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `cuando` (`cuando`)\n) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;\n";
	list( $sql ) = $run( wz_dump( 'cuenta_wp', array( 'wp_' => 'https://cuenta.com' ), $maria ), $opts );
	wz_check( false !== strpos( $sql, 'CREATE TABLE `wp_maria`' ) && false === strpos( $sql, 'AUTO_INCREMENT=42' ), 'La tabla de MariaDB no pasó.' );
	// Sin las opciones (Backuply, UpdraftPlus) nada cambia: CREATE DATABASE se sigue rechazando.
	try {
		$run( "CREATE DATABASE `x`;\n", array( 'dialect' => '' ) );
		$ok = false;
	} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
		$ok = true;
	}
	wz_check( $ok, 'Sin las opciones de Webuzo se aceptó CREATE DATABASE.' );
} );

wz_case( 'W9 que no se confundan: Backuply y Webuzo, y la subida reconoce el de Webuzo', function() use ( $multi ) {
	// Un respaldo de Webuzo en el almacén de Backuply: su importador lo rechaza y dice adónde va.
	$backuply_store = Digitalisimo_Backups_Backuply_Import::store_directory( true );
	copy( wz_home() . '/' . $multi, $backuply_store . '/' . $multi );
	$refused = Digitalisimo_Backups::service_backuply_import( $multi, array( 'source' => 'store' ) );
	wz_check( is_wp_error( $refused ) && 422 === $refused->get_error_data()['status'] && false !== strpos( $refused->get_error_message(), 'Webuzo' ), 'La importación de Backuply aceptó uno de Webuzo: ' . json_encode( is_wp_error( $refused ) ? $refused->get_error_message() : $refused ) );
	// Aunque llegara al trabajo (puesto a mano), su `scan` lo reconoce.
	$spec = Digitalisimo_Backups_Backuply_Import::spec( 'store', $multi );
	$queued = Digitalisimo_Backups_Jobs::enqueue_import( $spec );
	$job = wz_drive( $queued['job']['id'] );
	wz_check( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'respaldo de cuenta de Webuzo' ), 'El trabajo de Backuply no reconoce uno de Webuzo: ' . json_encode( $job['error'] ) );
	unlink( $backuply_store . '/' . $multi );
	// Y al revés: uno de Backuply en el almacén de Webuzo.
	$name = wz_put( wz_store(), 'backuply_backup_full_remote_2026-01-01_00-00-00.tar.gz', gzencode( wz_tar( array( wz_entry( 'softsql.sql', '0', "-- SQL\n" ), wz_entry( 'wp-load.php', '0', "<?php\n" ) ) ) ) );
	$refused = Digitalisimo_Backups::service_webuzo_import( $name );
	wz_check( is_wp_error( $refused ) && 422 === $refused->get_error_data()['status'] && false !== strpos( $refused->get_error_message(), 'Backuply' ), 'La importación de Webuzo aceptó uno de Backuply.' );
	unlink( wz_store() . '/' . $name );
	// La subida: un .tar.gz de Webuzo va a su almacén y su resultado lleva a importarlo.
	$bytes = wz_single();
	$start = Digitalisimo_Backups::service_upload_start( array( 'name' => 'subido_backup_full_remote_2026-05-05_05-05-05.tar.gz', 'size' => strlen( $bytes ) ) );
	wz_check( ! is_wp_error( $start ), 'No empezó la subida: ' . ( is_wp_error( $start ) ? $start->get_error_message() : '' ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += (int) $start['chunk_size'] ) Digitalisimo_Backups::service_upload_chunk( $start['id'], $offset, array( 'data' => substr( $bytes, $offset, (int) $start['chunk_size'] ) ) );
	$done = Digitalisimo_Backups::service_upload_finish( $start['id'] );
	wz_check( ! is_wp_error( $done ) && 'webuzo' === $done['container'] && is_file( wz_store() . '/' . $done['file'] ) && ! file_exists( $backuply_store . '/' . $done['file'] ), 'La subida de Webuzo no quedó en su almacén: ' . json_encode( is_wp_error( $done ) ? $done->get_error_message() : $done ) );
	$job = wz_import( $done['file'], array( 'source' => 'store' ) );
	wz_check( 'success' === $job['status'] && wz_row( $job['result']['import']['file'] ), 'No se importó lo subido: ' . json_encode( $job['error'] ) );
	$deleted = Digitalisimo_Backups::service_webuzo_delete( $done['file'] );
	wz_check( ! is_wp_error( $deleted ) && ! is_file( wz_store() . '/' . $done['file'] ), 'No se borró del almacén.' );
	wz_check( is_wp_error( Digitalisimo_Backups::service_webuzo_delete( $multi ) ), 'Se pudo borrar un respaldo del home de la cuenta.' );
} );

wz_case( 'W10 REST, WP-CLI, pantalla y panel de avance', function() use ( $multi, &$converted ) {
	wz_check( '' !== $converted && wz_row( $converted ), 'No hay fila del ZIP convertido (falló W3).' );
	if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=nonce-' . $action; } }
	ob_start();
	try {
		bk_call( 'Digitalisimo_Backups', 'history_row', wz_row( $converted ), 0, false, $converted );
	} finally {
		$row = (string) ob_get_clean();
	}
	wz_check( false !== strpos( $row, '>Importado</span>' ) && false !== strpos( $row, 'Importado de Webuzo (' ) && false !== strpos( $row, 'placeholder="RESTAURAR SIN FIRMA"' ), 'La fila «Importado» de Webuzo no se muestra como tal.' );
	$GLOBALS['wz_routes'] = array();
	Digitalisimo_Backups_Rest::routes();
	foreach ( array( '/webuzo', '/webuzo/import', '/webuzo/fetch' ) as $route ) wz_check( isset( $GLOBALS['wz_routes'][ $route ] ), 'Falta la ruta REST ' . $route );
	wz_check( (bool) preg_grep( '#^/webuzo/\(\?P<name>#', array_keys( $GLOBALS['wz_routes'] ) ) && isset( $GLOBALS['wz_routes']['/webuzo/import'][0]['args']['site'] ), 'Falta DELETE /webuzo/<nombre> o el parámetro site.' );
	wz_check( false !== strpos( (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php' ), "'digitalisimo-backups webuzo'" ), 'Falta wp digitalisimo-backups webuzo.' );
	ob_start();
	bk_call( 'Digitalisimo_Backups', 'webuzo_panel', false, Digitalisimo_Backups_Webuzo_Import::sources() );
	$html = (string) ob_get_clean();
	wz_check( false !== strpos( $html, 'value="' . $multi . '"' ) && false !== strpos( $html, Digitalisimo_Backups::ACTION_WEBUZO_IMPORT ) && false !== strpos( $html, 'name="webuzo_site" value="public_html/tienda"' ) && false !== strpos( $html, 'Importar este WordPress' ) && false === strpos( $html, $GLOBALS['bk_root'] ), 'La tarjeta de Webuzo no ofrece elegir el WordPress o revela rutas.' );
	try {
		$sent = bk_post( array( 'Digitalisimo_Backups', 'webuzo_import_request' ), array( '_wpnonce' => 'nonce-otra', 'webuzo_file' => $multi ) );
		$sent = ! empty( $sent[0] );
	} catch ( Throwable $e ) {
		$sent = false;
	}
	wz_check( ! $sent, 'La importación se pidió sin su nonce.' );
	$progress = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-progress.php' );
	$js = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/assets/manual-progress.js' );
	wz_check( false !== strpos( $progress, 'ACTION_WEBUZO_IMPORT' ) && false !== strpos( $js, 'digitalisimo_backups_webuzo_import' ) && false !== strpos( $js, 'job.choose' ) && false !== strpos( (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/assets/upload.js' ), "result.container === 'webuzo'" ), 'El panel de avance no conoce la importación de Webuzo.' );
} );

bk_cleanup();
foreach ( array( wz_home() . '/cuenta_backup_full_remote_2026-11-18_03-37-08.tar.gz', wz_home() . '/cuenta_backup_full_remote_2026-11-18_03-37-08.tar.gz.json' ) as $leftover ) @unlink( $leftover );
echo $failures ? "FALLA: $failures caso(s)\n" : "Importación de Webuzo: todo bien\n";
exit( $failures ? 1 : 0 );
