<?php
/**
 * Arnés de las pruebas de la venta automática de DIGITALÍSIMO Hosting (tests/hosting-provision.php,
 * tests/hosting-renewal.php y tests/hosting-services.php), sin WordPress ni WooCommerce: lo mínimo de ambos que usa el
 * código (ganchos, opciones de sitio y de red, entradas y su meta, pedidos, productos, correos, Action Scheduler) y,
 * por `pre_http_request` como en WordPress, un servidor Webuzo y una cuenta de Namecheap simulados. Ninguna petición
 * sale de la máquina: sin filtro que conteste, `wp_remote_post()` devuelve un error.
 */
error_reporting( E_ALL );
require_once __DIR__ . '/php74.php';
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DIGITALISIMO_HOSTING_FILE', __DIR__ . '/digitalisimo-hosting.php' );

$GLOBALS['checks'] = 0;
function check( $c, $m ) { if ( ! $c ) { echo "FALLA: $m\n"; exit( 1 ); } $GLOBALS['checks']++; }

// ── Ganchos ─────────────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['hooks'] = array();
$GLOBALS['dns'] = array(); // nombre => IP que «resuelve» (B3); los demás nombres no resuelven, sin salir a la red
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $prio ][] = array( $cb, $args ); return true; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return add_filter( $hook, $cb, $prio, $args ); }
function apply_filters( $hook, $value, ...$rest ) {
	if ( empty( $GLOBALS['hooks'][ $hook ] ) ) return $value;
	ksort( $GLOBALS['hooks'][ $hook ] );
	foreach ( $GLOBALS['hooks'][ $hook ] as $cbs ) foreach ( $cbs as $cb ) $value = call_user_func_array( $cb[0], array_slice( array_merge( array( $value ), $rest ), 0, max( 1, $cb[1] ) ) );
	return $value;
}
function do_action( $hook, ...$args ) {
	if ( empty( $GLOBALS['hooks'][ $hook ] ) ) return;
	ksort( $GLOBALS['hooks'][ $hook ] );
	foreach ( $GLOBALS['hooks'][ $hook ] as $cbs ) foreach ( $cbs as $cb ) call_user_func_array( $cb[0], array_slice( $args, 0, max( 1, $cb[1] ) ) );
}
function add_shortcode() {} function register_rest_route() {} function register_post_type( $t = '', $a = array() ) { $GLOBALS['registered_cpt'] = $a; }

// ── Opciones (sitio y red) ──────────────────────────────────────────────────────────────────────────────────
$GLOBALS['multisite'] = false; $GLOBALS['blog'] = 1; $GLOBALS['blogs'] = array( 1 => array() ); $GLOBALS['site_options'] = array();
function is_multisite() { return $GLOBALS['multisite']; }
function is_network_admin() { return ! empty( $GLOBALS['network_admin'] ); }
function get_current_blog_id() { return $GLOBALS['blog']; }
function switch_blog( $id ) { $GLOBALS['blogs'][ $GLOBALS['blog'] ] = $GLOBALS['options']; $GLOBALS['blog'] = $id; $GLOBALS['options'] = $GLOBALS['blogs'][ $id ] ?? array(); }
$GLOBALS['options'] = array( 'admin_email' => 'admin@tienda.test', 'blogname' => 'Tienda' );
// Caché de objetos (H-1, séptima revisión): con $GLOBALS['cache_sim'] cada petición guarda en memoria lo que leyó de
// opciones y meta (como WordPress, y como los drop-ins de Redis, Memcached o LSMCD aun con caché persistente);
// other_request() simula otra petición que escribe en la base sin tocar la memoria de ésta. Apagado, nada cambia.
$GLOBALS['cache_sim'] = false; $GLOBALS['ext_cache'] = false; $GLOBALS['rt_opt'] = array(); $GLOBALS['rt_meta'] = array();
function wp_using_ext_object_cache() { return (bool) $GLOBALS['ext_cache']; }
function wp_cache_delete( $key, $group = '' ) { if ( 'post_meta' === $group ) unset( $GLOBALS['rt_meta'][ (int) $key ] ); if ( 'options' === $group ) { if ( 'alloptions' === $key ) $GLOBALS['rt_opt'] = array(); else unset( $GLOBALS['rt_opt'][ $key ] ); } return true; }
function digi_cache_reset() { $GLOBALS['rt_opt'] = array(); $GLOBALS['rt_meta'] = array(); }
function other_request( callable $cb ) { $m = $GLOBALS['rt_meta']; $o = $GLOBALS['rt_opt']; digi_cache_reset(); $cb(); $GLOBALS['rt_meta'] = $m; $GLOBALS['rt_opt'] = $o; }
function get_option( $k, $d = false ) {
	if ( ! $GLOBALS['cache_sim'] ) return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d;
	if ( ! array_key_exists( $k, $GLOBALS['rt_opt'] ) ) { if ( ! array_key_exists( $k, $GLOBALS['options'] ) ) return $d; $GLOBALS['rt_opt'][ $k ] = $GLOBALS['options'][ $k ]; }
	return $GLOBALS['rt_opt'][ $k ];
}
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; unset( $GLOBALS['rt_opt'][ $k ] ); return true; }
function add_option( $k, $v = '', $x = '', $a = null ) { unset( $GLOBALS['rt_opt'][ $k ] ); if ( array_key_exists( $k, $GLOBALS['options'] ) ) return false; $GLOBALS['options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['options'][ $k ], $GLOBALS['rt_opt'][ $k ] ); return true; }
function wp_load_alloptions() { return $GLOBALS['options']; }
function wp_set_option_autoload( $k, $a ) { return true; }
function get_site_option( $k, $d = false ) { if ( ! $GLOBALS['multisite'] ) return get_option( $k, $d ); return array_key_exists( $k, $GLOBALS['site_options'] ) ? $GLOBALS['site_options'][ $k ] : $d; }
function update_site_option( $k, $v ) { if ( ! $GLOBALS['multisite'] ) return update_option( $k, $v ); $GLOBALS['site_options'][ $k ] = $v; return true; }
function delete_site_option( $k ) { if ( ! $GLOBALS['multisite'] ) return delete_option( $k ); unset( $GLOBALS['site_options'][ $k ] ); return true; }
function get_transient( $k ) { return false; } function set_transient() { return true; } function get_site_transient( $k ) { return false; } function set_site_transient() { return true; }
function get_bloginfo( $k = '' ) { return get_option( 'blogname', 'Tienda' ); }

// ── Utilidades de WordPress ─────────────────────────────────────────────────────────────────────────────────
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
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
function wp_specialchars_decode( $t, $q = 0 ) { return htmlspecialchars_decode( (string) $t, ENT_QUOTES ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_unslash( $v ) { return $v; }
function checked( $a, $b = true, $echo = true ) { return $a == $b ? 'checked="checked"' : ''; }
function absint( $v ) { return abs( (int) $v ); }
function wp_salt( $s = 'auth' ) { return ( $GLOBALS['salt'] ?? 'sal-de-prueba-' ) . $s; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function admin_url( $p = '' ) { return 'https://tienda.test/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function rest_ensure_response( $r ) { return $r; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'] ?? array( 'manage_options', 'manage_woocommerce', 'manage_network_options' ), true ); }
function wp_get_current_user() { return (object) array( 'user_login' => 'admin' ); }
function wp_create_nonce( $a ) { return 'n:' . $a; }
function wp_nonce_field( $a, $name = '_wpnonce', $ref = true ) { echo '<input type="hidden" name="' . $name . '" value="n:' . $a . '">'; }
function check_admin_referer( $a ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== 'n:' . $a ) throw new Digi_Test_Die( 'nonce', 403 ); return true; }
function submit_button( $text = 'Guardar', $type = 'primary', $name = 'submit' ) { echo '<input type="submit" name="' . $name . '" value="' . $text . '">'; }
class Digi_Test_Die extends Exception { public $status; public function __construct( $m, $s = 500 ) { parent::__construct( $m ); $this->status = $s; } }
class Digi_Test_Redirect extends Exception {}
function wp_die( $m = '', $code = 500 ) { throw new Digi_Test_Die( (string) $m, is_array( $code ) ? 500 : (int) $code ); }
function wp_safe_redirect( $u ) { $GLOBALS['redirect'] = $u; throw new Digi_Test_Redirect( $u ); }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['cron'][ $hook ] ?? false; }
function wp_schedule_event( $t, $r, $hook, $args = array() ) { $GLOBALS['cron'][ $hook ] = $t; return true; }
function wp_schedule_single_event( $t, $hook, $args = array() ) { $GLOBALS['cron'][ $hook ] = $t; return true; }
class WP_Error { public $code; public $message; public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; } public function get_error_message() { return $this->message; } public function get_error_code() { return $this->code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }

// ── Correo ──────────────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['mails'] = array();
function wp_mail( $to, $subject, $body, $headers = '', $att = array() ) { $GLOBALS['mails'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }

// ── HTTP: sólo lo que contesta un filtro pre_http_request ──────────────────────────────────────────────────────
$GLOBALS['http'] = array();
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['http'][] = array( 'url' => $url, 'args' => $args );
	$r = apply_filters( 'pre_http_request', false, $args + array( 'method' => 'POST' ), $url );
	return false === $r ? new WP_Error( 'http_request_failed', 'Sin red en la prueba' ) : $r;
}
function wp_remote_get( $url, $args = array() ) { return wp_remote_post( $url, $args ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }

// ── Entradas y su meta (servicios) ─────────────────────────────────────────────────────────────────────────
$GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['next_id'] = 1000;
function wp_insert_post( $a ) { $id = ++$GLOBALS['next_id']; $GLOBALS['posts'][ $id ] = (object) ( $a + array( 'ID' => $id, 'blog' => $GLOBALS['blog'] ) ); $GLOBALS['posts'][ $id ]->ID = $id; return $id; }
function get_post( $id ) { $p = $GLOBALS['posts'][ (int) $id ] ?? null; return $p && $p->blog === $GLOBALS['blog'] ? $p : null; }
function get_posts( $q ) {
	// Productos (2026-11-26, productos por extensión): los de $GLOBALS['products'], publicados salvo $GLOBALS['product_status'], con meta_key/meta_value.
	if ( 'product' === ( $q['post_type'] ?? '' ) ) {
		$out = array();
		foreach ( array_keys( $GLOBALS['products'] ) as $id ) {
			if ( ( $q['post_status'] ?? 'publish' ) !== get_post_status( $id ) ) continue;
			if ( isset( $q['meta_key'] ) && ! isset( $GLOBALS['postmeta'][ $id ][ $q['meta_key'] ] ) ) continue;
			if ( isset( $q['meta_value'] ) && (string) ( $GLOBALS['postmeta'][ $id ][ $q['meta_key'] ] ?? '' ) !== (string) $q['meta_value'] ) continue;
			$out[] = $id;
		}
		sort( $out );
		return $out;
	}
	$out = array(); foreach ( $GLOBALS['posts'] as $id => $p ) if ( $p->post_type === $q['post_type'] && $p->blog === $GLOBALS['blog'] ) $out[] = $id; return $out;
}
function get_post_status( $id ) { return isset( $GLOBALS['products'][ (int) $id ] ) ? ( $GLOBALS['product_status'][ (int) $id ] ?? 'publish' ) : ( isset( $GLOBALS['posts'][ (int) $id ] ) ? 'publish' : false ); }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['postmeta'][ (int) $id ][ $k ], $GLOBALS['rt_meta'][ (int) $id ] ); return true; }
function wc_get_product_id_by_sku( $sku ) { foreach ( $GLOBALS['products'] as $id => $p ) if ( '' !== (string) $p->sku && 0 === strcasecmp( (string) $p->sku, (string) $sku ) ) return $id; return 0; }
function get_current_user_id() { return (int) ( $GLOBALS['user_id'] ?? 1 ); }
function get_post_meta( $id, $k, $single = false ) {
	$id = (int) $id;
	if ( ! $GLOBALS['cache_sim'] ) return $GLOBALS['postmeta'][ $id ][ $k ] ?? '';
	if ( ! array_key_exists( $id, $GLOBALS['rt_meta'] ) ) $GLOBALS['rt_meta'][ $id ] = $GLOBALS['postmeta'][ $id ] ?? array();
	return $GLOBALS['rt_meta'][ $id ][ $k ] ?? '';
}
// $GLOBALS['meta_trig'] = array( clave, callback ): al escribir esa meta, otra petición corre el callback (una vez).
$GLOBALS['meta_trig'] = null;
function update_post_meta( $id, $k, $v ) {
	$GLOBALS['postmeta'][ (int) $id ][ $k ] = $v; unset( $GLOBALS['rt_meta'][ (int) $id ] );
	$t = $GLOBALS['meta_trig']; if ( $t && $t[0] === $k ) { $GLOBALS['meta_trig'] = null; other_request( $t[1] ); }
	return true;
}
function has_term() { return false; }

// ── WooCommerce ─────────────────────────────────────────────────────────────────────────────────────────────
class Digi_Test_Product {
	public $id; public $name; public $price; public $sale = ''; public $saves = 0; public $sku = '';
	public function get_sku() { return $this->sku; } public function get_price() { return (string) ( '' !== (string) $this->sale ? $this->sale : $this->price ); }
	public function __construct( $id, $name, $price ) { $this->id = $id; $this->name = $name; $this->price = $price; }
	public function get_id() { return $this->id; } public function get_name() { return $this->name; }
	public function get_regular_price() { return (string) $this->price; } public function set_regular_price( $p ) { $this->price = $p; }
	public function get_sale_price() { return (string) $this->sale; } public function set_sale_price( $p ) { $this->sale = $p; }
	public function save() { $this->saves++; return $this->id; }
}
$GLOBALS['products'] = array();
function digi_product( $id, $name, $price, $plan = '' ) { $GLOBALS['products'][ $id ] = new Digi_Test_Product( $id, $name, $price ); if ( $plan ) update_post_meta( $id, '_digitalisimo_webuzo_plan', $plan ); }
function wc_get_product( $id ) { return $GLOBALS['products'][ (int) $id ] ?? null; }
class Digi_Test_Item {
	public $product; public $variation = 0; public $qty = 1; public $meta = array(); public $name;
	public $total;
	public function __construct( $pid, $name, $qty = 1, $meta = array(), $total = null ) { $this->product = $pid; $this->name = $name; $this->qty = $qty; $this->meta = $meta; $p = $GLOBALS['products'][ $pid ] ?? null; $this->total = null !== $total ? $total : ( $p ? (float) $p->price * $qty : 0 ); }
	public function get_total() { return $this->total; }
	public function get_product_id() { return $this->product; } public function get_variation_id() { return $this->variation; }
	public function get_quantity() { return $this->qty; } public function get_name() { return $this->name; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function add_meta_data( $k, $v, $unique = false ) { $this->meta[ $k ] = $v; }
	public function save() { return true; }
}
class WC_Order {
	public $id; public $status = 'pending'; public $items = array(); public $meta = array(); public $notes = array(); public $billing = array(); public $customer = 7; public $total = 0;
	/** Pasarela en línea por defecto (paga y fecha el pago al pasar a «procesando»); 'cod', 'bacs', 'cheque' o '' son fuera de línea. */
	public $method = 'stripe'; public $date_paid = 0; public $created = 0;
	public function get_payment_method() { return $this->method; }
	/** Número de transacción: lo deja payment_complete() de una pasarela en línea (las fuera de línea no). */
	public $txn = null; public $currency = 'MXN';
	public function get_transaction_id() { return null !== $this->txn ? $this->txn : ( in_array( $this->method, array( 'cod', 'bacs', 'cheque', '' ), true ) ? '' : 'txn_' . $this->id ); }
	public function get_currency() { return $this->currency; }
	public function get_date_paid() { return $this->date_paid ? new DateTime( '@' . $this->date_paid ) : null; }
	public function get_date_created() { return new DateTime( '@' . ( $this->created ?: time() ) ); }
	public function delete_meta_data( $k ) { unset( $this->meta[ $k ] ); }
	public function get_id() { return $this->id; }
	public function get_status() { return $this->status; }
	public function get_items() { return $this->items; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function save() { $GLOBALS['saved'][ $this->id ] = json_encode( array( $this->status, $this->meta ) ); return $this->id; }
	public function add_order_note( $n, $customer = 0 ) { $this->notes[] = $n; return count( $this->notes ); }
	public function update_status( $s, $note = '' ) { $old = $this->status; $this->status = $s;
		// Como WC_Order::maybe_set_date_paid(): una pasarela en línea fecha el pago al pasar a un estado pagado.
		if ( ! $this->date_paid && in_array( $s, array( 'processing', 'completed' ), true ) && ! in_array( $this->method, array( 'cod', 'bacs', 'cheque', '' ), true ) ) $this->date_paid = time(); if ( $note ) $this->add_order_note( $note ); $this->save(); if ( $old !== $s ) do_action( 'woocommerce_order_status_' . $s, $this->id, $this ); return true; }
	public function get_customer_id() { return $this->customer; }
	public function __call( $name, $args ) {
		if ( preg_match( '/^get_billing_(\w+)$/', $name, $m ) ) return $this->billing[ $m[1] ] ?? '';
		if ( preg_match( '/^set_billing_(\w+)$/', $name, $m ) ) { $this->billing[ $m[1] ] = $args[0]; return null; }
		throw new Error( 'WC_Order::' . $name . ' no existe en el arnés' );
	}
	public function get_address( $t ) { return $this->billing; }
	public function set_address( $a, $t ) { $this->billing = $a; }
	public function get_item( $id ) { return $this->items[ $id - 1 ] ?? false; }
	public function add_product( $p, $qty ) { $this->items[] = new Digi_Test_Item( $p->get_id(), $p->get_name(), $qty ); $this->total += $p->price; return count( $this->items ); }
	public function calculate_totals() { return $this->total; }
	public function needs_payment() { return in_array( $this->status, array( 'pending', 'failed' ), true ); }
	public function is_paid() { return in_array( $this->status, array( 'processing', 'completed' ), true ); }
	public function get_checkout_payment_url() { return 'https://tienda.test/checkout/order-pay/' . $this->id . '/?pay_for_order=true&key=wc_order_x'; }
	public function get_edit_order_url() { return admin_url( 'post.php?post=' . $this->id . '&action=edit' ); }
}
$GLOBALS['orders'] = array(); $GLOBALS['next_order'] = 500;
function wc_get_order( $id ) { return $GLOBALS['orders'][ (int) $id ] ?? false; }
function wc_create_order( $a = array() ) { $o = new WC_Order(); $o->id = ++$GLOBALS['next_order']; $o->customer = (int) ( $a['customer_id'] ?? 0 ); $o->created = time(); $GLOBALS['orders'][ $o->id ] = $o; return $o; }
function wc_add_order_item_meta( $item_id, $k, $v ) { foreach ( $GLOBALS['orders'] as $o ) { if ( isset( $o->items[ $item_id - 1 ] ) && $o->id === $GLOBALS['next_order'] ) $o->items[ $item_id - 1 ]->meta[ $k ] = $v; } return true; }
/** Un pedido del cliente, como lo deja el checkout. */
function digi_order( array $items, array $billing = array() ) {
	$o = wc_create_order( array( 'customer_id' => 7 ) );
	$o->status = 'on-hold';
	foreach ( $items as $i ) $o->items[] = $i;
	$o->billing = $billing + array( 'first_name' => 'Ana', 'last_name' => 'López', 'company' => '', 'address_1' => 'Av. Juárez 100', 'address_2' => '', 'city' => 'Guadalajara', 'state' => 'JA', 'postcode' => '44100', 'country' => 'MX', 'phone' => '33 1234 5678', 'email' => 'ana@cliente.test' );
	return $o;
}
class Digi_Test_Countries {
	public function get_states( $cc ) { return 'MX' === $cc ? array( 'JA' => 'Jalisco', 'CX' => 'Ciudad de M&eacute;xico' ) : array(); }
	public function get_country_calling_code( $cc ) { return array( 'MX' => '+52', 'US' => '+1' )[ $cc ] ?? ''; }
}
class Digi_Test_Mailer { private $emails = null; public function get_emails() { if ( null === $this->emails ) $this->emails = apply_filters( 'woocommerce_email_classes', array() ); return $this->emails; } }
class Digi_Test_WC { public $countries; public $cart = null; private $mailer; public function __construct() { $this->countries = new Digi_Test_Countries(); $this->mailer = new Digi_Test_Mailer(); } public function mailer() { return $this->mailer; } }
function WC() { static $wc = null; if ( ! $wc ) $wc = new Digi_Test_WC(); return $wc; }
class WC_Email {
	public $id; public $title; public $description; public $customer_email = false; public $placeholders = array(); public $recipient; public $object; public $enabled = 'yes';
	public function __construct() { $this->enabled = get_option( 'woocommerce_' . $this->id . '_settings', array( 'enabled' => 'yes' ) )['enabled'] ?? 'yes'; }
	public function is_enabled() { return 'yes' === $this->enabled; }
	public function get_recipient() { return $this->recipient; }
	public function format_string( $s ) { return strtr( $s, $this->placeholders ); }
	public function get_subject() { return $this->format_string( $this->get_default_subject() ); }
	public function get_heading() { return $this->format_string( $this->get_default_heading() ); }
	public function get_additional_content() { return $this->get_default_additional_content(); }
	public function get_content() { return $this->get_content_html(); }
	public function get_headers() { return ''; } public function get_attachments() { return array(); }
	public function send( $to, $subject, $message, $headers, $att ) { return wp_mail( $to, $subject, $message, $headers, $att ); }
}

// ── Action Scheduler ────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['queue'] = array();
function as_enqueue_async_action( $hook, $args = array(), $group = '' ) { $GLOBALS['queue'][] = array( 'hook' => $hook, 'args' => $args, 'at' => 0, 'group' => $group ); return count( $GLOBALS['queue'] ); }
function as_schedule_single_action( $t, $hook, $args = array(), $group = '' ) { $GLOBALS['queue'][] = array( 'hook' => $hook, 'args' => $args, 'at' => $t, 'group' => $group ); return count( $GLOBALS['queue'] ); }
function as_schedule_recurring_action( $t, $interval, $hook, $args = array(), $group = '' ) { $GLOBALS['recurring'][ $hook ] = array( $t, $interval, $group ); return 1; }
function as_has_scheduled_action( $hook, $args = null, $group = '' ) { foreach ( $GLOBALS['queue'] as $a ) if ( $a['hook'] === $hook && ( null === $args || $a['args'] === $args ) ) return true; return isset( $GLOBALS['recurring'][ $hook ] ); }
function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) { $GLOBALS['queue'] = array_values( array_filter( $GLOBALS['queue'], function ( $a ) use ( $hook, $args ) { return ! ( $a['hook'] === $hook && ( ! $args || $a['args'] === $args ) ); } ) ); }
/** Corre lo encolado (también lo programado a futuro si $all); devuelve cuántas tareas corrió. */
function digi_run_queue( $all = true, $max = 50 ) {
	$n = 0;
	while ( $GLOBALS['queue'] && $n < $max ) {
		$a = array_shift( $GLOBALS['queue'] );
		if ( ! $all && $a['at'] > time() ) { $GLOBALS['queue'][] = $a; break; }
		do_action( $a['hook'], ...$a['args'] ); $n++;
	}
	return $n;
}

// ── Servidores simulados ────────────────────────────────────────────────────────────────────────────────────
/**
 * Webuzo: cuentas en $GLOBALS['webuzo']['users']; modos: 'auth' (clave rechazada con el texto de bloqueo por intentos),
 * 'html' (página de acceso), 'timeout' (las próximas N llamadas no llegan), 'lost_create' (crea la cuenta pero la
 * respuesta se pierde). Anota cada llamada (act y campos, sin la clave) en $GLOBALS['webuzo']['calls'].
 * Namecheap: dominios de la cuenta en $GLOBALS['nc']['domains'] (dominio => vencimiento), saldo, precios y modos
 * 'timeout' (por comando) y 'auth'.
 */
function digi_servers_reset() {
	$GLOBALS['webuzo'] = array( 'users' => array( 'otro' => array( 'user' => 'otro', 'domain' => 'otro-cliente.com', 'status' => '' ) ), 'calls' => array(), 'mode' => '', 'timeout' => 0, 'key' => 'CLAVE-WEBUZO-OK', 'keys_seen' => array() );
	$GLOBALS['nc'] = array( 'domains' => array(), 'balance' => 50.0, 'price' => array( 'com' => 11.28, 'mx' => 25.0 ), 'calls' => array(), 'timeout' => array(), 'mode' => '' );
	$GLOBALS['fx'] = array( 'token' => 'TOKEN-BANXICO', 'banxico' => 'ok', 'banxico_rate' => '18.5000', 'frankfurter' => 'ok', 'frankfurter_rate' => 18.6, 'calls' => array() );
}
/** Precio de Namecheap: un número (registro = renovación, cargo ICANN 0.18) o array( REGISTER, RENEW, extra ). */
function digi_nc_price( $tld, $action ) {
	$p = $GLOBALS['nc']['price'][ $tld ] ?? 9.99;
	if ( is_array( $p ) ) return array( (float) $p[ $action ], (float) ( $p['extra'] ?? 0 ) );
	return array( (float) $p, 0.18 );
}
digi_servers_reset();
function digi_json( $data, $code = 200 ) { return array( 'response' => array( 'code' => $code ), 'body' => json_encode( $data ), 'headers' => array() ); }
function digi_xml( $inner, $ok = true ) { return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '<?xml version="1.0" encoding="utf-8"?><ApiResponse Status="' . ( $ok ? 'OK' : 'ERROR' ) . '" xmlns="http://api.namecheap.com/xml.response">' . ( $ok ? '<Errors /><CommandResponse>' . $inner . '</CommandResponse>' : '<Errors>' . $inner . '</Errors>' ) . '</ApiResponse>' ); }
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false !== $pre ) return $pre; // otro filtro ya contestó (una falla puntual de una prueba)
		$body = (array) ( $args['body'] ?? array() );
		if ( 0 === strpos( $url, 'https://webuzo.test:2005/' ) ) {
			$w = &$GLOBALS['webuzo'];
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
			$fields = $body; unset( $fields['apikey'] );
			$w['calls'][] = array( 'act' => $q['act'] ?? '', 'fields' => $fields, 'url' => $url );
			$w['keys_seen'][] = $body['apikey'] ?? '';
			if ( $w['timeout'] > 0 ) { $w['timeout']--; return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 45001 milliseconds' ); }
			if ( 'html' === $w['mode'] ) return array( 'response' => array( 'code' => 200 ), 'body' => '<html><body>Login</body></html>', 'headers' => array() );
			if ( 'auth' === $w['mode'] || ( $body['apikey'] ?? '' ) !== $w['key'] ) return digi_json( array( 'error' => array( 'Incorrect Username or Password. 1 attempt(s) left' ) ) );
			$act = $q['act'] ?? '';
			// Como el Webuzo real (prueba autorizada del 2026-11-24): con skip=1 contesta éxito SIN cambiar nada; sin skip el
			// cambio se ve en la lista (en el modo 'lento', una consulta después; en 'ignora', nunca). Errores como objeto
			// por código y textos en español, todo con HTTP 200.
			foreach ( array( 'suspend' => 'suspended', 'unsuspend' => '' ) as $op => $to ) {
				if ( 'users' !== $act || ! isset( $body[ $op ] ) ) continue;
				if ( ! isset( $w['users'][ $body[ $op ] ] ) ) return digi_json( array( 'error' => array( 'no_user' => 'No existe tal usuario' ), 'users' => null ) );
				if ( empty( $body['skip'] ) && 'ignora' !== $w['mode'] ) { if ( 'lento' === $w['mode'] ) $w['later'][ $body[ $op ] ] = $to; else { $w['users'][ $body[ $op ] ]['status'] = $to; if ( '' === $to ) unset( $w['users'][ $body[ $op ] ]['suspend_reason'] ); } }
				return digi_json( array( 'done' => array( 'msg' => 'suspend' === $op ? 'Usuario suspendido con éxito' : 'Usuario reactivado con éxito' ), 'users' => $w['users'], 'num_res' => count( $w['users'] ), 'reslen' => 50, 'cur_page' => 0 ) );
			}
			if ( 'users' === $act ) {
				$list = digi_json( array( 'title' => 'Webuzo - Panel de administrador', 'users' => $w['users'], 'resellers' => array(), 'num_res' => count( $w['users'] ), 'reslen' => 50, 'cur_page' => 0 ) );
				foreach ( (array) ( $w['later'] ?? array() ) as $u => $to ) $w['users'][ $u ]['status'] = $to;
				$w['later'] = array();
				return $list;
			}
			if ( 'add_user' === $act && 'nodone' === $w['mode'] ) return digi_json( array( 'title' => 'Webuzo' ) );
			if ( 'add_user' === $act && 'echo' === $w['mode'] ) return digi_json( array( 'error' => array( str_repeat( 'x', 280 ) . ' The password ' . $body['user_passwd'] . ' is too weak.' ) ) );
			if ( 'add_user' === $act && ! empty( $body['create_user'] ) ) {
				if ( isset( $w['users'][ $body['user'] ] ) ) return digi_json( array( 'error' => array( 'user_exists' => 'El usuario ya existe' ), 'plans' => array() ) );
				if ( $body['user_passwd'] !== $body['cnf_user_passwd'] ) return digi_json( array( 'error' => array( 'pass_mismatch' => 'Las contraseñas ingresadas no coinciden.' ), 'plans' => array() ) );
				$w['users'][ $body['user'] ] = array( 'user' => $body['user'], 'domain' => $body['domain'], 'email' => $body['email'], 'plan' => $body['plan'], 'status' => '', 'pass' => $body['user_passwd'] );
				if ( 'lost_create' === $w['mode'] ) { $w['mode'] = ''; return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ); }
				// Como el real: done trae la contraseña EN CLARO en data.user_passwd.
				return digi_json( array( 'title' => 'Webuzo', 'plans' => array(), 'done' => array( 'msg' => 'El usuario se guardó con éxito', 'data' => array( 'domain' => $body['domain'], 'user' => $body['user'], 'user_passwd' => $body['user_passwd'], 'email' => $body['email'], 'plan' => $body['plan'] ), 'user' => array( 'domain' => $body['domain'], 'user' => $body['user'], 'plan' => $body['plan'] ) ) ) );
			}
			return digi_json( array( 'error' => array( 'act' => 'Acción desconocida' ) ) );
		}
		if ( 0 === strpos( $url, 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/oportuno' ) ) {
			$f = &$GLOBALS['fx'];
			$f['calls'][] = array( 'src' => 'banxico', 'token' => $args['headers']['Bmx-Token'] ?? '' );
			if ( 'down' === $f['banxico'] ) return new WP_Error( 'http_request_failed', 'cURL error 28' );
			if ( ( $args['headers']['Bmx-Token'] ?? '' ) !== $f['token'] ) return array( 'response' => array( 'code' => 401 ), 'body' => '{"error":{"mensaje":"Token inválido"}}', 'headers' => array() );
			return digi_json( array( 'bmx' => array( 'series' => array( array( 'idSerie' => 'SF43718', 'titulo' => 'Tipo de cambio Pesos por dólar E.U.A. Fecha de determinación (FIX)', 'datos' => array( array( 'fecha' => '21/11/2026', 'dato' => 'ne' === $f['banxico'] ? 'N/E' : $f['banxico_rate'] ) ) ) ) ) ) );
		}
		if ( 0 === strpos( $url, 'https://api.frankfurter.dev/v2/rate/usd/mxn' ) ) {
			$f = &$GLOBALS['fx'];
			$f['calls'][] = array( 'src' => 'frankfurter' );
			if ( 'down' === $f['frankfurter'] ) return array( 'response' => array( 'code' => 503 ), 'body' => '', 'headers' => array() );
			return digi_json( array( 'date' => '2026-11-21', 'base' => 'USD', 'quote' => 'MXN', 'rate' => $f['frankfurter_rate'] ) );
		}
		if ( 0 === strpos( $url, 'https://api.namecheap.com/xml.response' ) || 0 === strpos( $url, 'https://api.sandbox.namecheap.com/' ) ) {
			$n = &$GLOBALS['nc'];
			$cmd = (string) ( $body['Command'] ?? '' );
			$fields = $body; unset( $fields['ApiKey'] );
			$n['calls'][] = array( 'cmd' => $cmd, 'fields' => $fields, 'key' => $body['ApiKey'] ?? '' );
			if ( ! empty( $n['timeout'][ $cmd ] ) ) { $n['timeout'][ $cmd ]--; return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ); }
			if ( 'auth' === $n['mode'] ) return digi_xml( '<Error Number="1011102">Parameter APIKey is invalid</Error>', false );
			switch ( $cmd ) {
				case 'namecheap.users.getBalances':
					return digi_xml( '<UserGetBalancesResult Currency="USD" AvailableBalance="' . number_format( $n['balance'], 2, '.', '' ) . '" AccountBalance="' . number_format( $n['balance'], 2, '.', '' ) . '" />' );
				case 'namecheap.users.getPricing':
					list( $p, $extra ) = digi_nc_price( $body['ProductName'], $body['ActionName'] );
					$attr = $n['extra_attr'] ?? 'YourAdditonalCost'; // así, mal escrito, lo manda Namecheap
					return digi_xml( '<UserGetPricingResult><ProductType Name="domains"><ProductCategory Name="' . strtolower( $body['ActionName'] ) . '"><Product Name="' . $body['ProductName'] . '"><Price Duration="1" DurationType="YEAR" Price="' . ( $p + 5 ) . '" PricingType="MULTIPLE" ' . ( 'AdditionalCost' === $attr ? '' : 'AdditionalCost="' . ( $extra + 9 ) . '" ' ) . 'YourPrice="' . $p . '" ' . $attr . '="' . $extra . '" Currency="USD" /><Price Duration="2" DurationType="YEAR" Price="' . ( 2 * $p ) . '" YourPrice="' . ( 2 * $p ) . '" ' . $attr . '="' . ( 2 * $extra ) . '" Currency="USD" /></Product></ProductCategory></ProductType></UserGetPricingResult>' );
				case 'namecheap.domains.getInfo':
					$d = $body['DomainName'];
					if ( ! isset( $n['domains'][ $d ] ) ) return digi_xml( '<Error Number="2019166">Domain not found</Error>', false );
					return digi_xml( '<DomainGetInfoResult Status="Ok" DomainName="' . $d . '"><DomainDetails><CreatedDate>' . gmdate( 'm/d/Y', $n['created'][ $d ] ?? gmmktime( 0, 0, 0, 1, 1, 2026 ) ) . '</CreatedDate><ExpiredDate>' . gmdate( 'm/d/Y', $n['domains'][ $d ] ) . '</ExpiredDate></DomainDetails></DomainGetInfoResult>' );
				case 'namecheap.domains.check':
					$d = $body['DomainList'];
					return digi_xml( '<DomainCheckResult Domain="' . $d . '" Available="' . ( isset( $n['domains'][ $d ] ) || in_array( $d, $n['taken'] ?? array(), true ) ? 'false' : 'true' ) . '" IsPremiumName="' . ( in_array( $d, $n['premium'] ?? array(), true ) ? 'true' : 'false' ) . '" PremiumRegistrationPrice="0" />' );
				case 'namecheap.domains.create':
					$d = $body['DomainName'];
					if ( isset( $n['domains'][ $d ] ) ) return digi_xml( '<Error Number="2030280">Domain is not available</Error>', false );
					$cost = array_sum( digi_nc_price( substr( $d, strpos( $d, '.' ) + 1 ), 'REGISTER' ) );
					if ( $n['balance'] < $cost ) return digi_xml( '<Error Number="2528166">Insufficient funds</Error>', false );
					$n['balance'] -= $cost; $n['domains'][ $d ] = strtotime( '+1 year' ); $n['created'][ $d ] = time();
					return digi_xml( '<DomainCreateResult Domain="' . $d . '" Registered="true" ChargedAmount="' . number_format( $cost, 2 ) . '" DomainID="9' . count( $n['domains'] ) . '" OrderID="1" TransactionID="1" WhoisguardEnable="false" NonRealTimeDomain="false" />' );
				case 'namecheap.domains.dns.setCustom':
					$n['dns'][ $body['SLD'] . '.' . $body['TLD'] ] = $body['Nameservers'];
					return digi_xml( '<DomainDNSSetCustomResult Domain="' . $body['SLD'] . '.' . $body['TLD'] . '" Updated="true" />' );
				case 'namecheap.domains.getTldList':
					$out = '';
					foreach ( array( 'com', 'net', 'org', 'mx', 'com.mx', 'lat' ) as $t ) $out .= '<Tld Name="' . $t . '" IsApiRegisterable="true" IsApiRenewable="true" IsApiTransferable="' . ( in_array( $t, $n['no_transfer'] ?? array(), true ) ? 'false' : 'true' ) . '" IsEppRequired="true" Type="GTLD">' . $t . '</Tld>';
					return digi_xml( '<Tlds>' . $out . '</Tlds>' );
				case 'namecheap.domains.renew':
					$d = $body['DomainName'];
					$cost = array_sum( digi_nc_price( substr( $d, strpos( $d, '.' ) + 1 ), 'RENEW' ) );
					$n['balance'] -= $cost; $n['domains'][ $d ] = strtotime( '+1 year', $n['domains'][ $d ] );
					return digi_xml( '<DomainRenewResult DomainName="' . $d . '" DomainID="1" Renew="true" OrderID="2" TransactionID="2" ChargedAmount="' . number_format( $cost, 2 ) . '" />' );
			}
			return digi_xml( '<Error Number="1010000">Unknown command</Error>', false );
		}
		return $pre;
	},
	10,
	3
);

/** Contadores de llamadas por acción. */
function digi_webuzo_count( $act, $field = null, $value = null ) { $c = 0; foreach ( $GLOBALS['webuzo']['calls'] as $call ) if ( $call['act'] === $act && ( null === $field || ( $call['fields'][ $field ] ?? null ) === $value ) ) $c++; return $c; }
function digi_nc_count( $cmd ) { $c = 0; foreach ( $GLOBALS['nc']['calls'] as $call ) if ( $call['cmd'] === $cmd ) $c++; return $c; }

/** Ajustes de una tienda lista para vender (lo que el dueño configura en Digitalisimo → Hosting). */
function digi_store_settings( array $extra = array() ) {
	update_option( 'digitalisimo_hosting_ecommerce_migrated', 1 );
	update_option( 'digitalisimo_hosting_options', $extra + array(
		'enabled' => 1, 'provision_enabled' => 1, 'api_user' => 'nc-api', 'username' => 'nc-user', 'api_key' => 'CLAVE-NAMECHEAP', 'client_ip' => '203.0.113.5',
		'domain_products' => ".com|20\n.mx|21", 'hosting_product_ids' => '', 'hosting_category_ids' => '63',
		'webuzo_url' => 'https://webuzo.test:2005', 'webuzo_api_user' => 'root', 'webuzo_api_key' => 'CLAVE-WEBUZO-OK', 'grace_days' => 7,
		'nameservers' => 'ns1.digitalisimo.website, ns2.digitalisimo.website', 'provision_since' => 1,
		// Las pruebas anteriores al 2026-11-25 suponen hosting con dominio propio sin revisión manual (por omisión es 0).
		'own_domain_auto' => 1,
	) );
	update_option( 'woocommerce_calc_taxes', 'yes' ); update_option( 'woocommerce_currency', 'MXN' );
}

define( 'DIGITALISIMO_HOSTING_URL', 'https://tienda.test/wp-content/plugins/digitalisimo-hosting/' );
define( 'DIGITALISIMO_HOSTING_VERSION', '9.9.9' );
foreach ( array( 'class-hosting', 'class-hosting-products', 'class-hosting-checkout', 'class-hosting-webuzo', 'class-hosting-namecheap', 'class-hosting-pricing', 'class-hosting-provisioning', 'class-hosting-services-page' ) as $digi_file ) require __DIR__ . '/../../digitalisimo-hosting/includes/' . $digi_file . '.php';
Digitalisimo_Hosting_Provisioning::init();
digi_product( 14693, 'Web Hosting Starter', 970, 'digitalisimo_starter' );
digi_product( 14696, 'Web Hosting Pro', 5850, 'digitalisimo_pro' );
digi_product( 20, 'Dominio .com', 300 );
digi_product( 21, 'Dominio .mx', 600 );
// DNS simulado (B3, H-5): un nombre fuera de $GLOBALS['dns'] «resuelve» a una IP pública de ejemplo, sin salir a la red.
add_filter( 'digitalisimo_hosting_webuzo_host_ips', static function ( $ips, $host ) { return array_key_exists( $host, $GLOBALS['dns'] ) ? (array) $GLOBALS['dns'][ $host ] : array( '93.184.216.34' ); }, 10, 2 );
function remove_filter( $hook, $cb, $prio = 10 ) { if ( empty( $GLOBALS['hooks'][ $hook ][ $prio ] ) ) return false; $GLOBALS['hooks'][ $hook ][ $prio ] = array_values( array_filter( $GLOBALS['hooks'][ $hook ][ $prio ], function ( $h ) use ( $cb ) { return $h[0] !== $cb; } ) ); if ( ! $GLOBALS['hooks'][ $hook ][ $prio ] ) unset( $GLOBALS['hooks'][ $hook ][ $prio ] ); if ( empty( $GLOBALS['hooks'][ $hook ] ) ) unset( $GLOBALS['hooks'][ $hook ] ); return true; }
function remove_action( $hook, $cb, $prio = 10 ) { return remove_filter( $hook, $cb, $prio ); }
