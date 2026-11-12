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

// Sin secretos, `status` ya avisa de que hay que definirlas antes de conectar un destino.
$status = $B::service_status();
if ( 'none' !== ( $status['credentials'] ?? null ) || false === strpos( (string) ( $status['credentials_notice'] ?? '' ), 'AUTH_KEY y SECURE_AUTH_KEY propias' ) ) throw new RuntimeException( 'Sin salts propias, el estado no avisa de que hay que definirlas: ' . json_encode( $status ) );
if ( false !== $C::key() || $C::can_encrypt() || false !== $K::wrap_key() ) throw new RuntimeException( 'Con las salts de ejemplo no debe haber clave de credenciales ni de llavero.' );

// Revisión de 1.0.74: en un sitio nuevo sin salts propias, «Destinos» tal como se abre (sin ?edit= ni ?add=) tiene que
// llevar a un formulario que sí guarde. Una conexión adicional sólo guarda credenciales cifradas: «Añadir destino» abre
// la conexión principal, y el formulario de alta (por un enlace guardado) también enlaza a ella.
$_GET = array( 'page' => 'digitalisimo-backups', 'tab' => 'servers' );
ob_start();
bk_call( $B, 'servers_panel', $settings(), array() );
$list = ob_get_clean();
if ( ! preg_match( '#<a class="button button-primary" href="([^"]+)">Añadir destino</a>#', $list, $add ) ) throw new RuntimeException( '«Destinos» no tiene «Añadir destino».' );
parse_str( (string) parse_url( html_entity_decode( $add[1], ENT_QUOTES ), PHP_URL_QUERY ), $followed );
$_GET = $followed;
ob_start();
bk_call( $B, 'servers_panel', $settings(), array() );
$form = ob_get_clean();
if ( false === strpos( $form, 'name="action" value="' . $B::ACTION_SAVE . '"' ) || false !== strpos( $form, 'value="' . $B::ACTION_SAVE_CONNECTION . '"' ) || 1 !== substr_count( $form, 'class="digitalisimo-backups-connection-form"' ) ) throw new RuntimeException( 'Sin salts propias, «Añadir destino» (' . $add[1] . ') no abre el formulario de la conexión principal, el único que guarda sin cifrar.' );
$_GET = array( 'page' => 'digitalisimo-backups', 'tab' => 'servers', 'add' => '1' );
ob_start();
bk_call( $B, 'servers_panel', $settings(), array() );
$adding = ob_get_clean();
$_GET = array();
if ( ! preg_match( '#<p class="description digitalisimo-backups-warning">Sin AUTH_KEY y SECURE_AUTH_KEY propias[^<]*<a href="[^"]*tab=servers&amp;edit=sftp[^"]*">conexión principal</a>#', $adding ) ) throw new RuntimeException( 'El formulario de otra conexión no avisa de que sin salts propias no guarda ni enlaza a la conexión principal.' );

// Revisión de 1.0.74 (H5): «Cifrado y firma» (plegado con fold_long() como en «Ajustes») deja a la vista, fuera de «Más»,
// que este sitio no puede guardar una frase.
ob_start();
Digitalisimo_Backups_Security::panel( false, array() );
$security = $B::fold_long( (string) ob_get_clean() );
$at = strpos( $security, 'Este sitio no puede guardar una frase' );
$fold = false === $at ? false : strrpos( substr( $security, 0, $at ), '<details class="digitalisimo-backups-help">' );
if ( false === $at || ( false !== $fold && false === strpos( substr( $security, $fold, $at - $fold ), '</details>' ) ) ) throw new RuntimeException( 'Sin salts propias, «Cifrado y firma» esconde en «Más» que este sitio no puede guardar una frase.' );

// Guardar: se guarda como hasta ahora, con aviso; nunca se pierde un secreto.
list( $ok, $message ) = bk_post( array( $B, 'save' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $fields + $secrets ) );
if ( ! $ok || false === strpos( $message, 'se guardaron sin cifrar' ) || false === strpos( $message, 'AUTH_KEY y SECURE_AUTH_KEY' ) ) throw new RuntimeException( 'Guardar sin salts propias no avisó: ' . $message );
foreach ( $secrets as $value ) if ( false !== strpos( $message, $value ) ) throw new RuntimeException( 'La redirección repite un secreto.' );
if ( array_intersect_key( $settings(), $secrets ) != $secrets || false !== get_option( $C::OPTION ) ) throw new RuntimeException( 'Sin salts propias los secretos no se conservaron como hasta ahora o se marcaron como limpios.' );
$status = $B::service_status();
if ( 'unprotected' !== $status['credentials'] || false === strpos( $status['credentials_notice'], 'sin cifrar' ) || ! isset( $status['destinations']['sftp'], $status['destinations']['google'] ) ) throw new RuntimeException( 'El estado no avisa de las credenciales sin cifrar: ' . json_encode( $status ) );
foreach ( $secrets as $value ) if ( false !== strpos( json_encode( $status ), $value ) ) throw new RuntimeException( 'El estado lleva un secreto.' );
// Desde 1.0.74 «Destinos» pinta el formulario de la conexión principal (con los campos de todos los tipos) al pedirlo: ?edit=<tipo>.
$_GET = array( 'edit' => 'sftp' );
ob_start();
bk_call( $B, 'servers_panel', $settings(), array() );
$html = ob_get_clean();
$_GET = array();
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
