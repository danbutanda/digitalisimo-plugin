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
	function apply_filters( $hook, $value ) { return array_key_exists( $hook, $GLOBALS['digi']['filters'] ) ? $GLOBALS['digi']['filters'][ $hook ] : $value; }
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
	function is_singular( $t = '' ) { return true; }
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
	// Cada motor declara su interruptor por filtro sólo mientras está activo en el sitio.
	check_vc( array() === VC::SWITCH_KEYS && array() === VC::switches(), 'El núcleo no conoce interruptores por sí mismo.' );
	check_vc( array( 'digitalisimo_visibility_enable' ) === Visibility::visitor_content_switch( array() ), 'El motor propio anuncia su interruptor cuando está activo.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visibility_enabled'] = false;
	check_vc( array( 'x' ) === Visibility::visitor_content_switch( array( 'x' ) ), 'Con el motor propio apagado en el sitio, su interruptor no cuenta: la página se pinta igual para todos.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visibility_enabled'] );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_switches'] = Visibility::visitor_content_switch( array() );

	// Caché de elementos: los widgets que incrustan plantillas y las diapositivas de plantilla son dinámicos.
	foreach ( VC::TEMPLATE_WIDGETS as $type ) {
		check_vc( true === VC::is_dynamic_content( false, array( 'widgetType' => $type, 'settings' => array() ) ), $type . ' no debe guardarse en la caché de elementos.' );
	}
	check_vc( true === VC::is_dynamic_content( false, array( 'widgetType' => 'slides', 'settings' => array( 'slides' => array( array( 'digitalisimo_template_id' => '7' ) ) ) ) ), 'Las diapositivas de plantilla son dinámicas.' );
	check_vc( true === VC::is_dynamic_content( false, array( 'widgetType' => 'otro-slider', 'settings' => array( 'slides' => array( array( 'digitalisimo_template_id' => '7' ) ) ) ) ) && false === VC::is_dynamic_content( false, array( 'widgetType' => 'otro-slider', 'settings' => array( 'slides' => array( array( 'heading' => 'x' ) ) ) ) ), 'Un widget registrado con otro nombre sobre el de diapositivas también es dinámico sólo si usa plantillas.' );
	check_vc( false === VC::is_dynamic_content( false, array( 'widgetType' => 'slides', 'settings' => array( 'slides' => array( array( 'heading' => 'x' ) ) ) ) ) && false === VC::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array() ) ), 'Lo demás conserva la decisión de Elementor.' );
	check_vc( true === VC::is_dynamic_content( true, array( 'widgetType' => 'heading', 'settings' => array() ) ), 'Lo que ya era dinámico sigue siéndolo.' );

	// Detección de elementos condicionados en los tres motores.
	check_vc( VC::is_conditioned( array( 'e_display_conditions' => array( '[[{"condition":"login_status"}]]' ) ) ) && VC::is_conditioned( array( 'digitalisimo_visibility_enable' => 'yes' ) ), 'Los dos motores del núcleo cuentan como contenido por visitante.' );
	check_vc( ! VC::is_conditioned( array( 'ep_display_conditions_enable' => 'yes' ) ), 'El núcleo no conoce los interruptores heredados por sí mismo.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_switches'] = array( 'digitalisimo_visibility_enable', 'ep_display_conditions_enable' );
	check_vc( VC::is_conditioned( array( 'ep_display_conditions_enable' => 'yes' ) ), 'Un plugin de compatibilidad añade los suyos por filtro.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_switches'] = array();
	check_vc( ! VC::is_conditioned( array( 'digitalisimo_visibility_enable' => 'yes' ) ) && ! VC::is_conditioned( array( 'ep_display_conditions_enable' => 'yes' ) ), 'Un interruptor de un motor apagado no marca la página.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_content_switches'] = array( 'digitalisimo_visibility_enable' );
	check_vc( ! VC::is_conditioned( array( 'e_display_conditions' => array( '[]' ) ) ) && ! VC::is_conditioned( array( 'digitalisimo_visibility_enable' => '' ) ) && ! VC::is_conditioned( array( 'title' => 'Hola' ) ), 'Sin condiciones no se marca nada.' );
	check_vc( VC::is_conditioned( array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => array( array( '$$type' => 'condition-group', 'value' => array( 'x' ) ) ) ) ) ) && ! VC::is_conditioned( array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => null ) ) ), 'Las condiciones de los elementos atómicos cuentan como contenido por visitante.' );

	// Marcadores en el JSON guardado del documento (sin decodificarlo).
	$doc = static function ( $settings ) { return '[{"id":"a1","elType":"container","settings":{},"elements":[{"id":"b2","elType":"widget","widgetType":"heading","settings":' . $settings . '}]}]'; };
	check_vc( VC::has_conditioned_markers( $doc( '{"title":"Hola","e_display_conditions":["[[{\\"id\\":\\"c1\\",\\"condition\\":\\"login_status\\"}]]"]}' ) ) && VC::has_conditioned_markers( $doc( '{"e_display_conditions":"[[{\\"id\\":\\"c1\\"}]]"}' ) ) && VC::has_conditioned_markers( $doc( '{"e_display_conditions": "[{\\"id\\":\\"c1\\"}]"}' ) ), 'Las condiciones nativas guardadas se detectan en el JSON.' );
	check_vc( ! VC::has_conditioned_markers( $doc( '{"e_display_conditions":["[]"]}' ) ) && ! VC::has_conditioned_markers( $doc( '{"e_display_conditions":"[[]]"}' ) ) && ! VC::has_conditioned_markers( $doc( '{"e_display_conditions":""}' ) ) && ! VC::has_conditioned_markers( $doc( '{"title":"e_display_conditions"}' ) ), 'Condiciones vacías o el texto suelto no cuentan.' );
	check_vc( VC::has_conditioned_markers( $doc( '{"display-conditions":{"$$type":"display-conditions","value":[{"$$type":"condition-group","value":[{"x":1}]}]}}' ) ) && ! VC::has_conditioned_markers( $doc( '{"display-conditions":{"$$type":"display-conditions","value":[]}}' ) ) && ! VC::has_conditioned_markers( $doc( '{"display-conditions":{"$$type":"display-conditions","value":null}}' ) ), 'Las condiciones atómicas se detectan sólo con grupos.' );
	check_vc( VC::has_conditioned_markers( $doc( '{"digitalisimo_visibility_enable":"yes","digitalisimo_visibility_rules":[]}' ) ) && ! VC::has_conditioned_markers( $doc( '{"digitalisimo_visibility_enable":""}' ) ) && ! VC::has_conditioned_markers( $doc( '{"ep_display_conditions_enable":"yes"}' ) ) && ! VC::has_conditioned_markers( '' ) && ! VC::has_conditioned_markers( null ), 'Los interruptores vigentes se detectan; los de motores ausentes y el vacío, no.' );
	check_vc( array( 12 ) === VC::documents_to_scan(), 'Sin Theme Builder se revisa el documento de la URL.' );

	// Antes de la salida: el documento de la URL sin condiciones no toca la caché de página.
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( '{"title":"Hola"}' );
	VC::flag_before_output();
	check_vc( ! defined( 'DONOTCACHEPAGE' ) && 0 === $GLOBALS['digi']['headers'], 'Un documento sin condiciones no pide quedar fuera de la caché.' );
	VC::reset();

	// Caché de página: al pintar un elemento condicionado se avisa una sola vez, fuera del editor y de la administración.
	$element = new class() { public $settings = array(); public function get_settings() { return $this->settings; } };
	VC::before_render( $element );
	check_vc( ! defined( 'DONOTCACHEPAGE' ) && 0 === $GLOBALS['digi']['headers'], 'Un elemento sin condiciones no toca la caché de página.' );
	$element->settings = array( 'digitalisimo_visibility_enable' => 'yes' );
	VC::before_render( $element );
	VC::before_render( $element );
	check_vc( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE && defined( 'LSCACHE_NO_CACHE' ) && 1 === $GLOBALS['digi']['headers'] && in_array( 'litespeed_control_set_nocache', $GLOBALS['digi']['actions'], true ), 'La página condicionada pide no guardarse en caché, una sola vez.' );
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
	// Antes de la salida: con condiciones en el documento, las cabeceras salen en template_redirect (a tiempo para CDN y proxies).
	VC::reset();
	$GLOBALS['digi']['meta'][12]['_elementor_data'] = $doc( '{"e_display_conditions":["[[{\\"id\\":\\"c1\\",\\"condition\\":\\"login_status\\"}]]"]}' );
	VC::flag_before_output();
	check_vc( 2 === $GLOBALS['digi']['headers'], 'Un documento con condiciones pide no guardarse antes de la salida.' );
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
	check_vc( false === Own::check( 'user', $rule( 'user', '9' ) ) && false === Own::check( 'user', $rule( 'user', 9 ) ) && true === Own::check( 'user', $rule( 'user', '3' ) ) && true === Own::check( 'user', $rule( 'user', ' ' ) ), 'Propio: un solo ID guardado como texto restringe a ese usuario.' );
	$GLOBALS['digi']['user'] = 9;
	$GLOBALS['digi']['roles'] = array( 'administrator' );
	check_vc( true === Own::check( 'role', $rule( 'role', 'administrator' ) ) && true === Own::check( 'role', $rule( 'role', array( 'editor', 'administrator' ) ) ) && false === Own::check( 'role', $rule( 'role', array( 'subscriber' ) ) ) && false === Own::check( 'role', $rule( 'role', 'subscriber' ) ), 'Propio: el rol acepta un valor o una lista.' );
	$GLOBALS['digi']['user'] = 0;
	check_vc( false === Own::check( 'role', $rule( 'role', array( 'administrator' ) ) ), 'Propio: sin sesión ningún rol cumple.' );
	$_SERVER['REQUEST_URI'] = '/oferta/';
	$_GET = array();
	check_vc( false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ) && true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip', 'not' ) ), 'Propio: sin parámetros en la URL el parámetro no está: «es» no se cumple y «no es» sí.' );
	$_SERVER['REQUEST_URI'] = '/oferta/?ref=vip';
	$_GET = array( 'ref' => 'vip' );
	check_vc( true === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=vip', 'not' ) ) && false === Own::check( 'url_parameters', $rule( 'url_parameters', 'ref=otro' ) ), 'Propio: con parámetros se compara de verdad.' );

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
