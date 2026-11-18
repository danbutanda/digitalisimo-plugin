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
 * Revisión de 1.0.80:
 *   L5  (H5) MariaDB → MySQL 8: Aria con ROW_FORMAT=FIXED pasa a InnoDB sin él (con innodb_strict_mode, el de serie,
 *       el CREATE TABLE fallaba después de la copia previa).
 *   L6  (H4) MySQL 8 → MariaDB: una clave única utf8mb4_0900_ai_ci con «abc» y «abc »: verify lo avisa, la importación
 *       choca de verdad con utf8mb4_unicode_520_ci (antes del cambio) y con la NO PAD que propone el aviso se restaura.
 *   L7  (H10) una restauración que falla tras crear sus vistas: la vuelta a la copia previa quita la que creó y repone
 *       la de antes con su definición de antes.
 *   L8  (H1) lo que escriben MySQL 8 y MariaDB con SHOW CREATE VIEW pasa el guardián sin firma; y una vista de un archivo
 *       sin firma que lee otra base de datos con un alias de ese nombre se rechaza en la verificación (antes se creaba y
 *       leía la otra base con los permisos del usuario de WordPress).
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
function dl_restore( $db, $file, $confirm = 'RESTAURAR' ) {
	$GLOBALS['wpdb'] = $db;
	if ( class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) Digitalisimo_Backups_Db_Compat::server( true );
	foreach ( Digitalisimo_Backups_Jobs::active() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	@unlink( ABSPATH . '.maintenance' );
	$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
	$GLOBALS['bk_filters']['digitalisimo_backups_job_max_attempts'] = 1;
	$result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => $confirm, 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
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

dl_case( 'L5 MariaDB → MySQL 8: Aria con ROW_FORMAT=FIXED pasa a InnoDB sin él (innodb_strict_mode)', function() use ( $mysql_sock, $maria_sock ) {
	$source = dl_site( $maria_sock, 'Origen Aria fija' );
	$source->must( "CREATE TABLE `wp_fixed` (`id` int(11) NOT NULL, `code` char(8) NOT NULL DEFAULT '', PRIMARY KEY (`id`)) ENGINE=Aria DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=FIXED" );
	$source->must( "INSERT INTO `wp_fixed` (`id`, `code`) VALUES (1, 'uno')" );
	dl_check( false !== stripos( (string) ( $source->get_row( 'SHOW CREATE TABLE `wp_fixed`', 'ARRAY_N' )[1] ?? '' ), 'ROW_FORMAT=FIXED' ), 'MariaDB no guardó ROW_FORMAT=FIXED.' );
	$backup = dl_backup( $source );
	$target = dl_site( $mysql_sock, 'Destino MySQL estricto' );
	dl_check( '1' === (string) $target->get_var( 'SELECT @@innodb_strict_mode' ), 'Este MySQL no tiene innodb_strict_mode (el de serie).' );
	$outcome = dl_restore( $target, $backup );
	dl_check( dl_succeeded( $outcome ), 'La restauración no terminó bien: ' . dl_job_text( $outcome ) );
	$check = new Bk_Mysqli_DB( $mysql_sock, 'wordpress_test' );
	$create = (string) ( $check->get_row( 'SHOW CREATE TABLE `wp_fixed`', 'ARRAY_N' )[1] ?? '' );
	dl_check( false !== strpos( $create, 'ENGINE=InnoDB' ) && false === stripos( $create, 'ROW_FORMAT' ), 'La tabla no quedó en InnoDB sin ROW_FORMAT=FIXED: ' . $create );
	dl_check( 'uno' === $check->get_var( 'SELECT code FROM wp_fixed WHERE id = 1' ), 'La fila no llegó: ' . json_encode( $check->get_results( 'SELECT * FROM wp_fixed', 'ARRAY_A' ) ) );
	dl_check( false !== strpos( (string) ( $outcome['job']['result']['restore']['notice'] ?? '' ), 'ROW_FORMAT=FIXED (wp_fixed)' ), 'El informe no dice que se quitó ROW_FORMAT=FIXED.' );
} );

dl_case( 'L6 MySQL 8 → MariaDB: «abc» y «abc » en una clave única utf8mb4_0900_ai_ci: avisado, choca de verdad y con una NO PAD se restaura', function() use ( $mysql_sock, $maria_sock ) {
	if ( ! class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) throw new RuntimeException( 'El código anterior no adapta la base de datos.' );
	$source = dl_site( $mysql_sock, 'Origen con espacios' );
	$source->must( 'CREATE TABLE `wp_slugs` (`id` int NOT NULL AUTO_INCREMENT, `slug` varchar(100) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci' );
	$source->must( "INSERT INTO `wp_slugs` (`id`, `slug`) VALUES (1, 'abc'), (2, 'abc ')" );
	$backup = dl_backup( $source );
	$target = dl_site( $maria_sock, 'Destino PAD' );
	$GLOBALS['wpdb'] = $target;
	Digitalisimo_Backups_Db_Compat::server( true );
	$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
	$verify = Digitalisimo_Backups::service_verify( basename( $backup ) );
	dl_check( ! is_wp_error( $verify ) && preg_grep( '/La tabla wp_slugs tiene 1 fila\(s\) en las que la clave única slug \(slug\) termina en espacio.*utf8mb4_unicode_520_nopad_ci/', (array) $verify['database']['warnings'] ), 'verify no avisa de la clave única que choca: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : ( $verify['database'] ?? null ) ) );
	// Sin hacer nada, lo que avisa pasa de verdad: «Duplicate entry» al importar, antes del cambio (el sitio sigue igual).
	$outcome = dl_restore( $target, $backup );
	dl_check( ! dl_succeeded( $outcome ) && false !== stripos( dl_job_text( $outcome ), 'Duplicate entry' ) && 'Destino PAD' === $target->get_var( "SELECT option_value FROM wp_options WHERE option_name = 'blogname'" ), 'MariaDB no chocó con «abc» y «abc » o el sitio cambió: ' . dl_job_text( $outcome ) );
	// Con el remedio que propone el aviso (una NO PAD que MariaDB tiene), se restaura con las dos filas.
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] = function( $chosen, $from ) { return 'utf8mb4_0900_ai_ci' === $from ? 'utf8mb4_unicode_520_nopad_ci' : $chosen; };
	try {
		$outcome = dl_restore( $target, $backup );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] );
	}
	dl_check( dl_succeeded( $outcome ), 'Con una intercalación NO PAD no se restauró: ' . dl_job_text( $outcome ) );
	$check = new Bk_Mysqli_DB( $maria_sock, 'wordpress_test' );
	dl_check( '2' === (string) $check->get_var( 'SELECT COUNT(*) FROM wp_slugs' ) && 'utf8mb4_unicode_520_nopad_ci' === $check->get_var( "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'wordpress_test' AND TABLE_NAME = 'wp_slugs'" ), 'No quedaron las dos filas con la intercalación NO PAD.' );
} );

/** Un `$wpdb` real cuya primera sentencia con este texto falla, ya creadas las vistas de una restauración (un fallo tras el cambio). */
class Dl_Fail_After_Views_DB extends Bk_Mysqli_DB {
	public $fail_finish = '';
	public function query( $sql ) {
		if ( '' !== $this->fail_finish && false !== strpos( (string) $sql, $this->fail_finish ) && preg_grep( '/^CREATE OR REPLACE .*VIEW `/', $this->queries ) ) {
			$this->fail_finish = '';
			$this->queries[] = $sql;
			$this->last_error = 'Fallo simulado tras crear las vistas';
			return false;
		}
		return parent::query( $sql );
	}
}
dl_case( 'L7 un fallo tras crear las vistas: la vuelta a la copia previa quita la que creó y repone la de antes', function() use ( $mysql_sock, $maria_sock ) {
	if ( ! class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) throw new RuntimeException( 'El código anterior no crea vistas al restaurar.' );
	$source = dl_site( $mysql_sock, 'Origen con vistas' );
	$source->must( 'CREATE VIEW `wp_nueva` AS select `wp_posts`.`ID` AS `ID` from `wp_posts`' );
	$source->must( 'CREATE VIEW `wp_antes` AS select `wp_posts`.`ID` AS `del_respaldo` from `wp_posts`' );
	$backup = dl_backup( $source );
	dl_site( $maria_sock, 'Destino con su vista' )->must( 'CREATE VIEW `wp_antes` AS select `wp_options`.`option_name` AS `de_antes` from `wp_options`' );
	$target = new Dl_Fail_After_Views_DB( $maria_sock, 'wordpress_test' );
	// Otro dominio aquí: la reubicación termina fijando home en wp_options, y esa sentencia falla (tras las vistas).
	$home = $GLOBALS['bk_options']['home'] ?? null;
	$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = 'https://destino.test';
	$target->fail_finish = 'UPDATE `wp_options` SET option_value';
	try {
		$outcome = dl_restore( $target, $backup );
	} finally {
		if ( null === $home ) unset( $GLOBALS['bk_options']['home'], $GLOBALS['bk_options']['siteurl'] ); else $GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = $home;
	}
	$job = (array) ( $outcome['job'] ?? array() );
	dl_check( ! empty( $job['spec']['rollback'] ) && 'success' === ( $job['status'] ?? '' ) && preg_grep( '/^CREATE OR REPLACE .*VIEW `wp_nueva`/', $target->queries ), 'No hubo una restauración con vistas que fallara y volviera a la copia previa: ' . dl_job_text( $outcome ) );
	$check = new Bk_Mysqli_DB( $maria_sock, 'wordpress_test' );
	$views = array_map( 'strval', $check->get_col( "SHOW FULL TABLES WHERE Table_type = 'VIEW'" ) );
	$create = (string) ( $check->get_row( 'SHOW CREATE VIEW `wp_antes`', 'ARRAY_N' )[1] ?? '' );
	dl_check( array( 'wp_antes' ) === $views && false !== strpos( $create, '`de_antes`' ), 'Tras la vuelta, las vistas no son las de antes: ' . json_encode( array( $views, $create ) ) );
	dl_check( false !== strpos( (string) ( $job['result']['restore']['notice'] ?? '' ), '(wp_nueva)' ), 'El informe de la vuelta no dice que quitó la vista: ' . (string) ( $job['result']['restore']['notice'] ?? '' ) );
} );

dl_case( 'L8 vistas sin firma: lo que escriben MySQL 8 y MariaDB pasa el guardián, y otra base de datos con un alias se rechaza', function() use ( $mysql_sock, $maria_sock ) {
	if ( ! class_exists( 'Digitalisimo_Backups_Db_Compat' ) ) throw new RuntimeException( 'El código anterior no restaura vistas.' );
	$views = array(
		'wp_r1' => 'select p.ID, count(m.meta_id) as n from wp_posts p left join wp_postmeta m on m.post_id = p.ID group by p.ID',
		'wp_r2' => 'select ID, (select count(*) from wp_postmeta x where x.post_id = wp_posts.ID) as n from wp_posts where ID in (select post_id from wp_postmeta) and exists (select 1 from wp_postmeta y where y.post_id = wp_posts.ID)',
		'wp_r3' => 'select d.ID from (select ID from wp_posts) d',
		'wp_r4' => 'select ID from wp_posts union all select post_id from wp_postmeta',
		'wp_r5' => "select extract(year from now()) as y, trim(both ' ' from p.post_content) as t, cast(p.ID as char) as c, convert(p.post_content using utf8mb4) as u, group_concat(distinct m.meta_key order by m.meta_key separator ',') as k from wp_posts p join wp_postmeta m on m.post_id = p.ID group by p.ID, p.post_content",
		'wp_r6' => 'select p.ID, row_number() over (partition by p.post_content order by p.ID) as r from wp_posts p',
		'wp_r7' => 'select p.ID from wp_posts p, wp_postmeta m where m.post_id = p.ID',
		'wp_r8' => "select case when p.ID > 1 then 'grande' else 'chico' end as tam, coalesce(p.post_content, '') as c, if(p.ID = 1, 'uno', 'otro') as u from wp_posts p straight_join wp_postmeta m on m.post_id = p.ID",
	);
	$tables = array( 'wp_options', 'wp_posts', 'wp_postmeta' );
	foreach ( array( 'MySQL' => $mysql_sock, 'MariaDB' => $maria_sock ) as $server => $socket ) {
		$db = dl_site( $socket, 'Vistas de ' . $server );
		$db->must( 'CREATE TABLE `wp_postmeta` (`meta_id` bigint unsigned NOT NULL AUTO_INCREMENT, `post_id` bigint unsigned NOT NULL DEFAULT 0, `meta_key` varchar(255) DEFAULT NULL, PRIMARY KEY (`meta_id`))' );
		foreach ( $views as $name => $select ) $db->must( 'CREATE VIEW `' . $name . '` AS ' . $select );
		$GLOBALS['wpdb'] = $db;
		foreach ( array_keys( $views ) as $name ) {
			$sql = Digitalisimo_Backups_Db_Compat::view_definition( $name );
			foreach ( array( 'defer', 'rewrite' ) as $mode ) {
				try {
					$guard = ( new Digitalisimo_Backups_Sql_Guard( 'exporter', false, $tables ) )->views( $mode, array_keys( $views ) );
					$guard->check( (string) $sql );
				} catch ( Exception $e ) {
					throw new RuntimeException( $server . ': el guardián sin firma rechazó lo que escribe SHOW CREATE VIEW (' . $mode . '): ' . $e->getMessage() );
				}
			}
			dl_check( false !== $db->query( $guard->rewritten() ), $server . ': la vista reescrita no se pudo crear: ' . $guard->rewritten() . ' — ' . $db->last_error );
		}
	}
	// El ataque de H1: en el mismo servidor, otra base de datos con un secreto, y una vista del sitio que la lee con un
	// alias que se llama como esa base. En un archivo sin firma, ni el alias ni el manifiesto le dan permiso.
	$root = new Bk_Mysqli_DB( $mysql_sock, '' );
	$root->must( 'DROP DATABASE IF EXISTS `otra_base`' );
	$root->must( 'CREATE DATABASE `otra_base`' );
	$root->must( 'CREATE TABLE `otra_base`.`tabla` (`secreto` varchar(40) NOT NULL)' );
	$root->must( "INSERT INTO `otra_base`.`tabla` (`secreto`) VALUES ('clave de otro sitio')" );
	try {
		$source = dl_site( $mysql_sock, 'Origen con vista de otra base' );
		$source->must( 'CREATE VIEW `wp_mala` AS select `t`.`secreto` AS `s` from (`wp_posts` `otra_base` join `otra_base`.`tabla` `t`)' );
		$file = dl_backup( $source );
		// Sin firma y sin registro: como un ZIP que llega de fuera.
		bk_call( 'Digitalisimo_Backups', 'remove_history', basename( $file ) );
		$zip = new ZipArchive();
		$zip->open( $file );
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		unset( $manifest['auth'] );
		$zip->addFromString( 'manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->setArchiveComment( '' );
		$zip->close();
		$target = dl_site( $mysql_sock, 'Destino sin la vista' );
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $old ) @unlink( $old );
		$outcome = dl_restore( $target, $file, 'RESTAURAR SIN FIRMA' );
		$check = new Bk_Mysqli_DB( $mysql_sock, 'wordpress_test' );
		$created = array_map( 'strval', $check->get_col( "SHOW FULL TABLES WHERE Table_type = 'VIEW'" ) );
		dl_check( ! in_array( 'wp_mala', $created, true ), 'La vista del archivo sin firma que lee otra base de datos se creó: ' . json_encode( $check->get_col( 'SELECT `s` FROM `wp_mala`' ) ) );
		dl_check( ! dl_succeeded( $outcome ) && false !== strpos( dl_job_text( $outcome ), 'otra base de datos' ) && 'inspect' === ( $outcome['job']['phase'] ?? 'inspect' ) && ! glob( bk_storage() . '/safety-before-restore-*' ), 'El archivo sin firma no se rechazó en la verificación: ' . dl_job_text( $outcome ) );
	} finally {
		$root->must( 'DROP DATABASE IF EXISTS `otra_base`' );
	}
} );

bk_cleanup();
if ( $failures ) { echo 'FALLA: ' . $failures . " caso(s) de la restauración real entre MySQL y MariaDB.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: restauración real entre MySQL 8 y MariaDB 10.11 (intercalaciones, motores, opciones, columnas generadas, NO_AUTO_VALUE_ON_ZERO, vistas, disparadores, max_allowed_packet, ROW_FORMAT, claves NO PAD, la vuelta a la copia previa y vistas sin firma) en orden.\n";
