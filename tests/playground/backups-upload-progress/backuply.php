<?php
/**
 * Respaldos de Backuply sintéticos para el ensayo «subir e importar con el panel de avance» (los escribe el PHP de la PC
 * antes de levantar Playground): el formato de Backuply 1.6.0 que lee la importación (I9, como los de
 * tests/backups-backuply-import.php): un .tar.gz en varios miembros gzip con softsql.sql al estilo de phpMyAdmin, el sitio
 * (wp-config.php, el núcleo mínimo, un plugin, un tema y medios de 1 MiB que no se comprimen, para que haya fases que
 * seguir) y al final softperms.txt, softver.txt y su archivo de información; ese archivo también en backups_info-*.
 * Uso: php backuply.php <carpeta wp-content/backuply del sitio> <carpeta del ensayo>
 *   deja <carpeta>/backups-lab01/wp_origen.example_2026-11-16_10-00-00.tar.gz (y su información) y, para subirlo,
 *   <ensayo>/wp_otro.example_2026-11-16_11-00-00.tar.gz.
 */
list( , $folder, $out ) = $argv + array( '', '', '' );
if ( '' === $folder || '' === $out ) { fwrite( STDERR, "uso: php backuply.php <carpeta backuply> <carpeta del ensayo>\n" ); exit( 2 ); }

function bl_header( $name, $size, $type = '0', $mode = 0644, $mtime = 1763287200 ) {
	$first = pack( 'a100a8a8a8a12a12', $name, sprintf( '%07s', decoct( $mode ) ), sprintf( '%07s', decoct( 1000 ) ), sprintf( '%07s', decoct( 1000 ) ), sprintf( '%011s', decoct( $size ) ), sprintf( '%011s', decoct( $mtime ) ) );
	$last = pack( 'a1a100a6a2a32a32a8a8a155a12', $type, '', 'ustar ', ' ', 'www', 'www', '', '', '', '' );
	$sum = 0;
	for ( $i = 0; $i < 148; $i++ ) $sum += ord( $first[ $i ] );
	$sum += 8 * 32;
	for ( $i = 0; $i < 356; $i++ ) $sum += ord( $last[ $i ] );
	return $first . pack( 'a8', sprintf( '%06s ', decoct( $sum ) ) ) . $last;
}
function bl_pad( $data ) { return $data . str_repeat( "\0", ( 512 - strlen( $data ) % 512 ) % 512 ); }
function bl_entry( $name, $data = '', $type = '0', $mode = 0644 ) { return bl_header( $name, '0' === $type ? strlen( $data ) : 0, $type, $mode ) . ( '0' === $type ? bl_pad( $data ) : '' ); }
function bl_pma( $value ) { return "'" . str_replace( array( "\x00", "\x0a", "\x0d", "\x1a" ), array( '\0', '\n', '\r', '\Z' ), str_replace( "'", "''", str_replace( '\\', '\\\\', (string) $value ) ) ) . "'"; }
/** Una tabla como `backuply_PMA_exportData()`: varias filas por INSERT, partido a los 50 000 caracteres. */
function bl_table( $table, $create, array $numeric, array $rows ) {
	$sql = "\n-- Delete any existing table `$table`\nDROP TABLE IF EXISTS `$table`;\n--\n-- Table structure for table `$table`\n--\n\n" . $create . ";\n\n--\n-- Dumping data for table `$table`\n--\n\n";
	$length = 0;
	$new = true;
	foreach ( $rows as $row ) {
		$sql .= $new ? 'INSERT INTO `' . $table . "` VALUES\n(" : ",\n(";
		$new = false;
		$cells = array();
		foreach ( array_values( $row ) as $i => $value ) {
			$cells[] = null === $value ? 'NULL' : ( ! empty( $numeric[ $i ] ) ? (string) $value : bl_pma( $value ) );
			if ( null !== $value ) $length += strlen( (string) $value );
		}
		$sql .= implode( ', ', $cells ) . ')';
		if ( $length > 50000 ) { $sql .= ";\n"; $new = true; $length = 0; }
	}
	if ( ! $new ) $sql .= ";\n";
	return $sql . "\n-- --------------------------------------------------------\n";
}
function bl_dump( $home ) {
	$p = 'bk_';
	$sql = "-- Softaculous SQL Dump\n-- http://www.softaculous.com\n--\n-- Host: localhost\n-- Generation Time: November 16, 2026, 10:00 am\n-- Server version: 8.0.36\n-- PHP Version: 8.2.0\n\nSET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\nSET time_zone = \"+00:00\";\n\n/*!40101 SET NAMES utf8mb4 */;\n\n--\n-- Database: `wordpress`\n--\n\n-- --------------------------------------------------------\n";
	$sql .= bl_table( $p . 'options', "CREATE TABLE `{$p}options` (\n  `option_id` INTEGER PRIMARY KEY,\n  `option_name` varchar(191) NOT NULL UNIQUE,\n  `option_value` longtext NOT NULL,\n  `autoload` varchar(20) NOT NULL DEFAULT 'yes'\n)", array( true, false, false, false ), array(
		array( 1, 'siteurl', $home, 'yes' ),
		array( 2, 'home', $home, 'yes' ),
		array( 3, 'blogname', 'Sitio de Backuply', 'yes' ),
		array( 4, $p . 'user_roles', serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ) ) ), 'yes' ),
	) );
	$posts = array();
	for ( $i = 1; $i <= 300; $i++ ) $posts[] = array( $i, 'Entrada ' . $i . ' de Backuply', str_repeat( 'Contenido de la entrada ' . $i . ' con <a href="' . $home . '/blog/">enlace</a>. ', 20 ), $home . '/?p=' . $i, 'post' );
	$sql .= bl_table( $p . 'posts', "CREATE TABLE `{$p}posts` (\n  `ID` INTEGER PRIMARY KEY,\n  `post_title` text NOT NULL,\n  `post_content` longtext NOT NULL,\n  `guid` varchar(255) NOT NULL DEFAULT '',\n  `post_type` varchar(20) NOT NULL DEFAULT 'post'\n)", array( true, false, false, false, false ), $posts );
	$sql .= bl_table( $p . 'users', "CREATE TABLE `{$p}users` (\n  `ID` INTEGER PRIMARY KEY,\n  `user_login` varchar(60) NOT NULL,\n  `user_email` varchar(100) NOT NULL,\n  `user_url` varchar(100) NOT NULL DEFAULT ''\n)", array( true, false, false, false ), array( array( 1, 'admin', 'admin@origen.example', $home ) ) );
	$sql .= bl_table( $p . 'usermeta', "CREATE TABLE `{$p}usermeta` (\n  `umeta_id` INTEGER PRIMARY KEY,\n  `user_id` bigint(20) NOT NULL DEFAULT 0,\n  `meta_key` varchar(255),\n  `meta_value` longtext\n)", array( true, true, false, false ), array( array( 1, 1, $p . 'capabilities', serialize( array( 'administrator' => true ) ) ), array( 2, 1, $p . 'user_level', '10' ) ) );
	return $sql;
}
function bl_archive( $name, $home ) {
	$info = "<?php exit();?>\n" . json_encode( array( 'name' => $name, 'backup_dir' => 1, 'backup_db' => 1, 'email' => 'admin@origen.example', 'date_time' => '2026-11-16 10:00:am', 'btime' => 1763287200, 'auto_backup' => false, 'ext' => 'tar.gz', 'size' => 0, 'backup_site_url' => $home, 'backup_site_path' => '/var/www/origen' ), JSON_PRETTY_PRINT );
	$tar = bl_entry( 'softsql.sql', bl_dump( $home ) );
	$tar .= bl_entry( 'wp-admin/', '', '5', 0755 );
	$tar .= bl_entry( 'wp-admin/index.php', "<?php // admin de Backuply\n" );
	$tar .= bl_entry( 'wp-config.php', "<?php\ndefine( 'DB_NAME', 'wordpress' );\n\$table_prefix = 'bk_';\nrequire_once ABSPATH . 'wp-settings.php';\n" );
	$tar .= bl_entry( 'wp-content/', '', '5', 0755 );
	$tar .= bl_entry( 'wp-content/plugins/hola/hola.php', "<?php /* Plugin Name: Hola */\n" );
	$tar .= bl_entry( 'wp-content/themes/tema/style.css', "/* Theme Name: Tema */\n" );
	// Medios que no se comprimen: la descompresión y el ZIP convertido tienen tramos que seguir.
	for ( $i = 1; $i <= 12; $i++ ) $tar .= bl_entry( 'wp-content/uploads/2026/11/foto-' . $i . '.bin', random_bytes( 1048576 ) );
	$tar .= bl_entry( 'wp-includes/version.php', "<?php\n\$wp_version = '6.8.1';\n" );
	$tar .= bl_entry( 'wp-load.php', "<?php // load de Backuply\n" );
	$tar .= bl_entry( 'wp-settings.php', "<?php // settings de Backuply\n" );
	$tar .= bl_entry( 'softperms.txt', "/ 0755\n" );
	$tar .= bl_entry( 'softver.txt', '1.6.0' );
	$tar .= bl_entry( $name . '.php', $info );
	$tar .= str_repeat( "\0", 1024 );
	// Backuply añade al .tar.gz en cada tramo: varios miembros gzip seguidos.
	$gz = '';
	foreach ( str_split( $tar, (int) ceil( strlen( $tar ) / 3 ) ) as $piece ) $gz .= gzencode( $piece, 1 );
	return array( $gz, $info );
}
@mkdir( $folder . '/backups-lab01', 0755, true );
@mkdir( $folder . '/backups_info-lab01', 0755, true );
$name = 'wp_origen.example_2026-11-16_10-00-00';
list( $gz, $info ) = bl_archive( $name, 'https://origen.example' );
file_put_contents( $folder . '/backups-lab01/' . $name . '.tar.gz', $gz );
file_put_contents( $folder . '/backups_info-lab01/' . $name . '.php', $info );
$other = 'wp_otro.example_2026-11-16_11-00-00';
list( $gz2 ) = bl_archive( $other, 'https://otro.example' );
file_put_contents( $out . '/' . $other . '.tar.gz', $gz2 );
echo "== Backuply sintético: {$name}.tar.gz (" . round( strlen( $gz ) / 1048576, 1 ) . " MB) en su carpeta y {$other}.tar.gz para subirlo\n";
