<?php
/**
 * T2, revisión adversaria: cada hallazgo con la prueba que fallaba con la rama revisada (`abc95d88`) y pasa ahora.
 *
 * H1  Una fila borrada y vuelta a crear entre dos tramos (un transitorio que caduca) no sale dos veces: una tabla
 *     cortada a mitad se repite entera en el tramo siguiente; y la comprobación del SQL detecta uno que no importaría.
 * H2  La verificación por partes descomprime con memoria acotada, también datos muy comprimibles con memory_limit bajo.
 * H3  Un cambio de esquema a mitad de una tabla no deja INSERT con columnas que el CREATE no tiene.
 * H4  Que no hubo una instantánea única se ve en la pantalla, REST, WP-CLI y el historial.
 * H5  El código del sitio que sigue cambiando durante las dos pasadas del empaquetado deja un aviso visible.
 * H6  Un archivo que cambia en cada tramo se copia de corrido y el empaquetado termina; si ni así, falla nombrándolo.
 * H7  Si la verificación no puede leer el manifiesto como la restauración, lo dice en un aviso visible.
 * H8  El bucle verify → pack agota sus intentos.
 * P1  Claves ENUM y FLOAT se paginan por desplazamiento dentro de la instantánea.
 * P3  La ruta de la entrada a medias va en base64 (no se estropea si no es UTF-8).
 * P5  Una muerte justo después de dar al SQL su nombre final no hace empezar la exportación de nuevo.
 * P7  Un archivo sustituido por otro del mismo tamaño y fecha (otro inodo) vuelve a empezar su entrada.
 * P8  Con tablas no transaccionales, una tabla borrada entre tramos reinicia la exportación con la lista actual.
 * CHK La comprobación del SQL detecta, sentencia a sentencia, lo que no se importaría (H1 y H3).
 * SQL El database.sql del ZIP es el SQL comprobado; uno cambiado después no se da por verificado.
 *
 * Con BK_REVIEW_ONLY=H1,H2… sólo corren esas secciones.
 */
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();
$db = $GLOBALS['wpdb'];
$root = $GLOBALS['bk_root'] . '/public/';

function rv_section( $name ) {
	$only = (string) getenv( 'BK_REVIEW_ONLY' );
	return '' === $only || in_array( $name, explode( ',', $only ), true );
}
function rv_filters( array $filters ) {
	foreach ( array( 'digitalisimo_backups_step_units', 'digitalisimo_backups_db_batch', 'digitalisimo_backups_zip_chunk', 'digitalisimo_backups_verify_parts_from', 'digitalisimo_backups_step_min', 'digitalisimo_backups_request_limit', 'digitalisimo_backups_table_min_seconds' ) as $hook ) unset( $GLOBALS['bk_filters'][ $hook ] );
	foreach ( $filters as $hook => $value ) $GLOBALS['bk_filters'][ $hook ] = function() use ( $value ) { return $value; };
}
function rv_until( $id, callable $until, $limit = 400 ) {
	for ( $i = 0; $i < $limit; $i++ ) {
		$job = bk_job( $id );
		if ( $until( $job ) ) return $job;
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) bk_fail( 'El trabajo terminó (' . $job['status'] . ': ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) . ') antes de llegar al punto buscado.' );
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	bk_fail( 'El trabajo no llegó al punto buscado en ' . $limit . ' ticks.' );
}
function rv_done( $id, $limit = 600 ) {
	$job = rv_until( $id, function( $job ) { return Digitalisimo_Backups_Job_Store::is_terminal( $job ); }, $limit );
	bk_assert( 'success' === $job['status'], 'El trabajo no terminó bien: ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) );
	return $job;
}
function rv_zip( array $job ) { return bk_storage() . '/' . $job['spec']['file']; }
function rv_sql( array $job ) {
	$zip = new ZipArchive();
	bk_assert( true === $zip->open( rv_zip( $job ), ZipArchive::CHECKCONS ), 'El ZIP no supera CHECKCONS.' );
	$sql = $zip->getFromName( 'database.sql' );
	$zip->close();
	return $sql;
}
function rv_entry( array $job, $name ) {
	$zip = new ZipArchive();
	bk_assert( true === $zip->open( rv_zip( $job ), ZipArchive::CHECKCONS ), 'El ZIP no supera CHECKCONS.' );
	$data = $zip->getFromName( $name );
	$zip->close();
	return $data;
}
function rv_warnings( array $job ) { return array_map( function( $w ) { return is_array( $w ) ? (string) ( $w['text'] ?? '' ) : (string) $w; }, (array) ( $job['warnings'] ?? array() ) ); }
function rv_notices( array $job ) { return array_values( array_filter( array_map( function( $w ) { return is_array( $w ) ? (string) ( $w['notice'] ?? '' ) : ''; }, (array) ( $job['warnings'] ?? array() ) ) ) ); }
function rv_resume( $id, $phase ) { return bk_job( $id )['checkpoint'][ $phase ]['resume'] ?? null; }
function rv_staging( $id ) { return bk_storage() . '/jobs/' . $id . '/staging'; }
/** Muerte del proceso del tramo en curso: arrendamiento de un proceso muerto que el detector reconoce. */
function rv_kill( $id, $phase ) {
	bk_kill_in( $id, $phase );
	Digitalisimo_Backups_Jobs::recover_if_idle();
	bk_ready( $id );
}
function rv_private( $class, $method ) {
	$reflection = new ReflectionMethod( $class, $method );
	if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true );
	return $reflection;
}
/** Sustituye un archivo conservando su tamaño y su fecha de modificación (otro inodo si `$new_inode`). */
function rv_replace( $path, $content, $new_inode ) {
	$mtime = filemtime( $path );
	if ( $new_inode ) {
		file_put_contents( $path . '.nuevo', $content );
		touch( $path . '.nuevo', $mtime );
		rename( $path . '.nuevo', $path );
	} else {
		file_put_contents( $path, $content );
		touch( $path, $mtime );
	}
	clearstatcache();
}

/**
 * Base simulada con datos y definiciones que cambian entre tramos, con la semántica de MySQL para la paginación
 * (`WHERE clave > …`, `ORDER BY`, `LIMIT` con o sin desplazamiento) y una espera por consulta de filas.
 */
class Review_DB extends Digitalisimo_Jobs_Test_DB {
	public $data = array();
	public $create = array();
	public $keys = array();
	public $delay = 0;
	public $gone = array();
	public function get_col( $query ) { $this->queries[] = $query; if ( 'SHOW TABLES' === $query ) $this->exports++; return array_keys( $this->data ); }
	public function get_row( $query, $format = null ) {
		$this->queries[] = $query;
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW TABLE STATUS' ) ) return array( 'Engine' => $this->engine );
		preg_match( '/`([^`]+)`/', $query, $m );
		if ( ! isset( $this->data[ $m[1] ] ) ) { $this->last_error = "Table '" . $m[1] . "' doesn't exist"; return null; }
		return array( $m[1], $this->create[ $m[1] ] );
	}
	public function get_results( $query, $format = null ) {
		$this->queries[] = $query;
		$this->last_error = '';
		preg_match( '/`([^`]+)`/', $query, $m );
		$table = $m[1];
		$key = $this->keys[ $table ] ?? 'id';
		if ( false !== strpos( $query, 'SHOW INDEX' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => $key, 'Seq_in_index' => 1 ) );
		if ( 0 === strpos( $query, 'SELECT * FROM' ) ) {
			if ( $this->delay ) usleep( $this->delay );
			$rows = array_values( $this->data[ $table ] ?? array() );
			usort( $rows, function( $a, $b ) use ( $key ) { return is_numeric( $a[ $key ] ) ? ( (float) $a[ $key ] <=> (float) $b[ $key ] ) : strcmp( $a[ $key ], $b[ $key ] ); } );
			return bk_page( $rows, $query, $key );
		}
		return array();
	}
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'LOCK TABLES' ) ) foreach ( $this->gone as $table ) if ( false !== strpos( $sql, '`' . $table . '`' ) ) { $this->queries[] = $sql; $this->last_error = "Table '" . $table . "' doesn't exist"; return false; }
		return parent::query( $sql );
	}
}
function rv_options_db() {
	$db = new Review_DB();
	$db->keys = array( 'wp_options' => 'option_id', 'wp_plugin_log' => 'id' );
	$db->create['wp_options'] = "CREATE TABLE `wp_options` (\n  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `option_name` varchar(191) NOT NULL DEFAULT '',\n  `option_value` longtext NOT NULL,\n  `autoload` varchar(20) NOT NULL DEFAULT 'yes',\n  PRIMARY KEY (`option_id`),\n  UNIQUE KEY `option_name` (`option_name`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
	$db->create['wp_plugin_log'] = "CREATE TABLE `wp_plugin_log` (\n  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `msg` text NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
	// Valores de 120 KB: lo leído antes del corte ya pasó del búfer al archivo, que hay que truncar al descartar la tabla.
	foreach ( array( 'siteurl', '_transient_feed_abc', 'blogname', 'active_plugins', 'cron', 'theme_mods' ) as $i => $name ) $db->data['wp_options'][ $i + 1 ] = array( 'option_id' => (string) ( $i + 1 ), 'option_name' => $name, 'option_value' => 'v' . ( $i + 1 ) . str_repeat( 'x', 122880 ), 'autoload' => 'yes' );
	for ( $i = 1; $i <= 6; $i++ ) $db->data['wp_plugin_log'][ $i ] = array( 'id' => (string) $i, 'msg' => 'linea ' . $i );
	return $db;
}
/**
 * Hace avanzar el trabajo como una pila de 30 s con WP-Cron: el primer tramo de cada tabla de `$cut` llega con la
 * petición casi agotada (0,4 s útiles) y la tabla queda a medias; entre ese tramo y el siguiente corre `$between`
 * (lo que haría WordPress); el resto de los tramos tienen la petición entera.
 */
function rv_drive_cut( $id, Review_DB $db, array $cut, callable $between ) {
	rv_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2, 'digitalisimo_backups_request_limit' => 10, 'digitalisimo_backups_step_min' => 0.05 ) );
	$db->delay = 150000;
	$tried = array();
	$job = bk_job( $id );
	for ( $i = 0; $i < 300 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
		$r = $job['checkpoint']['database']['resume'] ?? null;
		$next = 'database' !== $job['phase'] ? '' : ( is_array( $r ) ? (string) ( $r['tables'][ $r['table'] ] ?? '' ) : (string) array_keys( $db->data )[0] );
		$short = in_array( $next, $cut, true ) && empty( $tried[ $next ] );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - ( $short ? 6.6 : 0 );
		Digitalisimo_Backups_Jobs::tick( 0 );
		if ( $short ) { $tried[ $next ] = true; $between( $next ); }
		$job = bk_job( $id );
	}
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
	$db->delay = 0;
	rv_filters( array() );
	bk_assert( count( $tried ) === count( $cut ), 'La prueba no llegó a cortar ' . implode( ', ', $cut ) . '.' );
	return $job;
}

// H1. Fila borrada y vuelta a crear entre dos tramos: la tabla cortada se repite entera y ninguna clave única se repite.
if ( rv_section( 'H1' ) ) {
$GLOBALS['wpdb'] = $mutating = rv_options_db();
$h1 = bk_manual( 'local', 'transitorio-entre-tramos' );
$job = rv_drive_cut( $h1['id'], $mutating, array( 'wp_options' ), function( $table ) use ( $mutating ) {
	// El transitorio (option_id 2, ya leído) caduca y se vuelve a crear: mismo option_name, option_id nuevo.
	unset( $mutating->data['wp_options'][2] );
	$mutating->data['wp_options'][7] = array( 'option_id' => '7', 'option_name' => '_transient_feed_abc', 'option_value' => 'nuevo', 'autoload' => 'no' );
} );
bk_assert( 'success' === $job['status'], 'El respaldo con el transitorio vuelto a crear no terminó: ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) );
$sql = rv_sql( $job );
preg_match_all( "/^INSERT INTO `wp_options` \\(`option_id`,`option_name`,`option_value`,`autoload`\\) VALUES \\('(\\d+)','([^']*)'/m", $sql, $m );
bk_assert( 6 === count( $m[2] ) && count( $m[2] ) === count( array_unique( $m[2] ) ) && array( '1', '3', '4', '5', '6', '7' ) === $m[1], 'Un option_name sale dos veces (el SQL no se importaría): ' . json_encode( array_count_values( $m[2] ) ) );
bk_assert( 'transaction-per-table' === bk_zip_entries( rv_zip( $job ) )['database_consistency'] && ! preg_grep( '/empieza de nuevo/', rv_warnings( $job ) ), 'El manifiesto no declara la exportación por tablas, o la tabla cortada no se descartó limpia y la exportación tuvo que empezar de nuevo.' );
// La comprobación del SQL rechaza uno que no se importaría: un option_name repetido inyectado antes de comprobarlo.
rv_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2 ) );
$h1b = bk_manual( 'local', 'sql-inimportable' );
$r = rv_until( $h1b['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'check' === ( $r['stage'] ?? '' ) && 0 === (int) $r['check']['table']; } )['checkpoint']['database']['resume'];
$part = rv_staging( $h1b['id'] ) . '/' . $r['file'];
$raw = file_get_contents( $part );
$last = "INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('7','_transient_feed_abc','nuevo','no');\n";
bk_assert( false !== strpos( $raw, $last ), 'La prueba no encontró la última fila de wp_options.' );
file_put_contents( $part, str_replace( $last, $last . "INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('8','blogname','otro','yes');\n", $raw ) );
$job = rv_done( $h1b['id'] );
bk_assert( preg_grep( '/no se podría importar: un valor de una clave única se repite \(blogname\) en la tabla wp_options/', rv_warnings( $job ) ) && false === strpos( rv_sql( $job ), "'8','blogname'" ), 'La comprobación no detectó un SQL con una clave única repetida, o lo empaquetó.' );
rv_filters( array() );
$GLOBALS['wpdb'] = $db;
}

// H2. Verificación por partes con datos muy comprimibles y memory_limit bajo: la memoria de la descompresión está acotada.
if ( rv_section( 'H2' ) ) {
$limit = ini_get( 'memory_limit' );
// Directo: 64 MB de ceros (unos 64 KB comprimidos) leídos con 24 MB libres.
$zip_path = $GLOBALS['bk_root'] . '/ceros.zip';
$writer = new Digitalisimo_Backups_Zip_Writer( $zip_path, 0 );
$writer->begin( 'ceros.log', time(), 0100644, 64 * 1048576, 8 );
$block = str_repeat( "\0", 1048576 );
for ( $i = 0; $i < 64; $i++ ) $writer->data( $block );
$record = $writer->finish();
$writer->commit();
$writer->close();
unset( $block );
ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 24 * 1048576 ) );
list( $crc, $sha, $raw ) = Digitalisimo_Backups_Zip_Writer::hash_region( $zip_path, Digitalisimo_Backups_Zip_Writer::data_offset( $record ), 8, 0, (int) $record['z'] );
$peak = memory_get_peak_usage( true );
ini_set( 'memory_limit', $limit );
bk_assert( 64 * 1048576 === $raw && hash_final( $crc ) === $record['c'] && hash_final( $sha ) === $record['h'], 'hash_region no recorrió los 64 MB de ceros.' );
// Por el motor: un registro de 48 MB de ceros, empaquetado en trozos de 8 MB y verificado por partes, con 32 MB libres.
$log = $root . 'wp-content/ceros.log';
$out = fopen( $log, 'wb' );
for ( $i = 0; $i < 48; $i++ ) fwrite( $out, str_repeat( "\0", 1048576 ) );
fclose( $out );
rv_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 8 * 1048576, 'digitalisimo_backups_verify_parts_from' => 1024 ) );
$h2 = bk_manual( 'local', 'ceros' );
ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 32 * 1048576 ) );
$job = rv_done( $h2['id'] );
ini_set( 'memory_limit', $limit );
rv_filters( array() );
bk_assert( isset( bk_zip_entries( rv_zip( $job ) )['entries']['wordpress/wp-content/ceros.log'] ), 'El registro de ceros no está en el respaldo.' );
unlink( $log );
}

// H3. Cambio de esquema a mitad de una tabla (dbDelta de una actualización): la tabla sale entera con su CREATE nuevo.
if ( rv_section( 'H3' ) ) {
$GLOBALS['wpdb'] = $mutating = rv_options_db();
$h3 = bk_manual( 'local', 'esquema-entre-tramos' );
$job = rv_drive_cut( $h3['id'], $mutating, array( 'wp_plugin_log' ), function( $table ) use ( $mutating ) {
	$mutating->create['wp_plugin_log'] = str_replace( "  `msg` text NOT NULL,\n", "  `msg` text NOT NULL,\n  `level` varchar(10) NOT NULL DEFAULT 'info',\n", $mutating->create['wp_plugin_log'] );
	foreach ( $mutating->data['wp_plugin_log'] as $k => $row ) $mutating->data['wp_plugin_log'][ $k ]['level'] = 'info';
} );
bk_assert( 'success' === $job['status'], 'El respaldo con el cambio de esquema no terminó: ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) );
$sql = rv_sql( $job );
preg_match( '/CREATE TABLE `wp_plugin_log` \(.*?\) ENGINE=InnoDB[^;]*;/s', $sql, $create );
bk_assert( $create && false !== strpos( $create[0], '`level`' ) && 6 === preg_match_all( '/^INSERT INTO `wp_plugin_log` \(`id`,`msg`,`level`\)/m', $sql ) && 6 === substr_count( $sql, 'INSERT INTO `wp_plugin_log`' ), 'El SQL tiene INSERT con columnas que su CREATE no tiene, o le faltan filas.' );
$GLOBALS['wpdb'] = $db;
}

// CHK (H1 y H3). La comprobación del SQL, sentencia a sentencia, sobre SQL hecho a mano: detecta lo que no importaría.
if ( rv_section( 'CHK' ) ) {
$check = rv_private( 'Digitalisimo_Backups_Sql_Export', 'check_table' );
$create = "CREATE TABLE `wp_t` (\n  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `name` varchar(191) NOT NULL,\n  `slug` varchar(255) NOT NULL,\n  `n` int(11) NOT NULL,\n  `v` longtext,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `name` (`name`),\n  UNIQUE KEY `slug` (`slug`(4)),\n  UNIQUE KEY `n` (`n`)\n) ENGINE=InnoDB;\n";
$row = function( $id, $name, $slug, $n, $v = 'NULL' ) { return "INSERT INTO `wp_t` (`id`,`name`,`slug`,`n`,`v`) VALUES ('" . $id . "','" . $name . "','" . $slug . "','" . $n . "'," . $v . ");\n"; };
$run = function( $body ) use ( $check, $create ) {
	$path = $GLOBALS['bk_root'] . '/check.sql';
	file_put_contents( $path, "DROP TABLE IF EXISTS `wp_t`;\n" . $create . $body . "\n" );
	$in = fopen( $path, 'rb' );
	try { $check->invoke( null, $in, 'wp_t' ); return ''; } catch ( Digitalisimo_Backups_Resume_Invalid $e ) { return $e->getMessage(); } finally { fclose( $in ); }
};
// Válido: clave única entera fuera de orden (no es la primaria), texto con comillas escapadas y NULL en una clave única.
bk_assert( '' === $run( $row( 1, 'a', 'abcd1', 30, "'x'" ) . $row( 2, "b\\'c", 'abce2', 10 ) . $row( 5, 'd', 'abcf', 20 ) ), 'La comprobación rechazó un SQL válido: ' . $run( $row( 1, 'a', 'abcd1', 30, "'x'" ) . $row( 2, "b\\'c", 'abce2', 10 ) . $row( 5, 'd', 'abcf', 20 ) ) );
$cases = array(
	'clave primaria repetida' => array( $row( 1, 'a', 'aaaa', 1 ) . $row( 1, 'b', 'bbbb', 2 ), 'la clave primaria 1 se repite' ),
	'clave primaria fuera de orden' => array( $row( 3, 'a', 'aaaa', 1 ) . $row( 2, 'b', 'bbbb', 2 ), 'la clave primaria 2 se repite o sale fuera de orden' ),
	'clave única repetida' => array( $row( 1, 'a', 'aaaa', 1 ) . $row( 2, 'a', 'bbbb', 2 ), 'un valor de una clave única se repite (a)' ),
	'prefijo de clave única repetido' => array( $row( 1, 'a', 'abcd1', 1 ) . $row( 2, 'b', 'abcd2', 2 ), 'un valor de una clave única se repite (abcd)' ),
	'entero único repetido' => array( $row( 1, 'a', 'aaaa', 7 ) . $row( 2, 'b', 'bbbb', 7 ), 'un valor de una clave única se repite (7)' ),
	'columna que el CREATE no tiene' => array( "INSERT INTO `wp_t` (`id`,`name`,`slug`,`n`,`v`,`level`) VALUES ('1','a','aaaa','1',NULL,'info');\n", 'nombra columnas que no son las de su CREATE TABLE' ),
	'falta una columna' => array( "INSERT INTO `wp_t` (`id`,`name`,`slug`,`n`) VALUES ('1','a','aaaa','1');\n", 'nombra columnas que no son las de su CREATE TABLE' ),
	'menos valores que columnas' => array( "INSERT INTO `wp_t` (`id`,`name`,`slug`,`n`,`v`) VALUES ('1','a','aaaa','1');\n", 'no tiene un valor por columna' ),
	'sentencia ajena' => array( "DELETE FROM `wp_t`;\n", 'hay una sentencia que el exportador no escribe' ),
);
foreach ( $cases as $what => $case ) {
	$message = $run( $case[0] );
	bk_assert( false !== strpos( $message, $case[1] ), 'La comprobación no detectó «' . $what . '»: ' . ( '' === $message ? 'lo aceptó' : $message ) );
}
}

// H4. Respaldo sin instantánea única: «Completo con avisos» en la pantalla, el texto en REST y WP-CLI, y la fila del
// historial con la coherencia y el aviso (también en la lista de restauración).
if ( rv_section( 'H4' ) ) {
rv_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$h4 = bk_manual( 'local', 'avisos-visibles' );
$job = rv_done( $h4['id'] );
rv_filters( array() );
$view = Digitalisimo_Backups_Jobs::view( $job );
bk_assert( 'Completo con avisos' === $view['status_label'] && 1 === count( $view['notices'] ) && false !== strpos( $view['notices'][0], 'no hay una instantánea común a todas' ) && false !== strpos( $view['detail'], 'Con avisos: La base de datos se exportó en' ), 'La vista del trabajo no muestra que no hubo una instantánea única: ' . json_encode( $view, JSON_UNESCAPED_UNICODE ) );
// Una fase repetida tras volver atrás da otra vez su aviso: la vista muestra uno por clase.
$twice = $job;
$twice['warnings'] = array_merge( (array) $job['warnings'], (array) $job['warnings'] );
bk_assert( $view['notices'] === Digitalisimo_Backups_Jobs::view( $twice )['notices'], 'Un aviso repetido se muestra dos veces.' );
$service = Digitalisimo_Backups::service_job_view( $job );
bk_assert( $service['notices'] === $view['notices'] && 'Completo con avisos' === $service['status_label'], 'REST no da el texto de los avisos del trabajo.' );
$file = basename( rv_zip( $job ) );
$row = null;
foreach ( bk_history() as $item ) if ( $file === ( $item['file'] ?? '' ) ) $row = $item;
bk_assert( is_array( $row ) && 'transaction-per-table' === $row['consistency'] && 'database_per_table' === $row['notices'][0]['code'] && '' !== Digitalisimo_Backups::history_notices_text( $row ), 'La fila del historial no lleva la coherencia de la base ni el aviso.' );
$listed = null;
foreach ( Digitalisimo_Backups::service_backups() as $item ) if ( $file === ( $item['file'] ?? '' ) ) $listed = $item;
bk_assert( is_array( $listed ) && 'transaction-per-table' === $listed['consistency'] && $listed['notices'] && false !== strpos( $listed['notices'][0], 'instantánea' ), 'La lista de respaldos de REST y WP-CLI no da la coherencia ni el aviso.' );
// Pantalla: la tarjeta del trabajo y la lista de restauración.
$panel = rv_private( 'Digitalisimo_Backups', 'jobs_panel' );
ob_start();
$panel->invoke( null, false );
$html = ob_get_clean();
bk_assert( false !== strpos( $html, 'Completo con avisos' ) && false !== strpos( $html, 'no hay una instantánea común a todas' ), 'La tarjeta de trabajos de la pantalla no muestra el aviso.' );
$restore = rv_private( 'Digitalisimo_Backups', 'restore_panel' );
ob_start();
$restore->invoke( null, bk_history() );
$html = ob_get_clean();
bk_assert( false !== strpos( $html, 'con avisos' ), 'La lista de restauración no marca el respaldo con avisos.' );
// WP-CLI: el aviso por STDERR (también con --porcelain) y en `list`.
require_once __DIR__ . '/backups-coverage/cli.php';
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';
WP_CLI::$output = array();
Digitalisimo_Backups_Cli::finish( $service, false );
bk_assert( preg_grep( '/^Warning: La base de datos se exportó en \d+ tramos/', WP_CLI::$output ) && preg_grep( '/^Success: Respaldo completo creado y verificado, con avisos: /', WP_CLI::$output ), 'WP-CLI no mostró el aviso al crear: ' . json_encode( WP_CLI::$output, JSON_UNESCAPED_UNICODE ) );
WP_CLI::$output = array();
Digitalisimo_Backups_Cli::finish( $service, true );
bk_assert( preg_grep( '/^Warning: La base de datos se exportó/', WP_CLI::$output ) && in_array( $file, WP_CLI::$output, true ), 'WP-CLI --porcelain se calló el aviso.' );
WP_CLI::$output = array();
( new Digitalisimo_Backups_Cli() )->list_( array(), array( 'format' => 'json' ) );
$rows = json_decode( WP_CLI::$output[0], true );
$shown = array_values( array_filter( $rows, function( $r ) use ( $file ) { return $file === $r['file']; } ) );
bk_assert( $shown && false !== strpos( $shown[0]['notices'], 'instantánea' ), 'wp digitalisimo-backups list no muestra el aviso.' );
}

// H5. El código del sitio sigue cambiando durante las dos pasadas del empaquetado: «Completo con avisos», con la carpeta.
if ( rv_section( 'H5' ) ) {
rv_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$h5 = bk_manual( 'local', 'codigo-que-cambia' );
$plugin = $root . 'wp-content/plugins/example';
rv_until( $h5['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && 'write' === $r['stage']; } );
touch( $plugin, time() + 100 );
rv_until( $h5['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && 'write' === $r['stage'] && 2 === (int) ( $r['passes'] ?? 0 ); } );
touch( $plugin, time() + 200 );
$job = rv_done( $h5['id'] );
rv_filters( array() );
$view = Digitalisimo_Backups_Jobs::view( $job );
bk_assert( in_array( 'files_changed', rv_notices( $job ), true ) && 'Completo con avisos' === $view['status_label'] && preg_grep( '#wp-content/plugins/example#', $view['notices'] ) && preg_grep( '#wp-content/plugins/example#', (array) ( bk_zip_entries( rv_zip( $job ) )['notices'] ?? array() ) ), 'El código que cambió en las dos pasadas no dejó un aviso visible: ' . json_encode( $view['notices'], JSON_UNESCAPED_UNICODE ) );
}

// H6. Un registro al que se escribe en cada tramo: tras dos vueltas se copia de corrido y el empaquetado termina.
if ( rv_section( 'H6' ) ) {
$debug = $root . 'wp-content/debug.log';
$text = '';
for ( $i = 0; $i < 3000; $i++ ) $text .= sprintf( "[09-Nov-2026 10:%02d:%02d UTC] PHP Notice: línea %05d\n", $i / 60 % 60, $i % 60, $i );
file_put_contents( $debug, $text );
rv_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 16384 ) );
$h6 = bk_manual( 'local', 'registro-que-cambia' );
$job = bk_job( $h6['id'] );
$writes = 0;
for ( $i = 0; $i < 300 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
	Digitalisimo_Backups_Jobs::tick( 0 );
	$job = bk_job( $h6['id'] );
	$r = $job['checkpoint']['pack']['resume'] ?? null;
	// Cada petición del sitio (también los ticks del respaldo) escribe en el registro.
	if ( is_array( $r ) && ( ( is_array( $r['partial'] ) && 'wordpress/wp-content/debug.log' === $r['partial']['n'] ) || ( is_array( $r['pending'] ?? null ) && 'wordpress/wp-content/debug.log' === $r['pending'][0] ) ) ) { file_put_contents( $debug, "[09-Nov-2026] PHP Notice: otra petición\n", FILE_APPEND ); $writes++; }
}
rv_filters( array() );
bk_assert( 'success' === $job['status'], 'El empaquetado de un registro que cambia en cada tramo no terminó (' . $job['status'] . ', fase ' . $job['phase'] . ', ' . $writes . ' escrituras).' );
$copied = rv_entry( $job, 'wordpress/wp-content/debug.log' );
bk_assert( 0 === strpos( file_get_contents( $debug ), $copied ) && strlen( $copied ) > strlen( $text ) && $writes >= 2, 'La copia del registro no es una versión entera del archivo.' );
// Si la copia de corrido también muere dos veces (no da tiempo), falla nombrando el archivo, sin girar sin fin.
rv_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 16384 ) );
$h6b = bk_manual( 'local', 'registro-que-no-cabe' );
$job = bk_job( $h6b['id'] );
for ( $i = 0; $i < 300 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
	$r = $job['checkpoint']['pack']['resume'] ?? null;
	if ( is_array( $r ) && 'wordpress/wp-content/debug.log' === ( $r['oneshot'] ?? '' ) ) {
		// Decidido copiarlo de corrido: la petición muere copiándolo, dos veces.
		rv_kill( $h6b['id'], 'pack' );
		rv_kill( $h6b['id'], 'pack' );
		Digitalisimo_Backups_Jobs::tick( 0 );
		$job = bk_job( $h6b['id'] );
		break;
	}
	Digitalisimo_Backups_Jobs::tick( 0 );
	$job = bk_job( $h6b['id'] );
	$r = $job['checkpoint']['pack']['resume'] ?? null;
	if ( is_array( $r ) && is_array( $r['partial'] ) && 'wordpress/wp-content/debug.log' === $r['partial']['n'] ) file_put_contents( $debug, "[09-Nov-2026] PHP Notice: otra petición\n", FILE_APPEND );
}
rv_filters( array() );
bk_assert( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'wp-content/debug.log cambia mientras se respalda' ) && ! empty( $job['error']['permanent'] ), 'Un registro que cambia y no cabe de corrido no falló nombrándolo: ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) );
unlink( $debug );
}

// H7. Sin memoria libre para leer el manifiesto como la restauración, «verificado» no lo cubre y se dice.
if ( rv_section( 'H7' ) ) {
// Un manifiesto de unos 100 KB: 250 archivos con nombres largos.
$many = $root . 'wp-content/uploads/muchos';
@mkdir( $many, 0700, true );
for ( $i = 0; $i < 250; $i++ ) file_put_contents( $many . '/' . sprintf( '%04d', $i ) . str_repeat( '-nombre-largo-de-una-imagen', 6 ) . '.txt', 'x' );
$plain = rv_done( bk_manual( 'local', 'manifiesto-con-memoria' )['id'] );
bk_assert( ! in_array( 'restore_memory', rv_notices( $plain ), true ), 'Con memoria de sobra la verificación avisó de que no leyó el manifiesto.' );
$h7 = bk_manual( 'local', 'manifiesto-sin-memoria' );
rv_until( $h7['id'], function( $job ) { return 'verify' === $job['phase']; } );
$limit = ini_get( 'memory_limit' );
// Memoria libre por debajo de 5 veces el manifiesto, pero suficiente para verificar entrada a entrada.
ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 4 * 1048576 + 262144 ) );
$job = rv_done( $h7['id'] );
ini_set( 'memory_limit', $limit );
$view = Digitalisimo_Backups_Jobs::view( $job );
$file = basename( rv_zip( $job ) );
$row = array();
foreach ( bk_history() as $item ) if ( $file === ( $item['file'] ?? '' ) ) $row = $item;
bk_assert( in_array( 'restore_memory', rv_notices( $job ), true ) && 'Completo con avisos' === $view['status_label'] && preg_grep( '/no prueba que la restauración pueda leerlo/', $view['notices'] ) && false !== strpos( Digitalisimo_Backups::history_notices_text( $row ), 'no prueba que la restauración pueda leerlo' ), 'Sin leer el manifiesto, el respaldo quedó «verificado» sin decirlo: ' . json_encode( $view['notices'], JSON_UNESCAPED_UNICODE ) );
foreach ( glob( $many . '/*' ) as $path ) unlink( $path );
rmdir( $many );
}

// H8. Un fallo de verificación que se repite tras el primer tramo agota los intentos (no gira verify → pack sin fin).
if ( rv_section( 'H8' ) ) {
file_put_contents( $root . 'wp-content/uploads/2026/11/tardio.txt', str_repeat( "contenido de una entrada tardía\n", 2048 ) );
rv_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$h8 = bk_manual( 'local', 'verificacion-que-falla' );
$job = bk_job( $h8['id'] );
$hit = false;
$cycles = 0;
for ( $i = 0; $i < 600 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
	Digitalisimo_Backups_Jobs::tick( 0 );
	$job = bk_job( $h8['id'] );
	$r = $job['checkpoint']['verify']['resume'] ?? null;
	// Tras el primer tramo de cada verificación, un byte de la entrada tardía se estropea (mismo tamaño y fecha).
	if ( ! $hit && 'verify' === $job['phase'] && is_array( $r ) && (int) $r['index'] > 0 ) {
		$staged = rv_staging( $h8['id'] ) . '/' . $job['checkpoint']['pack']['file'];
		$raw = file_get_contents( $staged );
		$at = strpos( $raw, 'wordpress/wp-content/uploads/2026/11/tardio.txt' ) + 47 + 40;
		$raw[ $at ] = chr( ord( $raw[ $at ] ) ^ 0x55 );
		$mtime = filemtime( $staged );
		file_put_contents( $staged, $raw );
		touch( $staged, $mtime );
		clearstatcache();
		$hit = true;
	}
	if ( $hit && 'pack' === $job['phase'] ) { $cycles++; $hit = false; }
	if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) bk_ready( $h8['id'] );
}
rv_filters( array() );
bk_assert( 'failed' === $job['status'] && 'verify' === $job['phase'] && false !== strpos( (string) $job['error']['message'], 'se agotaron 3 intentos' ) && $cycles <= 3, 'El bucle verify → pack no agotó sus intentos (' . $job['status'] . ', ' . $cycles . ' vueltas): ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) );
unlink( $root . 'wp-content/uploads/2026/11/tardio.txt' );
}

// SQL. El database.sql del ZIP es el SQL comprobado: uno cambiado después (mismo tamaño y fecha, así que su huella no
// cambia) no se da por verificado; empaquetar otra vez no lo arregla y el trabajo falla con el motivo.
if ( rv_section( 'SQL' ) ) {
rv_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$sq = bk_manual( 'local', 'sql-cambiado' );
$job = rv_until( $sq['id'], function( $job ) { return 'pack' === $job['phase']; } );
$path = rv_staging( $sq['id'] ) . '/' . $job['checkpoint']['database']['file'];
$raw = file_get_contents( $path );
$at = strpos( $raw, 'fila 1' );
$raw[ $at ] = 'F';
$mtime = filemtime( $path );
file_put_contents( $path, $raw );
touch( $path, $mtime );
clearstatcache();
$job = rv_until( $sq['id'], function( $job ) { return Digitalisimo_Backups_Job_Store::is_terminal( $job ); }, 800 );
rv_filters( array() );
bk_assert( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'no es el SQL que se exportó y se comprobó' ), 'Un SQL cambiado después de comprobarlo pasó la verificación: ' . $job['status'] . ' ' . json_encode( $job['error'], JSON_UNESCAPED_UNICODE ) );
}

// P1. Claves que no se comparan como se ordenan (ENUM, FLOAT): por desplazamiento dentro de la instantánea.
if ( rv_section( 'P1' ) ) {
$keyed = new Review_DB();
$keyed->keys = array( 'wp_enum' => 'k', 'wp_float' => 'f' );
$keyed->create['wp_enum'] = "CREATE TABLE `wp_enum` (\n  `k` enum('zeta','alfa','beta') NOT NULL,\n  `v` text,\n  PRIMARY KEY (`k`)\n) ENGINE=InnoDB";
$keyed->create['wp_float'] = "CREATE TABLE `wp_float` (\n  `f` double NOT NULL,\n  `v` text,\n  PRIMARY KEY (`f`)\n) ENGINE=InnoDB";
foreach ( array( 'zeta', 'alfa', 'beta' ) as $k ) $keyed->data['wp_enum'][ $k ] = array( 'k' => $k, 'v' => 'valor ' . $k );
foreach ( array( '0.1', '0.2', '0.30000000000000004', '1.5' ) as $f ) $keyed->data['wp_float'][ $f ] = array( 'f' => $f, 'v' => 'valor ' . $f );
$GLOBALS['wpdb'] = $keyed;
rv_filters( array( 'digitalisimo_backups_db_batch' => 2 ) );
$job = rv_done( bk_manual( 'local', 'claves-por-desplazamiento' )['id'] );
rv_filters( array() );
$selects = array_values( preg_grep( '/^SELECT \* FROM `wp_(enum|float)`/', $keyed->queries ) );
bk_assert( array( 'SELECT * FROM `wp_enum` ORDER BY `k` LIMIT 0, 2', 'SELECT * FROM `wp_enum` ORDER BY `k` LIMIT 2, 2', 'SELECT * FROM `wp_float` ORDER BY `f` LIMIT 0, 2', 'SELECT * FROM `wp_float` ORDER BY `f` LIMIT 2, 2', 'SELECT * FROM `wp_float` ORDER BY `f` LIMIT 4, 2' ) === $selects, 'Las claves ENUM o DOUBLE no se paginan por desplazamiento: ' . json_encode( $selects ) );
$sql = rv_sql( $job );
bk_assert( 3 === substr_count( $sql, 'INSERT INTO `wp_enum`' ) && 4 === substr_count( $sql, 'INSERT INTO `wp_float`' ), 'Faltan o sobran filas con claves ENUM o DOUBLE.' );
$GLOBALS['wpdb'] = $db;
}

// P3 y P7. La entrada a medias guarda su ruta en base64 y la identidad completa del archivo: uno sustituido por otro del
// mismo tamaño y fecha (otro inodo) vuelve a empezar, y el ZIP lleva el contenido nuevo entero, no una mezcla.
if ( rv_section( 'P3' ) || rv_section( 'P7' ) ) {
$big = $root . 'wp-content/uploads/2026/11/grande.txt';
$old = '';
for ( $i = 0; $i < 2048; $i++ ) $old .= sprintf( "línea %05d de un texto que se comprime bien\n", $i );
$old = substr( str_pad( $old, 65536, 'x' ), 0, 65536 );
file_put_contents( $big, $old );
rv_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 16384 ) );
$p7 = bk_manual( 'local', 'archivo-sustituido' );
$job = rv_until( $p7['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && is_array( $r['partial'] ) && 'wordpress/wp-content/uploads/2026/11/grande.txt' === $r['partial']['n'] && $r['partial']['raw'] >= 16384; } );
if ( rv_section( 'P3' ) ) {
	$partial = $job['checkpoint']['pack']['resume']['partial'];
	bk_assert( base64_encode( $big ) === ( $partial['src'] ?? '' ) && isset( $partial['id']['ino'], $partial['id']['ctime'] ), 'La entrada a medias no guarda su ruta en base64 o su identidad completa.' );
	$decode = rv_private( 'Digitalisimo_Backups_Packer', 'decode_source' );
	bk_assert( "/srv/www/caf\xe9/wp-content/a.log" === $decode->invoke( null, json_decode( json_encode( base64_encode( "/srv/www/caf\xe9/wp-content/a.log" ), JSON_INVALID_UTF8_SUBSTITUTE ) ) ), 'Una ruta que no es UTF-8 no sobrevive al estado del trabajo.' );
}
$new = strtoupper( $old );
rv_replace( $big, $new, true );
$job = rv_done( $p7['id'] );
rv_filters( array() );
bk_assert( $new === rv_entry( $job, 'wordpress/wp-content/uploads/2026/11/grande.txt' ), 'El archivo sustituido a mitad (mismo tamaño y fecha, otro inodo) quedó mezclado en el ZIP.' );
unlink( $big );
}

// P5. Muerte justo después de dar al SQL comprobado su nombre final: se retoma, sin empezar de nuevo.
if ( rv_section( 'P5' ) ) {
rv_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$p5 = bk_manual( 'local', 'muerte-tras-renombrar' );
$r = rv_until( $p5['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'check' === ( $r['stage'] ?? '' ) && (int) $r['check']['table'] === count( $r['tables'] ) - 1; } )['checkpoint']['database']['resume'];
$part = rv_staging( $p5['id'] ) . '/' . $r['file'];
rename( $part, substr( $part, 0, -5 ) );
rv_kill( $p5['id'], 'database' );
$db->queries = array();
$job = rv_done( $p5['id'] );
rv_filters( array() );
bk_assert( ! preg_grep( '/empezó de nuevo|no se pudo retomar/i', rv_warnings( $job ) ) && ! in_array( 'SHOW TABLES', $db->queries, true ), 'Una muerte tras renombrar el SQL hizo empezar la exportación de nuevo.' );
}

// P8. Tablas no transaccionales: una tabla borrada entre tramos hace fallar LOCK TABLES al retomar; la exportación
// empieza de nuevo con la lista actual, con aviso, en lugar de gastar intentos.
if ( rv_section( 'P8' ) ) {
$locking = rv_options_db();
$locking->engine = 'MyISAM';
$locking->keys['wp_temp'] = 'id';
$locking->create['wp_temp'] = "CREATE TABLE `wp_temp` (\n  `id` bigint(20) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=MyISAM";
$locking->data['wp_temp'] = array( 1 => array( 'id' => '1' ) );
$GLOBALS['wpdb'] = $locking;
rv_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$p8 = bk_manual( 'local', 'tabla-borrada-con-bloqueo' );
rv_until( $p8['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'check' !== ( $r['stage'] ?? 'export' ) && 1 === (int) $r['table']; } );
unset( $locking->data['wp_temp'] );
$locking->gone = array( 'wp_temp' );
$job = rv_done( $p8['id'] );
rv_filters( array() );
bk_assert( array( 'wp_options', 'wp_plugin_log' ) === bk_zip_entries( rv_zip( $job ) )['database_tables'] && preg_grep( '/No se pudo abrir la instantánea con la lista de tablas del primer tramo/', rv_warnings( $job ) ) && 0 === (int) ( $job['attempts']['database'] ?? 0 ), 'Una tabla borrada entre tramos con LOCK TABLES no reinició la exportación con la lista actual.' );
$GLOBALS['wpdb'] = $db;
}

rv_filters( array() );
bk_assert( ! Digitalisimo_Backups_Jobs::has_active(), 'Quedaron trabajos activos.' );
bk_cleanup();
echo "DIGITALÍSIMO Backups: revisión de T2 (H1–H8, P1, P3, P5, P7 y P8): tablas enteras por instantánea, SQL comprobado, descompresión acotada, avisos visibles, código que cambia, registros que cambian, manifiesto declarado y vueltas atrás contadas.\n";
