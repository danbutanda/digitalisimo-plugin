<?php
/**
 * Brecha #7 del estudio de UpdraftPlus 1.26.14: un conjunto de UpdraftPlus se importa (trabajo `import`, formato
 * `updraft`) a un ZIP de Backups que entra en la lista como «Importado» y se restaura con la restauración por pasos, con
 * una base de datos de verdad (SQLite por tests/lib/backups-sqlite.php). Los conjuntos se construyen como los escribe
 * UpdraftPlus 1.26.14 (`backup.php`): nombres `backup_<fecha>_<sitio>_<nonce>-<entidad><n>.<ext>`, partes `uploads2`,
 * la base de datos en gzip de varios miembros con su cabecera `#` (Backup of, Home URL, Content URL, Uploads URL, Table
 * prefix, ABSPATH, Site info multisite y multisite_data), `# Table:`, `DROP TABLE IF EXISTS`, el CREATE con « ;», INSERT
 * de varias filas con « \n» y «,\n », enteros sin comillas, binarios `0x…`, BIT `b'…'`, vistas, disparadores y rutinas
 * entre `DELIMITER ;;`, una tabla de mysqldump (LOCK TABLES, `/*!40000 ALTER TABLE … DISABLE KEYS *\/`, `_binary`), y los
 * ZIP `plugins/…`, `themes/…`, `mu-plugins/…`, `uploads/…` y `others` sin carpeta; la base cifrada como su
 * UpdraftPlus_Encryption (Rijndael de 128 bits en CBC, vector cero, clave de la frase completada con ceros). Comprueba:
 *   U1  lista de conjuntos (carpeta de UpdraftPlus y almacén), sin rutas; nombres hostiles, sin capability, sin base;
 *   U2  importación por tramos a un ZIP v3 sin firma: SQL al formato del exportador (binarios, BIT, enteros, NULL,
 *       escapes, mysqldump), sin vistas ni rutinas (avisadas) ni claves de Backups del origen; cada pieza en su carpeta
 *       de esta instalación (también `uploads2`), el núcleo y lo que falta de este WordPress, `wp-config.php` nunca;
 *       fila «Importado» en la lista (fuera de la retención, de otra instalación); verify «sin firma»;
 *   U3  restaurarlo en otro dominio, otra ruta y otro prefijo: direcciones, rutas, prefijo, archivos y núcleo intactos;
 *       RESTAURAR sola no basta;
 *   U4  base de datos cifrada: sin frase o con otra se rechaza sin dejar nada; con la suya (128 y 160 bits) se importa
 *       y la copia descifrada se borra;
 *   U5  la restauración directa de antes (una sola petición, sin copia previa) ya no existe: se rechaza con el camino;
 *   U6  conjuntos hostiles o incompletos fallan sin dejar nada (rutas «..», cabecera que no es de UpdraftPlus, gzip
 *       truncado, una pieza con dos carpetas, sin base de datos);
 *   U7  subir las piezas por fragmentos (al almacén `updraft/` con su nombre exacto), importarlas y borrarlas;
 *   U8  conjunto de una red: manifiesto de red y su fila con sus sitios;
 *   U9  una muerte a mitad de cada tramo no cambia el ZIP convertido;
 *   U10 REST y pantalla;
 *   U11 (revisión bk-180, H2) un archivo de este WordPress que cambia entre `scan` y `files`, entre dos tramos de su copia o
 *       mientras se lee hace fallar la importación; sin cambios, el SHA-256 del manifiesto es el de los bytes copiados;
 *   U12 (H3, U07) una pieza reescrita a mitad de la importación la hace fallar;
 *   U13 (H3, U08) el tope de expansión de la base de datos (bomba gzip);
 *   U14 (H7) tope de expansión de cada pieza ZIP y tope absoluto de todas, también sin disk_free_space();
 *   U15 (H6) un descifrado que no cabe en el tope de una petición web se para y pide WP-CLI, sin dejar nada;
 *   U16 (H3) el inspector de cada pieza, el del ZIP final y los dos filtros de «others» bastan cada uno solo.
 * Deben fallar con el código anterior (1.0.79, ea9753ca: no existe la importación de UpdraftPlus); U11, U14 y U15, con el
 * de acda04b2 (1.0.80 antes de la revisión).
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
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route( $namespace, $route, $args ) { $GLOBALS['ud_routes'][ $route ] = $args; return true; } }
$GLOBALS['bk_caps']['manage_options'] = true;
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;

$failures = 0;
function ud_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function ud_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
ud_check( class_exists( 'Digitalisimo_Backups' ) && method_exists( 'Digitalisimo_Backups', 'service_updraft_import' ), 'No existe la importación de UpdraftPlus (service_updraft_import).' );

/* ------------------------------------------------------------------ El formato de UpdraftPlus 1.26.14 */

/** Cadena como backup_table(): \\ y \' y después NUL, LF, CR y ^Z. */
function ud_str( $v ) { return "'" . str_replace( array( "\x00", "\x0a", "\x0d", "\x1a" ), array( '\0', '\n', '\r', '\Z' ), str_replace( '\'', '\\\'', str_replace( '\\', '\\\\', (string) $v ) ) ) . "'"; }
/** Valor por tipo de columna como backup_table(): `i` entero, `b` binario, `bit:N`, `s` cadena. */
function ud_val( $v, $type ) {
	// Entero: vacío o NULL se escribe con el valor por defecto de la columna (`i:0`), o NULL si no tiene (`i`).
	if ( 0 === strpos( $type, 'i' ) && 0 !== strpos( $type, 'bit' ) ) { $v = ( null === $v || '' === $v ) ? ( 'i' === $type ? 'NULL' : substr( $type, 2 ) ) : $v; return '' === $v ? "''" : (string) $v; }
	if ( 'b' === $type ) return null === $v ? 'NULL' : ( '' === $v ? "''" : '0x' . bin2hex( str_repeat( '0', (int) floor( strspn( $v, '0' ) / 4 ) ) . $v ) );
	if ( 0 === strpos( $type, 'bit:' ) ) {
		if ( null === $v ) return 'NULL';
		$bits = '';
		foreach ( str_split( bin2hex( $v ) ) as $nibble ) $bits .= str_pad( decbin( hexdec( $nibble ) ), 4, '0', STR_PAD_LEFT );
		return "b'" . str_pad( $bits, (int) substr( $type, 4 ), '0', STR_PAD_LEFT ) . "'";
	}
	return null === $v ? 'NULL' : ud_str( $v );
}
/** Una tabla como su bucle de backup_db(): `# Table:`, write_table_backup_beginning(), INSERT de varias filas y el fin. */
function ud_table( $table, $create, array $types, array $rows, $per = 2 ) {
	$sql = '# Table: `' . $table . "`\n";
	$sql .= "\n# Delete any existing table `" . $table . "`\n\nDROP TABLE IF EXISTS `" . $table . "`;\n";
	$sql .= "\n# Table structure of table `" . $table . "`\n\n" . $create . ' ;';
	$sql .= "\n\n# Data contents of table `" . $table . "`\n\n";
	foreach ( array_chunk( $rows, $per ) as $chunk ) {
		$entries = array();
		foreach ( $chunk as $row ) {
			$values = array();
			foreach ( array_values( $row ) as $i => $value ) $values[] = ud_val( $value, $types[ $i ] );
			$entries[] = '(' . implode( ', ', $values ) . ')';
		}
		$sql .= " \nINSERT INTO `" . $table . '` VALUES ' . implode( ",\n ", $entries ) . ';';
	}
	return $sql . "\n# End of data contents of table `" . $table . "`\n\n";
}
/** Cabecera de backup_db_header(); en una red, multisite_data y el sitio principal. */
function ud_header( array $o = array() ) {
	$o += array( 'url' => 'https://origen.com', 'prefix' => 'ud_', 'abspath' => '/var/www/origen/', 'multisite' => false, 'sites' => array(), 'version' => '1.26.14', 'wp' => '6.8.1' );
	$h = "# WordPress MySQL database backup\n# Created by UpdraftPlus version " . $o['version'] . " (https://teamupdraft.com/updraftplus/)\n";
	$h .= '# WordPress Version: ' . $o['wp'] . ", running on PHP 8.2.24 (Apache), MySQL 8.0.36\n# Backup of: " . $o['url'] . "\n# Home URL: " . $o['url'] . "\n# Content URL: " . $o['url'] . "/wp-content\n# Uploads URL: " . $o['url'] . "/wp-content/uploads\n";
	$h .= '# Table prefix: ' . $o['prefix'] . "\n# Filtered table prefix: " . $o['prefix'] . "\n# ABSPATH: " . $o['abspath'] . "\n# UpdraftPlus plugin slug: updraftplus/updraftplus.php\n# Site info: multisite=" . ( $o['multisite'] ? '1' : '0' ) . "\n";
	if ( $o['multisite'] ) $h .= '# Site info: multisite_data=' . json_encode( $o['sites'] ) . "\n# Site info: multisite_main_site_id=1\n";
	$h .= "# Site info: sql_mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES\n# Site info: end\n\n# Generated: Monday 9. November 2026 22:00 UTC\n# Hostname: localhost\n# Database: `wordpress`\n# --------------------------------------------------------\n";
	$h .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n/*!40101 SET NAMES utf8mb4 */;\n/*!40101 SET foreign_key_checks = 0 */;\n\n";
	return $h;
}
/** Las tablas de un WordPress de prueba (CREATE que SQLite entiende, con la forma de SHOW CREATE TABLE) y sus filas. */
function ud_tables( $p = 'ud_', $url = 'https://origen.com' ) {
	$widget = serialize( array( 2 => array( 'title' => 'Año ñandú €', 'text' => '<a href="' . $url . '/contacto/">Contacto</a>' ), '_multiwidget' => 1 ) );
	$t = array();
	$t[] = ud_table( $p . 'options', "CREATE TABLE `{$p}options` (\n  `option_id` INTEGER PRIMARY KEY,\n  `option_name` varchar(191) NOT NULL UNIQUE,\n  `option_value` longtext NOT NULL,\n  `autoload` varchar(20) NOT NULL DEFAULT 'yes'\n)", array( 'i', 's', 's', 's' ), array(
		array( 1, 'siteurl', $url, 'yes' ),
		array( 2, 'home', $url, 'yes' ),
		array( 3, 'blogname', "Sitio de O'Reilly; con -- y /* y # dentro", 'yes' ),
		array( 4, 'widget_text', $widget, 'yes' ),
		array( 5, 'upload_path', '/var/www/origen/wp-content/uploads', 'yes' ),
		array( 6, $p . 'user_roles', serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ) ) ), 'yes' ),
		array( 7, 'digitalisimo_backups_vault_keyring', 'no-debe-viajar', 'no' ),
		array( 8, 'updraft_interval', 'daily', 'yes' ),
	) );
	$tricky = "Texto con 'comillas', \"dobles\", ; punto y coma, \\ barra, \\' barra y comilla, salto\nde línea, \r retorno, \x1a ctrl-Z y \0 nulo -- no es comentario /* tampoco */ # ni esto; <a href=\"" . $url . "/blog/\">blog</a>";
	$t[] = ud_table( $p . 'posts', "CREATE TABLE `{$p}posts` (\n  `ID` INTEGER PRIMARY KEY,\n  `post_title` text NOT NULL,\n  `post_content` longtext NOT NULL,\n  `guid` varchar(255) NOT NULL DEFAULT '',\n  `post_type` varchar(20) NOT NULL DEFAULT 'post'\n)", array( 'i', 's', 's', 's', 's' ), array(
		array( 1, 'Inicio', $tricky, $url . '/?p=1', 'page' ),
		array( 2, 'Grande', str_repeat( 'Lorem ipsum ñ ', 4300 ), $url . '/?p=2', 'post' ),
		array( 3, 'Tercera', 'fila del segundo INSERT', $url . '/?p=3', 'post' ),
	) );
	$t[] = ud_table( $p . 'postmeta', "CREATE TABLE `{$p}postmeta` (\n  `meta_id` INTEGER PRIMARY KEY,\n  `post_id` bigint(20) NOT NULL DEFAULT 0,\n  `meta_key` varchar(255),\n  `meta_value` longtext\n)", array( 'i', 'i:0', 's', 's' ), array( array( 1, 1, '_nulo', null ), array( 2, 1, '_wp_attached_file', '2026/11/foto.jpg' ), array( 3, '', '_sin_entrada', 'x' ) ) );
	$t[] = ud_table( $p . 'users', "CREATE TABLE `{$p}users` (\n  `ID` INTEGER PRIMARY KEY,\n  `user_login` varchar(60) NOT NULL,\n  `user_email` varchar(100) NOT NULL,\n  `user_url` varchar(100) NOT NULL DEFAULT ''\n)", array( 'i', 's', 's', 's' ), array( array( 1, 'admin', 'admin@origen.com', $url ) ) );
	$t[] = ud_table( $p . 'usermeta', "CREATE TABLE `{$p}usermeta` (\n  `umeta_id` INTEGER PRIMARY KEY,\n  `user_id` bigint(20) NOT NULL DEFAULT 0,\n  `meta_key` varchar(255),\n  `meta_value` longtext\n)", array( 'i', 'i', 's', 's' ), array( array( 1, 1, $p . 'capabilities', serialize( array( 'administrator' => true ) ) ), array( 2, 1, $p . 'user_level', '10' ) ) );
	$t[] = ud_table( $p . 'binarios', "CREATE TABLE `{$p}binarios` (\n  `id` INTEGER PRIMARY KEY,\n  `datos` blob,\n  `bandera` bit(8)\n)", array( 'i', 'b', 'bit:8' ), array( array( 1, "\x00\xff\x01binario'\\", "\x05" ), array( 2, '', null ), array( 3, null, "\x80" ) ) );
	// Una tabla grande que UpdraftPlus volcó con mysqldump (backup_table_bindump(): sin comentarios, --extended-insert).
	$t[] = "# Table: `{$p}grande`\n/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!50503 SET character_set_client = utf8mb4 */;\nDROP TABLE IF EXISTS `{$p}grande`;\nCREATE TABLE `{$p}grande` (\n  `id` INTEGER PRIMARY KEY,\n  `v` longblob\n);\n/*!40101 SET character_set_client = @saved_cs_client */;\nLOCK TABLES `{$p}grande` WRITE;\n/*!40000 ALTER TABLE `{$p}grande` DISABLE KEYS */;\nINSERT INTO `{$p}grande` VALUES (1,_binary 'uno\\0dos'),(2,'tres');\n/*!40000 ALTER TABLE `{$p}grande` ENABLE KEYS */;\nUNLOCK TABLES;\n";
	// Una vista (write_table_backup_beginning() con VIEW).
	$t[] = "# Table: `{$p}vista`\n\n# Delete any existing table `{$p}vista`\n\nDROP TABLE IF EXISTS `{$p}vista`;\nDROP VIEW IF EXISTS `{$p}vista`;\n\n# Table structure of view `{$p}vista`\n\nCREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `{$p}vista` AS select `{$p}posts`.`ID` AS `ID` from `{$p}posts` ;\n\n# Data contents of view `{$p}vista`\n\n\n# End of data contents of table `{$p}vista`\n\n";
	return $t;
}
/** Disparadores y rutinas como los escribe backup_db() al final, entre DELIMITER ;;, y el pie. */
function ud_tail( $p = 'ud_' ) {
	return "DELIMITER ;;\n\n\n\n# Triggers of  `{$p}posts`\n\nDROP TRIGGER IF EXISTS `{$p}trig`;;\nCREATE TRIGGER `{$p}trig` BEFORE INSERT ON `{$p}posts` FOR EACH ROW SET NEW.post_title = 'x; DROP TABLE y';;\n\nDELIMITER ;\n\n\n\n# Dumping routines for database `wordpress`\n\nDELIMITER ;;\n\nDROP PROCEDURE IF EXISTS `{$p}proc`;;\n\nCREATE DEFINER=`root`@`localhost` PROCEDURE `{$p}proc`() BEGIN SELECT 1; DELETE FROM `{$p}posts`; END\n\n;;\n\nDELIMITER ;\n\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
}
/** La base de datos como la cose backup_db(): un miembro gzip por la cabecera, otro por tabla y el final, concatenados. */
function ud_db( array $o = array(), $extra = array() ) {
	$p = $o['prefix'] ?? 'ud_';
	$gz = gzencode( ud_header( $o ), 6 );
	foreach ( array_merge( ud_tables( $p, $o['url'] ?? 'https://origen.com' ), $extra ) as $table ) $gz .= gzencode( $table, 6 );
	return $gz . gzencode( ud_tail( $p ), 6 );
}
function ud_zip( array $entries ) {
	$path = tempnam( sys_get_temp_dir(), 'udzip' );
	@unlink( $path );
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE );
	foreach ( $entries as $name => $data ) {
		if ( '/' === substr( $name, -1 ) ) $zip->addEmptyDir( rtrim( $name, '/' ) );
		else $zip->addFromString( $name, $data );
	}
	$zip->close();
	$bytes = (string) file_get_contents( $path );
	@unlink( $path );
	return $bytes;
}
$GLOBALS['ud_foto'] = random_bytes( 70000 );
/** Las piezas de un conjunto completo: nombre (sin el conjunto) => bytes. */
function ud_pieces( array $o = array() ) {
	$o += array( 'db' => null );
	return array(
		'-db.gz' => null === $o['db'] ? ud_db() : $o['db'],
		'-plugins.zip' => ud_zip( array( 'plugins/' => '', 'plugins/hola/' => '', 'plugins/hola/hola.php' => "<?php /* Plugin Name: Hola */\n", 'plugins/hola/readme.txt' => "Hola\n" ) ),
		'-themes.zip' => ud_zip( array( 'themes/tema/style.css' => "/* Theme Name: Tema */\n", 'themes/tema/index.php' => "<?php // tema\n" ) ),
		'-mu-plugins.zip' => ud_zip( array( 'mu-plugins/propio.php' => "<?php // mu de UpdraftPlus\n" ) ),
		'-uploads.zip' => ud_zip( array( 'uploads/2026/11/foto.jpg' => $GLOBALS['ud_foto'] ) ),
		'-uploads2.zip' => ud_zip( array( 'uploads/2026/12/otra.txt' => "segunda parte de uploads\n" ) ),
		'-others.zip' => ud_zip( array( 'languages/es_MX.mo' => "MO\n", 'index.php' => "<?php // Silence is golden.\n", 'wp-config.php' => "<?php // no debe viajar\n", 'plugins/colado.php' => "<?php // no\n", 'upgrade/temp.txt' => "temporal\n", 'cache/pagina.html' => "cacheada\n" ) ),
	);
}
function ud_put_set( $dir, $set, array $pieces ) {
	if ( ! is_dir( $dir ) ) mkdir( $dir, 0755, true );
	foreach ( $pieces as $suffix => $bytes ) {
		file_put_contents( $dir . '/' . $set . $suffix, $bytes );
		touch( $dir . '/' . $set . $suffix, time() - 3600 );
	}
}
/** Cifrado de UpdraftPlus_Encryption::encrypt(): CBC continuo con vector cero y relleno sólo al final si hace falta. */
function ud_encrypt( $plain, $phrase ) {
	$length = strlen( $phrase );
	$bits = $length <= 16 ? 128 : ( $length <= 20 ? 160 : ( $length <= 24 ? 192 : ( $length <= 28 ? 224 : 256 ) ) );
	$key = str_pad( substr( $phrase, 0, $bits >> 3 ), $bits >> 3, "\0" );
	if ( strlen( $plain ) % 16 ) { $pad = 16 - strlen( $plain ) % 16; $plain .= str_repeat( chr( $pad ), $pad ); }
	if ( in_array( $bits, array( 128, 192, 256 ), true ) ) return openssl_encrypt( $plain, 'aes-' . $bits . '-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat( "\0", 16 ) );
	require_once __DIR__ . '/../digitalisimo-backups/includes/lib/autoload.php';
	$cipher = new \DigitalisimoBackups\Vendor\phpseclib3\Crypt\Rijndael( 'cbc' );
	$cipher->setBlockLength( 128 );
	$cipher->setKeyLength( $bits );
	$cipher->setKey( $key );
	$cipher->setIV( str_repeat( "\0", 16 ) );
	$cipher->disablePadding();
	return $cipher->encrypt( $plain );
}
function ud_drive( $id, $one = false, $between = null ) {
	for ( $i = 0; $i < 5000; $i++ ) {
		Digitalisimo_Backups_Jobs::tick( $one ? 0.0 : PHP_INT_MAX );
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $job;
		if ( $between ) $between( $job );
	}
	throw new RuntimeException( 'El trabajo ' . $id . ' no terminó.' );
}
function ud_import( $set, array $args = array(), $one = false, $between = null ) {
	$queued = Digitalisimo_Backups::service_updraft_import( $set, $args + array( 'via' => 'test' ) );
	ud_check( ! is_wp_error( $queued ), 'No se encoló la importación: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	ud_check( 'import' === $queued['kind'] && 'Importación de UpdraftPlus' === $queued['origin_label'], 'La vista del trabajo no es la de una importación de UpdraftPlus: ' . json_encode( $queued ) );
	return ud_drive( $queued['id'], $one, $between );
}
function ud_zip_map( $path ) {
	$zip = new ZipArchive();
	ud_check( true === $zip->open( $path ), 'No se abrió el ZIP convertido.' );
	$out = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( 'manifest.json' !== $name ) $out[ $name ] = hash( 'sha256', (string) $zip->getFromIndex( $i ) );
	}
	$zip->close();
	ksort( $out );
	return $out;
}
function ud_staging_left() {
	$root = Digitalisimo_Backups_Job_Store::root();
	$left = array();
	foreach ( (array) scandir( $root ) as $id ) if ( Digitalisimo_Backups_Job_Store::valid_id( $id ) && is_dir( $root . '/' . $id . '/staging' ) && array_diff( scandir( $root . '/' . $id . '/staging' ), array( '.', '..' ) ) ) $left[] = $id;
	return $left;
}
function ud_store() { Digitalisimo_Backups::load_updraft_import(); return Digitalisimo_Backups_Updraft_Import::store_directory( true ); }
function ud_row( $file ) { foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) if ( ( $row['file'] ?? '' ) === $file ) return $row; return null; }

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
// Núcleo de este WordPress con su versión, y un archivo PHP propio de la raíz.
file_put_contents( $abspath . '/wp-includes/version.php', "<?php\n\$wp_version = '7.1.4';\n" );
file_put_contents( $abspath . '/index.php', "<?php // index de este WordPress\n" );
@mkdir( $abspath . '/wp-includes/js', 0755, true );
file_put_contents( $abspath . '/wp-includes/js/core.js', "/* núcleo de aquí */\n" );
$config_before = file_get_contents( $abspath . '/wp-config.php' );
$core_before = array( 'wp-load.php' => file_get_contents( $abspath . '/wp-load.php' ), 'wp-includes/version.php' => file_get_contents( $abspath . '/wp-includes/version.php' ), 'wp-includes/js/core.js' => file_get_contents( $abspath . '/wp-includes/js/core.js' ) );
$set = 'backup_2026-11-09-2200_Mi_Sitio_0123456789ab';
$updraft_dir = WP_CONTENT_DIR . '/updraft';

ud_case( 'U1 lista de conjuntos, sin rutas; nombres hostiles, sin capability y sin base de datos', function() use ( $set, $updraft_dir ) {
	$none = Digitalisimo_Backups::service_updraft();
	ud_check( ! is_wp_error( $none ) && array() === $none['sets'], 'Sin UpdraftPlus, la lista no está vacía: ' . json_encode( $none ) );
	ud_put_set( $updraft_dir, $set, ud_pieces() );
	// Un conjunto sin su base de datos (sólo archivos) se lista, pero no se importa.
	ud_put_set( $updraft_dir, 'backup_2026-11-08-1000_Mi_Sitio_aaaaaaaaaaaa', array( '-plugins.zip' => ud_zip( array( 'plugins/x/x.php' => "<?php\n" ) ) ) );
	file_put_contents( $updraft_dir . '/log.0123456789ab.txt', 'registro de UpdraftPlus' );
	$list = Digitalisimo_Backups::service_updraft();
	ud_check( 2 === count( $list['sets'] ), 'La lista no tiene los dos conjuntos: ' . json_encode( $list['sets'] ) );
	$item = $list['sets'][0];
	ud_check( $set === $item['id'] && 'updraft' === $item['source'] && true === $item['database'] && false === $item['encrypted'] && true === $item['importable'] && 7 === count( $item['pieces'] ) && 2 === $item['entities']['uploads'], 'La lista no describe el conjunto: ' . json_encode( $item ) );
	ud_check( false === $list['sets'][1]['importable'] && false !== strpos( $list['sets'][1]['problem'], 'base de datos' ), 'Un conjunto sin base de datos se ofrece para importar.' );
	ud_check( false === strpos( json_encode( $list ), $GLOBALS['bk_root'] ), 'La lista revela rutas del servidor.' );
	foreach ( array( '../wp-config.php', 'backup_x', 'backup_2026-11-09-2200_Mi_Sitio_0123456789ab/../x', 'backup_2026-11-09-2200_Mi/Sitio_0123456789ab' ) as $bad ) ud_check( is_wp_error( Digitalisimo_Backups::service_updraft_import( $bad ) ), 'Se aceptó el nombre ' . $bad );
	$missing = Digitalisimo_Backups::service_updraft_import( 'backup_2026-01-01-0000_Otro_bbbbbbbbbbbb' );
	ud_check( is_wp_error( $missing ) && 404 === $missing->get_error_data()['status'], 'Un conjunto que no existe no responde 404.' );
	$incomplete = Digitalisimo_Backups::service_updraft_import( 'backup_2026-11-08-1000_Mi_Sitio_aaaaaaaaaaaa' );
	ud_check( is_wp_error( $incomplete ) && 422 === $incomplete->get_error_data()['status'], 'Un conjunto sin base de datos se encoló.' );
	$GLOBALS['bk_caps'] = array();
	ud_check( is_wp_error( Digitalisimo_Backups::service_updraft() ) && is_wp_error( Digitalisimo_Backups::service_updraft_import( $set ) ), 'Sin capability se listó o importó.' );
	$GLOBALS['bk_caps']['manage_options'] = true;
} );

$converted = '';
ud_case( 'U2 importación por tramos a un ZIP v3 sin firma, «Importado» en la lista', function() use ( $set, &$converted, $abspath ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	try {
		$job = ud_import( $set, array( 'source' => 'updraft' ), true );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	}
	ud_check( 'success' === $job['status'], 'La importación falló: ' . json_encode( $job['error'] ) );
	$phases = array();
	foreach ( Digitalisimo_Backups_Job_Store::events( $job['id'] ) as $event ) if ( 'phase' === ( $event['type'] ?? '' ) && ( $event['phase'] ?? '' ) === ( $event['next'] ?? '' ) ) $phases[ $event['phase'] ] = ( $phases[ $event['phase'] ] ?? 0 ) + 1;
	ud_check( ( $phases['scan'] ?? 0 ) >= 5 && ( $phases['database'] ?? 0 ) >= 5 && ( $phases['files'] ?? 0 ) >= 5, 'Las fases no avanzaron por tramos: ' . json_encode( $phases ) );
	$converted = $job['result']['import']['file'];
	ud_check( 'digitalisimo-updraft-2026-11-09-2200_Mi_Sitio_0123456789ab.zip' === $converted && is_file( bk_storage() . '/' . $converted ) && $converted === sanitize_file_name( $converted ), 'El ZIP convertido no está en el almacén con su nombre: ' . $converted );
	ud_check( 'RESTAURAR SIN FIRMA' === $job['result']['import']['confirmation'] && 'ud_' === $job['result']['import']['prefix'] && 7 === $job['result']['import']['tables'] && true === $job['result']['import']['listed'], 'El resultado no describe la importación: ' . json_encode( $job['result'] ) );
	ud_check( array() === ud_staging_left(), 'Quedó el área de trabajo de la importación.' );
	$zip = new ZipArchive();
	ud_check( true === $zip->open( bk_storage() . '/' . $converted ), 'No se abre el ZIP convertido.' );
	ud_check( '' === (string) $zip->getArchiveComment(), 'El ZIP convertido lleva firma.' );
	Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
	$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
	ud_check( 'digitalisimo-backup/v3' === $manifest['format'] && 'ud_' === $manifest['table_prefix'] && false === $manifest['multisite'] && 'https://origen.com' === $manifest['home_url'] && '/var/www/origen' === $manifest['origin']['abspath'] && '/var/www/origen/wp-content/uploads' === $manifest['origin']['uploads_dir'] && 'updraft' === $manifest['imported_from']['format'] && '1.26.14' === $manifest['imported_from']['updraft_version'] && '6.8.1' === $manifest['wordpress_version'] && '8.2.24' === $manifest['php_version'], 'Manifiesto inesperado: ' . json_encode( $manifest ) );
	// Las carpetas del origen salen de sus URL: los medios bajo el contenido, bajo el sitio (otra carpeta) o, si no se
	// pueden deducir (un CDN), la de siempre.
	$origin_of = function( $content, $uploads ) { return bk_call( 'Digitalisimo_Backups_Updraft_Import', 'origin', array( 'abspath' => '/var/www/o', 'siteurl' => 'https://o.com', 'home' => 'https://o.com', 'content_url' => $content, 'uploads_url' => $uploads, 'multisite' => false, 'sites' => array(), 'main_site' => 0 ), 'wp_' ); };
	$a = $origin_of( 'https://o.com/app', 'https://o.com/app/uploads' );
	$b = $origin_of( 'https://o.com/app', 'https://o.com/media' );
	$c = $origin_of( 'https://o.com/wp-content', 'https://cdn.o.net/uploads' );
	ud_check( '/var/www/o/app' === $a['content_dir'] && '/var/www/o/app/uploads' === $a['uploads_dir'] && '/var/www/o/media' === $b['uploads_dir'] && '/var/www/o/wp-content/uploads' === $c['uploads_dir'], 'Las carpetas del origen no salen de sus URL: ' . json_encode( array( $a, $b, $c ) ) );
	ud_check( array( 'ud_options', 'ud_posts', 'ud_postmeta', 'ud_users', 'ud_usermeta', 'ud_binarios', 'ud_grande' ) === $manifest['database_tables'], 'Tablas del manifiesto: ' . json_encode( $manifest['database_tables'] ) );
	$raw = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$notices = implode( ' ', (array) ( $raw['notices'] ?? array() ) );
	ud_check( false !== strpos( $notices, '1 vista' ) && false !== strpos( $notices, '2 disparador' ) && false !== strpos( $notices, 'núcleo de WordPress' ) && false !== strpos( $notices, '(7.1.4)' ) && false !== strpos( $notices, 'wp-config.php' ), 'Avisos del manifiesto: ' . $notices );
	// Cada pieza en su carpeta de esta instalación; el núcleo, de este WordPress; wp-config.php sólo el marcador.
	foreach ( array( 'wp-content/plugins/hola/hola.php', 'wp-content/plugins/hola/readme.txt', 'wp-content/themes/tema/style.css', 'wp-content/mu-plugins/propio.php', 'wp-content/uploads/2026/12/otra.txt', 'wp-content/languages/es_MX.mo', 'wp-content/index.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-includes/js/core.js', 'index.php' ) as $present ) ud_check( false !== $zip->locateName( 'wordpress/' . $present ), 'Falta wordpress/' . $present . ' en el ZIP.' );
	ud_check( $GLOBALS['ud_foto'] === $zip->getFromName( 'wordpress/wp-content/uploads/2026/11/foto.jpg' ), 'El binario de uploads no llegó idéntico.' );
	ud_check( "<?php\n\$wp_version = '7.1.4';\n" === $zip->getFromName( 'wordpress/wp-includes/version.php' ), 'El núcleo no es el de este WordPress.' );
	ud_check( false === strpos( (string) $zip->getFromName( 'wordpress/wp-config.php' ), 'no debe viajar' ) && false !== strpos( (string) $zip->getFromName( 'wordpress/wp-config.php' ), 'Marcador del ZIP convertido' ), 'wp-config.php no es el marcador.' );
	foreach ( array( 'wordpress/wp-content/wp-config.php', 'wordpress/wp-content/plugins/colado.php', 'wordpress/wp-content/upgrade/temp.txt', 'wordpress/wp-content/cache/pagina.html', 'wordpress/wp-content/plugins/example.php', 'wordpress/wp-content/updraft/' . $set . '-db.gz' ) as $absent ) ud_check( false === $zip->locateName( $absent ), 'El ZIP trae ' . $absent );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$zip->close();
	ud_check( 0 === strpos( $sql, "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n" ), 'El SQL no tiene la forma del exportador.' );
	ud_check( false !== strpos( $sql, "INSERT INTO `ud_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('3','blogname','Sitio de O\\'Reilly; con -- y /* y # dentro','yes');" ), 'La cadena de UpdraftPlus no pasó a la del exportador: ' . substr( $sql, (int) strpos( $sql, 'blogname' ) - 80, 200 ) );
	ud_check( false !== strpos( $sql, "\\\\ barra, \\\\\\' barra y comilla, salto\\nde línea, \\r retorno, \\Z ctrl-Z y \\0 nulo" ), 'Barras, saltos, ^Z o NUL no pasaron bien.' );
	ud_check( false !== strpos( $sql, "('1','1','_nulo',NULL)" ) && false !== strpos( $sql, "('3','0','_sin_entrada','x')" ) && 3 === substr_count( $sql, 'INSERT INTO `ud_posts`' ), 'NULL, enteros o las filas de varios INSERT no se convirtieron.' );
	ud_check( false !== strpos( $sql, "VALUES ('1','\\0" . "\xff\x01binario\\'\\\\','\x05');" ) && false !== strpos( $sql, "VALUES ('2','',NULL);" ) && false !== strpos( $sql, "VALUES ('3',NULL,'\x80');" ), 'Binarios 0x… o BIT b\'…\' no pasaron a sus bytes: ' . substr( $sql, (int) strpos( $sql, 'INSERT INTO `ud_binarios`' ), 200 ) );
	ud_check( false !== strpos( $sql, "INSERT INTO `ud_grande` (`id`,`v`) VALUES ('1','uno\\0dos');" ) && false !== strpos( $sql, "INSERT INTO `ud_grande` (`id`,`v`) VALUES ('2','tres');" ), 'La tabla de mysqldump (_binary, DISABLE KEYS) no se convirtió.' );
	foreach ( array( 'digitalisimo_backups_vault_keyring', 'VIEW', 'TRIGGER', 'PROCEDURE', 'DELIMITER', 'LOCK TABLES', 'DISABLE KEYS', '_binary', 'SQL_MODE' ) as $absent ) ud_check( false === strpos( $sql, $absent ), 'El SQL convertido trae ' . $absent );
	// La fila de la lista: «Importado», de fuera (fuera de la retención, de otra instalación), sin firma.
	$row = ud_row( $converted );
	ud_check( is_array( $row ) && 'imported' === $row['origin'] && 'updraft' === $row['import']['format'] && $set === $row['import']['source'] && 'installation' === $row['scope'] && 'full' === $row['backup_type'] && hash_equals( (string) $row['sha256'], hash_file( 'sha256', bk_storage() . '/' . $converted ) ), 'La fila de la lista no es la de un importado: ' . json_encode( $row ) );
	ud_check( Digitalisimo_Backups::is_uploaded_row( $row ) && 'Importado' === bk_call( 'Digitalisimo_Backups', 'uploaded_summary', $row )['label'] && false === bk_call( 'Digitalisimo_Backups', 'keep_config_same_installation', $converted ), 'La fila «Importado» no se trata como una de fuera.' );
	$verify = Digitalisimo_Backups::service_verify( $converted );
	ud_check( ! is_wp_error( $verify ) && true === $verify['verified'] && 'untrusted' === $verify['trust'] && 'RESTAURAR SIN FIRMA' === $verify['confirmation'] && true === $verify['compatible'], 'verify no da el ZIP por «sin firma» y restaurable: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $verify ) );
	ud_check( $converted === Digitalisimo_Backups::service_updraft()['sets'][0]['imported'], 'La lista de UpdraftPlus no dice que ya se importó.' );
} );

ud_case( 'U3 restaurar el importado en otro dominio, otra ruta y otro prefijo', function() use ( $dest, $abspath, $set, &$converted, $config_before, $core_before, $updraft_dir ) {
	ud_check( '' !== $converted, 'No hay ZIP convertido (falló U2).' );
	ud_check( is_wp_error( Digitalisimo_Backups::service_restore( $converted, array( 'confirm' => 'RESTAURAR' ) ) ), 'Se restauró un importado sin firma con RESTAURAR.' );
	// Backups está activo en este sitio (el conjunto viene de un sitio sin él).
	$GLOBALS['bk_options']['active_plugins'] = array( Digitalisimo_Backups_Restore_Maintenance::plugin() );
	$result = Digitalisimo_Backups::service_restore( $converted, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	ud_check( ! is_wp_error( $result ), 'La restauración falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	$opt = function( $option ) use ( $dest ) { return $dest->value( 'wp_options', 'option_value', "option_name = '" . $option . "'" ); };
	ud_check( 'https://destino.test/sitio' === $opt( 'home' ) && 'https://destino.test/sitio' === $opt( 'siteurl' ), 'home/siteurl: ' . $opt( 'home' ) );
	ud_check( "Sitio de O'Reilly; con -- y /* y # dentro" === $opt( 'blogname' ) && 'no-debe-viajar' !== $opt( 'digitalisimo_backups_vault_keyring' ), 'blogname o las claves de Backups del origen.' );
	ud_check( null !== $opt( 'wp_user_roles' ) && null === $opt( 'ud_user_roles' ), 'Las claves con prefijo no pasaron a wp_.' );
	$widget = unserialize( $opt( 'widget_text' ), array( 'allowed_classes' => false ) );
	ud_check( is_array( $widget ) && 'Año ñandú €' === $widget[2]['title'] && false !== strpos( $widget[2]['text'], 'https://destino.test/sitio/contacto/' ), 'El serializado no se reubicó: ' . $opt( 'widget_text' ) );
	ud_check( $abspath . '/wp-content/uploads' === $opt( 'upload_path' ), 'La ruta del servidor no se reubicó: ' . $opt( 'upload_path' ) );
	// El conjunto no traía Backups activo: queda activo (si no, tras el cambio los ticks no cargarían el plugin).
	$active = @unserialize( (string) $opt( 'active_plugins' ), array( 'allowed_classes' => false ) );
	ud_check( is_array( $active ) && in_array( Digitalisimo_Backups_Restore_Maintenance::plugin(), $active, true ), 'Backups no quedó activo en la base restaurada: ' . json_encode( $active ) );
	ud_check( "\x00\xff\x01binario'\\" === $dest->value( 'wp_binarios', 'datos', 'id = 1' ) && "\x05" === $dest->value( 'wp_binarios', 'bandera', 'id = 1' ), 'Los binarios no llegaron idénticos.' );
	ud_check( str_repeat( 'Lorem ipsum ñ ', 4300 ) === $dest->value( 'wp_posts', 'post_content', 'ID = 2' ) && 'https://origen.com/?p=1' === $dest->value( 'wp_posts', 'guid', 'ID = 1' ), 'Contenido grande o guid.' );
	ud_check( ! in_array( 'ud_options', $dest->names(), true ) && ! in_array( 'wp_vista', $dest->names(), true ), 'Quedaron tablas del origen o la vista: ' . json_encode( $dest->names() ) );
	foreach ( array( 'wp-content/plugins/hola/hola.php', 'wp-content/themes/tema/style.css', 'wp-content/mu-plugins/propio.php', 'wp-content/uploads/2026/12/otra.txt', 'wp-content/languages/es_MX.mo' ) as $present ) ud_check( is_file( $abspath . '/' . $present ), 'No se restauró ' . $present );
	ud_check( $GLOBALS['ud_foto'] === file_get_contents( $abspath . '/wp-content/uploads/2026/11/foto.jpg' ), 'La foto no llegó idéntica.' );
	foreach ( $core_before as $name => $bytes ) ud_check( $bytes === file_get_contents( $abspath . '/' . $name ), 'El núcleo de este WordPress cambió: ' . $name );
	ud_check( $config_before === file_get_contents( $abspath . '/wp-config.php' ), 'La restauración escribió wp-config.php.' );
	ud_check( ! is_file( $abspath . '/wp-content/plugins/colado.php' ) && ! is_file( $abspath . '/wp-content/wp-config.php' ), 'Se restauró lo que UpdraftPlus no restaura.' );
	ud_check( is_file( $updraft_dir . '/' . $set . '-db.gz' ), 'La carpeta de UpdraftPlus cambió.' );
	ud_check( is_array( $result['relocation'] ) && 0 === $result['relocation']['check']['urls'] && 0 === $result['relocation']['check']['paths'], 'Quedaron URL o rutas del origen: ' . json_encode( $result['relocation']['check'] ?? null ) );
} );

ud_case( 'U4 base de datos cifrada: sin frase u otra se rechaza; con la suya (128 y 160 bits) se importa', function() {
	$plain = ud_db();
	foreach ( array( 'frase-corta' => 'cccccccccccc', 'una-frase-de-20-car' => 'dddddddddddd' ) as $phrase => $nonce ) {
		$name = 'backup_2026-11-10-0800_Cifrado_' . $nonce;
		$pieces = ud_pieces();
		unset( $pieces['-db.gz'] );
		$pieces['-db.gz.crypt'] = ud_encrypt( $plain, $phrase );
		ud_put_set( ud_store(), $name, $pieces );
		$item = array_values( array_filter( Digitalisimo_Backups::service_updraft()['sets'], function( $s ) use ( $name ) { return $name === $s['id']; } ) )[0];
		ud_check( true === $item['encrypted'] && true === $item['importable'] && 'store' === $item['source'], 'El conjunto cifrado no se describe: ' . json_encode( $item ) );
		$no = Digitalisimo_Backups::service_updraft_import( $name );
		ud_check( is_wp_error( $no ) && 'digitalisimo_backups_updraft_secret' === $no->get_error_code() && false !== strpos( $no->get_error_message(), $name . '-db.gz.crypt' ), 'Sin frase no pidió la frase (nombrando la pieza cifrada).' );
		$wrong = Digitalisimo_Backups::service_updraft_import( $name, array( 'secret' => 'otra-frase' ) );
		ud_check( is_wp_error( $wrong ) && 'digitalisimo_backups_updraft_secret' === $wrong->get_error_code() && false !== strpos( $wrong->get_error_message(), 'no descifra' ), 'Con otra frase no se rechazó: ' . ( is_wp_error( $wrong ) ? $wrong->get_error_message() : 'se encoló' ) );
		ud_check( ! glob( ud_store() . '/.decrypted-*' ), 'Quedó una copia descifrada tras una frase equivocada.' );
		$job = ud_import( $name, array( 'secret' => $phrase ) );
		ud_check( 'success' === $job['status'] && true === $job['result']['import']['listed'], 'No se importó con su frase (' . strlen( $phrase ) . ' bytes): ' . json_encode( $job['error'] ?? null ) );
		ud_check( ! glob( ud_store() . '/.decrypted-*' ), 'Quedó la copia descifrada tras importar.' );
		ud_check( false === strpos( json_encode( Digitalisimo_Backups_Job_Store::events( $job['id'] ) ) . json_encode( $job ), $phrase ), 'La frase quedó en el trabajo o en sus eventos.' );
		$zip = new ZipArchive();
		$zip->open( bk_storage() . '/' . $job['result']['import']['file'] );
		ud_check( false !== strpos( (string) $zip->getFromName( 'database.sql' ), 'fila del segundo INSERT' ), 'La base descifrada no se convirtió.' );
		$zip->close();
	}
} );

ud_case( 'U5 la restauración directa de antes se rechaza con el camino nuevo', function() use ( $set, $dest ) {
	$before = $dest->value( 'wp_options', 'option_value', "option_name = 'blogname'" );
	list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'updraft' => '1', 'updraft_set' => $set, 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
	ud_check( ! $ok && false !== strpos( (string) $message, 'impórtalo' ), 'La restauración directa de UpdraftPlus no se rechazó: ' . $message );
	ud_check( $before === $dest->value( 'wp_options', 'option_value', "option_name = 'blogname'" ), 'La restauración directa cambió la base de datos.' );
	ud_check( ! method_exists( 'Digitalisimo_Backups', 'restore_updraft_set' ), 'Sigue la ruta de una sola petición sin copia previa.' );
} );

ud_case( 'U6 conjuntos hostiles o incompletos fallan sin dejar nada', function() {
	$cases = array(
		'ruta con ..' => array( '-others.zip' => ud_zip( array( '../../fuera.php' => "<?php\n" ) ) ),
		'cabecera ajena' => array( '-db.gz' => gzencode( "-- MySQL dump\nCREATE TABLE `a` (`id` int);\n" ) ),
		'gzip truncado' => array( '-db.gz' => substr( ud_db(), 0, -40 ) ),
		'dos carpetas' => array( '-plugins.zip' => ud_zip( array( 'plugins/a.php' => "<?php\n", 'otra/b.php' => "<?php\n" ) ) ),
		'sentencia prohibida' => array( '-db.gz' => ud_db( array(), array( "GRANT ALL ON *.* TO 'x'@'%';\n" ) ) ),
	);
	$n = 0;
	foreach ( $cases as $label => $override ) {
		$name = sprintf( 'backup_2026-11-11-0%03d_Hostil_%012x', $n, 0xe00000 + $n );
		$n++;
		$pieces = array_merge( ud_pieces(), $override );
		if ( 'ruta con ..' === $label ) {
			// ZipArchive normaliza «..» al crear: el nombre se escribe a mano en el ZIP.
			$zip = ud_zip( array( 'xx/../../fuera.php' => "<?php\n" ) );
			$pieces['-others.zip'] = str_replace( 'xx/../../fuera.php', '../../../fuera.php', $zip );
		}
		ud_put_set( ud_store(), $name, $pieces );
		$before = scandir( bk_storage() );
		$queued = Digitalisimo_Backups::service_updraft_import( $name );
		if ( is_wp_error( $queued ) ) continue;
		$job = ud_drive( $queued['id'] );
		ud_check( 'failed' === $job['status'], $label . ': la importación no falló.' );
		ud_check( $before === scandir( bk_storage() ) && array() === ud_staging_left(), $label . ': quedó algo en el almacén o en el área de trabajo.' );
		ud_check( ! file_exists( dirname( $GLOBALS['bk_root'] ) . '/fuera.php' ) && ! file_exists( $GLOBALS['bk_root'] . '/fuera.php' ), $label . ': se escribió fuera.' );
	}
} );

ud_case( 'U7 subir las piezas por fragmentos, importarlas y borrarlas', function() {
	$name = 'backup_2026-11-12-0930_Subido_ffffffffffff';
	$files = array();
	foreach ( ud_pieces() as $suffix => $bytes ) {
		$start = Digitalisimo_Backups::service_upload_start( array( 'name' => $name . $suffix, 'size' => strlen( $bytes ) ) );
		ud_check( ! is_wp_error( $start ), 'No empezó la subida de ' . $suffix . ': ' . ( is_wp_error( $start ) ? $start->get_error_message() : '' ) );
		$chunk = (int) $start['chunk_size'];
		for ( $offset = 0; $offset < strlen( $bytes ); $offset += $chunk ) {
			$sent = Digitalisimo_Backups::service_upload_chunk( $start['id'], $offset, array( 'data' => substr( $bytes, $offset, $chunk ) ) );
			ud_check( ! is_wp_error( $sent ), 'Falló un fragmento: ' . ( is_wp_error( $sent ) ? $sent->get_error_message() : '' ) );
		}
		$done = Digitalisimo_Backups::service_upload_finish( $start['id'] );
		ud_check( ! is_wp_error( $done ) && 'updraft' === $done['container'] && $name . $suffix === $done['file'], 'La pieza no terminó como de UpdraftPlus: ' . json_encode( is_wp_error( $done ) ? $done->get_error_message() : $done ) );
		$files[] = $done['file'];
	}
	foreach ( $files as $file ) ud_check( is_file( ud_store() . '/' . $file ) && ! file_exists( bk_storage() . '/' . $file ), 'La pieza no quedó en updraft/ del almacén: ' . $file );
	$fake = 'no es un zip';
	$start = Digitalisimo_Backups::service_upload_start( array( 'name' => $name . '-themes2.zip', 'size' => strlen( $fake ) ) );
	Digitalisimo_Backups::service_upload_chunk( $start['id'], 0, array( 'data' => $fake ) );
	$rejected = Digitalisimo_Backups::service_upload_finish( $start['id'] );
	ud_check( is_wp_error( $rejected ) && 'digitalisimo_backups_upload_invalid' === $rejected->get_error_code() && ! file_exists( ud_store() . '/' . $name . '-themes2.zip' ), 'Una pieza que no es ZIP no se descartó.' );
	$job = ud_import( $name, array( 'source' => 'store' ) );
	ud_check( 'success' === $job['status'] && ud_row( $job['result']['import']['file'] ), 'No se importó el subido: ' . json_encode( $job['error'] ?? $job['result'] ) );
	$deleted = Digitalisimo_Backups::service_updraft_delete( $name );
	ud_check( ! is_wp_error( $deleted ) && 7 === $deleted['deleted'] && ! glob( ud_store() . '/' . $name . '*' ), 'No se borraron las piezas del almacén: ' . json_encode( $deleted ) );
	ud_check( is_wp_error( Digitalisimo_Backups::service_updraft_delete( 'backup_2026-11-09-2200_Mi_Sitio_0123456789ab' ) ), 'Se pudo borrar un conjunto de la carpeta de UpdraftPlus.' );
} );

ud_case( 'U8 conjunto de una red: manifiesto de red y su fila con sus sitios', function() {
	$name = 'backup_2026-11-13-1100_Red_121212121212';
	$sites = array( array( 'blog_id' => 1, 'domain' => 'red.com', 'path' => '/', 'siteurl' => 'https://red.com', 'home' => 'https://red.com', 'uploads_url' => 'https://red.com/wp-content/uploads' ), array( 'blog_id' => 2, 'domain' => 'red.com', 'path' => '/uno/', 'siteurl' => 'https://red.com/uno', 'home' => 'https://red.com/uno', 'uploads_url' => 'https://red.com/wp-content/uploads/sites/2' ) );
	$network = array(
		ud_table( 'ud_blogs', "CREATE TABLE `ud_blogs` (\n  `blog_id` INTEGER PRIMARY KEY,\n  `site_id` bigint(20) NOT NULL DEFAULT 0,\n  `domain` varchar(200) NOT NULL DEFAULT '',\n  `path` varchar(100) NOT NULL DEFAULT ''\n)", array( 'i', 'i', 's', 's' ), array( array( 1, 1, 'red.com', '/' ), array( 2, 1, 'red.com', '/uno/' ) ) ),
		ud_table( 'ud_site', "CREATE TABLE `ud_site` (\n  `id` INTEGER PRIMARY KEY,\n  `domain` varchar(200) NOT NULL DEFAULT '',\n  `path` varchar(100) NOT NULL DEFAULT ''\n)", array( 'i', 's', 's' ), array( array( 1, 'red.com', '/' ) ) ),
	);
	$pieces = ud_pieces( array( 'db' => ud_db( array( 'url' => 'https://red.com', 'multisite' => true, 'sites' => $sites ), $network ) ) );
	ud_put_set( ud_store(), $name, $pieces );
	$job = ud_import( $name );
	ud_check( 'success' === $job['status'] && true === $job['result']['import']['multisite'], 'La red no se importó como red: ' . json_encode( $job['error'] ?? $job['result'] ) );
	$zip = new ZipArchive();
	$zip->open( bk_storage() . '/' . $job['result']['import']['file'] );
	$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
	$zip->close();
	ud_check( true === $manifest['multisite'] && 'red.com' === $manifest['origin']['network']['domain'] && '/' === $manifest['origin']['network']['path'] && false === $manifest['origin']['subdomain'], 'El manifiesto no es el de una red: ' . json_encode( $manifest['origin'] ) );
	$row = ud_row( $job['result']['import']['file'] );
	ud_check( 'network' === $row['scope'] && array( 1 => 'red.com', 2 => 'red.com/uno' ) === $row['sites'], 'La fila de la red no lleva su alcance y sus sitios: ' . json_encode( $row ) );
	// En este WordPress individual, la red se restaura como uno de sus sitios (1.0.79): la fila se ofrece para restaurar.
	ud_check( true === bk_call( 'Digitalisimo_Backups', 'restorable_item', $row ), 'El importado de una red no se ofrece para restaurar uno de sus sitios en un individual.' );
} );

ud_case( 'U9 una muerte a mitad de cada tramo no cambia el ZIP convertido', function() use ( $set, &$converted ) {
	ud_check( '' !== $converted, 'No hay ZIP convertido (falló U2).' );
	$reference = ud_zip_map( bk_storage() . '/' . $converted );
	// Trozos de 16 KB y dos unidades por tramo: la foto (70 KB) y database.sql quedan a medias entre tramos y, al
	// retomarlas, su flujo (del ZIP de la pieza o del SQL convertido) se vuelve a leer hasta donde se quedaron.
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 2;
	$GLOBALS['bk_filters']['digitalisimo_backups_zip_chunk'] = function() { return 16384; };
	$partials = array();
	try {
		$job = ud_import( $set, array( 'source' => 'updraft' ), true, function( $job ) use ( &$partials ) {
			$partial = $job['checkpoint']['files']['partial'] ?? null;
			if ( is_array( $partial ) ) $partials[ 'sql' === $partial['src'] ? 'database.sql' : (string) $partial['entry']['n'] ] = true;
			$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
			foreach ( array( 'source.sql', 'index.jsonl', 'database.sql', 'converted.zip', 'entries.jsonl' ) as $file ) if ( is_file( $staging . '/' . $file ) ) file_put_contents( $staging . '/' . $file, 'basura de un proceso muerto', FILE_APPEND );
		} );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'], $GLOBALS['bk_filters']['digitalisimo_backups_zip_chunk'] );
	}
	ud_check( 'success' === $job['status'], 'La importación con muertes falló: ' . json_encode( $job['error'] ) );
	ud_check( isset( $partials['database.sql'], $partials['wp-content/uploads/2026/11/foto.jpg'] ), 'Ninguna entrada quedó a medias entre tramos (database.sql y la foto de una pieza ZIP): ' . json_encode( array_keys( $partials ) ) );
	$again = ud_zip_map( bk_storage() . '/' . $job['result']['import']['file'] );
	ud_check( isset( $again['wordpress/wp-config.php'] ), 'Tras un database.sql a medias entre tramos, el ZIP no lleva el marcador de wp-config.php (la restauración lo exige).' );
	// El núcleo de este WordPress no cambió con la restauración de U3 (UpdraftPlus no lo trae): todo idéntico.
	ud_check( array_keys( $reference ) === array_keys( $again ), 'El ZIP con muertes no tiene las mismas entradas.' );
	foreach ( $reference as $name => $hash ) ud_check( $hash === $again[ $name ], 'La entrada ' . $name . ' cambió con las muertes.' );
} );

ud_case( 'U10 REST, WP-CLI, pantalla y panel de avance', function() use ( $set, &$converted ) {
	// La fila «Importado» de la lista: su distintivo y su panel «Restaurar» piden RESTAURAR SIN FIRMA (lo que exige).
	ud_check( '' !== $converted && ud_row( $converted ), 'No hay fila del ZIP convertido (falló U2).' );
	if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=nonce-' . $action; } }
	ob_start();
	try {
		bk_call( 'Digitalisimo_Backups', 'history_row', ud_row( $converted ), 0, false, $converted );
	} finally {
		$row = (string) ob_get_clean();
	}
	ud_check( false !== strpos( $row, '>Importado</span>' ) && false !== strpos( $row, 'Importado de UpdraftPlus (' . $set . ')' ) && false !== strpos( $row, 'escribe RESTAURAR SIN FIRMA para confirmar' ) && false !== strpos( $row, 'placeholder="RESTAURAR SIN FIRMA"' ), 'La fila «Importado» no se muestra como tal o su panel no pide RESTAURAR SIN FIRMA.' );
	// El panel de avance lleva a esa fila al terminar la importación (`listed`), no a «Restaurar un archivo suelto».
	$views = array();
	foreach ( Digitalisimo_Backups_Job_Store::all() as $job ) if ( Digitalisimo_Backups_Import_Job::is_updraft( $job ) && 'success' === $job['status'] ) $views[] = Digitalisimo_Backups_Jobs::view( $job );
	ud_check( $views && ! array_filter( $views, function( $view ) { return true !== $view['listed'] || 'Importación de UpdraftPlus' !== $view['origin_label']; } ), 'La vista de la importación no dice que quedó en la lista: ' . json_encode( $views[0] ?? null ) );
	$GLOBALS['ud_routes'] = array();
	Digitalisimo_Backups_Rest::routes();
	foreach ( array( '/updraft', '/updraft/import', '/updraft/fetch' ) as $route ) ud_check( isset( $GLOBALS['ud_routes'][ $route ] ), 'Falta la ruta REST ' . $route );
	ud_check( (bool) preg_grep( '#^/updraft/\(\?P<set>#', array_keys( $GLOBALS['ud_routes'] ) ), 'Falta DELETE /updraft/<conjunto>.' );
	ud_check( class_exists( 'Digitalisimo_Backups_Cli_Updraft' ) || false !== strpos( (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php' ), "'digitalisimo-backups updraft'" ), 'Falta wp digitalisimo-backups updraft.' );
	ob_start();
	bk_call( 'Digitalisimo_Backups', 'updraft_panel', false, Digitalisimo_Backups_Updraft_Import::sources() );
	$html = (string) ob_get_clean();
	ud_check( false !== strpos( $html, 'value="' . $set . '"' ) && false !== strpos( $html, Digitalisimo_Backups::ACTION_UPDRAFT_IMPORT ) && false !== strpos( $html, 'name="updraft_secret"' ) && false === strpos( $html, $GLOBALS['bk_root'] ), 'La tarjeta de UpdraftPlus no ofrece importar (con la frase de los cifrados) o revela rutas.' );
	try {
		$sent = bk_post( array( 'Digitalisimo_Backups', 'updraft_import_request' ), array( '_wpnonce' => 'nonce-otra', 'updraft_set' => $set ) );
		$sent = ! empty( $sent[0] );
	} catch ( Throwable $e ) {
		$sent = false;
	}
	ud_check( ! $sent, 'La importación se pidió sin su nonce.' );
} );

/* ------------------------------------------------------------------ Revisión bk-180 (H2, H3, H6, H7) */

/** Comprueba que el SHA-256 del manifiesto de un ZIP convertido es el de los bytes de cada entrada; devuelve el manifiesto. */
function ud_manifest_matches( $path ) {
	$zip = new ZipArchive();
	ud_check( true === $zip->open( $path ), 'No se abre el ZIP convertido.' );
	try {
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$entries = (array) ( $manifest['entries'] ?? array() );
		ud_check( count( $entries ) === $zip->numFiles - 1, 'El manifiesto no lista todas las entradas.' );
		foreach ( $entries as $name => $entry ) {
			$bytes = $zip->getFromName( $name );
			ud_check( is_string( $bytes ) && strlen( $bytes ) === (int) $entry['size'] && hash( 'sha256', $bytes ) === $entry['sha256'], 'El SHA-256 del manifiesto no es el de los bytes de ' . $name . '.' );
		}
		return $manifest;
	} finally {
		$zip->close();
	}
}
/** Importa y exige que falle con `$needle` sin dejar nada en el almacén ni en el área de trabajo. */
function ud_import_fails( $name, array $args, $needle, $one = false, $between = null ) {
	$before = scandir( bk_storage() );
	$job = ud_import( $name, $args, $one, $between );
	$error = is_array( $job['error'] ?? null ) ? (string) $job['error']['message'] : '';
	ud_check( 'failed' === $job['status'], 'La importación no falló (se esperaba «' . $needle . '»): ' . json_encode( $job['result'] ?? null ) );
	ud_check( false !== strpos( $error, $needle ), 'La importación falló por otra razón (se esperaba «' . $needle . '»): ' . $error );
	ud_check( $before === scandir( bk_storage() ) && array() === ud_staging_left(), 'Quedó algo en el almacén o en el área de trabajo.' );
	return $job;
}
/** La entrada de este WordPress que copy() está copiando ahora ('' si es otra cosa): para actuar a mitad de su copia. */
function ud_copying() {
	foreach ( debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT ) as $frame ) {
		if ( 'copy' === ( $frame['function'] ?? '' ) && 'Digitalisimo_Backups_Updraft_Import' === ( $frame['class'] ?? '' ) ) return (string) ( $frame['args'][5]['entry']['n'] ?? '' );
	}
	return '';
}
/** classify() de una entrada con otra disposición de carpetas: su línea del índice, o null si no se importa. */
function ud_classify( $entity, $entry, array $layout ) {
	$cursor = array( 'skipped' => array(), 'files' => 0, 'bytes' => 0, 'mu' => false );
	$index = fopen( 'php://memory', 'w+' );
	$method = new ReflectionMethod( 'Digitalisimo_Backups_Updraft_Import', 'classify' );
	if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
	$layout += array( 'plugins' => 'wp-content/plugins', 'themes' => 'wp-content/themes', 'mu' => 'wp-content/mu-plugins', 'uploads' => 'wp-content/uploads', 'content' => 'wp-content' );
	$args = array( $entity, '', $entry, 0, array( 'size' => 3, 'mtime' => 0 ), $layout, &$cursor, $index );
	$method->invokeArgs( null, $args );
	rewind( $index );
	$line = (string) stream_get_contents( $index );
	fclose( $index );
	return '' === $line ? null : json_decode( $line, true );
}

ud_case( 'U11 un archivo de este WordPress que cambia entre fases o mientras se copia hace fallar la importación (nunca entra truncado ni mezclado)', function() use ( $set, $abspath ) {
	$target = $abspath . '/wp-includes/js/core.js';
	$original = (string) file_get_contents( $target );
	$mtime = (int) filemtime( $target );
	$put = function( $bytes, $shift ) use ( $target, $mtime ) { file_put_contents( $target, $bytes ); touch( $target, $mtime + $shift ); clearstatcache(); };
	try {
		// Sin cambios: el SHA-256 de cada entrada del manifiesto es el de sus bytes; el del núcleo, el del disco. Sin firma.
		$job = ud_import( $set, array( 'source' => 'updraft' ) );
		ud_check( 'success' === $job['status'], 'La importación sin cambios falló: ' . json_encode( $job['error'] ) );
		$manifest = ud_manifest_matches( bk_storage() . '/' . $job['result']['import']['file'] );
		ud_check( hash( 'sha256', $original ) === ( $manifest['entries']['wordpress/wp-includes/js/core.js']['sha256'] ?? '' ), 'El núcleo del ZIP no es el del disco.' );
		ud_check( 'untrusted' === ( $manifest['imported_from']['trust'] ?? '' ), 'El manifiesto de lo importado no dice que no es confiable: ' . json_encode( $manifest['imported_from'] ?? null ) );
		$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
		// R1 de la revisión: crece entre `scan` y `files` (una actualización automática a mitad de la importación).
		$armed = true;
		ud_import_fails( $set, array( 'source' => 'updraft' ), 'cambió durante la importación', true, function( $job ) use ( &$armed, $put ) {
			if ( $armed && 'done' === ( $job['checkpoint']['scan']['step'] ?? '' ) ) { $armed = false; $put( "/* núcleo de aquí */\n/* línea nueva de la actualización */ var nuevo = 1;\n", 0 ); }
		} );
		// Mismo tamaño y otra fecha: sería un núcleo con dos versiones mezcladas.
		$put( $original, 0 );
		$armed = true;
		ud_import_fails( $set, array( 'source' => 'updraft' ), 'cambió durante la importación', true, function( $job ) use ( &$armed, $put, $original ) {
			if ( $armed && 'done' === ( $job['checkpoint']['scan']['step'] ?? '' ) ) { $armed = false; $put( str_repeat( 'x', strlen( $original ) ), 5 ); }
		} );
		// Su entrada queda a medias entre tramos (trozos de 4 bytes) y el archivo cambia antes de retomarla.
		$put( $original, 0 );
		$armed = true;
		$GLOBALS['bk_filters']['digitalisimo_backups_zip_chunk'] = function( $size ) { return 'wp-includes/js/core.js' === ud_copying() ? 4 : $size; };
		ud_import_fails( $set, array( 'source' => 'updraft' ), 'cambió durante la importación', true, function( $job ) use ( &$armed, $put, $original ) {
			$partial = $job['checkpoint']['files']['partial'] ?? null;
			if ( $armed && is_array( $partial ) && 'wp-includes/js/core.js' === ( $partial['entry']['n'] ?? '' ) ) { $armed = false; $put( str_repeat( 'y', strlen( $original ) ), 7 ); }
		} );
		ud_check( ! $armed, 'La entrada del núcleo no quedó a medias entre tramos.' );
		// Crece mientras se lee (después de abrirlo): lo ve la comprobación de después de copiarlo.
		$put( $original, 0 );
		$armed = true;
		$GLOBALS['bk_filters']['digitalisimo_backups_zip_chunk'] = function( $size ) use ( &$armed, $target, $mtime ) {
			if ( $armed && 'wp-includes/js/core.js' === ud_copying() ) { $armed = false; file_put_contents( $target, "/* crece */\n", FILE_APPEND ); touch( $target, $mtime ); clearstatcache(); }
			return $size;
		};
		ud_import_fails( $set, array( 'source' => 'updraft' ), 'cambió mientras se copiaba', true );
		ud_check( ! $armed, 'El archivo no llegó a cambiar mientras se copiaba.' );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'], $GLOBALS['bk_filters']['digitalisimo_backups_zip_chunk'] );
		$put( $original, 0 );
	}
} );

ud_case( 'U12 una pieza que UpdraftPlus reescribe a mitad de la importación la hace fallar (nunca se mezclan dos versiones)', function() use ( $set, $updraft_dir ) {
	$piece = $updraft_dir . '/' . $set . '-uploads.zip';
	$bytes = (string) file_get_contents( $piece );
	$mtime = (int) filemtime( $piece );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	$armed = true;
	try {
		ud_import_fails( $set, array( 'source' => 'updraft' ), 'cambió durante la importación', true, function( $job ) use ( &$armed, $piece ) {
			if ( ! $armed || 'done' !== ( $job['checkpoint']['scan']['step'] ?? '' ) ) return;
			$armed = false;
			// Las mismas entradas con otros bytes (otra foto del mismo tamaño): sin comprobarla, entraría mezclada.
			file_put_contents( $piece, ud_zip( array( 'uploads/2026/11/foto.jpg' => random_bytes( 70000 ) ) ) );
			touch( $piece, time() - 1800 );
		} );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
		file_put_contents( $piece, $bytes );
		touch( $piece, $mtime );
	}
	ud_check( ! $armed, 'La pieza no llegó a cambiar.' );
} );

ud_case( 'U13 una base de datos de UpdraftPlus que se descomprime más allá del tope (bomba gzip) no se importa', function() {
	$name = 'backup_2026-11-14-0700_Bomba_131313131313';
	$filler = ud_table( 'ud_relleno', "CREATE TABLE `ud_relleno` (\n  `id` INTEGER PRIMARY KEY,\n  `v` longtext\n)", array( 'i', 's' ), array( array( 1, str_repeat( 'a', 600000 ) ) ) );
	$pieces = ud_pieces( array( 'db' => ud_db( array(), array( $filler ) ) ) );
	ud_put_set( ud_store(), $name, $pieces );
	// El tope es 50 veces lo comprimido, al menos 64 MB y nunca más que el máximo que se importa: con ese máximo en
	// 300 KB, esta base (más de 600 KB descomprimida, unos KB comprimida) se rechaza en la descompresión.
	$GLOBALS['bk_filters']['digitalisimo_backups_import_max_size'] = 300000;
	try {
		ud_check( 300000 === Digitalisimo_Backups_Backuply_Import::max_expansion( strlen( $pieces['-db.gz'] ) ), 'El tope no es el máximo que se importa.' );
		ud_import_fails( $name, array(), 'bomba de descompresión' );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_import_max_size'] );
	}
	$job = ud_import( $name );
	ud_check( 'success' === $job['status'], 'Con el tope de siempre, la misma base no se importó: ' . json_encode( $job['error'] ) );
	Digitalisimo_Backups::service_updraft_delete( $name );
} );

ud_case( 'U14 piezas ZIP: tope de expansión por pieza y tope absoluto, también sin disk_free_space()', function() {
	$GLOBALS['bk_filters']['digitalisimo_backups_free_space'] = null;
	try {
		// Un uploads.zip de unos 70 KB que declara 70 MB (ceros, 1 000 veces): supera 64 MB, el mínimo del tope.
		$sparse = tempnam( sys_get_temp_dir(), 'udcero' );
		$handle = fopen( $sparse, 'r+' );
		ftruncate( $handle, 70 * 1048576 );
		fclose( $handle );
		$path = tempnam( sys_get_temp_dir(), 'udbomba' );
		@unlink( $path );
		$zip = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE );
		$zip->addFile( $sparse, 'uploads/2026/11/ceros.bin' );
		$zip->close();
		@unlink( $sparse );
		$name = 'backup_2026-11-14-0800_Bomba_141414141414';
		$pieces = ud_pieces();
		$pieces['-uploads.zip'] = (string) file_get_contents( $path );
		@unlink( $path );
		ud_check( strlen( $pieces['-uploads.zip'] ) < 1048576, 'La pieza de prueba no es pequeña: ' . strlen( $pieces['-uploads.zip'] ) );
		ud_put_set( ud_store(), $name, $pieces );
		ud_import_fails( $name, array(), 'bomba de descompresión' );
		Digitalisimo_Backups::service_updraft_delete( $name );
		// Tope absoluto: cada pieza cabe en el suyo (90 KB de 160 KB), pero las de medios juntas declaran más.
		$name = 'backup_2026-11-14-0900_Grande_151515151515';
		$pieces = ud_pieces();
		$pieces['-uploads.zip'] = ud_zip( array( 'uploads/2026/11/a.bin' => random_bytes( 90000 ) ) );
		$pieces['-uploads2.zip'] = ud_zip( array( 'uploads/2026/12/b.bin' => random_bytes( 90000 ) ) );
		ud_put_set( ud_store(), $name, $pieces );
		$GLOBALS['bk_filters']['digitalisimo_backups_import_max_size'] = 160000;
		try {
			ud_import_fails( $name, array(), 'el máximo que se importa' );
		} finally {
			unset( $GLOBALS['bk_filters']['digitalisimo_backups_import_max_size'] );
		}
		$job = ud_import( $name );
		ud_check( 'success' === $job['status'], 'Con el máximo de siempre, el mismo conjunto no se importó: ' . json_encode( $job['error'] ) );
		Digitalisimo_Backups::service_updraft_delete( $name );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_free_space'] );
	}
} );

ud_case( 'U15 un descifrado que no cabe en el tope de una petición web se para y pide WP-CLI, sin dejar nada', function() {
	// Frase de 19 caracteres: clave de 160 bits, el Rijndael de PHP (unos 5 MB/s). Más de un trozo de 2 MiB.
	$phrase = 'una-frase-de-19-car';
	$name = 'backup_2026-11-15-1000_Lento_161616161616';
	$pieces = ud_pieces();
	unset( $pieces['-db.gz'] );
	$pieces['-db.gz.crypt'] = ud_encrypt( ud_db() . str_repeat( "\0", 3 * 1048576 ), $phrase );
	ud_put_set( ud_store(), $name, $pieces );
	$jobs = count( Digitalisimo_Backups_Job_Store::all() );
	$GLOBALS['bk_filters']['digitalisimo_backups_updraft_decrypt_seconds'] = 0.02;
	try {
		$slow = Digitalisimo_Backups::service_updraft_import( $name, array( 'secret' => $phrase ) );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_updraft_decrypt_seconds'] );
	}
	ud_check( is_wp_error( $slow ) && 'digitalisimo_backups_updraft_slow' === $slow->get_error_code() && false !== strpos( $slow->get_error_message(), 'WP-CLI' ) && false !== strpos( $slow->get_error_message(), '--secret-file' ) && false !== strpos( $slow->get_error_message(), '160 bits' ), 'El descifrado lento no se paró pidiendo WP-CLI: ' . ( is_wp_error( $slow ) ? $slow->get_error_code() . ' ' . $slow->get_error_message() : 'se encoló' ) );
	ud_check( false === strpos( $slow->get_error_message(), $phrase ), 'El mensaje lleva la frase.' );
	ud_check( ! glob( ud_store() . '/.decrypted-*' ) && count( Digitalisimo_Backups_Job_Store::all() ) === $jobs, 'Quedó una copia descifrada a medias o un trabajo.' );
	// La decisión: se para al llegar al tope o, antes, si al ritmo de lo hecho lo que falta no cabe (sin esperar a llegar).
	ud_check( Digitalisimo_Backups_Updraft_Import::too_slow( 0.3, 2097152, 25165824, 2.0 ) && Digitalisimo_Backups_Updraft_Import::too_slow( 2.0, 23068672, 25165824, 2.0 ) && ! Digitalisimo_Backups_Updraft_Import::too_slow( 0.3, 2097152, 4194304, 2.0 ) && ! Digitalisimo_Backups_Updraft_Import::too_slow( 1.5, 23068672, 25165824, 2.0 ), 'El tope del descifrado no se decide por el ritmo de lo hecho y lo que falta.' );
	// Sin tope (PHP CLI, como WP-CLI), el mismo conjunto se importa.
	ud_check( 0.0 === Digitalisimo_Backups_Updraft_Import::decrypt_seconds(), 'En PHP CLI el descifrado tiene tope.' );
	$job = ud_import( $name, array( 'secret' => $phrase ) );
	ud_check( 'success' === $job['status'], 'Sin tope no se importó: ' . json_encode( $job['error'] ) );
	Digitalisimo_Backups::service_updraft_delete( $name );
} );

ud_case( 'U16 cada defensa basta sola: el inspector de cada pieza, el del ZIP final y los dos filtros de «others»', function() {
	// Sólo el inspector de cada pieza ve un enlace simbólico: en el ZIP convertido sería un archivo normal.
	$name = 'backup_2026-11-16-1100_Enlace_171717171717';
	$pieces = ud_pieces();
	$raw = tempnam( sys_get_temp_dir(), 'udlink' );
	bk_raw_zip( $raw, array( array( 'name' => 'uploads/2026/11/foto.jpg', 'data' => 'jpg' ), array( 'name' => 'uploads/2026/11/enlace', 'data' => '/etc/passwd', 'attr' => 0120777 << 16 ) ) );
	$pieces['-uploads.zip'] = (string) file_get_contents( $raw );
	@unlink( $raw );
	ud_put_set( ud_store(), $name, $pieces );
	ud_import_fails( $name, array(), 'enlace simbólico' );
	Digitalisimo_Backups::service_updraft_delete( $name );
	// Sólo el inspector del ZIP final ve el mismo archivo en dos partes de una entidad (cada pieza, sola, está bien).
	$name = 'backup_2026-11-16-1200_Doble_181818181818';
	$pieces = ud_pieces();
	$pieces['-uploads2.zip'] = ud_zip( array( 'uploads/2026/11/foto.jpg' => 'otra versión' ) );
	ud_put_set( ud_store(), $name, $pieces );
	ud_import_fails( $name, array(), 'duplicados' );
	Digitalisimo_Backups::service_updraft_delete( $name );
	// «others» nunca trae plugins, temas ni medios: por su carpeta de primer nivel (aunque los plugins de aquí vivan fuera
	// de wp-content) y por las carpetas de esta instalación (aunque los medios de aquí se llamen de otra forma).
	ud_check( null === ud_classify( 'others', 'plugins/colado.php', array( 'plugins' => 'app/plugins' ) ), 'Con los plugins de aquí fuera de wp-content, «others» trajo plugins/.' );
	ud_check( null === ud_classify( 'others', 'media/2026/11/colado.jpg', array( 'uploads' => 'wp-content/media' ) ), 'Con los medios de aquí en wp-content/media, «others» los trajo.' );
	$kept = ud_classify( 'others', 'languages/es_MX.mo', array() );
	ud_check( is_array( $kept ) && 'wp-content/languages/es_MX.mo' === $kept['n'], 'Lo demás de «others» dejó de importarse: ' . json_encode( $kept ) );
} );

bk_cleanup();
echo $failures ? "FALLO: $failures casos.\n" : "Importación de UpdraftPlus: todo correcto.\n";
exit( $failures ? 1 : 0 );
