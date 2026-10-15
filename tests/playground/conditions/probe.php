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
// Señales a la caché de página: se miden en un proceso limpio por página (probe-cache.php), como en WordPress.
$result['no-cache'] = array();
foreach ( array( 'nativo-contenedor', 'propio-contenedor', 'heredado-contenedor', 'plantilla-acordeon' ) as $name ) {
	$result['_cases'][ $name ] = array( $cases[ $name ][1], $cases[ $name ][2], $page_ids[ 'digi-cond-' . $name . '-24' ] ?? 0 );
}
file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
