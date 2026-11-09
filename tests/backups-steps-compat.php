<?php
/**
 * T2: el respaldo hecho por tramos tiene el mismo formato que el de un solo paso y lo restauran sin cambios el código
 * actual y el publicado de 1.0.58 y 1.0.60 (tomados de rollback/ y dist/, con sus clases renombradas). El SQL es
 * idéntico byte a byte al del exportador de un paso (también con una fila de más de 1 MB), el manifiesto es el mismo
 * JSON con las mismas claves en el mismo orden, la firma calculada por partes es la de libsodium, y lo mismo vale con
 * Zip64 forzado y con el contenedor DGBK.
 */
require __DIR__ . '/lib/backups-env.php';
$S = 'Digitalisimo_Backups_Security';
$repo = dirname( __DIR__ );
$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
$GLOBALS['bk_filters']['digitalisimo_backups_db_batch'] = 1;
$GLOBALS['bk_filters']['digitalisimo_backups_zip_chunk'] = 4096;
$big = '';
for ( $i = 0; $i < 3000; $i++ ) $big .= sprintf( "registro %05d: texto que se comprime\n", $i );
file_put_contents( ABSPATH . 'wp-content/uploads/grande.txt', $big );
file_put_contents( ABSPATH . 'wp-content/uploads/año ñandú.txt', "nombre UTF-8\n" );
$GLOBALS['wpdb']->posts[] = array( 'ID' => '3', 'post_content' => str_repeat( "fila grande con 'comillas' y \\ barras\n", 40000 ) );
$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido T2 original';

function compat_open( $path ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path, ZipArchive::CHECKCONS ) ) throw new RuntimeException( 'FALLA: ' . basename( $path ) . ' no supera CHECKCONS.' );
	return $zip;
}
function compat_fail( $message ) { throw new RuntimeException( 'FALLA: ' . $message ); }
/** Código publicado de una versión con todas sus clases renombradas (Digitalisimo_Backups* → Digi<versión>_Backups*). */
function compat_load( $repo, $version ) {
	$prefix = 'Digi' . str_replace( '.', '', $version ) . '_Backups';
	if ( class_exists( $prefix, false ) ) return $prefix;
	$package = null;
	foreach ( array( 'dist', 'rollback' ) as $folder ) if ( is_file( $repo . '/' . $folder . '/digitalisimo-backups-' . $version . '.zip' ) ) $package = $repo . '/' . $folder . '/digitalisimo-backups-' . $version . '.zip';
	if ( ! $package ) compat_fail( 'Falta el paquete publicado de Backups ' . $version . ' en dist/ o rollback/.' );
	$zip = new ZipArchive();
	$zip->open( $package );
	$files = array( 'class-backups-job-store', 'class-backups-jobs', 'class-backups-backup-job', 'security/class-sodium', 'security/class-keys', 'security/class-manifest-auth', 'security/class-encrypted-file', 'security/class-archive-inspector', 'security/class-sql-guard', 'security/class-security', 'class-backups-layout', 'trait-backups-service', 'class-backups' );
	foreach ( $files as $file ) {
		$code = $zip->getFromName( 'digitalisimo-backups/includes/' . $file . '.php' );
		if ( false === $code ) {
			if ( 'class-backups' === $file ) compat_fail( 'El paquete ' . $version . ' no trae class-backups.php.' );
			continue; // 1.0.58 tiene sólo la clase principal.
		}
		$code = preg_replace( '/^<\?php\s*/', '', $code );
		$code = str_replace( "defined( 'ABSPATH' ) || exit;", '', $code );
		$code = preg_replace( '/^(?:require_once [^\n]*|foreach \( array\([^\n]*require_once[^\n]*|unset\( \$digitalisimo_backups_part \);)$/m', '', $code );
		$code = preg_replace( '/\bDigitalisimo_Backups(?=\b|_)/', $prefix, $code );
		eval( $code ); // phpcs:ignore Squiz.PHP.Eval
	}
	$zip->close();
	return $prefix;
}
function compat_call( $class, $method, ...$args ) {
	$reflection = new ReflectionMethod( $class, $method );
	if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}
/** Restaura con el código de una versión (su inspección y su confirmación si la tiene) y comprueba que volvió el contenido. */
function compat_restore( $prefix, $path, $needle ) {
	$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado después del respaldo';
	$GLOBALS['wpdb']->queries = array();
	if ( class_exists( $prefix . '_Security', false ) ) {
		$security = $prefix . '_Security';
		$report = $security::open( $path, array(), '' );
		try {
			$security::require_confirmation( $report, 'RESTAURAR' );
			compat_call( $prefix, 'restore_digitalisimo_zip', $report['path'], $report );
		} finally {
			$security::close( $report );
		}
	} else {
		compat_call( $prefix, 'restore_digitalisimo_zip', $path );
	}
	if ( ! preg_grep( '/^INSERT INTO `wp_posts`.*' . preg_quote( $needle, '/' ) . '/s', $GLOBALS['wpdb']->queries ) ) compat_fail( $prefix . ' no restauró ' . basename( $path ) . '.' );
}

// 1. El SQL por tramos es el del exportador de un paso, byte a byte (también la fila de más de 1 MB).
Digitalisimo_Backups_Keys::set_passphrase( 'frase de la prueba de formato de T2' );
$signed = bk_backup();
$zip = compat_open( $signed );
$sql = $zip->getFromName( 'database.sql' );
$manifest_bytes = $zip->getFromName( 'manifest.json' );
$comment = $zip->getArchiveComment();
$zip->close();
$legacy_sql = tempnam( sys_get_temp_dir(), 'bk-t2-sql-' );
bk_call( 'Digitalisimo_Backups', 'database_sql_to_file', $GLOBALS['wpdb']->tables(), $legacy_sql );
// Las opciones cambiaron al registrar el respaldo; se compara el SQL de las mismas filas exportando otra vez por tramos.
$GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] = array();
$again = bk_backup();
$zip = compat_open( $again );
$sql_again = $zip->getFromName( 'database.sql' );
$zip->close();
$GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] = array();
bk_call( 'Digitalisimo_Backups', 'database_sql_to_file', $GLOBALS['wpdb']->tables(), $legacy_sql );
if ( file_get_contents( $legacy_sql ) !== $sql_again ) compat_fail( 'El SQL exportado por tramos difiere del exportador de un paso.' );
if ( 3 !== substr_count( $sql, 'INSERT INTO `wp_posts`' ) || false === strpos( $sql, str_repeat( "fila grande con \\'comillas\\' y \\\\ barras\\n", 3 ) ) ) compat_fail( 'La fila grande no se exportó igual.' );
@unlink( $again );

// 2. Manifiesto: el mismo JSON con formato de wp_json_encode( …, JSON_PRETTY_PRINT ) y la firma de libsodium.
$manifest = json_decode( $manifest_bytes, true );
if ( wp_json_encode( $manifest, JSON_PRETTY_PRINT ) !== $manifest_bytes ) compat_fail( 'El manifiesto escrito por partes no es el JSON de wp_json_encode.' );
$report = $S::open( $signed, array(), '' );
if ( 'signed' !== $report['trust'] ) compat_fail( 'El respaldo por tramos no se reconoce como firmado.' );
$auth = Digitalisimo_Backups_Manifest_Auth::verify( ( function() use ( $signed ) { $z = new ZipArchive(); $z->open( $signed ); return $z; } )(), '' );
if ( 'signed' !== $auth['state'] || Digitalisimo_Backups_Manifest_Auth::comment( $auth['root'], $manifest_bytes ) !== $comment ) compat_fail( 'La firma calculada por partes no es la de sodium_crypto_auth.' );
$S::close( $report );
$check = compat_open( $signed );
bk_call( 'Digitalisimo_Backups', 'verify_zip_contents', $check );
$check->close();
if ( 'transaction-steps' !== $manifest['database_consistency'] || Digitalisimo_Backups_Packer::PACKER !== $manifest['packer'] ) compat_fail( 'El manifiesto no declara la exportación por tramos o el empaquetador.' );

// 3. Mismas entradas, tamaños, SHA-256 y claves del manifiesto (en el mismo orden) que el empaquetado de un paso.
$sql_path = tempnam( sys_get_temp_dir(), 'bk-t2-db-' );
file_put_contents( $sql_path, $sql );
$spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retention' => 'none', 'via' => 'test' ) );
$one_step = rtrim( bk_storage(), '/' ) . '/un-paso.zip';
Digitalisimo_Backups::job_build_package( $one_step, $sql_path, array( 'size' => strlen( $sql ), 'sha256' => hash( 'sha256', $sql ), 'at' => $manifest['created_at'], 'mode' => $manifest['database_consistency'], 'tables' => $manifest['database_tables'] ), array( 'job_id' => $manifest['job_id'], 'spec' => $spec ) );
$zip = compat_open( $one_step );
$reference = json_decode( $zip->getFromName( 'manifest.json' ), true );
$zip->close();
if ( array_keys( $reference ) !== array_keys( $manifest ) ) compat_fail( 'Las claves del manifiesto no están en el mismo orden: ' . json_encode( array_keys( $manifest ) ) );
$volatile = array( 'backup_id', 'completed_at', 'files_started_at', 'files_completed_at', 'packer' );
foreach ( $reference as $key => $value ) if ( ! in_array( $key, $volatile, true ) && 'entries' !== $key && $value !== $manifest[ $key ] ) compat_fail( 'El manifiesto difiere del de un paso en ' . $key . '.' );
ksort( $reference['entries'] );
$entries = $manifest['entries'];
ksort( $entries );
if ( $reference['entries'] !== $entries ) compat_fail( 'Las entradas del ZIP por tramos no son las del ZIP de un paso.' );
@unlink( $one_step );
@unlink( $sql_path );
@unlink( $legacy_sql );

// 4. Lo restauran el código actual y el publicado de 1.0.58 y 1.0.60.
$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido T2 original';
compat_restore( 'Digitalisimo_Backups', $signed, 'contenido T2 original' );
// 1.0.58 no conoce el llavero y su restauración reemplaza wp_options sin conservarlo (límite declarado): va la última.
foreach ( array( '1.0.60', '1.0.58' ) as $version ) compat_restore( compat_load( $repo, $version ), $signed, 'contenido T2 original' );

// 5. Zip64 forzado (como con más de 65 535 entradas o 4 GiB): CHECKCONS, inspección, contenido y las tres restauraciones.
Digitalisimo_Backups_Keys::set_passphrase( 'frase de la prueba de formato de T2' );
Digitalisimo_Backups_Zip_Writer::$force_zip64 = true;
$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido T2 zip64';
$wide = bk_backup();
Digitalisimo_Backups_Zip_Writer::$force_zip64 = false;
$zip = compat_open( $wide );
bk_call( 'Digitalisimo_Backups', 'verify_zip_contents', $zip );
if ( $big !== $zip->getFromName( 'wordpress/wp-content/uploads/grande.txt' ) || "nombre UTF-8\n" !== $zip->getFromName( 'wordpress/wp-content/uploads/año ñandú.txt' ) ) compat_fail( 'El ZIP con Zip64 no devuelve los archivos.' );
$zip->close();
$raw = file_get_contents( $wide );
if ( false === strpos( $raw, "PK\x06\x06" ) || false === strpos( $raw, "PK\x06\x07" ) ) compat_fail( 'El ZIP forzado no lleva el fin de directorio Zip64.' );
compat_restore( 'Digitalisimo_Backups', $wide, 'contenido T2 zip64' );
foreach ( array( '1.0.60', '1.0.58' ) as $version ) compat_restore( compat_load( $repo, $version ), $wide, 'contenido T2 zip64' );

// 6. Cifrado: el DGBK sellado al final de «verify» se abre, se verifica y se restaura; 1.0.60 también.
Digitalisimo_Backups_Keys::set_passphrase( 'frase de la prueba de formato de T2' );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true );
$GLOBALS['wpdb']->posts[0]['post_content'] = 'contenido T2 cifrado';
$sealed = bk_backup();
if ( '.dgbk' !== substr( $sealed, -5 ) || ! Digitalisimo_Backups_Encrypted_File::is_encrypted( $sealed ) ) compat_fail( 'El respaldo cifrado por tramos no es un DGBK.' );
compat_restore( 'Digitalisimo_Backups', $sealed, 'contenido T2 cifrado' );
compat_restore( compat_load( $repo, '1.0.60' ), $sealed, 'contenido T2 cifrado' );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false );

bk_cleanup();
echo "DIGITALÍSIMO Backups: el respaldo por tramos tiene el formato de siempre (SQL, manifiesto y firma idénticos, Zip64 y DGBK) y lo restauran el código actual, 1.0.58 y 1.0.60.\n";
