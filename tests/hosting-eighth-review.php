<?php
// Octava revisión de la venta automática de DIGITALÍSIMO Hosting (2026-11-26), sin WordPress, con Webuzo y Namecheap
// simulados y la caché de cada petición (tests/fixtures/hosting-wp.php, cache_sim). Cada caso falla con el código de la
// séptima ronda (4fbca311):
//   1  la renovación de un servicio desplazado pagada con la venta apagada avisa para reembolsar y no promete retomarse;
//   2  el pedido reembolsado o cancelado en otra petición detiene el gasto y nunca pasa a «completado»; si ya se gastó,
//      se avisa; una baja a media renovación sin gasto también avisa para reembolsar;
//   3  la escritura final del estado es condicional: una baja que llega mientras se cancela la suspensión gana;
//   4  un DNS que falla y luego responde es falla de red (reintento), no de configuración;
//   5  las formas de IPv6 que llevan una IPv4 interna dentro no son públicas.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['http'] = array(); $GLOBALS['dns'] = array(); $GLOBALS['trig'] = null; $GLOBALS['meta_trig'] = null;
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
function hosting_only( $domain, $customer = 7 ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); digi_run_queue();
	return $o;
}
function setopt( $k, $v ) { $o = get_option( 'digitalisimo_hosting_options' ); $o[ $k ] = $v; update_option( 'digitalisimo_hosting_options', $o ); }
function sale( $on ) { setopt( 'provision_enabled', $on ? 1 : 0 ); }
function admin_mails() { return array_values( array_map( function ( $m ) { return $m['subject']; }, array_filter( $GLOBALS['mails'], function ( $m ) { return 'admin@tienda.test' === $m['to']; } ) ) ); }
function count_mail( $needle ) { return count( array_filter( admin_mails(), function ( $s ) use ( $needle ) { return false !== strpos( $s, $needle ); } ) ); }
function base_meta( $id, $key ) { return $GLOBALS['postmeta'][ (int) $id ][ '_dhs_' . $key ] ?? null; }
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$t = $GLOBALS['trig'] ?? null; if ( ! $t ) return $pre;
	$body = (array) ( $args['body'] ?? array() );
	if ( (string) ( $body['Command'] ?? '' ) === $t[0] || false !== strpos( $url, 'act=' . $t[0] ) ) { $GLOBALS['trig'] = null; other_request( $t[1] ); }
	return $pre;
}, 5, 3 );
function renewal_ready( $domain, $with_domain = true ) {
	$P = 'Digitalisimo_Hosting_Provisioning';
	$s = $with_domain ? provisioned( $domain ) : (int) hosting_only( $domain )->get_meta( '_digitalisimo_service' );
	$exp = (int) $P::svc( $s, 'expires' );
	$P::daily( $exp - 29 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
	$GLOBALS['mails'] = array();
	$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending';
	digi_cache_reset();
	return array( $s, $exp, $r );
}

// ── 1: renovación de un servicio desplazado pagada con la venta apagada ──────────────────────────────────────
reset_all();
$a = provisioned( 'doble2.com', 7 ); $exp = (int) $P::svc( $a, 'expires' ); $ua = $P::svc( $a, 'webuzo_user' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$ra = wc_get_order( (int) $P::svc( $a, 'renewal_order' ) );
unset( $GLOBALS['webuzo']['users'][ $ua ] );
hosting_only( 'doble2.com', 7 );
$P::daily( $exp - 6 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
$GLOBALS['mails'] = array();
sale( false ); $ra->status = 'pending'; $ra->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 1 === count_mail( 'renovación pagada de un servicio viejo: doble2.com' ), '1 pagada con la venta apagada: se avisa para reembolsar' );
check( false === strpos( implode( "\n", $ra->notes ), 'se retoman' ) && false !== strpos( implode( "\n", $ra->notes ), 'NO se aplica' ), '1 la nota del pedido no promete retomarla: dice que no se aplica' );
sale( true ); $P::sweep_renewals(); digi_run_queue( true, 200 );
foreach ( array( 1, 2, 3 ) as $d ) { $P::daily( $exp - 6 * DAY_IN_SECONDS + $d * DAY_IN_SECONDS ); digi_run_queue( true, 200 ); }
check( 1 === count( admin_mails() ) && 'superseded' === ( (array) $ra->get_meta( $P::META_RENEW ) )['stopped'], '1 al encender y en los días siguientes: detenida, sin más avisos ni «retomada»' );

// ── 2: pedido reembolsado o cancelado en otra petición; baja a media renovación sin gasto ─────────────────────
$GLOBALS['cache_sim'] = true; $GLOBALS['ext_cache'] = true;
reset_all();
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'reembolso.com' ) ) ) ); $o->customer = 7;
$GLOBALS['trig'] = array( 'namecheap.domains.check', function () use ( $o ) { $o->update_status( 'refunded' ); } );
$o->update_status( 'processing' ); digi_run_queue( true, 200 ); $sid = (int) $o->get_meta( '_digitalisimo_service' );
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 0 === digi_webuzo_count( 'add_user' ) && 'refunded' === $o->get_status() && 'active' !== base_meta( $sid, 'status' ), '2 reembolsado antes del cobro: no se registra ni se crea nada' );
reset_all();
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'reembolso2.com' ) ) ) ); $o->customer = 7;
$GLOBALS['trig'] = array( 'add_user', function () use ( $o ) { $o->update_status( 'refunded' ); } );
$o->update_status( 'processing' ); digi_run_queue( true, 200 ); $sid = (int) $o->get_meta( '_digitalisimo_service' );
check( 'refunded' === $o->get_status() && 'active' !== base_meta( $sid, 'status' ), '2 reembolsado durante la última llamada: el pedido nunca pasa a «completado» ni el servicio a «activo»' );
check( 1 === count_mail( 'pedido reembolsado con gasto: reembolso2.com' ), '2 y como ya se gastó (dominio y cuenta), se avisa al administrador' );
reset_all(); list( $s, $exp, $r ) = renewal_ready( 'solohosting.com', false );
$GLOBALS['meta_trig'] = array( '_dhs_last_renewal_order', function () use ( $s ) { Digitalisimo_Hosting_Provisioning::cancel_service( $s ); } );
$r->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 'cancelled' === base_meta( $s, 'status' ) && (int) base_meta( $s, 'expires' ) === $exp && 1 === count_mail( 'renovación pagada de un servicio dado de baja: solohosting.com' ), '2 baja a media renovación sin gasto: no se extiende y se avisa para reembolsar' );

// ── 3: la escritura final es condicional ──────────────────────────────────────────────────────────────────────
reset_all(); list( $s, $exp, $r ) = renewal_ready( 'residual.com', false );
$GLOBALS['meta_trig'] = array( '_dhs_last_renewal_order', function () use ( $s ) {
	$GLOBALS['meta_trig'] = array( '_dhs_toggle_attempts', function () use ( $s ) { Digitalisimo_Hosting_Provisioning::cancel_service( $s ); } );
} );
$r->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 'cancelled' === base_meta( $s, 'status' ) && (int) base_meta( $s, 'expires' ) === $exp, '3 una baja que llega mientras se cancela la suspensión gana: sigue dada de baja y sin año extra' );
$GLOBALS['cache_sim'] = false; $GLOBALS['ext_cache'] = false; digi_cache_reset();

// ── 4: DNS que falla y luego responde ─────────────────────────────────────────────────────────────────────────
reset_all(); $GLOBALS['fl'] = 0;
add_filter( 'digitalisimo_hosting_webuzo_host_ips', function ( $ips, $host ) { if ( 'flap.test' !== $host ) return $ips; $GLOBALS['fl']++; return $GLOBALS['fl'] <= 3 ? array() : array( '93.184.216.34' ); }, 20, 2 );
setopt( 'webuzo_url', 'https://flap.test:2005' ); $GLOBALS['http'] = array();
$r = $W::from_settings()->users();
check( 'transient' === $r['kind'] && false === strpos( $r['message'], 'pública' ) && ! $GLOBALS['http'], '4 DNS que falla al resolver (aunque responda después): falla de red, sin llamar y sin hablar de IP no pública' );
$GLOBALS['dns']['privada.test'] = array( '10.0.0.5' ); setopt( 'webuzo_url', 'https://privada.test:2005' );
$r = $W::from_settings()->users();
check( 'config' === $r['kind'] && ! $GLOBALS['http'], '4 una IP de verdad no pública sí es error de configuración (sin llamar)' );
$ref = new ReflectionProperty( $W, 'dns' ); if ( PHP_VERSION_ID < 80100 ) $ref->setAccessible( true ); $ref->setValue( null, array() );
check( null === $W::pin( 'https://privada.test:2005', $why ) && 'private' === $why, '4 y el motivo es la IP no pública (no el DNS)' );

// ── 5: IPv6 con IPv4 interna dentro ───────────────────────────────────────────────────────────────────────────
reset_all(); $ref = new ReflectionProperty( $W, 'dns' ); if ( PHP_VERSION_ID < 80100 ) $ref->setAccessible( true );
foreach ( array( '2002:7f00:1::1', '2002:c0a8:101::1', '64:ff9b:1::a00:1', '::ffff:0:7f00:1', '::ffff:0:a00:1', '2001:0:7f00:1::1', 'fec0::1', 'ff02::1', '100::1' ) as $i => $ip ) {
	$GLOBALS['dns'][ 'v6-' . $i . '.test' ] = array( $ip ); $ref->setValue( null, array() );
	check( null === $W::pin( 'https://v6-' . $i . '.test:2005' ), '5 no es pública: ' . $ip );
}
$GLOBALS['dns']['v6ok.test'] = array( '2002:5db8:d822::1' ); $ref->setValue( null, array() );
check( null !== $W::pin( 'https://v6ok.test:2005' ), '5 6to4 con una IPv4 pública sí se acepta' );

echo "Hosting, octava revisión: {$GLOBALS['checks']} comprobaciones correctas.\n";
