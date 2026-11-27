<?php
// B2 de la quinta revisión (2026-11-26): con la clave y la URL de Webuzo en wp-config.php, el usuario de la API y «no
// verificar el certificado» tampoco se cambian desde la pantalla (salen de constantes o quedan fijos). Falla con bfe284e6.
define( 'DIGITALISIMO_WEBUZO_API_KEY', 'CLAVE-WEBUZO-OK' );
define( 'DIGITALISIMO_WEBUZO_URL', 'https://webuzo.test:2005' );
require __DIR__ . '/fixtures/hosting-wp.php';
digi_store_settings();
$o = get_option( 'digitalisimo_hosting_options' ); $o['webuzo_url'] = 'https://atacante.test:2005'; $o['webuzo_insecure'] = 1; $o['webuzo_api_user'] = 'otro'; update_option( 'digitalisimo_hosting_options', $o );
$GLOBALS['http'] = array(); $r = Digitalisimo_Hosting_Webuzo::from_settings()->users(); $h = $GLOBALS['http'][0] ?? null;
check( 'ok' === $r['kind'] && $h && 0 === strpos( $h['url'], 'https://webuzo.test:2005/' ), 'B2 con las dos constantes, la clave sólo va a la URL de wp-config.php' );
check( true === $h['args']['sslverify'] && 'root' === $h['args']['body']['apiuser'], 'B2 lo guardado antes (no verificar certificado, otro usuario) no se usa con la clave de wp-config.php' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_url' => 'https://atacante.test:2005', 'webuzo_insecure' => 1, 'webuzo_api_user' => 'x' ) );
ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean(); $_POST = array();
check( false === strpos( $html, 'CLAVE-WEBUZO-OK' ), 'B2 la pantalla no imprime la clave' );
check( false === strpos( $html, 'name="hosting[webuzo_api_user]"' ) && false === strpos( $html, 'name="hosting[webuzo_insecure]"' ), 'B2 el usuario de la API y la casilla del certificado no se editan en pantalla' );
$GLOBALS['http'] = array(); Digitalisimo_Hosting_Webuzo::from_settings()->users(); $h = $GLOBALS['http'][0] ?? null;
check( $h && true === $h['args']['sslverify'] && 'root' === $h['args']['body']['apiuser'] && 0 === strpos( $h['url'], 'https://webuzo.test:2005/' ), 'B2 un formulario alterado tampoco los cambia' );
echo "Hosting, wp-config.php bloquea Webuzo: {$GLOBALS['checks']} comprobaciones correctas.\n";
