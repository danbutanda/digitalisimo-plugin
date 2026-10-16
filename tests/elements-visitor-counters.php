<?php
/**
 * Contadores de visitas y sesiones: las cookies se escriben (con el buffer de salida que abre Visitor_Content cuando la
 * página usa esas reglas), las sesiones se distinguen con la cookie de sesión del navegador, y las reglas propias
 * siguen leyendo las cookies del constructor anterior cuando Migración las declara por filtro.
 */
namespace Elementor { class Plugin { public static $instance; public $editor; } class Controls_Manager {} class Repeater {} }
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'YEAR_IN_SECONDS', 31536000 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['digi'] = array( 'filters' => array(), 'options' => array(), 'user' => 0, 'roles' => array() );
	function add_action() {} function add_filter() {} function do_action() {} function has_filter() { return false; }
	function apply_filters( $hook, $value, ...$args ) { if ( ! array_key_exists( $hook, $GLOBALS['digi']['filters'] ) ) { return $value; } $o = $GLOBALS['digi']['filters'][ $hook ]; return is_callable( $o ) ? $o( $value, ...$args ) : $o; }
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
	function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
	function wp_unslash( $v ) { return $v; }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function wp_generate_password( $length = 12, $special = true, $extra = false ) { return str_repeat( 'x', $length ); }
	function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
	function home_url( $p = '' ) { return 'https://www.mi-sitio.test/' . ltrim( $p, '/' ); }
	function get_option( $n, $d = false ) { return array_key_exists( $n, $GLOBALS['digi']['options'] ) ? $GLOBALS['digi']['options'][ $n ] : ( 'gmt_offset' === $n ? -6 : $d ); }
	function current_time( $t ) { return time() - 6 * HOUR_IN_SECONDS; }
	function is_user_logged_in() { return $GLOBALS['digi']['user'] > 0; }
	function get_current_user_id() { return $GLOBALS['digi']['user']; }
	function wp_get_current_user() { return (object) array( 'roles' => $GLOBALS['digi']['roles'] ); }
	function is_singular( $t = '' ) { return true; } function is_single() { return false; } function is_front_page() { return false; } function is_home() { return false; } function is_404() { return false; }
	function get_the_ID() { return 12; } function get_queried_object_id() { return 12; } function get_queried_object() { return null; } function get_post_type() { return 'page'; }
	function get_locale() { return 'es_MX'; } function do_shortcode( $c ) { return $c; } function shortcode_unautop( $c ) { return $c; }
	function check_counters( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	class WooCommerce {}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visitor.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visibility-rules.php';
	require __DIR__ . '/../digitalisimo-migration/legacy/conditions/class-legacy-rules.php';
	use Digitalisimo\Elements\Conditions\Visitor;
	use Digitalisimo\Elements\Conditions\Visibility_Rules as Own;
	use Digitalisimo\Elements\Conditions\Legacy_Rules as Legacy;

	$_SERVER['HTTP_HOST'] = 'www.mi-sitio.test';
	$host = md5( 'www.mi-sitio.test' );
	// En CLI las cabeceras nunca están enviadas: equivale a la página bufferizada.
	check_counters( ! headers_sent(), 'La prueba corre con las cabeceras abiertas, como una página bufferizada.' );

	// Visitas: sin cookie cuenta 0 y se escribe; con cookie suma una; la propia lee la heredada como semilla.
	$_COOKIE = array();
	check_counters( 0 === Visitor::visit_count( 'abc', 'digi-visit-count-' ) && '0' === ( Visitor::$written['digi-visit-count-abc'] ?? null ), 'Primera visita: 0 y se escribe la cookie.' );
	$_COOKIE = array( 'digi-visit-count-abc' => '2' );
	check_counters( 3 === Visitor::visit_count( 'abc', 'digi-visit-count-' ) && '3' === Visitor::$written['digi-visit-count-abc'], 'Cada página suma una visita.' );
	$_COOKIE = array( 'bdt-visit-count-abc' => '4' );
	check_counters( 0 === Visitor::visit_count( 'abc', 'digi-visit-count-' ), 'Sin semillas declaradas la cookie heredada no cuenta.' );
	check_counters( 5 === Visitor::visit_count( 'abc', 'digi-visit-count-', array( 'bdt-visit-count-' ) ) && '5' === Visitor::$written['digi-visit-count-abc'], 'Con la semilla heredada el visitante recurrente no vuelve a cero.' );
	check_counters( 5 === Visitor::visit_count( 'abc', 'bdt-visit-count-' ) && '5' === Visitor::$written['bdt-visit-count-abc'], 'El motor heredado usa la cookie de Element Pack.' );

	// Reglas: «hasta N visitas» del propio y del heredado, con sus cookies.
	$own    = static function ( $key, $value, $operator = 'is' ) { return array( 'rule_key' => $key, 'rule_operator' => $operator, 'rule_' . $key . '_value' => $value ); };
	$legacy = static function ( $key, $value, $operator = 'is' ) { return array( 'ep_condition_key' => $key, 'ep_condition_operator' => $operator, 'ep_condition_' . $key . '_value' => $value ); };
	$_COOKIE = array( 'bdt-visit-count-abc' => '1' );
	check_counters( true === Legacy::check( 'visit_count', $legacy( 'visit_count', '3' ), 'abc' ) && false === Legacy::check( 'visit_count', $legacy( 'visit_count', '2' ), 'abc' ), 'Heredado: «hasta 3 visitas» se cumple en la segunda visita y «hasta 2» ya no.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visit_count_fallbacks'] = array( 'bdt-visit-count-' );
	$_COOKIE = array( 'bdt-visit-count-abc' => '1' );
	check_counters( true === Own::check( 'visit_count', $own( 'visit_count', '3' ), 'abc' ) && false === Own::check( 'visit_count', $own( 'visit_count', '2' ), 'abc' ), 'Propio tras convertir: lee la cookie heredada y cuenta igual.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_visit_count_fallbacks'] );

	// Sesiones: la cookie de sesión del navegador sustituye a session_id(); sin cookie previa se crea y devuelve false.
	$_COOKIE = array();
	Visitor::$written = array();
	$session = Visitor::session_id();
	check_counters( 20 === strlen( $session ) && $session === Visitor::$written['digi_session'], 'La sesión del navegador se crea la primera vez.' );
	check_counters( false === Visitor::session_count( 'abc', '_digi_' ) && json_encode( array( 'user_id' => $session, 'count' => 1 ) ) === Visitor::$written[ '_digi_' . $host . 'abc' ], 'Primera sesión: se guarda con 1 y no se compara (como el origen).' );
	$_COOKIE = array( '_digi_' . $host . 'abc' => json_encode( array( 'user_id' => $session, 'count' => 1 ) ) );
	Visitor::$written = array();
	check_counters( 1 === Visitor::session_count( 'abc', '_digi_' ) && array() === Visitor::$written, 'Misma sesión: el contador no cambia ni se reescribe.' );
	$_COOKIE = array( '_digi_' . $host . 'abc' => json_encode( array( 'user_id' => 'otra-sesion', 'count' => 1 ) ) );
	check_counters( 2 === Visitor::session_count( 'abc', '_digi_' ) && json_encode( array( 'user_id' => $session, 'count' => 2 ) ) === Visitor::$written[ '_digi_' . $host . 'abc' ], 'Otra sesión: cuenta una más y se guarda.' );
	$_COOKIE = array( '_ep_' . $host . 'abc' => json_encode( array( 'user_id' => 'php-session', 'count' => 3 ) ) );
	check_counters( 4 === Visitor::session_count( 'abc', '_digi_', '_ep_' ) && isset( Visitor::$written[ '_digi_' . $host . 'abc' ] ), 'La cookie heredada sirve de semilla para la propia.' );
	check_counters( 4 === Visitor::session_count( 'abc', '_ep_' ) && isset( Visitor::$written[ '_ep_' . $host . 'abc' ] ), 'El motor heredado escribe la cookie de Element Pack.' );
	$_COOKIE = array( '_ep_' . $host . 'abc' => json_encode( array( 'user_id' => $session, 'count' => 2 ) ) );
	check_counters( true === Legacy::check( 'session_count', $legacy( 'session_count', '2' ), 'abc' ) && false === Legacy::check( 'session_count', $legacy( 'session_count', '1' ), 'abc' ), 'Heredado: «hasta 2 sesiones» se cumple con 2 y «hasta 1» no.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_session_count_fallback'] = '_ep_';
	check_counters( true === Own::check( 'session_count', $own( 'session_count', '2' ), 'abc' ) && false === Own::check( 'session_count', $own( 'session_count', '1' ), 'abc' ), 'Propio tras convertir: lee la cookie heredada de sesiones.' );
	unset( $GLOBALS['digi']['filters']['digitalisimo_elements_session_count_fallback'] );

	// Zona horaria: la cookie propia manda; las heredadas sólo si Migración las declara.
	$_COOKIE = array( 'ep_visitor_tz' => 'America/Mexico_City' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visibility_country_use_timezone'] = true;
	$GLOBALS['digi']['filters']['digitalisimo_elements_country_remote_lookup'] = false;
	check_counters( '' === Visitor::country(), 'Sin declarar la cookie heredada no se lee.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_timezone_cookies'] = array( 'ep_visitor_tz' );
	check_counters( 'MX' === Visitor::country(), 'La cookie de zona horaria heredada resuelve el país.' );
	$_COOKIE = array( 'ep_visitor_tz' => 'America/Mexico_City', 'digi_visitor_tz' => 'Europe/Madrid' );
	check_counters( 'ES' === Visitor::country(), 'La cookie propia manda sobre la heredada.' );
	echo "DIGITALÍSIMO Elements: contadores de visitas y sesiones con cookies reales y semillas heredadas validados.\n";
}
