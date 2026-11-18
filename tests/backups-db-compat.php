<?php
/**
 * Compatibilidad de la base de datos al restaurar en otro servidor (rama backups/restore-db-compat; brechas 4, 8, 10,
 * 11, 14, 15 y 16 del estudio frente a UpdraftPlus 1.26.14):
 *
 *   C1  la pasada en seco anota juegos de caracteres, intercalaciones, motores, opciones de tabla del otro servidor,
 *       columnas generadas, la fila más grande, vistas y los plugins y el tema activos, también en un archivo confiable;
 *   C2  la decisión sustituye lo que el servidor no tiene por lo más cercano (MySQL 8 → MariaDB, MariaDB 11 → MySQL,
 *       idioma, mayúsculas, binaria, utf8mb3, Aria) y rechaza lo que perdería datos (emojis sin utf8mb4, una fila que no
 *       cabe en max_allowed_packet), sin tocar lo que el servidor sí tiene ni lo que no se pudo comprobar;
 *   C3  el guardián aplica la sustitución sobre los tokens exactos (nunca dentro de un texto) y sigue rechazando todo lo
 *       que rechazaba con la anotación, la sustitución y las vistas activas;
 *   C4  columnas generadas: DEFAULT en un INSERT de un respaldo anterior, sin chocar con la reubicación;
 *   C5  vistas: se aplazan, se reescriben sin DEFINER, con SQL SECURITY INVOKER y los nombres de este sitio; en un
 *       archivo sin firma sólo las que no salen de la base ni del respaldo;
 *   C6  sesión de la importación: NO_AUTO_VALUE_ON_ZERO y sin NO_BACKSLASH_ESCAPES, nunca SET GLOBAL, y de vuelta;
 *   C7  avisos de versión (PHP, lo que piden el núcleo, los plugins y el tema activos del respaldo, WordPress al
 *       restaurar sólo la base, otro servidor de base de datos) que nunca bloquean.
 * Deben fallar con el código anterior (no existía Digitalisimo_Backups_Db_Compat ni la anotación del guardián).
 */
require __DIR__ . '/lib/backups-env.php';

$failures = 0;
function dc_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function dc_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
$C = 'Digitalisimo_Backups_Db_Compat';
$G = 'Digitalisimo_Backups_Sql_Guard';
dc_check( class_exists( $C ), 'No existe Digitalisimo_Backups_Db_Compat.' );

// Servidores de ejemplo: lo que dicen SHOW CHARACTER SET, SHOW COLLATION y SHOW ENGINES en cada uno (recortado).
function dc_server( $kind ) {
	$common = array( 'utf8mb4_general_ci', 'utf8mb4_bin', 'utf8mb4_unicode_ci', 'utf8mb4_unicode_520_ci', 'utf8mb4_spanish_ci', 'utf8mb4_spanish2_ci', 'utf8mb4_german2_ci', 'latin1_swedish_ci', 'latin1_bin', 'binary' );
	$charsets = array( 'utf8mb4' => 'utf8mb4_general_ci', 'latin1' => 'latin1_swedish_ci', 'binary' => 'binary', 'ascii' => 'ascii_general_ci' );
	if ( 'mariadb' === $kind ) {
		$collations = array_merge( $common, array( 'utf8mb4_uca1400_ai_ci', 'utf8mb4_uca1400_as_ci', 'utf8mb4_uca1400_as_cs', 'utf8mb4_uca1400_spanish_ai_ci', 'utf8mb4_unicode_520_nopad_ci', 'utf8mb4_nopad_bin', 'utf8mb3_general_ci', 'utf8mb3_unicode_ci', 'utf8mb3_unicode_520_ci', 'utf8mb3_bin' ) );
		$charsets['utf8mb3'] = 'utf8mb3_general_ci';
		$engines = array( 'INNODB' => true, 'ARIA' => true, 'MYISAM' => true, 'MEMORY' => true, 'CSV' => true );
	} else {
		$collations = array_merge( $common, array( 'utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin', 'utf8mb4_es_0900_ai_ci', 'utf8mb3_general_ci', 'utf8mb3_unicode_ci', 'utf8mb3_unicode_520_ci', 'utf8mb3_bin' ) );
		$charsets['utf8mb3'] = 'utf8mb3_general_ci';
		$engines = array( 'INNODB' => true, 'MYISAM' => true, 'MEMORY' => true, 'CSV' => true, 'ARCHIVE' => true );
	}
	$map = array();
	foreach ( $collations as $name ) $map[ $name ] = Digitalisimo_Backups_Db_Compat::charset_of( $name );
	return array( 'kind' => $kind, 'version' => 'mariadb' === $kind ? '10.11.14' : '8.0.46', 'info' => '', 'charsets' => $charsets, 'collations' => $map, 'engines' => $engines, 'default_engine' => 'INNODB', 'packet' => 16777216 );
}
$mariadb = dc_server( 'mariadb' );
$mysql = dc_server( 'mysql' );
// Un servidor viejo sin utf8mb4.
$old = array( 'kind' => 'mysql', 'version' => '5.1.73', 'charsets' => array( 'utf8' => 'utf8_general_ci', 'latin1' => 'latin1_swedish_ci' ), 'collations' => array( 'utf8_general_ci' => 'utf8', 'utf8_unicode_ci' => 'utf8', 'utf8_bin' => 'utf8', 'latin1_swedish_ci' => 'latin1' ), 'engines' => array( 'INNODB' => true, 'MYISAM' => true ), 'default_engine' => 'MYISAM', 'packet' => 1048576 );

$mysql_table = "CREATE TABLE `wp_plugin_log` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  `note` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs DEFAULT 'COLLATE utf8mb4_0900_ai_ci',\n  `total` int GENERATED ALWAYS AS ((`id` * 2)) VIRTUAL,\n  `stamp` int AS (`id` + 1) STORED,\n  `spanish` varchar(20) COLLATE utf8mb4_es_0900_ai_ci DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `total` (`total`)\n) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='ENGINE=Aria PAGE_CHECKSUM=1'";
$mariadb_table = "CREATE TABLE `wp_stats` (\n  `id` int(11) NOT NULL,\n  `v` varchar(10) COLLATE utf8mb4_uca1400_ai_ci DEFAULT NULL,\n  `w` varchar(10) COLLATE utf8mb4_uca1400_spanish_ai_ci DEFAULT NULL,\n  `p` int(11) GENERATED ALWAYS AS (`id` * 3) PERSISTENT,\n  PRIMARY KEY (`id`)\n) ENGINE=Aria DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci PAGE_CHECKSUM=1 TRANSACTIONAL=1 ROW_FORMAT=PAGE";
$tables = array( 'wp_options', 'wp_posts', 'wp_plugin_log', 'wp_stats', 'wp_v' );

dc_case( 'C1 la pasada en seco anota lo de cada CREATE TABLE, también en un archivo confiable', function() use ( $G, $mysql_table, $mariadb_table, $tables ) {
	foreach ( array( true, false ) as $trusted ) {
		$guard = ( new $G( 'exporter', $trusted, $tables ) )->collect();
		$guard->check( $mysql_table );
		$guard->check( $mariadb_table );
		$guard->check( "INSERT INTO `wp_plugin_log` (`id`,`note`) VALUES ('1','" . str_repeat( 'x', 5000 ) . "')" );
		$guard->check( "INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('1','active_plugins','a:2:{i:0;s:19:\"needs-php/plugin.php\";i:1;s:9:\"hello.php\";}','yes')" );
		$guard->check( "INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('2','stylesheet','tema-nuevo','yes')" );
		$found = $guard->found();
		$label = $trusted ? 'confiable' : 'sin firma';
		dc_check( isset( $found['collations']['utf8mb4_0900_ai_ci'], $found['collations']['utf8mb4_0900_as_cs'], $found['collations']['utf8mb4_es_0900_ai_ci'], $found['collations']['utf8mb4_uca1400_ai_ci'], $found['collations']['utf8mb4_uca1400_spanish_ai_ci'] ), $label . ': faltan intercalaciones: ' . json_encode( array_keys( (array) ( $found['collations'] ?? array() ) ) ) );
		dc_check( ! isset( $found['collations']['utf8mb4_0900_ai_ci_'] ) && array( 'wp_plugin_log' ) === $found['collations']['utf8mb4_0900_ai_ci'], $label . ': la intercalación no dice su tabla.' );
		dc_check( isset( $found['charsets']['utf8mb4'], $found['engines']['INNODB'], $found['engines']['ARIA'], $found['options']['PAGE_CHECKSUM'], $found['options']['TRANSACTIONAL'] ), $label . ': faltan motores u opciones: ' . json_encode( $found ) );
		dc_check( array( 'total', 'stamp' ) === $found['generated']['wp_plugin_log'] && array( 'p' ) === $found['generated']['wp_stats'] && 1 === (int) $found['persistent'], $label . ': columnas generadas mal anotadas: ' . json_encode( $found['generated'] ?? null ) );
		// Un COMMENT o un DEFAULT que dicen ENGINE o COLLATE son texto, no opciones.
		dc_check( ! isset( $found['engines']['ARIA_'] ) && 1 === count( $found['engines']['ARIA'] ) && ! isset( $found['collations']["utf8mb4_0900_ai_ci'"] ), $label . ': leyó un texto como opción.' );
		dc_check( (int) $found['largest'][0] > 5000 && 'wp_plugin_log' === $found['largest'][1], $label . ': no anotó la fila más grande.' );
		dc_check( isset( $found['plugins']['needs-php/plugin.php'], $found['plugins']['hello.php'], $found['themes']['tema-nuevo'] ), $label . ': no anotó los plugins y el tema activos.' );
	}
	// Las columnas generadas del exportador (la misma lectura, sin la base de datos).
	dc_check( array( 'total', 'stamp' ) === Digitalisimo_Backups_Db_Compat::generated_columns( $mysql_table ), 'generated_columns() no las encontró.' );
	dc_check( array() === Digitalisimo_Backups_Db_Compat::generated_columns( "CREATE TABLE `t` (`a` varchar(20) DEFAULT 'AS (x)', `b` int COMMENT 'GENERATED ALWAYS AS (1)')" ), 'generated_columns() leyó un texto.' );
} );

dc_case( 'C2 la intercalación, el juego de caracteres y el motor más cercanos que el servidor tiene', function() use ( $C, $mariadb, $mysql, $old ) {
	$cases = array(
		// MySQL 8 → MariaDB 10.11: las genéricas van a la del núcleo de WordPress; con idioma, la del idioma; con mayúsculas o binarias, igual de estrictas.
		array( 'utf8mb4_0900_ai_ci', 'utf8mb4', $mariadb, 'utf8mb4_unicode_520_ci' ),
		array( 'utf8mb4_0900_as_ci', 'utf8mb4', $mariadb, 'utf8mb4_uca1400_as_ci' ),
		array( 'utf8mb4_0900_as_cs', 'utf8mb4', $mariadb, 'utf8mb4_uca1400_as_cs' ),
		array( 'utf8mb4_0900_bin', 'utf8mb4', $mariadb, 'utf8mb4_bin' ),
		array( 'utf8mb4_es_0900_ai_ci', 'utf8mb4', $mariadb, 'utf8mb4_uca1400_spanish_ai_ci' ),
		// MariaDB 11 → MySQL 8.
		array( 'utf8mb4_uca1400_ai_ci', 'utf8mb4', $mysql, 'utf8mb4_unicode_520_ci' ),
		array( 'utf8mb4_uca1400_spanish_ai_ci', 'utf8mb4', $mysql, 'utf8mb4_es_0900_ai_ci' ),
		array( 'utf8mb4_uca1400_as_cs', 'utf8mb4', $mysql, 'utf8mb4_0900_as_cs' ),
		array( 'utf8mb4_unicode_520_nopad_ci', 'utf8mb4', $mysql, 'utf8mb4_unicode_520_ci' ),
		array( 'utf8mb4_nopad_bin', 'utf8mb4', $mysql, 'utf8mb4_bin' ),
		// Sin utf8mb4: la misma intercalación en utf8.
		array( 'utf8mb4_unicode_520_ci', 'utf8', $old, 'utf8_unicode_ci' ),
		array( 'utf8mb4_bin', 'utf8', $old, 'utf8_bin' ),
	);
	foreach ( $cases as $case ) {
		$got = $C::closest_collation( $case[0], $case[1], $case[2] );
		dc_check( $case[3] === $got, $case[0] . ' en ' . $case[2]['kind'] . ' ' . $case[2]['version'] . ' debía ser ' . $case[3] . ' y fue ' . var_export( $got, true ) );
	}
	dc_check( 'utf8' === $C::closest_charset( 'utf8mb4', $old )['charset'] && $C::closest_charset( 'utf8mb4', $old )['lossy'], 'utf8mb4 sin soporte no va a utf8 con pérdida.' );
	dc_check( 'utf8mb4' === $C::closest_charset( 'cp1251', $mysql )['charset'] && ! $C::closest_charset( 'cp1251', $mysql )['lossy'], 'Un juego que no existe no va a utf8mb4.' );
	dc_check( 'utf8_general_ci' === $C::collation_available( 'utf8mb3_general_ci', $old ) && 'utf8mb3_general_ci' === $C::collation_available( 'utf8_general_ci', $mysql ), 'utf8 y utf8mb3 no se tratan como el mismo.' );
	// El filtro sólo elige una que el servidor tiene y del mismo juego de caracteres.
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] = 'utf8mb4_general_ci';
	dc_check( 'utf8mb4_general_ci' === $C::closest_collation( 'utf8mb4_0900_ai_ci', 'utf8mb4', $mariadb ), 'El filtro no eligió otra.' );
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] = 'latin1_bin';
	dc_check( 'utf8mb4_unicode_520_ci' === $C::closest_collation( 'utf8mb4_0900_ai_ci', 'utf8mb4', $mariadb ), 'El filtro eligió una de otro juego.' );
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] = 'utf8mb4_no_existe_ci';
	dc_check( 'utf8mb4_unicode_520_ci' === $C::closest_collation( 'utf8mb4_0900_ai_ci', 'utf8mb4', $mariadb ), 'El filtro eligió una que el servidor no tiene.' );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] );
} );

dc_case( 'C2 la decisión: sustituye sólo lo que falta y rechaza lo que perdería datos', function() use ( $C, $G, $mariadb, $mysql, $old, $mysql_table, $mariadb_table, $tables ) {
	$scan = function( array $statements, $four = false ) use ( $G, $tables ) {
		$guard = ( new $G( 'exporter', true, $tables ) )->collect( array( 'four_byte' => $four ) );
		foreach ( $statements as $statement ) $guard->check( $statement );
		return $guard->found();
	};
	$found = $scan( array( $mysql_table, $mariadb_table ) );
	$to_maria = $C::plan( $found, $mariadb );
	dc_check( ! $to_maria['problems'], 'MySQL → MariaDB no debía tener problemas: ' . implode( ' ', $to_maria['problems'] ) );
	dc_check( 'utf8mb4_unicode_520_ci' === ( $to_maria['collations']['utf8mb4_0900_ai_ci'] ?? '' ) && ! isset( $to_maria['collations']['utf8mb4_uca1400_ai_ci'] ) && ! isset( $to_maria['collations']['utf8mb4_unicode_520_ci'] ), 'MySQL → MariaDB: mapa de intercalaciones equivocado: ' . json_encode( $to_maria['collations'] ) );
	dc_check( ! $to_maria['engines'] && ! $to_maria['drop_options'] && ! $to_maria['persistent'], 'MariaDB conserva Aria y sus opciones.' );
	dc_check( preg_grep( '/utf8mb4_0900_ai_ci → utf8mb4_unicode_520_ci \(wp_plugin_log\)/', $to_maria['notes'] ), 'La nota no dice qué cambió y en qué tabla: ' . implode( ' | ', $to_maria['notes'] ) );
	$to_mysql = $C::plan( $found, $mysql );
	dc_check( 'utf8mb4_unicode_520_ci' === ( $to_mysql['collations']['utf8mb4_uca1400_ai_ci'] ?? '' ) && ! isset( $to_mysql['collations']['utf8mb4_0900_ai_ci'] ), 'MariaDB → MySQL: mapa equivocado: ' . json_encode( $to_mysql['collations'] ) );
	dc_check( 'InnoDB' === ( $to_mysql['engines']['ARIA'] ?? '' ) && in_array( 'PAGE_CHECKSUM', $to_mysql['drop_options'], true ) && in_array( 'TRANSACTIONAL', $to_mysql['drop_options'], true ) && $to_mysql['persistent'], 'MariaDB → MySQL: Aria, sus opciones o PERSISTENT no cambian.' );
	dc_check( array( 'total', 'stamp' ) === $to_mysql['generated']['wp_plugin_log'], 'La decisión perdió las columnas generadas.' );
	// Lo que no se pudo comprobar (listas vacías: la base simulada, SQLite) no cambia nada.
	$unknown = $C::plan( $found, array( 'kind' => '', 'charsets' => null, 'collations' => null, 'engines' => null, 'packet' => 0 ) );
	dc_check( ! $unknown['collations'] && ! $unknown['charsets'] && ! $unknown['engines'] && ! $unknown['drop_options'] && ! $unknown['problems'], 'Sin saber qué tiene el servidor se sustituyó algo.' );
	// Sin utf8mb4: sin emojis se crea en utf8 con aviso; con emojis se rechaza antes de tocar nada.
	$plain = $C::plan( $scan( array( "CREATE TABLE `wp_posts` (`ID` int, `c` text) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "INSERT INTO `wp_posts` (`ID`,`c`) VALUES ('1','sin emojis ñ')" ), true ), $old );
	dc_check( ! $plain['problems'] && 'utf8' === $plain['charsets']['utf8mb4'] && 'utf8_unicode_ci' === $plain['collations']['utf8mb4_unicode_ci'] && preg_grep( '/no tiene utf8mb4/', $plain['warnings'] ), 'Sin utf8mb4 y sin emojis no se adaptó: ' . json_encode( $plain ) );
	$emoji = $C::plan( $scan( array( "CREATE TABLE `wp_posts` (`ID` int, `c` text) DEFAULT CHARSET=utf8mb4", "INSERT INTO `wp_posts` (`ID`,`c`) VALUES ('1','hola \xF0\x9F\x98\x80')" ), true ), $old );
	dc_check( preg_grep( '/caracteres de 4 bytes.*wp_posts/', $emoji['problems'] ), 'Sin utf8mb4, los emojis no rechazaron la restauración: ' . json_encode( $emoji['problems'] ) );
	// max_allowed_packet: la fila más grande (o la reubicada, que puede crecer) contra el del servidor.
	$big = $scan( array( "CREATE TABLE `wp_posts` (`ID` int, `c` longtext) DEFAULT CHARSET=utf8mb4", "INSERT INTO `wp_posts` (`ID`,`c`) VALUES ('1','" . str_repeat( 'a', 1100000 ) . "')" ) );
	$small = $C::plan( $big, $old );
	dc_check( preg_grep( '/no cabe en max_allowed_packet.*Súbelo a 2 MB/', $small['problems'] ), 'Una fila mayor que max_allowed_packet no se rechazó: ' . json_encode( $small['problems'] ) );
	dc_check( ! $C::plan( $big, $mysql )['problems'], 'Una fila que cabe se rechazó.' );
	dc_check( (bool) $C::plan( array(), $old, array( 'grown' => 1048000 ) )['problems'], 'La fila reubicada que crece no se comparó.' );
	// Un motor que no existe y no hay sustituto: problema; uno que existe: nada.
	$no_innodb = array( 'kind' => 'mysql', 'charsets' => null, 'collations' => null, 'engines' => array( 'MYISAM' => true ), 'default_engine' => 'MYISAM', 'packet' => 0 );
	dc_check( 'MYISAM' === ( $C::plan( array( 'engines' => array( 'TOKUDB' => array( 'wp_x' ) ) ), $no_innodb )['engines']['TOKUDB'] ?? '' ), 'Sin InnoDB, un motor que falta no va al de serie.' );
} );

dc_case( 'C3 el guardián sustituye los tokens exactos y nunca dentro de un texto', function() use ( $C, $G, $mariadb, $mysql, $mysql_table, $mariadb_table, $tables ) {
	$found = ( new $G( 'exporter', true, $tables ) )->collect();
	$found->check( $mysql_table );
	$found->check( $mariadb_table );
	foreach ( array( true, false ) as $trusted ) {
		$plan = $C::plan( $found->found(), $mariadb );
		$guard = ( new $G( 'exporter', $trusted, $tables ) )->compat( $C::guard_plan( $plan ) );
		$guard->rename_tables( array( 'wp_plugin_log' => 'wp_dgbkr_abc123_000' ) );
		dc_check( $guard->check( $mysql_table ), 'La tabla de MySQL no pasó.' );
		$sql = $guard->rewritten();
		dc_check( false !== strpos( $sql, 'CREATE TABLE `wp_dgbkr_abc123_000`' ) && false !== strpos( $sql, 'COLLATE=utf8mb4_unicode_520_ci COMMENT' ) && false !== strpos( $sql, 'COLLATE utf8mb4_uca1400_as_cs DEFAULT' ) && false !== strpos( $sql, 'COLLATE utf8mb4_uca1400_spanish_ai_ci DEFAULT' ), 'MySQL → MariaDB: la sentencia no cambió como debía: ' . $sql );
		dc_check( false !== strpos( $sql, "DEFAULT 'COLLATE utf8mb4_0900_ai_ci'" ) && false !== strpos( $sql, "COMMENT='ENGINE=Aria PAGE_CHECKSUM=1'" ), 'Se cambió un texto: ' . $sql );
		$plan = $C::plan( $found->found(), $mysql );
		$guard = ( new $G( 'exporter', $trusted, $tables ) )->compat( $C::guard_plan( $plan ) );
		$guard->check( $mariadb_table );
		$sql = $guard->rewritten();
		dc_check( false !== strpos( $sql, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci' ) && false === strpos( $sql, 'PAGE_CHECKSUM' ) && false === strpos( $sql, 'TRANSACTIONAL' ) && false === strpos( $sql, 'ROW_FORMAT' ) && false !== strpos( $sql, '(`id` * 3) STORED' ) && false !== strpos( $sql, 'COLLATE utf8mb4_es_0900_ai_ci DEFAULT' ), 'MariaDB → MySQL: la sentencia no cambió como debía: ' . $sql );
	}
	// Un ROW_FORMAT que no es PAGE se queda aunque cambie el motor.
	$guard = ( new $G( 'exporter', true, array( 'wp_x' ) ) )->compat( array( 'engines' => array( 'ARIA' => 'InnoDB' ) ) );
	$guard->check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=DYNAMIC' );
	dc_check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB ROW_FORMAT=DYNAMIC' === $guard->rewritten(), 'Se quitó un ROW_FORMAT que no es de Aria: ' . $guard->rewritten() );
	// SET NAMES de un servidor sin utf8mb4.
	$guard = ( new $G( 'exporter', false, array( 'wp_x' ) ) )->compat( array( 'charsets' => array( 'utf8mb4' => 'utf8' ) ) );
	$guard->check( 'SET NAMES utf8mb4' );
	dc_check( 'SET NAMES utf8' === $guard->rewritten(), 'SET NAMES no cambió: ' . $guard->rewritten() );
} );

dc_case( 'C3 el guardián sigue rechazando todo lo que rechazaba, con la anotación, la sustitución y las vistas activas', function() use ( $G ) {
	$rejected = array(
		"GRANT ALL ON *.* TO 'a'@'%'" => 'GRANT',
		"LOAD DATA INFILE '/etc/passwd' INTO TABLE `wp_posts`" => 'LOAD',
		"INSERT INTO `wp_posts` (`ID`,`post_content`) VALUES ('1',LOAD_FILE('/etc/passwd'))" => 'valores literales',
		"CREATE TABLE `wp_posts` (`a` int) DATA DIRECTORY='/var/www'" => 'DIRECTORY',
		"CREATE TABLE `wp_posts` (`a` int) ENGINE=FEDERATED CONNECTION='mysql://u@h/db/t'" => 'CONNECTION',
		"CREATE TABLE `wp_posts` (`a` int) ENGINE = SPIDER" => 'SPIDER',
		"CREATE TABLE `wp_posts` (`a` int) ENGINE='CONNECT' table_type=CSV file_name='/etc/passwd'" => 'CONNECT',
		"CREATE TABLE `wp_posts` (`a` text DEFAULT (LOAD_FILE('/etc/passwd')))" => 'LOAD_FILE',
		"CREATE TABLE `wp_posts` (`a` int) /*!50100 TABLESPACE `x` */" => 'comentario ejecutable',
		"CREATE TABLE `wp_posts` (\n `a` int,\n FOREIGN KEY (`a`) REFERENCES `otra`.`t` (`id`)\n) ENGINE=InnoDB" => 'otra base de datos',
		"CREATE TABLE `wp_posts` (\n `a` text\n) TABLE `mysql`.`user`" => 'no se admite en un archivo sin firma',
		"CREATE TABLE `wp_users` (`a` int)" => 'no figura en el manifiesto',
		"SET sql_mode = 'NO_BACKSLASH_ESCAPES'" => 'variable no permitida',
		"SET NAMES gbk" => 'juego de caracteres',
		"CREATE TRIGGER `t` BEFORE INSERT ON `wp_posts` FOR EACH ROW SET @a = 1" => 'sólo se admite CREATE TABLE',
		"CREATE PROCEDURE `p`() SELECT 1" => 'sólo se admite CREATE TABLE',
		"CREATE TABLE `wp_posts` (`a` int) ENGINE=Aria /*!900000 DATA DIRECTORY='/var/www' */" => 'comentario ejecutable',
		"CREATE TABLE `wp_posts` (`a` int) COLLATE=utf8mb4_0900_ai_ci INDEX DIRECTORY='/x'" => 'DIRECTORY',
	);
	$compat = array( 'charsets' => array( 'utf8mb4' => 'utf8' ), 'collations' => array( 'utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_520_ci' ), 'engines' => array( 'ARIA' => 'InnoDB', 'SPIDER' => 'InnoDB', 'FEDERATED' => 'InnoDB', 'CONNECT' => 'InnoDB' ), 'drop_options' => array( 'PAGE_CHECKSUM' ), 'generated' => array( 'wp_posts' => array( 'post_content' ) ) );
	foreach ( $rejected as $sql => $needle ) {
		foreach ( array( 'scan' => function( $g ) { return $g->collect( array( 'four_byte' => true ) )->views( 'defer' ); }, 'import' => function( $g ) use ( $compat ) { return $g->compat( $compat )->views( 'defer' ); }, 'views' => function( $g ) { return $g->views( 'rewrite' ); } ) as $mode => $setup ) {
			bk_expect_failure( $mode . ': ' . substr( $sql, 0, 40 ), function() use ( $G, $sql, $setup ) { $setup( new $G( 'exporter', false, array( 'wp_posts', 'wp_options' ) ) )->check( $sql ); }, $needle );
		}
	}
} );

dc_case( 'C4 columnas generadas de un respaldo anterior: DEFAULT, sin chocar con la reubicación', function() use ( $G ) {
	$guard = ( new $G( 'exporter', false, array( 'wp_plugin_log' ) ) )->compat( array( 'generated' => array( 'wp_plugin_log' => array( 'total' ) ) ) );
	$seen = null;
	$guard->on_insert( function( $insert, $sql ) use ( &$seen ) {
		$seen = $insert;
		$spans = array();
		foreach ( $insert['rows'] as $row ) foreach ( $row as $value ) if ( is_array( $value ) && 'str' === $value['type'] ) { $text = substr( $sql, $value['start'], $value['end'] - $value['start'] ); if ( false !== strpos( $text, 'origen.test' ) ) $spans[] = array( $value['start'], $value['end'], str_replace( 'origen.test', 'destino.test', $text ) ); }
		return $spans;
	} );
	$guard->check( "INSERT INTO `wp_plugin_log` (`id`,`total`,`note`) VALUES ('7','https://origen.test/x','https://origen.test/y')" );
	$sql = $guard->rewritten();
	dc_check( "INSERT INTO `wp_plugin_log` (`id`,`total`,`note`) VALUES ('7', DEFAULT,'https://destino.test/y')" === $sql, 'La columna generada no pasó a DEFAULT o la reubicación chocó: ' . $sql );
	dc_check( 'other' === $seen['rows'][0][1]['type'], 'La reubicación vio el valor de la columna generada.' );
	// Otra tabla y otras columnas no cambian.
	$guard->check( "INSERT INTO `wp_plugin_log` (`id`,`note`) VALUES ('8','x')" );
	dc_check( "INSERT INTO `wp_plugin_log` (`id`,`note`) VALUES ('8','x')" === $guard->rewritten(), 'Cambió un INSERT sin la columna generada.' );
} );

dc_case( 'C5 vistas: aplazadas, reescritas sin DEFINER y, sin firma, sólo dentro de la base y del respaldo', function() use ( $G ) {
	$view = 'CREATE ALGORITHM=UNDEFINED DEFINER=`cpaneluser_wp`@`localhost` SQL SECURITY DEFINER VIEW `wp_v` AS select `p`.`ID` AS `ID`,count(`m`.`meta_id`) AS `n`,_utf8mb4\'x\' AS `t` from (`wp_posts` `p` left join `wp_postmeta` `m` on((`m`.`post_id` = `p`.`ID`))) where (`p`.`post_status` = \'publish\') group by `p`.`ID`';
	$tables = array( 'wp_posts', 'wp_postmeta', 'wp_v' );
	foreach ( array( true, false ) as $trusted ) {
		$scan = ( new $G( 'exporter', $trusted, $tables ) )->collect()->views( 'defer' );
		$scan->rename_tables( array( 'wp_posts' => 'wp_dgbkr_x_000' ) );
		dc_check( $scan->check( $view ) && $scan->skipped() && 'wp_v' === $scan->last_view(), 'La vista no se aplazó.' );
		dc_check( isset( $scan->found()['views']['wp_v'] ) && ! $scan->rename_problems(), 'La vista no se anotó o dio un problema de renombrado.' );
		$rewrite = ( new $G( 'exporter', $trusted, $tables ) )->views( 'rewrite' );
		$rewrite->rename_tables( array( 'wp_posts' => 'dst_posts', 'wp_postmeta' => 'dst_postmeta', 'wp_v' => 'dst_v' ) );
		$rewrite->check( $view );
		$sql = $rewrite->rewritten();
		dc_check( 0 === strpos( $sql, 'CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `dst_v` AS select' ) && false === strpos( $sql, 'DEFINER' ) && false === strpos( $sql, 'cpaneluser' ), 'La vista no se reescribió: ' . $sql );
		dc_check( false !== strpos( $sql, 'from (`dst_posts` `p` left join `dst_postmeta` `m`' ) && false !== strpos( $sql, "`p`.`post_status` = 'publish'" ), 'La vista no apunta a las tablas de este sitio: ' . $sql );
	}
	// Sin SQL SECURITY: se añade INVOKER; un DEFINER entre comillas simples también se quita.
	$rewrite = ( new $G( 'exporter', false, $tables ) )->views( 'rewrite' );
	$rewrite->check( "CREATE DEFINER='root'@'%' VIEW `wp_v` AS select 1 AS `a`" );
	dc_check( 'CREATE OR REPLACE SQL SECURITY INVOKER VIEW `wp_v` AS select 1 AS `a`' === $rewrite->rewritten(), 'Vista sin SQL SECURITY: ' . $rewrite->rewritten() );
	// Una vista declarada en el manifiesto (respaldo nuevo) aunque no esté en su lista de tablas.
	( new $G( 'exporter', false, array( 'wp_posts' ) ) )->views( 'defer', array( 'wp_v' ) )->check( 'CREATE VIEW `wp_v` AS select `wp_posts`.`ID` AS `ID` from `wp_posts`' );
	$rejected = array(
		'CREATE VIEW `wp_v` AS select load_file(\'/etc/passwd\') AS `x`' => 'función LOAD_FILE',
		'CREATE VIEW `wp_v` AS select sleep(10) AS `x`' => 'función SLEEP',
		'CREATE VIEW `wp_v` AS select sys_exec(\'id\') AS `x`' => 'función SYS_EXEC',
		'CREATE VIEW `wp_v` AS select `mysql`.`user`.`authentication_string` AS `x` from `mysql`.`user`' => 'otra base de datos',
		'CREATE VIEW `wp_v` AS select `u`.`x` AS `x` from `otra`.`wp_users` `u`' => 'otra base de datos',
		'CREATE VIEW `wp_v` AS select @@datadir AS `x`' => 'variables',
		'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_users`' => 'no está en el respaldo',
		'CREATE VIEW `wp_v` AS select /*!50000 1 */ AS `x`' => 'comentario ejecutable',
		'CREATE VIEW `wp_v` AS select 1 AS `x` into outfile \'/tmp/x\'' => 'palabra INTO',
		'CREATE VIEW `wp_otra` AS select 1 AS `x`' => 'no figura en el manifiesto',
		'CREATE VIEW `wp_v` AS select 1 AS `x` procedure analyse()' => 'PROCEDURE',
		'CREATE DEFINER=`root`@@`localhost` VIEW `wp_v` AS select 1' => 'DEFINER',
	);
	foreach ( $rejected as $sql => $needle ) {
		bk_expect_failure( 'vista sin firma: ' . substr( $sql, 0, 50 ), function() use ( $G, $sql, $tables ) { ( new $G( 'exporter', false, $tables ) )->views( 'defer' )->check( $sql ); }, $needle );
	}
	// Sin el modo de vistas, como siempre: sin firma se rechaza y, con firma y otro prefijo, no se puede renombrar.
	bk_expect_failure( 'sin modo', function() use ( $G, $view, $tables ) { ( new $G( 'exporter', false, $tables ) )->check( $view ); }, 'sólo se admite CREATE TABLE' );
} );

dc_case( 'C6 sesión de la importación: NO_AUTO_VALUE_ON_ZERO y sin NO_BACKSLASH_ESCAPES, nunca SET GLOBAL', function() use ( $C ) {
	$db = $GLOBALS['wpdb'];
	$mode = new class( $db ) {
		public $inner;
		public $queries = array();
		public $mode = 'STRICT_TRANS_TABLES,NO_BACKSLASH_ESCAPES,NO_ENGINE_SUBSTITUTION';
		public function __construct( $inner ) { $this->inner = $inner; }
		public function get_var( $q ) { $this->queries[] = $q; return 'SELECT @@SESSION.sql_mode' === $q ? $this->mode : null; }
		public function query( $q ) { $this->queries[] = $q; if ( preg_match( "/^SET SESSION sql_mode = '([A-Z_,]*)'$/", $q, $m ) ) $this->mode = $m[1]; return 1; }
	};
	$GLOBALS['wpdb'] = $mode;
	try {
		$previous = $C::session_begin();
		dc_check( 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_AUTO_VALUE_ON_ZERO' === $mode->mode, 'Modo de la sesión: ' . $mode->mode );
		$C::session_end( $previous );
		dc_check( 'STRICT_TRANS_TABLES,NO_BACKSLASH_ESCAPES,NO_ENGINE_SUBSTITUTION' === $mode->mode, 'El modo no volvió: ' . $mode->mode );
		dc_check( ! preg_grep( '/GLOBAL/i', $mode->queries ), 'Se usó SET GLOBAL.' );
		// Sin poder leer el modo: se añade en la propia sesión, sin perder lo demás.
		$mode->mode = null;
		$C::session_begin();
		dc_check( (bool) preg_grep( "/^SET SESSION sql_mode = CONCAT_WS\\(',', NULLIF\\(REPLACE\\(@@SESSION.sql_mode, 'NO_BACKSLASH_ESCAPES', ''\\), ''\\), 'NO_AUTO_VALUE_ON_ZERO'\\)$/", $mode->queries ), 'Sin leer el modo no se añadió.' );
	} finally {
		$GLOBALS['wpdb'] = $db;
	}
} );

dc_case( 'C7 avisos de versión del origen, sin bloquear', function() use ( $C, $mariadb ) {
	$zip_path = sys_get_temp_dir() . '/dc-versions-' . bin2hex( random_bytes( 4 ) ) . '.zip';
	$zip = new ZipArchive();
	$zip->open( $zip_path, ZipArchive::CREATE );
	$zip->addFromString( 'wordpress/wp-includes/version.php', "<?php\n\$wp_version = '9.9';\n\$required_php_version = '99.0.0';\n" );
	$zip->addFromString( 'wordpress/wp-content/plugins/needs-php/plugin.php', "<?php\n/**\n * Plugin Name: Necesita PHP nuevo\n * Requires PHP: 99.1\n */\n" );
	$zip->addFromString( 'wordpress/wp-content/plugins/hello.php', "<?php\n/* Plugin Name: Hola\n * Requires PHP: 5.6 */\n" );
	$zip->addFromString( 'wordpress/wp-content/themes/tema-nuevo/style.css', "/*\nTheme Name: Tema nuevo\nRequires PHP: 98.0\n*/\n" );
	$zip->close();
	$zip->open( $zip_path );
	$found = array( 'plugins' => array( 'needs-php/plugin.php' => true, 'hello.php' => true, '../../etc/passwd.php' => true ), 'themes' => array( 'tema-nuevo' => true ) );
	$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = $mariadb;
	try {
		$C::server( true );
		$warnings = implode( "\n", $C::version_warnings( array( 'php_version' => '99.4.1', 'wordpress_version' => '6.8', 'database_version' => '8.0.36', 'database_server' => '8.0.36-0ubuntu0.24.04.1' ), $zip, $found ) );
		dc_check( false !== strpos( $warnings, 'el WordPress del respaldo pide PHP 99.0.0' ) && false !== strpos( $warnings, 'el plugin Necesita PHP nuevo (activo en el respaldo) pide PHP 99.1' ) && false !== strpos( $warnings, 'el tema Tema nuevo (activo en el respaldo) pide PHP 98.0' ), 'No avisó de lo que pide PHP más nuevo: ' . $warnings );
		dc_check( false === strpos( $warnings, 'Hola' ) && false === strpos( $warnings, 'passwd' ), 'Avisó de un plugin que funciona o leyó una ruta fuera de plugins.' );
		dc_check( false !== strpos( $warnings, 'El respaldo se hizo con PHP 99.4.1' ) && false !== strpos( $warnings, 'La base de datos del respaldo es de MySQL 8.0.36 y este servidor tiene MariaDB 10.11.14' ), 'No avisó de PHP o del servidor de base de datos: ' . $warnings );
		// Sólo la base de datos, de un WordPress más nuevo.
		$only_db = Digitalisimo_Backups_Restore_Parts::normalize( array( 'parts' => 'database' ), false );
		$db_warnings = implode( "\n", $C::version_warnings( array( 'wordpress_version' => '99.0' ), $zip, array(), $only_db ) );
		dc_check( false !== strpos( $db_warnings, 'La base de datos del respaldo es de WordPress 99.0 y aquí está WordPress 6.8' ) && false === strpos( $db_warnings, 'el WordPress del respaldo pide PHP' ), 'Sólo la base: aviso de WordPress equivocado: ' . $db_warnings );
		// La misma serie de PHP, el mismo servidor y nada que pida más: sin avisos.
		$same = $C::version_warnings( array( 'php_version' => PHP_VERSION, 'database_server' => '10.11.14-MariaDB', 'database_version' => '10.11.14' ), null, array() );
		dc_check( array() === $same, 'Avisos sin motivo: ' . implode( ' | ', $same ) );
		// Los disparadores, rutinas y eventos que no viajan.
		dc_check( false !== strpos( $C::objects_text( array( 'triggers' => array( 'wp_t1' ), 'routines' => array( 'PROCEDURE wp_p' ) ) ), '1 disparador(es) (wp_t1), 1 procedimiento(s) o función(es) (PROCEDURE wp_p)' ) && '' === $C::objects_text( array() ), 'El aviso de disparadores y rutinas no es el esperado.' );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_db_server'] );
		$C::server( true );
		$zip->close();
		@unlink( $zip_path );
	}
} );

bk_cleanup();
if ( $failures ) { echo "FALLA: " . $failures . " caso(s) de compatibilidad de la base de datos.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: compatibilidad de la base de datos (intercalaciones, juegos de caracteres, motores, opciones, columnas generadas, vistas, sesión y versiones) en orden.\n";
