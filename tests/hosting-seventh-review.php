<?php
// Séptima revisión de la venta automática de DIGITALÍSIMO Hosting (2026-11-26), sin WordPress, con Webuzo y Namecheap
// simulados y, donde importa, con la caché de objetos de cada petición (tests/fixtures/hosting-wp.php, cache_sim). Cada
// caso falla con el código de la sexta ronda (431e8ba4):
//   H-1   con caché persistente (Redis, Memcached, LSMCD) la baja o la venta apagada en OTRA petición también detienen
//         el cobro: la copia en memoria de la petición se descarta antes de cada gasto;
//   H-2a  un motivo de suspensión viejo que Webuzo conserva en una cuenta activa no convierte en ajena la suspensión del
//         plugin: sólo cuenta un motivo que cambió después de la orden;
//   H-3a  una renovación vieja pagada después del aviso del servicio desplazado avisa otra vez, y el vigilante no la
//         vuelve a encolar cada día;
//   menores: reencender el módulo retoma lo anotado (también la revisión diaria); un fallo del DNS en un alta se
//         reintenta como falla de red.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['http'] = array(); $GLOBALS['dns'] = array(); $GLOBALS['trig'] = null;
	digi_cache_reset();
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' );
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7 ) );
}
function provisioned( $domain, $customer = 7 ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); digi_run_queue();
	return (int) $o->get_meta( '_digitalisimo_service' );
}
function hosting_only( $domain, $customer = 7, $run = true ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); if ( $run ) digi_run_queue();
	return $o;
}
function setopt( $k, $v ) { $o = get_option( 'digitalisimo_hosting_options' ); $o[ $k ] = $v; update_option( 'digitalisimo_hosting_options', $o ); }
function sale( $on ) { setopt( 'provision_enabled', $on ? 1 : 0 ); }
function admin_mails() { return array_values( array_map( function ( $m ) { return $m['subject']; }, array_filter( $GLOBALS['mails'], function ( $m ) { return 'admin@tienda.test' === $m['to']; } ) ) ); }
function count_mail( $needle ) { return count( array_filter( admin_mails(), function ( $s ) use ( $needle ) { return false !== strpos( $s, $needle ); } ) ); }
function pay_renewal( $s ) { $P = 'Digitalisimo_Hosting_Provisioning'; $r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' ); return $r; }
function click( $op, $s ) { $_POST = array( 'service' => $s, 'op' => $op, '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( $op, $s ) ); $x = ''; try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Exception $e ) { $x = $e->getMessage(); } $_POST = array(); return $x; }
function wstatus( $user ) { return $GLOBALS['webuzo']['users'][ $user ]['status'] ?? null; }
function later_days( array $days ) { $P = 'Digitalisimo_Hosting_Provisioning'; foreach ( $days as $d ) { $P::daily( time() + $d * DAY_IN_SECONDS ); digi_run_queue( true, 200 ); } }
function base_meta( $id, $key ) { return $GLOBALS['postmeta'][ (int) $id ][ '_dhs_' . $key ] ?? null; }
// Otra petición «en paralelo» (escribe en la base, no en la memoria de ésta): al ver un comando de Namecheap o un act de Webuzo.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$t = $GLOBALS['trig'] ?? null; if ( ! $t ) return $pre;
	$body = (array) ( $args['body'] ?? array() );
	if ( (string) ( $body['Command'] ?? '' ) === $t[0] || false !== strpos( $url, 'act=' . $t[0] ) ) { $GLOBALS['trig'] = null; other_request( $t[1] ); }
	return $pre;
}, 5, 3 );

// ── H-1 con la caché de cada petición (sin y con caché persistente) ──────────────────────────────────────────
$GLOBALS['cache_sim'] = true;
foreach ( array( false => 'sin caché persistente', true => 'con caché persistente' ) as $ext => $label ) {
	reset_all(); $GLOBALS['ext_cache'] = (bool) $ext;
	$s = provisioned( 'renueva.com' ); $exp = (int) $P::svc( $s, 'expires' );
	$P::daily( $exp - 29 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
	$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending';
	digi_cache_reset(); $renews = digi_nc_count( 'namecheap.domains.renew' );
	$GLOBALS['trig'] = array( 'namecheap.domains.getInfo', function () use ( $s ) { Digitalisimo_Hosting_Provisioning::cancel_service( $s ); } );
	$r->update_status( 'processing' ); digi_run_queue( true, 200 );
	check( digi_nc_count( 'namecheap.domains.renew' ) === $renews && 'cancelled' === base_meta( $s, 'status' ) && (int) base_meta( $s, 'expires' ) === $exp, 'H-1 ' . $label . ': baja en otra petición durante la renovación: no se cobra, sigue dada de baja y sin año extra' );

	reset_all(); $GLOBALS['ext_cache'] = (bool) $ext;
	$s = provisioned( 'apaga.com' ); $exp = (int) $P::svc( $s, 'expires' );
	$P::daily( $exp - 29 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
	$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending';
	digi_cache_reset(); $renews = digi_nc_count( 'namecheap.domains.renew' );
	$GLOBALS['trig'] = array( 'namecheap.domains.getInfo', function () { sale( false ); } );
	$r->update_status( 'processing' ); digi_run_queue( true, 200 );
	check( digi_nc_count( 'namecheap.domains.renew' ) === $renews && (int) base_meta( $s, 'expires' ) === $exp, 'H-1 ' . $label . ': venta apagada en otra petición durante la renovación: no se cobra ni se extiende' );

	reset_all(); $GLOBALS['ext_cache'] = (bool) $ext;
	$GLOBALS['nc']['balance'] = 0.01;
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'saldo.com' ) ) ) ); $o->customer = 7;
	$o->update_status( 'processing' ); digi_run_queue( true, 200 );
	$sid = (int) $o->get_meta( '_digitalisimo_service' );
	$GLOBALS['nc']['balance'] = 50.0; $P::retry( $sid ); digi_cache_reset();
	$GLOBALS['trig'] = array( 'namecheap.domains.check', function () use ( $sid ) { Digitalisimo_Hosting_Provisioning::cancel_service( $sid ); } );
	digi_run_queue( true, 200 );
	check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 0 === digi_webuzo_count( 'add_user' ) && 'cancelled' === base_meta( $sid, 'status' ), 'H-1 ' . $label . ': baja en otra petición durante un reintento del alta: no se registra ni se crea nada' );
}
$GLOBALS['cache_sim'] = false; $GLOBALS['ext_cache'] = false; digi_cache_reset();

// ── H-2a: un motivo viejo en una cuenta activa ────────────────────────────────────────────────────────────────
reset_all();
$s = provisioned( 'motivoviejo.com' ); $u = $P::svc( $s, 'webuzo_user' ); $exp = (int) $P::svc( $s, 'expires' );
$GLOBALS['webuzo']['users'][ $u ]['suspend_reason'] = 'Bandwidth Overuse'; // Webuzo real: activa y con el motivo de antes
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
check( 'suspended' === wstatus( $u ), 'H-2a preparación: el plugin la suspendió por falta de pago' );
$GLOBALS['mails'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 );
check( '' === wstatus( $u ) && 'active' === $P::svc( $s, 'status' ) && ! count_mail( 'fuera del plugin' ), 'H-2a el cliente que pagó se reactiva aunque Webuzo conserve un motivo viejo' );
reset_all();
$s = provisioned( 'motivonuevo.com' ); $u = $P::svc( $s, 'webuzo_user' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$GLOBALS['webuzo']['users'][ $u ]['suspend_reason'] = 'Bandwidth Overuse'; // Webuzo puso su motivo DESPUÉS
$GLOBALS['mails'] = array();
pay_renewal( $s ); digi_run_queue( true, 200 );
check( 'suspended' === wstatus( $u ) && 1 === count_mail( 'suspendida fuera del plugin' ), 'H-2a un motivo que cambió después de la orden del plugin sí la hace ajena (no se reactiva, se avisa)' );

// ── H-3a: la renovación vieja pagada tras el aviso ────────────────────────────────────────────────────────────
reset_all();
$a = provisioned( 'doble.com', 7 ); $exp = (int) $P::svc( $a, 'expires' ); $ua = $P::svc( $a, 'webuzo_user' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$ra = wc_get_order( (int) $P::svc( $a, 'renewal_order' ) );
unset( $GLOBALS['webuzo']['users'][ $ua ] );
$ob = hosting_only( 'doble.com', 7 ); $b = (int) $ob->get_meta( '_digitalisimo_service' );
$P::daily( $exp - 6 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
check( 1 === count_mail( 'servicio viejo de doble.com' ), 'H-3a preparación: salió el aviso del servicio desplazado' );
$GLOBALS['mails'] = array();
$ra->status = 'pending'; $ra->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 1 === count_mail( 'renovación pagada de un servicio viejo: doble.com' ), 'H-3a pagar la renovación vieja después del aviso avisa otra vez (para reembolsar)' );
$GLOBALS['mails'] = array(); $renews = digi_nc_count( 'namecheap.domains.renew' );
later_days( array( 1, 2, 3 ) );
check( ! admin_mails() && digi_nc_count( 'namecheap.domains.renew' ) === $renews, 'H-3a el vigilante no la vuelve a encolar ni avisa «retomada» cada día' );

// ── Menores ───────────────────────────────────────────────────────────────────────────────────────────────────
reset_all( array( 'own_domain_auto' => 0 ) );
$o = hosting_only( 'propio-mx.com', 7 ); $sid = (int) $o->get_meta( '_digitalisimo_service' );
setopt( 'enabled', 0 ); click( 'approve', $sid ); digi_run_queue( true, 200 );
check( 0 === digi_webuzo_count( 'add_user' ) && $P::svc( $sid, 'resume_provision' ), 'Reencender: aprobado con el módulo apagado queda anotado' );
$GLOBALS['caps'] = array( 'manage_options', 'manage_woocommerce' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'enabled' => 1, 'provision_enabled' => 1 ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array(); digi_run_queue( true, 200 );
check( ! empty( get_option( 'digitalisimo_hosting_options' )['enabled'] ) && 1 === digi_webuzo_count( 'add_user' ) && 'active' === $P::svc( $sid, 'status' ), 'Reencender el módulo (con la venta ya marcada) retoma lo anotado' );
reset_all( array( 'own_domain_auto' => 0 ) );
$o = hosting_only( 'propio2.com', 7 ); $sid = (int) $o->get_meta( '_digitalisimo_service' );
sale( false ); click( 'approve', $sid ); digi_run_queue( true, 200 );
sale( true ); $P::daily(); digi_run_queue( true, 200 );
check( 1 === digi_webuzo_count( 'add_user' ), 'La revisión diaria también retoma lo anotado (venta encendida sin pasar por los ajustes)' );

reset_all();
$GLOBALS['dns']['webuzo.test'] = array(); $ref = new ReflectionProperty( $W, 'dns' ); if ( PHP_VERSION_ID < 80100 ) $ref->setAccessible( true ); $ref->setValue( null, array() ); // la resolución guardada de la petición, fuera
$o = hosting_only( 'dnscaido.com', 7, false ); digi_run_queue( true, 1 );
check( false !== strpos( implode( "\n", $o->notes ), 'Reintento automático' ) && ! get_option( $W::BLOCK ), 'Un fallo del DNS en el alta se reintenta como falla de red (sin parada de clave)' );
$GLOBALS['dns']['webuzo.test'] = array( '93.184.216.34' ); digi_run_queue( true, 200 );
check( 'completed' === $o->get_status() && 1 === digi_webuzo_count( 'add_user' ), 'Al volver el DNS, el reintento entrega el servicio' );

echo "Hosting, séptima revisión: {$GLOBALS['checks']} comprobaciones correctas.\n";
