<?php
/**
 * Claves y llavero: vectores fijos (contrastados con hashlib de Python), frase sin alterar, límites de la KDF, llavero
 * envuelto, fallo cerrado cuando cambian AUTH_KEY/SECURE_AUTH_KEY, filas del llavero fuera de database.sql, unión del
 * llavero tras restaurar, pestaña y redirects sin secretos, nonce y capacidad. Al final, la clave en wp-config.php.
 */
require __DIR__ . '/lib/backups-env.php';
$K = 'Digitalisimo_Backups_Keys';

// 1. Vectores fijos: congelan PBKDF2 → huella y la derivación de la clave del llavero.
$kdf = array( 'alg' => 'pbkdf2-sha256', 'iterations' => 600000, 'salt' => 'AAECAwQFBgcICQoLDA0ODw' );
$root = $K::derive( 'frase de prueba muy larga 2026', $kdf );
if ( '7b34dd8cb589ad162089dbc9e54de90a576ceee33a5e99c36f561759fc7a1464' !== bin2hex( $root ) || 'a31f0cf37caf2a3ef09a18d9f961e967' !== $K::key_id( $root ) ) throw new RuntimeException( 'Cambió la derivación PBKDF2 o la huella de una clave.' );
if ( 'c0748d5b0ce4cf98416357ba2cb21724' !== $K::key_id( str_repeat( 'B', 32 ) ) || 'd21164b32891a2454a5ab01f8ee4fa040d6b5805b6b649a00607331cc6eed73a' !== bin2hex( $K::file_key( str_repeat( 'B', 32 ), str_repeat( "\x01", 32 ) ) ) ) throw new RuntimeException( 'Cambió la derivación de subclaves.' );
if ( '821f071cddda26b4' !== $K::wrap_id( $K::wrap_key() ) ) throw new RuntimeException( 'Cambió la derivación de la clave del llavero.' );

// 2. Límites antes de derivar y claves en bruto.
$bad = array(
	array( 'alg' => 'pbkdf2-sha256', 'iterations' => 99999, 'salt' => $kdf['salt'] ),
	array( 'alg' => 'pbkdf2-sha256', 'iterations' => 5000001, 'salt' => $kdf['salt'] ),
	array( 'alg' => 'pbkdf2-sha256', 'iterations' => '600000', 'salt' => $kdf['salt'] ),
	array( 'alg' => 'argon2id13', 'iterations' => 600000, 'salt' => $kdf['salt'] ),
	array( 'alg' => 'pbkdf2-sha256', 'iterations' => 600000, 'salt' => 'AAECAwQFBgcICQoLDA0O' ),
	array( 'alg' => 'pbkdf2-sha256', 'iterations' => 600000, 'salt' => 'AAEC+wQFBgcICQoLDA0ODw' ),
);
foreach ( $bad as $index => $params ) if ( false !== $K::derive( 'frase de prueba muy larga 2026', $params ) ) throw new RuntimeException( 'Se derivó con parámetros fuera de rango (' . $index . ').' );
if ( false !== $K::derive( str_repeat( 'a', 15 ), $kdf ) || false !== $K::derive( str_repeat( 'a', 1025 ), $kdf ) || false === $K::derive( str_repeat( 'a', 1024 ), $kdf ) ) throw new RuntimeException( 'La longitud de la frase no se valida.' );
$raw = str_repeat( "\xfb", 32 );
foreach ( array( base64_encode( $raw ), rtrim( base64_encode( $raw ), '=' ), rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ), ' ' . base64_encode( $raw ) . "\n" ) as $variant ) if ( $K::parse_raw_key( $variant ) !== $raw ) throw new RuntimeException( 'No se aceptó una forma válida de la clave en base64.' );
foreach ( array( '', 'clave', base64_encode( str_repeat( 'a', 31 ) ), base64_encode( str_repeat( 'a', 33 ) ), str_repeat( '*', 43 ), array( 'x' ) ) as $invalid ) if ( false !== $K::parse_raw_key( $invalid ) ) throw new RuntimeException( 'Se aceptó una clave en bruto inválida.' );
if ( 'none' !== $K::constant_state() ) throw new RuntimeException( 'Sin constante el estado debe ser «none».' );

// 3. Frase sin alterar: espacios, «<» y «&» cuentan; la versión recortada no abre lo firmado.
$passphrase = '  <frase & con "espacios"> ';
$result = $K::set_passphrase( $passphrase );
$ring = $K::keyring();
if ( 'created' !== $result['result'] || $ring['active'] !== $result['key_id'] || 1 !== count( $ring['keys'] ) ) throw new RuntimeException( 'No se creó la clave de la frase.' );
$descriptor = $K::descriptor( $ring['active'], 'passphrase', $ring['keys'][ $ring['active'] ]['kdf'] );
if ( ! $K::resolve_with_secret( $descriptor, $passphrase ) || $K::resolve_with_secret( $descriptor, trim( $passphrase ) ) || $K::resolve_with_secret( $descriptor, htmlspecialchars( $passphrase ) ) ) throw new RuntimeException( 'La frase se alteró al derivar.' );
$stored = serialize( $GLOBALS['bk_options'] );
if ( false !== strpos( $stored, 'frase & con' ) || false !== strpos( $stored, bin2hex( $K::resolve_by_id( $ring['active'] ) ) ) || false !== strpos( $stored, $K::b64url_encode( $K::resolve_by_id( $ring['active'] ) ) ) ) throw new RuntimeException( 'La frase o la clave se guardaron en claro.' );
$first = $ring['active'];
if ( 'unchanged' !== $K::set_passphrase( $passphrase )['result'] || 1 !== count( $K::keyring()['keys'] ) ) throw new RuntimeException( 'Repetir la frase activa creó otra clave.' );
$second = $K::set_passphrase( 'otra frase bastante larga' )['key_id'];
$ring = $K::keyring();
if ( $ring['active'] !== $second || empty( $ring['keys'][ $first ]['retired'] ) || 'retired' !== $K::entry_state( $first, $ring['keys'][ $first ], $ring ) ) throw new RuntimeException( 'Cambiar la frase no retiró la anterior.' );
if ( ! $K::resolve_by_id( $first ) ) throw new RuntimeException( 'Una clave retirada dejó de servir para restaurar.' );
bk_expect_failure( 'quitar la activa', function() use ( $K, $second ) { $K::remove( $second ); }, 'no se puede quitar' );

// 4. Las claves de wp-config.php cambian: la entrada pide la frase y el respaldo cifrado falla cerrado.
$GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
$saved_ring = $GLOBALS['bk_options'][ $K::KEYRING ];
$GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $second ]['wrap']['kw_id'] = '0000000000000000';
$ring = $K::keyring();
if ( 'needs_passphrase' !== $K::entry_state( $second, $ring['keys'][ $second ], $ring ) ) throw new RuntimeException( 'Una entrada con otra clave de envoltura no pide la frase.' );
$storage = bk_storage();
$before = glob( $storage . '/*' );
$history = $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] ?? array();
bk_expect_failure( 'fallo cerrado', function() { bk_call( 'Digitalisimo_Backups', 'create_network' ); }, 'No se creó un respaldo sin cifrar' );
if ( glob( $storage . '/*' ) !== $before || ( $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] ?? array() ) !== $history || glob( $storage . '/.staging/*' ) ) throw new RuntimeException( 'El fallo cerrado dejó archivos, filas o staging.' );
// Sin cifrado, el respaldo se crea sin firma en lugar de dejar el sitio sin respaldo.
$GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] = array( 'encrypt' => false );
$unsigned = bk_call( 'Digitalisimo_Backups', 'create_network' );
if ( Digitalisimo_Backups_Security::describe( $unsigned )['signed'] ) throw new RuntimeException( 'Se firmó con una clave que no se pudo abrir.' );
unlink( $unsigned );
if ( 'rewrapped' !== $K::set_passphrase( 'otra frase bastante larga' )['result'] || 'active' !== $K::entry_state( $second, $K::keyring()['keys'][ $second ], $K::keyring() ) ) throw new RuntimeException( 'Volver a escribir la frase no recuperó la clave.' );

// 5. El llavero nunca entra en database.sql; las demás opciones sí.
$signed = bk_call( 'Digitalisimo_Backups', 'create_network' );
$zip = new ZipArchive();
$zip->open( $signed );
$sql = $zip->getFromName( 'database.sql' );
$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
$zip->close();
if ( false !== strpos( $sql, 'digitalisimo_backups_vault_' ) || false === strpos( $sql, 'Sitio de prueba' ) || false === strpos( $sql, 'digitalisimo_backups_security' ) ) throw new RuntimeException( 'El SQL del respaldo contiene el llavero o perdió otras opciones.' );
if ( ( $manifest['auth']['key']['id'] ?? '' ) !== $second || false !== ( $manifest['auth']['encrypted'] ?? null ) ) throw new RuntimeException( 'El respaldo no quedó firmado con la clave activa.' );
$GLOBALS['wpdb']->base_prefix = 'wp_';
$legacy_sql = bk_call( 'Digitalisimo_Backups', 'database_sql', array( 'wp_options' ) );
if ( false !== strpos( $legacy_sql, 'digitalisimo_backups_vault_' ) ) throw new RuntimeException( 'El exportador heredado incluye el llavero.' );
if ( ! bk_call( 'Digitalisimo_Backups', 'secret_row', 'wp_sitemeta', array( 'meta_key' => 'digitalisimo_backups_vault_keyring' ) ) || ! bk_call( 'Digitalisimo_Backups', 'secret_row', 'wp_3_options', array( 'option_name' => 'digitalisimo_backups_vault_x' ) ) || bk_call( 'Digitalisimo_Backups', 'secret_row', 'wp_postmeta', array( 'meta_key' => 'digitalisimo_backups_vault_x' ) ) ) throw new RuntimeException( 'La exclusión no cubre options y sitemeta.' );

// 6. Restaurar un SQL sin llavero y con la protección más débil no debilita nada.
$GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] = array( 'encrypt' => true, 'require_signed' => true );
$ring_before = $GLOBALS['bk_options'][ $K::KEYRING ];
$report = Digitalisimo_Backups_Security::open( $signed, array(), '' );
if ( 'signed' !== $report['trust'] ) throw new RuntimeException( 'Un respaldo firmado con una clave del llavero no se reconoce como firmado.' );
bk_call( 'Digitalisimo_Backups', 'restore_digitalisimo_zip', $report['path'], $report );
Digitalisimo_Backups_Security::close( $report );
if ( ( $GLOBALS['bk_options'][ $K::KEYRING ] ?? null ) !== $ring_before ) throw new RuntimeException( 'La restauración perdió o cambió el llavero.' );
$security = $GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] ?? array();
if ( empty( $security['encrypt'] ) || empty( $security['require_signed'] ) ) throw new RuntimeException( 'La restauración debilitó la configuración de seguridad.' );
// Unión por huella: lo que trae el sitio restaurado se añade y lo de antes nunca se descarta.
$K::restore_snapshot( array( 'keyring' => array( 'version' => 1, 'active' => $second, 'keys' => array( $second => $ring_before['keys'][ $second ] ) ), 'security' => array( 'encrypt' => false, 'require_signed' => false ) ) );
if ( ! isset( $GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $first ], $GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $second ] ) || empty( $GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ]['encrypt'] ) ) throw new RuntimeException( 'La unión del llavero descartó una clave o debilitó la protección.' );
unset( $GLOBALS['bk_options'][ Digitalisimo_Backups_Security::OPTION ] );

// 7. La pestaña y los redirects no devuelven secretos; un campo vacío conserva la frase; nonce y capacidad.
$GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] = array( array( 'file' => 'x.zip', 'key_id' => $second, 'scope' => 'installation', 'size' => 1048576 ) );
ob_start();
Digitalisimo_Backups_Security::panel( false, $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] );
$html = ob_get_clean();
$root_second = $K::resolve_by_id( $second );
foreach ( array( 'otra frase', $K::b64url_encode( $root_second ), base64_encode( $root_second ), bin2hex( $root_second ), $GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $second ]['wrap']['box'], AUTH_KEY ) as $secret ) if ( false !== strpos( $html, $secret ) ) throw new RuntimeException( 'La pestaña Seguridad muestra un secreto.' );
if ( preg_match( '/[A-Za-z0-9+\/_-]{43}/', $html ) ) throw new RuntimeException( 'La pestaña Seguridad muestra algo con forma de clave de 32 bytes.' );
if ( false === strpos( $html, 'value="" autocomplete="new-password" placeholder="Guardada' ) || false === strpos( $html, 'digitalisimo-secret is-set' ) || false === strpos( $html, $K::short( $second ) ) || false === strpos( $html, '<td>1</td>' ) ) throw new RuntimeException( 'La pestaña no anuncia la frase guardada, su huella o los respaldos que dependen de ella.' );
$nonce = 'nonce-' . Digitalisimo_Backups_Security::ACTION_SAVE;
list( $ok, $message, $url ) = bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), array( '_wpnonce' => $nonce, 'digitalisimo_backups_security' => array( 'passphrase' => '', 'passphrase_repeat' => '', 'require_signed' => '1' ) ) );
if ( ! $ok || $K::keyring()['active'] !== $second || false === strpos( $url, 'tab=security' ) ) throw new RuntimeException( 'Un campo vacío no conservó la frase: ' . $message );
list( $ok, $message, $url ) = bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), array( '_wpnonce' => $nonce, 'digitalisimo_backups_security' => array( 'passphrase' => 'una frase nueva y larga', 'passphrase_repeat' => 'una frase nueva y larga!', 'offsite' => '1' ) ) );
if ( $ok || $K::keyring()['active'] !== $second || false !== strpos( $url, 'frase nueva' ) ) throw new RuntimeException( 'Se aceptaron dos frases distintas o el redirect repite la frase.' );
list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), array( '_wpnonce' => $nonce, 'digitalisimo_backups_security' => array( 'passphrase' => 'una frase nueva y larga', 'passphrase_repeat' => 'una frase nueva y larga' ) ) );
if ( $ok || false === strpos( $message, 'fuera de este sitio' ) ) throw new RuntimeException( 'Se guardó una frase sin confirmar que se guardó fuera del sitio.' );
list( $ok, $message, $url ) = bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), array( '_wpnonce' => $nonce, 'digitalisimo_backups_security' => array( 'passphrase' => 'una frase nueva y larga', 'passphrase_repeat' => 'una frase nueva y larga', 'offsite' => '1', 'encrypt' => '1' ) ) );
$third = $K::keyring()['active'];
if ( ! $ok || $third === $second || false !== strpos( $url, 'frase nueva' ) || empty( Digitalisimo_Backups_Security::settings()['encrypt'] ) || false === strpos( $message, 'nadie puede recuperarlos' ) ) throw new RuntimeException( 'No se guardó la frase nueva con el aviso de cifrado: ' . $message );
try { bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), array( '_wpnonce' => 'falso', 'digitalisimo_backups_security' => array( 'require_signed' => '' ) ) ); throw new RuntimeException( 'Se guardó sin nonce.' ); } catch ( Bk_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
$GLOBALS['bk_caps'] = array( 'manage_options' => false );
try { bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), array( '_wpnonce' => $nonce ) ); throw new RuntimeException( 'Se guardó sin permisos.' ); } catch ( Bk_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
try { bk_post( array( 'Digitalisimo_Backups_Security', 'remove_key' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups_Security::ACTION_REMOVE_KEY, 'digitalisimo_backups_key' => $first, 'digitalisimo_backups_key_confirmation' => 'QUITAR' ) ); throw new RuntimeException( 'Se quitó una clave sin permisos.' ); } catch ( Bk_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
list( $ok ) = bk_post( array( 'Digitalisimo_Backups_Security', 'remove_key' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups_Security::ACTION_REMOVE_KEY, 'digitalisimo_backups_key' => $first, 'digitalisimo_backups_key_confirmation' => 'quizá' ) );
if ( $ok || ! isset( $K::keyring()['keys'][ $first ] ) ) throw new RuntimeException( 'Se quitó una clave sin escribir QUITAR.' );
list( $ok ) = bk_post( array( 'Digitalisimo_Backups_Security', 'remove_key' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups_Security::ACTION_REMOVE_KEY, 'digitalisimo_backups_key' => $first, 'digitalisimo_backups_key_confirmation' => 'quitar' ) );
if ( ! $ok || isset( $K::keyring()['keys'][ $first ] ) ) throw new RuntimeException( 'No se quitó la clave retirada.' );
// En Multisite el llavero y la configuración son opciones de red.
$GLOBALS['bk_multisite'] = true;
$K::set_passphrase( 'frase de toda la red, larga' );
if ( empty( $GLOBALS['bk_site_options'][ $K::KEYRING ]['active'] ) || $K::keyring()['active'] === $third ) throw new RuntimeException( 'En Multisite el llavero no es de la red.' );
$GLOBALS['bk_multisite'] = false;

// 8. La clave en wp-config.php: firma y cifra lo nuevo, el cifrado es obligatorio y su valor nunca se muestra.
$constant = base64_encode( random_bytes( 32 ) );
define( 'DIGITALISIMO_BACKUPS_KEY', $constant );
if ( 'valid' !== $K::constant_state() || empty( Digitalisimo_Backups_Security::settings()['encrypt'] ) ) throw new RuntimeException( 'La constante no obliga a cifrar.' );
$plan = Digitalisimo_Backups_Security::plan( true );
if ( ! $plan['encrypt'] || 'wp-config' !== $plan['descriptor']['source'] || null !== $plan['descriptor']['kdf'] ) throw new RuntimeException( 'La constante no es la clave de lo nuevo.' );
if ( ! $K::resolve_by_id( $third ) ) throw new RuntimeException( 'Con la constante, las claves del llavero dejaron de servir para restaurar.' );
ob_start();
Digitalisimo_Backups_Security::panel( false, array() );
$html = ob_get_clean();
if ( false !== strpos( $html, $constant ) || false !== strpos( $html, rtrim( $constant, '=' ) ) || false === strpos( $html, 'wp-config.php · huella ' . $K::short( $K::key_id( base64_decode( $constant ) ) ) ) || false === strpos( $html, 'disabled="disabled"' ) ) throw new RuntimeException( 'La pestaña muestra la constante o no bloquea el cifrado.' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: claves, llavero, fallo cerrado, exclusión del SQL y unión tras restaurar verificados.\n";
