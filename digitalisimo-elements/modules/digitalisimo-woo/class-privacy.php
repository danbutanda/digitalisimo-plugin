<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Privacidad de F4-Woo (D22, V.4.5 y V.4.6).
 *
 * - **Un solo texto de política para Elements:** `Conditions\Page_Visits::privacy_text()` pasa su lista por el filtro
 *   `digitalisimo_elements_privacy_items`; `add_item()` añade elementos a esa lista. Con un widget de lista de deseos
 *   en el paquete, se añade el de `digi_wishlist` (V.4.5).
 * - **WP Consent API** (V.4.6, firma verificada en 2.1.0, `docs/fusion/f4woo.md` §1): la lista de invitado se declara
 *   como almacenamiento local (`$type = 'LOCALSTORAGE'`, noveno argumento de `wp_add_cookie_info()`) en la categoría
 *   `functional`. Si la versión instalada no tiene ese argumento, no se declara por la API: el texto de la política
 *   ya lo describe.
 * - **API para la lista de deseos (frente B):** `exporter()` y `eraser()` registran un exportador y un borrador de datos
 *   personales; `eraser_result()` da la respuesta con sus cuatro campos (W-10); `forget_on_site()` borra una opción de
 *   usuario del sitio `$blog_id` con su prefijo (R-I12: nunca `delete_user_option()`, que usaría el sitio en curso).
 */
final class Privacy {
	const FILTER = 'digitalisimo_elements_privacy_items';

	/** Clave del almacenamiento del navegador de la lista de invitado (D4). En una red, seguida de `_<sitio>`. */
	const STORAGE_KEY = 'digi_wishlist';

	/** Widgets que escriben esa lista. */
	const WISHLIST_WIDGETS = array( 'digitalisimo-woo-wishlist-button', 'digitalisimo-woo-wishlist' );

	/** @var bool */
	private static $initialized = false;

	/** @var string[] Elementos añadidos a la lista del texto de política. */
	private static $items = array();

	/** Con WooCommerce: el elemento de la lista de deseos y su declaración en la WP Consent API. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( self::FILTER, array( __CLASS__, 'items' ) );
		if ( self::wishlist_available() ) {
			self::add_item( self::wishlist_item() );
			add_action( 'init', array( __CLASS__, 'register_cookie_info' ) );
		}
	}

	/** ¿Trae el paquete algún widget de lista de deseos? */
	public static function wishlist_available(): bool {
		foreach ( self::WISHLIST_WIDGETS as $name ) {
			if ( Registry::available( $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Añade un elemento (HTML con `<strong>` para el nombre) a la lista del texto de política de Elements.
	 *
	 * @param string $html Texto del elemento.
	 */
	public static function add_item( string $html ): void {
		$html = trim( $html );
		if ( '' !== $html && ! in_array( $html, self::$items, true ) ) {
			self::$items[] = $html;
		}
	}

	/**
	 * Filtro `digitalisimo_elements_privacy_items`.
	 *
	 * @param mixed $items Lista recibida.
	 * @return string[]
	 */
	public static function items( $items ): array {
		$items = is_array( $items ) ? array_values( $items ) : array();
		foreach ( self::$items as $item ) {
			if ( ! in_array( $item, $items, true ) ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/** Clave del almacenamiento del navegador en este sitio (`digi_wishlist`, o `digi_wishlist_<sitio>` en una red). */
	public static function storage_key(): string {
		$network = function_exists( 'is_multisite' ) && is_multisite();
		return $network && function_exists( 'get_current_blog_id' ) ? self::STORAGE_KEY . '_' . (int) get_current_blog_id() : self::STORAGE_KEY;
	}

	/** Elemento de la lista de deseos (V.4.5). */
	public static function wishlist_item(): string {
		return '<strong>digi_wishlist</strong> (en una red, <strong>digi_wishlist_</strong> con el número del sitio), en el almacenamiento del navegador: los productos que el visitante pidió guardar, sólo después de pulsar el botón y como mucho 365 días. Para mostrar la lista, el navegador envía los números de producto al sitio, que no los guarda. Si el visitante inicia sesión y acepta añadirla a su cuenta, la lista se guarda en su cuenta de este sitio hasta que la vacíe, deje de ser usuario del sitio o borre la cuenta, y se puede exportar o borrar desde las herramientas de privacidad. Con la lista de deseos de WooCommerce activa, la cuenta usa la de WooCommerce.';
	}

	/** ¿Admite la WP Consent API instalada el tipo de almacenamiento (`$type`, noveno argumento)? */
	public static function consent_supports_type(): bool {
		if ( ! function_exists( 'wp_add_cookie_info' ) ) {
			return false;
		}
		try {
			$params = ( new \ReflectionFunction( 'wp_add_cookie_info' ) )->getParameters();
		} catch ( \Throwable $error ) {
			return false;
		}
		return isset( $params[8] ) && 'type' === $params[8]->getName();
	}

	/** `init`, con la WP Consent API: la lista de invitado, como almacenamiento local en `functional`. */
	public static function register_cookie_info(): void {
		if ( ! self::consent_supports_type() ) {
			return;
		}
		wp_add_cookie_info(
			self::storage_key(),
			'DIGITALÍSIMO Elements',
			'functional',
			'Hasta 365 días',
			'Guardar en este navegador los productos que el visitante pidió añadir a su lista de deseos.',
			'Números de productos',
			false,
			false,
			'LOCALSTORAGE'
		);
	}

	/**
	 * Registra un exportador de datos personales.
	 *
	 * @param string   $id       Identificador (`digitalisimo-elements-wishlist`).
	 * @param string   $label    Nombre visible («Lista de deseos»).
	 * @param callable $callback `( string $email, int $page ): array{ data: array, done: bool }`.
	 */
	public static function exporter( string $id, string $label, callable $callback ): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( $exporters ) use ( $id, $label, $callback ) {
				$exporters        = is_array( $exporters ) ? $exporters : array();
				$exporters[ $id ] = array(
					'exporter_friendly_name' => $label,
					'callback'               => $callback,
				);
				return $exporters;
			}
		);
	}

	/**
	 * Registra un borrador de datos personales.
	 *
	 * @param string   $id       Identificador.
	 * @param string   $label    Nombre visible.
	 * @param callable $callback `( string $email, int $page ): array` con los campos de `eraser_result()`.
	 */
	public static function eraser( string $id, string $label, callable $callback ): void {
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( $erasers ) use ( $id, $label, $callback ) {
				$erasers        = is_array( $erasers ) ? $erasers : array();
				$erasers[ $id ] = array(
					'eraser_friendly_name' => $label,
					'callback'             => $callback,
				);
				return $erasers;
			}
		);
	}

	/**
	 * Respuesta de un borrador (W-10): `items_removed`, `items_retained` (siempre `false`: no se retiene nada),
	 * `messages` y `done`.
	 *
	 * @param string[] $messages Avisos para la pantalla de privacidad.
	 */
	public static function eraser_result( bool $removed, array $messages = array(), bool $done = true ): array {
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array_values( array_map( 'strval', $messages ) ),
			'done'           => $done,
		);
	}

	/**
	 * Borra la opción de usuario `$key` del sitio `$blog_id` (con su prefijo de tablas), también desde la administración
	 * de la red, donde el sitio en curso es el principal (R-I12).
	 *
	 * @param string $key Opción de usuario sin prefijo (`digitalisimo_wishlist`).
	 */
	public static function forget_on_site( int $user_id, int $blog_id, string $key ): bool {
		global $wpdb;
		if ( $user_id <= 0 || '' === $key || ! isset( $wpdb ) || ! is_object( $wpdb ) || ! function_exists( 'delete_user_meta' ) ) {
			return false;
		}
		$prefix = $blog_id > 0 && method_exists( $wpdb, 'get_blog_prefix' ) ? $wpdb->get_blog_prefix( $blog_id ) : $wpdb->prefix;
		return (bool) delete_user_meta( $user_id, $prefix . $key );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$items       = array();
		self::$initialized = false;
	}
}
