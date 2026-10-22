<?php
/**
 * F4-Woo, frente F: las correcciones declaradas del derivado de PRO `modules/loop-filter/` (V.8.2 de la especificación).
 *
 * - Sólo cambia lo declarado: deshaciendo los cuatro bloques de `module.php` y la línea de `Hierarchy_Or_Query` se
 *   obtiene, byte a byte, la sha256 de 4.3.0.164. Esa reconstrucción se carga en otro espacio de nombres y sirve de
 *   referencia para comparar con `===`.
 * - R-B3: `parse_query_string()` parte sólo por el primer guion tras el ID. `pa_talla-zapato` y `tipo-de-tela` se
 *   leen de la URL, también para el filtro de taxonomía de PRO; los nombres que ya se leían dan lo mismo que 4.3.0.164
 *   en la lectura y en `filter_loop_query()`.
 * - V-B1: con una URL que sólo trae `digi_price_min`, el `render()` real del filtro de taxonomía de PRO no lanza
 *   `TypeError` en PHP 8; sin la clave `taxonomy` inicializada, sí (se comprueba con esa línea quitada).
 * - Tipos registrados: se apartan del camino de taxonomía y se aplican una vez con su filtro; `taxonomy` y un tipo no
 *   registrado siguen el camino de siempre (`===` con 4.3.0.164).
 * - V-M2: `Hierarchy_Or_Query` de 4.3.0.164 termina en error con dos categorías hijas del mismo padre y «O»; la
 *   corregida devuelve la consulta.
 */
namespace ElementorPro\Base {
	abstract class Module_Base {
		public function __construct() {}
	}
	/** Lo que el filtro de taxonomía usa del widget base: ajustes y atributos del marcado. */
	abstract class Base_Widget {
		public $settings = array();
		private $attributes = array();
		public function __construct( array $settings = array() ) { $this->settings = $settings; }
		public function get_id() { return 'f1lter1'; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function add_render_attribute( $element, $key = null, $value = null ) {
			foreach ( is_array( $key ) ? $key : array( $key => $value ) as $name => $attr ) { $this->attributes[ $element ][ $name ] = $attr; }
		}
		public function print_render_attribute_string( $element ) {
			foreach ( $this->attributes[ $element ] ?? array() as $name => $value ) { echo ' ' . $name . '="' . esc_attr( (string) $value ) . '"'; }
		}
	}
}
namespace ElementorPro\Core {
	class Utils {
		public static function _unstable_get_super_global_value( $super_global, $key ) { return $super_global[ $key ] ?? null; }
		public static function get_current_post_id() { return 0; }
	}
}
namespace ElementorPro\Modules\LoopFilter\Data {
	class Controller { public function __construct() {} }
}
namespace ElementorPro {
	class Plugin {
		public static $loop_filter;
		public $modules_manager;
		private static $instance;
		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
				self::$instance->modules_manager = new class() { public function get_modules( $name ) { return 'loop-filter' === $name ? Plugin::$loop_filter : null; } };
			}
			return self::$instance;
		}
		public static function elementor() { return \Elementor\Plugin::$instance; }
	}
}
namespace ElementorPro\Modules\ThemeBuilder {
	class Module {
		public static function instance() { return new self(); }
		public function get_locations_manager() { return new class() { public function get_current_location() { return 'single'; } }; }
		public function get_conditions_manager() { return new class() { public function get_documents_for_location( $location ) { return array(); } }; }
	}
}
namespace Elementor {
	class Controls_Manager { const SELECT = 'select'; const SWITCHER = 'switcher'; const TEXT = 'text'; const NUMBER = 'number'; const TAB_STYLE = 'style'; }
	class Utils { public static function find_element_recursive( $elements, $id ) { return false; } }
	class Plugin { public static $instance; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$dir  = $root . '/modules/loop-filter/';

	// Avisos y deprecaciones de PHP también cuentan como fallo: el código que se prueba debe correr limpio.
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	function check_parse( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function remove_all_filters( $hook ) { unset( $GLOBALS['wp_filters'][ $hook ] ); }
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) {
			foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); }
		}
		return $value;
	}
	function wp_parse_str( $string, &$array ) { parse_str( $string, $array ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function sanitize_title( $title ) { return strtolower( trim( preg_replace( '/[^A-Za-z0-9_\-%]+/', '-', (string) $title ), '-' ) ); }
	function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_html__( $text, $domain = 'default' ) { return esc_attr( $text ); }
	function wp_doing_ajax() { return false; }
	function get_the_ID() { return 0; }
	function get_query_var( $name ) { return 0; }
	function is_page() { return true; }
	function is_single() { return false; }
	function get_permalink() { return 'https://example.test/tienda/'; }
	class WP_Error {}
	class WP_Term {
		public $term_id; public $slug; public $name; public $parent; public $taxonomy; public $count = 3;
		public function __construct( $id, $slug, $taxonomy, $parent = 0 ) { $this->term_id = $id; $this->slug = $slug; $this->name = ucfirst( $slug ); $this->taxonomy = $taxonomy; $this->parent = $parent; }
	}
	$GLOBALS['terms'] = array(
		'product_cat'     => array( new WP_Term( 10, 'ropa', 'product_cat' ), new WP_Term( 11, 'camisetas', 'product_cat', 10 ), new WP_Term( 12, 'sudaderas', 'product_cat', 10 ), new WP_Term( 13, 'zapatos', 'product_cat' ) ),
		'pa_talla-zapato' => array( new WP_Term( 20, '40', 'pa_talla-zapato' ), new WP_Term( 21, '41', 'pa_talla-zapato' ) ),
		'category'        => array( new WP_Term( 30, 'news', 'category' ) ),
		'post_tag'        => array( new WP_Term( 40, 'a', 'post_tag' ), new WP_Term( 41, 'b', 'post_tag' ) ),
	);
	function get_terms( $args ) {
		if ( ! isset( $GLOBALS['terms'][ $args['taxonomy'] ] ) ) { return new WP_Error(); }
		return array_values( array_filter( $GLOBALS['terms'][ $args['taxonomy'] ], function ( $term ) use ( $args ) { return ! isset( $args['parent'] ) || $term->parent === $args['parent']; } ) );
	}

	\Elementor\Plugin::$instance = (object) array(
		'documents' => new class() { public function get_doc_for_frontend( $id ) { return null; } },
		'editor'    => new class() { public function is_edit_mode() { return false; } },
	);

	require $root . '/modules/posts/traits/pagination-trait.php';
	foreach ( array( 'query/data/query-constants.php', 'query/interfaces/query-interface.php', 'query/query-types/single-terms-query.php', 'query/query-types/hierarchy-and-query.php', 'query/query-types/hierarchy-or-query.php', 'query/taxonomy-manager.php', 'query/taxonomy-query-builder.php', 'traits/hierarchical-taxonomy-trait.php', 'traits/taxonomy-filter-trait.php', 'module.php', 'widgets/taxonomy-filter.php' ) as $file ) {
		require $dir . $file;
	}

	// --- Sólo cambia lo declarado: deshacer los bloques da la sha256 de 4.3.0.164. ---------------------------------
	$module_src = file_get_contents( $dir . 'module.php' );
	$init_block = <<<'PHP'
		// DIGITALÍSIMO (F4-Woo, V.8.2): la clave `taxonomy` existe siempre, delante de las demás, aunque la URL o el
		// refresco sólo traigan otro tipo (un precio). El filtro de taxonomía la lee sin comprobarla
		// (`widgets/taxonomy-filter.php:765`) y, sin ella, `array_key_exists()` recibiría `null` en PHP 8.
		if ( ! is_array( $filter_data ) ) {
			$filter_data = [];
		}
		if ( ! isset( $filter_data['taxonomy'] ) ) {
			$filter_data = array_merge( [ 'taxonomy' => [] ], $filter_data );
		}


PHP;
	$types_block = <<<'PHP'
		// DIGITALÍSIMO (F4-Woo, V.8.2): los tipos registrados (precio y atributo de F4-Woo, los de F7b) se apartan y se
		// aplican al final con su filtro. `taxonomy` y cualquier tipo no registrado siguen el camino de siempre.
		$registered_filters = [];
		$registered_types = apply_filters( 'digitalisimo_elements_loop_filter_types', [] );
		foreach ( is_array( $registered_types ) ? $registered_types : [] as $registered_type ) {
			if ( is_string( $registered_type ) && 'taxonomy' !== $registered_type && isset( $filter_types[ $registered_type ] ) ) {
				$registered_filters[ $registered_type ] = $filter_types[ $registered_type ];
				unset( $filter_types[ $registered_type ] );
			}
		}


PHP;
	$apply_block = <<<'PHP'
		foreach ( $registered_filters as $registered_type => $registered_filter ) {
			$query_args = apply_filters( 'digitalisimo_elements_loop_filter_query', $query_args, $registered_type, is_array( $registered_filter ) ? $registered_filter : [], $widget );
		}


PHP;
	$explode_now = <<<'PHP'
		// DIGITALÍSIMO (F4-Woo, V.8.2): sólo por el primer guion tras el ID, así un nombre con guion
		// (`pa_talla-zapato`, `tipo-de-tela`) deja de descartarse; un nombre sin guion da lo mismo que antes.
		$filter = explode( '-', $filter, 2 );
PHP;
	$explode_was = "\t\t\$filter = explode( '-', \$filter );";
	$type_now = <<<'PHP'
			// DIGITALÍSIMO (F4-Woo, V.8.2): el tipo de cada nombre lo decide su registro (`digi_price_*`, `digiattr_*`);
			// cualquier otro nombre sigue siendo una taxonomía.
			$filter_type = apply_filters( 'digitalisimo_elements_loop_filter_param_type', 'taxonomy', $parsed_query_string['taxonomy'] );
			if ( ! is_string( $filter_type ) || '' === $filter_type ) {
				$filter_type = 'taxonomy';
			}

			$filter_data = [
				$filter_type => [
PHP;
	$type_was = "\t\t\t\$filter_data = [\n\t\t\t\t'taxonomy' => [";
	$undo = array(
		array( $init_block, '' ),
		array( $types_block, '' ),
		array( $apply_block, '' ),
		array( $explode_now, $explode_was ),
		array( $type_now, $type_was ),
	);
	$legacy_src = $module_src;
	foreach ( $undo as $index => $pair ) {
		check_parse( 1 === substr_count( $module_src, $pair[0] ), 'El bloque ' . ( $index + 1 ) . ' de V.8.2 está una sola vez en module.php.' );
		$legacy_src = str_replace( $pair[0], $pair[1], $legacy_src );
	}
	check_parse( '9d3e2e90e954d613232a2b8d506c3f841568cf982f1575c75be04f789bd828a5' === hash( 'sha256', $legacy_src ), 'module.php sólo cambia en los cuatro bloques declarados de V.8.2.' );
	check_parse( 4 === substr_count( $module_src, 'DIGITALÍSIMO' ), 'Cuatro notas DIGITALÍSIMO en module.php, una por cambio.' );
	$or_src = file_get_contents( $dir . 'query/query-types/hierarchy-or-query.php' );
	$or_fix = "\t\t// DIGITALÍSIMO (F4-Woo, V.8.2): como en Hierarchy_And_Query; sin esta línea, dos términos hijos del mismo padre\n\t\t// con «O» terminaban en un error fatal al llamar a un método sobre `null`.\n\t\t\$this->taxonomy_manager = \$taxonomy_manager;\n";
	$or_property = "\tprivate \$taxonomy_manager;\n";
	check_parse( 1 === substr_count( $or_src, $or_fix ) && 1 === substr_count( $or_src, $or_property ), 'La corrección de Hierarchy_Or_Query está una vez.' );
	$or_legacy = str_replace( array( $or_fix, $or_property ), '', $or_src );
	check_parse( '07eb57e9e04b4c372de911730317f6b48f35a3329c1ae56a67e6a95ead27ea91' === hash( 'sha256', $or_legacy ), 'hierarchy-or-query.php sólo cambia en la línea declarada.' );

	// Referencias: 4.3.0.164 y la versión sin la clave `taxonomy` inicializada, cada una en su espacio de nombres.
	$load_as = function ( $src, $namespace ) {
		$src = str_replace( 'namespace ElementorPro\\Modules\\LoopFilter;', 'namespace ElementorPro\\Modules\\LoopFilter\\' . $namespace . ';', $src );
		eval( '?>' . $src );
		return 'ElementorPro\\Modules\\LoopFilter\\' . $namespace . '\\Module';
	};
	$legacy_class  = $load_as( $legacy_src, 'Legacy164' );
	$no_init_class = $load_as( str_replace( $init_block, '', $module_src ), 'WithoutInit' );
	$current_class = 'ElementorPro\\Modules\\LoopFilter\\Module';
	$or_legacy_class = 'ElementorPro\\Modules\\LoopFilter\\Query\\QueryTypes\\Legacy164\\Hierarchy_Or_Query';
	eval( '?>' . str_replace( 'namespace ElementorPro\\Modules\\LoopFilter\\Query\\QueryTypes;', 'namespace ElementorPro\\Modules\\LoopFilter\\Query\\QueryTypes\\Legacy164;', $or_legacy ) );

	$from_url = function ( $class, $query_string ) {
		$_SERVER['QUERY_STRING'] = $query_string;
		return new $class();
	};
	$loop = new class() { public function get_id() { return 'abc1234'; } };
	$base_args = array( 'post_type' => 'product', 'posts_per_page' => 6 );

	// --- Los nombres que ya se leían: lectura y consulta `===` 4.3.0.164, sin tipos registrados. --------------------
	$legacy_fixtures = array(
		'e-filter-abc1234-product_cat=ropa',
		'e-filter-abc1234-product_cat=zapatos~ropa',
		'e-filter-abc1234-product_cat=ropa+zapatos',
		'e-filter-abc1234-product_cat=ropa&e-filter-abc1234-category=news',
		'e-filter-abc1234-post_tag=a~b&e-filter-def5678-category=news&foo=bar',
		'e-filter-abc1234-product_cat=',
		'e-filter-ABC1234-product_cat=ropa',
		'e-filter-abc123-product_cat=ropa',
		'e-filter-abc1234-digi_price_min=10',
		'e-page-abc1234=2&paged=3',
		'',
	);
	foreach ( $legacy_fixtures as $query_string ) {
		$old = $from_url( $legacy_class, $query_string );
		$new = $from_url( $current_class, $query_string );
		if ( false === strpos( $query_string, 'digi_price' ) ) {
			check_parse( $old->get_query_string_filters() === $new->get_query_string_filters(), 'Misma lectura que 4.3.0.164: ' . $query_string );
		}
		check_parse( $old->filter_loop_query( $base_args, $loop ) === $new->filter_loop_query( $base_args, $loop ), 'Misma consulta que 4.3.0.164: ' . $query_string );
	}
	// Sin tipos registrados, `digi_price_min` sigue siendo una taxonomía inexistente: el bucle sale vacío (D26), nunca de más.
	$price_as_tax = $from_url( $current_class, 'e-filter-abc1234-digi_price_min=10' )->get_query_string_filters();
	check_parse( array( 'abc1234' => array( 'taxonomy' => array( 'digi_price_min' => array( 'terms' => array( '10' ), 'logicalJoin' => 'AND' ) ) ) ) === $price_as_tax, 'Sin F4-Woo, digi_price_min se lee como taxonomía, como en 4.3.0.164.' );

	// --- R-B3: nombres con guion. ---------------------------------------------------------------------------------
	$dash = $from_url( $current_class, 'e-filter-abc1234-pa_talla-zapato=40~41&e-filter-abc1234-tipo-de-tela=lino' );
	$read = $dash->get_query_string_filters();
	check_parse( array( 'terms' => array( '40', '41' ), 'logicalJoin' => 'OR' ) === ( $read['abc1234']['taxonomy']['pa_talla-zapato'] ?? null ), 'pa_talla-zapato se lee de la URL (filtro de taxonomía de PRO).' );
	check_parse( array( 'terms' => array( 'lino' ), 'logicalJoin' => 'AND' ) === ( $read['abc1234']['taxonomy']['tipo-de-tela'] ?? null ), 'tipo-de-tela se lee de la URL.' );
	check_parse( array() === $from_url( $legacy_class, 'e-filter-abc1234-pa_talla-zapato=40~41' )->get_query_string_filters(), '4.3.0.164 descartaba el nombre con guion (el defecto que se corrige).' );
	$dash_query = $dash->filter_loop_query( $base_args, $loop );
	$tax_names  = array();
	array_walk_recursive( $dash_query['tax_query'], function ( $value, $key ) use ( &$tax_names ) { if ( 'taxonomy' === $key ) { $tax_names[] = $value; } } );
	check_parse( in_array( 'pa_talla-zapato', $tax_names, true ) && in_array( 'tipo-de-tela', $tax_names, true ), 'Los dos nombres con guion llegan a la consulta (una taxonomía inexistente da el bucle vacío, nunca de más).' );
	// Separa sólo por el primer guion: el ID tiene siempre 7 caracteres.
	$odd = $from_url( $current_class, 'e-filter-abc1234--x=1' )->get_query_string_filters();
	check_parse( isset( $odd['abc1234']['taxonomy']['-x'] ), 'Lo que sigue al primer guion es el nombre, tal cual.' );

	// Un nombre con guion reservado por F4-Woo, con el tipo que da su registro.
	add_filter( 'digitalisimo_elements_loop_filter_param_type', function ( $type, $name ) {
		if ( 0 === strpos( $name, 'digi_price_' ) ) { return 'digitalisimo_price'; }
		if ( 0 === strpos( $name, 'digiattr_' ) ) { return 'digitalisimo_attribute'; }
		return $type;
	}, 10, 2 );
	$attr = $from_url( $current_class, 'e-filter-abc1234-digiattr_pa_talla-zapato=40~41' )->get_query_string_filters();
	check_parse( array( 'taxonomy' => array(), 'digitalisimo_attribute' => array( 'digiattr_pa_talla-zapato' => array( 'terms' => array( '40', '41' ), 'logicalJoin' => 'OR' ) ) ) === $attr['abc1234'], 'digiattr_pa_talla-zapato se lee con el tipo digitalisimo_attribute.' );
	foreach ( array( 'e-filter-abc1234-product_cat=ropa', 'e-filter-abc1234-product_cat=zapatos~ropa&e-filter-abc1234-post_tag=a' ) as $query_string ) {
		check_parse( $from_url( $legacy_class, $query_string )->get_query_string_filters() === $from_url( $current_class, $query_string )->get_query_string_filters(), 'Con el registro de tipos, los nombres de PRO dan lo mismo: ' . $query_string );
	}

	// --- V-B1: precio sólo en la URL y el filtro de taxonomía de PRO en el mismo bucle, en PHP 8. -----------------
	$settings = array( 'selected_element' => 'abc1234', 'taxonomy' => 'product_cat', 'number_of_taxonomies' => '', 'show_first_item' => 'yes', 'first_item_title' => 'Todos', 'show_empty_items' => '', 'show_child_taxonomy' => '' );
	$render = function ( $module ) use ( $settings ) {
		\ElementorPro\Plugin::$loop_filter = $module;
		$widget = new \ElementorPro\Modules\LoopFilter\Widgets\Taxonomy_Filter( $settings );
		ob_start();
		try {
			$widget->render();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	};
	$price_only = $from_url( $current_class, 'e-filter-abc1234-digi_price_min=10' );
	check_parse( array( 'taxonomy' => array(), 'digitalisimo_price' => array( 'digi_price_min' => array( 'terms' => array( '10' ), 'logicalJoin' => 'AND' ) ) ) === $price_only->get_query_string_filters()['abc1234'], 'Con sólo el precio, la clave taxonomy existe (vacía) delante del precio.' );
	$html = $render( $price_only );
	check_parse( false !== strpos( $html, 'class="e-filter"' ) && 3 === substr_count( $html, 'class="e-filter-item"' ) && 1 === substr_count( $html, 'aria-pressed="true"' ) && false !== strpos( $html, 'data-filter="__all" aria-pressed="true"' ), 'El filtro de PRO se pinta sin error y con «Todos» activo.' );
	$both = $from_url( $current_class, 'e-filter-abc1234-digi_price_min=10&e-filter-abc1234-product_cat=ropa' );
	$html = $render( $both );
	check_parse( false !== strpos( $html, 'data-filter="ropa" aria-pressed="true"' ) && false !== strpos( $html, 'data-filter="__all" aria-pressed="false"' ), 'Precio y taxonomía en el mismo bucle: el término de la URL queda activo.' );
	$reversed = $from_url( $current_class, 'e-filter-abc1234-product_cat=ropa&e-filter-abc1234-digi_price_min=10' );
	check_parse( false !== strpos( $render( $reversed ), 'data-filter="ropa" aria-pressed="true"' ), 'También en el otro orden.' );
	// Por el refresco: los filtros del cuerpo de la petición pasan por el mismo punto.
	$rest = $from_url( $current_class, '' );
	$rest->register_widget_filter( 'abc1234', array( 'digitalisimo_price' => array( 'digi_price_max' => array( 'terms' => array( '50' ) ) ) ) );
	check_parse( array( 'taxonomy', 'digitalisimo_price' ) === array_keys( $rest->get_query_string_filters()['abc1234'] ), 'register_widget_filter() pone la clave taxonomy también en el refresco.' );
	check_parse( false !== strpos( $render( $rest ), 'data-filter="__all" aria-pressed="true"' ), 'Sin error tras un refresco con sólo el precio.' );
	// Sin la línea de V.8.2, PHP 8 lanza el TypeError de la crítica: la línea es la que lo evita.
	$failed = '';
	set_error_handler( function () { return true; } ); // El aviso de la clave ausente precede al TypeError.
	try {
		$render( $from_url( $no_init_class, 'e-filter-abc1234-digi_price_min=10' ) );
	} catch ( \TypeError $error ) {
		$failed = $error->getMessage();
	} finally {
		restore_error_handler();
	}
	check_parse( false !== strpos( $failed, 'array_key_exists' ), 'Sin inicializar la clave taxonomy, el render de PRO lanza TypeError (V-B1).' );

	// --- Tipos registrados: se apartan y se aplican una vez; taxonomy y lo no registrado, como siempre. -----------
	$calls = array();
	add_filter( 'digitalisimo_elements_loop_filter_types', function ( $types ) { return array_merge( $types, array( 'digitalisimo_price', 'taxonomy', 5, '' ) ); } );
	add_filter( 'digitalisimo_elements_loop_filter_query', function ( $args, $type, $filters, $widget ) use ( &$calls ) {
		$calls[] = array( $type, $filters, $widget->get_id() );
		$args['digitalisimo_price_range'] = $filters;
		return $args;
	}, 10, 4 );
	$tax  = array( 'product_cat' => array( 'terms' => array( 'zapatos' ), 'logicalJoin' => 'AND' ) );
	$old  = $from_url( $legacy_class, '' );
	$old->register_widget_filter( 'abc1234', array( 'taxonomy' => $tax ) );
	$new = $from_url( $current_class, '' );
	$new->register_widget_filter( 'abc1234', array( 'taxonomy' => $tax, 'digitalisimo_price' => array( 'digi_price_min' => array( 'terms' => array( '10' ) ) ) ) );
	$expected = $old->filter_loop_query( $base_args, $loop );
	$got      = $new->filter_loop_query( $base_args, $loop );
	check_parse( array( array( 'digitalisimo_price', array( 'digi_price_min' => array( 'terms' => array( '10' ) ) ), 'abc1234' ) ) === $calls, 'El tipo registrado se aplica una vez, con sus filtros y el widget.' );
	check_parse( $expected['tax_query'] === $got['tax_query'] && array( 'digi_price_min' => array( 'terms' => array( '10' ) ) ) === $got['digitalisimo_price_range'], 'El precio no entra en tax_query y la taxonomía da lo mismo que 4.3.0.164.' );
	$calls = array();
	foreach ( array( array( 'foo' => array( 'category' => array( 'terms' => array( 'news' ), 'logicalJoin' => 'AND' ) ) ), array( 'taxonomy' => $tax, 'foo' => array( 'category' => array( 'terms' => array( 'news' ), 'logicalJoin' => 'AND' ) ) ), array( 'foo' => $tax, 'taxonomy' => array( 'category' => array( 'terms' => array( 'news' ), 'logicalJoin' => 'AND' ) ) ) ) as $body ) {
		$old = $from_url( $legacy_class, '' );
		$old->register_widget_filter( 'abc1234', $body );
		$new = $from_url( $current_class, '' );
		$new->register_widget_filter( 'abc1234', $body );
		check_parse( $old->filter_loop_query( $base_args, $loop ) === $new->filter_loop_query( $base_args, $loop ), 'Un tipo no registrado sigue el camino de siempre: ' . json_encode( array_keys( $body ) ) );
	}
	check_parse( array() === $calls, 'Sin el tipo registrado en la petición, el filtro de tipos no se llama.' );
	remove_all_filters( 'digitalisimo_elements_loop_filter_types' );
	remove_all_filters( 'digitalisimo_elements_loop_filter_query' );

	// --- V-M2: dos categorías hijas del mismo padre con «O». -------------------------------------------------------
	$manager = new \ElementorPro\Modules\LoopFilter\Query\Taxonomy_Manager();
	$manager->get_taxonomy_terms( 'product_cat' );
	$selected = $manager->get_hierarchy_of_selected_terms( array( 'terms' => array( 'camisetas', 'sudaderas' ), 'logicalJoin' => 'OR' ), 'product_cat' );
	check_parse( array( 'ropa' => array( 'camisetas', 'sudaderas' ) ) === $selected['hierarchical-terms'], 'Fixture: dos hijas del mismo padre.' );
	$legacy_error = '';
	set_error_handler( function () { return true; } ); // Antes del Error, PHP avisa de la propiedad inexistente.
	try {
		( new $or_legacy_class( $selected, $manager ) )->get_query();
	} catch ( \Error $error ) {
		$legacy_error = $error->getMessage();
	} finally {
		restore_error_handler();
	}
	check_parse( false !== strpos( $legacy_error, 'is_parent_term_with_children() on null' ), 'Hierarchy_Or_Query de 4.3.0.164 termina en error fatal con dos hijas y «O»: el defecto se confirma.' );
	$fixed = ( new \ElementorPro\Modules\LoopFilter\Query\QueryTypes\Hierarchy_Or_Query( $selected, $manager ) )->get_query();
	check_parse( array( array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'camisetas' ), 'operator' => 'IN' ), array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'sudaderas' ), 'operator' => 'IN' ), 'relation' => 'OR' ) ) === $fixed, 'La consulta corregida une las dos hijas con «O».' );
	$or_loop = $from_url( $current_class, 'e-filter-abc1234-product_cat=camisetas~sudaderas' )->filter_loop_query( $base_args, $loop );
	check_parse( 'OR' === ( $or_loop['tax_query'][0]['relation'] ?? '' ), 'Desde la URL, el bucle se filtra sin error.' );

	echo "DIGITALÍSIMO Elements: loop-filter lee nombres con guion, inicializa la clave taxonomy (sin TypeError en PHP 8), aplica los tipos registrados y corrige Hierarchy_Or_Query; lo demás, igual que 4.3.0.164.\n";
}
