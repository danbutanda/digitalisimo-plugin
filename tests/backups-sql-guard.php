<?php
/**
 * Guardián SQL: acepta todo lo que escribió cualquier versión del exportador, rechaza lo que sale de la base de datos
 * o del sitio, y divide el SQL exactamente como lo hizo siempre la importación (comparación con el divisor anterior).
 */
require __DIR__ . '/lib/backups-env.php';

$guard = function( $trusted, $tables = array( 'wp_options', 'wp_posts' ) ) { return new Digitalisimo_Backups_Sql_Guard( 'exporter', $trusted, $tables ); };
$dump = function( $prefix = 'wp_' ) { return new Digitalisimo_Backups_Sql_Guard( 'mysqldump', false, null, $prefix ); };
$scan_text = function( $g, $sql, $chunk = 7 ) {
	$offset = 0;
	return $g->scan( function() use ( $sql, &$offset, $chunk ) { if ( $offset >= strlen( $sql ) ) return null; $part = substr( $sql, $offset, $chunk ); $offset += $chunk; return $part; } );
};

// 1. Lo que escribe el exportador actual (filas con comillas, «;», barras, saltos y nulos) y el heredado con cabecera «--».
$GLOBALS['bk_options']['digitalisimo_backups_history'] = array( array( 'file' => "a'b;c" ) );
$file = sys_get_temp_dir() . '/bk-guard-' . bin2hex( random_bytes( 4 ) ) . '.sql';
bk_call( 'Digitalisimo_Backups', 'database_sql_to_file', array( 'wp_options', 'wp_posts' ), $file );
$current = file_get_contents( $file );
unlink( $file );
$legacy = bk_call( 'Digitalisimo_Backups', 'database_sql', array( 'wp_options', 'wp_posts' ) );
if ( 0 !== strpos( $legacy, '-- Digitalisimo WordPress backup' ) ) throw new RuntimeException( 'El exportador heredado no trae su cabecera.' );
foreach ( array( 'actual' => $current, 'heredado' => $legacy ) as $label => $sql ) {
	foreach ( array( true, false ) as $trusted ) {
		$executed = $scan_text( $guard( $trusted ), $sql, 5 );
		if ( $executed < 8 ) throw new RuntimeException( 'El SQL ' . $label . ' no pasó completo el dialecto del exportador.' );
	}
}
// Particiones y opciones propias del servidor: confiable siempre; no confiable sólo las que no salen de la base de datos.
$partitioned = "CREATE TABLE `wp_posts` (\n `ID` bigint NOT NULL,\n `kind` varchar(20) DEFAULT 'a;b' COMMENT 'DATA DIRECTORY en un comentario',\n PRIMARY KEY (`ID`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n/*!50100 PARTITION BY HASH (`ID`)\nPARTITIONS 4 */";
$guard( true )->check( $partitioned );
$guard( false )->check( $partitioned );
$guard( false )->check( "CREATE TABLE `wp_posts` (`a` int /*!80023 INVISIBLE */, `b` int GENERATED ALWAYS AS ((`a` + 1)) VIRTUAL, CONSTRAINT `c` CHECK ((`a` like 'x%'))) ENGINE=InnoDB" );
$guard( true )->check( "CREATE TABLE `wp_posts` (`a` int) ENGINE=FEDERATED CONNECTION='mysql://u@h/db/t'" );
$guard( true )->check( 'CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `wp_view` AS select 1 AS `a`' );

// 2. Rechazos en un archivo no confiable.
$rejected = array(
	'GRANT ALL ON *.* TO \'a\'@\'%\'' => 'GRANT',
	"CREATE USER 'a'@'%' IDENTIFIED BY 'b'" => 'sólo se admite CREATE TABLE',
	"LOAD DATA INFILE '/etc/passwd' INTO TABLE `wp_posts`" => 'LOAD',
	"SELECT * FROM `wp_posts` INTO OUTFILE '/var/www/x.php'" => 'SELECT',
	'INSERT INTO `wp_posts` (`ID`) SELECT 1' => 'valores literales',
	"INSERT INTO `wp_posts` (`ID`,`post_content`) VALUES ('1',LOAD_FILE('/etc/passwd'))" => 'valores literales',
	"INSERT INTO `wp_posts` (`ID`) VALUES (1)" => 'valores literales',
	"INSERT INTO `wp_posts` VALUES ('1')" => 'lista de columnas',
	"INSERT INTO `wp_posts` (`ID`) VALUES ('1') ON DUPLICATE KEY UPDATE `ID`='2'" => 'texto de más',
	"INSERT INTO `wp_posts` (`ID`) VALUES (/*!50000 '1' */)" => 'valores literales',
	"/*!50000 DROP DATABASE wordpress */" => 'comentario ejecutable',
	"CREATE TABLE `wp_posts` (`a` int) DATA DIRECTORY='/var/www'" => 'DIRECTORY',
	"CREATE TABLE `wp_posts` (`a` int) INDEX DIRECTORY='/var/www'" => 'DIRECTORY',
	"CREATE TABLE `wp_posts` (`a` int) ENGINE=FEDERATED CONNECTION='mysql://u@h/db/t'" => 'CONNECTION',
	"CREATE TABLE `wp_posts` (`a` int) ENGINE = SPIDER" => 'SPIDER',
	"CREATE TABLE `wp_posts` (`a` int) ENGINE=MRG_MYISAM UNION=(`wp_options`)" => 'MRG_MYISAM',
	"CREATE TABLE `wp_posts` LIKE `wp_options`" => 'LIKE o SELECT',
	"CREATE TABLE `wp_posts` (LIKE `wp_options`)" => 'LIKE o SELECT',
	"CREATE TABLE `wp_posts` SELECT * FROM `wp_options`" => 'LIKE o SELECT',
	"CREATE TABLE `wp_posts` (`a` int) AS SELECT 1" => 'SELECT',
	"CREATE TABLE `wp_posts` (`a` text DEFAULT (LOAD_FILE('/etc/passwd')))" => 'LOAD_FILE',
	"CREATE TABLE `wp_posts` (`a` int) /*!50100 TABLESPACE `x` */" => 'comentario ejecutable',
	"CREATE TEMPORARY TABLE `wp_posts` (`a` int)" => 'sólo se admite CREATE TABLE',
	"DROP TABLE IF EXISTS `mysql`.`user`" => 'otra base de datos',
	"DROP TABLE IF EXISTS `wp_users`" => 'no figura en el manifiesto',
	"DROP TABLE `wp_posts`" => 'se esperaba IF',
	"DROP DATABASE wordpress" => 'se esperaba TABLE',
	"INSERT INTO `wp_po\\`sts` (`ID`) VALUES ('1')" => 'barra invertida',
	"SET GLOBAL general_log = 1" => 'variable no permitida',
	"SET sql_mode = 'NO_BACKSLASH_ESCAPES'" => 'variable no permitida',
	"SET NAMES gbk" => 'juego de caracteres',
	"SET @a = 1" => 'variable no permitida',
	"USE mysql" => 'USE',
	"DELIMITER ;;" => 'DELIMITER',
	"CALL x()" => 'CALL',
	"INSERT INTO `wp_posts` (`ID`) VALUES ('1'" => 'se esperaba',
	"INSERT INTO `wp_posts` (`ID`) VALUES ('1)" => 'cadena sin cerrar',
	"INSERT /*+ SET_VAR(sql_mode='') */ INTO `wp_posts` (`ID`) VALUES ('1')" => 'optimizador',
	'INSERT INTO `wp_posts` (`ID`) VALUES ("1")' => 'valores literales',
);
foreach ( $rejected as $sql => $needle ) bk_expect_failure( 'no confiable: ' . substr( $sql, 0, 40 ), function() use ( $guard, $sql ) { $guard( false )->check( $sql ); }, $needle );
// El mensaje nombra la sentencia y su tipo y nunca muestra valores de filas.
$message = bk_expect_failure( 'sin valores', function() use ( $guard ) { $g = $guard( false, array( 'wp_posts' ) ); $g->check( "SET NAMES utf8mb4" ); $g->check( "INSERT INTO `wp_secret` (`a`) VALUES ('contraseña-muy-secreta')" ); }, 'sentencia 2, INSERT' );
if ( false !== strpos( $message, 'contraseña' ) || false === strpos( $message, 'INSERT INTO' ) ) throw new RuntimeException( 'El error de una sentencia INSERT muestra sus valores: ' . $message );
// Una sentencia que sólo trae comentarios no se ejecuta; una que empieza por «--» ni siquiera llega al guardián.
if ( false !== $guard( false )->check( '/* nada */' ) ) throw new RuntimeException( 'Una sentencia vacía se habría ejecutado.' );
bk_expect_failure( 'vacío', function() use ( $guard, $scan_text ) { $scan_text( $guard( false ), "-- sólo un comentario;\n-- otro;" ); }, 'está vacía' );
bk_expect_failure( 'cadena abierta', function() use ( $guard, $scan_text ) { $scan_text( $guard( false ), "INSERT INTO `wp_posts` (`ID`) VALUES ('1);" ); }, 'cadena SQL incompleta' );

// 3. Dialecto mysqldump: lo que escriben UpdraftPlus y mysqldump, siempre como no confiable.
$updraft = "# WordPress MySQL database backup\n# Created by UpdraftPlus version 1.24 (https://example.test)\n# Backup of: https://example.test\n# Table prefix: wp_\n\n/*!40101 SET NAMES utf8mb4 */;\n\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n# --------------------------------------------------------\n# Table: `wp_options`\n# --------------------------------------------------------\n\n# Delete any existing table `wp_options`\n\nDROP TABLE IF EXISTS `wp_options`;\n\n# Table structure of table `wp_options`\n\nCREATE TABLE `wp_options` (\n  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `option_name` varchar(191) NOT NULL DEFAULT '',\n  PRIMARY KEY (`option_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n# Data contents of table `wp_options`\nINSERT INTO `wp_options` ( `option_id`, `option_name` ) VALUES (1, 'siteurl'), (2, 'it''s \\'escaped\\''), (-3, 0x6162), (4, _binary 'x'), (5, NULL), (6, 1.5e3), (7, b'01');\n\n# End of data contents of table `wp_options`\nSET FOREIGN_KEY_CHECKS = 1;\n\n# End of WordPress MySQL database backup\n";
$executed = $scan_text( $dump(), $updraft, 11 );
if ( 7 !== $executed ) throw new RuntimeException( 'El volcado de UpdraftPlus no pasó el dialecto mysqldump (' . $executed . ' sentencias).' );
$mysqldump = "/*M!999999\\- enable the sandbox mode */ \n-- MySQL dump 10.13\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET NAMES utf8mb4 */;\n/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\nDROP TABLE IF EXISTS `wp_posts`;\n/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!50503 SET character_set_client = utf8mb4 */;\nCREATE TABLE `wp_posts` (`ID` bigint NOT NULL, PRIMARY KEY (`ID`)) ENGINE=InnoDB;\n/*!40101 SET character_set_client = @saved_cs_client */;\nLOCK TABLES `wp_posts` WRITE;\n/*!40000 ALTER TABLE `wp_posts` DISABLE KEYS */;\nINSERT INTO `wp_posts` VALUES (1),(2);\n/*!40000 ALTER TABLE `wp_posts` ENABLE KEYS */;\nUNLOCK TABLES;\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n";
$executed = $scan_text( $dump(), $mysqldump, 13 );
if ( $executed < 18 ) throw new RuntimeException( 'El volcado de mysqldump no pasó el dialecto (' . $executed . ').' );
$dump_rejected = array(
	"/*!50001 CREATE ALGORITHM=UNDEFINED VIEW `wp_v` AS select 1 */" => 'comentario ejecutable',
	"/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `t` BEFORE INSERT ON `wp_posts` FOR EACH ROW SET @a = 1 */" => 'comentario ejecutable',
	"CREATE PROCEDURE `p`() SELECT 1" => 'sólo se admite CREATE TABLE',
	"CREATE FUNCTION f() RETURNS int RETURN 1" => 'sólo se admite CREATE TABLE',
	"CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1" => 'sólo se admite CREATE TABLE',
	"SET SQL_MODE='ANSI_QUOTES'" => 'modo SQL',
	"SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES'" => 'modo SQL',
	"SET @@SESSION.sql_mode = 'NO_BACKSLASH_ESCAPES,ANSI'" => 'modo SQL',
	"SET @@GLOBAL.sql_mode = ''" => 'variable no permitida',
	"SET GLOBAL sql_mode = ''" => 'variable no permitida',
	"SET PASSWORD = 'x'" => 'variable no permitida',
	"SET NAMES sjis" => 'juego de caracteres',
	"SET character_set_client = gbk" => 'juego de caracteres',
	"SELECT 1 INTO DUMPFILE '/tmp/x'" => 'SELECT',
	"DROP TABLE IF EXISTS `other_posts`" => 'prefijo',
	"LOCK TABLES `wp_posts` WRITE, `mysql`.`user` WRITE" => 'otra base de datos',
	"ALTER TABLE `wp_posts` ADD COLUMN `x` int" => 'activar o desactivar índices',
	"INSERT INTO `wp_posts` VALUES (now())" => 'valores literales',
	"INSERT INTO `wp_posts` VALUES (@a)" => 'valores literales',
	"GRANT SELECT ON *.* TO 'a'" => 'GRANT',
);
foreach ( $dump_rejected as $sql => $needle ) bk_expect_failure( 'mysqldump: ' . substr( $sql, 0, 40 ), function() use ( $dump, $sql ) { $dump()->check( $sql ); }, $needle );
// Una variable de usuario cargada con un texto no puede cambiar después el modo SQL ni el juego de caracteres.
bk_expect_failure( 'variable con texto', function() use ( $dump ) { $g = $dump(); $g->check( "SET @m = 'NO_BACKSLASH_ESCAPES'" ); $g->check( 'SET SQL_MODE = @m' ); }, 'valor no permitido para SQL_MODE' );
bk_expect_failure( 'variable sin asignar', function() use ( $dump ) { $dump()->check( 'SET character_set_client = @cs' ); }, 'valor no permitido para CHARACTER_SET_CLIENT' );

// 4. El análisis falla antes de la importación y la importación también pasa por el guardián.
$zip_path = sys_get_temp_dir() . '/bk-guard-' . bin2hex( random_bytes( 4 ) ) . '.zip';
$zip = new ZipArchive();
$zip->open( $zip_path, ZipArchive::CREATE );
$zip->addFromString( 'database.sql', "SET NAMES utf8mb4;\nINSERT INTO `wp_posts` (`ID`) VALUES ('1');\nINSERT INTO `wp_users` (`ID`) VALUES ('1');\n" );
$zip->close();
$zip->open( $zip_path );
$GLOBALS['wpdb']->queries = array();
bk_expect_failure( 'análisis', function() use ( $zip, $guard ) { $guard( false, array( 'wp_posts' ) )->scan_zip( $zip ); }, 'sentencia 3' );
if ( $GLOBALS['wpdb']->queries ) throw new RuntimeException( 'El análisis en seco ejecutó consultas.' );
bk_expect_failure( 'importación', function() use ( $zip, $guard ) { bk_call( 'Digitalisimo_Backups', 'import_zip_sql', $zip, $guard( false, array( 'wp_posts' ) ) ); }, 'sentencia 3' );
if ( 2 !== count( $GLOBALS['wpdb']->queries ) ) throw new RuntimeException( 'La importación no se detuvo en la sentencia rechazada.' );
$zip->close();
unlink( $zip_path );

// 5. El lector divide igual que el divisor anterior de la importación, con cualquier tamaño de fragmento.
function bk_old_split( $sql ) {
	$out = array(); $current = ''; $quote = ''; $escaped = false; $length = strlen( $sql );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $sql[ $i ];
		if ( $quote ) { $current .= $char; if ( $escaped ) { $escaped = false; continue; } if ( '\\' === $char ) { $escaped = true; continue; } if ( $char === $quote ) $quote = ''; continue; }
		if ( "'" === $char || '"' === $char || '`' === $char ) { $quote = $char; $current .= $char; continue; }
		if ( ';' === $char ) { $statement = trim( $current ); if ( '' !== $statement && 0 !== strpos( $statement, '--' ) ) $out[] = $statement; $current = ''; continue; }
		$current .= $char;
	}
	if ( $quote ) return 'abierta';
	$statement = trim( $current );
	if ( '' !== $statement && 0 !== strpos( $statement, '--' ) ) $out[] = $statement;
	return $out;
}
mt_srand( 20261105 );
$alphabet = array( 'a', 'b', ' ', "\n", ';', "'", '"', '`', '\\', '-', '-', 'x' );
for ( $round = 0; $round < 600; $round++ ) {
	$sql = '';
	for ( $i = mt_rand( 0, 60 ); $i > 0; $i-- ) $sql .= $alphabet[ mt_rand( 0, count( $alphabet ) - 1 ) ];
	$reader = new Digitalisimo_Backups_Sql_Reader();
	$got = array();
	for ( $offset = 0; $offset < strlen( $sql ); $offset += $size ) { $size = mt_rand( 1, 9 ); $got = array_merge( $got, $reader->feed( substr( $sql, $offset, $size ) ) ); }
	try { $got = array_merge( $got, $reader->finish() ); } catch ( Exception $e ) { $got = 'abierta'; }
	if ( $got !== bk_old_split( $sql ) ) throw new RuntimeException( 'El lector divide distinto que el divisor anterior: ' . json_encode( $sql ) );
}
foreach ( array( $current, $legacy, $updraft, $mysqldump ) as $sql ) {
	$reader = new Digitalisimo_Backups_Sql_Reader();
	if ( array_merge( $reader->feed( $sql ), $reader->finish() ) !== bk_old_split( $sql ) ) throw new RuntimeException( 'El lector divide distinto un volcado real.' );
}

bk_cleanup();
echo 'DIGITALÍSIMO Backups: guardián SQL con ' . count( $rejected ) . ' + ' . count( $dump_rejected ) . " rechazos, volcados reales aceptados y división idéntica a la anterior.\n";
