<?php
/**
 * Sonda del ensayo de Skydropx en el MCP de DIGITALÍSIMO Elements (mu-plugin que copia setup.php).
 *
 * - **La plataforma Skydropx simulada** con `pre_http_request`, sin salir nunca a internet:
 *   - la cotización (POST a la URL que la «plataforma» fijó, `cotiza.skydropx.test`) responde dos tarifas calculadas con
 *     el peso que manda el plugin y anota lo recibido;
 *   - la guía (GET firmado a `ecommerce.pro.skydropx.com/api/v3/order/woocommerce/mx/labels`) comprueba la firma HMAC con
 *     la clave guardada y responde un PDF mínimo; se anota si venía firmada y si la firma era válida (nunca la firma);
 *   - los webhooks de WooCommerce hacia la plataforma (entrega síncrona en el ensayo) se anotan con su tema, el pedido y
 *     el rastreo que llevan;
 *   - cualquier otro host fuera del sitio: error. El correo no sale.
 * - `?digi_sk=<paso>&token=<token>` prepara o lee el sitio: `ping`, `selftest`, `activate-sky`, `deactivate-sky`,
 *   `users`, `store` (tienda, productos y pedidos), `link` (lo que hace la plataforma al vincular: la clave del plugin,
 *   la cotización con su URL y su zona, y los webhooks), `mode`, `order`, `product`, `platform`, `cart-meta`, `log`.
 * Los secretos del ensayo van a /digi-probe/secrets.json (el arnés comprueba que no salen en ningún resultado y lo borra).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_SK_DIR       = '/digi-probe';
const DIGI_SK_QUOTE     = 'cotiza.skydropx.test';
const DIGI_SK_PLATFORM  = 'ecommerce.pro.skydropx.com';
const DIGI_SK_PDF       = "%PDF-1.4\n% guia de prueba\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_SK_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);

// Avisos de PHP de la herramienta (o del plugin mientras ella lo usa): a php-errors.log; el arnés falla con cualquiera.
function digi_sk_ours( string $text ): bool {
	return 1 === preg_match( '#modules/digitalisimo-woo/mcp/|digitalisimo_woo_mcp|digitalisimo-woo/skydropx|Woo\\\\Mcp\\\\|/plugins/skydropx/#i', $text );
}
function digi_sk_php_error( string $line ) {
	file_put_contents( DIGI_SK_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' · ' . $line . "\n", FILE_APPEND );
}
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( digi_sk_ours( (string) $file ) || digi_sk_ours( (string) $message ) ) {
			digi_sk_php_error( "PHP $severity: $message en $file:$line" );
		}
		return false;
	}
);
foreach ( array( 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run' ) as $digi_sk_hook ) {
	add_action(
		$digi_sk_hook,
		static function ( ...$args ) use ( $digi_sk_hook ) {
			$text  = $digi_sk_hook . ': ' . implode( ' · ', array_map( static function ( $a ) { return is_scalar( $a ) ? (string) $a : ''; }, $args ) );
			$trace = function_exists( 'wp_debug_backtrace_summary' ) ? (array) wp_debug_backtrace_summary( null, 0, false ) : array();
			if ( digi_sk_ours( $text ) || digi_sk_ours( implode( ',', $trace ) ) ) {
				digi_sk_php_error( $text . ' · ' . implode( ',', array_slice( $trace, -6 ) ) );
			}
		},
		10,
		3
	);
}

/** Bitácora de la plataforma simulada (/digi-probe/platform.json). */
function digi_sk_platform( string $kind, array $entry ) {
	$file              = DIGI_SK_DIR . '/platform.json';
	$all               = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
	$all               = is_array( $all ) ? $all : array();
	$all[ $kind ][]    = $entry;
	file_put_contents( $file, wp_json_encode( $all ) );
}
function digi_sk_response( int $code, string $body, string $type ) {
	return array(
		'headers'  => array( 'content-type' => $type ),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( DIGI_SK_QUOTE === $host ) {
			$body   = json_decode( (string) ( $args['body'] ?? '' ), true );
			$weight = 0.0;
			$items  = array();
			foreach ( (array) ( $body['items'] ?? array() ) as $item ) {
				$weight += (float) ( $item['weight'] ?? 0 ) * (float) ( $item['quantity'] ?? 1 );
				$items[] = array_intersect_key( (array) $item, array_flip( array( 'id', 'quantity', 'weight', 'length', 'width', 'height' ) ) );
			}
			digi_sk_platform(
				'quotes',
				array(
					'method'   => $args['method'] ?? '',
					'items'    => $items,
					'to'       => $body['address_to']['zip_code'] ?? '',
					'from'     => $body['address_from']['zip_code'] ?? '',
					'total'    => $body['total_price'] ?? null,
				)
			);
			$rates = array(
				array( 'service_provider' => 'fedex', 'service_name' => 'Estándar', 'service_code' => 'standard', 'total_price' => round( 120 + 15 * $weight, 2 ), 'description' => '3 a 5 días', 'currency' => 'MXN' ),
				array( 'service_provider' => 'dhl', 'service_name' => 'Express', 'service_code' => 'express', 'total_price' => round( 210 + 20 * $weight, 2 ), 'description' => '1 a 2 días', 'currency' => 'MXN' ),
			);
			return digi_sk_response( 200, wp_json_encode( array( 'rates' => $rates ) ), 'application/json' );
		}
		if ( DIGI_SK_PLATFORM === $host ) {
			$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
			if ( '/api/v3/order/woocommerce/mx/labels' === $path ) {
				$headers = array_change_key_case( (array) ( $args['headers'] ?? array() ), CASE_LOWER );
				$stamp   = (string) ( $headers['x-skydropx-timestamp'] ?? '' );
				$sign    = (string) ( $headers['x-skydropx-signature'] ?? '' );
				$query   = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
				parse_str( $query, $params );
				$key     = class_exists( 'Skydropx\\Includes\\Skydropx_Repository' ) ? ( new Skydropx\Includes\Skydropx_Repository() )->fetch_plugin_api_key() : null;
				$secret  = is_array( $key ) ? (string) $key['consumer_secret'] : '';
				$expect  = '' !== $secret ? base64_encode( hash_hmac( 'sha256', $stamp . "\nGET\n" . $path . '?' . $query . "\n", $secret, true ) ) : '';
				digi_sk_platform(
					'labels',
					array(
						'order'  => (int) ( $params['order_id'] ?? 0 ),
						'domain' => (string) ( $params['domain'] ?? '' ),
						'signed' => '' !== $sign && '' !== $stamp,
						'valid'  => '' !== $expect && hash_equals( $expect, $sign ),
					)
				);
				return digi_sk_response( 200, DIGI_SK_PDF, 'application/pdf' );
			}
			$headers = array_change_key_case( (array) ( $args['headers'] ?? array() ), CASE_LOWER );
			$payload = json_decode( (string) ( $args['body'] ?? '' ), true );
			$number  = '';
			foreach ( (array) ( $payload['shipping_lines'] ?? array() ) as $line ) {
				foreach ( (array) ( $line['meta_data'] ?? array() ) as $meta ) {
					if ( 'Tracking Number' === ( $meta['key'] ?? '' ) ) {
						$number = (string) $meta['value'];
					}
				}
			}
			digi_sk_platform(
				'webhooks',
				array(
					'topic'    => (string) ( $headers['x-wc-webhook-topic'] ?? '' ),
					'order'    => (int) ( $payload['id'] ?? 0 ),
					'tracking' => $number,
					'modified' => (string) ( $payload['date_modified_gmt'] ?? '' ),
				)
			);
			return digi_sk_response( 200, '{}', 'application/json' );
		}
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_sk_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );
// En el ensayo los webhooks se entregan en la misma petición (en un sitio real, WooCommerce los encola).
add_filter( 'woocommerce_webhook_deliver_async', '__return_false' );

function digi_sk_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}
function digi_sk_secret( string $key, $value ) {
	$file        = DIGI_SK_DIR . '/secrets.json';
	$all         = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
	$all         = is_array( $all ) ? $all : array();
	$all[ $key ] = $value;
	file_put_contents( $file, wp_json_encode( $all ) );
}
function digi_sk_user( string $login, string $role ): int {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$user = get_user_by( 'login', $login );
	$id   = $user ? (int) $user->ID : (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'user_email' => $login . '@example.test', 'role' => $role ) );
	( new WP_User( $id ) )->set_role( $role );
	$created = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'digi-sk' ) );
	if ( ! is_wp_error( $created ) ) {
		digi_sk_secret( 'password:' . $login, $created[0] );
	}
	return $id;
}
/** Estado de un pedido para el ensayo: líneas de envío con sus metas, estado, notas al cliente, total y fecha. */
function digi_sk_order( int $id ): array {
	$order = wc_get_order( $id );
	if ( ! $order ) {
		return array( 'exists' => false );
	}
	$lines = array();
	foreach ( $order->get_items( 'shipping' ) as $item ) {
		$meta = array();
		foreach ( $item->get_meta_data() as $entry ) {
			$data                = $entry->get_data();
			$meta[ $data['key'] ] = $data['value'];
		}
		$lines[] = array( 'id' => $item->get_id(), 'method_id' => $item->get_method_id(), 'total' => $item->get_total(), 'meta' => $meta );
	}
	$notes = wc_get_order_notes( array( 'order_id' => $id, 'type' => 'customer' ) );
	return array(
		'exists'         => true,
		'status'         => $order->get_status(),
		'total'          => $order->get_total(),
		'total_tax'      => $order->get_total_tax(),
		'shipping_total' => $order->get_shipping_total(),
		'lines'          => $lines,
		'customer_notes' => array_map( static function ( $n ) { return $n->content; }, $notes ),
		'modified'       => $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : 0,
	);
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['digi_sk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_SK_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! isset( $_GET['token'] ) || ! hash_equals( $token, (string) $_GET['token'] ) ) { // phpcs:ignore
			digi_sk_out( array( 'error' => 'token' ), 403 );
		}
		$step = sanitize_key( wp_unslash( $_GET['digi_sk'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$arg  = isset( $_GET['value'] ) ? sanitize_text_field( wp_unslash( $_GET['value'] ) ) : ''; // phpcs:ignore
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		switch ( $step ) {
			case 'ping':
				digi_sk_out( array( 'ready' => true, 'woo' => class_exists( 'WooCommerce' ), 'sky' => defined( 'SKYDROPX_VERSION' ) ? SKYDROPX_VERSION : '', 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'wc' => defined( 'WC_VERSION' ) ? WC_VERSION : '', 'hpos' => class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) );
				break;
			case 'selftest':
				@unlink( DIGI_SK_DIR . '/php-errors.log' ); // phpcs:ignore
				trigger_error( 'digitalisimo_woo_mcp autoprueba del detector', E_USER_NOTICE ); // phpcs:ignore
				$log = (string) @file_get_contents( DIGI_SK_DIR . '/php-errors.log' ); // phpcs:ignore
				@unlink( DIGI_SK_DIR . '/php-errors.log' ); // phpcs:ignore
				digi_sk_out( array( 'notice' => false !== strpos( $log, 'autoprueba del detector' ) ) );
				break;
			case 'activate-sky':
				$result = activate_plugin( 'skydropx/skydropx.php' );
				digi_sk_out( array( 'active' => ! is_wp_error( $result ) && is_plugin_active( 'skydropx/skydropx.php' ), 'error' => is_wp_error( $result ) ? $result->get_error_message() : '' ) );
				break;
			case 'deactivate-sky':
				// Sin desinstalar ni avisar a la plataforma (eso lo haría su desactivación «de verdad»): sólo apagarlo.
				deactivate_plugins( 'skydropx/skydropx.php', true );
				digi_sk_out( array( 'active' => is_plugin_active( 'skydropx/skydropx.php' ) ) );
				break;
			case 'users':
				$users = array();
				foreach ( array( 'gerente' => 'shop_manager', 'editora' => 'editor' ) as $login => $role ) {
					$users[ $login ] = digi_sk_user( $login, $role );
				}
				$users['admin'] = digi_sk_user( 'admin', 'administrator' );
				digi_sk_out( array( 'users' => $users ) );
				break;
			case 'store':
				foreach ( array(
					'woocommerce_coming_soon'          => 'no',
					'woocommerce_default_country'      => 'MX:JAL',
					'woocommerce_store_address'        => 'Av. Vallarta 1000',
					'woocommerce_store_city'           => 'Guadalajara',
					'woocommerce_store_postcode'       => '44100',
					'woocommerce_currency'             => 'MXN',
					'woocommerce_weight_unit'          => 'kg',
					'woocommerce_dimension_unit'       => 'cm',
					'woocommerce_allow_tracking'       => 'no',
					'woocommerce_onboarding_profile'   => array( 'skipped' => true ),
					'woocommerce_show_marketplace_suggestions' => 'no',
				) as $name => $value ) {
					update_option( $name, $value );
				}
				update_option( 'elementor_mcp_enabled', true );
				$simple = static function ( string $name, array $props ) {
					$p = new WC_Product_Simple();
					$p->set_props( array_merge( array( 'name' => $name, 'status' => 'publish', 'regular_price' => '300' ), $props ) );
					return $p->save();
				};
				$p1 = $simple( 'Taladro completo', array( 'weight' => '1.2', 'length' => '20', 'width' => '15', 'height' => '10', 'sku' => 'SK-1' ) );
				$p2 = $simple( 'Llave sin medidas', array( 'weight' => '0', 'regular_price' => '150', 'sku' => 'SK-2' ) );
				$p3 = $simple( 'Curso en línea', array( 'virtual' => true, 'regular_price' => '99', 'sku' => 'SK-3' ) );
				$attribute = new WC_Product_Attribute();
				$attribute->set_name( 'Talla' );
				$attribute->set_options( array( 'Chica', 'Grande' ) );
				$attribute->set_visible( true );
				$attribute->set_variation( true );
				$variable = new WC_Product_Variable();
				$variable->set_props( array( 'name' => 'Overol', 'status' => 'publish', 'length' => '30', 'width' => '20', 'height' => '10', 'sku' => 'SK-4' ) );
				$variable->set_attributes( array( $attribute ) );
				$vid  = $variable->save();
				$vars = array();
				foreach ( array( 'chica' => '0.5', 'grande' => '' ) as $size => $weight ) {
					$v = new WC_Product_Variation();
					$v->set_props( array( 'parent_id' => $vid, 'status' => 'publish', 'regular_price' => '450', 'weight' => $weight, 'sku' => 'SK-4-' . $size ) );
					$v->set_attributes( array( 'talla' => ucfirst( $size ) ) );
					$vars[ $size ] = $v->save();
				}
				WC_Product_Variable::sync( $vid );
				$order = static function ( array $meta, bool $shipping, string $status ) use ( $p1 ) {
					$o = wc_create_order();
					$o->add_product( wc_get_product( $p1 ), 1 );
					$address = array( 'first_name' => 'Ana', 'last_name' => 'Prueba', 'address_1' => 'Calle 1', 'city' => 'Ciudad de México', 'state' => 'DF', 'postcode' => '06700', 'country' => 'MX', 'email' => 'ana@example.test' );
					$o->set_address( $address, 'billing' );
					$o->set_address( $address, 'shipping' );
					if ( $shipping ) {
						$item = new WC_Order_Item_Shipping();
						$item->set_props( array( 'method_title' => 'Express', 'method_id' => 'skydropx', 'total' => '210' ) );
						foreach ( $meta as $key => $value ) {
							$item->add_meta_data( $key, $value, true );
						}
						$o->add_item( $item );
					}
					$o->calculate_totals();
					$o->set_status( $status );
					return $o->save();
				};
				$a = $order( array(), true, 'processing' );
				$b = $order( array( 'Tracking Number' => 'SKY-777', 'Tracking Company' => 'FedEx', 'Tracking URL' => 'https://rastreo.skydropx.test/SKY-777' ), true, 'processing' );
				$c = $order( array(), false, 'on-hold' );
				digi_sk_out( array( 'products' => array( 'complete' => $p1, 'zero' => $p2, 'virtual' => $p3, 'variable' => $vid, 'chica' => $vars['chica'], 'grande' => $vars['grande'] ), 'orders' => array( 'a' => $a, 'b' => $b, 'c' => $c ) ) );
				break;
			case 'link':
				// Lo que la plataforma deja en la tienda al vincularla: la clave del plugin (con su método, como el botón
				// «Vincular mi tienda»), la cotización activa con su URL y su zona, y sus webhooks de pedidos.
				$admin = get_user_by( 'login', 'admin' );
				wp_set_current_user( $admin->ID );
				$keys = ( new Skydropx\Includes\Skydropx_Repository() )->reset_api_keys();
				if ( is_wp_error( $keys ) ) {
					digi_sk_out( array( 'error' => $keys->get_error_message() ), 500 );
				}
				digi_sk_secret( 'consumer_key', $keys[0] );
				digi_sk_secret( 'consumer_secret', $keys[1] );
				$token = 'qt_' . wp_generate_password( 24, false );
				digi_sk_secret( 'quote_token', $token );
				Skydropx\Includes\Skydropx_Quotation_Settings::set_enabled( true );
				Skydropx\Includes\Skydropx_Quotation_Settings::save_base_url( 'https://' . DIGI_SK_QUOTE . '/v1/rates/tienda-ensayo?token=' . $token );
				( new Skydropx\Includes\Skydropx_Shipping_Zone() )->create();
				foreach ( array( 'order.created', 'order.updated' ) as $topic ) {
					$secret = 'whsec_' . wp_generate_password( 24, false );
					digi_sk_secret( 'webhook_' . $topic, $secret );
					$hook = new WC_Webhook();
					$hook->set_name( 'Skydropx ' . $topic );
					$hook->set_user_id( $admin->ID );
					$hook->set_topic( $topic );
					$hook->set_secret( $secret );
					$hook->set_delivery_url( 'https://' . DIGI_SK_PLATFORM . '/api/v3/shop/webhook/mx/woocommerce/' . str_replace( '.', '_', $topic ) );
					$hook->set_status( 'active' );
					$hook->save();
				}
				digi_sk_out( array( 'linked' => ( new Skydropx\Includes\Skydropx_Repository() )->has_plugin_api_keys() ) );
				break;
			case 'mode':
				update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => $arg, 'own' => array( 'mode' ) ), false );
				digi_sk_out( array( 'mode' => \Digitalisimo\Elements\Woo\Mcp\Settings::mode() ) );
				break;
			case 'order':
				digi_sk_out( digi_sk_order( absint( $arg ) ) );
				break;
			case 'product':
				$p = wc_get_product( absint( $arg ) );
				digi_sk_out( $p ? array( 'weight' => $p->get_weight( 'edit' ), 'length' => $p->get_length( 'edit' ), 'width' => $p->get_width( 'edit' ), 'height' => $p->get_height( 'edit' ) ) : array( 'exists' => false ) );
				break;
			case 'platform':
				$file = DIGI_SK_DIR . '/platform.json';
				digi_sk_out( is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array() );
				break;
			case 'cart-meta':
				// El carrito guardado de cada usuario: la cotización de prueba no debe crear ni tocar ninguno.
				global $wpdb;
				$rows = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_woocommerce\\_persistent\\_cart\\_%'" ); // phpcs:ignore
				digi_sk_out( array( 'persistent_carts' => (int) $rows, 'sessions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_sessions" ) ) ); // phpcs:ignore
				break;
			case 'log':
				digi_sk_out( array( 'log' => get_option( 'digitalisimo_elements_woo_mcp_log', array() ) ) );
				break;
		}
		digi_sk_out( array( 'error' => 'paso desconocido' ), 400 );
	},
	99
);
