<?php
// Migración de la configuración con IDs y «|» de DIGITALÍSIMO Hosting (pedido del propietario del 2026-11-27), sin
// WordPress. Una tienda configurada como hasta 1.0.37 (`domain_products` .com|ID, una línea sin ID resuelta por la marca
// y otra por el SKU, transferencia por SKU, `registrar_by_tld` .mx|dynadot, IDs y categorías de hosting, un plan sólo con
// su paquete de Webuzo, `finder_upsell_ids`, `finder_requirements` y `finder_order`):
//   1. después de migrar se comporta igual: las mismas extensiones con los mismos productos, la transferencia, el
//      registrador de cada extensión, los planes, «Se compran juntos», los avisos y el orden;
//   2. los ajustes quedan sin IDs ni «|» (lo viejo se guarda aparte, de respaldo) y los productos con su tipo;
//   3. un producto marcado con una extensión que la lista con IDs no usaba sigue sin venderse («Ninguno»);
//   4. una segunda corrida no cambia nada (ni forzada, sin la marca de hecho);
//   5. multisitio: cada sitio migra lo suyo, una vez; la opción de la red no se toca;
// Uso: php tests/hosting-products-migration.php
require __DIR__ . '/fixtures/hosting-wp.php';
$H = 'Digitalisimo_Hosting'; $PR = 'Digitalisimo_Hosting_Products'; $F = 'Digitalisimo_Hosting_Finder'; $R = 'Digitalisimo_Hosting_Registrars';

/** Una tienda como la dejaba la versión anterior: sin tipos en los productos, todo en los ajustes. */
function legacy_store() {
	digi_servers_reset();
	digi_store_settings();
	foreach ( array_keys( $GLOBALS['postmeta'] ) as $id ) if ( isset( $GLOBALS['products'][ $id ] ) ) unset( $GLOBALS['postmeta'][ $id ] );
	delete_option( 'digitalisimo_hosting_products_migrated' ); delete_option( Digitalisimo_Hosting_Products::BACKUP );
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' ); update_post_meta( 14696, '_digitalisimo_webuzo_plan', 'digitalisimo_pro' );
	digi_product( 22, 'Dominio .net', 280 ); $GLOBALS['products'][22]->sku = 'dominio-net';
	digi_product( 25, 'Dominio .org (marcado y sin usar)', 300 ); update_post_meta( 25, '_digitalisimo_tld', '.org' );
	digi_product( 26, 'Dominio .com viejo', 250 ); update_post_meta( 26, '_digitalisimo_tld', '.com' );
	digi_product( 50, 'Transferencia de dominio', 400 ); $GLOBALS['products'][50]->sku = 'transferencia-dominio';
	digi_product( 14694, 'Correo empresarial', 600 ); $GLOBALS['product_cats'][14694] = array( 63 );
	update_post_meta( 21, '_digitalisimo_tld', '.mx' );
	$o = get_option( 'digitalisimo_hosting_options' );
	$o += array(
		'domain_products'      => ".com|20\n.mx|\n.net|",
		'registrar_by_tld'     => ".mx|dynadot\n.com.mx|dynadot",
		'hosting_product_ids'  => '14693',
		'hosting_category_ids' => '63',
		'finder_upsell_ids'    => '14696, 14693',
		'finder_requirements'  => ".mx|Requisito MX\n.net|Requisito NET",
		'finder_order'         => '.mx, .com',
		'dynadot_api_key'      => 'CLAVE-DYNADOT',
	);
	update_option( 'digitalisimo_hosting_options', $o );
	Digitalisimo_Hosting_Products::flush(); Digitalisimo_Hosting_Lists::flush();
}
function snapshot() { $o = $GLOBALS['options']; unset( $o['digitalisimo_hosting_products_migrated'] ); return serialize( array( $o, $GLOBALS['postmeta'] ) ); }

// ── 1 y 2. Mismo comportamiento ───────────────────────────────────────────────────────────────────────────────
legacy_store();
// Lo que hacía la versión anterior con estos ajustes (escrito a mano: así resolvía Digitalisimo_Hosting_Products).
$before_map = array( '.com' => 20, '.mx' => 21, '.net' => 22 );
check( true === $PR::migrate(), 'La migración corre una vez' );
$o = get_option( 'digitalisimo_hosting_options' );
check( $before_map === $H::domain_products(), 'Las mismas extensiones con los mismos productos: .com (por ID), .mx (por la marca) y .net (por el SKU)' );
check( array( '*' => 50 ) === $PR::transfers() && 'transfer' === Digitalisimo_Hosting_Checkout::kind( 50 ), 'La transferencia (por SKU) sigue igual' );
check( 'dynadot' === $R::id_for_ext( '.mx' ) && 'dynadot' === $R::id_for_ext( '.com.mx' ) && 'namecheap' === $R::id_for_ext( '.com' ) && array( '.mx' => 'dynadot', '.com.mx' => 'dynadot' ) === $o['registrar_by_tld'], '«Registrador por extensión» pasa a filas: .mx y .com.mx con Dynadot' );
foreach ( array( 14693, 14694, 14696 ) as $id ) check( 'hosting' === Digitalisimo_Hosting_Checkout::kind( $id ) && 12 === $PR::period( $id ), "El producto {$id} sigue siendo hosting (por ID, por categoría o por su paquete), anual como antes" );
check( array( 14696, 14693 ) === $o['finder_upsell'] && array( 14696, 14693 ) === array_column( $F::together(), 'id' ), '«Se compran juntos»: los mismos planes, en el mismo orden' );
check( array( '.mx' => 'Requisito MX', '.net' => 'Requisito NET' ) === $F::requirements() && array( '.mx', '.com', '.net' ) === $F::ordered(), 'Los avisos de requisitos y el orden siguen iguales' );
foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'finder_upsell_ids' ) as $k ) check( ! array_key_exists( $k, $o ), "«{$k}» sale de los ajustes" );
check( false === strpos( serialize( $o ), '|' ) && is_array( $o['finder_requirements'] ) && is_array( $o['finder_order'] ), 'Los ajustes quedan sin «|»' );
$b = get_option( $PR::BACKUP );
check( ".com|20\n.mx|\n.net|" === $b['domain_products'] && '14693' === $b['hosting_product_ids'] && ".mx|dynadot\n.com.mx|dynadot" === $b['registrar_by_tld'], 'Lo viejo queda guardado aparte, tal cual, de respaldo' );
check( 'domain' === get_post_meta( 20, '_digitalisimo_hosting_type', true ) && '.com' === get_post_meta( 20, '_digitalisimo_tld', true ) && 'register' === get_post_meta( 22, '_digitalisimo_domain_kind', true ) && 'hosting' === get_post_meta( 14694, '_digitalisimo_hosting_type', true ), 'Cada producto queda marcado con su tipo, como si lo hubieran elegido en el editor' );

// ── 3. Lo que no se vendía sigue sin venderse ─────────────────────────────────────────────────────────────────
check( 'none' === get_post_meta( 25, '_digitalisimo_hosting_type', true ) && 'none' === get_post_meta( 26, '_digitalisimo_hosting_type', true ) && ! isset( $H::domain_products()['.org'] ) && ! $PR::catalog()['duplicates'], 'Un .org marcado que la lista no usaba y un .com viejo que perdía contra el ID quedan en «Ninguno»' );

// ── 4. Idempotente ────────────────────────────────────────────────────────────────────────────────────────────
$snap = snapshot();
check( false === $PR::migrate() && $snap === snapshot(), 'Una segunda corrida no cambia nada' );
delete_option( 'digitalisimo_hosting_products_migrated' ); $PR::flush();
$PR::migrate();
check( $snap === snapshot() && $before_map === $H::domain_products(), 'Forzada otra vez (sin la marca de hecho), tampoco cambia nada' );
// Lo que el dueño cambie después en el editor no lo deshace una corrida más.
update_post_meta( 22, '_digitalisimo_hosting_type', 'none' ); delete_option( 'digitalisimo_hosting_products_migrated' ); $PR::migrate();
check( 'none' === get_post_meta( 22, '_digitalisimo_hosting_type', true ), 'Un producto que ya tiene tipo nunca se toca' );
update_post_meta( 22, '_digitalisimo_hosting_type', 'domain' );

// ── 5. Multisitio ─────────────────────────────────────────────────────────────────────────────────────────────
legacy_store();
$GLOBALS['multisite'] = true;
$GLOBALS['site_options'] = array( 'digitalisimo_hosting_options' => array( 'api_user' => 'red', 'domain_products' => '.com|999', 'registrar_by_tld' => '.com|dynadot' ) );
$network = serialize( $GLOBALS['site_options'] );
$site1 = $GLOBALS['options'];
switch_blog( 2 );
$GLOBALS['options'] = array( 'digitalisimo_hosting_ecommerce_migrated' => 1, 'digitalisimo_hosting_options' => array( 'enabled' => 1, 'domain_products' => '.com|20', 'registrar_by_tld' => '.com|dynadot', 'finder_order' => '.com', 'finder_upsell_ids' => '14696' ) );
check( true === $PR::migrate(), 'El sitio 2 migra lo suyo' );
$o2 = get_option( 'digitalisimo_hosting_options' );
check( array( '.com' => 'dynadot' ) === $o2['registrar_by_tld'] && array( '.com' ) === $o2['finder_order'] && array( 14696 ) === $o2['finder_upsell'] && ! isset( $o2['domain_products'] ) && get_option( 'digitalisimo_hosting_products_migrated' ), 'Sitio 2: sus filas, su orden y sus planes; su marca de hecho' );
check( false === $PR::migrate(), 'Sitio 2: una segunda corrida no hace nada' );
switch_blog( 1 );
check( $site1 === $GLOBALS['options'] && ! get_option( 'digitalisimo_hosting_products_migrated' ), 'El sitio 1 no se tocó: migra cuando le toque' );
check( true === $PR::migrate() && array( '.mx' => 'dynadot', '.com.mx' => 'dynadot' ) === get_option( 'digitalisimo_hosting_options' )['registrar_by_tld'], 'El sitio 1 migra lo suyo (no lo del sitio 2)' );
check( $network === serialize( $GLOBALS['site_options'] ), 'La opción de la red no se toca' );
$GLOBALS['multisite'] = false; $GLOBALS['site_options'] = array();

// ── 6. MCP con la tienda migrada ──────────────────────────────────────────────────────────────────────────────
$json = wp_json_encode( $H::mcp_settings() );
check( false === strpos( $json, '|' ) && ! preg_match( '/"[a-z_]*_ids"/', $json ), 'Después de migrar, el MCP no expone «|» ni claves de IDs' );

echo "Hosting, migración de productos y ajustes sin IDs: {$GLOBALS['checks']} comprobaciones correctas.\n";
