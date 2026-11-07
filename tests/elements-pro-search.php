<?php
/**
 * Búsqueda (`digitalisimo-search`), el widget propio que sustituye a la «Search» de PRO (C3 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c3.md, frente F), su ruta REST (`Search_Rest`) y su mapa
 * `modules/digitalisimo-convert/maps/search.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB sin jQuery; sin `--e-global-*`, `url()`, clases o variables de PRO
 *   ni nombres de otros proveedores);
 * - controles: se cargan a la vez el widget de PRO y el propio; cada control y grupo de PRO (también los que arma con
 *   `$tab_id`, el grupo de consulta y la plantilla de bucle) tiene uno propio con el mismo nombre, tipo, valor por defecto
 *   (salvo los textos), opciones, condiciones, dispositivos, pestaña y sección;
 * - cobertura del mapa: cada control de PRO declarado una vez y los textos por defecto de PRO en `defaults`;
 * - marcado: formulario `role=search` con etiqueta, botón «Borrar» real, envío visible u oculto, campo `e_search_props`,
 *   resultados en vivo (región con nombre, estado anunciado, indicador de carga, posición); mensaje sin resultados y
 *   paginación (los enlaces y los inactivos de PRO); plantilla del editor;
 * - REST: `id` de 7 caracteres, modelo del editor de este widget, permiso del front sólo con el documento publicado y
 *   el widget dentro;
 * - conversión con `Converter::tree()` de búsquedas de PRO reales (por defecto, campo, resultados en vivo, consulta,
 *   estilos de cada pestaña, paginación, `__dynamic__` y `__globals__`, CSS personalizado): ningún `pro.unmapped`, cada
 *   clave cae en un control propio y lo convertido se pinta.
 */
namespace Elementor {
	class Plugin { public static $instance; public $experiments; public $documents; public $elements_manager; }
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const NUMBER = 'number';
		const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions';
		const ICONS = 'icons'; const RAW_HTML = 'raw_html'; const HOVER_ANIMATION = 'hover_animation'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Icons_Manager {
		public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) {
			if ( ! empty( $icon['value'] ) ) {
				echo '<i class="' . htmlspecialchars( (string) $icon['value'], ENT_QUOTES ) . '" aria-hidden="true"></i>';
			}
		}
	}
	class Utils {
		public static function validate_html_tag( $tag ) {
			return in_array( strtolower( (string) $tag ), array( 'article', 'aside', 'div', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'main', 'nav', 'p', 'section', 'span' ), true ) ? strtolower( (string) $tag ) : 'div';
		}
		public static function print_validated_html_tag( $tag ) { echo self::validate_html_tag( $tag ); }
		public static function find_element_recursive( $elements, $id ) {
			foreach ( $elements as $element ) {
				if ( $id === ( $element['id'] ?? null ) ) {
					return $element;
				}
				$found = self::find_element_recursive( $element['elements'] ?? array(), $id );
				if ( $found ) {
					return $found;
				}
			}
			return false;
		}
	}
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $id       = 'abc1234';
		protected $section = '';
		protected $tab = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function get_id() { return $this->id; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args ) { $this->tab = $name; }
		public function end_controls_tab() { $this->tab = ''; }
		public function end_controls_tabs() { $this->tab = ''; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section, '_tab' => $this->tab ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, '_section' => $this->section, '_tab' => $this->tab ) + $args; $this->order[] = '@' . $args['name']; }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_TEXT = 'globals/colors?id=text'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; }
}
namespace Elementor\Core\Base {
	class Document { const STATUS_PUBLISH = 'publish'; const TYPE_META_KEY = '_elementor_template_type'; }
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {}
}
namespace ElementorPro\Core\Utils {
	class Collection {
		private $items;
		public function __construct( $items ) { $this->items = $items; }
		public function except( $keys ) { return new self( array_diff_key( $this->items, array_flip( $keys ) ) ); }
		public function map_with_keys( $cb ) { $out = array(); foreach ( $this->items as $k => $v ) { $out += $cb( $v, $k ); } return new self( $out ); }
		public function filter() { return new self( array_filter( $this->items ) ); }
		public function all() { return $this->items; }
	}
}
namespace ElementorPro\Core {
	class Utils { public static function get_current_post_id() { return 42; } }
}
namespace ElementorPro\Modules\QueryControl\Controls {
	class Group_Control_Query { public static function get_type() { return 'query-group'; } }
	class Template_Query { const CONTROL_ID = 'template_query'; }
}
namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_OBJECT_LIBRARY_TEMPLATE = 'library_template'; }
}
namespace ElementorPro\Modules\LoopBuilder\Documents {
	class Loop { public static function get_type() { return 'loop-item'; } }
}
namespace ElementorPro\Modules\LoopBuilder\Files\Css {
	trait Loop_Css_Trait {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['se'] = array( 'features' => array(), 'posts' => array(), 'docs' => array(), 'editable' => false, 'referer' => '' );
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_js( $v ) { return addslashes( (string) $v ); }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<b><strong><em><a>' ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function home_url( $path = '' ) { return 'https://sitio.test/' . ltrim( $path, '/' ); }
	function rest_url( $path = '' ) { return 'https://sitio.test/wp-json/' . ltrim( $path, '/' ); }
	function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
	function do_action( $hook, ...$args ) {}
	function get_post_types( $args = array(), $output = 'names' ) { return array( 'portfolio' => (object) array( 'label' => 'Portafolio' ), 'product' => (object) array( 'label' => 'Productos' ) ); }
	function get_the_ID() { return 7; }
	function get_post( $id ) { return $GLOBALS['se']['posts'][ $id ] ?? null; }
	function post_password_required( $post ) { return false; }
	function wp_get_referer() { return $GLOBALS['se']['referer']; }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function wp_parse_str( $q, &$out ) { parse_str( $q, $out ); }
	function url_to_postid( $url ) { return '' === $url ? 0 : 9; }
	function get_permalink( $id ) { return $id ? 'https://sitio.test/buscar' : 'https://sitio.test/'; }
	function trailingslashit( $v ) { return rtrim( (string) $v, '/' ) . '/'; }
	function paginate_links( $args ) {
		$GLOBALS['se']['paginate'] = $args;
		$out = array();
		for ( $i = 1; $i <= $args['total']; $i++ ) {
			$out[] = $i === $args['current'] ? '<span aria-current="page" class="page-numbers current">' . $args['before_page_number'] . $i . '</span>' : '<a class="page-numbers" href="' . $args['base'] . '?e-search-page=' . $i . '">' . $args['before_page_number'] . $i . '</a>';
		}
		if ( $args['prev_next'] && $args['current'] > 1 ) {
			array_unshift( $out, '<a class="prev page-numbers" href="#">' . $args['prev_text'] . '</a>' );
		}
		if ( $args['prev_next'] && $args['current'] < $args['total'] ) {
			$out[] = '<a class="next page-numbers" href="#">' . $args['next_text'] . '</a>';
		}
		return $out;
	}
	function check_se( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	/** Petición REST de prueba. */
	class SE_Request {
		private $p;
		public function __construct( array $p ) { $this->p = $p; }
		public function get_param( $k ) { return $this->p[ $k ] ?? null; }
	}

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-navigation/class-module.php';
	\Digitalisimo\Elements\Navigation\Module::init();
	require $root . 'modules/digitalisimo-navigation/widgets/class-search.php';
	require $root . 'modules/digitalisimo-navigation/class-search-rest.php';
	require $root . 'modules/search/widgets/search.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['se']['features'][ $f ] ); } };
	\Elementor\Plugin::$instance->documents   = new class() {
		public function get( $id ) {
			$data = $GLOBALS['se']['docs'][ $id ] ?? null;
			return null === $data ? null : new class( $data ) {
				private $data;
				public function __construct( $data ) { $this->data = $data; }
				public function get_elements_data() { return $this->data; }
				public function is_editable_by_current_user() { return $GLOBALS['se']['editable']; }
			};
		}
		public function get_current() { return null; }
	};

	use Digitalisimo\Elements\Navigation\Module;
	use Digitalisimo\Elements\Navigation\Search_Rest;
	use Digitalisimo\Elements\Navigation\Widgets\Search;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$own = new Search();
	( new ReflectionMethod( $own, 'register_controls' ) )->invoke( $own );
	$pro = new \ElementorPro\Modules\Search\Widgets\Search();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );

	$defaults = static function ( $widget ) {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			}
		}
		return $out;
	};
	$render = static function ( array $settings ) use ( $own, $defaults ) {
		$widget           = new Search();
		$widget->controls = $own->controls;
		$widget->settings = $settings + $defaults( $own );
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return preg_replace( '/>\s+</', '><', trim( (string) ob_get_clean() ) );
	};

	// 1. Registro y recursos.
	$name = 'digitalisimo-search';
	check_se( $name === $own->get_name() && 'Búsqueda' === $own->get_title() && array( 'digitalisimo' ) === $own->get_categories() && 'eicon-site-search' === $own->get_icon(), 'Nombre, título, icono y categoría propios.' );
	check_se( isset( Module::WIDGETS[ $name ] ) && Search::class === Module::WIDGETS[ $name ]['class'] && ! in_array( $name, Module::NESTED, true ) && 'Búsqueda' === Module::TITLES[ $name ], 'El módulo de navegación registra el widget (no anidado), con su título.' );
	check_se( array( $name ) === $own->get_style_depends() && array( $name ) === $own->get_script_depends(), 'Su hoja y su script, por el módulo.' );
	check_se( 'search' === $own->get_query_name(), 'Nombre de consulta de PRO: los ajustes de la consulta son search_query_*.' );
	$dir = $root . 'modules/digitalisimo-navigation/';
	$css = (string) file_get_contents( $dir . 'assets/css/search.css' );
	$js  = (string) file_get_contents( $dir . 'assets/js/search.js' );
	check_se( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_se( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/\.e-search|--e-search|elementor-pagination/', $css ) && str_contains( $css, 'prefers-reduced-motion' ) && str_contains( $css, ':focus-visible:not(.digi-search__input){outline:2px solid currentColor;outline-offset:2px}' ) && str_contains( $css, '.digi-search__input:focus{outline:0' ) && ! preg_match( '/:focus-visible\{/', $css ) && str_contains( $css, 'min-width:24px;min-height:24px' ), 'La hoja: sin kit, url() ni clases o variables de PRO; movimiento reducido, foco visible en los botones (el campo sin contorno, como PRO) y «Borrar» de 24 × 24 px.' );
	check_se( strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|elementorProFrontend|ElementorProFrontendConfig|refresh-search/', $js ), 'Script de ' . strlen( $js ) . ' B (tope 6 144) sin jQuery, sin los globales ni el punto REST de PRO.' );
	foreach ( array( "'ArrowDown'", "'Escape'", 'role=status', 'runReadyTrigger', 'e-search-page', 'X-WP-Nonce', 'digi-search__clear--off', '--dgs-iw' ) as $piece ) {
		check_se( str_contains( $js, $piece ), 'El script no tiene: ' . $piece );
	}
	$source = (string) file_get_contents( $dir . 'widgets/class-search.php' );
	$rest   = (string) file_get_contents( $dir . 'class-search-rest.php' );
	$mapsrc = (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/search.php' );
	foreach ( array( $source, $rest, $css, $js, $mapsrc ) as $text ) {
		check_se( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $text ), 'Sin nombres de otros proveedores (guarda de build-packages.sh).' );
	}
	check_se( ! preg_match( '/https?:\/\/(?!www\.w3\.org\/2000\/svg)/', $source . $js . $css . $rest ), 'Ninguna URL de terceros en el marcado, el script o la hoja.' );

	// 2. Un control propio por cada control de PRO, igual salvo los textos.
	$same = static function ( $key, $a, $b ) { return ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ); };
	check_se( $pro->order === $own->order, 'Los mismos controles y grupos en el mismo orden. Sobran: ' . implode( ', ', array_diff( $own->order, $pro->order ) ) . ' · faltan: ' . implode( ', ', array_diff( $pro->order, $own->order ) ) );
	foreach ( $pro->controls as $cname => $p ) {
		$o = $own->controls[ $cname ];
		foreach ( array( 'type', 'condition', 'conditions', 'responsive', '_section', '_tab', 'size_units', 'separator', 'frontend_available', 'render_type', 'return_value', 'label_block', 'min', 'toggle', 'global', 'fa4compatibility', 'skin', 'skin_settings', 'content_classes', 'autocomplete', 'actions' ) as $key ) {
			check_se( $same( $key, $p, $o ), "$cname: «{$key}» distinto de PRO (" . json_encode( $p[ $key ] ?? null ) . ' / ' . json_encode( $o[ $key ] ?? null ) . ').' );
		}
		if ( ! in_array( $p['type'] ?? 'text', array( 'text', 'textarea' ), true ) ) {
			check_se( $same( 'default', $p, $o ), "$cname: el valor por defecto no es el de PRO (" . json_encode( $p['default'] ?? null ) . ' / ' . json_encode( $o['default'] ?? null ) . ').' );
		}
		if ( isset( $p['options'] ) ) {
			check_se( array_keys( $p['options'] ) === array_keys( $o['options'] ), "$cname: las opciones no son las de PRO." );
		}
		if ( isset( $p['range'] ) ) {
			check_se( $p['range'] == $o['range'], "$cname: el rango no es el de PRO." );
		}
		if ( isset( $p['selectors_dictionary'] ) ) {
			check_se( array_keys( $p['selectors_dictionary'] ) === array_keys( $o['selectors_dictionary'] ), "$cname: el diccionario de selectores no tiene los valores de PRO." );
		}
		check_se( isset( $p['selectors'] ) === isset( $o['selectors'] ) && count( (array) ( $p['selectors'] ?? array() ) ) === count( (array) ( $o['selectors'] ?? array() ) ) || 'icon_clear_hover_color' === $cname, "$cname: selectores distintos de los de PRO en número." );
		if ( ! empty( $o['selectors'] ) ) {
			check_se( ! preg_match( '/e-search|--e-|e-focus|elementor-pagination/', implode( ' ', array_keys( $o['selectors'] ) ) . implode( ' ', $o['selectors'] ) . implode( ' ', (array) ( $o['selectors_dictionary'] ?? array() ) ) ), "$cname: selector o variable de PRO." );
		}
	}
	foreach ( $pro->groups as $gname => $p ) {
		$o = $own->groups[ $gname ] ?? null;
		check_se( null !== $o, "$gname: sin grupo propio." );
		foreach ( array( 'type', 'types', 'exclude', 'condition', '_section', '_tab', 'global', 'presets', 'separator' ) as $key ) {
			check_se( $same( $key, $p, $o ), "Grupo $gname: «{$key}» distinto de PRO." );
		}
		foreach ( (array) ( $p['fields_options'] ?? array() ) as $field => $opts ) {
			check_se( ( $opts['default'] ?? null ) === ( $o['fields_options'][ $field ]['default'] ?? null ) && array_keys( (array) ( $opts['options'] ?? array() ) ) === array_keys( (array) ( $o['fields_options'][ $field ]['options'] ?? array() ) ), "Grupo $gname: $field distinto de PRO." );
		}
		check_se( ! isset( $p['selector'] ) || ( ! preg_match( '/e-search|e-focus|elementor-pagination/', (string) $o['selector'] ) && str_contains( (string) $o['selector'], '.digi-search__' ) ), "Grupo $gname: selector propio." );
	}
	foreach ( $pro->sections as $sname => $p ) {
		check_se( isset( $own->sections[ $sname ] ) && $same( 'tab', $p, $own->sections[ $sname ] ) && $same( 'condition', $p, $own->sections[ $sname ] ), "Sección $sname: falta o cambia de pestaña o condición." );
	}
	check_se( array( 'any', 'product', 'post', 'page', 'portfolio' ) === array_keys( $own->groups['search_query']['fields_options']['post_type']['options'] ), 'Consulta: todos, productos, entradas, páginas y los tipos públicos propios (sin repetir productos), como PRO.' );
	check_se( 'Escribe para buscar…' === $own->controls['search_input_placeholder_text']['default'] && 'Buscar' === $own->controls['submit_button_text']['default'], 'Los textos por defecto propios están en español.' );
	check_se( Search::FOCUS . ' .digi-search__input' !== $own->groups['search_field_background_focus']['selector'] && '{{WRAPPER}} .digi-search__input:focus' === $own->groups['search_field_background_focus']['selector'] && isset( $own->controls['search_field_input_text_color_focus']['selectors'][ Search::FOCUS ] ), 'Pestaña «Con el foco»: el campo con el foco (PRO: la clase .e-focus que ponía su script).' );

	// 3. Cobertura del mapa.
	$map = require $root . 'modules/digitalisimo-convert/maps/search.php';
	check_se( 'search' === $map['source'] && $name === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'Origen, destino, estado y panel del mapa.' );
	$declared = array_merge( $map['keep'], array_keys( $map['rename'] ?? array() ), array_keys( $map['groups'] ), $map['ui'], array_keys( $map['drop'] ?? array() ) );
	foreach ( $pro->controls as $cname => $p ) {
		check_se( in_array( $cname, $declared, true ), "$cname: el mapa no lo declara." );
		if ( in_array( $cname, $map['ui'], true ) ) {
			check_se( in_array( $own->controls[ $cname ]['type'], array( 'heading', 'raw_html' ), true ), "$cname: encabezado o descripción del panel." );
		}
		if ( in_array( $p['type'] ?? 'text', array( 'text', 'textarea' ), true ) && isset( $p['default'] ) ) {
			check_se( ( $map['defaults'][ $cname ] ?? null ) === $p['default'], "$cname: el texto por defecto de PRO no está en defaults." );
		}
	}
	foreach ( array_keys( $pro->groups ) as $gname ) {
		check_se( ( $map['groups'][ $gname ] ?? '' ) === $gname, "$gname: grupo sin declarar." );
	}
	check_se( count( $declared ) === count( array_unique( $declared ) ) && count( $declared ) === count( $pro->controls ) + count( $pro->groups ), 'El mapa declara cada control de PRO una sola vez (' . count( $declared ) . ' / ' . ( count( $pro->controls ) + count( $pro->groups ) ) . ').' );

	// 4. Marcado.
	$html = $render( array() );
	check_se( str_starts_with( $html, '<search class="digi-search" role="search"><form class="digi-search__form" action="https://sitio.test/" method="get"><label class="digi-search__label" for="search-abc1234"><span class="elementor-screen-only">Buscar</span></label><div class="digi-search__field"><input id="search-abc1234" class="digi-search__input" type="search" name="s" value="" placeholder="Escribe para buscar…" autocomplete="off">' ), 'Formulario role=search con etiqueta asociada y campo sin combobox (sin resultados en vivo): ' . $html );
	check_se( str_contains( $html, '<button type="button" class="digi-search__clear digi-search__clear--off" aria-label="Borrar la búsqueda"><i class="fas fa-times" aria-hidden="true"></i></button>' ) && str_contains( $html, '<button class="digi-search__submit" type="submit"><span>Buscar</span></button><input type="hidden" name="e_search_props" value="abc1234-7"></form></search>' ), 'Botón «Borrar» real, oculto sin texto; envío con su texto; campo de la consulta de PRO.' );
	check_se( ! str_contains( $html, 'digi-search__results' ) && ! str_contains( $html, 'data-digi-search' ) && ! str_contains( $html, 'role="status"' ), 'Sin resultados en vivo, ni región ni configuración del script.' );
	$html = $render( array( 'submit_trigger' => 'key_enter', 'icon_search' => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ), 'icon_clear' => array( 'value' => '', 'library' => '' ), 'autocomplete' => 'yes', 'search_input_placeholder_text' => 'Busca "algo" <b>' ) );
	check_se( str_contains( $html, '<search class="digi-search digi-search--icon"' ) && str_contains( $html, '<span class="elementor-screen-only">Buscar</span><i class="fas fa-search" aria-hidden="true"></i></label>' ) && ! str_contains( $html, 'digi-search__clear' ) && str_contains( $html, 'autocomplete="on"' ) && str_contains( $html, 'placeholder="Busca &quot;algo&quot; &lt;b&gt;"' ), 'Icono del campo, sin «Borrar», autocompletar y texto escapado.' );
	check_se( str_contains( $html, '<button class="digi-search__submit elementor-screen-only" type="submit"><span class="elementor-screen-only">Buscar</span></button>' ), 'Con la tecla Intro, el botón sigue en el formulario pero sólo para lectores de pantalla (como PRO).' );
	$html = $render( array( 'submit_button_text' => '', 'icon_submit' => array( 'value' => 'fas fa-arrow-right', 'library' => 'fa-solid' ), 'submit_hover_animation' => 'grow' ) );
	check_se( str_contains( $html, '<button class="digi-search__submit elementor-animation-grow" type="submit" aria-label="Buscar"><i class="fas fa-arrow-right" aria-hidden="true"></i></button>' ), 'Botón sólo con icono: nombre accesible propio; animación al pasar el ratón.' );
	$html = $render( array( 'live_results' => 'yes', 'template_id' => '55', 'minimum_search_characters' => 2, 'enable_loader' => 'yes', 'pagination_type_options' => 'previous_next' ) );
	preg_match( '/data-digi-search="([^"]*)"/', $html, $m );
	$cfg = json_decode( html_entity_decode( $m[1] ?? '', ENT_QUOTES ), true );
	check_se( 'https://sitio.test/wp-json/digitalisimo-elements/v1/search' === ( $cfg['rest'] ?? '' ) && 2 === $cfg['min'] && Search::strings() === $cfg['t'], 'Resultados en vivo: la REST del propio sitio, el mínimo de caracteres y los textos del estado.' );
	check_se( str_contains( $html, 'aria-controls="results-abc1234"' ) && str_contains( $html, '<div id="results-abc1234" class="digi-search__results digi-search--prevnext" role="region" aria-label="Resultados de la búsqueda" hidden><div class="digi-search__box"></div><div class="digi-search__loader" hidden><svg' ) && str_contains( $html, '</svg></div></div><span class="elementor-screen-only" role="status"></span></div><button class="digi-search__submit"' ), 'Región de resultados con nombre, oculta, dentro del campo, con el indicador de carga y el estado anunciado.' );
	$wide = $render( array( 'live_results' => 'yes', 'template_id' => '55', 'results_is_dropdown_width' => 'widget_width' ) );
	check_se( preg_match( '/<\/div><div id="results-abc1234"[^>]*>.*<\/div><span class="elementor-screen-only" role="status"><\/span><button class="digi-search__submit"/', $wide ) && ! str_contains( $wide, 'digi-search__loader' ), 'Con «El del widget», la región va fuera del campo (ancho del widget); sin indicador de carga si no se pide.' );
	check_se( ! str_contains( $render( array( 'live_results' => 'yes', 'template_id' => '' ) ), 'digi-search__results' ), 'Sin plantilla de bucle no hay resultados en vivo (como PRO).' );

	// 5. Mensaje sin resultados y paginación.
	$widget           = new Search();
	$widget->settings = array( 'enable_nothing_found_message' => 'yes', 'nothing_found_message_text' => 'Nada <b>aquí</b><script>x</script>', 'nothing_found_message_html_tag' => 'h3' ) + $defaults( $own );
	ob_start();
	( new ReflectionMethod( $widget, 'handle_no_posts_found' ) )->invoke( $widget );
	check_se( '<h3 class="digi-search__nothing">Nada <b>aquí</b>x</h3>' === ob_get_clean(), 'Mensaje sin resultados con su etiqueta y el texto filtrado.' );
	$widget->settings['enable_nothing_found_message'] = '';
	ob_start();
	( new ReflectionMethod( $widget, 'handle_no_posts_found' ) )->invoke( $widget );
	check_se( '' === ob_get_clean(), 'Sin mensaje si se ocultó.' );
	$pages = static function ( array $settings, int $page, int $max, string $referer = 'https://sitio.test/buscar/?lang=es' ) use ( $own, $defaults ) {
		$GLOBALS['se']['referer'] = $referer;
		$widget           = new Search();
		$widget->settings = $settings + $defaults( $own );
		$widget->set_page_number( $page );
		( new ReflectionProperty( $widget, 'query' ) )->setValue( $widget, (object) array( 'max_num_pages' => $max, 'found_posts' => $max * 6 ) );
		ob_start();
		$widget->render_pagination();
		return (string) ob_get_clean();
	};
	check_se( '' === $pages( array(), 1, 4 ) && '' === $pages( array( 'pagination_type_options' => 'numbers' ), 1, 1 ), 'Sin paginación por defecto ni con una sola página.' );
	$html = $pages( array( 'pagination_type_options' => 'numbers_previous_next', 'pagination_prev_label' => 'Antes', 'pagination_next_label' => 'Después', 'page_limit_settings' => 3 ), 1, 8 );
	check_se( str_starts_with( $html, '<span class="prev page-numbers inactive">Antes</span>' ) && str_contains( $html, '<a rel="nofollow" class="page-numbers" href="https://sitio.test/buscar/%_%?e-search-page=2">' ) && 3 === $GLOBALS['se']['paginate']['total'] && '?e-search-page=%#%' === $GLOBALS['se']['paginate']['format'] && array( 'lang' => 'es' ) === $GLOBALS['se']['paginate']['add_args'] && str_contains( $GLOBALS['se']['paginate']['before_page_number'], 'elementor-screen-only' ), 'Paginación como PRO: límite de páginas, base de la página que pide, «anterior» inactivo en la primera y enlaces nofollow: ' . $html );
	$html = $pages( array( 'pagination_type_options' => 'previous_next' ), 3, 3 );
	check_se( str_ends_with( $html, '<span class="next page-numbers inactive">Next</span>' ) || str_ends_with( $html, '<span class="next page-numbers inactive">Siguiente</span>' ), '«Siguiente» inactivo en la última: ' . $html );

	// 6. REST.
	check_se( Search_Rest::valid_id( 'abc1234' ) && ! Search_Rest::valid_id( 'abc123' ) && ! Search_Rest::valid_id( 'abc12345' ) && ! Search_Rest::valid_id( 'abc-123' ) && ! Search_Rest::valid_id( 1234567 ), 'id de 7 letras o números.' );
	check_se( Search_Rest::valid_model( array( 'id' => 'abc1234', 'widgetType' => $name, 'settings' => array() ), 'abc1234' ) && ! Search_Rest::valid_model( array( 'id' => 'abc1234', 'widgetType' => 'search', 'settings' => array() ), 'abc1234' ) && ! Search_Rest::valid_model( array( 'id' => 'zzz9999', 'widgetType' => $name, 'settings' => array() ), 'abc1234' ), 'Modelo del editor: sólo de este widget.' );
	$GLOBALS['se']['posts'] = array( 10 => (object) array( 'post_status' => 'publish' ), 11 => (object) array( 'post_status' => 'draft' ) );
	$tree                   = array( array( 'id' => 'c000001', 'elType' => 'container', 'elements' => array( array( 'id' => 'abc1234', 'elType' => 'widget', 'widgetType' => $name, 'settings' => array() ), array( 'id' => 'pro1234', 'elType' => 'widget', 'widgetType' => 'search', 'settings' => array() ) ) ) );
	$GLOBALS['se']['docs']  = array( 10 => $tree, 11 => $tree );
	$req = static function ( array $p ) { return new SE_Request( $p + array( 'post_id' => 10, 'widget_id' => 'abc1234' ) ); };
	check_se( true === Search_Rest::permission( $req( array() ) ), 'Front: documento publicado con la Búsqueda dentro.' );
	check_se( false === Search_Rest::permission( $req( array( 'post_id' => 11 ) ) ) && false === Search_Rest::permission( $req( array( 'widget_id' => 'pro1234' ) ) ) && false === Search_Rest::permission( $req( array( 'widget_id' => 'zzz9999' ) ) ) && false === Search_Rest::permission( $req( array( 'post_id' => 99 ) ) ), 'Front: ni borradores, ni otro widget, ni un id que no está, ni un documento que no existe.' );
	$model = array( 'id' => 'abc1234', 'widgetType' => $name, 'settings' => array( 'live_results' => 'yes' ) );
	check_se( false === Search_Rest::permission( $req( array( 'post_id' => 11, 'widget_model' => $model ) ) ), 'Editor: sin permiso de edición, el modelo no vale.' );
	$GLOBALS['se']['editable'] = true;
	check_se( true === Search_Rest::permission( $req( array( 'post_id' => 11, 'widget_model' => $model ) ) ) && false === Search_Rest::permission( $req( array( 'post_id' => 11, 'widget_model' => array( 'widgetType' => 'html' ) + $model ) ) ), 'Editor: quien puede editar envía el modelo de este widget (y sólo de este).' );
	$GLOBALS['se']['editable'] = false;

	// 7. Plantilla del editor.
	ob_start();
	( new ReflectionMethod( $own, 'content_template' ) )->invoke( $own );
	$template = (string) ob_get_clean();
	foreach ( array( '<search class="digi-search', 'role="search"', 'for="search-{{ digiId }}"', 'digi-search__clear digi-search__clear--off', 'aria-label="Borrar la búsqueda"', 'role="region"', 'role=\\"status\\"', 'data-digi-search="{{ digiConfig }}"', 'digitalisimo-elements\\/v1\\/search' ) as $piece ) {
		check_se( str_contains( $template, $piece ) || str_contains( $template, stripslashes( $piece ) ), 'La plantilla del editor no tiene: ' . $piece );
	}
	check_se( ! str_contains( $template, '{{{ settings.' ), 'La plantilla no imprime ajustes sin escapar.' );

	// 8. Conversión.
	Registry::reset();
	$cases = array(
		'defaults' => array(),
		'field'    => array( 'search_input_placeholder_text' => 'Busca en la tienda', 'icon_search' => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ), 'autocomplete' => 'yes', 'icon_clear' => array( 'value' => 'fas fa-times-circle', 'library' => 'fa-solid' ), 'submit_trigger' => 'both', 'submit_button_text' => 'Ir', 'icon_submit' => array( 'value' => 'fas fa-arrow-right', 'library' => 'fa-solid' ) ),
		'live'     => array( 'live_results' => 'yes', 'template_id' => '321', 'minimum_search_characters' => 2, 'number_of_columns' => 3, 'number_of_columns_mobile' => 1, 'number_of_items' => 9, 'number_of_items_tablet' => 4, 'equal_height' => 'yes', 'enable_loader' => 'yes', 'enable_nothing_found_message' => '', 'nothing_found_message_html_tag' => 'h4' ),
		'query'    => array( 'live_results' => 'yes', 'template_id' => '321', 'search_query_post_type' => 'post', 'search_query_include' => array( 'terms' ), 'search_query_include_term_ids' => array( '5', '7' ), 'search_query_exclude' => array( 'authors' ), 'search_query_exclude_authors' => array( '2' ), 'search_query_orderby' => 'title', 'search_query_order' => 'asc', 'search_query_query_id' => 'tienda' ),
		'style'    => array( 'search_field_typography_typography' => 'custom', 'search_field_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'placeholder_color' => '#999999', 'search_field_background_normal_background' => 'classic', 'search_field_background_normal_color' => '#f5f5f5', 'search_field_input_text_color_normal' => '#111111', 'search_field_icon_search_normal_color_normal' => '#245eff', 'search_field_border_normal_border' => 'solid', 'search_field_border_normal_width' => array( 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ), 'search_field_box_shadow__normal_box_shadow_type' => 'yes', 'search_field_background_focus_background' => 'classic', 'search_field_background_focus_color' => '#ffffff', 'search_field_input_text_color_focus' => '#000000', 'search_field_border_focus_border' => 'dashed', 'search_field_search_input_transition' => array( 'unit' => 'ms', 'size' => 200 ), 'search_field_border_radius' => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ), 'search_field_padding_mobile' => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ), 'search_field_icon_label_size' => array( 'unit' => 'px', 'size' => 18 ), 'search_field_icon_gap' => array( 'unit' => 'px', 'size' => 8 ), 'search_field_submit_gap' => array( 'unit' => 'px', 'size' => 0 ) ),
		'clear'    => array( 'icon_clear_size' => array( 'unit' => 'px', 'size' => 16 ), 'icon_clear_normal_color' => '#888888', 'icon_clear_hover_color' => '#e63946', 'icon_clear_hover_transition' => array( 'unit' => 's', 'size' => 0.2 ) ),
		'submit'   => array( 'icon_submit' => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ), 'submit_typography_typography' => 'custom', 'submit_typography_font_weight' => '700', 'icon_submit_position' => 'end', 'icon_submit_size' => array( 'unit' => 'px', 'size' => 16 ), 'icon_submit_gap' => array( 'unit' => 'px', 'size' => 6 ), 'submit_text_color_normal' => '#ffffff', 'submit_icon_color_normal' => '#ffffff', 'submit_background_normal_background' => 'classic', 'submit_background_normal_color' => '#245eff', 'submit_border_normal_border' => 'solid', 'submit_box_shadow_normal_box_shadow_type' => 'yes', 'submit_text_color_hover' => '#111111', 'submit_icon_color_hover' => '#111111', 'submit_background_hover_background' => 'gradient', 'submit_border_hover_border' => 'dotted', 'submit_box_shadow_hover_box_shadow_type' => 'yes', 'submit_hover_animation' => 'grow', 'submit_hover_transition' => array( 'unit' => 'ms', 'size' => 300 ), 'submit_border_radius' => array( 'unit' => 'px', 'top' => '0', 'right' => '8', 'bottom' => '8', 'left' => '0', 'isLinked' => false ), 'submit_padding' => array( 'unit' => 'px', 'top' => '12', 'right' => '20', 'bottom' => '12', 'left' => '20', 'isLinked' => false ) ),
		'results'  => array( 'live_results' => 'yes', 'template_id' => '321', 'results_background_background' => 'classic', 'results_background_color' => '#fafafa', 'results_border_type_border' => 'solid', 'results_box_shadow_box_shadow_type' => 'yes', 'results_border_radius' => array( 'unit' => 'px', 'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'isLinked' => true ), 'results_padding' => array( 'unit' => 'px', 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'isLinked' => true ), 'results_distance_from_search_field' => array( 'unit' => 'px', 'size' => 4 ), 'results_is_dropdown_width' => 'widget_width', 'results_is_custom_width' => 'yes', 'results_custom_width' => array( 'unit' => 'px', 'size' => 600 ), 'results_max_height' => array( 'unit' => 'px', 'size' => 400 ), 'results_alignment' => 'center', 'search_result_column_gap' => array( 'unit' => 'px', 'size' => 12 ), 'search_result_row_gap' => array( 'unit' => 'px', 'size' => 12 ), 'enable_loader' => 'yes', 'search_result_loader_icon_color' => '#245eff', 'search_result_loader_icon_size' => array( 'unit' => 'px', 'size' => 24 ) ),
		'pages'    => array( 'live_results' => 'yes', 'template_id' => '321', 'pagination_type_options' => 'numbers_previous_next', 'pagination_prev_label' => '«', 'pagination_next_label' => '»', 'page_limit_settings' => 3, 'pagination_shorten_settings' => 'yes', 'style_additional_settings_alignment' => 'flex-end', 'style_additional_settings_vertical_position' => 'top', 'typography_title_typography' => 'custom', 'style_additional_settings_normal_color' => '#245eff', 'style_additional_settings_hover_color' => '#111111', 'style_button_color_icon_active' => '#e63946', 'page_numbers_space_between' => array( 'unit' => 'px', 'size' => 6 ), 'top_spacing' => array( 'unit' => 'px', 'size' => 10 ), 'bottom_spacing' => array( 'unit' => 'px', 'size' => 0 ) ),
		'nothing'  => array( 'live_results' => 'yes', 'template_id' => '321', 'nothing_found_message_text' => 'Nada por aquí', 'nothing_found_message_space_from_top' => array( 'unit' => 'px', 'size' => 10 ), 'nothing_found_message_space_from_bottom' => array( 'unit' => 'px', 'size' => 10 ), 'nothing_found_message_alignment' => 'start', 'nothing_found_message_typography_typography' => 'custom', 'nothing_found_message_color' => '#e63946', 'nothing_found_message_text_shadow_text_shadow_type' => 'yes', 'nothing_found_message_text_stroke_text_stroke_type' => 'yes' ),
		'globals'  => array( 'search_field_input_text_color_normal' => '', '__globals__' => array( 'search_field_input_text_color_normal' => 'globals/colors?id=text', 'search_field_input_text_color_focus' => 'globals/colors?id=primary', 'submit_background_normal_color' => 'globals/colors?id=accent', 'typography_title_typography' => 'globals/typography?id=primary', 'search_field_typography_typography' => 'globals/typography?id=text', '_background_color' => 'globals/colors?id=secondary' ) ),
		'dynamic'  => array( '__dynamic__' => array( 'search_input_placeholder_text' => '[elementor-tag id="t1" name="site-title" settings="%7B%7D"]', 'submit_button_text' => '[elementor-tag id="t2" name="shortcode" settings="%7B%7D"]' ) ),
		'css'      => array( 'custom_css' => "selector .e-search-input{letter-spacing:1px}\nselector.e-focus .e-search-submit{opacity:.9}\nselector .e-search-results-container .elementor-pagination a{text-decoration:underline}\n.elementor-widget-search .e-search-results-list{gap:4px}\nselector .e-search-input-wrapper > svg, selector .e-search-input-wrapper>i{display:none!important}", '_css_classes' => 'mi-buscador', '_element_id' => 'buscador', 'hide_tablet' => 'hidden-tablet' ),
	);
	$tree = array( array( 'id' => 'p1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'search', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_se( count( $cases ) === count( $log ), 'Se convierte cada búsqueda del árbol (' . count( $log ) . ').' );
	$own_keys = array_keys( $own->controls );
	$lands    = static function ( $key ) use ( $own_keys, $own ) {
		if ( in_array( Translator::base( (string) $key ), $own_keys, true ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( array_keys( $own->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$out = array();
	foreach ( $converted[0]['elements'] as $index => $element ) {
		check_se( $name === $element['widgetType'] && $tree[0]['elements'][ $index ]['id'] === $element['id'], $element['id'] . ': pasa a la Búsqueda con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_se( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_se( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_se( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni pro.approx; sólo el CSS: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_se( 'Type to start searching...' === $d['search_input_placeholder_text'] && 'Search' === $d['submit_button_text'] && 'Previous' === $d['pagination_prev_label'] && 'search' === $d['_digitalisimo_pro'], 'Una búsqueda sin cambios conserva los textos visibles de PRO.' );
	check_se( 'post' === $out['query']['search_query_post_type'] && array( '5', '7' ) === $out['query']['search_query_include_term_ids'] && 'tienda' === $out['query']['search_query_query_id'] && array( '2' ) === $out['query']['search_query_exclude_authors'], 'La consulta pasa entera (mismo grupo de las funciones avanzadas).' );
	check_se( 4 === $out['live']['number_of_items_tablet'] && 1 === $out['live']['number_of_columns_mobile'] && '321' === $out['live']['template_id'] && 'h4' === $out['live']['nothing_found_message_html_tag'], 'Resultados en vivo por dispositivo.' );
	check_se( '#000000' === $out['style']['search_field_input_text_color_focus'] && 'dashed' === $out['style']['search_field_border_focus_border'] && 8 === $out['style']['search_field_icon_gap']['size'] && 'gradient' === $out['submit']['submit_background_hover_background'] && 'end' === $out['submit']['icon_submit_position'] && 'center' === $out['results']['results_alignment'] && 'top' === $out['pages']['style_additional_settings_vertical_position'] && 'yes' === $out['nothing']['nothing_found_message_text_stroke_text_stroke_type'], 'Estilos de cada pestaña.' );
	check_se( array( 'search_field_input_text_color_normal' => 'globals/colors?id=text', 'search_field_input_text_color_focus' => 'globals/colors?id=primary', 'submit_background_normal_color' => 'globals/colors?id=accent', 'typography_title_typography' => 'globals/typography?id=primary', 'search_field_typography_typography' => 'globals/typography?id=text', '_background_color' => 'globals/colors?id=secondary' ) === $out['globals']['__globals__'] && 2 === count( $out['dynamic']['__dynamic__'] ), 'Globales del kit y etiquetas dinámicas siguen a su control.' );
	// El icono de borrar era hijo directo del envoltorio en PRO; en el propio va dentro del botón «Borrar».
	check_se( "selector .digi-search__input{letter-spacing:1px}\nselector:has(.digi-search__input:focus) .digi-search__submit{opacity:.9}\nselector .digi-search__results .digi-search__pages a{text-decoration:underline}\n.elementor-widget-digitalisimo-search .digi-search__list{gap:4px}\nselector .digi-search__field > .digi-search__clear > svg, selector .digi-search__field > .digi-search__clear > i{display:none!important}" === $out['css']['custom_css'] && 'mi-buscador' === $out['css']['_css_classes'] && 'hidden-tablet' === $out['css']['hide_tablet'], 'CSS personalizado con las clases propias (el foco, con :has; el icono de borrar, dentro de su botón) y Avanzado intacto: ' . json_encode( $out['css']['custom_css'] ) );
	check_se( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	$painted = $render( $out['defaults'] );
	check_se( str_contains( $painted, 'placeholder="Type to start searching..."' ) && str_contains( $painted, '<span>Search</span>' ), 'Lo convertido se pinta con los textos de PRO.' );
	$painted = $render( $out['field'] );
	check_se( str_contains( $painted, 'fas fa-search' ) && str_contains( $painted, 'fas fa-times-circle' ) && str_contains( $painted, '<span>Ir</span>' ) && str_contains( $painted, 'autocomplete="on"' ), 'Y con sus iconos, su texto y el autocompletar.' );

	echo 'DIGITALÍSIMO Elements: Búsqueda (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B), su REST y su mapa desde la Search de PRO (' . count( $cases ) . " casos) validados.\n";
}
