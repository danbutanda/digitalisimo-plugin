<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

use Digitalisimo\Elements\Woo\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Datos personales del almacén propio de la lista de deseos (V.4.5), con la API de privacidad del frente A
 * (`Woo\Privacy`, que ya añade el texto de la política y la declaración de la WP Consent API):
 *
 * - exportador y borrador de este sitio, registrados en modo propio y, con la lista de WooCommerce activa, mientras quede
 *   alguna lista propia por mudar (`digitalisimo_elements_wishlist_legacy` > 0): ningún dato queda sin exportar ni
 *   borrar, y con todo mudado sólo responde el exportador de WooCommerce (B1);
 * - la limpieza al quitar a un usuario de un sitio, también desde la administración de la red, con el prefijo de ese
 *   sitio (R-I12).
 */
final class Personal_Data {
	const ID    = 'digitalisimo-elements-wishlist';
	const LABEL = 'Lista de deseos';

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		self::network_hooks();
	}

	/**
	 * La limpieza al quitar a un usuario de un sitio. En una red, el frente A la engancha también sin WooCommerce: la
	 * administración de la red corre en el sitio principal, donde WooCommerce puede no estar activo.
	 */
	public static function network_hooks(): void {
		if ( false === has_action( 'remove_user_from_blog', array( __CLASS__, 'remove_user_from_blog' ) ) ) {
			add_action( 'remove_user_from_blog', array( __CLASS__, 'remove_user_from_blog' ), 10, 2 );
		}
	}

	/** El exportador propio existe en modo propio y, con la lista de WooCommerce, mientras quede alguna lista propia. */
	public static function needed(): bool {
		return ! Source::is_store() || Store::legacy_count() > 0;
	}

	/** `admin_init` (las herramientas de privacidad corren en la administración, con WooCommerce ya iniciado). */
	public static function register(): void {
		if ( ! self::needed() ) {
			return;
		}
		Privacy::exporter( self::ID, self::LABEL, array( __CLASS__, 'export' ) );
		Privacy::eraser( self::ID, self::LABEL, array( __CLASS__, 'erase' ) );
	}

	/** Por producto guardado en este sitio: nombre, dirección y fecha. */
	public static function export( $email, $page = 1 ): array {
		$user = get_user_by( 'email', (string) $email );
		$data = array();
		if ( $user ) {
			foreach ( Store::read( (int) $user->ID ) as $id => $added ) {
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
				$data[]  = array(
					'group_id'    => self::ID,
					'group_label' => self::LABEL,
					'item_id'     => self::ID . '-' . (int) $id,
					'data'        => array(
						array( 'name' => 'Producto', 'value' => $product ? wp_strip_all_tags( (string) $product->get_name() ) : '#' . (int) $id ),
						array( 'name' => 'Dirección', 'value' => $product ? (string) $product->get_permalink() : '' ),
						array( 'name' => 'Guardado', 'value' => $added ? gmdate( 'Y-m-d H:i:s', (int) $added ) . ' UTC' : '' ),
					),
				);
			}
		}
		return array( 'data' => $data, 'done' => true );
	}

	/** Borra la lista propia del usuario en este sitio (W-10: `items_removed`, `items_retained`, `messages`, `done`). */
	public static function erase( $email, $page = 1 ): array {
		$user    = get_user_by( 'email', (string) $email );
		$removed = $user ? Store::clear( (int) $user->ID ) : false;
		return Privacy::eraser_result( $removed, $removed ? array( 'Se borró la lista de deseos de este sitio.' ) : array() );
	}

	/** `remove_user_from_blog( $user_id, $blog_id )`: la lista de ese sitio, nunca la del sitio en curso. */
	public static function remove_user_from_blog( $user_id, $blog_id = 0 ): void {
		$user_id = (int) $user_id;
		$blog_id = (int) $blog_id ? (int) $blog_id : (int) get_current_blog_id();
		if ( $user_id > 0 && Privacy::forget_on_site( $user_id, $blog_id, Store::META ) ) {
			Store::recount( $blog_id );
		}
	}
}
