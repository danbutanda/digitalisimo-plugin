<?php
// DIGITALÍSIMO Hosting por MCP y productos detectados (pedidos del propietario del 2026-11-26 y del 2026-11-27: «¿por qué
// usamos IDs de productos de hosting? debe ser un listado desplegable de lo que existe o detectarlos»):
//   1. de fábrica no hay IDs ni «|»: los productos se detectan y las listas por extensión son objetos y listas;
//   2. get devuelve todos los ajustes del sitio sin ningún secreto (de las claves y la cuenta de Namecheap, sólo si están)
//      y lo detectado: dominios (extensión, registro o transferencia, producto) y planes de hosting (paquete y periodo);
//   3. update COMBINA con lo guardado: lo que no llega no cambia, con la misma validación que el formulario;
//   4. la cuenta de Namecheap, Webuzo, los nameservers, Banxico y la venta automática no se escriben por MCP; una clave
//      desconocida, de sólo lectura, ya retirada (domain_products, IDs) o con otro tipo no guarda nada;
//   5. sin manage_options, nada; en una red se escribe la opción del sitio, nunca la de red, y «Heredar» no se pierde;
//   6. lo detectado: un borrador no vende, dos productos con la misma extensión y acción se avisan;
//   7. marcar un producto con su extensión (registro o transferencia) exige manage_woocommerce o manage_options y la
//      extensión se valida contra la lista de los registradores;
//   8. el aviso del escritorio (con la X) cuando hay repetidos o un sitio que vende hosting no tiene dominios.
// Uso: php tests/hosting-mcp-settings.php
require __DIR__ . '/fixtures/hosting-wp.php';
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
$H = 'Digitalisimo_Hosting'; $PR = 'Digitalisimo_Hosting_Products';
function opt() { return get_option( 'digitalisimo_hosting_options', array() ); }
function reset_site() { $GLOBALS['options'] = array( 'admin_email' => 'admin@tienda.test', 'blogname' => 'Tienda', 'digitalisimo_hosting_ecommerce_migrated' => 1, 'digitalisimo_hosting_products_migrated' => array( 'settings' => 1, 'products' => 1 ) ); $GLOBALS['caps'] = null; unset( $GLOBALS['caps'] ); Digitalisimo_Hosting_Products::flush(); Digitalisimo_Hosting_Lists::flush(); }

// ── 1. De fábrica ─────────────────────────────────────────────────────────────────────────────────────────────
reset_site();
$g = $H::mcp_settings();
$json = wp_json_encode( $g );
check( ! array_key_exists( 'domain_products', $g['settings'] ) && ! array_key_exists( 'hosting_product_ids', $g['settings'] ) && ! array_key_exists( 'finder_upsell_ids', $g['settings'] ) && ! array_key_exists( 'hosting_category_ids', $g['settings'] ), 'De fábrica no hay campos de IDs' );
check( false === strpos( $json, '|' ) && array( '.com', '.mx', '.com.mx', '.net', '.lat' ) === $g['settings']['finder_order'] && false !== strpos( $g['settings']['finder_requirements']->{'.mx'}, 'titular' ) && array() === $g['settings']['finder_upsell'], 'De fábrica: orden en lista, avisos por extensión y «se compran juntos» vacío, sin «|»' );
check( false === strpos( $json, '.org' ), 'De fábrica no hay rastro de .org' );

// ── 2. get sin secretos, con lo detectado ─────────────────────────────────────────────────────────────────────
reset_site();
digi_store_settings( array( 'banxico_token' => 'TOKEN-BANXICO-SECRETO' ) );
$g = $H::mcp_settings();
$json = wp_json_encode( $g );
foreach ( array( 'CLAVE-NAMECHEAP', 'CLAVE-WEBUZO-OK', 'TOKEN-BANXICO-SECRETO', 'nc-api', 'nc-user', '203.0.113.5' ) as $secret ) check( false === strpos( $json, $secret ), "get no devuelve «{$secret}»" );
check( true === $g['secrets_set']['api_key'] && true === $g['secrets_set']['webuzo_api_key'] && true === $g['secrets_set']['banxico_token'] && true === $g['namecheap']['configured'], 'get dice qué secretos están puestos y que la cuenta de Namecheap está configurada' );
check( true === $g['settings']['provision_enabled'] && 7 === $g['settings']['grace_days'] && 'https://webuzo.test:2005' === $g['settings']['webuzo_url'], 'get devuelve todos los demás ajustes con su tipo' );
check( in_array( 'api_key', $g['read_only'], true ) && in_array( 'finder_upsell', $g['writable'], true ) && ! in_array( 'domain_products', $g['writable'], true ) && ! array_intersect( $g['read_only'], $g['writable'] ), 'get dice qué se puede escribir y qué sólo se lee (sin las claves de IDs)' );
$doms = array(); foreach ( $g['domains'] as $d ) $doms[ $d['tld'] . '/' . $d['kind'] ] = $d['product_id'];
check( array( '.com/register' => 20, '.mx/register' => 21 ) === $doms && 'Dominio .com' === $g['domains'][0]['name'] && '$300' === $g['domains'][0]['price'], 'get trae los dominios detectados (extensión, venta, producto y precio)' );
$plans = array(); foreach ( $g['hosting_products'] as $h ) $plans[ $h['product_id'] ] = $h['plan'] . '/' . $h['period'];
check( 'digitalisimo_starter/Anual' === $plans[14693] && 'digitalisimo_pro/Anual' === $plans[14696], 'get trae los planes de hosting detectados con su paquete y periodo' );
check( false === strpos( $json, '|' ) && ! preg_match( '/"\d+\s*,\s*\d+"/', $json ), 'get no tiene «|» ni listas de IDs en texto' );

// ── 3. update combina ─────────────────────────────────────────────────────────────────────────────────────────
$before = opt();
$r = $H::mcp_update( array( 'registrar_by_tld' => array( '.mx' => 'dynadot' ), 'ask_domain' => false, 'webuzo_user_prefix' => 'Prueba-1234567', 'pricing_rounding' => 'end9', 'form_selector' => '<b>.mi-form</b>' ) );
check( is_array( $r ) && true === $r['ok'], 'update devuelve el resultado' );
$after = opt();
foreach ( array( 'api_key', 'api_user', 'username', 'client_ip', 'webuzo_api_key', 'webuzo_url', 'banxico_token', 'provision_enabled', 'own_domain_auto', 'nameservers', 'provision_since' ) as $k ) check( $before[ $k ] === $after[ $k ], "update no toca «{$k}», que no llegó" );
check( array( '.mx' => 'dynadot' ) === $after['registrar_by_tld'] && 0 === $after['ask_domain'], 'update guarda lo que llegó' );
check( 'prueba' === $after['webuzo_user_prefix'] && 'end9' === $after['pricing_rounding'] && '.mi-form' === $after['form_selector'], 'update valida como el formulario (prefijo saneado y recortado, redondeo, texto saneado)' );
check( 'prueba' === $r['adjusted']->webuzo_user_prefix && '.mi-form' === $r['adjusted']->form_selector && ! isset( $r['adjusted']->registrar_by_tld ), 'adjusted dice lo que la validación guardó distinto de lo enviado' );
check( in_array( 'registrar_by_tld', $r['changed'], true ) && in_array( 'ask_domain', $r['changed'], true ) && 'dynadot' === $r['settings']['registrar_by_tld']->{'.mx'}, 'changed y settings reflejan el valor resultante' );
$r2 = $H::mcp_update( array( 'simplify_checkout' => 'yes', 'registrar_by_tld' => array( '.com' => 'dynadot' ) ) );
check( 1 === opt()['simplify_checkout'] && array( '.mx' => 'dynadot', '.com' => 'dynadot' ) === opt()['registrar_by_tld'] && 0 === opt()['ask_domain'], 'Un segundo update sólo cambia lo suyo: el registrador por extensión se combina por extensión' );
$H::mcp_update( array( 'registrar_by_tld' => array( '.com' => 'default' ) ) );
check( array( '.mx' => 'dynadot' ) === opt()['registrar_by_tld'], '«default» regresa una extensión al registrador predeterminado' );

// Misma validación que el formulario: lo mismo enviado por la pantalla se guarda igual.
$mcp_saved = opt();
$GLOBALS['options']['digitalisimo_hosting_options'] = $before;
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'registrar_by_tld' => array( '.mx' => 'dynadot', '.com' => '' ), 'ask_domain' => '0', 'simplify_checkout' => '1', 'webuzo_user_prefix' => 'Prueba-1234567', 'pricing_rounding' => 'end9', 'form_selector' => '<b>.mi-form</b>' ) );
ob_start(); $H::page(); ob_end_clean(); $_POST = array();
foreach ( array( 'registrar_by_tld', 'ask_domain', 'webuzo_user_prefix', 'pricing_rounding', 'form_selector' ) as $k ) check( opt()[ $k ] === $mcp_saved[ $k ], "El formulario y el MCP guardan igual «{$k}»" );

// ── 4. Sólo lectura, desconocidas, retiradas y tipos ──────────────────────────────────────────────────────────
$snap = serialize( opt() );
foreach ( array( array( 'api_key' => 'OTRA' ), array( 'client_ip' => '1.2.3.4' ), array( 'webuzo_url' => 'https://evil.test:2005' ), array( 'webuzo_api_key' => 'X' ), array( 'nameservers' => 'ns1.evil.test' ), array( 'provision_enabled' => true ), array( 'own_domain_auto' => 1 ), array( 'enabled' => true ), array( 'grace_days' => 1 ) ) as $bad ) {
	$e = $H::mcp_update( $bad + array( 'replace_hosting' => true ) );
	check( is_wp_error( $e ) && 'digitalisimo_hosting_read_only' === $e->code && serialize( opt() ) === $snap, 'Por MCP no se escribe «' . key( $bad ) . '» y no se guarda nada del lote' );
}
foreach ( array( 'domain_products' => ".com|20", 'hosting_product_ids' => '14693', 'hosting_category_ids' => '63', 'finder_upsell_ids' => '14693' ) as $k => $v ) {
	$e = $H::mcp_update( array( $k => $v, 'replace_hosting' => true ) );
	check( is_wp_error( $e ) && 'digitalisimo_hosting_removed_key' === $e->code && false !== strpos( $e->message, 'Ya no existe' ) && serialize( opt() ) === $snap, "«{$k}» ya no existe: se explica qué usar y no se guarda nada" );
}
$e = $H::mcp_update( array( 'domain_productos' => '.com|1' ) );
check( is_wp_error( $e ) && 'digitalisimo_hosting_unknown_key' === $e->code && serialize( opt() ) === $snap, 'Una clave desconocida no guarda nada' );
$e = $H::mcp_update( array( 'simplify_checkout' => 'quizá', 'registrar_by_tld' => array( '.com' => 'dynadot' ) ) );
check( is_wp_error( $e ) && serialize( opt() ) === $snap, 'Un booleano inválido no guarda nada' );
$e = $H::mcp_update( array( 'pricing_rounding' => '7' ) );
check( is_wp_error( $e ) && serialize( opt() ) === $snap, 'Un redondeo fuera de la lista no guarda nada' );
foreach ( array( array( 'registrar_by_tld' => '.mx|dynadot' ), array( 'finder_requirements' => ".mx|Requisito" ), array( 'finder_order' => '.com, .mx' ), array( 'finder_order' => array( '.com', '.zzzz' ) ), array( 'finder_upsell' => '14693, 14696' ), array( 'finder_upsell' => array( 20 ) ) ) as $bad ) {
	$e = $H::mcp_update( $bad );
	check( is_wp_error( $e ) && 'digitalisimo_hosting_invalid_value' === $e->code && serialize( opt() ) === $snap, 'Formato viejo, extensión desconocida o producto que no es de hosting: no guarda nada · ' . json_encode( $bad ) );
}
check( is_wp_error( $H::mcp_update( array() ) ) && is_wp_error( $H::mcp_update( 'replace_hosting=1' ) ), 'Sin data (o data que no es un objeto) no hace nada' );
$r = $H::mcp_update( array( 'finder_upsell' => array( 14696, array( 'product_id' => 14693 ) ), 'finder_order' => array( 'mx', '.COM' ), 'finder_requirements' => array( '.mx' => '', '.com' => 'Sin requisitos especiales' ) ) );
check( ! is_wp_error( $r ) && array( 14696, 14693 ) === opt()['finder_upsell'] && array( '.mx', '.com' ) === opt()['finder_order'] && array( '.com.mx' => opt()['finder_requirements']['.com.mx'], '.com' => 'Sin requisitos especiales' ) === opt()['finder_requirements'], 'Las listas por MCP: planes por producto, orden normalizado y avisos combinados por extensión ("" quita uno)' );
check( array( array( 'product_id' => 14696, 'name' => 'Web Hosting Pro' ), array( 'product_id' => 14693, 'name' => 'Web Hosting Starter' ) ) === $r['settings']['finder_upsell'], '«Se compran juntos» sale con el nombre de cada plan, no como IDs sueltos' );
$snap = serialize( opt() );

// ── 5. Permiso y multisitio ───────────────────────────────────────────────────────────────────────────────────
$GLOBALS['caps'] = array( 'edit_posts', 'manage_woocommerce' );
$e = $H::mcp_update( array( 'replace_hosting' => true ) );
check( is_wp_error( $e ) && 'digitalisimo_hosting_forbidden' === $e->code && serialize( opt() ) === $snap, 'Sin manage_options, update se rechaza (aunque administre la tienda)' );
unset( $GLOBALS['caps'] );
$GLOBALS['multisite'] = true; $GLOBALS['site_options'] = array( 'digitalisimo_hosting_options' => array( 'api_user' => 'red', 'username' => 'red', 'api_key' => 'CLAVE-RED', 'client_ip' => '198.51.100.4', 'registrar_by_tld' => array( '.com' => 'dynadot' ) ) );
$o = opt(); $o['inherit_network'] = 1; update_option( 'digitalisimo_hosting_options', $o );
$network_before = serialize( $GLOBALS['site_options'] );
$r = $H::mcp_update( array( 'registrar_by_tld' => array( '.mx' => 'namecheap' ), 'replace_hosting' => true ) );
check( is_array( $r ) && array( '.mx' => 'namecheap' ) === opt()['registrar_by_tld'] && serialize( $GLOBALS['site_options'] ) === $network_before, 'En una red, update escribe la opción del sitio y nunca la de red' );
check( 1 === opt()['inherit_network'], 'En una red, update no le quita al sitio «Heredar de la red» (no llegó)' );
check( 'network' === $r['namecheap']['source'] && false === strpos( wp_json_encode( $r ), 'CLAVE-RED' ) && false === strpos( wp_json_encode( $r ), '198.51.100.4' ), 'Con «Heredar», get dice que la cuenta es de la red sin mostrarla' );
$GLOBALS['site_options']['digitalisimo_hosting_options'] += array( 'webuzo_url' => 'https://panel-de-la-red.test:2005', 'nameservers' => 'ns1.red.test, ns2.red.test' );
$g = $H::mcp_settings();
check( null === $g['settings']['webuzo_url'] && null === $g['settings']['nameservers'] && false === strpos( wp_json_encode( $g ), 'panel-de-la-red' ) && in_array( 'webuzo_url', $g['inherited'], true ), 'Con «Heredar», el servidor Webuzo y los nameservers de la red no se muestran (como en la pantalla)' );
$GLOBALS['multisite'] = false; $GLOBALS['site_options'] = array();

// ── 6. Lo detectado ───────────────────────────────────────────────────────────────────────────────────────────
reset_site();
digi_store_settings();
digi_product( 30, 'Dominio .net', 280 ); digi_product( 31, 'Dominio .net (borrador)', 500 ); digi_product( 32, 'Dominio .com otro', 310 );
digi_domain( 31, '.net' ); $GLOBALS['product_status'][31] = 'draft';
// Un producto marcado antes con la meta sola (sin tipo) sigue reconociéndose.
update_post_meta( 30, '_digitalisimo_tld', '.NET' );
check( array( '.com' => 20, '.mx' => 21, '.net' => 30 ) === $H::domain_products(), 'Los dominios marcados se detectan (también la marca vieja sola); un borrador no vende' );
digi_domain( 32, '.com' );
$c = $PR::catalog();
check( 20 === $H::domain_products()['.com'] && 1 === count( $c['duplicates'] ) && array( 20, 32 ) === $c['duplicates'][0]['ids'] && '.com' === $c['duplicates'][0]['tld'], 'Dos productos para .com (registro): se usa el primero y queda el aviso de repetido' );
check( preg_match( '/Ya hay otro producto para \.com \(registro\): «Dominio \.com»/', Digitalisimo_Hosting_Product_Editor::fields( 32, 'x', false ) ), 'El editor del producto repetido lo dice' );
ob_start(); $PR::settings_table(); $t = ob_get_clean();
check( false !== strpos( $t, 'Repetido' ) && false !== strpos( $t, 'Dominio .com otro' ) && false !== strpos( $t, '(repetido: no se usa)' ) && false === strpos( $t, '<textarea' ), 'Los ajustes muestran lo detectado y el repetido, sin campo de texto' );
update_post_meta( 32, '_digitalisimo_hosting_type', 'none' );

// ── 7. Marcar productos ───────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['caps'] = array( 'edit_posts' );
$e = $PR::mark( 32, '.mx' );
check( is_wp_error( $e ) && 'digitalisimo_hosting_forbidden' === $e->code && 'none' === get_post_meta( 32, '_digitalisimo_hosting_type', true ), 'Sin manage_woocommerce ni manage_options no se marca nada' );
$GLOBALS['caps'] = array( 'manage_woocommerce' );
update_post_meta( 21, '_digitalisimo_hosting_type', 'none' );
$r = $PR::mark( 32, 'mx' );
$found = array_values( array_filter( $r['domains'], function ( $d ) { return '.mx' === $d['tld']; } ) );
check( is_array( $r ) && '.mx' === get_post_meta( 32, '_digitalisimo_tld', true ) && 32 === $H::domain_products()['.mx'] && 'register' === $r['product']['kind'] && 'domain' === $r['product']['type'], 'Con manage_woocommerce se marca (y «mx» se guarda como «.mx»); el buscador ya lo usa' );
check( $found && 32 === $found[0]['product_id'] && false === strpos( wp_json_encode( $r ), '|' ), 'mark devuelve lo detectado, sin «|»' );
$e = $PR::mark( 32, '.m x!' );
check( is_wp_error( $e ) && 'digitalisimo_hosting_tld' === $e->code && '.mx' === get_post_meta( 32, '_digitalisimo_tld', true ), 'Una extensión inválida no cambia nada' );
$e = $PR::mark( 32, '.zzzz' );
check( is_wp_error( $e ) && 'digitalisimo_hosting_tld' === $e->code, 'Una extensión que los registradores no venden no se acepta (se valida contra su lista)' );
check( is_wp_error( $PR::mark( 999, '.com' ) ), 'Un producto que no existe no se marca' );
unset( $GLOBALS['caps'] );
digi_product( 40, 'Transferencia de dominio', 250 );
$r = $PR::mark( 40, '*', 'transfer' );
check( is_array( $r ) && '*' === $PR::transfer_ext( 40 ) && ! in_array( 40, $H::domain_products(), true ) && 'transfer' === $r['product']['kind'], 'Un producto de transferencia (cualquier extensión) no entra en los de registro' );
$PR::mark( 32, '' );
check( '' === get_post_meta( 32, '_digitalisimo_tld', true ) && 'none' === get_post_meta( 32, '_digitalisimo_hosting_type', true ) && ! isset( $H::domain_products()['.mx'] ), 'Quitar la marca deja el producto en «Ninguno» y lo detectado se actualiza' );
update_post_meta( 21, '_digitalisimo_hosting_type', 'domain' );

// ── 8. Aviso del escritorio ───────────────────────────────────────────────────────────────────────────────────
$GLOBALS['pagenow'] = 'index.php';
digi_domain( 32, '.mx' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( false !== strpos( $n, 'is-dismissible' ) && false !== strpos( $n, 'más de un producto para .mx' ), 'Escritorio: aviso con la X por el repetido' );
update_post_meta( 32, '_digitalisimo_hosting_type', 'none' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Sin repetidos y con dominios no hay aviso' );
foreach ( array( 20, 21, 30, 40 ) as $id ) update_post_meta( $id, '_digitalisimo_hosting_type', 'none' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( false !== strpos( $n, 'ningún producto está marcado como «Dominio»' ), 'Escritorio: un sitio que vende hosting sin productos de dominio recibe el aviso' );
foreach ( array( 14693, 14696 ) as $id ) update_post_meta( $id, '_digitalisimo_hosting_type', 'none' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Un sitio que no vende dominios ni hosting no recibe el aviso' );
$GLOBALS['pagenow'] = 'edit.php'; $_GET = array( 'post_type' => 'post' );
foreach ( array( 14693, 14696 ) as $id ) update_post_meta( $id, '_digitalisimo_hosting_type', 'hosting' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Fuera del escritorio, Plugins, productos y Hosting no se pinta' );
$GLOBALS['pagenow'] = 'index.php'; $GLOBALS['caps'] = array( 'edit_posts' );
ob_start(); $PR::admin_notice(); $n = ob_get_clean();
check( '' === $n, 'Quien no administra el sitio no ve el aviso' );
unset( $GLOBALS['caps'] );

echo "Hosting por MCP y productos detectados: {$GLOBALS['checks']} comprobaciones correctas.\n";
