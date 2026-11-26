<?php
/**
 * Pasos de seo-index.sh. Cada paso es una petición PHP propia en un WordPress individual.
 * Escribe sus comprobaciones en result.jsonl y los avisos de PHP de SEO en fatal.log.
 */
$step = getenv( 'DIGI_STEP' );
$dir  = '/wordpress/wp-content/probe';
set_error_handler( function( $no, $str, $file, $line ) use ( $dir ) {
	if ( ! ( error_reporting() & $no ) ) return false;
	if ( false !== strpos( $file, 'digitalisimo-seo' ) && ! ( $no & ( E_DEPRECATED | E_USER_DEPRECATED ) ) ) file_put_contents( $dir . '/fatal.log', "[$no] $str en $file:$line\n", FILE_APPEND );
	return false;
} );
register_shutdown_function( function() use ( $dir, $step ) {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( $dir . '/fatal.log', "[$step] {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND );
} );
function digi_check( $ok, $what ) {
	file_put_contents( '/wordpress/wp-content/probe/result.jsonl', json_encode( array( 'step' => getenv( 'DIGI_STEP' ), 'ok' => (bool) $ok, 'what' => $what ) ) . "\n", FILE_APPEND );
}
if ( 'setup' === $step ) {
	require '/wordpress/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$result = activate_plugin( 'digitalisimo-seo/digitalisimo-integrations.php' );
	digi_check( ! is_wp_error( $result ), 'El plugin se activa' );
	return;
}
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
global $wpdb;
$ids_file = $dir . '/ids.json';
$ids      = file_exists( $ids_file ) ? (array) json_decode( file_get_contents( $ids_file ), true ) : array();
$save_ids = function() use ( &$ids, $ids_file ) { file_put_contents( $ids_file, json_encode( $ids ) ); };
$digi_page     = function( $title, $slug, $parent = 0, $content = '' ) { return (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent, 'post_content' => $content ) ); };
$rename   = function( $id, $slug ) { $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $id ); $request->set_param( 'slug', $slug ); return rest_do_request( $request ); };

/** Pide una dirección como el front (parse_request, consulta, 404, «wp») y devuelve la redirección o null. */
function digi_get( $path ) {
	$captured = null;
	$filter   = function( $location, $status ) use ( &$captured ) { $captured = array( $location, $status ); return false; };
	add_filter( 'wp_redirect', $filter, 10, 2 );
	$_SERVER['REQUEST_URI'] = $path;
	unset( $_SERVER['PATH_INFO'] );
	$_GET = array();
	// Un objeto WP nuevo por petición, como en una visita real: reutilizarlo arrastra la regla y las variables de la anterior.
	$GLOBALS['wp_query'] = new WP_Query(); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['wp'] = new WP();
	$GLOBALS['wp']->main();
	remove_filter( 'wp_redirect', $filter, 10 );
	return $captured;
}
function digi_noindex( $path ) { digi_get( $path ); return ! empty( apply_filters( 'wp_robots', array() )['noindex'] ); }
function digi_sitemap() { ob_start(); Digitalisimo_Integrations_Sitemap::urls_xml( array( 'page' ) ); return ob_get_clean(); }

if ( 'seed' === $step ) {
	$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules( false );
	// Como digitalisimo.lat: importó de Rank Math (que trae «Auto Post Redirect» apagado) con el SEO de antes.
	$options = (array) get_option( 'digitalisimo_integrations_options', array() );
	$options['seo_redirect_slug_watch'] = 0;
	// La importación de antes escribía sin pasar por los ajustes: no quedaba marca de quién lo apagó.
	remove_all_actions( 'update_option_digitalisimo_integrations_options' );
	update_option( 'digitalisimo_integrations_options', $options );
	update_option( 'rank-math-options-general', array( 'redirections_post_redirect' => 'off' ) );
	update_option( 'digitalisimo_seo_rankmath_job', array( 'phase' => 'done' ), false );
	update_option( Digitalisimo_Integrations_Redirections::DB_OPTION, 2, false ); // La versión anterior de la tabla: falta la migración.
	$ids['buen'] = $digi_page( 'El Buen Fin 2026', 'buen-fin' );
	$ids['serv'] = $digi_page( 'Servicios', 'servicios' );
	$ids['web']  = $digi_page( 'Web', 'web', $ids['serv'] );
	$ids['tienda'] = $digi_page( 'Tienda', 'tienda', $ids['web'] );
	$ids['otra'] = $digi_page( 'Otra', 'otra' );
	$ids['a']    = $digi_page( 'Cadena', 'a-uno' );
	$ids['x']    = $digi_page( 'Ida y vuelta', 'x-ida' );
	$ids['z']    = $digi_page( 'Vieja', 'z-vieja' );
	$save_ids();
	digi_check( $ids['tienda'] && '/servicios/web/tienda/' === wp_parse_url( get_permalink( $ids['tienda'] ), PHP_URL_PATH ), 'Páginas con padre y nieta: ' . get_permalink( $ids['tienda'] ) );
	return;
}

if ( 'migrate' === $step ) {
	Digitalisimo_Integrations_Redirections::maybe_install(); // Lo que hace admin_init al actualizar el plugin.
	$options = (array) get_option( 'digitalisimo_integrations_options', array() );
	digi_check( 1 === (int) $options['seo_redirect_slug_watch'] && get_option( Digitalisimo_Integrations_Redirections::RESTORED ), 'La migración vuelve a encender el vigilante que apagó la importación de Rank Math' );
	digi_check( 3 === (int) get_option( Digitalisimo_Integrations_Redirections::DB_OPTION ), 'La tabla queda en la versión 3' );
	// Si después el dueño lo apaga en los ajustes, queda anotado y una migración nunca lo vuelve a encender.
	$options['seo_redirect_slug_watch'] = 0;
	update_option( 'digitalisimo_integrations_options', $options );
	$off = get_option( Digitalisimo_Integrations_Redirections::WATCH_OFF );
	digi_check( 'owner' === ( $off['by'] ?? '' ) && false === Digitalisimo_Integrations_Redirections::restore_slug_watch(), 'Lo que apaga el dueño queda anotado y no se vuelve a encender' );
	$options['seo_redirect_slug_watch'] = 1;
	update_option( 'digitalisimo_integrations_options', $options );
	digi_check( ! get_option( Digitalisimo_Integrations_Redirections::WATCH_OFF ), 'Encenderlo borra la marca' );
	return;
}

if ( 'rename' === $step ) {
	// Como la 14601: cambio de slug por la REST de WordPress.
	$response = $rename( $ids['buen'], 'black-friday' );
	digi_check( 200 === $response->get_status() && 'black-friday' === get_post_field( 'post_name', $ids['buen'] ), 'La REST cambia el slug: ' . $response->get_status() );
	$index = get_option( Digitalisimo_Integrations_Redirections::INDEX );
	digi_check( isset( $index['exact']['buen-fin'] ), 'El cambio por la REST crea la 301 del slug anterior' );
	return;
}

if ( 'parent' === $step ) {
	// El padre cambia de slug: subpágina y nieta cambian de dirección.
	wp_update_post( array( 'ID' => $ids['serv'], 'post_name' => 'soluciones' ) );
	$index = get_option( Digitalisimo_Integrations_Redirections::INDEX );
	digi_check( isset( $index['exact']['servicios'], $index['exact']['servicios/web'], $index['exact']['servicios/web/tienda'] ), 'Cambiar el slug del padre crea la 301 del padre, la hija y la nieta: ' . implode( ', ', array_keys( $index['exact'] ) ) );
	// La hija cambia de padre: su dirección y la de su hija cambian otra vez.
	wp_update_post( array( 'ID' => $ids['web'], 'post_parent' => $ids['otra'] ) );
	digi_check( '/otra/web/tienda/' === wp_parse_url( get_permalink( $ids['tienda'] ), PHP_URL_PATH ), 'La nieta cuelga ahora de /otra/web/' );
	$rule = function( $from ) use ( $wpdb ) { foreach ( (array) $wpdb->get_results( 'SELECT sources, url_to, status FROM ' . Digitalisimo_Integrations_Redirections::table(), ARRAY_A ) as $row ) foreach ( (array) json_decode( $row['sources'], true ) as $s ) if ( $from === $s['pattern'] && 'active' === $row['status'] ) return $row['url_to']; return null; };
	digi_check( '/otra/web/' === $rule( 'servicios/web' ) && '/otra/web/' === $rule( 'soluciones/web' ), 'Las reglas que llevaban a la dirección anterior pasan a la nueva (sin cadena): ' . json_encode( array( $rule( 'servicios/web' ), $rule( 'soluciones/web' ) ) ) );
	digi_check( '/otra/web/tienda/' === $rule( 'servicios/web/tienda' ) && '/otra/web/tienda/' === $rule( 'soluciones/web/tienda' ), 'La nieta también: ' . json_encode( array( $rule( 'servicios/web/tienda' ), $rule( 'soluciones/web/tienda' ) ) ) );
	return;
}

if ( 'chains' === $step ) {
	// A→B y luego B→C: A debe ir a C.
	wp_update_post( array( 'ID' => $ids['a'], 'post_name' => 'b-dos' ) );
	wp_update_post( array( 'ID' => $ids['a'], 'post_name' => 'c-tres' ) );
	// A→B→A: ningún bucle.
	wp_update_post( array( 'ID' => $ids['x'], 'post_name' => 'y-vuelta' ) );
	wp_update_post( array( 'ID' => $ids['x'], 'post_name' => 'x-ida' ) );
	// Un contenido nuevo ocupa una dirección vieja.
	wp_update_post( array( 'ID' => $ids['z'], 'post_name' => 'z-nueva' ) );
	$ids['z2'] = $digi_page( 'Nueva en la dirección vieja', 'z-vieja' );
	$save_ids();
	digi_check( 'z-vieja' === get_post_field( 'post_name', $ids['z2'] ), 'La página nueva toma la dirección vieja' );
	return;
}

if ( 'front' === $step ) {
	$expect = array(
		'/buen-fin/'                => '/black-friday/',
		'/servicios/'               => '/soluciones/',
		'/servicios/web/'           => '/otra/web/',
		'/servicios/web/tienda/'    => '/otra/web/tienda/',
		'/soluciones/web/tienda/'   => '/otra/web/tienda/',
		'/a-uno/'                   => '/c-tres/',
		'/b-dos/'                   => '/c-tres/',
		'/y-vuelta/'                => '/x-ida/',
	);
	foreach ( $expect as $from => $to ) {
		$got = digi_get( $from );
		digi_check( $got && home_url( $to ) === $got[0] && 301 === (int) $got[1], 'Pedir ' . $from . ' da 301 a ' . $to . ' en un solo salto: ' . json_encode( $got ) . ( $got ? '' : ' · ' . json_encode( array( 'is_404' => is_404(), 'rule' => $GLOBALS['wp']->matched_rule, 'vars' => $GLOBALS['wp']->query_vars, 'object' => get_queried_object_id(), 'index' => isset( get_option( Digitalisimo_Integrations_Redirections::INDEX )['exact'][ trim( $from, '/' ) ] ) ) ) ) );
	}
	foreach ( array( '/x-ida/', '/black-friday/', '/z-vieja/', '/otra/web/tienda/' ) as $alive ) {
		$got = digi_get( $alive );
		digi_check( null === $got && ! is_404(), 'Pedir ' . $alive . ' sirve el contenido sin redirigir: ' . json_encode( $got ) );
	}
	return;
}

if ( 'monitor' === $step ) {
	$options = (array) get_option( 'digitalisimo_integrations_options', array() );
	$options['seo_404_enabled'] = 1;
	update_option( 'digitalisimo_integrations_options', $options );
	Digitalisimo_Integrations_404_Monitor::maybe_install(); // admin_init: tabla, cron y carpeta del búfer.
	$digi_mon = Digitalisimo_Integrations_404_Monitor::class; // Nombres propios: WP::main() exporta las variables de consulta (m, page…) al ámbito global.
	digi_check( wp_next_scheduled( $digi_mon::FLUSH ) && wp_next_scheduled( $digi_mon::CRON ), 'Volcado cada hora y limpieza diaria programados' );
	digi_check( is_dir( dirname( $digi_mon::buffer_file() ) ), 'La carpeta del búfer se crea desde el escritorio' );
	$digi_mon::buffer_file(); // Calienta la ruta de subidas y las sales, como en cualquier petición que ya las usó.
	foreach ( array( '/vieja/tienda/', '/vieja/tienda/?utm=1', '/no-existe-nada-xyz/' ) as $path ) { digi_get( $path ); $queries = $wpdb->num_queries; $digi_mon::record(); digi_check( $queries === $wpdb->num_queries, 'Anotar el 404 de ' . $path . ' no hace ninguna consulta' ); }
	digi_check( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $digi_mon::table() ), 'Antes del volcado la tabla sigue vacía' );
	digi_check( 2 === $digi_mon::flush(), 'El volcado agrega dos URLs' );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT uri, hits FROM ' . $digi_mon::table() . ' WHERE uri = %s', '/vieja/tienda/' ), ARRAY_A );
	digi_check( $row && 2 === (int) $row['hits'], 'Dos visitas a la misma URL quedan en una fila: ' . json_encode( $row ) );
	$suggest = $digi_mon::suggest( '/vieja/tienda/' );
	digi_check( $suggest && '/otra/web/tienda/' === $suggest['url'], 'Sugiere la página con el mismo slug: ' . json_encode( $suggest ) );
	digi_check( null === $digi_mon::suggest( '/no-existe-nada-xyz/' ), 'Sin parecido no inventa un destino' );
	ob_start(); $digi_mon::render_log(); $html = ob_get_clean();
	digi_check( false !== strpos( $html, 'Crear 301 a la sugerencia' ) && false !== strpos( $html, rawurlencode( '/otra/web/tienda/' ) ), 'El registro ofrece crear la 301 a la sugerencia' );
	// Tope: con el búfer lleno, las visitas se descartan.
	file_put_contents( $digi_mon::buffer_file(), str_repeat( 'x', $digi_mon::BUFFER_MAX ) );
	digi_get( '/otra-mas/' ); $digi_mon::record();
	digi_check( $digi_mon::BUFFER_MAX === filesize( $digi_mon::buffer_file() ), 'Con el búfer lleno no crece' );
	$digi_mon::flush();
	digi_check( ! empty( get_option( $digi_mon::STATE )['full'] ), 'El volcado anota que el búfer se llenó' );
	return;
}

if ( 'hygiene' === $step ) {
	$digi_hyg = Digitalisimo_Integrations_Index_Hygiene::class;
	$digi_hyg::request_rescan(); // Como al activar un plugin: nueva época del front.
	sleep( 1 );
	add_shortcode( 'mi_formulario', function() { return 'formulario'; } ); // Sólo registrado en esta petición del «front».
	$digi_hyg::sample_front();
	digi_check( in_array( 'mi_formulario', (array) get_option( $digi_hyg::HISTORY ), true ), 'La muestra del front anota los shortcodes registrados' );
	$sane = array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>[Nota del editor] Precio en [MXN]. Lee [texto](https://ejemplo.test). [Ver video] [pdf]</p>', '__dynamic__' => array( 'editor' => '[elementor-tag id="a1" name="post-title" settings="%7B%7D"]' ) ) ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contacto', '__dynamic__' => array( 'link' => '[elementor-tag id="b2" name="site-url" settings="%7B%7D"]' ) ) ),
		array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'form', 'settings' => array( 'email_content' => '[all-fields] [field id="name"]', 'form_fields' => array( array( 'custom_id' => 'name', 'field_label' => 'Nombre' ) ) ) ),
	) ) );
	$broken = $sane; $broken[0]['elements'][] = array( 'id' => 'w4', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[tutor_student_registration_form]' ) );
	$elementor = function( $title, $slug, $data ) { return (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '<p>Copia vieja: [wpuf_account]</p>', 'meta_input' => array( '_elementor_edit_mode' => 'builder', '_elementor_data' => wp_slash( wp_json_encode( $data ) ) ) ) ); };
	for ( $i = 1; $i <= 40; $i++ ) $digi_page( 'Relleno ' . $i, 'relleno-' . $i, 0, '<p>Contenido normal.</p>' ); // Un sitio de tamaño real: el tope es el 10 %.
	$ids['cuenta']    = $digi_page( 'Cuenta', 'cuenta', 0, '<!-- wp:shortcode -->[wpuf_account]<!-- /wp:shortcode -->' );
	$ids['ingresar']  = $digi_page( 'Ingresar', 'ingresar', 0, '[wpuf_login]' );
	$ids['galeria']   = $digi_page( 'Galería', 'galeria', 0, '<p>Fotos</p>[gallery ids="1"]' );
	$ids['nota']      = $digi_page( 'Nota', 'nota', 0, '<p>[Nota del editor] Ver [1], [MXN], [texto](https://x.test) y <code>[ejemplo]</code></p><style>a[hidden]{display:none}</style>' );
	$ids['formulario'] = $digi_page( 'Formulario', 'formulario', 0, '[mi_formulario]' );
	$ids['el_sana']   = $elementor( 'Elementor sana', 'elementor-sana', $sane );
	$ids['el_rota']   = $elementor( 'Elementor rota', 'elementor-rota', $broken );
	$save_ids();
	update_post_meta( $ids['ingresar'], $digi_hyg::LOCK, '1' );
	$digi_meta = function( $key ) use ( $ids, $digi_hyg ) { return get_post_meta( $ids[ $key ], $digi_hyg::META, true ); };
	digi_check( array( 'shortcode:wpuf_account' ) === $digi_meta( 'cuenta' ) && array( 'shortcode:wpuf_login' ) === $digi_meta( 'ingresar' ), 'Al guardar se marcan las páginas con shortcodes de un plugin desinstalado: ' . json_encode( array( $digi_meta( 'cuenta' ), $digi_meta( 'ingresar' ) ) ) );
	digi_check( '' === $digi_meta( 'galeria' ) && '' === $digi_meta( 'nota' ) && '' === $digi_meta( 'formulario' ), 'Un shortcode registrado, texto con corchetes o CSS no marcan nada: ' . json_encode( array( $digi_meta( 'galeria' ), $digi_meta( 'nota' ), $digi_meta( 'formulario' ) ) ) );
	digi_check( '' === $digi_meta( 'el_sana' ), 'Una página de Elementor con etiquetas dinámicas, formulario y texto con corchetes no se marca (ni por su copia vieja del editor): ' . json_encode( $digi_meta( 'el_sana' ) ) );
	digi_check( array( 'shortcode:tutor_student_registration_form' ) === $digi_meta( 'el_rota' ), 'Un widget de shortcode de un plugin desinstalado sí: ' . json_encode( $digi_meta( 'el_rota' ) ) );
	return;
}

if ( 'hygiene-scan' === $step ) {
	// Se «desinstala» el plugin de [mi_formulario]: nueva época; el front ya no lo registra.
	$digi_hyg = Digitalisimo_Integrations_Index_Hygiene::class;
	$digi_hyg::request_rescan();
	digi_check( 0 === $digi_hyg::run_batch() && '' === get_post_meta( $ids['formulario'], $digi_hyg::META, true ), 'Sin muestra del front posterior al cambio, WP-Cron espera y no decide' );
	sleep( 1 );
	$digi_hyg::sample_front();
	for ( $i = 0; $i < 20 && $digi_hyg::run_batch() >= $digi_hyg::BATCH; $i++ );
	digi_check( array( 'shortcode:mi_formulario' ) === get_post_meta( $ids['formulario'], $digi_hyg::META, true ), 'Un shortcode que estuvo registrado y ya no está se marca en la pasada' );
	digi_check( '' === get_post_meta( $ids['el_sana'], $digi_hyg::META, true ) && '' === get_post_meta( $ids['nota'], $digi_hyg::META, true ), 'La pasada tampoco marca las páginas sanas' );
	$state = get_option( $digi_hyg::STATE );
	digi_check( ! empty( $state['done'] ) && 4 === (int) $state['flagged'] && empty( $state['held'] ), 'La pasada termina y cuenta 4 páginas: ' . json_encode( $state ) );
	// Guardar con un shortcode nuevo sin plugin lo marca; corregirlo lo limpia.
	wp_update_post( array( 'ID' => $ids['galeria'], 'post_content' => '<p>Fotos</p>[wpuf_dashboard]' ) );
	digi_check( array( 'shortcode:wpuf_dashboard' ) === get_post_meta( $ids['galeria'], $digi_hyg::META, true ), 'Guardar con un shortcode sin resolver marca la página' );
	wp_update_post( array( 'ID' => $ids['galeria'], 'post_content' => '<p>Fotos</p>' ) );
	digi_check( '' === get_post_meta( $ids['galeria'], $digi_hyg::META, true ), 'Corregirla y guardarla la saca de la lista' );
	return;
}

if ( 'hygiene-front' === $step ) {
	$digi_hyg = Digitalisimo_Integrations_Index_Hygiene::class;
	ob_start(); $digi_hyg::render_report(); $html = ob_get_clean();
	digi_check( false !== strpos( $html, '[wpuf_account]' ) && false !== strpos( $html, 'Sólo es un aviso' ) && false !== strpos( $html, 'Indexable por decisión del dueño' ), 'De fábrica, Indexación sólo avisa' );
	digi_check( ! digi_noindex( '/cuenta/' ) && false !== strpos( digi_sitemap(), '/cuenta/' ), 'De fábrica /cuenta/ sigue indexable y en el sitemap' );
	$options = (array) get_option( 'digitalisimo_integrations_options', array() );
	$options['seo_noindex_unresolved'] = 1;
	update_option( 'digitalisimo_integrations_options', $options );
	digi_check( digi_noindex( '/cuenta/' ) && digi_noindex( '/elementor-rota/' ), 'Con el ajuste encendido, /cuenta/ y /elementor-rota/ llevan noindex' );
	digi_check( ! digi_noindex( '/ingresar/' ), '/ingresar/ queda indexable por decisión del dueño' );
	digi_check( ! digi_noindex( '/elementor-sana/' ) && ! digi_noindex( '/nota/' ) && ! digi_noindex( '/black-friday/' ), 'Las páginas sanas siguen indexables' );
	$xml = digi_sitemap();
	digi_check( false === strpos( $xml, '/cuenta/' ) && false !== strpos( $xml, '/ingresar/' ) && false !== strpos( $xml, '/elementor-sana/' ), 'El sitemap saca /cuenta/ y conserva /ingresar/ y /elementor-sana/' );
	return;
}

if ( 'hygiene-cap' === $step ) {
	$digi_hyg = Digitalisimo_Integrations_Index_Hygiene::class;
	for ( $i = 1; $i <= 20; $i++ ) $digi_page( 'Rota ' . $i, 'rota-' . $i, 0, '[wpuf_account]' );
	$state = get_option( $digi_hyg::STATE );
	digi_check( ! empty( $state['held'] ) && 0 === (int) get_option( $digi_hyg::APPLY ), 'Más de 20 páginas marcadas: se retiene: ' . json_encode( $state ) );
	digi_check( ! digi_noindex( '/cuenta/' ) && false !== strpos( digi_sitemap(), '/cuenta/' ), 'Retenido, no se aplica nada aunque el ajuste esté encendido' );
	ob_start(); $digi_hyg::render_report(); $html = ob_get_clean();
	digi_check( false !== strpos( $html, 'Confirmar la lista' ), 'Indexación pide al dueño que confirme' );
	$state['confirmed'] = (int) $state['flagged'];
	update_option( $digi_hyg::STATE, $state, false );
	$digi_hyg::recount();
	digi_check( digi_noindex( '/cuenta/' ), 'Confirmada la lista, se aplica' );
	$options = (array) get_option( 'digitalisimo_integrations_options', array() );
	$options['seo_noindex_unresolved'] = 0;
	update_option( 'digitalisimo_integrations_options', $options );
	digi_check( ! digi_noindex( '/cuenta/' ) && false !== strpos( digi_sitemap(), '/cuenta/' ), 'Con el ajuste apagado nada cambia' );
	return;
}
