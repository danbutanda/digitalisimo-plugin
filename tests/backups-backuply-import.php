<?php
/**
 * I9 · Importar y restaurar los respaldos de Backuply que ya existen, con una base de datos de verdad (SQLite por
 * tests/lib/backups-sqlite.php). El `.tar.gz` se construye como lo escribe Backuply 1.6.0 (`backuplytar.php`: tar GNU
 * «ustar  », nombres largos `././@LongLink`, enlaces simbólicos sin destino, gzip en varios miembros; `backup_ins.php`:
 * `softsql.sql` con el volcado de phpMyAdmin, `softperms.txt`, `softver.txt` y `<nombre>.php` con `<?php exit();?>` y el
 * JSON de información; el archivo de información también en `backups_info-*`). Comprueba:
 *   B1  detección de Backuply y lista de sus respaldos (carpeta de Backuply y almacén), sin rutas del servidor;
 *   B2  importación por el motor de trabajos, por tramos (cada fase en varios), a un ZIP de Backups sin firma con
 *       manifiesto v3 estricto; el SQL pasa al formato del exportador (comillas duplicadas, barras, NUL, \Z, saltos,
 *       un INSERT de Backuply partido en dos, números y NULL), sin vistas, rutinas, disparadores ni claves foráneas
 *       (avisados), sin las claves de Backups del origen ni la carpeta de Backuply; verify lo da por «sin firma»;
 *   B3  restaurar ese ZIP con la restauración por pasos y la reubicación de E1: otro dominio, otra ruta y otro prefijo
 *       (bk_ → wp_), datos y archivos idénticos, la carpeta de Backuply del sitio intacta;
 *   B4  una muerte a mitad de cada tramo (bytes de más tras el punto de control en cada archivo del área de trabajo)
 *       no cambia el resultado: el ZIP convertido es idéntico al de una importación de un tirón;
 *   B5  archivos hostiles o incompletos: rutas con «..», absolutas o con barra invertida, enlaces duros, sumas que no
 *       cuadran, gzip truncado, tar sin final, «sólo archivos», «sólo base de datos», sentencias prohibidas (GRANT, un
 *       motor remoto, funciones en un INSERT, otra base de datos, un ejecutable que no es SET, una tabla que el volcado
 *       no creó, una cadena sin cerrar) y lo que no es gzip: falla sin dejar nada en el almacén ni en el área de trabajo,
 *       y el archivo de información de Backuply nunca se ejecuta;
 *   B6  subida por fragmentos de un `.tar.gz` (al almacén `backuply/`, no a «Restaurar») y su importación; uno que no
 *       es gzip se descarta;
 *   B7  pantalla («Respaldos de Backuply» con «Importar», nonce y capability), duplicados, cancelación y REST.
 * Deben fallar con el código anterior (1.0.64, d3ac3fa8): no existe el importador.
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
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route( $namespace, $route, $args ) { $GLOBALS['bi_routes'][ $route ] = $args; return true; } }
$GLOBALS['bk_caps']['manage_options'] = true;
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;

$failures = 0;
function bi_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function bi_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}

/* ------------------------------------------------------------------ El formato de Backuply */

/** Cabecera como `_writeHeader()` de backuplytar.php: «ustar » + « », permisos y tamaños en octal, suma al final. */
function bi_header( $name, $size, $type = '0', $mode = 0644, $mtime = 1762700000, $magic = 'ustar ', $version = ' ' ) {
	$first = pack( 'a100a8a8a8a12a12', $name, sprintf( '%07s', decoct( $mode ) ), sprintf( '%07s', decoct( 1000 ) ), sprintf( '%07s', decoct( 1000 ) ), sprintf( '%011s', decoct( $size ) ), sprintf( '%011s', decoct( $mtime ) ) );
	$last = pack( 'a1a100a6a2a32a32a8a8a155a12', $type, '', $magic, $version, 'www', 'www', '', '', '', '' );
	$sum = 0;
	for ( $i = 0; $i < 148; $i++ ) $sum += ord( $first[ $i ] );
	$sum += 8 * 32;
	for ( $i = 0; $i < 356; $i++ ) $sum += ord( $last[ $i ] );
	return $first . pack( 'a8', sprintf( '%06s ', decoct( $sum ) ) ) . $last;
}
function bi_pad( $data ) { return $data . str_repeat( "\0", ( 512 - strlen( $data ) % 512 ) % 512 ); }
/** Una entrada: con nombre de más de 99 bytes, antes su `././@LongLink` (tipo L), como `_writeLongHeader()`. */
function bi_entry( $name, $data = '', $type = '0', $mode = 0644 ) {
	$out = '';
	if ( strlen( $name ) > 99 ) $out .= bi_header( '././@LongLink', strlen( $name ), 'L', 0 ) . bi_pad( $name );
	return $out . bi_header( $name, '0' === $type ? strlen( $data ) : 0, $type, $mode ) . ( '0' === $type ? bi_pad( $data ) : '' );
}
/** Escape de `backuply_PMA_sqlAddslashes()` + `str_replace( NUL, LF, CR, ^Z )`. */
function bi_pma( $value ) { return "'" . str_replace( array( "\x00", "\x0a", "\x0d", "\x1a" ), array( '\0', '\n', '\r', '\Z' ), str_replace( "'", "''", str_replace( '\\', '\\\\', (string) $value ) ) ) . "'"; }
/** Una tabla como `backuply_PMA_getTableDef()` y `backuply_PMA_exportData()`: varias filas por INSERT, partido a los 50 000. */
function bi_table( $table, $create, array $numeric, array $rows, $auto = 0 ) {
	$sql = "\n-- Delete any existing table `$table`\nDROP TABLE IF EXISTS `$table`;\n--\n-- Table structure for table `$table`\n--\n\n" . $create . ( $auto ? ' AUTO_INCREMENT=' . $auto . ' ' : '' ) . ";\n\n";
	if ( $rows ) {
		$sql .= "--\n-- Dumping data for table `$table`\n--\n\n";
		$length = 0;
		$new = true;
		foreach ( $rows as $index => $row ) {
			$sql .= $new ? 'INSERT INTO `' . $table . "` VALUES\n(" : ",\n(";
			$new = false;
			$values = array();
			foreach ( array_values( $row ) as $i => $value ) {
				$values[] = null === $value ? 'NULL' : ( ! empty( $numeric[ $i ] ) ? (string) $value : bi_pma( $value ) );
				if ( null !== $value ) $length += strlen( (string) $value );
			}
			$sql .= implode( ', ', $values ) . ')';
			if ( $length > 50000 ) { $sql .= ";\n"; $new = true; $length = 0; }
		}
		if ( ! $new ) $sql .= ";\n";
	}
	return $sql . "\n-- --------------------------------------------------------\n";
}
/** Volcado completo de un WordPress de prueba (prefijo $p), con vista, claves foráneas, disparador y procedimiento. */
function bi_dump( $p = 'bk_', array $extra = array() ) {
	$widget = serialize( array( 2 => array( 'title' => 'Año ñandú €', 'text' => '<a href="https://origen.com/contacto/">Contacto</a>' ), '_multiwidget' => 1 ) );
	$sql = "-- Softaculous SQL Dump\n-- http://www.softaculous.com\n--\n-- Host: localhost\n-- Generation Time: November 9, 2026, 10:00 pm\n-- Server version: 8.0.36\n-- PHP Version: 8.2.0\n\nSET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\nSET time_zone = \"+00:00\";\n\n\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n/*!40101 SET NAMES utf8mb4 */;\n\n--\n-- Database: `wordpress`\n--\n\n-- --------------------------------------------------------\n";
	$sql .= bi_table( $p . 'hijos', "CREATE TABLE `{$p}hijos` (\n  `id` INTEGER PRIMARY KEY,\n  `padre` bigint(20) NOT NULL DEFAULT 0,\n  `nota` text\n)", array( true, true, false ), array( array( 1, 1, 'https://origen.com/hijo' ) ), 2 );
	$sql .= bi_table( $p . 'options', "CREATE TABLE `{$p}options` (\n  `option_id` INTEGER PRIMARY KEY,\n  `option_name` varchar(191) NOT NULL UNIQUE,\n  `option_value` longtext NOT NULL,\n  `autoload` varchar(20) NOT NULL DEFAULT 'yes'\n)", array( true, false, false, false ), array(
		array( 1, 'siteurl', 'https://origen.com', 'yes' ),
		array( 2, 'home', 'https://origen.com', 'yes' ),
		array( 3, 'blogname', "Sitio de O'Reilly; con -- y /* y # dentro", 'yes' ),
		array( 4, 'widget_text', $widget, 'yes' ),
		array( 5, 'upload_path', '/var/www/origen/wp-content/uploads', 'yes' ),
		array( 6, $p . 'user_roles', serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ) ) ), 'yes' ),
		array( 7, 'digitalisimo_backups_vault_keyring', 'no-debe-viajar', 'no' ),
		array( 8, 'backuply_settings', 'a:0:{}', 'yes' ),
	), 9 );
	$tricky = "Texto con 'comillas', \"dobles\", ; punto y coma, \\ barra, \\' barra y comilla, salto\nde línea, \r retorno, \x1a ctrl-Z y \0 nulo -- no es comentario /* tampoco */ # ni esto; <a href=\"https://origen.com/blog/\">blog</a>";
	$sql .= bi_table( $p . 'posts', "CREATE TABLE `{$p}posts` (\n  `ID` INTEGER PRIMARY KEY,\n  `post_title` text NOT NULL,\n  `post_content` longtext NOT NULL,\n  `guid` varchar(255) NOT NULL DEFAULT '',\n  `post_type` varchar(20) NOT NULL DEFAULT 'post'\n)", array( true, false, false, false, false ), array(
		array( 1, 'Inicio', $tricky, 'https://origen.com/?p=1', 'page' ),
		array( 2, 'Grande', str_repeat( 'Lorem ipsum ñ ', 4300 ), 'https://origen.com/?p=2', 'post' ),
		array( 3, 'Después del corte', 'fila tras el INSERT partido', 'https://origen.com/?p=3', 'post' ),
	), 4 );
	$sql .= bi_table( $p . 'postmeta', "CREATE TABLE `{$p}postmeta` (\n  `meta_id` INTEGER PRIMARY KEY,\n  `post_id` bigint(20) NOT NULL DEFAULT 0,\n  `meta_key` varchar(255),\n  `meta_value` longtext\n)", array( true, true, false, false ), array( array( 1, 1, '_nulo', null ), array( 2, 1, '_wp_attached_file', '2026/11/foto.jpg' ) ), 3 );
	$sql .= bi_table( $p . 'users', "CREATE TABLE `{$p}users` (\n  `ID` INTEGER PRIMARY KEY,\n  `user_login` varchar(60) NOT NULL,\n  `user_email` varchar(100) NOT NULL,\n  `user_url` varchar(100) NOT NULL DEFAULT ''\n)", array( true, false, false, false ), array( array( 1, 'admin', 'admin@origen.com', 'https://origen.com' ) ), 2 );
	$sql .= bi_table( $p . 'usermeta', "CREATE TABLE `{$p}usermeta` (\n  `umeta_id` INTEGER PRIMARY KEY,\n  `user_id` bigint(20) NOT NULL DEFAULT 0,\n  `meta_key` varchar(255),\n  `meta_value` longtext\n)", array( true, true, false, false ), array( array( 1, 1, $p . 'capabilities', serialize( array( 'administrator' => true ) ) ), array( 2, 1, $p . 'user_level', '10' ) ), 3 );
	foreach ( $extra as $more ) $sql .= $more;
	// Vistas, claves foráneas (al final, como `$alter_queries`), disparadores y rutinas, con DELIMITER.
	$sql .= "\n--\n-- Structure for view `{$p}vista`\n--\nDROP VIEW IF EXISTS `{$p}vista`;\n\nCREATE ALGORITHM=UNDEFINED DEFINER=CURRENT_USER SQL SECURITY DEFINER VIEW `{$p}vista` AS select `{$p}posts`.`ID` AS `ID` from `{$p}posts`;\n";
	$sql .= "\n--\n-- Constraints for table `{$p}hijos`\n--\nALTER TABLE `{$p}hijos`\n  ADD CONSTRAINT `fk_padre` FOREIGN KEY (`padre`) REFERENCES `{$p}posts` (`ID`);\n";
	$sql .= "\nDROP TRIGGER IF EXISTS `wordpress`.`{$p}trig`;\nDELIMITER //\nCREATE TRIGGER `{$p}trig` BEFORE INSERT ON `{$p}posts`\n FOR EACH ROW SET NEW.post_title = 'x; DROP TABLE y'\n\n// \nDELIMITER ;\n\n";
	$sql .= "\nDROP PROCEDURE IF EXISTS `wordpress`.`{$p}proc`;\nDELIMITER $$ \n-- \n-- Procedures \n--\nCREATE DEFINER=`root`@`localhost` PROCEDURE `{$p}proc`() BEGIN SELECT 1; DELETE FROM `{$p}posts`; END\n$$ \nDELIMITER ;\n\n";
	return $sql . "\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
}
/** Archivo de información de Backuply (`backuply_info_json()`), con código PHP que nunca debe ejecutarse. */
function bi_info( $name, array $over = array() ) {
	$info = $over + array( 'name' => $name, 'backup_dir' => 1, 'backup_db' => 1, 'email' => 'admin@origen.com', 'date_time' => '2026-11-09 22:00:pm', 'btime' => 1762725600, 'auto_backup' => false, 'ext' => 'tar.gz', 'size' => 1234, 'backup_site_url' => 'https://origen.com', 'backup_site_path' => '/var/www/origen' );
	return "<?php exit();?>\n" . json_encode( $info, JSON_PRETTY_PRINT );
}
$GLOBALS['bi_foto'] = random_bytes( 70000 );
$GLOBALS['bi_long'] = 'wp-content/uploads/carpeta-con-un-nombre-bastante-largo/y-otra-subcarpeta-mas-larga-todavia/archivo-con-nombre-muy-largo-para-ustar.txt';
/**
 * Entradas de un respaldo completo de Backuply como las escribe su bucle: softsql.sql primero, el sitio (rutas
 * relativas a ABSPATH, carpetas con «/»), y al final softperms.txt, softver.txt y el archivo de información.
 */
function bi_entries( $name, array $o = array() ) {
	$o += array( 'sql' => bi_dump(), 'config' => "<?php\n// \$table_prefix = 'falso_';\n/* define( 'MULTISITE', true ); */\ndefine( 'DB_NAME', 'wordpress' );\n\$table_prefix = 'bk_';\nrequire_once ABSPATH . 'wp-settings.php';\n", 'info' => bi_info( $name ) );
	$e = array();
	if ( false !== $o['sql'] ) $e[] = array( 'softsql.sql', $o['sql'] );
	$e[] = array( 'wp-admin/', '', '5', 0755 );
	$e[] = array( 'wp-admin/index.php', "<?php // admin de Backuply\n" );
	$e[] = array( 'wp-config.php', $o['config'] );
	$e[] = array( 'wp-content/', '', '5', 0755 );
	$e[] = array( 'wp-content/backuply/', '', '5', 0755 );
	$e[] = array( 'wp-content/backuply/backups-zz/viejo.tar.gz', 'no debe importarse' );
	$e[] = array( 'wp-content/enlace', '', '2', 0777 );
	$e[] = array( 'wp-content/plugins/hola/hola.php', "<?php /* Plugin Name: Hola */\n" );
	$e[] = array( 'wp-content/themes/tema/style.css', "/* Theme Name: Tema */\n" );
	$e[] = array( 'wp-content/uploads/2026/11/foto.jpg', $GLOBALS['bi_foto'] );
	$e[] = array( $GLOBALS['bi_long'], "texto con nombre largo\n" );
	$e[] = array( 'wp-includes/version.php', "<?php\n\$wp_version = '6.8.1';\n" );
	$e[] = array( 'wp-load.php', "<?php // load de Backuply\n" );
	$e[] = array( 'wp-settings.php', "<?php // settings de Backuply\n" );
	$e[] = array( 'softperms.txt', "/ 0755\nwp-content/enlace linkto=../otro 0777\n" );
	$e[] = array( 'softver.txt', '1.6.0' );
	$e[] = array( $name . '.php', $o['info'] );
	return $e;
}
function bi_tar( array $entries, $footer = true ) {
	$tar = '';
	foreach ( $entries as $entry ) $tar .= isset( $entry['raw'] ) ? $entry['raw'] : bi_entry( $entry[0], $entry[1], $entry[2] ?? '0', $entry[3] ?? 0644 );
	return $tar . ( $footer ? str_repeat( "\0", 1024 ) : '' );
}
/** Backuply añade al .tar.gz en cada tramo: varios miembros gzip seguidos. */
function bi_gz( $tar, $parts = 3 ) {
	$out = '';
	$size = (int) ceil( strlen( $tar ) / $parts );
	foreach ( str_split( $tar, max( 1, $size ) ) as $piece ) $out .= gzencode( $piece, 9 );
	return $out;
}
/** Deja un respaldo de Backuply en su carpeta de este sitio (y su archivo de información en backups_info-*). */
function bi_backuply_file( $name, $gz, $info = null ) {
	@mkdir( WP_CONTENT_DIR . '/backuply/backups-abc123', 0755, true );
	@mkdir( WP_CONTENT_DIR . '/backuply/backups_info-def456', 0755, true );
	file_put_contents( WP_CONTENT_DIR . '/backuply/backups-abc123/' . $name . '.tar.gz', $gz );
	file_put_contents( WP_CONTENT_DIR . '/backuply/backups_info-def456/' . $name . '.php', null === $info ? bi_info( $name ) : $info );
	return $name . '.tar.gz';
}
/** Deja un .tar.gz en el almacén (como una subida o uno traído de un destino). */
function bi_store_file( $file, $bytes ) {
	$dir = Digitalisimo_Backups_Backuply_Import::store_directory( true );
	file_put_contents( $dir . '/' . $file, $bytes );
	return $file;
}
/** Hace avanzar la cola hasta que el trabajo termina; con $one, un tramo por tick (y $between entre tramos). */
function bi_drive( $id, $one = false, $between = null ) {
	for ( $i = 0; $i < 5000; $i++ ) {
		Digitalisimo_Backups_Jobs::tick( $one ? 0.0 : PHP_INT_MAX );
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $job;
		if ( $between ) $between( $job );
	}
	throw new RuntimeException( 'El trabajo ' . $id . ' no terminó.' );
}
function bi_import( $name, $source = '', $one = false, $between = null ) {
	$queued = Digitalisimo_Backups::service_backuply_import( $name, array( 'source' => $source, 'via' => 'test' ) );
	bi_check( ! is_wp_error( $queued ), 'No se encoló la importación: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	bi_check( 'import' === $queued['kind'] && 'Importación de Backuply' === $queued['origin_label'], 'La vista del trabajo no es la de una importación: ' . json_encode( $queued ) );
	return bi_drive( $queued['id'], $one, $between );
}
/** Entradas del ZIP (nombre => SHA-256 del contenido), sin el manifiesto (lleva fechas e identificador propios). */
function bi_zip_map( $path ) {
	$zip = new ZipArchive();
	bi_check( true === $zip->open( $path ), 'No se abrió el ZIP convertido.' );
	$out = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( 'manifest.json' !== $name ) $out[ $name ] = hash( 'sha256', (string) $zip->getFromIndex( $i ) );
	}
	$zip->close();
	ksort( $out );
	return $out;
}
function bi_store_files() { return array_values( array_filter( scandir( bk_storage() ), function( $f ) { return preg_match( '/\.zip$/', $f ); } ) ); }
function bi_staging_left() {
	$root = Digitalisimo_Backups_Job_Store::root();
	$left = array();
	foreach ( (array) scandir( $root ) as $id ) if ( Digitalisimo_Backups_Job_Store::valid_id( $id ) && is_dir( $root . '/' . $id . '/staging' ) && array_diff( scandir( $root . '/' . $id . '/staging' ), array( '.', '..' ) ) ) $left[] = $id;
	return $left;
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
$name = 'wp_origen.com_2026-11-09_22-00-00';

bi_case( 'B1 detección de Backuply y lista de sus respaldos, sin rutas', function() use ( $name ) {
	$none = Digitalisimo_Backups::service_backuply();
	bi_check( ! is_wp_error( $none ) && false === $none['detected'] && array() === $none['backups'], 'Sin Backuply, la lista no está vacía: ' . json_encode( $none ) );
	@mkdir( WP_PLUGIN_DIR . '/backuply', 0755, true );
	file_put_contents( WP_PLUGIN_DIR . '/backuply/backuply.php', "<?php /* Plugin Name: Backuply */\n" );
	$GLOBALS['bk_options']['active_plugins'] = array( 'backuply/backuply.php' );
	bi_backuply_file( $name, bi_gz( bi_tar( bi_entries( $name ) ) ) );
	$list = Digitalisimo_Backups::service_backuply();
	bi_check( true === $list['detected'] && true === $list['installed'] && true === $list['active'], 'No se detectó Backuply: ' . json_encode( $list ) );
	bi_check( 1 === count( $list['backups'] ) && $name . '.tar.gz' === $list['backups'][0]['name'] && 'backuply' === $list['backups'][0]['source'] && true === $list['backups'][0]['database'] && true === $list['backups'][0]['files'] && 'https://origen.com' === $list['backups'][0]['site_url'] && '' === $list['backups'][0]['imported'], 'La lista no describe el respaldo: ' . json_encode( $list['backups'] ) );
	bi_check( false === strpos( json_encode( $list ), $GLOBALS['bk_root'] ) && false === strpos( json_encode( $list ), 'backups-abc123' ), 'La lista revela rutas del servidor.' );
	// Un nombre que no es de Backuply o que sale de su carpeta no se acepta.
	foreach ( array( '../wp-config.php', 'x.zip', 'a/b.tar.gz', '.oculto.tar.gz' ) as $bad ) bi_check( is_wp_error( Digitalisimo_Backups::service_backuply_import( $bad ) ), 'Se aceptó el nombre ' . $bad );
	$missing = Digitalisimo_Backups::service_backuply_import( 'no-existe.tar.gz' );
	bi_check( is_wp_error( $missing ) && 404 === $missing->get_error_data()['status'], 'Un respaldo que no existe no responde 404.' );
	$GLOBALS['bk_caps'] = array();
	bi_check( is_wp_error( Digitalisimo_Backups::service_backuply() ) && is_wp_error( Digitalisimo_Backups::service_backuply_import( $name . '.tar.gz' ) ), 'Sin capability se listó o importó.' );
	$GLOBALS['bk_caps']['manage_options'] = true;
} );

$converted = '';
bi_case( 'B2 importación por tramos a un ZIP de Backups sin firma', function() use ( $name, &$converted ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	try {
		$job = bi_import( $name . '.tar.gz', 'backuply', true );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	}
	bi_check( 'success' === $job['status'], 'La importación falló: ' . json_encode( $job['error'] ) );
	$phases = array();
	foreach ( Digitalisimo_Backups_Job_Store::events( $job['id'] ) as $event ) if ( 'phase' === ( $event['type'] ?? '' ) && ( $event['phase'] ?? '' ) === ( $event['next'] ?? '' ) ) $phases[ $event['phase'] ] = ( $phases[ $event['phase'] ] ?? 0 ) + 1;
	bi_check( ( $phases['scan'] ?? 0 ) >= 5 && ( $phases['database'] ?? 0 ) >= 5 && ( $phases['files'] ?? 0 ) >= 5, 'Las fases no avanzaron por tramos: ' . json_encode( $phases ) );
	$converted = $job['result']['import']['file'];
	bi_check( 'digitalisimo-backuply-' . $name . '.zip' === $converted && is_file( bk_storage() . '/' . $converted ), 'El ZIP convertido no está en el almacén: ' . $converted );
	bi_check( 'RESTAURAR SIN FIRMA' === $job['result']['import']['confirmation'] && 'bk_' === $job['result']['import']['prefix'] && 6 === $job['result']['import']['tables'], 'El resultado no describe la importación: ' . json_encode( $job['result'] ) );
	bi_check( array() === bi_staging_left(), 'Quedó el área de trabajo de la importación.' );
	$zip = new ZipArchive();
	bi_check( true === $zip->open( bk_storage() . '/' . $converted ), 'No se abre el ZIP convertido.' );
	bi_check( '' === (string) $zip->getArchiveComment(), 'El ZIP convertido lleva firma: un archivo de fuera nunca se firma.' );
	Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
	$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
	bi_check( 'digitalisimo-backup/v3' === $manifest['format'] && 'bk_' === $manifest['table_prefix'] && false === $manifest['multisite'] && 'https://origen.com' === $manifest['home_url'] && '/var/www/origen' === $manifest['origin']['abspath'] && 'backuply' === $manifest['imported_from']['format'] && '1.6.0' === $manifest['imported_from']['backuply_version'] && '6.8.1' === $manifest['wordpress_version'], 'Manifiesto inesperado: ' . json_encode( $manifest ) );
	bi_check( array( 'bk_hijos', 'bk_options', 'bk_posts', 'bk_postmeta', 'bk_users', 'bk_usermeta' ) === $manifest['database_tables'], 'Tablas del manifiesto (en el orden del volcado, sin la vista): ' . json_encode( $manifest['database_tables'] ) );
	$raw = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	bi_check( 4 === count( $raw['notices'] ?? array() ) && false !== strpos( implode( ' ', $raw['notices'] ), '1 vista' ) && false !== strpos( implode( ' ', $raw['notices'] ), '2 disparador' ) && false !== strpos( implode( ' ', $raw['notices'] ), 'claves foráneas' ) && false !== strpos( implode( ' ', $raw['notices'] ), 'enlace(s) simbólico' ), 'Avisos del manifiesto: ' . json_encode( $raw['notices'] ?? null ) );
	bi_check( false !== $zip->locateName( 'wordpress/' . $GLOBALS['bi_long'] ) && $GLOBALS['bi_foto'] === $zip->getFromName( 'wordpress/wp-content/uploads/2026/11/foto.jpg' ), 'Faltan archivos (nombre largo o binario) en el ZIP.' );
	foreach ( array( 'wordpress/softsql.sql', 'wordpress/softperms.txt', 'wordpress/softver.txt', 'wordpress/' . $name . '.php', 'wordpress/wp-content/backuply/backups-zz/viejo.tar.gz', 'wordpress/wp-content/enlace' ) as $absent ) bi_check( false === $zip->locateName( $absent ), 'El ZIP trae ' . $absent );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$zip->close();
	bi_check( 0 === strpos( $sql, "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n" ) && "SET foreign_key_checks = 1;\n" === substr( $sql, -28 ), 'El SQL no tiene la forma del exportador.' );
	bi_check( false !== strpos( $sql, "INSERT INTO `bk_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('3','blogname','Sitio de O\\'Reilly; con -- y /* y # dentro','yes');" ), 'La comilla duplicada de Backuply no pasó a la del exportador: ' . substr( $sql, strpos( $sql, 'blogname' ) - 80, 200 ) );
	bi_check( false !== strpos( $sql, "\\\\ barra, \\\\\\' barra y comilla, salto\\nde línea, \\r retorno, \\Z ctrl-Z y \\0 nulo" ), 'Barras, saltos, ^Z o NUL no pasaron bien al SQL convertido.' );
	bi_check( false !== strpos( $sql, "('1','1','_nulo',NULL)" ) && 3 === substr_count( $sql, 'INSERT INTO `bk_posts`' ), 'NULL o las filas del INSERT partido no se convirtieron.' );
	foreach ( array( 'digitalisimo_backups_vault_keyring', 'VIEW', 'TRIGGER', 'PROCEDURE', 'CONSTRAINT', 'DELIMITER', 'SQL_MODE', 'time_zone' ) as $absent ) bi_check( false === strpos( $sql, $absent ), 'El SQL convertido trae ' . $absent );
	bi_check( false === strpos( $sql, 'AUTO_INCREMENT=' ), 'El SQL convertido conserva el AUTO_INCREMENT de la tabla.' );
	$verify = Digitalisimo_Backups::service_verify( $converted );
	bi_check( ! is_wp_error( $verify ) && true === $verify['verified'] && 'untrusted' === $verify['trust'] && 'RESTAURAR SIN FIRMA' === $verify['confirmation'] && true === $verify['compatible'] && false === $verify['registered'], 'verify no da el ZIP por «sin firma» y restaurable: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $verify ) );
	$list = Digitalisimo_Backups::service_backuply();
	bi_check( $converted === $list['backups'][0]['imported'], 'La lista no dice que ya se importó.' );
} );

bi_case( 'B3 restaurar el ZIP importado en otro dominio, otra ruta y otro prefijo', function() use ( $dest, $abspath, $name, &$converted, $config_before ) {
	bi_check( '' !== $converted, 'No hay ZIP convertido (falló B2).' );
	$result = Digitalisimo_Backups::service_restore( $converted, array( 'confirm' => 'RESTAURAR' ) );
	bi_check( is_wp_error( $result ), 'Se restauró un archivo de fuera con RESTAURAR (sin «SIN FIRMA»).' );
	$result = Digitalisimo_Backups::service_restore( $converted, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	bi_check( ! is_wp_error( $result ), 'La restauración falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	$opt = function( $option ) use ( $dest ) { return $dest->value( 'wp_options', 'option_value', "option_name = '" . $option . "'" ); };
	bi_check( 'https://destino.test/sitio' === $opt( 'home' ) && 'https://destino.test/sitio' === $opt( 'siteurl' ), 'home/siteurl: ' . $opt( 'home' ) );
	bi_check( "Sitio de O'Reilly; con -- y /* y # dentro" === $opt( 'blogname' ), 'blogname: ' . $opt( 'blogname' ) );
	bi_check( 'no-debe-viajar' !== $opt( 'digitalisimo_backups_vault_keyring' ), 'Viajó una clave de Backups del sitio de origen.' );
	bi_check( null !== $opt( 'wp_user_roles' ) && null === $opt( 'bk_user_roles' ), 'Las claves con prefijo no pasaron a wp_.' );
	$widget = unserialize( $opt( 'widget_text' ), array( 'allowed_classes' => false ) );
	bi_check( is_array( $widget ) && 'Año ñandú €' === $widget[2]['title'] && false !== strpos( $widget[2]['text'], 'https://destino.test/sitio/contacto/' ), 'El serializado no se reubicó: ' . $opt( 'widget_text' ) );
	bi_check( $abspath . '/wp-content/uploads' === $opt( 'upload_path' ), 'La ruta del servidor no se reubicó: ' . $opt( 'upload_path' ) );
	$content = $dest->value( 'wp_posts', 'post_content', 'ID = 1' );
	bi_check( "Texto con 'comillas', \"dobles\", ; punto y coma, \\ barra, \\' barra y comilla, salto\nde línea, \r retorno, \x1a ctrl-Z y \0 nulo -- no es comentario /* tampoco */ # ni esto; <a href=\"https://destino.test/sitio/blog/\">blog</a>" === $content, 'El contenido no sobrevivió idéntico: ' . json_encode( $content ) );
	bi_check( str_repeat( 'Lorem ipsum ñ ', 4300 ) === $dest->value( 'wp_posts', 'post_content', 'ID = 2' ) && 'fila tras el INSERT partido' === $dest->value( 'wp_posts', 'post_content', 'ID = 3' ), 'Las filas del INSERT partido no están.' );
	bi_check( 'https://origen.com/?p=1' === $dest->value( 'wp_posts', 'guid', 'ID = 1' ), 'Se cambió la columna guid.' );
	bi_check( null === $dest->value( 'wp_postmeta', 'meta_value', "meta_key = '_nulo'" ) && '2026/11/foto.jpg' === $dest->value( 'wp_postmeta', 'meta_value', "meta_key = '_wp_attached_file'" ), 'postmeta (NULL) no quedó bien.' );
	bi_check( null !== $dest->value( 'wp_usermeta', 'meta_value', "meta_key = 'wp_capabilities'" ), 'wp_capabilities no se renombró.' );
	bi_check( ! in_array( 'bk_options', $dest->names(), true ) && ! in_array( 'bk_vista', $dest->names(), true ), 'Quedaron tablas con el prefijo del origen o la vista: ' . json_encode( $dest->names() ) );
	bi_check( $GLOBALS['bi_foto'] === file_get_contents( $abspath . '/wp-content/uploads/2026/11/foto.jpg' ) && "texto con nombre largo\n" === file_get_contents( $abspath . '/' . $GLOBALS['bi_long'] ) && is_file( $abspath . '/wp-content/plugins/hola/hola.php' ), 'Los archivos del respaldo no están en el sitio.' );
	bi_check( $config_before === file_get_contents( $abspath . '/wp-config.php' ), 'La restauración escribió wp-config.php.' );
	bi_check( is_file( WP_CONTENT_DIR . '/backuply/backups-abc123/' . $name . '.tar.gz' ) && ! file_exists( WP_CONTENT_DIR . '/backuply/backups-zz/viejo.tar.gz' ) && ! file_exists( $abspath . '/softsql.sql' ), 'La carpeta de Backuply del sitio cambió o se extrajo un metadato de Backuply.' );
	bi_check( is_array( $result['relocation'] ) && 0 === $result['relocation']['check']['urls'] && 0 === $result['relocation']['check']['paths'], 'Quedaron URL o rutas del origen: ' . json_encode( $result['relocation']['check'] ?? null ) );
} );

bi_case( 'B4 una muerte a mitad de cada tramo no cambia el ZIP convertido', function() use ( $name, &$converted ) {
	bi_check( '' !== $converted, 'No hay ZIP convertido (falló B2).' );
	$reference = bi_zip_map( bk_storage() . '/' . $converted );
	// Otra importación del mismo archivo, un tramo por tick; entre tramos, bytes de más en cada archivo del área de trabajo
	// (lo que escribiría un proceso que muere después de su punto de control).
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 2;
	$junk = 0;
	try {
		$job = bi_import( $name . '.tar.gz', 'backuply', true, function( $job ) use ( &$junk ) {
			foreach ( glob( Digitalisimo_Backups_Job_Store::staging( $job['id'] ) . '/*' ) as $file ) { file_put_contents( $file, random_bytes( 777 ), FILE_APPEND ); $junk++; }
		} );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	}
	bi_check( 'success' === $job['status'], 'La importación con muertes falló: ' . json_encode( $job['error'] ) );
	bi_check( $junk > 20, 'No se simularon suficientes muertes: ' . $junk );
	$again = $job['result']['import']['file'];
	bi_check( $again !== $converted && $reference === bi_zip_map( bk_storage() . '/' . $again ), 'El ZIP de la importación con muertes no es idéntico al de un tirón.' );
	$zip = new ZipArchive();
	$zip->open( bk_storage() . '/' . $again );
	Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
	$zip->close();
	@unlink( bk_storage() . '/' . $again );
} );

bi_case( 'B5 archivos hostiles o incompletos: fallan sin dejar nada', function() use ( $name, $abspath ) {
	$probe = $GLOBALS['bk_root'] . '-ejecutado.txt';
	$swap = function( array $entries, $from, $to ) { foreach ( $entries as &$e ) if ( $from === $e[0] ) $e[0] = $to; unset( $e ); return $entries; };
	$bad_sum = bi_header( 'wp-load.php', 5 );
	$bad_sum[0] = 'x';
	$cases = array(
		'ruta con ..' => array( bi_gz( bi_tar( array_merge( bi_entries( 'a' ), array( array( '../../evil.php', '<?php' ) ) ) ) ), 'ruta no permitida' ),
		'ruta absoluta' => array( bi_gz( bi_tar( array_merge( array( array( '/etc/evil', 'x' ) ), bi_entries( 'a' ) ) ) ), 'ruta no permitida' ),
		'barra invertida' => array( bi_gz( bi_tar( array_merge( bi_entries( 'a' ), array( array( 'wp-content\\x.php', 'x' ) ) ) ) ), 'ruta no permitida' ),
		'enlace duro' => array( bi_gz( bi_tar( array_merge( bi_entries( 'a' ), array( array( 'wp-content/duro', '', '1' ) ) ) ) ), 'enlace duro' ),
		'suma de la cabecera' => array( bi_gz( bi_tar( array_merge( bi_entries( 'a' ), array( array( 'raw' => $bad_sum . bi_pad( 'hola!' ) ) ) ) ) ), 'suma de comprobación' ),
		'gzip truncado' => array( substr( bi_gz( bi_tar( bi_entries( 'a' ) ), 1 ), 0, -3000 ), 'truncado' ),
		'tar sin final' => array( bi_gz( bi_tar( bi_entries( 'a' ), false ) ), 'marca de fin' ),
		'sólo archivos' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => false ) ) ) ), 'sólo archivos' ),
		'sólo base de datos' => array( bi_gz( bi_tar( array_values( array_filter( bi_entries( 'a' ), function( $e ) { return in_array( $e[0], array( 'softsql.sql', 'softver.txt', 'a.php' ), true ); } ) ) ) ), 'sólo base de datos' ),
		'GRANT' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => bi_dump( 'bk_', array( "GRANT ALL ON *.* TO 'x'@'%';\n" ) ) ) ) ) ), 'tipo de sentencia no admitido' ),
		'motor remoto' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => bi_dump( 'bk_', array( "CREATE TABLE `bk_remota` (\n `id` int\n) ENGINE=FEDERATED CONNECTION='mysql://u:p@evil/db/t';\n" ) ) ) ) ) ), 'no permitid' ),
		'función en INSERT' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => bi_dump( 'bk_', array( "INSERT INTO `bk_hijos` VALUES\n(9, 1, LOAD_FILE('/etc/passwd'));\n" ) ) ) ) ) ), 'cadenas, números o NULL' ),
		'otra base de datos' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => bi_dump( 'bk_', array( "INSERT INTO `mysql`.`user` VALUES\n('x');\n" ) ) ) ) ) ), 'otra base de datos' ),
		'ejecutable que no es SET' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => bi_dump( 'bk_', array( "/*!50000 DROP DATABASE wordpress */;\n" ) ) ) ) ) ), 'comentario ejecutable' ),
		'tabla que el volcado no creó' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => bi_dump( 'bk_', array( "INSERT INTO `wp_users` VALUES\n(1, 'x');\n" ) ) ) ) ) ), 'no creó' ),
		'cadena sin cerrar' => array( bi_gz( bi_tar( bi_entries( 'a', array( 'sql' => "SET time_zone = \"+00:00\";\nCREATE TABLE `bk_options` (\n `option_id` int\n);\nINSERT INTO `bk_options` VALUES\n('sin cerrar);\n" ) ) ) ), 'incompleta' ),
		'no es gzip' => array( "PK\x03\x04 esto es un ZIP", 'gzip' ),
	);
	$before = bk_tree();
	$store_before = bi_store_files();
	foreach ( $cases as $label => $case ) {
		$file = bi_store_file( 'hostil-' . substr( md5( $label ), 0, 8 ) . '.tar.gz', $case[0] );
		$job = bi_import( $file, 'store' );
		@unlink( Digitalisimo_Backups_Backuply_Import::store_directory() . '/' . $file );
		$matches = '/' === $case[1][0] ? preg_match( $case[1] . 'u', (string) $job['error']['message'] ) : false !== strpos( (string) $job['error']['message'], $case[1] );
		bi_check( 'failed' === $job['status'] && ! empty( $job['error']['permanent'] ) && $matches, $label . ': se esperaba un fallo con «' . $case[1] . '» y hubo: ' . $job['status'] . ' ' . json_encode( $job['error'] ) );
		@unlink( Digitalisimo_Backups_Backuply_Import::store_directory() . '/' . $file );
	}
	// El archivo de información de Backuply lleva código PHP: se lee como texto, nunca se ejecuta.
	$file = bi_store_file( 'con-codigo.tar.gz', bi_gz( bi_tar( bi_entries( 'con-codigo', array( 'info' => "<?php exit();?>\n<?php file_put_contents('" . $probe . "', 'x'); ?>" ) ) ) ) );
	$job = bi_import( $file, 'store' );
	bi_check( 'success' === $job['status'] && ! file_exists( $probe ), 'El archivo de información de Backuply se ejecutó o rompió la importación: ' . json_encode( $job['error'] ) );
	@unlink( bk_storage() . '/' . $job['result']['import']['file'] );
	bi_check( $store_before === bi_store_files(), 'Un fallo dejó un ZIP en el almacén: ' . json_encode( array_diff( bi_store_files(), $store_before ) ) );
	bi_check( array() === bi_staging_left(), 'Un fallo dejó el área de trabajo: ' . json_encode( bi_staging_left() ) );
	bi_check( $before === bk_tree(), 'Una importación tocó el sitio.' );
} );

bi_case( 'B6 subir un .tar.gz de Backuply por fragmentos e importarlo', function() use ( $name ) {
	$bytes = bi_gz( bi_tar( bi_entries( 'subido' ) ) );
	$start = Digitalisimo_Backups::service_upload_start( array( 'name' => 'Respaldo_Subido.tar.gz', 'size' => strlen( $bytes ) ) );
	bi_check( ! is_wp_error( $start ), 'No empezó la subida de un .tar.gz: ' . ( is_wp_error( $start ) ? $start->get_error_message() : '' ) );
	$chunk = (int) $start['chunk_size'];
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $chunk ) {
		$sent = Digitalisimo_Backups::service_upload_chunk( $start['id'], $offset, array( 'data' => substr( $bytes, $offset, $chunk ) ) );
		bi_check( ! is_wp_error( $sent ), 'Falló un fragmento: ' . ( is_wp_error( $sent ) ? $sent->get_error_message() : '' ) );
	}
	$done = Digitalisimo_Backups::service_upload_finish( $start['id'] );
	bi_check( ! is_wp_error( $done ) && 'backuply' === $done['container'] && 'Respaldo_Subido.tar.gz' === $done['file'], 'La subida no terminó como respaldo de Backuply: ' . json_encode( is_wp_error( $done ) ? $done->get_error_message() : $done ) );
	bi_check( is_file( Digitalisimo_Backups_Backuply_Import::store_directory() . '/Respaldo_Subido.tar.gz' ) && ! file_exists( bk_storage() . '/Respaldo_Subido.tar.gz' ), 'El .tar.gz no quedó en backuply/ del almacén.' );
	bi_check( $done === Digitalisimo_Backups::service_upload_finish( $start['id'] ), 'Repetir el cierre no devuelve lo mismo.' );
	$listed = array_values( array_filter( Digitalisimo_Backups::service_backuply()['backups'], function( $item ) { return 'store' === $item['source'] && 0 === strpos( $item['name'], 'Respaldo' ); } ) );
	bi_check( 1 === count( $listed ) && 'Respaldo_Subido.tar.gz' === $listed[0]['name'], 'El subido no aparece en la lista: ' . json_encode( $listed ) );
	$job = bi_import( 'Respaldo_Subido.tar.gz' );
	bi_check( 'success' === $job['status'] && 'digitalisimo-backuply-Respaldo_Subido.zip' === $job['result']['import']['file'], 'No se importó el subido: ' . json_encode( $job['error'] ?? $job['result'] ) );
	@unlink( bk_storage() . '/digitalisimo-backuply-Respaldo_Subido.zip' );
	$fake = 'no es gzip';
	$start = Digitalisimo_Backups::service_upload_start( array( 'name' => 'falso.tar.gz', 'size' => strlen( $fake ) ) );
	Digitalisimo_Backups::service_upload_chunk( $start['id'], 0, array( 'data' => $fake ) );
	$rejected = Digitalisimo_Backups::service_upload_finish( $start['id'] );
	bi_check( is_wp_error( $rejected ) && 'digitalisimo_backups_upload_invalid' === $rejected->get_error_code() && ! file_exists( Digitalisimo_Backups_Backuply_Import::store_directory() . '/falso.tar.gz' ), 'Un .tar.gz que no es gzip no se descartó.' );
} );

bi_case( 'B7 pantalla, duplicados, cancelación y REST', function() use ( $name ) {
	// La restauración de B3 dejó los plugins y las opciones del respaldo: Backuply vuelve a estar instalado y activo.
	@mkdir( WP_PLUGIN_DIR . '/backuply', 0755, true );
	file_put_contents( WP_PLUGIN_DIR . '/backuply/backuply.php', "<?php /* Plugin Name: Backuply */\n" );
	$GLOBALS['bk_options']['active_plugins'] = array( 'backuply/backuply.php' );
	ob_start();
	bk_call( 'Digitalisimo_Backups', 'backuply_panel', false );
	$html = ob_get_clean();
	bi_check( false !== strpos( $html, 'Respaldos de Backuply' ) && false !== strpos( $html, 'Backuply está instalado en este sitio y activo' ) && false !== strpos( $html, 'name="_wpnonce" value="nonce-digitalisimo_backups_backuply_import"' ) && false !== strpos( $html, 'value="' . $name . '.tar.gz"' ) && false !== strpos( $html, 'Importar de nuevo' ), 'La tarjeta no ofrece importar: ' . $html );
	bi_check( false === strpos( $html, $GLOBALS['bk_root'] ), 'La tarjeta revela rutas del servidor.' );
	$post = array( '_wpnonce' => 'nonce-digitalisimo_backups_backuply_import', 'backuply_file' => $name . '.tar.gz', 'backuply_source' => 'backuply' );
	list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'backuply_import_request' ), $post );
	bi_check( $ok && false !== strpos( $message, 'Importación de Backuply en cola' ), 'admin-post no encoló: ' . $message );
	$active = array_values( array_filter( Digitalisimo_Backups_Jobs::active(), array( 'Digitalisimo_Backups_Import_Job', 'is_import' ) ) );
	bi_check( 1 === count( $active ), 'No hay una importación en cola.' );
	$dup = Digitalisimo_Backups::service_backuply_import( $name . '.tar.gz' );
	bi_check( is_wp_error( $dup ) && 409 === $dup->get_error_data()['status'], 'Se encoló dos veces la misma importación.' );
	// Un tramo y se cancela: no queda nada.
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	Digitalisimo_Backups_Jobs::tick( 0.0 );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$cancel = Digitalisimo_Backups_Jobs::cancel( $active[0]['id'] );
	bi_check( ! empty( $cancel['ok'] ), 'No se pudo cancelar la importación: ' . json_encode( $cancel ) );
	$job = bi_drive( $active[0]['id'] );
	bi_check( 'cancelled' === $job['status'] && array() === bi_staging_left(), 'La cancelación dejó el área de trabajo o no terminó: ' . $job['status'] );
	try {
		bk_post( array( 'Digitalisimo_Backups', 'backuply_import_request' ), array( '_wpnonce' => 'otro' ) + $post );
		throw new RuntimeException( 'admin-post aceptó un nonce inválido.' );
	} catch ( Bk_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['bi_routes'] = array();
	Digitalisimo_Backups_Rest::routes();
	foreach ( array( '/backuply', '/backuply/import', '/backuply/fetch' ) as $route ) bi_check( isset( $GLOBALS['bi_routes'][ $route ] ), 'Falta la ruta REST ' . $route );
	$list = Digitalisimo_Backups_Rest::backuply( array() );
	bi_check( $list instanceof WP_REST_Response && 200 === $list->get_status() && $list->get_data()['detected'], 'GET /backuply no responde.' );
	$queued = Digitalisimo_Backups_Rest::backuply_import( array( 'name' => $name . '.tar.gz', 'source' => 'backuply' ) );
	bi_check( $queued instanceof WP_REST_Response && 202 === $queued->get_status() && 'import' === $queued->get_data()['kind'], 'POST /backuply/import no responde 202 con el trabajo.' );
	$job = bi_drive( $queued->get_data()['id'] );
	bi_check( 'success' === $job['status'], 'La importación por REST falló.' );
	$view = Digitalisimo_Backups::service_job( $job['id'] );
	bi_check( 'import' === $view['kind'] && $job['result']['import']['file'] === $view['file'] && false !== strpos( $view['detail'], 'RESTAURAR SIN FIRMA' ), 'La vista del trabajo terminado no dice qué hacer: ' . json_encode( $view ) );
	@unlink( bk_storage() . '/' . $job['result']['import']['file'] );
} );

bk_cleanup();
@unlink( $GLOBALS['bk_root'] . '-destino.sqlite' );
echo $failures ? "FALLA: {$failures} caso(s)\n" : "Importación de Backuply: todo correcto\n";
exit( $failures ? 1 : 0 );
