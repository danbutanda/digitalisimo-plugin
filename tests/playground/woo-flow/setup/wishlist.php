<?php
/**
 * Preparación de la lista de deseos para woo-flow.sh (W1, W2 y W12), en la petición `?digi_wf=setup` de cada sitio, con
 * WooCommerce cargado y el usuario 1. Idempotente. Devuelve en `$out['wishlist']`:
 *
 * - `pages`/`urls`: «Guardar» (`digi-wf-wl-guardar`: un botón por producto, el variable incluido, y el enlace con
 *   contador), «Lista» (`digi-wf-wl-lista`: el widget «Lista de deseos» con «Vaciar lista» y el contador) y «Vista»
 *   (`digi-wf-wl-vista`: una vista rápida y una lista, para los permisos de W12);
 * - `widgets`: los IDs de esos elementos; `products`: los de la tienda y un producto privado (`digi-wf-wl-privado`);
 * - `users`: clientes de prueba (`digiwf-b` … `digiwf-w12`), creados en el sitio principal. En una red, `digiwf-d` (W2:
 *   lista de WooCommerce en el sitio 3), `digiwf-f` y `digiwf-g` (W2: quitar del sitio 3 desde la red) son además
 *   miembros del sitio 3, y nadie lo es del sitio 2 (no miembro);
 * - `rest`: la base de las rutas propias (`digitalisimo-elements/v1/woo/`) y `ajax`/`network` para la administración.
 */

defined( 'ABSPATH' ) || exit;

return static function ( array $out, callable $fail ): array {
	$blog     = (int) get_current_blog_id();
	$products = isset( $out['products'] ) && is_array( $out['products'] ) ? $out['products'] : array();
	$wanted   = array( 'digi-wf-camiseta', 'digi-wf-gorra', 'digi-wf-calcetin', 'digi-wf-taza', 'digi-wf-lampara', 'digi-wf-cojin', 'digi-wf-sudadera' );
	foreach ( $wanted as $slug ) {
		if ( empty( $products[ $slug ] ) ) {
			$fail( "wishlist: falta el producto $slug de la tienda" );
			return $out;
		}
	}

	// Producto privado (W12: la vista rápida responde 404 y las fichas lo omiten). Privado no cuenta en W0.4.
	$private = get_posts( array( 'post_type' => 'product', 'name' => 'digi-wf-wl-privado', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $private ) {
		$private_id = (int) $private[0];
	} else {
		$product = new WC_Product_Simple();
		$product->set_name( 'Producto privado de prueba' );
		$product->set_slug( 'digi-wf-wl-privado' );
		$product->set_status( 'private' );
		$product->set_regular_price( '20' );
		$private_id = (int) $product->save();
	}
	if ( ! $private_id || 'private' !== get_post_status( $private_id ) ) {
		$fail( 'wishlist: el producto privado no quedó privado' );
	}

	$widget = static function ( string $id, string $type, array $settings ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	};
	$box    = static function ( string $id, array $children ): array {
		return array( 'id' => $id, 'elType' => 'container', 'isInner' => false, 'settings' => array( 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => array( 'size' => 16, 'unit' => 'px', 'column' => '16', 'row' => '16' ) ), 'elements' => $children );
	};
	$page   = static function ( string $slug, string $title, array $tree ) use ( $fail ): int {
		$found = get_page_by_path( $slug );
		$id    = $found ? (int) $found->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '' ) );
		if ( ! $id ) {
			$fail( "wishlist: no se pudo crear la página $slug" );
			return 0;
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		return $id;
	};

	// «Lista»: el widget de la lista (con «Vaciar lista») y el contador.
	$list_id = $page( 'digi-wf-wl-lista', 'Lista de deseos de prueba', array() );
	$counter = $widget( 'wlcount', 'digitalisimo-woo-wishlist-button', array( 'mode' => 'link', 'wishlist_page' => (string) $list_id, 'link_text' => 'Mi lista' ) );
	$page(
		'digi-wf-wl-lista',
		'Lista de deseos de prueba',
		array(
			$box( 'wlbox01', array( $counter ) ),
			$box( 'wlbox02', array( $widget( 'wllist1', 'digitalisimo-woo-wishlist', array( 'title' => 'Mi lista de deseos', 'clear' => 'yes', 'empty_text' => 'Tu lista de deseos está vacía.' ) ) ) ),
		)
	);

	// «Guardar»: un botón por producto (el variable incluido) y el contador.
	$buttons = array();
	$ids     = array();
	foreach ( array( 'camiseta', 'gorra', 'calcetin', 'taza', 'lampara', 'cojin', 'sudadera' ) as $index => $key ) {
		$id            = 'wlb' . str_pad( (string) $index, 4, '0', STR_PAD_LEFT );
		$ids[ $key ]   = $id;
		$buttons[]     = $widget( $id, 'digitalisimo-woo-wishlist-button', array( 'mode' => 'save', 'product_source' => 'custom', 'product_id' => (string) $products[ 'digi-wf-' . $key ], 'text' => 'Guardar' ) );
	}
	$save_id = $page( 'digi-wf-wl-guardar', 'Guardar en la lista de prueba', array( $box( 'wlbox03', array( $counter ) ), $box( 'wlbox04', $buttons ) ) );

	// «Vista»: una vista rápida (Camiseta) y una lista: el ID de la lista es un `widget_id` ajeno para la vista rápida.
	$view_id = $page(
		'digi-wf-wl-vista',
		'Vista rápida de prueba de la lista',
		array( $box( 'wlbox05', array( $widget( 'wlqv001', 'digitalisimo-woo-quick-view', array( 'product_source' => 'custom', 'product_id' => (string) $products['digi-wf-camiseta'] ) ), $widget( 'wlqvls1', 'digitalisimo-woo-wishlist', array( 'title' => 'Lista junto a la vista rápida' ) ) ) ) )
	);

	// Clientes de prueba: se crean en el sitio principal (en una red son de toda la red). En el sitio 3 de una red,
	// `digiwf-d`, `digiwf-f` y `digiwf-g` también son miembros; del sitio 2 no lo es ninguno.
	$users  = array();
	$logins = array( 'b', 'c', 'd', 'e', 'f', 'g', 'w12' );
	foreach ( $logins as $key ) {
		$login = 'digiwf-' . $key;
		$user  = get_user_by( 'login', $login );
		if ( ! $user && ( ! is_multisite() || is_main_site() ) ) {
			$id = wp_insert_user( array( 'user_login' => $login, 'user_email' => $login . '@example.com', 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer', 'display_name' => 'Cliente ' . strtoupper( $key ) ) );
			if ( is_wp_error( $id ) ) {
				$fail( "wishlist: usuario $login: " . $id->get_error_message() );
				continue;
			}
			$user = get_user_by( 'id', $id );
		}
		if ( ! $user ) {
			continue;
		}
		if ( is_multisite() && 3 === $blog && in_array( $key, array( 'd', 'f', 'g' ), true ) && ! is_user_member_of_blog( $user->ID, $blog ) ) {
			add_user_to_blog( $blog, $user->ID, 'customer' );
		}
		$users[ $key ] = array( 'id' => (int) $user->ID, 'email' => $user->user_email, 'member' => ! is_multisite() || is_user_member_of_blog( $user->ID, $blog ) );
	}

	$key_products = array( 'private' => $private_id );
	foreach ( $wanted as $slug ) {
		$key_products[ substr( $slug, strlen( 'digi-wf-' ) ) ] = (int) $products[ $slug ];
	}
	$out['wishlist'] = array(
		'blog'     => $blog,
		'pages'    => array( 'list' => $list_id, 'save' => $save_id, 'view' => $view_id ),
		'urls'     => array( 'list' => get_permalink( $list_id ), 'save' => get_permalink( $save_id ), 'view' => get_permalink( $view_id ), 'private' => get_permalink( $private_id ) ),
		'widgets'  => array( 'list' => 'wllist1', 'counter' => 'wlcount', 'buttons' => $ids, 'quick_view' => 'wlqv001', 'view_list' => 'wlqvls1' ),
		'products' => $key_products,
		'links'    => array( 'camiseta' => get_permalink( $products['digi-wf-camiseta'] ) ),
		'users'    => $users,
		'key'      => is_multisite() ? 'digi_wishlist_' . $blog : 'digi_wishlist',
		'rest'     => rest_url( 'digitalisimo-elements/v1/woo/' ),
		'index'    => rest_url( 'digitalisimo-elements/v1' ),
		'store'    => rest_url( 'wc/store/v1/shopper-lists/wishlist/items' ),
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'network'  => is_multisite() ? network_admin_url( 'site-users.php' ) : '',
	);
	return $out;
};
