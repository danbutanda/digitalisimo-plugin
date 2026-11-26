<?php
// DIGITALÍSIMO Hosting por MCP y productos por extensión (pedido del propietario del 2026-11-26):
//   1. el valor de fábrica de «Productos por extensión» es .com, .net y .mx (sin .org), y la lista vieja guardada tal cual
//      (.com, .mx y .org sin IDs) vale como la de fábrica de hoy;
//   2. get devuelve todos los ajustes del sitio sin ningún secreto (de las claves y la cuenta de Namecheap, sólo si están);
//   3. update COMBINA con lo guardado: lo que no llega no cambia, con la misma validación que el formulario;
//   4. la cuenta de Namecheap, Webuzo, los nameservers, Banxico y la venta automática no se escriben por MCP; una clave
//      desconocida, de sólo lectura o con otro tipo no guarda nada;
//   5. sin manage_options, nada; en una red se escribe la opción del sitio, nunca la de red, y «Heredar» no se pierde;
//   6. una línea sin ID (o la lista vacía) se resuelve con el producto marcado con su extensión o con su SKU; una línea
//      con ID manda; un borrador no vende;
//   7. marcar un producto con su extensión (registro o transferencia) exige manage_woocommerce o manage_options;
//   8. el aviso del escritorio (con la X) cuando la lista no resuelve productos o le falta alguno (B3).
// Uso: php tests/hosting-mcp-settings.php
require __DIR__ . '/fixtures/hosting-wp.php';
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
$H = 'Digitalisimo_Hosting'; $PR = 'Digitalisimo_Hosting_Products';
function opt() { return get_option( 'digitalisimo_hosting_options', array() ); }
function reset_site() { $GLOBALS['options'] = array( 'admin_email' => 'admin@tienda.test', 'blogname' => 'Tienda', 'digitalisimo_hosting_ecommerce_migrated' => 1 ); $GLOBALS['caps'] = null; unset( $GLOBALS['caps'] ); Digitalisimo_Hosting_Products::flush(); }

// ── 1. Valor de fábrica ───────────────────────────────────────────────────────────────────────────────────────
reset_site();
$g = $H::mcp_settings();
check( ".com|\n.net|\n.mx|" === $g['settings']['domain_products'], 'De fábrica: .com, .net y .mx' );
check( false === strpos( wp_json_encode( $g ), '.org' ), 'De fábrica no hay rastro de .org' );
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => ".com|\r\n.mx|\r\n.org|" ) );
check( ".com|\n.net|\n.mx|" === $H::settings()['domain_products'], 'La lista de fábrica vieja (.com, .mx y .org sin IDs) guardada por el formulario vale como la de hoy' );
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => ".com|20\n.org|33" ) );
check( ".com|20\n.org|33" === $H::settings()['domain_products'], 'Una lista propia (con IDs) se respeta tal cual' );

// ── 2. get sin secretos ───────────────────────────────────────────────────────────────────────────────────────
reset_site();
digi_store_settings( array( 'banxico_token' => 'TOKEN-BANXICO-SECRETO', 'hosting_category_ids' => '63' ) );
$g = $H::mcp_settings();
$json = wp_json_encode( $g );
foreach ( array( 'CLAVE-NAMECHEAP', 'CLAVE-WEBUZO-OK', 'TOKEN-BANXICO-SECRETO', 'nc-api', 'nc-user', '203.0.113.5' ) as $secret ) check( false === strpos( $json, $secret ), "get no devuelve «{$secret}»" );
check( true === $g['secrets_set']['api_key'] && true === $g['secrets_set']['webuzo_api_key'] && true === $g['secrets_set']['banxico_token'] && true === $g['namecheap']['configured'], 'get dice qué secretos están puestos y que la cuenta de Namecheap está configurada' );
check( '63' === $g['settings']['hosting_category_ids'] && true === $g['settings']['provision_enabled'] && 7 === $g['settings']['grace_days'] && 'https://webuzo.test:2005' === $g['settings']['webuzo_url'], 'get devuelve todos los demás ajustes con su tipo' );
check( in_array( 'api_key', $g['read_only'], true ) && in_array( 'domain_products', $g['writable'], true ) && ! array_intersect( $g['read_only'], $g['writable'] ), 'get dice qué se puede escribir y qué sólo se lee' );
check( isset( $g['domain_products']['resolved']['.com'] ) && 20 === $g['domain_products']['resolved']['.com']['product_id'], 'get trae la resolución de productos por extensión' );

// ── 3. update combina ─────────────────────────────────────────────────────────────────────────────────────────
$before = opt();
$r = $H::mcp_update( array( 'domain_products' => ".com|20\n.net|\n.mx|21", 'ask_domain' => false, 'pricing_margin' => '500', 'pricing_fee' => '3,5', 'form_selector' => '<b>.mi-form</b>' ) );
check( is_array( $r ) && true === $r['ok'], 'update devuelve el resultado' );
$after = opt();
foreach ( array( 'api_key', 'api_user', 'username', 'client_ip', 'webuzo_api_key', 'webuzo_url', 'banxico_token', 'hosting_category_ids', 'provision_enabled', 'own_domain_auto', 'nameservers', 'provision_since' ) as $k ) check( $before[ $k ] === $after[ $k ], "update no toca «{$k}», que no llegó" );
check( ".com|20\n.net|\n.mx|21" === $after['domain_products'] && 0 === $after['ask_domain'], 'update guarda lo que llegó' );
check( 90.0 === $after['pricing_margin'] && 3.5 === $after['pricing_fee'] && '.mi-form' === $after['form_selector'], 'update valida como el formulario (porcentaje hasta 90, coma decimal, texto saneado)' );
check( 90.0 === $r['adjusted']->pricing_margin && '.mi-form' === $r['adjusted']->form_selector && ! isset( $r['adjusted']->domain_products ), 'adjusted dice lo que la validación guardó distinto de lo enviado' );
check( in_array( 'domain_products', $r['changed'], true ) && in_array( 'ask_domain', $r['changed'], true ) && ".com|20\n.net|\n.mx|21" === $r['settings']['domain_products'], 'changed y settings reflejan el valor resultante' );
$r2 = $H::mcp_update( array( 'simplify_checkout' => 'yes' ) );
check( 1 === opt()['simplify_checkout'] && ".com|20\n.net|\n.mx|21" === opt()['domain_products'] && 0 === opt()['ask_domain'], 'Un segundo update sólo cambia su clave: nada de lo anterior se pierde' );

// Misma validación que el formulario: lo mismo enviado por la pantalla se guarda igual.
$mcp_saved = opt();
$GLOBALS['options']['digitalisimo_hosting_options'] = $before;
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'domain_products' => ".com|20\n.net|\n.mx|21", 'ask_domain' => '0', 'pricing_margin' => '500', 'pricing_fee' => '3,5', 'form_selector' => '<b>.mi-form</b>' ) );
ob_start(); $H::page(); ob_end_clean(); $_POST = array();
foreach ( array( 'domain_products', 'ask_domain', 'pricing_margin', 'pricing_fee', 'form_selector' ) as $k ) check( opt()[ $k ] === $mcp_saved[ $k ], "El formulario y el MCP guardan igual «{$k}»" );

// ── 4. Sólo lectura, desconocidas y tipos ─────────────────────────────────────────────────────────────────────
$snap = serialize( opt() );
foreach ( array( array( 'api_key' => 'OTRA' ), array( 'client_ip' => '1.2.3.4' ), array( 'webuzo_url' => 'https://evil.test:2005' ), array( 'webuzo_api_key' => 'X' ), array( 'nameservers' => 'ns1.evil.test' ), array( 'provision_enabled' => true ), array( 'own_domain_auto' => 1 ), array( 'enabled' => true ), array( 'grace_days' => 0 ), array( 'inherit_network' => 1 ), array( 'banxico_token' => 'T' ) ) as $bad ) {
	$e = $H::mcp_update( $bad + array( 'replace_hosting' => true ) );
	check( is_wp_error( $e ) && 'digitalisimo_hosting_read_only' === $e->code && serialize( opt() ) === $snap, 'Por MCP no se escribe «' . key( $bad ) . '» y no se guarda nada del lote' );
}
$e = $H::mcp_update( array( 'domain_productos' => '.com|1' ) );
check( is_wp_error( $e ) && 'digitalisimo_hosting_unknown_key' === $e->code && serialize( opt() ) === $snap, 'Una clave desconocida no guarda nada' );
$e = $H::mcp_update( array( 'simplify_checkout' => 'quizá', 'domain_products' => '.com|5' ) );
check( is_wp_error( $e ) && serialize( opt() ) === $snap, 'Un booleano inválido no guarda nada' );
$e = $H::mcp_update( array( 'pricing_margin' => 'mucho' ) );
check( is_wp_error( $e ) && serialize( opt() ) === $snap, 'Un número inválido no guarda nada' );
$e = $H::mcp_update( array( 'pricing_rounding' => '7' ) );
check( is_wp_error( $e ) && serialize( opt() ) === $snap, 'Un redondeo fuera de la lista no guarda nada' );
$e = $H::mcp_update( array( 'domain_products' => array( '.com' => 1 ) ) );
check( is_wp_error( $e ) && serialize( opt() ) === $snap, 'Un texto que llega como lista no guarda nada' );
check( is_wp_error( $H::mcp_update( array() ) ) && is_wp_error( $H::mcp_update( 'domain_products=.com' ) ), 'Sin data (o data que no es un objeto) no hace nada' );

// ── 5. Permiso y multisitio ───────────────────────────────────────────────────────────────────────────────────
$GLOBALS['caps'] = array( 'edit_posts', 'manage_woocommerce' );
$e = $H::mcp_update( array( 'replace_hosting' => true ) );
check( is_wp_error( $e ) && 'digitalisimo_hosting_forbidden' === $e->code && serialize( opt() ) === $snap, 'Sin manage_options, update se rechaza (aunque administre la tienda)' );
unset( $GLOBALS['caps'] );
$GLOBALS['multisite'] = true; $GLOBALS['site_options'] = array( 'digitalisimo_hosting_options' => array( 'api_user' => 'red', 'username' => 'red', 'api_key' => 'CLAVE-RED', 'client_ip' => '198.51.100.4', 'domain_products' => '.com|999' ) );
$o = opt(); $o['inherit_network'] = 1; update_option( 'digitalisimo_hosting_options', $o );
$network_before = serialize( $GLOBALS['site_options'] );
$r = $H::mcp_update( array( 'domain_products' => '.com|20', 'replace_hosting' => true ) );
check( is_array( $r ) && '.com|20' === opt()['domain_products'] && serialize( $GLOBALS['site_options'] ) === $network_before, 'En una red, update escribe la opción del sitio y nunca la de red' );
check( 1 === opt()['inherit_network'], 'En una red, update no le quita al sitio «Heredar de la red» (no llegó)' );
check( 'network' === $r['namecheap']['source'] && false === strpos( wp_json_encode( $r ), 'CLAVE-RED' ) && false === strpos( wp_json_encode( $r ), '198.51.100.4' ), 'Con «Heredar», get dice que la cuenta es de la red sin mostrarla' );
$GLOBALS['site_options']['digitalisimo_hosting_options'] += array( 'webuzo_url' => 'https://panel-de-la-red.test:2005', 'nameservers' => 'ns1.red.test, ns2.red.test' );
$g = $H::mcp_settings();
check( null === $g['settings']['webuzo_url'] && null === $g['settings']['nameservers'] && false === strpos( wp_json_encode( $g ), 'panel-de-la-red' ) && in_array( 'webuzo_url', $g['inherited'], true ), 'Con «Heredar», el servidor Webuzo y los nameservers de la red no se muestran (como en la pantalla)' );
$GLOBALS['multisite'] = false; $GLOBALS['site_options'] = array();

// ── 6. Resolución de productos ────────────────────────────────────────────────────────────────────────────────
reset_site();
digi_product( 30, 'Dominio .net', 280 ); digi_product( 31, 'Dominio .mx (borrador)', 500 ); digi_product( 32, 'Dominio .com otro', 310 );
$GLOBALS['products'][20]->sku = 'dominio-com'; $GLOBALS['products'][31]->sku = 'dominio-mx'; $GLOBALS['product_status'][31] = 'draft';
update_post_meta( 30, '_digitalisimo_tld', '.NET' );
$rep = $PR::report( ".com|\n.net|\n.mx|" );
check( 20 === $rep['resolved']['.com']['product_id'] && 'sku' === $rep['resolved']['.com']['source'], 'Una línea sin ID usa el producto con el SKU dominio-com' );
check( 30 === $rep['resolved']['.net']['product_id'] && 'meta' === $rep['resolved']['.net']['source'], 'Una línea sin ID usa el producto marcado con su extensión' );
check( array( '.mx' ) === $rep['unresolved'], 'Un producto en borrador no vende: .mx queda sin producto' );
$PR::flush();
check( array( '.com' => 32, '.net' => 30 ) === $PR::map( ".com|32\n.net|" ), 'Una línea con ID manda sobre el SKU' );
$PR::flush();
check( array( '.net' => 30, '.com' => 20 ) === $PR::map( '' ), 'Con la lista vacía: los productos marcados y, por SKU, las extensiones de fábrica' );
update_option( 'digitalisimo_hosting_options', array( 'enabled' => 1, 'domain_products' => ".com|\n.net|\n.mx|" ) ); $PR::flush();
check( array( '.com' => 20, '.net' => 30 ) === $H::domain_products(), 'domain_products() (buscador, carrito y aprovisionamiento) usa la resolución' );

// ── 7. Marcar productos ───────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['caps'] = array( 'edit_posts' );
$e = $PR::mark( 32, '.mx' );
check( is_wp_error( $e ) && 'digitalisimo_hosting_forbidden' === $e->code && '' === get_post_meta( 32, '_digitalisimo_tld', true ), 'Sin manage_woocommerce ni manage_options no se marca nada' );
$GLOBALS['caps'] = array( 'manage_woocommerce' );
$r = $PR::mark( 32, 'mx' );
check( is_array( $r ) && '.mx' === get_post_meta( 32, '_digitalisimo_tld', true ) && 32 === $H::domain_products()['.mx'] && 'register' === $r['product']['kind'], 'Con manage_woocommerce se marca (y «mx» se guarda como «.mx»); el buscador ya lo usa' );
check( 32 === $r['domain_products']['resolved']['.mx']['product_id'] && ! $r['domain_products']['unresolved'], 'mark devuelve la resolución resultante' );
$e = $PR::mark( 32, '.m x!' );
check( is_wp_error( $e ) && 'digitalisimo_hosting_tld' === $e->code && '.mx' === get_post_meta( 32, '_digitalisimo_tld', true ), 'Una extensión inválida no cambia nada' );
check( is_wp_error( $PR::mark( 999, '.com' ) ), 'Un producto que no existe no se marca' );
unset( $GLOBALS['caps'] );
digi_product( 40, 'Transferencia de dominio', 250 );
$r = $PR::mark( 40, '*', 'transfer' );
check( is_array( $r ) && '*' === $PR::transfer_ext( 40 ) && ! in_array( 40, $H::domain_products(), true ) && 'transfer' === $r['product']['kind'], 'Un producto de transferencia (cualquier extensión) no entra en los de registro' );
$PR::mark( 32, '' );
check( '' === get_post_meta( 32, '_digitalisimo_tld', true ) && ! isset( $H::domain_products()['.mx'] ), 'Quitar la marca la quita y la resolución se actualiza' );

// ── 8. Aviso del escritorio (B3) ──────────────────────────────────────────────────────────────────────────────
$GLOBALS['pagenow'] = 'index.php';
update_option( 'digitalisimo_hosting_options', array( 'enabled' => 1, 'domain_products' => ".com|\n.net|\n.mx|" ) );
$PR::flush();
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( false !== strpos( $n, 'is-dismissible' ) && false !== strpos( $n, '.mx' ) && false === strpos( $n, '.com,' ), 'Escritorio: aviso con la X que nombra la extensión sin producto' );
foreach ( array( 20, 30 ) as $id ) $GLOBALS['product_status'][ $id ] = 'draft';
$GLOBALS['products'][20]->sku = ''; delete_post_meta( 30, '_digitalisimo_tld' ); $PR::flush();
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( false !== strpos( $n, 'no resuelve ningún producto' ), 'Escritorio: aviso cuando la lista no resuelve ningún producto' );
$GLOBALS['pagenow'] = 'edit.php'; $_GET = array( 'post_type' => 'post' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Fuera del escritorio, Plugins, productos y Hosting no se pinta' );
$GLOBALS['pagenow'] = 'index.php'; $GLOBALS['caps'] = array( 'edit_posts' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Quien no administra el sitio no ve el aviso' );
unset( $GLOBALS['caps'] );
update_option( 'digitalisimo_hosting_options', array( 'enabled' => 1, 'domain_products' => ".com|20\n.mx|21" ) ); $GLOBALS['product_status'] = array(); $PR::flush();
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Con todas las extensiones resueltas no hay aviso' );

echo "Hosting por MCP y productos por extensión: {$GLOBALS['checks']} comprobaciones correctas.\n";
