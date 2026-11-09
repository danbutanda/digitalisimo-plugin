<?php
/**
 * T2: exportación, empaquetado y verificación por tramos. Un tramo muerto a mitad de una tabla se retoma truncando el
 * SQL al comienzo de esa tabla, que sale entera otra vez en una sola instantánea; uno muerto a mitad de una carpeta del
 * inventario o de un archivo se retoma desde su punto de control sin repetir ni saltar nada. El SQL y el ZIP finales
 * son los mismos que sin cortes, y lo escrito antes del punto de control no se reescribe. Cada tramo respeta su
 * presupuesto (el del tick entre tablas y el límite real de la petición dentro de una), trabaja la mitad tras una
 * muerte y las claves primarias compuestas, de texto y binarias se paginan en su orden. Con la rama anterior (S4) la
 * base de datos y el empaquetado son un paso cada uno y esta prueba falla en la primera comprobación.
 */
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();
$db = $GLOBALS['wpdb'];
$db->tables['wp_posts'] = 9;
$root = $GLOBALS['bk_root'] . '/public/';
// Un archivo comprimible de 64 KB: con trozos de 16 KB su entrada deflate queda partida en varios tramos.
$big = '';
for ( $i = 0; $i < 2048; $i++ ) $big .= sprintf( "línea %05d de un texto que se comprime bien\n", $i );
$big = substr( str_pad( $big, 65536, 'x' ), 0, 65536 );
file_put_contents( $root . 'wp-content/uploads/2026/11/grande.txt', $big );

function steps_filters( array $filters ) {
	foreach ( array( 'digitalisimo_backups_step_units', 'digitalisimo_backups_db_batch', 'digitalisimo_backups_zip_chunk', 'digitalisimo_backups_verify_parts_from', 'digitalisimo_backups_step_min', 'digitalisimo_backups_request_limit' ) as $hook ) unset( $GLOBALS['bk_filters'][ $hook ] );
	foreach ( $filters as $hook => $value ) $GLOBALS['bk_filters'][ $hook ] = function() use ( $value ) { return $value; };
}
function steps_until( $id, callable $until, $limit = 400 ) {
	for ( $i = 0; $i < $limit; $i++ ) {
		$job = bk_job( $id );
		if ( $until( $job ) ) return $job;
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) bk_fail( 'El trabajo terminó (' . $job['status'] . ': ' . json_encode( $job['error'] ) . ') antes de llegar al punto buscado.' );
		Digitalisimo_Backups_Jobs::tick( 0 );
		bk_assert_no_unverified_final( bk_job( $id ) );
	}
	bk_fail( 'El trabajo no llegó al punto buscado en ' . $limit . ' ticks.' );
}
function steps_done( $id, $limit = 600 ) {
	$job = steps_until( $id, function( $job ) { return Digitalisimo_Backups_Job_Store::is_terminal( $job ); }, $limit );
	bk_assert( 'success' === $job['status'], 'El trabajo no terminó bien: ' . json_encode( $job['error'] ) );
	return $job;
}
function steps_zip( array $job ) { return bk_storage() . '/' . $job['spec']['file']; }
function steps_sql( $path ) {
	$zip = new ZipArchive();
	bk_assert( true === $zip->open( $path, ZipArchive::CHECKCONS ), 'El ZIP no supera CHECKCONS.' );
	$sql = $zip->getFromName( 'database.sql' );
	$zip->close();
	return $sql;
}
function steps_resume( $id, $phase ) { return bk_job( $id )['checkpoint'][ $phase ]['resume'] ?? null; }
/** Muerte a mitad de un tramo: arrendamiento de un proceso muerto, el punto de control del tramo anterior y basura escrita después. */
function steps_kill( $id, $phase, array $garbage ) {
	bk_kill_in( $id, $phase );
	foreach ( $garbage as $path ) file_put_contents( $path, "basura de un tramo muerto\n", FILE_APPEND );
	Digitalisimo_Backups_Jobs::recover_if_idle();
	$job = bk_job( $id );
	bk_assert( 'queued' === $job['status'] && $phase === $job['phase'] && 1 === (int) $job['attempts'][ $phase ] && is_array( steps_resume( $id, $phase ) ), 'La muerte en «' . $phase . '» no conservó el punto de control del tramo anterior.' );
	foreach ( $garbage as $path ) bk_assert( is_file( $path ), 'La reanudación borró ' . basename( $path ) . ', que el punto de control necesita.' );
	return $job;
}
function steps_staging( $id ) { return bk_storage() . '/jobs/' . $id . '/staging'; }
/** Con BK_STEPS_ONLY=A,B… sólo corren esas secciones (para comprobarlas una a una contra la rama anterior). */
function steps_section( $name ) {
	$only = (string) getenv( 'BK_STEPS_ONLY' );
	return '' === $only || in_array( $name, explode( ',', $only ), true );
}
$same = function( $a, $b ) { ksort( $a ); ksort( $b ); return $a === $b; };
/** Textos de los avisos del trabajo (los visibles son arrays con su código y su texto). */
function steps_warnings( array $job ) { return array_map( function( $w ) { return is_array( $w ) ? (string) ( $w['text'] ?? '' ) : (string) $w; }, (array) ( $job['warnings'] ?? array() ) ); }
/** Códigos de los avisos visibles del trabajo. */
function steps_notices( array $job ) { return array_values( array_filter( array_map( function( $w ) { return is_array( $w ) ? (string) ( $w['notice'] ?? '' ) : ''; }, (array) ( $job['warnings'] ?? array() ) ) ) ); }

// Referencia: una corrida sin cortes (cada fase en un tramo).
steps_filters( array() );
$reference = bk_manual( 'local', 'referencia' );
$job = steps_done( $reference['id'] );
$expected_sql = steps_sql( steps_zip( $job ) );
$expected = bk_zip_entries( steps_zip( $job ) );
bk_assert( 'transaction' === $job['checkpoint']['database']['mode'] && 'transaction' === $expected['database_consistency'], 'Sin cortes, la base de datos no salió de una sola instantánea.' );
bk_assert( isset( $expected['entries']['wordpress/wp-content/uploads/2026/11/grande.txt'] ), 'La referencia no tiene el archivo grande.' );
bk_assert( 9 === substr_count( $expected_sql, 'INSERT INTO `wp_posts`' ) && 1 === substr_count( $expected_sql, 'SET foreign_key_checks = 1;' ), 'El SQL de referencia no tiene las filas esperadas.' );

// A. Muerte a mitad de una tabla: el punto de control queda siempre entre tablas enteras; el tramo siguiente trunca el
// SQL al comienzo de la tabla, la exporta entera desde su primera fila en su instantánea y no relee las ya escritas.
if ( steps_section( 'A' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2 ) );
$exports = $db->exports;
$a = bk_manual( 'local', 'mitad-de-tabla' );
$job = steps_until( $a['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'export' === $r['stage'] && 1 === (int) $r['table']; } );
$resume = steps_resume( $a['id'], 'database' );
bk_assert( 'wp_posts' === $resume['tables'][1] && 0 === (int) $resume['rows'] && 3 === (int) $resume['total'] && 1 === (int) $resume['snapshots'] && 'transaction' === $resume['mode'] && 0 === (int) ( $job['attempts']['database'] ?? 0 ), 'El punto de control no quedó entre dos tablas enteras: ' . json_encode( $resume ) );
$part = steps_staging( $a['id'] ) . '/' . $resume['file'];
$saved = substr( file_get_contents( $part ), 0, (int) $resume['size'] );
bk_assert( false === strpos( $saved, '`wp_posts`' ) && 3 === substr_count( $saved, 'INSERT INTO `wp_options`' ), 'Lo confirmado no es exactamente la tabla wp_options entera.' );
// Lo que dejaría un tramo muerto a mitad de wp_posts: su DROP, su CREATE y algunas filas.
steps_kill( $a['id'], 'database', array( $part ) );
file_put_contents( $part, "DROP TABLE IF EXISTS `wp_posts`;\nINSERT INTO `wp_posts` (`id`,`v`) VALUES ('1','x');\n", FILE_APPEND );
$db->queries = array();
Digitalisimo_Backups_Jobs::tick( 0 );
$after = steps_resume( $a['id'], 'database' );
$selects = array_values( preg_grep( '/^SELECT \* FROM `/', $db->queries ) );
bk_assert( $selects && 'SELECT * FROM `wp_posts` ORDER BY `id` LIMIT 2' === $selects[0] && ! preg_grep( '/`wp_options`/', $selects ), 'Tras la muerte no se exportó wp_posts entera desde su primera fila, o se releyó wp_options: ' . json_encode( $selects ) );
$now = file_get_contents( $part );
bk_assert( 0 === strpos( $now, $saved ) && false === strpos( $now, 'basura' ) && 1 === substr_count( $now, 'DROP TABLE IF EXISTS `wp_posts`' ) && 9 === substr_count( $now, 'INSERT INTO `wp_posts`' ), 'El SQL retomado reescribió lo confirmado o conservó lo del tramo muerto.' );
bk_assert( 2 === (int) $after['table'] && 12 === (int) $after['total'] && 2 === (int) $after['snapshots'], 'El recuento de filas o de instantáneas contó dos veces la tabla repetida: ' . json_encode( array( $after['table'], $after['total'], $after['snapshots'] ) ) );
bk_assert( 0.5 === (float) $after['scale'], 'Tras la muerte el tramo no trabajó la mitad (escala ' . $after['scale'] . ').' );
$job = steps_done( $a['id'] );
bk_assert( $exports + 1 === $db->exports, 'La reanudación volvió a pedir la lista de tablas.' );
bk_assert( $expected_sql === steps_sql( steps_zip( $job ) ), 'El SQL exportado por tramos y retomado no es idéntico al de una corrida sin cortes.' );
$manifest = bk_zip_entries( steps_zip( $job ) );
bk_assert( 'transaction-per-table' === $manifest['database_consistency'] && 'transaction-per-table' === $job['checkpoint']['database']['mode'] && 'digitalisimo-zip/1' === $manifest['packer'] && 2 === (int) $job['checkpoint']['database']['segments'] && in_array( 'database_per_table', steps_notices( $job ), true ), 'La exportación en varios tramos no se declaró en el manifiesto y en un aviso visible del trabajo.' );
bk_assert( true === $job['checkpoint']['database']['checked'], 'El SQL no se comprobó antes de empaquetarlo.' );
Digitalisimo_Backups::job_verify_package( steps_zip( $job ) );

}
// A2. Muerte durante la comprobación del SQL: sigue desde la última tabla comprobada, sin exportar otra vez.
if ( steps_section( 'A2' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2 ) );
$a2 = bk_manual( 'local', 'durante-la-comprobacion' );
steps_until( $a2['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'check' === $r['stage'] && 1 === (int) $r['check']['table']; } );
$db->queries = array();
steps_kill( $a2['id'], 'database', array() );
$job = steps_done( $a2['id'] );
bk_assert( ! preg_grep( '/^SELECT \* FROM /', $db->queries ) && ! in_array( 'SHOW TABLES', $db->queries, true ), 'Una muerte durante la comprobación repitió la exportación.' );
bk_assert( $expected_sql === steps_sql( steps_zip( $job ) ), 'Una muerte durante la comprobación cambió el SQL.' );

}
// B. Muerte a mitad del inventario por carpetas y a mitad de un archivo: las listas y el ZIP se truncan a su punto de
// control, lo escrito antes no cambia y el ZIP final tiene las mismas entradas que sin cortes.
if ( steps_section( 'B' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 16384 ) );
$b = bk_manual( 'local', 'mitad-de-carpeta' );
$job = steps_until( $b['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && 'inventory' === $r['stage'] && $r['dirs_read'] > 0 && $r['files'] >= 5; } );
$resume = steps_resume( $b['id'], 'pack' );
$base = steps_staging( $b['id'] ) . '/package.' . $resume['tag'];
bk_assert( is_file( $base . '.dirs.jsonl' ) && is_file( $base . '.files.jsonl' ) && $resume['dirs_read'] < $resume['dirs_size'], 'El inventario no avanzó carpeta a carpeta con su cola.' );
$listed = substr( file_get_contents( $base . '.files.jsonl' ), 0, (int) $resume['files_size'] );
steps_kill( $b['id'], 'pack', array( $base . '.dirs.jsonl', $base . '.files.jsonl' ) );
Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 0 === strpos( file_get_contents( $base . '.files.jsonl' ), $listed ) && false === strpos( file_get_contents( $base . '.files.jsonl' ) . file_get_contents( $base . '.dirs.jsonl' ), 'basura' ), 'El inventario retomado no siguió desde su punto de control.' );
// Ahora a mitad del archivo grande (deflate) y con parte del ZIP ya escrita.
$job = steps_until( $b['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && is_array( $r['partial'] ) && 'wordpress/wp-content/uploads/2026/11/grande.txt' === $r['partial']['n'] && $r['partial']['raw'] >= 32768; } );
$resume = steps_resume( $b['id'], 'pack' );
$zip_path = $base . '.zip';
bk_assert( 8 === (int) $resume['partial']['m'] && count( $resume['partial']['b'] ) >= 2 && (int) $resume['zip_size'] === (int) $resume['partial']['p'] + (int) $resume['partial']['z'], 'La entrada grande no quedó suspendida en un límite de bloque deflate.' );
// Lo confirmado no se reescribe: las entradas terminadas y los datos ya escritos de la abierta. Sólo los 12 bytes de
// CRC y tamaños de la cabecera de la entrada abierta se completan cuando termina (escritura sin descriptor de datos).
$confirmed = function( $bytes ) use ( &$resume ) { return hash( 'sha256', substr( $bytes, 0, (int) $resume['partial']['o'] ) . substr( $bytes, (int) $resume['partial']['p'], (int) $resume['zip_size'] - (int) $resume['partial']['p'] ) ); };
$written = $confirmed( file_get_contents( $zip_path ) );
$entries_before = (int) $resume['entries'];
steps_kill( $b['id'], 'pack', array( $zip_path, $base . '.entries.jsonl' ) );
$job = steps_done( $b['id'] );
$final = steps_zip( $job );
bk_assert( $written === $confirmed( file_get_contents( $final ) ), 'El empaquetado retomado reescribió bytes ya confirmados del ZIP.' );
$manifest = bk_zip_entries( $final );
bk_assert( $same( $expected['entries'], $manifest['entries'] ), 'El ZIP empaquetado por tramos y retomado no tiene las mismas entradas que sin cortes.' );
bk_assert( $entries_before > 1 && 0 === (int) ( $job['attempts']['pack'] ?? 0 ), 'El empaquetado no avanzó entrada a entrada.' );
Digitalisimo_Backups::job_verify_package( $final );
$zip = new ZipArchive();
$zip->open( $final );
bk_assert( $big === $zip->getFromName( 'wordpress/wp-content/uploads/2026/11/grande.txt' ) && 8 === (int) $zip->statName( 'wordpress/wp-content/uploads/2026/11/grande.txt' )['comp_method'] && 0 === (int) $zip->statName( 'wordpress/wp-content/uploads/2026/11/foto.jpg' )['comp_method'], 'libzip no lee igual el archivo partido en varios compresores o el JPEG no se guardó sin comprimir.' );
$zip->close();

}
// C. Verificación por tramos, también a mitad de una entrada (por sus límites de bloque), y muerte en medio.
if ( steps_section( 'C' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 16384, 'digitalisimo_backups_verify_parts_from' => 1024 ) );
$c = bk_manual( 'local', 'verificacion-por-tramos' );
// Con PHP 7.4 no se puede guardar el estado del hash: la verificación se corta entre entradas, no dentro de una.
$parts = Digitalisimo_Backups_Zip_Writer::hash_state_supported();
$job = steps_until( $c['id'], function( $job ) use ( $parts ) { $r = $job['checkpoint']['verify']['resume'] ?? null; return 'verify' === $job['phase'] && is_array( $r ) && ( $parts ? is_array( $r['partial'] ) && $r['partial']['from'] > 0 : $r['index'] > 3 ); } );
$resume = steps_resume( $c['id'], 'verify' );
bk_assert( 'content' === $resume['stage'] && $resume['index'] > 0, 'La verificación no avanzó entrada a entrada.' );
steps_kill( $c['id'], 'verify', array() );
$job = steps_done( $c['id'] );
bk_assert( 1 === count( array_filter( (array) Digitalisimo_Backups_Job_Store::events( $c['id'] ), function( $e ) { return 'interrupted' === $e['type']; } ) ), 'La verificación retomada no registró una sola interrupción.' );
// Un ZIP dañado por dentro (mismo tamaño y fecha) se rechaza también en la verificación por tramos.
$d = bk_manual( 'local', 'zip-danado-por-tramos' );
$job = steps_until( $d['id'], function( $job ) { return 'verify' === $job['phase']; } );
$staged = steps_staging( $d['id'] ) . '/' . $job['checkpoint']['pack']['file'];
$raw = file_get_contents( $staged );
$at = strpos( $raw, 'wordpress/wp-content/uploads/2026/11/grande.txt' ) + 60;
$raw[ $at ] = chr( ord( $raw[ $at ] ) ^ 0x55 );
$mtime = filemtime( $staged );
file_put_contents( $staged, $raw );
touch( $staged, $mtime );
clearstatcache();
$job = steps_until( $d['id'], function( $job ) { return 'pack' === $job['phase'] || Digitalisimo_Backups_Job_Store::is_terminal( $job ); } );
bk_assert( 'pack' === $job['phase'] && 1 === (int) $job['attempts']['verify'] && false !== strpos( (string) $job['error']['message'], 'verificación del ZIP falló' ), 'Un ZIP dañado superó la verificación por tramos.' );
steps_done( $d['id'] );
// Lo mismo con una entrada que se verifica leyéndola con ZipArchive (el JPEG, guardado sin comprimir).
steps_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$d2 = bk_manual( 'local', 'jpeg-danado' );
$job = steps_until( $d2['id'], function( $job ) { return 'verify' === $job['phase']; } );
$staged = steps_staging( $d2['id'] ) . '/' . $job['checkpoint']['pack']['file'];
$raw = file_get_contents( $staged );
$at = strpos( $raw, 'wordpress/wp-content/uploads/2026/11/foto.jpg' ) + 45 + 16000;
$raw[ $at ] = chr( ord( $raw[ $at ] ) ^ 0x55 );
$mtime = filemtime( $staged );
file_put_contents( $staged, $raw );
touch( $staged, $mtime );
clearstatcache();
$job = steps_until( $d2['id'], function( $job ) { return 'pack' === $job['phase'] || Digitalisimo_Backups_Job_Store::is_terminal( $job ); } );
bk_assert( 'pack' === $job['phase'] && false !== strpos( (string) $job['error']['message'], 'foto.jpg' ), 'Un JPEG dañado dentro del ZIP superó la verificación por tramos.' );
steps_done( $d2['id'] );

}
// D. Presupuesto: con una base lenta cada tick termina en su presupuesto entre tablas, una tabla en curso sigue hasta
// el límite real de la petición, y la exportación se reparte en tramos.
if ( steps_section( 'D' ) ) {
steps_filters( array( 'digitalisimo_backups_db_batch' => 1, 'digitalisimo_backups_step_min' => 0.05 ) );
$tables = $db->tables;
$db->tables = array( 'wp_options' => 3, 'wp_posts' => 5, 'wp_postmeta' => 5, 'wp_comments' => 5, 'wp_commentmeta' => 5, 'wp_terms' => 5 );
// Cada fila leída tarda 20 ms: una tabla de 5 filas, 0,1 s.
$db->on_select = function( $query ) { preg_match( '/LIMIT (?:[0-9]+, )?([0-9]+)$/', $query, $m ); usleep( 20000 * min( 40, (int) ( $m[1] ?? 40 ) ) ); };
$slow = bk_manual( 'local', 'presupuesto' );
Digitalisimo_Backups_Jobs::tick( 0 ); // Comprobaciones.
$durations = array();
for ( $i = 0; $i < 200 && 'database' === bk_job( $slow['id'] )['phase']; $i++ ) {
	$start = microtime( true );
	Digitalisimo_Backups_Jobs::tick( 0.2 );
	$durations[] = microtime( true ) - $start;
}
bk_assert( max( $durations ) < 0.45, 'Un tick de exportación pasó de su presupuesto de 0,2 s más una tabla: ' . round( max( $durations ), 3 ) . ' s.' );
bk_assert( count( $durations ) >= 3, 'La exportación lenta no se repartió en tramos (' . count( $durations ) . ' ticks).' );
$db->tables = array( 'wp_options' => 3, 'wp_posts' => 40 );
// El límite real de la petición manda aunque el que llama pida «sin límite» (la pantalla de 1.0.58 lo hacía): una tabla
// de 0,8 s se abandona al llegar a él y el SQL vuelve al comienzo de la tabla.
steps_filters( array( 'digitalisimo_backups_db_batch' => 1, 'digitalisimo_backups_step_min' => 0.05, 'digitalisimo_backups_request_limit' => 10 ) );
$late = bk_manual( 'local', 'limite-de-peticion' );
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 6.5; // Quedan 10 - 3 (margen) - 6,5 = 0,5 s.
$start = microtime( true );
Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
$elapsed = microtime( true ) - $start;
$job = bk_job( $late['id'] );
$r = $job['checkpoint']['database']['resume'] ?? array();
bk_assert( $elapsed < 1.8 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ), 'El tick no se detuvo antes del límite real de la petición: ' . round( $elapsed, 2 ) . ' s.' );
bk_assert( 1 === (int) ( $r['table'] ?? -1 ) && (int) $r['size'] === filesize( steps_staging( $late['id'] ) . '/' . $r['file'] ) && 0 === (int) ( $job['attempts']['database'] ?? 0 ), 'La tabla abandonada en el límite de la petición no volvió a su comienzo o gastó un intento.' );
// Sin tick (la copia previa de una restauración, que no puede seguir en otra petición) el plazo no se recorta.
bk_assert( INF === Digitalisimo_Backups_Step_Budget::deadline() && INF === Digitalisimo_Backups_Step_Budget::hard_deadline() && 0.0 === Digitalisimo_Backups_Step_Budget::request_remaining(), 'Fuera de un tick, el plazo se recortó por el límite de la petición.' );
// El AJAX de 1.0.58 (una pestaña abierta al actualizar) encadena ticks, pero no más allá del límite de la petición.
$db->tables['wp_posts'] = 200;
$legacy_ajax = bk_manual( 'local', 'ajax-de-1058' );
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 6.5;
$_POST = array( 'nonce' => 'ok', 'token' => $legacy_ajax['id'] );
$start = microtime( true );
try { Digitalisimo_Backups::manual_work(); } catch ( RuntimeException $response ) { bk_assert( 0 === strpos( $response->getMessage(), 'OK:' ), 'manual_work no respondió: ' . $response->getMessage() ); }
$_POST = array();
bk_assert( microtime( true ) - $start < 2.5 && ! Digitalisimo_Backups_Job_Store::is_terminal( bk_job( $legacy_ajax['id'] ) ), 'manual_work siguió más allá del límite real de la petición (' . round( microtime( true ) - $start, 2 ) . ' s, fase ' . bk_job( $legacy_ajax['id'] )['phase'] . ').' );
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
steps_filters( array() );
$select = $db->on_select;
$db->on_select = null;
steps_done( $slow['id'] );
steps_done( $late['id'] );
steps_done( $legacy_ajax['id'] );
// Una tabla que no cabe en una petición entera (con tiempo justo, no por empezar tarde) falla a la tercera vez,
// diciendo cuál y que hay que respaldar con WP-CLI: partida entre instantáneas podría no importarse. Cada tick es una
// petición nueva con 2 s útiles, y la tabla necesita 4 s.
$db->on_select = $select;
$db->tables['wp_posts'] = 200;
$cut = array( 'digitalisimo_backups_db_batch' => 10, 'digitalisimo_backups_step_min' => 0.05, 'digitalisimo_backups_request_limit' => 10, 'digitalisimo_backups_table_min_seconds' => 0.5 );
$fresh_request = function() { $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 5.0; }; // 10 - 3 (margen) - 5 = 2 s.
steps_filters( $cut );
$huge = bk_manual( 'local', 'tabla-que-no-cabe' );
$job = bk_job( $huge['id'] );
for ( $i = 0; $i < 20 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
	$fresh_request();
	Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$job = bk_job( $huge['id'] );
}
bk_assert( 'failed' === $job['status'] && 'database' === $job['phase'] && false !== strpos( (string) $job['error']['message'], 'wp_posts' ) && false !== strpos( (string) $job['error']['message'], 'WP-CLI' ) && ! empty( $job['error']['permanent'] ), 'Una tabla que no cabe en una petición no falló con su nombre y la salida por WP-CLI: ' . json_encode( $job['error'] ) );
// Tras una muerte el tramo trabaja la mitad: sus cortes no cuentan contra la tabla hasta volver a la escala completa.
$scaled = bk_manual( 'local', 'corte-tras-muerte' );
$job = bk_job( $scaled['id'] );
for ( $i = 0; $i < 10 && ! is_array( $job['checkpoint']['database']['resume'] ?? null ); $i++ ) {
	$fresh_request();
	Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$job = bk_job( $scaled['id'] );
}
steps_kill( $scaled['id'], 'database', array() );
$cuts = array();
for ( $i = 0; $i < 20 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
	$fresh_request();
	Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$job = bk_job( $scaled['id'] );
	$r = $job['checkpoint']['database']['resume'] ?? null;
	if ( is_array( $r ) ) $cuts[] = array( round( (float) $r['scale'], 3 ), (int) ( $r['abandons']['wp_posts'] ?? 0 ) );
}
// Un tick encadena tramos mientras le queda presupuesto: el primero que se ve ya creció desde 0,5.
bk_assert( $cuts && $cuts[0][0] < 1.0 && 0 === $cuts[0][1] && 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'wp_posts' ), 'Un corte a media escala contó contra la tabla, o la tabla no falló a escala completa: ' . json_encode( $cuts ) );
foreach ( $cuts as $cut_seen ) bk_assert( $cut_seen[0] >= 1.0 || 0 === $cut_seen[1], 'Un corte a media escala contó contra la tabla: ' . json_encode( $cuts ) );
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
steps_filters( array() );
$db->on_select = null;
$db->tables = $tables;
// Memoria: cerca de memory_limit, un tramo se detiene tras una unidad.
$limit = ini_get( 'memory_limit' );
ini_set( 'memory_limit', (string) (int) ( memory_get_usage( true ) * 1.25 ) );
$tight = Digitalisimo_Backups_Step_Budget::exhausted( INF, 1 ) && Digitalisimo_Backups_Step_Budget::memory_headroom() <= 0;
ini_set( 'memory_limit', $limit );
bk_assert( $tight && ! Digitalisimo_Backups_Step_Budget::exhausted( INF, 1 ), 'El presupuesto no se detiene cerca de memory_limit.' );
// Con el 80 % ya ocupado antes de respaldar (WordPress con sus plugins, unos 52 de 64 MB en Playground) el tramo sigue
// trabajando; sólo se detiene al tocar su reserva (la octava parte del límite, al menos 4 MB).
ini_set( 'memory_limit', '40M' );
$wordpress = str_repeat( 'w', 32 * 1048576 - memory_get_usage( true ) );
$room = Digitalisimo_Backups_Step_Budget::memory_headroom();
$more = str_repeat( 'm', 4 * 1048576 );
$full = Digitalisimo_Backups_Step_Budget::exhausted( INF, 1 );
// La exportación no parte su instantánea por eso: sólo cierra el tramo si ya casi no queda memoria.
$export_goes_on = ! Digitalisimo_Backups_Step_Budget::exhausted( INF, 1, true );
$last = str_repeat( 'l', 3 * 1048576 );
$export_stops = Digitalisimo_Backups_Step_Budget::exhausted( INF, 1, true );
unset( $last );
unset( $wordpress, $more );
ini_set( 'memory_limit', $limit );
bk_assert( $room > 0 && $room <= 3 * 1048576 && $full, 'Con el 80 % de la memoria ocupado el tramo no pudo trabajar, o no se detuvo en su reserva (' . $room . ' B libres).' );
bk_assert( $export_goes_on && $export_stops, 'La exportación cerró su tramo (y su instantánea) al tocar la reserva, o no lo cerró casi sin memoria.' );

}
// E. Escala adaptativa del lote: tras una muerte el primer lote de cada tabla es la mitad.
if ( steps_section( 'E' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$db->tables['wp_posts'] = 2000;
$first_limit = function() use ( $db ) { $q = array_values( preg_grep( '/^SELECT \* FROM `wp_posts`/', $db->queries ) ); return $q ? (int) preg_replace( '/^.* LIMIT /', '', $q[0] ) : 0; };
$at_posts = function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'export' === $r['stage'] && 1 === (int) $r['table']; };
$e0 = bk_manual( 'local', 'lote-sin-muerte' );
steps_until( $e0['id'], $at_posts );
$db->queries = array();
Digitalisimo_Backups_Jobs::tick( 0 );
$normal = $first_limit();
$e = bk_manual( 'local', 'lote-adaptativo' );
steps_until( $e['id'], $at_posts );
steps_kill( $e['id'], 'database', array() );
$db->queries = array();
Digitalisimo_Backups_Jobs::tick( 0 );
$halved = $first_limit();
bk_assert( $normal > 0 && (int) floor( $normal / 2 ) === $halved, 'Tras la muerte el primer lote no se redujo a la mitad (' . $normal . ' → ' . $halved . ').' );
steps_filters( array() );
steps_done( $e0['id'] );
steps_done( $e['id'] );
$db->tables['wp_posts'] = 9;

}
// F. Claves compuestas, de texto con cifras y binarias: la paginación sigue el orden de la clave en cada tramo.
if ( steps_section( 'F' ) ) {
class Steps_Keys_DB extends Digitalisimo_Jobs_Test_DB {
	public $keyed = array();
	public function __construct() {
		$rows = array();
		for ( $o = 1; $o <= 4; $o++ ) for ( $t = 1; $t <= 3; $t++ ) $rows[] = array( 'object_id' => (string) $o, 'term_taxonomy_id' => (string) $t, 'term_order' => '0' );
		$this->keyed['wp_term_relationships'] = array( 'keys' => array( 'object_id' => 'i', 'term_taxonomy_id' => 'i' ), 'create' => "CREATE TABLE `wp_term_relationships` (\n  `object_id` bigint(20) unsigned NOT NULL DEFAULT '0',\n  `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT '0',\n  `term_order` int(11) NOT NULL DEFAULT '0',\n  PRIMARY KEY (`object_id`,`term_taxonomy_id`)\n) ENGINE=InnoDB", 'rows' => $rows );
		$rows = array();
		foreach ( array( '9', '10', '1', '2', 'a\'b', 'b' ) as $k ) $rows[] = array( 'k' => $k, 'v' => 'valor ' . $k );
		$this->keyed['wp_strkey'] = array( 'keys' => array( 'k' => 's' ), 'create' => "CREATE TABLE `wp_strkey` (\n  `k` varchar(20) NOT NULL,\n  `v` text,\n  PRIMARY KEY (`k`)\n) ENGINE=InnoDB", 'rows' => $rows );
		$rows = array();
		foreach ( array( "\xff", "\x00\x01", "a\x00", "\x00" ) as $k ) $rows[] = array( 'b' => $k, 'v' => bin2hex( $k ) );
		$this->keyed['wp_binkey'] = array( 'keys' => array( 'b' => 'x' ), 'create' => "CREATE TABLE `wp_binkey` (\n  `b` varbinary(16) NOT NULL,\n  `v` text,\n  PRIMARY KEY (`b`)\n) ENGINE=InnoDB", 'rows' => $rows );
		$this->tables = array( 'wp_options' => 3, 'wp_term_relationships' => 0, 'wp_strkey' => 0, 'wp_binkey' => 0 );
	}
	public function get_row( $query, $format = null ) {
		foreach ( $this->keyed as $table => $spec ) if ( false !== strpos( $query, 'SHOW CREATE TABLE `' . $table . '`' ) ) { $this->queries[] = $query; return array( $table, $spec['create'] ); }
		return parent::get_row( $query, $format );
	}
	public function get_results( $query, $format = null ) {
		foreach ( $this->keyed as $table => $spec ) {
			if ( false === strpos( $query, '`' . $table . '`' ) ) continue;
			$this->queries[] = $query;
			$this->last_error = '';
			if ( 0 === strpos( $query, 'SHOW INDEX' ) ) { $i = 0; return array_map( function( $column ) use ( &$i ) { return array( 'Key_name' => 'PRIMARY', 'Column_name' => $column, 'Seq_in_index' => ++$i ); }, array_keys( $spec['keys'] ) ); }
			return $this->page( $spec, $query );
		}
		return parent::get_results( $query, $format );
	}
	/** Lo que haría MySQL: WHERE por clave (texto contra texto en orden binario; texto contra número, como número), ORDER BY y LIMIT. */
	private function page( array $spec, $query ) {
		$rows = $spec['rows'];
		$keys = $spec['keys'];
		$compare = function( $a, $b, $kind ) { return 'i' === $kind ? ( (int) $a <=> (int) $b ) : strcmp( $a, $b ); };
		usort( $rows, function( $x, $y ) use ( $keys, $compare ) { foreach ( $keys as $column => $kind ) { $c = $compare( $x[ $column ], $y[ $column ], $kind ); if ( $c ) return $c; } return 0; } );
		if ( preg_match( '/ WHERE (.*) ORDER BY /s', $query, $where ) ) {
			$groups = array();
			foreach ( explode( ') OR (', trim( $where[1], '()' ) ) as $group ) {
				$conditions = array();
				foreach ( explode( ' AND ', $group ) as $condition ) {
					if ( ! preg_match( "/^`(\\w+)` (>|=) (-?[0-9]+|X'[0-9a-f]*'|'(?:[^'\\\\]|\\\\.)*')$/s", $condition, $m ) ) throw new RuntimeException( 'FALLA: condición de clave inesperada: ' . $condition );
					$literal = $m[3];
					if ( "X'" === substr( $literal, 0, 2 ) ) $literal = array( 's', hex2bin( substr( $literal, 2, -1 ) ) );
					elseif ( "'" === $literal[0] ) $literal = array( 's', stripcslashes( substr( $literal, 1, -1 ) ) );
					else $literal = array( 'n', $literal );
					$conditions[] = array( $m[1], $m[2], $literal );
				}
				$groups[] = $conditions;
			}
			$rows = array_values( array_filter( $rows, function( $row ) use ( $groups ) {
				foreach ( $groups as $conditions ) {
					$all = true;
					foreach ( $conditions as $c ) {
						$value = $row[ $c[0] ];
						$cmp = 'n' === $c[2][0] ? ( (float) $value <=> (float) $c[2][1] ) : strcmp( $value, $c[2][1] );
						if ( ! ( '>' === $c[1] ? $cmp > 0 : 0 === $cmp ) ) { $all = false; break; }
					}
					if ( $all ) return true;
				}
				return false;
			} ) );
		}
		if ( preg_match( '/ LIMIT ([0-9]+)$/', $query, $limit ) ) $rows = array_slice( $rows, 0, (int) $limit[1] );
		return $rows;
	}
}
$GLOBALS['wpdb'] = new Steps_Keys_DB();
steps_filters( array() );
$whole = steps_done( bk_manual( 'local', 'claves-referencia' )['id'] );
$whole_sql = steps_sql( steps_zip( $whole ) );
bk_assert( 12 === substr_count( $whole_sql, 'INSERT INTO `wp_term_relationships`' ) && 6 === substr_count( $whole_sql, 'INSERT INTO `wp_strkey`' ) && 4 === substr_count( $whole_sql, 'INSERT INTO `wp_binkey`' ), 'La referencia de claves no exportó todas las filas.' );
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2 ) );
$GLOBALS['wpdb']->queries = array();
$keys = steps_done( bk_manual( 'local', 'claves-por-tramos' )['id'] );
bk_assert( $whole_sql === steps_sql( steps_zip( $keys ) ), 'Con claves compuestas, de texto o binarias, el SQL por tramos difiere del de un solo tramo.' );
$queries = implode( "\n", $GLOBALS['wpdb']->queries );
bk_assert( false !== strpos( $queries, "WHERE (`object_id` > 1) OR (`object_id` = 1 AND `term_taxonomy_id` > 2)" ) && false !== strpos( $queries, "WHERE (`k` > '10')" ) && false !== strpos( $queries, "WHERE (`b` > X'0001')" ), 'La paginación no usa la clave compuesta, el texto entre comillas o el literal binario.' );
$GLOBALS['wpdb'] = $db;

}
// G. Un archivo que desaparece después del inventario. En una carpeta de código (un plugin que se actualiza a mitad)
// el empaquetado empieza otra vez y el respaldo sale coherente; fuera de ellas (uploads) se omite, pero el respaldo queda
// «Completo con avisos» y lo dice. Uno obligatorio hace fallar el empaquetado.
if ( steps_section( 'G' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1 ) );
$g = bk_manual( 'local', 'archivo-desaparecido' );
steps_until( $g['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && 'write' === $r['stage']; } );
$gone = $root . 'wp-content/plugins/example/example.php';
$kept = file_get_contents( $gone );
unlink( $gone );
$job = steps_done( $g['id'] );
$manifest = bk_zip_entries( steps_zip( $job ) );
bk_assert( ! isset( $manifest['entries']['wordpress/wp-content/plugins/example/example.php'] ) && preg_grep( '/código del sitio cambió/', steps_warnings( $job ) ) && 2 === (int) $job['checkpoint']['pack']['passes'] && ! steps_notices( array( 'warnings' => array_filter( (array) $job['warnings'], function( $w ) { return is_array( $w ) && 'database_per_table' !== $w['notice']; } ) ) ), 'Un archivo de un plugin desaparecido a mitad no hizo empaquetar otra vez, o el respaldo coherente quedó con avisos.' );
file_put_contents( $gone, $kept );
$fleeting = $root . 'wp-content/uploads/2026/11/efimero.txt';
file_put_contents( $fleeting, "un archivo temporal\n" );
$g2 = bk_manual( 'local', 'subida-desaparecida' );
steps_until( $g2['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && 'write' === $r['stage']; } );
unlink( $fleeting );
$job = steps_done( $g2['id'] );
$manifest = bk_zip_entries( steps_zip( $job ) );
$view = Digitalisimo_Backups_Jobs::view( $job );
bk_assert( ! isset( $manifest['entries']['wordpress/wp-content/uploads/2026/11/efimero.txt'] ) && in_array( 'files_missing', steps_notices( $job ), true ) && 'Completo con avisos' === $view['status_label'] && preg_grep( '/efimero\.txt/', $view['notices'] ) && preg_grep( '/efimero\.txt/', (array) ( $manifest['notices'] ?? array() ) ), 'Un archivo desaparecido entre el inventario y su copia dio un respaldo «Completo» sin aviso visible.' );
$h = bk_manual( 'local', 'obligatorio-desaparecido' );
steps_until( $h['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && 'write' === $r['stage']; } );
$load = $root . 'wp-load.php';
$content = file_get_contents( $load );
unlink( $load );
$job = steps_until( $h['id'], function( $job ) { return Digitalisimo_Backups_Job_Store::is_terminal( $job ); } );
bk_assert( 'failed' === $job['status'] && false !== strpos( (string) $job['error']['message'], 'wp-load.php' ) && ! is_file( steps_zip( $job ) ), 'Sin un archivo obligatorio el empaquetado no falló limpio.' );
file_put_contents( $load, $content );

}
// H. Sin poder guardar el estado de CRC y SHA-256 (PHP 7.4) una entrada partida se retoma releyendo lo escrito.
if ( steps_section( 'H' ) ) {
Digitalisimo_Backups_Zip_Writer::$no_hash_state = true;
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_zip_chunk' => 16384 ) );
$i74 = bk_manual( 'local', 'sin-estado-de-hash' );
$job = steps_until( $i74['id'], function( $job ) { $r = $job['checkpoint']['pack']['resume'] ?? null; return is_array( $r ) && is_array( $r['partial'] ) && 'wordpress/wp-content/uploads/2026/11/grande.txt' === $r['partial']['n'] && $r['partial']['raw'] >= 32768; } );
bk_assert( null === steps_resume( $i74['id'], 'pack' )['partial']['hc'], 'La prueba no simuló un PHP sin estado de hash serializable.' );
$job = steps_done( $i74['id'] );
Digitalisimo_Backups_Zip_Writer::$no_hash_state = false;
bk_assert( $same( $expected['entries'], bk_zip_entries( steps_zip( $job ) )['entries'] ), 'Sin estado de hash serializable, la entrada retomada no quedó íntegra.' );
Digitalisimo_Backups::job_verify_package( steps_zip( $job ) );

}
// I. MyISAM: cada tramo toma y suelta su bloqueo de lectura, y nada lee opciones dentro de la instantánea.
if ( steps_section( 'I' ) ) {
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2 ) );
$db->engine = 'MyISAM';
$db->snapshot_violations = array();
$db->queries = array();
$job = steps_done( bk_manual( 'local', 'myisam-por-tramos' )['id'] );
$locks = count( preg_grep( '/^LOCK TABLES /', $db->queries ) );
bk_assert( 'lock-per-table' === $job['checkpoint']['database']['mode'] && $locks === $job['checkpoint']['database']['segments'] && $locks === count( array_keys( $db->queries, 'UNLOCK TABLES', true ) ) && ! $db->snapshot_violations, 'Con tablas no transaccionales los tramos no tomaron y soltaron su bloqueo de lectura.' );
$db->engine = 'InnoDB';

}
// J. Una tabla que desaparece a mitad de una exportación retomada (un plugin desinstalado): la exportación empieza de
// nuevo con la lista de tablas actual, con aviso, y el manifiesto no la nombra.
if ( steps_section( 'J' ) ) {
class Steps_Gone_DB extends Digitalisimo_Jobs_Test_DB {
	public $gone = '';
	public function get_row( $query, $format = null ) {
		if ( '' !== $this->gone && false !== strpos( $query, 'SHOW CREATE TABLE `' . $this->gone . '`' ) ) { $this->queries[] = $query; $this->last_error = "Table '" . $this->gone . "' doesn't exist"; return null; }
		return parent::get_row( $query, $format );
	}
}
$GLOBALS['wpdb'] = new Steps_Gone_DB();
$GLOBALS['wpdb']->tables = array( 'wp_options' => 3, 'wp_posts' => 9, 'wp_plugin_extra' => 4 );
steps_filters( array( 'digitalisimo_backups_step_units' => 1, 'digitalisimo_backups_db_batch' => 2 ) );
$j = bk_manual( 'local', 'tabla-desaparecida' );
steps_until( $j['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'export' === $r['stage'] && 1 === (int) $r['table']; } );
unset( $GLOBALS['wpdb']->tables['wp_plugin_extra'] );
$GLOBALS['wpdb']->gone = 'wp_plugin_extra';
$job = steps_done( $j['id'] );
$manifest = bk_zip_entries( steps_zip( $job ) );
bk_assert( array( 'wp_options', 'wp_posts' ) === $manifest['database_tables'] && preg_grep( '/wp_plugin_extra cambió o desapareció/', steps_warnings( $job ) ) && false === strpos( steps_sql( steps_zip( $job ) ), 'wp_plugin_extra' ), 'Una tabla desaparecida a mitad de la exportación no la reinició con la lista actual.' );
// Si el punto de control queda inválido una y otra vez, la exportación no empieza de nuevo sin fin: a la tercera vez
// cuenta como un fallo que gasta sus intentos.
$k = bk_manual( 'local', 'reinicios' );
$GLOBALS['wpdb']->tables['wp_plugin_extra'] = 4;
$GLOBALS['wpdb']->gone = '';
steps_until( $k['id'], function( $job ) { $r = $job['checkpoint']['database']['resume'] ?? null; return is_array( $r ) && 'export' === $r['stage'] && 1 === (int) $r['table']; } );
$GLOBALS['wpdb']->gone = 'wp_plugin_extra'; // Sigue en SHOW TABLES, pero su definición ya no se puede leer al retomar.
$job = steps_until( $k['id'], function( $job ) { return Digitalisimo_Backups_Job_Store::is_terminal( $job ); }, 800 );
bk_assert( 'failed' === $job['status'] && 'database' === $job['phase'], 'Un punto de control inválido una y otra vez no terminó en fallo: ' . $job['status'] );
$GLOBALS['wpdb'] = $db;
}

steps_filters( array() );
bk_assert( ! Digitalisimo_Backups_Jobs::has_active(), 'Quedaron trabajos activos.' );
bk_cleanup();
echo "DIGITALÍSIMO Backups: exportación (tablas enteras por instantánea), empaquetado y verificación por tramos retomados a mitad de tabla, de carpeta y de archivo, con presupuesto, escala adaptativa y claves compuestas, de texto y binarias.\n";
