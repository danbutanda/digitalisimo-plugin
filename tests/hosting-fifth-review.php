<?php
// Quinta revisión de la venta automática de DIGITALÍSIMO Hosting (2026-11-26), sin WordPress y con Webuzo y Namecheap
// simulados como los reales (tests/fixtures/hosting-wp.php). Cada caso falla con el código de la cuarta ronda (bfe284e6):
//   NUEVO-1  pagó y la reactivación no pudo leer Webuzo: error en el servicio, aviso, y la revisión diaria lo retoma
//            (también si la venta se apagó entre medio);
//   NUEVO-2  la reconciliación sólo reactiva lo que suspendió el plugin; una suspensión hecha fuera no se toca y se avisa;
//   NUEVO-3  «Dar de baja» detiene lo que está en marcha: cola, renovación pagada después, espera de la clave;
//   M2       «Suspender» con la venta apagada no hace nada ni deja el motivo; pedido nuevo con la venta apagada: nota;
//   A1       mismo dominio recontratado con cuenta nueva: el servicio viejo no la toca;
//   M1       fecha de Namecheap (hora del Este) un día antes que el intento propio: se adopta;
//   B3       la URL de Webuzo se compara contra la IP (formas numéricas, «localhost.», IPv4 en IPv6, nombres resueltos).
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['http'] = array(); $GLOBALS['dns'] = array();
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
function sale( $on ) { setopt( 'provision_enabled', $on ? 1 : 0 ); }
function setopt( $k, $v ) { $o = get_option( 'digitalisimo_hosting_options' ); $o[ $k ] = $v; update_option( 'digitalisimo_hosting_options', $o ); }
function has_mail( $needle ) { foreach ( $GLOBALS['mails'] as $m ) if ( 'admin@tienda.test' === $m['to'] && false !== strpos( $m['subject'], $needle ) ) return true; return false; }
function suspended_unpaid( $domain ) { $P = 'Digitalisimo_Hosting_Provisioning'; $s = provisioned( $domain ); $exp = (int) $P::svc( $s, 'expires' ); $P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 ); return $s; }
function pay_renewal( $s ) { $P = 'Digitalisimo_Hosting_Provisioning'; $r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' ); return $r; }
function click( $op, $s ) { $_POST = array( 'service' => $s, 'op' => $op, '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( $op, $s ) ); $x = ''; try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Exception $e ) { $x = $e->getMessage(); } $_POST = array(); return $x; }
function wstatus( $user ) { return $GLOBALS['webuzo']['users'][ $user ]['status'] ?? null; }
function later_days( array $days ) { $P = 'Digitalisimo_Hosting_Provisioning'; foreach ( $days as $d ) { $P::daily( time() + $d * DAY_IN_SECONDS ); digi_run_queue( true, 200 ); } }

// ── NUEVO-1: pagó y Webuzo no responde al reactivar ───────────────────────────────────────────────────────────
reset_all();
$s = suspended_unpaid( 'pago.com' );
check( 'suspended' === wstatus( 'pago' ), 'NUEVO-1 preparación: la cuenta quedó suspendida por falta de pago' );
$GLOBALS['mails'] = array(); $GLOBALS['webuzo']['timeout'] = 999;
pay_renewal( $s ); digi_run_queue( true, 200 ); $GLOBALS['webuzo']['timeout'] = 0;
check( 'suspended' === wstatus( 'pago' ) && false !== strpos( (string) $P::svc( $s, 'error' ), 'Pagado' ), 'NUEVO-1 sin red al reactivar: el servicio muestra el error (no se rinde en silencio)', $P::svc( $s, 'error' ) );
check( has_mail( 'pagado y sin reactivar: pago.com' ), 'NUEVO-1 sin red al reactivar: se avisa al administrador' );
later_days( array( 1, 2 ) );
check( '' === wstatus( 'pago' ) && 'active' === $P::svc( $s, 'status' ), 'NUEVO-1 la revisión diaria hace de red: con Webuzo sano, la cuenta pagada se reactiva sola', array( wstatus( 'pago' ), $P::svc( $s, 'status' ) ) );

reset_all();
$s = suspended_unpaid( 'sinurl.com' );
$GLOBALS['mails'] = array(); setopt( 'webuzo_url', '' );
pay_renewal( $s ); digi_run_queue( true, 200 );
check( '' !== (string) $P::svc( $s, 'error' ) && has_mail( 'pagado y sin reactivar: sinurl.com' ), 'NUEVO-1 Webuzo sin configurar al pagar: error en el servicio y aviso', $P::svc( $s, 'error' ) );
setopt( 'webuzo_url', 'https://webuzo.test:2005' ); later_days( array( 1, 2 ) );
check( '' === wstatus( 'sinurl' ), 'NUEVO-1 al volver a configurar Webuzo, la revisión diaria la reactiva' );

reset_all();
$s = suspended_unpaid( 'apagon.com' );
pay_renewal( $s ); digi_run_queue( true, 1 ); // la renovación corre; la reconciliación queda en cola
sale( false ); digi_run_queue( true, 200 );
check( 'suspended' === wstatus( 'apagon' ), 'NUEVO-1 con la venta apagada la reconciliación no llama a Webuzo' );
sale( true ); $P::sweep_renewals(); later_days( array( 1, 2 ) );
check( '' === wstatus( 'apagon' ) && 'active' === $P::svc( $s, 'status' ), 'NUEVO-1 la tarea no se pierde: al reencender la venta se reactiva', array( wstatus( 'apagon' ), $P::svc( $s, 'status' ) ) );

// ── NUEVO-2: sólo se reactiva lo que suspendió el plugin ──────────────────────────────────────────────────────
reset_all();
$s = provisioned( 'abuso.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$GLOBALS['webuzo']['users']['abuso']['status'] = 'suspended'; // suspendida a mano en el panel de Webuzo
$GLOBALS['mails'] = array(); $GLOBALS['webuzo']['calls'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 ); later_days( array( 1, 2 ) );
check( 'suspended' === wstatus( 'abuso' ) && 0 === digi_webuzo_count( 'users', 'unsuspend', 'abuso' ), 'NUEVO-2 una cuenta suspendida fuera del plugin no se reactiva al pagar ni en la revisión diaria' );
check( has_mail( 'suspendida fuera del plugin: abuso.com' ), 'NUEVO-2 y se avisa al administrador' );

reset_all();
$s = provisioned( 'amano.com' ); $exp = (int) $P::svc( $s, 'expires' );
click( 'suspend', $s ); digi_run_queue( true, 200 );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); pay_renewal( $s ); digi_run_queue( true, 200 ); later_days( array( 1, 2 ) );
check( 'suspended' === wstatus( 'amano' ) && 'manual' === $P::svc( $s, 'suspend_reason' ), 'NUEVO-2 la suspensión manual desde el plugin sigue tras el pago' );

reset_all();
$s = suspended_unpaid( 'propia.com' ); $GLOBALS['webuzo']['calls'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 );
check( '' === wstatus( 'propia' ) && 1 === digi_webuzo_count( 'users', 'unsuspend', 'propia' ), 'NUEVO-2 la que suspendió el plugin por falta de pago sí se reactiva al pagar' );

// Orden de suspender en camino: Webuzo la aplicó pero la respuesta se perdió; el cliente paga: se reactiva.
reset_all();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( empty( $GLOBALS['lose_suspend'] ) || false === strpos( $url, 'act=users' ) || empty( $args['body']['suspend'] ) ) return $pre;
	$GLOBALS['webuzo']['users'][ $args['body']['suspend'] ]['status'] = 'suspended';
	return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 45001 milliseconds' );
}, 5, 3 );
$s = provisioned( 'camino.com' ); $exp = (int) $P::svc( $s, 'expires' );
$GLOBALS['lose_suspend'] = 1;
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$GLOBALS['lose_suspend'] = 0;
check( 'suspended' === wstatus( 'camino' ), 'NUEVO-2 preparación: la suspensión se aplicó en Webuzo sin respuesta' );
pay_renewal( $s ); digi_run_queue( true, 200 );
check( '' === wstatus( 'camino' ), 'NUEVO-2 una suspensión que el plugin mandó (en camino, sin respuesta) sí se reactiva al pagar' );

// ── NUEVO-3: la baja detiene lo que está en marcha ────────────────────────────────────────────────────────────
reset_all();
$s = provisioned( 'baja.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending';
click( 'cancel', $s );
$GLOBALS['mails'] = array(); $renews = digi_nc_count( 'namecheap.domains.renew' );
$r->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 'cancelled' === $P::svc( $s, 'status' ) && (int) $P::svc( $s, 'expires' ) === $exp && digi_nc_count( 'namecheap.domains.renew' ) === $renews, 'NUEVO-3 la renovación pagada de un servicio dado de baja no renueva nada' );
check( has_mail( 'dado de baja' ), 'NUEVO-3 y se avisa al administrador para reembolsar' );

reset_all();
$GLOBALS['nc']['timeout']['namecheap.domains.getInfo'] = 1;
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'aborto.com' ) ) ) ); $o->customer = 12;
$o->update_status( 'processing' ); digi_run_queue( true, 1 );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $bal = $GLOBALS['nc']['balance'];
check( ! empty( $GLOBALS['queue'] ), 'NUEVO-3 preparación: el alta quedó con un reintento en cola' );
click( 'cancel', $s );
check( empty( $GLOBALS['queue'] ), 'NUEVO-3 la baja descarta las tareas en cola del servicio' );
digi_run_queue( true, 200 );
check( ! isset( $GLOBALS['nc']['domains']['aborto.com'] ) && $bal === $GLOBALS['nc']['balance'] && ! isset( $GLOBALS['webuzo']['users']['aborto'] ), 'NUEVO-3 tras la baja no se registra el dominio ni se crea la cuenta' );
$P::enqueue( $P::HOOK_PROVISION, $o->get_id() ); digi_run_queue( true, 200 );
check( ! isset( $GLOBALS['nc']['domains']['aborto.com'] ) && ! isset( $GLOBALS['webuzo']['users']['aborto'] ), 'NUEVO-3 un alta que llega a correr de todos modos respeta la baja' );
check( false === $P::retry( $s ), 'NUEVO-3 «Reintentar» no hace nada en un servicio dado de baja' );

reset_all();
$GLOBALS['webuzo']['mode'] = 'auth';
$o = hosting_only( 'esperaclave.com', 13 ); $s = (int) $o->get_meta( '_digitalisimo_service' );
check( (bool) get_option( $P::WAITING, array() ), 'NUEVO-3 preparación: el alta quedó esperando la clave de Webuzo' );
click( 'cancel', $s );
$GLOBALS['webuzo']['mode'] = ''; $W::forget( 'CLAVE-WEBUZO-OK' ); $W::resume(); digi_run_queue( true, 200 );
check( ! isset( $GLOBALS['webuzo']['users']['esperaclave'] ), 'NUEVO-3 al levantar la parada no se crea la cuenta de un servicio dado de baja' );

// ── M2: la venta apagada ──────────────────────────────────────────────────────────────────────────────────────
reset_all();
$s = provisioned( 'manual.com' ); sale( false ); $c = count( $GLOBALS['webuzo']['calls'] );
$x = click( 'suspend', $s ); digi_run_queue( true, 200 );
check( count( $GLOBALS['webuzo']['calls'] ) === $c && '' === (string) $P::svc( $s, 'suspend_reason' ) && false !== strpos( $x, 'done=off' ), 'M2 «Suspender» con la venta apagada no hace nada, lo dice y no deja el motivo manual', $x );
sale( true ); later_days( array( 1, 2 ) );
check( '' === wstatus( 'manual' ) && 'active' === $P::svc( $s, 'status' ), 'M2 al reencender no aparece una suspensión manual pendiente' );
reset_all(); sale( false );
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'durante.com' ) ) ) ); $o->update_status( 'processing' ); digi_run_queue( true, 200 );
check( false !== strpos( implode( "\n", $o->notes ), 'venta automática está apagada' ), 'M2 un pedido pagado con la venta apagada deja una nota visible' );

// ── A1: el mismo dominio recontratado con una cuenta nueva ────────────────────────────────────────────────────
reset_all();
$a = provisioned( 'vuelve.com', 7 ); $exp = (int) $P::svc( $a, 'expires' );
unset( $GLOBALS['webuzo']['users']['vuelve'] );
$ob = hosting_only( 'vuelve.com', 7 ); $b = (int) $ob->get_meta( '_digitalisimo_service' );
$P::svc_set( $b, array( 'created' => (int) $P::svc( $a, 'created' ) + 60, 'expires' => $exp + 300 * DAY_IN_SECONDS ) );
$GLOBALS['webuzo']['calls'] = array();
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
check( 'vuelve' === $P::svc( $b, 'webuzo_user' ) && 0 === digi_webuzo_count( 'users', 'suspend', 'vuelve' ) && '' === wstatus( 'vuelve' ), 'A1 al vencer el servicio viejo no se suspende la cuenta nueva del mismo dominio' );

// ── M1: fecha de Namecheap en hora del Este ───────────────────────────────────────────────────────────────────
reset_all();
$o = digi_order( array( new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'noche.com' ) ) ) ); $o->customer = 9;
$sent = gmmktime( 2, 0, 0, 11, 26, 2026 );
$o->update_meta_data( '_digitalisimo_provision', array( 'paid' => $sent, 'domain' => array( 'create_sent' => $sent ) ) ); $o->status = 'processing'; $o->date_paid = $sent;
$GLOBALS['nc']['domains']['noche.com'] = strtotime( '+1 year' ); $GLOBALS['nc']['created']['noche.com'] = gmmktime( 0, 0, 0, 11, 25, 2026 );
$P::enqueue( $P::HOOK_PROVISION, $o->get_id() ); digi_run_queue( true, 200 );
check( 'completed' === $o->get_status(), 'M1 el registro propio con la fecha del Este un día antes se adopta', $o->get_status() );

// ── B3: la URL de Webuzo contra la IP ─────────────────────────────────────────────────────────────────────────
reset_all();
foreach ( array( 'https://127.1:2005', 'https://2130706433:2005', 'https://0x7f000001:2005', 'https://0177.0.0.1:2005', 'https://0x7f.0.0.1:2005', 'https://127.0.0.1.:2005', 'https://localhost.:2005', 'https://0:2005', 'https://0.0.0.0:2005',
	'https://[::ffff:127.0.0.1]:2005', 'https://[::ffff:7f00:1]:2005', 'https://[::ffff:a9fe:a9fe]', 'https://[64:ff9b::7f00:1]:2005', 'https://[0:0:0:0:0:0:0:1]:2005', 'https://[fe80::1]:2005', 'https://[fd00::1]:2005', 'https://100.112.180.84:2005' ) as $u ) check( false === $W::safe_url( $u ), 'B3 se rechaza ' . $u );
$GLOBALS['dns'] = array( 'interno.ejemplo.com' => array( '10.1.2.3' ), 'v6.ejemplo.com' => array( '::1' ), '127.0.0.1.nip.io' => array( '127.0.0.1' ), 'mixto.ejemplo.com' => array( '199.85.208.102', '192.168.1.25' ), 'panel.ejemplo.com' => array( '199.85.208.102' ) );
foreach ( array( 'interno.ejemplo.com', 'v6.ejemplo.com', '127.0.0.1.nip.io', 'mixto.ejemplo.com' ) as $h ) check( false === $W::safe_url( 'https://' . $h . ':2005' ), 'B3 se rechaza un nombre que resuelve a una IP interna: ' . $h );
check( true === $W::safe_url( 'https://panel.ejemplo.com:2005' ) && true === $W::safe_url( 'https://199.85.208.102:2005' ) && true === $W::safe_url( 'https://[2606:4700::1111]:2005' ), 'B3 se aceptan nombres e IP públicas' );
foreach ( array( 'https://127.1:2005', 'https://localhost.:2005', 'https://[::ffff:7f00:1]:2005' ) as $u ) {
	reset_all(); setopt( 'webuzo_url', '' );
	$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_url' => $u ) );
	ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
	$GLOBALS['http'] = array(); $W::from_settings()->users();
	check( '' === get_option( 'digitalisimo_hosting_options' )['webuzo_url'] && ! $GLOBALS['http'], 'B3 el formulario no guarda ' . $u . ' ni sale la clave hacia ahí' );
}

echo "Hosting, quinta revisión: {$GLOBALS['checks']} comprobaciones correctas.\n";
