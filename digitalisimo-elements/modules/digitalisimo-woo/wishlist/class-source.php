<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Una sola fuente para la lista de un usuario con sesión (F4-Woo V.4.1, D5): con la función `product_wishlist` de
 * WooCommerce activa, la Store API de WooCommerce (`wc/store/v1/shopper-lists/wishlist/items`); si no, el almacén propio.
 * Nunca se escriben los dos: con la función activa, la ruta propia sólo acepta la mudanza.
 *
 * Se detecta por símbolos públicos (`Utilities\FeaturesUtil` y la clase de la ruta de la Store API), nunca por las
 * clases internas de WooCommerce. La ruta se comprueba en el servidor REST sólo cuando ya existe (`rest_api_init`):
 * construirlo en cada página del front para mirarla costaría registrar todas las rutas del sitio.
 */
final class Source {
	const FEATURE     = 'product_wishlist';
	const SLUG        = 'wishlist';
	const ROUTE       = '/wc/store/v1/shopper-lists/(?P<slug>[a-z0-9-]+)/items';
	const ROUTE_CLASS = 'Automattic\\WooCommerce\\StoreApi\\Routes\\V1\\ShopperListItems';
	const UTIL        = 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

	/** @var array<int, string> Modo calculado, por si `rest_api_init` ya corrió (0 o 1). */
	private static $mode = array();

	/** 'store' (Store API de WooCommerce) o 'own' (almacén propio). */
	public static function mode(): string {
		$rest = (int) ( function_exists( 'did_action' ) && did_action( 'rest_api_init' ) );
		if ( ! isset( self::$mode[ $rest ] ) ) {
			self::$mode[ $rest ] = self::detect( (bool) $rest ) ? 'store' : 'own';
		}
		return self::$mode[ $rest ];
	}

	public static function is_store(): bool {
		return 'store' === self::mode();
	}

	/** URL de los elementos de la lista de WooCommerce (sólo tiene sentido en modo 'store'). */
	public static function store_url(): string {
		return function_exists( 'rest_url' ) ? (string) rest_url( 'wc/store/v1/shopper-lists/' . self::SLUG . '/items' ) : '';
	}

	/** Para las pruebas y tras activar o apagar la función en la misma petición. */
	public static function reset(): void {
		self::$mode = array();
	}

	private static function detect( bool $rest ): bool {
		$util = self::UTIL;
		if ( ! class_exists( $util ) || ! method_exists( $util, 'feature_is_enabled' ) || ! class_exists( self::ROUTE_CLASS ) ) {
			return false;
		}
		try {
			if ( ! $util::feature_is_enabled( self::FEATURE ) ) {
				return false;
			}
		} catch ( \Throwable $e ) {
			return false;
		}
		if ( $rest && function_exists( 'rest_get_server' ) ) {
			$routes = rest_get_server()->get_routes();
			return is_array( $routes ) && isset( $routes[ self::ROUTE ] );
		}
		return true;
	}
}
