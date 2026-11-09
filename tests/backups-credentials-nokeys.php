<?php
/**
 * S4 · sin AUTH_KEY y SECURE_AUTH_KEY propias en wp-config.php (las de ejemplo de WordPress; las mismas reglas que el
 * llavero): no hay con qué cifrar, así que nada se rompe. Guardar un secreto lo guarda como hasta 1.0.60 (en claro,
 * porque no guardarlo dejaría el sitio sin destino) con aviso en la redirección, la pantalla y `status`; la carga del
 * escritorio no lo toca ni marca los ajustes como limpios; los respaldos y su restauración siguen funcionando, y lo
 * que se cifró con unas salts que ya no están nunca se lee en claro: queda en «vuelve a escribir».
 */
define( 'AUTH_KEY', 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY', 'put your unique phrase here' );
require __DIR__ . '/lib/backups-env.php';
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$K = 'Digitalisimo_Backups_Keys';
function get_current_user_id() { return 1; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce-' . $action; }
$secrets = array( 'sftp_password' => 'Sftp-SinSalts.b81f4c', 'google_client_secret' => 'GOCSPX-sin-salts-02de', 'google_refresh_token' => '1//refresh-sin-salts-6f13' );
$fields = array( 'destination' => 'local', 'retention' => '100', 'sftp_host' => 'sftp.example.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_path' => '/backups', 'sftp_fingerprint' => str_repeat( 'C', 40 ), 'google_client_id' => 'cliente-google' );
$settings = function() use ( $B ) { return bk_call( $B, 'settings' ); };

if ( false !== $C::key() || $C::can_encrypt() || false !== $K::wrap_key() ) throw new RuntimeException( 'Con las salts de ejemplo no debe haber clave de credenciales ni de llavero.' );
// Sin secretos, `status` ya avisa de que hay que definirlas antes de conectar un destino.
$status = $B::service_status();
if ( 'none' !== $status['credentials'] || false === strpos( $status['credentials_notice'], 'AUTH_KEY y SECURE_AUTH_KEY propias' ) ) throw new RuntimeException( 'Sin salts propias, el estado no avisa de que hay que definirlas: ' . json_encode( $status ) );

// Guardar: se guarda como hasta ahora, con aviso; nunca se pierde un secreto.
list( $ok, $message ) = bk_post( array( $B, 'save' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $fields + $secrets ) );
if ( ! $ok || false === strpos( $message, 'se guardaron sin cifrar' ) || false === strpos( $message, 'AUTH_KEY y SECURE_AUTH_KEY' ) ) throw new RuntimeException( 'Guardar sin salts propias no avisó: ' . $message );
foreach ( $secrets as $value ) if ( false !== strpos( $message, $value ) ) throw new RuntimeException( 'La redirección repite un secreto.' );
if ( array_intersect_key( $settings(), $secrets ) != $secrets || false !== get_option( $C::OPTION ) || false !== get_option( $B::CREDENTIALS_CLEAN ) ) throw new RuntimeException( 'Sin salts propias los secretos no se conservaron como hasta ahora o se marcaron como limpios.' );
$status = $B::service_status();
if ( 'unprotected' !== $status['credentials'] || false === strpos( $status['credentials_notice'], 'sin cifrar' ) || ! isset( $status['destinations']['sftp'], $status['destinations']['google'] ) ) throw new RuntimeException( 'El estado no avisa de las credenciales sin cifrar: ' . json_encode( $status ) );
foreach ( $secrets as $value ) if ( false !== strpos( json_encode( $status ), $value ) ) throw new RuntimeException( 'El estado lleva un secreto.' );
ob_start();
bk_call( $B, 'servers_panel', $settings(), array() );
$html = ob_get_clean();
foreach ( $secrets as $value ) if ( false !== strpos( $html, $value ) ) throw new RuntimeException( 'La pantalla muestra un secreto.' );
if ( 3 !== substr_count( $html, 'placeholder="Guardado · escribe sólo para reemplazar"' ) ) throw new RuntimeException( 'La pantalla no anuncia los secretos guardados.' );
// La carga del escritorio no puede cifrar: no toca nada ni marca los ajustes como limpios.
$options = $GLOBALS['bk_options'];
$B::credentials_on_load();
if ( 'unprotected' !== $B::migrate_credentials() || $GLOBALS['bk_options'] !== $options ) throw new RuntimeException( 'Sin salts propias, la migración cambió algo.' );

// Los respaldos siguen funcionando y su restauración devuelve los secretos como estaban.
$backup = bk_backup( true );
update_option( $B::OPTION, $fields );
$report = Digitalisimo_Backups_Security::open( $backup, bk_call( $B, 'history_item', basename( $backup ) ), '' );
try { bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report ); } finally { Digitalisimo_Backups_Security::close( $report ); }
if ( array_intersect_key( $settings(), $secrets ) != $secrets ) throw new RuntimeException( 'Sin salts propias, la restauración perdió un secreto.' );

// Lo que se cifró con unas salts que ya no están no se lee nunca en claro: queda en «vuelve a escribir».
update_option( $B::OPTION, $fields );
$K::put( $C::OPTION, array( 'v' => 1, 'items' => array( 'sftp_password' => array( 'kw' => '0123456789abcdef', 'c' => $K::b64url_encode( random_bytes( 64 ) ) ) ) ) );
$status = $B::service_status();
if ( '' !== $settings()['sftp_password'] || 'needs_reentry' !== $status['credentials'] || false === strpos( $status['credentials_notice'], 'vuelve a escribir la contraseña de SFTP' ) || false === strpos( $status['credentials_notice'], 'AUTH_KEY y SECURE_AUTH_KEY propias' ) ) throw new RuntimeException( 'Un almacén cifrado sin salts no pide volver a escribir: ' . json_encode( $status ) );

bk_cleanup();
echo "DIGITALÍSIMO Backups: sin AUTH_KEY y SECURE_AUTH_KEY propias, las credenciales se guardan como antes con aviso y nada se rompe.\n";
