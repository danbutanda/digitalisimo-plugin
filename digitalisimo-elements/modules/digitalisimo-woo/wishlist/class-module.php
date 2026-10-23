<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

use Digitalisimo\Elements\Woo\Assets;
use Digitalisimo\Elements\Woo\Module as Woo;
use Digitalisimo\Elements\Woo\Privacy;
use Digitalisimo\Elements\Woo\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Lista de deseos de F4-Woo (frente B): botón, contador y lista; invitados en el navegador y usuarios con sesión en una
 * sola fuente (V.4.1). La arranca `Woo\Module` con WooCommerce; la hoja y el script los registra `Woo\Assets`
 * (capacidad `wishlist`) y los widgets, `Woo\Registry`.
 *
 * La configuración del navegador va en línea antes del script propio, decidida en el pie según el script encolado (D21,
 * V.12.3): la parte pública es igual para todos los visitantes del sitio (clave, rutas, modo); la de un usuario con
 * sesión y miembro del sitio añade su lista propia y su nonce, que una caché de página no sirve.
 */
final class Module {
	const FEATURE = 'wishlist';

	/** Lo que usa de WooCommerce, comprobado al arrancar (D3). */
	const SYMBOLS = array( 'wc_get_product()', 'wc_get_stock_html()', 'woocommerce_template_loop_add_to_cart()' );

	/** Textos del navegador (todos los visitantes). */
	const TEXTS = array(
		'saved'   => 'Guardado en tu lista de deseos',
		'removed' => 'Quitado de tu lista de deseos',
		'full'    => 'Tu lista de deseos está llena.',
		'cleared' => 'Lista vaciada',
		'error'   => 'No se pudo actualizar tu lista de deseos.',
	);

	/** Textos del aviso de fusión, sólo para un usuario con sesión (V.4.2). */
	const ACCOUNT_TEXTS = array(
		'ask1'      => 'Tienes 1 producto guardado en este navegador. ¿Añadirlo a tu lista?',
		'ask'       => 'Tienes %d productos guardados en este navegador. ¿Añadirlos a tu lista?',
		'add'       => 'Añadir',
		'discard'   => 'Descartar',
		'added'     => 'Añadidos a tu lista de deseos',
		'discarded' => 'Se borró la lista de este navegador.',
		'failed'    => '%d no se pudieron añadir: si tienen opciones, guárdalos desde su ficha.',
	);

	private static $booted = false;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		foreach ( array( 'source', 'store', 'button', 'cards', 'routes', 'personal-data' ) as $file ) {
			require_once __DIR__ . '/class-' . $file . '.php';
		}
		if ( ! Woo::requires( self::FEATURE, self::SYMBOLS ) ) {
			Personal_Data::network_hooks(); // Los datos ya guardados siguen pudiéndose borrar.
			return;
		}
		Personal_Data::init();
		Routes::register();
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'footer_config' ), 9 );
	}

	public static function handle(): string {
		return Assets::handle( self::FEATURE );
	}

	/** Configuración del navegador, sólo si el script está encolado en esta página. */
	public static function footer_config(): void {
		if ( ! wp_script_is( self::handle(), 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script( self::handle(), 'window.digitalisimoWishlist=' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/** @return array<string, mixed> */
	public static function config(): array {
		$store  = Source::is_store();
		$config = array(
			'key'   => Privacy::storage_key(),
			'cards' => Rest::url( 'cards' ),
			'mode'  => $store ? 'store' : 'own',
			'guest' => self::guest_storage(),
			'limit' => Store::limit(),
			'max'   => max( Store::LIMIT, Store::limit() ),
			't'     => self::TEXTS,
		);
		$user = get_current_user_id();
		if ( ! $user || ( is_multisite() && ! is_user_member_of_blog( $user ) ) ) {
			return $config; // Un usuario de la red que no es miembro del sitio usa el modo de invitado.
		}
		$config['t']       += self::ACCOUNT_TEXTS;
		$own                = Store::valid( Store::read( $user ) );
		$config['loggedIn'] = true;
		$config['rest']     = Rest::url( '' );
		$config['ajax']     = admin_url( 'admin-ajax.php' );
		$config['legacy']   = $store && Store::has_data( $user );
		if ( $store ) {
			$config['store'] = Source::store_url();
			// Mudanza (D34): sólo lo que la Store API acepta; un variable necesita su variación y no se puede pasar.
			$own = array_filter(
				$own,
				static function ( $added, $id ) {
					$product = wc_get_product( $id );
					return $product && ! $product->is_type( 'variable' );
				},
				ARRAY_FILTER_USE_BOTH
			);
		}
		$config['items'] = array_map( 'intval', array_keys( $own ) );
		if ( ! $store || $config['legacy'] ) {
			$config['nonce'] = wp_create_nonce( 'wp_rest' );
		}
		return $config;
	}

	/** Modo invitado en todo el sitio; el filtro lo apaga y el botón de un invitado pasa a «Inicia sesión para guardar». */
	public static function guest_storage(): bool {
		return (bool) apply_filters( 'digitalisimo_elements_wishlist_guest_storage', true );
	}

	/**
	 * Destino de «Inicia sesión para guardar»: «Mi cuenta» de WooCommerce, el acceso de los clientes. `wp_login_url()` va
	 * filtrado a la ruta privada de acceso si DIGITALÍSIMO Tools la usa, y no debe salir en el HTML público de cada ficha.
	 */
	public static function login_url(): string {
		$url = '';
		if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 ) {
			$url = (string) wc_get_page_permalink( 'myaccount' );
		}
		if ( '' === $url ) {
			$url = (string) wp_login_url();
		}
		return (string) apply_filters( 'digitalisimo_elements_wishlist_login_url', $url );
	}

	/** Aviso del editor cuando la lista de WooCommerce está activa (V.4.1). */
	public static function store_notice(): string {
		return Source::is_store() ? 'Con la lista de deseos de WooCommerce activa, este widget usa esa lista. WooCommerce guarda la variación elegida: en un producto variable, el botón no se muestra.' : '';
	}
}
