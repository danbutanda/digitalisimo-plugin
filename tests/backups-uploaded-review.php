<?php
/**
 * Revisión adversarial de «lo subido queda en la lista» (rama backups/uploaded-in-list), con el motor de trabajos, las
 * cadenas y la restauración por pasos reales (tests/lib/backups-env.php). Cada caso falla con el código anterior a los
 * arreglos (80abd237):
 *
 *   M1  un punto subido sin firma de este sitio cuyo manifiesto nombra en su cadena un respaldo hecho aquí no lo fija ni
 *       lo bloquea (se borra y la retención lo trata como siempre) y queda «cadena incompleta», sin «Restaurar»; un
 *       incremental propio, firmado con la clave de este sitio y vuelto a subir, sí se apoya en su base de aquí;
 *   M3  con el historial lleno, una subida nunca saca la fila de un respaldo hecho aquí, y una fila subida tampoco sale
 *       cuando llegan 100 respaldos propios más;
 *   M5  un `.dgbk` de otra clave subido sin su frase no se registra (queda «sin registro»: «da su frase»); con su frase
 *       (al cerrar la subida o «Agregar al listado») se registra con su tipo y su cadena, y su base no se borra;
 *   B1  subir otra vez un respaldo hecho aquí devuelve la confianza y la palabra de su fila (RESTAURAR);
 *   B4  la retención de una automatización no toca filas subidas aunque sean varias y la vista previa de restaurar por
 *       partes de un respaldo v3 no lo trata como anterior a v3 ni avisa en PHP;
 *   B5  con «sólo firmados», un ZIP firmado con una frase que se escribió al subirlo dice «escribe su frase», no
 *       «no se restaurará».
 *
 * Y los de la verificación de esos arreglos (fallan con 9fc9c0d2):
 *
 *   N1  un incremental propio sin firma, descargado y vuelto a subir, se restaura otra vez sobre su base de aquí (su
 *       eslabón es exactamente el que nombra: `backup_id`, SHA-256 del manifiesto y posición);
 *   N2  un punto subido sin firma que nombra por su nombre otro subido (que no es su eslabón) no bloquea su «Borrar»;
 *   N3  las filas subidas cuyo archivo se borró fuera de Backups (30, por FTP) salen en la retención (global y de una
 *       automatización); lo subido tiene su propio tope de 100 filas (sale la más antigua, nunca una propia, una
 *       bloqueada ni una de la que depende otra);
 *   N4  un `.dgbk` subido sin su frase y vuelto a subir con ella no deja otra copia: se registra con su nombre y su cadena
 *       queda completa; el aviso sólo propone «Agregar al listado» con su frase.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/backups-incremental/helpers.php';
if ( ! function_exists( 'wp_delete_file' ) ) { function wp_delete_file( $file ) { return @unlink( $file ); } }
$root = rtrim( ABSPATH, '/' );
$B = 'Digitalisimo_Backups';
$K = 'Digitalisimo_Backups_Keys';
$S = 'Digitalisimo_Backups_Security';
$failures = 0;

foreach ( array(
	'index.php' => "<?php // portada\n",
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 20000 ),
) as $name => $content ) inc_put( $name, $content );
$GLOBALS['bk_options']['blogname'] = 'Este sitio';

function rv_upload( $name, $bytes, $secret = '' ) {
	$session = Digitalisimo_Backups::service_upload_start( array( 'name' => $name, 'size' => strlen( $bytes ) ) );
	inc_check( ! is_wp_error( $session ), 'No empezó la subida: ' . ( is_wp_error( $session ) ? $session->get_error_message() : '' ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) {
		$chunk = Digitalisimo_Backups::service_upload_chunk( $session['id'], $offset, array( 'data' => substr( $bytes, $offset, $session['chunk_size'] ) ) );
		inc_check( ! is_wp_error( $chunk ), 'Un fragmento falló.' );
	}
	return Digitalisimo_Backups::service_upload_finish( $session['id'], array( 'via' => 'rest', 'secret' => $secret ) );
}
function rv_summary( $file ) { foreach ( Digitalisimo_Backups::service_backups() as $row ) if ( $file === $row['file'] ) return $row; return null; }
function rv_reset() {
	foreach ( glob( rtrim( bk_storage(), '/' ) . '/digitalisimo-*.{zip,dgbk}', GLOB_BRACE ) ?: array() as $file ) @unlink( $file );
	$GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] = array();
	bk_remove_tree( rtrim( bk_storage(), '/' ) . '/chains' );
}
function rv_delete( $file ) {
	return bk_post( array( 'Digitalisimo_Backups', 'delete_backup' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_DELETE, 'file' => basename( $file ) ) );
}

// «Otro sitio»: una cadena sin firma (base + incremental 1). Este sitio sólo se queda con sus bytes.
inc_put( 'wp-content/uploads/2026/11/a.txt', 'a 0' );
$other_base = inc_path( inc_run( 'otro-sitio' ) );
inc_put( 'wp-content/uploads/2026/11/a.txt', 'a 1' );
$other_i1 = inc_path( inc_run( 'otro-sitio' ) );
$other = array( 'base' => file_get_contents( $other_base ), 'i1' => file_get_contents( $other_i1 ), 'base_name' => basename( $other_base ), 'i1_name' => basename( $other_i1 ) );
rv_reset();

inc_case( 'M1 un punto subido sin firma que nombra un respaldo hecho aquí no lo fija, no lo bloquea y queda «cadena incompleta»', function() use ( $other, $B ) {
	$mine = bk_backup( true );
	touch( $mine, time() - 7200 );
	// El manifiesto del punto (no está en su inventario) nombra como base el respaldo hecho aquí.
	$tmp = sys_get_temp_dir() . '/rv-punto-' . bin2hex( random_bytes( 3 ) ) . '.zip';
	file_put_contents( $tmp, $other['i1'] );
	$zip = new ZipArchive();
	$zip->open( $tmp );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$manifest['chain']['links'][0]['file'] = basename( $mine );
	$zip->addFromString( 'manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	$zip->close();
	$up = rv_upload( 'digitalisimo-backup-otro-punto-20261115-120000-0a0b0c0d.zip', file_get_contents( $tmp ) );
	unlink( $tmp );
	inc_check( ! is_wp_error( $up ) && true === $up['listed'] && false === $up['restorable'], 'El punto subido no quedó en la lista sin «Restaurar»: ' . json_encode( is_wp_error( $up ) ? $up->get_error_message() : $up ) );
	$summary = rv_summary( $up['file'] );
	inc_check( false === $summary['restorable'] && array( basename( $mine ) ) === $summary['chain']['missing'], 'La lista no dice «cadena incompleta» del punto subido: ' . json_encode( $summary['chain'] ) );
	inc_check( 0 === strpos( (string) bk_call( $B, 'uploaded_blocked_text', inc_row( $up['file'] ) ), 'Cadena incompleta' ), 'La pantalla no dice «Cadena incompleta».' );
	inc_check( array() === Digitalisimo_Backups_Chain::dependents( (array) $GLOBALS['bk_options'][ $B::HISTORY ], basename( $mine ) ), 'El punto subido figura como dependiente del respaldo hecho aquí.' );
	// La retención global (Conservar 1) borra el propio más antiguo: el subido no lo fija.
	$newer = bk_backup( true );
	bk_call( $B, 'prune_locked', bk_storage(), 1 );
	inc_check( ! is_file( $mine ) && null === inc_row( $mine ) && is_file( $newer ), 'La retención no borró el respaldo hecho aquí que nombra el punto subido.' );
	$restore = inc_restore( $up['file'], 'RESTAURAR' );
	inc_check( is_wp_error( $restore ) && 'digitalisimo_backups_chain_incomplete' === $restore->get_error_code(), 'El punto subido con la cadena incompleta empezó a restaurarse: ' . ( is_wp_error( $restore ) ? $restore->get_error_code() : 'aceptado' ) );
	// «Borrar» de uno hecho aquí que el punto nombra (otro, aún presente) se acepta.
	$third = bk_backup( true );
	$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
	foreach ( $history as &$row ) if ( $up['file'] === $row['file'] ) $row['chain']['links'][0]['file'] = basename( $third );
	unset( $row );
	$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
	list( $ok, $message ) = rv_delete( $third );
	inc_check( $ok && ! is_file( $third ), '«Borrar» de un respaldo hecho aquí se negó por el punto subido: ' . $message );
	rv_reset();
} );

inc_case( 'M1 un incremental propio firmado con la clave de este sitio, vuelto a subir, se apoya en su base de aquí', function() use ( $B, $K, $root ) {
	$K::set_passphrase( 'frase de este sitio para la cadena propia de la revisión' );
	inc_put( 'wp-content/uploads/2026/11/b.txt', 'b 0' );
	$base = inc_path( inc_run( 'propia' ) );
	inc_put( 'wp-content/uploads/2026/11/b.txt', 'b 1' );
	$GLOBALS['bk_options']['blogname'] = 'Punto propio 1';
	$point = inc_path( inc_run( 'propia' ) );
	$tree = inc_files();
	$db = inc_db();
	$bytes = file_get_contents( $point );
	unlink( $point );
	$GLOBALS['bk_options'][ $B::HISTORY ] = array_values( array_filter( (array) $GLOBALS['bk_options'][ $B::HISTORY ], function( $row ) use ( $point ) { return basename( $point ) !== $row['file']; } ) );
	inc_put( 'wp-content/uploads/2026/11/b.txt', 'b 2 después' );
	$GLOBALS['bk_options']['blogname'] = 'Cambiado';
	$up = rv_upload( basename( $point ), $bytes );
	inc_check( ! is_wp_error( $up ) && true === $up['restorable'] && 'signed' === $up['trust'] && true === inc_row( $up['file'] )['upload']['site_key'], 'El incremental propio firmado no quedó restaurable sobre su base de aquí: ' . json_encode( is_wp_error( $up ) ? $up->get_error_message() : $up ) );
	inc_check( array( $up['file'] ) === Digitalisimo_Backups_Chain::dependents( (array) $GLOBALS['bk_options'][ $B::HISTORY ], basename( $base ) ), 'El incremental propio vuelto a subir no protege su base.' );
	$result = inc_restore( $up['file'], 'RESTAURAR' );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok() && $tree === inc_files() && $db === inc_db(), 'El incremental propio vuelto a subir no se restauró sobre su base: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	foreach ( array_keys( $GLOBALS['bk_options'] ) as $key ) if ( 0 === strpos( $key, $K::SECRET_PREFIX ) ) unset( $GLOBALS['bk_options'][ $key ] );
	rv_reset();
} );

inc_case( 'M3 con el historial lleno, una subida no saca la fila de un respaldo hecho aquí, y lo subido no sale con 100 propios más', function() use ( $other, $B ) {
	$own = bk_backup( true );
	$fill = function( $count, $prefix ) {
		$rows = array();
		for ( $i = 0; $i < $count; $i++ ) $rows[] = array( 'date' => gmdate( 'Y-m-d H:i:s', time() - $i ), 'file' => sprintf( 'digitalisimo-backup-%s-%03d.zip', $prefix, $i ), 'size' => 1, 'sha256' => str_repeat( 'a', 64 ), 'destination' => 'local', 'scope' => 'installation', 'policy' => 'automation-diaria', 'backup_type' => 'full', 'origin' => 'created' );
		return $rows;
	};
	$GLOBALS['bk_options'][ $B::HISTORY ] = array_merge( $fill( 99, 'relleno' ), array( inc_row( $own ) ) );
	$up = rv_upload( 'digitalisimo-backup-otro-sitio-20261115-130000-1a2b3c4d.zip', $other['base'] );
	inc_check( ! is_wp_error( $up ), 'La subida falló.' );
	inc_check( null !== inc_row( $own ) && 101 === count( $GLOBALS['bk_options'][ $B::HISTORY ] ), 'Una subida sacó del historial la fila de un respaldo hecho aquí con su archivo: ' . count( $GLOBALS['bk_options'][ $B::HISTORY ] ) . ' filas.' );
	$restore = Digitalisimo_Backups::service_restore( basename( $own ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	inc_check( ! is_wp_error( $restore ), 'El respaldo hecho aquí perdió su confianza «registrado» tras una subida: ' . ( is_wp_error( $restore ) ? $restore->get_error_code() : '' ) );
	for ( $i = 0; $i < 2000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	// 100 respaldos propios más nuevos: la fila subida (al final) no sale; las propias siguen con su tope de 100.
	$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
	$capped = bk_call( $B, 'history_cap', array_merge( $fill( 100, 'nuevo' ), $history ) );
	$files = array_column( $capped, 'file' );
	$own_unlocked = array_filter( $capped, function( $row ) { return ! Digitalisimo_Backups::is_uploaded_row( $row ) && empty( $row['locked'] ); } );
	inc_check( in_array( $up['file'], $files, true ) && 100 === count( $own_unlocked ), 'El tope del historial sacó la fila subida (o no aplicó su tope de 100 a las propias): ' . count( $capped ) . ' filas, ' . count( $own_unlocked ) . ' propias sin bloquear.' );
	rv_reset();
} );

inc_case( 'M5 un .dgbk de otra clave subido sin su frase no se registra; con su frase entra con su tipo y su cadena', function() use ( $B, $K, $S ) {
	$phrase = 'frase de la cadena cifrada del otro sitio, bastante larga';
	$K::set_passphrase( $phrase );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
	inc_put( 'wp-content/uploads/2026/11/c.txt', 'c 0' );
	$base = inc_path( inc_run( 'cifrada' ) );
	inc_put( 'wp-content/uploads/2026/11/c.txt', 'c 1' );
	$point = inc_path( inc_run( 'cifrada' ) );
	$bytes = array( 'base' => file_get_contents( $base ), 'point' => file_get_contents( $point ) );
	$names = array( 'base' => basename( $base ), 'point' => basename( $point ) );
	foreach ( array_keys( $GLOBALS['bk_options'] ) as $key ) if ( 0 === strpos( $key, $K::SECRET_PREFIX ) ) unset( $GLOBALS['bk_options'][ $key ] );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
	rv_reset();
	$up = rv_upload( $names['point'], $bytes['point'] );
	inc_check( ! is_wp_error( $up ) && false === $up['listed'] && false === $up['registered'] && null === inc_row( $up['file'] ) && is_file( rtrim( bk_storage(), '/' ) . '/' . $up['file'] ) && false !== strpos( implode( ' ', (array) $up['problems'] ), 'Da su frase para agregarlo a la lista' ), 'Un .dgbk de otra clave subido sin su frase se registró (o no lo dijo): ' . json_encode( is_wp_error( $up ) ? $up->get_error_message() : $up ) );
	$denied = Digitalisimo_Backups::service_register( $up['file'], array( 'confirm' => 'AGREGAR SIN FIRMA' ) );
	inc_check( is_wp_error( $denied ) && 'digitalisimo_backups_register_secret' === $denied->get_error_code() && null === inc_row( $up['file'] ), '«Agregar al listado» registró un .dgbk sin su frase.' );
	$added = Digitalisimo_Backups::service_register( $up['file'], array( 'confirm' => 'AGREGAR SIN FIRMA', 'secret' => $phrase ) );
	$row = inc_row( $up['file'] );
	inc_check( ! is_wp_error( $added ) && $row && 'incremental' === $row['backup_type'] && 1 === (int) $row['chain']['sequence'] && 'added' === $row['origin'], 'Con su frase, el punto cifrado no se agregó con su tipo y su cadena: ' . json_encode( is_wp_error( $added ) ? $added->get_error_message() : $row ) );
	$base_up = rv_upload( $names['base'], $bytes['base'], $phrase );
	inc_check( ! is_wp_error( $base_up ) && true === $base_up['listed'] && 'full' === inc_row( $base_up['file'] )['backup_type'], 'Con su frase al cerrar la subida, la base cifrada no se registró: ' . json_encode( is_wp_error( $base_up ) ? $base_up->get_error_message() : $base_up ) );
	list( $ok, $message ) = rv_delete( $base_up['file'] );
	inc_check( ! $ok && false !== strpos( $message, 'dependen' ), 'La base cifrada se borró aunque el punto agregado depende de ella: ' . $message );
	rv_reset();
} );

inc_case( 'B1 subir otra vez un respaldo hecho aquí da la confianza y la palabra de su fila', function() use ( $B ) {
	$mine = bk_backup( true );
	$up = rv_upload( 'digitalisimo-backup-propio-otra-vez.zip', file_get_contents( $mine ) );
	$verify = Digitalisimo_Backups::service_verify( basename( $mine ) );
	inc_check( ! is_wp_error( $up ) && true === $up['duplicate'] && basename( $mine ) === $up['file'] && 'provenance' === $up['trust'] && 'RESTAURAR' === $up['confirmation'] && ! is_wp_error( $verify ) && $verify['confirmation'] === $up['confirmation'], 'El duplicado de uno hecho aquí no dio la confianza de su fila: ' . json_encode( is_wp_error( $up ) ? $up->get_error_message() : array( $up['trust'], $up['confirmation'] ) ) );
	rv_reset();
} );

inc_case( 'B4 la retención de una automatización no toca varias filas subidas, y la vista previa por partes de un v3 es la de un v3', function() use ( $other, $B ) {
	$a = rv_upload( 'digitalisimo-backup-subido-a.zip', $other['base'] );
	$b = rv_upload( 'digitalisimo-backup-subido-b.zip', $other['i1'] );
	inc_check( ! is_wp_error( $a ) && ! is_wp_error( $b ) && 'uploaded' === inc_row( $a['file'] )['policy'] && 'uploaded' === inc_row( $b['file'] )['policy'], 'Las subidas no quedaron con su política.' );
	bk_call( $B, 'prune_policy', array( 'id' => 'uploaded', 'retain' => 1 ) );
	inc_check( is_file( rtrim( bk_storage(), '/' ) . '/' . $a['file'] ) && is_file( rtrim( bk_storage(), '/' ) . '/' . $b['file'] ) && inc_row( $a['file'] ) && inc_row( $b['file'] ), 'La retención de una automatización con la política de las subidas borró una.' );
	$mine = bk_backup( true );
	$zip = new ZipArchive();
	$zip->open( $mine );
	$warnings = array();
	set_error_handler( function( $no, $text ) use ( &$warnings ) { $warnings[] = $text; return true; } );
	try {
		$preview = bk_call( $B, 'service_parts_preview', $zip, Digitalisimo_Backups_Restore_Parts::normalize( array( 'parts' => 'plugins' ) ) );
	} finally {
		restore_error_handler();
		$zip->close();
	}
	$text = json_encode( $preview['replaces'], JSON_UNESCAPED_UNICODE );
	inc_check( ! $warnings && false !== strpos( $text, 'se retiran los que el respaldo no tiene' ) && false === strpos( $text, 'anterior al formato v3' ), 'La vista previa por partes de un v3 lo trató como anterior a v3 o avisó en PHP: ' . $text . ' ' . json_encode( $warnings ) );
	rv_reset();
} );

inc_case( 'B5 con «sólo firmados», un ZIP firmado con una frase escrita al subirlo dice que se restaura con esa frase', function() use ( $B, $K, $S ) {
	$phrase = 'frase de otro sitio que firma sin cifrar, bastante larga';
	$K::set_passphrase( $phrase );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
	$bytes = file_get_contents( bk_backup( true ) );
	foreach ( array_keys( $GLOBALS['bk_options'] ) as $key ) if ( 0 === strpos( $key, $K::SECRET_PREFIX ) ) unset( $GLOBALS['bk_options'][ $key ] );
	rv_reset();
	$up = rv_upload( 'digitalisimo-backup-firmado-con-frase.zip', $bytes, $phrase );
	inc_check( ! is_wp_error( $up ) && true === $up['listed'] && 'signed' === inc_row( $up['file'] )['upload']['signature'], 'El ZIP firmado con la frase escrita al subirlo no quedó en la lista como firmado: ' . json_encode( is_wp_error( $up ) ? $up->get_error_message() : $up ) );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => true );
	$text = (string) bk_call( $B, 'uploaded_signed_only_text', inc_row( $up['file'] ) );
	inc_check( false !== strpos( $text, 'escribe su frase' ) && false === strpos( $text, 'no se restaurará' ), 'Con «sólo firmados», el ZIP firmado con una frase que este sitio no guarda se anunció como «no se restaurará»: ' . $text );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
	rv_reset();
} );

inc_case( 'N1 un incremental propio sin firma, descargado y vuelto a subir, se restaura otra vez sobre su base de aquí', function() use ( $B ) {
	rv_reset(); // Cada caso empieza con el almacén y el historial vacíos, aunque el anterior fallara.
	inc_put( 'wp-content/uploads/2026/11/d.txt', 'd 0' );
	$base = inc_path( inc_run( 'propia-sin-firma' ) );
	inc_put( 'wp-content/uploads/2026/11/d.txt', 'd 1' );
	$GLOBALS['bk_options']['blogname'] = 'Punto sin firma 1';
	$point = inc_path( inc_run( 'propia-sin-firma' ) );
	$tree = inc_files();
	$db = inc_db();
	$bytes = file_get_contents( $point );
	inc_check( empty( inc_row( $point )['signed'] ), 'La cadena propia de la prueba no debía ir firmada.' );
	list( $ok ) = rv_delete( $point );
	inc_check( $ok && ! is_file( $point ) && null === inc_row( $point ), '«Borrar» del punto propio no lo quitó.' );
	inc_put( 'wp-content/uploads/2026/11/d.txt', 'd 2 después' );
	$GLOBALS['bk_options']['blogname'] = 'Cambiado';
	$up = rv_upload( basename( $point ), $bytes );
	inc_check( ! is_wp_error( $up ) && true === $up['restorable'] && empty( inc_row( $up['file'] )['upload']['site_key'] ), 'El incremental propio sin firma vuelto a subir no quedó restaurable sobre su base de aquí: ' . json_encode( is_wp_error( $up ) ? $up->get_error_message() : array( $up['restorable'], rv_summary( $up['file'] )['chain'] ?? null ) ) );
	inc_check( array( $up['file'] ) === Digitalisimo_Backups_Chain::dependents( (array) $GLOBALS['bk_options'][ $B::HISTORY ], basename( $base ) ), 'El punto vuelto a subir no protege su base, que es exactamente su eslabón.' );
	$result = inc_restore( $up['file'], $up['confirmation'] );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok() && $tree === inc_files() && $db === inc_db(), 'El incremental propio sin firma vuelto a subir no se restauró sobre su base: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	rv_reset();
} );

inc_case( 'N2 un punto subido sin firma que nombra por su nombre otro subido (que no es su eslabón) no bloquea su «Borrar»', function() use ( $other ) {
	rv_reset(); // Cada caso empieza con el almacén y el historial vacíos, aunque el anterior fallara.
	inc_put( 'wp-content/uploads/2026/11/x.txt', 'otro respaldo cualquiera' );
	$x_path = bk_backup();
	$x_bytes = file_get_contents( $x_path );
	unlink( $x_path );
	$x = rv_upload( 'digitalisimo-backup-otro-subido-20261115-140000-0e0f0a0b.zip', $x_bytes );
	inc_check( ! is_wp_error( $x ) && true === $x['listed'], 'No se subió el otro respaldo.' );
	$tmp = sys_get_temp_dir() . '/rv-n2-' . bin2hex( random_bytes( 3 ) ) . '.zip';
	file_put_contents( $tmp, $other['i1'] );
	$zip = new ZipArchive();
	$zip->open( $tmp );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$manifest['chain']['links'][0]['file'] = $x['file'];
	$zip->addFromString( 'manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	$zip->close();
	$point = rv_upload( 'digitalisimo-backup-punto-manipulado-20261115-140100-0a0b0c0e.zip', file_get_contents( $tmp ) );
	unlink( $tmp );
	inc_check( ! is_wp_error( $point ) && false === $point['restorable'], 'El punto manipulado quedó restaurable.' );
	list( $ok, $message ) = rv_delete( $x['file'] );
	inc_check( $ok && null === inc_row( $x['file'] ), '«Borrar» de otro subido se negó por un punto que sólo lo nombra: ' . $message );
	rv_reset();
} );

inc_case( 'N3 filas subidas sin archivo salen en la retención; lo subido tiene su propio tope de 100 filas', function() use ( $other, $B ) {
	rv_reset(); // Cada caso empieza con el almacén y el historial vacíos, aunque el anterior fallara.
	$own = bk_backup( true );
	$kept = rv_upload( 'digitalisimo-backup-subido-con-archivo.zip', $other['base'] );
	$ghost_files = array();
	for ( $i = 0; $i < 30; $i++ ) {
		$tmp = sys_get_temp_dir() . '/rv-n3-' . $i . '.zip';
		file_put_contents( $tmp, $other['i1'] );
		$zip = new ZipArchive();
		$zip->open( $tmp );
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$manifest['created_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() - 86400 - $i * 60 );
		$zip->addFromString( 'manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->close();
		$g = rv_upload( sprintf( 'digitalisimo-backup-crece-%02d.zip', $i ), file_get_contents( $tmp ) );
		unlink( $tmp );
		inc_check( ! is_wp_error( $g ) && true === $g['listed'], 'La subida ' . $i . ' falló.' );
		@unlink( rtrim( bk_storage(), '/' ) . '/' . $g['file'] ); // El archivo desaparece fuera de Backups (FTP).
		$ghost_files[] = $g['file'];
	}
	// Una fila subida sin archivo pero bloqueada se queda (como las propias).
	$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
	foreach ( $history as &$row ) if ( $ghost_files[0] === $row['file'] ) $row['locked'] = true;
	unset( $row );
	$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
	inc_check( 32 === count( $history ), 'El historial no tiene las filas esperadas antes de la retención: ' . count( $history ) );
	$errors = array();
	bk_call( $B, 'prune_locked', bk_storage(), 10 );
	$files = array_column( (array) $GLOBALS['bk_options'][ $B::HISTORY ], 'file' );
	if ( ! ( 3 === count( $files ) && in_array( basename( $own ), $files, true ) && in_array( $kept['file'], $files, true ) && in_array( $ghost_files[0], $files, true ) ) ) $errors[] = 'La retención global no retiró las filas subidas sin archivo (o retiró otras): quedan ' . count( $files ) . ' filas.';
	// También la de una automatización.
	$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
	$ghost = inc_row( $kept['file'] );
	$ghost['file'] = 'digitalisimo-backup-fantasma-20261115-150000-0a0b0c0f.zip';
	array_unshift( $history, $ghost );
	$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
	bk_call( $B, 'prune_policy', array( 'id' => 'automation-diaria', 'retain' => 5 ) );
	if ( ! ( null === inc_row( $ghost['file'] ) && null !== inc_row( $kept['file'] ) ) ) $errors[] = 'La retención de una automatización no retiró la fila subida sin archivo.';
	rv_reset();
	// Tope propio de 100: sale la subida más antigua, nunca una propia, una bloqueada ni una de la que depende otra.
	$base = rv_upload( $other['base_name'], $other['base'] );
	$point = rv_upload( $other['i1_name'], $other['i1'] );
	inc_check( ! is_wp_error( $base ) && ! is_wp_error( $point ) && true === $point['restorable'], 'La cadena subida de la prueba no quedó completa: ' . json_encode( is_wp_error( $point ) ? $point->get_error_message() : $point['restorable'] ) );
	$fake = function( $prefix, $count ) {
		$rows = array();
		for ( $i = 0; $i < $count; $i++ ) $rows[] = array( 'date' => '2026-11-15 10:00:00', 'file' => sprintf( 'digitalisimo-backup-%s-%03d.zip', $prefix, $i ), 'size' => 1, 'sha256' => str_repeat( 'b', 64 ), 'destination' => 'local', 'scope' => 'installation', 'policy' => 'uploaded', 'backup_type' => 'full', 'origin' => 'uploaded', 'upload' => array( 'site_key' => false ) );
		return $rows;
	};
	$created = array();
	for ( $i = 0; $i < 3; $i++ ) $created[] = array( 'date' => '2026-11-15 09:00:00', 'file' => sprintf( 'digitalisimo-backup-propio-%03d.zip', $i ), 'size' => 1, 'sha256' => str_repeat( 'c', 64 ), 'destination' => 'local', 'scope' => 'installation', 'policy' => 'manual', 'backup_type' => 'full', 'origin' => 'created' );
	$old = $fake( 'viejo', 5 );
	$old[2]['locked'] = true;
	$history = array_merge( $fake( 'nuevo', 99 ), array( inc_row( $point['file'] ) ), $created, $old, array( inc_row( $base['file'] ) ), $fake( 'mas-viejo', 2 ) );
	$capped = array_column( bk_call( $B, 'history_cap', $history ), 'file' );
	$dropped = array_values( array_diff( array_column( $history, 'file' ), $capped ) );
	$expected = array( $old[0]['file'], $old[1]['file'], $old[3]['file'], $old[4]['file'], 'digitalisimo-backup-mas-viejo-000.zip', 'digitalisimo-backup-mas-viejo-001.zip' );
	if ( ! ( $expected === $dropped && 105 === count( $capped ) && in_array( $base['file'], $capped, true ) ) ) $errors[] = 'El tope de lo subido no sacó exactamente las subidas más antiguas (sin tocar propias, bloqueadas ni la base de un punto que se queda): sacó ' . json_encode( $dropped );
	inc_check( ! $errors, implode( ' | ', $errors ) );
	rv_reset();
} );

inc_case( 'N4 un .dgbk subido sin su frase y vuelto a subir con ella se registra con su nombre, sin otra copia', function() use ( $B, $K, $S ) {
	rv_reset(); // Cada caso empieza con el almacén y el historial vacíos, aunque el anterior fallara.
	$phrase = 'frase de la cadena cifrada para N4, bastante larga';
	$K::set_passphrase( $phrase );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
	inc_put( 'wp-content/uploads/2026/11/e.txt', 'e 0' );
	$base = inc_path( inc_run( 'cifrada-n4' ) );
	inc_put( 'wp-content/uploads/2026/11/e.txt', 'e 1' );
	$point = inc_path( inc_run( 'cifrada-n4' ) );
	$bytes = array( 'base' => file_get_contents( $base ), 'point' => file_get_contents( $point ) );
	$names = array( 'base' => basename( $base ), 'point' => basename( $point ) );
	foreach ( array_keys( $GLOBALS['bk_options'] ) as $key ) if ( 0 === strpos( $key, $K::SECRET_PREFIX ) ) unset( $GLOBALS['bk_options'][ $key ] );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
	rv_reset();
	$errors = array();
	foreach ( array( 'base', 'point' ) as $which ) {
		$blind = rv_upload( $names[ $which ], $bytes[ $which ] );
		inc_check( ! is_wp_error( $blind ) && false === $blind['listed'] && $names[ $which ] === $blind['file'], 'El .dgbk sin frase no quedó suelto con su nombre.' );
		$text = implode( ' ', (array) $blind['problems'] );
		if ( 'base' === $which && ! ( false !== strpos( $text, '«Agregar al listado» con su frase' ) && false === strpos( $text, 'vuelve a subirlo' ) ) ) $errors[] = 'El aviso del .dgbk sin frase no propone sólo «Agregar al listado» con su frase: ' . $text;
	}
	foreach ( array( 'base', 'point' ) as $which ) {
		$again = rv_upload( $names[ $which ], $bytes[ $which ], $phrase );
		if ( ! ( ! is_wp_error( $again ) && true === $again['listed'] && $names[ $which ] === $again['file'] ) ) $errors[] = 'Vuelto a subir con su frase, el .dgbk ' . $which . ' no se registró con su nombre: ' . json_encode( is_wp_error( $again ) ? $again->get_error_message() : $again['file'] );
	}
	$summary = rv_summary( $names['point'] );
	$stored = glob( rtrim( bk_storage(), '/' ) . '/digitalisimo-*.dgbk' ) ?: array();
	if ( ! ( $summary && true === $summary['restorable'] && 2 === count( $stored ) ) ) $errors[] = 'La cadena vuelta a subir con su frase no quedó completa o el almacén guarda otra copia: ' . json_encode( array( $summary['restorable'] ?? null, $summary['chain']['missing'] ?? null, count( $stored ) ) );
	inc_check( ! $errors, implode( ' | ', $errors ) );
	rv_reset();
} );

bk_cleanup();
if ( $failures ) { echo "FALLA: {$failures} caso(s) de la revisión de lo subido.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: revisión de lo subido (M1, M3, M5, B1, B4, B5, N1-N4): un punto subido sin firma no ancla respaldos de aquí y queda «cadena incompleta»; el propio firmado se apoya en su base; el tope del historial cuenta aparte lo subido; un .dgbk sin su frase no se registra; el duplicado da la palabra de su fila; la retención y la vista previa v3 son las de siempre; «sólo firmados» no condena al firmado con frase; el propio sin firma vuelto a subir se restaura sobre su eslabón exacto, nombrar no basta para bloquear, las subidas sin archivo salen y tienen su tope, y un .dgbk vuelto a subir con su frase no se duplica.\n";
