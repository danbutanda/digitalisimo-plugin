<?php
/**
 * F7b, frente D: filtros ampliados (V.5, V.14, C1-13, C1-14, C2-1, C2-13, C2-18 y C2-30), con dobles de WordPress y del
 * núcleo y las clases reales del módulo neutro `modules/digitalisimo-filters/`, de F4-Woo (`Woo\Filters\Types`,
 * `Query`, `Filter_Widget`) y del derivado de PRO `modules/loop-filter/module.php`.
 *
 * - Arranque: los cuatro tipos con sus prefijos en el registro único, un solo enganche, `pre_get_posts` sólo con
 *   `digi_f`, REST y editor; con los de F4-Woo, seis tipos y nada repetido.
 * - Nombres con `<fid>`: `digi_s_`, `digi_m_` (y `-max`, que el derivado parte por el primer guion tras el ID del
 *   bucle), `digi_o_`, `digi_pp_`.
 * - Del widget guardado a la consulta: búsqueda (2 a 100 caracteres), campo (lista y rango con límites), orden (lista
 *   cerrada, sin `rand`; campo sin perder entradas), por página (lista, ≤ 100); topes de 4 `meta_query` por petición.
 * - Petición REST forjada (C2-1): clave, `per_page` y `orderby` no declarados, un `<fid>` de otro documento o de otro
 *   bucle, un tipo cambiado: el bucle queda sin esos filtros. El documento del refresco es el `post_id` de la ruta.
 * - Mismo conjunto que `WP_Query` directo: los argumentos que salen del derivado son los que se escribirían a mano.
 * - Destino «Archivo» con `digi_f`: sólo la consulta principal de un archivo, documento publicado, widget de destino
 *   «Archivo»; sin `digi_f`, nada.
 * - Convivencia con F4-Woo: precio y filtros nuevos en el mismo bucle; las clases de F4-Woo extienden las neutras.
 * - AJAX del editor: `edit_post`, tipo público, consultas acotadas (nunca `SELECT DISTINCT` sobre la tabla entera),
 *   sin claves protegidas ni de la lista negra, transitorio.
 * - Widgets: marcado accesible (`form[role=search]`, etiqueta, «Limpiar» con nombre, `aria-pressed`), `nofollow`,
 *   `style_transfer => false`, sin `default` en estilo, sin `<script>`; registro tolerante y recursos con `defer`.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Controls_Manager {
		const SELECT = 'select'; const TEXT = 'text'; const NUMBER = 'number'; const SWITCHER = 'switcher'; const RAW_HTML = 'raw_html';
		const COLOR = 'color'; const SLIDER = 'slider'; const HEADING = 'heading'; const TAB_STYLE = 'style'; const REPEATER = 'repeater';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	abstract class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $id = '';
		public function __construct( array $data = array(), $args = null ) { $this->id = $data['id'] ?? ''; $this->settings = $data['settings'] ?? array(); }
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) {
			$all = $this->settings + $this->defaults();
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function defaults() { $out = array(); foreach ( $this->controls() as $id => $c ) { if ( array_key_exists( 'default', $c ) ) { $out[ $id ] = $c['default']; } } return $out; }
		public function start_controls_section( $id, $args ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); } $this->controls[ $id ] = $args; }
		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args ); }
		public function add_group_control( $type, $args ) { $this->add_control( $args['name'], $args + array( 'group' => $type ) ); }
		public function controls() { $this->controls = array(); $this->register_controls(); return $this->controls; }
		public function html() { ob_start(); $this->render(); return ob_get_clean(); }
		protected function register_controls() {}
		protected function render() {}
	}
	class Plugin { public static $instance; }
}
namespace Elementor\Core\Frontend {
	class Performance { public static function should_optimize_controls() { return false; } }
}
namespace ElementorPro\Base {
	abstract class Module_Base { public function __construct() {} }
}
namespace ElementorPro\Core {
	class Utils { public static function _unstable_get_super_global_value( $super_global, $key ) { return $super_global[ $key ] ?? null; } }
}
namespace ElementorPro\Modules\LoopFilter\Data {
	class Controller { public function __construct() {} }
}
namespace {
	use Digitalisimo\Elements\Filters\Module;
	use Digitalisimo\Elements\Filters\Query;
	use Digitalisimo\Elements\Filters\Types;
	use Digitalisimo\Elements\Filters\Registry;
	use Digitalisimo\Elements\Filters\Editor;
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$GLOBALS['checks'] = 0;
	function check_f7b( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } $GLOBALS['checks']++; }

	// --- WordPress, con lo justo. -----------------------------------------------------------------------------------
	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function has_filter( $hook ) { return ! empty( $GLOBALS['wp_filters'][ $hook ] ); }
	function hooked( $hook, $priority = 10 ) { return count( $GLOBALS['wp_filters'][ $hook ][ $priority ] ?? array() ); }
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
		return $value;
	}
	function do_action( $hook, ...$args ) { apply_filters( $hook, null, ...$args ); }
	$GLOBALS['transients'] = array();
	function get_transient( $name ) { return $GLOBALS['transients'][ $name ] ?? false; }
	function set_transient( $name, $value, $ttl = 0 ) { $GLOBALS['transients'][ $name ] = array( $value, $ttl ); $GLOBALS['transients'][ $name ] = $value; $GLOBALS['ttl'][ $name ] = $ttl; return true; }
	function sanitize_title( $title ) { return strtolower( trim( preg_replace( '/[^A-Za-z0-9_\-%]+/', '-', (string) $title ), '-' ) ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function sanitize_text_field( $text ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) ); }
	function is_protected_meta( $key, $type = '' ) { return '_' === substr( (string) $key, 0, 1 ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_parse_str( $string, &$array ) { parse_str( $string, $array ); }
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
	$GLOBALS['scripts'] = array();
	$GLOBALS['styles']  = array();
	function wp_script_is( $handle, $list ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
	function wp_style_is( $handle, $list ) { return isset( $GLOBALS['styles'][ $handle ] ); }
	function wp_register_script( $handle, $src, $deps, $ver, $args ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'args' ); return true; }
	function wp_register_style( $handle, $src, $deps, $ver ) { $GLOBALS['styles'][ $handle ] = compact( 'src', 'deps' ); return true; }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'args' ) + array( 'enqueued' => true ); }
	$GLOBALS['query_vars'] = array();
	function get_query_var( $name ) { return $GLOBALS['query_vars'][ $name ] ?? 0; }
	function is_page() { return true; }
	function is_single() { return false; }
	function get_permalink() { return 'https://example.test/catalogo/'; }
	function wp_create_nonce( $action ) { return 'nonce'; }
	$GLOBALS['postmeta'] = array();
	function get_post_meta( $id, $key, $single ) { return $GLOBALS['postmeta'][ (int) $id ][ $key ] ?? ''; }
	$GLOBALS['post_status'] = array();
	function get_post_status( $id ) { return $GLOBALS['post_status'][ (int) $id ] ?? false; }
	$GLOBALS['caps'] = array();
	function current_user_can( $cap, ...$args ) { return in_array( $cap . ':' . ( $args[0] ?? '' ), $GLOBALS['caps'], true ); }
	function get_post_types( $args = array(), $output = 'names' ) { $types = array( 'post' => 'post', 'page' => 'page', 'attachment' => 'attachment', 'producto' => 'producto' ); return 'objects' === $output ? array_map( function ( $n ) { return (object) array( 'label' => ucfirst( $n ) ); }, $types ) : $types; }
	function get_object_taxonomies( $type ) { return array( 'product_cat' ); }
	class WP_Term { public $term_id; public $slug; public $name; public $taxonomy; public $parent = 0; public $count = 1; public function __construct( $id, $slug, $taxonomy ) { $this->term_id = $id; $this->slug = $slug; $this->name = $slug; $this->taxonomy = $taxonomy; } }
	function get_terms( $args ) { return array( new WP_Term( 1, 'ropa', $args['taxonomy'] ) ); }
	function is_wp_error( $thing ) { return false; }
	class WPDB_Double {
		public $prefix = 'wp_'; public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $wc_product_meta_lookup = 'wp_wc_product_meta_lookup'; public $queries = array(); public $cols = array();
		public function prepare( $query, ...$args ) { return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $args ); }
		public function get_col( $sql ) { $this->queries[] = $sql; return array_shift( $this->cols ) ?: array(); }
	}
	$GLOBALS['wpdb'] = new WPDB_Double();

	// Documento actual del núcleo (el que se pinta) y modo editor.
	\Elementor\Plugin::$instance = (object) array(
		'editor'    => new class() { public $edit = false; public function is_edit_mode() { return $this->edit; } },
		'preview'   => new class() { public function is_preview_mode() { return false; } },
		'documents' => new class() { public $current = 10; public function get_current() { $id = $this->current; return $id ? new class( $id ) { private $id; public function __construct( $id ) { $this->id = $id; } public function get_post() { return (object) array( 'ID' => $this->id ); } public function get_main_id() { return $this->id; } } : null; } },
	);

	// --- Documentos guardados. ----------------------------------------------------------------------------------------
	$w = function ( $id, $type, $settings ) { return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings ); };
	$loop = array( 'destination' => 'loop', 'selected_element' => 'loop123' );
	$doc10 = array(
		array(
			'id' => 'cont001', 'elType' => 'container', 'settings' => array(),
			'elements' => array(
				$w( 'loop123', 'loop-grid', array( 'posts_per_page' => 6 ) ),
				$w( 'fsearch', 'digitalisimo-loop-search', $loop ),
				$w( 'frange1', 'digitalisimo-loop-meta-filter', $loop + array( 'meta_key' => 'precio', 'value_type' => 'DECIMAL', 'mode' => 'range', 'range_min' => '0', 'range_max' => '1000' ) ),
				$w( 'fcolor1', 'digitalisimo-loop-meta-filter', $loop + array( 'meta_key' => '__custom', 'meta_key_custom' => 'color', 'mode' => 'in', 'options' => array( array( '_id' => 'a', 'value' => 'Rojo', 'label' => 'Rojo' ), array( '_id' => 'b', 'value' => 'Nueva York', 'label' => 'NY' ), array( '_id' => 'c', 'value' => 'azul' ) ) ) ),
				$w( 'fsecret', 'digitalisimo-loop-meta-filter', $loop + array( 'meta_key' => '_secreto', 'mode' => 'in', 'options' => array( array( 'value' => 'x' ) ) ) ),
				$w( 'fapikey', 'digitalisimo-loop-meta-filter', $loop + array( 'meta_key' => '__custom', 'meta_key_custom' => 'api_key', 'mode' => 'equal', 'options' => array( array( 'value' => 'x' ) ) ) ),
				$w( 'forder1', 'digitalisimo-loop-orderby', $loop + array( 'order_meta_key' => 'precio', 'orders' => array( array( 'order_key' => 'title_asc' ), array( 'order_key' => 'date_desc', 'label' => 'Nuevos' ), array( 'order_key' => 'meta_desc' ), array( 'order_key' => 'rand' ), array( 'order_key' => 'price_asc' ) ) ) ),
				$w( 'fpage01', 'digitalisimo-loop-per-page', $loop + array( 'values' => '12, 24, 48, 500, 0, 24' ) ),
				$w( 'fother1', 'digitalisimo-loop-search', array( 'destination' => 'loop', 'selected_element' => 'otro123' ) ),
				$w( 'farch01', 'digitalisimo-loop-orderby', array( 'destination' => 'archive' ) ),
				$w( 'farch02', 'digitalisimo-loop-per-page', array( 'destination' => 'archive' ) ),
				$w( 'farch03', 'digitalisimo-loop-meta-filter', array( 'destination' => 'archive', 'meta_key' => 'color', 'mode' => 'in', 'options' => array( array( 'value' => 'Rojo' ), array( 'value' => 'azul' ) ) ) ),
			),
		),
	);
	foreach ( array( 'fm00001', 'fm00002', 'fm00003', 'fm00004', 'fm00005' ) as $i => $fid ) {
		$doc10[0]['elements'][] = $w( $fid, 'digitalisimo-loop-meta-filter', $loop + array( 'meta_key' => 'campo' . $i, 'mode' => 'equal', 'options' => array( array( 'value' => 'si' ) ) ) );
	}
	$doc20 = array( $w( 'fdoc020', 'digitalisimo-loop-search', $loop ), $w( 'loop123', 'loop-grid', array() ) );
	$GLOBALS['postmeta'][10]['_elementor_data'] = json_encode( $doc10 );
	$GLOBALS['postmeta'][20]['_elementor_data'] = json_encode( $doc20 );
	$GLOBALS['postmeta'][30]['_elementor_data'] = '[{"id":"x","elType":"widget","widgetType":"heading","settings":{}}]';
	$GLOBALS['post_status'] = array( 10 => 'publish', 20 => 'publish', 40 => 'draft' );
	$GLOBALS['postmeta'][40]['_elementor_data'] = json_encode( $doc10 );

	// --- Módulo real. ------------------------------------------------------------------------------------------------
	require $root . '/modules/digitalisimo-filters/class-module.php';
	Module::init();
	Module::init();
	check_f7b( array( 'digitalisimo_search' => 'digi_s_', 'digitalisimo_meta' => 'digi_m_', 'digitalisimo_orderby' => 'digi_o_', 'digitalisimo_per_page' => 'digi_pp_' ) === Types::registered(), 'Los cuatro tipos con sus prefijos (V.5).' );
	foreach ( array( 'digitalisimo_elements_loop_filter_types', 'digitalisimo_elements_loop_filter_param_type', 'digitalisimo_elements_loop_filter_query' ) as $hook ) {
		check_f7b( 1 === hooked( $hook ), 'Un solo enganche en ' . $hook );
	}
	check_f7b( 1 === hooked( 'elementor/widgets/register', 20 ) && 1 === hooked( 'elementor/ajax/register_actions' ) && 1 === hooked( 'elementor/editor/after_enqueue_scripts' ) && 1 === hooked( 'rest_api_init' ), 'Registro de widgets (20), AJAX y script del editor, REST: una vez.' );
	check_f7b( ! has_filter( 'pre_get_posts' ) && ! has_filter( 'posts_clauses' ) && ! has_filter( 'rest_request_before_callbacks' ), 'Sin digi_f, sin REST y sin orden por precio: nada en pre_get_posts, posts_clauses ni REST (coste 0).' );
	do_action( 'rest_api_init' );
	check_f7b( 1 === hooked( 'rest_request_before_callbacks' ), 'En REST, el documento del refresco.' );
	$_GET = array( 'digi_f' => '10-farch01' );
	Module::reset();
	Module::init();
	check_f7b( 1 === hooked( 'pre_get_posts', 20 ), 'Con digi_f, pre_get_posts (20).' );
	$_GET = array();
	check_f7b( '' === Module::file_for( 'Digitalisimo\\Elements\\Woo\\Types' ) && '' === Module::file_for( 'Digitalisimo\\Elements\\Filters\\..\\X' ) && str_ends_with( Module::file_for( 'Digitalisimo\\Elements\\Filters\\Meta_Widget' ), '/class-meta-widget.php' ), 'Autocarga acotada al espacio de nombres del módulo.' );

	// --- Nombres en la URL: tipo por prefijo; el derivado parte por el primer guion tras el ID del bucle. ----------------
	foreach ( array( 'digi_s_fsearch' => 'digitalisimo_search', 'digi_m_frange1' => 'digitalisimo_meta', 'digi_m_frange1-max' => 'digitalisimo_meta', 'digi_o_forder1' => 'digitalisimo_orderby', 'digi_pp_fpage01' => 'digitalisimo_per_page', 'product_cat' => 'taxonomy', 'digi_s_' => 'taxonomy' ) as $name => $type ) {
		check_f7b( $type === Types::param_type( 'taxonomy', $name ), 'Tipo de ' . $name );
	}
}
namespace {
	use Digitalisimo\Elements\Filters\Module;
	use Digitalisimo\Elements\Filters\Query;
	use Digitalisimo\Elements\Filters\Types;
	use Digitalisimo\Elements\Filters\Registry;
	use Digitalisimo\Elements\Filters\Editor;
	$loop_dir = $root . '/modules/loop-filter/';
	foreach ( array( 'query/data/query-constants.php', 'query/interfaces/query-interface.php', 'query/query-types/single-terms-query.php', 'query/query-types/hierarchy-and-query.php', 'query/query-types/hierarchy-or-query.php', 'query/taxonomy-manager.php', 'query/taxonomy-query-builder.php', 'traits/hierarchical-taxonomy-trait.php', 'module.php' ) as $file ) {
		require_once $loop_dir . $file;
	}
	$loop_widget = new class() { public function get_id() { return 'loop123'; } };
	$base_args   = array( 'post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'desc', 'posts_per_page' => 6 );
	$from_url    = function ( $query_string, $loop = null ) use ( $loop_widget, $base_args ) {
		Query::reset();
		$_SERVER['QUERY_STRING'] = $query_string;
		$filter = new \ElementorPro\Modules\LoopFilter\Module();
		return $filter->filter_loop_query( $base_args, $loop ?: $loop_widget );
	};
	$parse = digi_test_accessible( ( new \ReflectionClass( \ElementorPro\Modules\LoopFilter\Module::class ) )->getMethod( 'parse_query_string' ) );
	if ( PHP_VERSION_ID < 80100 ) { $parse->setAccessible( true ); }
	check_f7b( array( 'taxonomy' => 'digi_m_frange1-max', 'widget_id' => 'loop123' ) === $parse->invoke( new \ElementorPro\Modules\LoopFilter\Module(), 'e-filter-loop123-digi_m_frange1-max' ), 'El derivado parte el nombre por el primer guion tras el ID: -max llega entero.' );

	// --- Mismo conjunto que WP_Query directo: los argumentos del derivado son los escritos a mano. -------------------
	$args = $from_url( 'e-filter-loop123-digi_s_fsearch=zapatos+de+cuero&e-filter-loop123-digi_m_frange1=10&e-filter-loop123-digi_m_frange1-max=50,5&e-filter-loop123-digi_m_fcolor1=rojo~nueva-york&e-filter-loop123-digi_o_forder1=title_asc&e-filter-loop123-digi_pp_fpage01=24' );
	$direct = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'posts_per_page' => 24,
		'tax_query'      => array( 'relation' => 'AND' ),
		's'              => 'zapatos de cuero',
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => 'precio', 'type' => 'DECIMAL(20,6)', 'value' => array( '10', '50.5' ), 'compare' => 'BETWEEN' ),
			array( 'key' => 'color', 'type' => 'CHAR', 'value' => array( 'Rojo', 'Nueva York' ), 'compare' => 'IN' ),
		),
	);
	ksort( $args );
	ksort( $direct );
	check_f7b( $direct === $args, 'Búsqueda, rango, lista, orden y por página: los mismos argumentos que un WP_Query directo. ' . json_encode( $args ) );
	$plain = $from_url( '' );
	check_f7b( $base_args === $plain, 'Sin parámetros, la consulta del bucle no cambia.' );

	// Búsqueda: 2 a 100 caracteres, sin HTML; el orden del bucle no se toca.
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_s_fsearch=a' )['s'] ), 'Un carácter: sin búsqueda.' );
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_s_fsearch=' . str_repeat( 'x', 101 ) )['s'] ), '101 caracteres: sin búsqueda.' );
	$args = $from_url( 'e-filter-loop123-digi_s_fsearch=%3Cb%3Eluz%3C%2Fb%3E' );
	check_f7b( 'luz' === $args['s'] && 'date' === $args['orderby'], 'Sin etiquetas; el orden del bucle queda como estaba: ' . json_encode( $args ) );
	// Rango: al revés se intercambia, los límites del widget mandan, un extremo inválido no filtra.
	$range = function ( $qs ) use ( $from_url ) { $a = $from_url( $qs ); return $a['meta_query'][0] ?? null; };
	check_f7b( array( 'key' => 'precio', 'type' => 'DECIMAL(20,6)', 'value' => array( '20', '900' ), 'compare' => 'BETWEEN' ) === $range( 'e-filter-loop123-digi_m_frange1=900&e-filter-loop123-digi_m_frange1-max=20' ), 'Rango al revés: se intercambia.' );
	check_f7b( array( 'key' => 'precio', 'type' => 'DECIMAL(20,6)', 'value' => '1000', 'compare' => '<=' ) === $range( 'e-filter-loop123-digi_m_frange1=abc&e-filter-loop123-digi_m_frange1-max=5000' ), 'Un extremo inválido no filtra; el otro se lleva al límite del widget (1000).' );
	check_f7b( array( 'key' => 'precio', 'type' => 'DECIMAL(20,6)', 'value' => '0', 'compare' => '>=' ) === $range( 'e-filter-loop123-digi_m_frange1=-30' ), 'Por debajo del límite inferior: el límite.' );
	check_f7b( null === $range( 'e-filter-loop123-digi_m_frange1=1e3&e-filter-loop123-digi_m_frange1-max=x' ), 'Exponentes y texto: sin filtro.' );
	// Lista: sólo fichas declaradas; ninguna válida, ningún filtro.
	$args = $from_url( 'e-filter-loop123-digi_m_fcolor1=verde~azul~nueva-york' );
	check_f7b( array( 'azul', 'Nueva York' ) === $args['meta_query'][0]['value'], 'Una ficha no declarada se descarta; las válidas, con el valor guardado (con espacios).' );
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_m_fcolor1=verde' )['meta_query'] ), 'Sin fichas válidas, sin meta_query.' );
	check_f7b( 'Nueva York' === ( $from_url( 'e-filter-loop123-digi_m_fcolor1=nueva-york' )['meta_query'][0]['value'][0] ?? '' ), 'Una ficha sola (sin «~»).' );
	// Claves protegidas o de la lista negra: nunca.
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_m_fsecret=x&e-filter-loop123-digi_m_fapikey=x' )['meta_query'] ), 'Clave protegida (_secreto) o de la lista negra (api_key): sin filtro.' );
	// Orden: de la lista del widget, sin rand; por campo conserva las entradas sin él; precio sólo con WooCommerce.
	check_f7b( 'date' === $from_url( 'e-filter-loop123-digi_o_forder1=rand' )['orderby'], 'rand nunca.' );
	check_f7b( 'date' === $from_url( 'e-filter-loop123-digi_o_forder1=modified_desc' )['orderby'], 'Un orden fuera de la lista del widget no se aplica.' );
	check_f7b( 'date' === $from_url( 'e-filter-loop123-digi_o_forder1=price_asc' )['orderby'], 'Precio sin WooCommerce: no se aplica.' );
	$args = $from_url( 'e-filter-loop123-digi_o_forder1=meta_desc' );
	check_f7b( array( 'digi_order_forder1' => 'DESC' ) === $args['orderby'] && array( 'relation' => 'OR', 'digi_order_forder1' => array( 'key' => 'precio', 'compare' => 'EXISTS', 'type' => 'DECIMAL(20,6)' ), array( 'key' => 'precio', 'compare' => 'NOT EXISTS' ) ) === $args['meta_query'][0], 'Por campo: cláusula con nombre y NOT EXISTS (no quita entradas sin el campo).' );
	// Por página: de la lista, ≤ 100.
	check_f7b( 48 === $from_url( 'e-filter-loop123-digi_pp_fpage01=48' )['posts_per_page'], 'Una cantidad de la lista.' );
	foreach ( array( '500', '7', '0', '-1', '24.5', '1e2' ) as $bad ) {
		check_f7b( 6 === $from_url( 'e-filter-loop123-digi_pp_fpage01=' . $bad )['posts_per_page'], 'Por página fuera de la lista o > 100: ' . $bad );
	}
	check_f7b( array( 12, 24, 48 ) === Query::normalize( 'digitalisimo-loop-per-page', array( 'values' => '12, 24, 48, 500, 0, 24' ) )['values'], 'Lista: enteros de 1 a 100 sin repetir.' );
	// Tope de 4 meta_query por petición (el mismo widget no gasta otro).
	$qs = array();
	foreach ( array( 'fm00001', 'fm00002', 'fm00003', 'fm00004', 'fm00005' ) as $fid ) { $qs[] = 'e-filter-loop123-digi_m_' . $fid . '=si'; }
	$args = $from_url( implode( '&', $qs ) );
	check_f7b( 5 === count( $args['meta_query'] ), 'Cinco widgets de campo: sólo 4 meta_query (más la relación).' );
	Query::reset();
	$args = Query::apply_meta( array(), array( 'digi_m_fm00001' => array( 'terms' => array( 'si' ) ) ), $loop_widget );
	$args = Query::apply_meta( $args, array( 'digi_m_fm00001' => array( 'terms' => array( 'si' ) ), 'digi_m_fm00002' => array( 'terms' => array( 'si' ) ), 'digi_m_fm00003' => array( 'terms' => array( 'si' ) ), 'digi_m_fm00004' => array( 'terms' => array( 'si' ) ) ), $loop_widget );
	check_f7b( 6 === count( $args['meta_query'] ), 'El mismo widget aplicado dos veces no gasta otro cupo (el resultado repite su cláusula; los otros 3 caben).' );
	// Un meta_query previo con OR no se amplía.
	Query::reset();
	$args = Query::apply_meta( array( 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'a' ), array( 'key' => 'b' ) ) ), array( 'digi_m_fcolor1' => array( 'terms' => array( 'rojo' ) ) ), $loop_widget );
	check_f7b( 'AND' === $args['meta_query']['relation'] && 'OR' === $args['meta_query'][0]['relation'] && 'color' === $args['meta_query'][1]['key'], 'Un grupo OR existente queda entero dentro de un AND.' );
	// Al calcular los términos del filtro de PRO (include_field_ids_arg), nada.
	$GLOBALS['wp_query'] = (object) array( 'include_field_ids_arg' => true );
	check_f7b( $base_args === $from_url( 'e-filter-loop123-digi_s_fsearch=hola' ) || ! isset( $from_url( 'e-filter-loop123-digi_s_fsearch=hola' )['s'] ), 'Con include_field_ids_arg los tipos no se aplican.' );
	$GLOBALS['wp_query'] = (object) array();

	// --- Otro documento, otro bucle, otro tipo: descartados. ---------------------------------------------------------
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_s_fdoc020=hola' )['s'] ), 'Un <fid> de otro documento: descartado.' );
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_s_fother1=hola' )['s'] ), 'Un <fid> de este documento que filtra otro bucle: descartado.' );
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_s_frange1=hola' )['s'] ), 'Un <fid> de otro tipo de widget: descartado.' );
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_o_farch01=title_asc' )['orderby'] ) || 'date' === $from_url( 'e-filter-loop123-digi_o_farch01=title_asc' )['orderby'], 'Un widget con destino «Archivo» no filtra el bucle.' );
	\Elementor\Plugin::$instance->documents->current = 20;
	check_f7b( 'hola' === ( $from_url( 'e-filter-loop123-digi_s_fdoc020=hola' )['s'] ?? '' ) && ! isset( $from_url( 'e-filter-loop123-digi_s_fsearch=hola' )['s'] ), 'El documento del bucle decide: en el 20, su buscador sí y el del 10 no.' );
	\Elementor\Plugin::$instance->documents->current = 0;
	check_f7b( ! isset( $from_url( 'e-filter-loop123-digi_s_fsearch=hola' )['s'] ), 'Sin documento actual (ni refresco): falla cerrado.' );
	\Elementor\Plugin::$instance->documents->current = 10;
	$other_loop = new class() { public function get_id() { return 'otro123'; } };
	check_f7b( 'hola' === ( $from_url( 'e-filter-otro123-digi_s_fother1=hola', $other_loop )['s'] ?? '' ), 'El buscador de otro bucle filtra el suyo.' );

	// --- Petición REST forjada (C2-1). ----------------------------------------------------------------------------------
	$request = function ( $route, $post ) { return new class( $route, $post ) { private $r; private $p; public function __construct( $r, $p ) { $this->r = $r; $this->p = $p; } public function get_route() { return $this->r; } public function get_param( $k ) { return 'post_id' === $k ? $this->p : null; } }; };
	$refresh = function ( $filters, $post = 10 ) use ( $request, $loop_widget, $base_args ) {
		Query::reset();
		\Elementor\Plugin::$instance->documents->current = 0;
		check_f7b( 'x' === Query::rest_document( 'x', null, $request( '/elementor-pro/v1/refresh-loop', $post ) ), 'rest_request_before_callbacks devuelve la respuesta tal cual.' );
		$_SERVER['QUERY_STRING'] = '';
		$filter = new \ElementorPro\Modules\LoopFilter\Module();
		$filter->register_widget_filter( 'loop123', $filters );
		$out = $filter->filter_loop_query( $base_args, $loop_widget );
		\Elementor\Plugin::$instance->documents->current = 10;
		return $out;
	};
	$forged = $refresh(
		array(
			'digitalisimo_meta'     => array( 'digi_m_zzzzzzz' => array( 'terms' => array( 'x' ) ), 'digi_m_fcolor1' => array( 'terms' => array( 'secreto' ), 'meta_key' => '_pass' ), 'meta_key' => array( 'terms' => array( '_edit_lock' ) ) ),
			'digitalisimo_per_page' => array( 'digi_pp_fpage01' => array( 'terms' => array( '1000' ) ), 'posts_per_page' => array( 'terms' => array( '-1' ) ) ),
			'digitalisimo_orderby'  => array( 'digi_o_forder1' => array( 'terms' => array( 'rand' ) ), 'orderby' => array( 'terms' => array( 'rand' ) ) ),
			'digitalisimo_search'   => array( 'digi_m_frange1' => array( 'terms' => array( 'hola' ) ), 'digi_s_fdoc020' => array( 'terms' => array( 'hola' ) ) ),
		)
	);
	check_f7b( $base_args + array( 'tax_query' => array( 'relation' => 'AND' ) ) === $forged, 'Petición forjada (clave, per_page, orderby y tipos no declarados, <fid> de otro documento): el bucle queda sin esos filtros. ' . json_encode( $forged ) );
	$ok = $refresh( array( 'digitalisimo_search' => array( 'digi_s_fsearch' => array( 'terms' => array( 'hola mundo' ), 'logicalJoin' => 'AND' ) ), 'digitalisimo_per_page' => array( 'digi_pp_fpage01' => array( 'terms' => array( '12' ) ) ) ) );
	check_f7b( 'hola mundo' === $ok['s'] && 12 === $ok['posts_per_page'], 'El refresco del coordinador (texto entero) con el documento del post_id.' );
	check_f7b( ! isset( $refresh( array( 'digitalisimo_search' => array( 'digi_s_fsearch' => array( 'terms' => array( 'hola' ) ) ) ), 20 )['s'] ), 'Con el post_id de otro documento, el <fid> no está: descartado.' );
	Query::reset();
	Query::rest_document( null, null, $request( '/wp/v2/posts', 99 ) );
	check_f7b( 10 === Query::loop_document(), 'Otra ruta REST no cambia el documento.' );

	// --- Destino «Archivo» con digi_f. ---------------------------------------------------------------------------------
	class WP_Query_Double {
		public $query_vars; public $sets = array(); public $flags;
		public function __construct( $vars, $flags = array() ) { $this->query_vars = $vars; $this->flags = $flags + array( 'main' => true, 'feed' => false, 'archive' => true, 'home' => false, 'search' => false, 'pta' => false, 'tax' => false ); }
		public function is_main_query() { return $this->flags['main']; }
		public function is_feed() { return $this->flags['feed']; }
		public function is_archive() { return $this->flags['archive']; }
		public function is_home() { return $this->flags['home']; }
		public function is_search() { return $this->flags['search']; }
		public function is_post_type_archive( $t = '' ) { return $this->flags['pta']; }
		public function is_tax( $t = '' ) { return $this->flags['tax']; }
		public function set( $k, $v ) { $this->sets[ $k ] = $v; $this->query_vars[ $k ] = $v; }
		public function get( $k ) { return $this->query_vars[ $k ] ?? ''; }
	}
	$archive = function ( $get, $flags = array() ) {
		Query::reset();
		$_GET = $get;
		$q = new WP_Query_Double( array( 'category_name' => 'noticias', 'posts_per_page' => 10, 'meta_query' => '' ), $flags );
		Query::archive( $q );
		$_GET = array();
		return $q->sets;
	};
	$sets = $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'title_desc', 'digi_pp_farch02' => '24', 'digi_m_farch03' => 'rojo,azul,verde' ) );
	check_f7b( array( 'meta_query' => array( 'relation' => 'AND', array( 'key' => 'color', 'type' => 'CHAR', 'value' => array( 'Rojo', 'azul' ), 'compare' => 'IN' ) ), 'orderby' => 'title', 'order' => 'DESC', 'posts_per_page' => 24 ) === $sets, 'Archivo: orden, por página y campo de los widgets de destino «Archivo» del documento de digi_f. ' . json_encode( $sets ) );
	check_f7b( array() === $archive( array( 'digi_o_farch01' => 'title_desc', 'digi_pp_farch02' => '24' ) ), 'Sin digi_f, los parámetros propios se ignoran.' );
	check_f7b( array() === $archive( array( 'digi_f' => '40-farch01', 'digi_o_farch01' => 'title_desc' ) ), 'Documento sin publicar: nada.' );
	check_f7b( array() === $archive( array( 'digi_f' => '10-forder1', 'digi_o_forder1' => 'title_asc' ) ), 'digi_f de un widget con destino Loop Grid: nada.' );
	check_f7b( array( 'posts_per_page' => 24 ) === $archive( array( 'digi_f' => '10-farch01', 'digi_o_forder1' => 'title_asc', 'digi_pp_farch02' => '24', 'digi_pp_fpage01' => '48' ) ), 'Cada parámetro contra su propio widget: los de destino Loop Grid no cuentan.' );
	check_f7b( array() === $archive( array( 'digi_f' => '10-zzzzzzz', 'digi_o_farch01' => 'title_desc' ) ) && array() === $archive( array( 'digi_f' => '10-farch01x', 'digi_o_farch01' => 'title_desc' ) ) && array() === $archive( array( 'digi_f' => array( '10-farch01' ), 'digi_o_farch01' => 'title_desc' ) ), 'digi_f de un widget inexistente o mal formado: nada.' );
	check_f7b( array() === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'title_desc' ), array( 'main' => false ) ), 'Sólo la consulta principal.' );
	check_f7b( array() === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'title_desc' ), array( 'archive' => false ) ), 'Sólo en un archivo, la portada del blog o la búsqueda (no en una entrada).' );
	check_f7b( array() === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'title_desc' ), array( 'feed' => true ) ), 'Nunca en un feed.' );
	$GLOBALS['is_admin'] = true;
	check_f7b( array() === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'title_desc' ) ), 'Nunca en la administración.' );
	$GLOBALS['is_admin'] = false;
	check_f7b( array( 'orderby' => 'title', 'order' => 'DESC' ) === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'title_desc' ), array( 'archive' => false, 'search' => true ) ), 'En la búsqueda, sí.' );
	check_f7b( array() === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'rand' ) ) && array() === $archive( array( 'digi_f' => '10-farch01', 'digi_o_farch01' => 'modified_desc' ) ), 'Archivo: sólo los órdenes por defecto del widget (sin rand).' );

	// --- Convivencia con F4-Woo: un registro, dos módulos, el mismo bucle. ---------------------------------------------
	foreach ( array( 'class-types.php', 'class-query.php' ) as $file ) { require_once $root . '/modules/digitalisimo-woo/filters/' . $file; }
	$woo_types = \Digitalisimo\Elements\Woo\Filters\Types::class;
	$woo_query = \Digitalisimo\Elements\Woo\Filters\Query::class;
	check_f7b( is_subclass_of( $woo_types, Types::class ) && is_subclass_of( $woo_query, Query::class ), 'Las clases de F4-Woo extienden las neutras (C1-13).' );
	$woo_types::register( $woo_types::PRICE, $woo_types::PRICE_PREFIX, array( $woo_query, 'apply_price' ) );
	$woo_types::register( $woo_types::ATTRIBUTE, $woo_types::ATTRIBUTE_PREFIX, array( $woo_query, 'apply_attribute' ) );
	check_f7b( 6 === count( Types::registered() ) && Types::registered() === $woo_types::registered(), 'Un solo registro con los seis tipos.' );
	foreach ( array( 'digitalisimo_elements_loop_filter_types', 'digitalisimo_elements_loop_filter_param_type', 'digitalisimo_elements_loop_filter_query' ) as $hook ) {
		check_f7b( 1 === hooked( $hook ), 'Sigue habiendo un solo enganche en ' . $hook );
	}
	check_f7b( 'digitalisimo_price' === Types::param_type( 'taxonomy', 'digi_price_min' ) && 'digitalisimo_per_page' === Types::param_type( 'taxonomy', 'digi_pp_fpage01' ) && 'digitalisimo_attribute' === Types::param_type( 'taxonomy', 'digiattr_pa_color' ), 'Los prefijos no se pisan (digi_price_ y digi_pp_).' );
	$args = $from_url( 'e-filter-loop123-digi_price_min=50&e-filter-loop123-digi_price_max=10&e-filter-loop123-digi_s_fsearch=botas&e-filter-loop123-product_cat=ropa' );
	check_f7b( array( 'min' => 10.0, 'max' => 50.0 ) === $args['digitalisimo_price_range'] && 'botas' === $args['s'] && 'product_cat' === ( $args['tax_query'][0]['taxonomy'] ?? '' ), 'Precio (F4-Woo), búsqueda (F7b) y la taxonomía de PRO en el mismo bucle.' );
	check_f7b( ' LEFT JOIN wp_wc_product_meta_lookup wc_product_meta_lookup ON wp_posts.ID = wc_product_meta_lookup.product_id ' === $woo_query::append_join( '' ) && $woo_query::append_join( 'x' ) === Query::append_join( 'x' ), 'La guarda del JOIN es la misma para F4-Woo y para el orden por precio.' );
	// Orden por precio con WooCommerce: tabla de búsqueda con la guarda, sólo en bucles de productos.
	eval( 'class WooCommerce {}' );
	Query::reset();
	$clauses_before = hooked( 'posts_clauses' );
	$args = Query::apply_orderby( array( 'post_type' => 'post', 'orderby' => 'date' ), array( 'digi_o_forder1' => array( 'terms' => array( 'price_asc' ) ) ), $loop_widget );
	check_f7b( 'date' === $args['orderby'] && $clauses_before === hooked( 'posts_clauses' ), 'Precio en un bucle de entradas: no.' );
	$args = Query::apply_orderby( array( 'post_type' => 'product', 'orderby' => 'date' ), array( 'digi_o_forder1' => array( 'terms' => array( 'price_asc' ) ) ), $loop_widget );
	check_f7b( 'none' === $args['orderby'] && 'ASC' === $args['digitalisimo_order_price'] && $clauses_before + 1 === hooked( 'posts_clauses' ), 'Precio en un bucle de productos: variable propia y un solo posts_clauses.' );
	$q       = new WP_Query_Double( $args );
	$clauses = Query::order_clauses( array( 'join' => ' LEFT JOIN wp_wc_product_meta_lookup wc_product_meta_lookup ON x ', 'orderby' => 'wp_posts.post_date DESC' ), $q );
	check_f7b( 1 === substr_count( $clauses['join'], 'wc_product_meta_lookup ON' ) && false !== strpos( $clauses['orderby'], 'wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC' ), 'Orden de la tienda sobre la tabla, sin repetir el JOIN del filtro de precio.' );
	check_f7b( array( 'orderby' => 'x' ) === Query::order_clauses( array( 'orderby' => 'x' ), new WP_Query_Double( array() ) ), 'Otra consulta: sin cambios.' );

	// --- Claves de campo y lista negra (V.8). ----------------------------------------------------------------------------
	foreach ( array( '_precio' => '', 'api_key' => '', 'keywords' => 'keywords', 'user-email' => '', 'Precio_M2' => 'Precio_M2', 'nombre_cliente' => '', 'TOKEN' => '', 'a.b' => '', str_repeat( 'a', 65 ) => '', 'color' => 'color' ) as $key => $expected ) {
		check_f7b( $expected === Query::meta_key( $key ), 'Clave ' . $key );
	}
	add_filter( 'digitalisimo_elements_inline_tags_denied', function ( $list ) { $list[] = 'salario'; return $list; } );
	check_f7b( '' === Query::meta_key( 'salario_bruto' ) && 'salarios' === Query::meta_key( 'salarios' ), 'La lista negra se amplía con su filtro, por fragmentos.' );

	// --- AJAX del editor (C2-18). ----------------------------------------------------------------------------------------
	$ajax = new class() { public $actions = array(); public function register_ajax_action( $tag, $cb ) { $this->actions[ $tag ] = $cb; } };
	Editor::register_ajax( $ajax );
	check_f7b( array( 'digitalisimo_filters_meta_keys' ) === array_keys( $ajax->actions ), 'Una acción AJAX, por elementor/ajax/register_actions.' );
	$call = function ( $data ) { try { return Editor::meta_keys( $data ); } catch ( \Exception $e ) { return $e->getCode(); } };
	check_f7b( 403 === $call( array( 'post_type' => 'post' ) ) && 403 === $call( array( 'editor_post_id' => 10, 'post_type' => 'post' ) ), 'Sin documento o sin edit_post (suscriptor): 403.' );
	$GLOBALS['caps'] = array( 'edit_post:11' );
	check_f7b( 403 === $call( array( 'editor_post_id' => 10, 'post_type' => 'post' ) ), 'Autor de otro documento: 403.' );
	$GLOBALS['caps'] = array( 'edit_post:10' );
	check_f7b( 400 === $call( array( 'editor_post_id' => 10, 'post_type' => 'privado' ) ) && 400 === $call( array( 'editor_post_id' => 10, 'post_type' => 'attachment' ) ) && 400 === $call( array( 'editor_post_id' => 10 ) ), 'Tipo no público, adjuntos o vacío: 400.' );
	check_f7b( array() === $GLOBALS['wpdb']->queries, 'Sin permiso o con un tipo inválido, ninguna consulta.' );
	$GLOBALS['wpdb']->cols = array( array( '5', '3' ), array( 'color', 'api_key', 'keywords', '_edit_lock', 'email_contacto', 'precio' ) );
	$res = $call( array( 'editor_post_id' => '10', 'post_type' => 'producto' ) );
	check_f7b( array( 'keys' => array( 'color', 'keywords', 'precio' ) ) === $res, 'Claves sin protegidas ni de la lista negra: ' . json_encode( $res ) );
	check_f7b( 2 === count( $GLOBALS['wpdb']->queries ) && "SELECT ID FROM wp_posts WHERE post_type = 'producto' AND post_status = 'publish' ORDER BY ID DESC LIMIT 100" === $GLOBALS['wpdb']->queries[0] && false !== strpos( $GLOBALS['wpdb']->queries[1], 'FROM wp_postmeta WHERE post_id IN (5,3) AND meta_key NOT LIKE ' ) && false !== strpos( $GLOBALS['wpdb']->queries[1], 'LIMIT 200' ), 'Dos consultas acotadas (100 entradas, 200 claves): ' . implode( ' | ', $GLOBALS['wpdb']->queries ) );
	foreach ( $GLOBALS['wpdb']->queries as $sql ) { check_f7b( ! preg_match( '/SELECT DISTINCT meta_key FROM wp_postmeta(?! WHERE post_id IN)/', $sql ), 'Nunca un SELECT DISTINCT sobre la tabla entera.' ); }
	check_f7b( array( 'color', 'keywords', 'precio' ) === $GLOBALS['transients']['digi_filters_keys_producto'] && 3600 === $GLOBALS['ttl']['digi_filters_keys_producto'], 'Transitorio de una hora por tipo.' );
	$call( array( 'editor_post_id' => 10, 'post_type' => 'producto' ) );
	check_f7b( 2 === count( $GLOBALS['wpdb']->queries ), 'La segunda vez, del transitorio.' );
	Editor::script();
	check_f7b( array( 'elementor-editor' ) === ( $GLOBALS['scripts']['digitalisimo-filters-editor']['deps'] ?? null ) && 'defer' === $GLOBALS['scripts']['digitalisimo-filters-editor']['args']['strategy'], 'filters-editor.js sólo en el editor.' );
	$GLOBALS['caps'] = array();

	// --- Recursos: sin WooCommerce, los handles de F4-Woo con sus argumentos; con ellos ya registrados, nada. -------------
	Module::register_assets();
	$s = $GLOBALS['scripts'];
	check_f7b( array( 'elementor-frontend' ) === $s['digitalisimo-woo-filters']['deps'] && 'defer' === $s['digitalisimo-woo-filters']['args']['strategy'] && ! empty( $s['digitalisimo-woo-filters']['args']['in_footer'] ) && str_ends_with( $s['digitalisimo-woo-filters']['src'], 'assets/js/filters.js' ), 'Sin WooCommerce: el coordinador con defer y detrás de elementor-frontend.' );
	check_f7b( array( 'digitalisimo-woo-filters' ) === $s['digitalisimo-woo-filters-form']['deps'] && 'defer' === $s['digitalisimo-woo-filters-form']['args']['strategy'], 'filters-form.js detrás del coordinador, con defer.' );
	check_f7b( isset( $GLOBALS['styles']['digitalisimo-woo-filters'], $GLOBALS['styles']['digitalisimo-filters'] ) && array( 'digitalisimo-woo-filters' ) === $GLOBALS['styles']['digitalisimo-filters']['deps'], 'filters.css y la hoja propia detrás de ella.' );
	$GLOBALS['scripts'] = array( 'digitalisimo-woo-filters' => 'woo', 'digitalisimo-woo-filters-form' => 'woo' );
	$GLOBALS['styles']  = array( 'digitalisimo-woo-filters' => 'woo' );
	Module::register_assets();
	check_f7b( 'woo' === $GLOBALS['scripts']['digitalisimo-woo-filters'] && 'woo' === $GLOBALS['scripts']['digitalisimo-woo-filters-form'] && 'woo' === $GLOBALS['styles']['digitalisimo-woo-filters'] && isset( $GLOBALS['styles']['digitalisimo-filters'] ), 'Con F4-Woo, sus registros no se tocan.' );
	check_f7b( hooked( 'elementor/frontend/after_register_scripts', 11 ) >= 1 && hooked( 'wp_enqueue_scripts', 6 ) >= 1, 'Registro después del de F4-Woo (10 y 5).' );
	$sizes = array( 'modules/digitalisimo-filters/assets/f7b-filters.css' => 2048, 'assets/js/filters-form.js' => 6144, 'assets/js/filters.js' => 10240 /* C5: también la Cuadrícula de bucle propia. */, 'modules/digitalisimo-filters/assets/filters-editor.js' => 8192 );
	foreach ( $sizes as $file => $cap ) { check_f7b( filesize( $root . '/' . $file ) <= $cap, $file . ' pesa ' . filesize( $root . '/' . $file ) . ' B (tope ' . $cap . ').' ); }
	check_f7b( ! preg_match( '/url\(|#[0-9a-f]{3,6}\b|rgba?\(/i', file_get_contents( $root . '/modules/digitalisimo-filters/assets/f7b-filters.css' ) ), 'La hoja propia no define colores ni url().' );
}
namespace {
	use Digitalisimo\Elements\Filters\Module;
	use Digitalisimo\Elements\Filters\Query;
	use Digitalisimo\Elements\Filters\Types;
	use Digitalisimo\Elements\Filters\Registry;
	use Digitalisimo\Elements\Filters\Editor;
	// --- Widgets. --------------------------------------------------------------------------------------------------------
	require_once $root . '/modules/posts/traits/pagination-trait.php';
	$manager = new class() { public $types = array( 'digitalisimo-loop-orderby' => 'ya' ); public $registered = array(); public function get_widget_types( $n ) { return $this->types[ $n ] ?? null; } public function register( $w ) { $this->registered[] = $w->get_name(); } };
	$done = Registry::widgets( $manager );
	check_f7b( array( 'digitalisimo-loop-search', 'digitalisimo-loop-meta-filter', 'digitalisimo-loop-per-page' ) === $done && $done === $manager->registered, 'Registro tolerante: los que el núcleo no tiene, en la categoría propia.' );
	$classes = array();
	foreach ( Registry::WIDGETS as $name => $entry ) {
		check_f7b( is_file( $root . '/modules/digitalisimo-filters/' . $entry['file'] ) && is_file( $root . '/' . $entry['css'] ) && is_file( $root . '/' . $entry['js'] ), 'Archivos del registro: ' . $name );
		$classes[ $name ] = Registry::load( $name );
		check_f7b( $entry['class'] === $classes[ $name ], 'Clase de ' . $name );
	}
	check_f7b( in_array( 'Digitalisimo\\Elements\\Filters\\Filter_Form', class_uses( \Digitalisimo\Elements\Filters\Filter_Widget::class ), true ), 'La base neutra usa el rasgo común.' );
	require_once $root . '/modules/digitalisimo-woo/class-widget.php';
	require_once $root . '/modules/digitalisimo-woo/filters/class-filter-widget.php';
	check_f7b( in_array( 'Digitalisimo\\Elements\\Filters\\Filter_Form', class_uses( \Digitalisimo\Elements\Woo\Filters\Filter_Widget::class ), true ) && is_subclass_of( \Digitalisimo\Elements\Woo\Filters\Filter_Widget::class, \Digitalisimo\Elements\Woo\Widget::class ), 'La base de F4-Woo usa el mismo rasgo y sigue extendiendo Woo\\Widget (Inert::cooperates()).' );
	foreach ( $classes as $name => $class ) {
		$widget = new $class( array( 'id' => 'fsearch' ) );
		check_f7b( $name === $widget->get_name() && array( 'digitalisimo' ) === $widget->get_categories(), $name . ': nombre y categoría.' );
		check_f7b( array( 'digitalisimo-woo-filters', 'digitalisimo-filters' ) === $widget->get_style_depends() && array( 'digitalisimo-woo-filters', 'digitalisimo-woo-filters-form' ) === $widget->get_script_depends(), $name . ': hojas y scripts (los de F4-Woo y la propia).' );
		check_f7b( count( $widget->get_keywords() ) >= 3, $name . ': palabras clave en español.' );
		$controls = $widget->controls();
		check_f7b( 'loop' === $controls['destination']['default'] && array( '' => 'Elige un Loop Grid' ) === $controls['selected_element']['options'] && false === $controls['destination']['style_transfer'] && false === $controls['selected_element']['style_transfer'], $name . ': destino y bucle sin style_transfer.' );
		foreach ( $controls as $id => $control ) {
			$style = ! empty( $control['selectors'] ) || ! empty( $control['group'] ) || ! empty( $control['selector'] );
			if ( $style ) {
				check_f7b( ! array_key_exists( 'default', $control ), $name . ': control de estilo ' . $id . ' sin default.' );
			} elseif ( ! in_array( $control['type'] ?? '', array( 'raw_html', 'heading' ), true ) ) {
				check_f7b( false === ( $control['style_transfer'] ?? null ), $name . ': ' . $id . ' no es de estilo y declara style_transfer => false (V.12).' );
			}
			foreach ( array( 'label', 'raw', 'description', 'default', 'placeholder' ) as $key ) {
				if ( isset( $control[ $key ] ) && is_string( $control[ $key ] ) ) {
					check_f7b( false === stripos( $control[ $key ], 'elementor' ), $name . ': texto sin el nombre del núcleo: ' . $control[ $key ] );
				}
			}
		}
		// Los valores por defecto de los controles son los que aplica el servidor con los ajustes vacíos.
		check_f7b( Query::normalize( $name, $widget->get_settings_for_display() ) === Query::normalize( $name, array() ), $name . ': mismos valores por defecto en el control y en el servidor.' );
		check_f7b( '' === ( new $class( array( 'id' => 'fsearch', 'settings' => array( 'selected_element' => '' ) ) ) )->html(), $name . ': sin Loop Grid, el front no pinta nada.' );
		\Elementor\Plugin::$instance->editor->edit = true;
		check_f7b( false !== strpos( ( new $class( array( 'id' => 'fsearch', 'settings' => array( 'selected_element' => 'NO VALE' ) ) ) )->html(), 'Elige el Loop Grid que filtra este widget.' ), $name . ': en el editor, el aviso.' );
		\Elementor\Plugin::$instance->editor->edit = false;
	}
	check_f7b( array( 'date_desc', 'title_asc', 'title_desc' ) === array_column( ( new $classes['digitalisimo-loop-orderby']() )->controls()['orders']['default'], 'order_key' ), 'Órdenes por defecto (sin rand).' );
	$no_rand = ( new $classes['digitalisimo-loop-orderby']() )->controls()['orders']['fields'][0]['options'];
	check_f7b( ! isset( $no_rand['rand'] ) && 9 === count( $no_rand ), 'El repetidor de órdenes no ofrece rand.' );

	$render = function ( $class, $id, $settings ) { return ( new $class( array( 'id' => $id, 'settings' => $settings ) ) )->html(); };
	$assert_clean = function ( $html, $what ) { check_f7b( ! preg_match( '/<script|<img|ld\+json|<meta/', $html ), $what . ': sin <script>, <img>, metas ni JSON-LD.' ); };
	// Buscador, destino Loop Grid (C2-30).
	$_GET = array( 'e-filter-loop123-digi_s_fsearch' => 'zapatos rojos', 'e-filter-loop123-digi_pp_fpage01' => '24', 'e-page-loop123' => '2', 'utm_source' => 'boletín' );
	$html = $render( $classes['digitalisimo-loop-search'], 'fsearch', array( 'selected_element' => 'loop123' ) );
	check_f7b( 0 === strpos( $html, '<form class="digi-filter digi-filter--search" method="get" action="https://example.test/catalogo/" aria-label="Buscar" data-digi-filter="search" data-target="loop" data-loop="loop123"' ) && false !== strpos( $html, ' role="search" data-type="digitalisimo_search" data-key="digi_s_fsearch" data-multiple="0">' ), 'Buscador: form[role=search] con nombre, tipo y clave: ' . substr( $html, 0, 400 ) );
	check_f7b( false !== strpos( $html, '<label class="digi-filter__label" for="digi-s-fsearch">Buscar</label><input class="digi-filter__input" type="search" id="digi-s-fsearch" name="e-filter-loop123-digi_s_fsearch" value="zapatos rojos" minlength="2" maxlength="100">' ), 'Etiqueta visible unida al campo, valor de la URL, 2 a 100 caracteres.' );
	check_f7b( false !== strpos( $html, '<button type="submit" class="digi-filter__submit">Buscar</button>' ), 'Envío con botón (no al teclear).' );
	check_f7b( false !== strpos( $html, '<input type="hidden" name="e-filter-loop123-digi_pp_fpage01" value="24"><input type="hidden" name="utm_source" value="boletín">' ) && false === strpos( $html, 'name="e-page-loop123"' ), 'Conserva los demás parámetros, sin la paginación del bucle.' );
	check_f7b( false !== strpos( $html, '<a class="digi-filter__clear" rel="nofollow" href="https://example.test/catalogo/?e-filter-loop123-digi_pp_fpage01=24&#038;utm_source=bolet%C3%ADn" aria-label="Limpiar la búsqueda">Limpiar</a>' ), '«Limpiar» con nombre propio y nofollow.' );
	check_f7b( false !== strpos( $html, '<span class="e-filter" hidden data-base-url="https://example.test/catalogo/"' ) && 1 === preg_match( '/data-status="\{[^"]*Resultados actualizados[^"]*Ning\\\\u00fan resultado[^"]*\}"/', $html ), 'El span de PRO y los textos de la región viva del coordinador.' );
	$assert_clean( $html, 'Buscador' );
	$html = $render( $classes['digitalisimo-loop-search'], 'fsearch', array( 'selected_element' => 'loop123', 'show_label' => '' ) );
	check_f7b( false !== strpos( $html, '<label class="digi-filter__label digi-filter__sr" for="digi-s-fsearch">' ), 'Etiqueta oculta a la vista, no al lector.' );
	// Buscador, destino «Archivo»: el buscador nativo.
	$_GET = array( 's' => 'luz', 'utm_source' => 'x' );
	$html = $render( $classes['digitalisimo-loop-search'], 'fsearch', array( 'destination' => 'archive' ) );
	check_f7b( false !== strpos( $html, 'action="https://example.test/"' ) && false !== strpos( $html, 'data-target="archive" data-loop=""' ) && false !== strpos( $html, 'name="s" value="luz"' ) && false === strpos( $html, 'type="hidden"' ) && false === strpos( $html, 'digi-filter__clear' ) && false === strpos( $html, 'e-filter' ), 'Archivo: s hacia la página de resultados, sin campos ocultos ni span del bucle.' );
	// Campo: rango (dos campos con -max) y lista (casillas, botones con «~» o «,» y digi_f).
	$_GET = array( 'e-filter-loop123-digi_m_frange1' => '10', 'e-filter-loop123-digi_m_frange1-max' => 'abc' );
	$range_settings = array( 'selected_element' => 'loop123', 'meta_key' => 'precio', 'value_type' => 'DECIMAL', 'mode' => 'range', 'range_min' => '0', 'range_max' => '1000', 'step' => 0.5, 'legend' => 'Precio' );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'frange1', $range_settings );
	check_f7b( false !== strpos( $html, 'class="digi-filter digi-filter--range"' ) && false !== strpos( $html, 'data-digi-filter="range"' ) && false !== strpos( $html, 'data-type="digitalisimo_meta" data-key-min="digi_m_frange1" data-key-max="digi_m_frange1-max" data-apply="button" data-min="0" data-max="1000"' ), 'Rango: tipo y claves de los dos extremos para filters-form.js: ' . substr( $html, 0, 600 ) );
	check_f7b( false !== strpos( $html, 'name="e-filter-loop123-digi_m_frange1" data-digi-edge="min" value="10" min="0" max="1000" step="0.5"' ) && false !== strpos( $html, 'name="e-filter-loop123-digi_m_frange1-max" data-digi-edge="max" value="" min="0"' ), 'Los dos campos con los nombres reservados; un valor inválido de la URL no se pinta.' );
	check_f7b( false !== strpos( $html, 'aria-label="Quitar el filtro por precio"' ) && false !== strpos( $html, '<legend class="digi-filter__legend">Precio</legend>' ), 'Rango: título y «Limpiar» con nombre.' );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'frange1', array( 'value_type' => 'DATE', 'range_min' => '2026-01-01' ) + $range_settings );
	check_f7b( false !== strpos( $html, 'type="date"' ) && false !== strpos( $html, 'min="2026-01-01"' ) && false === strpos( $html, 'step=' ), 'Rango de fechas: campos de fecha.' );
	$_GET = array( 'e-filter-loop123-digi_m_fcolor1' => 'rojo~verde' );
	$list_settings = array( 'selected_element' => 'loop123', 'meta_key' => '__custom', 'meta_key_custom' => 'color', 'mode' => 'in', 'options' => array( array( 'value' => 'Rojo' ), array( 'value' => 'Nueva York', 'label' => 'NY' ), array( 'value' => 'azul' ) ), 'legend' => 'Color' );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'fcolor1', $list_settings );
	check_f7b( false !== strpos( $html, 'class="digi-filter digi-filter--meta digi-filter--checkboxes"' ) && false !== strpos( $html, 'aria-label="Filtrar por color"' ) && false !== strpos( $html, 'data-type="digitalisimo_meta" data-key="digi_m_fcolor1" data-logic="OR" data-multiple="1"' ), 'Lista: casillas con el tipo y la clave.' );
	check_f7b( false !== strpos( $html, '<input type="checkbox" name="e-filter-loop123-digi_m_fcolor1" value="rojo" checked><span>Rojo</span>' ) && false !== strpos( $html, 'value="nueva-york"><span>NY</span>' ), 'Las fichas en el valor, el texto visible en la etiqueta; una ficha no declarada no se marca.' );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'fcolor1', array( 'display' => 'buttons' ) + $list_settings );
	check_f7b( 3 === substr_count( $html, 'class="digi-filter__term" rel="nofollow" role="button"' ) && false !== strpos( $html, 'aria-pressed="true" data-value="rojo" href="https://example.test/catalogo/"' ) && false !== strpos( $html, 'aria-pressed="false" data-value="azul" href="https://example.test/catalogo/?e-filter-loop123-digi_m_fcolor1=rojo~azul"' ) && false === strpos( $html, 'digi-filter__submit' ), 'Botones: enlaces con aria-pressed, nofollow y la selección con «~».' );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'fcolor1', array( 'mode' => 'equal', 'display' => 'dropdown' ) + $list_settings );
	check_f7b( false !== strpos( $html, '<select class="digi-filter__select" name="e-filter-loop123-digi_m_fcolor1" aria-label="Color"><option value="">Cualquiera</option><option value="rojo" selected>Rojo</option>' ) && false !== strpos( $html, 'data-multiple="0"' ), 'Desplegable de un valor.' );
	check_f7b( '' === $render( $classes['digitalisimo-loop-meta-filter'], 'fsecret', array( 'meta_key' => '_secreto' ) + $list_settings ), 'Clave protegida: no pinta nada en el front.' );
	$_GET = array( 'digi_m_farch03' => 'rojo', 'digi_f' => '10-farch03', 'digi_o_farch01' => 'title_desc' );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'farch03', array( 'destination' => 'archive', 'display' => 'buttons' ) + $list_settings );
	check_f7b( false !== strpos( $html, 'data-target="archive" data-loop=""' ) && false === strpos( $html, 'class="e-filter"' ) && false !== strpos( $html, 'data-value="azul" href="https://example.test/catalogo/?digi_o_farch01=title_desc&#038;digi_f=10-farch03&#038;digi_m_farch03=rojo,azul"' ), 'Archivo: nombres sin bucle, lista con «,» y digi_f del documento y el widget: ' . $html );
	$html = $render( $classes['digitalisimo-loop-meta-filter'], 'farch03', array( 'destination' => 'archive' ) + $list_settings );
	check_f7b( 1 === substr_count( $html, 'name="digi_f"' ) && false !== strpos( $html, '<input type="hidden" name="digi_f" value="10-farch03">' ) && false !== strpos( $html, '<input type="hidden" name="digi_o_farch01" value="title_desc">' ), 'Archivo: un solo digi_f (el propio) y los parámetros de los otros widgets.' );
	// Orden y por página.
	$_GET = array( 'e-filter-loop123-digi_o_forder1' => 'title_asc' );
	$html = $render( $classes['digitalisimo-loop-orderby'], 'forder1', array( 'selected_element' => 'loop123', 'order_meta_key' => 'precio', 'orders' => array( array( 'order_key' => 'title_asc' ), array( 'order_key' => 'price_asc' ), array( 'order_key' => 'meta_desc', 'label' => 'Más caros' ) ) ) );
	check_f7b( false !== strpos( $html, '<select class="digi-filter__select" name="e-filter-loop123-digi_o_forder1" aria-label="Ordenar por"><option value="">Orden predeterminado</option><option value="title_asc" selected>Título (A-Z)</option><option value="price_asc">Precio: de menor a mayor</option><option value="meta_desc">Más caros</option></select>' ), 'Orden: desplegable con las opciones del widget (precio con WooCommerce): ' . $html );
	check_f7b( false !== strpos( $html, 'data-type="digitalisimo_orderby" data-key="digi_o_forder1"' ) && false !== strpos( $html, 'aria-label="Volver al orden predeterminado">Restablecer</a>' ), 'Orden: tipo, clave y «Restablecer» con nombre.' );
	$html = $render( $classes['digitalisimo-loop-per-page'], 'fpage01', array( 'selected_element' => 'loop123', 'display' => 'buttons', 'values' => '12, 24' ) );
	check_f7b( false !== strpos( $html, 'class="digi-filter digi-filter--per-page digi-filter--buttons"' ) && false !== strpos( $html, 'data-value="24" href="https://example.test/catalogo/?e-filter-loop123-digi_o_forder1=title_asc&#038;e-filter-loop123-digi_pp_fpage01=24"' ) && false !== strpos( $html, 'data-type="digitalisimo_per_page" data-key="digi_pp_fpage01" data-logic="OR" data-multiple="0"' ), 'Por página: botones con la cantidad y el orden conservado.' );
	foreach ( array( 'digitalisimo-loop-meta-filter', 'digitalisimo-loop-orderby', 'digitalisimo-loop-per-page' ) as $name ) {
		$assert_clean( $render( $classes[ $name ], 'forder1', array( 'selected_element' => 'loop123', 'meta_key' => 'color', 'options' => array( array( 'value' => 'a' ) ) ) ), $name );
	}
	$_GET = array();

	// --- Revisión por tokens del módulo: PHP 7.4, sin proveedores, sin jQuery, sin SELECT DISTINCT global. -------------
	$files = glob( $root . '/modules/digitalisimo-filters/*.php' );
	check_f7b( 11 === count( $files ), 'Los once archivos del módulo (' . count( $files ) . ').' );
	foreach ( $files as $file ) {
		$source = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $file ) );
		check_f7b( ! preg_match( '/\bstr_contains\(|\bstr_ends_with\(|\bmatch\s*\(|\bfn\s*\(|\?->/', $source ), basename( $file ) . ': compatible con PHP 7.4.' );
		check_f7b( false !== strpos( file_get_contents( $file ), "defined( 'ABSPATH' ) || exit;" ), basename( $file ) . ': guarda ABSPATH.' );
		check_f7b( ! preg_match( '/Automattic\\\\WooCommerce\\\\Internal|woocommerce_enable_post_clause_filtering|\bwp_die\s*\(/', $source ), basename( $file ) . ': nada interno de WooCommerce ni wp_die().' );
		check_f7b( ! preg_match( '/DISTINCT\s+meta_key\s+FROM\s+\{\$wpdb->postmeta\}(?!\s+WHERE\s+post_id\s+IN)/', $source ), basename( $file ) . ': nunca DISTINCT sobre postmeta entera.' );
		check_f7b( ! preg_match( '/\b(Bricks|Divi|Betheme|Elementor Pro)\b/', $source ), basename( $file ) . ': sin nombres de proveedores.' );
	}
	foreach ( array( 'assets/js/filters.js', 'assets/js/filters-form.js', 'modules/digitalisimo-filters/assets/filters-editor.js' ) as $file ) {
		check_f7b( ! preg_match( '/jQuery|\$\(/', file_get_contents( $root . '/' . $file ) ), $file . ': sin jQuery.' );
	}
	$loader = file_get_contents( $root . '/pro-elements.php' );
	$filters_at = strpos( $loader, 'Filters\\Module::init()' );
	check_f7b( false !== $filters_at && $filters_at < strpos( $loader, 'Woo\\Module::init()' ) && 1 === substr_count( $loader, 'modules/digitalisimo-filters/class-module.php' ), 'pro-elements.php arranca el módulo neutro una vez, antes de F4-Woo.' );

	echo 'DIGITALÍSIMO Elements: filtros ampliados de F7b (tipos con <fid>, petición forjada, otro documento u otro bucle, topes, WP_Query directo, «Archivo» con digi_f, convivencia con F4-Woo, AJAX del editor y widgets accesibles) en verde: ' . $GLOBALS['checks'] . " comprobaciones.\n";
}
