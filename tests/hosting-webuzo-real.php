<?php
// El Webuzo real (prueba autorizada del 2026-11-24, /var/tmp/digi-wt/hosting-prov-reports/webuzo-real/INFORME-REAL.md)
// contra el cliente de DIGITALÍSIMO Hosting, con un doble que se comporta como él (tests/fixtures/hosting-wp.php):
//   1. con skip=1, suspender y reactivar contestan «done … con éxito» sin cambiar nada: el plugin no manda skip;
//   2. «done» no prueba nada: la suspensión o reactivación sólo se da por hecha cuando act=users muestra el estado; si
//      tarda, la comprobación se reprograma (no se bloquea); si nunca cambia, se reenvía (3 veces) y se avisa, sin
//      marcarla como hecha;
//   3. si el cliente paga mientras la orden de suspender viajaba, al confirmarse se reactiva enseguida;
//   4. add_user devuelve la contraseña en claro en done.data.user_passwd: no sale del cliente;
//   5. errores reales de add_user ({"pass_mismatch": …}, {"inv_pri_domain": …}, HTTP 200): no detienen Webuzo;
//   6. el mensaje es done['msg'].
// Falla con el código de 177639e6 (antes de la prueba real).
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array();
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' );
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7 ) );
}
function provisioned( $domain ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => $domain ) ) ) );
	$o->update_status( 'processing' ); digi_run_queue();
	return (int) $o->get_meta( '_digitalisimo_service' );
}
function overdue_now( $s ) { // pasada la gracia, como la revisión diaria
	$P = 'Digitalisimo_Hosting_Provisioning';
	$exp = (int) $P::svc( $s, 'expires' );
	$P::daily( $exp - 29 * DAY_IN_SECONDS );
	$P::daily( $exp + 8 * DAY_IN_SECONDS );
	return $exp;
}
function sent_skip() { foreach ( $GLOBALS['webuzo']['calls'] as $c ) if ( isset( $c['fields']['skip'] ) ) return true; return false; }

// 1. Sin skip: la suspensión y la reactivación se aplican de verdad.
reset_all();
$s = provisioned( 'real.com' ); overdue_now( $s ); digi_run_queue();
check( ! sent_skip(), 'No se manda skip (con skip=1 el Webuzo real contesta éxito sin suspender)' );
check( 'suspended' === $GLOBALS['webuzo']['users']['real']['status'] && 'suspended' === $P::svc( $s, 'status' ), 'La cuenta queda suspendida en Webuzo y el servicio lo refleja' );
$P::svc_set( $s, array( 'unsuspend_reason' => 'manual' ) ); $P::enqueue( $P::HOOK_UNSUSPEND, $s ); digi_run_queue();
check( '' === $GLOBALS['webuzo']['users']['real']['status'] && 'active' === $P::svc( $s, 'status' ), 'Reactivar también se aplica de verdad' );

// 2a. Webuzo tarda en aplicar: la comprobación se reprograma y sólo se marca cuando la lista lo muestra.
reset_all();
$s = provisioned( 'lento.com' );
$GLOBALS['webuzo']['mode'] = 'lento';
overdue_now( $s );
digi_run_queue( true, 1 ); // la orden de suspender
check( 'active' === $P::svc( $s, 'status' ) && 'digitalisimo_hosting_verify' === ( $GLOBALS['queue'][0]['hook'] ?? '' ) && $GLOBALS['queue'][0]['at'] > time(), '«done» no basta: el servicio no se marca suspendido y la confirmación queda programada (no bloquea)' );
digi_run_queue( true, 1 ); // primera comprobación: todavía activa
check( 'active' === $P::svc( $s, 'status' ) && 1 === count( $GLOBALS['queue'] ), 'Primera comprobación: Webuzo aún no la suspende; se vuelve a mirar más tarde' );
digi_run_queue();
check( 'suspended' === $P::svc( $s, 'status' ) && 'suspended' === $GLOBALS['webuzo']['users']['lento']['status'], 'Cuando la lista la muestra suspendida, se marca suspendida' );
// 2b. Webuzo contesta «done» y nunca aplica: no se marca, se reenvía 3 veces y se avisa.
reset_all();
$s = provisioned( 'ignora.com' );
$GLOBALS['webuzo']['mode'] = 'ignora'; $GLOBALS['mails'] = array();
overdue_now( $s ); digi_run_queue( true, 200 );
$sends = 0; foreach ( $GLOBALS['webuzo']['calls'] as $c ) if ( isset( $c['fields']['suspend'] ) ) $sends++;
check( 'active' === $P::svc( $s, 'status' ) && '' === $GLOBALS['webuzo']['users']['ignora']['status'], 'Si Webuzo nunca aplica la suspensión, el servicio no se da por suspendido' );
check( 3 === $sends && false !== strpos( (string) $P::svc( $s, 'error' ), 'sigue activa' ), 'Se reenvía la orden 3 veces (falla reintentable) y queda el motivo' );
check( 1 === count( array_filter( digi_mails_to( 'admin@tienda.test' ), function ( $m ) { return false !== strpos( $m['subject'], 'no se suspendió' ); } ) ), 'Y se avisa al administrador' );
check( ! $W::any_block(), 'Nada de esto detiene Webuzo (no es un error de clave)' );

// 3. Pagó mientras la orden de suspender viajaba: al confirmarse suspendida, se reactiva enseguida.
reset_all();
$s = provisioned( 'viaje.com' );
$GLOBALS['webuzo']['mode'] = 'lento';
overdue_now( $s ); digi_run_queue( true, 1 ); // orden enviada, sin confirmar
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
$r->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 'active' === $P::svc( $s, 'status' ) && '' === $GLOBALS['webuzo']['users']['viaje']['status'] && (int) $P::svc( $s, 'expires' ) > time() + 300 * DAY_IN_SECONDS, 'Pagó con la suspensión en camino: queda activa en Webuzo y renovada' );

// 4. add_user devuelve la contraseña en claro: no sale del cliente.
reset_all();
$api = $W::from_settings();
$r = $api->create_user( 'clarito', 'clarito.com', 'Contraseña-Secreta-123!', 'a@b.test', 'digitalisimo_starter' );
check( 'ok' === $r['kind'] && 'El usuario se guardó con éxito' === $r['message'], 'Crear: el mensaje es done[\'msg\']' );
check( false === strpos( json_encode( $r ), 'Contraseña-Secreta-123' ) && false === strpos( json_encode( $r ), 'user_passwd' ), 'La contraseña que Webuzo devuelve en done.data.user_passwd no queda en el resultado' );

// 5. Errores reales de add_user: no detienen Webuzo.
reset_all();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( empty( $GLOBALS['err'] ) || false === strpos( $url, 'act=add_user' ) ) return $pre;
	$GLOBALS['webuzo']['calls'][] = array( 'act' => 'add_user', 'fields' => array(), 'url' => $url );
	return digi_json( array( 'title' => 'Webuzo - Panel de administrador', 'error' => $GLOBALS['err'], 'plans' => array() ) );
}, 5, 3 );
foreach ( array( array( 'pass_mismatch' => 'Las contraseñas ingresadas no coinciden.' ), array( 'inv_pri_domain' => 'Dominio inválido' ) ) as $i => $err ) {
	$GLOBALS['err'] = $err;
	$s = provisioned( 'error' . $i . '.com' );
	check( ! $W::any_block() && 'on-hold' === $P::svc( $s, 'status' ) && false !== strpos( (string) $P::svc( $s, 'error' ), current( $err ) ), 'Error real «' . key( $err ) . '»: sólo ese pedido en espera, sin parada de clave' );
}
$GLOBALS['err'] = null;

echo "Hosting, Webuzo real: {$GLOBALS['checks']} comprobaciones correctas.\n";
