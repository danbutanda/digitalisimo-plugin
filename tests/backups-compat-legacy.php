<?php
/**
 * Compatibilidad: respaldos creados con el código publicado de cada versión con manifiesto v3 anterior a la seguridad
 * (de 1.0.38 a 1.0.58, todas las que haya en rollback/ o dist/; 1.0.38, 1.0.45 y 1.0.58 son obligatorias), cargado con
 * otro nombre de clase, se restauran con el código nuevo: con su fila del historial (procedencia, RESTAURAR) y sin ella
 * (no confiable, RESTAURAR SIN FIRMA). Los respaldos de red anteriores a v3 (de 1.0.1 a 1.0.37, manifiesto sin
 * formato y sin SHA-256 en su fila; 1.0.1, 1.0.24 y 1.0.37 son obligatorias), que 1.0.58 también restauraba, se
 * restauran como no confiables. Y hacia delante: 1.0.58 restaura un ZIP nuevo firmado, no abre un .dgbk sin tocar el
 * sitio y su rotación no lo borra.
 */
require __DIR__ . '/lib/backups-env.php';
$S = 'Digitalisimo_Backups_Security';
$repo = dirname( __DIR__ );

/** Carga la clase publicada de una versión con otro nombre; null si su ZIP no está en dist/ ni en rollback/. */
function bk_legacy_class( $repo, $version ) {
	$class = 'Digitalisimo_Backups_' . str_replace( '.', '_', $version );
	if ( class_exists( $class, false ) ) return $class;
	foreach ( array( 'dist', 'rollback' ) as $folder ) {
		$path = $repo . '/' . $folder . '/digitalisimo-backups-' . $version . '.zip';
		if ( ! is_file( $path ) ) continue;
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) throw new RuntimeException( 'No se pudo abrir ' . $path );
		$code = $zip->getFromName( 'digitalisimo-backups/includes/class-backups.php' );
		$zip->close();
		$code = preg_replace( '/^<\?php\s*/', '', (string) $code );
		$code = str_replace( "defined( 'ABSPATH' ) || exit;", '', $code );
		$code = preg_replace( '/\bfinal class Digitalisimo_Backups\b/', 'final class ' . $class, $code, 1, $count );
		if ( 1 !== $count ) throw new RuntimeException( 'No se encontró la clase en ' . $path );
		eval( $code ); // phpcs:ignore Squiz.PHP.Eval
		return $class;
	}
	return null;
}
function bk_restore_with( $path, $item, $confirmation ) {
	$report = Digitalisimo_Backups_Security::open( $path, $item, '' );
	try {
		Digitalisimo_Backups_Security::require_confirmation( $report, $confirmation );
		bk_call( 'Digitalisimo_Backups', 'restore_digitalisimo_zip', $report['path'], $report );
	} finally {
		Digitalisimo_Backups_Security::close( $report );
	}
	return $report['trust'];
}

$storage = bk_storage();
$checked = array();
// Versiones publicadas con manifiesto v3 y sin el bloque de seguridad (1.0.37 todavía escribía v2).
$versions = array();
foreach ( array( 'dist', 'rollback' ) as $folder ) {
	foreach ( glob( $repo . '/' . $folder . '/digitalisimo-backups-1.0.*.zip' ) ?: array() as $package ) {
		if ( preg_match( '/-(1\.0\.[0-9]+)\.zip$/', $package, $match ) && version_compare( $match[1], '1.0.38', '>=' ) && version_compare( $match[1], '1.0.58', '<=' ) ) $versions[ $match[1] ] = true;
	}
}
foreach ( array( '1.0.38', '1.0.45', '1.0.58' ) as $required ) if ( ! isset( $versions[ $required ] ) ) throw new RuntimeException( 'Falta el paquete publicado de Backups ' . $required . ' en dist/ o rollback/.' );
$versions = array_keys( $versions );
usort( $versions, 'version_compare' );
foreach ( $versions as $version ) {
	$class = bk_legacy_class( $repo, $version );
	if ( null === $class ) throw new RuntimeException( 'Falta el paquete publicado de Backups ' . $version . ' en dist/ o rollback/.' );
	$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido original ' . $version;
	$created = bk_call( $class, 'create_network' );
	$zip = new ZipArchive();
	$zip->open( $created );
	$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
	$sql = $zip->getFromName( 'database.sql' );
	$zip->close();
	if ( 'digitalisimo-backup/v3' !== $manifest['format'] || isset( $manifest['auth'] ) ) throw new RuntimeException( $version . ' no creó un ZIP v3 sin firma.' );
	// Su SQL pasa el dialecto del exportador, confiable y no confiable.
	foreach ( array( true, false ) as $trusted ) {
		$offset = 0;
		Digitalisimo_Backups_Sql_Guard::for_backup( $manifest, $trusted )->scan( function() use ( $sql, &$offset ) { if ( $offset >= strlen( $sql ) ) return null; $part = substr( $sql, $offset, 4096 ); $offset += 4096; return $part; } );
	}
	$path = $storage . '/digitalisimo-legacy-' . $version . '.zip';
	copy( $created, $path );
	@unlink( $created );
	$sha = hash_file( 'sha256', $path );
	// Sin fila: no confiable; RESTAURAR no basta y no toca el sitio.
	$GLOBALS['wpdb']->queries = array();
	bk_expect_failure( $version . ' sin fila', function() use ( $path ) { bk_restore_with( $path, array(), 'RESTAURAR' ); }, 'RESTAURAR SIN FIRMA' );
	if ( $GLOBALS['wpdb']->queries ) throw new RuntimeException( $version . ': el rechazo tocó la base de datos.' );
	$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado después';
	if ( 'untrusted' !== bk_restore_with( $path, array(), 'RESTAURAR SIN FIRMA' ) ) throw new RuntimeException( $version . ': sin fila debía ser no confiable.' );
	if ( ! preg_grep( '/^INSERT INTO `wp_posts`.*contenido original ' . preg_quote( $version, '/' ) . '/s', $GLOBALS['wpdb']->queries ) ) throw new RuntimeException( $version . ': la restauración no dejó el mismo estado.' );
	// Con fila y SHA-256 (desde 1.0.39 el historial lo guarda): procedencia y RESTAURAR.
	$row = array( 'file' => basename( $path ), 'scope' => 'installation', 'destination' => 'local' );
	if ( version_compare( $version, '1.0.39', '>=' ) ) {
		$GLOBALS['wpdb']->queries = array();
		if ( 'provenance' !== bk_restore_with( $path, $row + array( 'sha256' => $sha ), 'RESTAURAR' ) || ! $GLOBALS['wpdb']->queries ) throw new RuntimeException( $version . ': con su fila debía restaurarse como propio.' );
	} elseif ( 'untrusted' !== Digitalisimo_Backups_Security::open( $path, $row, '' )['trust'] ) {
		throw new RuntimeException( $version . ': una fila sin SHA-256 no prueba la procedencia.' );
	}
	$checked[] = $version;
}

// Respaldos de red anteriores a v3: sin formato ni SHA-256 por entrada, y su fila del historial sin SHA-256.
$older = array();
foreach ( glob( $repo . '/rollback/digitalisimo-backups-1.0.*.zip' ) ?: array() as $package ) {
	if ( preg_match( '/-(1\.0\.[0-9]+)\.zip$/', $package, $match ) && version_compare( $match[1], '1.0.1', '>=' ) && version_compare( $match[1], '1.0.38', '<' ) ) $older[ $match[1] ] = true;
}
foreach ( array( '1.0.1', '1.0.24', '1.0.37' ) as $required ) if ( ! isset( $older[ $required ] ) ) throw new RuntimeException( 'Falta el paquete publicado de Backups ' . $required . ' en rollback/.' );
$older = array_keys( $older );
usort( $older, 'version_compare' );
foreach ( $older as $version ) {
	$class = bk_legacy_class( $repo, $version );
	$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido original ' . $version;
	$created = bk_call( $class, 'create_network' );
	$zip = new ZipArchive();
	$zip->open( $created );
	$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	if ( 'network' !== ( $manifest['scope'] ?? '' ) || isset( $manifest['format'] ) ) throw new RuntimeException( $version . ' no creó un respaldo de red anterior a v3.' );
	$path = $storage . '/digitalisimo-legacy-' . $version . '.zip';
	copy( $created, $path );
	@unlink( $created );
	$GLOBALS['wpdb']->queries = array();
	bk_expect_failure( $version . ' sin firma', function() use ( $path ) { bk_restore_with( $path, array( 'file' => basename( $path ), 'scope' => 'network', 'destination' => 'local' ), 'RESTAURAR' ); }, 'RESTAURAR SIN FIRMA' );
	if ( $GLOBALS['wpdb']->queries ) throw new RuntimeException( $version . ': el rechazo tocó la base de datos.' );
	$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado después';
	if ( 'untrusted' !== bk_restore_with( $path, array( 'file' => basename( $path ), 'scope' => 'network', 'destination' => 'local' ), 'RESTAURAR SIN FIRMA' ) ) throw new RuntimeException( $version . ': debía ser no confiable.' );
	if ( ! preg_grep( '/^INSERT INTO `wp_posts`.*contenido original ' . preg_quote( $version, '/' ) . '/s', $GLOBALS['wpdb']->queries ) ) throw new RuntimeException( $version . ': la restauración no dejó el mismo estado.' );
	$checked[] = $version;
}

// Hacia delante: 1.0.58 restaura un ZIP nuevo firmado, no abre un .dgbk y su rotación no lo borra.
$legacy = bk_legacy_class( $repo, '1.0.58' );
Digitalisimo_Backups_Keys::set_passphrase( 'frase hacia delante bastante larga' );
$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido firmado';
$signed = bk_backup();
if ( ! $S::describe( $signed )['signed'] ) throw new RuntimeException( 'El respaldo nuevo no quedó firmado.' );
$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado otra vez';
$GLOBALS['wpdb']->queries = array();
bk_call( $legacy, 'restore_digitalisimo_zip', $signed );
if ( ! preg_grep( '/^INSERT INTO `wp_posts`.*contenido firmado/s', $GLOBALS['wpdb']->queries ) ) throw new RuntimeException( '1.0.58 no restauró el ZIP nuevo firmado.' );
// Límite declarado: 1.0.58 no conoce el llavero y su restauración reemplaza wp_options sin conservarlo; lo cifrado
// después se abre escribiendo la frase, que viaja con su salt en cada respaldo.
if ( isset( $GLOBALS['bk_options'][ Digitalisimo_Backups_Keys::KEYRING ] ) ) throw new RuntimeException( 'Se esperaba que 1.0.58 no conservara el llavero (límite declarado).' );
Digitalisimo_Backups_Keys::set_passphrase( 'frase hacia delante bastante larga' );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true );
$sealed = bk_backup();
$GLOBALS['wpdb']->queries = array();
$tree = bk_tree();
bk_expect_failure( '1.0.58 con .dgbk', function() use ( $legacy, $sealed ) { bk_call( $legacy, 'restore_digitalisimo_zip', $sealed ); }, 'No se puede abrir el ZIP' );
if ( $GLOBALS['wpdb']->queries || bk_tree() !== $tree ) throw new RuntimeException( '1.0.58 tocó el sitio al intentar abrir un .dgbk.' );
bk_call( $legacy, 'prune', $storage, 0 );
if ( ! file_exists( $sealed ) ) throw new RuntimeException( 'La rotación de 1.0.58 borró un respaldo cifrado.' );

bk_cleanup();
echo 'DIGITALÍSIMO Backups: respaldos de ' . implode( ', ', $checked ) . " se restauran (v3 con y sin fila, anteriores a v3 como no confiables); 1.0.58 restaura uno nuevo firmado y respeta los .dgbk.\n";
