<?php
// Precio de los dominios en tiempo real (agregado del propietario del 2026-11-24), sin WordPress y con Namecheap, Banxico
// y Frankfurter simulados por pre_http_request (pruebas deterministas: nada sale a internet):
//   1. los números del .mx (registro 23.98, renovación 58.98 USD): costo base = el mayor, FIX de Banxico con token en la
//      cabecera, colchón 3 %, margen 25 %, comisión sobre el total con IVA y redondeo hacia arriba al múltiplo de 10;
//      el precio del producto cambia y el margen se cumple tras la comisión;
//   2. redondeo a múltiplo de 9, terminado en 9 y sin redondeo; nunca por debajo de costo + margen;
//   3. «YourAdditonalCost» (así lo escribe Namecheap) y sus otras formas;
//   4. falla Namecheap → ningún precio cambia, aviso e historial; falla Banxico → respaldo Frankfurter; fallan las dos
//      → ningún cambio;
//   5. un dominio pagado cuyo costo real subió no se registra (ni se renueva): en espera con nota y aviso;
//   6. historial y «Actualizar precios ahora» en la pantalla de servicios; la consulta diaria va en Action Scheduler.
require __DIR__ . '/fixtures/hosting-wp.php';
$PR = 'Digitalisimo_Hosting_Pricing';
$P  = 'Digitalisimo_Hosting_Provisioning';
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function digi_pricing( array $extra = array() ) {
	digi_store_settings( $extra + array( 'pricing_enabled' => 1, 'banxico_token' => 'TOKEN-BANXICO', 'pricing_cushion' => 3, 'pricing_margin' => 25, 'pricing_fee' => 3.6, 'pricing_vat' => 16, 'pricing_min_margin' => 10, 'pricing_rounding' => '10' ) );
}
$GLOBALS['nc']['price'] = array( 'com' => array( 'REGISTER' => 11.28, 'RENEW' => 15.98, 'extra' => 0.18 ), 'mx' => array( 'REGISTER' => 23.98, 'RENEW' => 58.98, 'extra' => 0 ) );

// ── 1. Los números del .mx ──────────────────────────────────────────────────────────────────────────────────
digi_pricing();
$e = $PR::run();
check( 'ok' === $e['status'] && 18.5 === $e['fx'] && 'Banxico FIX 21/11/2026' === $e['fx_source'], 'El tipo de cambio es el FIX de Banxico (serie SF43718)' );
check( 'TOKEN-BANXICO' === $GLOBALS['fx']['calls'][0]['token'] && 1 === count( $GLOBALS['fx']['calls'] ), 'El token de Banxico va en la cabecera Bmx-Token y no hace falta el respaldo' );
$mx = $e['rows'][1];
check( '.mx' === $mx['ext'] && 23.98 === $mx['register_usd'] && 58.98 === $mx['renew_usd'] && 58.98 === $mx['base_usd'], '.mx: el costo base es el mayor entre registro (23.98) y renovación (58.98)' );
// 58.98 × 18.5 × 1.03 = 1123.86; × 1.25 = 1404.83; ÷ (1 − 0.036 × 1.16) = 1466.05 → 1470.
check( 1123.86 === $mx['cost'] && 1404.83 === $mx['floor'] && 1466.05 === $mx['raw'] && 1470 === $mx['price'], '.mx: costo 1123.86 MXN, piso 1404.83, 1466.05 antes de redondear y 1470 publicado' );
check( '1470' === wc_get_product( 21 )->get_regular_price() && '600' === $mx['old'], 'El producto de .mx pasa de 600 a 1470 (sin IVA)' );
$net = 1470 * ( 1 - 0.036 * 1.16 );
check( $net >= 1123.8639 * 1.25, 'Tras la comisión sobre el total con IVA, queda al menos costo + margen' );
$com = $e['rows'][0];
check( 16.16 === round( $com['base_usd'], 2 ) && 11.46 === round( $com['register_usd'], 2 ) && 16.16 === round( $com['renew_usd'], 2 ) && (string) $com['price'] === wc_get_product( 20 )->get_regular_price(), '.com: registro y renovación con el cargo ICANN (YourAdditonalCost), precio publicado' );
check( 0.03 === $e['cushion'] && 0.25 === $e['margin'] && 0.036 === $e['fee'] && 0.16 === $e['vat'], 'El historial guarda colchón, margen, comisión e IVA' );
check( false === strpos( json_encode( get_option( $PR::HISTORY ) ), 'TOKEN-BANXICO' ), 'El historial no guarda el token' );

// ── 2. Redondeo y piso ──────────────────────────────────────────────────────────────────────────────────────
check( 1467 === $PR::round_up( 1466.05, '9' ) && 1469 === $PR::round_up( 1466.05, 'end9' ) && 1470 === $PR::round_up( 1466.05, '10' ) && 1467 === $PR::round_up( 1466.05, 'none' ), 'Hacia arriba: múltiplo de 9 (1467), terminado en 9 (1469), múltiplo de 10 (1470), al peso (1467)' );
check( 1470 === $PR::round_up( 1470, '10' ) && 1469 === $PR::round_up( 1469, 'end9' ) && 1479 === $PR::round_up( 1469.01, 'end9' ), 'Un precio que ya cumple no sube; uno que se pasa por centavos sí' );
digi_pricing( array( 'pricing_rounding' => '9' ) ); $PR::run();
check( '1467' === wc_get_product( 21 )->get_regular_price(), 'Con «múltiplo de 9» el .mx queda en 1467' );
digi_pricing( array( 'pricing_rounding' => 'end9' ) ); $PR::run();
check( '1469' === wc_get_product( 21 )->get_regular_price(), 'Con «terminado en 9» el .mx queda en 1469' );
foreach ( array( array( 0, 0 ), array( 0.5, 0.1 ), array( 3.6, 16 ) ) as $c ) {
	$r = $PR::sell_price( 58.98, 18.5, array( 'cushion' => 0.03, 'margin' => 0.25, 'fee' => $c[0] / 100, 'vat' => $c[1] / 100, 'rounding' => 'none' ) );
	check( $r['price'] >= $r['floor'] && $r['price'] >= 1404.83, 'Nunca por debajo de costo + margen (comisión ' . $c[0] . ' %)' );
}
// Una oferta por debajo del nuevo precio se retira.
wc_get_product( 20 )->sale = '99';
digi_pricing(); $e = $PR::run();
check( '' === wc_get_product( 20 )->get_sale_price() && ! empty( $e['rows'][0]['sale_removed'] ), 'Una oferta por debajo del precio nuevo se retira (vendería con pérdida)' );

// ── 3. El cargo ICANN con sus tres nombres ──────────────────────────────────────────────────────────────────
foreach ( array( 'YourAdditonalCost', 'YourAdditionalCost', 'AdditionalCost' ) as $attr ) {
	$GLOBALS['nc']['extra_attr'] = $attr; $GLOBALS['nc']['calls'] = array();
	$r = Digitalisimo_Hosting_Namecheap::from_settings()->price( 'com', 'RENEW' );
	check( 'ok' === $r['kind'] && abs( $r['value'] - 16.16 ) < 0.001, "El cargo ICANN se lee de «{$attr}»" );
	check( 'DOMAIN' === $GLOBALS['nc']['calls'][0]['fields']['ProductType'] && 'RENEW' === $GLOBALS['nc']['calls'][0]['fields']['ActionName'] && 'com' === $GLOBALS['nc']['calls'][0]['fields']['ProductName'], 'getPricing con ProductType=DOMAIN, ActionName y ProductName' );
}
unset( $GLOBALS['nc']['extra_attr'] );

// ── 4. Fallas ───────────────────────────────────────────────────────────────────────────────────────────────
$before = array( wc_get_product( 20 )->get_regular_price(), wc_get_product( 21 )->get_regular_price() );
$GLOBALS['mails'] = array();
$GLOBALS['nc']['mode'] = 'auth';
$e = $PR::run(); $GLOBALS['nc']['mode'] = '';
check( 'error' === $e['status'] && false !== strpos( $e['message'], 'Namecheap' ) && $before === array( wc_get_product( 20 )->get_regular_price(), wc_get_product( 21 )->get_regular_price() ), 'Falla Namecheap: ningún precio cambia' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ) && false !== strpos( digi_mails_to( 'admin@tienda.test' )[0]['body'], 'No se cambió ningún precio' ), 'Y se avisa al administrador' );
// Sólo falla la renovación del .mx (la última consulta): tampoco cambia nada, ni el .com que sí respondió.
$GLOBALS['nc']['price']['mx']['RENEW'] = 70;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { return ( 0 === strpos( $url, 'https://api.namecheap.com' ) && 'mx' === ( $args['body']['ProductName'] ?? '' ) && 'RENEW' === ( $args['body']['ActionName'] ?? '' ) && ! empty( $GLOBALS['fail_mx_renew'] ) ) ? new WP_Error( 'x', 'cURL error 28' ) : $pre; }, 5, 3 );
$GLOBALS['fail_mx_renew'] = true;
$e = $PR::run(); $GLOBALS['fail_mx_renew'] = false;
check( 'error' === $e['status'] && $before === array( wc_get_product( 20 )->get_regular_price(), wc_get_product( 21 )->get_regular_price() ), 'Una sola consulta fallida (renovación de .mx) deja todos los precios como estaban' );
$GLOBALS['nc']['price']['mx']['RENEW'] = 58.98;
// Banxico caído: respaldo.
$GLOBALS['fx']['banxico'] = 'down'; $GLOBALS['fx']['calls'] = array();
$e = $PR::run();
check( 'ok' === $e['status'] && 18.6 === $e['fx'] && 0 === strpos( $e['fx_source'], 'Frankfurter 2026-11-21 (respaldo: Banxico no respondió' ), 'Banxico caído: se usa Frankfurter y el historial lo dice' );
check( array( 'banxico', 'frankfurter' ) === array_column( $GLOBALS['fx']['calls'], 'src' ), 'Primero Banxico, después el respaldo' );
$GLOBALS['fx']['banxico'] = 'ne';
check( 'ok' === $PR::run()['status'] && 18.6 === $PR::history()[ count( $PR::history() ) - 1 ]['fx'], 'Banxico sin dato del día («N/E»): respaldo' );
$GLOBALS['fx']['banxico'] = 'ok';
digi_pricing( array( 'banxico_token' => '' ) ); $GLOBALS['fx']['calls'] = array();
$e = $PR::run();
check( 'ok' === $e['status'] && array( 'frankfurter' ) === array_column( $GLOBALS['fx']['calls'], 'src' ) && false !== strpos( $e['fx_source'], 'falta el token' ), 'Sin token de Banxico, directo al respaldo' );
digi_pricing();
$before = array( wc_get_product( 20 )->get_regular_price(), wc_get_product( 21 )->get_regular_price() );
$GLOBALS['fx']['banxico'] = 'down'; $GLOBALS['fx']['frankfurter'] = 'down'; $GLOBALS['mails'] = array();
$e = $PR::run();
check( 'error' === $e['status'] && false !== strpos( $e['message'], 'Sin tipo de cambio: Banxico no respondió; Frankfurter no dio USD→MXN' ) && $before === array( wc_get_product( 20 )->get_regular_price(), wc_get_product( 21 )->get_regular_price() ), 'Fallan las dos fuentes: ningún precio cambia' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ), 'Y se avisa al administrador' );
$GLOBALS['fx']['banxico'] = 'ok'; $GLOBALS['fx']['frankfurter'] = 'ok';
digi_pricing( array( 'pricing_enabled' => 0 ) );
check( null === $PR::run(), 'Con los precios automáticos apagados (por defecto) no se consulta nada' );
digi_pricing(); $PR::run();

// ── 5. El costo real subió desde que se cobró ───────────────────────────────────────────────────────────────
$GLOBALS['mails'] = array();
$cheap = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'subio.mx' ), 600 ) ) );
$GLOBALS['nc']['price']['mx']['REGISTER'] = 40; // 40 × 18.5 = 740 MXN; con 10 % hacen falta 814 y 600 deja 574.94
$creates = digi_nc_count( 'namecheap.domains.create' );
$cheap->update_status( 'processing' ); digi_run_queue();
check( $creates === digi_nc_count( 'namecheap.domains.create' ) && 'on-hold' === $cheap->get_status(), 'Costo real por encima de lo cobrado menos el margen mínimo: no se registra y el pedido queda en espera' );
check( false !== strpos( implode( "\n", $cheap->notes ), 'El costo real de registrar subio.mx subió: Namecheap cobra 40.00 USD (740.00 MXN al tipo 18.5000 de Banxico FIX 21/11/2026) y lo cobrado, 600.00 MXN sin IVA, deja 574.94 MXN tras la comisión; con el margen mínimo de 10 % hacen falta 814.00 MXN' ), 'La nota da los números' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ), 'Y se avisa al administrador' );
$ok = digi_order( array( new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'alcanza.mx' ), 1470 ) ) );
$ok->update_status( 'processing' ); digi_run_queue();
check( 'completed' === $ok->get_status() && isset( $GLOBALS['nc']['domains']['alcanza.mx'] ), 'Cobrado al precio vigente (1470), el mismo costo sí se registra' );
// Renovación: el costo de renovar subió por encima de lo que cobra el pedido de renovación.
$GLOBALS['nc']['price']['mx']['REGISTER'] = 23.98; $GLOBALS['nc']['balance'] = 500;
$s = (int) $ok->get_meta( '_digitalisimo_service' );
$P::daily( (int) $P::svc( $s, 'expires' ) - 29 * DAY_IN_SECONDS );
$renewal = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( 1470.0 === (float) $renewal->get_items()[0]->get_total(), 'El pedido de renovación cobra el precio vigente del producto' );
$GLOBALS['nc']['price']['mx']['RENEW'] = 90; $renews = digi_nc_count( 'namecheap.domains.renew' );
$renewal->update_status( 'processing' ); digi_run_queue();
check( $renews === digi_nc_count( 'namecheap.domains.renew' ) && false !== strpos( implode( "\n", $renewal->notes ), 'El costo real de renovar alcanza.mx subió' ), 'Una renovación cuyo costo subió no se hace' );
$GLOBALS['nc']['price']['mx']['RENEW'] = 58.98;
check( true === $P::retry( $s ) && digi_run_queue() && $renews + 1 === digi_nc_count( 'namecheap.domains.renew' ), 'Con el costo de vuelta, «Reintentar» la renueva' );

// ── 6. Historial, botón y programación ──────────────────────────────────────────────────────────────────────
ob_start(); Digitalisimo_Hosting_Services_Page::render(); $html = ob_get_clean();
check( false !== strpos( $html, '<h2>Precios de dominios</h2>' ) && false !== strpos( $html, '18.5000 · Banxico FIX 21/11/2026 · colchón 3 %' ) && false !== strpos( $html, '23.98 / 58.98' ) && false !== strpos( $html, 'Sin cambios:</strong> Sin tipo de cambio' ), 'La pantalla muestra el historial: tipo de cambio y fuente, colchón, costos y las corridas sin cambios' );
check( false !== strpos( $html, 'name="op" value="prices"' ) && false !== strpos( $html, 'value="n:digitalisimo_hosting_service_prices_0"' ), '«Actualizar precios ahora» con su nonce' );
$GLOBALS['queue'] = array();
$_POST = array( 'action' => 'digitalisimo_hosting_service', 'service' => 0, 'op' => 'prices', '_wpnonce' => 'n:digitalisimo_hosting_service_prices_0' );
try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Digi_Test_Redirect $r ) {} $_POST = array();
check( 1 === count( $GLOBALS['queue'] ) && 'digitalisimo_hosting_prices_now' === $GLOBALS['queue'][0]['hook'], 'El botón encola la consulta en Action Scheduler' );
$GLOBALS['caps'] = array( 'manage_options' );
$_POST = array( 'action' => 'digitalisimo_hosting_service', 'service' => 0, 'op' => 'prices', '_wpnonce' => 'n:digitalisimo_hosting_service_prices_0' );
try { Digitalisimo_Hosting_Services_Page::handle(); $denied = false; } catch ( Digi_Test_Die $d ) { $denied = 403 === $d->status; } $_POST = array();
check( $denied, 'Sin manage_woocommerce no se puede' );
$GLOBALS['caps'] = array( 'manage_options', 'manage_woocommerce' );
unset( $GLOBALS['recurring'] );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'pricing_enabled' => 1, 'pricing_margin' => '30,5', 'pricing_fee' => '-4', 'pricing_rounding' => 'raro', 'banxico_token' => '' ) );
ob_start(); Digitalisimo_Hosting::page(); $page = ob_get_clean(); $_POST = array();
$o = get_option( 'digitalisimo_hosting_options' );
check( isset( $GLOBALS['recurring']['digitalisimo_hosting_prices'] ) && DAY_IN_SECONDS === $GLOBALS['recurring']['digitalisimo_hosting_prices'][1], 'Guardar con los precios automáticos encendidos programa la consulta diaria en Action Scheduler' );
check( 30.5 === $o['pricing_margin'] && 0.0 === (float) $o['pricing_fee'] && '10' === $o['pricing_rounding'] && 'TOKEN-BANXICO' === $o['banxico_token'], 'Porcentajes con coma decimal, sin negativos; un redondeo desconocido no se guarda; el token vacío se conserva' );
check( false === strpos( $page, 'TOKEN-BANXICO' ) && preg_match( '#name="hosting\[banxico_token\]" value="••••••••••••"#u', $page ), 'El token de Banxico va enmascarado' );

echo "Hosting, precios de dominios: {$GLOBALS['checks']} comprobaciones correctas.\n";
