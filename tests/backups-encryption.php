<?php
/**
 * Contenedor DGBK v1: ida y vuelta en los bordes de bloque, cada manipulación rechazada (truncado, bytes de más,
 * FINAL ausente o adelantado, cabecera o bloque alterados, bloques reordenados, SHA-256 interior, backup_id), flujo
 * completo (cifrar, registrar, restaurar con la clave del sitio o escrita en otro servidor), rotación, vector conocido
 * congelado para siempre e interoperabilidad entre libsodium nativo y sodium_compat. Al final, constante inválida.
 */
require __DIR__ . '/lib/backups-env.php';
$K = 'Digitalisimo_Backups_Keys';
$E = 'Digitalisimo_Backups_Encrypted_File';
$S = 'Digitalisimo_Backups_Security';
$work = sys_get_temp_dir() . '/bk-enc-' . bin2hex( random_bytes( 4 ) );
mkdir( $work, 0700 );
$root = random_bytes( 32 );
$descriptor = $K::descriptor( $K::key_id( $root ), 'wp-config' );
$meta = array( 'backup_id' => wp_generate_uuid4(), 'created_at' => '2026-11-05T00:00:00+00:00', 'plugin_version' => '9.9.9', 'multisite' => false );
$chunk = 4096;

// 1. Ida y vuelta: 1 byte, un bloque exacto, un bloque más uno y tres bloques.
foreach ( array( 1, $chunk, $chunk + 1, 3 * $chunk ) as $size ) {
	$plain = random_bytes( $size );
	file_put_contents( "$work/in", $plain );
	@unlink( "$work/out.dgbk" );
	$header = $E::encrypt( "$work/in", "$work/out.dgbk", $root, $descriptor, $meta, $chunk );
	$parsed = $E::read_header( "$work/out.dgbk" );
	if ( $parsed['size'] !== $E::expected_size( strlen( $parsed['prefix'] ), $size, $chunk ) || $parsed['chunks'] !== (int) ceil( $size / $chunk ) || $header['inner_sha256'] !== hash( 'sha256', $plain ) ) throw new RuntimeException( 'Cabecera o tamaño incorrectos para ' . $size . ' bytes.' );
	@unlink( "$work/back" );
	$E::decrypt( "$work/out.dgbk", $root, "$work/back" );
	if ( file_get_contents( "$work/back" ) !== $plain ) throw new RuntimeException( 'La ida y vuelta no devolvió los mismos ' . $size . ' bytes.' );
	if ( false !== strpos( file_get_contents( "$work/out.dgbk" ), substr( $plain, 0, min( 64, $size ) ) ) && $size > 16 ) throw new RuntimeException( 'El contenido viaja en claro.' );
}
bk_expect_failure( 'otra clave', function() use ( $E, $work ) { $E::decrypt( "$work/out.dgbk", random_bytes( 32 ) ); }, 'no con la escrita' );
bk_expect_failure( 'bloque fuera de rango', function() use ( $E, $work, $root, $descriptor, $meta ) { $E::encrypt( "$work/in", "$work/x.dgbk", $root, $descriptor, $meta, 1000 ); }, 'Tamaño de bloque' );

/** Escritor del formato para fabricar archivos que el cifrador nunca produce (etiquetas o cabecera a medida). */
function bk_forge( $path, $root, $descriptor, $meta, $plain, $chunk, $override = array(), $tags = null ) {
	$salt = random_bytes( 32 );
	$header = array_merge( array( 'format' => 'digitalisimo-encrypted-backup/v1', 'backup_id' => $meta['backup_id'], 'created_at' => $meta['created_at'], 'plugin_version' => '9.9.9', 'scope' => 'installation', 'multisite' => false, 'inner_size' => strlen( $plain ), 'inner_sha256' => hash( 'sha256', $plain ), 'cipher' => 'secretstream-xchacha20poly1305', 'chunk_size' => $chunk, 'file_salt' => Digitalisimo_Backups_Keys::b64url_encode( $salt ), 'key' => $descriptor ), $override );
	$json = json_encode( $header, JSON_UNESCAPED_SLASHES );
	$prefix = 'DGBK' . chr( 1 ) . "\0\0\0" . pack( 'N', strlen( $json ) ) . $json;
	list( $state, $stream_header ) = sodium_crypto_secretstream_xchacha20poly1305_init_push( Digitalisimo_Backups_Keys::file_key( $root, $salt ) );
	$out = $prefix . $stream_header;
	$blocks = str_split( $plain, $chunk );
	foreach ( $blocks as $i => $block ) $out .= sodium_crypto_secretstream_xchacha20poly1305_push( $state, $block, 0 === $i ? $prefix : '', $tags ? $tags( $i, count( $blocks ) ) : ( $i === count( $blocks ) - 1 ? 3 : 0 ) );
	file_put_contents( $path, $out );
	return strlen( $prefix ) + 24;
}
// 2. Manipulaciones sobre un archivo de tres bloques (el último incompleto).
$plain = random_bytes( 2 * $chunk + 1808 );
file_put_contents( "$work/in", $plain );
@unlink( "$work/good.dgbk" );
$E::encrypt( "$work/in", "$work/good.dgbk", $root, $descriptor, $meta, $chunk );
$good = file_get_contents( "$work/good.dgbk" );
$offset = $E::read_header( "$work/good.dgbk" )['offset'];
$block = $chunk + 17;
$headers_at = 12;
$mutations = array(
	'truncado en medio' => array( substr( $good, 0, $offset + 5000 ), 'incompleto' ),
	'truncado al final' => array( substr( $good, 0, -1 ), 'incompleto' ),
	'bytes de más' => array( $good . "\0", 'bytes de más' ),
	'cabecera alterada' => array( substr_replace( $good, '9', strpos( $good, '2026-11-05' ) + 3, 1 ), 'modificado o está dañado' ),
	'cabecera secretstream alterada' => array( substr_replace( $good, chr( ord( $good[ $offset - 1 ] ) ^ 1 ), $offset - 1, 1 ), 'modificado o está dañado' ),
	'bloque alterado' => array( substr_replace( $good, chr( ord( $good[ $offset + $block + 10 ] ) ^ 1 ), $offset + $block + 10, 1 ), 'modificado o está dañado' ),
	'bloques reordenados' => array( substr( $good, 0, $offset ) . substr( $good, $offset + $block, $block ) . substr( $good, $offset, $block ) . substr( $good, $offset + 2 * $block ), 'modificado o está dañado' ),
	'bloque duplicado' => array( substr( $good, 0, $offset ) . substr( $good, $offset, $block ) . substr( $good, $offset, $block ) . substr( $good, $offset + 2 * $block ), 'modificado o está dañado' ),
	'versión desconocida' => array( substr_replace( $good, chr( 2 ), 4, 1 ), 'DGBK v1' ),
	'cabecera gigante' => array( substr_replace( $good, pack( 'N', 1 << 20 ), 8, 4 ), 'cabecera' ),
);
foreach ( $mutations as $label => $case ) {
	file_put_contents( "$work/bad.dgbk", $case[0] );
	@unlink( "$work/bad.out" );
	bk_expect_failure( $label, function() use ( $E, $work, $root ) { $E::decrypt( "$work/bad.dgbk", $root, "$work/bad.out" ); }, $case[1] );
	if ( file_exists( "$work/bad.out" ) ) throw new RuntimeException( $label . ': quedó un archivo descifrado a medias.' );
}
$forged = array(
	'FINAL ausente' => array( array(), function( $i, $n ) { return 0; } ),
	'FINAL adelantado' => array( array(), function( $i, $n ) { return 1 === $i ? 3 : ( $i === $n - 1 ? 3 : 0 ); } ),
	'SHA-256 interior distinto' => array( array( 'inner_sha256' => str_repeat( 'a', 64 ) ), null ),
	'bloque fuera de rango' => array( array( 'chunk_size' => 3000 ), null ),
	'parámetros KDF hostiles' => array( array( 'key' => array( 'id' => $descriptor['id'], 'source' => 'passphrase', 'kdf' => array( 'alg' => 'pbkdf2-sha256', 'iterations' => 999999999, 'salt' => 'AAECAwQFBgcICQoLDA0ODw' ) ) ), null ),
);
foreach ( $forged as $label => $case ) {
	bk_forge( "$work/forged.dgbk", $root, $descriptor, $meta, $plain, $chunk, $case[0], $case[1] );
	bk_expect_failure( $label, function() use ( $E, $work, $root ) { $E::decrypt( "$work/forged.dgbk", $root ); }, '' );
}
// El escritor de prueba es fiel al formato: sin cambios, el lector lo acepta.
bk_forge( "$work/forged.dgbk", $root, $descriptor, $meta, $plain, $chunk );
$E::decrypt( "$work/forged.dgbk", $root );

// 3. Flujo completo con la frase del sitio: sólo llega al almacén el .dgbk verificado.
$passphrase = 'frase de cifrado bastante larga';
$key_id = $K::set_passphrase( $passphrase )['key_id'];
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
$storage = bk_storage();
$sealed = bk_backup();
if ( '.dgbk' !== substr( $sealed, -5 ) || dirname( $sealed ) !== $storage || glob( $storage . '/digitalisimo-*.zip' ) || glob( $storage . '/*.part' ) || glob( $storage . '/.staging/*' ) ) throw new RuntimeException( 'El cifrado dejó un ZIP en claro, un .part o staging en el almacén.' );
if ( false !== strpos( file_get_contents( $sealed ), 'manifest.json' ) || false !== strpos( file_get_contents( $sealed ), 'wp-config' ) ) throw new RuntimeException( 'Los nombres de archivo del respaldo cifrado viajan en claro.' );
$header = $E::read_header( $sealed )['header'];
if ( $key_id !== $header['key']['id'] || 'passphrase' !== $header['key']['source'] ) throw new RuntimeException( 'La cabecera no lleva el descriptor de la frase.' );
bk_call( 'Digitalisimo_Backups', 'remember', $sealed, 'local', 'installation', array( 'id' => 'manual', 'type' => 'full' ) );
$row = $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ][0];
if ( 'dgbk' !== $row['container'] || $key_id !== $row['key_id'] || ! $row['signed'] || 'created' !== $row['origin'] || hash_file( 'sha256', $sealed ) !== $row['sha256'] || $header['backup_id'] !== $row['backup_id'] ) throw new RuntimeException( 'El historial no registró el .dgbk con su SHA-256 y su clave.' );
ob_start();
bk_call( 'Digitalisimo_Backups', 'restore_panel', $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] );
$panel = ob_get_clean();
if ( false === strpos( $panel, 'cifrado, clave ' . $K::short( $key_id ) ) || false === strpos( $panel, 'type="password" name="backup_secret" value=""' ) || false === strpos( $panel, 'sin ella no se puede restaurar' ) ) throw new RuntimeException( 'La lista de restauración no avisa del cifrado o no ofrece el campo de la frase.' );
if ( false !== strpos( $panel, 'escribe su frase o su clave' ) || false !== strpos( $S::history_badge( $row ), 'falta la clave' ) || false === strpos( $S::history_badge( $row ), 'Cifrado · ' . $K::short( $key_id ) ) ) throw new RuntimeException( 'Con la clave en el llavero no debe pedirse la frase.' );
// Fallo dentro del cifrado: el .part se borra y nada se publica.
$staging = $S::new_staging( $storage );
file_put_contents( "$staging/inner.zip", 'zip' );
bk_expect_failure( 'cifrado con otra clave', function() use ( $S, $staging, $storage, $root, $descriptor, $meta, $K ) { $S::seal( "$staging/inner.zip", $storage . '/digitalisimo-fallo.dgbk', array( 'root' => $root, 'descriptor' => $K::descriptor( str_repeat( 'a', 32 ), 'wp-config' ), 'encrypt' => true ), $meta ); }, 'no corresponde' );
if ( file_exists( $storage . '/digitalisimo-fallo.dgbk' ) || file_exists( $storage . '/digitalisimo-fallo.dgbk.part' ) ) throw new RuntimeException( 'Un cifrado fallido dejó archivos en el almacén.' );
$S::remove_tree( $staging );
// Un fallo al construir el ZIP borra el staging y no deja ZIP en claro.
symlink( $work, ABSPATH . 'enlace' );
bk_expect_failure( 'enlace en la instalación', function() { bk_backup(); }, 'enlace simbólico' );
unlink( ABSPATH . 'enlace' );
if ( glob( $storage . '/digitalisimo-*.zip' ) || glob( $storage . '/.staging/*' ) || 1 !== count( glob( $storage . '/digitalisimo-*.dgbk' ) ) ) throw new RuntimeException( 'Un respaldo cifrado fallido dejó restos.' );

// 4. Restaurar con la clave del sitio, y en «otro servidor» sin llavero: sin frase se explica, con la frase funciona.
$restore = function( $secret, $adopt = false ) use ( $sealed ) { return bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => basename( $sealed ), 'confirmation' => 'RESTAURAR', 'backup_secret' => $secret ) + ( $adopt ? array( 'backup_secret_adopt' => '1' ) : array() ) ); };
$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado después del respaldo';
$GLOBALS['wpdb']->queries = array();
list( $ok, $message ) = $restore( '' );
if ( ! $ok || ! preg_grep( '/^INSERT INTO `wp_posts`.*punto y coma/s', $GLOBALS['wpdb']->queries ) || glob( $storage . '/.staging/*' ) ) throw new RuntimeException( 'No se restauró el respaldo cifrado con la clave del sitio: ' . $message );
if ( ( $GLOBALS['bk_options'][ $K::KEYRING ]['active'] ?? '' ) !== $key_id || empty( $GLOBALS['bk_options'][ $S::OPTION ]['encrypt'] ) ) throw new RuntimeException( 'La restauración perdió el llavero o la configuración de cifrado.' );
$ring = $GLOBALS['bk_options'][ $K::KEYRING ];
unset( $GLOBALS['bk_options'][ $K::KEYRING ] );
// Sin la clave en este sitio, la interfaz lo dice antes de intentar nada.
ob_start();
bk_call( 'Digitalisimo_Backups', 'restore_panel', $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] );
$panel = ob_get_clean();
$badge = $S::history_badge( $row );
if ( false === strpos( $panel, basename( $sealed ) . ' · cifrado, clave ' . $K::short( $key_id ) . ' · escribe su frase o su clave' ) || false === strpos( $badge, 'falta la clave' ) || false === strpos( $badge, 'sin ella no se puede restaurar' ) ) throw new RuntimeException( 'Sin la clave en el llavero, la interfaz no avisa que hay que escribir la frase.' );
$GLOBALS['wpdb']->queries = array();
list( $ok, $message ) = $restore( '' );
if ( $ok || false === strpos( $message, 'Este respaldo está cifrado con la clave ' . $K::short( $key_id ) . '. Escribe su frase o su clave; sin ella no se puede restaurar.' ) || $GLOBALS['wpdb']->queries ) throw new RuntimeException( 'Sin la frase, el mensaje no es claro o se tocó el sitio: ' . $message );
list( $ok, $message, $url ) = $restore( 'una frase equivocada y larga' );
if ( $ok || false === strpos( $message, 'no corresponde a la clave ' . $K::short( $key_id ) ) || $GLOBALS['wpdb']->queries || false !== strpos( $url, 'equivocada' ) ) throw new RuntimeException( 'Una frase equivocada no se rechazó limpiamente: ' . $message );
list( $ok, $message, $url ) = $restore( $passphrase, true );
if ( ! $ok || false !== strpos( $url, 'cifrado%20bastante' ) || ( $GLOBALS['bk_options'][ $K::KEYRING ]['active'] ?? '' ) !== $key_id || false === strpos( $message, 'firma ahora los respaldos nuevos' ) ) throw new RuntimeException( 'La frase escrita no restauró o no quedó para los respaldos nuevos: ' . $message );
if ( glob( $storage . '/.staging/*' ) ) throw new RuntimeException( 'La restauración dejó el ZIP descifrado en staging.' );
$safety = glob( $storage . '/safety-before-restore-*' );
// Copias previas: nunca cifradas; firmadas cuando el sitio tenía clave (la primera), sin firma en «otro servidor».
$signed_copies = 0;
foreach ( $safety as $copy ) { if ( $E::is_encrypted( $copy ) || '.zip' !== substr( $copy, -4 ) ) throw new RuntimeException( 'La copia previa debe quedar en claro.' ); $signed_copies += $S::describe( $copy )['signed'] ? 1 : 0; }
if ( 2 !== count( $safety ) || 1 !== $signed_copies ) throw new RuntimeException( 'La copia previa con clave no quedó firmada.' );

// 5. La rotación incluye los .dgbk y respeta .staging/ y los .part. Sólo cuenta y borra respaldos con fila en el historial:
// uno «sin registro» (subido o copiado a mano) se queda (revisión del lote corto, R3).
foreach ( array( 'digitalisimo-a.dgbk' => 100, 'digitalisimo-b.zip' => 200, 'digitalisimo-suelto.dgbk' => 300 ) as $name => $age ) { file_put_contents( "$storage/$name", 'x' ); touch( "$storage/$name", time() - $age ); }
foreach ( array( 'digitalisimo-a.dgbk', 'digitalisimo-b.zip' ) as $name ) $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ][] = array( 'file' => $name, 'date' => gmdate( 'Y-m-d H:i:s' ), 'destination' => 'local' );
$staging = $S::new_staging( $storage );
file_put_contents( "$staging/inner.zip", 'x' );
file_put_contents( "$storage/digitalisimo-c.dgbk.part", 'x' );
bk_call( 'Digitalisimo_Backups', 'prune', $storage, 1 );
if ( ! file_exists( $sealed ) || file_exists( "$storage/digitalisimo-a.dgbk" ) || file_exists( "$storage/digitalisimo-b.zip" ) || ! file_exists( "$storage/digitalisimo-suelto.dgbk" ) || ! file_exists( "$staging/inner.zip" ) || ! file_exists( "$storage/digitalisimo-c.dgbk.part" ) ) throw new RuntimeException( 'La rotación no trató bien .dgbk, .staging o .part.' );
// Al tomar el bloqueo, cualquier operación siguiente (también una que no cifra) borra los huérfanos: un ZIP en claro
// o descifrado que dejó un proceso interrumpido no espera al próximo respaldo cifrado.
$lock = bk_call( 'Digitalisimo_Backups', 'acquire_operation_lock' );
bk_call( 'Digitalisimo_Backups', 'release_operation_lock', $lock );
if ( glob( $storage . '/.staging/*' ) || file_exists( "$storage/digitalisimo-c.dgbk.part" ) || ! file_exists( $sealed ) ) throw new RuntimeException( 'La limpieza de huérfanos falló o tocó un respaldo.' );

// 6. Vector conocido: un DGBK v1 de referencia se abre siempre con su clave (base64 de 32 bytes «B»).
$kat = __DIR__ . '/fixtures/backups/kat-v1.dgbk';
if ( '1a77e1036baf2df86d63c6e816bc6b8d72c236d88033836c1d81caa2a5c26b5c' !== hash_file( 'sha256', $kat ) ) throw new RuntimeException( 'El vector conocido cambió.' );
$kat_header = $E::read_header( $kat )['header'];
if ( '85ef12e6-5c2c-4ee9-85b5-8c8fb0f2c2d3' !== $kat_header['backup_id'] || 'c0748d5b0ce4cf98416357ba2cb21724' !== $kat_header['key']['id'] || 1048576 !== $kat_header['chunk_size'] || 2827 !== $kat_header['inner_size'] ) throw new RuntimeException( 'La cabecera del vector conocido no se lee igual.' );
copy( $kat, $storage . '/digitalisimo-kat.dgbk' );
copy( $kat, $storage . '/digitalisimo-renombrado.zip' );
file_put_contents( $storage . '/digitalisimo-roto.dgbk', 'DGBK' . chr( 1 ) . "\0\0\0" . pack( 'N', 3 ) . '{x}' );
ob_start();
bk_call( 'Digitalisimo_Backups', 'restore_panel', $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] );
$panel = ob_get_clean();
foreach ( array( 'digitalisimo-kat.dgbk · sin registro · cifrado, clave c0748d5b · escribe su frase o su clave', 'digitalisimo-renombrado.zip · sin registro · cifrado, clave c0748d5b · escribe su frase o su clave', 'digitalisimo-roto.dgbk · sin registro · cifrado, cabecera dañada' ) as $label ) if ( false === strpos( $panel, $label ) ) throw new RuntimeException( 'La lista de restauración no describe un archivo cifrado suelto: ' . $label );
unlink( $storage . '/digitalisimo-renombrado.zip' );
unlink( $storage . '/digitalisimo-roto.dgbk' );
bk_expect_failure( 'vector sin clave', function() use ( $S, $storage ) { $S::open( $storage . '/digitalisimo-kat.dgbk', array(), '' ); }, 'cifrado con la clave c0748d5b' );
$report = $S::open( $storage . '/digitalisimo-kat.dgbk', array(), base64_encode( str_repeat( 'B', 32 ) ) );
if ( 'signed' !== $report['trust'] || 'secret' !== $report['via'] || hash_file( 'sha256', $report['path'] ) !== $kat_header['inner_sha256'] ) throw new RuntimeException( 'El vector conocido no se descifra ni se verifica.' );
bk_call( 'Digitalisimo_Backups', 'restore_digitalisimo_zip', $report['path'], $report );
$S::close( $report );
bk_expect_failure( 'adoptar una clave de wp-config', function() use ( $K ) { $K::adopt( str_repeat( 'B', 32 ), $K::descriptor( $K::key_id( str_repeat( 'B', 32 ) ), 'wp-config' ) ); }, 'wp-config.php' );

// 7. El backup_id interior debe coincidir con la cabecera, y lo interior debe estar firmado como cifrado.
$inner = "$work/inner.zip";
@unlink( $inner );
$E::decrypt( $sealed, $K::resolve_by_id( $key_id ), $inner );
@unlink( "$storage/digitalisimo-swap.dgbk" );
$E::encrypt( $inner, "$storage/digitalisimo-swap.dgbk", $K::resolve_by_id( $key_id ), $header['key'], array( 'backup_id' => wp_generate_uuid4() ) + $meta );
bk_expect_failure( 'backup_id distinto', function() use ( $S, $storage ) { $S::open( "$storage/digitalisimo-swap.dgbk", array(), '' ); }, 'no corresponde a su cabecera' );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false );
$signed_plain = bk_backup();
$plain_manifest = json_decode( ( function( $p ) { $z = new ZipArchive(); $z->open( $p ); $m = $z->getFromName( 'manifest.json' ); $z->close(); return $m; } )( $signed_plain ), true );
@unlink( "$storage/digitalisimo-wrap.dgbk" );
$E::encrypt( $signed_plain, "$storage/digitalisimo-wrap.dgbk", $K::resolve_by_id( $key_id ), $header['key'], array( 'backup_id' => $plain_manifest['backup_id'] ) + $meta );
bk_expect_failure( 'interior sin marca de cifrado', function() use ( $S, $storage ) { $S::open( "$storage/digitalisimo-wrap.dgbk", array(), '' ); }, 'no corresponde a su cabecera' );
if ( glob( $storage . '/.staging/*' ) ) throw new RuntimeException( 'Un rechazo tras descifrar dejó el staging.' );

// 8. Interoperabilidad: lo cifrado con libsodium nativo se abre con sodium_compat y al revés (sólo con la extensión).
if ( extension_loaded( 'sodium' ) && ! getenv( 'DIGI_FORCE_SODIUM_COMPAT' ) ) {
	require_once __DIR__ . '/lib/sodium_compat/autoload.php';
	ParagonIE_Sodium_Compat::$disableFallbackForUnitTests = true;
	$C = 'ParagonIE_Sodium_Compat';
	$compat_file_key = function( $root, $salt ) use ( $C ) { return $C::crypto_generichash( 'DGBK1/file|' . $salt, $root, 32 ); };
	$parsed = $E::read_header( "$work/good.dgbk" );
	$state = $C::crypto_secretstream_xchacha20poly1305_init_pull( $parsed['stream_header'], $compat_file_key( $root, $K::b64url_decode( $parsed['header']['file_salt'], 32 ) ) );
	$data = substr( $good, $parsed['offset'] );
	$out = '';
	for ( $i = 0; $i < $parsed['chunks']; $i++ ) {
		$length = min( $chunk, $parsed['header']['inner_size'] - strlen( $out ) ) + 17;
		list( $m, $tag ) = $C::crypto_secretstream_xchacha20poly1305_pull( $state, substr( $data, 0, $length ), 0 === $i ? $parsed['prefix'] : '' );
		$data = substr( $data, $length );
		$out .= $m;
		if ( ( $i === $parsed['chunks'] - 1 ? 3 : 0 ) !== $tag ) throw new RuntimeException( 'sodium_compat leyó otra etiqueta.' );
	}
	if ( $out !== $plain ) throw new RuntimeException( 'sodium_compat no descifra lo cifrado con libsodium nativo.' );
	$salt = random_bytes( 32 );
	$json = json_encode( array( 'format' => 'digitalisimo-encrypted-backup/v1', 'backup_id' => $meta['backup_id'], 'created_at' => $meta['created_at'], 'plugin_version' => '9.9.9', 'scope' => 'installation', 'multisite' => false, 'inner_size' => strlen( $plain ), 'inner_sha256' => hash( 'sha256', $plain ), 'cipher' => 'secretstream-xchacha20poly1305', 'chunk_size' => $chunk, 'file_salt' => $K::b64url_encode( $salt ), 'key' => $descriptor ), JSON_UNESCAPED_SLASHES );
	$prefix = 'DGBK' . chr( 1 ) . "\0\0\0" . pack( 'N', strlen( $json ) ) . $json;
	list( $state, $stream_header ) = $C::crypto_secretstream_xchacha20poly1305_init_push( $compat_file_key( $root, $salt ) );
	$file = $prefix . $stream_header;
	$blocks = str_split( $plain, $chunk );
	foreach ( $blocks as $i => $part ) $file .= $C::crypto_secretstream_xchacha20poly1305_push( $state, $part, 0 === $i ? $prefix : '', $i === count( $blocks ) - 1 ? 3 : 0 );
	file_put_contents( "$work/compat.dgbk", $file );
	@unlink( "$work/compat.out" );
	$E::decrypt( "$work/compat.dgbk", $root, "$work/compat.out" );
	if ( file_get_contents( "$work/compat.out" ) !== $plain ) throw new RuntimeException( 'libsodium nativo no descifra lo cifrado con sodium_compat.' );
	echo "Interoperabilidad libsodium nativo ↔ sodium_compat verificada.\n";
} else {
	echo 'Sin libsodium nativo: se usa sodium_compat (' . ( function_exists( 'sodium_crypto_secretstream_xchacha20poly1305_init_push' ) ? 'cargado' : 'ausente' ) . "); la interoperabilidad se comprueba en la CI.\n";
}

// 9. Constante inválida en wp-config.php: el cifrado sigue obligatorio, los respaldos fallan y su valor nunca se repite.
define( 'DIGITALISIMO_BACKUPS_KEY', 'no-es-una-clave-valida' );
$before = glob( $storage . '/*' );
$message = bk_expect_failure( 'constante inválida', function() { bk_backup(); }, 'no es válida' );
if ( false !== strpos( $message, 'no-es-una-clave' ) || glob( $storage . '/*' ) !== $before ) throw new RuntimeException( 'La constante inválida se repitió en el mensaje o dejó archivos.' );
ob_start();
$S::panel( false, array() );
$html = ob_get_clean();
if ( false === strpos( $html, 'inválida' ) || false !== strpos( $html, 'no-es-una-clave' ) ) throw new RuntimeException( 'La pestaña no avisa de la constante inválida o repite su valor.' );
// La copia previa a restaurar no depende de la constante: se crea sin cifrar y sin firma.
$safety_copy = bk_call( 'Digitalisimo_Backups', 'create_network', 'safety-before-restore-' );
if ( $E::is_encrypted( $safety_copy ) || $S::describe( $safety_copy )['signed'] ) throw new RuntimeException( 'La copia previa falló o se firmó con una clave inválida.' );

bk_remove_tree( $work );
bk_cleanup();
echo 'DIGITALÍSIMO Backups: DGBK v1 con ' . ( count( $mutations ) + count( $forged ) ) . " manipulaciones rechazadas, restauración con y sin llavero y vector conocido verificados.\n";
