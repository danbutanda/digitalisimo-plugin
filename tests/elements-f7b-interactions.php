<?php
/**
 * F7b, frente B: interacciones PRO (`actions/class-interactions-state.php` y la línea de
 * `modules/interactions/module.php`; D6, C2-21 y V.3 de la especificación).
 *
 * - Estados `pending`/`clear`/`ack` en la opción automática, leídos sólo de `wp_load_alloptions()`; sin la opción, o
 *   sin carga automática, apagadas.
 * - Inventario por el recorrido por lotes: disparadores y efectos que sólo reproduce PRO, en cadena JSON o arreglo y a
 *   cualquier profundidad; sin documentos, `clear`; `clear` y `ack` no vuelven a `pending`.
 * - Aviso persistente sólo en las pantallas de DIGITALÍSIMO, con `manage_options`, la lista con enlaces y el
 *   formulario de `admin-post.php` con nonce; la acción exige POST, capability y nonce.
 * - Movimiento reducido: script en línea `after` de `motion-js`, sólo con las interacciones encendidas y en el front.
 * - El derivado: una sola línea cambia (la condición de licencia por `Interactions_State::enabled()`), el resto del
 *   archivo es el de 4.3.0.168 byte a byte, y el constructor real registra los ganchos de PRO sólo con el estado
 *   encendido, sin consultar la licencia.
 */
namespace Elementor\Modules\AtomicWidgets {
	class Module { const EXPERIMENT_NAME = 'e_atomic_elements'; }
}
namespace Elementor\Modules\Interactions {
	class Module { const MODULE_NAME = 'e-interactions'; const HANDLE_MOTION_JS = 'motion-js'; }
	class Presets { public function defaults() { return array(); } }
}
namespace Elementor {
	class Utils { public static function is_script_debug() { return false; } public static function is_elementor_tests() { return false; } }
}
namespace ElementorPro {
	class Plugin {
		public static $atomic = true;
		public static function elementor() {
			return (object) array( 'experiments' => new class() { public function is_feature_active( $name ) { return Plugin::$atomic && 'e_atomic_elements' === $name; } } );
		}
	}
}
namespace ElementorPro\Base {
	class Module_Base { public function __construct() {} }
}
namespace ElementorPro\Core {
	class Utils { public static function get_class_constant( $class, $name, $default ) { return defined( $class . '::' . $name ) ? constant( $class . '::' . $name ) : $default; } }
}
namespace ElementorPro\License {
	class Admin { public static function get_license_key() { throw new \RuntimeException( 'La licencia ya no se consulta.' ); } }
	class API {
		const BC_VALIDATION_CALLBACK = 'x';
		public static function is_license_active() { throw new \RuntimeException( 'La licencia ya no se consulta.' ); }
		public static function is_licence_has_feature( $f, $c ) { throw new \RuntimeException( 'La licencia ya no se consulta.' ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );

	$GLOBALS['checks'] = 0;
	function check_ix( $ok, $message ) { $GLOBALS['checks']++; if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	$GLOBALS['wp_filters'] = array();
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][] = array( $callback, $args, $priority ); return true; }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
	function do_action( $hook, ...$args ) { foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
	$GLOBALS['admin'] = false;
	function is_admin() { return $GLOBALS['admin']; }
	// Opciones: valor y carga automática; `wp_load_alloptions()` sólo devuelve las automáticas.
	$GLOBALS['options'] = array();
	$GLOBALS['queries'] = 0;
	function wp_load_alloptions() { $out = array(); foreach ( $GLOBALS['options'] as $k => $o ) { if ( $o[1] ) { $out[ $k ] = $o[0]; } } return $out; }
	function get_option( $name, $default = false ) { if ( ! isset( $GLOBALS['options'][ $name ] ) ) { return $default; } if ( ! $GLOBALS['options'][ $name ][1] ) { $GLOBALS['queries']++; } return $GLOBALS['options'][ $name ][0]; }
	function add_option( $name, $value, $deprecated = '', $autoload = null ) { if ( isset( $GLOBALS['options'][ $name ] ) ) { return false; } $GLOBALS['options'][ $name ] = array( $value, true === $autoload || 'yes' === $autoload ); return true; }
	function update_option( $name, $value, $autoload = null ) { $auto = isset( $GLOBALS['options'][ $name ] ) ? $GLOBALS['options'][ $name ][1] : false; if ( null !== $autoload ) { $auto = (bool) $autoload; } $changed = ! isset( $GLOBALS['options'][ $name ] ) || $GLOBALS['options'][ $name ][0] !== $value; $GLOBALS['options'][ $name ] = array( $value, $auto ); return $changed; }
	function wp_set_option_autoload( $name, $autoload ) { if ( isset( $GLOBALS['options'][ $name ] ) ) { $GLOBALS['options'][ $name ][1] = (bool) $autoload; } return true; }
	$GLOBALS['caps'] = array( 'manage_options' => true, 'edit_post' => true );
	function current_user_can( $cap, ...$args ) { return ! empty( $GLOBALS['caps'][ $cap ] ); }
	function __( $text, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return $text; }
	function _n( $one, $many, $n, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return 1 === (int) $n ? $one : $many; }
	function esc_html__( $text, $domain = 'default' ) { return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function wp_unslash( $value ) { return $value; }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
	function add_query_arg( ...$a ) { $args = is_array( $a[0] ) ? $a[0] : array( $a[0] => $a[1] ); $url = is_array( $a[0] ) ? $a[1] : $a[2]; return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
	function get_current_blog_id() { return 3; }
	function wp_create_nonce( $action ) { return substr( md5( 'n|' . $action ), 0, 10 ); }
	function wp_verify_nonce( $nonce, $action ) { return $nonce === wp_create_nonce( $action ) ? 1 : false; }
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '">'; if ( $echo ) { echo $f; } return $f; }
	$GLOBALS['titles'] = array( 11 => 'Portada <b>', 12 => '', 13 => 'Contacto' );
	function get_the_title( $id ) { return $GLOBALS['titles'][ $id ] ?? 'Entrada ' . $id; }
	$GLOBALS['registered'] = array();
	function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['registered'][ $handle ] ); }
	function wp_add_inline_script( $handle, $code, $position = 'after' ) { $GLOBALS['inline'][ $handle ][ $position ][] = $code; return true; }
	function wp_get_referer() { return $GLOBALS['referer'] ?? false; }
	function wp_safe_redirect( $url, $status = 302, $by = '' ) { throw new \LengthException( $status . ' ' . $url ); }
	function wp_die( $message = '', $title = '', $args = array() ) { throw new \DomainException( (string) ( $args['response'] ?? 500 ) ); }

	require $root . '/modules/digitalisimo-f7b/actions/class-interactions-state.php';
	use Digitalisimo\Elements\F7b\Actions\Interactions_State as State;

	// -------------------------------------------------------------------------------------------------------------
	// 1. Estados.
	check_ix( 'pending' === State::state() && ! State::enabled(), 'Sin la opción: en pausa.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'clear', false );
	check_ix( ! State::enabled(), 'Sin carga automática no se lee (ninguna consulta en el front): en pausa.' );
	foreach ( array( 'clear' => true, 'ack' => true, 'pending' => false, 'otra' => false, '' => false ) as $value => $expected ) {
		$GLOBALS['options'][ State::OPTION ] = array( $value, true );
		check_ix( $expected === State::enabled(), 'Estado «' . $value . '»: ' . ( $expected ? 'encendidas' : 'apagadas' ) );
	}
	check_ix( 0 === $GLOBALS['queries'], 'enabled() no consulta la base de datos.' );
	unset( $GLOBALS['options'][ State::OPTION ] );
	check_ix( ! State::set_state( 'abierto' ) && ! isset( $GLOBALS['options'][ State::OPTION ] ), 'Un estado fuera de la lista no se guarda.' );
	State::admin_init();
	check_ix( array( 'pending', true ) === $GLOBALS['options'][ State::OPTION ], 'admin_init crea la opción en pausa y con carga automática.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'clear', false );
	State::admin_init();
	check_ix( array( 'clear', true ) === $GLOBALS['options'][ State::OPTION ], 'Si existía sin carga automática, se la pone sin cambiar el valor.' );
	unset( $GLOBALS['options'][ State::OPTION ] );

	// -------------------------------------------------------------------------------------------------------------
	// 2. Qué cuenta como interacción de PRO.
	$item = function ( $trigger, $effect = 'fade', $wrapped = true ) {
		if ( ! $wrapped ) {
			return array( 'trigger' => $trigger, 'animation' => array( 'effect' => $effect ) );
		}
		return array(
			'$$type' => 'interaction-item',
			'value'  => array(
				'interaction_id' => array( '$$type' => 'string', 'value' => '10-a-1' ),
				'trigger'        => array( '$$type' => 'string', 'value' => $trigger ),
				'animation'      => array( '$$type' => 'animation-preset-props', 'value' => array( 'effect' => array( '$$type' => 'string', 'value' => $effect ) ) ),
			),
		);
	};
	$json = function ( array $items ) { return json_encode( array( 'items' => $items, 'version' => 1 ) ); };
	foreach ( array( 'hover', 'click', 'scrollOn', 'scrollOut' ) as $trigger ) {
		check_ix( State::interactions_use_pro( $json( array( $item( $trigger ) ) ) ), $trigger . ' sólo lo reproduce PRO.' );
		check_ix( State::interactions_use_pro( array( 'items' => array( $item( $trigger, 'fade', false ) ) ) ), $trigger . ' también sin forma de propiedad.' );
	}
	foreach ( array( 'load', 'scrollIn' ) as $trigger ) {
		check_ix( ! State::interactions_use_pro( $json( array( $item( $trigger ) ) ) ), $trigger . ' ya lo reproduce el núcleo.' );
	}
	check_ix( State::interactions_use_pro( $json( array( $item( 'load', 'custom' ) ) ) ), 'El efecto personalizado es de PRO.' );
	check_ix( ! State::interactions_use_pro( '{roto' ) && ! State::interactions_use_pro( array() ) && ! State::interactions_use_pro( 7 ), 'Un valor ilegible no cuenta.' );
	$read = file_get_contents( $root . '/elementor/modules/interactions/presets.php' );
	check_ix( false !== strpos( $read, "const ADDITIONAL_TRIGGERS = [ 'scrollOut', 'scrollOn', 'hover', 'click' ];" ) && false !== strpos( $read, "const ADDITIONAL_EFFECTS = [ 'custom' ];" ), 'Las listas son las del núcleo (presets.php).' );
	check_ix( State::PRO_TRIGGERS === array( 'scrollOut', 'scrollOn', 'hover', 'click' ) && State::PRO_EFFECTS === array( 'custom' ), 'Mismas listas en Interactions_State.' );
	$tree = array( array( 'id' => 'a', 'elements' => array( array( 'id' => 'b', 'elements' => array( array( 'id' => 'c', 'interactions' => $json( array( $item( 'load' ), $item( 'hover' ) ) ) ) ) ) ) ) );
	check_ix( State::uses_pro( $tree ) && ! State::uses_pro( array( array( 'id' => 'a', 'interactions' => $json( array( $item( 'scrollIn' ) ) ) ) ) ), 'A cualquier profundidad.' );
	$parser = file_get_contents( $root . '/elementor/modules/interactions/cache/elements-interactions.php' );
	check_ix( false !== strpos( $parser, "\$element['interactions']" ) && false !== strpos( $parser, "\$interactions_value['items']" ), 'El núcleo guarda las interacciones en interactions → items.' );

	// -------------------------------------------------------------------------------------------------------------
	// 3. Inventario por lotes.
	State::init();
	check_ix( array( 'digitalisimo_elements_f7b_scan_document', 'digitalisimo_elements_f7b_scan_done' ) === array_keys( $GLOBALS['wp_filters'] ), 'En el front con el estado en pausa sólo engancha el inventario (cron).' );
	do_action( 'digitalisimo_elements_f7b_scan_done' );
	check_ix( 'clear' === State::state() && array( 'docs' => array(), 'total' => 0, 'done' => true, 'running' => array() ) === State::inventory(), 'Un sitio sin documentos pasa a clear en el primer lote.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'pending', true );
	foreach ( array( 13 => $tree, 11 => $tree, 20 => array(), 12 => $tree ) as $id => $elements ) {
		do_action( 'digitalisimo_elements_f7b_scan_document', $id, $elements );
	}
	do_action( 'digitalisimo_elements_f7b_scan_document', 12, array() );
	do_action( 'digitalisimo_elements_f7b_scan_document', 0, $tree );
	do_action( 'digitalisimo_elements_f7b_scan_done' );
	$inv = State::inventory();
	check_ix( 'pending' === State::state() && array( 11, 13 ) === $inv['docs'] && 2 === $inv['total'] && $inv['done'] && array() === $inv['running'], 'Con documentos, sigue en pausa con la lista (un documento que ya no las tiene sale).' );
	check_ix( false === $GLOBALS['options'][ State::INVENTORY ][1], 'La lista no tiene carga automática.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'ack', true );
	do_action( 'digitalisimo_elements_f7b_scan_document', 30, $tree );
	do_action( 'digitalisimo_elements_f7b_scan_done' );
	check_ix( 'ack' === State::state() && array( 30 ) === State::inventory()['docs'], 'ack no vuelve a pending con interacciones nuevas.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'clear', true );
	do_action( 'digitalisimo_elements_f7b_scan_document', 31, $tree );
	do_action( 'digitalisimo_elements_f7b_scan_done' );
	check_ix( 'clear' === State::state(), 'clear tampoco.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'pending', true );
	for ( $i = 100; $i < 330; $i++ ) {
		State::scan_document( $i, $tree );
	}
	State::scan_done();
	$inv = State::inventory();
	check_ix( State::MAX_LIST === count( $inv['docs'] ) && 230 === $inv['total'], 'La lista guarda 200 documentos y cuenta todos.' );

	// -------------------------------------------------------------------------------------------------------------
	// 4. Aviso.
	$notice = function () { ob_start(); State::notice(); return ob_get_clean(); };
	$GLOBALS['options'][ State::INVENTORY ] = array( array( 'docs' => array( 11, 12, 13 ), 'total' => 3, 'done' => true, 'running' => array() ), false );
	$_GET = array( 'page' => 'edit.php' );
	check_ix( '' === $notice(), 'Fuera de las pantallas de DIGITALÍSIMO no hay aviso.' );
	$_GET = array( 'page' => 'digitalisimo-elements-front' );
	$html = $notice();
	check_ix( false !== strpos( $html, 'class="notice notice-warning digitalisimo-interactions-notice"' ) && false !== strpos( $html, 'button button-primary' ), 'Aviso con los componentes de digitalisimo-ui (.wrap .notice y botones).' );
	check_ix( false !== strpos( $html, '3 documentos tienen interacciones' ) && false !== strpos( $html, 'Reproducir las interacciones guardadas' ), 'Texto del aviso y del botón.' );
	check_ix( false !== strpos( $html, '<a href="https://example.test/wp-admin/post.php?post=11&amp;action=elementor">Portada &lt;b&gt;</a>' ) && false !== strpos( $html, '>Documento 12</a>' ), 'Lista con enlace al editor de cada documento y los títulos escapados.' );
	check_ix( false !== strpos( $html, '<form method="post" action="https://example.test/wp-admin/admin-post.php">' ) && false !== strpos( $html, 'name="action" value="' . State::ACTION . '"' ) && false !== strpos( $html, 'value="' . wp_create_nonce( State::nonce_action() ) . '"' ), 'Formulario a admin-post.php con su acción y nonce.' );
	check_ix( State::ACTION . '|3' === State::nonce_action(), 'El nonce es por sitio.' );
	check_ix( false === stripos( strip_tags( $html ), 'elementor' ) && false === stripos( strip_tags( $html ), 'bricks' ), 'Sin nombres de proveedores en el texto visible.' );
	$GLOBALS['caps']['edit_post'] = false;
	check_ix( false === strpos( $notice(), '<a href' ), 'Sin edit_post, el título sin enlace.' );
	$GLOBALS['caps']['edit_post'] = true;
	$GLOBALS['caps']['manage_options'] = false;
	check_ix( '' === $notice(), 'Sin manage_options no hay aviso.' );
	$GLOBALS['caps']['manage_options'] = true;
	$GLOBALS['options'][ State::INVENTORY ][0]['done'] = false;
	check_ix( '' === $notice(), 'Mientras el inventario no termina, no hay aviso.' );
	$GLOBALS['options'][ State::INVENTORY ][0] = array( 'docs' => range( 1, 25 ), 'total' => 40, 'done' => true, 'running' => array() );
	$html = $notice();
	check_ix( State::SHOWN === substr_count( $html, '<li>' ) && false !== strpos( $html, 'Y 20 más.' ) && false !== strpos( $html, '40 documentos' ), 'Como mucho 20 en la lista y «Y N más».' );
	$GLOBALS['options'][ State::OPTION ] = array( 'clear', true );
	check_ix( '' === $notice(), 'Con clear no hay aviso.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'pending', true );
	check_ix( false !== strpos( $notice(), 'notice-warning' ) && false === strpos( $notice(), 'is-dismissible' ), 'El aviso de pausa es persistente: no se descarta y vuelve en cada pantalla.' );

	// Acción.
	$nonce = wp_create_nonce( State::nonce_action() );
	check_ix( 'method' === State::process_play( array( '_wpnonce' => $nonce ), 'GET' ), 'Sólo POST.' );
	$GLOBALS['caps']['manage_options'] = false;
	check_ix( 'forbidden' === State::process_play( array( '_wpnonce' => $nonce ), 'POST' ), 'Sólo con manage_options.' );
	$GLOBALS['caps']['manage_options'] = true;
	check_ix( 'nonce' === State::process_play( array( '_wpnonce' => 'otro' ), 'POST' ) && 'nonce' === State::process_play( array(), 'POST' ), 'Con su nonce.' );
	check_ix( 'pending' === State::state(), 'Nada cambia sin pasar las tres comprobaciones.' );
	check_ix( 'played' === State::process_play( array( '_wpnonce' => $nonce ), 'post' ) && 'ack' === State::state() && State::enabled(), '«Reproducir las interacciones guardadas» pasa a ack.' );
	$_GET = array( 'page' => 'digitalisimo-elements-front', State::NOTICE => 'played' );
	check_ix( false !== strpos( $notice(), 'notice-success' ), 'Tras la acción, aviso de éxito.' );
	$GLOBALS['options'][ State::OPTION ] = array( 'pending', true );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array( '_wpnonce' => $nonce );
	$GLOBALS['referer']        = 'https://example.test/wp-admin/admin.php?page=digitalisimo-elements-kits';
	try {
		State::handle_play();
		check_ix( false, 'handle_play() redirige.' );
	} catch ( \LengthException $e ) {
		check_ix( '303 https://example.test/wp-admin/admin.php?page=digitalisimo-elements-kits&digitalisimo_interactions=played' === $e->getMessage(), 'Vuelve a la pantalla de origen con el resultado: ' . $e->getMessage() );
	}
	$GLOBALS['referer'] = 'https://example.test/wp-admin/edit.php';
	try {
		State::handle_play();
	} catch ( \LengthException $e ) {
		check_ix( false !== strpos( $e->getMessage(), 'admin.php?page=digitalisimo-elements-front&' ), 'Desde otra pantalla, a «Rendimiento».' );
	}
	$_POST = array( '_wpnonce' => 'malo' );
	try {
		State::handle_play();
	} catch ( \DomainException $e ) {
		check_ix( '400' === $e->getMessage(), 'Nonce caducado: error 400 sin cambiar nada.' );
	}

	// -------------------------------------------------------------------------------------------------------------
	// 5. Administración y movimiento reducido.
	State::reset();
	$GLOBALS['wp_filters'] = array();
	$GLOBALS['admin']      = true;
	State::init();
	foreach ( array( 'admin_init', 'admin_notices', 'admin_post_' . State::ACTION ) as $hook ) {
		check_ix( isset( $GLOBALS['wp_filters'][ $hook ] ), 'En la administración: ' . $hook );
	}
	check_ix( ! isset( $GLOBALS['wp_filters']['admin_post_nopriv_' . State::ACTION] ) && ! isset( $GLOBALS['wp_filters']['network_admin_notices'] ), 'Sin acción anónima ni aviso de red (es un estado por sitio).' );
	check_ix( ! isset( $GLOBALS['wp_filters']['elementor/frontend/after_register_scripts'] ), 'En la administración, nada del front.' );
	State::reset();
	$GLOBALS['wp_filters'] = array();
	$GLOBALS['admin']      = false;
	$GLOBALS['options'][ State::OPTION ] = array( 'clear', true );
	State::init();
	$entry = $GLOBALS['wp_filters']['elementor/frontend/after_register_scripts'][0] ?? null;
	check_ix( $entry && 30 === $entry[2], 'Encendidas: el script de movimiento reducido tras el registro del núcleo (10) y de PRO (20).' );
	do_action( 'elementor/frontend/after_register_scripts' );
	check_ix( empty( $GLOBALS['inline'] ), 'Sin motion-js registrado (v3), nada.' );
	$GLOBALS['registered']['motion-js'] = true;
	do_action( 'elementor/frontend/after_register_scripts' );
	$code = $GLOBALS['inline']['motion-js']['after'][0] ?? '';
	check_ix( "if(matchMedia('(prefers-reduced-motion: reduce)').matches&&window.Motion&&Motion.MotionGlobalConfig)Motion.MotionGlobalConfig.skipAnimations=true" === $code && strlen( $code ) <= 150, 'Script en línea after de motion-js (≈ 120 B): ' . strlen( $code ) . ' B.' );
	$motion = (string) file_get_contents( $root . '/elementor/assets/lib/motion/motion.js' );
	check_ix( false !== strpos( $motion, 'MotionGlobalConfig' ) && false !== strpos( $motion, 'skipAnimations' ), 'motion.js expone MotionGlobalConfig.skipAnimations.' );
	$core = (string) file_get_contents( $root . '/elementor/modules/interactions/module.php' );
	check_ix( false !== strpos( $core, "const HANDLE_MOTION_JS            = 'motion-js';" ), 'El handle del núcleo es motion-js.' );
	check_ix( isset( $GLOBALS['domains']['pro-elements'] ) && 1 === count( $GLOBALS['domains'] ), 'Textos con el dominio de los módulos propios.' );

	// -------------------------------------------------------------------------------------------------------------
	// 6. La línea del derivado.
	$file  = $root . '/modules/interactions/module.php';
	$lines = file( $file );
	$line  = 41;
	$now   = $lines[ $line - 1 ];
	check_ix( "\t\tif ( ! \$this->is_experiment_active() || ! ( class_exists( '\\Digitalisimo\\Elements\\F7b\\Actions\\Interactions_State' ) && \\Digitalisimo\\Elements\\F7b\\Actions\\Interactions_State::enabled() ) ) { // DIGITALÍSIMO (F7b): la licencia se sustituye por el estado del sitio, que sólo se enciende tras el inventario de las interacciones guardadas (V.3).\n" === $now, 'La línea 41 es la declarada: ' . $now );
	$rest = $lines;
	unset( $rest[ $line - 1 ] );
	check_ix( 'e6374f4b38d7869fd6ef59fc3b442c8b1aea26a67411215993435b2290b94cdc' === hash( 'sha256', implode( '', $rest ) ), 'El resto del archivo, byte a byte como en 4.3.0.168.' );
	$original              = $lines;
	$original[ $line - 1 ] = "\t\tif ( ! \$this->is_experiment_active() || ! \$this->is_supported_by_current_license() ) {\n";
	check_ix( '194f031ccea298067d0be02db60fa14d44fae1dbd49a338b99df3024b4f9556e' === hash( 'sha256', implode( '', $original ) ), 'Con la línea original, el archivo de 4.3.0.168 (sha256).' );
	check_ix( 1 === substr_count( implode( '', $lines ), 'DIGITALÍSIMO' ), 'Una sola marca del derivado.' );

	// El constructor real: con el estado encendido registra los ganchos de PRO, sin consultar la licencia.
	require $root . '/modules/interactions/hooks.php';
	require $file;
	$GLOBALS['wp_filters'] = array();
	$GLOBALS['options'][ State::OPTION ] = array( 'pending', true );
	new \ElementorPro\Modules\Interactions\Module();
	check_ix( array() === $GLOBALS['wp_filters'], 'En pausa: no se registra nada (y la licencia no se consulta).' );
	$GLOBALS['options'][ State::OPTION ] = array( 'ack', true );
	new \ElementorPro\Modules\Interactions\Module();
	check_ix( isset( $GLOBALS['wp_filters']['elementor-pro/editor/v2/packages'], $GLOBALS['wp_filters']['wp_footer'] ) && 20 === $GLOBALS['wp_filters']['elementor/frontend/after_register_scripts'][0][2], 'Encendidas: los ganchos de hooks.php de PRO.' );
	$GLOBALS['wp_filters'] = array();
	\ElementorPro\Plugin::$atomic = false;
	new \ElementorPro\Modules\Interactions\Module();
	check_ix( array() === $GLOBALS['wp_filters'], 'Sin el experimento de widgets atómicos (v3), nada: la condición del experimento se conserva.' );

	echo 'DIGITALÍSIMO Elements F7b: interacciones PRO validadas (' . $GLOBALS['checks'] . " comprobaciones).\n";
}
