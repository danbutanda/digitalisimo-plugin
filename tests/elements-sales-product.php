<?php
/**
 * Kit de ventas de DIGITALÍSIMO Elements: la landing TERMINADA de un producto de la tienda (`create-page` con
 * `product_id` y `offer`) y el terminado de cualquier página del kit (`finish`, por defecto). Caso real (plomerito,
 * 2026-11-23): la promo de Black Friday de un purificador de $10,000 a $7,500 «de hoy a fin de mes» salió como la
 * plantilla con 36 placeholders sin llenar, el WhatsApp y el logo de muestra y enlaces a páginas que no existen.
 *
 * Con un producto simulado de la tienda (nombre, descripción con lista, tres imágenes, precio 10000, oferta 7500, fin de
 * mes) y un sitio simulado (logo, pie con un enlace wa.me, privacidad y términos publicados, sin página de reembolso):
 * - la página no lleva ningún [placeholder] (ninguna cadena con «[»), en contenedores y en secciones;
 * - lleva el nombre, el precio normal tachado y el de oferta (sólo en la oferta), las tres imágenes, el botón que
 *   agrega el producto al carrito y lleva a pagar, el WhatsApp del pie del sitio y el logo del sitio;
 * - sólo enlaza a páginas que existen (ninguna ruta inventada del kit), y lo que falta se informa;
 * - sin WhatsApp en el sitio no queda el número de muestra: el botón va a contacto o se quita;
 * - una plantilla de curso sin datos (finish por defecto) tampoco deja placeholders a la vista, y con finish: false
 *   queda como antes;
 * - la oferta se valida (precio menor que el normal, fecha real, etiqueta sin enlaces) y se avisa si la tienda cobra
 *   otro precio.
 *
 * Sin WordPress ni la tienda: dobles mínimos. Falla con el código anterior: create-page rechaza product_id
 * («Parámetros desconocidos») y Site, Finisher y Product no existen.
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
	class User {
		public static function is_current_user_can_edit_post_type( $type ) {
			return true;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'OBJECT', 'OBJECT' );
	define( 'ARRAY_A', 'ARRAY_A' );

	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['sp_count'] = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['sp_count'];
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}
	function check_error( $result, string $code, string $message ) {
		check( $result instanceof WP_Error && $code === $result->get_error_code(), $message . ' (esperaba ' . $code . ', llegó ' . ( $result instanceof WP_Error ? $result->get_error_code() . ': ' . $result->get_error_message() : 'un resultado' ) . ')' );
	}

	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function add_action() {
	}
	function add_filter() {
	}
	function apply_filters( $hook, $value, ...$args ) {
		return isset( $GLOBALS['sp']['filters'][ $hook ] ) ? call_user_func( $GLOBALS['sp']['filters'][ $hook ], $value, ...$args ) : $value;
	}
	function remove_filter() {
	}
	function current_user_can( $cap, $id = 0 ) {
		if ( 'edit_post' === $cap ) {
			return ! in_array( (int) $id, $GLOBALS['sp']['not_mine'] ?? array(), true );
		}
		return 'edit_pages' === $cap;
	}
	// El guardado de Pages (create y regenerar), con un documento del constructor mínimo.
	function post_type_exists( $type ) {
		return 'page' === $type;
	}
	function post_type_supports( $type, $feature ) {
		return true;
	}
	function get_current_blog_id() {
		return $GLOBALS['sp']['blog'] ?? 1;
	}
	function get_post( $id ) {
		$p = $GLOBALS['sp']['posts'][ (int) $id ] ?? null;
		return $p ? (object) ( $p + array( 'ID' => (int) $id ) ) : null;
	}
	function wp_insert_post( $data, $wp_error = false ) {
		$id                            = 2000 + count( $GLOBALS['sp']['posts'] ?? array() );
		$GLOBALS['sp']['posts'][ $id ] = array( 'post_type' => $data['post_type'], 'post_status' => $data['post_status'], 'post_title' => $data['post_title'] );
		$GLOBALS['sp']['inserted'][]   = $id;
		return $id;
	}
	function wp_update_post( $data ) {
		foreach ( $data as $k => $v ) {
			if ( 'ID' !== $k ) {
				$GLOBALS['sp']['posts'][ (int) $data['ID'] ][ $k ] = $v;
			}
		}
	}
	function wp_delete_post( $id, $force = false ) {
		unset( $GLOBALS['sp']['posts'][ (int) $id ] );
	}
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['sp']['postmeta'][ (int) $id ][ $key ] = $value;
	}
	function remove_query_arg( $key, $url ) {
		return $url;
	}
	function get_preview_post_link( $id ) {
		return 'https://tienda.test/?page_id=' . $id . '&preview=true';
	}
	class Sp_Document {
		public $id;
		public function __construct( $id ) {
			$this->id = $id;
		}
		public function set_is_built_with_elementor( $on ) {
		}
		public function update_settings( $settings ) {
		}
		public function save( $data ) {
			$GLOBALS['sp']['saved'][ $this->id ] = $data['elements'];
			return true;
		}
		public function get_edit_url() {
			return 'https://tienda.test/wp-admin/post.php?post=' . $this->id . '&action=elementor';
		}
		public function get_wp_preview_url() {
			return 'https://tienda.test/?page_id=' . $this->id . '&preview=true';
		}
	}
	class Sp_Documents {
		public function get( $id, $cache = true ) {
			return new Sp_Document( $id );
		}
	}
	function sanitize_text_field( $value ) {
		$value = preg_replace( '#<(script|style)[^>]*?>.*?</\1>#si', '', (string) $value );
		return trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) );
	}
	function wp_kses_post( $value ) {
		return strip_tags( (string) $value, '<p><br><strong><b><em><i><span><del><a><ul><ol><li><small>' );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
	function home_url( $path = '' ) {
		return 'https://tienda.test' . $path;
	}
	function wp_timezone() {
		return new DateTimeZone( $GLOBALS['sp']['tz'] ?? 'America/Mexico_City' );
	}
	function wp_timezone_string() {
		return $GLOBALS['sp']['tz'] ?? 'America/Mexico_City';
	}
	function get_bloginfo( $what ) {
		return 'name' === $what ? 'Plomería Ensayo' : '';
	}
	function get_option( $name, $default = false ) {
		return $GLOBALS['sp']['options'][ $name ] ?? $default;
	}
	function get_theme_mod( $name ) {
		return 'custom_logo' === $name ? $GLOBALS['sp']['logo'] : false;
	}
	function wp_get_attachment_image_url( $id, $size = 'full' ) {
		return $GLOBALS['sp']['attachments'][ (int) $id ] ?? false;
	}
	function get_post_meta( $id, $key, $single = false ) {
		if ( isset( $GLOBALS['sp']['postmeta'][ (int) $id ][ $key ] ) ) {
			return $GLOBALS['sp']['postmeta'][ (int) $id ][ $key ];
		}
		return '_wp_attachment_image_alt' === $key ? 'Foto ' . $id : '';
	}
	function get_post_status( $id ) {
		return $GLOBALS['sp']['posts'][ (int) $id ]['post_status'] ?? ( $GLOBALS['sp']['pages'][ (int) $id ]['status'] ?? false );
	}
	function get_permalink( $id ) {
		if ( 252 === (int) $id ) {
			return 'https://tienda.test/producto/purificador/';
		}
		return isset( $GLOBALS['sp']['pages'][ (int) $id ] ) ? 'https://tienda.test/' . $GLOBALS['sp']['pages'][ (int) $id ]['slug'] . '/' : false;
	}
	function get_page_by_path( $slug, $output = OBJECT, $type = 'page' ) {
		foreach ( $GLOBALS['sp']['pages'] as $id => $page ) {
			if ( $slug === $page['slug'] ) {
				return (object) array( 'ID' => $id );
			}
		}
		return null;
	}
	function wc_get_page_id( $page ) {
		return $GLOBALS['sp']['wc_pages'][ $page ] ?? -1;
	}
	function wc_terms_and_conditions_page_id() {
		return $GLOBALS['sp']['wc_pages']['terms'] ?? 0;
	}
	function get_woocommerce_currency() {
		return 'MXN';
	}
	function get_woocommerce_currency_symbol() {
		return '&#36;';
	}
	function wc_attribute_label( $name, $product = null ) {
		return ucfirst( str_replace( 'pa_', '', $name ) );
	}
	function wc_get_product_terms( $id, $name, $args ) {
		return array( 'Negro' );
	}
	function wc_format_dimensions( $dims ) {
		return implode( ' &times; ', array_filter( $dims ) ) . ' cm';
	}
	function get_comments( $args ) {
		return $GLOBALS['sp']['reviews'];
	}
	function get_comment_meta( $id, $key, $single ) {
		return 'rating' === $key ? ( $GLOBALS['sp']['ratings'][ (int) $id ] ?? 5 ) : true;
	}
	function wc_get_product( $id ) {
		return 252 === (int) $id ? $GLOBALS['sp']['product'] : ( 253 === (int) $id ? new Sp_Product( array( 'status' => 'draft' ) ) : false );
	}
	class Sp_Gateway {
		public $enabled;
		private $title;
		public function __construct( $title, $enabled ) {
			$this->title   = $title;
			$this->enabled = $enabled;
		}
		public function get_title() {
			return $this->title;
		}
	}
	class Sp_Gateways {
		public function payment_gateways() {
			return array( new Sp_Gateway( 'Tarjeta de crédito o débito', 'yes' ), new Sp_Gateway( 'Transferencia bancaria', 'yes' ), new Sp_Gateway( 'Pago contra entrega', 'no' ) );
		}
	}
	class Sp_Wc {
		public function payment_gateways() {
			return new Sp_Gateways();
		}
	}
	function WC() {
		return new Sp_Wc();
	}
	/** Un atributo visible de la tienda. */
	class Sp_Attribute {
		private $name;
		private $options;
		private $taxonomy;
		public function __construct( $name, $options, $taxonomy ) {
			$this->name     = $name;
			$this->options  = $options;
			$this->taxonomy = $taxonomy;
		}
		public function get_visible() {
			return true;
		}
		public function get_name() {
			return $this->name;
		}
		public function is_taxonomy() {
			return $this->taxonomy;
		}
		public function get_options() {
			return $this->options;
		}
	}
	/** El producto del caso: Purificador y Alcalinizador ROTOPLAS, $10,000. */
	class Sp_Product {
		public $d;
		public function __construct( array $d = array() ) {
			$this->d = $d + array(
				'status'      => 'publish',
				'type'        => 'simple',
				'name'        => 'Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS',
				'short'       => '<p>Agua purificada y alcalina directo de tu tarja, sin garrafones. Instalación sencilla sobre la tarja.</p>',
				'description' => '<p>El purificador elimina el cloro, el sarro y el mal sabor del agua de la llave.</p><ul><li>Agua alcalina: eleva el pH del agua para un mejor sabor</li><li>Filtra el 99 % de bacterias y sedimentos</li><li>Sin garrafones: ahorra dinero y espacio</li><li>Cartucho de larga duración</li></ul><p>Incluye garantía de 1 año contra defectos de fabricación.</p>',
				'regular'     => '10000',
				'sale'        => '',
				'price'       => '10000',
				'image'       => 501,
				'gallery'     => array( 502, 503 ),
				'stock'       => true,
			);
		}
		public function get_status() {
			return $this->d['status'];
		}
		public function get_type() {
			return $this->d['type'];
		}
		public function get_name() {
			return $this->d['name'];
		}
		public function get_short_description() {
			return $this->d['short'];
		}
		public function get_description() {
			return $this->d['description'];
		}
		public function get_regular_price() {
			return $this->d['regular'];
		}
		public function get_sale_price() {
			return $this->d['sale'];
		}
		public function get_price() {
			return $this->d['price'];
		}
		public function get_image_id() {
			return $this->d['image'];
		}
		public function get_gallery_image_ids() {
			return $this->d['gallery'];
		}
		public function get_attributes() {
			return $this->d['attributes'] ?? array( new Sp_Attribute( 'pa_color', array(), true ), new Sp_Attribute( 'Capacidad', array( '2 litros por minuto' ), false ) );
		}
		public function get_date_on_sale_to() {
			return null;
		}
		public function has_weight() {
			return true;
		}
		public function get_weight() {
			return '3.5';
		}
		public function has_dimensions() {
			return true;
		}
		public function get_dimensions( $formatted ) {
			return array( 30, 20, 45 );
		}
		public function is_purchasable() {
			return true;
		}
		public function is_in_stock() {
			return $this->d['stock'];
		}
		public function get_post_password() {
			return $this->d['password'] ?? '';
		}
		public function is_virtual() {
			return ! empty( $this->d['virtual'] );
		}
		public function is_downloadable() {
			return false;
		}
		/** Producto con variaciones: los precios por variación (como WC_Product_Variable::get_variation_prices()). */
		public function get_variation_prices( $for_display = false ) {
			return $this->d['variations'];
		}
		/** Lo que leía el código anterior: el mínimo de cada precio, de variaciones distintas. */
		public function get_variation_regular_price( $which ) {
			return min( $this->d['variations']['regular_price'] );
		}
		public function get_variation_sale_price( $which ) {
			return min( $this->d['variations']['sale_price'] );
		}
	}
	/** La base de datos: el pie del sitio (plantilla del constructor) con un enlace wa.me y una página con otro. */
	class Sp_Wpdb {
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public function prepare( $query, ...$args ) {
			return $query;
		}
		public function get_results( $query, $output ) {
			$rows = $this->rows( $query );
			if ( false !== strpos( $query, "post_password = ''" ) ) {
				$rows = array_values( array_filter( $rows, static function ( $r ) { return empty( $r['post_password'] ); } ) );
			}
			return $rows;
		}
		private function rows( $query ) {
			if ( false !== strpos( $query, "p.post_type IN ('elementor_library'" ) ) {
				return $GLOBALS['sp']['meta_rows']; // Cabecera, pie, botón flotante y menús, en su propia consulta.
			}
			if ( false !== strpos( $query, 'p.post_type NOT IN' ) ) {
				return array_slice( $GLOBALS['sp']['page_rows'], 0, 40 );
			}
			if ( false !== strpos( $query, '_elementor_data' ) ) {
				// Una sola consulta (el código anterior): las páginas editadas hace poco llenan el límite de 60.
				return array_slice( array_merge( $GLOBALS['sp']['page_rows'], $GLOBALS['sp']['meta_rows'] ), 0, 60 );
			}
			return $GLOBALS['sp']['content_rows'];
		}
	}
	/** Un método de envío de la tienda. */
	class Sp_Method {
		public $id;
		public $enabled = 'yes';
		public $requires;
		public $min_amount;
		private $title;
		public function __construct( $id, $title, $requires = '', $min_amount = '' ) {
			$this->id         = $id;
			$this->title      = $title;
			$this->requires   = $requires;
			$this->min_amount = $min_amount;
		}
		public function get_title() {
			return $this->title;
		}
	}
	class WC_Shipping_Zones {
		public static function get_zones() {
			return $GLOBALS['sp']['zones'];
		}
	}
	class WC_Shipping_Zone {
		public function __construct( $id = 0 ) {
		}
		public function get_shipping_methods( $enabled = false ) {
			return array();
		}
	}
	class Sp_Experiments {
		public function is_feature_active( $name ) {
			return 'container' === $name && ! empty( $GLOBALS['sp']['containers'] );
		}
	}

	function sp_reset( bool $whatsapp = true, bool $contact = true ) {
		$GLOBALS['sp'] = array(
			'containers'   => true,
			'logo'         => 900,
			'attachments'  => array(
				900 => 'https://tienda.test/wp-content/uploads/logo-plomeria.png',
				501 => 'https://tienda.test/wp-content/uploads/purificador-1.jpg',
				502 => 'https://tienda.test/wp-content/uploads/purificador-2.jpg',
				503 => 'https://tienda.test/wp-content/uploads/purificador-3.jpg',
			),
			'options'      => array( 'wp_page_for_privacy_policy' => 3, 'woocommerce_weight_unit' => 'kg' ),
			'pages'        => array(
				3  => array( 'slug' => 'aviso-de-privacidad-plomeria', 'status' => 'publish' ),
				7  => array( 'slug' => 'terminos-de-servicio', 'status' => 'publish' ),
				8  => array( 'slug' => 'carrito', 'status' => 'publish' ),
				9  => array( 'slug' => 'finalizar-compra', 'status' => 'publish' ),
				10 => array( 'slug' => 'politica-de-reembolso', 'status' => 'draft' ), // Existe, pero en borrador: no cuenta.
			),
			'wc_pages'     => array( 'terms' => 7, 'cart' => 8, 'checkout' => 9 ),
			'product'      => new Sp_Product(),
			'reviews'      => array(),
			'meta_rows'    => array(),
			'page_rows'    => array(),
			'content_rows' => array(),
			'ratings'      => array(),
			'zones'        => array(),
		);
		if ( $contact ) {
			$GLOBALS['sp']['pages'][11] = array( 'slug' => 'contacto', 'status' => 'publish' );
		}
		if ( $whatsapp ) {
			$GLOBALS['sp']['meta_rows'] = array(
				array( 'ID' => 40, 'post_type' => 'elementor_library', 'post_title' => 'Pie', 'meta_key' => '_elementor_data', 'meta_value' => '[{"settings":{"link":{"url":"https:\/\/wa.me\/5218711112222?text=Hola"}}}]', 'conditions' => serialize( array( 'include/general' ) ) ),
			);
			$GLOBALS['sp']['content_rows'] = array(
				array( 'ID' => 41, 'post_type' => 'post', 'post_title' => 'Viejo', 'post_content' => '<a href="https://wa.me/5215550000000">viejo</a>' ),
			);
		}
		$plugin                      = new stdClass();
		$plugin->experiments         = new Sp_Experiments();
		$plugin->documents           = new Sp_Documents();
		\Elementor\Plugin::$instance = $plugin;
	}
	$GLOBALS['wpdb'] = new Sp_Wpdb();
	sp_reset();

	$base = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-sales';
	require_once $base . '/class-module.php';
	\Digitalisimo\Elements\Sales\Module::init();

	use Digitalisimo\Elements\Sales\Catalog;
	use Digitalisimo\Elements\Sales\Placeholders;
	use Digitalisimo\Elements\Sales\Site;
	use Digitalisimo\Elements\Sales\Mcp\Executor;

	/** Cada cadena de la página (texto, enlaces, ajustes) con su ruta. */
	function sp_strings( array $elements ): array {
		$out = array();
		Placeholders::map_strings(
			$elements,
			static function ( $owner, $path, $value ) use ( &$out ) {
				$out[] = array( $owner, $path, $value );
				return $value;
			}
		);
		return $out;
	}
	function sp_all( array $elements ): string {
		return implode( "\n", array_column( sp_strings( $elements ), 2 ) );
	}
	function sp_widgets( array $els, array &$out = array() ): array {
		foreach ( $els as $e ) {
			if ( 'widget' === ( $e['elType'] ?? '' ) ) {
				$out[] = $e;
			}
			sp_widgets( $e['elements'] ?? array(), $out );
		}
		return $out;
	}
	/** Los enlaces de la página (ajustes `url` y `href` de los textos). */
	function sp_links( array $elements ): array {
		$out = array();
		foreach ( sp_strings( $elements ) as $s ) {
			if ( 'url' === substr( $s[1], -3 ) && '' !== $s[2] ) {
				$out[] = $s[2];
			}
			if ( preg_match_all( '/href="([^"]*)"/', $s[2], $m ) ) {
				$out = array_merge( $out, $m[1] );
			}
		}
		return $out;
	}

	$created = array();
	$fake    = static function ( $title, $elements ) use ( &$created ) {
		$created[] = array( $title, $elements );
		return array(
			'id'          => 1040 + count( $created ),
			'status'      => 'draft',
			'edit_url'    => 'https://tienda.test/wp-admin/post.php?post=1041&action=elementor',
			'preview_url' => 'https://tienda.test/?page_id=1041&preview=true',
		);
	};
	// «De hoy a fin de mes»: el último día del mes de mañana, a las 23:59 (nunca en el pasado).
	$tz       = new DateTimeZone( 'America/Mexico_City' );
	$fin      = ( new DateTimeImmutable( 'tomorrow', $tz ) )->format( 'Y-m-t' );
	$promo    = array(
		'product_id' => 252,
		'offer'      => array(
			'price' => '7500',
			'ends'  => $fin,
			'label' => 'Black Friday',
		),
	);
	$name     = 'Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS';
	$images   = array( 'purificador-1.jpg', 'purificador-2.jpg', 'purificador-3.jpg' );
	$checkout = 'https://tienda.test/finalizar-compra/?add-to-cart=252';

	// ================================================================ 1. Site: el WhatsApp que el sitio ya publica
	check( array( '5218711112222' ) === Site::whatsapp_numbers( '<a href="https://wa.me/5218711112222?text=Hola">' ), 'wa.me' );
	check( array( '5218711112222' ) === Site::whatsapp_numbers( '"url":"https:\/\/wa.me\/+5218711112222"' ), 'wa.me en el JSON del constructor (con \\/ y +)' );
	check( array( '528711112222' ) === Site::whatsapp_numbers( 'https://api.whatsapp.com/send?phone=528711112222&text=hola' ), 'api.whatsapp.com/send?phone=' );
	check( array( '528711112222' ) === Site::whatsapp_numbers( 'https://api.whatsapp.com/send/?text=hola&amp;phone=%2B528711112222' ), 'api.whatsapp.com con phone después del texto' );
	check( array( '528711112222' ) === Site::whatsapp_numbers( '{"chat_button_platform":"whatsapp","chat_button_number":"+52 871 111 2222"}' ), 'el botón flotante del constructor' );
	check( array() === Site::whatsapp_numbers( 'https://wa.me/52XXXXXXXXXX y tel:8711112222' ), 'el número de muestra y un teléfono no son WhatsApp' );
	check( array( '5218711112222', 'el pie' ) === array_slice( Site::pick_whatsapp( array( array( 'wa.me/5215550000000', 1, 'una entrada' ), array( 'wa.me/5218711112222', 3, 'el pie' ) ) ), 0, 2 ), 'gana el número de la cabecera o el pie (más peso)' );
	$site = Site::read();
	check( '5218711112222' === $site['whatsapp'] && false !== strpos( $site['whatsapp_source'], 'Pie' ), 'Site::read: el WhatsApp del pie del sitio (' . $site['whatsapp'] . ' · ' . $site['whatsapp_source'] . ')' );
	check( 'https://tienda.test/aviso-de-privacidad-plomeria/' === $site['links']['/aviso-de-privacidad/'] && 'https://tienda.test/terminos-de-servicio/' === $site['links']['/terminos-y-condiciones/'], 'Site::read: privacidad y términos, las páginas que el sitio declara' );
	check( '' === $site['links']['/politica-de-reembolso/'] && '' === $site['links']['/gracias-compra/'], 'Site::read: una página en borrador o que no existe no cuenta' );
	check( 'https://tienda.test/contacto/' === $site['contact_url'] && 'https://tienda.test/wp-content/uploads/logo-plomeria.png' === $site['logo']['url'] && array( 'Tarjeta de crédito o débito', 'Transferencia bancaria' ) === $site['gateways'] && '$' === $site['currency_symbol'], 'Site::read: contacto, logo, medios de pago activos y moneda' );

	// ================================================================ 2. La landing del producto del caso
	foreach ( array( true, false ) as $containers ) {
		$variant = $containers ? 'contenedores' : 'secciones';
		sp_reset();
		$GLOBALS['sp']['containers'] = $containers;
		$created                     = array();
		$out                         = Executor::run( 'create-page', $promo, $fake );
		check( is_array( $out ) && true === $out['ok'], "$variant: create-page con product_id crea la página (" . ( $out instanceof WP_Error ? $out->get_error_message() : '' ) . ')' );
		$els = $created[0][1];
		$all = sp_all( $els );
		check( 'Black Friday: ' . $name === $out['title'] && 'venta-corta' === $out['template'] && 'draft' === $out['status'] && true === $out['finished'], "$variant: borrador terminado de la venta corta, con título de la promo" );
		$left = array_filter(
			sp_strings( $els ),
			static function ( $s ) {
				return false !== strpos( $s[2], '[' );
			}
		);
		check( ! $left, "$variant: ningún [placeholder] en la página: " . json_encode( array_slice( array_values( $left ), 0, 3 ), JSON_UNESCAPED_UNICODE ) );
		check( array() === $out['placeholders_left'], "$variant: la respuesta dice que no queda ningún placeholder" );
		check( substr_count( $all, $name ) >= 3, "$variant: el nombre del producto (portada, «Conócelo», oferta)" );
		check( false !== strpos( $all, '<del>$10,000 MXN</del>' ) && false !== strpos( $all, '$7,500 MXN' ) && false !== strpos( $all, 'Ahorras $2,500 MXN (25 %)' ), "$variant: precio normal tachado, precio de oferta y ahorro" );
		foreach ( $images as $image ) {
			check( false !== strpos( $all, 'https://tienda.test/wp-content/uploads/' . $image ), "$variant: la imagen del producto $image" );
		}
		check( false === strpos( $all, 'placeholder.png' ) && ! array_filter( sp_widgets( $els ), static function ( $w ) { return 'image' === $w['widgetType'] && empty( $w['settings']['image']['url'] ); } ), "$variant: ninguna imagen de muestra" );
		check( substr_count( $all, 'logo-plomeria.png' ) >= 2, "$variant: el logo del sitio en la portada, la barra y el pie" );
		$buttons = array_values( array_filter( sp_widgets( $els ), static function ( $w ) { return 'button' === $w['widgetType']; } ) );
		$buy     = array_values( array_filter( $buttons, static function ( $w ) use ( $checkout ) { return $checkout === ( $w['settings']['link']['url'] ?? '' ); } ) );
		check( 1 === count( $buy ) && '¡Sí, lo quiero ahora!' === $buy[0]['settings']['text'], "$variant: el botón de la oferta agrega el producto al carrito y lleva a pagar" );
		check( false !== strpos( $all, 'https://wa.me/5218711112222' ) && false === strpos( $all, '52XXXXXXXXXX' ) && false === strpos( $all, '5215550000000' ), "$variant: el WhatsApp del pie del sitio, nunca el de muestra" );
		check( '5218711112222' === $out['whatsapp'] && false !== strpos( $out['whatsapp_source'], 'Pie' ), "$variant: la respuesta dice de dónde salió el WhatsApp" );
		// Sólo páginas que existen.
		$real = array( 'https://tienda.test/aviso-de-privacidad-plomeria/', 'https://tienda.test/terminos-de-servicio/', $checkout );
		foreach ( sp_links( $els ) as $link ) {
			$ok = in_array( $link, $real, true ) || 0 === strpos( $link, 'https://wa.me/5218711112222' ) || 0 === strpos( $link, 'https://tienda.test/wp-content/uploads/' ) || '#oferta' === $link;
			check( $ok, "$variant: un enlace a una página que no existe o a otro sitio: $link" );
		}
		check( false !== strpos( $all, 'href="https://tienda.test/aviso-de-privacidad-plomeria/"' ) && false !== strpos( $all, 'href="https://tienda.test/terminos-de-servicio/"' ) && false === strpos( $all, 'reembolso/' ), "$variant: el pie enlaza a la privacidad y los términos reales; sin página de reembolso, sin ese enlace" );
		check( array( '/politica-de-reembolso/' ) === array_values( array_column( $out['pages_to_create'], 'path' ) ), "$variant: la respuesta dice qué página falta (" . json_encode( $out['pages_to_create'] ) . ')' );
		// El precio sólo en la oferta.
		foreach ( $els as $section ) {
			$text = sp_all( array( $section ) );
			if ( 'oferta' !== ( $section['settings']['_element_id'] ?? '' ) ) {
				check( ! preg_match( '/\$\s?[0-9]|MXN/', $text ), "$variant: un precio fuera de la oferta en «" . ( $section['settings']['_title'] ?? '' ) . '»' );
			}
		}
		// Datos del producto, no de curso.
		check( false !== strpos( $all, 'Agua alcalina' ) && false !== strpos( $all, 'Filtra el 99 % de bacterias y sedimentos' ) && false !== strpos( $all, 'Capacidad: 2 litros por minuto' ) && false !== strpos( $all, 'Peso: 3.5 kg' ), "$variant: beneficios de la lista de la descripción y características de los atributos" );
		check( false !== strpos( $all, 'Incluye garantía de 1 año contra defectos de fabricación.' ), "$variant: la garantía que dice el producto" );
		check( ! preg_match( '/lecciones|módulo|BONUS|meses sin intereses|Hola, soy|CFDI|Testimonio/u', $all ), "$variant: nada de curso ni datos inventados (lecciones, bonus, meses, factura, testimonios)" );
		check( false !== strpos( $all, 'Tarjeta de crédito o débito y Transferencia bancaria' ) && false === strpos( $all, 'OXXO' ), "$variant: los medios de pago activos de la tienda, no los de muestra" );
		check( false !== strpos( $all, 'Black Friday' ) && 1 === count( array_filter( sp_widgets( $els ), static function ( $w ) { return 'countdown' === $w['widgetType']; } ) ), "$variant: la etiqueta de la promo y la barra con la cuenta regresiva al fin de mes" );
		check( $fin . ' 23:59' === $out['close_date'] && false !== strpos( $all, 'Esta oferta termina el ' . (int) substr( $fin, 8 ) . ' de ' ) && false !== strpos( $all, 'Hasta el ' . (int) substr( $fin, 8 ) . ' de ' ) && false !== strpos( $all, 'Este precio termina el ' . (int) substr( $fin, 8 ) . ' de ' ), "$variant: el cierre real (fin de mes a las 23:59) en la barra, la oferta y las preguntas" );
		check( ! preg_match( '/\((día|mes|hora) de cierre\)/u', $all ), "$variant: ningún placeholder disfrazado entre paréntesis" );
		check( ! in_array( 'testimonios', array_column( $out['removed'], 'block' ), true ) && in_array( 'testimonios', $out['omitted_blocks'], true ), "$variant: sin reseñas reales, sin el bloque de testimonios" );
		$sources = array_column( $out['filled_from'], 'source', 'field' );
		check( isset( $sources['nombre del producto'], $sources['imágenes'], $sources['beneficios'], $sources['garantía'], $sources['whatsapp'], $sources['logo'], $sources['precio de oferta'], $sources['precio normal'] ), "$variant: filled_from dice qué se llenó y de dónde: " . implode( ', ', array_keys( $sources ) ) );
		check( array() === array_filter( $out['pending'], static function ( $p ) { return ! empty( $p['required'] ); } ), "$variant: nada obligatorio pendiente: " . json_encode( $out['pending'], JSON_UNESCAPED_UNICODE ) );
		check( (bool) preg_grep( '/La tienda cobra hoy \$10,000 MXN.*anuncia \$7,500 MXN/u', $out['review'] ), "$variant: avisa que la tienda cobra otro precio que la landing" );
		check( 'https://tienda.test/?page_id=1041&preview=true' === $out['preview_url'] && false !== strpos( $out['edit_url'], 'action=elementor' ) && false !== strpos( $out['llm_instructions'], 'filled_from' ), "$variant: enlaces de edición y vista previa, e instrucciones para reportarlo corto" );
		check( 252 === $out['product']['id'] && '7,500' === $out['product']['price'] && '10,000' === $out['product']['regular'] && $checkout === $out['product']['buy_url'], "$variant: el resumen del producto y la oferta" );
	}

	// ================================================================ 3. Sin WhatsApp en el sitio: nunca el número de muestra
	sp_reset( false, true );
	$created = array();
	$out     = Executor::run( 'create-page', $promo, $fake );
	$all     = sp_all( $created[0][1] );
	check( false === strpos( $all, '52XXXXXXXXXX' ) && false === strpos( $all, 'wa.me' ), 'sin WhatsApp en el sitio: ni el número de muestra ni wa.me' );
	$contact = array_values( array_filter( sp_widgets( $created[0][1] ), static function ( $w ) { return 'button' === $w['widgetType'] && 'https://tienda.test/contacto/' === ( $w['settings']['link']['url'] ?? '' ); } ) );
	check( 1 === count( $contact ) && 'Contáctanos' === $contact[0]['settings']['text'] && 'fas fa-envelope' === $contact[0]['settings']['selected_icon']['value'], 'sin WhatsApp: el botón lleva a la página de contacto real' );
	check( false === stripos( $all, 'whatsapp' ) && '' === $out['whatsapp'] && (bool) preg_grep( '/página de contacto/', $out['review'] ), 'sin WhatsApp: ningún texto manda a WhatsApp y la revisión lo dice' );
	sp_reset( false, false );
	$created = array();
	$out     = Executor::run( 'create-page', $promo, $fake );
	$all     = sp_all( $created[0][1] );
	check( false === strpos( $all, '52XXXXXXXXXX' ) && false === stripos( $all, 'whatsapp' ) && (bool) preg_grep( '/se quitó el botón/', $out['review'] ), 'sin WhatsApp ni contacto: se quita el botón y la revisión lo dice' );
	check( ! array_filter( sp_widgets( $created[0][1] ), static function ( $w ) { return 'button' === $w['widgetType'] && false !== strpos( (string) ( $w['settings']['link']['url'] ?? '' ), 'wa.me' ); } ), 'sin WhatsApp ni contacto: ningún botón a wa.me' );
	// El WhatsApp que se pide gana al del sitio.
	sp_reset();
	$created = array();
	$out     = Executor::run( 'create-page', $promo + array( 'whatsapp' => '5218719998888' ), $fake );
	check( false !== strpos( sp_all( $created[0][1] ), 'https://wa.me/5218719998888' ) && 'el número que se pidió' === $out['whatsapp_source'], 'el whatsapp de la entrada gana al del sitio' );

	// ================================================================ 4. Otros productos y ofertas
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'sale' => '8000', 'price' => '8000', 'description' => '<p>Sin listas.</p>', 'gallery' => array() ) );
	$GLOBALS['sp']['logo']    = 0;
	$GLOBALS['sp']['reviews'] = array( (object) array( 'comment_ID' => 1, 'comment_content' => 'Funciona muy bien, el agua sabe mejor.', 'comment_author' => 'Laura' ) );
	$created                  = array();
	$out                      = Executor::run( 'create-page', array( 'product_id' => 252 ), $fake );
	$all                      = sp_all( $created[0][1] );
	check( true === $out['ok'] && $name === $out['title'] && null === $out['close_date'], 'sin offer: el precio rebajado de la tienda, sin etiqueta y sin cierre (' . json_encode( $out['close_date'] ) . ')' );
	check( false !== strpos( $all, '<del>$10,000 MXN</del>' ) && false !== strpos( $all, '$8,000 MXN' ) && ! preg_grep( '/La tienda cobra hoy/', $out['review'] ), 'sin offer: el precio rebajado y el normal de la tienda; sin aviso de precio distinto' );
	check( false !== strpos( $all, 'Funciona muy bien, el agua sabe mejor.' ) && false !== strpos( $all, 'Laura' ) && false !== strpos( $all, '★★★★★' ), 'una reseña aprobada real va en testimonios' );
	// Una oferta más cara que lo que la tienda ya cobra (rebajado a 8,000): nunca se pide subir el precio de la tienda.
	$created = array();
	$out     = Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'price' => '9000' ) ), $fake );
	check( ! preg_grep( '/pon el precio rebajado/', $out['review'] ) && (bool) preg_grep( '/ya cobra menos \(\$8,000 MXN\).*anuncia la landing \(\$9,000 MXN\).*no hace falta tocar la tienda/u', $out['review'] ), 'una oferta más cara que el precio rebajado de la tienda: no pide subirlo, pide revisar la cifra · ' . json_encode( $out['review'], JSON_UNESCAPED_UNICODE ) );
	$out     = Executor::run( 'create-page', array( 'product_id' => 252 ), $fake );
	check( false === strpos( $all, 'logo-plomeria.png' ) && ! array_filter( sp_widgets( $created[0][1] ), static function ( $w ) { return 'image' === $w['widgetType'] && empty( $w['settings']['image']['url'] ); } ), 'sin logo en el sitio: las imágenes de muestra del logo se quitan' );
	check( ! array_filter( sp_widgets( $created[0][1] ), static function ( $w ) { return 'countdown' === $w['widgetType']; } ) && false === strpos( $all, 'termina el' ), 'sin fecha de fin real: sin contador ni textos de cierre' );
	check( ! preg_grep( '/\[/', array_column( sp_strings( $created[0][1] ), 2 ) ), 'sin offer: tampoco quedan placeholders' );
	// Sin garantía en el producto ni política de reembolso: se informa, sin inventarla.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'description' => '<ul><li>Uno</li></ul>' ) );
	$created                  = array();
	$out                      = Executor::run( 'create-page', $promo, $fake );
	$pending                  = array_values( array_filter( $out['pending'], static function ( $p ) { return 'garantia-y-preguntas' === $p['block']; } ) );
	check( 1 === count( $pending ) && $pending[0]['required'] && false !== strpos( $pending[0]['missing'], 'garantía' ) && ! preg_grep( '/garant/i', array_column( array_filter( sp_strings( $created[0][1] ), static function ( $s ) { return '_title' !== $s[1]; } ), 2 ) ), 'sin garantía real: no se inventa; se informa como pendiente con el bloque y el campo' );
	check( (bool) preg_grep( '/Faltan datos en bloques obligatorios \(garantia-y-preguntas/', $out['review'] ), 'la revisión dice qué falta en un bloque obligatorio' );
	// Agotado: el botón a la ficha.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'stock' => false ) );
	$created                  = array();
	$out                      = Executor::run( 'create-page', $promo, $fake );
	check( false !== strpos( sp_all( $created[0][1] ), 'https://tienda.test/producto/purificador/' ) && false === strpos( sp_all( $created[0][1] ), 'add-to-cart' ) && (bool) preg_grep( '/agotado/', $out['review'] ), 'agotado: el botón lleva a la ficha y se avisa' );

	// Candados de la oferta: nada se crea.
	sp_reset();
	$created = array();
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'price' => '12000' ) ), $fake ), 'digitalisimo_sales_offer', 'una oferta más cara que el precio normal' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'price' => '$7,500 MXN' ) ), $fake ), 'digitalisimo_sales_offer', 'un precio con moneda' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'price' => '7500', 'ends' => 'fin de mes' ) ), $fake ), 'digitalisimo_sales_offer', 'una fecha que no es fecha' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'price' => '7500', 'ends' => '2020-01-31' ) ), $fake ), 'digitalisimo_sales_close_date', 'una fecha que ya pasó' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'label' => 'Visita https://otro.test' ) ), $fake ), 'digitalisimo_sales_offer', 'una etiqueta con un enlace' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'offer' => array( 'precio' => '7500' ) ), $fake ), 'digitalisimo_sales_offer', 'una clave desconocida en offer' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 999 ), $fake ), 'digitalisimo_sales_product', 'un producto que no existe' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 253 ), $fake ), 'digitalisimo_sales_product', 'un producto en borrador' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'template' => 'venta-completa' ), $fake ), 'digitalisimo_sales_invalid', 'product_id con otra plantilla' );
	check_error( Executor::run( 'create-page', array( 'product_id' => 252, 'finish' => false ), $fake ), 'digitalisimo_sales_invalid', 'product_id sin terminar' );
	check_error( Executor::run( 'create-page', array( 'template' => 'venta-corta', 'title' => 'X', 'offer' => array( 'price' => '1' ) ), $fake ), 'digitalisimo_sales_invalid', 'offer sin product_id' );
	check( array() === $created, 'con un error no se crea nada' );

	// ================================================================ 5. Cualquier plantilla: terminada por defecto
	foreach ( Catalog::ids() as $id ) {
		sp_reset();
		$created = array();
		$out     = Executor::run( 'create-page', array( 'template' => $id, 'title' => 'Prueba ' . $id ), $fake );
		check( is_array( $out ) && $out['finished'], "$id: creada y terminada" );
		$left = array_filter(
			sp_strings( $created[0][1] ),
			static function ( $s ) {
				return false !== strpos( $s[2], '[' );
			}
		);
		check( ! $left, "$id: sin datos, ningún placeholder a la vista: " . json_encode( array_slice( array_values( $left ), 0, 2 ), JSON_UNESCAPED_UNICODE ) );
		$all = sp_all( $created[0][1] );
		$has_whatsapp = false !== strpos( json_encode( Catalog::load( $id, 'containers' ) ), 'wa.me' );
		check( false === strpos( $all, '52XXXXXXXXXX' ), "$id: sin el número de muestra" );
		check( ! $has_whatsapp || false !== strpos( $all, 'https://wa.me/5218711112222' ), "$id: los botones de WhatsApp de la plantilla llevan al número del sitio" );
		foreach ( sp_links( $created[0][1] ) as $link ) {
			check( '/' !== $link[0], "$id: ninguna ruta inventada del kit ($link)" );
		}
		check( ! array_filter( sp_widgets( $created[0][1] ), static function ( $w ) { return 'image' === $w['widgetType'] && empty( $w['settings']['image']['url'] ); } ), "$id: ninguna imagen de muestra" );
		check( $out['pending'] === array_values( $out['pending'] ) && ! array_filter( $out['pending'], static function ( $p ) { return ! isset( $p['block'], $p['field'], $p['missing'] ); } ), "$id: lo pendiente con su bloque y su campo" );
	}
	// Con finish: false, la plantilla queda como antes (para llenarla a mano), con lo pendiente informado.
	sp_reset();
	$created = array();
	$out     = Executor::run( 'create-page', array( 'template' => 'venta-corta', 'title' => 'A mano', 'finish' => false ), $fake );
	check( false === $out['finished'] && $out['pending_count'] > 30 && false !== strpos( sp_all( $created[0][1] ), '[Beneficio 1]' ) && false !== strpos( sp_all( $created[0][1] ), 'wa.me/5218711112222' ), 'finish: false deja los placeholders (y usa el WhatsApp del sitio)' );

	// ================================================================ 5b. Revisión adversarial (2026-11-23)
	$run = static function ( array $args, array $deps = array() ) use ( $fake, &$created ) {
		$created = array();
		$out     = Executor::run( 'create-page', $args, $fake, $deps );
		return array( $out, is_array( $out ) && $created ? sp_all( $created[0][1] ) : '' );
	};
	// 1. Un nombre con decimales, «$», «MXN» o «pesos» no es un precio: no pasa por la regla del precio fuera de la oferta.
	foreach ( array( 'Bomba periférica 0.50 HP', 'Manguera 1.25 m', 'Tinaco de 450 pesos $ MXN' ) as $nombre ) {
		sp_reset();
		$GLOBALS['sp']['product'] = new Sp_Product( array( 'name' => $nombre ) );
		list( $out, $all )        = $run( $promo );
		check( is_array( $out ) && true === $out['ok'], "R1 «{$nombre}»: create-page no lo toma por precio (" . ( $out instanceof WP_Error ? $out->get_error_message() : '' ) . ')' );
		check( false !== strpos( $all, 'text=Hola,%20tengo%20una%20pregunta%20sobre%20' . rawurlencode( $nombre ) ), "R1 «{$nombre}»: el mensaje de WhatsApp lleva el nombre" );
	}
	// 2. Pagos: neutro, sin «en línea» (transferencia y pago contra entrega no lo son).
	sp_reset();
	list( $out, $all ) = $run( $promo );
	check( false !== strpos( $all, 'Formas de pago: Tarjeta de crédito o débito y Transferencia bancaria' ) && false === strpos( $all, 'en línea' ), 'R2: «Formas de pago: …» y ningún «en línea»' );
	// 3. WhatsApp: cada origen cuenta una vez; el pie no queda fuera por las páginas recientes; aviso con los candidatos.
	sp_reset();
	$GLOBALS['sp']['meta_rows'] = array( array( 'ID' => 40, 'post_type' => 'elementor_library', 'post_title' => 'Pie', 'meta_key' => '_elementor_data', 'meta_value' => '[{"url":"https:\/\/wa.me\/5218711112222"}]', 'conditions' => serialize( array( 'include/general' ) ) ) );
	$GLOBALS['sp']['page_rows'] = array_fill( 0, 60, array( 'ID' => 50, 'post_type' => 'page', 'post_title' => 'Promos', 'meta_key' => '_elementor_data', 'meta_value' => '[{"url":"https:\/\/wa.me\/5219990001111"},{"url":"https:\/\/wa.me\/5219990001111"},{"url":"https:\/\/wa.me\/5219990001111"},{"url":"https:\/\/wa.me\/5219990001111"}]' ) );
	$GLOBALS['sp']['content_rows'] = array();
	$site = Site::read();
	check( '5218711112222' === $site['whatsapp'] && 2 === count( $site['whatsapp_candidates'] ), 'R3: gana el pie del sitio (una página que repite otro número cuenta una vez; el pie no se queda fuera del límite): ' . $site['whatsapp'] );
	list( $out, $all ) = $run( $promo );
	check( (bool) preg_grep( '/más de un número para el bloque whatsapp: 5218711112222 .*5219990001111/u', $out['review'] ) && 2 === count( $out['whatsapp_candidates'] ), 'R3: la revisión avisa los números en disputa con su origen' );
	// 11. Joinchat sólo con su plugin activo.
	sp_reset( false );
	$GLOBALS['sp']['options']['joinchat'] = array( 'telephone' => '+52 871 555 4444' );
	check( '' === Site::read()['whatsapp'], 'R11: los ajustes de Joinchat sin el plugin activo no cuentan' );
	define( 'JOINCHAT_VERSION', '5.0' );
	check( '528715554444' === Site::read()['whatsapp'], 'R11: con el plugin activo, sí' );
	// 4. Producto con variaciones: nunca el normal de una con el rebajado de otra.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product(
		array(
			'type'       => 'variable',
			'regular'    => '',
			'sale'       => '',
			'price'      => '7000',
			'variations' => array(
				'price'         => array( 11 => '8000', 12 => '7000' ),
				'regular_price' => array( 11 => '8000', 12 => '7500' ),
				'sale_price'    => array( 11 => '', 12 => '7000' ),
			),
		)
	);
	list( $out, $all ) = $run( array( 'product_id' => 252 ) );
	check( false !== strpos( $all, '<del>$7,500 MXN</del>' ) && false !== strpos( $all, 'Hoy, desde: <span>$7,000 MXN</span>' ) && false === strpos( $all, '$8,000' ) && false !== strpos( $all, 'Ahorras $500 MXN' ), 'R4: el tachado y el ahorro son de la misma variación (la más barata), con «desde»' );
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'type' => 'variable', 'regular' => '', 'sale' => '', 'price' => '7000', 'variations' => array( 'price' => array( 11 => '9000', 12 => '7000' ), 'regular_price' => array( 11 => '9000', 12 => '7000' ), 'sale_price' => array( 11 => '', 12 => '' ) ) ) );
	list( $out, $all ) = $run( array( 'product_id' => 252 ) );
	check( false !== strpos( $all, 'Desde: <span>$7,000 MXN</span>' ) && false === strpos( $all, '<del>' ) && false === strpos( $all, 'Ahorras' ), 'R4: sin rebaja en esa variación, «Desde $X» sin tachado ni ahorro' );
	// 5. Envíos: sólo métodos que llevan el producto, y sólo si el producto se envía; envío gratis, dicho.
	$envios = static function ( array $methods, array $product = array() ) use ( $run, $promo ) {
		sp_reset();
		$GLOBALS['sp']['product'] = new Sp_Product( $product );
		$site                     = Site::read();
		$site['shipping']         = $methods;
		return $run( $promo, array( 'site' => $site ) )[1];
	};
	$pickup = array( 'title' => 'Recoger en tienda', 'method' => 'local_pickup', 'free' => false );
	$flat   = array( 'title' => 'Envío estándar', 'method' => 'flat_rate', 'free' => false );
	$gratis = array( 'title' => 'Envío gratis', 'method' => 'free_shipping', 'free' => true );
	check( false === strpos( $envios( array( $pickup ) ), '¿Hacen envíos?' ), 'R5: recoger en tienda no es un envío' );
	check( false !== strpos( $envios( array( $gratis, $pickup ) ), 'Sí, el envío es gratis.' ) && false === strpos( $envios( array( $gratis ) ), 'se calcula al pagar' ), 'R5: envío gratis, dicho (sin «el costo se calcula al pagar»)' );
	check( false !== strpos( $envios( array( $flat ) ), 'Opciones de envío: Envío estándar. El costo se calcula al pagar.' ), 'R5: envío con costo' );
	check( false === strpos( $envios( array( $flat ), array( 'virtual' => true ) ), '¿Hacen envíos?' ), 'R5: un producto virtual no habla de envíos' );
	// 6. La hora del cierre, en la zona real del sitio (sin una zona con nombre, la hora va sola: «hora UTC» no le dice
	//    nada a un cliente; caso plomerito 2).
	sp_reset();
	$GLOBALS['sp']['tz'] = 'America/Tijuana';
	list( $out, $all )   = $run( $promo );
	check( substr_count( $all, '(hora de Tijuana)' ) >= 3 && false === strpos( $all, 'centro de México' ) && $fin . ' 23:59' === $out['close_date'], 'R6 America/Tijuana: la barra, la oferta y la pregunta dicen (hora de Tijuana), nunca «centro de México»' );
	sp_reset();
	$GLOBALS['sp']['tz'] = 'UTC';
	list( $out, $all )   = $run( $promo );
	check( false === strpos( $all, 'UTC' ) && false === strpos( $all, 'centro de México' ) && false !== strpos( $all, 'a las 23:59' ) && $fin . ' 23:59' === $out['close_date'], 'R6 UTC sin país de la tienda: la hora del cierre va sola, sin «hora UTC» ni «centro de México»: ' . implode( ' | ', array_filter( explode( "\n", $all ), static function ( $l ) { return false !== strpos( $l, 'UTC' ) || false !== strpos( $l, 'México' ) || false !== strpos( $l, '23:59' ); } ) ) );
	// 8. Reseñas: sin respuestas de la tienda y sólo de 4 o 5 estrellas.
	sp_reset();
	$GLOBALS['sp']['reviews'] = array(
		(object) array( 'comment_ID' => 1, 'comment_parent' => 0, 'comment_content' => 'Excelente, el agua sabe mejor.', 'comment_author' => 'Laura' ),
		(object) array( 'comment_ID' => 2, 'comment_parent' => 0, 'comment_content' => 'Regular, tardó el envío.', 'comment_author' => 'Pedro' ),
		(object) array( 'comment_ID' => 3, 'comment_parent' => 1, 'comment_content' => 'Gracias por tu compra.', 'comment_author' => 'La tienda' ),
		(object) array( 'comment_ID' => 4, 'comment_parent' => 0, 'comment_content' => 'Sin estrellas.', 'comment_author' => 'Ana' ),
	);
	$GLOBALS['sp']['ratings'] = array( 2 => 3, 4 => 0 );
	list( $out, $all )        = $run( $promo );
	check( false !== strpos( $all, 'Excelente, el agua sabe mejor.' ) && false === strpos( $all, 'Regular, tardó' ) && false === strpos( $all, 'Gracias por tu compra' ) && false === strpos( $all, 'Sin estrellas' ), 'R8: sólo reseñas de 4 o 5 estrellas, sin respuestas de la tienda' );
	// 9. Producto con contraseña: privado.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'password' => 'secreta' ) );
	check_error( $run( $promo )[0], 'digitalisimo_sales_product', 'R9: un producto con contraseña se rechaza como privado' );
	// 10. Garantía: una oración que la niega no es una garantía.
	foreach ( array( 'Este producto no incluye garantía.', 'Se vende sin garantía.', 'La garantía no aplica en liquidación.' ) as $frase ) {
		sp_reset();
		$GLOBALS['sp']['product'] = new Sp_Product( array( 'description' => '<ul><li>Uno</li></ul><p>' . $frase . '</p>' ) );
		list( $out, $all )        = $run( $promo );
		check( false === strpos( $all, '<p style="text-align:center">' . $frase ) && in_array( 'garantia-y-preguntas', array_column( $out['pending'], 'block' ), true ), "R10 «{$frase}»: no se toma como garantía (queda pendiente)" );
	}

	// ================================================================ 5c. Segunda revisión (2026-11-23)
	// S1. La hora del cierre se nombra con la zona del sitio SÓLO en los textos del cierre: la fecha de un evento (03, 04)
	//     la escribe el usuario en la hora que dice, y su «(hora del centro de México)» se queda.
	$evento = array( '[Día]' => '5', '[mes]' => 'diciembre', '[hora]' => '19:00', '[remitente]' => 'Plomería Ensayo' );
	foreach ( array( 'UTC' => 'hora UTC', 'America/Cancun' => 'hora de Cancún' ) as $zona => $etiqueta ) {
		foreach ( array( true, false ) as $terminar ) {
			$modo = $terminar ? 'terminada' : 'a mano';
			sp_reset();
			$GLOBALS['sp']['tz'] = $zona;
			list( $out, $all )   = $run( array( 'template' => 'captacion', 'title' => 'Clase', 'replacements' => $evento, 'finish' => $terminar ) );
			check( false !== strpos( $all, '5 de diciembre · 19:00 (hora del centro de México)' ) && false === strpos( $all, $etiqueta ), "S1 $zona, 03 $modo: la hora del evento sigue «(hora del centro de México)»" );
			list( $out, $all ) = $run( array( 'template' => 'gracias-registro', 'title' => 'Gracias', 'replacements' => $evento, 'finish' => $terminar ) );
			check( false !== strpos( $all, '5 de diciembre a las 19:00 (centro de México)' ) && false === strpos( $all, $etiqueta ), "S1 $zona, 04 $modo: la hora del evento sigue «(centro de México)»" );
			list( $out, $all ) = $run( array( 'template' => 'venta-corta', 'title' => 'Venta', 'close_date' => $fin . ' 23:59', 'finish' => $terminar ) );
			if ( 'UTC' === $zona ) {
				check( false !== strpos( $all, '23:59' ) && false === strpos( $all, 'hora UTC' ) && false === strpos( $all, 'de cierre] (hora del centro' ), "S1 $zona, 02 $modo: el cierre dice la hora sola, sin «hora UTC»" );
			} else {
				check( false !== strpos( $all, '23:59 (' . $etiqueta . ')' ) && false === strpos( $all, 'de cierre] (hora del centro' ), "S1 $zona, 02 $modo: el cierre dice ($etiqueta)" );
			}
		}
	}
	// S2. Envío gratis con condiciones: se dice con su condición; nunca «el envío es gratis» a secas.
	check( 'en compras desde $1,500' === Site::free_condition( 'min_amount', '1500' ) && 'con cupón' === Site::free_condition( 'coupon', '' ) && 'en compras desde $999.50 o con cupón' === Site::free_condition( 'either', '999.5' ) && 'en compras desde $1,500 y con cupón' === Site::free_condition( 'both', '1500' ) && '' === Site::free_condition( '', '' ), 'S2: la condición de un envío gratis (mínimo, cupón, cualquiera, ambos)' );
	sp_reset();
	$GLOBALS['sp']['zones'] = array(
		array( 'zone_id' => 1, 'shipping_methods' => array( new Sp_Method( 'flat_rate', 'Envío a domicilio' ), new Sp_Method( 'local_pickup', 'Recoger en tienda' ) ) ),
		array( 'zone_id' => 2, 'shipping_methods' => array( new Sp_Method( 'free_shipping', 'Envío a domicilio', 'min_amount', '1500' ) ) ),
	);
	$envio = Site::read()['shipping'];
	check( 2 === count( $envio ) && false === $envio[1]['free'] && 'en compras desde $1,500' === $envio[1]['condition'], 'S2: cada método de cada zona por separado (no se juntan por título), sin recoger en tienda' );
	list( $out, $all ) = $run( $promo );
	check( false !== strpos( $all, 'Opciones de envío: Envío a domicilio y Envío a domicilio (en compras desde $1,500). El costo se calcula al pagar.' ) && false === strpos( $all, 'el envío es gratis' ) && false === strpos( $all, 'Hay envío gratis' ), 'S2: el envío gratis con mínimo se dice con su condición' );
	$GLOBALS['sp']['zones'] = array( array( 'zone_id' => 1, 'shipping_methods' => array( new Sp_Method( 'free_shipping', 'Envío gratis', 'coupon' ) ) ) );
	list( $out, $all )      = $run( $promo );
	check( false !== strpos( $all, 'Envío gratis (con cupón)' ) && false === strpos( $all, 'el envío es gratis' ), 'S2: un envío gratis con cupón no es «el envío es gratis»' );
	// S3. Garantía: las negaciones van sobre la garantía, no sobre otra palabra.
	$garantia = static function ( string $html, array $attributes = array() ) use ( $run, $promo ) {
		sp_reset();
		$GLOBALS['sp']['product'] = new Sp_Product( array( 'description' => '<ul><li>Uno</li></ul>' . $html ) );
		if ( $attributes ) {
			$GLOBALS['sp']['product']->d['attributes'] = $attributes;
		}
		return $run( $promo );
	};
	foreach ( array( '<p>Ningún tipo de garantía.</p>', '<p>Garantía: ninguna.</p>', '<p>Garantía: -</p>' ) as $frase ) {
		list( $out, $all ) = $garantia( $frase );
		check( in_array( 'garantia-y-preguntas', array_column( $out['pending'], 'block' ), true ), 'S3 «' . strip_tags( $frase ) . '»: no es una garantía' );
	}
	foreach ( array( 'Envío sin costo y garantía de 12 meses' => 'garantía de 12 meses', 'No te preocupes, tienes garantía de 2 años' => 'tienes garantía de 2 años' ) as $frase => $ve ) {
		list( $out, $all ) = $garantia( '<p>' . $frase . '.</p>' );
		check( false !== strpos( $all, '<p style="text-align:center">' . $frase . '.</p>' ) && ! in_array( 'garantia-y-preguntas', array_column( $out['pending'], 'block' ), true ), "S3 «{$frase}»: sí es una garantía ($ve)" );
	}
	list( $out, $all ) = $garantia( '', array( new Sp_Attribute( 'Garantía', array( '-' ), false ) ) );
	check( in_array( 'garantia-y-preguntas', array_column( $out['pending'], 'block' ), true ), 'S3: el atributo «Garantía: -» no es una garantía' );
	// S4. Variaciones empatadas en precio: la de ID menor (orden estable), con su propio precio normal.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'type' => 'variable', 'regular' => '', 'sale' => '', 'price' => '7000', 'variations' => array( 'price' => array( 12 => '7000', 11 => '7000' ), 'regular_price' => array( 12 => '7500', 11 => '7000' ), 'sale_price' => array( 12 => '7000', 11 => '' ) ) ) );
	list( $out, $all )        = $run( array( 'product_id' => 252 ) );
	check( false === strpos( $all, '<del>' ) && false !== strpos( $all, 'Precio: <span>$7,000 MXN</span>' ), 'S4: con dos variaciones al mismo precio gana la de ID menor (11, sin rebaja): sin tachado' );
	// S5. WhatsApp: ni páginas con contraseña ni plantillas guardadas sin uso le ganan a la parte del sitio en uso.
	sp_reset();
	$GLOBALS['sp']['content_rows'] = array();
	for ( $i = 0; $i < 4; $i++ ) {
		$GLOBALS['sp']['content_rows'][] = array( 'ID' => 60 + $i, 'post_type' => 'page', 'post_title' => 'Privada ' . $i, 'post_password' => 'x', 'post_content' => '<a href="https://wa.me/5214440000000">x</a>' );
	}
	check( '5218711112222' === Site::read()['whatsapp'], 'S5: las páginas con contraseña no cuentan' );
	$GLOBALS['sp']['content_rows'] = array();
	$GLOBALS['sp']['meta_rows'][]  = array( 'ID' => 70, 'post_type' => 'elementor_library', 'post_title' => 'Pie viejo', 'meta_key' => '_elementor_data', 'meta_value' => '[{"url":"https:\/\/wa.me\/5213330000000"}]', 'conditions' => null );
	$GLOBALS['sp']['meta_rows'][]  = array( 'ID' => 71, 'post_type' => 'elementor_library', 'post_title' => 'Borrador de pie', 'meta_key' => '_elementor_data', 'meta_value' => '[{"url":"https:\/\/wa.me\/5213330000000"}]', 'conditions' => serialize( array() ) );
	check( '5218711112222' === Site::read()['whatsapp'], 'S5: dos plantillas guardadas sin uso pesan menos que el pie en uso' );

	// ================================================================ 5d. Regenerar en el mismo borrador y encabezados sueltos
	// T1. post_id regenera el MISMO borrador del kit (Pages de verdad, sin $create de prueba).
	sp_reset();
	$primera = Executor::run( 'create-page', $promo );
	check( is_array( $primera ) && false === $primera['regenerated'] && 'draft' === $GLOBALS['sp']['posts'][ $primera['id'] ]['post_status'] && array( 'blog' => 1, 'template' => 'venta-corta' ) === array_intersect_key( $GLOBALS['sp']['postmeta'][ $primera['id'] ]['_digitalisimo_sales_kit'], array( 'blog' => 1, 'template' => 1 ) ) && md5( '' ) === ( $GLOBALS['sp']['postmeta'][ $primera['id'] ]['_digitalisimo_sales_kit']['hash'] ?? '' ), 'T1: el borrador nuevo lleva la marca del kit (sitio, plantilla y la huella de lo que dejó)' );
	$antes   = count( $GLOBALS['sp']['inserted'] );
	$segunda = Executor::run( 'create-page', array( 'post_id' => $primera['id'], 'template' => 'venta-corta', 'title' => 'Otra vuelta' ) );
	check( is_array( $segunda ) && true === $segunda['regenerated'] && $primera['id'] === $segunda['id'] && $antes === count( $GLOBALS['sp']['inserted'] ) && 'Otra vuelta' === $GLOBALS['sp']['posts'][ $primera['id'] ]['post_title'] && 'draft' === $GLOBALS['sp']['posts'][ $primera['id'] ]['post_status'], 'T1: con post_id se regenera el mismo borrador (mismo ID, sin crear otro, sigue en borrador)' );
	check( false === strpos( json_encode( $GLOBALS['sp']['saved'][ $primera['id'] ] ), 'Purificador' ), 'T1: el borrador regenerado lleva los elementos nuevos' );
	$ajena = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Borrador de otra persona' ) );
	foreach (
		array(
			'una página que no creó el kit'    => array( $ajena, null ),
			'una página publicada del kit'     => array( $primera['id'], 'publish' ),
			'una página programada del kit'    => array( $primera['id'], 'future' ),
			'una página privada del kit'       => array( $primera['id'], 'private' ),
			'una página en revisión del kit'   => array( $primera['id'], 'pending' ),
			'una página que no existe'         => array( 99999, null ),
		) as $caso => $c
	) {
		if ( null !== $c[1] ) {
			$GLOBALS['sp']['posts'][ $c[0] ]['post_status'] = $c[1];
		}
		$saved = json_encode( $GLOBALS['sp']['saved'] ?? array() );
		$r     = Executor::run( 'create-page', array( 'post_id' => $c[0], 'template' => 'venta-corta', 'title' => 'No' ) );
		check( $r instanceof WP_Error && in_array( $r->get_error_code(), array( 'digitalisimo_sales_post_id' ), true ) && $saved === json_encode( $GLOBALS['sp']['saved'] ?? array() ), "T1: $caso no se toca ({$r->get_error_code()}: " . ( $r instanceof WP_Error ? $r->get_error_message() : '' ) . ')' );
		$GLOBALS['sp']['posts'][ $primera['id'] ]['post_status'] = 'draft';
	}
	$GLOBALS['sp']['blog'] = 2;
	check_error( Executor::run( 'create-page', array( 'post_id' => $primera['id'], 'template' => 'venta-corta', 'title' => 'No' ) ), 'digitalisimo_sales_post_id', 'T1: un borrador del kit de otro sitio de la red no se toca' );
	$GLOBALS['sp']['blog']     = 1;
	$GLOBALS['sp']['not_mine'] = array( $primera['id'] );
	check_error( Executor::run( 'create-page', array( 'post_id' => $primera['id'], 'template' => 'venta-corta', 'title' => 'No' ) ), 'digitalisimo_sales_forbidden', 'T1: un borrador del kit que el usuario no puede editar no se toca' );
	check( isset( \Digitalisimo\Elements\Sales\Mcp\Tools::input_schema( 'create-page' )['properties']['post_id'] ), 'T1: create-page admite post_id' );
	// T2. Encabezados sueltos de la descripción («Beneficios», «Características», «Detalles:») no son beneficios ni párrafos.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'short' => '<h3>Beneficios</h3><p>Agua purificada directo de tu tarja.</p>', 'description' => '<p><strong>Beneficios</strong></p><ul><li>Características:</li><li>Agua alcalina</li><li>Sin garrafones</li></ul><p>Detalles:</p><p>Características</p><ul><li>Acero inoxidable</li></ul><p>Incluye garantía de 1 año.</p>' ) );
	list( $out, $all )        = $run( $promo );
	$textos                   = array_column( array_filter( sp_strings( $created[0][1] ), static function ( $s ) { return '_title' !== $s[1]; } ), 2 );
	$sueltos                  = array_filter( $textos, static function ( $t ) { return (bool) preg_match( '/(^|>)\s*(Beneficios|Características:?|Detalles:)\s*(<|$)/u', $t ) && false === strpos( $t, 'Características: ' ); } );
	$sueltos                  = array_values( array_diff( $sueltos, array( 'Características' ) ) ); // El título propio de las características.
	check( ! $sueltos, 'T2: ningún encabezado suelto de la descripción en la página: ' . json_encode( $sueltos, JSON_UNESCAPED_UNICODE ) );
	check( false !== strpos( $all, 'Agua alcalina' ) && false !== strpos( $all, 'Acero inoxidable' ) && false !== strpos( $all, 'Agua purificada directo de tu tarja.' ), 'T2: los beneficios y los párrafos reales sí se copian' );

	// ================================================================ 5e. Caso plomerito 2 (2026-11-24)
	// U1. Sitio en UTC y tienda en México: «fin de mes» es la medianoche de la tienda (centro de México), la página lo dice
	//     así y el contador guarda ese instante en la zona del sitio (el widget lee su fecha con wp_timezone()).
	$due_of = static function ( array $els ): array {
		return array_values( array_map( static function ( $w ) { return $w['settings']['due_date'] ?? ''; }, array_filter( sp_widgets( $els ), static function ( $w ) { return 'countdown' === $w['widgetType']; } ) ) );
	};
	sp_reset();
	$GLOBALS['sp']['tz']                                    = 'UTC';
	$GLOBALS['sp']['options']['woocommerce_default_country'] = 'MX:COA';
	check( 'America/Mexico_City' === Site::sales_timezone()->getName() && 'America/Mexico_City' === Site::read()['timezone'], 'U1: con el sitio en UTC, la hora de la tienda es la de su país (México: centro)' );
	list( $out, $all ) = $run( $promo );
	$utc               = ( new DateTimeImmutable( $fin . ' 23:59', new DateTimeZone( 'America/Mexico_City' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i' );
	check( substr_count( $all, '(hora del centro de México)' ) >= 2 && false === strpos( $all, 'UTC' ) && $fin . ' 23:59' === $out['close_date'], 'U1: la página dice «(hora del centro de México)» y nunca «UTC»' );
	check( array( $utc ) === $due_of( $created[0][1] ), "U1: el contador cierra a las 23:59 de México ($utc en la hora del sitio): " . json_encode( $due_of( $created[0][1] ) ) );
	// Con el sitio en una ciudad, manda la del sitio, y el contador no se mueve.
	sp_reset();
	$GLOBALS['sp']['tz']                                    = 'America/Tijuana';
	$GLOBALS['sp']['options']['woocommerce_default_country'] = 'MX:COA';
	list( $out, $all ) = $run( $promo );
	check( 'America/Tijuana' === Site::sales_timezone()->getName() && array( $fin . ' 23:59' ) === $due_of( $created[0][1] ), 'U1: con una ciudad elegida en el sitio, manda la del sitio' );
	// Un país con una sola zona la toma; uno con varias y sin la principal anotada, se queda la del sitio.
	sp_reset();
	$GLOBALS['sp']['tz']                                    = 'UTC';
	$GLOBALS['sp']['options']['woocommerce_default_country'] = 'GT';
	check( 'America/Guatemala' === Site::sales_timezone()->getName(), 'U1: Guatemala, su única zona' );
	$GLOBALS['sp']['options']['woocommerce_default_country'] = 'RU';
	check( 'UTC' === Site::sales_timezone()->getName(), 'U1: un país con varias zonas y sin la principal anotada: la del sitio' );
	// U2. «Foto de la persona detrás de la marca»: con una sola foto del producto no queda ni a la vista ni guardada.
	sp_reset();
	$GLOBALS['sp']['product'] = new Sp_Product( array( 'image' => 501, 'gallery' => array() ) );
	list( $out, $all )        = $run( $promo );
	$fotos                    = array_values( array_filter( sp_widgets( $created[0][1] ), static function ( $w ) { return 'image' === $w['widgetType'] && false !== strpos( (string) ( $w['settings']['image']['url'] ?? '' ), 'purificador-1.jpg' ); } ) );
	check( is_array( $out ) && false === stripos( $all, 'persona detrás' ) && false === stripos( $all, 'detrás de la marca' ), 'U2: ningún texto de muestra de «quién está detrás» en la landing de producto' );
	check( $fotos && ! array_filter( $fotos, static function ( $w ) { return isset( $w['settings']['caption'] ) || 'custom' === ( $w['settings']['caption_source'] ?? '' ); } ), 'U2: la foto del producto sin pie de muestra' );
	// U3. Un borrador sin la marca del kit (creado antes de que existiera, o por el Asistente IA) se puede regenerar si
	//     el filtro lo acepta; queda marcado; uno publicado nunca, aunque el filtro diga que sí.
	sp_reset();
	$viejo = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Oferta de fin de mes' ) );
	check_error( Executor::run( 'create-page', array( 'post_id' => $viejo, 'template' => 'venta-corta', 'title' => 'No' ) ), 'digitalisimo_sales_post_id', 'U3: sin la marca y sin el filtro, no se toca' );
	$GLOBALS['sp']['filters']['digitalisimo_sales_regenerable'] = static function ( $ok, $id ) use ( $viejo ) {
		return $ok || $viejo === (int) $id;
	};
	$r = Executor::run( 'create-page', $promo + array( 'post_id' => $viejo ) );
	check( is_array( $r ) && true === $r['regenerated'] && $viejo === $r['id'] && is_array( $GLOBALS['sp']['postmeta'][ $viejo ]['_digitalisimo_sales_kit'] ?? null ), 'U3: con el filtro, el borrador viejo se rehace (mismo ID) y queda con la marca del kit' );
	$GLOBALS['sp']['posts'][ $viejo ]['post_status'] = 'publish';
	unset( $GLOBALS['sp']['postmeta'][ $viejo ]['_digitalisimo_sales_kit'] );
	check_error( Executor::run( 'create-page', array( 'post_id' => $viejo, 'template' => 'venta-corta', 'title' => 'No' ) ), 'digitalisimo_sales_post_id', 'U3: una página publicada nunca, aunque el filtro diga que sí' );

	// ================================================================ 6. Guía y descripciones
	$guide = Catalog::guide();
	check( false !== strpos( $guide, 'product_id' ) && false !== strpos( $guide, 'datos que dio el usuario o que están en el sitio' ) && ! preg_match( '/Sólo datos que dio el usuario/u', $guide ), 'la guía explica product_id y deja usar los datos del sitio' );
	check( (bool) preg_match( '/nunca .*testimonios.*urgencia/isu', Catalog::guide( 'reglas' ) ), 'la guía prohíbe inventar testimonios, reseñas, cifras o urgencia' );
	$tools = \Digitalisimo\Elements\Sales\Mcp\Tools::DESCRIPTIONS['create-page'];
	check( false !== strpos( $tools, 'product_id' ) && false !== strpos( $tools, 'datos que te dio el usuario o que están en el sitio' ) && false !== strpos( $tools, 'nunca inventes testimonios, reseñas' ), 'create-page: product_id y datos del sitio, sin inventar' );

	echo 'Landing de producto del kit de ventas: ' . $GLOBALS['sp_count'] . ' comprobaciones en verde (PHP ' . PHP_VERSION . ").\n";
}
