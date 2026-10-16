<?php
/**
 * Contenido condicionado por visitante: los widgets que incrustan plantillas no entran en la caché de elementos de
 * Elementor, la página que pinta un elemento condicionado pide a las cachés de página que no la guarden, y las reglas
 * propias de usuario, rol y parámetros de URL siguen la semántica documentada y las heredadas el port literal de Element Pack.
 */
namespace Elementor { class Plugin { public static $instance; public $editor; } class Controls_Manager {} class Repeater {} }
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['digi'] = array( 'hooks' => array(), 'actions' => array(), 'user' => 0, 'roles' => array(), 'type' => 'page', 'queried' => 12, 'flags' => array(), 'filters' => array(), 'headers' => 0, 'admin' => false, 'edit' => false );
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['digi']['edit']; } };
	function add_action( $hook, $callback ) { $GLOBALS['digi']['hooks'][ $hook ] = $callback; }
	function add_filter( $hook, $callback ) { $GLOBALS['digi']['hooks'][ $hook ] = $callback; }
	function do_action( $hook ) { $GLOBALS['digi']['actions'][] = $hook; }
	function apply_filters( $hook, $value, ...$args ) { if ( ! array_key_exists( $hook, $GLOBALS['digi']['filters'] ) ) { return $value; } $o = $GLOBALS['digi']['filters'][ $hook ]; return is_callable( $o ) ? $o( $value, ...$args ) : $o; }
	function is_admin() { return $GLOBALS['digi']['admin']; }
	function nocache_headers() { $GLOBALS['digi']['headers']++; }
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
	function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
	function wp_unslash( $v ) { return $v; }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
	function home_url( $p = '' ) { return 'https://www.mi-sitio.test/' . ltrim( $p, '/' ); }
	function get_option( $n, $d = false ) { return 'gmt_offset' === $n ? -6 : $d; }
	function current_time( $t ) { return time() - 6 * HOUR_IN_SECONDS; }
	function is_user_logged_in() { return $GLOBALS['digi']['user'] > 0; }
	function get_current_user_id() { return $GLOBALS['digi']['user']; }
	function wp_get_current_user() { return (object) array( 'roles' => $GLOBALS['digi']['roles'] ); }
	function is_singular( $t = '' ) { return $GLOBALS['digi']['singular'] ?? true; }
	function get_page_template_slug( $id = 0 ) { return $GLOBALS['digi']['template'] ?? ''; }
	function is_single() { return false; }
	function is_front_page() { return false; } function is_home() { return false; } function is_404() { return false; }
	function get_the_ID() { return 12; } function get_queried_object_id() { return 12; } function get_queried_object() { return null; } function get_post_type() { return 'page'; }
	function get_locale() { return 'es_MX'; }
	function is_feed() { return false; } function is_robots() { return false; } function is_embed() { return false; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['digi']['meta'][ $id ][ $key ] ?? ''; }
	function do_shortcode( $c ) { return $c; } function shortcode_unautop( $c ) { return $c; }
	function check_vc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	class WooCommerce {}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visitor-content.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visibility-rules.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visibility.php';
	require __DIR__ . '/../digitalisimo-migration/legacy/conditions/class-legacy-rules.php';
	use Digitalisimo\Elements\Conditions\Visitor_Content as VC;
	use Digitalisimo\Elements\Visibility;
	use Digitalisimo\Elements\Conditions\Visibility_Rules as Own;
	use Digitalisimo\Elements\Conditions\Legacy_Rules as Legacy;

	VC::init();
	check_vc( isset( $GLOBALS['digi']['hooks']['elementor/element/is_dynamic_content'], $GLOBALS['digi']['hooks']['elementor/frontend/before_render'], $GLOBALS['digi']['hooks']['template_redirect'] ), 'Debe engancharse a la caché de elementos, al render y a template_redirect (antes de la salida).' );
	// Cada motor declara sus reglas por filtro sólo mientras está activo en el sitio; el núcleo no conoce motores por sí mismo.
	check_vc( array() === VC::SWITCH_KEYS && array() === VC::switches() && array( 'e_display_conditions', 'display-conditions' ) === VC::markers(), 'El núcleo no conoce interruptores ni marcadores ajenos por sí mismo.' );
	$own_settings = array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_rules' => array( array( 'rule_key' => 'authentication' ), array( 'rule_key' => 'country' ) ) );
	check_vc( array( 'authentication', 'country' ) === Visibility::visitor_content_conditions( array(), $own_settings ) && array( 'x' ) === Visibility::visitor_content_conditions( array( 'x' ), array( 'digitalisimo_visibility_enable' => '' ) + $own_settings ), 'El motor propio declara las claves de sus reglas activas.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visibility_enabled'] = false;
	check_vc( array( 'x' ) === Visibility::visitor_content_conditions( array( 'x' ), $own_settings ), 'Con el motor propio apagado en el sitio sus reglas no cuentan: la página se pinta igual para todos.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visibility_enabled'] );
	check_vc( in_array( 'digitalisimo_visibility_enable', Visibility::visitor_content_markers( array() ), true ), 'El motor propio declara su marcador.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_conditions'] = array( Visibility::class, 'visitor_content_conditions' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_markers'] = array( Visibility::class, 'visitor_content_markers' );

	// Caché de elementos: los widgets que incrustan plantillas y las diapositivas de plantilla son dinámicos.
	foreach ( VC::TEMPLATE_WIDGETS as $type ) {
		check_vc( true === VC::is_dynamic_content( false, array( 'widgetType' => $type, 'settings' => array() ) ), $type . ' no debe guardarse en la caché de elementos.' );
	}
	check_vc( true === VC::is_dynamic_content( false, array( 'widgetType' => 'slides', 'settings' => array( 'slides' => array( array( 'digitalisimo_template_id' => '7' ) ) ) ) ), 'Las diapositivas de plantilla son dinámicas.' );
	check_vc( true === VC::is_dynamic_content( false, array( 'widgetType' => 'otro-slider', 'settings' => array( 'slides' => array( array( 'digitalisimo_template_id' => '7' ) ) ) ) ) && false === VC::is_dynamic_content( false, array( 'widgetType' => 'otro-slider', 'settings' => array( 'slides' => array( array( 'heading' => 'x' ) ) ) ) ), 'Un widget registrado con otro nombre sobre el de diapositivas también es dinámico sólo si usa plantillas.' );
	check_vc( false === VC::is_dynamic_content( false, array( 'widgetType' => 'slides', 'settings' => array( 'slides' => array( array( 'heading' => 'x' ) ) ) ) ) && false === VC::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array() ) ), 'Lo demás conserva la decisión de Elementor.' );
	check_vc( true === VC::is_dynamic_content( true, array( 'widgetType' => 'heading', 'settings' => array() ) ), 'Lo que ya era dinámico sigue siéndolo.' );

	// Identificadores de condición en los tres formatos y clasificación: sesión, rol, usuario y contenido no varían entre visitantes anónimos.
	check_vc( array( 'login_status', 'digitalisimo_country' ) === VC::condition_ids( array( 'e_display_conditions' => array( '[[{"id":"a","condition":"login_status","comparator":"is"}],[{"condition":"digitalisimo_country"}]]' ) ) ), 'Las condiciones nativas se leen de sus grupos.' );
	check_vc( array( 'login_status' ) === VC::condition_ids( array( 'e_display_conditions' => '[[{"condition":"login_status"}]]' ) ), 'También como cadena.' );
	check_vc( array( 'digitalisimo_browser' ) === VC::condition_ids( array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => array( array( '$$type' => 'condition-group', 'value' => array( array( '$$type' => 'condition', 'value' => array( 'condition' => array( '$$type' => 'string', 'value' => 'digitalisimo_browser' ) ) ) ) ) ) ) ) ), 'Las condiciones atómicas se leen a cualquier profundidad.' );
	check_vc( array( 'authentication', 'country' ) === VC::condition_ids( $own_settings ), 'Las reglas propias llegan por el filtro.' );
	check_vc( array() === VC::condition_ids( array( 'e_display_conditions' => array( '[]' ) ) ) && array() === VC::condition_ids( array( 'e_display_conditions' => '[[]]' ) ) && array() === VC::condition_ids( array( 'title' => 'Hola' ) ) && array() === VC::condition_ids( array( 'e_display_conditions' => array( '[[{"condition":""}]]' ) ) ), 'Sin condiciones no hay identificadores.' );
	check_vc( ! VC::varies_by_visitor( array( 'login_status', 'user_role', 'authentication', 'role', 'user', 'post_type', 'digitalisimo_special_page', 'page_author' ) ) && ! VC::varies_by_visitor( array() ), 'Sesión, rol, usuario y contenido no varían entre visitantes anónimos.' );
	check_vc( VC::varies_by_visitor( array( 'login_status', 'digitalisimo_country' ) ) && VC::varies_by_visitor( array( 'browser' ) ) && VC::varies_by_visitor( array( 'current_date' ) ) && VC::varies_by_visitor( array( 'url_parameters' ) ) && VC::varies_by_visitor( array( 'dynamic_tags' ) ) && VC::varies_by_visitor( array( 'inventada' ) ) && VC::varies_by_visitor( array( '*' ) ), 'País, navegador, fecha, URL, etiquetas dinámicas y lo desconocido sí varían.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_cacheable_conditions'] = array_merge( VC::CACHEABLE_CONDITIONS, array( 'inventada' ) );
	check_vc( ! VC::varies_by_visitor( array( 'inventada' ) ), 'Un sitio puede declarar cacheable una condición propia.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_cacheable_conditions'] );
	check_vc( ! VC::is_conditioned( array( 'e_display_conditions' => array( '[[{"condition":"login_status"}]]' ) ) ) && VC::is_conditioned( array( 'e_display_conditions' => array( '[[{"condition":"login_status"},{"condition":"digitalisimo_country"}]]' ) ) ), 'Un elemento sólo para sesión sigue en caché; uno por país, no.' );
	check_vc( ! VC::is_conditioned( array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_rules' => array( array( 'rule_key' => 'authentication' ) ) ) ) && VC::is_conditioned( $own_settings ), 'Lo mismo con las reglas propias.' );
	check_vc( ! VC::is_conditioned( array( 'ep_display_conditions_enable' => 'yes' ) ), 'El núcleo no conoce los interruptores heredados por sí mismo.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_switches'] = array( 'ep_display_conditions_enable' );
	check_vc( VC::is_conditioned( array( 'ep_display_conditions_enable' => 'yes' ) ) && array( '*' ) === VC::condition_ids( array( 'ep_display_conditions_enable' => 'yes' ) ) && in_array( 'ep_display_conditions_enable', VC::markers(), true ), 'Un interruptor de compatibilidad (motor que no enumera sus reglas) cuenta como condición desconocida.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_switches'] );
	check_vc( VC::is_conditioned( array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => array( array( '$$type' => 'condition-group', 'value' => array( array( 'condition' => 'digitalisimo_os' ) ) ) ) ) ) ) && ! VC::is_conditioned( array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => null ) ) ), 'Las condiciones atómicas (editor v4) se clasifican igual.' );

	// JSON guardado del documento: sólo se decodifica si aparece un marcador, y sólo cuentan las condiciones que varían.
	$doc    = static function ( $settings ) { return '[{"id":"a1","elType":"container","settings":{},"elements":[{"id":"b2","elType":"widget","widgetType":"heading","settings":' . $settings . '}]}]'; };
	$native = static function ( $condition ) { return '{"title":"Hola","e_display_conditions":["[[{\\"id\\":\\"c1\\",\\"condition\\":\\"' . $condition . '\\"}]]"]}'; };
	check_vc( VC::has_visitor_conditions( $doc( $native( 'digitalisimo_country' ) ) ) && VC::has_visitor_conditions( $doc( '{"e_display_conditions":"[[{\\"condition\\":\\"current_date\\"}]]"}' ) ), 'Las condiciones nativas que varían se detectan en el JSON.' );
	check_vc( ! VC::has_visitor_conditions( $doc( $native( 'login_status' ) ) ) && ! VC::has_visitor_conditions( $doc( $native( 'user_role' ) ) ), 'Un documento sólo con condiciones de sesión sigue en caché (los usuarios con sesión nunca se sirven desde ella).' );
	check_vc( ! VC::has_visitor_conditions( $doc( '{"e_display_conditions":["[]"]}' ) ) && ! VC::has_visitor_conditions( $doc( '{"e_display_conditions":"[[]]"}' ) ) && ! VC::has_visitor_conditions( $doc( '{"title":"e_display_conditions"}' ) ) && ! VC::has_visitor_conditions( '' ) && ! VC::has_visitor_conditions( null ) && ! VC::has_visitor_conditions( 'no es json e_display_conditions' ), 'Condiciones vacías, el texto suelto, el vacío o un JSON roto no cuentan.' );
	check_vc( VC::has_visitor_conditions( $doc( '{"display-conditions":{"$$type":"display-conditions","value":[{"$$type":"condition-group","value":[{"condition":"digitalisimo_browser"}]}]}}' ) ) && ! VC::has_visitor_conditions( $doc( '{"display-conditions":{"$$type":"display-conditions","value":[]}}' ) ), 'Las condiciones atómicas se detectan sólo con grupos que varían.' );
	check_vc( VC::has_visitor_conditions( $doc( '{"digitalisimo_visibility_enable":"yes","digitalisimo_visibility_rules":[{"rule_key":"url_parameters"}]}' ) ) && ! VC::has_visitor_conditions( $doc( '{"digitalisimo_visibility_enable":"yes","digitalisimo_visibility_rules":[{"rule_key":"authentication"}]}' ) ) && ! VC::has_visitor_conditions( $doc( '{"digitalisimo_visibility_enable":""}' ) ) && ! VC::has_visitor_conditions( $doc( '{"ep_display_conditions_enable":"yes","ep_display_conditions":[{"ep_condition_key":"browser"}]}' ) ), 'Las reglas propias se clasifican por su clave; las de un motor ausente no cuentan.' );
	check_vc( VC::has_visitor_conditions( '[{"id":"x","elType":"container","settings":{},"elements":[{"id":"y","elType":"container","settings":{},"elements":[{"id":"z","elType":"widget","settings":' . $native( 'digitalisimo_language' ) . '}]}]}]' ), 'Se recorren los hijos a cualquier profundidad.' );

	// Identificadores de todo el documento y buffer de salida para las reglas que escriben cookies.
	check_vc( array( 'digitalisimo_language' ) === VC::document_condition_ids( $doc( $native( 'digitalisimo_language' ) ) ) && array() === VC::document_condition_ids( $doc( '{"title":"Hola"}' ) ) && array( 'authentication', 'country' ) === VC::document_condition_ids( $doc( json_encode( $own_settings ) ) ), 'Se enumeran las condiciones de todos los elementos del documento.' );
	check_vc( VC::needs_buffer( array( 'login_status', 'visit_count' ) ) && VC::needs_buffer( array( 'session_count' ) ) && ! VC::needs_buffer( array( 'digitalisimo_country', 'authentication' ) ) && ! VC::needs_buffer( array() ), 'Sólo visitas y sesiones necesitan el buffer de salida.' );
	check_vc( ! VC::varies_by_visitor( array( 'authentication' ) ) && VC::varies_by_visitor( array( 'visit_count' ) ), 'Las visitas varían por visitante (la página no se guarda).' );

	// Documentos a revisar: el de la URL si está construido con el editor, más las ubicaciones del Theme Builder que su plantilla imprime.
	check_vc( array() === VC::documents_to_scan(), 'Una página sin «construido con Elementor» no se revisa.' );
	$GLOBALS['digi']['meta'][12]['_elementor_edit_mode'] = 'builder';
	check_vc( array( 12 ) === VC::documents_to_scan(), 'Sin Theme Builder se revisa el documento de la URL.' );
	check_vc( array( 'header', 'footer', 'single', 'popup' ) === VC::theme_locations(), 'Una entrada con plantilla predeterminada imprime cabecera, pie, plantilla de entrada y ventanas.' );
	$GLOBALS['digi']['template'] = 'elementor_canvas';
	check_vc( array() === VC::theme_locations(), 'Con Canvas no se imprime ninguna ubicación.' );
	$GLOBALS['digi']['template'] = 'elementor_header_footer';
	check_vc( array( 'header', 'footer', 'popup' ) === VC::theme_locations(), 'Con «Cabecera y pie» (o cualquier plantilla del tema) no se imprime la plantilla de entrada.' );
	$GLOBALS['digi']['template'] = 'default';
	check_vc( array( 'header', 'footer', 'single', 'popup' ) === VC::theme_locations(), '«default» es la plantilla predeterminada.' );
	$GLOBALS['digi']['template'] = '';
	$GLOBALS['digi']['singular'] = false;
	check_vc( array( 'header', 'footer', 'archive', 'popup' ) === VC::theme_locations() && array() === VC::documents_to_scan(), 'Un archivo imprime cabecera, pie, archivo y ventanas, sin documento propio.' );
	$GLOBALS['digi']['singular'] = true;

	// Antes de la salida: el documento de la URL sin condiciones (o sólo con las de sesión) no toca la caché de página.
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( '{"title":"Hola"}' );
	VC::flag_before_output();
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( $native( 'login_status' ) );
	VC::flag_before_output();
	check_vc( ! defined( 'DONOTCACHEPAGE' ) && 0 === $GLOBALS['digi']['headers'], 'Un documento sin condiciones por visitante no pide quedar fuera de la caché.' );
	VC::reset();

	// Caché de página: al pintar un elemento que varía se avisa una sola vez, fuera del editor y de la administración.
	$element = new class() { public $settings = array(); public function get_settings() { return $this->settings; } };
	VC::before_render( $element );
	$element->settings = array( 'e_display_conditions' => array( '[[{"condition":"login_status"}]]' ) );
	VC::before_render( $element );
	check_vc( ! defined( 'DONOTCACHEPAGE' ) && 0 === $GLOBALS['digi']['headers'], 'Un elemento sin condiciones, o sólo de sesión, no toca la caché de página.' );
	$element->settings = $own_settings;
	VC::before_render( $element );
	VC::before_render( $element );
	check_vc( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE && defined( 'LSCACHE_NO_CACHE' ) && 1 === $GLOBALS['digi']['headers'] && in_array( 'litespeed_control_set_nocache', $GLOBALS['digi']['actions'], true ), 'La página con contenido por visitante pide no guardarse en caché, una sola vez.' );
	// headers_sent() es nativa de PHP: en CLI siempre es false, así que nocache_headers() se invoca; en una página ya enviada no.
	VC::reset();
	$GLOBALS['digi']['edit'] = true;
	VC::before_render( $element );
	check_vc( 1 === $GLOBALS['digi']['headers'], 'En el editor no se tocan las cabeceras.' );
	$GLOBALS['digi']['edit'] = false;
	VC::reset();
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_no_cache'] = false;
	VC::before_render( $element );
	check_vc( 1 === $GLOBALS['digi']['headers'], 'Un filtro del sitio puede desactivar el aviso.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_no_cache'] );
	// Antes de la salida: con condiciones por visitante en el documento, las cabeceras salen en template_redirect (a tiempo para CDN y proxies).
	VC::reset();
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( $native( 'digitalisimo_country' ) );
	VC::flag_before_output();
	check_vc( 2 === $GLOBALS['digi']['headers'], 'Un documento con condiciones por visitante pide no guardarse antes de la salida.' );
	VC::flag_before_output();
	VC::before_render( $element );
	check_vc( 2 === $GLOBALS['digi']['headers'], 'Se avisa una sola vez por petición.' );
	VC::reset();
	$GLOBALS['digi']['edit'] = true;
	VC::flag_before_output();
	$GLOBALS['digi']['edit'] = false;
	VC::reset();
	$GLOBALS['digi']['admin'] = true;
	VC::flag_before_output();
	$GLOBALS['digi']['admin'] = false;
	check_vc( 2 === $GLOBALS['digi']['headers'], 'En el editor y en la administración no se decide nada.' );
	VC::reset();
	$GLOBALS['digi']['meta'][12]['_elementor_edit_mode'] = '';
	VC::flag_before_output();
	check_vc( 2 === $GLOBALS['digi']['headers'], 'Una página devuelta al editor de WordPress conserva su JSON pero ya no cuenta.' );
	$GLOBALS['digi']['meta'][12]['_elementor_edit_mode'] = 'builder';
	VC::reset();
	// Buffer de salida: sólo cuando el documento usa visitas o sesiones; un filtro del sitio lo desactiva.
	$level = ob_get_level();
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( '{"digitalisimo_visibility_enable":"yes","digitalisimo_visibility_rules":[{"rule_key":"visit_count"}]}' );
	VC::flag_before_output();
	check_vc( VC::buffered() && ob_get_level() === $level + 1 && 3 === $GLOBALS['digi']['headers'], 'Con «Número de visitas» se abre el buffer y la página no se guarda en caché.' );
	ob_end_clean();
	VC::reset();
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_buffer'] = false;
	VC::flag_before_output();
	check_vc( ! VC::buffered() && ob_get_level() === $level, 'Un filtro del sitio evita el buffer.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_buffer'] );
	VC::reset();
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( $native( 'digitalisimo_country' ) );
	VC::flag_before_output();
	check_vc( ! VC::buffered() && ob_get_level() === $level, 'Sin esas reglas no se abre ningún buffer.' );
	VC::reset();
	// Si otro motor ya ocultó el elemento, el propio no lo reabre.
	$hidden = new class() { public function get_settings() { return array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => 'show', 'digitalisimo_visibility_rules' => array( array( 'rule_key' => 'authentication', 'rule_operator' => 'is', 'rule_authentication_value' => 'authenticated' ) ) ); } public function get_id() { return 'e9'; } };
	$GLOBALS['digi']['user'] = 9;
	check_vc( true === Visibility::should_render( true, $hidden ) && false === Visibility::should_render( false, $hidden ), 'Un elemento que otro motor ocultó sigue oculto aunque las reglas propias lo mostrarían.' );
	$GLOBALS['digi']['user'] = 0;

	// Reglas del motor propio: usuario sin selección = cualquier usuario con sesión; rol guardado como lista; parámetros sin query.
	$rule = static function ( $key, $value, $operator = 'is' ) {
		return array( 'rule_key' => $key, 'rule_operator' => $operator, 'rule_' . $key . '_value' => $value );
	};
	$GLOBALS['digi']['user'] = 0;
	check_vc( false === Own::check( 'user', $rule( 'user', array() ) ) && true === Own::check( 'user', $rule( 'user', array(), 'not' ) ), 'Propio: sin usuarios elegidos, un visitante anónimo no cumple.' );
	$GLOBALS['digi']['user'] = 9;
	check_vc( true === Own::check( 'user', $rule( 'user', array() ) ) && true === Own::check( 'user', $rule( 'user', '' ) ), 'Propio: sin usuarios elegidos, cualquier usuario con sesión cumple.' );
	check_vc( true === Own::check( 'user', $rule( 'user', array( '3', '9' ) ) ) && false === Own::check( 'user', $rule( 'user', array( '3' ) ) ), 'Propio: con usuarios elegidos sólo cumplen ellos.' );
	$GLOBALS['digi']['user'] = 0;
	check_vc( false === Own::check( 'user', $rule( 'user', array( '0' ) ) ) && false === Own::check( 'user', $rule( 'user', '0' ) ), 'Propio: un visitante anónimo nunca es el usuario «0».' );
	$GLOBALS['digi']['user'] = 3;
	set_error_handler( static function ( $no, $str, $file, $line ) { throw new ErrorException( $str, 0, $no, $file, $line ); } );
	check_vc( true === Own::check( 'user', $rule( 'user', array( '' ) ) ) && true === Own::check( 'user', $rule( 'user', array( ' ', null ) ) ) && true === Own::check( 'user', $rule( 'user', array( array( 'id' => '' ) ) ) ), 'Propio: una lista sólo con blancos es «sin usuarios elegidos».' );
	check_vc( true === Own::check( 'user', $rule( 'user', array( array( 'id' => '3' ), array( 'id' => 9 ) ) ) ) && false === Own::check( 'user', $rule( 'user', array( array( 'id' => '9' ) ) ) ), 'Propio: una lista de objetos {id} restringe a esos usuarios.' );
	check_vc( false === Own::check( 'user', $rule( 'user', '9' ) ) && false === Own::check( 'user', $rule( 'user', 9 ) ) && true === Own::check( 'user', $rule( 'user', '3' ) ) && true === Own::check( 'user', $rule( 'user', ' ' ) ), 'Propio: un solo ID guardado como texto restringe a ese usuario.' );
	$GLOBALS['digi']['user'] = 9;
	$GLOBALS['digi']['roles'] = array( 'administrator' );
	check_vc( true === Own::check( 'role', $rule( 'role', 'administrator' ) ) && true === Own::check( 'role', $rule( 'role', array( 'editor', 'administrator' ) ) ) && false === Own::check( 'role', $rule( 'role', array( 'subscriber' ) ) ) && false === Own::check( 'role', $rule( 'role', 'subscriber' ) ), 'Propio: el rol acepta un valor o una lista.' );
	check_vc( true === Own::check( 'role', $rule( 'role', array( array( 'id' => 'administrator' ) ) ) ) && false === Own::check( 'role', $rule( 'role', array( array( 'id' => 'editor' ), array() ) ) ), 'Propio: una lista de objetos {id} vale como lista de roles, sin avisos PHP.' );
	$GLOBALS['digi']['user'] = 0;
	check_vc( false === Own::check( 'role', $rule( 'role', array( 'administrator' ) ) ), 'Propio: sin sesión ningún rol cumple.' );
	$_SERVER['REQUEST_URI'] = '/oferta/';
	$_GET = array();
	check_vc( false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip', 'not' ) ), 'Propio: sin parámetros en la URL el parámetro no está: «es» no se cumple y «no es» sí.' );
	$_SERVER['REQUEST_URI'] = '/oferta/?ref=vip';
	$_GET = array( 'ref' => 'vip' );
	check_vc( true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip', 'not' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=otro' ) ), 'Propio: con parámetros se compara de verdad.' );
	check_vc( true === Own::check( 'url_parameters', $rule( 'url_parameters', "x=1\r\nref=vip" ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'otro' ) ), 'Propio: varias líneas (también con retorno de carro) y «sólo nombre».' );
	$_SERVER['REQUEST_URI'] = '/oferta/?ref=v%20ip&acento=ma%C3%B1ana&eq=a%3Db';
	$_GET = array( 'ref' => 'v ip', 'acento' => 'mañana', 'eq' => 'a=b' );
	check_vc( true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=v ip' ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=v%20ip' ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'acento=mañana' ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'eq=a=b' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ), 'Propio: los valores codificados (espacios, acentos, «=») se comparan decodificados.' );
	$_SERVER['REQUEST_URI'] = '/oferta/?ref';
	$_GET = array( 'ref' => '' );
	check_vc( true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ), 'Propio: el parámetro presente sin valor cuenta como presente.' );
	$_SERVER['REQUEST_URI'] = '/oferta/?ref[]=vip';
	$_GET = array( 'ref' => array( 'vip' ) );
	check_vc( false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref' ) ), 'Propio: un parámetro de lista no es un valor, pero sí está presente.' );
	restore_error_handler();

	// Motor heredado: port literal de Element Pack, con sus particularidades (un documento migrado no cambia lo que ve cada visitante).
	$legacy = static function ( $key, $value, $operator = 'is' ) {
		return array( 'ep_condition_key' => $key, 'ep_condition_operator' => $operator, 'ep_condition_' . $key . '_value' => $value );
	};
	$GLOBALS['digi']['user'] = 9;
	check_vc( false === Legacy::check( 'user', $legacy( 'user', array() ) ) && true === Legacy::check( 'user', $legacy( 'user', array(), 'not' ) ) && false === Legacy::check( 'user', $legacy( 'user', '' ) ), 'Heredado: sin usuarios elegidos nadie cumple, como en Element Pack.' );
	check_vc( true === Legacy::check( 'user', $legacy( 'user', array( '3', '9' ) ) ) && false === Legacy::check( 'user', $legacy( 'user', array( '3' ) ) ), 'Heredado: con usuarios elegidos sólo cumplen ellos.' );
	$GLOBALS['digi']['roles'] = array( 'administrator' );
	check_vc( true === Legacy::check( 'role', $legacy( 'role', 'administrator' ) ) && false === Legacy::check( 'role', $legacy( 'role', 'subscriber' ) ), 'Heredado: el rol es un solo valor.' );
	$GLOBALS['digi']['user'] = 0;
	check_vc( false === Legacy::check( 'role', $legacy( 'role', 'administrator' ) ), 'Heredado: sin sesión ningún rol cumple.' );
	$_SERVER['REQUEST_URI'] = '/oferta/';
	$_GET = array();
	check_vc( true !== Legacy::check( 'url_parameters', $legacy( 'url_parameters', 'ref=vip' ) ) && true !== Legacy::check( 'url_parameters', $legacy( 'url_parameters', 'ref=vip', 'not' ) ), 'Heredado: sin parámetros en la URL la regla no se cumple, ni con «es» ni con «no es».' );
	// Combinación: «Mostrar si el parámetro es ref=vip» no se ve sin parámetros; «Ocultar si el parámetro no es ref=vip» sí se oculta sin parámetros.
	$_SERVER['REQUEST_URI'] = '/oferta/';
	$_GET = array();
	$settings = array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => 'show', 'digitalisimo_visibility_relation' => 'all', 'digitalisimo_visibility_rules' => array( array( 'rule_key' => 'url_parameters', 'rule_operator' => 'is', 'rule_url_parameters_value' => 'ref=vip' ) ) );
	check_vc( false === Own::should_show( $settings, 'e1' ), 'Sin parámetros en la URL, «mostrar si el parámetro es» no muestra el elemento.' );
	$settings['digitalisimo_visibility_to'] = 'hide';
	$settings['digitalisimo_visibility_rules'][0]['rule_operator'] = 'not';
	check_vc( false === Own::should_show( $settings, 'e1' ), 'Sin parámetros en la URL, «ocultar si el parámetro no es» oculta el elemento.' );
	$settings['digitalisimo_visibility_to'] = 'show';
	check_vc( true === Own::should_show( $settings, 'e1' ), 'Sin parámetros en la URL, «mostrar si el parámetro no es» muestra el elemento.' );
	$_SERVER['REQUEST_URI'] = '/oferta/?ref=vip';
	$_GET = array( 'ref' => 'vip' );
	check_vc( false === Own::should_show( $settings, 'e1' ), 'Con el parámetro presente, «mostrar si el parámetro no es» no lo muestra.' );
	echo "DIGITALÍSIMO Elements: contenido por visitante y reglas de usuario, rol y URL validados en los dos motores.\n";
}
