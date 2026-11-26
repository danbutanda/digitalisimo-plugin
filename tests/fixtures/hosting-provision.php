<?php
/**
 * Arnés de las pruebas de la venta automática de DIGITALÍSIMO Hosting (tests/hosting-provision.php,
 * tests/hosting-renewal.php y tests/hosting-services-screen.php), sin WordPress: lo mínimo de WordPress, WooCommerce
 * (pedidos, productos, correos) y Action Scheduler, y los servidores de Webuzo y Namecheap SIMULADOS por el filtro
 * `pre_http_request`, como en WordPress (nunca se llama a la API real). Cada petición se anota sin la clave.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'DIGITALISIMO_HOSTING_URL', 'https://tienda.test/wp-content/plugins/digitalisimo-hosting/' );
define( 'DIGITALISIMO_HOSTING_VERSION', '9.9.9' );
set_error_handler( function ( $no, $str, $file, $line ) { echo "FALLA: aviso de PHP $str en $file:$line\n"; exit( 1 ); } );

$checks = 0;
function check( $c, $m ) { global $checks; if ( ! $c ) { echo "FALLA: $m\n"; exit( 1 ); } $checks++; }

// ── Ganchos ──────────────────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['hooks'] = array();
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $h ][ $p ][] = array( $cb, $n ); return true; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { return add_filter( $h, $cb, $p, $n ); }
function apply_filters( $h, $v, ...$args ) {
	if ( empty( $GLOBALS['hooks'][ $h ] ) ) return $v;
	ksort( $GLOBALS['hooks'][ $h ] );
	foreach ( $GLOBALS['hooks'][ $h ] as $cbs ) foreach ( $cbs as $cb ) $v = call_user_func_array( $cb[0], array_slice( array_merge( array( $v ), $args ), 0, max( 1, $cb[1] ) ) );
	return $v;
}
function do_action( $h, ...$args ) {
	$GLOBALS['actions'][] = $h;
	if ( empty( $GLOBALS['hooks'][ $h ] ) ) return;
	ksort( $GLOBALS['hooks'][ $h ] );
	foreach ( $GLOBALS['hooks'][ $h ] as $cbs ) foreach ( $cbs as $cb ) call_user_func_array( $cb[0], array_slice( $args, 0, $cb[1] ) );
}
function add_shortcode() {} function register_rest_route() {} function register_post_type() {}

// ── Opciones (sitio y red) ───────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['options'] = array( 'admin_email' => 'dueno@tienda.test', 'digitalisimo_hosting_ecommerce_migrated' => 1 );
$GLOBALS['site_options'] = array(); $GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false;
function is_multisite() { return $GLOBALS['multisite']; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['options'][ $k ] ); return true; }
function wp_load_alloptions() { return $GLOBALS['options']; }
function wp_set_option_autoload( $k, $a ) { return true; }
function get_site_option( $k, $d = false ) { if ( ! $GLOBALS['multisite'] ) return get_option( $k, $d ); return $GLOBALS['site_options'][ $k ] ?? $d; }
function update_site_option( $k, $v ) { if ( ! $GLOBALS['multisite'] ) return update_option( $k, $v ); $GLOBALS['site_options'][ $k ] = $v; return true; }
function delete_site_option( $k ) { if ( ! $GLOBALS['multisite'] ) return delete_option( $k ); unset( $GLOBALS['site_options'][ $k ] ); return true; }
function get_transient( $k ) { return false; } function set_transient() { return true; } function get_site_transient( $k ) { return false; } function set_site_transient() { return true; }

// ── Utilidades de WordPress ─────────────────────────────────────────────────────────────────────────────────────
function wp_salt( $s = 'auth' ) { return 'sal-de-prueba-' . $s; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_url_raw( $u ) { return (string) $u; }
function wp_kses_post( $t ) { return (string) $t; }
function wpautop( $t ) { return '<p>' . $t . '</p>'; }
function wptexturize( $t ) { return (string) $t; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_unslash( $v ) { return $v; }
function checked( $a, $b = true, $e = true ) { return $a == $b ? 'checked="checked"' : ''; }
function absint( $v ) { return abs( (int) $v ); }
function admin_url( $p = '' ) { return 'https://tienda.test/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_bloginfo( $k = '' ) { return 'Tienda'; }
function wp_specialchars_decode( $t, $q = 0 ) { return htmlspecialchars_decode( (string) $t, ENT_QUOTES ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); }
function has_term() { return false; }
function submit_button( $text = 'Guardar', $type = 'primary', $name = 'submit' ) { echo '<input type="submit" name="' . $name . '" value="' . $text . '">'; }
class WP_Error { public $code; public $message; public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; } public function get_error_message() { return $this->message; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function rest_ensure_response( $r ) { return $r; }

// Permisos, nonces y redirecciones.
$GLOBALS['caps'] = array( 'manage_options' => true, 'manage_woocommerce' => true, 'manage_network_options' => true );
function current_user_can( $c ) { return ! empty( $GLOBALS['caps'][ $c ] ); }
function wp_create_nonce( $a ) { return 'n:' . $a; }
function wp_nonce_field( $a, $name = '_wpnonce', $ref = true ) { echo '<input type="hidden" name="' . $name . '" value="n:' . $a . '">'; }
function check_admin_referer( $a, $name = '_wpnonce' ) { if ( ( $_POST[ $name ] ?? $_REQUEST[ $name ] ?? '' ) !== 'n:' . $a ) throw new Hosting_Test_Die( 'nonce', 403 ); return true; }
class Hosting_Test_Die extends Exception { public $status; public function __construct( $m, $s = 500 ) { parent::__construct( (string) $m ); $this->status = $s; } }
class Hosting_Test_Redirect extends Exception {}
function wp_die( $m = '', $s = 500 ) { throw new Hosting_Test_Die( $m, is_array( $s ) ? 500 : (int) $s ); }
function wp_safe_redirect( $url ) { throw new Hosting_Test_Redirect( $url ); }
function wp_get_current_user() { return (object) array( 'user_login' => 'admin' ); }

// Correo de WordPress (avisos al administrador).
$GLOBALS['wp_mails'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['wp_mails'][] = compact( 'to', 'subject', 'body' ); return true; }

// ── HTTP: sólo los servidores simulados; cualquier otro destino es un error ─────────────────────────────────────
$GLOBALS['http'] = array();
function wp_remote_post( $url, $args = array() ) {
	$pre = apply_filters( 'pre_http_request', false, $args + array( 'method' => 'POST' ), $url );
	if ( false !== $pre ) return $pre;
	return new WP_Error( 'offline', 'Sin red en la prueba: ' . $url );
}
function wp_remote_get( $url, $args = array() ) { return wp_remote_post( $url, $args ); }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }

/** Webuzo simulado (API de administración, act=users / add_user, con apiuser y apikey en el POST). */
class Fake_Webuzo {
	public $key = 'CLAVE-WEBUZO-REAL-987654';
	public $users = array( 'otro' => array( 'user' => 'otro', 'domain' => 'otro-cliente.com', 'status' => '' ) );
	public $mode = 'ok';          // ok | timeout | auth | html | timeout-after-create
	public $calls = array();
	public $plans = array( 'digitalisimo_starter', 'digitalisimo_digital', 'digitalisimo_growth', 'digitalisimo_pro' );
	public function handle( $url, $body ) {
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
		$act = $q['act'] ?? '';
		$log = $body; unset( $log['apikey'], $log['user_passwd'], $log['cnf_user_passwd'] ); $log['act'] = $act; $log['has_key'] = isset( $body['apikey'] ); $log['has_pass'] = isset( $body['user_passwd'] ) && $body['user_passwd'] === ( $body['cnf_user_passwd'] ?? null );
		$this->calls[] = $log;
		if ( 'timeout' === $this->mode ) return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 45001 milliseconds' );
		if ( 'html' === $this->mode ) return array( 'response' => array( 'code' => 200 ), 'body' => '<html><body>Login</body></html>' );
		if ( 'auth' === $this->mode || ( $body['apikey'] ?? '' ) !== $this->key || 'root' !== ( $body['apiuser'] ?? '' ) ) return self::json( array( 'error' => array( 'login' => 'Incorrect Username or Password. 1 attempt(s) left' ) ) );
		if ( 'users' === $act && isset( $body['suspend'] ) ) { $this->users[ $body['suspend'] ]['status'] = 'suspended'; return self::json( array( 'done' => array( 'msg' => 'User suspended successfully' ) ) ); }
		if ( 'users' === $act && isset( $body['unsuspend'] ) ) { $this->users[ $body['unsuspend'] ]['status'] = ''; return self::json( array( 'done' => array( 'msg' => 'User unsuspended successfully' ) ) ); }
		if ( 'users' === $act ) return self::json( array( 'users' => $this->users ) );
		if ( 'add_user' === $act ) {
			if ( isset( $this->users[ $body['user'] ] ) ) return self::json( array( 'error' => array( 'user' => 'The username already exists' ) ) );
			if ( ! in_array( $body['plan'], $this->plans, true ) ) return self::json( array( 'error' => array( 'plan' => 'The plan does not exist' ) ) );
			$this->users[ $body['user'] ] = array( 'user' => $body['user'], 'domain' => $body['domain'], 'status' => '', 'plan' => $body['plan'], 'email' => $body['email'], 'pass' => $body['user_passwd'] );
			if ( 'timeout-after-create' === $this->mode ) { $this->mode = 'ok'; return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ); }
			return self::json( array( 'done' => array( 'msg' => 'The User has been saved successfully' ) ) );
		}
		return self::json( array( 'error' => 'Unknown act' ) );
	}
	public static function json( $v ) { return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $v ) ); }
	public function count( $act, $key = null ) { $n = 0; foreach ( $this->calls as $c ) if ( $c['act'] === $act && ( null === $key || isset( $c[ $key ] ) ) ) $n++; return $n; }
}

/** Namecheap simulado (XML), con saldo y precios. */
class Fake_Namecheap {
	public $key = 'CLAVE-NAMECHEAP-REAL-4242';
	public $balance = 50.00;
	public $prices = array( 'REGISTER' => 9.58, 'RENEW' => 11.28 );
	public $domains = array();   // dominio => array( expires => ts, ns => [], contact => [] )
	public $mode = 'ok';         // ok | timeout | timeout-after-create
	public $calls = array();
	public function handle( $body ) {
		$log = $body; unset( $log['ApiKey'] ); $log['has_key'] = isset( $body['ApiKey'] );
		$this->calls[] = $log;
		if ( 'timeout' === $this->mode ) return new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
		if ( ( $body['ApiKey'] ?? '' ) !== $this->key ) return self::xml( '', '<Error Number="1011102">Parameter APIKey is invalid</Error>' );
		$cmd = $body['Command'];
		if ( 'namecheap.users.getBalances' === $cmd ) return self::xml( '<UserGetBalancesResult Currency="USD" AvailableBalance="' . number_format( $this->balance, 2, '.', '' ) . '" AccountBalance="' . $this->balance . '"/>' );
		if ( 'namecheap.users.getPricing' === $cmd ) return self::xml( '<UserGetPricingResult><ProductType Name="domains"><ProductCategory Name="' . strtolower( $body['ActionName'] ) . '"><Product Name="' . $body['ProductName'] . '"><Price Duration="1" DurationType="YEAR" Price="' . $this->prices[ $body['ActionName'] ] . '" YourPrice="' . $this->prices[ $body['ActionName'] ] . '" AdditionalCost="0.20" Currency="USD"/><Price Duration="2" DurationType="YEAR" Price="20" YourPrice="20" AdditionalCost="0.40" Currency="USD"/></Product></ProductCategory></ProductType></UserGetPricingResult>' );
		if ( 'namecheap.domains.getInfo' === $cmd ) {
			$d = $body['DomainName'];
			if ( ! isset( $this->domains[ $d ] ) ) return self::xml( '', '<Error Number="2019166">Domain not found</Error>' );
			return self::xml( '<DomainGetInfoResult Status="Ok" DomainName="' . $d . '"><DomainDetails><CreatedDate>' . gmdate( 'm/d/Y', $this->domains[ $d ]['created'] ?? gmmktime( 0, 0, 0, 1, 1, 2026 ) ) . '</CreatedDate><ExpiredDate>' . gmdate( 'm/d/Y', $this->domains[ $d ]['expires'] ) . '</ExpiredDate></DomainDetails></DomainGetInfoResult>' );
		}
		if ( 'namecheap.domains.check' === $cmd ) {
			$d = $body['DomainList'];
			return self::xml( '<DomainCheckResult Domain="' . $d . '" Available="' . ( isset( $this->domains[ $d ] ) ? 'false' : 'true' ) . '" IsPremiumName="false"/>' );
		}
		if ( 'namecheap.domains.create' === $cmd ) {
			$d = $body['DomainName'];
			if ( isset( $this->domains[ $d ] ) ) return self::xml( '', '<Error Number="2019166">Domain not available</Error>' );
			$cost = $this->prices['REGISTER'] + 0.20;
			if ( $this->balance < $cost ) return self::xml( '', '<Error Number="2528166">Order creation failed: insufficient funds</Error>' );
			$this->balance -= $cost;
			$this->domains[ $d ] = array( 'expires' => strtotime( '+1 year' ), 'created' => time(), 'ns' => array(), 'contact' => $body );
			if ( 'timeout-after-create' === $this->mode ) { $this->mode = 'ok'; return new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ); }
			return self::xml( '<DomainCreateResult Domain="' . $d . '" Registered="true" ChargedAmount="' . $cost . '" DomainID="9001" OrderID="1" TransactionID="2" WhoisguardEnable="false" NonRealTimeDomain="false"/>' );
		}
		if ( 'namecheap.domains.dns.setCustom' === $cmd ) {
			$d = $body['SLD'] . '.' . $body['TLD'];
			$this->domains[ $d ]['ns'] = explode( ',', $body['Nameservers'] );
			return self::xml( '<DomainDNSSetCustomResult Domain="' . $d . '" Updated="true"/>' );
		}
		if ( 'namecheap.domains.renew' === $cmd ) {
			$d = $body['DomainName']; $cost = $this->prices['RENEW'] + 0.20;
			if ( $this->balance < $cost ) return self::xml( '', '<Error Number="2528166">insufficient funds</Error>' );
			$this->balance -= $cost;
			$this->domains[ $d ]['expires'] = strtotime( '+1 year', $this->domains[ $d ]['expires'] );
			if ( 'timeout-after-create' === $this->mode ) { $this->mode = 'ok'; return new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ); }
			return self::xml( '<DomainRenewResult DomainName="' . $d . '" DomainID="9001" Renew="true" OrderID="3" TransactionID="4" ChargedAmount="' . $cost . '"/>' );
		}
		return self::xml( '', '<Error Number="1">Unknown command</Error>' );
	}
	public static function xml( $result, $error = '' ) {
		$status = $error ? 'ERROR' : 'OK';
		return array( 'response' => array( 'code' => 200 ), 'body' => '<?xml version="1.0" encoding="utf-8"?><ApiResponse Status="' . $status . '" xmlns="http://api.namecheap.com/xml.response"><Errors>' . $error . '</Errors><CommandResponse>' . $result . '</CommandResponse></ApiResponse>' );
	}
	public function count( $cmd ) { $n = 0; foreach ( $this->calls as $c ) if ( $c['Command'] === $cmd ) $n++; return $n; }
}

$GLOBALS['webuzo'] = new Fake_Webuzo(); $GLOBALS['namecheap'] = new Fake_Namecheap();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$GLOBALS['http'][] = $url;
	if ( false !== strpos( $url, 'webuzo.test:2005/index.php' ) ) return $GLOBALS['webuzo']->handle( $url, (array) ( $args['body'] ?? array() ) );
	if ( false !== strpos( $url, 'api.namecheap.com/xml.response' ) || false !== strpos( $url, 'api.sandbox.namecheap.com/xml.response' ) ) return $GLOBALS['namecheap']->handle( (array) ( $args['body'] ?? array() ) );
	return $pre;
}, 10, 3 );

// ── Entradas (servicios) ─────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['next_id'] = 1000;
function wp_insert_post( $a ) { $id = ++$GLOBALS['next_id']; $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => $a['post_type'], 'post_title' => $a['post_title'] ?? '', 'post_status' => $a['post_status'] ?? 'publish' ); return $id; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_posts( $a ) { $out = array(); foreach ( $GLOBALS['posts'] as $id => $p ) if ( $p->post_type === $a['post_type'] ) $out[] = $id; return $out; }
function get_post_meta( $id, $k, $single = true ) { return $GLOBALS['postmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['postmeta'][ (int) $id ][ $k ] = $v; return true; }

// ── Cron y Action Scheduler ─────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['cron'] = array(); $GLOBALS['as'] = array();
function wp_next_scheduled( $h, $args = array() ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_schedule_single_event( $t, $h, $args = array() ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function as_enqueue_async_action( $h, $args = array(), $g = '' ) { $GLOBALS['as'][] = array( 'hook' => $h, 'args' => $args, 'group' => $g, 'time' => time(), 'status' => 'pending' ); return count( $GLOBALS['as'] ); }
function as_schedule_single_action( $t, $h, $args = array(), $g = '' ) { $GLOBALS['as'][] = array( 'hook' => $h, 'args' => $args, 'group' => $g, 'time' => $t, 'status' => 'pending' ); return count( $GLOBALS['as'] ); }
function as_has_scheduled_action( $h, $args = null, $g = '' ) { foreach ( $GLOBALS['as'] as $a ) if ( $a['hook'] === $h && in_array( $a['status'], array( 'pending', 'running' ), true ) && ( null === $args || $a['args'] === $args ) ) return true; return false; }
/** Corre lo pendiente (también lo programado a futuro si $all), como lo haría la cola; devuelve cuántas tareas corrió. */
function run_queue( $all = true, $max = 50 ) {
	$n = 0;
	while ( $max-- > 0 ) {
		$found = false;
		foreach ( $GLOBALS['as'] as $i => $a ) {
			if ( 'pending' !== $a['status'] || ( ! $all && $a['time'] > time() ) ) continue;
			$GLOBALS['as'][ $i ]['status'] = 'running';
			do_action( $a['hook'], ...$a['args'] );
			$GLOBALS['as'][ $i ]['status'] = 'complete';
			$found = true; $n++;
			break;
		}
		if ( ! $found ) break;
	}
	return $n;
}
function pending_actions( $hook = null ) { $out = array(); foreach ( $GLOBALS['as'] as $a ) if ( 'pending' === $a['status'] && ( null === $hook || $a['hook'] === $hook ) ) $out[] = $a; return $out; }

// ── WooCommerce ─────────────────────────────────────────────────────────────────────────────────────────────────
class Fake_Product { public $id; public $name; public $price; public function __construct( $id, $name, $price ) { $this->id = $id; $this->name = $name; $this->price = $price; } public function get_id() { return $this->id; } public function get_name() { return $this->name; } public function get_price() { return $this->price; } }
class Fake_Item {
	public $product; public $variation = 0; public $meta = array(); public $qty = 1; public $name;
	public function __construct( $product_id, $name, $meta = array(), $qty = 1 ) { $this->product = $product_id; $this->name = $name; $this->meta = $meta; $this->qty = $qty; }
	public function get_product_id() { return $this->product; } public function get_variation_id() { return $this->variation; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; } public function get_quantity() { return $this->qty; } public function get_name() { return $this->name; }
}
class Fake_Order {
	public $id; public $status = 'pending'; public $items = array(); public $meta = array(); public $notes = array(); public $billing = array(); public $customer = 7; public $created_via = 'checkout'; public $total = 0;
	public function get_id() { return $this->id; }
	/** Pasarela en línea: el pago queda fechado al pasar a un estado pagado (como WC_Order::maybe_set_date_paid()). */
	public function get_payment_method() { return 'stripe'; }
	public function get_transaction_id() { return 'txn_' . $this->id; }
	public function get_currency() { return 'MXN'; }
	public function get_date_paid() { return $this->is_paid() ? new DateTime( '@' . time() ) : null; }
	public function get_date_created() { return new DateTime( '@' . time() ); }
	public function delete_meta_data( $k ) { unset( $this->meta[ $k ] ); }
	public function get_status() { return $this->status; }
	public function update_status( $s, $note = '' ) { $old = $this->status; $this->status = $s; if ( $note ) $this->notes[] = $note; $this->save(); if ( $old !== $s ) do_action( 'woocommerce_order_status_' . $s, $this->id, $this ); return true; }
	public function get_items() { return $this->items; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function save() { return $this->id; }
	public function add_order_note( $n, $customer = 0 ) { $this->notes[] = $n; return count( $this->notes ); }
	public function __call( $m, $a ) { if ( 0 === strpos( $m, 'get_billing_' ) ) return $this->billing[ substr( $m, 12 ) ] ?? ''; if ( 0 === strpos( $m, 'set_billing_' ) ) { $this->billing[ substr( $m, 12 ) ] = $a[0]; return; } throw new Error( "Método no simulado: $m" ); }
	public function get_address( $t ) { return $this->billing; }
	public function set_address( $a, $t ) { $this->billing = $a; }
	public function get_customer_id() { return $this->customer; }
	public function needs_payment() { return in_array( $this->status, array( 'pending', 'failed' ), true ); }
	public function is_paid() { return in_array( $this->status, array( 'processing', 'completed' ), true ); }
	public function get_checkout_payment_url() { return 'https://tienda.test/finalizar-compra/order-pay/' . $this->id . '/?pay_for_order=true&key=wc_order_x'; }
	public function get_edit_order_url() { return 'https://tienda.test/wp-admin/post.php?post=' . $this->id . '&action=edit'; }
	public function add_product( $product, $qty ) { $this->items[] = new Fake_Item( $product->get_id(), $product->get_name(), array(), $qty ); $this->total += $product->get_price() * $qty; return count( $this->items ); }
	public function calculate_totals() { return $this->total; }
}
$GLOBALS['orders'] = array(); $GLOBALS['products'] = array(); $GLOBALS['next_order'] = 500;
function wc_get_order( $id ) { return $GLOBALS['orders'][ (int) $id ] ?? false; }
function wc_get_product( $id ) { return $GLOBALS['products'][ (int) $id ] ?? null; }
function wc_create_order( $a = array() ) { $o = new Fake_Order(); $o->id = ++$GLOBALS['next_order']; $o->customer = $a['customer_id'] ?? 0; $o->created_via = $a['created_via'] ?? ''; $GLOBALS['orders'][ $o->id ] = $o; $GLOBALS['last_created'] = $o; return $o; }
function wc_add_order_item_meta( $item_id, $k, $v ) { $GLOBALS['last_created']->items[ $item_id - 1 ]->meta[ $k ] = $v; }

class Fake_Countries {
	public function get_states( $cc ) { return 'MX' === $cc ? array( 'JA' => 'Jalisco', 'CX' => 'Ciudad de México' ) : array(); }
	public function get_country_calling_code( $cc ) { $m = array( 'MX' => '+52', 'US' => '+1' ); return $m[ $cc ] ?? ''; }
}
/** WC_Email mínimo: activado, asunto y encabezado con sus marcadores; enviar anota el correo. */
class WC_Email {
	public $id; public $title; public $description; public $customer_email = false; public $placeholders = array(); public $recipient; public $object; public $enabled = 'yes';
	public function __construct() { $this->placeholders = array_merge( array( '{site_title}' => 'Tienda' ), $this->placeholders ); if ( isset( $GLOBALS['email_enabled'][ $this->id ] ) ) $this->enabled = $GLOBALS['email_enabled'][ $this->id ]; }
	public function is_enabled() { return 'yes' === $this->enabled; }
	public function get_recipient() { return $this->recipient; }
	public function format_string( $s ) { return str_replace( array_keys( $this->placeholders ), array_values( $this->placeholders ), $s ); }
	public function get_subject() { return $this->format_string( $this->get_default_subject() ); }
	public function get_heading() { return $this->format_string( $this->get_default_heading() ); }
	public function get_additional_content() { return $this->format_string( $this->get_default_additional_content() ); }
	public function get_content() { return $this->get_content_html(); }
	public function get_headers() { return ''; }
	public function get_attachments() { return array(); }
	public function send( $to, $subject, $message, $headers, $att ) { $GLOBALS['wc_mails'][] = array( 'id' => $this->id, 'to' => $to, 'subject' => $subject, 'body' => $message ); return true; }
}
class Fake_Mailer { public function get_emails() { static $e = null; if ( null === $e || ! empty( $GLOBALS['emails_reset'] ) ) { $GLOBALS['emails_reset'] = false; $e = apply_filters( 'woocommerce_email_classes', array() ); } return $e; } }
class WooCommerce {}
function WC() { static $wc = null; if ( ! $wc ) $wc = new class { public $countries; public $cart = null; public function __construct() { $this->countries = new Fake_Countries(); } public function mailer() { return new Fake_Mailer(); } }; return $wc; }
$GLOBALS['wc_mails'] = array();

require __DIR__ . '/../../digitalisimo-hosting/includes/class-hosting.php';
require __DIR__ . '/../../digitalisimo-hosting/includes/class-hosting-webuzo.php';
require __DIR__ . '/../../digitalisimo-hosting/includes/class-hosting-namecheap.php';
require __DIR__ . '/../../digitalisimo-hosting/includes/class-hosting-provisioning.php';
require __DIR__ . '/../../digitalisimo-hosting/includes/class-hosting-services-page.php';
Digitalisimo_Hosting::init();

// ── La tienda de prueba: los planes y precios de digitalisimo.lat y un producto por extensión ───────────────────
$GLOBALS['products'] = array(
	14693 => new Fake_Product( 14693, 'Web Hosting Starter', 970 ), 14694 => new Fake_Product( 14694, 'Web Hosting Digital', 1750 ),
	14695 => new Fake_Product( 14695, 'Web Hosting Growth', 3500 ), 14696 => new Fake_Product( 14696, 'Web Hosting Pro', 5850 ),
	201 => new Fake_Product( 201, 'Dominio .com', 350 ), 202 => new Fake_Product( 202, 'Dominio .mx', 690 ), 203 => new Fake_Product( 203, 'Dominio .org', 420 ),
);
foreach ( array( 14693 => 'digitalisimo_starter', 14694 => 'digitalisimo_digital', 14695 => 'digitalisimo_growth', 14696 => 'digitalisimo_pro' ) as $pid => $plan ) update_post_meta( $pid, '_digitalisimo_webuzo_plan', $plan );

/** Ajustes de la tienda con la venta automática encendida (servidor y claves simulados). */
function hosting_settings( array $extra = array() ) {
	update_option( 'digitalisimo_hosting_options', $extra + array(
		'enabled' => 1, 'provision_enabled' => 1, 'provision_since' => 1, 'own_domain_auto' => 1, 'api_user' => 'digitalisimo', 'username' => 'digitalisimo', 'api_key' => $GLOBALS['namecheap']->key, 'client_ip' => '203.0.113.10',
		'domain_products' => ".com|201\n.mx|202\n.org|203", 'webuzo_url' => 'https://webuzo.test:2005', 'webuzo_api_user' => 'root', 'webuzo_api_key' => $GLOBALS['webuzo']->key, 'grace_days' => 7,
	), false );
}

/** Un pedido de cheque/transferencia con hosting y dominio, todavía sin pagar. */
function hosting_order( $domain = 'mitienda.com', $plan_product = 14693, $domain_product = 201, array $billing = array() ) {
	$o = new Fake_Order(); $o->id = ++$GLOBALS['next_order']; $o->status = 'on-hold';
	if ( $plan_product ) $o->items[] = new Fake_Item( $plan_product, $GLOBALS['products'][ $plan_product ]->get_name() );
	if ( $domain_product ) $o->items[] = new Fake_Item( $domain_product, $GLOBALS['products'][ $domain_product ]->get_name(), array( 'Dominio' => $domain ) );
	elseif ( $plan_product ) $o->items[0]->meta['Dominio'] = $domain;
	$o->billing = $billing + array( 'first_name' => 'Ana', 'last_name' => 'Pérez López', 'company' => '', 'address_1' => 'Av. Juárez 100', 'address_2' => '', 'city' => 'Guadalajara', 'state' => 'JA', 'postcode' => '44100', 'country' => 'MX', 'phone' => '33 1234 5678', 'email' => 'ana@cliente.test' );
	$GLOBALS['orders'][ $o->id ] = $o;
	return $o;
}

/** ¿Algún texto (notas, correos al administrador, registros de llamadas, metadatos) contiene el secreto? */
function hosting_leaks( $secret ) {
	$haystack = serialize( array( $GLOBALS['wp_mails'], array_map( function ( $o ) { return array( $o->notes, $o->meta ); }, $GLOBALS['orders'] ), $GLOBALS['postmeta'], $GLOBALS['options'], $GLOBALS['site_options'], $GLOBALS['webuzo']->calls, $GLOBALS['namecheap']->calls, $GLOBALS['http'] ) );
	return false !== strpos( $haystack, $secret );
}
add_filter( 'digitalisimo_hosting_webuzo_host_ips', static function () { return array(); } ); // sin DNS real en las pruebas (B3)
