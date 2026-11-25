<?php
/**
 * Sonda del ensayo de la venta automática de DIGITALÍSIMO Hosting (mu-plugin que copia setup.php).
 *
 * - Nada sale del sitio: Webuzo (https://webuzo.test:2005), Namecheap (api.namecheap.com), Banxico (SIE, serie
 *   SF43718) y Frankfurter los contesta aquí un servidor simulado por `pre_http_request`, con su estado en la opción
 *   `digi_hp_fake` (cuentas, dominios, saldo, precios, modos de falla) y cada llamada anotada sin claves; cualquier otro
 *   host recibe un error. El correo no sale: se guarda en /digi-probe/mails.json (lo borra el arnés al terminar).
 * - `?digi_hp=<paso>&token=<token>` (el de /digi-probe/token.txt): `store` (arma la tienda), `state` (pedido, notas,
 *   servicio, llamadas y correos, sin contraseñas), `pay` (marca un pedido como pagado, como el administrador con una
 *   transferencia), `run` (corre la cola de Action Scheduler del grupo digitalisimo-hosting), `fake` (cambia el modo
 *   del servidor simulado), `daily` (la revisión diaria con la fecha de prueba), `prices` (la corrida de precios),
 *   `screen` (la pantalla Servicios de hosting como administradora) y `key` (guarda una clave nueva de Webuzo como la
 *   pantalla de ajustes).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
const DIGI_HP_DIR = '/digi-probe';

register_shutdown_function( static function () {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( DIGI_HP_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
} );
set_error_handler( static function ( $severity, $message, $file ) {
	if ( false !== strpos( (string) $file, 'digitalisimo-hosting/' ) ) file_put_contents( DIGI_HP_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file\n", FILE_APPEND );
	return false;
} );

function digi_hp_fake() {
	$f = get_option( 'digi_hp_fake' );
	if ( ! is_array( $f ) ) $f = array( 'users' => array( 'otro' => array( 'user' => 'otro', 'domain' => 'otro-cliente.com', 'status' => '' ) ), 'domains' => array(), 'balance' => 80.0, 'price' => array( 'com' => array( 11.28, 15.98, 0.18 ), 'mx' => array( 23.98, 58.98, 0 ) ), 'webuzo' => 'ok', 'calls' => array(), 'bad_keys' => 0 );
	return $f;
}
function digi_hp_save( $f ) { update_option( 'digi_hp_fake', $f, false ); }
function digi_hp_json( $data, $code = 200 ) { return array( 'response' => array( 'code' => $code, 'message' => 'OK' ), 'body' => wp_json_encode( $data ), 'headers' => array(), 'cookies' => array(), 'filename' => null ); }
function digi_hp_xml( $inner, $ok = true ) { return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'cookies' => array(), 'filename' => null, 'body' => '<?xml version="1.0" encoding="utf-8"?><ApiResponse Status="' . ( $ok ? 'OK' : 'ERROR' ) . '" xmlns="http://api.namecheap.com/xml.response">' . ( $ok ? '<Errors /><CommandResponse>' . $inner . '</CommandResponse>' : '<Errors>' . $inner . '</Errors>' ) . '</ApiResponse>' ); }

add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = wp_parse_url( (string) $url, PHP_URL_HOST );
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host || $host === $home || in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) return $pre;
	$body = (array) ( $args['body'] ?? array() );
	$f = digi_hp_fake();
	$secrets = json_decode( (string) @file_get_contents( DIGI_HP_DIR . '/secrets.json' ), true ) ?: array();
	if ( 'webuzo.test' === $host ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
		$act = (string) ( $q['act'] ?? '' );
		$fields = $body; unset( $fields['apikey'], $fields['user_passwd'], $fields['cnf_user_passwd'] );
		$f['calls'][] = array( 'api' => 'webuzo', 'act' => $act, 'fields' => $fields, 'key_in_url' => false !== strpos( $url, (string) ( $body['apikey'] ?? '§' ) ) );
		if ( 'auth' === $f['webuzo'] || ( $body['apikey'] ?? '' ) !== ( $secrets['webuzo'] ?? '' ) ) { $f['bad_keys']++; digi_hp_save( $f ); return digi_hp_json( array( 'error' => array( 'Incorrect Username or Password. 1 attempt(s) left' ) ) ); }
		// Como el Webuzo real (prueba autorizada del 2026-11-24): con skip=1 contesta éxito sin cambiar nada.
		if ( 'users' === $act && isset( $body['suspend'] ) ) { if ( empty( $body['skip'] ) ) $f['users'][ $body['suspend'] ]['status'] = 'suspended'; digi_hp_save( $f ); return digi_hp_json( array( 'done' => array( 'msg' => 'Usuario suspendido con éxito' ), 'users' => $f['users'], 'num_res' => count( $f['users'] ), 'reslen' => 50, 'cur_page' => 0 ) ); }
		if ( 'users' === $act && isset( $body['unsuspend'] ) ) { if ( empty( $body['skip'] ) ) $f['users'][ $body['unsuspend'] ]['status'] = ''; digi_hp_save( $f ); return digi_hp_json( array( 'done' => array( 'msg' => 'Usuario reactivado con éxito' ), 'users' => $f['users'], 'num_res' => count( $f['users'] ), 'reslen' => 50, 'cur_page' => 0 ) ); }
		if ( 'users' === $act ) { digi_hp_save( $f ); return digi_hp_json( array( 'users' => $f['users'] ) ); }
		if ( 'add_user' === $act ) {
			if ( isset( $f['users'][ $body['user'] ] ) ) { digi_hp_save( $f ); return digi_hp_json( array( 'error' => array( 'The username already exists' ) ) ); }
			$f['users'][ $body['user'] ] = array( 'user' => $body['user'], 'domain' => $body['domain'], 'email' => $body['email'], 'plan' => $body['plan'], 'status' => '', 'pass_sha' => hash( 'sha256', (string) $body['user_passwd'] ), 'pass_ok' => $body['user_passwd'] === $body['cnf_user_passwd'] && strlen( $body['user_passwd'] ) >= 12 );
			// La contraseña real sólo para comprobar el correo; el arnés la borra al terminar.
			file_put_contents( DIGI_HP_DIR . '/passwords.json', wp_json_encode( array( $body['user'] => $body['user_passwd'] ) + ( json_decode( (string) @file_get_contents( DIGI_HP_DIR . '/passwords.json' ), true ) ?: array() ) ) );
			digi_hp_save( $f );
			return digi_hp_json( array( 'done' => array( 'msg' => 'The User has been saved successfully' ) ) );
		}
		digi_hp_save( $f );
		return digi_hp_json( array( 'error' => array( 'Unknown act' ) ) );
	}
	if ( 'api.namecheap.com' === $host || 'api.sandbox.namecheap.com' === $host ) {
		$cmd = (string) ( $body['Command'] ?? '' );
		$fields = $body; unset( $fields['ApiKey'] );
		$f['calls'][] = array( 'api' => 'namecheap', 'cmd' => $cmd, 'fields' => $fields, 'key_ok' => ( $body['ApiKey'] ?? '' ) === ( $secrets['namecheap'] ?? '' ) );
		$tld = function ( $d ) { return substr( $d, strpos( $d, '.' ) + 1 ); };
		$cost = function ( $t, $action ) use ( $f ) { $p = $f['price'][ $t ] ?? array( 9.99, 9.99, 0 ); return ( 'RENEW' === $action ? $p[1] : $p[0] ) + $p[2]; };
		$res = digi_hp_xml( '<Error Number="1010000">Unknown command</Error>', false );
		switch ( $cmd ) {
			case 'namecheap.users.getBalances': $res = digi_hp_xml( '<UserGetBalancesResult Currency="USD" AvailableBalance="' . number_format( $f['balance'], 2, '.', '' ) . '" />' ); break;
			case 'namecheap.users.getPricing':
				$p = $f['price'][ $body['ProductName'] ] ?? array( 9.99, 9.99, 0 ); $your = 'RENEW' === $body['ActionName'] ? $p[1] : $p[0];
				$res = digi_hp_xml( '<UserGetPricingResult><ProductType Name="domains"><ProductCategory Name="' . strtolower( $body['ActionName'] ) . '"><Product Name="' . esc_attr( $body['ProductName'] ) . '"><Price Duration="1" DurationType="YEAR" Price="' . ( $your + 3 ) . '" PricingType="MULTIPLE" AdditionalCost="' . $p[2] . '" YourPrice="' . $your . '" YourPriceType="MULTIPLE" YourAdditonalCost="' . $p[2] . '" Currency="USD" /></Product></ProductCategory></ProductType></UserGetPricingResult>' );
				break;
			case 'namecheap.domains.getInfo':
				$d = $body['DomainName'];
				$res = isset( $f['domains'][ $d ] ) ? digi_hp_xml( '<DomainGetInfoResult Status="Ok" DomainName="' . esc_attr( $d ) . '"><DomainDetails><ExpiredDate>' . gmdate( 'm/d/Y', $f['domains'][ $d ] ) . '</ExpiredDate></DomainDetails></DomainGetInfoResult>' ) : digi_hp_xml( '<Error Number="2019166">Domain not found</Error>', false );
				break;
			case 'namecheap.domains.create':
				$d = $body['DomainName']; $c = $cost( $tld( $d ), 'REGISTER' );
				if ( $f['balance'] < $c ) { $res = digi_hp_xml( '<Error Number="2528166">Insufficient funds</Error>', false ); break; }
				$f['balance'] -= $c; $f['domains'][ $d ] = strtotime( '+1 year' );
				$res = digi_hp_xml( '<DomainCreateResult Domain="' . esc_attr( $d ) . '" Registered="true" ChargedAmount="' . number_format( $c, 2, '.', '' ) . '" DomainID="' . ( 7000 + count( $f['domains'] ) ) . '" OrderID="1" TransactionID="1" WhoisguardEnable="false" NonRealTimeDomain="false" />' );
				break;
			case 'namecheap.domains.dns.setCustom':
				$f['dns'][ $body['SLD'] . '.' . $body['TLD'] ] = $body['Nameservers'];
				$res = digi_hp_xml( '<DomainDNSSetCustomResult Domain="' . esc_attr( $body['SLD'] . '.' . $body['TLD'] ) . '" Updated="true" />' );
				break;
			case 'namecheap.domains.renew':
				$d = $body['DomainName']; $c = $cost( $tld( $d ), 'RENEW' );
				$f['balance'] -= $c; $f['domains'][ $d ] = strtotime( '+1 year', $f['domains'][ $d ] );
				$res = digi_hp_xml( '<DomainRenewResult DomainName="' . esc_attr( $d ) . '" DomainID="1" Renew="true" OrderID="2" TransactionID="2" ChargedAmount="' . number_format( $c, 2, '.', '' ) . '" />' );
				break;
		}
		digi_hp_save( $f );
		return $res;
	}
	if ( 'www.banxico.org.mx' === $host ) {
		$f['calls'][] = array( 'api' => 'banxico', 'token_ok' => ( $args['headers']['Bmx-Token'] ?? '' ) === ( $secrets['banxico'] ?? '' ) );
		digi_hp_save( $f );
		if ( ! empty( $f['banxico_down'] ) ) return new WP_Error( 'http_request_failed', 'cURL error 28' );
		return digi_hp_json( array( 'bmx' => array( 'series' => array( array( 'idSerie' => 'SF43718', 'titulo' => 'FIX', 'datos' => array( array( 'fecha' => '21/11/2026', 'dato' => '18.5000' ) ) ) ) ) ) );
	}
	if ( 'api.frankfurter.dev' === $host ) {
		$f['calls'][] = array( 'api' => 'frankfurter' ); digi_hp_save( $f );
		return digi_hp_json( array( 'date' => '2026-11-21', 'base' => 'USD', 'quote' => 'MXN', 'rate' => 18.6 ) );
	}
	return new WP_Error( 'digi_hp_offline', 'Sin red en el ensayo.' );
}, 10, 3 );

// La cola de Action Scheduler sólo corre con el paso «run» de la sonda: sin el corredor asíncrono (petición de vuelta
// al cerrar) ni WP-Cron, que la procesarían a destiempo y harían el ensayo no determinista.
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_action( 'wp_loaded', static function () { if ( class_exists( 'ActionScheduler' ) ) remove_action( 'action_scheduler_run_queue', array( ActionScheduler::runner(), 'run' ) ); } );
add_filter( 'pre_wp_mail', static function ( $null, $atts ) {
	$mails = json_decode( (string) @file_get_contents( DIGI_HP_DIR . '/mails.json' ), true ) ?: array();
	$mails[] = array( 'to' => is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'], 'subject' => (string) $atts['subject'], 'body' => (string) $atts['message'] );
	file_put_contents( DIGI_HP_DIR . '/mails.json', wp_json_encode( $mails ) );
	return true;
}, 10, 2 );

add_action( 'init', static function () {
	if ( empty( $_GET['digi_hp'] ) ) return;
	$token = trim( (string) @file_get_contents( DIGI_HP_DIR . '/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit; }
	$step = sanitize_key( $_GET['digi_hp'] );
	$id = absint( $_GET['id'] ?? 0 );
	$P = 'Digitalisimo_Hosting_Provisioning';
	$out = array( 'step' => $step );
	switch ( $step ) {
		case 'store':
			update_option( 'woocommerce_currency', 'MXN' );
			update_option( 'woocommerce_default_country', 'MX:JA' );
			update_option( 'woocommerce_calc_taxes', 'yes' ); // como digitalisimo.lat (IVA 16 % a México, precios sin IVA)
			update_option( 'woocommerce_prices_include_tax', 'no' );
			update_option( 'woocommerce_enable_guest_checkout', 'yes' );
			update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes', 'title' => 'Cheque', 'description' => '', 'instructions' => '' ) );
			update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Transferencia', 'description' => '', 'instructions' => '' ) );
			update_option( 'admin_email', 'admin@tienda.test' );
			$term = wp_insert_term( 'Hosting', 'product_cat' );
			$cat  = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
			$make = static function ( $name, $price, $plan = '', $cats = array() ) {
				$p = new WC_Product_Simple();
				$p->set_name( $name ); $p->set_status( 'publish' ); $p->set_virtual( true ); $p->set_regular_price( (string) $price ); $p->set_category_ids( $cats );
				$id = $p->save();
				if ( $plan ) update_post_meta( $id, '_digitalisimo_webuzo_plan', $plan );
				return $id;
			};
			$ids = array( 'starter' => $make( 'Web Hosting Starter', 970, 'digitalisimo_starter', array( $cat ) ), 'pro' => $make( 'Web Hosting Pro', 5850, 'digitalisimo_pro', array( $cat ) ), 'com' => $make( 'Dominio .com', 300 ), 'mx' => $make( 'Dominio .mx', 600 ) );
			update_option( 'digitalisimo_hosting_options', array(
				'enabled' => 1, 'provision_enabled' => 1, 'provision_since' => time() - 60, 'inherit_network' => 0,
				'api_user' => 'nc-ensayo', 'username' => 'nc-ensayo', 'api_key' => 'NC-' . wp_generate_password( 24, false ), 'client_ip' => '203.0.113.5',
				'domain_products' => ".com|{$ids['com']}\n.mx|{$ids['mx']}", 'hosting_product_ids' => '', 'hosting_category_ids' => (string) $cat,
				'webuzo_url' => 'https://webuzo.test:2005', 'webuzo_api_user' => 'root', 'webuzo_api_key' => 'WZ-' . wp_generate_password( 24, false ),
				'nameservers' => 'ns1.digitalisimo.website, ns2.digitalisimo.website', 'grace_days' => 7,
				'pricing_enabled' => 1, 'banxico_token' => 'BX-' . wp_generate_password( 24, false ), 'pricing_cushion' => 3, 'pricing_margin' => 25, 'pricing_fee' => 3.6, 'pricing_vat' => 16, 'pricing_min_margin' => 10, 'pricing_rounding' => '10',
			), false );
			$o = get_option( 'digitalisimo_hosting_options' );
			file_put_contents( DIGI_HP_DIR . '/secrets.json', wp_json_encode( array( 'webuzo' => $o['webuzo_api_key'], 'namecheap' => $o['api_key'], 'banxico' => $o['banxico_token'] ) ) );
			$out += array( 'products' => $ids, 'category' => $cat, 'woocommerce' => WC()->version, 'gateways' => array_keys( WC()->payment_gateways()->get_available_payment_gateways() ) );
			break;
		case 'pay':
			$order = wc_get_order( $id );
			// Cheque y transferencia son pagos fuera de línea: el administrador confirma el cobro marcándolo «completado».
			$order->update_status( 'completed', 'Pago por transferencia confirmado (ensayo).' );
			$out['status'] = $order->get_status();
			break;
		case 'run':
			$ran = 0; $all = ! empty( $_GET['all'] );
			for ( $round = 0; $round < 10; $round++ ) {
				$ids = as_get_scheduled_actions( array( 'group' => $P::GROUP, 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => 50, 'orderby' => 'date', 'order' => 'ASC' ), 'ids' );
				$did = 0;
				foreach ( $ids as $aid ) {
					$action = ActionScheduler::store()->fetch_action( $aid );
					$date = $action->get_schedule() ? $action->get_schedule()->get_date() : null;
					if ( ! $all && $date && $date->getTimestamp() > time() ) continue;
					if ( $action->get_schedule() instanceof ActionScheduler_IntervalSchedule || $action->get_schedule() instanceof ActionScheduler_CronSchedule ) { if ( ! $all ) continue; }
					ActionScheduler::runner()->process_action( $aid, 'digi-hp' ); $ran++; $did++;
				}
				if ( ! $did ) break;
			}
			$out['ran'] = $ran;
			$out['pending'] = count( as_get_scheduled_actions( array( 'group' => $P::GROUP, 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => 50 ), 'ids' ) );
			$out['hooks'] = array_values( array_map( static function ( $a ) { return $a->get_hook(); }, as_get_scheduled_actions( array( 'group' => $P::GROUP, 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => 50 ) ) ) );
			break;
		case 'fake':
			$f = digi_hp_fake();
			foreach ( array( 'webuzo', 'balance', 'banxico_down' ) as $k ) if ( isset( $_GET[ $k ] ) ) $f[ $k ] = 'balance' === $k ? (float) $_GET[ $k ] : sanitize_key( $_GET[ $k ] );
			if ( isset( $_GET['mx_register'] ) ) $f['price']['mx'][0] = (float) $_GET['mx_register'];
			digi_hp_save( $f );
			$out['fake'] = array_diff_key( $f, array( 'calls' => 1 ) );
			break;
		case 'daily':
			$svc = absint( $_GET['svc'] ?? 0 );
			$now = (int) $P::svc( $svc, 'expires' ) + (int) ( $_GET['offset'] ?? 0 ) * DAY_IN_SECONDS;
			$P::daily( $now );
			$out['now'] = $now;
			break;
		case 'prices':
			$out['entry'] = Digitalisimo_Hosting_Pricing::run();
			break;
		case 'key':
			// Lo mismo que «Guardar» con una clave escrita en Digitalisimo → Hosting.
			$secrets = json_decode( (string) file_get_contents( DIGI_HP_DIR . '/secrets.json' ), true );
			$o = get_option( 'digitalisimo_hosting_options' ); $old = $o['webuzo_api_key']; $o['webuzo_api_key'] = $secrets['webuzo']; update_option( 'digitalisimo_hosting_options', $o, false );
			Digitalisimo_Hosting_Webuzo::forget( $old );
			Digitalisimo_Hosting_Webuzo::resume();
			break;
		case 'badkey':
			$o = get_option( 'digitalisimo_hosting_options' ); $o['webuzo_api_key'] = 'CLAVE-VIEJA-' . wp_generate_password( 8, false ); update_option( 'digitalisimo_hosting_options', $o, false );
			break;
		case 'screen':
			wp_set_current_user( 1 );
			require_once ABSPATH . 'wp-admin/includes/template.php';
			ob_start(); Digitalisimo_Hosting_Services_Page::render(); $out['html'] = ob_get_clean();
			break;
		case 'state':
		default:
			$order = $id ? wc_get_order( $id ) : null;
			if ( $order ) {
				$notes = wc_get_order_notes( array( 'order_id' => $id, 'order' => 'ASC' ) );
				$out['order'] = array( 'status' => $order->get_status(), 'notes' => array_map( static function ( $n ) { return array( 'text' => $n->content, 'customer' => (bool) $n->customer_note ); }, $notes ), 'meta' => wp_json_encode( $order->get_meta_data() ), 'service' => (int) $order->get_meta( '_digitalisimo_service' ), 'renewal_of' => (int) $order->get_meta( '_digitalisimo_renewal_of' ), 'total' => $order->get_total(), 'pay_url' => $order->get_checkout_payment_url(), 'items' => array_values( array_map( static function ( $i ) { return array( 'product' => $i->get_product_id(), 'domain' => $i->get_meta( 'Dominio' ), 'total' => $i->get_total() ); }, $order->get_items() ) ) );
			}
			$svc = absint( $_GET['svc'] ?? ( $order ? (int) $order->get_meta( '_digitalisimo_service' ) : 0 ) );
			if ( $svc ) foreach ( array( 'status', 'domain', 'plan', 'webuzo_user', 'expires', 'renewal_order', 'error', 'domain_registered' ) as $k ) $out['service'][ $k ] = $P::svc( $svc, $k );
			if ( $svc ) $out['service']['has_pass'] = '' !== (string) $P::svc( $svc, 'pass' );
			$f = digi_hp_fake();
			$out['calls'] = $f['calls']; $out['users'] = $f['users']; $out['domains'] = $f['domains']; $out['dns'] = $f['dns'] ?? array(); $out['bad_keys'] = $f['bad_keys'];
			$out['mails'] = json_decode( (string) @file_get_contents( DIGI_HP_DIR . '/mails.json' ), true ) ?: array();
			$out['block'] = get_site_option( Digitalisimo_Hosting_Webuzo::BLOCK );
			$out['prices'] = array();
			foreach ( Digitalisimo_Hosting::domain_products() as $ext => $pid ) { $p = wc_get_product( $pid ); $out['prices'][ $ext ] = $p ? $p->get_regular_price() : null; }
			$out['history'] = Digitalisimo_Hosting_Pricing::history();
			$out['recurring'] = (bool) as_has_scheduled_action( Digitalisimo_Hosting_Pricing::HOOK );
	}
	wp_send_json( $out );
}, 99 );
