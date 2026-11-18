<?php
/**
 * Restauración real entre MySQL 8.0 y MariaDB 10.11 (servidores de verdad: los arranca tests/backups-db-compat-live.sh
 * y pasa sus sockets en DC_MYSQL_SOCK y DC_MARIA_SOCK). El respaldo y la restauración por pasos son los del motor real;
 * lo que decide si una intercalación, un motor o una opción existen es el servidor.
 *
 *   L1  MySQL 8 → MariaDB: una tabla de plugin con utf8mb4_0900_ai_ci (MariaDB no la tiene), columnas generadas y una
 *       fila con 0 en su AUTO_INCREMENT: se restaura con utf8mb4_unicode_520_ci, las columnas las calcula MariaDB y el 0
 *       se conserva (NO_AUTO_VALUE_ON_ZERO). Con el código anterior: «Unknown collation» después de la copia previa.
 *   L2  MySQL 8 → MariaDB con una vista y un disparador: el respaldo se hace (antes fallaba: una vista no tiene motor),
 *       avisa del disparador y la vista se crea al final del cambio sin DEFINER y con SQL SECURITY INVOKER.
 *   L3  MariaDB → MySQL 8: Aria con PAGE_CHECKSUM, TRANSACTIONAL y ROW_FORMAT=PAGE, utf8mb4_uca1400_ai_ci y una columna
 *       PERSISTENT: InnoDB, utf8mb4_unicode_520_ci y STORED. Con el código anterior: error de sintaxis de MySQL.
 *   L4  max_allowed_packet del servidor más chico que la fila más grande: rechazo en la verificación, sin copia previa.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-mysqli.php';

$mysql_sock = (string) getenv( 'DC_MYSQL_SOCK' );
$maria_sock = (string) getenv( 'DC_MARIA_SOCK' );
if ( '' === $mysql_sock || '' === $maria_sock ) { echo "OMITIDA: faltan DC_MYSQL_SOCK y DC_MARIA_SOCK (los pone tests/backups-db-compat-live.sh).\n"; exit( 0 ); }

$failures = 0;
function dl_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function dl_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
/** Una base de datos `wordpress_test` vacía en ese servidor, con un WordPress mínimo (opciones y entradas del núcleo). */
function dl_site( $socket, $blogname ) {
	$root = new Bk_Mysqli_DB( $socket, '' );
	$root->must( 'DROP DATABASE IF EXISTS `wordpress_test`' );
	$root->must( 'CREATE DATABASE `wordpress_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci' );
	$db = new Bk_Mysqli_DB( $socket, 'wordpress_test' );
	$db->must( "CREATE TABLE `wp_options` (`option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `option_name` varchar(191) NOT NULL DEFAULT '', `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes', PRIMARY KEY (`option_id`), UNIQUE KEY `option_name` (`option_name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci" );
	$db->must( "CREATE TABLE `wp_posts` (`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `post_content` longtext NOT NULL, PRIMARY KEY (`ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci" );
	foreach ( array( 'siteurl' => 'https://example.test', 'home' => 'https://example.test', 'blogname' => $blogname ) as $name => $value ) $db->must( $db->prepare( 'INSERT INTO `wp_options` (`option_name`, `option_value`) VALUES (%s, %s)', $name, $value ) );
	$db->must( "INSERT INTO `wp_posts` (`ID`, `post_content`) VALUES (1, 'Hola desde " . $db->dbh->real_escape_string( $blogname ) . " \u{1F600}')" );
	return $db;
}
/** Respaldo completo con el motor real de la base $db (registrado: el respaldo es de confianza, como uno propio). */
function dl_backup( $db ) {
	$GLOBALS['wpdb'] = $db;
	if ( class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) Digitalisimo_Backups_Db_Compat::server( true );
	return bk_backup( true );
}
/** Restauración por pasos de $file en la base $db, hasta terminar. Devuelve el trabajo (o un WP_Error si se rechazó al encolar). */
function dl_restore( $db, $file ) {
	$GLOBALS['wpdb'] = $db;
	if ( class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) Digitalisimo_Backups_Db_Compat::server( true );
	foreach ( Digitalisimo_Backups_Jobs::active() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	@unlink( ABSPATH . '.maintenance' );
	$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
	$GLOBALS['bk_filters']['digitalisimo_backups_job_max_attempts'] = 1;
	$result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	for ( $i = 0; $i < 3000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$last = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
	return is_wp_error( $result ) && ! $last ? $result : array( 'job' => $last, 'refused' => is_wp_error( $result ) ? $result->get_error_message() : '' );
}
function dl_job_text( $outcome ) {
	if ( is_wp_error( $outcome ) ) return $outcome->get_error_message();
	$job = (array) ( $outcome['job'] ?? array() );
	return trim( (string) ( $outcome['refused'] ?? '' ) . ' ' . (string) ( $job['status'] ?? '' ) . ' ' . (string) ( $job['phase'] ?? '' ) . ' ' . (string) ( $job['error']['message'] ?? '' ) );
}
function dl_succeeded( $outcome ) { return is_array( $outcome ) && 'success' === ( $outcome['job']['status'] ?? '' ); }

$mysql = new Bk_Mysqli_DB( $mysql_sock, '' );
$maria = new Bk_Mysqli_DB( $maria_sock, '' );
echo 'Servidores: MySQL ' . $mysql->db_server_info() . ' y MariaDB ' . $maria->db_server_info() . "\n";

dl_case( 'L1 MySQL 8 → MariaDB: utf8mb4_0900_ai_ci, columnas generadas y una fila con 0 en su AUTO_INCREMENT', function() use ( $mysql_sock, $maria_sock ) {
	$source = dl_site( $mysql_sock, 'Origen MySQL' );
	$source->must( "CREATE TABLE `wp_plugin_log` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `note` varchar(191) DEFAULT NULL, `qty` int NOT NULL DEFAULT 0, `total` int GENERATED ALWAYS AS ((`qty` * 2)) VIRTUAL, `stamp` int GENERATED ALWAYS AS ((`qty` + 1)) STORED, PRIMARY KEY (`id`), KEY `total` (`total`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci" );
	$source->must( "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO')" );
	$source->must( "INSERT INTO `wp_plugin_log` (`id`, `note`, `qty`) VALUES (0, 'registro por defecto', 5), (5, 'otro', 7)" );
	$backup = dl_backup( $source );
	$target = dl_site( $maria_sock, 'Destino MariaDB' );
	$outcome = dl_restore( $target, $backup );
	dl_check( dl_succeeded( $outcome ), 'La restauración no terminó bien: ' . dl_job_text( $outcome ) );
	$check = new Bk_Mysqli_DB( $maria_sock, 'wordpress_test' );
	dl_check( 'Origen MySQL' === $check->get_var( "SELECT option_value FROM wp_options WHERE option_name = 'blogname'" ), 'MariaDB no tiene los datos del respaldo.' );
	dl_check( 'utf8mb4_unicode_520_ci' === $check->get_var( "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'wordpress_test' AND TABLE_NAME = 'wp_plugin_log'" ), 'La tabla no quedó con utf8mb4_unicode_520_ci.' );
	dl_check( 'registro por defecto' === $check->get_var( 'SELECT note FROM wp_plugin_log WHERE id = 0' ) && '2' === (string) $check->get_var( 'SELECT COUNT(*) FROM wp_plugin_log' ), 'La fila con 0 en su AUTO_INCREMENT cambió de número: ' . json_encode( $check->get_results( 'SELECT id, note FROM wp_plugin_log', 'ARRAY_A' ) ) );
	dl_check( '14' === (string) $check->get_var( 'SELECT total FROM wp_plugin_log WHERE id = 5' ) && '8' === (string) $check->get_var( 'SELECT stamp FROM wp_plugin_log WHERE id = 5' ) && '10' === (string) $check->get_var( 'SELECT total FROM wp_plugin_log WHERE id = 0' ), 'Las columnas generadas no las calculó MariaDB.' );
	dl_check( false !== strpos( (string) $check->get_var( 'SELECT post_content FROM wp_posts WHERE ID = 1' ), "\u{1F600}" ), 'Se perdió el emoji.' );
	$notice = (string) ( $outcome['job']['result']['restore']['notice'] ?? '' );
	dl_check( false !== strpos( $notice, 'utf8mb4_0900_ai_ci → utf8mb4_unicode_520_ci (wp_plugin_log)' ), 'El informe no dice la sustitución: ' . $notice );
} );

dl_case( 'L2 MySQL 8 → MariaDB con una vista y un disparador: se respalda, se avisa y la vista se crea al final', function() use ( $mysql_sock, $maria_sock ) {
	$source = dl_site( $mysql_sock, 'Origen con vista' );
	$source->must( 'CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `wp_v` AS select `wp_posts`.`ID` AS `ID`, `wp_posts`.`post_content` AS `c` from `wp_posts`' );
	$source->must( "CREATE TABLE `wp_counter` (`id` int NOT NULL, `n` int NOT NULL DEFAULT 0, PRIMARY KEY (`id`)) ENGINE=InnoDB" );
	$source->must( 'CREATE TRIGGER `wp_count_posts` AFTER INSERT ON `wp_posts` FOR EACH ROW UPDATE `wp_counter` SET `n` = `n` + 1 WHERE `id` = 1' );
	$backup = dl_backup( $source );
	$zip = new ZipArchive();
	$zip->open( $backup );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	dl_check( array( 'wp_v' ) === ( $manifest['database_views'] ?? null ) && array( 'wp_count_posts' ) === ( $manifest['database_objects']['triggers'] ?? null ), 'El manifiesto no trae la vista o el disparador: ' . json_encode( array( $manifest['database_views'] ?? null, $manifest['database_objects'] ?? null ) ) );
	$target = dl_site( $maria_sock, 'Destino sin vista' );
	$outcome = dl_restore( $target, $backup );
	dl_check( dl_succeeded( $outcome ), 'La restauración no terminó bien: ' . dl_job_text( $outcome ) );
	$check = new Bk_Mysqli_DB( $maria_sock, 'wordpress_test' );
	$create = (string) ( $check->get_row( 'SHOW CREATE VIEW `wp_v`', 'ARRAY_N' )[1] ?? '' );
	dl_check( false !== strpos( $create, 'SQL SECURITY INVOKER' ) && '1' === (string) $check->get_var( 'SELECT COUNT(*) FROM `wp_v`' ), 'La vista no se creó o no funciona: ' . $create . ' ' . $check->last_error );
	$notice = (string) ( $outcome['job']['result']['restore']['notice'] ?? '' );
	dl_check( false !== strpos( $notice, 'wp_count_posts' ) && false !== strpos( $notice, 'Se crearon 1 vista(s)' ), 'El informe no avisa del disparador o de la vista: ' . $notice );
} );

dl_case( 'L3 MariaDB → MySQL 8: Aria con sus opciones, utf8mb4_uca1400_ai_ci y una columna PERSISTENT', function() use ( $mysql_sock, $maria_sock ) {
	$source = dl_site( $maria_sock, 'Origen MariaDB' );
	$source->must( 'CREATE TABLE `wp_stats` (`id` int(11) NOT NULL, `v` varchar(20) COLLATE utf8mb4_uca1400_ai_ci DEFAULT NULL, `p` int(11) AS (`id` * 3) PERSISTENT, PRIMARY KEY (`id`)) ENGINE=Aria DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci PAGE_CHECKSUM=1 TRANSACTIONAL=1 ROW_FORMAT=PAGE' );
	$source->must( "INSERT INTO `wp_stats` (`id`, `v`) VALUES (7, 'siete')" );
	$backup = dl_backup( $source );
	$target = dl_site( $mysql_sock, 'Destino MySQL' );
	$outcome = dl_restore( $target, $backup );
	dl_check( dl_succeeded( $outcome ), 'La restauración no terminó bien: ' . dl_job_text( $outcome ) );
	$check = new Bk_Mysqli_DB( $mysql_sock, 'wordpress_test' );
	$row = $check->get_row( "SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'wordpress_test' AND TABLE_NAME = 'wp_stats'", 'ARRAY_A' );
	dl_check( 'InnoDB' === ( $row['ENGINE'] ?? '' ) && 'utf8mb4_unicode_520_ci' === ( $row['TABLE_COLLATION'] ?? '' ), 'La tabla no quedó en InnoDB con utf8mb4_unicode_520_ci: ' . json_encode( $row ) );
	dl_check( '21' === (string) $check->get_var( 'SELECT p FROM wp_stats WHERE id = 7' ) && 'siete' === $check->get_var( 'SELECT v FROM wp_stats WHERE id = 7' ), 'Los datos o la columna generada no están en MySQL.' );
	dl_check( false !== stripos( (string) ( $check->get_row( 'SHOW CREATE TABLE `wp_stats`', 'ARRAY_N' )[1] ?? '' ), 'STORED' ), 'PERSISTENT no pasó a STORED.' );
} );

dl_case( 'L4 una fila mayor que max_allowed_packet: rechazo en la verificación, sin copia previa', function() use ( $mysql_sock, $maria_sock ) {
	if ( ! class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) throw new RuntimeException( 'El código anterior no compara max_allowed_packet antes de la copia previa.' );
	$source = dl_site( $mysql_sock, 'Origen grande' );
	$source->must( "INSERT INTO `wp_posts` (`ID`, `post_content`) VALUES (2, REPEAT('a', 3000000))" );
	$backup = dl_backup( $source );
	$target = dl_site( $maria_sock, 'Destino chico' );
	$root = new Bk_Mysqli_DB( $maria_sock, '' );
	$root->must( 'SET GLOBAL max_allowed_packet = 2097152' ); // Sólo la prueba lo cambia en su servidor propio.
	try {
		$target = new Bk_Mysqli_DB( $maria_sock, 'wordpress_test' );
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $old ) @unlink( $old );
		$outcome = dl_restore( $target, $backup );
		dl_check( ! dl_succeeded( $outcome ) && false !== strpos( dl_job_text( $outcome ), 'max_allowed_packet' ) && 'inspect' === ( $outcome['job']['phase'] ?? '' ), 'No se rechazó por max_allowed_packet en la verificación: ' . dl_job_text( $outcome ) );
		dl_check( ! glob( bk_storage() . '/safety-before-restore-*' ) && 'Destino chico' === $target->get_var( "SELECT option_value FROM wp_options WHERE option_name = 'blogname'" ), 'Antes del rechazo se hizo la copia previa o cambió el sitio.' );
	} finally {
		$root->must( 'SET GLOBAL max_allowed_packet = 16777216' );
	}
} );

bk_cleanup();
if ( $failures ) { echo 'FALLA: ' . $failures . " caso(s) de la restauración real entre MySQL y MariaDB.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: restauración real entre MySQL 8 y MariaDB 10.11 (intercalaciones, motores, opciones, columnas generadas, NO_AUTO_VALUE_ON_ZERO, vistas, disparadores y max_allowed_packet) en orden.\n";
