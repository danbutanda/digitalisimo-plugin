<?php
/**
 * Condiciones de visualización en páginas reales: lo que debe ocultarse no aparece en el HTML, y lo que debe
 * mostrarse sí, con los tres motores (Display Conditions nativo, reglas propias `digitalisimo_visibility_*` y
 * reglas heredadas `ep_display_conditions_*`), en widgets y en contenedores con hijos, como visitante y con
 * sesión, con la caché de elementos de Elementor desactivada y activada. Resultado en result.json.
 */
define( 'WP_USE_THEMES', true );
require '/wordpress/wp-load.php';
ini_set( 'memory_limit', '1024M' );
$result = array();
$widget = static function ( $id, $text, array $settings = array() ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) ( array( 'title' => $text ) + $settings ), 'elements' => array() );
};
$container = static function ( $id, array $children, array $settings = array() ) {
	return array( 'id' => $id, 'elType' => 'container', 'settings' => (object) $settings, 'elements' => $children );
};
$native = static function ( array $conditions ) {
	return array( 'e_display_conditions' => array( wp_json_encode( array( $conditions ) ) ) );
};
$own = static function ( array $rules, $to = 'show', $relation = 'all' ) {
	return array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => $to, 'digitalisimo_visibility_relation' => $relation, 'digitalisimo_visibility_rules' => $rules );
};
$legacy = static function ( array $rules, $to = 'show', $relation = 'all' ) {
	return array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => $to, 'ep_display_conditions_relation' => $relation, 'ep_display_conditions' => $rules );
};
$logged_in_only = array( 'condition' => 'login_status', 'comparator' => 'is', 'status' => 'logged_in' );
$own_logged     = array( '_id' => 'r1', 'rule_key' => 'authentication', 'rule_operator' => 'is', 'rule_authentication_value' => 'authenticated' );
$legacy_logged  = array( '_id' => 'r1', 'ep_condition_key' => 'authentication', 'ep_condition_operator' => 'is', 'ep_condition_authentication_value' => 'authenticated' );
$legacy_role    = array( '_id' => 'r2', 'ep_condition_key' => 'role', 'ep_condition_operator' => 'is', 'ep_condition_role_value' => 'administrator' );

// Cada caso: elementos de la página, textos que un VISITANTE no debe ver y textos que SÍ debe ver; y lo mismo con SESIÓN.
$cases = array(
	'nativo-widget'      => array( array( $container( 'c1', array( $widget( 'w1', 'NATIVO-OCULTO', $native( array( $logged_in_only ) ) ), $widget( 'w2', 'NATIVO-VISIBLE' ) ) ) ), array( 'NATIVO-OCULTO' ), array( 'NATIVO-VISIBLE' ), array(), array( 'NATIVO-OCULTO', 'NATIVO-VISIBLE' ) ),
	'nativo-contenedor'  => array( array( $container( 'c1', array( $widget( 'w1', 'NATIVO-HIJO-OCULTO' ) ), $native( array( $logged_in_only ) ) ), $container( 'c2', array( $widget( 'w2', 'NATIVO-HIJO-VISIBLE' ) ) ) ), array( 'NATIVO-HIJO-OCULTO' ), array( 'NATIVO-HIJO-VISIBLE' ), array(), array( 'NATIVO-HIJO-OCULTO', 'NATIVO-HIJO-VISIBLE' ) ),
	'propio-widget'      => array( array( $container( 'c1', array( $widget( 'w1', 'PROPIO-OCULTO', $own( array( $own_logged ) ) ), $widget( 'w2', 'PROPIO-VISIBLE' ) ) ) ), array( 'PROPIO-OCULTO' ), array( 'PROPIO-VISIBLE' ), array(), array( 'PROPIO-OCULTO', 'PROPIO-VISIBLE' ) ),
	'propio-contenedor'  => array( array( $container( 'c1', array( $widget( 'w1', 'PROPIO-HIJO-OCULTO' ) ), $own( array( $own_logged ) ) ), $container( 'c2', array( $widget( 'w2', 'PROPIO-HIJO-VISIBLE' ) ) ) ), array( 'PROPIO-HIJO-OCULTO' ), array( 'PROPIO-HIJO-VISIBLE' ), array(), array( 'PROPIO-HIJO-OCULTO', 'PROPIO-HIJO-VISIBLE' ) ),
	'propio-hide'        => array( array( $container( 'c1', array( $widget( 'w1', 'PROPIO-SOLO-ANONIMO', $own( array( $own_logged ), 'hide' ) ) ) ) ), array(), array( 'PROPIO-SOLO-ANONIMO' ), array( 'PROPIO-SOLO-ANONIMO' ), array() ),
	'heredado-widget'    => array( array( $container( 'c1', array( $widget( 'w1', 'HEREDADO-OCULTO', $legacy( array( $legacy_logged ) ) ), $widget( 'w2', 'HEREDADO-VISIBLE' ) ) ) ), array( 'HEREDADO-OCULTO' ), array( 'HEREDADO-VISIBLE' ), array(), array( 'HEREDADO-OCULTO', 'HEREDADO-VISIBLE' ) ),
	'heredado-contenedor' => array( array( $container( 'c1', array( $widget( 'w1', 'HEREDADO-HIJO-OCULTO' ) ), $legacy( array( $legacy_logged ) ) ) ), array( 'HEREDADO-HIJO-OCULTO' ), array(), array(), array( 'HEREDADO-HIJO-OCULTO' ) ),
	// «Todas»: con sesión de administrador se cumplen las dos; un suscriptor sólo la primera.
	'heredado-todas'     => array( array( $container( 'c1', array( $widget( 'w1', 'HEREDADO-ADMIN', $legacy( array( $legacy_logged, $legacy_role ) ) ) ) ) ), array( 'HEREDADO-ADMIN' ), array(), array(), array( 'HEREDADO-ADMIN' ) ),
	// Un widget nativo oculto dentro de un contenedor con enlace del elemento (otro rasgo que usa before/after_render).
	'nativo-en-enlace'   => array( array( $container( 'c1', array( $widget( 'w1', 'ENLACE-OCULTO', $native( array( $logged_in_only ) ) ), $widget( 'w2', 'ENLACE-VISIBLE' ) ), array( 'digitalisimo_element_link' => array( 'url' => 'https://example.com/' ) ) ) ), array( 'ENLACE-OCULTO' ), array( 'ENLACE-VISIBLE' ), array(), array( 'ENLACE-OCULTO', 'ENLACE-VISIBLE' ) ),
	// El elemento oculto lleva a la vez otro rasgo que envuelve su salida (enlace, figuras, figuras heredadas, efectos).
	'oculto-con-enlace'  => array( array( $container( 'c1', array( $widget( 'w1', 'OCULTO-ENLACE', $native( array( $logged_in_only ) ) + array( 'digitalisimo_element_link' => array( 'url' => 'https://example.com/' ) ) ) ) ) ), array( 'OCULTO-ENLACE', 'digi-element-link' ), array(), array(), array( 'OCULTO-ENLACE' ) ),
	'oculto-con-figuras' => array( array( $container( 'c1', array( $widget( 'w1', 'OCULTO-FIGURA-HIJO' ) ), $native( array( $logged_in_only ) ) + array( 'digitalisimo_figures_enable' => 'yes', 'digitalisimo_figures' => array( array( '_id' => 'f1', 'shape_type' => 'circle' ) ) ) ) ), array( 'OCULTO-FIGURA-HIJO', 'digi-figure' ), array(), array(), array( 'OCULTO-FIGURA-HIJO' ) ),
	'oculto-propio-enlace' => array( array( $container( 'c1', array( $widget( 'w1', 'OCULTO-PROPIO-ENLACE', $own( array( $own_logged ) ) + array( 'digitalisimo_element_link' => array( 'url' => 'https://example.com/' ) ) ) ) ) ), array( 'OCULTO-PROPIO-ENLACE' ), array(), array(), array( 'OCULTO-PROPIO-ENLACE' ) ),
	'oculto-heredado-figuras' => array( array( $container( 'c1', array( $widget( 'w1', 'OCULTO-HEREDADO-FIGURA' ) ), $legacy( array( $legacy_logged ) ) + array( 'bdt_shape_builder_enable' => 'yes', 'bdt_shape_builder_list' => array( array( '_id' => 'f1', 'shape_type' => 'circle' ) ) ) ) ), array( 'OCULTO-HEREDADO-FIGURA' ), array(), array(), array( 'OCULTO-HEREDADO-FIGURA' ) ),
	'oculto-heredado-enlace' => array( array( $container( 'c1', array( $widget( 'w1', 'OCULTO-HEREDADO-ENLACE', $legacy( array( $legacy_logged ) ) + array( 'element_pack_wrapper_link' => array( 'url' => 'https://example.com/' ) ) ) ) ) ), array( 'OCULTO-HEREDADO-ENLACE' ), array(), array(), array( 'OCULTO-HEREDADO-ENLACE' ) ),
	// Reglas que dependen de la petición: tipo de contenido, página especial y URL, evaluadas al pintar una página.
	'propio-post-type'   => array( array( $container( 'c1', array( $widget( 'w1', 'SOLO-ENTRADAS', $own( array( array( '_id' => 'r1', 'rule_key' => 'post_type', 'rule_operator' => 'is', 'rule_post_type_value' => array( 'post' ) ) ) ) ), $widget( 'w2', 'SOLO-PAGINAS', $own( array( array( '_id' => 'r1', 'rule_key' => 'post_type', 'rule_operator' => 'is', 'rule_post_type_value' => array( 'page' ) ) ) ) ) ) ) ), array( 'SOLO-ENTRADAS' ), array( 'SOLO-PAGINAS' ), array( 'SOLO-ENTRADAS' ), array( 'SOLO-PAGINAS' ) ),
	'propio-static-page' => array( array( $container( 'c1', array( $widget( 'w1', 'SOLO-PORTADA', $own( array( array( '_id' => 'r1', 'rule_key' => 'static_page', 'rule_operator' => 'is', 'rule_static_page_value' => 'home' ) ) ) ), $widget( 'w2', 'SOLO-404', $own( array( array( '_id' => 'r1', 'rule_key' => 'static_page', 'rule_operator' => 'is', 'rule_static_page_value' => '404' ) ) ) ) ) ) ), array( 'SOLO-PORTADA', 'SOLO-404' ), array(), array( 'SOLO-PORTADA', 'SOLO-404' ), array() ),
	'nativo-post-type'   => array( array( $container( 'c1', array( $widget( 'w1', 'NATIVO-SOLO-ENTRADAS', $native( array( array( 'condition' => 'digitalisimo_post_type', 'comparator' => 'is_one_of', 'types' => array( 'post' ) ) ) ) ), $widget( 'w2', 'NATIVO-SOLO-PAGINAS', $native( array( array( 'condition' => 'digitalisimo_post_type', 'comparator' => 'is_one_of', 'types' => array( 'page' ) ) ) ) ) ) ) ), array( 'NATIVO-SOLO-ENTRADAS' ), array( 'NATIVO-SOLO-PAGINAS' ), array( 'NATIVO-SOLO-ENTRADAS' ), array( 'NATIVO-SOLO-PAGINAS' ) ),
);

// Plantilla con un widget oculto para visitantes, pintada dentro de un acordeón propio y de un Loop nativo.
$template = get_page_by_path( 'digi-cond-template', OBJECT, 'elementor_library' );
if ( ! $template ) {
	$tid = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'digi-cond-template', 'post_name' => 'digi-cond-template' ) );
	update_post_meta( $tid, '_elementor_edit_mode', 'builder' );
	update_post_meta( $tid, '_elementor_template_type', 'section' );
	wp_set_object_terms( $tid, 'section', 'elementor_library_type' );
	update_post_meta( $tid, '_elementor_data', wp_slash( wp_json_encode( array( $container( 'tc1', array( $widget( 'tw1', 'PLANTILLA-OCULTO', $native( array( $logged_in_only ) ) ), $widget( 'tw2', 'PLANTILLA-OCULTO-PROPIO', $own( array( $own_logged ) ) ), $widget( 'tw3', 'PLANTILLA-VISIBLE' ) ) ) ) ) ) );
	$template = get_post( $tid );
}
$cases['plantilla-acordeon'] = array( array( $container( 'c1', array( array( 'id' => 'a1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-accordion', 'settings' => (object) array( 'tabs' => array( array( '_id' => 'i1', 'tab_title' => 'Panel', 'source' => 'elementor', 'template_id' => (string) $template->ID ) ) ), 'elements' => array() ) ) ) ), array( 'PLANTILLA-OCULTO', 'PLANTILLA-OCULTO-PROPIO' ), array( 'PLANTILLA-VISIBLE' ), array(), array( 'PLANTILLA-OCULTO', 'PLANTILLA-OCULTO-PROPIO', 'PLANTILLA-VISIBLE' ) );
$cases['plantilla-shortcode'] = array( array( $container( 'c1', array( array( 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => (object) array( 'shortcode' => '[elementor-template id="' . $template->ID . '"]' ), 'elements' => array() ) ) ) ), array( 'PLANTILLA-OCULTO', 'PLANTILLA-OCULTO-PROPIO' ), array( 'PLANTILLA-VISIBLE' ), array(), array( 'PLANTILLA-OCULTO', 'PLANTILLA-OCULTO-PROPIO', 'PLANTILLA-VISIBLE' ) );

$page_ids = array();
$render = static function ( $id ) {
	$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post']         = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	// Como una visita nueva: Elementor guarda en memoria los documentos ya pintados.
	\Elementor\Plugin::$instance->documents->get( $id, false );
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
	wp_reset_postdata();
	return $html;
};
$check = static function ( $html, array $hidden, array $visible ) {
	$problems = array();
	foreach ( $hidden as $text ) {
		if ( false !== strpos( $html, $text ) ) {
			$problems[] = 'se muestra ' . $text;
		}
	}
	foreach ( $visible as $text ) {
		if ( false === strpos( $html, $text ) ) {
			$problems[] = 'falta ' . $text;
		}
	}
	return $problems;
};

foreach ( array( 'disable', '24' ) as $cache ) {
	update_option( 'elementor_element_cache_ttl', $cache );
	foreach ( $cases as $name => $case ) {
		list( $data, $anon_hidden, $anon_visible, $user_hidden, $user_visible ) = $case;
		$slug = 'digi-cond-' . $name . '-' . $cache;
		$doc_post = get_page_by_path( $slug );
		$id   = $doc_post ? $doc_post->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
		$page_ids[ $slug ] = (int) $id;
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		delete_post_meta( $id, '_elementor_element_cache' );
		$entry = array();
		// Primero visitante (es quien suele llenar la caché), después con sesión, y otra vez visitante: una caché
		// llenada por un administrador no debe mostrar al visitante lo que sólo él podía ver.
		wp_set_current_user( 0 );
		$entry['anonimo']   = $check( $render( $id ), $anon_hidden, $anon_visible );
		wp_set_current_user( 1 );
		$entry['sesion']    = $check( $render( $id ), $user_hidden, $user_visible );
		wp_set_current_user( 0 );
		$entry['anonimo-2'] = $check( $render( $id ), $anon_hidden, $anon_visible );
		// Vista previa del editor (como administrador): todo se ve; después, como visitante, se vuelve a ocultar.
		if ( 'disable' === $cache ) {
			wp_set_current_user( 1 );
			\Elementor\Plugin::$instance->editor->set_edit_mode( true );
			$entry['editor'] = $check( $render( $id ), array(), array_values( array_filter( array_merge( $anon_hidden, $anon_visible, $user_hidden, $user_visible ), static function ( $text ) { return false === strpos( $text, 'digi-' ); } ) ) );
			\Elementor\Plugin::$instance->editor->set_edit_mode( false );
			wp_set_current_user( 0 );
			$entry['anonimo-3'] = $check( $render( $id ), $anon_hidden, $anon_visible );
		}
		$result[ 'cache-' . $cache ][ $name ] = $entry;
		// Con la caché de elementos activa, quien llena la caché primero no debe decidir lo que ven los demás:
		// se vacía y se pinta primero con sesión, después como visitante.
		if ( '24' === $cache ) {
			delete_post_meta( $id, '_elementor_element_cache' );
			wp_set_current_user( 1 );
			$render( $id );
			wp_set_current_user( 0 );
			$result[ 'cache-' . $cache ][ $name ]['anonimo-tras-sesion'] = $check( $render( $id ), $anon_hidden, $anon_visible );
		}
	}
}
// ── F7a (V.13 #9 y #11): las siete condiciones nuevas, el centinela, la lista vacía y la ausencia de WooCommerce ──────
// Cada variante pinta la página con sus cookies y su consulta, como visitante y con sesión, con la caché de elementos
// apagada y a 24 h. Ningún valor recibido o guardado (cookie, meta, término buscado) aparece nunca en el HTML.
$f7a_secrets = array( 'valor-cookie-secreto-k7', 'azul-meta-secreto-k7', 'termino-secreto-k7' );
$f7a_page    = static function ( $slug ) {
	$found = get_page_by_path( $slug );
	return $found ? (int) $found->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug, 'post_author' => 1 ) );
};
$f7a_visited = $f7a_page( 'digi-cond-f7a-visitada' );
update_user_meta( 1, 'first_name', 'Ana' );
$f7a_woo      = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
$f7a_products = array( $f7a_visited, $f7a_visited );
if ( $f7a_woo ) {
	foreach ( array( 'instock', 'outofstock' ) as $i => $status ) {
		$existing = get_page_by_path( 'digi-cond-producto-' . $status, OBJECT, 'product' );
		if ( $existing ) {
			$f7a_products[ $i ] = (int) $existing->ID;
			continue;
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'Producto ' . $status );
		$product->set_slug( 'digi-cond-producto-' . $status );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->set_stock_status( $status );
		$f7a_products[ $i ] = (int) $product->save();
	}
}
$f7a_sentinel = array( 'condition' => 'digitalisimo_special_page', 'comparator' => 'is_one_of', 'pages' => array( 'digitalisimo_f7a' ) );
$f7a_groups   = static function ( array $groups ) {
	return array( 'e_display_conditions' => array( wp_json_encode( $groups ) ) );
};
$f7a_w = static function ( $id, $text, array $groups = array() ) use ( $widget, $f7a_groups ) {
	return $widget( $id, $text, $groups ? $f7a_groups( $groups ) : array() );
};
$cookie   = static function ( $comparator, $extra = array(), $name = 'digi_prueba' ) {
	return array( 'condition' => 'digitalisimo_cookie', 'name' => $name, 'comparator' => $comparator ) + $extra;
};
$visit    = static function ( $comparator, array $pages ) {
	return array( 'condition' => 'digitalisimo_page_visit', 'comparator' => $comparator, 'pages' => $pages );
};
$field    = static function ( $key, $comparator, $value = '', $source = 'post' ) {
	return array( 'condition' => 'digitalisimo_field', 'source' => $source, 'key' => $key, 'comparator' => $comparator, 'value' => $value );
};
$ufield   = static function ( $key, $comparator, $value = '' ) {
	return array( 'condition' => 'digitalisimo_user_field', 'key' => $key, 'comparator' => $comparator, 'value' => $value );
};
$author   = static function ( $comparator, array $roles = array() ) {
	return array( 'condition' => 'digitalisimo_author', 'comparator' => $comparator, 'roles' => $roles );
};
$search   = static function ( $comparator, $value = '' ) {
	return array( 'condition' => 'digitalisimo_search', 'comparator' => $comparator, 'value' => $value );
};
$stock    = static function ( array $products, $comparator, $match = 'any' ) {
	return array( 'condition' => 'digitalisimo_product_stock_of', 'products' => array_map( 'strval', $products ), 'match' => $match, 'comparator' => $comparator );
};
$both     = static function ( array $hidden, array $visible ) {
	return array( 'anonimo' => array( $hidden, $visible ), 'sesion' => array( $hidden, $visible ) );
};
$with_cookie = array( 'digi_prueba' => 'valor-cookie-secreto-k7', 'wordpress_logged_in_k7' => 'x' );
$p0          = $f7a_products[0];
$p1          = $f7a_products[1];
$in_stock    = $f7a_woo ? array( 'EXIST-EN-STOCK', 'EXIST-ALGUNO-AGOTADO' ) : array();
$f7a_cases   = array(
	// Variable (navegador): existe, no existe, es; un nombre vetado nunca se cumple; con el centinela, igual que sin él.
	'f7a-cookie'       => array(
		'elements' => array( $container( 'f1', array(
			$f7a_w( 'a1', 'COOKIE-EXISTE', array( array( $cookie( 'is_not_empty' ) ) ) ),
			$f7a_w( 'a2', 'COOKIE-NO-EXISTE', array( array( $cookie( 'is_empty' ) ) ) ),
			$f7a_w( 'a3', 'COOKIE-ES', array( array( $cookie( 'is', array( 'value' => 'valor-cookie-secreto-k7' ) ) ) ) ),
			$f7a_w( 'a4', 'COOKIE-VETADA', array( array( $cookie( 'is_not_empty', array(), 'wordpress_logged_in_k7' ) ) ) ),
			$f7a_w( 'a5', 'COOKIE-CENTINELA', array( array( $cookie( 'is_not_empty' ), $f7a_sentinel ) ) ),
			$f7a_w( 'a6', 'COOKIE-CONTROL' ),
		) ) ),
		'variants' => array(
			'con-cookie' => array( 'cookies' => $with_cookie ) + $both( array( 'COOKIE-NO-EXISTE', 'COOKIE-VETADA' ), array( 'COOKIE-EXISTE', 'COOKIE-ES', 'COOKIE-CENTINELA', 'COOKIE-CONTROL' ) ),
			'sin-cookie' => array( 'cookies' => array() ) + $both( array( 'COOKIE-EXISTE', 'COOKIE-ES', 'COOKIE-VETADA', 'COOKIE-CENTINELA' ), array( 'COOKIE-NO-EXISTE', 'COOKIE-CONTROL' ) ),
		),
	),
	// Variable (cookie de visitas del sitio): visitó, no visitó; una lista vacía nunca se cumple; una cookie dañada no cuenta.
	'f7a-visita'       => array(
		'elements' => array( $container( 'f2', array(
			$f7a_w( 'b1', 'VISITO-ALGUNA', array( array( $visit( 'is_one_of', array( (string) $f7a_visited ) ) ) ) ),
			$f7a_w( 'b2', 'NO-VISITO-NINGUNA', array( array( $visit( 'is_none_of', array( (string) $f7a_visited ) ) ) ) ),
			$f7a_w( 'b3', 'VISITO-LISTA-VACIA', array( array( $visit( 'is_one_of', array() ) ) ) ),
			$f7a_w( 'b4', 'NO-VISITO-LISTA-VACIA', array( array( $visit( 'is_none_of', array() ) ) ) ),
			$f7a_w( 'b5', 'VISITA-CONTROL' ),
		) ) ),
		'variants' => array(
			'visito'        => array( 'cookies' => array( 'digi_seen' => base_convert( (string) $f7a_visited, 10, 36 ) ) ) + $both( array( 'NO-VISITO-NINGUNA', 'VISITO-LISTA-VACIA', 'NO-VISITO-LISTA-VACIA' ), array( 'VISITO-ALGUNA', 'VISITA-CONTROL' ) ),
			'no-visito'     => array( 'cookies' => array() ) + $both( array( 'VISITO-ALGUNA', 'VISITO-LISTA-VACIA', 'NO-VISITO-LISTA-VACIA' ), array( 'NO-VISITO-NINGUNA', 'VISITA-CONTROL' ) ),
			'cookie-danada' => array( 'cookies' => array( 'digi_seen' => base_convert( (string) $f7a_visited, 10, 36 ) . ';x' ) ) + $both( array( 'VISITO-ALGUNA', 'VISITO-LISTA-VACIA', 'NO-VISITO-LISTA-VACIA' ), array( 'NO-VISITO-NINGUNA', 'VISITA-CONTROL' ) ),
		),
	),
	// Cacheable (de la URL): la meta del contenido; las metas protegidas o que suenan a secreto y un valor vacío nunca.
	'f7a-campo'        => array(
		'meta'     => array( 'digi_tono' => 'azul-meta-secreto-k7', '_digi_privado' => 'x', 'digi_token' => 'x' ),
		'elements' => array( $container( 'f3', array(
			$f7a_w( 'c1', 'CAMPO-ES', array( array( $field( 'digi_tono', 'is', 'azul-meta-secreto-k7' ) ) ) ),
			$f7a_w( 'c2', 'CAMPO-CONTIENE', array( array( $field( 'digi_tono', 'contains', 'azul' ) ) ) ),
			$f7a_w( 'c3', 'CAMPO-NO-ES', array( array( $field( 'digi_tono', 'is_not', 'azul-meta-secreto-k7' ) ) ) ),
			$f7a_w( 'c4', 'CAMPO-PROTEGIDO', array( array( $field( '_digi_privado', 'is_not_empty' ) ) ) ),
			$f7a_w( 'c5', 'CAMPO-VETADO', array( array( $field( 'digi_token', 'is_not_empty' ) ) ) ),
			$f7a_w( 'c6', 'CAMPO-VALOR-VACIO', array( array( $field( 'digi_tono', 'is', '' ) ) ) ),
			$f7a_w( 'c7', 'CAMPO-TERMINO', array( array( $field( 'digi_tono', 'is_not_empty', '', 'term' ) ) ) ),
			$f7a_w( 'c8', 'CAMPO-CONTROL' ),
		) ) ),
		'variants' => array(
			'pagina' => $both( array( 'CAMPO-NO-ES', 'CAMPO-PROTEGIDO', 'CAMPO-VETADO', 'CAMPO-VALOR-VACIO', 'CAMPO-TERMINO' ), array( 'CAMPO-ES', 'CAMPO-CONTIENE', 'CAMPO-CONTROL' ) ),
		),
	),
	// Cacheable (sólo con sesión): campo del perfil de la lista blanca; sin sesión nunca se cumple.
	'f7a-usuario'      => array(
		'elements' => array( $container( 'f4', array(
			$f7a_w( 'd1', 'USUARIO-ES', array( array( $ufield( 'first_name', 'is', 'Ana' ) ) ) ),
			$f7a_w( 'd2', 'USUARIO-NO-ES', array( array( $ufield( 'first_name', 'is_not', 'Ana' ) ) ) ),
			$f7a_w( 'd3', 'USUARIO-VALOR-VACIO', array( array( $ufield( 'first_name', 'is', '' ) ) ) ),
			$f7a_w( 'd4', 'USUARIO-CLAVE-FUERA', array( array( $ufield( 'session_tokens', 'is_not_empty' ) ) ) ),
			$f7a_w( 'd5', 'USUARIO-CONTROL' ),
		) ) ),
		'variants' => array(
			'pagina' => array(
				'anonimo' => array( array( 'USUARIO-ES', 'USUARIO-NO-ES', 'USUARIO-VALOR-VACIO', 'USUARIO-CLAVE-FUERA' ), array( 'USUARIO-CONTROL' ) ),
				'sesion'  => array( array( 'USUARIO-NO-ES', 'USUARIO-VALOR-VACIO', 'USUARIO-CLAVE-FUERA' ), array( 'USUARIO-ES', 'USUARIO-CONTROL' ) ),
			),
		),
	),
	// Cacheable (de la URL): el autor de la página (el administrador) es el usuario actual, o tiene uno de estos roles.
	'f7a-autor'        => array(
		'elements' => array( $container( 'f5', array(
			$f7a_w( 'g1', 'AUTOR-ES-ACTUAL', array( array( $author( 'is_current_user' ) ) ) ),
			$f7a_w( 'g2', 'AUTOR-NO-ES-ACTUAL', array( array( $author( 'is_not_current_user' ) ) ) ),
			$f7a_w( 'g3', 'AUTOR-ROL', array( array( $author( 'role_in', array( 'administrator' ) ) ) ) ),
			$f7a_w( 'g4', 'AUTOR-ROL-VACIO', array( array( $author( 'role_in' ) ) ) ),
			$f7a_w( 'g5', 'AUTOR-SIN-ROL-VACIO', array( array( $author( 'role_not_in' ) ) ) ),
			$f7a_w( 'g6', 'AUTOR-CONTROL' ),
		) ) ),
		'variants' => array(
			'pagina' => array(
				'anonimo' => array( array( 'AUTOR-ES-ACTUAL', 'AUTOR-ROL-VACIO', 'AUTOR-SIN-ROL-VACIO' ), array( 'AUTOR-NO-ES-ACTUAL', 'AUTOR-ROL', 'AUTOR-CONTROL' ) ),
				'sesion'  => array( array( 'AUTOR-NO-ES-ACTUAL', 'AUTOR-ROL-VACIO', 'AUTOR-SIN-ROL-VACIO' ), array( 'AUTOR-ES-ACTUAL', 'AUTOR-ROL', 'AUTOR-CONTROL' ) ),
			),
		),
	),
	// Cacheable (de la URL): sólo en la página de resultados; un término esperado vacío nunca se cumple.
	'f7a-busqueda'     => array(
		'elements' => array( $container( 'f6', array(
			$f7a_w( 'h1', 'BUSQUEDA-CONTIENE', array( array( $search( 'contains', 'secreto' ) ) ) ),
			$f7a_w( 'h2', 'BUSQUEDA-ES', array( array( $search( 'is', 'termino-secreto-k7' ) ) ) ),
			$f7a_w( 'h3', 'BUSQUEDA-NO-ES', array( array( $search( 'is_not', 'termino-secreto-k7' ) ) ) ),
			$f7a_w( 'h4', 'BUSQUEDA-UNO-DE', array( array( $search( 'is_one_of', 'otro, termino-secreto-k7' ) ) ) ),
			$f7a_w( 'h5', 'BUSQUEDA-SIN-RESULTADOS', array( array( $search( 'no_results' ) ) ) ),
			$f7a_w( 'h6', 'BUSQUEDA-CON-RESULTADOS', array( array( $search( 'has_results' ) ) ) ),
			$f7a_w( 'h7', 'BUSQUEDA-VALOR-VACIO', array( array( $search( 'is', '' ) ) ) ),
			$f7a_w( 'h8', 'BUSQUEDA-CONTROL' ),
		) ) ),
		'variants' => array(
			'resultados' => array( 'query' => array( 's' => 'termino-secreto-k7' ) ) + $both( array( 'BUSQUEDA-NO-ES', 'BUSQUEDA-CON-RESULTADOS', 'BUSQUEDA-VALOR-VACIO' ), array( 'BUSQUEDA-CONTIENE', 'BUSQUEDA-ES', 'BUSQUEDA-UNO-DE', 'BUSQUEDA-SIN-RESULTADOS', 'BUSQUEDA-CONTROL' ) ),
			'pagina'     => $both( array( 'BUSQUEDA-CONTIENE', 'BUSQUEDA-ES', 'BUSQUEDA-NO-ES', 'BUSQUEDA-UNO-DE', 'BUSQUEDA-SIN-RESULTADOS', 'BUSQUEDA-CON-RESULTADOS', 'BUSQUEDA-VALOR-VACIO' ), array( 'BUSQUEDA-CONTROL' ) ),
		),
	),
	// Variable: existencias de productos concretos. Sin WooCommerce nunca se cumple; una lista vacía, tampoco.
	'f7a-existencias'  => array(
		'elements' => array( $container( 'f7', array(
			$f7a_w( 'i1', 'EXIST-EN-STOCK', array( array( $stock( array( $p0 ), 'instock' ) ) ) ),
			$f7a_w( 'i2', 'EXIST-AGOTADO', array( array( $stock( array( $p0 ), 'outofstock' ) ) ) ),
			$f7a_w( 'i3', 'EXIST-TODOS-EN-STOCK', array( array( $stock( array( $p0, $p1 ), 'instock', 'all' ) ) ) ),
			$f7a_w( 'i4', 'EXIST-ALGUNO-AGOTADO', array( array( $stock( array( $p0, $p1 ), 'outofstock', 'any' ) ) ) ),
			$f7a_w( 'i5', 'EXIST-LISTA-VACIA', array( array( $stock( array(), 'instock' ) ) ) ),
			$f7a_w( 'i6', 'EXIST-CONTROL' ),
		) ) ),
		'variants' => array(
			'pagina' => $both( array_values( array_diff( array( 'EXIST-EN-STOCK', 'EXIST-AGOTADO', 'EXIST-TODOS-EN-STOCK', 'EXIST-ALGUNO-AGOTADO', 'EXIST-LISTA-VACIA' ), $in_stock ) ), array_merge( $in_stock, array( 'EXIST-CONTROL' ) ) ),
		),
	),
	// Centinela: en 4.3.0.164 se cumple solo y no cambia un grupo; en un O, el otro grupo decide.
	'f7a-centinela'    => array(
		'elements' => array( $container( 'f8', array(
			$f7a_w( 'j1', 'CENTINELA-SOLO', array( array( $f7a_sentinel ) ) ),
			$f7a_w( 'j2', 'CENTINELA-O', array( array( $cookie( 'is_not_empty' ), $f7a_sentinel ), array( $author( 'role_in' ), $f7a_sentinel ) ) ),
			$f7a_w( 'j3', 'CENTINELA-CONTROL' ),
		) ) ),
		'variants' => array(
			'con-cookie' => array( 'cookies' => $with_cookie ) + $both( array(), array( 'CENTINELA-SOLO', 'CENTINELA-O', 'CENTINELA-CONTROL' ) ),
			'sin-cookie' => array( 'cookies' => array() ) + $both( array( 'CENTINELA-O' ), array( 'CENTINELA-SOLO', 'CENTINELA-CONTROL' ) ),
		),
	),
);
$f7a_render = static function ( $id, $query ) {
	$GLOBALS['wp_query']     = new WP_Query( $query ? $query : array( 'page_id' => $id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post']         = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	\Elementor\Plugin::$instance->documents->get( $id, false );
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
	wp_reset_postdata();
	return $html;
};
$f7a_variant = static function ( $id, array $variant, $who ) use ( $f7a_render, $check, $f7a_secrets ) {
	$saved = $_COOKIE;
	foreach ( (array) ( $variant['cookies'] ?? array() ) as $name => $value ) {
		$_COOKIE[ $name ] = $value;
	}
	wp_set_current_user( 'sesion' === $who ? 1 : 0 );
	list( $hidden, $visible ) = $variant[ $who ];
	$problems = $check( $f7a_render( $id, $variant['query'] ?? null ), array_merge( $hidden, $f7a_secrets ), $visible );
	$_COOKIE = $saved;
	wp_set_current_user( 0 );
	return $problems;
};
foreach ( array( 'disable', '24' ) as $cache ) {
	update_option( 'elementor_element_cache_ttl', $cache );
	foreach ( $f7a_cases as $name => $case ) {
		$id = $f7a_page( 'digi-cond-' . $name . '-' . $cache );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $case['elements'] ) ) );
		foreach ( (array) ( $case['meta'] ?? array() ) as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		delete_post_meta( $id, '_elementor_element_cache' );
		$page_ids[ 'digi-cond-' . $name . '-' . $cache ] = (int) $id;
		$entry = array();
		// Cada variante, como visitante y con sesión; con la caché a 24 h, la primera se repite al final: lo que pintaron
		// las demás no se le sirve.
		$variants = $case['variants'];
		$order    = array_keys( $variants );
		if ( '24' === $cache ) {
			$order[] = $order[0];
		}
		foreach ( $order as $n => $label ) {
			foreach ( array( 'anonimo', 'sesion' ) as $who ) {
				$key = $label . ' · ' . $who . ( $n >= count( $variants ) ? ' · repetida' : '' );
				$entry[ $key ] = $f7a_variant( $id, $variants[ $label ], $who );
			}
		}
		// Vista previa del editor: todo se ve, nunca un valor; después, el visitante vuelve a ver lo suyo.
		if ( 'disable' === $cache ) {
			wp_set_current_user( 1 );
			\Elementor\Plugin::$instance->editor->set_edit_mode( true );
			$first = reset( $variants );
			$texts = array_unique( array_merge( $first['anonimo'][0], $first['anonimo'][1], $first['sesion'][0], $first['sesion'][1] ) );
			$entry['editor'] = $check( $f7a_render( $id, $first['query'] ?? null ), $f7a_secrets, array_values( $texts ) );
			\Elementor\Plugin::$instance->editor->set_edit_mode( false );
			wp_set_current_user( 0 );
			$entry['tras-editor · anonimo'] = $f7a_variant( $id, $first, 'anonimo' );
		}
		$result[ 'cache-' . $cache ][ $name ] = $entry;
	}
}
$result['_f7a'] = array( 'woo' => $f7a_woo, 'visitada' => $f7a_visited, 'productos' => $f7a_products );

// Señales a la caché de página: se miden en un proceso limpio por página (probe-cache.php), como en WordPress.
$result['no-cache'] = array();
foreach ( array( 'nativo-contenedor', 'propio-contenedor', 'heredado-contenedor', 'plantilla-acordeon' ) as $name ) {
	$result['_cases'][ $name ] = array( $cases[ $name ][1], $cases[ $name ][2], $page_ids[ 'digi-cond-' . $name . '-24' ] ?? 0 );
}
file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
