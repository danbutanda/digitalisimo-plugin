<?php
/**
 * Origen del ensayo de Webuzo (paso runPHP del blueprint de backups-webuzo.sh, después de backups-relocate/prefix.php con
 * el prefijo `dig`, como hay en cuentas reales): contenido que lleva la dirección del sitio (una entrada con un enlace y una
 * imagen de la biblioteca, una página, un plugin propio activo y un mu-plugin) y, con eso, **el respaldo de cuenta que
 * haría el panel de Webuzo**: tar GNU en un solo miembro gzip con sus dos tramos `./` (backuply-data con info, domains,
 * secretos falsos y `mysql/<base>.sql.gz` en el formato de mysqldump de MariaDB 11; después el home con public_html =
 * este WordPress, .acme.sh, ssl, correo y un respaldo anidado). La base trae también las tablas `wp_` del WordPress que
 * instaló Playground: otro WordPress en la misma base, que no debe importarse. Deja /digi-probe/setup.json y el respaldo en
 * /digi-probe/out/; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	global $wpdb;
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-ud-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	file_put_contents( WPMU_PLUGIN_DIR . '/mu-origen.php', "<?php\n// mu-plugin del sitio de origen de Webuzo\n" );
	wp_mkdir_p( WP_PLUGIN_DIR . '/demo-origen' );
	file_put_contents( WP_PLUGIN_DIR . '/demo-origen/demo-origen.php', "<?php\n/* Plugin Name: Demo del origen de Webuzo */\n" );
	$activated = activate_plugin( 'demo-origen/demo-origen.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó el plugin del origen: ' . $activated->get_error_message() );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin del origen.' );
	wp_set_current_user( $admin->ID );
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
	$uploads = wp_upload_dir( '2026/11' );
	wp_mkdir_p( $uploads['path'] );
	file_put_contents( $uploads['path'] . '/wz-principal.png', $png );
	$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen principal', 'post_status' => 'inherit' ), $uploads['path'] . '/wz-principal.png' );
	update_post_meta( $attachment, '_wp_attached_file', '2026/11/wz-principal.png' );
	$home = home_url();
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada de Webuzo principal', 'post_content' => '<p><a href="' . $home . '/contacto/">Contacto en ' . $home . '</a></p><p><img src="' . $uploads['url'] . '/wz-principal.png" alt="principal"></p>' ) );
	if ( ! $post || is_wp_error( $post ) ) throw new RuntimeException( 'No se creó la entrada.' );
	wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Contacto', 'post_name' => 'contacto', 'post_content' => 'Escríbenos.' ) );
	update_option( 'blogname', 'Sitio de origen Webuzo' );
	wp_cache_flush();

	/* ---------------------------------------------------------- El volcado, como mysqldump de MariaDB 11 (--databases) */
	$db = 'origen_wp';
	$quote = function( $v ) { return null === $v ? 'NULL' : "'" . strtr( (string) $v, array( '\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z' ) ) . "'"; };
	$sql = "/*M!999999\\- enable the sandbox mode */ \n-- MariaDB dump 10.19-11.8.8-MariaDB, for linux-systemd (x86_64)\n--\n-- Host: localhost    Database: $db\n-- ------------------------------------------------------\n-- Server version\t11.8.8-MariaDB\n\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET NAMES utf8mb4 */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;\n\n--\n-- Current Database: `$db`\n--\n\nCREATE DATABASE /*!32312 IF NOT EXISTS*/ `$db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;\n\nUSE `$db`;\n\n";
	$tables = array();
	foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) if ( preg_match( '/^(?:dig|wp_)[A-Za-z0-9_]+$/D', (string) $table ) ) $tables[] = (string) $table;
	sort( $tables );
	foreach ( $tables as $table ) {
		$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . $table . '`', ARRAY_N );
		if ( ! is_array( $create ) || empty( $create[1] ) ) throw new RuntimeException( 'SHOW CREATE TABLE no respondió para ' . $table . '.' );
		$sql .= "--\n-- Table structure for table `$table`\n--\n\nDROP TABLE IF EXISTS `$table`;\n/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!40101 SET character_set_client = utf8mb4 */;\n" . rtrim( $create[1], " ;\n" ) . ";\n/*!40101 SET character_set_client = @saved_cs_client */;\n\n--\n-- Dumping data for table `$table`\n--\n\nLOCK TABLES `$table` WRITE;\n/*!40000 ALTER TABLE `$table` DISABLE KEYS */;\nSET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;\n";
		$rows = (array) $wpdb->get_results( 'SELECT * FROM `' . $table . '`', ARRAY_N );
		foreach ( array_chunk( $rows, 50 ) as $chunk ) {
			$values = array();
			foreach ( $chunk as $row ) $values[] = '(' . implode( ',', array_map( $quote, $row ) ) . ')';
			$sql .= 'INSERT INTO `' . $table . '` VALUES ' . implode( ',', $values ) . ";\n";
		}
		$sql .= "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\nUNLOCK TABLES;\nCOMMIT;\nSET AUTOCOMMIT=@OLD_AUTOCOMMIT;\n\n";
	}
	$sql .= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;\n\n-- Dump completed on 2026-11-18  4:05:26\n";

	/* ---------------------------------------------------------- El tar del panel: dos tramos `./`, GNU, un miembro gzip */
	$header = function( $name, $type, $size, $link = '' ) {
		$raw = function( $name, $type, $size, $link ) {
			$h = str_pad( substr( $name, 0, 100 ), 100, "\0" ) . sprintf( '%07o', '5' === $type ? 0755 : 0644 ) . "\0" . sprintf( '%07o', 1000 ) . "\0" . sprintf( '%07o', 1000 ) . "\0" . sprintf( '%011o', $size ) . "\0" . sprintf( '%011o', time() ) . "\0" . '        ' . $type . str_pad( $link, 100, "\0" ) . "ustar  \0" . str_pad( 'origen', 32, "\0" ) . str_pad( 'origen', 32, "\0" ) . str_repeat( "\0", 183 );
			$sum = 0;
			for ( $i = 0; $i < 512; $i++ ) $sum += ord( $h[ $i ] );
			return substr_replace( $h, sprintf( '%06o', $sum ) . "\0 ", 148, 8 );
		};
		$out = '';
		if ( strlen( $name ) > 99 ) $out .= $raw( '././@LongLink', 'L', strlen( $name ) + 1, '' ) . str_pad( $name . "\0", (int) ( ceil( ( strlen( $name ) + 1 ) / 512 ) * 512 ), "\0" );
		return $out . $raw( $name, $type, $size, $link );
	};
	wp_mkdir_p( $dir . '/out' );
	$name = 'origen_backup_full_remote_' . gmdate( 'Y-m-d_H-i-s' ) . '.tar.gz';
	$gz = gzopen( $dir . '/out/' . $name, 'wb6' );
	if ( ! $gz ) throw new RuntimeException( 'No se pudo escribir el respaldo de la cuenta.' );
	$put = function( $name, $type, $data = '', $link = '' ) use ( $gz, $header ) {
		$size = '0' === $type ? strlen( $data ) : 0;
		gzwrite( $gz, $header( $name, $type, $size, $link ) );
		if ( $size ) gzwrite( $gz, $data . str_repeat( "\0", ( 512 - $size % 512 ) % 512 ) );
	};
	$domains = array( 'origen.test' => array( 'path' => '/home/origen/public_html', 'type' => 'main', 'force_https' => 0 ) );
	$put( './', '5' );
	$put( './backuply-data/', '5' );
	$put( './backuply-data/ssl/', '5' );
	$put( './backuply-data/ssl/origen.test-combined.pem', '0', "-----BEGIN PRIVATE KEY-----\nLLAVE-PRIVADA-NO-VIAJA\n-----END PRIVATE KEY-----\n" );
	$put( './backuply-data/mysql/', '5' );
	$put( './backuply-data/mysql/' . $db . '.sql.gz', '0', gzencode( $sql, 6 ) );
	$put( './backuply-data/grant_priv.json', '0', wp_json_encode( array( 'assigned_users' => array( array( 'db' => $db, 'dbuser' => 'origen_u', 'dbuser_hash_pass' => '*HASH-NO-VIAJA', 'host' => 'localhost', 'grant_priv' => array( 'ALL PRIVILEGES' ) ) ), 'alldbs' => array( $db => 'origen_u' ) ) ) );
	$put( './backuply-data/info', '0', wp_json_encode( array( 'domain' => 'origen.test', 'user' => 'origen', 'email' => 'dueno@origen.test', 'homedir' => '/home/origen', 'domains' => $domains ) ) );
	$put( './backuply-data/shadow', '0', "origen:\$6\$SALT\$HASH-NO-VIAJA:20000::::::\n" );
	$put( './backuply-data/domains', '0', wp_json_encode( $domains ) );
	$put( './', '5' );
	$put( './.acme.sh/origen.test/origen.test.key', '0', "ACME-NO-VIAJA\n" );
	$put( './ssl/keys/llave.key', '0', "SSL-NO-VIAJA\n" );
	$put( './mail/origen.test/dueno/cur/1:2,S', '0', "Correo que no viaja\n" );
	$put( './origen_backup_full_remote_2025-01-01_00-00-00.tar.gz', '0', "respaldo viejo\n" );
	$put( './www', '2', '', '/home/origen/public_html' );
	$put( './public_html/', '5' );
	$root = rtrim( ABSPATH, '/' );
	$files = 0;
	$walk = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $walk as $path => $info ) {
		$relative = substr( (string) $path, strlen( $root ) + 1 );
		// Lo que una cuenta real no tiene: la base SQLite de Playground y el plugin del ensayo.
		if ( preg_match( '#^wp-content/(database|plugins/digitalisimo-backups)(/|$)#', $relative ) ) continue;
		if ( $info->isLink() ) continue;
		if ( $info->isDir() ) { $put( './public_html/' . $relative . '/', '5' ); continue; }
		if ( ! $info->isFile() ) continue;
		$put( './public_html/' . $relative, '0', (string) file_get_contents( (string) $path ) );
		$files++;
	}
	gzwrite( $gz, str_repeat( "\0", 10240 ) );
	gzclose( $gz );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'home' => $home, 'archive' => $name, 'files' => $files, 'tables' => $tables, 'size' => filesize( $dir . '/out/' . $name ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
