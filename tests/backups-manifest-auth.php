<?php
/**
 * Firma del manifiesto: vector fijo del MAC, firma al crear, estados signed / needs_key / wrong_key / invalid /
 * unsigned ante cada manipulación, y niveles de confianza (firmado, registrado en este sitio, sin firma).
 */
require __DIR__ . '/lib/backups-env.php';
$K = 'Digitalisimo_Backups_Keys';
$A = 'Digitalisimo_Backups_Manifest_Auth';
$S = 'Digitalisimo_Backups_Security';

// 1. Vector fijo (contrastado con hmac/hashlib de Python): congela el formato del comentario sin un ZIP binario.
$fixed = str_repeat( 'B', 32 );
if ( 'DIGITALISIMO-AUTH/1 c0748d5b0ce4cf98416357ba2cb21724 KgdHfzUDyO87j4iAsDVUnmC6y03AkIYZD9BDttJhaSQ' !== $A::comment( $fixed, "{\n    \"format\": \"digitalisimo-backup\\/v3\"\n}" ) ) throw new RuntimeException( 'Cambió el formato o el cálculo del MAC del manifiesto.' );

// 2. Con una frase en el llavero el respaldo se firma; sin cifrado sigue siendo un ZIP v3.
$passphrase = 'frase de firma bastante larga';
$key_id = $K::set_passphrase( $passphrase )['key_id'];
$signed = bk_backup();
$zip = new ZipArchive();
$zip->open( $signed );
$comment = $zip->getArchiveComment();
$manifest_raw = $zip->getFromName( 'manifest.json' );
$manifest = json_decode( $manifest_raw, true );
$zip->close();
if ( 0 !== strpos( $comment, 'DIGITALISIMO-AUTH/1 ' . $key_id . ' ' ) || 'digitalisimo-backup/v3' !== $manifest['format'] || $key_id !== $manifest['auth']['key']['id'] || 'passphrase' !== $manifest['auth']['key']['source'] || 600000 !== $manifest['auth']['key']['kdf']['iterations'] || false !== $manifest['auth']['encrypted'] ) throw new RuntimeException( 'El respaldo no lleva firma y descriptor de clave.' );
$info = $S::describe( $signed );
if ( 'zip' !== $info['container'] || ! $info['signed'] || $key_id !== $info['key_id'] || $manifest['backup_id'] !== $info['backup_id'] ) throw new RuntimeException( 'El historial no registraría la firma.' );
$report = $S::open( $signed, array(), '' );
if ( 'signed' !== $report['trust'] || 'keyring' !== $report['via'] ) throw new RuntimeException( 'Un respaldo firmado con una clave del llavero no se verifica.' );
$S::close( $report );

/** Copia del respaldo firmado con cambios; $comment null conserva el comentario original. */
function bk_tamper( $source, $name, $changes, $comment = null ) {
	$path = dirname( $source ) . '/digitalisimo-tamper-' . $name . '.zip';
	copy( $source, $path );
	$zip = new ZipArchive();
	$zip->open( $path );
	foreach ( $changes as $entry => $content ) $zip->addFromString( $entry, $content );
	if ( null !== $comment ) $zip->setArchiveComment( $comment );
	$zip->close();
	return $path;
}
$reencode = function( $changes ) use ( $manifest ) { $m = $manifest; foreach ( $changes as $k => $v ) { if ( null === $v ) unset( $m[ $k ] ); else $m[ $k ] = $v; } return json_encode( $m, JSON_PRETTY_PRINT ); };
$entries = $manifest['entries'];
$entries['wordpress/wp-load.php'] = array( 'size' => 4, 'sha256' => hash( 'sha256', 'otro' ) );
$tampered = array(
	'byte del manifiesto' => bk_tamper( $signed, 'byte', array( 'manifest.json' => $manifest_raw . ' ' ) ),
	'archivo y suma juntos' => bk_tamper( $signed, 'file', array( 'wordpress/wp-load.php' => 'otro', 'manifest.json' => $reencode( array( 'entries' => $entries ) ) ) ),
	'sin comentario con auth' => bk_tamper( $signed, 'nocomment', array(), '' ),
	'comentario mal formado' => bk_tamper( $signed, 'malformed', array(), 'DIGITALISIMO-AUTH/1 ' . $key_id . ' ???' ),
	'comentario de otra clave' => bk_tamper( $signed, 'otherid', array(), str_replace( $key_id, str_repeat( 'f', 32 ), $comment ) ),
	'auth alterado' => bk_tamper( $signed, 'auth', array( 'manifest.json' => $reencode( array( 'auth' => array( 'version' => 2 ) + $manifest['auth'] ) ) ) ),
);
$GLOBALS['wpdb']->queries = array();
foreach ( $tampered as $label => $path ) {
	$state = ( function() use ( $path, $A ) { $z = new ZipArchive(); $z->open( $path ); $r = $A::verify( $z ); $z->close(); return $r['state']; } )();
	if ( 'invalid' !== $state ) throw new RuntimeException( $label . ': se esperaba una firma inválida y el estado es ' . $state . '.' );
	bk_expect_failure( $label, function() use ( $S, $path ) { $S::open( $path, array(), '' ); }, 'No se cambió nada del sitio' );
	unlink( $path );
}
if ( $GLOBALS['wpdb']->queries ) throw new RuntimeException( 'Comprobar firmas hizo consultas.' );

// 3. Quitar a la vez el bloque auth y el comentario deja un archivo sin firma: no confiable, y bloqueado con «sólo firmados».
$stripped = bk_tamper( $signed, 'stripped', array( 'manifest.json' => $reencode( array( 'auth' => null ) ) ), '' );
$report = $S::open( $stripped, array(), '' );
if ( 'untrusted' !== $report['trust'] || 'unsigned' !== $report['auth'] ) throw new RuntimeException( 'Un respaldo sin firma no quedó como no confiable.' );
bk_expect_failure( 'sin firma', function() use ( $S, $report ) { $S::require_confirmation( $report, 'RESTAURAR' ); }, 'RESTAURAR SIN FIRMA' );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'require_signed' => true );
bk_expect_failure( 'sólo firmados', function() use ( $S, $report ) { $S::require_confirmation( $report, 'RESTAURAR SIN FIRMA' ); }, 'sólo permite restaurar respaldos firmados' );
unset( $GLOBALS['bk_options'][ $S::OPTION ] );
$S::close( $report );
// Un comentario sin bloque auth se ignora.
$commented = bk_tamper( $stripped, 'commented', array(), $comment );
$z = new ZipArchive(); $z->open( $commented );
if ( 'unsigned' !== $A::verify( $z )['state'] ) throw new RuntimeException( 'Un comentario sin bloque auth no se ignoró.' );
$z->close();
unlink( $commented );

// 4. La fila del historial con el mismo SHA-256 da procedencia; una fila importada nunca.
$row = array( 'file' => basename( $stripped ), 'sha256' => hash_file( 'sha256', $stripped ) );
foreach ( array( 'provenance' => $row, 'untrusted' => $row + array( 'origin' => 'imported' ) ) as $expected => $item ) {
	$report = $S::open( $stripped, $item, '' );
	if ( $expected !== $report['trust'] ) throw new RuntimeException( 'La procedencia del historial no dio ' . $expected . '.' );
	$S::close( $report );
}
unlink( $stripped );

// 5. En otro servidor (llavero vacío): sin secreto pide la clave, con otro secreto se rechaza, con la frase se firma.
$saved_ring = $GLOBALS['bk_options'][ $K::KEYRING ];
unset( $GLOBALS['bk_options'][ $K::KEYRING ] );
$report = $S::open( $signed, array(), '' );
if ( 'untrusted' !== $report['trust'] || 'needs_key' !== $report['auth'] ) throw new RuntimeException( 'Sin la clave, un respaldo firmado debe pedirla y quedar como no confiable.' );
$message = bk_expect_failure( 'pista de la clave', function() use ( $S, $report ) { $S::require_confirmation( $report, 'RESTAURAR' ); }, $K::short( $key_id ) );
$S::close( $report );
bk_expect_failure( 'otra frase', function() use ( $S, $signed ) { $S::open( $signed, array(), 'una frase equivocada pero larga' ); }, 'no corresponde a la clave ' . $K::short( $key_id ) );
$report = $S::open( $signed, array(), $passphrase );
if ( 'signed' !== $report['trust'] || 'secret' !== $report['via'] ) throw new RuntimeException( 'La frase escrita no verificó la firma.' );
$S::close( $report );
$GLOBALS['bk_options'][ $K::KEYRING ] = $saved_ring;

// 6. Sin clave no se firma: el manifiesto no lleva auth ni el ZIP comentario (formato de 1.0.58).
unset( $GLOBALS['bk_options'][ $K::KEYRING ] );
$plain = bk_backup();
$z = new ZipArchive(); $z->open( $plain );
$plain_manifest = json_decode( $z->getFromName( 'manifest.json' ), true );
if ( isset( $plain_manifest['auth'] ) || '' !== (string) $z->getArchiveComment() ) throw new RuntimeException( 'Sin clave el respaldo no debe llevar firma.' );
$z->close();

bk_cleanup();
echo 'DIGITALÍSIMO Backups: firma del manifiesto, ' . count( $tampered ) . " manipulaciones rechazadas y niveles de confianza verificados.\n";
