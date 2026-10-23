<?php
/**
 * F4-Woo, frente F: filtros de precio y de atributo (V.8 de la especificación), con dobles de WordPress y WooCommerce
 * y las clases reales de F, del frente A (`Woo\Module::requires()`, `Woo\Widget`, `Inert`), de D (`Swatches\Data`) y
 * de PRO (`modules/loop-filter/module.php`, `Pagination_Trait`).
 *
 * - Registro de tipos: nombres reservados por prefijo, `taxonomy` intocable, sin efecto con `include_field_ids_arg`.
 * - Del URL al bucle: `module.php` real con los tipos registrados (precio al revés, atributo con guion).
 * - Precio: validación (vacío, no numérico, negativo, > 10⁹, al revés, coma, decimales), condición de solapamiento,
 *   ajuste de impuestos y JOIN con el alias y la guarda de WooCommerce, también con la ordenación por precio en los dos
 *   órdenes (V-I19).
 * - Atributo: con la tabla de atributos (regla de `Filterer`: `in_stock`, «O», «Y» con `HAVING`, `1=0`) y sin ella
 *   (`tax_query` de `WC_Query::get_tax_query()`); un nombre que no es un atributo deja el bucle vacío.
 * - «Tienda»: parámetros nativos desde `$_GET` saneado; nada engancha `woocommerce_enable_post_clause_filtering`.
 * - Widgets: marcado (`.e-filter` oculto, campos ocultos, `nofollow`, `aria-hidden` de los deslizadores con «Ambos»),
 *   controles sin WooCommerce en la pila optimizada y sin `default` que pinte en los de estilo.
 */
namespace Elementor {
	class Controls_Manager {
		const SELECT = 'select'; const TEXT = 'text'; const NUMBER = 'number'; const SWITCHER = 'switcher'; const RAW_HTML = 'raw_html';
		const COLOR = 'color'; const SLIDER = 'slider'; const HEADING = 'heading'; const TAB_STYLE = 'style';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	/** Lo que los widgets usan del núcleo: ajustes, controles y render. */
	abstract class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $sections = array();
		public function __construct( array $data = array() ) { $this->settings = $data; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; }
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
	class Performance { public static $optimize = false; public static function should_optimize_controls() { return self::$optimize; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'WC_VERSION', '11.2.1' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	function check_filters( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	class WooCommerce {}
	function WC() { static $wc; if ( ! $wc ) { $wc = new stdClass(); $wc->customer = new stdClass(); } return $wc; }
	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function has_filter( $hook ) { return ! empty( $GLOBALS['wp_filters'][ $hook ] ); }
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
		return $value;
	}
	function do_action( $hook, ...$args ) { apply_filters( $hook, null, ...$args ); }
	$GLOBALS['options'] = array( 'woocommerce_attribute_lookup_enabled' => 'no', 'woocommerce_hide_out_of_stock_items' => 'no', 'woocommerce_tax_display_shop' => 'excl' );
	function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
	function wp_load_alloptions() { return $GLOBALS['options']; }
	$GLOBALS['transients'] = array();
	function get_transient( $name ) { return $GLOBALS['transients'][ $name ] ?? false; }
	function set_transient( $name, $value, $ttl = 0 ) { $GLOBALS['transients'][ $name ] = $value; return true; }
	function delete_transient( $name ) { unset( $GLOBALS['transients'][ $name ] ); return true; }
	function sanitize_title( $title ) { return strtolower( trim( preg_replace( '/[^A-Za-z0-9_\-%]+/', '-', (string) $title ), '-' ) ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function sanitize_hex_color( $color ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : ''; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function wp_unslash( $value ) { return stripslashes( $value ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_parse_str( $string, &$array ) { parse_str( $string, $array ); }
	function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
	function wp_list_pluck( $list, $field ) { return array_map( function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list ); }
	function absint( $value ) { return abs( (int) $value ); }
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	$GLOBALS['scripts'] = array();
	function wp_script_is( $handle, $list ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
	function wp_register_script( $handle, $src, $deps, $ver, $args ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'args' ); return true; }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'args' ) + array( 'enqueued' => true ); }
	$GLOBALS['query_vars'] = array();
	function get_query_var( $name ) { return $GLOBALS['query_vars'][ $name ] ?? 0; }
	function is_page() { return true; }
	function is_single() { return false; }
	function get_permalink() { return $GLOBALS['permalink'] ?? 'https://example.test/tienda/'; }
	function taxonomy_exists( $taxonomy ) { return isset( $GLOBALS['terms'][ $taxonomy ] ) || in_array( $taxonomy, array( 'product_cat' ), true ); }
	function get_term_meta( $id, $key, $single ) { return $GLOBALS['termmeta'][ $id ][ $key ] ?? ''; }
	function update_termmeta_cache( $ids ) { $GLOBALS['primed'][] = $ids; }
	function wp_attachment_is_image( $id ) { return 99 === (int) $id; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img src="https://example.test/' . (int) $id . '.jpg" alt="' . esc_attr( $attrs['alt'] ) . '" decoding="async"' . ( isset( $attrs['aria-hidden'] ) ? ' aria-hidden="true"' : '' ) . '>'; }
	class WP_Term { public $term_id; public $slug; public $name; public $taxonomy; public $parent = 0; public $count = 1; public function __construct( $id, $slug, $name, $taxonomy ) { $this->term_id = $id; $this->slug = $slug; $this->name = $name; $this->taxonomy = $taxonomy; } }
	$GLOBALS['terms'] = array(
		'pa_color'        => array( new WP_Term( 1, 'rojo', 'Rojo', 'pa_color' ), new WP_Term( 2, 'azul', 'Azul', 'pa_color' ), new WP_Term( 3, 'verde', 'Verde', 'pa_color' ) ),
		'pa_talla-zapato' => array( new WP_Term( 7, '40', '40', 'pa_talla-zapato' ), new WP_Term( 8, '41', '41', 'pa_talla-zapato' ) ),
	);
	$GLOBALS['empty_terms'] = array( 3 );
	$GLOBALS['get_terms_calls'] = array();
	function get_terms( $args ) {
		$GLOBALS['get_terms_calls'][] = $args;
		$terms = $GLOBALS['terms'][ $args['taxonomy'] ] ?? null;
		if ( null === $terms ) { return new WP_Error(); }
		if ( isset( $args['slug'] ) ) { $terms = array_filter( $terms, function ( $t ) use ( $args ) { return in_array( $t->slug, (array) $args['slug'], true ); } ); }
		if ( ! empty( $args['hide_empty'] ) ) { $terms = array_filter( $terms, function ( $t ) { return ! in_array( $t->term_id, $GLOBALS['empty_terms'], true ); } ); }
		$terms = array_values( $terms );
		return 'ids' === ( $args['fields'] ?? '' ) ? wp_list_pluck( $terms, 'term_id' ) : $terms;
	}
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
	class WP_Error {}
	function wc_get_attribute_taxonomies() { return array( 'id:1' => (object) array( 'attribute_name' => 'color', 'attribute_label' => 'Color' ), 'id:2' => (object) array( 'attribute_name' => 'talla-zapato', 'attribute_label' => 'Talla de zapato' ), 'id:3' => (object) array( 'attribute_name' => 'tamaño', 'attribute_label' => 'Tamaño' ) ); }
	function wc_attribute_taxonomy_name( $name ) { return 'pa_' . $name; }
	function wc_get_attribute_taxonomy_names() { return array_map( function ( $a ) { return 'pa_' . $a->attribute_name; }, array_values( wc_get_attribute_taxonomies() ) ); }
	function wc_attribute_label( $taxonomy ) { return 'pa_color' === $taxonomy ? 'Color' : 'Talla de zapato'; }
	function wc_get_price_decimals() { return 2; }
	function wc_get_price_decimal_separator() { return ','; }
	function wc_get_price_thousand_separator() { return '.'; }
	function get_woocommerce_currency_symbol() { return '&euro;'; }
	function get_woocommerce_price_format() { return '%2$s&nbsp;%1$s'; }
	function wc_price( $value ) { return '<span class="amount"><bdi>' . number_format( (float) $value, 2, ',', '.' ) . '&nbsp;<span>&euro;</span></bdi></span>'; }
	function wc_tax_enabled() { return 'yes' === get_option( 'woocommerce_calc_taxes' ); }
	function wc_prices_include_tax() { return false; }
	class WC_Tax {
		public static function get_rates( $class = '' ) { return array( 1 => array( 'rate' => 21 ) ); }
		public static function calc_inclusive_tax( $price, $rates ) { return array( 1 => $price - $price / 1.21 ); }
		public static function calc_exclusive_tax( $price, $rates ) { return array( 1 => $price * 0.21 ); }
		public static function get_tax_total( $taxes ) { return array_sum( $taxes ); }
	}
	/** $wpdb con prepare() como el de WordPress (`%f` sin configuración regional) y consultas registradas. */
	class WPDB_Double {
		public $prefix = 'wp_'; public $posts = 'wp_posts'; public $wc_product_meta_lookup = 'wp_wc_product_meta_lookup'; public $queries = array(); public $row = null; public $col = array();
		public function prepare( $query, ...$args ) { $query = str_replace( array( '%f', '%s', '%d' ), array( '%F', "'%s'", '%d' ), $query ); return vsprintf( $query, $args ); }
		public function get_row( $sql ) { $this->queries[] = $sql; return $this->row; }
		public function get_col( $sql ) { $this->queries[] = $sql; return $this->col; }
	}
	$GLOBALS['wpdb'] = new WPDB_Double();
	class WP_Query_Double { public $vars; public function __construct( $vars ) { $this->vars = $vars; } public function get( $key ) { return $this->vars[ $key ] ?? ''; } }

	// Las clases reales.
	require $root . '/modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();
	foreach ( array( 'class-inert.php', 'class-widget.php', 'swatches/class-data.php', 'filters/class-types.php', 'filters/class-query.php', 'filters/class-bounds.php', 'filters/class-module.php' ) as $file ) {
		require_once $root . '/modules/digitalisimo-woo/' . $file;
	}
	$types  = \Digitalisimo\Elements\Woo\Filters\Types::class;
	$query  = \Digitalisimo\Elements\Woo\Filters\Query::class;
	$bounds = \Digitalisimo\Elements\Woo\Filters\Bounds::class;
	$module = \Digitalisimo\Elements\Woo\Filters\Module::class;

	// --- Arranque: tipos, invalidación y scripts; con un símbolo ausente, nada. ------------------------------------
	$module::init();
	$module::init();
	check_filters( array( 'digitalisimo_price' => 'digi_price_', 'digitalisimo_attribute' => 'digiattr_' ) === $types::registered(), 'Los dos tipos con sus prefijos reservados.' );
	foreach ( array( 'digitalisimo_elements_loop_filter_types', 'digitalisimo_elements_loop_filter_param_type', 'digitalisimo_elements_loop_filter_query' ) as $hook ) {
		check_filters( 1 === count( $GLOBALS['wp_filters'][ $hook ][10] ?? array() ), 'Un solo enganche en ' . $hook );
	}
	foreach ( $bounds::EVENTS as $event ) { check_filters( has_filter( $event ), 'Los límites se invalidan con ' . $event ); }
	check_filters( ! has_filter( 'posts_clauses' ) && ! has_filter( 'woocommerce_enable_post_clause_filtering' ), 'Sin un bucle filtrado no se engancha posts_clauses; nunca woocommerce_enable_post_clause_filtering.' );
	$module::register_scripts();
	$form_script = $GLOBALS['scripts']['digitalisimo-woo-filters-form'] ?? array();
	check_filters( array( 'digitalisimo-woo-filters' ) === ( $form_script['deps'] ?? null ) && 'defer' === ( $form_script['args']['strategy'] ?? '' ) && ! empty( $form_script['args']['in_footer'] ), 'filters-form.js va con defer, en el pie y detrás del coordinador.' );
	$module::editor_script();
	check_filters( array( 'elementor-editor' ) === ( $GLOBALS['scripts']['digitalisimo-woo-filters-editor']['deps'] ?? null ) && ! empty( $GLOBALS['scripts']['digitalisimo-woo-filters-editor']['enqueued'] ), 'filters-editor.js sólo en el editor.' );
	$module_src = file_get_contents( $root . '/modules/digitalisimo-woo/filters/class-module.php' );
	check_filters( 1 === preg_match( "/Woo::requires\( 'filters', \\\$symbols \)/", $module_src ) && strpos( $module_src, 'return;' ) < strpos( $module_src, 'Types::register(' ), 'Con un símbolo ausente, Module::init() vuelve antes de registrar nada.' );

	// --- Tipos: prefijos, taxonomy intocable, registro inválido. --------------------------------------------------
	check_filters( 'digitalisimo_price' === $types::param_type( 'taxonomy', 'digi_price_min' ) && 'digitalisimo_attribute' === $types::param_type( 'taxonomy', 'digiattr_pa_talla-zapato' ), 'Los nombres reservados dan su tipo.' );
	check_filters( 'taxonomy' === $types::param_type( 'taxonomy', 'product_cat' ) && 'taxonomy' === $types::param_type( 'taxonomy', 'digi_price_' ) && 'taxonomy' === $types::param_type( 'taxonomy', 'pa_color' ), 'Otro nombre sigue siendo una taxonomía (un prefijo solo, también).' );
	check_filters( 'otro' === $types::param_type( 'otro', 'digi_price_min' ), 'Un tipo ya decidido por otro registro no se toca.' );
	$types::register( 'taxonomy', 'tax_', function ( $a ) { return array(); } );
	$types::register( 'Mal tipo', 'x_', function ( $a ) { return array(); } );
	$types::register( 'sin_prefijo', 'sin', function ( $a ) { return array(); } );
	check_filters( 2 === count( $types::registered() ), 'taxonomy, un nombre inválido o un prefijo sin guion bajo no se registran.' );
	check_filters( array( 'otro', 'digitalisimo_price', 'digitalisimo_attribute' ) === $types::types( array( 'otro', 'digitalisimo_price' ) ), 'La lista de tipos se suma a la de otros registros sin repetir.' );
	check_filters( array( 'x' => 1 ) === $types::query( array( 'x' => 1 ), 'desconocido', array(), null ) && 'no' === $types::query( 'no', 'digitalisimo_price', array(), null ), 'Un tipo ajeno o unos argumentos raros pasan sin cambios.' );
	$GLOBALS['wp_query'] = (object) array( 'include_field_ids_arg' => true );
	check_filters( array( 'x' => 1 ) === $types::query( array( 'x' => 1 ), 'digitalisimo_price', array( 'digi_price_min' => array( 'terms' => array( '5' ) ) ), null ), 'Al calcular los términos del filtro de PRO (include_field_ids_arg), los tipos no se aplican.' );
	$GLOBALS['wp_query'] = (object) array();

	// --- Precio: validación. --------------------------------------------------------------------------------------
	$price = function ( $min, $max ) use ( $query ) {
		$filters = array();
		if ( null !== $min ) { $filters['digi_price_min'] = array( 'terms' => array( $min ), 'logicalJoin' => 'AND' ); }
		if ( null !== $max ) { $filters['digi_price_max'] = array( 'terms' => array( $max ), 'logicalJoin' => 'AND' ); }
		return $query::price_range( $filters );
	};
	check_filters( array( 'min' => 10.0, 'max' => 50.0 ) === $price( '10', '50' ), 'Dos extremos válidos.' );
	check_filters( array( 'min' => 5.0, 'max' => 20.5 ) === $price( '20.5', '5' ), 'Al revés se intercambian.' );
	check_filters( array( 'min' => 10.5, 'max' => null ) === $price( '10,5', '' ), 'Coma decimal y extremo vacío.' );
	check_filters( array( 'min' => 10.13, 'max' => null ) === $price( '10.129', 'abc' ), 'Decimales de la tienda; lo no numérico no filtra.' );
	check_filters( null === $price( '-1', '1e3' ) && null === $price( '1000000001', '' ) && null === $price( array( '1' ), '' ) && null === $price( '', '' ), 'Negativo, exponente, más de 10⁹, arreglo o vacío: sin filtro.' );
	check_filters( array( 'min' => null, 'max' => 1000000000.0 ) === $price( null, '1000000000' ), 'El tope de 10⁹ vale.' );
	$args = $query::apply_price( array( 'post_type' => 'product' ), array( 'digi_price_min' => array( 'terms' => array( 'x' ) ) ) );
	check_filters( array( 'post_type' => 'product' ) === $args && ! has_filter( 'posts_clauses' ), 'Sin un extremo válido, la consulta no cambia ni se engancha nada.' );
	$args = $query::apply_price( array( 'post_type' => 'product' ), array( 'digi_price_max' => array( 'terms' => '50' ) ) );
	check_filters( array( 'min' => null, 'max' => 50.0 ) === $args['digitalisimo_price_range'] && 1 === count( $GLOBALS['wp_filters']['posts_clauses'][10] ?? array() ), 'La variable propia y un solo posts_clauses.' );
	$query::apply_price( array(), array( 'digi_price_max' => array( 'terms' => '60' ) ) );
	check_filters( 1 === count( $GLOBALS['wp_filters']['posts_clauses'][10] ), 'posts_clauses se engancha una sola vez.' );

	// --- Precio: cláusulas, JOIN con alias y guarda, ordenación por precio en los dos órdenes. ---------------------
	$wc_order = function ( $clauses ) {
		// `WC_Query::order_by_price_asc_post_clauses()` con la guarda de `ProductUtil::append_product_sorting_table_join()` (11.2.1).
		global $wpdb;
		if ( ! preg_match( '/\bwc_product_meta_lookup\b/', $clauses['join'] ) ) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON $wpdb->posts.ID = wc_product_meta_lookup.product_id ";
		}
		$clauses['orderby'] = ' wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC ';
		return $clauses;
	};
	$empty    = array( 'join' => '', 'where' => ' AND 1=1', 'orderby' => '' );
	$filtered = new WP_Query_Double( array( 'digitalisimo_price_range' => array( 'min' => 10.0, 'max' => 50.0 ) ) );
	$mine     = $query::clauses( $empty, $filtered );
	check_filters( 1 === substr_count( $mine['join'], 'wc_product_meta_lookup ON' ) && false !== strpos( $mine['join'], 'LEFT JOIN wp_wc_product_meta_lookup wc_product_meta_lookup ON wp_posts.ID = wc_product_meta_lookup.product_id' ), 'JOIN con el alias de WooCommerce.' );
	check_filters( ' AND 1=1 AND NOT (50.000000<wc_product_meta_lookup.min_price OR 10.000000>wc_product_meta_lookup.max_price ) ' === $mine['where'], 'Condición de solapamiento de price_filter_post_clauses(): ' . $mine['where'] );
	$after  = $wc_order( $mine );
	$before = $query::clauses( $wc_order( $empty ), $filtered );
	check_filters( 1 === substr_count( $after['join'], 'JOIN' ) && 1 === substr_count( $before['join'], 'JOIN' ), 'Con la ordenación por precio antes o después, el JOIN está una sola vez (V-I19).' );
	check_filters( $before['where'] === $mine['where'] && $after['orderby'] === $before['orderby'], 'Mismo filtro y misma ordenación en los dos órdenes.' );
	$open = $query::clauses( $empty, new WP_Query_Double( array( 'digitalisimo_price_range' => array( 'min' => 10.0, 'max' => null ) ) ) );
	check_filters( false !== strpos( $open['where'], sprintf( '%F<', (float) PHP_INT_MAX ) ), 'Sin máximo, PHP_INT_MAX como WooCommerce.' );
	check_filters( $empty === $query::clauses( $empty, new WP_Query_Double( array() ) ) && 'x' === $query::clauses( 'x', $filtered ), 'Sin la variable propia, las cláusulas no cambian.' );
	check_filters( 'JOIN x ON wp_posts.ID = wc_product_meta_lookup.product_id' === trim( $query::append_join( 'JOIN x ON wp_posts.ID = wc_product_meta_lookup.product_id' ) ), 'Una mención del alias basta para no repetir el JOIN (la misma guarda).' );
	check_filters( 1 === substr_count( $query::append_join( null ), 'LEFT JOIN' ), 'Una cláusula que no es texto se trata como vacía.' );
	// Impuestos: la tienda guarda sin impuestos y los muestra con ellos (21 %).
	$GLOBALS['options']['woocommerce_calc_taxes']       = 'yes';
	$GLOBALS['options']['woocommerce_tax_display_shop'] = 'incl';
	$taxed = $query::clauses( $empty, new WP_Query_Double( array( 'digitalisimo_price_range' => array( 'min' => 12.1, 'max' => 121.0 ) ) ) );
	check_filters( false !== strpos( $taxed['where'], 'NOT (100.000000<' ) && false !== strpos( $taxed['where'], 'OR 10.000000>' ), 'Ajuste de impuestos de WooCommerce: ' . $taxed['where'] );
	$GLOBALS['options']['woocommerce_calc_taxes']       = 'no';
	$GLOBALS['options']['woocommerce_tax_display_shop'] = 'excl';

	// --- Atributo: sin la tabla (tax_query nativo) y con ella (regla de Filterer). --------------------------------
	$attr = function ( $filters, $args = array( 'tax_query' => array( 'relation' => 'AND' ) ) ) use ( $query ) { return $query::apply_attribute( $args, $filters ); };
	$or   = array( 'digiattr_pa_color' => array( 'terms' => array( 'rojo', 'Azul', 'rojo' ), 'logicalJoin' => 'OR' ) );
	$got  = $attr( $or );
	check_filters( array( 'relation' => 'AND', array( 'taxonomy' => 'pa_color', 'field' => 'slug', 'terms' => array( 'rojo', 'azul' ), 'operator' => 'IN', 'include_children' => false ) ) === $got['tax_query'], 'Sin la tabla, el tax_query de WC_Query::get_tax_query() con «O».' );
	$and = $attr( array( 'digiattr_pa_talla-zapato' => array( 'terms' => array( '40', '41' ), 'logicalJoin' => 'AND' ) ) );
	check_filters( 'AND' === $and['tax_query'][0]['operator'] && 'pa_talla-zapato' === $and['tax_query'][0]['taxonomy'], 'Con «Y», operador AND; el atributo con guion vale.' );
	check_filters( array( 0 ) === ( $attr( array( 'digiattr_pa_noexiste' => array( 'terms' => array( 'x' ) ) ) )['post__in'] ?? null ) && array( 0 ) === ( $attr( array( 'digiattr_product_cat' => array( 'terms' => array( 'ropa' ) ) ) )['post__in'] ?? null ), 'Un nombre que no es un atributo deja el bucle vacío: falla cerrado.' );
	check_filters( array( 'tax_query' => array( 'relation' => 'AND' ) ) === $attr( array( 'digiattr_pa_color' => array( 'terms' => array() ) ) ), 'Sin términos, nada.' );
	$no_tax = $attr( $or, array( 'post_type' => 'product' ) );
	check_filters( 'AND' === $no_tax['tax_query']['relation'] && 'pa_color' === $no_tax['tax_query'][0]['taxonomy'], 'Sin tax_query previo, se crea con relación AND.' );
	$GLOBALS['options']['woocommerce_attribute_lookup_enabled'] = 'yes';
	$lookup = $attr( $or );
	check_filters( array( 'pa_color' => array( 'terms' => array( 'rojo', 'azul' ), 'query_type' => 'or' ) ) === $lookup['digitalisimo_attribute_filter'] && array( 'relation' => 'AND' ) === $lookup['tax_query'], 'Con la tabla, la variable propia y ningún tax_query.' );
	$where = function ( $chosen ) use ( $query ) { $c = $query::clauses( array( 'join' => '', 'where' => '' ), new WP_Query_Double( array( 'digitalisimo_attribute_filter' => $chosen ) ) ); return preg_replace( '/\s+/', ' ', $c['where'] ); };
	$or_where = $where( $lookup['digitalisimo_attribute_filter'] );
	check_filters( ' AND ( wp_posts.ID IN ( SELECT product_or_parent_id FROM ( SELECT product_or_parent_id FROM wp_wc_product_attributes_lookup lt WHERE term_id in (1,2) ) temp ))' === $or_where, 'Regla de Filterer con «O»: ' . $or_where );
	$GLOBALS['options']['woocommerce_hide_out_of_stock_items'] = 'yes';
	check_filters( false !== strpos( $where( $lookup['digitalisimo_attribute_filter'] ), 'WHERE term_id in (1,2) AND in_stock = 1 )' ), 'Con «Ocultar agotados», in_stock = 1 por variación.' );
	add_filter( 'woocommerce_product_attributes_filterer_hide_out_of_stock', function () { return false; } );
	check_filters( false === strpos( $where( $lookup['digitalisimo_attribute_filter'] ), 'in_stock' ), 'El filtro de WooCommerce decide como en la tienda.' );
	unset( $GLOBALS['wp_filters']['woocommerce_product_attributes_filterer_hide_out_of_stock'] );
	$and_where = $where( array( 'pa_color' => array( 'terms' => array( 'rojo', 'azul' ), 'query_type' => 'and' ), 'pa_talla-zapato' => array( 'terms' => array( '40' ), 'query_type' => 'or' ) ) );
	check_filters( false !== strpos( $and_where, 'WHERE term_id in (7) AND in_stock = 1 )' ) && false !== strpos( $and_where, 'WHERE is_variation_attribute=0 AND in_stock = 1 AND term_id in (1,2) GROUP BY product_id HAVING COUNT(product_id)=2 UNION SELECT product_or_parent_id FROM wp_wc_product_attributes_lookup lt WHERE is_variation_attribute=1 AND in_stock = 1 AND term_id in (1,2) GROUP BY product_or_parent_id HAVING COUNT(DISTINCT term_id)=2 )' ) && 1 === substr_count( $and_where, ' temp ) AND ' ), '«Y» con HAVING COUNT en los dos ramales, unida a la otra condición: ' . $and_where );
	check_filters( ' AND 1=0' === $where( array( 'pa_color' => array( 'terms' => array( 'morado' ), 'query_type' => 'or' ) ) ), 'Términos que no existen: 1=0, como Filterer.' );
	$GLOBALS['options']['woocommerce_hide_out_of_stock_items']  = 'no';
	$GLOBALS['options']['woocommerce_attribute_lookup_enabled'] = 'no';

	// --- Del URL al bucle con el módulo real de PRO (los tipos registrados). --------------------------------------
	require_once __DIR__ . '/elements-loop-filter-doubles.inc';
}
