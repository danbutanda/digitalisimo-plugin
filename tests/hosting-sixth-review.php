<?php
// Sexta revisión de la venta automática de DIGITALÍSIMO Hosting (2026-11-26), sin WordPress y con Webuzo y Namecheap
// simulados (tests/fixtures/hosting-wp.php). Cada caso falla con el código de la quinta ronda (a4f3cb30):
//   H-1  «Dar de baja» o apagar la venta mientras la tarea ya corre: antes de cada llamada que cobra o crea se relee el
//        servicio y la venta; al terminar no se pisa el estado; si ya se gastó algo, se avisa para reembolsar;
//   H-2  una cuenta ya suspendida en el panel de Webuzo (abuso) no se «suspende» otra vez ni la reactiva el pago;
//   H-3  el servicio viejo de un dominio recontratado no avisa ni cobra; se pide darlo de baja;
//   H-4  botones con la venta o el módulo apagados, aviso de cuenta borrada (una vez), DNS una vez por petición;
//   H-5  la IP de Webuzo se fija para cURL (CURLOPT_RESOLVE, sólo https); sin resolver o sin cURL no sale la clave.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['http'] = array(); $GLOBALS['dns'] = array(); $GLOBALS['trig'] = null;
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' );
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7 ) );
}
function provisioned( $domain, $customer = 7 ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); digi_run_queue();
	return (int) $o->get_meta( '_digitalisimo_service' );
}
function hosting_only( $domain, $customer = 7 ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); digi_run_queue();
	return $o;
}
function setopt( $k, $v ) { $o = get_option( 'digitalisimo_hosting_options' ); $o[ $k ] = $v; update_option( 'digitalisimo_hosting_options', $o ); }
function sale( $on ) { setopt( 'provision_enabled', $on ? 1 : 0 ); }
function admin_mails() { return array_values( array_map( function ( $m ) { return $m['subject']; }, array_filter( $GLOBALS['mails'], function ( $m ) { return 'admin@tienda.test' === $m['to']; } ) ) ); }
function has_mail( $needle ) { foreach ( admin_mails() as $s ) if ( false !== strpos( $s, $needle ) ) return true; return false; }
function count_mail( $needle ) { return count( array_filter( admin_mails(), function ( $s ) use ( $needle ) { return false !== strpos( $s, $needle ); } ) ); }
function suspended_unpaid( $domain ) { $P = 'Digitalisimo_Hosting_Provisioning'; $s = provisioned( $domain ); $exp = (int) $P::svc( $s, 'expires' ); $P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 ); return $s; }
function pay_renewal( $s ) { $P = 'Digitalisimo_Hosting_Provisioning'; $r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' ); return $r; }
function click( $op, $s ) { $_POST = array( 'service' => $s, 'op' => $op, '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( $op, $s ) ); $x = ''; try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Exception $e ) { $x = $e->getMessage(); } $_POST = array(); return $x; }
function wstatus( $user ) { return $GLOBALS['webuzo']['users'][ $user ]['status'] ?? null; }
function later_days( array $days ) { $P = 'Digitalisimo_Hosting_Provisioning'; foreach ( $days as $d ) { $P::daily( time() + $d * DAY_IN_SECONDS ); digi_run_queue( true, 200 ); } }
// Otra petición «en paralelo»: al ver un comando de Namecheap o un act de Webuzo, corre el callback una vez.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$t = $GLOBALS['trig'] ?? null; if ( ! $t ) return $pre;
	$body = (array) ( $args['body'] ?? array() );
	if ( (string) ( $body['Command'] ?? '' ) === $t[0] || false !== strpos( $url, 'act=' . $t[0] ) ) { $GLOBALS['trig'] = null; call_user_func( $t[1] ); }
	return $pre;
}, 5, 3 );

// ── H-1: baja o venta apagada a medio camino ──────────────────────────────────────────────────────────────────
reset_all();
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'carrera.com' ) ) ) ); $o->customer = 7;
$GLOBALS['trig'] = array( 'namecheap.domains.check', function () use ( $o ) { Digitalisimo_Hosting_Provisioning::cancel_service( (int) $o->get_meta( '_digitalisimo_service' ) ); } );
$o->update_status( 'processing' ); digi_run_queue( true, 200 );
$sid = (int) $o->get_meta( '_digitalisimo_service' );
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 0 === digi_webuzo_count( 'add_user' ) && 'cancelled' === $P::svc( $sid, 'status' ), 'H-1 (F3) baja durante el alta: no se registra el dominio, no se crea la cuenta y la baja se conserva' );

reset_all();
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'gasto.com' ) ) ) ); $o->customer = 7;
$GLOBALS['trig'] = array( 'add_user', function () use ( $o ) { Digitalisimo_Hosting_Provisioning::cancel_service( (int) $o->get_meta( '_digitalisimo_service' ) ); } );
$o->update_status( 'processing' ); digi_run_queue( true, 200 );
$sid = (int) $o->get_meta( '_digitalisimo_service' );
check( 'cancelled' === $P::svc( $sid, 'status' ) && has_mail( 'dado de baja con gasto: gasto.com' ), 'H-1 baja durante la última llamada (cuenta creada, dominio registrado): no se pisa la baja y se avisa para reembolsar o cancelar' );
check( false !== strpos( implode( "\n", $o->notes ), 'quedó registrado' ) && false !== strpos( implode( "\n", $o->notes ), 'quedó creada' ), 'H-1 la nota del pedido dice lo que ya se gastó' );

reset_all();
$s = provisioned( 'renueva.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$GLOBALS['trig'] = array( 'namecheap.users.getBalances', function () use ( $s ) { Digitalisimo_Hosting_Provisioning::cancel_service( $s ); } );
$renews = digi_nc_count( 'namecheap.domains.renew' );
pay_renewal( $s ); digi_run_queue( true, 200 );
check( digi_nc_count( 'namecheap.domains.renew' ) === $renews && 'cancelled' === $P::svc( $s, 'status' ) && (int) $P::svc( $s, 'expires' ) === $exp, 'H-1 (F4) baja durante la renovación: no se renueva en Namecheap, no se extiende y la baja se conserva' );

reset_all();
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'apagar.com' ) ) ) ); $o->customer = 7;
$GLOBALS['trig'] = array( 'namecheap.domains.check', function () { sale( false ); } );
$o->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 0 === digi_webuzo_count( 'add_user' ), 'H-1 (H1) venta apagada durante el alta: no se cobra ni se crea nada' );
sale( true ); $P::sweep_renewals(); digi_run_queue( true, 200 );
check( 1 === digi_nc_count( 'namecheap.domains.create' ) && isset( $GLOBALS['webuzo']['users']['apagar'] ) && 'completed' === $o->get_status(), 'H-1 al encender la venta se retoma el alta interrumpida' );

// ── H-2: una suspensión que no es del plugin ──────────────────────────────────────────────────────────────────
reset_all();
$s = provisioned( 'abuso3.com' ); $exp = (int) $P::svc( $s, 'expires' );
$GLOBALS['webuzo']['users']['abuso3']['status'] = 'suspended';
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$sent = $P::svc( $s, 'suspend_sent' );
check( 0 === digi_webuzo_count( 'users', 'suspend', 'abuso3' ) && 'foreign' === ( $sent['reason'] ?? '' ) && 'suspended' === $P::svc( $s, 'status' ), 'H-2 (H2) ya suspendida en el panel: no se manda «suspender» y la marca es «foreign»' );
$GLOBALS['mails'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 ); later_days( array( 1, 2 ) );
check( 'suspended' === wstatus( 'abuso3' ) && 0 === digi_webuzo_count( 'users', 'unsuspend', 'abuso3' ), 'H-2 (F2) el pago no reactiva una suspensión que el plugin no hizo' );

reset_all();
$s = suspended_unpaid( 'vieja.com' );
$GLOBALS['webuzo']['users']['vieja']['status'] = ''; // reactivada a mano en el panel
later_days( array( 1 ) );                             // la revisión diaria lo ve
check( 'active' === $P::svc( $s, 'status' ) && ! $P::svc( $s, 'suspend_sent' ), 'H-2 (F1) una reactivación en el panel invalida la marca «auto» (la revisión diaria la ve)' );
$GLOBALS['webuzo']['users']['vieja']['status'] = 'suspended'; // y suspendida otra vez a mano, por abuso
$GLOBALS['mails'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 );
check( 'suspended' === wstatus( 'vieja' ) && 0 === digi_webuzo_count( 'users', 'unsuspend', 'vieja' ) && has_mail( 'suspendida fuera del plugin: vieja.com' ), 'H-2 (F1) con la marca vieja invalidada, el pago no reactiva la suspensión del panel y se avisa' );

reset_all();
$s = suspended_unpaid( 'motivo.com' );
$GLOBALS['webuzo']['users']['motivo']['suspend_reason'] = 'Abuso de recursos'; // el panel pide un motivo; el plugin nunca escribe uno
$GLOBALS['mails'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 );
check( 'suspended' === wstatus( 'motivo' ) && 0 === digi_webuzo_count( 'users', 'unsuspend', 'motivo' ) && has_mail( 'suspendida fuera del plugin: motivo.com' ), 'H-2 (F1) una suspensión con motivo escrito en Webuzo no es del plugin: el pago no la reactiva' );

reset_all();
$s = suspended_unpaid( 'propia.com' ); $GLOBALS['webuzo']['calls'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 );
check( '' === wstatus( 'propia' ) && 1 === digi_webuzo_count( 'users', 'unsuspend', 'propia' ), 'H-2 la suspensión que hizo el plugin (vio la cuenta activa y la suspendió) sí se reactiva al pagar' );

// ── H-3: el servicio viejo de un dominio recontratado ─────────────────────────────────────────────────────────
reset_all();
$a = provisioned( 'doble.com', 7 ); $exp = (int) $P::svc( $a, 'expires' );
unset( $GLOBALS['webuzo']['users']['doble'] );
$ob = hosting_only( 'doble.com', 7 ); $b = (int) $ob->get_meta( '_digitalisimo_service' );
$P::svc_set( $b, array( 'expires' => $exp + 300 * DAY_IN_SECONDS ) ); $GLOBALS['mails'] = array();
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp - 20 * DAY_IN_SECONDS ); $P::daily( $exp - 6 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$to_client = array_filter( $GLOBALS['mails'], function ( $m ) { return 'admin@tienda.test' !== $m['to']; } );
check( ! (int) $P::svc( $a, 'renewal_order' ) && ! $to_client, 'H-3 (F8) el servicio viejo no crea pedido de renovación ni avisa al cliente' );
check( 1 === count_mail( 'servicio viejo de doble.com' ), 'H-3 se pide al administrador darlo de baja, una sola vez' );

// ── H-4: menores ──────────────────────────────────────────────────────────────────────────────────────────────
reset_all( array( 'own_domain_auto' => 0 ) );
$o = hosting_only( 'propio.com', 9 ); $sid = (int) $o->get_meta( '_digitalisimo_service' );
sale( false );
$x = click( 'approve', $sid ); digi_run_queue( true, 200 );
check( false !== strpos( $x, 'done=approve_off' ) && 0 === digi_webuzo_count( 'add_user' ), 'H-4 (F5) «Aprobar» con la venta apagada no dice «en cola» y no crea nada' );
$x = click( 'retry', $sid );
check( false !== strpos( $x, 'done=retry_off' ), 'H-4 (F5) «Reintentar» con la venta apagada dice que se retoma al encenderla' );
sale( true ); $P::sweep_renewals(); digi_run_queue( true, 200 );
check( 1 === digi_webuzo_count( 'add_user' ) && 'active' === $P::svc( $sid, 'status' ), 'H-4 (F5) al encender la venta se retoma lo aprobado' );

reset_all();
$s = provisioned( 'modulo.com' ); setopt( 'enabled', 0 );
$x = click( 'suspend', $s ); digi_run_queue( true, 200 );
check( false !== strpos( $x, 'done=off' ) && '' === (string) $P::svc( $s, 'suspend_reason' ) && '' === wstatus( 'modulo' ), 'H-4 (F6) con el módulo apagado «Suspender» no hace nada ni guarda el motivo' );

reset_all();
$s = suspended_unpaid( 'borrada.com' );
unset( $GLOBALS['webuzo']['users']['borrada'] ); $GLOBALS['mails'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 ); later_days( array( 1, 2, 3 ) );
check( false !== strpos( (string) $P::svc( $s, 'error' ), 'ya no existe en Webuzo' ) && false === strpos( (string) $P::svc( $s, 'error' ), ': .' ), 'H-4 (F7) cuenta borrada: el motivo es claro', $P::svc( $s, 'error' ) );
check( 1 === count_mail( 'pagado y sin reactivar: borrada.com' ), 'H-4 (F7) y se avisa una sola vez, no cada día' );

reset_all();
$GLOBALS['dns_calls'] = 0;
add_filter( 'digitalisimo_hosting_webuzo_host_ips', function ( $ips, $host ) { if ( 'lento.test' === $host ) $GLOBALS['dns_calls']++; return $ips; }, 5, 2 );
$W::safe_url( 'https://lento.test:2005' ); $W::safe_url( 'https://lento.test:2005' ); $W::pin( 'https://lento.test:2005' );
check( 1 === $GLOBALS['dns_calls'], 'H-4 el DNS se consulta una vez por petición' );

// ── H-5: la IP de la llamada se fija ──────────────────────────────────────────────────────────────────────────
reset_all();
$GLOBALS['dns']['panel.ejemplo.com'] = array( '93.184.216.34' ); $GLOBALS['dns']['v6.ejemplo.com'] = array( '2606:4700::1111' );
$pin = $W::pin( 'https://panel.ejemplo.com:2005' );
check( 'panel.ejemplo.com:2005:93.184.216.34' === $W::resolve_entry( $pin ), 'H-5 CURLOPT_RESOLVE fija el nombre a la IP validada (host:puerto:ip)' );
check( 'v6.ejemplo.com:443:[2606:4700::1111]' === $W::resolve_entry( $W::pin( 'https://v6.ejemplo.com' ) ), 'H-5 IPv6 entre corchetes y puerto 443 por omisión' );
check( '' === $W::resolve_entry( $W::pin( 'https://199.85.208.102:2005' ) ), 'H-5 con la IP en la URL no hace falta fijarla' );
if ( defined( 'CURLOPT_PROTOCOLS' ) ) { $c = $W::curl_options( $pin ); check( CURLPROTO_HTTPS === $c[ CURLOPT_PROTOCOLS ] && array( 'panel.ejemplo.com:2005:93.184.216.34' ) === $c[ CURLOPT_RESOLVE ], 'H-5 opciones de cURL: sólo https y la IP fijada' ); }
$GLOBALS['seen_hook'] = null;
add_filter( 'pre_http_request', function ( $pre ) { if ( null === $GLOBALS['seen_hook'] ) $GLOBALS['seen_hook'] = ! empty( $GLOBALS['hooks']['http_api_curl'] ) && ! empty( $GLOBALS['hooks']['requests-fsockopen.remote_socket'] ); return $pre; }, 1, 1 );
$r = $W::from_settings()->users();
check( 'ok' === $r['kind'] && true === $GLOBALS['seen_hook'], 'H-5 durante la llamada están los ganchos que fijan la IP (cURL) y cortan otro transporte' );
check( empty( $GLOBALS['hooks']['http_api_curl'] ) && empty( $GLOBALS['hooks']['requests-fsockopen.remote_socket'] ), 'H-5 los ganchos son de un solo uso (se quitan al terminar)' );
reset_all(); $GLOBALS['dns']['sin-dns.test'] = array(); setopt( 'webuzo_url', 'https://sin-dns.test:2005' ); $GLOBALS['http'] = array(); // (la resolución se guarda por petición: un nombre nuevo)
$r = $W::from_settings()->users();
check( 'config' === $r['kind'] && ! $GLOBALS['http'] && ! get_option( $W::BLOCK ), 'H-5 si el nombre no resuelve no se llama (la clave no sale) y no cuenta como clave rechazada' );
reset_all();
add_filter( 'pre_http_request', function ( $pre ) { if ( ! empty( $GLOBALS['sin_curl'] ) ) do_action( 'requests-fsockopen.remote_socket' ); return $pre; }, 2, 1 );
$GLOBALS['sin_curl'] = 1; $r = $W::from_settings()->users(); $GLOBALS['sin_curl'] = 0;
check( 'config' === $r['kind'] && ! get_option( $W::BLOCK ), 'H-5 con un transporte que no es cURL se corta antes de conectar' );

echo "Hosting, sexta revisión: {$GLOBALS['checks']} comprobaciones correctas.\n";
