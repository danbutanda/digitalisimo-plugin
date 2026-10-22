<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

use Digitalisimo\Elements\Woo\Products;
use Digitalisimo\Elements\Woo\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Rutas de la lista de deseos (V.4.3), en `digitalisimo-elements/v1/woo/` con la base REST del frente A (que pone las
 * cabeceras sin caché en cada respuesta y carga el carrito donde se pide):
 *
 * - `woo/wishlist` (GET, POST): la lista del almacén propio de un usuario con sesión y miembro del sitio, con
 *   `X-WP-Nonce`. Con la lista de WooCommerce activa responde 409 salvo la mudanza (`op: clear` con `moved`): nunca se
 *   escriben dos almacenes (B1).
 * - `woo/cards` (POST, público): fichas de una instancia del widget «Lista de deseos» para los productos que envía el
 *   navegador en el cuerpo JSON (≤ 8 KB); los IDs nunca van en la URL: un GET responde 405 (B2).
 */
final class Routes {
	const WIDGET = 'digitalisimo-woo-wishlist';
	const OPS    = array( 'add', 'remove', 'clear', 'merge' );

	/** @var array<string, array|null> Elemento hallado por documento y widget, por petición. */
	private static $found = array();

	public static function register(): void {
		Rest::route(
			'wishlist',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_list' ),
					'permission_callback' => array( __CLASS__, 'can_use_account' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'post_list' ),
					'permission_callback' => array( __CLASS__, 'can_use_account' ),
					'args'                => array(
						'op'      => array( 'type' => 'string', 'required' => true, 'enum' => self::OPS ),
						'product' => array( 'type' => 'integer', 'minimum' => 1 ),
						'items'   => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'maxItems' => 500 ),
						'moved'   => array( 'type' => 'boolean', 'default' => false ),
					),
				),
			)
		);
		Rest::route(
			'cards',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'post_cards' ),
					'permission_callback' => array( __CLASS__, 'can_read_cards' ),
					'digitalisimo_cart'   => true, // Precios, impuestos y precios por sesión del visitante (V-I8).
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_cards' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lista con sesión (almacén propio).

	/** Usuario con sesión y, en una red, miembro del sitio: 401 sin sesión, 403 sin pertenencia. */
	public static function can_use_account() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'digitalisimo_wishlist_login', 'Inicia sesión para usar tu lista de deseos.', array( 'status' => rest_authorization_required_code() ) );
		}
		if ( is_multisite() && ! is_user_member_of_blog() ) {
			return new \WP_Error( 'digitalisimo_wishlist_member', 'Tu cuenta no pertenece a este sitio.', array( 'status' => 403 ) );
		}
		return true;
	}

	public static function get_list( $request ) {
		if ( Source::is_store() ) {
			return self::moved();
		}
		return self::payload( Store::valid( Store::read( get_current_user_id() ) ) );
	}

	public static function post_list( $request ) {
		$op   = (string) $request->get_param( 'op' );
		$user = get_current_user_id();
		if ( Source::is_store() ) {
			if ( 'clear' !== $op || true !== $request->get_param( 'moved' ) ) {
				return self::moved();
			}
			Store::clear( $user );
			return array( 'items' => array(), 'limit' => Store::limit(), 'moved' => true );
		}
		$items = Store::valid( Store::read( $user ) );
		$limit = Store::limit();
		$now   = time();
		if ( 'add' === $op || 'remove' === $op ) {
			$product = (int) $request->get_param( 'product' );
			if ( $product <= 0 ) {
				return new \WP_Error( 'digitalisimo_wishlist_invalid', 'Falta el producto.', array( 'status' => 400 ) );
			}
			if ( 'remove' === $op ) {
				unset( $items[ $product ] );
			} elseif ( ! isset( $items[ $product ] ) ) {
				if ( ! Products::is_public( $product ) ) {
					return new \WP_Error( 'digitalisimo_wishlist_invalid', 'Este producto no se puede guardar.', array( 'status' => 400 ) );
				}
				if ( count( $items ) >= $limit ) {
					return new \WP_Error( 'digitalisimo_wishlist_full', 'Tu lista de deseos está llena.', array( 'status' => 409 ) );
				}
				$items[ $product ] = $now;
			}
		} elseif ( 'clear' === $op ) {
			$items = array();
		} else {
			// merge: lo que el visitante aceptó añadir desde este navegador («Añadir» del aviso), sin pasar del tope.
			foreach ( Products::ids( (array) $request->get_param( 'items' ), $limit ) as $id ) {
				if ( ! isset( $items[ $id ] ) && count( $items ) < $limit ) {
					$items[ $id ] = $now;
				}
			}
		}
		return self::payload( Store::write( $user, $items ) );
	}

	/** @param array<int, int> $items */
	private static function payload( array $items ): array {
		$out = array();
		foreach ( $items as $id => $added ) {
			$out[] = array( 'id' => (int) $id, 'added' => (int) $added );
		}
		return array( 'items' => $out, 'limit' => Store::limit() );
	}

	private static function moved(): \WP_Error {
		return new \WP_Error( 'digitalisimo_wishlist_moved', 'Con la lista de deseos de WooCommerce activa, tu lista se guarda en WooCommerce.', array( 'status' => 409 ) );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Fichas (público).

	/** Los IDs nunca van en la URL: un GET responde 405 y anuncia que sólo se admite POST. */
	public static function get_cards() {
		$response = new \WP_REST_Response( array( 'code' => 'digitalisimo_wishlist_method', 'message' => 'Esta ruta sólo admite POST.', 'data' => array( 'status' => 405 ) ), 405 );
		$response->header( 'Allow', 'POST' );
		return $response;
	}

	/**
	 * Cuerpo JSON acotado y una instancia del widget en el documento indicado (publicado, o editable por quien está en el
	 * editor). Un widget global se sigue una vez a su plantilla (`Rest::element()`).
	 */
	public static function can_read_cards( $request ) {
		$input = self::cards_input( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		return null === self::element( $input ) ? new \WP_Error( 'digitalisimo_wishlist_widget', 'No se encontró la lista de deseos.', array( 'status' => 403 ) ) : true;
	}

	public static function post_cards( $request ) {
		$input = self::cards_input( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$element = self::element( $input );
		if ( null === $element ) {
			return new \WP_Error( 'digitalisimo_wishlist_widget', 'No se encontró la lista de deseos.', array( 'status' => 403 ) );
		}
		return Cards::render( (array) $element['settings'], $input['ids'] );
	}

	/** @return array{post_id: int, widget_id: string, ids: int[]}|\WP_Error */
	private static function cards_input( $request ) {
		$data   = Rest::body( $request, Rest::MAX_BODY );
		$post   = is_array( $data ) ? ( $data['post_id'] ?? 0 ) : 0;
		$widget = is_array( $data ) ? ( $data['widget_id'] ?? '' ) : '';
		$ids    = is_array( $data ) ? ( $data['ids'] ?? null ) : null;
		$max    = max( Store::LIMIT, Store::limit() );
		if ( ! is_int( $post ) && ! ( is_string( $post ) && ctype_digit( $post ) ) ) {
			$post = 0;
		}
		if ( (int) $post <= 0 || ! is_string( $widget ) || ! preg_match( Rest::ID_PATTERN, $widget ) || ! is_array( $ids ) || count( $ids ) > $max ) {
			return new \WP_Error( 'digitalisimo_wishlist_body', 'La petición no es válida.', array( 'status' => 400 ) );
		}
		$clean = Products::ids( $ids, $max );
		if ( count( $clean ) !== count( array_unique( array_map( 'strval', $ids ) ) ) ) {
			return new \WP_Error( 'digitalisimo_wishlist_body', 'La petición no es válida.', array( 'status' => 400 ) );
		}
		return array( 'post_id' => (int) $post, 'widget_id' => $widget, 'ids' => $clean );
	}

	private static function element( array $input ): ?array {
		$key = $input['post_id'] . ':' . $input['widget_id'];
		if ( ! array_key_exists( $key, self::$found ) ) {
			self::$found[ $key ] = Rest::element(
				$input['post_id'],
				$input['widget_id'],
				static function ( array $element ): bool {
					return 'widget' === ( $element['elType'] ?? '' ) && self::WIDGET === ( $element['widgetType'] ?? '' );
				}
			);
		}
		return self::$found[ $key ];
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$found = array();
	}
}
