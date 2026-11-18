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
 *
 * Revisión adversarial de 1.0.80 (fallan con acda04b2 salvo C11 y C12, que cubren mutaciones que sobrevivían):
 *   C10 (H1) vista sin firma: nunca otra base de datos donde va una tabla, ni con un alias ni con el manifiesto;
 *   C11 (H3) un respaldo anterior que lista una vista como tabla; C12 (H3) max_allowed_packet con la fila reubicada;
 *   C13 (H1) al crearla, una vista sin firma sólo lee las tablas que trae la restauración;
 *   C14 (H5) ROW_FORMAT que InnoDB no admite; C15 (H4) claves únicas NO PAD → PAD SPACE con espacios al final;
 *   C16 (H10) la vuelta a la copia previa quita las vistas que creó la restauración y repone las de antes.
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
		// Tres partes son base.tabla.columna aunque la primera se parezca a un alias.
		'CREATE VIEW `wp_v` AS select `mysql`.`user`.`authentication_string` AS `x` from `wp_posts` `mysql`' => 'otra base de datos en una vista',
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

/**
 * C10 (revisión de 1.0.80, H1): en una vista de un archivo sin firma, lo que va donde se nombra una tabla (tras FROM,
 * JOIN o una coma de la lista del FROM, también en una subconsulta) no puede llevar otra base de datos delante, y ni un
 * alias de la misma vista ni lo que diga el manifiesto (que en un archivo sin firma escribe su autor) lo permiten. Los
 * seis casos de tests/rev-view-alias.php del revisor y sus variantes, en la pasada en seco y en la reescritura.
 */
dc_case( 'C10 vista sin firma: ningún nombre de otra base de datos donde va una tabla, con alias, manifiesto ni variantes', function() use ( $G ) {
	$tables = array( 'wp_posts', 'wp_postmeta', 'wp_v' );
	$other = array(
		// Los del revisor: el alias (de columna o de tabla) o el manifiesto con el nombre de la otra base.
		'alias de columna' => 'CREATE VIEW `wp_v` AS select `o`.`ID` AS `otra_base` from `otra_base`.`tabla` `o`',
		'alias de tabla' => 'CREATE VIEW `wp_v` AS select `t`.`ID` AS `a` from (`wp_posts` `otra_base` join `otra_base`.`tabla` `t`)',
		'sin alias' => 'CREATE VIEW `wp_v` AS select `o`.`ID` AS `x` from `otra_base`.`tabla` `o`',
		// Comillas, comentarios, mayúsculas y espacios alrededor del punto.
		'sin comillas' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `otra_base` join otra_base.tabla',
		'sólo la base entre comillas' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `otra_base` join `otra_base`.tabla',
		'sólo la tabla entre comillas' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `otra_base` join otra_base.`tabla`',
		'comentarios en el punto' => 'CREATE VIEW `wp_v` AS select `t`.`ID` AS `a` from `wp_posts` `otra_base` join `otra_base` /* x */ . /* y */ `tabla` `t`',
		'comentario de línea' => "CREATE VIEW `wp_v` AS select `t`.`ID` AS `a` from `wp_posts` `otra_base` join `otra_base` -- x\n.# y\n`tabla` `t`",
		'mayúsculas' => 'CREATE VIEW `wp_v` AS SELECT `T`.`ID` AS `A` FROM `wp_posts` `OTRA_BASE` JOIN `OTRA_BASE`.`TABLA` `T`',
		'espacios y saltos' => "CREATE VIEW `wp_v` AS select `t`.`ID` AS `a`\nfrom\t`wp_posts` `otra_base`\n join\n`otra_base`\n .\n `tabla`\t`t`",
		'information_schema' => 'CREATE VIEW `wp_v` AS select `t`.`TABLE_NAME` AS `n` from `information_schema`.`TABLES` `t`',
		'information_schema con alias' => 'CREATE VIEW `wp_v` AS select `t`.`TABLE_NAME` AS `n` from (`wp_posts` `information_schema` join `information_schema`.`TABLES` `t`)',
		'information_schema sin comillas' => 'CREATE VIEW `wp_v` AS select 1 AS `n` from information_schema.tables',
		'mysql.user con alias' => 'CREATE VIEW `wp_v` AS select `u`.`authentication_string` AS `x` from (`wp_posts` `mysql` join `mysql`.`user` `u`)',
		// Otras formas de nombrar una tabla: la coma, STRAIGHT_JOIN, NATURAL, LEFT OUTER, subconsultas y UNION.
		'coma del FROM' => 'CREATE VIEW `wp_v` AS select `u`.`authentication_string` AS `x` from `wp_posts` `mysql`, `mysql`.`user` `u`',
		'coma tras ON' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `p` join `wp_postmeta` `otra_base` on((`otra_base`.`post_id` = `p`.`ID`)), `otra_base`.`tabla`',
		'STRAIGHT_JOIN' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `otra_base` straight_join `otra_base`.`tabla`',
		'NATURAL LEFT OUTER JOIN' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `otra_base` natural left outer join `otra_base`.`tabla`',
		'lista entre paréntesis' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from (`wp_posts` `otra_base`, (`otra_base`.`tabla`))',
		'subconsulta en la lista' => 'CREATE VIEW `wp_v` AS select (select `x`.`a` from `otra_base`.`t` `x` limit 1) AS `s` from `wp_posts` `otra_base`',
		'subconsulta con IN' => 'CREATE VIEW `wp_v` AS select `p`.`ID` AS `ID` from `wp_posts` `p` where `p`.`ID` in (select `otra_base`.`t`.`id` from `otra_base`.`t`)',
		'EXISTS' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `otra_base` where exists(select 1 from `otra_base`.`t`)',
		'tabla derivada' => 'CREATE VIEW `wp_v` AS select `d`.`a` AS `a` from (select `t`.`a` AS `a` from `otra_base`.`t` `t`) `d`',
		'UNION' => 'CREATE VIEW `wp_v` AS select 1 AS `a` from `wp_posts` `otra_base` union select 1 AS `1` from `otra_base`.`t`',
		// En una expresión: tres partes, una función de otra base y una función por su nombre entre comillas invertidas.
		'tres partes' => 'CREATE VIEW `wp_v` AS select `otra_base`.`tabla`.`c` AS `x` from `wp_posts` `otra_base`',
		'función de otra base' => 'CREATE VIEW `wp_v` AS select `otra_base`.`f`(1) AS `x` from `wp_posts` `otra_base`',
		'función de otra base con espacio' => 'CREATE VIEW `wp_v` AS select `otra_base` . `f` (1) AS `x` from `wp_posts` `otra_base`',
		'función entre comillas invertidas' => "CREATE VIEW `wp_v` AS select `sys_exec`('id') AS `x` from `wp_posts`",
		'palabra delante del punto' => 'CREATE VIEW `wp_v` AS select date.`x` AS `x` from `wp_posts` `DATE`',
		'palabra donde va una tabla' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from dual',
		'nombre que no es tabla ni alias' => 'CREATE VIEW `wp_v` AS select `otra_base`.`c` AS `x` from `wp_posts` `p`',
		'tabla fuera del respaldo tras una coma' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from `wp_posts` `p`, `wp_users` `u`',
		'nada donde va la tabla' => 'CREATE VIEW `wp_v` AS select 1 AS `x` from { OJ `wp_posts` }',
	);
	foreach ( $other as $label => $sql ) {
		foreach ( array( 'defer', 'rewrite' ) as $mode ) {
			bk_expect_failure( $mode . ' · ' . $label, function() use ( $G, $sql, $tables, $mode ) { ( new $G( 'exporter', false, $tables ) )->views( $mode )->check( $sql ); } );
		}
	}
	// El manifiesto sin firma no da permiso: `otra_base` como tabla o como vista declarada.
	$sql = 'CREATE VIEW `wp_v` AS select `o`.`ID` AS `x` from `otra_base`.`tabla` `o`';
	foreach ( array( 'defer', 'rewrite' ) as $mode ) {
		bk_expect_failure( $mode . ' · manifiesto con otra_base en database_tables', function() use ( $G, $sql, $mode ) { ( new $G( 'exporter', false, array( 'wp_posts', 'wp_v', 'otra_base' ) ) )->views( $mode )->check( $sql ); }, 'otra base de datos' );
		bk_expect_failure( $mode . ' · manifiesto con otra_base en database_views', function() use ( $G, $sql, $mode ) { ( new $G( 'exporter', false, array( 'wp_posts', 'wp_v' ) ) )->views( $mode, array( 'otra_base' ) )->check( $sql ); }, 'otra base de datos' );
	}
	// Al crearla (reescritura con las tablas que trae de verdad esta restauración), una vista que sólo declara el manifiesto
	// sin firma tampoco vale como origen.
	bk_expect_failure( 'origen que sólo declara el manifiesto', function() use ( $G ) { ( new $G( 'exporter', false, array( 'wp_posts', 'wp_v' ) ) )->views( 'rewrite', array( 'wp_v', 'wp_fantasma' ) )->view_sources( array( 'wp_posts', 'wp_v' ) )->check( 'CREATE VIEW `wp_v` AS select `f`.`ID` AS `ID` from `wp_fantasma` `f`' ); }, 'no está en el respaldo' );
	// Lo legítimo sigue valiendo (lo que escriben MySQL 8 y MariaDB con SHOW CREATE VIEW).
	$fine = array(
		'CREATE VIEW `wp_v` AS select `wp_posts`.`ID` AS `ID` from `wp_posts`',
		'CREATE VIEW `wp_v` AS select `p`.`ID` AS `ID`,count(`m`.`meta_id`) AS `n` from (`wp_posts` `p` left join `wp_postmeta` `m` on((`m`.`post_id` = `p`.`ID`))) group by `p`.`ID`,`p`.`post_title` order by `p`.`ID` desc limit 5,10',
		'CREATE VIEW `wp_v` AS select `p`.`ID` AS `ID` from `wp_posts` `p`, `wp_postmeta` `m` where (`m`.`post_id` = `p`.`ID`)',
		'CREATE VIEW `wp_v` AS select `p`.`ID` AS `ID`,(select count(0) from `wp_postmeta` `m` where (`m`.`post_id` = `p`.`ID`)) AS `n` from `wp_posts` `p` where `p`.`ID` in (select `wp_postmeta`.`post_id` from `wp_postmeta`) and exists(select 1 from `wp_postmeta` `x` where (`x`.`post_id` = `p`.`ID`))',
		'CREATE VIEW `wp_v` AS select `d`.`ID` AS `ID` from (select `wp_posts`.`ID` AS `ID` from `wp_posts`) `d`',
		'CREATE VIEW `wp_v` AS select `wp_posts`.`ID` AS `ID` from `wp_posts` union all select `wp_postmeta`.`post_id` AS `post_id` from `wp_postmeta`',
		"CREATE VIEW `wp_v` AS select extract(year from `p`.`post_date`) AS `y`,trim(both ' ' from `p`.`post_title`) AS `t`,substring(`p`.`post_title` from 2 for 3) AS `s`,convert(`p`.`post_title` using utf8mb4) AS `c`,cast(`p`.`ID` as char charset utf8mb4) AS `i` from `wp_posts` `p`",
		"CREATE VIEW `wp_v` AS select `p`.`ID` AS `ID`,group_concat(distinct `m`.`meta_key` order by `m`.`meta_key` ASC separator ',') AS `k`,row_number() OVER (PARTITION BY `p`.`post_type` ORDER BY `p`.`ID` )  AS `r` from (`wp_posts` `p` join `wp_postmeta` `m` using (`ID`)) group by `p`.`ID`",
		'CREATE VIEW `wp_v` AS select `p`.* from `wp_posts` AS `p` straight_join `wp_postmeta` AS `m` on((`m`.`post_id` = `p`.`ID`)) where (`p`.`ID` > .5) with cascaded check option',
	);
	foreach ( $fine as $sql ) {
		foreach ( array( 'defer', 'rewrite' ) as $mode ) {
			try {
				( new $G( 'exporter', false, $tables ) )->views( $mode )->check( $sql );
			} catch ( Exception $e ) {
				throw new RuntimeException( 'Se rechazó una vista legítima (' . $mode . '): ' . $e->getMessage() );
			}
		}
	}
	// Una vista que usa otra del mismo respaldo (declarada) sigue valiendo, y con firma no se mira el SELECT.
	( new $G( 'exporter', false, array( 'wp_posts' ) ) )->views( 'defer', array( 'wp_v', 'wp_v2' ) )->check( 'CREATE VIEW `wp_v` AS select `wp_v2`.`ID` AS `ID` from `wp_v2`' );
	( new $G( 'exporter', false, array( 'wp_posts' ) ) )->views( 'rewrite', array( 'wp_v', 'wp_v2' ) )->view_sources( array( 'wp_posts', 'wp_v2' ) )->check( 'CREATE VIEW `wp_v` AS select `wp_v2`.`ID` AS `ID` from `wp_v2`' );
	( new $G( 'exporter', true, $tables ) )->views( 'rewrite' )->check( 'CREATE VIEW `wp_v` AS select `t`.`ID` AS `ID` from `wordpress_test`.`wp_posts` `t`' );
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

/**
 * C8 de punta a punta con el motor real (respaldo por la cola y restauración por pasos) sobre la base simulada, con un
 * servidor de destino distinto (filtro digitalisimo_backups_db_server): MySQL 8 (0900, columnas generadas, una vista y
 * un disparador) → MariaDB 10.11.
 */
class Dc_Test_DB extends Bk_Test_DB {
	public $info = '8.0.46-0ubuntu0.24.04.1';
	public $mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
	public $log_rows = array( array( 'id' => '0', 'note' => 'fila cero', 'total' => '0', 'stamp' => '1' ), array( 'id' => '5', 'note' => 'otra', 'total' => '10', 'stamp' => '6' ) );
	public function tables() { return array_merge( parent::tables(), array( 'wp_v' ) ); }
	public function db_server_info() { return $this->info; }
	public function get_var( $query ) {
		if ( 'SELECT @@SESSION.sql_mode' === $query ) return $this->mode;
		if ( 'SELECT @@max_allowed_packet' === $query ) return '16777216';
		return parent::get_var( $query );
	}
	public function get_row( $query, $format ) {
		if ( 0 === strpos( $query, 'SHOW CREATE VIEW `wp_v`' ) ) { $this->last_error = ''; return array( 'wp_v', 'CREATE ALGORITHM=UNDEFINED DEFINER=`cuenta_origen`@`localhost` SQL SECURITY DEFINER VIEW `wp_v` AS select `wordpress_test`.`wp_posts`.`ID` AS `ID` from `wordpress_test`.`wp_posts`', 'utf8mb4', 'utf8mb4_0900_ai_ci' ); }
		return parent::get_row( $query, $format );
	}
	public function get_results( $query, $format ) {
		$this->last_error = '';
		if ( "SHOW FULL TABLES WHERE Table_type = 'VIEW'" === $query ) return array( array( 'wp_v', 'VIEW' ) );
		if ( false !== strpos( $query, 'information_schema.TRIGGERS' ) ) return array( array( 'n' => 'wp_cuenta_pedidos' ) );
		if ( false !== strpos( $query, 'information_schema.' ) ) return array();
		if ( false !== strpos( $query, 'SHOW INDEX FROM `wp_plugin_log`' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( false !== strpos( $query, '`wp_plugin_log`' ) ) return bk_page( $this->log_rows, $query, 'id' );
		return parent::get_results( $query, $format );
	}
	public function query( $sql ) {
		if ( preg_match( "/^SET SESSION sql_mode = '([A-Z_,]*)'$/", (string) $sql, $m ) ) $this->mode = $m[1];
		return parent::query( $sql );
	}
}
dc_case( 'C8 de punta a punta: respaldo de MySQL 8 con vista, columnas generadas y disparador, restaurado en MariaDB 10.11', function() use ( $C, $mariadb ) {
	$previous = $GLOBALS['wpdb'];
	$db = new Dc_Test_DB();
	$db->extra_tables['wp_plugin_log'] = "CREATE TABLE `wp_plugin_log` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  `note` varchar(191) DEFAULT NULL,\n  `total` int GENERATED ALWAYS AS ((`id` * 2)) VIRTUAL,\n  `stamp` int GENERATED ALWAYS AS ((`id` + 1)) STORED,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
	try {
		$C::server( true );
		$backup = bk_backup( true );
		$zip = new ZipArchive();
		$zip->open( $backup );
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$sql = (string) $zip->getFromName( 'database.sql' );
		$zip->close();
		dc_check( array( 'wp_v' ) === ( $manifest['database_views'] ?? null ) && ! in_array( 'wp_v', (array) $manifest['database_tables'], true ), 'La vista no quedó aparte en el manifiesto: ' . json_encode( array( $manifest['database_views'] ?? null, $manifest['database_tables'] ) ) );
		dc_check( array( 'wp_cuenta_pedidos' ) === ( $manifest['database_objects']['triggers'] ?? null ) && '8.0.46-0ubuntu0.24.04.1' === ( $manifest['database_server'] ?? '' ), 'El manifiesto no dice los disparadores o el servidor de origen.' );
		dc_check( (bool) preg_match( "/INSERT INTO `wp_plugin_log` \(`id`,`note`\) VALUES \('0','fila cero'\);/", $sql ) && false === strpos( $sql, '`total`)' ), 'El exportador escribió las columnas generadas: ' . substr( $sql, (int) strpos( $sql, 'INSERT INTO `wp_plugin_log`' ), 200 ) );
		dc_check( (bool) preg_match( "/\nCREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `wp_v` AS select `wp_posts`.`ID` AS `ID` from `wp_posts`;\n\nSET foreign_key_checks = 1;\n$/", $sql ) && false === strpos( $sql, 'cuenta_origen' ) && false === strpos( $sql, 'wordpress_test' ), 'La vista no va al final, sin DEFINER y sin la base de datos: ' . substr( $sql, -300 ) );
		$backup_job = null;
		foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'backup' === $job['kind'] ) $backup_job = $job;
		dc_check( $backup_job && preg_grep( '/1 disparador\(es\) \(wp_cuenta_pedidos\) que el respaldo no lleva/', array_map( function( $w ) { return is_array( $w ) ? (string) ( $w['text'] ?? '' ) : (string) $w; }, (array) $backup_job['warnings'] ) ), 'El respaldo no avisó de los disparadores.' );
		// En MariaDB 10.11 (sin 0900): la intercalación cambia en la tabla temporal, el 0 se conserva y la vista se crea al final.
		$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = $mariadb;
		$C::server( true );
		$db->info = '10.11.14-MariaDB-0ubuntu0.24.04.1';
		$db->queries = array();
		$GLOBALS['bk_options']['blogname'] = 'Cambiado';
		$result = Digitalisimo_Backups::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
		dc_check( ! is_wp_error( $result ), 'La restauración no empezó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		for ( $i = 0; $i < 2000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
		$restore = null;
		foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] ) $restore = $job;
		dc_check( $restore && 'success' === $restore['status'], 'La restauración no terminó bien: ' . json_encode( $restore['error'] ?? $restore['status'] ?? null ) );
		$creates = preg_grep( '/^CREATE TABLE `wp_dgbkr_[0-9a-f]+_[0-9]+` \(\n  `id` bigint unsigned/', $db->queries );
		dc_check( 1 === count( $creates ) && false !== strpos( reset( $creates ), 'COLLATE=utf8mb4_unicode_520_ci' ) && false === strpos( reset( $creates ), '0900' ), 'La tabla temporal no se creó con la intercalación de MariaDB: ' . json_encode( $creates ) );
		$session = preg_grep( '/^SET SESSION sql_mode = /', $db->queries );
		dc_check( $session && false !== strpos( reset( $session ), 'NO_AUTO_VALUE_ON_ZERO' ) && 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' === $db->mode && ! preg_grep( '/SET GLOBAL/i', $db->queries ), 'La importación no usó NO_AUTO_VALUE_ON_ZERO en su sesión o no la devolvió.' );
		$order = array_keys( preg_grep( '/^(RENAME TABLE |CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `wp_v` AS select `wp_posts`\.`ID` AS `ID` from `wp_posts`$)/', $db->queries ) );
		dc_check( 2 === count( $order ) && 0 === strpos( $db->queries[ $order[0] ], 'RENAME TABLE ' ), 'La vista no se creó después del cambio de tablas: ' . json_encode( array_values( preg_grep( '/VIEW/', $db->queries ) ) ) );
		$notice = (string) ( $restore['result']['restore']['notice'] ?? '' );
		dc_check( false !== strpos( $notice, 'Intercalación utf8mb4_0900_ai_ci → utf8mb4_unicode_520_ci (wp_plugin_log)' ) && false !== strpos( $notice, 'Se crearon 1 vista(s)' ) && false !== strpos( $notice, 'wp_cuenta_pedidos' ), 'El informe no dice lo que se adaptó: ' . $notice );
		dc_check( array( 'wp_v' ) === ( $restore['result']['restore']['database']['views']['created'] ?? null ), 'El informe público no trae las vistas creadas.' );
		dc_check( (bool) preg_grep( '/^Base de datos: Intercalación utf8mb4_0900_ai_ci/', array_map( 'strval', array_filter( (array) $restore['warnings'], 'is_string' ) ) ), 'El trabajo no avisó al terminar la verificación.' );
		// Un max_allowed_packet que no alcanza: rechazo en la verificación, sin copia previa ni cambios.
		$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = array( 'packet' => 300 ) + $mariadb;
		$C::server( true );
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $old ) @unlink( $old );
		$db->queries = array();
		$GLOBALS['bk_options']['blogname'] = 'Cambiado otra vez';
		$refused = Digitalisimo_Backups::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
		for ( $i = 0; $i < 200 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
		$last = null;
		foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
		$message = is_wp_error( $refused ) ? $refused->get_error_message() : (string) ( $last['error']['message'] ?? '' );
		dc_check( false !== strpos( $message, 'max_allowed_packet' ) && 'inspect' === ( $last['phase'] ?? '' ) && empty( $last['checkpoint']['safety'] ), 'max_allowed_packet no rechazó en la verificación: ' . $message );
		dc_check( ! glob( bk_storage() . '/safety-before-restore-*' ) && ! preg_grep( '/^RENAME TABLE /', $db->queries ) && 'Cambiado otra vez' === $GLOBALS['bk_options']['blogname'], 'Antes del rechazo se creó la copia previa o se tocó el sitio.' );
		// verify dice lo mismo antes de confirmar.
		$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = $mariadb;
		$C::server( true );
		$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
		$verify = Digitalisimo_Backups::service_verify( basename( $backup ) );
		dc_check( ! is_wp_error( $verify ) && preg_grep( '/utf8mb4_0900_ai_ci → utf8mb4_unicode_520_ci/', (array) $verify['database']['changes'] ) && in_array( 'wp_v', (array) $verify['database']['views'], true ) && preg_grep( '/wp_cuenta_pedidos/', (array) $verify['database']['warnings'] ), 'verify no dice lo que se adaptará: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : ( $verify['database'] ?? null ) ) );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_db_server'] );
		$GLOBALS['wpdb'] = $previous;
		$C::server( true );
	}
} );

dc_case( 'C9 lo que dice el servidor: nombres completos de MariaDB 10.10+, motores disponibles y max_allowed_packet', function() use ( $C, $G ) {
	$previous = $GLOBALS['wpdb'];
	$GLOBALS['wpdb'] = new class() {
		public $last_error = '';
		public function db_server_info() { return '10.11.14-MariaDB-0ubuntu0.24.04.1'; }
		public function db_version() { return '10.11.14'; }
		public function suppress_errors( $s = true ) { return false; }
		public function get_var( $q ) { return 'SELECT @@max_allowed_packet' === $q ? '16777216' : null; }
		public function get_results( $q, $f ) {
			if ( 'SHOW CHARACTER SET' === $q ) return array( array( 'Charset' => 'utf8mb4', 'Default collation' => 'utf8mb4_general_ci' ), array( 'Charset' => 'utf8mb3', 'Default collation' => 'utf8mb3_general_ci' ) );
			if ( 'SHOW COLLATION' === $q ) return array( array( 'Collation' => 'utf8mb4_unicode_520_ci', 'Charset' => 'utf8mb4' ), array( 'Collation' => 'uca1400_ai_ci', 'Charset' => null ), array( 'Collation' => 'utf8mb3_general_ci', 'Charset' => 'utf8mb3' ) );
			if ( false !== strpos( $q, 'COLLATION_CHARACTER_SET_APPLICABILITY' ) ) return array( array( 'COLLATION_NAME' => 'uca1400_ai_ci', 'CHARACTER_SET_NAME' => 'utf8mb4', 'FULL_COLLATION_NAME' => 'utf8mb4_uca1400_ai_ci' ), array( 'COLLATION_NAME' => 'uca1400_ai_ci', 'CHARACTER_SET_NAME' => 'utf8mb3', 'FULL_COLLATION_NAME' => 'utf8mb3_uca1400_ai_ci' ) );
			if ( 'SHOW ENGINES' === $q ) return array( array( 'Engine' => 'InnoDB', 'Support' => 'DEFAULT' ), array( 'Engine' => 'Aria', 'Support' => 'YES' ), array( 'Engine' => 'FEDERATED', 'Support' => 'NO' ) );
			return array();
		}
	};
	try {
		$server = $C::server( true );
		dc_check( 'mariadb' === $server['kind'] && 16777216 === $server['packet'] && 'INNODB' === $server['default_engine'], 'No leyó el tipo, el paquete o el motor de serie: ' . json_encode( $server ) );
		dc_check( 'utf8mb4' === ( $server['collations']['utf8mb4_uca1400_ai_ci'] ?? '' ) && ! isset( $server['collations']['uca1400_ai_ci'] ), 'No leyó el nombre completo de las intercalaciones UCA 14 de MariaDB: ' . json_encode( $server['collations'] ) );
		dc_check( isset( $server['engines']['ARIA'] ) && ! isset( $server['engines']['FEDERATED'] ), 'Un motor que el servidor no da (NO) cuenta como disponible.' );
		// Con eso, un respaldo de MariaDB 11 (uca1400) no se toca aquí.
		$found = array( 'collations' => array( 'utf8mb4_uca1400_ai_ci' => array( 'wp_x' ) ) );
		dc_check( ! $C::plan( $found, $server )['collations'], 'Se sustituyó una intercalación que MariaDB 10.11 sí tiene.' );
	} finally {
		$GLOBALS['wpdb'] = $previous;
		$C::server( true );
	}
	// Conversión de una red: las vistas del sitio viajan con él; las de otros sitios, no.
	dc_check( $C::site_owns( 'wp_2_v', 'wp_2_', 'wp_' ) && ! $C::site_owns( 'wp_3_v', 'wp_2_', 'wp_' ) && ! $C::site_owns( 'wp_v', 'wp_2_', 'wp_' ), 'Vistas de un sitio de la red mal repartidas.' );
	dc_check( $C::site_owns( 'wp_v', 'wp_', 'wp_' ) && ! $C::site_owns( 'wp_2_v', 'wp_', 'wp_' ) && ! $C::site_owns( 'otra_v', 'wp_', 'wp_' ), 'Vistas del sitio principal mal repartidas.' );
	// Y la conversión la deja lista para el paquete: los usuarios con el nombre del sitio, sin DEFINER.
	$guard = ( new $G( 'exporter', true, array( 'wp_users', 'wp_2_posts', 'wp_2_v' ) ) )->views( 'rewrite' );
	$guard->rename_tables( array( 'wp_users' => 'wp_2_users' ) );
	$guard->check( 'CREATE ALGORITHM=UNDEFINED DEFINER=`red`@`%` SQL SECURITY DEFINER VIEW `wp_2_v` AS select `p`.`ID` AS `ID`,`u`.`user_login` AS `l` from (`wp_2_posts` `p` join `wp_users` `u` on((`u`.`ID` = `p`.`ID`)))' );
	dc_check( 'CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `wp_2_v` AS select `p`.`ID` AS `ID`,`u`.`user_login` AS `l` from (`wp_2_posts` `p` join `wp_2_users` `u` on((`u`.`ID` = `p`.`ID`)))' === $guard->rewritten(), 'La vista convertida no quedó lista: ' . $guard->rewritten() );
} );

/**
 * C14 (revisión de 1.0.80, H5): al pasar de Aria (u otro motor que el servidor no tiene) a InnoDB, también se quita un
 * ROW_FORMAT que InnoDB no admite (FIXED, PAGE: con innodb_strict_mode, el de serie de MySQL 5.7 y 8, CREATE TABLE da
 * ER_ILLEGAL_HA_CREATE_OPTION después de la copia previa); los que admite (DYNAMIC, COMPACT, REDUNDANT, COMPRESSED,
 * DEFAULT) se quedan, y con otro motor de destino sólo se quita PAGE (como antes). La decisión lo dice.
 */
dc_case( 'C14 Aria (u otro motor) → InnoDB quita el ROW_FORMAT que InnoDB no admite', function() use ( $C, $G, $mysql ) {
	$to_innodb = array( 'engines' => array( 'ARIA' => 'InnoDB', 'TOKUDB' => 'InnoDB' ) );
	$cases = array(
		'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=FIXED' => 'CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB ',
		'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria DEFAULT CHARSET=utf8mb4 ROW_FORMAT = fixed PAGE_CHECKSUM=1' => 'CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4  PAGE_CHECKSUM=1',
		"CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=PAGE COMMENT='ROW_FORMAT=FIXED'" => "CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB  COMMENT='ROW_FORMAT=FIXED'",
		'CREATE TABLE `wp_x` (`a` int) ENGINE=TokuDB ROW_FORMAT=TOKUDB_ZLIB' => 'CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB ',
		'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=DYNAMIC' => 'CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB ROW_FORMAT=DYNAMIC',
		'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=COMPRESSED' => 'CREATE TABLE `wp_x` (`a` int) ENGINE=InnoDB ROW_FORMAT=COMPRESSED',
		// Sin cambio de motor no se toca nada.
		'CREATE TABLE `wp_x` (`a` int) ENGINE=MyISAM ROW_FORMAT=FIXED' => 'CREATE TABLE `wp_x` (`a` int) ENGINE=MyISAM ROW_FORMAT=FIXED',
	);
	foreach ( array( true, false ) as $trusted ) {
		foreach ( $cases as $sql => $expected ) {
			$guard = ( new $G( 'exporter', $trusted, array( 'wp_x' ) ) )->compat( $to_innodb );
			$guard->check( $sql );
			dc_check( $expected === $guard->rewritten(), 'ROW_FORMAT con el motor cambiado a InnoDB: «' . $guard->rewritten() . '» en vez de «' . $expected . '».' );
		}
	}
	// A un motor que no es InnoDB (un servidor sin InnoDB): FIXED se queda (MyISAM lo admite) y PAGE, como siempre, no.
	$guard = ( new $G( 'exporter', true, array( 'wp_x' ) ) )->compat( array( 'engines' => array( 'ARIA' => 'MyISAM' ) ) );
	$guard->check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=FIXED' );
	dc_check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=MyISAM ROW_FORMAT=FIXED' === $guard->rewritten(), 'A MyISAM se quitó FIXED: ' . $guard->rewritten() );
	$guard->check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=PAGE' );
	dc_check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=MyISAM ' === $guard->rewritten(), 'A MyISAM no se quitó PAGE: ' . $guard->rewritten() );
	// La decisión (MariaDB → MySQL 8) lo dice en sus notas, con la tabla.
	$scan = ( new $G( 'exporter', true, array( 'wp_x', 'wp_y' ) ) )->collect();
	$scan->check( 'CREATE TABLE `wp_x` (`a` int) ENGINE=Aria ROW_FORMAT=FIXED' );
	$scan->check( 'CREATE TABLE `wp_y` (`a` int) ENGINE=Aria ROW_FORMAT=DYNAMIC' );
	$plan = $C::plan( $scan->found(), $mysql );
	dc_check( 'InnoDB' === ( $plan['engines']['ARIA'] ?? '' ) && preg_grep( '/ROW_FORMAT=FIXED \(wp_x\).*InnoDB no lo admite/', $plan['notes'] ) && ! preg_grep( '/ROW_FORMAT=DYNAMIC/', $plan['notes'] ), 'La decisión no dice que quita ROW_FORMAT=FIXED: ' . implode( ' | ', $plan['notes'] ) );
} );

/* ------------------------------------------------------------------------------- Revisión de 1.0.80 (H3, H10) */

/**
 * Base simulada con vistas: SHOW TABLES las lista como MySQL, SHOW FULL TABLES dice cuáles son, SHOW CREATE VIEW da su
 * definición y CREATE OR REPLACE VIEW / DROP VIEW las cambian. `$fail_finish`: la primera sentencia que contiene ese
 * texto, en una restauración que no es la vuelta a la copia previa y ya creadas sus vistas, falla (un fallo después
 * del cambio, en la fase «Direcciones y cachés»).
 */
class Dc_Site_DB extends Bk_Test_DB {
	public $info = '';
	public $packet = 0;
	public $views = array();
	public $fail_finish = '';
	/** Vistas cuyo SHOW CREATE VIEW falla (una cuenta sin permiso para leer su definición). */
	public $unreadable = array();
	/** Filas de las tablas de extra_tables: nombre => array( columna de la clave primaria, filas ). */
	public $rows = array();
	public function tables() { return array_merge( parent::tables(), array_keys( $this->views ) ); }
	public function db_server_info() { return $this->info; }
	public function get_var( $query ) {
		if ( 'SELECT @@max_allowed_packet' === $query ) return $this->packet ? (string) $this->packet : null;
		return parent::get_var( $query );
	}
	public function get_row( $query, $format ) {
		if ( preg_match( '/^SHOW CREATE VIEW `([^`]+)`/', (string) $query, $m ) ) {
			$this->last_error = in_array( $m[1], $this->unreadable, true ) ? 'SHOW VIEW command denied' : '';
			return isset( $this->views[ $m[1] ] ) && '' === $this->last_error ? array( $m[1], $this->views[ $m[1] ], 'utf8mb4', 'utf8mb4_unicode_520_ci' ) : null;
		}
		return parent::get_row( $query, $format );
	}
	public function get_results( $query, $format ) {
		$this->last_error = '';
		if ( "SHOW FULL TABLES WHERE Table_type = 'VIEW'" === $query ) return array_map( function( $name ) { return array( $name, 'VIEW' ); }, array_keys( $this->views ) );
		foreach ( $this->rows as $table => $data ) {
			if ( false === strpos( $query, '`' . $table . '`' ) ) continue;
			if ( false !== strpos( $query, 'SHOW INDEX' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => $data[0], 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
			return bk_page( $data[1], $query, $data[0] );
		}
		return parent::get_results( $query, $format );
	}
	public function query( $sql ) {
		if ( '' !== $this->fail_finish && false !== strpos( (string) $sql, $this->fail_finish ) && preg_grep( '/^CREATE OR REPLACE .*VIEW `/', $this->queries ) ) {
			$this->fail_finish = '';
			$this->queries[] = $sql;
			$this->last_error = 'Fallo simulado tras crear las vistas';
			return false;
		}
		$done = parent::query( $sql );
		if ( preg_match( '/^CREATE OR REPLACE .*?VIEW `([^`]+)` AS /s', (string) $sql, $m ) ) $this->views[ $m[1] ] = 'CREATE ALGORITHM=UNDEFINED DEFINER=`aqui`@`localhost` SQL SECURITY INVOKER VIEW `' . $m[1] . '` AS ' . substr( (string) $sql, strpos( (string) $sql, ' AS ', strpos( (string) $sql, 'VIEW `' ) ) + 4 );
		if ( preg_match( '/^DROP VIEW IF EXISTS `([^`]+)`$/', (string) $sql, $m ) ) unset( $this->views[ $m[1] ] );
		return $done;
	}
}
/** Restauración por pasos de $file hasta terminar (sin reintentos); devuelve los trabajos de restauración, del primero al último. */
function dc_restore( $file, $confirm = 'RESTAURAR' ) {
	foreach ( Digitalisimo_Backups_Jobs::active() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	@unlink( ABSPATH . '.maintenance' );
	$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
	$GLOBALS['bk_filters']['digitalisimo_backups_job_max_attempts'] = 1;
	$before = array_column( Digitalisimo_Backups_Jobs::all(), 'id' );
	$refused = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => $confirm, 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	for ( $i = 0; $i < 3000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_job_max_attempts'] );
	$jobs = array_values( array_filter( Digitalisimo_Backups_Jobs::all(), function( $job ) use ( $before ) { return 'restore' === $job['kind'] && ! in_array( $job['id'], $before, true ); } ) );
	usort( $jobs, function( $a, $b ) { return (float) $a['queued_at'] <=> (float) $b['queued_at']; } );
	return array( 'jobs' => $jobs, 'refused' => is_wp_error( $refused ) ? $refused->get_error_message() : '' );
}
/**
 * Copia de un respaldo con su SQL y su manifiesto cambiados (como los escribía otra versión). Con $register, registrada
 * como propia (de confianza); sin él, sin firma ni registro (se restaura con RESTAURAR SIN FIRMA).
 */
function dc_rezip( $source, $name, callable $sql, $manifest = null, $register = true ) {
	$path = bk_storage() . '/' . $name;
	copy( $source, $path );
	$zip = new ZipArchive();
	$zip->open( $path );
	$text = (string) $sql( (string) $zip->getFromName( 'database.sql' ) );
	$data = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	if ( $manifest ) $data = $manifest( $data );
	$data['entries']['database.sql'] = array( 'size' => strlen( $text ), 'sha256' => hash( 'sha256', $text ) );
	$data['database_size'] = strlen( $text );
	$data['uncompressed_size'] = array_sum( array_column( $data['entries'], 'size' ) );
	if ( ! $register ) { unset( $data['auth'] ); $zip->setArchiveComment( '' ); }
	$zip->addFromString( 'database.sql', $text );
	$zip->addFromString( 'manifest.json', json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	$zip->close();
	if ( $register ) bk_call( 'Digitalisimo_Backups', 'remember', $path, 'local', 'installation', array( 'id' => 'manual', 'type' => 'full' ) );
	return $path;
}
/** La sentencia INSERT más larga del SQL de un respaldo (sin su «;»), como la mide la pasada en seco. */
function dc_largest_insert( $file ) {
	$zip = new ZipArchive();
	$zip->open( $file );
	$largest = 0;
	foreach ( explode( "\n", (string) $zip->getFromName( 'database.sql' ) ) as $line ) if ( 0 === strpos( $line, 'INSERT INTO ' ) ) $largest = max( $largest, strlen( rtrim( $line, ';' ) ) );
	$zip->close();
	return $largest;
}

/**
 * C15 (revisión de 1.0.80, H4): una clave única de texto con una intercalación NO PAD de MySQL 8 (`_0900_`) que la
 * decisión pasa a una PAD SPACE (MariaDB no tiene las `_0900_`): «abc» y «abc » chocarían. La pasada en seco mira, sólo
 * en esas claves, si algún valor termina en espacio (sin guardar valores) y la decisión lo avisa antes de la copia
 * previa, con la intercalación NO PAD que el servidor tenga. También si las filas llegan en otro tramo que su CREATE
 * TABLE, en verify y en el registro del trabajo.
 */
$GLOBALS['dc_slugs'] = "CREATE TABLE `wp_slugs` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  `slug` varchar(191) NOT NULL,\n  `code` varchar(20) COLLATE utf8mb4_bin DEFAULT NULL,\n  `ref` varchar(20) COLLATE utf8mb4_0900_bin DEFAULT NULL,\n  `n` int DEFAULT NULL,\n  `note` text,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `slug` (`slug`(100)),\n  UNIQUE KEY `code` (`code`),\n  UNIQUE KEY `ref_n` (`ref`,`n`),\n  KEY `note` (`note`(10)),\n  CONSTRAINT `fk` FOREIGN KEY (`slug`) REFERENCES `wp_posts` (`ID`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
dc_case( 'C15 NO PAD → PAD SPACE: valores con espacios al final en una clave única, avisados en la verificación', function() use ( $C, $G, $mariadb, $mysql ) {
	$create = $GLOBALS['dc_slugs'];
	$inserts = array(
		"INSERT INTO `wp_slugs` (`id`,`slug`,`code`,`ref`,`n`,`note`) VALUES ('1','abc','x ','r','1','nota ')",
		"INSERT INTO `wp_slugs` (`id`,`slug`,`code`,`ref`,`n`,`note`) VALUES ('2','abc ','y','s ','2',NULL)",
		"INSERT INTO `wp_slugs` (`id`,`slug`,`code`,`ref`,`n`,`note`) VALUES ('3','d\\'e  ','z','t','3','x')",
	);
	$tables = array( 'wp_slugs', 'wp_posts' );
	$scan = ( new $G( 'exporter', true, $tables ) )->collect();
	$scan->check( $create );
	foreach ( $inserts as $insert ) $scan->check( $insert );
	$found = $scan->found();
	dc_check( array( 'slug', 'ref_n' ) === array_keys( (array) ( $found['pad_watch']['wp_slugs'] ?? array() ) ) && array( 'slug' => 'utf8mb4_0900_ai_ci' ) === $found['pad_watch']['wp_slugs']['slug'] && array( 'ref' => 'utf8mb4_0900_bin' ) === $found['pad_watch']['wp_slugs']['ref_n'], 'No se vigilan justo las claves únicas NO PAD (ni la primaria entera, ni la PAD, ni la foránea, ni un índice normal): ' . json_encode( $found['pad_watch'] ?? null ) );
	dc_check( 2 === ( $found['trailing']['wp_slugs']['slug']['rows'] ?? 0 ) && 1 === ( $found['trailing']['wp_slugs']['ref_n']['rows'] ?? 0 ) && ! isset( $found['trailing']['wp_slugs']['code'] ), 'Las filas con espacios al final no se contaron bien: ' . json_encode( $found['trailing'] ?? null ) );
	dc_check( false === strpos( json_encode( $found ), 'abc' ), 'La pasada en seco guardó valores de la base de datos.' );
	$to_maria = $C::plan( $found, $mariadb );
	$warnings = preg_grep( '/clave única/', $to_maria['warnings'] );
	dc_check( 2 === count( $warnings ) && preg_grep( '/^La tabla wp_slugs tiene 2 fila\(s\) en las que la clave única slug \(slug\) termina en espacio: utf8mb4_0900_ai_ci .*utf8mb4_unicode_520_ci.*PAD SPACE.*Duplicate entry.*después de la copia previa.*digitalisimo_backups_restore_collation.*utf8mb4_unicode_520_nopad_ci/', $warnings ) && preg_grep( '/clave única ref_n \(ref\).*utf8mb4_0900_bin.*utf8mb4_bin/', $warnings ), 'MySQL 8 → MariaDB no avisó de la clave única que puede chocar: ' . json_encode( $to_maria['warnings'] ) );
	dc_check( ! $to_maria['problems'], 'El aviso no debe impedir la restauración.' );
	// En MySQL 8 la intercalación no cambia: nada que avisar. Sin espacios al final, tampoco.
	dc_check( ! preg_grep( '/clave única/', $C::plan( $found, $mysql )['warnings'] ), 'Se avisó sin cambiar la intercalación.' );
	$clean = ( new $G( 'exporter', true, $tables ) )->collect();
	$clean->check( $create );
	$clean->check( "INSERT INTO `wp_slugs` (`id`,`slug`,`code`,`ref`,`n`,`note`) VALUES ('1','abc','x ','r','1','nota ')" );
	dc_check( ! preg_grep( '/clave única/', $C::plan( $clean->found(), $mariadb )['warnings'] ), 'Se avisó sin ningún valor con espacios al final en una clave NO PAD.' );
	// Con el remedio que propone (el filtro elige una NO PAD que el servidor tiene), esa clave ya no se avisa.
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] = function( $chosen, $from ) { return 'utf8mb4_0900_ai_ci' === $from ? 'utf8mb4_unicode_520_nopad_ci' : $chosen; };
	try {
		$nopad = $C::plan( $found, $mariadb );
		dc_check( 'utf8mb4_unicode_520_nopad_ci' === ( $nopad['collations']['utf8mb4_0900_ai_ci'] ?? '' ) && ! preg_grep( '/clave única slug/', $nopad['warnings'] ) && preg_grep( '/clave única ref_n/', $nopad['warnings'] ), 'Con una intercalación NO PAD elegida se siguió avisando (o se dejó de avisar de la otra): ' . json_encode( $nopad['warnings'] ) );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_collation'] );
	}
	// Un servidor sin ninguna intercalación NO PAD (MySQL 5.7): lo dice.
	$mysql57 = $mysql;
	foreach ( array_keys( $mysql57['collations'] ) as $name ) if ( false !== strpos( $name, '0900' ) ) unset( $mysql57['collations'][ $name ] );
	dc_check( preg_grep( '/no tiene ninguna intercalación NO PAD de utf8mb4/', $C::plan( $found, $mysql57 )['warnings'] ), 'Sin intercalaciones NO PAD, el aviso no dice qué hacer.' );
	// Por tramos: el CREATE TABLE en uno y las filas en el siguiente (la vigilancia pasa por lo anotado).
	$first = ( new $G( 'exporter', true, $tables ) )->collect();
	$first->check( $create );
	$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = $mariadb;
	try {
		$C::server( true );
		$second = $C::attach_scan( new $G( 'exporter', true, $tables ), array(), $first->found() );
		foreach ( $inserts as $insert ) $second->check( $insert );
		$merged = $C::merge( $first->found(), $second->found() );
		dc_check( 2 === ( $merged['trailing']['wp_slugs']['slug']['rows'] ?? 0 ) && preg_grep( '/clave única slug/', $C::plan( $merged, $mariadb )['warnings'] ), 'Con las filas en otro tramo no se vio la clave que puede chocar: ' . json_encode( $merged['trailing'] ?? null ) );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_db_server'] );
		$C::server( true );
	}
	// De punta a punta: un respaldo de MySQL 8 con esa tabla, verificado y restaurado en MariaDB 10.11.
	$previous = $GLOBALS['wpdb'];
	$db = new Dc_Site_DB();
	$db->info = '8.0.46-0ubuntu0.24.04.1';
	$db->extra_tables['wp_slugs'] = $create;
	$db->rows['wp_slugs'] = array( 'id', array( array( 'id' => '1', 'slug' => 'abc', 'code' => 'x', 'ref' => 'r', 'n' => '1', 'note' => null ), array( 'id' => '2', 'slug' => 'abc ', 'code' => 'y', 'ref' => 's', 'n' => '2', 'note' => null ) ) );
	$GLOBALS['wpdb'] = $db;
	try {
		$C::server( true );
		$backup = bk_backup( true );
		$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = $mariadb;
		$C::server( true );
		$db->info = '10.11.14-MariaDB-0ubuntu0.24.04.1';
		$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
		$verify = Digitalisimo_Backups::service_verify( basename( $backup ) );
		dc_check( ! is_wp_error( $verify ) && preg_grep( '/^La tabla wp_slugs tiene 1 fila\(s\) en las que la clave única slug/', (array) $verify['database']['warnings'] ), 'verify no avisa de la clave única que puede chocar: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : ( $verify['database'] ?? null ) ) );
		// Una sentencia por tramo: el CREATE TABLE y sus filas, en tramos distintos de la pasada en seco.
		$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
		try {
			$outcome = dc_restore( $backup );
		} finally {
			unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
		}
		$restore = end( $outcome['jobs'] );
		$texts = array_map( function( $w ) { return is_array( $w ) ? (string) ( $w['text'] ?? '' ) : (string) $w; }, (array) ( $restore['warnings'] ?? array() ) );
		dc_check( $restore && preg_grep( '/^La tabla wp_slugs tiene 1 fila\(s\) en las que la clave única slug/', $texts ), 'El trabajo no avisó al terminar la verificación: ' . json_encode( array( $restore['status'] ?? null, $texts ) ) );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_db_server'] );
		$GLOBALS['wpdb'] = $previous;
		$C::server( true );
	}
} );

/**
 * C11 (H3, M09 y M12): un respaldo anterior (de un exportador de un paso) escribía una vista como tabla: su DROP TABLE,
 * su CREATE VIEW y sus filas, y la listaba en database_tables. Se restaura sin tabla temporal para ella (si no, la
 * restauración falla tras la copia previa: «no crea la tabla»), sin ejecutar nada sobre ella en la base viva (ni su
 * DROP TABLE ni sus filas) y la vista se crea al final del cambio.
 */
dc_case( 'C11 respaldo anterior con una vista listada como tabla: sin tabla temporal, sin tocarla en la base viva y creada al final', function() use ( $C ) {
	$previous = $GLOBALS['wpdb'];
	$db = new Dc_Site_DB();
	$GLOBALS['wpdb'] = $db;
	try {
		$C::server( true );
		$base = bk_backup( true );
		$old = dc_rezip( $base, 'digitalisimo-backup-vista-como-tabla.zip', function( $sql ) {
			$block = "DROP TABLE IF EXISTS `wp_v`;\nCREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `wp_v` AS select `wp_posts`.`ID` AS `ID` from `wp_posts`;\nINSERT INTO `wp_v` (`ID`) VALUES ('1');\nINSERT INTO `wp_v` (`ID`) VALUES ('2');\n\n";
			$at = strrpos( $sql, 'SET foreign_key_checks = 1;' );
			return false === $at ? $sql . $block : substr( $sql, 0, $at ) . $block . substr( $sql, $at );
		}, function( $manifest ) { $manifest['database_tables'][] = 'wp_v'; unset( $manifest['database_views'] ); return $manifest; } );
		$db->queries = array();
		$outcome = dc_restore( $old );
		$restore = end( $outcome['jobs'] );
		dc_check( $restore && 'success' === $restore['status'], 'El respaldo anterior con una vista entre sus tablas no se restauró: ' . json_encode( array( $outcome['refused'], $restore['phase'] ?? null, $restore['error']['message'] ?? null ) ) );
		dc_check( ! preg_grep( '/^(DROP TABLE IF EXISTS|INSERT INTO|CREATE TABLE) `wp_v`/', $db->queries ), 'Se ejecutó sobre la vista de la base viva lo que el respaldo anterior escribía para ella: ' . json_encode( array_values( preg_grep( '/`wp_v`/', $db->queries ) ) ) );
		$renames = preg_grep( '/^RENAME TABLE /', $db->queries );
		dc_check( 1 === count( $renames ) && false === strpos( (string) reset( $renames ), '`wp_v`' ), 'La vista entró en el cambio de tablas: ' . json_encode( array_values( $renames ) ) );
		$order = array_keys( preg_grep( '/^(RENAME TABLE |CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `wp_v` AS select `wp_posts`\.`ID` AS `ID` from `wp_posts`$)/', $db->queries ) );
		dc_check( 2 === count( $order ) && 0 === strpos( $db->queries[ $order[0] ], 'RENAME TABLE ' ), 'La vista no se creó al final del cambio: ' . json_encode( array_values( preg_grep( '/VIEW/', $db->queries ) ) ) );
		dc_check( array( 'wp_v' ) === ( $restore['result']['restore']['database']['views']['created'] ?? null ), 'El informe no dice que se creó la vista.' );
	} finally {
		$GLOBALS['wpdb'] = $previous;
		$C::server( true );
	}
} );

/**
 * C12 (H3, M08): la fila más grande cabe en max_allowed_packet tal como viene, pero reubicada (a un dominio más largo)
 * ya no: se rechaza en la verificación, sin copia previa ni cambios.
 */
dc_case( 'C12 max_allowed_packet con la fila ya reubicada: rechazo en la verificación, sin copia previa', function() use ( $C, $mariadb ) {
	$previous = $GLOBALS['wpdb'];
	$options = $GLOBALS['bk_options'];
	$db = new Dc_Site_DB();
	$db->info = '10.11.14-MariaDB-0ubuntu0.24.04.1';
	$db->posts[] = array( 'ID' => '3', 'post_content' => str_repeat( '<a href="https://o.test/p">x</a> ', 2000 ) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = 'https://o.test';
	try {
		$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = $mariadb;
		$C::server( true );
		$backup = bk_backup( true );
		$largest = dc_largest_insert( $backup );
		dc_check( $largest > 60000, 'La fila grande no quedó en el respaldo.' );
		// Aquí, con un dominio 38 caracteres más largo (2000 veces), la fila crece unos 76 KB: el paquete deja 30 KB.
		$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = 'https://destino-con-un-nombre-mucho-mas-largo.example';
		$GLOBALS['bk_filters']['digitalisimo_backups_db_server'] = array( 'packet' => $largest + $C::PACKET_MARGIN + 30000 ) + $mariadb;
		$C::server( true );
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $copy ) @unlink( $copy );
		$db->queries = array();
		$outcome = dc_restore( $backup );
		$last = end( $outcome['jobs'] );
		$message = '' !== $outcome['refused'] ? $outcome['refused'] : (string) ( $last['error']['message'] ?? '' );
		dc_check( false !== strpos( $message, 'max_allowed_packet' ) && false !== strpos( $message, 'No se cambió nada del sitio' ) && 'inspect' === ( $last['phase'] ?? '' ) && empty( $last['checkpoint']['safety'] ), 'La fila reubicada que no cabe en max_allowed_packet no se rechazó en la verificación: ' . json_encode( array( $message, $last['phase'] ?? null, $last['status'] ?? null ) ) );
		dc_check( ! glob( bk_storage() . '/safety-before-restore-*' ) && ! preg_grep( '/^RENAME TABLE /', $db->queries ), 'Antes del rechazo se creó la copia previa o se tocó el sitio.' );
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_db_server'] );
		$GLOBALS['wpdb'] = $previous;
		$GLOBALS['bk_options'] = $options;
		$C::server( true );
	}
} );

/**
 * C13 (H1, al crearla): un archivo sin firma lista en su manifiesto la tabla de otra aplicación que comparte la base
 * (sin el prefijo del sitio: la restauración no la trae ni la toca) y trae una vista que la lee. La pasada en seco la
 * acepta (es una tabla del manifiesto, sin otra base delante), pero al crearla al final del cambio sólo valen las tablas
 * que trae de verdad esta restauración: esa vista no se crea y el informe lo dice; la que lee las del sitio, sí.
 */
dc_case( 'C13 vista sin firma al crearla: sólo lee las tablas que trae la restauración, no las que sólo lista el manifiesto', function() use ( $C ) {
	$previous = $GLOBALS['wpdb'];
	$db = new Dc_Site_DB();
	$GLOBALS['wpdb'] = $db;
	try {
		$C::server( true );
		$base = bk_backup( true );
		$unsigned = dc_rezip( $base, 'digitalisimo-backup-vista-de-otra-app.zip', function( $sql ) {
			$table = "DROP TABLE IF EXISTS `otra_app`;\nCREATE TABLE `otra_app` (\n  `x` varchar(20) DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\nINSERT INTO `otra_app` (`x`) VALUES ('secreto');\n\n";
			$views = "CREATE VIEW `wp_v` AS select `o`.`x` AS `x` from `otra_app` `o`;\nCREATE VIEW `wp_w` AS select `wp_posts`.`ID` AS `ID` from `wp_posts`;\n\n";
			$at = strrpos( $sql, 'SET foreign_key_checks = 1;' );
			return substr( $sql, 0, $at ) . $table . $views . substr( $sql, $at );
		}, function( $manifest ) { $manifest['database_tables'][] = 'otra_app'; $manifest['database_views'] = array( 'wp_v', 'wp_w' ); return $manifest; }, false );
		$db->queries = array();
		$outcome = dc_restore( $unsigned, 'RESTAURAR SIN FIRMA' );
		$restore = end( $outcome['jobs'] );
		dc_check( $restore && 'success' === $restore['status'], 'El archivo sin firma no se restauró: ' . json_encode( array( $outcome['refused'], $restore['phase'] ?? null, $restore['error']['message'] ?? null ) ) );
		$views = (array) ( $restore['result']['restore']['database']['views'] ?? array() );
		dc_check( array( 'wp_w' ) === ( $views['created'] ?? null ) && false !== strpos( (string) ( $views['failed']['wp_v'] ?? '' ), 'otra_app' ), 'La vista que lee una tabla que la restauración no trae se creó (o la otra no): ' . json_encode( $views ) );
		dc_check( ! preg_grep( '/VIEW `wp_v`/', $db->queries ) && ! preg_grep( '/`otra_app`/', array_filter( $db->queries, function( $q ) { return 0 !== strpos( $q, 'SELECT' ) && 0 !== strpos( $q, 'SHOW' ); } ) ), 'Se ejecutó algo sobre la tabla de la otra aplicación o la vista que la lee.' );
	} finally {
		$GLOBALS['wpdb'] = $previous;
		$C::server( true );
	}
} );

/**
 * C16 (revisión de 1.0.80, H10): una restauración (con reubicación) crea sus vistas al final del cambio y después falla
 * («Direcciones y cachés»): la vuelta a la copia previa quita la vista que creó y que no había antes, y vuelve a crear
 * con su definición de antes la que ya había (que la restauración reemplazó): la copia previa respalda las vistas.
 */
dc_case( 'C16 vuelta a la copia previa: fuera la vista que creó la restauración, de vuelta la que había antes', function() use ( $C ) {
	$previous = $GLOBALS['wpdb'];
	$options = $GLOBALS['bk_options'];
	$db = new Dc_Site_DB();
	$GLOBALS['wpdb'] = $db;
	try {
		// El origen (otro dominio) tiene dos vistas: una nueva aquí y otra con el nombre de una que aquí ya existe.
		$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = 'https://o.test';
		$db->views = array(
			'wp_nueva' => 'CREATE ALGORITHM=UNDEFINED DEFINER=`origen`@`localhost` SQL SECURITY DEFINER VIEW `wp_nueva` AS select `wordpress_test`.`wp_posts`.`ID` AS `ID` from `wordpress_test`.`wp_posts`',
			'wp_antes' => 'CREATE ALGORITHM=UNDEFINED DEFINER=`origen`@`localhost` SQL SECURITY DEFINER VIEW `wp_antes` AS select `wordpress_test`.`wp_posts`.`ID` AS `del_respaldo` from `wordpress_test`.`wp_posts`',
		);
		$C::server( true );
		$backup = bk_backup( true );
		// Aquí: otro dominio y sólo la vista de antes, con su propia definición.
		$GLOBALS['bk_options']['home'] = $GLOBALS['bk_options']['siteurl'] = 'https://example.test';
		$db->views = array( 'wp_antes' => 'CREATE ALGORITHM=UNDEFINED DEFINER=`aqui`@`localhost` SQL SECURITY DEFINER VIEW `wp_antes` AS select `wordpress_test`.`wp_options`.`option_name` AS `de_antes` from `wordpress_test`.`wp_options`' );
		$db->fail_finish = 'UPDATE `wp_options` SET option_value';
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $copy ) @unlink( $copy );
		$db->queries = array();
		$outcome = dc_restore( $backup );
		$failed = $outcome['jobs'][0] ?? null;
		$rollback = end( $outcome['jobs'] );
		dc_check( $failed && 'failed' === $failed['status'] && $rollback && ! empty( $rollback['spec']['rollback'] ) && 'success' === $rollback['status'], 'No hubo la vuelta a la copia previa tras el fallo: ' . json_encode( array_map( function( $j ) { return array( $j['status'], $j['phase'], $j['error']['message'] ?? null ); }, $outcome['jobs'] ) ) );
		$create = function( $view ) use ( $db ) { return array_keys( preg_grep( '/^CREATE OR REPLACE .*VIEW `' . $view . '` AS /', $db->queries ) ); };
		$drop = function( $view ) use ( $db ) { return array_keys( preg_grep( '/^DROP VIEW IF EXISTS `' . $view . '`$/', $db->queries ) ); };
		dc_check( 1 === count( $create( 'wp_nueva' ) ) && 2 === count( $create( 'wp_antes' ) ), 'La restauración no creó sus vistas o la vuelta no repuso la de antes: ' . json_encode( array_values( preg_grep( '/VIEW/', $db->queries ) ) ) );
		dc_check( 1 === count( $drop( 'wp_nueva' ) ) && $drop( 'wp_nueva' )[0] > $create( 'wp_nueva' )[0] && ! $drop( 'wp_antes' ), 'La vuelta a la copia previa no quitó la vista que creó la restauración (o quitó la de antes): ' . json_encode( array_values( preg_grep( '/VIEW/', $db->queries ) ) ) );
		dc_check( array( 'wp_antes' ) === array_keys( $db->views ) && false !== strpos( $db->views['wp_antes'], '`de_antes`' ) && false !== strpos( $db->views['wp_antes'], '`wp_options`' ), 'Tras la vuelta, las vistas no son las de antes: ' . json_encode( $db->views ) );
		// La copia previa respaldó la vista de antes, con su definición.
		$safety = glob( bk_storage() . '/safety-before-restore-*' );
		dc_check( 1 === count( $safety ), 'No quedó la copia previa.' );
		$zip = new ZipArchive();
		$zip->open( $safety[0] );
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$sql = (string) $zip->getFromName( 'database.sql' );
		$zip->close();
		dc_check( array( 'wp_antes' ) === ( $manifest['database_views'] ?? null ) && false !== strpos( $sql, 'VIEW `wp_antes` AS select `wp_options`.`option_name` AS `de_antes` from `wp_options`;' ), 'La copia previa no lleva la vista que había antes: ' . json_encode( $manifest['database_views'] ?? null ) );
		$views = (array) ( $rollback['result']['restore']['database']['views'] ?? array() );
		dc_check( array( 'wp_nueva' ) === ( $views['dropped'] ?? null ) && array( 'wp_antes' ) === ( $views['created'] ?? null ) && false !== strpos( (string) ( $rollback['result']['restore']['notice'] ?? '' ), 'Se quitaron 1 vista(s) que había creado la restauración que se deshizo y la copia previa no trae (wp_nueva)' ), 'El informe de la vuelta no dice qué vistas quitó y repuso: ' . json_encode( array( $views, $rollback['result']['restore']['notice'] ?? null ) ) );
		// Si la copia previa no pudo leer la definición de la vista que ya había (un permiso), no la lleva: la vuelta no la
		// borra (ya existía antes de la restauración); sólo quita la que la restauración creó.
		$db->views = array( 'wp_antes' => 'CREATE ALGORITHM=UNDEFINED DEFINER=`aqui`@`localhost` SQL SECURITY DEFINER VIEW `wp_antes` AS select `wordpress_test`.`wp_options`.`option_name` AS `de_antes` from `wordpress_test`.`wp_options`' );
		$db->unreadable = array( 'wp_antes' );
		$db->fail_finish = 'UPDATE `wp_options` SET option_value';
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $copy ) @unlink( $copy );
		$db->queries = array();
		$outcome = dc_restore( $backup );
		$rollback = end( $outcome['jobs'] );
		dc_check( 2 === count( $outcome['jobs'] ) && ! empty( $rollback['spec']['rollback'] ) && 'success' === $rollback['status'], 'Sin leer la vista de antes, no hubo la vuelta a la copia previa: ' . json_encode( array_map( function( $j ) { return array( $j['status'], $j['phase'], $j['error']['message'] ?? null ); }, $outcome['jobs'] ) ) );
		dc_check( 1 === count( $drop( 'wp_nueva' ) ) && ! $drop( 'wp_antes' ) && array( 'wp_antes' ) === array_keys( $db->views ), 'La vuelta borró una vista que ya existía antes de la restauración: ' . json_encode( array_values( preg_grep( '/VIEW/', $db->queries ) ) ) );
		// Un sitio sin ninguna vista antes de restaurar (la copia previa no trae ninguna): la vuelta quita las dos.
		$db->views = array();
		$db->unreadable = array();
		$db->fail_finish = 'UPDATE `wp_options` SET option_value';
		foreach ( glob( bk_storage() . '/safety-before-restore-*' ) as $copy ) @unlink( $copy );
		$db->queries = array();
		$outcome = dc_restore( $backup );
		$rollback = end( $outcome['jobs'] );
		dc_check( 2 === count( $outcome['jobs'] ) && ! empty( $rollback['spec']['rollback'] ) && 'success' === $rollback['status'] && 2 === count( preg_grep( '/^CREATE OR REPLACE .*VIEW `/', $db->queries ) ), 'Sin vistas antes, no hubo la restauración con vistas y su vuelta: ' . json_encode( array_map( function( $j ) { return array( $j['status'], $j['phase'], $j['error']['message'] ?? null ); }, $outcome['jobs'] ) ) );
		dc_check( 1 === count( $drop( 'wp_nueva' ) ) && 1 === count( $drop( 'wp_antes' ) ) && array() === $db->views && array( 'wp_antes', 'wp_nueva' ) === array_values( array_intersect( array( 'wp_antes', 'wp_nueva' ), (array) ( $rollback['result']['restore']['database']['views']['dropped'] ?? array() ) ) ), 'Sin vistas antes, la vuelta dejó vistas de la restauración: ' . json_encode( array( $db->views, $rollback['result']['restore']['database'] ?? null ) ) );
	} finally {
		$GLOBALS['wpdb'] = $previous;
		$GLOBALS['bk_options'] = $options;
		$C::server( true );
	}
} );

bk_cleanup();
if ( $failures ) { echo "FALLA: " . $failures . " caso(s) de compatibilidad de la base de datos.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: compatibilidad de la base de datos (intercalaciones, juegos de caracteres, motores, opciones, columnas generadas, vistas, sesión y versiones) en orden.\n";
