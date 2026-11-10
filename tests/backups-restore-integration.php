<?php
/**
 * DIGITALÍSIMO Backups · la integración 1.0.64 (restauración por pasos + T2 + php74), tras su revisión adversaria
 * (/var/tmp/digi-wt/bk-int-review/REVIEW.md). Cada caso falla con la integración revisada (aa758708):
 *   H1  zip_entry_reader() lee del ZIP abierto sólo hasta 256 KiB (más grande, getStream()), una entrada de 1 MiB no
 *       triplica la memoria y la restauración cierra el tramo con menos de 4 MiB libres (la reserva mínima de T2).
 *   H5  una entrada con datos de más tras su tamaño declarado (o declarada vacía) no se lee recortada.
 *   H2  dentro de una restauración, un fallo de verificación de la copia previa que se repite tras cada empaquetado
 *       agota sus 3 vueltas a «pack» y la restauración falla sin tocar el sitio (no da vueltas sin fin).
 *   H3  tras una muerte en un tramo de T2 de la copia previa, el tramo siguiente trabaja la mitad (T2 ve los intentos).
 *   H4  los avisos de T2 de la copia previa (la base salió tabla por tabla) llegan al trabajo de la restauración y a la
 *       fila de la copia previa en el historial, con su coherencia; una vuelta a ella lo dice.
 * Uso: php tests/backups-restore-integration.php
 */
require __DIR__ . '/lib/backups-env.php';

$failures = 0;
function ri_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function ri_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
/** ZIP escrito a mano, con el tamaño, el CRC y el método que se pidan (para entradas que mienten). */
function ri_zip( $path, array $entries ) {
	$out = '';
	$cd = '';
	foreach ( $entries as $e ) {
		$method = $e['method'] ?? 0;
		$payload = 8 === $method ? gzdeflate( $e['data'], 6 ) : $e['data'];
		$size = $e['size'] ?? strlen( $e['data'] );
		$crc = $e['crc'] ?? crc32( $e['data'] );
		$offset = strlen( $out );
		$out .= pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, $method, 0, 0x5A21, $crc, strlen( $payload ), $size, strlen( $e['name'] ), 0 ) . $e['name'] . $payload;
		$cd .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 0x031e, 20, 0, $method, 0, 0x5A21, $crc, strlen( $payload ), $size, strlen( $e['name'] ), 0, 0, 0, 0, 0100644 << 16, $offset ) . $e['name'];
	}
	$out .= $cd . pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $entries ), count( $entries ), strlen( $cd ), strlen( $out ), 0 );
	file_put_contents( $path, $out );
	$zip = new ZipArchive();
	ri_check( true === $zip->open( $path ), 'No se pudo abrir el ZIP de prueba.' );
	return $zip;
}
function ri_type( $stream ) { $meta = stream_get_meta_data( $stream ); return strtoupper( (string) ( $meta['stream_type'] ?? '' ) ); }

$tmp = sys_get_temp_dir() . '/ri-' . bin2hex( random_bytes( 3 ) );
mkdir( $tmp, 0700, true );

ri_case( 'H1 el lector usa el ZIP abierto sólo hasta 256 KiB y una entrada de 1 MiB no triplica la memoria', function() use ( $tmp ) {
	$small = str_repeat( 's', 200 * 1024 );
	$medium = random_bytes( 300 * 1024 );
	$large = random_bytes( 1048576 );
	$zip = ri_zip( $tmp . '/h1.zip', array( array( 'name' => 'p.bin', 'data' => $small ), array( 'name' => 'm.bin', 'data' => $medium ), array( 'name' => 'g.bin', 'data' => $large ) ) );
	$p = Digitalisimo_Backups::zip_entry_reader( $zip, 'p.bin', strlen( $small ) );
	ri_check( 'TEMP' === ri_type( $p ) && $small === stream_get_contents( $p ), 'Una entrada de 200 KiB no se leyó del ZIP abierto.' );
	fclose( $p );
	$m = Digitalisimo_Backups::zip_entry_reader( $zip, 'm.bin', strlen( $medium ) );
	ri_check( 'TEMP' !== ri_type( $m ) && $medium === stream_get_contents( $m ), 'Una entrada de 300 KiB se copió entera a memoria (' . ri_type( $m ) . '): el umbral sigue en 1 MiB.' );
	fclose( $m );
	// La memoria de PHP al leer 1 MiB como lo hacen la verificación y T2 (trozos de 1 MiB): antes, 3 MiB (cadena, flujo
	// temporal y trozo); como mucho la entrada y poco más.
	if ( function_exists( 'memory_reset_peak_usage' ) ) {
		gc_collect_cycles();
		memory_reset_peak_usage();
		$base = memory_get_usage();
		$g = Digitalisimo_Backups::zip_entry_reader( $zip, 'g.bin', strlen( $large ) );
		$hash = hash_init( 'sha256' );
		while ( ! feof( $g ) ) hash_update( $hash, (string) fread( $g, 1048576 ) );
		fclose( $g );
		$extra = memory_get_peak_usage() - $base;
		ri_check( hash_final( $hash ) === hash( 'sha256', $large ), 'La entrada de 1 MiB no se leyó entera.' );
		ri_check( $extra < 1.6 * 1048576, sprintf( 'Leer 1 MiB ocupó %.2f MiB de más.', $extra / 1048576 ) );
	}
	$zip->close();
	// La restauración cierra el tramo con menos de 4 MiB libres (la peor unidad pide un bloque de 2 MiB más).
	$limit = ini_get( 'memory_limit' );
	ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 3 * 1048576 ) );
	$closes = bk_call( 'Digitalisimo_Backups', 'restore_exhausted', INF, 0 );
	ini_set( 'memory_limit', $limit );
	ri_check( true === $closes, 'Con 3 MiB libres la restauración sigue con el tramo.' );
} );

ri_case( 'H5 una entrada con datos de más tras su tamaño declarado no se lee recortada', function() use ( $tmp ) {
	$declared = str_repeat( 'A', 1000 );
	$evil = "<?php system( \$_GET['c'] ); ?>" . str_repeat( 'B', 470 );
	foreach ( array( 0, 8 ) as $method ) {
		$zip = ri_zip( $tmp . '/h5-' . $method . '.zip', array( array( 'name' => 'wordpress/a.php', 'data' => $declared . $evil, 'method' => $method, 'size' => 1000, 'crc' => crc32( $declared ) ) ) );
		$stream = Digitalisimo_Backups::zip_entry_reader( $zip, 'wordpress/a.php', 1000 );
		$read = $stream ? (string) @stream_get_contents( $stream ) : ''; // el aviso «CRC error» de libzip es el esperado
		if ( $stream ) fclose( $stream );
		$zip->close();
		ri_check( 1000 !== strlen( $read ), 'Con el método ' . $method . ', los datos de más tras el tamaño declarado se leyeron recortados (' . strlen( $read ) . ' bytes): quien llama ya no puede rechazarlos.' );
	}
	$zip = ri_zip( $tmp . '/h5-cero.zip', array( array( 'name' => 'wordpress/b.php', 'data' => $evil, 'method' => 8, 'size' => 0, 'crc' => 0 ) ) );
	$stream = Digitalisimo_Backups::zip_entry_reader( $zip, 'wordpress/b.php', 0 );
	ri_check( ! $stream || 'TEMP' !== ri_type( $stream ), 'Una entrada declarada vacía no fue por getStream().' );
	if ( $stream ) fclose( $stream );
	$zip->close();
} );

// Lo que sigue usa una restauración entera con la copia previa por tramos de T2.
$root = rtrim( ABSPATH, '/' );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
for ( $i = 0; $i < 30; $i++ ) { @mkdir( $root . '/wp-content/plugins/p' . $i, 0700, true ); file_put_contents( $root . '/wp-content/plugins/p' . $i . '/p.php', "<?php // plugin $i\n" . str_repeat( "// relleno $i\n", 200 ) ); }
$GLOBALS['bk_options']['blogname'] = 'Sitio original';
$backup = bk_backup( true );
$GLOBALS['bk_options']['blogname'] = 'Cambiado';

function ri_restore_job() {
	$last = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && empty( $job['spec']['rollback'] ) && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
	return $last;
}
/** Encola la restauración sin avanzarla (una petición de 1 s: restore_drive() se detiene enseguida). */
function ri_enqueue( $backup ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_request_limit'] = function() { return 1; };
	$result = Digitalisimo_Backups::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	$GLOBALS['bk_filters']['digitalisimo_backups_request_limit'] = function() { return 0; };
	ri_check( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
	return ri_restore_job();
}
function ri_reset( $root ) {
	foreach ( Digitalisimo_Backups_Job_Store::all() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	@unlink( $root . '/.maintenance' );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$GLOBALS['bk_options']['blogname'] = 'Cambiado';
}
function ri_sub( $job ) { return (string) ( $job['checkpoint']['safety']['sub'] ?? '' ); }

ri_case( 'H2 la copia previa de una restauración no vuelve sin fin de «verify» a «pack»: agota sus intentos y falla sin tocar el sitio', function() use ( $root, $backup ) {
	ri_reset( $root );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 4; };
	$id = ri_enqueue( $backup )['id'];
	$corrupted = null;
	$packs = 0;
	for ( $t = 0; $t < 1500; $t++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		$pack = $job['checkpoint']['safety']['checkpoint']['pack'] ?? null;
		if ( 'safety' === $job['phase'] && 'verify' === ri_sub( $job ) && is_array( $pack ) && isset( $pack['entries'] ) && empty( $job['checkpoint']['safety']['checkpoint']['verify']['resume'] ) && $corrupted !== json_encode( array( $pack['file'], $pack['fp'] ?? null, $packs ) ) ) {
			// Tras cada empaquetado, un proceso ajeno estropea un byte de una entrada tardía (mismo tamaño y fecha).
			$packs++;
			$corrupted = json_encode( array( $pack['file'], $pack['fp'] ?? null, $packs ) );
			$staging = Digitalisimo_Backups_Job_Store::staging( $id );
			$records = array_values( array_filter( array_map( function( $line ) { return json_decode( $line, true ); }, explode( "\n", (string) file_get_contents( $staging . '/' . $pack['entries'] ) ) ) ) );
			$target = $records[ max( 0, count( $records ) - 3 ) ];
			$file = $staging . '/' . $pack['file'];
			$mtime = filemtime( $file );
			$handle = fopen( $file, 'r+b' );
			$at = Digitalisimo_Backups_Zip_Writer::data_offset( $target );
			fseek( $handle, $at );
			$byte = fread( $handle, 1 );
			fseek( $handle, $at );
			fwrite( $handle, chr( ord( $byte ) ^ 0xff ) );
			fclose( $handle );
			touch( $file, $mtime );
			clearstatcache();
		}
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	$job = Digitalisimo_Backups_Jobs::get( $id );
	ri_check( Digitalisimo_Backups_Job_Store::is_terminal( $job ), 'Tras ' . $t . ' ticks y ' . $packs . ' empaquetados de la copia previa, la restauración sigue en ' . $job['phase'] . '/' . ri_sub( $job ) . ' (vueltas sin fin a «pack»).' );
	ri_check( 'failed' === $job['status'] && 'safety' === $job['phase'] && $packs <= 4 && false !== strpos( (string) ( $job['error']['message'] ?? '' ), 'copia previa' ), 'La copia previa no falló al agotar sus vueltas: ' . json_encode( array( $job['status'], $job['phase'], $packs, $job['error'] ?? null ), JSON_UNESCAPED_UNICODE ) );
	ri_check( 'Cambiado' === ( $GLOBALS['bk_options']['blogname'] ?? '' ) && ! file_exists( $root . '/.maintenance' ), 'El sitio cambió o quedó en mantenimiento.' );
} );

ri_case( 'H3 tras una muerte en un tramo de T2 de la copia previa, el tramo siguiente trabaja la mitad', function() use ( $root, $backup ) {
	ri_reset( $root );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	$id = ri_enqueue( $backup )['id'];
	$job = null;
	for ( $t = 0; $t < 300; $t++ ) {
		Digitalisimo_Backups_Jobs::tick( 0 );
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( 'safety' === $job['phase'] && 'database' === ri_sub( $job ) && ! empty( $job['checkpoint']['safety']['checkpoint']['database']['resume'] ) ) break;
	}
	ri_check( $job && 'database' === ri_sub( $job ), 'La copia previa no llegó a exportar la base de datos por tramos.' );
	// Lo que hace el motor al reconocer la petición muerta: cuenta un intento de la fase (sin tocar su punto de control).
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) {
		$fresh = Digitalisimo_Backups_Job_Store::read( $id );
		$fresh['attempts']['safety'] = 1;
		Digitalisimo_Backups_Job_Store::write( $fresh );
	} );
	Digitalisimo_Backups_Jobs::tick( 0 );
	$job = Digitalisimo_Backups_Jobs::get( $id );
	$resume = $job['checkpoint']['safety']['checkpoint']['database']['resume'] ?? null;
	ri_check( is_array( $resume ) && (float) ( $resume['scale'] ?? 1 ) <= 0.5, 'Tras una muerte, el tramo siguiente de T2 en la copia previa no trabajó la mitad (escala ' . json_encode( is_array( $resume ) ? ( $resume['scale'] ?? null ) : null ) . '): T2 no ve los intentos.' );
	for ( $t = 0; $t < 3000 && ! Digitalisimo_Backups_Job_Store::is_terminal( Digitalisimo_Backups_Jobs::get( $id ) ); $t++ ) Digitalisimo_Backups_Jobs::tick( 0 );
	ri_check( 'success' === Digitalisimo_Backups_Jobs::get( $id )['status'], 'La restauración no terminó.' );
} );

ri_case( 'H4 los avisos de T2 de la copia previa llegan al trabajo y al historial, y una vuelta a ella lo dice', function() use ( $root, $backup ) {
	ri_reset( $root );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	$id = ri_enqueue( $backup )['id'];
	for ( $t = 0; $t < 3000 && ! Digitalisimo_Backups_Job_Store::is_terminal( Digitalisimo_Backups_Jobs::get( $id ) ); $t++ ) Digitalisimo_Backups_Jobs::tick( 0 );
	$job = Digitalisimo_Backups_Jobs::get( $id );
	ri_check( 'success' === $job['status'], 'La restauración no terminó: ' . json_encode( $job['error'] ?? null ) );
	$safety = (string) ( $job['checkpoint']['safety']['file'] ?? '' );
	$row = array();
	foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $item ) if ( $safety === ( $item['file'] ?? '' ) ) $row = $item;
	ri_check( false !== strpos( (string) ( $row['consistency'] ?? '' ), 'per-table' ), 'La fila de la copia previa no tiene la coherencia de su base: ' . json_encode( $row['consistency'] ?? null ) );
	ri_check( '' !== Digitalisimo_Backups::history_notices_text( $row ), 'La fila de la copia previa no tiene sus avisos.' );
	$notices = array();
	foreach ( (array) $job['warnings'] as $warning ) if ( is_array( $warning ) && ! empty( $warning['notice'] ) ) $notices[ $warning['notice'] ] = (string) $warning['text'];
	ri_check( isset( $notices['database_per_table'] ) && 0 === strpos( $notices['database_per_table'], 'Copia previa:' ), 'El trabajo de la restauración no tiene el aviso de la copia previa: ' . json_encode( $notices, JSON_UNESCAPED_UNICODE ) );
	$text = bk_call( 'Digitalisimo_Backups', 'restore_safety_consistency_text', $safety );
	ri_check( false !== strpos( (string) $text, 'tabla por tabla' ), 'El aviso de una vuelta a esta copia previa no dice que salió tabla por tabla.' );
} );

bk_cleanup();
if ( $GLOBALS['failures'] ) { echo 'FALLA: ' . $GLOBALS['failures'] . " caso(s) de la integración 1.0.64.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: integración 1.0.64 tras su revisión (H1–H5): lector del ZIP con memoria acotada y sin recortar, copia previa por tramos con intentos, vueltas atrás y avisos.\n";
