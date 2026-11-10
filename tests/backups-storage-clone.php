<?php
/**
 * Revisión adversaria de E2FIX, C1 y C6: el almacén privado de este sitio cuando el de otro sitio de la misma cuenta existe.
 *   (c1) Clon producción → staging en la MISMA cuenta: el SQL del respaldo trae el registro del almacén de producción,
 *        cuya carpeta sí existe aquí, y la restauración muere justo después del RENAME TABLE. La petición siguiente corre
 *        en OTRO proceso (sin la caché de local_directory()), como en producción: usa su almacén, aplica el diario, ve la
 *        restauración en curso y no toca nada del almacén de producción. Antes adoptaba el de producción, no aplicaba el
 *        diario, perdía la restauración (el sitio quedaba en mantenimiento) y borraba sus `.part`.
 *   (c1-control) Lo mismo con la carpeta del origen inexistente aquí.
 *   (c6) Un dominio adicional con la raíz dentro de la de otro sitio (`public_html/<dominio>`): la carpeta propia nunca
 *        queda dentro de esa raíz pública; sube hasta la carpeta de la cuenta.
 * Necesita proc_open y un PHP del sistema (la CI y la PC); en php-wasm se omite y lo dice.
 */
if ( in_array( '--siguiente', $argv, true ) ) {
	// ------------------------------------------------------------ La petición siguiente, en otro proceso
	$ctx = json_decode( file_get_contents( getenv( 'BK_CLONE_CTX' ) ), true );
	define( 'ABSPATH', $ctx['abspath'] );
	require __DIR__ . '/lib/backups-env.php';
	$_SERVER['DOCUMENT_ROOT'] = $ctx['document_root'];
	$GLOBALS['bk_options'] = unserialize( file_get_contents( $ctx['options'] ) );
	$B = 'Digitalisimo_Backups';
	$out = array();
	try {
		$out['almacen'] = $B::storage_directory();
		$lock = bk_call( $B, 'acquire_operation_lock' );
		bk_call( $B, 'release_operation_lock', $lock );
	} catch ( Throwable $e ) {
		$out['excepcion'] = get_class( $e ) . ': ' . $e->getMessage();
	}
	clearstatcache();
	$out['registro'] = get_option( $B::STORAGE );
	$out['diario_pendiente'] = is_file( $ctx['storage'] . '/' . $B::RESTORE_JOURNAL );
	try { $active = Digitalisimo_Backups_Jobs::restore_active(); $out['restauracion'] = $active ? (string) $active['id'] : null; } catch ( Throwable $e ) { $out['restauracion'] = null; }
	if ( '' !== $ctx['prod'] ) {
		$out['prod_part'] = is_file( $ctx['prod'] . '/digitalisimo-prod-20261110-120000-aaaaaaaa.zip.part' );
		$out['prod_permisos'] = sprintf( '%o', fileperms( $ctx['prod'] . '/notas-de-produccion.txt' ) & 0777 );
		$out['prod_jobs'] = is_dir( $ctx['prod'] . '/jobs' );
	}
	echo json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
	exit( 0 );
}
if ( in_array( '--anidada', $argv, true ) ) {
	// ------------------------------------------------------------ (c6) raíz pública anidada en la de otro sitio
	$base = sys_get_temp_dir() . '/digitalisimo-cuenta-' . bin2hex( random_bytes( 3 ) );
	mkdir( $base . '/public_html/adicional.example', 0755, true );
	file_put_contents( $base . '/public_html/index.php', '<?php // el sitio principal' );
	define( 'ABSPATH', $base . '/public_html/adicional.example/' );
	require __DIR__ . '/lib/backups-env.php';
	$_SERVER['DOCUMENT_ROOT'] = $base . '/public_html/adicional.example';
	try { $dir = Digitalisimo_Backups::storage_directory(); } catch ( Throwable $e ) { $dir = 'excepción: ' . $e->getMessage(); }
	$inside = 0 === strpos( (string) $dir, $base . '/public_html/' ) || 0 === strpos( (string) $dir, 'excepción' );
	echo json_encode( array( 'almacen' => $dir, 'cuenta' => $base, 'ok' => ! $inside && 0 === strpos( (string) $dir, realpath( $base ) . '/.digitalisimo-backups-' ) ) ), "\n";
	if ( is_dir( (string) $dir ) ) bk_remove_tree( (string) $dir );
	bk_remove_tree( $base );
	bk_remove_tree( $GLOBALS['bk_root'] );
	exit( 0 );
}
if ( ! function_exists( 'proc_open' ) || '' === PHP_BINARY || 'Unknown' === PHP_OS_FAMILY || 'Windows' === PHP_OS_FAMILY ) {
	echo "DIGITALÍSIMO Backups: almacén de un clon de la misma cuenta OMITIDA (requiere proc_open y un PHP del sistema).\n";
	exit( 0 );
}
$failures = array();
/** Corre este archivo en un proceso propio y devuelve el JSON de su última línea. */
$child = function( array $args, array $env = array() ) {
	$process = proc_open( array_merge( array( PHP_BINARY, '-d', 'display_errors=stderr', __FILE__ ), $args ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env + array( 'PATH' => (string) getenv( 'PATH' ), 'TMPDIR' => sys_get_temp_dir() ) );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	proc_close( $process );
	$lines = array_filter( explode( "\n", trim( $out ) ) );
	$json = json_decode( (string) end( $lines ), true );
	return is_array( $json ) ? $json : array( 'salida' => substr( $out . ' ' . $err, 0, 800 ) );
};

if ( in_array( '--muerte', $argv, true ) ) {
	// ------------------------------------------------------------ La restauración que muere tras el RENAME
	$exists = '1' === (string) getenv( 'BK_CLONE_EXISTS' );
	require __DIR__ . '/lib/backups-env.php';
	$B = 'Digitalisimo_Backups';
	$storage = bk_storage();
	$here = get_option( $B::STORAGE );
	// El almacén de producción, en la misma cuenta: otra raíz de WordPress, la misma regla de nombre.
	$prod = dirname( (string) realpath( $_SERVER['DOCUMENT_ROOT'] ) ) . '/.digitalisimo-backups-' . substr( hash( 'sha256', 'produccion-' . bin2hex( random_bytes( 4 ) ) ), 0, 16 );
	if ( $exists ) {
		mkdir( $prod, 0700 );
		file_put_contents( $prod . '/digitalisimo-prod-20261109-120000-bbbbbbbb.zip', 'respaldo verificado de producción' );
		chmod( $prod . '/digitalisimo-prod-20261109-120000-bbbbbbbb.zip', 0600 );
		file_put_contents( $prod . '/digitalisimo-prod-20261110-120000-aaaaaaaa.zip.part', 'respaldo de producción a medio escribir' );
		file_put_contents( $prod . '/notas-de-produccion.txt', 'archivo ajeno' );
		chmod( $prod . '/notas-de-produccion.txt', 0644 );
	}
	update_option( $B::STORAGE, array( 'wordpress_root' => $here['wordpress_root'], 'directory' => $prod ) );
	$backup = bk_backup( true );
	update_option( $B::STORAGE, $here );
	register_shutdown_function( function() use ( $B, $storage, $prod, $exists, $here, $child ) {
		bk_close_dead_locks();
		$ctx = tempnam( sys_get_temp_dir(), 'bk-clone-ctx-' );
		$options = tempnam( sys_get_temp_dir(), 'bk-clone-opt-' );
		file_put_contents( $options, serialize( $GLOBALS['bk_options'] ) );
		file_put_contents( $ctx, json_encode( array( 'abspath' => ABSPATH, 'document_root' => $_SERVER['DOCUMENT_ROOT'], 'options' => $options, 'storage' => $storage, 'prod' => $exists ? $prod : '' ) ) );
		$r = $child( array( '--siguiente' ), array( 'BK_CLONE_CTX' => $ctx ) );
		$r['propio'] = $storage;
		$r['ok'] = ( $r['almacen'] ?? '' ) === $storage && ( $r['registro']['directory'] ?? '' ) === $here['directory'] && empty( $r['diario_pendiente'] ) && ! empty( $r['restauracion'] ) && ( ! $exists || ( ! empty( $r['prod_part'] ) && '644' === ( $r['prod_permisos'] ?? '' ) && empty( $r['prod_jobs'] ) ) );
		echo json_encode( $r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
		@unlink( $ctx );
		@unlink( $options );
		bk_cleanup();
		if ( $exists ) bk_remove_tree( $prod );
	} );
	$GLOBALS['wpdb']->exit_after = 'RENAME TABLE ';
	$report = Digitalisimo_Backups_Security::open( $backup, bk_call( $B, 'history_item', basename( $backup ) ), '' );
	bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report );
	echo json_encode( array( 'salida' => 'la restauración no se interrumpió' ) ), "\n";
	exit( 0 );
}

foreach ( array( 'c1' => '1', 'c1-control' => '0' ) as $label => $exists ) {
	$r = $child( array( '--muerte' ), array( 'BK_CLONE_EXISTS' => $exists ) );
	if ( ! empty( $r['ok'] ) ) echo 'ok (' . $label . ')' . "\n";
	else { $failures[] = $label; echo 'FALLA (' . $label . '): tras una muerte justo después del RENAME, la petición siguiente ' . ( '1' === $exists ? '(con la carpeta de producción existente) ' : '' ) . 'no usó su almacén, no aplicó el diario, perdió la restauración o tocó el de producción: ' . json_encode( $r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"; }
}
$r = $child( array( '--anidada' ) );
if ( ! empty( $r['ok'] ) ) echo 'ok (c6)' . "\n";
else { $failures[] = 'c6'; echo 'FALLA (c6): con la raíz pública dentro de la de otro sitio, el almacén quedó dentro de una raíz pública: ' . json_encode( $r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"; }
if ( $failures ) throw new RuntimeException( 'Almacén de un clon de la misma cuenta: fallaron ' . implode( ', ', $failures ) . '.' );
echo "DIGITALÍSIMO Backups: el almacén de un clon de la misma cuenta y el de una raíz anidada son siempre los de este sitio, fuera de toda raíz pública.\n";
