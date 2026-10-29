<?php
/**
 * Operaciones de sonda de la lista de deseos (woo-flow.sh W1, W2 y W12): `?digi_wf=<op>&token=…&…`, en el sitio de la
 * petición y como el usuario 1. Sólo Playground.
 *
 * - `wishlist_session` (`user`, `nonces`): una sesión real del usuario (token de sesión y cookies `logged_in` y `auth`
 *   firmadas) y sus nonces (`wp_rest` y los que se pidan), creados con esa sesión: el navegador y las peticiones de
 *   Node entran como ese usuario sin pasar por el formulario de acceso.
 * - `wishlist_state` (`user`, `blogs`, `rest`): la lista propia del usuario en cada sitio pedido (la meta con el prefijo
 *   del sitio), la de WooCommerce, el contador de listas propias, la función `product_wishlist`, el modo de la fuente (en
 *   el front y, con `rest=1`, con el servidor REST construido), si el exportador propio sigue haciendo falta y la
 *   pertenencia al sitio.
 * - `wishlist_feature` (`on`): enciende o apaga la lista de WooCommerce en este sitio, como la pantalla de funciones.
 * - `wishlist_seed` (`user`, `ids`): guarda una lista propia en este sitio con el almacén del módulo (W2 en red).
 * - `wishlist_privacy` (`email`, `type`, `run`, `request`): los exportadores o borradores que ve la administración; con
 *   `run=1`, lo que devuelve cada exportador para ese correo; con `request=1`, una solicitud confirmada (cierra las que
 *   queden abiertas para ese correo y esa acción) y la sesión del administrador con su nonce, para la acción AJAX.
 * - `wishlist_remove_from_blog` (`user`, `blog`): `remove_user_from_blog()` desde este sitio, con otro sitio de destino
 *   (en la red, el sitio en curso no es el que pierde al usuario).
 */

use Digitalisimo\Elements\Woo\Wishlist\Personal_Data;
use Digitalisimo\Elements\Woo\Wishlist\Source;
use Digitalisimo\Elements\Woo\Wishlist\Store;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
$digi_wf_wl_arg = static function ( string $name, string $default = '' ): string {
	return isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : $default;
};
$digi_wf_wl_ids = static function ( string $raw ): array {
	return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
};

/** Una sesión real de `$user` (token y cookies firmadas) y sus nonces (`wp_rest` y `$actions`), creados con esa sesión. */
$digi_wf_wl_session = static function ( int $user, array $actions = array() ): array {
	if ( ! $user || ! get_userdata( $user ) ) {
		return array( 'error' => 'usuario' );
	}
	$expiration = time() + DAY_IN_SECONDS;
	$token      = WP_Session_Tokens::get_instance( $user )->create( $expiration );
	$logged_in  = wp_generate_auth_cookie( $user, $expiration, 'logged_in', $token );
	$auth       = wp_generate_auth_cookie( $user, $expiration, 'auth', $token );
	// Los nonces dependen del token de la sesión: se crean con la cookie que el navegador enviará.
	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in;
	wp_set_current_user( $user );
	$nonces = array( 'wp_rest' => wp_create_nonce( 'wp_rest' ) );
	foreach ( $actions as $action ) {
		$action = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $action );
		if ( '' !== $action ) {
			$nonces[ $action ] = wp_create_nonce( $action );
		}
	}
	return array(
		'user'    => $user,
		'cookies' => array(
			array( 'name' => LOGGED_IN_COOKIE, 'value' => $logged_in ),
			array( 'name' => AUTH_COOKIE, 'value' => $auth ),
		),
		'nonces'  => $nonces,
	);
};

return array(
	'wishlist_session'          => static function () use ( $digi_wf_wl_arg, $digi_wf_wl_session ) {
		return $digi_wf_wl_session( absint( $digi_wf_wl_arg( 'user' ) ), explode( ',', $digi_wf_wl_arg( 'nonces' ) ) );
	},

	'wishlist_state'            => static function () use ( $digi_wf_wl_arg, $digi_wf_wl_ids ) {
		global $wpdb;
		$user  = absint( $digi_wf_wl_arg( 'user' ) );
		$blog  = (int) get_current_blog_id();
		$blogs = is_multisite() ? $digi_wf_wl_ids( $digi_wf_wl_arg( 'blogs', (string) $blog ) ) : array( $blog );
		$state = array( 'blog' => $blog, 'user' => $user, 'own' => array(), 'own_by_blog' => array(), 'woo' => array() );
		if ( ! class_exists( Store::class ) ) {
			$state['error'] = 'el módulo de la lista de deseos no está cargado';
			return $state;
		}
		foreach ( $blogs as $id ) {
			$raw                                = get_user_meta( $user, $wpdb->get_blog_prefix( $id ) . Store::META, true );
			$state['own_by_blog'][ (string) $id ] = array_keys( Store::normalize( $raw ) );
		}
		$state['own']          = array_keys( Store::read( $user ) );
		$state['legacy']       = get_option( Store::LEGACY, null );
		$state['feature']      = get_option( 'woocommerce_product_wishlist_enabled', 'no' );
		$state['mode_front']   = Source::mode();
		$state['member']       = ! is_multisite() || is_user_member_of_blog( $user, $blog );
		$state['zip']          = class_exists( 'ZipArchive' );
		$list_class            = 'Automattic\\WooCommerce\\Internal\\ShopperLists\\ShopperList';
		if ( $user && class_exists( $list_class ) ) {
			$list = $list_class::get_by_slug_raw( 'wishlist', $user );
			if ( $list ) {
				foreach ( $list->get_items() as $item ) {
					$state['woo'][] = (int) $item->get_product_id();
				}
			}
		}
		$state['own_exporter'] = Personal_Data::needed();
		// Con `rest=1`, el modo también con el servidor REST construido (comprueba que la ruta de la Store API exista).
		// Construirlo registra todas las rutas del sitio: sólo cuando se pide.
		if ( '1' === $digi_wf_wl_arg( 'rest' ) ) {
			rest_get_server();
			Source::reset();
			$state['mode_rest'] = Source::mode();
		}
		return $state;
	},

	'wishlist_feature'          => static function () use ( $digi_wf_wl_arg ) {
		$on = '1' === $digi_wf_wl_arg( 'on' );
		update_option( 'woocommerce_product_wishlist_enabled', $on ? 'yes' : 'no' );
		return array( 'blog' => (int) get_current_blog_id(), 'feature' => get_option( 'woocommerce_product_wishlist_enabled' ) );
	},

	'wishlist_seed'             => static function () use ( $digi_wf_wl_arg, $digi_wf_wl_ids ) {
		$user  = absint( $digi_wf_wl_arg( 'user' ) );
		$items = array();
		foreach ( $digi_wf_wl_ids( $digi_wf_wl_arg( 'ids' ) ) as $index => $id ) {
			$items[ $id ] = time() - 60 + $index;
		}
		$saved = Store::write( $user, $items );
		return array( 'blog' => (int) get_current_blog_id(), 'user' => $user, 'own' => array_keys( $saved ), 'legacy' => get_option( Store::LEGACY, null ) );
	},

	'wishlist_privacy'          => static function () use ( $digi_wf_wl_arg, $digi_wf_wl_session ) {
		// Los exportadores y borradores que ve la administración: los de WordPress y WooCommerce ya están registrados (filtros
		// y `init`); el propio se registra en `admin_init`, que esta petición del front no dispara: se llama a ese mismo
		// gancho, y sólo a él, si está enganchado. Con `run=1` se llama a cada uno con el correo, como la acción AJAX.
		$email = sanitize_email( $digi_wf_wl_arg( 'email' ) );
		$erase = 'erase' === $digi_wf_wl_arg( 'type' );
		$hook  = array( Personal_Data::class, 'register' );
		if ( false !== has_action( 'admin_init', $hook ) ) {
			call_user_func( $hook );
		}
		$list = $erase ? apply_filters( 'wp_privacy_personal_data_erasers', array() ) : apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$out  = array( 'keys' => array_keys( (array) $list ), 'own_hooked' => false !== has_action( 'admin_init', $hook ), 'results' => array() );
		// Con `request=1`, una solicitud confirmada (cierra las abiertas para ese correo y esa acción) y la sesión del
		// administrador con el nonce de esa solicitud, para la acción AJAX de la pantalla.
		if ( '1' === $digi_wf_wl_arg( 'request' ) ) {
			$type = $erase ? 'remove_personal_data' : 'export_personal_data';
			$open = get_posts( array( 'post_type' => 'user_request', 'post_name__in' => array( $type ), 'title' => $email, 'post_status' => array( 'request-pending', 'request-confirmed' ), 'numberposts' => -1, 'fields' => 'ids' ) );
			foreach ( $open as $id ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'request-completed' ) );
			}
			$request = wp_create_user_request( $email, $type, array(), 'confirmed' );
			if ( is_wp_error( $request ) ) {
				return array( 'error' => $request->get_error_message() );
			}
			$action         = ( $erase ? 'wp-privacy-erase-personal-data-' : 'wp-privacy-export-personal-data-' ) . (int) $request;
			$out['request'] = (int) $request;
			$out['admin']   = $digi_wf_wl_session( 1, array( $action ) );
			$out['nonce']   = $out['admin']['nonces'][ $action ] ?? '';
		}
		if ( '1' !== $digi_wf_wl_arg( 'run' ) ) {
			return $out;
		}
		foreach ( (array) $list as $key => $item ) {
			$response = call_user_func( $item['callback'], $email, 1 );
			if ( $erase ) {
				$out['results'][ $key ] = $response;
				continue;
			}
			$groups = array();
			$names  = array();
			foreach ( (array) ( $response['data'] ?? array() ) as $row ) {
				$group            = (string) ( $row['group_id'] ?? '' );
				$groups[ $group ] = ( $groups[ $group ] ?? 0 ) + 1;
				if ( 'digitalisimo-elements-wishlist' === $group ) {
					$names[] = implode( ' · ', wp_list_pluck( (array) ( $row['data'] ?? array() ), 'value' ) );
				}
			}
			$out['results'][ $key ] = array( 'groups' => $groups, 'names' => $names, 'done' => $response['done'] ?? null );
		}
		return $out;
	},

	'wishlist_remove_from_blog' => static function () use ( $digi_wf_wl_arg ) {
		$user = absint( $digi_wf_wl_arg( 'user' ) );
		$blog = absint( $digi_wf_wl_arg( 'blog' ) );
		if ( ! is_multisite() || ! $user || ! $blog ) {
			return array( 'error' => 'sólo en una red, con usuario y sitio' );
		}
		$result = remove_user_from_blog( $user, $blog );
		return array( 'current' => (int) get_current_blog_id(), 'blog' => $blog, 'removed' => true === $result, 'member' => is_user_member_of_blog( $user, $blog ) );
	},
);
// phpcs:enable WordPress.Security.NonceVerification.Recommended
