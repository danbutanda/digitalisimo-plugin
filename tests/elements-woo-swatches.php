<?php
/**
 * F4-Woo, frente D: muestras de variación (V.6).
 *
 * Con las clases reales del frente A (`Woo\Module` y su autocarga, `Assets`, `Widget`, `Inert`) y dobles de WordPress,
 * WooCommerce y el constructor. Comprueba:
 * - las metas `color` e `image` con el formato de WooCommerce 11.2.1 (*fixtures* del frente 0,
 *   `tests/fixtures/woo-visual-11.2.1.json`): escritura excluyente, lectura imagen → color, sin canal alfa;
 * - la opción `digitalisimo_elements_swatches`: forma, saneado, cerrojo de 30 s, sin consulta si no está en
 *   `alloptions`, renombrado y borrado;
 * - las pantallas: nada sin el nonce propio (REST, CSV, WP-CLI y K no reescriben nada), permiso
 *   `manage_product_terms`, sin campos propios en un atributo `wc-visual`, columna sin duplicar la de WooCommerce;
 * - el formulario: sin configurar, `$html` idéntico; configurado, el `<select>` byte a byte dentro del envoltorio, los
 *   radios con `form` y `required` en el primero; `$html` ajeno intacto; hoja y script en la ficha antes del cuerpo;
 * - el bucle: enlaces `attribute_pa_*` con `rel="nofollow"`, «+N», 0 consultas por producto, *slug* inexistente sin
 *   salida y aviso en el editor;
 * - `Atc_Styles`: ningún `default`, cada control de estilo con su condición, `assets` declarados;
 * - recursos: hoja ≤ 4 KB, scripts ≤ 8 KB, sin jQuery como dependencia ni `--e-global-*`; ninguna clase
 *   `Automattic\WooCommerce\Internal\…` llamada.
 */

namespace Elementor {
	class Controls_Manager {
		const TAB_STYLE = 'style';
		const SELECT = 'select';
		const SWITCHER = 'switcher';
		const SLIDER = 'slider';
		const COLOR = 'color';
		const NUMBER = 'number';
		const CHOOSE = 'choose';
		const RAW_HTML = 'raw_html';
	}
	class Group_Control_Typography {
		public static function get_type() { return 'typography'; }
	}
	class Plugin {
		public static $instance;
		public $editor;
		public $preview;
	}
	/** Pila de controles mínima: registra, cuenta y detecta repetidos. */
	class Controls_Stack_Double {
		public $controls = array();
		public $sections = array();
		public function start_controls_section( $id, $args = array() ) { $this->sections[] = $id; }
		public function end_controls_section() {}
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function add_control( $id, $args ) {
			if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); }
			$this->controls[ $id ] = $args + array( '_kind' => 'control' );
		}
		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args + array( '_kind' => 'responsive' ) ); }
		public function add_group_control( $type, $args ) { $this->add_control( $args['name'], $args + array( '_kind' => 'group:' . $type ) ); }
	}
	abstract class Widget_Base extends Controls_Stack_Double {
		public $settings = array();
		public $type_instance = false;
		public function __construct( $settings = array() ) { $this->settings = $settings; }
		public function get_id() { return 'abc1234'; }
		public function is_type_instance() { return $this->type_instance; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) {
			$all = $this->settings;
			foreach ( $this->controls as $id => $control ) {
				if ( ! array_key_exists( $id, $all ) && array_key_exists( 'default', $control ) ) { $all[ $id ] = $control['default']; }
			}
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function boot() { $this->register_controls(); return $this; }
		public function html() { ob_start(); $this->render(); return ob_get_clean(); }
		protected function content_template() {}
	}
}

namespace Elementor\Core\Frontend {
	class Performance {
		public static $optimize = false;
		public static function should_optimize_controls() { return self::$optimize; }
	}
}

namespace Digitalisimo\Elements {
	/** Doble del cerrojo compartido: anota el nombre y los segundos, y ejecuta. */
	final class Lock {
		public static $calls = array();
		public static $busy = false;
		public static function run( string $option, callable $callback, int $stale = 300, $busy = false ) {
			self::$calls[] = array( $option, $stale );
			return self::$busy ? $busy : $callback();
		}
	}
	/** Doble del generador único de imágenes. */
	final class Image_Markup {
		public static $calls = array();
		public static function img( array $image, array $args = array() ) {
			self::$calls[] = array( $image, $args );
			$id = (int) ( $image['id'] ?? 0 );
			return $id ? '<img src="/i/' . $id . '.jpg" alt=""' . ( ! empty( $args['decorative'] ) ? ' aria-hidden="true"' : '' ) . ' class="' . ( $args['class'] ?? '' ) . '">' : '';
		}
		public static function alt( array $image, $fallback = '' ) { return (string) $fallback; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.165' );
	define( 'WC_VERSION', '11.2.1' );

	function check_sw( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	class WooCommerce {}
	function WC() { return new \stdClass(); }

	class WP_Term {
		public $term_id; public $name; public $slug; public $taxonomy;
		public function __construct( $id, $name, $slug, $taxonomy = 'pa_color' ) { $this->term_id = $id; $this->name = $name; $this->slug = $slug; $this->taxonomy = $taxonomy; }
	}
	class WC_Product_Attribute {
		private $d;
		public function __construct( array $d ) { $this->d = $d + array( 'taxonomy' => true, 'variation' => true, 'options' => array() ); }
		public function is_taxonomy() { return $this->d['taxonomy']; }
		public function get_variation() { return $this->d['variation']; }
		public function get_name() { return $this->d['name']; }
		public function get_options() { return $this->d['options']; }
	}
	class WC_Product {
		public $d;
		public function __construct( array $d ) { $this->d = $d + array( 'type' => 'variable', 'attributes' => array(), 'children' => array(), 'image' => 0, 'vattrs' => array() ); }
		public function is_type( $type ) { return $this->d['type'] === $type; }
		public function get_id() { return $this->d['id']; }
		public function get_name() { return $this->d['name']; }
		public function get_permalink() { return 'https://sitio.test/producto/' . $this->d['slug'] . '/'; }
		public function get_attributes() { return $this->d['attributes']; }
		public function get_children() { return $this->d['children']; }
		public function get_image_id() { return $this->d['image']; }
		public function get_variation_attributes() { return $this->d['vattrs']; }
	}

	// ---------------------------------------------------------------- Dobles de WordPress.
	$GLOBALS['sw'] = array();
	function sw_reset() {
		$GLOBALS['sw'] = array(
			'hooks' => array(), 'meta' => array(), 'options' => array(), 'alloptions' => array(), 'get_option' => 0, 'updates' => array(),
			'nonce' => true, 'can' => true, 'enqueued' => array(), 'registered' => array(), 'transients' => array(), 'get_terms' => 0,
			'prime' => 0, 'product_terms' => array(), 'products' => array(), 'is_product' => false, 'queried' => 0, 'admin' => true, 'attribute_taxonomies' => array(),
		);
		$_POST = array();
		$_REQUEST = array();
		\Digitalisimo\Elements\Lock::$calls = array();
		\Digitalisimo\Elements\Image_Markup::$calls = array();
		if ( class_exists( '\\Digitalisimo\\Elements\\Woo\\Swatches\\Data', false ) ) {
			\Digitalisimo\Elements\Woo\Swatches\Data::flush();
			\Digitalisimo\Elements\Woo\Swatches\Loop::flush();
		}
	}
	sw_reset();
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['sw']['hooks'][ $hook ][] = array( $cb, $priority, $args ); return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function has_action( $hook ) { return ! empty( $GLOBALS['sw']['hooks'][ $hook ] ); }
	function apply_filters( $hook, $value, ...$rest ) {
		foreach ( $GLOBALS['sw']['hooks'][ $hook ] ?? array() as $h ) { $value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $rest ), 0, $h[2] ) ); }
		return $value;
	}
	function do_action( $hook, ...$args ) { foreach ( $GLOBALS['sw']['hooks'][ $hook ] ?? array() as $h ) { call_user_func_array( $h[0], array_slice( $args, 0, $h[2] ) ); } }
	function __( $text ) { return $text; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $t ) { return esc_html( $t ); }
	function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' ); }
	function esc_url_raw( $u ) { return (string) $u; }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_unslash( $v ) { return $v; }
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
	function sanitize_text_field( $t ) { return trim( (string) $t ); }
	function sanitize_title( $t ) { return strtolower( preg_replace( '/[^A-Za-z0-9_\-]+/', '-', (string) $t ) ); }
	function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $c ); }
	function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	function wp_list_pluck( $list, $field ) { return array_map( function ( $o ) use ( $field ) { return is_object( $o ) ? $o->$field : $o[ $field ]; }, $list ); }
	function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
	function is_admin() { return $GLOBALS['sw']['admin']; }
	function is_network_admin() { return false; }
	/** `sanitize_hex_color()` de WordPress (wp-includes/formatting.php). */
	function sanitize_hex_color( $color ) {
		if ( '' === $color ) { return ''; }
		if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) { return $color; }
		return null;
	}
	function get_term_meta( $id, $key, $single = false ) { return $GLOBALS['sw']['meta'][ $id ][ $key ] ?? ''; }
	function update_term_meta( $id, $key, $value ) { $GLOBALS['sw']['meta'][ $id ][ $key ] = $value; return true; }
	function delete_term_meta( $id, $key ) { unset( $GLOBALS['sw']['meta'][ $id ][ $key ] ); return true; }
	function update_termmeta_cache( $ids ) { $GLOBALS['sw']['prime']++; return true; }
	function _prime_post_caches( $ids, $a = true, $b = true ) { return true; }
	$GLOBALS['sw_fixture'] = json_decode( file_get_contents( __DIR__ . '/fixtures/woo-visual-11.2.1.json' ), true );
	function wp_attachment_is_image( $id ) { return ! empty( $GLOBALS['sw_fixture']['adjuntos'][ (string) $id ]['imagen'] ); }
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { $a = $GLOBALS['sw_fixture']['adjuntos'][ (string) $id ] ?? array(); return ! empty( $a['imagen'] ) ? $a['url'] : false; }
	function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) { return array( 'https://sitio.test/v' . $id . '.jpg', 300, 300 ); }
	function wp_get_attachment_image_srcset( $id, $size = 'thumbnail' ) { return 'https://sitio.test/v' . $id . '.jpg 300w'; }
	function wp_get_attachment_image_sizes( $id, $size = 'thumbnail' ) { return '300px'; }
	function wp_load_alloptions() { return $GLOBALS['sw']['alloptions']; }
	function get_option( $name, $default = false ) { $GLOBALS['sw']['get_option']++; return array_key_exists( $name, $GLOBALS['sw']['options'] ) ? $GLOBALS['sw']['options'][ $name ] : $default; }
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['sw']['options'][ $name ] = $value;
		$GLOBALS['sw']['alloptions'][ $name ] = serialize( $value );
		$GLOBALS['sw']['updates'][] = array( $name, $value, $autoload );
		return true;
	}
	function wp_cache_delete( $key, $group = '' ) { return true; }
	function maybe_unserialize( $v ) { $u = @unserialize( $v ); return false === $u && 'b:0;' !== $v ? $v : $u; } // phpcs:ignore
	/** `$wpdb` mínimo: `read_fresh()` lee la opción de la base (aquí, las opciones del doble). */
	final class Sw_Wpdb {
		public $options = 'wp_options';
		public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
		public function get_var( $query ) { $name = $query[1][0]; return array_key_exists( $name, $GLOBALS['sw']['options'] ) ? serialize( $GLOBALS['sw']['options'][ $name ] ) : null; }
	}
	$GLOBALS['wpdb'] = new Sw_Wpdb();
	function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['sw']['nonce'] && 'n-' . $action === $nonce ? 1 : false; }
	function wp_nonce_field( $action, $name, $referer = true, $echo = true ) { return '<input type="hidden" name="' . $name . '" value="n-' . $action . '">'; }
	function current_user_can( $cap ) { return $GLOBALS['sw']['can'] && 'manage_product_terms' === $cap; }
	function get_current_user_id() { return 7; }
	function set_transient( $k, $v, $t = 0 ) { $GLOBALS['sw']['transients'][ $k ] = $v; return true; }
	function get_transient( $k ) { return $GLOBALS['sw']['transients'][ $k ] ?? false; }
	function delete_transient( $k ) { unset( $GLOBALS['sw']['transients'][ $k ] ); return true; }
	function taxonomy_exists( $t ) { return in_array( $t, array( 'pa_color', 'pa_talla', 'pa_visual' ), true ); }
	function get_term( $id ) { foreach ( $GLOBALS['sw']['product_terms'] as $t ) { if ( (int) $t->term_id === (int) $id ) { return $t; } } return null; }
	function get_terms( $args ) { $GLOBALS['sw']['get_terms']++; return array_values( array_filter( $GLOBALS['sw']['product_terms'], function ( $t ) use ( $args ) { return $t->taxonomy === $args['taxonomy']; } ) ); }
	function wc_get_product_terms( $id, $taxonomy, $args = array() ) { return get_terms( array( 'taxonomy' => $taxonomy ) ); }
	function wc_attribute_label( $taxonomy, $product = null ) { return 'pa_color' === $taxonomy ? 'Color' : ucfirst( substr( $taxonomy, 3 ) ); }
	function wc_get_attribute_taxonomies() { return $GLOBALS['sw']['attribute_taxonomies']; }
	function wc_get_attribute( $id ) { foreach ( $GLOBALS['sw']['attribute_taxonomies'] as $a ) { if ( (int) $a->attribute_id === (int) $id ) { return (object) array( 'slug' => $a->attribute_name, 'type' => $a->attribute_type ); } } return null; }
	function is_product() { return $GLOBALS['sw']['is_product']; }
	function get_queried_object_id() { return $GLOBALS['sw']['queried']; }
	function wc_get_product( $id ) { return $GLOBALS['sw']['products'][ (int) $id ] ?? null; }
	function get_the_ID() { return 0; }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function wp_style_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['sw']['registered'][ 'css:' . $h ] ) : in_array( 'css:' . $h, $GLOBALS['sw']['enqueued'], true ); }
	function wp_script_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['sw']['registered'][ 'js:' . $h ] ) : in_array( 'js:' . $h, $GLOBALS['sw']['enqueued'], true ); }
	function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['sw']['registered'][ 'css:' . $h ] = compact( 'src', 'deps' ); return true; }
	function wp_register_script( $h, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['sw']['registered'][ 'js:' . $h ] = compact( 'src', 'deps', 'args' ); return true; }
	function wp_enqueue_style( $h, $src = '' ) { $GLOBALS['sw']['enqueued'][] = 'css:' . $h; }
	function wp_enqueue_script( $h, $src = '' ) { $GLOBALS['sw']['enqueued'][] = 'js:' . $h; }
	function wp_enqueue_media() { $GLOBALS['sw']['enqueued'][] = 'media'; }

	$root = dirname( __DIR__ ) . '/digitalisimo-elements';
	require $root . '/modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();
	$dir = $root . '/modules/digitalisimo-woo/swatches/';
	require $dir . 'class-module.php';
	$data  = \Digitalisimo\Elements\Woo\Swatches\Data::class;
	$form  = \Digitalisimo\Elements\Woo\Swatches\Form::class;
	$admin = \Digitalisimo\Elements\Woo\Swatches\Admin::class;
	$loop  = \Digitalisimo\Elements\Woo\Swatches\Loop::class;

	// ---------------------------------------------------------------- Arranque.
	\Digitalisimo\Elements\Woo\Swatches\Module::init();
	check_sw( isset( $GLOBALS['sw']['hooks']['woocommerce_dropdown_variation_attribute_options_html'] ) && 20 === $GLOBALS['sw']['hooks']['woocommerce_dropdown_variation_attribute_options_html'][0][1], 'El filtro del desplegable se engancha con prioridad 20.' );
	foreach ( array( 'woocommerce_attribute_added', 'woocommerce_attribute_updated', 'woocommerce_attribute_deleted', 'created_term', 'edited_term', 'woocommerce_after_add_attribute_fields', 'woocommerce_after_edit_attribute_fields' ) as $hook ) {
		check_sw( isset( $GLOBALS['sw']['hooks'][ $hook ] ), 'Gancho registrado: ' . $hook );
	}
	check_sw( isset( $GLOBALS['sw']['hooks']['elementor/element/woocommerce-product-add-to-cart/section_atc_variations_style/after_section_end'], $GLOBALS['sw']['hooks']['elementor/element/wc-add-to-cart/section_style/after_section_end'] ), 'Atc_Styles va en los dos widgets «Añadir al carrito», tras sus secciones de estilo.' );
	check_sw( ! isset( $GLOBALS['sw']['hooks']['wp_enqueue_scripts'] ) || 1 === count( $GLOBALS['sw']['hooks']['wp_enqueue_scripts'] ), 'Los recursos los registra Woo\Assets: el submódulo sólo encola en la ficha.' );

	// ---------------------------------------------------------------- Metas con el formato de WooCommerce 11.2.1.
	$fixture = $GLOBALS['sw_fixture'];
	foreach ( $fixture['escritura'] as $case ) {
		sw_reset();
		$GLOBALS['sw']['meta'][9] = array( 'color' => '#123', 'image' => 77 );
		$data::save_term( 9, (string) $case['color'], (int) $case['image'] );
		$expected = $case['metas'];
		check_sw( $GLOBALS['sw']['meta'][9] == $expected, 'Escritura «' . $case['caso'] . '»: ' . json_encode( $GLOBALS['sw']['meta'][9] ) . ' frente a ' . json_encode( $expected ) );
	}
	foreach ( $fixture['lectura'] as $case ) {
		sw_reset();
		$GLOBALS['sw']['meta'][9] = $case['metas'];
		$got = $data::term( new WP_Term( 9, 'Rojo', 'rojo' ) );
		$type = $got['image'] ? 'image' : ( '' !== $got['color'] ? 'color' : 'none' );
		check_sw( $type === $case['visual']['type'], 'Lectura «' . $case['caso'] . '»: ' . $type );
		check_sw( 'image' !== $type || wp_get_attachment_image_url( $got['image'] ) === $case['visual']['value'], 'La imagen es la de WooCommerce.' );
		check_sw( 'color' !== $type || $got['color'] === $case['visual']['value'], 'El color es el de WooCommerce.' );
		check_sw( 'Rojo' === $got['label'], 'La etiqueta es el nombre del término (sin metas propias).' );
	}
	check_sw( '' === $data::color( '#cc000080' ) && '#c00' === $data::color( '#c00' ) && '' === $data::color( array() ), 'Sin canal alfa ni valores que no son cadenas.' );

	// ---------------------------------------------------------------- Opción por atributo.
	sw_reset();
	check_sw( $data::defaults() === $data::attribute( 'pa_color' ) && 0 === $GLOBALS['sw']['get_option'], 'Sin la opción en alloptions, desplegable y ninguna consulta.' );
	check_sw( array() === $data::normalize( array() ) && array() === $data::normalize( 'x' ), 'La opción recién creada (`[]`) es válida.' );
	check_sw( array( 'color' => array( 'type' => 'visual', 'shape' => 'circle', 'size' => 64 ) ) === $data::normalize( array( 'v' => 1, 'attributes' => array( 'color' => array( 'type' => 'visual', 'size' => 99 ), 'talla' => array( 'type' => 'select' ), 'MAL' => array( 'type' => 'visual' ), 'x' => array( 'type' => 'color' ) ) ) ), 'Normalización: tamaño acotado, desplegables y claves inválidas fuera.' );
	check_sw( $data::put( 'color', array( 'type' => 'visual', 'shape' => 'rounded', 'size' => 40 ) ), 'Guardar una entrada válida.' );
	check_sw( array( 'v' => 1, 'attributes' => array( 'color' => array( 'type' => 'visual', 'shape' => 'rounded', 'size' => 40 ) ) ) === $GLOBALS['sw']['options'][ $data::OPTION ] && true === $GLOBALS['sw']['updates'][0][2], 'Forma `{ v, attributes }` con carga automática.' );
	check_sw( array( array( 'digitalisimo_elements_lock_swatches', 30 ) ) === \Digitalisimo\Elements\Lock::$calls, 'Bajo el cerrojo `digitalisimo_elements_lock_swatches`, 30 s (V-M7).' );
	check_sw( array( 'type' => 'visual', 'shape' => 'rounded', 'size' => 40 ) === $data::attribute( 'pa_color' ), 'Lectura tras escribir.' );
	check_sw( ! $data::put( 'color', array( 'type' => 'color' ) ) && ! $data::put( 'pa color', array( 'type' => 'visual' ) ), 'Tipo o *slug* inválidos no se guardan.' );
	check_sw( 24 === $data::sanitize_entry( array( 'type' => 'label', 'size' => 2 ) )['size'] && 'pill' === $data::sanitize_entry( array( 'type' => 'label' ) )['shape'], 'Tamaño 24–64; una etiqueta sin forma es una píldora.' );
	$data::move( 'color', 'colour' );
	check_sw( null === $data::entry( 'color' ) && 'rounded' === $data::entry( 'colour' )['shape'], 'Renombrar mueve la entrada.' );
	$data::put( 'colour', array( 'type' => 'select' ) );
	check_sw( null === $data::entry( 'colour' ), '«Desplegable» borra la entrada.' );
	$data::put( 'talla', array( 'type' => 'label' ) );
	$data::remove( 'talla' );
	check_sw( array() === $data::all(), 'Borrar la entrada.' );
	\Digitalisimo\Elements\Lock::$busy = true;
	check_sw( ! $data::put( 'color', array( 'type' => 'visual' ) ), 'Con el cerrojo tomado no se escribe.' );
	\Digitalisimo\Elements\Lock::$busy = false;

	// ---------------------------------------------------------------- Pantallas de atributos.
	sw_reset();
	$GLOBALS['sw']['attribute_taxonomies'] = array(
		(object) array( 'attribute_id' => 1, 'attribute_name' => 'color', 'attribute_label' => 'Color', 'attribute_type' => 'select' ),
		(object) array( 'attribute_id' => 2, 'attribute_name' => 'visual', 'attribute_label' => 'Visual', 'attribute_type' => 'wc-visual' ),
	);
	$posted = array( 'digitalisimo_swatch_type' => 'visual', 'digitalisimo_swatch_shape' => 'circle', 'digitalisimo_swatch_size' => '36' );
	$_POST = $posted; // Sin nonce: REST, CSV, WP-CLI o K.
	$admin::attribute_added( 1, array( 'attribute_name' => 'color' ) );
	$admin::attribute_updated( 1, array( 'attribute_name' => 'color' ), 'color' );
	check_sw( array() === $GLOBALS['sw']['updates'], 'Sin el nonce propio no se escribe nada.' );
	$_POST = $posted + array( 'digitalisimo_swatch_attribute_nonce' => 'n-digitalisimo_swatch_attribute' );
	$GLOBALS['sw']['can'] = false;
	$admin::attribute_added( 1, array( 'attribute_name' => 'color' ) );
	check_sw( array() === $GLOBALS['sw']['updates'], 'Sin `manage_product_terms` no se escribe nada.' );
	$GLOBALS['sw']['can'] = true;
	$admin::attribute_added( 1, array( 'attribute_name' => 'color' ) );
	check_sw( array( 'type' => 'visual', 'shape' => 'circle', 'size' => 36 ) === $data::entry( 'color' ), 'Con el nonce y el permiso se guarda el tipo.' );
	$_POST = array();
	$admin::attribute_updated( 1, array( 'attribute_name' => 'tono' ), 'color' );
	check_sw( null === $data::entry( 'color' ) && 36 === $data::entry( 'tono' )['size'], 'Renombrar el *slug* (también sin formulario) mueve la entrada (R-I13).' );
	$admin::attribute_deleted( 1, 'tono', 'pa_tono' );
	check_sw( null === $data::entry( 'tono' ), 'Borrar el atributo borra su entrada.' );
	$_GET['edit'] = 2;
	ob_start();
	$admin::edit_attribute_fields();
	$fields = ob_get_clean();
	unset( $_GET['edit'] );
	check_sw( false !== strpos( $fields, 'name="digitalisimo_swatch_type"' ) && false !== strpos( $fields, 'value="select" selected' ) && false !== strpos( $fields, 'digitalisimo_swatch_attribute_nonce' ), 'Edición: el tipo (desplegable por defecto) y el nonce propio.' );
	check_sw( false !== strpos( $fields, 'Muestra visual' ) && false !== strpos( $fields, 'ya guarda colores e imágenes en WooCommerce' ), 'Un atributo `wc-visual` propone «Muestra visual» sin elegirla.' );
	check_sw( false !== strpos( $fields, '<tr class="form-field">' ), 'Edición en filas de `form-table`; ninguna clase de digitalisimo-ui.' );
	ob_start();
	$admin::add_attribute_fields();
	check_sw( false !== strpos( ob_get_clean(), '<div class="form-field">' ), 'Alta en bloques `div.form-field`.' );

	// ---------------------------------------------------------------- Pantallas de términos.
	$data::put( 'color', array( 'type' => 'visual' ) );
	$data::put( 'visual', array( 'type' => 'visual' ) );
	$_REQUEST['taxonomy'] = 'pa_color';
	$GLOBALS['sw']['hooks'] = array();
	$admin::admin_init();
	check_sw( isset( $GLOBALS['sw']['hooks']['pa_color_add_form_fields'], $GLOBALS['sw']['hooks']['pa_color_edit_form_fields'], $GLOBALS['sw']['hooks']['manage_edit-pa_color_columns'] ), 'Campos y columna en un atributo con «Muestra visual».' );
	$_REQUEST['taxonomy'] = 'pa_visual';
	$GLOBALS['sw']['hooks'] = array();
	$admin::admin_init();
	check_sw( ! isset( $GLOBALS['sw']['hooks']['pa_visual_add_form_fields'] ) && isset( $GLOBALS['sw']['hooks']['manage_edit-pa_visual_columns'] ), 'En un `wc-visual` no hay campos propios (escriben los de WooCommerce).' );
	check_sw( array( 'cb' => 1, 'name' => 1, 'visual' => 1, 'slug' => 1 ) === $admin::columns( array( 'cb' => 1, 'name' => 1, 'visual' => 1, 'slug' => 1 ) ), 'Sin duplicar la columna `visual` de WooCommerce.' );
	$_REQUEST['taxonomy'] = 'pa_color';
	check_sw( array( 'cb', 'name', 'digitalisimo_swatch', 'slug' ) === array_keys( $admin::columns( array( 'cb' => 1, 'name' => 1, 'slug' => 1 ) ) ), 'Columna «Muestra» antes del *slug*.' );
	ob_start();
	$admin::add_term_fields( 'pa_color' );
	$term_fields = ob_get_clean();
	check_sw( false !== strpos( $term_fields, 'type="color"' ) && false !== strpos( $term_fields, 'name="digitalisimo_swatch_color"' ) && false !== strpos( $term_fields, 'type="hidden" name="digitalisimo_swatch_image"' ) && false !== strpos( $term_fields, 'data-digi-swatch-pick' ), 'Color (paleta y hexadecimal) e imagen por el modal de Medios.' );
	check_sw( false === strpos( $term_fields, 'type="url"' ) && false === strpos( $term_fields, 'name="digitalisimo_swatch_image" type="text"' ), 'La dirección de la imagen nunca se escribe.' );
	$admin::enqueue( 'edit-tags.php' );
	check_sw( in_array( 'media', $GLOBALS['sw']['enqueued'], true ) && in_array( 'js:digitalisimo-woo-swatches-admin', $GLOBALS['sw']['enqueued'], true ), 'Modal de Medios y script propio sólo en esa pantalla.' );
	$GLOBALS['sw']['enqueued'] = array();
	$admin::enqueue( 'edit.php' );
	check_sw( array() === $GLOBALS['sw']['enqueued'], 'En otra pantalla, nada.' );

	$GLOBALS['sw']['meta'][31] = array( 'color' => '#abc' );
	$_POST = array( 'digitalisimo_swatch_color' => '#c00', 'digitalisimo_swatch_image' => '' );
	$admin::save_term( 31, 0, 'pa_color' );
	check_sw( array( 'color' => '#abc' ) === $GLOBALS['sw']['meta'][31], 'Término sin el nonce propio: nada cambia.' );
	$_POST['digitalisimo_swatch_term_nonce'] = 'n-digitalisimo_swatch_term';
	$admin::save_term( 31, 0, 'pa_visual' );
	check_sw( array( 'color' => '#abc' ) === $GLOBALS['sw']['meta'][31], 'En un `wc-visual` guarda WooCommerce, no los campos propios.' );
	$_POST['digitalisimo_swatch_color'] = '#cc000080';
	$admin::save_term( 31, 0, 'pa_color' );
	check_sw( array( 'color' => '#abc' ) === $GLOBALS['sw']['meta'][31] && false !== strpos( (string) get_transient( 'digitalisimo_swatch_notice_7' ), 'no se guardó' ), 'Un color inválido no se guarda y la pantalla lo dice.' );
	$_POST['digitalisimo_swatch_color'] = '#c00';
	$_POST['digitalisimo_swatch_image'] = '55';
	$admin::save_term( 31, 0, 'pa_color' );
	check_sw( array( 'image' => 55 ) === $GLOBALS['sw']['meta'][31], 'Imagen válida: gana la imagen y se borra el color (formato de WooCommerce).' );
	$_POST['digitalisimo_swatch_image'] = '56';
	$admin::save_term( 31, 0, 'pa_color' );
	check_sw( array( 'image' => 55 ) === $GLOBALS['sw']['meta'][31], 'Un adjunto que no es imagen no se guarda.' );
	$_POST = array();
	$_REQUEST = array();

	// ---------------------------------------------------------------- Formulario.
	sw_reset();
	$terms = array( new WP_Term( 11, 'Rojo', 'rojo' ), new WP_Term( 12, 'Azul', 'azul' ), new WP_Term( 13, 'Verde', 'verde' ), new WP_Term( 21, 'M', 'm', 'pa_talla' ) );
	$GLOBALS['sw']['product_terms'] = $terms;
	$GLOBALS['sw']['meta'] = array( 11 => array( 'color' => '#cc0000' ), 12 => array( 'image' => 55 ) );
	$attributes = array(
		'pa_color' => new WC_Product_Attribute( array( 'name' => 'pa_color', 'options' => array( 11, 12, 13 ) ) ),
		'pa_talla' => new WC_Product_Attribute( array( 'name' => 'pa_talla', 'options' => array( 21 ) ) ),
	);
	$product = new WC_Product( array( 'id' => 40, 'name' => 'Camiseta <b>lisa</b>', 'slug' => 'camiseta', 'attributes' => $attributes, 'children' => array( 41, 42 ) ) );
	$select  = '<select id="pa_color" class="" name="attribute_pa_color" data-attribute_name="attribute_pa_color" data-show_option_none="yes"><option value="">Elige una opción</option><option value="rojo" >Rojo</option><option value="azul"  selected=\'selected\'>Azul</option><option value="verde" >Verde</option></select>';
	$args    = array( 'attribute' => 'pa_color', 'product' => $product, 'options' => array( 'rojo', 'azul', 'verde' ), 'selected' => 'azul', 'required' => false );
	check_sw( $select === $form::filter( $select, $args ), 'Sin configurar, el `<select>` sale idéntico (`===`).' );
	check_sw( array() === $GLOBALS['sw']['enqueued'], 'Sin configurar, nada se encola.' );
	$GLOBALS['sw']['options'][ $data::OPTION ] = array( 'v' => 1, 'attributes' => array( 'color' => array( 'type' => 'visual', 'shape' => 'circle', 'size' => 40 ), 'talla' => array( 'type' => 'label', 'shape' => 'pill', 'size' => 32 ) ) );
	$GLOBALS['sw']['alloptions'][ $data::OPTION ] = 'x';
	$data::flush();
	$foreign = '<div class="otro-plugin">' . $select . '</div>';
	check_sw( $foreign === $form::filter( $foreign, $args ), 'Un `$html` que ya no empieza por `<select` no se toca.' );
	check_sw( $select === $form::filter( $select, array( 'attribute' => 'Color local' ) + $args ), 'Un atributo propio del producto (no `pa_*`) no se toca.' );
	$out = $form::filter( $select, $args );
	check_sw( 0 === strpos( $out, '<div class="digi-swatch-field" data-digi-swatches="pa_color">' . $select . '<div class="digi-swatches' ), 'El `<select>` byte a byte dentro del envoltorio, seguido de los radios.' );
	preg_match_all( '/<input type="radio" name="digi_sw_([a-z0-9-]+)" form="digi-sw-form-([a-z0-9-]+)" value="([a-z]+)"([^>]*)>/', $out, $radios, PREG_SET_ORDER );
	check_sw( 3 === count( $radios ) && array( 'rojo', 'azul', 'verde' ) === array_column( $radios, 3 ), 'Un radio por opción, en el orden del desplegable.' );
	check_sw( $radios[0][1] === $radios[0][2] && 'p40-1' === $radios[0][1], 'Radios agrupados por `name` y fuera del pedido con `form` (V.6.3).' );
	check_sw( ' checked' === $radios[1][4] && '' === $radios[0][4], 'La opción elegida en el desplegable sale marcada.' );
	check_sw( false !== strpos( $out, 'role="radiogroup" aria-label="Color"' ) && false !== strpos( $out, '--digi-swatch-size-attr:40px' ), 'Grupo con nombre accesible y tamaño del atributo.' );
	check_sw( false !== strpos( $out, 'style="--digi-swatch-color:#cc0000"' ) && false !== strpos( $out, '<img src="/i/55.jpg" alt="" aria-hidden="true" class="digi-swatch__img">' ), 'Color por variable e imagen decorativa por Image_Markup.' );
	check_sw( false !== strpos( $out, '<span class="digi-swatch__label screen-reader-text">Rojo</span>' ) && false !== strpos( $out, 'digi-swatch digi-swatch--text"><input type="radio" name="digi_sw_p40-1" form="digi-sw-form-p40-1" value="verde"' ), 'El nombre del término es el nombre accesible; sin color ni imagen, se ve el nombre.' );
	check_sw( in_array( 'css:digitalisimo-woo-swatches', $GLOBALS['sw']['enqueued'], true ) && in_array( 'js:digitalisimo-woo-swatches', $GLOBALS['sw']['enqueued'], true ), 'Fuera de la ficha, el filtro encola la hoja y el script (Woo\Assets).' );
	$out = $form::filter( $select, array( 'required' => true ) + $args );
	check_sw( 1 === substr_count( $out, ' required' ) - substr_count( $select, ' required' ) && false !== strpos( $out, 'value="rojo" required' ), '`required` sólo en el primer radio.' );
	check_sw( false === strpos( $out, 'digi_sw_p40-1"' ), 'Cada grupo de la página tiene su identificador.' );
	$talla = $form::filter( '<select id="pa_talla" name="attribute_pa_talla"><option value="">Elige</option><option value="m">M</option></select>', array( 'attribute' => 'pa_talla', 'product' => $product, 'options' => array( 'm' ), 'selected' => '' ) );
	check_sw( false !== strpos( $talla, 'digi-swatches--pill digi-swatches--label' ) && false !== strpos( $talla, '<span class="digi-swatch__label">M</span>' ) && false === strpos( $talla, 'digi-swatch__face' ), '«Etiqueta»: el nombre visible, sin cara.' );

	// Ficha: hoja y script antes del cuerpo, sólo con muestras.
	$GLOBALS['sw']['enqueued'] = array();
	$GLOBALS['sw']['products'][40] = $product;
	$GLOBALS['sw']['queried'] = 40;
	$GLOBALS['sw']['is_product'] = true;
	$form::enqueue_product();
	check_sw( in_array( 'css:digitalisimo-woo-swatches', $GLOBALS['sw']['enqueued'], true ), 'En la ficha de un variable con muestras, la hoja va en `wp_enqueue_scripts` (sin salto de diseño).' );
	$GLOBALS['sw']['enqueued'] = array();
	$GLOBALS['sw']['products'][43] = new WC_Product( array( 'id' => 43, 'name' => 'Simple', 'slug' => 's', 'type' => 'simple' ) );
	$GLOBALS['sw']['queried'] = 43;
	$form::enqueue_product();
	check_sw( array() === $GLOBALS['sw']['enqueued'], 'Un producto simple no encola nada.' );
	$GLOBALS['sw']['is_product'] = false;

	// ---------------------------------------------------------------- Bucle.
	$GLOBALS['sw']['get_terms'] = 0;
	$GLOBALS['sw']['prime'] = 0;
	$second = new WC_Product( array( 'id' => 50, 'name' => 'Sudadera', 'slug' => 'sudadera', 'attributes' => array( 'pa_color' => new WC_Product_Attribute( array( 'name' => 'pa_color', 'options' => array( 13, 11 ) ) ) ) ) );
	$a = $loop::markup( $product, array( 'max' => 2 ) );
	$b = $loop::markup( $second, array() );
	check_sw( 1 === $GLOBALS['sw']['get_terms'] && 1 === $GLOBALS['sw']['prime'], 'Términos y metas una vez por página: 0 consultas por producto (V-M12).' );
	check_sw( false !== strpos( $a, 'href="https://sitio.test/producto/camiseta/?attribute_pa_color=rojo" rel="nofollow"' ), 'Enlace que preselecciona la variación, con `rel="nofollow"` (V.13).' );
	check_sw( false !== strpos( $a, '<span class="screen-reader-text">Camiseta lisa, Rojo</span>' ) && false !== strpos( $a, 'aria-label="Color: opciones"' ), 'Nombre accesible «{producto}, {término}».' );
	check_sw( 1 === substr_count( $a, 'digi-swatch--more' ) && false !== strpos( $a, '+1<span class="screen-reader-text"> opciones más de Camiseta lisa</span>' ) && 1 === preg_match( '#digi-swatch--more digi-swatch--text" href="https://sitio.test/producto/camiseta/" rel="nofollow"#', $a ), '«+N» a la ficha, también `nofollow`.' );
	check_sw( false !== strpos( $b, 'attribute_pa_color=rojo' ) && false !== strpos( $b, 'attribute_pa_color=verde' ) && strpos( $b, 'verde' ) < strpos( $b, 'rojo' ) === false, 'Sólo los términos del producto, en el orden del atributo.' );
	check_sw( '' === $loop::markup( new WC_Product( array( 'id' => 60, 'name' => 'S', 'slug' => 's', 'type' => 'simple', 'attributes' => $attributes ) ) ), 'Un producto que no es variable no pinta nada.' );
	check_sw( '' === $loop::markup( $product, array( 'attribute' => 'pa_viejo' ) ), 'Un *slug* que ya no existe no pinta nada (falla cerrado).' );
	$GLOBALS['sw']['products'][41] = new WC_Product( array( 'id' => 41, 'name' => 'V', 'slug' => 'v', 'type' => 'variation', 'image' => 91, 'vattrs' => array( 'attribute_pa_color' => 'rojo' ) ) );
	$GLOBALS['sw']['products'][42] = new WC_Product( array( 'id' => 42, 'name' => 'V', 'slug' => 'v', 'type' => 'variation', 'image' => 0, 'vattrs' => array( 'attribute_pa_color' => 'azul' ) ) );
	$hover = $loop::markup( $product, array( 'hover' => true ) );
	check_sw( false !== strpos( $hover, ' data-digi-sw-hover' ) && 1 === substr_count( $hover, 'data-digi-sw-img=' ) && false !== strpos( $hover, '&quot;src&quot;:&quot;https://sitio.test/v91.jpg&quot;' ), '«Cambiar la imagen al pasar el cursor»: la imagen de la variación, sólo donde la hay.' );

	// Widget del bucle.
	$widget_class = \Digitalisimo\Elements\Woo\Swatches\Loop_Widget::class;
	class_exists( $widget_class );
	check_sw( is_subclass_of( $widget_class, \Digitalisimo\Elements\Woo\Widget::class ) && \Digitalisimo\Elements\Woo\Inert::cooperates( $widget_class ), 'El widget sigue el contrato de Woo\Widget (sin WooCommerce, Inert usa la misma clase).' );
	\Elementor\Core\Frontend\Performance::$optimize = true;
	$widget = ( new $widget_class( array( 'attribute' => 'pa_viejo' ) ) )->boot();
	check_sw( array( '' => 'El primero con muestras' ) === $widget->controls['attribute']['options'], 'En la pila optimizada del front, las opciones de atributos no se construyen (V.12.1).' );
	\Elementor\Core\Frontend\Performance::$optimize = false;
	$GLOBALS['sw']['attribute_taxonomies'] = array(
		(object) array( 'attribute_id' => 1, 'attribute_name' => 'color', 'attribute_label' => 'Color', 'attribute_type' => 'select' ),
		(object) array( 'attribute_id' => 3, 'attribute_name' => 'material', 'attribute_label' => 'Material', 'attribute_type' => 'select' ),
	);
	$editor = ( new $widget_class() )->boot();
	check_sw( array( '' => 'El primero con muestras', 'pa_color' => 'Color' ) === $editor->controls['attribute']['options'], 'En el editor, sólo los atributos con muestras.' );
	check_sw( '' === $widget->html(), 'En el front, un *slug* que ya no existe no pinta nada.' );
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return true; } };
	\Elementor\Plugin::$instance->preview = new class() { public function is_preview_mode() { return false; } };
	check_sw( false !== strpos( $widget->html(), 'El atributo ya no existe' ), 'En el editor, el aviso «El atributo ya no existe».' );
	\Elementor\Plugin::$instance = null;
	check_sw( array() === ( new $widget_class( array( 'hover_image' => '' ) ) )->boot()->get_script_depends() && array( 'digitalisimo-woo-swatches' ) === ( new $widget_class( array( 'hover_image' => 'yes' ) ) )->boot()->get_script_depends(), 'El script sólo con «Cambiar la imagen al pasar el cursor».' );
	$GLOBALS['product'] = $product;
	check_sw( false !== strpos( ( new $widget_class( array() ) )->boot()->html(), 'attribute_pa_color=' ), 'En una plantilla de bucle, el producto del bucle.' );
	unset( $GLOBALS['product'] );

	// ---------------------------------------------------------------- Atc_Styles.
	$atc   = \Digitalisimo\Elements\Woo\Swatches\Atc_Styles::class;
	$stack = new \Elementor\Controls_Stack_Double();
	$atc::register_controls( $stack );
	$atc::register_controls( $stack );
	check_sw( array( 'digitalisimo_swatch_section' ) === $stack->sections, 'Una sola sección «Muestras» por pila.' );
	foreach ( $stack->controls as $name => $control ) {
		check_sw( ! array_key_exists( 'default', $control ), 'Ningún `default` en un control añadido a un widget existente (D33): ' . $name );
		if ( $atc::SWITCH !== $name ) {
			check_sw( array( $atc::SWITCH => 'yes' ) === ( $control['condition'] ?? null ), 'Cada control de estilo, detrás del interruptor opt-in: ' . $name );
			check_sw( ! empty( $control['selectors'] ) || 0 === strpos( $control['_kind'], 'group:' ), 'Controles de estilo con `selectors` o de un grupo del núcleo: ' . $name );
		}
	}
	$assets = $stack->controls[ $atc::SWITCH ]['assets'] ?? array();
	check_sw( 'digitalisimo-woo-swatches' === ( $assets['styles'][0]['name'] ?? '' ) && 'digitalisimo-woo-swatches' === ( $assets['scripts'][0]['name'] ?? '' ) && 'yes' === $assets['styles'][0]['conditions']['terms'][0]['value'], 'Hoja y script declarados en `assets` con su condición (V.12.2).' );

	// ---------------------------------------------------------------- Identificador en la ruta de la vista rápida.
	define( 'REST_REQUEST', true );
	check_sw( false !== strpos( $form::filter( $select, $args ), 'name="digi_sw_q40-' ), 'En la ruta de la vista rápida, otro prefijo: no coincide con un formulario de la página.' );

	// ---------------------------------------------------------------- Recursos y revisión estática.
	foreach ( array( 'assets/css/swatches.css' => 4096, 'assets/css/swatches-admin.css' => 4096, 'assets/js/swatches.js' => 8192, 'assets/js/swatches-admin.js' => 8192 ) as $file => $cap ) {
		$source = file_get_contents( $root . '/' . $file );
		check_sw( strlen( $source ) <= $cap, $file . ' pesa ' . strlen( $source ) . ' B (tope ' . $cap . ').' );
		check_sw( false === strpos( $source, '--e-global-' ) && false === strpos( $source, 'Elementor' ), $file . ': sin paleta del sitio ni el nombre del proveedor.' );
	}
	check_sw( false === strpos( file_get_contents( $root . '/assets/js/swatches.js' ), 'jQuery(' ) || 1 === preg_match_all( '/w\.jQuery\(/', file_get_contents( $root . '/assets/js/swatches.js' ) ), 'jQuery sólo como puente opcional (`window.jQuery`), nunca como dependencia.' );
	// CLS (W6): con JS, los radios desde la primera pintura; sin JS (o sin la consulta `scripting`), el <select> hasta
	// que el script arranca. La regla va después de la que oculta los radios antes de arrancar, con su mismo selector.
	$css = file_get_contents( $root . '/assets/css/swatches.css' );
	$mq  = strpos( $css, '@media (scripting:enabled){.digi-swatch-field>select{display:none!important}.digi-swatch-field:not(.digi-swatch-field--ready)>.digi-swatches{display:flex}}' );
	check_sw( false !== $mq && $mq > (int) strpos( $css, '.digi-swatch-field:not(.digi-swatch-field--ready)>.digi-swatches{display:none}' ) && false !== strpos( $css, '.digi-swatch-field--ready>select{display:none!important}' ), 'Con JS, la hoja oculta el <select> y muestra los radios desde la primera pintura (`@media (scripting:enabled)`): sin salto de diseño; sin JS, el <select>.' );
	foreach ( glob( $dir . '*.php' ) as $file ) {
		$code = '';
		$strings = array();
		foreach ( token_get_all( file_get_contents( $file ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$code .= is_array( $token ) ? $token[1] : $token;
			if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$strings[] = $token[1];
			}
		}
		check_sw( false === strpos( $code, 'Automattic\\WooCommerce\\Internal' ) && false === strpos( $code, 'Automattic\\\\WooCommerce\\\\Internal' ), basename( $file ) . ': ninguna clase interna de WooCommerce llamada (V.6.1).' );
		foreach ( $strings as $string ) {
			check_sw( ! preg_match( '/(^|[^\\\\A-Za-z_])Elementor([^\\\\A-Za-z_]|$)/', $string ), basename( $file ) . ': texto con el nombre del proveedor: ' . $string );
		}
		check_sw( false === strpos( $code, "'attribute_type' =>" ) && false === strpos( $code, "update_option( 'woocommerce" ), basename( $file ) . ': no escribe `attribute_type` ni opciones de WooCommerce.' );
	}
	echo "DIGITALÍSIMO Elements: muestras de variación (F4-Woo, frente D) validadas.\n";
}
