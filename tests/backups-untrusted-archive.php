<?php
/**
 * Archivos no confiables: cada ZIP hostil se rechaza antes de tocar el sitio, sin consultas SQL, sin escribir en
 * ABSPATH y sin crear la copia previa. También comprueba la confirmación según la confianza y el escritor seguro.
 */
define( 'DIGITALISIMO_BACKUPS_MAX_ENTRIES', 40 );
require __DIR__ . '/lib/backups-env.php';

$storage = bk_storage();
$valid = bk_call( 'Digitalisimo_Backups', 'create_network' );
if ( ! is_file( $valid ) || '.zip' !== substr( $valid, -4 ) ) throw new RuntimeException( 'No se creó el respaldo de referencia.' );
$zip = new ZipArchive();
$zip->open( $valid );
$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
$files = array();
for ( $i = 0; $i < $zip->numFiles; $i++ ) { $name = $zip->getNameIndex( $i ); $files[ $name ] = $zip->getFromName( $name ); }
$zip->close();
$GLOBALS['wpdb']->queries = array();
if ( isset( $manifest['auth'] ) || ! in_array( 'options:digitalisimo_backups_vault_*', $manifest['excluded_secrets'] ?? array(), true ) ) throw new RuntimeException( 'Sin clave el manifiesto no lleva firma y declara las filas excluidas.' );

/** Copia del respaldo válido con cambios; el manifiesto se ajusta salvo que se pida lo contrario. */
function bk_variant( $name, $files, $manifest, $changes, $fix_manifest = true ) {
	foreach ( $changes as $entry => $content ) {
		if ( null === $content ) { unset( $files[ $entry ], $manifest['entries'][ $entry ] ); continue; }
		$files[ $entry ] = $content;
		$manifest['entries'][ $entry ] = array( 'size' => strlen( $content ), 'sha256' => hash( 'sha256', $content ) );
	}
	if ( $fix_manifest ) {
		unset( $manifest['entries']['manifest.json'] );
		$manifest['file_count'] = count( $manifest['entries'] ) - 1;
		$manifest['uncompressed_size'] = array_sum( array_column( $manifest['entries'], 'size' ) );
		$files['manifest.json'] = json_encode( $manifest, JSON_PRETTY_PRINT );
	}
	$path = bk_storage() . '/digitalisimo-' . $name . '.zip';
	@unlink( $path );
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE );
	foreach ( $files as $entry => $content ) $zip->addFromString( $entry, $content );
	$zip->close();
	return $path;
}
/** Restaura como lo hace la pantalla: sin fila en el historial, así que el archivo no es confiable. */
function bk_restore_untrusted( $path ) {
	$report = Digitalisimo_Backups_Security::open( $path, array(), '' );
	try {
		if ( 'untrusted' !== $report['trust'] ) throw new RuntimeException( 'Un archivo sin firma ni registro debe ser no confiable.' );
		bk_call( 'Digitalisimo_Backups', 'restore_digitalisimo_zip', $report['path'], $report );
	} finally {
		Digitalisimo_Backups_Security::close( $report );
	}
}
$sql = $files['database.sql'];
$raw_base = array();
foreach ( $files as $name => $content ) $raw_base[] = array( 'name' => $name, 'data' => $content );
$cases = array(
	'punto-punto' => array( fn() => bk_variant( 'dotdot', $files, $manifest, array( 'wordpress/../escape.php' => '<?php' ) ), 'ruta no permitida' ),
	'salida-doble' => array( fn() => bk_variant( 'outside', $files, $manifest, array( 'wordpress/../../x.php' => '<?php' ) ), 'ruta no permitida' ),
	'absoluta' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-abs.zip', array_merge( $raw_base, array( array( 'name' => '/etc/passwd', 'data' => 'x' ) ) ) ), 'ruta no permitida' ),
	'unidad' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-drive.zip', array_merge( $raw_base, array( array( 'name' => 'C:/x.php', 'data' => 'x' ) ) ) ), 'ruta no permitida' ),
	'nulo' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-nul.zip', array_merge( $raw_base, array( array( 'name' => "wordpress/a\0.php", 'data' => 'x' ) ) ) ), '' ),
	'barra-invertida' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-backslash.zip', array_merge( $raw_base, array( array( 'name' => 'wordpress\\..\\x.php', 'data' => 'x' ) ) ) ), 'barra invertida' ),
	'raiz-desconocida' => array( fn() => bk_variant( 'root', $files, $manifest, array( 'evil.php' => '<?php' ) ), 'fuera de las raíces' ),
	'enlace' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-link.zip', array_merge( $raw_base, array( array( 'name' => 'wordpress/wp-content/link', 'data' => '/etc/passwd', 'attr' => 0120777 << 16 ) ) ) ), 'enlace simbólico' ),
	'dispositivo' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-fifo.zip', array_merge( $raw_base, array( array( 'name' => 'wordpress/wp-content/fifo', 'data' => '', 'attr' => 0010644 << 16 ) ) ) ), 'archivo especial' ),
	'cifrada' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-encrypted.zip', array_merge( $raw_base, array( array( 'name' => 'wordpress/secret.php', 'data' => 'x', 'flags' => 1 ) ) ) ), '' ),
	'metodo' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-method.zip', array_merge( $raw_base, array( array( 'name' => 'wordpress/odd.php', 'data' => 'x', 'method' => 12 ) ) ) ), '' ),
	'duplicado' => array( fn() => bk_raw_zip( $storage . '/digitalisimo-dup.zip', array_merge( $raw_base, array( array( 'name' => 'wordpress/wp-load.php', 'data' => 'otro' ) ) ) ), '' ),
	'demasiadas' => array( function() use ( $files, $manifest ) { $extra = array(); for ( $i = 0; $i < 40; $i++ ) $extra[ 'wordpress/many/' . $i . '.txt' ] = 'x'; return bk_variant( 'many', $files, $manifest, $extra ); }, 'máximo permitido' ),
	'entrada-de-mas' => array( fn() => bk_variant( 'extra', $files, $manifest, array( 'wordpress/extra.php' => '<?php' ), false ), 'manifiesto' ),
	'entrada-de-menos' => array( fn() => bk_variant( 'missing', $files, $manifest, array( 'wordpress/wp-content/plugins/example.php' => null ), false ), 'manifiesto' ),
	'sha-no-hex' => array( function() use ( $files, $manifest ) { $manifest['entries']['wordpress/wp-load.php']['sha256'] = strtoupper( $manifest['entries']['wordpress/wp-load.php']['sha256'] ); return bk_variant( 'upper', $files, $manifest, array() ); }, 'SHA-256' ),
	'tabla-ajena' => array( fn() => bk_variant( 'table', $files, $manifest, array( 'database.sql' => $sql . "DROP TABLE IF EXISTS `wp_users`;\n" ) ), 'no figura en el manifiesto' ),
	'grant' => array( fn() => bk_variant( 'grant', $files, $manifest, array( 'database.sql' => "GRANT ALL ON *.* TO 'x'@'%';\n" . $sql ) ), 'sentencia 1, GRANT' ),
	'data-directory' => array( fn() => bk_variant( 'datadir', $files, $manifest, array( 'database.sql' => str_replace( 'COLLATE=utf8mb4_unicode_520_ci', "COLLATE=utf8mb4_unicode_520_ci DATA DIRECTORY='/var/www'", $sql ) ) ), 'DIRECTORY' ),
	'prefijo' => array( function() use ( $files, $manifest ) { $manifest['table_prefix'] = 'wp_;DROP'; return bk_variant( 'prefix', $files, $manifest, array() ); }, 'prefijo' ),
	'id-no-uuid' => array( function() use ( $files, $manifest ) { $manifest['backup_id'] = '../../etc'; return bk_variant( 'uuid', $files, $manifest, array() ); }, 'identificador' ),
);
$tree = bk_tree();
$history = $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] ?? array();
$count = 0;
foreach ( $cases as $label => $case ) {
	$path = $case[0]();
	$queries = count( $GLOBALS['wpdb']->queries );
	bk_expect_failure( $label, function() use ( $path ) { bk_restore_untrusted( $path ); }, $case[1] );
	if ( count( $GLOBALS['wpdb']->queries ) !== $queries ) throw new RuntimeException( $label . ': el rechazo hizo consultas SQL.' );
	if ( bk_tree() !== $tree ) throw new RuntimeException( $label . ': el rechazo escribió en ABSPATH.' );
	if ( glob( $storage . '/safety-before-restore-*' ) || ( $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] ?? array() ) !== $history ) throw new RuntimeException( $label . ': el rechazo creó una copia previa.' );
	@unlink( $path );
	$count++;
}

// libzip rechaza los duplicados al abrir con CHECKCONS; si otra versión los acepta, el inspector los rechaza.
$dup = bk_raw_zip( $storage . '/digitalisimo-dup2.zip', array( array( 'name' => 'wordpress/a.php', 'data' => '1' ), array( 'name' => 'wordpress/a.php', 'data' => '2' ) ) );
$dup_zip = new ZipArchive();
if ( true === $dup_zip->open( $dup ) ) { bk_expect_failure( 'duplicado (inspector)', function() use ( $dup_zip ) { Digitalisimo_Backups_Archive_Inspector::entries( $dup_zip, false ); }, 'duplicados' ); $dup_zip->close(); }
@unlink( $dup );

// Espacio declarado mayor que el disco: se inyecta el espacio libre.
$GLOBALS['bk_filters']['digitalisimo_backups_free_space'] = 1024.0;
bk_expect_failure( 'espacio', function() use ( $valid ) { bk_restore_untrusted( $valid ); }, 'No hay espacio suficiente' );
unset( $GLOBALS['bk_filters']['digitalisimo_backups_free_space'] );
// Colisión de mayúsculas sólo en un disco que no las distingue (sonda simulada).
$collision = bk_variant( 'case', $files, $manifest, array( 'wordpress/wp-content/Readme.txt' => 'a', 'wordpress/wp-content/README.txt' => 'b' ) );
$GLOBALS['bk_filters']['digitalisimo_backups_case_insensitive'] = true;
bk_expect_failure( 'mayúsculas', function() use ( $collision ) { bk_restore_untrusted( $collision ); }, 'mayúsculas' );
$GLOBALS['bk_filters']['digitalisimo_backups_case_insensitive'] = false;
$zip->open( $collision );
Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
$zip->close();
unset( $GLOBALS['bk_filters']['digitalisimo_backups_case_insensitive'] );
@unlink( $collision );
if ( count( $GLOBALS['wpdb']->queries ) || bk_tree() !== $tree || glob( $storage . '/safety-before-restore-*' ) ) throw new RuntimeException( 'Las comprobaciones de espacio y mayúsculas cambiaron el sitio.' );

// Un respaldo confiable conserva la conversión de «\» a «/» y sus opciones de CREATE TABLE.
$zip->open( $valid );
Digitalisimo_Backups_Archive_Inspector::entries( $zip, true );
$zip->close();
$trusted = new Digitalisimo_Backups_Sql_Guard( 'exporter', true, array( 'wp_options' ) );
$trusted->check( "CREATE TABLE `wp_options` (`a` int) DATA DIRECTORY='/srv/mysql'" );

// Tamaño real mayor que el declarado: el escritor nunca escribe más de lo declarado.
$liar = bk_raw_zip( $storage . '/digitalisimo-liar.zip', array( array( 'name' => 'wordpress/big.bin', 'data' => str_repeat( 'A', 50000 ), 'method' => 8, 'declared' => 10 ) ) );
$liar_zip = new ZipArchive();
if ( true === $liar_zip->open( $liar ) ) {
	bk_expect_failure( 'tamaño falso', function() use ( $liar_zip ) { bk_call( 'Digitalisimo_Backups', 'extract_digitalisimo_files', $liar_zip ); }, '' );
	$liar_zip->close();
}
if ( file_exists( ABSPATH . 'big.bin' ) || glob( ABSPATH . 'big.bin.digitalisimo-restore-*' ) ) throw new RuntimeException( 'Un tamaño declarado falso dejó un archivo en ABSPATH.' );
@unlink( $liar );

// Confirmación según la confianza.
bk_expect_failure( 'sin firma con RESTAURAR', function() { Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'untrusted', 'auth' => 'unsigned' ), 'restaurar' ); }, 'RESTAURAR SIN FIRMA' );
Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'untrusted', 'auth' => 'unsigned' ), ' restaurar  sin firma ' );
Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'provenance', 'auth' => 'unsigned' ), 'RESTAURAR' );
Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'signed', 'auth' => 'signed' ), 'RESTAURAR' );
bk_expect_failure( 'confirmación vacía', function() { Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'signed', 'auth' => 'signed' ), 'SI' ); }, 'RESTAURAR' );
$GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] = array( 'require_signed' => true );
bk_expect_failure( 'sólo firmados', function() { Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'untrusted', 'auth' => 'unsigned' ), 'RESTAURAR SIN FIRMA' ); }, 'sólo permite restaurar respaldos firmados' );
Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'provenance', 'auth' => 'unsigned' ), 'RESTAURAR' );
unset( $GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] );

// De punta a punta por admin-post: un archivo suelto con RESTAURAR no toca el sitio; con RESTAURAR SIN FIRMA se restaura.
$loose = basename( $valid );
list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => $loose, 'confirmation' => 'RESTAURAR' ) );
if ( $ok || false === strpos( $message, 'RESTAURAR SIN FIRMA' ) || count( $GLOBALS['wpdb']->queries ) || bk_tree() !== $tree ) throw new RuntimeException( 'Un archivo sin registro se restauró con RESTAURAR: ' . $message );
list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => '.operation.lock', 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
if ( $ok ) throw new RuntimeException( 'Se aceptó restaurar un archivo interno del almacén.' );
// Un enlace del almacén no es un respaldo, aunque apunte a otro respaldo del mismo almacén o a un archivo de fuera.
symlink( $valid, $storage . '/digitalisimo-enlace-interno.zip' );
symlink( ABSPATH . 'wp-config.php', $storage . '/digitalisimo-enlace-externo.zip' );
foreach ( array( 'digitalisimo-enlace-interno.zip', 'digitalisimo-enlace-externo.zip' ) as $link ) if ( '' !== bk_call( 'Digitalisimo_Backups', 'local_path', $link ) ) throw new RuntimeException( 'Se aceptó un enlace del almacén como respaldo: ' . $link );
unlink( $storage . '/digitalisimo-enlace-interno.zip' );
unlink( $storage . '/digitalisimo-enlace-externo.zip' );
$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado después del respaldo';
list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => $loose, 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
if ( ! $ok ) throw new RuntimeException( 'El respaldo válido sin registro no se restauró con RESTAURAR SIN FIRMA: ' . $message );
$inserts = array_filter( $GLOBALS['wpdb']->queries, function( $q ) { return 0 === strpos( $q, 'INSERT INTO `wp_posts`' ); } );
if ( ! $inserts || false === strpos( implode( "\n", $inserts ), 'punto y coma' ) ) throw new RuntimeException( 'La restauración no reimportó los datos del respaldo.' );
if ( 1 !== count( glob( $storage . '/safety-before-restore-*' ) ) ) throw new RuntimeException( 'La restauración no creó su copia previa.' );
if ( ! in_array( Digitalisimo_Backups::ACTION_RESTORE, $GLOBALS['bk_nonces'], true ) ) throw new RuntimeException( 'La restauración no comprobó el nonce.' );
$GLOBALS['bk_caps'] = array();
try { bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => $loose, 'confirmation' => 'RESTAURAR SIN FIRMA' ) ); throw new RuntimeException( 'Se restauró sin permisos.' ); } catch ( Bk_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
try { bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'otro', 'backup_file' => $loose, 'confirmation' => 'RESTAURAR SIN FIRMA' ) ); throw new RuntimeException( 'Se restauró sin nonce.' ); } catch ( Bk_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement

// Un archivo que sólo cambia el SHA-256 registrado se rechaza aunque esté bien formado.
$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
bk_expect_failure( 'SHA del historial', function() use ( $valid ) { Digitalisimo_Backups_Security::open( $valid, array( 'sha256' => str_repeat( '0', 64 ) ), '' ); }, 'ya no coincide' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: {$count} archivos hostiles rechazados sin consultas, sin escrituras y sin copia previa.\n";
