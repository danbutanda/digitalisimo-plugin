<?php
/**
 * Sonda del ensayo del dominio antes de pagar y de Hosting en el MCP (mu-plugin que copia setup.php).
 *
 * - Nada sale del sitio: Namecheap (api.namecheap.com) lo contesta aquí un servidor simulado por `pre_http_request`
 *   (domains.check, domains.getTldList, domains.getInfo), con cada llamada anotada sin la clave en la opción
 *   `digi_hc_calls`; cualquier otro host recibe un error. El correo no sale.
 * - `?digi_hc=<paso>&token=<token>` (el de /digi-probe/token.txt): `store` (arma la tienda: plan, dominios con SKU
 *   dominio-com/-net/-mx, sin IDs en «Productos por extensión», transferencia sin marcar, cuenta de Namecheap y claves
 *   inventadas, y las contraseñas de aplicación de una administradora y de una suscriptora), `option` (lo guardado de
 *   Hosting comparado con los secretos, sin devolverlos), `state` (un pedido: líneas con su meta, claves de meta del
 *   pedido, si el EPP guardado descifra al del ensayo, notas, y las claves de meta que da la REST v3 de pedidos), `page`
 *   (crea la página atómica con el formulario e-form, [resultado_dominio] y [digitalisimo_domain_search]) y `calls`.
 * Los secretos del ensayo quedan en /digi-probe/secrets.json (el arnés los busca en los resultados y los borra al final).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
const DIGI_HC_DIR = '/digi-probe';

register_shutdown_function( static function () {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( DIGI_HC_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
} );
set_error_handler( static function ( $severity, $message, $file ) {
	if ( false !== strpos( (string) $file, 'digitalisimo-hosting/' ) || false !== strpos( (string) $file, 'digitalisimo-elements/modules/digitalisimo-hosting/' ) ) file_put_contents( DIGI_HC_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file\n", FILE_APPEND );
	return false;
} );
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

function digi_hc_secrets() { return json_decode( (string) @file_get_contents( DIGI_HC_DIR . '/secrets.json' ), true ) ?: array(); }
function digi_hc_xml( $inner, $ok = true ) { return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'cookies' => array(), 'filename' => null, 'body' => '<?xml version="1.0" encoding="utf-8"?><ApiResponse Status="' . ( $ok ? 'OK' : 'ERROR' ) . '" xmlns="http://api.namecheap.com/xml.response">' . ( $ok ? '<Errors /><CommandResponse>' . $inner . '</CommandResponse>' : '<Errors>' . $inner . '</Errors>' ) . '</ApiResponse>' ); }

add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = wp_parse_url( (string) $url, PHP_URL_HOST );
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host || $host === $home || in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) return $pre;
	// Tipo de cambio para el freno de pérdida (sin token de Banxico se usa Frankfurter).
	if ( 'api.frankfurter.dev' === $host ) return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'cookies' => array(), 'filename' => null, 'body' => wp_json_encode( array( 'date' => gmdate( 'Y-m-d' ), 'base' => 'USD', 'quote' => 'MXN', 'rate' => 18.5 ) ) );
	if ( 'api.namecheap.com' !== $host ) return new WP_Error( 'digi_hc_offline', 'Sin red en el ensayo.' );
	$body = (array) ( $args['body'] ?? array() );
	$cmd = (string) ( $body['Command'] ?? '' );
	$fields = $body; unset( $fields['ApiKey'] );
	$calls = get_option( 'digi_hc_calls', array() );
	$calls[] = array( 'cmd' => $cmd, 'fields' => $fields, 'key_ok' => ( $body['ApiKey'] ?? '' ) === ( digi_hc_secrets()['namecheap'] ?? '§' ), 'key_in_url' => false !== strpos( (string) $url, 'ApiKey' ), 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ) );
	update_option( 'digi_hc_calls', $calls, false );
	$taken = array( 'ocupado.com', 'miempresa.com', 'ajeno.com', 'ajeno.mx', 'ajeno.lat', 'ajeno.net' );
	switch ( $cmd ) {
		case 'namecheap.domains.check':
			$d = (string) $body['DomainList'];
			return digi_hc_xml( '<DomainCheckResult Domain="' . esc_attr( $d ) . '" Available="' . ( in_array( $d, $taken, true ) ? 'false' : 'true' ) . '" IsPremiumName="false" />' );
		case 'namecheap.domains.getTldList':
			$out = '';
			foreach ( array( 'com', 'net', 'org', 'mx' ) as $t ) $out .= '<Tld Name="' . $t . '" IsApiRegisterable="true" IsApiTransferable="' . ( 'mx' === $t ? 'false' : 'true' ) . '" IsEppRequired="true">' . $t . '</Tld>';
			return digi_hc_xml( '<Tlds>' . $out . '</Tlds>' );
		case 'namecheap.users.getPricing':
			// .com cuesta 9.99 USD en todo; «caro» se simula con .net: 60 USD por transferir.
			$tld = (string) $body['ProductName']; $your = 'net' === $tld && 'TRANSFER' === $body['ActionName'] ? 60 : 9.99;
			return digi_hc_xml( '<UserGetPricingResult><ProductType Name="domains"><ProductCategory Name="' . esc_attr( strtolower( $body['ActionName'] ) ) . '"><Product Name="' . esc_attr( $tld ) . '"><Price Duration="1" DurationType="YEAR" Price="' . ( $your + 1 ) . '" YourPrice="' . $your . '" YourAdditonalCost="0.18" Currency="USD" /></Product></ProductCategory></ProductType></UserGetPricingResult>' );
		case 'namecheap.domains.getInfo':
			return digi_hc_xml( '<Error Number="2019166">Domain not found</Error>', false );
	}
	return digi_hc_xml( '<Error Number="1010000">Unknown command</Error>', false );
}, 10, 3 );

function digi_hc_out( array $data, int $status = 200 ) { status_header( $status ); header( 'Content-Type: application/json; charset=utf-8' ); echo wp_json_encode( $data ); exit; }

add_action( 'init', static function () {
	if ( ! isset( $_GET['digi_hc'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$token = trim( (string) @file_get_contents( DIGI_HC_DIR . '/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) digi_hc_out( array( 'error' => 'token' ), 403 );
	$admin = get_user_by( 'login', 'admin' );
	wp_set_current_user( $admin->ID );
	$step = sanitize_key( wp_unslash( $_GET['digi_hc'] ) );
	switch ( $step ) {
		case 'store':
			update_option( 'woocommerce_currency', 'MXN' );
			update_option( 'woocommerce_default_country', 'MX:JA' );
			update_option( 'woocommerce_enable_guest_checkout', 'yes' );
			update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes', 'title' => 'Cheque', 'description' => '', 'instructions' => '' ) );
			$term = wp_insert_term( 'Hosting', 'product_cat' );
			$cat  = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
			$make = static function ( $name, $price, $sku = '', $cats = array(), $plan = '' ) {
				$p = new WC_Product_Simple();
				$p->set_name( $name ); $p->set_status( 'publish' ); $p->set_virtual( true ); $p->set_regular_price( (string) $price ); $p->set_category_ids( $cats );
				if ( $sku ) $p->set_sku( $sku );
				$id = $p->save();
				if ( $plan ) update_post_meta( $id, '_digitalisimo_webuzo_plan', $plan );
				return $id;
			};
			$ids = array( 'plan' => $make( 'Web Hosting Starter', 970, '', array( $cat ), 'digitalisimo_starter' ), 'com' => $make( 'Dominio .com', 300, 'dominio-com' ), 'net' => $make( 'Dominio .net', 280, 'dominio-net' ), 'mx' => $make( 'Dominio .mx', 600, 'dominio-mx' ), 'org' => $make( 'Dominio .org (sin vender)', 250, 'dominio-org' ), 'transfer' => $make( 'Transferencia de dominio', 250 ) );
			$secrets = array( 'namecheap' => 'NC-' . wp_generate_password( 24, false ), 'webuzo' => 'WZ-' . wp_generate_password( 24, false ), 'banxico' => 'BX-' . wp_generate_password( 24, false ), 'epp' => 'E' . wp_generate_password( 20, true, false ), 'nc_user' => 'ncuser' . wp_generate_password( 8, false ) );
			// Lo que dejó la pantalla de ajustes: la cuenta y las claves (no se tocan por MCP); la lista de fábrica vieja con .org.
			update_option( 'digitalisimo_hosting_options', array( 'enabled' => 1, 'inherit_network' => 0, 'api_user' => $secrets['nc_user'], 'username' => $secrets['nc_user'], 'api_key' => $secrets['namecheap'], 'client_ip' => '203.0.113.5', 'webuzo_url' => 'https://webuzo.test:2005', 'webuzo_api_user' => 'root', 'webuzo_api_key' => $secrets['webuzo'], 'banxico_token' => $secrets['banxico'], 'domain_products' => ".com|\n.mx|\n.org|", 'hosting_category_ids' => (string) $cat, 'provision_enabled' => 0 ), false );
			update_option( 'digitalisimo_hosting_ecommerce_migrated', 1 );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			$reader = get_user_by( 'login', 'lectora' );
			$reader_id = $reader ? $reader->ID : wp_insert_user( array( 'user_login' => 'lectora', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'lectora@tienda.test', 'role' => 'shop_manager' ) );
			$a = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-hc' ) );
			$b = WP_Application_Passwords::create_new_application_password( $reader_id, array( 'name' => 'digi-hc' ) );
			$secrets['password:admin'] = $a[0]; $secrets['password:lectora'] = $b[0];
			file_put_contents( DIGI_HC_DIR . '/secrets.json', wp_json_encode( $secrets ) );
			delete_option( 'digi_hc_calls' );
			Digitalisimo_Hosting_Products::flush();
			digi_hc_out( array( 'products' => $ids, 'woocommerce' => WC()->version, 'gateways' => array_keys( WC()->payment_gateways()->get_available_payment_gateways() ) ) );
		case 'option':
			$o = get_option( 'digitalisimo_hosting_options' ); $s = digi_hc_secrets();
			digi_hc_out( array( 'same_account' => $o['api_key'] === $s['namecheap'] && $o['api_user'] === $s['nc_user'] && '203.0.113.5' === $o['client_ip'] && $o['webuzo_api_key'] === $s['webuzo'] && $o['banxico_token'] === $s['banxico'] && 'https://webuzo.test:2005' === $o['webuzo_url'], 'domain_products' => $o['domain_products'], 'ask_domain' => $o['ask_domain'] ?? null, 'provision_enabled' => $o['provision_enabled'], 'hosting_category_ids' => $o['hosting_category_ids'], 'resolved' => Digitalisimo_Hosting::domain_products() ) );
		case 'state':
			$order = wc_get_order( absint( $_GET['id'] ?? 0 ) );
			if ( ! $order ) digi_hc_out( array( 'error' => 'pedido' ), 404 );
			$items = array();
			foreach ( $order->get_items() as $item ) { $meta = array(); foreach ( $item->get_meta_data() as $m ) $meta[ $m->key ] = $m->value; $items[] = array( 'product' => $item->get_product_id(), 'name' => $item->get_name(), 'meta' => $meta ); }
			$keys = array(); foreach ( $order->get_meta_data() as $m ) $keys[] = $m->key;
			$epp_ok = $order->get_meta( '_digitalisimo_epp' ) ? Digitalisimo_Hosting_Checkout::epp( $order, 'ajeno.com' ) === digi_hc_secrets()['epp'] : null;
			$rest = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/orders/' . $order->get_id() ) );
			$data = $rest->get_data();
			$rest_keys = array(); foreach ( (array) ( $data['meta_data'] ?? array() ) as $m ) $rest_keys[] = is_object( $m ) ? $m->key : ( $m['key'] ?? '' );
			$notes = array(); foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $n ) $notes[] = $n->content;
			digi_hc_out( array( 'status' => $order->get_status(), 'customer' => $order->get_customer_id(), 'items' => $items, 'meta_keys' => $keys, 'epp_ok' => $epp_ok, 'rest_status' => $rest->get_status(), 'rest_meta_keys' => $rest_keys, 'rest_json' => wp_json_encode( $data ), 'notes' => $notes ) );
		case 'page':
			$id = (int) get_option( 'digi_hc_page' );
			if ( ! $id || ! get_post( $id ) ) {
				$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Dominios (atómica)' ) );
				$s = static function ( $v ) { return array( '$$type' => 'string', 'value' => $v ); };
				$data = array(
					array( 'id' => 'dc0a001', 'elType' => 'e-div-block', 'settings' => array(), 'isInner' => false, 'elements' => array(
						array( 'id' => 'dc0f001', 'elType' => 'e-form', 'settings' => array(), 'isInner' => false, 'elements' => array(
							array( 'id' => 'dc0i001', 'elType' => 'widget', 'widgetType' => 'e-form-input', 'settings' => array( '_cssid' => $s( 'dominio' ), 'placeholder' => $s( 'minegocio' ) ), 'elements' => array() ),
							array( 'id' => 'dc0b001', 'elType' => 'widget', 'widgetType' => 'e-form-submit-button', 'settings' => array(), 'elements' => array() ),
						) ),
						array( 'id' => 'dc0r001', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[resultado_dominio]' ), 'elements' => array() ),
					) ),
					array( 'id' => 'dc0a002', 'elType' => 'e-div-block', 'settings' => array(), 'isInner' => false, 'elements' => array(
						array( 'id' => 'dc0w001', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[digitalisimo_domain_search]' ), 'elements' => array() ),
					) ),
				);
				update_post_meta( $id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
				update_option( 'digi_hc_page', $id, false );
			}
			$exp = \Elementor\Plugin::$instance->experiments;
			digi_hc_out( array( 'id' => $id, 'atomic' => $exp->is_feature_active( 'e_atomic_elements' ), 'atomic_form' => $exp->is_feature_active( 'e_pro_atomic_form' ) ) );
		case 'calls':
			digi_hc_out( array( 'calls' => get_option( 'digi_hc_calls', array() ) ) );
	}
	digi_hc_out( array( 'error' => 'paso' ), 400 );
}, 99 );
