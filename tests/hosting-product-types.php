<?php
// Productos de dominio y de hosting sin IDs (pedido del propietario del 2026-11-27: «¿por qué usamos IDs de productos de
// hosting? debe ser un listado desplegable de lo que existe o detectarlos … En el editor de producto, en los tipos
// variable y simple, el plugin hosting debe agregar las opciones si es dominio o hosting»), sin WordPress:
//   1. el editor de producto marca un producto simple como Dominio .com y uno variable como Dominio .mx (una variación
//      hereda .mx, otra es .com.mx): el buscador, el carrito y la venta automática los detectan, sin IDs en los ajustes;
//   2. dos productos con la misma extensión y acción: aviso en el editor, en los ajustes y en lo que devuelve el MCP;
//   3. el bloque del editor: desplegable de extensiones con buscador (nunca texto libre), Registro/Transferencia, el
//      dominio siempre anual; hosting con el paquete de Webuzo (traído del servidor) y el periodo; lo que no está en la
//      lista no se guarda;
//   4. sin conexión (registrador o Webuzo caídos, o sin cuenta): la lista guardada o la base, con un aviso llano;
//   5. la pantalla de ajustes: lo detectado, una fila por extensión (registrador y requisitos), el orden con «Subir» y
//      «Bajar», «Se compran juntos» como desplegable múltiple; ningún campo de IDs ni con «|»;
//   6. el MCP no expone IDs crudos ni «|».
// Uso: php tests/hosting-product-types.php
require __DIR__ . '/fixtures/hosting-wp.php';
$H = 'Digitalisimo_Hosting'; $PR = 'Digitalisimo_Hosting_Products'; $E = 'Digitalisimo_Hosting_Product_Editor'; $L = 'Digitalisimo_Hosting_Lists';
$P = 'Digitalisimo_Hosting_Provisioning'; $C = 'Digitalisimo_Hosting_Checkout'; $F = 'Digitalisimo_Hosting_Finder';
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
function wc_add_notice( $m, $t = 'success' ) { $GLOBALS['notices'][] = array( $t, $m ); }
function get_woocommerce_currency() { return 'MXN'; }
function last_list() { foreach ( array_reverse( $GLOBALS['nc']['calls'] ) as $c ) if ( 'namecheap.domains.check' === $c['cmd'] ) return explode( ',', $c['fields']['DomainList'] ); return array(); }
function fresh_lists() { delete_option( Digitalisimo_Hosting_Lists::TLDS ); delete_option( Digitalisimo_Hosting_Lists::PLANS ); delete_transient( 'digitalisimo_hosting_nc_tlds' ); Digitalisimo_Hosting_Lists::flush(); }

digi_servers_reset();
digi_store_settings();
// La tienda empieza sin productos de dominio marcados.
foreach ( array( 20, 21 ) as $id ) update_post_meta( $id, '_digitalisimo_hosting_type', 'none' );
check( array() === $H::domain_products(), 'Sin productos marcados no hay dominios a la venta' );

// ── 1. Marcar en el editor: simple .com y variable .mx ────────────────────────────────────────────────────────
digi_product( 60, 'Dominio punto com', 290 );
digi_variable( 61, 'Dominios de México', array( 62 => array( '.mx', 650 ), 63 => array( '.com.mx', 450 ) ) );
check( array() === $E::apply( 60, array( 'type' => 'domain', 'tld' => '.com', 'action' => 'register' ), false ), 'El editor marca el producto simple como Dominio .com (registro)' );
check( array() === $E::apply( 61, array( 'type' => 'domain', 'tld' => '.mx', 'action' => 'register' ), false ), 'El editor marca el producto variable como Dominio .mx' );
check( array() === $E::apply( 63, array( 'type' => 'inherit', 'tld' => '.com.mx', 'action' => '' ), true ) && array() === $E::apply( 62, array( 'type' => 'inherit', 'tld' => '', 'action' => '' ), true ), 'Una variación hereda .mx del producto y otra es .com.mx' );
check( array( '.com' => 60, '.mx' => 62, '.com.mx' => 63 ) === $H::domain_products(), 'Se detectan solos: .com (simple), .mx y .com.mx (variaciones), sin IDs en los ajustes' );
check( ! array_key_exists( 'domain_products', get_option( 'digitalisimo_hosting_options' ) ) && ! array_key_exists( 'hosting_product_ids', get_option( 'digitalisimo_hosting_options' ) ), 'Los ajustes no guardan IDs de productos' );
check( 'register' === $C::kind( 62 ) && '.mx' === $PR::register_ext( 62 ) && '.com.mx' === $PR::register_ext( 63 ), 'Cada variación es un dominio de su extensión' );
$r = $F::search( 'minegocio' );
$sold = array_column( array_merge( array( $r['main'] ), $r['rows'] ), 'product_id', 'ext' );
check( array( '.com' => 60, '.mx' => 62, '.com.mx' => 63 ) === $sold && array( 'minegocio.com', 'minegocio.mx', 'minegocio.com.mx' ) === array_slice( last_list(), 0, 3 ), 'El buscador consulta y ofrece .com, .mx y .com.mx con su producto (la variación, para el carrito)' );
check( '$650' === $r['rows'][0]['price_text'], 'El precio sale de la variación' );
check( '' === $C::line_error( 62, array( 'digitalisimo_domain' => 'minegocio.mx' ), false ) && false !== strpos( $C::line_error( 62, array( 'digitalisimo_domain' => 'minegocio.com' ), false ), 'no es .mx' ), 'El carrito compara la extensión exacta de la variación' );
// La Store API con la variación (como la manda el buscador).
$threw = null;
try { $C::store_api_validate( wc_get_product( 62 ), array( 'cart_item_data' => array(), 'quantity' => 1 ) ); } catch ( Exception $e ) { $threw = $e->getMessage(); }
check( null !== $threw && false !== strpos( $threw, 'Busca primero el nombre' ), 'Store API: una variación de dominio sin nombre no entra al carrito' );
// La venta automática registra el dominio de la variación.
$o = digi_order( array( new Digi_Test_Item( 62, 'Dominios de México - .mx', 1, array( 'Dominio' => 'variacion.mx' ) ) ) );
$o->update_status( 'processing' ); digi_run_queue();
$s = (int) $o->get_meta( '_digitalisimo_service' );
check( 'completed' === $o->get_status() && isset( $GLOBALS['nc']['domains']['variacion.mx'] ) && 62 === (int) $P::svc( $s, 'domain_product' ), 'La venta automática registra el .mx comprado con la variación y la guarda en el servicio' );

// ── 2. Dos productos con la misma extensión y acción ──────────────────────────────────────────────────────────
digi_product( 64, 'Otro dominio .com', 310 );
$errs = $E::apply( 64, array( 'type' => 'domain', 'tld' => '.com', 'action' => 'register' ), false );
check( 1 === count( $errs ) && false !== strpos( $errs[0], 'Ya hay otro producto para .com (registro)' ), 'Guardar un segundo .com de registro avisa en el editor' );
check( 60 === $H::domain_products()['.com'] && array( 60, 64 ) === $PR::catalog()['duplicates'][0]['ids'], 'Se usa el primero y queda anotado el repetido' );
check( preg_match( '/role="alert">Ya hay otro producto para \.com \(registro\): «Dominio punto com»/', $E::fields( 64, 'digitalisimo_hosting', false ) ), 'El bloque del editor muestra el error del repetido' );
$g = $H::mcp_settings();
check( 1 === count( $g['duplicates'] ) && '.com' === $g['duplicates'][0]['tld'] && array( 60, 64 ) === array_column( $g['duplicates'][0]['products'], 'product_id' ), 'El MCP también lo avisa' );
check( array() === $E::apply( 64, array( 'type' => 'domain', 'tld' => '.com', 'action' => 'transfer' ), false ) && ! $PR::catalog()['duplicates'], 'La misma extensión con otra acción (transferencia) no es repetido' );
update_post_meta( 64, '_digitalisimo_hosting_type', 'none' );

// ── 3. El bloque del editor ───────────────────────────────────────────────────────────────────────────────────
$html = $E::fields( 60, 'digitalisimo_hosting', false );
check( preg_match( '#<select id="dh-p60-tld" name="digitalisimo_hosting\[tld\]"[^>]*>.*<option value="\.com" selected>\.com</option>#s', $html ) && preg_match( '#<input type="search" id="dh-p60-find"[^>]*aria-controls="dh-p60-tld"#', $html ), 'Dominio: la extensión es un desplegable con buscador' );
check( ! preg_match( '#<input type="text"#', $html ) && false !== strpos( $html, 'siempre por un año' ) && false !== strpos( $html, 'value="transfer"' ) && preg_match( '#<label for="dh-p60-type">Tipo</label><select id="dh-p60-type" name="digitalisimo_hosting\[type\]" data-dh-type><option value="none">Ninguno</option><option value="domain" selected>Dominio</option><option value="hosting">Hosting</option>#', $html ), 'Sin campo de texto: tipo (Ninguno, Dominio, Hosting), Registro o Transferencia y «siempre por un año»' );
foreach ( array( '.com', '.net', '.mx', '.com.mx', '.lat', '.org' ) as $ext ) check( false !== strpos( $html, '<option value="' . $ext . '"' ), "La lista de los registradores trae {$ext}" );
check( false !== strpos( $html, 'name="digitalisimo_hosting[plan]"' ) && false !== strpos( $html, '<option value="digitalisimo_starter">Starter (digitalisimo_starter)</option>' ) && false !== strpos( $html, '<option value="36">Trianual</option>' ) && false !== strpos( $html, '<option value="1">Mensual</option>' ), 'Hosting: el paquete del desplegable de Webuzo (act=plans) y el periodo de mensual a trianual' );
check( 1 === digi_webuzo_count( 'plans' ) && 1 <= digi_nc_count( 'namecheap.domains.getTldList' ), 'Las listas salen de Webuzo y del registrador' );
$E::fields( 63, 'v', true ); $E::fields( 64, 'x', false );
check( 1 === digi_webuzo_count( 'plans' ), 'Y se guardan: abrir otro producto no vuelve a llamar a Webuzo' );
$vhtml = $E::fields( 62, 'digitalisimo_hosting_var[0]', true );
check( preg_match( '#name="digitalisimo_hosting_var\[0\]\[type\]" data-dh-type><option value="inherit" selected>Igual que el producto</option>#', $vhtml ) && false !== strpos( $vhtml, 'esta variación es…' ), 'Una variación tiene su propio bloque, con «Igual que el producto»' );
$errs = $E::apply( 60, array( 'type' => 'domain', 'tld' => '.zzzz', 'action' => 'register' ), false );
check( $errs && false !== strpos( $errs[0], 'no está en la lista' ) && '.com' === get_post_meta( 60, '_digitalisimo_tld', true ), 'Una extensión que no está en la lista no se guarda (no hay texto libre)' );
$errs = $E::apply( 60, array( 'type' => 'domain', 'tld' => '*', 'action' => 'register' ), false );
check( $errs && '.com' === get_post_meta( 60, '_digitalisimo_tld', true ), '«Cualquier extensión» sólo vale para una transferencia' );
check( $E::apply( 60, array( 'type' => 'otra' ), false ) && 'domain' === get_post_meta( 60, '_digitalisimo_hosting_type', true ), 'Un tipo desconocido no cambia nada' );
digi_product( 65, 'Correo y hosting', 800 );
check( array() === $E::apply( 65, array( 'type' => 'hosting', 'plan' => 'digitalisimo_pro', 'period' => '6' ), false ) && 'hosting' === $C::kind( 65 ) && 6 === $PR::period( 65 ) && 'digitalisimo_pro' === $PR::plan( 65 ), 'Hosting: paquete y periodo (semestral) guardados' );
$errs = $E::apply( 65, array( 'type' => 'hosting', 'plan' => 'paquete-inventado', 'period' => '5' ), false );
check( 2 === count( $errs ) && 'digitalisimo_pro' === $PR::plan( 65 ) && 6 === $PR::period( 65 ), 'Un paquete que no está en Webuzo y un periodo que no existe no se guardan' );
check( array() === $E::apply( 65, array( 'type' => 'none' ), false ) && '' === $C::kind( 65 ), '«Ninguno» deja de ser hosting' );
// Guardado real: los ganchos de WooCommerce con lo que manda el formulario.
$_POST = array( 'digitalisimo_hosting' => array( 'type' => 'hosting', 'plan' => 'digitalisimo_starter', 'period' => '3' ) );
$E::save_product( wc_get_product( 65 ) );
$_POST = array( 'digitalisimo_hosting_var' => array( 1 => array( 'type' => 'inherit', 'tld' => '.com.mx', 'action' => 'transfer' ) ) );
$E::save_variation( wc_get_product( 63 ), 1 );
$_POST = array();
check( 3 === $PR::period( 65 ) && 'transfer' === $C::kind( 63 ), 'Los ganchos de guardado del producto y de la variación usan el mismo bloque' );
$E::apply( 63, array( 'type' => 'inherit', 'tld' => '.com.mx', 'action' => 'register' ), true );

// ── 4. Sin conexión: lista guardada o base, nunca texto libre ─────────────────────────────────────────────────
fresh_lists();
$GLOBALS['nc']['mode'] = 'auth';
$t = $L::tlds();
$html = $E::fields( 60, 'digitalisimo_hosting', false );
check( 'base' === $t['source'] && in_array( '.com', $t['list'], true ) && in_array( '.mx', $t['list'], true ) && false !== strpos( $html, 'role="status">No pudimos consultar a los registradores ahora' ), 'Registrador caído y nada guardado: la lista base de extensiones comunes, con aviso' );
check( false !== strpos( $html, '<select id="dh-p60-tld"' ) && ! preg_match( '#<input type="text"#', $html ), 'Sin conexión el campo sigue siendo un desplegable' );
check( array() === $E::apply( 60, array( 'type' => 'domain', 'tld' => '.net', 'action' => 'register' ), false ) && '.net' === get_post_meta( 60, '_digitalisimo_tld', true ), 'Sin conexión se puede elegir una de la lista base' );
$E::apply( 60, array( 'type' => 'domain', 'tld' => '.com', 'action' => 'register' ), false );
$GLOBALS['nc']['mode'] = ''; fresh_lists();
check( 'registrar' === $L::tlds()['source'], 'Con el registrador de vuelta, su lista' );
$o = get_option( $L::TLDS ); $o['at'] = time() - 2 * DAY_IN_SECONDS; $o['tried'] = time() - 2 * HOUR_IN_SECONDS; update_option( $L::TLDS, $o );
delete_transient( 'digitalisimo_hosting_nc_tlds' ); $GLOBALS['nc']['mode'] = 'auth'; $L::flush();
$t = $L::tlds();
check( 'cache' === $t['source'] && in_array( '.lat', $t['list'], true ) && false !== strpos( $t['notice'], 'lista guardada' ), 'Registrador caído con una lista guardada: la guardada, con aviso' );
$before = digi_nc_count( 'namecheap.domains.getTldList' ); $L::flush(); $L::tlds();
check( $before === digi_nc_count( 'namecheap.domains.getTldList' ), 'Un fallo no se reintenta en cada pantalla (espera una hora)' );
$GLOBALS['nc']['mode'] = '';
$opt = get_option( 'digitalisimo_hosting_options' ); $saved_key = $opt['api_key']; $opt['api_key'] = ''; update_option( 'digitalisimo_hosting_options', $opt ); fresh_lists();
$t = $L::tlds();
check( 'base' === $t['source'] && false !== strpos( $t['notice'], 'Todavía no hay cuenta de registrador' ), 'Sin cuenta de registrador: la base, con el aviso de que falta la cuenta' );
$opt['api_key'] = $saved_key; update_option( 'digitalisimo_hosting_options', $opt ); fresh_lists();
// Webuzo sin responder (sin red): los paquetes que ya usan los productos, con aviso; la clave no se bloquea.
$GLOBALS['webuzo']['timeout'] = 1;
$p = $L::plans();
$html = $E::fields( 65, 'digitalisimo_hosting', false );
check( 'base' === $p['source'] && isset( $p['list']['digitalisimo_starter'] ) && false !== strpos( $html, 'role="status">No pudimos consultar a Webuzo ahora' ) && false !== strpos( $html, '<select id="dh-p65-plan"' ), 'Webuzo caído: los paquetes en uso, en el desplegable, con aviso' );
check( ! Digitalisimo_Hosting_Webuzo::from_settings()->blocked(), 'Una lista de paquetes que no responde no detiene la venta automática' );
$GLOBALS['webuzo']['timeout'] = 0; fresh_lists();

// ── 5. La pantalla de ajustes ─────────────────────────────────────────────────────────────────────────────────
function settings_html() { ob_start(); Digitalisimo_Hosting::page(); return ob_get_clean(); }
$html = settings_html();
check( false === strpos( $html, '<textarea' ) && false === strpos( $html, 'hosting[domain_products]' ) && false === strpos( $html, 'hosting_product_ids' ) && false === strpos( $html, 'hosting_category_ids' ) && false === strpos( $html, 'finder_upsell_ids' ) && false === strpos( $html, '.com|' ), 'Ajustes: ningún campo de IDs ni con «|»' );
check( preg_match( '#<td><strong>\.com</strong></td><td><a href="[^"]*post\.php\?post=60&amp;action=edit">Dominio punto com</a></td><td>Registro · un año</td><td>\$290</td>#', $html ) && preg_match( '#post\.php\?post=61&amp;action=edit">Dominios de México - \.mx</a>#', $html ), '«Productos por extensión» muestra lo detectado: extensión, producto con enlace, registro y precio' );
check( preg_match( '#<select id="dh-reg-mx" name="hosting\[registrar_by_tld\]\[\.mx\]"><option value="">Predeterminado \(Namecheap\)</option><option value="namecheap">Namecheap</option><option value="dynadot">Dynadot</option></select>#', $html ) && false !== strpos( $html, 'name="hosting[registrar_by_tld][.com.mx]"' ), 'Registrador por extensión: una fila con desplegable por extensión detectada' );
check( false !== strpos( $html, 'name="hosting[finder_requirements][.mx]"' ) && false !== strpos( $html, 'name="hosting[finder_requirements][.com]"' ), 'Requisitos: una fila por extensión detectada' );
check( preg_match( '#<ol class="dh-order-list" data-dh-order[^>]*><li draggable="true" data-ext="\.com">.*?aria-label="Subir \.com">Subir</button>.*?aria-label="Bajar \.com">Bajar</button>#s', $html ) && false !== strpos( $html, 'role="status" aria-live="polite" data-dh-order-status' ), 'Orden de las extensiones: lista ordenable con «Subir» y «Bajar» (y arrastrando), con aviso para lectores de pantalla' );
check( preg_match( '#<select id="dh-upsell" name="hosting\[finder_upsell\]\[\]" multiple#', $html ) && false !== strpos( $html, '>Web Hosting Starter</option>' ), '«Se compran juntos»: desplegable múltiple de los planes de hosting detectados' );
check( false !== strpos( $html, 'Plan' ) && preg_match( '#post\.php\?post=14693&amp;action=edit">Web Hosting Starter</a></td><td>digitalisimo_starter</td><td>Anual</td>#', $html ), '«Compra de hosting» muestra los planes detectados con su paquete y periodo' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'registrar_by_tld' => array( '.mx' => 'dynadot', '.com' => '' ), 'finder_order' => array( '.mx', '.com.mx', '.com' ), 'finder_requirements' => array( '.mx' => 'Datos reales', '.com' => '' ), 'finder_upsell' => array( '', '14696' ) ) );
settings_html(); $_POST = array();
$o = get_option( 'digitalisimo_hosting_options' );
check( array( '.mx' => 'dynadot' ) === $o['registrar_by_tld'] && array( '.mx', '.com.mx', '.com' ) === $o['finder_order'] && array( '.mx' => 'Datos reales' ) === $o['finder_requirements'] && array( 14696 ) === $o['finder_upsell'], 'Guardar la pantalla: filas por extensión, el orden y los planes elegidos' );
check( 'dynadot' === Digitalisimo_Hosting_Registrars::id_for_domain( 'algo.mx' ) && array( '.mx', '.com.mx', '.com' ) === $F::ordered() && 'Datos reales' === $F::requirements()['.mx'] && array( 14696 ) === array_column( $F::together(), 'id' ), 'Lo guardado manda en el buscador y el registrador' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'finder_upsell' => array( '' ) ) );
settings_html(); $_POST = array();
check( array() === get_option( 'digitalisimo_hosting_options' )['finder_upsell'] && in_array( 14693, array_column( $F::together(), 'id' ), true ), 'Sin elegir ninguno: se muestran los planes de hosting detectados' );
// Un plan variable en «Se compran juntos»: su variación anual.
digi_variable( 80, 'Plan Variable', array( 81 => array( 'Mensual', 99, array( '_digitalisimo_hosting_period' => 1 ) ), 82 => array( 'Anual', 990, array( '_digitalisimo_hosting_period' => 12 ) ) ), array( '_digitalisimo_hosting_type' => 'hosting', '_digitalisimo_webuzo_plan' => 'digitalisimo_pro' ) );
$together = $F::together();
check( in_array( 82, array_column( $together, 'id' ), true ) && ! in_array( 81, array_column( $together, 'id' ), true ) && ! in_array( 80, array_column( $together, 'id' ), true ), 'Un plan variable se ofrece con su variación anual (la que se puede agregar al carrito)' );

// ── 6. MCP: sin IDs crudos ni «|» ─────────────────────────────────────────────────────────────────────────────
$g = $H::mcp_settings();
$json = wp_json_encode( $g );
check( false === strpos( $json, '|' ) && ! preg_match( '/"[a-z_]*_ids"/', $json ) && ! preg_match( '/"\s*\d+\s*(,\s*\d+\s*)+"/', $json ), 'El MCP de ajustes no expone «|» ni listas de IDs en texto' );
check( is_object( $g['settings']['registrar_by_tld'] ) && is_array( $g['settings']['finder_order'] ) && is_object( $g['settings']['finder_requirements'] ) && is_array( $g['settings']['finder_upsell'] ), 'Las listas por extensión son objetos y listas' );
$names = array(); foreach ( $g['domains'] as $d ) $names[] = $d['tld'] . ':' . $d['name'];
check( in_array( '.mx:Dominios de México - .mx', $names, true ) && in_array( '.com:Dominio punto com', $names, true ), 'El MCP dice qué producto vende cada extensión, por nombre' );
$state = $PR::mark( 64, '.lat' );
check( is_array( $state ) && false === strpos( wp_json_encode( $state ), '|' ) && 'anual' === $state['product']['billing'], 'La herramienta de productos de dominio usa la forma nueva (tipo, extensión, venta, siempre anual)' );

echo "Hosting, productos de dominio y hosting sin IDs: {$GLOBALS['checks']} comprobaciones correctas.\n";
