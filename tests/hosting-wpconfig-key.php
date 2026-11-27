<?php
// B2 de la revisión final (2026-11-25): con la clave de Webuzo en wp-config.php, la URL tiene que estar también ahí. Una
// clave de wp-config nunca se manda a una URL que se edita en la pantalla (un administrador de sitio podría desviarla
// a su servidor). Falla con el código de 7c2ed5ad.
define( 'DIGITALISIMO_WEBUZO_API_KEY', 'CLAVE-ROOT-DE-WPCONFIG' );
require __DIR__ . '/fixtures/hosting-wp.php';
digi_store_settings();
$GLOBALS['multisite'] = true; $GLOBALS['site_options']['digitalisimo_hosting_ecommerce_network_migrated'] = 1;
$GLOBALS['caps'] = array( 'manage_options', 'manage_woocommerce' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_url' => 'https://atacante.test:2005', 'webuzo_api_user' => 'root' ) );
ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean(); $_POST = array();
check( false === strpos( $html, 'CLAVE-ROOT' ), 'B2 la pantalla no imprime la clave de wp-config.php' );
check( false === strpos( $html, 'name="hosting[webuzo_url]"' ) && false !== strpos( $html, 'define también <code>DIGITALISIMO_WEBUZO_URL</code>' ), 'B2 con la clave en wp-config.php, la URL no se edita en pantalla y se pide la constante' );
check( 'https://atacante.test:2005' !== ( get_option( 'digitalisimo_hosting_options' )['webuzo_url'] ?? '' ), 'B2 una URL enviada a mano (formulario alterado) no se guarda' );
$o = get_option( 'digitalisimo_hosting_options' ); $o['webuzo_url'] = 'https://atacante.test:2005'; update_option( 'digitalisimo_hosting_options', $o ); // aun si quedó guardada de antes
$GLOBALS['http'] = array();
$r = Digitalisimo_Hosting_Webuzo::from_settings()->users();
check( 'config' === $r['kind'] && ! $GLOBALS['http'], 'B2 sin DIGITALISIMO_WEBUZO_URL no sale ninguna petición con la clave de wp-config.php' );
echo "Hosting, clave en wp-config.php: {$GLOBALS['checks']} comprobaciones correctas.\n";
