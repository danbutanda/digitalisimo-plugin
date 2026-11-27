<?php
// Periodos de cobro del hosting (pedido del propietario del 2026-11-27: «el dominio siempre va anual; el hosting debe
// poder ser mensual, trimestral, semestral, anual, bianual, trianual»), con Webuzo y Namecheap simulados.
//
// Antes, cualquier variación vencía a un año y cada renovación sumaba otro año: quien compraba el mensual recibía un año
// y al renovar pagaba el precio mensual por otro año completo. Aquí, un plan variable con variaciones mensual, anual y
// trianual (el periodo en `_digitalisimo_hosting_period`):
//   1. mensual: vence en un mes; avisos a 7 y 3 días; el pedido de renovación lleva la MISMA variación con su precio;
//      al pagar suma un mes; la gracia es de 5 días como mucho; el dominio sigue anual y se renueva aparte;
//   2. anual: vence en un año, junto con el dominio (un solo pedido de renovación, como siempre);
//   3. trianual: vence en 36 meses y la renovación suma 36 meses; el dominio (anual) tiene su propio pedido, que sólo
//      renueva el dominio en el registrador y no toca el hosting.
// Uso: php tests/hosting-periods.php
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
function mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function near( $a, $b ) { return abs( (int) $a - (int) $b ) <= 120; }
function ymd( $t ) { return gmdate( 'Y-m-d', (int) $t ); }
function line_ids( $order ) { return array_map( function ( $i ) { return (int) ( $i->get_variation_id() ?: $i->get_product_id() ); }, $order->get_items() ); }

digi_servers_reset();
$GLOBALS['nc']['balance'] = 500;
digi_store_settings( array( 'grace_days' => 7 ) );
// «Plan Nube»: un producto variable de hosting (paquete en el producto) con una variación por periodo.
digi_variable( 70, 'Plan Nube', array(
	71 => array( 'Mensual', 150, array( '_digitalisimo_hosting_period' => 1 ) ),
	72 => array( 'Anual', 1500, array( '_digitalisimo_hosting_period' => 12 ) ),
	73 => array( 'Trianual', 3900, array( '_digitalisimo_hosting_period' => 36 ) ),
), array( '_digitalisimo_hosting_type' => 'hosting', '_digitalisimo_webuzo_plan' => 'digitalisimo_starter', '_digitalisimo_hosting_period' => 12 ) );

function buy( $variation, $domain ) {
	$o = digi_order( array( new Digi_Test_Item( $variation, 'Plan Nube' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => $domain ) ) ), array( 'email' => 'ana@' . $domain ) );
	$o->update_status( 'processing' ); digi_run_queue();
	check( 'completed' === $o->get_status(), "El pedido de $domain se entrega" );
	return (int) $o->get_meta( '_digitalisimo_service' );
}

// ── 1. Mensual ──────────────────────────────────────────────────────────────────────────────────────────────────
$t0 = time();
$GLOBALS['dom'] = 'mensual.com'; $s = buy( 71, 'mensual.com' );
$exp = (int) $P::svc( $s, 'expires' );
check( near( $exp, strtotime( '+1 month', $t0 ) ), 'Mensual: la compra vence en un mes (' . ymd( $exp ) . '), no en un año' );
check( 71 === (int) $P::svc( $s, 'hosting_product' ) && 1 === (int) $P::svc( $s, 'period' ), 'El servicio guarda la variación mensual y su periodo' );
check( near( $P::domain_due( $s ), strtotime( '+1 year', $t0 ) ) && isset( $GLOBALS['nc']['domains']['mensual.com'] ), 'El dominio se registra por un año aunque el hosting sea mensual' );
$GLOBALS['mails'] = array();
$P::daily( $exp - 8 * DAY_IN_SECONDS );
check( ! $P::svc( $s, 'renewal_order' ) && ! $GLOBALS['mails'], 'Mensual: a 8 días todavía no hay pedido ni aviso' );
$P::daily( $exp - 6 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( $r && array( 71 ) === line_ids( $r ) && 150.0 === (float) $r->total, 'Mensual: el pedido de renovación lleva la misma variación mensual con su precio (150), sin el dominio' );
$m = mails_to( 'ana@' . $GLOBALS['dom'] );
check( 1 === count( $m ) && 'Tu hosting vence en 7 días: mensual.com' === $m[0]['subject'] && false !== strpos( $m[0]['body'], '(mensual)' ) && false !== strpos( $m[0]['body'], 'se suspende 5 días después' ), 'Mensual: primer aviso a 7 días, dice el periodo y la gracia del mensual (5 días)' );
$P::daily( $exp - 2 * DAY_IN_SECONDS );
$m = mails_to( 'ana@' . $GLOBALS['dom'] );
check( 2 === count( $m ) && 'Tu hosting vence en 3 días: mensual.com' === $m[1]['subject'], 'Mensual: segundo aviso a 3 días' );
$r->update_status( 'processing' ); digi_run_queue();
$exp2 = (int) $P::svc( $s, 'expires' );
check( 'completed' === $r->get_status() && near( $exp2, strtotime( '+1 month', $exp ) ), 'Mensual: al pagar la renovación suma UN mes (' . ymd( $exp ) . ' → ' . ymd( $exp2 ) . ')' );
check( near( $P::domain_due( $s ), strtotime( '+1 year', $t0 ) ), 'Mensual: renovar el hosting no toca el vencimiento del dominio' );
// La gracia del mensual: 5 días como mucho (el ajuste dice 7).
$P::daily( $exp2 - 6 * DAY_IN_SECONDS );
$GLOBALS['queue'] = array();
$P::daily( $exp2 + 4 * DAY_IN_SECONDS );
check( ! $GLOBALS['queue'] && 'active' === $P::svc( $s, 'status' ), 'Mensual: dentro de los 5 días de gracia no se suspende' );
$P::daily( $exp2 + 6 * DAY_IN_SECONDS );
check( 1 === count( $GLOBALS['queue'] ) && 'digitalisimo_hosting_suspend' === $GLOBALS['queue'][0]['hook'], 'Mensual: pasados 5 días sin pago se suspende' );
$GLOBALS['queue'] = array();

// ── 2. Anual: hosting y dominio juntos, como siempre ────────────────────────────────────────────────────────────
$t0 = time();
$GLOBALS['dom'] = 'anual.com'; $s = buy( 72, 'anual.com' );
$exp = (int) $P::svc( $s, 'expires' );
check( near( $exp, strtotime( '+1 year', $t0 ) ) && 12 === (int) $P::svc( $s, 'period' ), 'Anual: vence en un año' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( $r && array( 72, 20 ) === line_ids( $r ) && ! $P::svc( $s, 'domain_renewal_order' ), 'Anual: un solo pedido de renovación con el plan anual y el dominio (vencen el mismo día)' );
$r->update_status( 'processing' ); digi_run_queue();
check( near( (int) $P::svc( $s, 'expires' ), strtotime( '+1 year', $exp ) ) && near( $P::domain_due( $s ), strtotime( '+1 year', $exp ) ), 'Anual: al pagar, hosting y dominio un año más' );

// ── 3. Trianual: 36 meses; el dominio anual se renueva aparte ───────────────────────────────────────────────────
$t0 = time();
$GLOBALS['dom'] = 'tres.com'; $s = buy( 73, 'tres.com' );
$exp = (int) $P::svc( $s, 'expires' );
check( near( $exp, strtotime( '+3 year', $t0 ) ) && 36 === (int) $P::svc( $s, 'period' ), 'Trianual: la compra vence en 36 meses (' . ymd( $exp ) . ')' );
$dd = $P::domain_due( $s );
check( near( $dd, strtotime( '+1 year', $t0 ) ), 'Trianual: el dominio vence en un año' );
$GLOBALS['mails'] = array();
$P::daily( $dd - 29 * DAY_IN_SECONDS );
$dr = wc_get_order( (int) $P::svc( $s, 'domain_renewal_order' ) );
check( $dr && array( 20 ) === line_ids( $dr ) && 'tres.com' === $dr->get_items()[0]->get_meta( 'Dominio' ) && ! $P::svc( $s, 'renewal_order' ), 'Trianual: al año, un pedido sólo para el dominio (el hosting no se cobra)' );
$m = mails_to( 'ana@' . $GLOBALS['dom'] );
check( 1 === count( $m ) && 'Tu dominio vence en 30 días: tres.com' === $m[0]['subject'] && false !== strpos( $m[0]['body'], 'por un año' ), 'Trianual: el aviso es del dominio y por un año' );
$renews = digi_nc_count( 'namecheap.domains.renew' );
$dr->update_status( 'processing' ); digi_run_queue();
check( 'completed' === $dr->get_status() && $renews + 1 === digi_nc_count( 'namecheap.domains.renew' ) && near( $P::domain_due( $s ), strtotime( '+1 year', $dd ) ), 'Trianual: pagar el dominio lo renueva en el registrador y le suma un año' );
check( $exp === (int) $P::svc( $s, 'expires' ) && 'active' === $P::svc( $s, 'status' ) && ! $P::svc( $s, 'domain_renewal_order' ), 'Trianual: renovar el dominio no cambia el vencimiento del hosting' );
// La renovación del trianual: la misma variación, su precio y 36 meses más.
$r = $P::renewal_order( $s );
check( $r && array( 73 ) === line_ids( $r ) && 3900.0 === (float) $r->total, 'Trianual: el pedido de renovación lleva la variación trianual con su precio (3900)' );
$r->update_status( 'processing' ); digi_run_queue();
check( near( (int) $P::svc( $s, 'expires' ), strtotime( '+3 year', $exp ) ), 'Trianual: al pagar suma 36 meses (' . ymd( $exp ) . ' → ' . ymd( (int) $P::svc( $s, 'expires' ) ) . ')' );

// ── 4. Meses exactos ────────────────────────────────────────────────────────────────────────────────────────────
check( '2027-02-28' === gmdate( 'Y-m-d', $P::add_months( gmmktime( 10, 0, 0, 1, 31, 2027 ), 1 ) ) && '2027-07-31' === gmdate( 'Y-m-d', $P::add_months( gmmktime( 10, 0, 0, 1, 31, 2027 ), 6 ) ) && '2028-01-31' === gmdate( 'Y-m-d', $P::add_months( gmmktime( 10, 0, 0, 10, 31, 2027 ), 3 ) ), 'Un mes desde el 31 de enero es el 28 de febrero (no el 3 de marzo); trimestre y semestre igual' );

echo "Hosting, periodos de cobro: {$GLOBALS['checks']} comprobaciones correctas.\n";
