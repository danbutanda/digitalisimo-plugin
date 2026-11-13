<?php
/**
 * Kit de ventas de DIGITALÍSIMO Elements (`modules/digitalisimo-sales/`): definiciones cerradas de las tres
 * herramientas MCP (`digitalisimo-sales/list-templates`, `create-page` y `guide`), catálogo y variantes, placeholders,
 * el armado de la página (IDs regenerados, reemplazos, saneado por campo, URLs de otros sitios rechazadas, precios,
 * WhatsApp, fecha de cierre y urgencia), el borrador siempre (`Pages::create()` y la ejecución), permisos
 * (`edit_pages`; `publish_pages` nunca se consulta), el registro en el contrato de abilities del núcleo (con su registro
 * y sin él) y el aviso al importar un ZIP de plantillas sin la carga de archivos sin filtrar.
 *
 * Sin WordPress ni el núcleo: dobles mínimos. Falla con el código anterior: el módulo no existe.
 */
namespace Elementor\Modules\Mcp\Abilities {
	class Ability_Definition {
		public $label;
		public $description;
		public $category;
		public $output_schema;
		public $meta;
		public $permission_callback;
		public $input_schema;
		public function __construct( $label, $description, $category, $output_schema, $meta, $permission_callback, $input_schema = array() ) {
			$this->label               = $label;
			$this->description         = $description;
			$this->category            = $category;
			$this->output_schema       = $output_schema;
			$this->meta                = $meta;
			$this->permission_callback = $permission_callback;
			$this->input_schema        = $input_schema;
		}
	}
	abstract class Abstract_Ability {
		private $cached = null;
		abstract protected function get_ability_id(): string;
		abstract protected function get_definition(): Ability_Definition;
		abstract public function execute( $input = array() );
		final public function execute_guarded( $input = array() ) {
			$GLOBALS['sk_guarded'][] = $this->get_id();
			return $this->execute( $input );
		}
		public function check_permission(): bool {
			// Como el núcleo real: (bool) del callback; un WP_Error valdría true.
			return (bool) call_user_func( $this->definition()->permission_callback );
		}
		public function register(): void {
			$GLOBALS['sk_core_register'][] = $this->get_id();
		}
		public function get_id(): string {
			return $this->get_ability_id();
		}
		public function is_exposed_via_proxy(): bool {
			return true;
		}
		protected function definition(): Ability_Definition {
			if ( null === $this->cached ) {
				$this->cached = $this->get_definition();
			}
			return $this->cached;
		}
		public function definition_for_test(): Ability_Definition {
			return $this->definition();
		}
	}
}

namespace Elementor\Modules\Mcp\Registry {
	class Ability_Registry {
		public $abilities = array();
		public function add( $ability ): void {
			$this->abilities[ $ability->get_id() ] = $ability;
		}
	}
}

namespace Elementor\Modules\Mcp {
	class Module {
		public $test_registry;
		public function registry() {
			return $this->test_registry;
		}
	}
}

namespace Elementor\MCP\Composer\Admin {
	class McpSettingsController {
		public static function is_enabled(): bool {
			return ! empty( $GLOBALS['sk']['mcp'] );
		}
	}
}

namespace Elementor\Core\Files {
	class Uploads_Manager {
		const UNFILTERED_FILE_UPLOADS_KEY = 'elementor_unfiltered_files_upload';
		public static function are_unfiltered_uploads_enabled() {
			return ! empty( $GLOBALS['sk']['options']['elementor_unfiltered_files_upload'] ) && ! empty( $GLOBALS['sk']['can_json'] ) && empty( $GLOBALS['sk']['filter_off'] );
		}
	}
}

namespace Elementor {
	class Plugin {
		public static $instance;
	}
	class User {
		public static function is_current_user_can_upload_json() {
			return ! empty( $GLOBALS['sk']['can_json'] );
		}
		public static function is_current_user_can_edit_post_type( $type ) {
			return current_user_can( 'edit_pages' ) && empty( $GLOBALS['sk']['role_excluded'] );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	// Cualquier aviso o advertencia de PHP es un fallo.
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['sk_count'] = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['sk_count'];
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}
	function check_error( $result, string $code, string $message ) {
		check( $result instanceof WP_Error && $code === $result->get_error_code(), $message . ' (esperaba ' . $code . ', llegó ' . ( $result instanceof WP_Error ? $result->get_error_code() . ': ' . $result->get_error_message() : json_encode( is_array( $result ) ? array_keys( $result ) : $result ) ) . ')' );
	}

	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_data() {
			return $this->data;
		}
	}
	class Sk_Redirect extends Exception {
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['sk']['hooks'][] = array( $hook, $callback, $priority );
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['sk']['hooks'][] = array( $hook, $callback, $priority );
	}
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['sk']['hooks'] as $i => $h ) {
			if ( array( $hook, $callback, $priority ) === $h ) {
				unset( $GLOBALS['sk']['hooks'][ $i ] );
			}
		}
		$GLOBALS['sk']['hooks'] = array_values( $GLOBALS['sk']['hooks'] );
		return true;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$hooks = array_values( array_filter( $GLOBALS['sk']['hooks'], static function ( $h ) use ( $hook ) { return $hook === $h[0]; } ) );
		usort( $hooks, static function ( $a, $b ) { return $a[2] <=> $b[2]; } );
		foreach ( $hooks as $h ) {
			$value = call_user_func( $h[1], $value, ...$args );
		}
		return $value;
	}
	/** Como `Elementor\DB::iterate_data()` del núcleo 4.3.5 (`includes/db.php`). */
	function sk_iterate_data( $data_container, $callback ) {
		if ( isset( $data_container['elType'] ) ) {
			if ( ! empty( $data_container['elements'] ) ) {
				$data_container['elements'] = sk_iterate_data( $data_container['elements'], $callback );
			}
			return call_user_func( $callback, $data_container );
		}
		foreach ( $data_container as $element_key => $element_value ) {
			$element_data = sk_iterate_data( $data_container[ $element_key ], $callback );
			if ( null === $element_data ) {
				continue;
			}
			$data_container[ $element_key ] = $element_data;
		}
		return $data_container;
	}
	/**
	 * Como el saneador del núcleo 4.3.5 (`modules/content-sanitizer/module.php` y `Widget_Heading::sanitize()`): para
	 * quien no tiene `manage_options`, recorre los datos y lee `widgetType` de cada elemento; a los títulos les quita
	 * todos los atributos (la precedencia de `! substr(…) === 'data-'` los filtra todos).
	 */
	function sk_heading_sanitize( string $title ): string {
		return (string) preg_replace( '/<([a-z][a-z0-9]*)\b[^>]*?(\/?)>/i', '<$1$2>', $title );
	}
	function sk_core_sanitizer( $data, $document = null ) {
		if ( current_user_can( 'manage_options' ) || empty( $data['elements'] ) ) {
			return $data;
		}
		if ( false === strpos( json_encode( $data ), '"widgetType":"heading"' ) ) {
			return $data;
		}
		return sk_iterate_data(
			$data,
			static function ( $element ) {
				if ( 'heading' === $element['widgetType'] ) {
					$element['settings']['title'] = sk_heading_sanitize( $element['settings']['title'] );
				}
				return $element;
			}
		);
	}
	function current_user_can( $cap ) {
		$GLOBALS['sk']['asked'][] = $cap;
		return in_array( $cap, $GLOBALS['sk']['caps'], true );
	}
	function sanitize_text_field( $value ) {
		// Como wp_strip_all_tags(): fuera <script> y <style> con su contenido, y después las etiquetas.
		$value = preg_replace( '#<(script|style)[^>]*?>.*?</\1>#si', '', (string) $value );
		return trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) );
	}
	function wp_kses_post( $value ) {
		$value = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', (string) $value );
		$value = strip_tags( $value, '<p><br><strong><b><em><i><span><del><a><ul><ol><li><small>' );
		return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function sanitize_file_name( $name ) {
		return preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $name );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function wp_verify_nonce( $nonce, $action ) {
		return 'buen-nonce' === $nonce && 'elementor_ajax' === $action ? 1 : false;
	}
	function wp_safe_redirect( $url ) {
		$GLOBALS['sk']['redirect'] = $url;
		throw new Sk_Redirect( $url );
	}
	function add_query_arg( $key, $value, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( $value );
	}
	function admin_url( $path = '' ) {
		return 'https://sitio.test/wp-admin/' . $path;
	}
	function esc_url( $url ) {
		return (string) $url;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function get_option( $name, $default = false ) {
		return $GLOBALS['sk']['options'][ $name ] ?? $default;
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
	function home_url( $path = '' ) {
		return 'https://sitio.test' . $path;
	}
	function wp_timezone() {
		return new DateTimeZone( 'America/Mexico_City' );
	}
	function post_type_exists( $type ) {
		return 'page' === $type;
	}
	function post_type_supports( $type, $feature ) {
		return 'page' === $type && 'elementor' === $feature;
	}
	function wp_insert_post( $data, $wp_error = false ) {
		$GLOBALS['sk']['inserted'][] = $data;
		$id                          = 100 + count( $GLOBALS['sk']['inserted'] );
		$GLOBALS['sk']['status'][ $id ] = $data['post_status'];
		return $id;
	}
	function wp_delete_post( $id, $force = false ) {
		$GLOBALS['sk']['deleted'][] = array( $id, $force );
		unset( $GLOBALS['sk']['status'][ $id ] );
	}
	function get_post_status( $id ) {
		return $GLOBALS['sk']['status'][ $id ] ?? false;
	}
	function wp_update_post( $data ) {
		$GLOBALS['sk']['updated'][] = $data;
		if ( isset( $data['post_status'] ) ) {
			$GLOBALS['sk']['status'][ $data['ID'] ] = $data['post_status'];
		}
	}
	function remove_query_arg( $key, $url ) {
		return preg_replace( '/[&?]' . preg_quote( $key, '/' ) . '=[^&]*/', '', $url );
	}
	function get_preview_post_link( $id ) {
		return 'https://sitio.test/?page_id=' . $id . '&preview=true';
	}
	function wp_register_ability( $id, $args ) {
		$GLOBALS['sk']['abilities'][ $id ] = $args;
	}
	function wp_register_ability_category( $id, $args ) {
		$GLOBALS['sk']['categories'][ $id ] = $args;
	}

	/** El documento del constructor, como lo usa `Pages`. */
	class Sk_Document {
		public $id;
		public $built = false;
		public $saved = null;
		public function __construct( $id ) {
			$this->id = $id;
		}
		public function set_is_built_with_elementor( $on ) {
			$this->built = $on;
		}
		public function update_settings( array $settings ) {
			$GLOBALS['sk']['doc_settings'][ $this->id ] = array_replace_recursive( $GLOBALS['sk']['doc_settings'][ $this->id ] ?? array(), $settings );
			return $this;
		}
		public function save( $data ) {
			$GLOBALS['sk']['saves'][] = array( $this->id, $data );
			try {
				$GLOBALS['sk']['filtered'][] = apply_filters( 'elementor/document/save/data', $data, $this );
			} catch ( Throwable $e ) {
				$GLOBALS['sk']['save_error'] = $e->getMessage();
				throw $e;
			}
			if ( ! empty( $GLOBALS['sk']['save_fails'] ) ) {
				return false;
			}
			if ( ! empty( $GLOBALS['sk']['save_publishes'] ) ) {
				$GLOBALS['sk']['status'][ $this->id ] = 'publish';
			}
			return true;
		}
		public function get_edit_url() {
			return 'https://sitio.test/wp-admin/post.php?post=' . $this->id . '&action=elementor';
		}
		public function get_wp_preview_url() {
			return 'https://sitio.test/?page_id=' . $this->id . '&preview=true&preview_nonce=abc';
		}
	}
	class Sk_Documents {
		public $docs = array();
		public function get( $id, $from_cache = true ) {
			if ( ! empty( $GLOBALS['sk']['no_document'] ) ) {
				return false;
			}
			if ( ! isset( $this->docs[ $id ] ) ) {
				$this->docs[ $id ] = new Sk_Document( $id );
			}
			return $this->docs[ $id ];
		}
	}
	class Sk_Experiments {
		public function is_feature_active( $name ) {
			return 'container' === $name && ! empty( $GLOBALS['sk']['containers'] );
		}
	}
	class Sk_Modules {
		public $mcp;
		public function get_modules( $name ) {
			return 'mcp' === $name ? $this->mcp : null;
		}
	}

	function sk_reset( array $caps = array( 'edit_pages', 'edit_posts', 'read' ) ) {
		$GLOBALS['sk'] = array(
			'caps'       => $caps,
			'asked'      => array(),
			'hooks'      => array(),
			'options'    => array(),
			'mcp'        => true,
			'containers' => true,
			'can_json'   => true,
			'inserted'   => array(),
			'status'     => array(),
			'saves'      => array(),
			'abilities'  => array(),
			'categories' => array(),
		);
		$GLOBALS['sk_core_register'] = array();
		$GLOBALS['sk_guarded']       = array();
		$plugin                      = new stdClass();
		$plugin->experiments         = new Sk_Experiments();
		$plugin->documents           = new Sk_Documents();
		$plugin->modules_manager     = new Sk_Modules();
		\Elementor\Plugin::$instance = $plugin;
		$_REQUEST                    = array();
		$_GET                        = array();
		$_FILES                      = array();
	}
	sk_reset();

	$base = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-sales';
	require_once $base . '/class-module.php';
	\Digitalisimo\Elements\Sales\Module::init();
	$init_hooks = array_column( $GLOBALS['sk']['hooks'], 0 );

	use Digitalisimo\Elements\Sales\Builder;
	use Digitalisimo\Elements\Sales\Catalog;
	use Digitalisimo\Elements\Sales\Import_Notice;
	use Digitalisimo\Elements\Sales\Pages;
	use Digitalisimo\Elements\Sales\Placeholders;
	use Digitalisimo\Elements\Sales\Mcp\Executor;
	use Digitalisimo\Elements\Sales\Mcp\Tools;

	$ids8 = array( 'venta-completa', 'venta-corta', 'captacion', 'gracias-registro', 'carrito', 'oferta-unica', 'gracias-compra', 'prueba-social' );

	/** Todos los IDs (elementos y `_id` de listas) de un árbol. */
	function sk_ids( array $tree, array &$out = array() ): array {
		foreach ( $tree as $k => $v ) {
			if ( is_array( $v ) ) {
				sk_ids( $v, $out );
			} elseif ( ( 'id' === $k || '_id' === $k ) && is_string( $v ) ) {
				$out[] = $v;
			}
		}
		return $out;
	}
	function sk_widgets( array $els, array &$count = array() ): array {
		foreach ( $els as $e ) {
			if ( 'widget' === ( $e['elType'] ?? '' ) ) {
				$count[ $e['widgetType'] ] = ( $count[ $e['widgetType'] ] ?? 0 ) + 1;
			}
			sk_widgets( $e['elements'] ?? array(), $count );
		}
		return $count;
	}
	function sk_strings( array $tree ): string {
		$out = array();
		array_walk_recursive(
			$tree,
			static function ( $v ) use ( &$out ) {
				if ( is_string( $v ) ) {
					$out[] = $v;
				}
			}
		);
		return implode( "\n", $out );
	}
	function sk_find( array $els, callable $match ): array {
		$out = array();
		foreach ( $els as $e ) {
			if ( $match( $e ) ) {
				$out[] = $e;
			}
			$out = array_merge( $out, sk_find( $e['elements'] ?? array(), $match ) );
		}
		return $out;
	}
	$env = array(
		'site_host' => 'sitio.test',
		'timezone'  => new DateTimeZone( 'America/Mexico_City' ),
		'now'       => strtotime( '2026-11-13 12:00:00 UTC' ),
	);
	$build = static function ( string $id, array $input, string $variant = 'sections' ) use ( $env ) {
		return Builder::build( Catalog::load( $id, $variant ), Catalog::meta( $id ), $input, $env );
	};

	// ================================================================ 1. Definiciones
	check( Tools::TOOLS === array( 'list-templates', 'create-page', 'guide' ), 'tres herramientas' );
	foreach ( Tools::TOOLS as $tool ) {
		$d = Tools::definition( $tool );
		check( 'digitalisimo-sales/' . $tool === Tools::id( $tool ) && $tool === Tools::tool_of( Tools::id( $tool ) ), "$tool: id digitalisimo-sales/<verbo>" );
		check( 'digitalisimo-sales' === $d['category'], "$tool: categoría propia" );
		check( false === $d['input_schema']['additionalProperties'] && 'object' === $d['input_schema']['type'], "$tool: entrada cerrada" );
		check( false === strpos( json_encode( $d['input_schema'] ), 'post_id' ), "$tool: sin post_id" );
		check( false === $d['meta']['show_in_rest'] && array( 'public' => false, 'type' => 'tool' ) === $d['meta']['mcp'], "$tool: sólo en el servidor de Elements" );
		check( false === $d['meta']['annotations']['destructive'], "$tool: no destructiva" );
		check( ( 'create-page' !== $tool ) === $d['meta']['annotations']['readonly'] && ( 'create-page' !== $tool ) === $d['meta']['annotations']['idempotent'], "$tool: sólo create-page escribe" );
		check( ! preg_match( '/\bElementor\b/', $d['label'] . ' ' . $d['description'] ), "$tool: sin el nombre del constructor original" );
		check( preg_match( '/[áéíóúñ]/u', $d['description'] ) && strlen( $d['description'] ) > 200, "$tool: descripción en español para el modelo" );
	}
	check( 'digitalisimo-sales/x' !== Tools::id( 'x' ) || '' === Tools::tool_of( 'digitalisimo-sales/x' ), 'un id desconocido no es herramienta' );
	check( '' === Tools::tool_of( 'digitalisimo-woo/products' ), 'otro prefijo no es herramienta' );
	$cp = Tools::input_schema( 'create-page' );
	check( array( 'template', 'title' ) === $cp['required'], 'create-page exige template y title' );
	check( array( 'template', 'title', 'replacements', 'omit_blocks', 'whatsapp', 'close_date' ) === array_keys( $cp['properties'] ), 'create-page: plantilla, título, reemplazos, bloques que se quitan, WhatsApp y fecha de cierre, nada más' );
	check( preg_match( '/nunca se reordena/', Tools::DESCRIPTIONS['create-page'] ) && preg_match( '/nunca reordenar/', Tools::DESCRIPTIONS['list-templates'] ) && preg_match( '/no cambiar el orden/', Tools::DESCRIPTIONS['guide'] ), 'las descripciones dicen que un bloque se quita pero el orden no se cambia' );
	check( $ids8 === $cp['properties']['template']['enum'], 'template: las ocho plantillas' );
	check( '^[0-9]{8,15}$' === $cp['properties']['whatsapp']['pattern'], 'whatsapp: sólo dígitos' );
	check( preg_match( '/BORRADOR/', Tools::DESCRIPTIONS['create-page'] ) && preg_match( '/nunca la publica/', Tools::DESCRIPTIONS['create-page'] ), 'create-page dice que sólo crea borradores' );
	check( preg_match( '/nunca inventes testimonios/', Tools::DESCRIPTIONS['create-page'] ), 'create-page dice que no se inventa nada' );
	check( array_keys( Tools::input_schema( 'guide' )['properties'] ) === array( 'section' ) && ! isset( Tools::input_schema( 'guide' )['required'] ), 'guide: section opcional' );
	check( array_keys( Tools::input_schema( 'list-templates' )['properties'] ) === array( 'template' ), 'list-templates: template opcional' );
	// Nombres de otras empresas en los textos de las herramientas: sólo los de una excepción de marcas.json para
	// mcp/class-tools.php, y cada aparición dentro de sus `frases` (WhatsApp: la entrada whatsapp y la regla del llamado
	// a la acción, pendiente de confirmar por el operador).
	$marcas = json_decode( file_get_contents( __DIR__ . '/playground/brand-audit/marcas.json' ), true );
	$rx     = static function ( string $pattern ): string {
		return '/' . str_replace( '/', '\/', $pattern ) . '/u';
	};
	$tools_ex = array_values(
		array_filter(
			$marcas['excepciones'],
			static function ( $ex ) use ( $rx ) {
				return preg_match( $rx( $ex['archivos'] ), 'digitalisimo-elements/modules/digitalisimo-sales/mcp/class-tools.php' );
			}
		)
	);
	$tools_used = array_fill( 0, count( $tools_ex ), 0 );
	foreach ( Tools::TOOLS as $tool ) {
		$text = Tools::LABELS[ $tool ] . ' ' . Tools::DESCRIPTIONS[ $tool ] . ' ' . json_encode( Tools::input_schema( $tool ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		foreach ( $marcas['nombres'] as $n ) {
			$left = $text;
			foreach ( $tools_ex as $k => $ex ) {
				if ( in_array( $n['nombre'], $ex['nombres'], true ) && preg_match( $rx( $n['patron'] ), $left ) ) {
					$left = (string) preg_replace( $rx( $ex['frases'] ), '', $left );
					++$tools_used[ $k ];
				}
			}
			check( ! preg_match( $rx( $n['patron'] ), $left ), "$tool: «{$n['nombre']}» en sus textos fuera de una excepción declarada" );
		}
	}
	foreach ( $tools_ex as $k => $ex ) {
		check( 'tests/elements-sales-mcp.php' === ( $ex['comprueba'] ?? '' ) && $tools_used[ $k ] > 0, 'marcas.json: la excepción de los textos de las herramientas se usa y la aplica esta prueba' );
	}

	// ================================================================ 2. Catálogo y variantes
	check( $ids8 === Catalog::ids(), 'el manifiesto trae las ocho plantillas en orden' );
	foreach ( $ids8 as $id ) {
		$s = Catalog::load( $id, 'sections' );
		$c = Catalog::load( $id, 'containers' );
		check( $s && $c && '0.4' === $s['version'] && 'page' === $c['type'], "$id: las dos variantes se leen" );
		check( sk_widgets( $s['content'] ) === sk_widgets( $c['content'] ) && sk_widgets( $s['content'] ) === Catalog::meta( $id )['widget_types'], "$id: mismos widgets en las dos variantes y en el manifiesto" );
		check( false === strpos( json_encode( $c['content'] ), '"elType":"section"' ) && false === strpos( json_encode( $c['content'] ), '"elType":"column"' ), "$id: la variante de contenedores no lleva secciones ni columnas" );
		check( false === strpos( json_encode( $s['content'] ), '"elType":"container"' ), "$id: la variante de secciones no lleva contenedores" );
	}
	check( null === Catalog::load( 'venta-completa', '../sections' ) && null === Catalog::load( 'no-existe', 'sections' ), 'ninguna ruta sale de la entrada' );
	check( 'containers' === Catalog::variant(), 'con el experimento de contenedores activo: contenedores' );
	$GLOBALS['sk']['containers'] = false;
	check( 'sections' === Catalog::variant(), 'sin contenedores: secciones clásicas' );
	$GLOBALS['sk']['containers'] = true;
	foreach ( Catalog::GUIDE_SECTIONS as $section => $title ) {
		$text = Catalog::guide( $section );
		check( 0 === strpos( $text, '## ' . $title . "\n" ) && strlen( $text ) > 200, "guía: la parte $section" );
	}
	check( '' === Catalog::guide( 'no-existe' ) && strlen( Catalog::guide() ) > 5000, 'guía: entera o por partes; una parte desconocida no devuelve nada' );
	check( false !== strpos( Catalog::guide( 'formulas' ), '«De [antes] a [después] en [tiempo]»' ), 'guía: las fórmulas' );
	check( 26 === preg_match_all( '/^\| [0-9]+ \|/m', Catalog::guide( 'orden' ) ), 'guía: el orden en 26 bloques' );
	check( false !== strpos( Catalog::guide( 'orden' ), 'Se puede quitar un bloque que no aplique, pero no se cambia el' ) && false !== strpos( Catalog::guide( 'reglas' ), 'El orden de los bloques no se cambia' ), 'guía: se quita un bloque, no se cambia el orden' );
	$keys01 = array_column( Catalog::meta( 'venta-completa' )['blocks'], 'key' );
	check( 26 === count( $keys01 ) && preg_match_all( '/\(`([a-z0-9-]+)`\)/', Catalog::guide( 'orden' ), $mm ) && $keys01 === $mm[1], 'guía: las claves de los 26 bloques de la 01, en su orden' );
	// La versión del kit con la persona de DIGITALÍSIMO no entra en Elements.
	foreach ( glob( $base . '/templates/*/*.json' ) as $file ) {
		check( ! preg_match( '/Daniel|Butanda/i', file_get_contents( $file ) ), basename( dirname( $file ) ) . '/' . basename( $file ) . ': sin la persona de la versión propia' );
	}
	check( 16 === count( glob( $base . '/templates/*/*.json' ) ) && ! preg_match( '/Daniel|Butanda/i', file_get_contents( $base . '/templates/manifest.json' ) . Catalog::guide() ), 'ni en el manifiesto ni en la guía' );
	check( 15 === preg_match_all( '/^\| [^|#-][^|]* \| «/mu', Catalog::guide( 'formulas' ) ), 'guía: 15 fórmulas' );

	// ================================================================ 3. Placeholders
	check( array( array( 0, 9, '[Nombre]' ) ) !== Placeholders::spans( '[Nombre]' ) && array( array( 0, 8, '[Nombre]' ) ) === Placeholders::spans( '[Nombre]' ), 'un tramo' );
	$sp = Placeholders::spans( 'A [Bonus: «[cantidad] [recurso]»] y $[precio] [sin cerrar' );
	check( 2 === count( $sp ) && '[Bonus: «[cantidad] [recurso]»]' === $sp[0][2] && '[precio]' === $sp[1][2], 'tramos de primer nivel: el anidado es uno solo; uno sin cerrar no cuenta' );
	$sum = Placeholders::summary( Catalog::load( 'venta-completa', 'sections' )['content'] );
	check( 'cierre' === $sum['[día de cierre]']['kind'] && 'cierre' === $sum['[hora de cierre]']['kind'], 'tipo cierre' );
	// Cada contador de cada plantilla lleva una fecha que el widget puede leer (new DateTime() falla con un texto) y ya
	// pasada (nunca una futura inventada).
	foreach ( array( 'sections', 'containers' ) as $variant ) {
		foreach ( $ids8 as $id ) {
			foreach ( sk_find( Catalog::load( $id, $variant )['content'], static function ( $e ) { return 'countdown' === ( $e['widgetType'] ?? '' ); } ) as $cd ) {
				$ok = true;
				try {
					$at = new DateTime( $cd['settings']['due_date'], new DateTimeZone( 'America/Mexico_City' ) );
				} catch ( Exception $e ) {
					$ok = false;
				}
				check( $ok && $at->getTimestamp() < strtotime( '2026-01-01' ), "$id/$variant: el contador trae una fecha legible y ya pasada" );
			}
		}
	}
	check( 'enlace' === $sum['[enlace del video]']['kind'] && 8 === $sum['[enlace del video]']['count'], 'tipo enlace (los ocho videos)' );
	check( 'precio' === $sum['[precio]']['kind'] && 'precio' === $sum['[X]']['kind'], 'tipo precio' );
	check( 'dato_real' === $sum['[Nombre real]']['kind'] && 'dato_real' === $sum['[Testimonio real, con permiso: qué logró y en cuánto tiempo.]']['kind'], 'tipo dato real' );
	check( 'texto' === $sum['[producto]']['kind'] && $sum['[producto]']['count'] >= 5, 'tipo texto, contado también en el mensaje de WhatsApp' );
	check( ! isset( $sum['52XXXXXXXXXX'] ), 'el número de muestra no es un placeholder' );
	$sum4 = Placeholders::summary( Catalog::load( 'gracias-registro', 'sections' )['content'] );
	check( 'enlace' === $sum4['[enlace del grupo]']['kind'], 'el enlace del grupo es de tipo enlace' );
	check( 'whatsapp' === Placeholders::field( 'button', 'link.url', 'https://wa.me/52XXXXXXXXXX?text=x' ) && 'url' === Placeholders::field( 'button', 'link.url', '/carrito/' ) && 'rich' === Placeholders::field( 'text-editor', 'editor', 'x' ) && 'plain' === Placeholders::field( 'button', 'text', 'x' ) && 'date' === Placeholders::field( 'countdown', 'due_date', 'x' ) && 'rich' === Placeholders::field( 'accordion', 'tabs.tab_content', 'x' ) && 'plain' === Placeholders::field( 'accordion', 'tabs.tab_title', 'x' ), 'tipos de campo' );

	// ================================================================ 4. Armado
	foreach ( array( 'sections', 'containers' ) as $variant ) {
		foreach ( $ids8 as $id ) {
			$tpl = Catalog::load( $id, $variant );
			$r   = $build( $id, array(), $variant );
			check( is_array( $r ) && isset( $r['elements'] ), "$id/$variant: se arma sin reemplazos" );
			$new = sk_ids( $r['elements'] );
			$old = sk_ids( $tpl['content'] );
			check( count( $new ) === count( array_unique( $new ) ) && ! array_intersect( $new, $old ), "$id/$variant: todos los IDs regenerados y sin repetirse" );
			check( ! array_filter( $new, static function ( $x ) { return ! preg_match( '/^[0-9a-f]{7}$/', $x ); } ), "$id/$variant: IDs de siete hexadecimales" );
			check( ( false !== strpos( json_encode( $tpl ), '52XXXXXXXXXX' ) ) === $r['whatsapp_pending'] && 0 === $r['filled'], "$id/$variant: sin datos, nada se llena y el número de muestra se informa" );
			if ( 'venta-completa' !== $id && 'carrito' !== $id ) {
				check( sk_widgets( $r['elements'] ) === sk_widgets( $tpl['content'] ) && array() === $r['removed_urgency'], "$id/$variant: sin urgencia, los mismos widgets" );
			}
		}
	}
	$tpl1 = Catalog::load( 'venta-completa', 'sections' );
	$r    = $build( 'venta-completa', array() );
	check( 1 === count( $r['removed_urgency'] ) && ! isset( sk_widgets( $r['elements'] )['countdown'] ), 'sin close_date: se quita la barra de cierre, sin ningún contador' );
	check( count( $r['elements'] ) === count( $tpl1['content'] ) - 1, 'sin close_date: una sección menos (la barra)' );
	$pend = array_column( $r['pending'], 'count', 'placeholder' );
	check( isset( $pend['[día de cierre]'] ) && ! isset( $pend['[fecha y hora de cierre]'] ), 'sin close_date: los textos de cierre del resumen quedan pendientes; no queda ningún contador' );
	check( $r['pending_count'] === array_sum( $pend ), 'pending_count suma las apariciones' );
	$r5 = $build( 'carrito', array() );
	check( 1 === count( $r5['removed_urgency'] ) && ! isset( sk_widgets( $r5['elements'] )['countdown'] ), 'carrito sin close_date: se quita su contador' );

	// Reemplazos de texto, en todas las apariciones o en orden.
	$r = $build(
		'venta-completa',
		array(
			'replacements' => array(
				'[producto]'    => 'Curso de Ventas',
				'Nombre real'   => array( 'Ana López', 'Luis Pérez' ),
				'[no existe]'   => 'x',
				'[Marca]'       => '   ',
				'[precio]'      => '1,997',
				'[mensualidad]' => '199.70',
			),
		)
	);
	$all = sk_strings( $r['elements'] );
	$left = Placeholders::summary( $r['elements'] );
	$filled_n = substr_count( $all, 'Curso de Ventas' ) + substr_count( $all, 'Curso%20de%20Ventas' );
	check( ! isset( $left['[producto]'] ) && $filled_n === $sum['[producto]']['count'], '«[producto]» se llena en todas sus apariciones (las de dentro de otro placeholder son parte de su fórmula): ' . $filled_n . ' de ' . $sum['[producto]']['count'] . ( isset( $left['[producto]'] ) ? ', quedan ' . $left['[producto]']['count'] : '' ) );
	check( false !== strpos( $all, 'text=Hola%2C%20quiero%20saber%20si%20Curso%20de%20Ventas%20es%20para%20m%C3%AD' ), 'también dentro del mensaje de WhatsApp, codificado' );
	check( 1 === substr_count( $all, 'Ana López' ) && 1 === substr_count( $all, 'Luis Pérez' ) && $left['[Nombre real]']['count'] === $sum['[Nombre real]']['count'] - 2, 'una lista llena las apariciones en orden y el resto queda pendiente' );
	check( array( 'placeholder' => '[no existe]', 'reason' => 'No está en esta plantilla (list-templates con template da la lista exacta).' ) === $r['ignored'][0] && '[Marca]' === $r['ignored'][1]['placeholder'], 'un placeholder que no está o un valor vacío se ignoran y se informan' );
	check( in_array( '[Nombre real]', $r['real_data'], true ) && ! in_array( '[producto]', $r['real_data'], true ), 'los datos reales llenados se piden revisar' );
	check( false !== strpos( $all, 'Hoy: <span style="color:#00A86B">$1,997 MXN</span>' ), 'el precio queda en pesos' );
	check( in_array( '[producto]', $r['filled_keys'], true ) && $r['filled'] > 7, 'filled cuenta lo que se llenó' );
	check( ! isset( array_column( $r['pending'], 'count', 'placeholder' )['[producto]'] ), 'lo llenado ya no está pendiente' );

	// Saneado por campo.
	$r   = $build(
		'venta-corta',
		array(
			'replacements' => array(
				'[Beneficio 1]'               => '<b>Dueños</b> de negocio',
				'[La promesa en una frase y por qué ahora.]' => '<strong>Vende más</strong> <em>hoy</em>',
			),
		)
	);
	$all = sk_strings( $r['elements'] );
	check( false !== strpos( $all, '"title_text":"Dueños de negocio"' ) || false !== strpos( $all, "\nDueños de negocio\n" ), 'campo de texto plano: sin etiquetas ni scripts' );
	check( false === strpos( $all, '<b>Dueños' ), 'campo de texto plano: nada del HTML recibido' );
	check( false !== strpos( $all, '<p><strong>Vende más</strong> <em>hoy</em></p>' ), 'campo con HTML: el formato en línea se conserva (wp_kses_post)' );

	// URLs de otros sitios: rechazadas, con esquema, `//` o `www.` (un dominio suelto en un texto no es un enlace: R4).
	foreach ( array( 'https://example.com/x', 'http://otro.test', '//evil.test/a', 'www.otro.com', 'Visita www.otro.mx/oferta', 'javascript:alert(1)', 'data:text/html;base64,AAAA', 'HTTPS://EVIL.TEST' ) as $bad ) {
		check_error( $build( 'venta-corta', array( 'replacements' => array( '[producto]' => $bad ) ) ), 'digitalisimo_sales_external_url', "URL de otro sitio rechazada: $bad" );
	}
	check_error( $build( 'venta-corta', array( 'replacements' => array( '[producto]' => array( 'Bien', 'https://evil.test' ) ) ) ), 'digitalisimo_sales_external_url', 'también dentro de una lista' );
	foreach ( array( 'Escríbenos: hola@sitio.com', 'https://wa.me/5218711234567', 'https://sitio.test/oferta/', 'Ve a /carrito/', 'S.A. de C.V.' ) as $good ) {
		check( '' === Builder::external_url( $good, 'sitio.test' ), "permitido: $good" );
	}
	// Precios.
	foreach ( array( '$1,997', '49 USD', '1.997,00', 'mil' ) as $bad ) {
		check_error( $build( 'venta-corta', array( 'replacements' => array( '[precio]' => $bad ) ) ), 'digitalisimo_sales_price', "precio que no es una cifra en pesos: $bad" );
	}
	// Cierre y enlaces no se llenan con reemplazos.
	check_error( $build( 'venta-completa', array( 'replacements' => array( '[día de cierre]' => '15' ) ) ), 'digitalisimo_sales_close_placeholder', 'el cierre sólo con close_date' );
	check_error( $build( 'venta-completa', array( 'replacements' => array( '[enlace del video]' => '/video/' ) ) ), 'digitalisimo_sales_link_placeholder', 'un enlace se pone en el editor' );
	check_error( $build( 'venta-corta', array( 'replacements' => array( '[producto]' => array( 'a' ) ) + array_fill_keys( range( 1, 401 ), 'x' ) ) ), 'digitalisimo_sales_replacements', 'demasiados reemplazos' );
	check_error( $build( 'venta-corta', array( 'replacements' => array( '[producto]' => array( array( 'x' ) ) ) ) ), 'digitalisimo_sales_replacements', 'un valor que no es texto' );
	check_error( $build( 'venta-corta', array( 'replacements' => 'x' ) ), 'digitalisimo_sales_replacements', 'replacements que no es objeto' );

	// WhatsApp.
	$r   = $build( 'venta-corta', array( 'whatsapp' => '5218711234567' ) );
	$all = sk_strings( $r['elements'] );
	check( ! $r['whatsapp_pending'] && false === strpos( $all, '52XXXXXXXXXX' ) && false !== strpos( $all, 'https://wa.me/5218711234567?text=' ), 'whatsapp: todos los botones al número del sitio' );
	foreach ( array( '+5218711234567', '52 871 123 4567', '871-123', '1234567', 'abc' ) as $bad ) {
		check_error( $build( 'venta-corta', array( 'whatsapp' => $bad ) ), 'digitalisimo_sales_whatsapp', "whatsapp sólo dígitos: $bad" );
	}

	// Fecha de cierre.
	$r   = $build( 'venta-completa', array( 'close_date' => '2026-12-15 23:59' ) );
	$cds = sk_find( $r['elements'], static function ( $e ) { return 'countdown' === ( $e['widgetType'] ?? '' ); } );
	check( 1 === count( $cds ) && '2026-12-15 23:59' === $cds[0]['settings']['due_date'], 'close_date llena el contador' );
	$all = sk_strings( $r['elements'] );
	check( false !== strpos( $all, 'La inscripción cierra el 15 de diciembre a las 23:59' ) && false === strpos( $all, 'de cierre]' ) && array() === $r['removed_urgency'] && '2026-12-15 23:59' === $r['close_date'], 'close_date llena los textos de cierre y conserva la urgencia' );
	check( sk_widgets( $r['elements'] ) === Catalog::meta( 'venta-completa' )['widget_types'], 'con close_date, todos los widgets' );
	check_error( $build( 'captacion', array( 'close_date' => '2026-12-15 23:59' ) ), 'digitalisimo_sales_close_date', 'una plantilla sin contador ni textos de cierre no lleva close_date' );
	foreach ( array( '2026-11-01 10:00', '2026-02-30 10:00', '15/12/2026 23:59', '2026-12-15T23:59', '2030-01-01 00:00', '2026-13-01 00:00' ) as $bad ) {
		check_error( $build( 'carrito', array( 'close_date' => $bad ) ), 'digitalisimo_sales_close_date', "close_date inválida, pasada o lejana: $bad" );
	}
	$r = $build( 'carrito', array( 'close_date' => '2026-11-20 18:00' ), 'containers' );
	check( 1 === count( sk_find( $r['elements'], static function ( $e ) { return 'countdown' === ( $e['widgetType'] ?? '' ); } ) ) && false !== strpos( sk_strings( $r['elements'] ), 'Este precio termina el 20 a las 18:00' ), 'carrito con contenedores y close_date' );

	// Bloques: se quitan enteros, nunca se reordenan; el pie no se quita.
	$r     = $build( 'venta-completa', array( 'omit_blocks' => array( 'al-terminar', 'oferta', 'al-terminar' ), 'close_date' => '2026-12-15 23:59' ) );
	$meta1 = Catalog::meta( 'venta-completa' );
	$keep  = array();
	foreach ( $meta1['blocks'] as $b ) {
		if ( ! in_array( $b['key'], array( 'al-terminar', 'oferta' ), true ) ) {
			$keep = array_merge( $keep, $b['sections'] );
		}
	}
	$titles = static function ( array $els ) {
		return array_map( static function ( $e ) { return $e['settings']['_title'] ?? ''; }, $els );
	};
	$expected = array_values( array_filter( $tpl1['content'], static function ( $e ) use ( $keep ) { return in_array( $e['id'], $keep, true ); } ) );
	check( $titles( $r['elements'] ) === $titles( $expected ) && count( $r['elements'] ) === count( $tpl1['content'] ) - 2, 'omit_blocks quita los bloques enteros y lo demás queda en su orden' );
	check( array( 'al-terminar', 'oferta' ) === $r['omitted_blocks'] && array( 'oferta' ) === $r['broken_anchors'], 'omit_blocks informa lo quitado y los botones que llevaban al bloque quitado' );
	$r = $build( 'venta-completa', array( 'omit_blocks' => array( 'comunidad' ), 'close_date' => '2026-12-15 23:59' ) );
	check( array( 'historias' ) === $r['broken_anchors'], 'sin «Lo avala la comunidad», el botón «Ver más historias» queda sin destino' );
	check( array() === $build( 'venta-completa', array( 'close_date' => '2026-12-15 23:59' ) )['broken_anchors'], 'la plantilla entera no tiene anclas rotas' );
	check_error( $build( 'venta-completa', array( 'omit_blocks' => array( 'pie' ) ) ), 'digitalisimo_sales_omit_blocks', 'el pie (avisos legales) no se quita' );
	check_error( $build( 'venta-completa', array( 'omit_blocks' => array( 'no-existe' ) ) ), 'digitalisimo_sales_omit_blocks', 'un bloque que no existe' );
	check_error( $build( 'venta-corta', array( 'omit_blocks' => array( 'barra-de-cierre' ) ) ), 'digitalisimo_sales_omit_blocks', 'un bloque de otra plantilla' );
	check_error( $build( 'venta-corta', array( 'omit_blocks' => 'oferta' ) ), 'digitalisimo_sales_omit_blocks', 'omit_blocks que no es lista' );
	foreach ( $ids8 as $id ) {
		$m = Catalog::meta( $id );
		check( array_merge( ...array_column( $m['blocks'], 'sections' ) ) === array_column( Catalog::load( $id, 'containers' )['content'], 'id' ), "$id: los bloques recorren las secciones en orden (también con contenedores)" );
	}

	// ================================================================ 5. Ejecución y permisos
	$created = array();
	$fake    = static function ( $title, $elements ) use ( &$created ) {
		$created[] = array( $title, $elements );
		return array(
			'id'          => 77,
			'status'      => 'draft',
			'edit_url'    => 'https://sitio.test/edit',
			'preview_url' => 'https://sitio.test/?page_id=77&preview=true',
		);
	};
	sk_reset( array( 'edit_posts', 'read', 'publish_posts' ) ); // un autor: sin edit_pages
	foreach ( Tools::TOOLS as $tool ) {
		check_error( Executor::run( $tool, array( 'template' => 'venta-corta', 'title' => 'X' ), $fake ), 'digitalisimo_sales_forbidden', "$tool: sin edit_pages, nada" );
		check_error( Executor::permission( $tool ), 'digitalisimo_sales_forbidden', "$tool: permiso negado sin edit_pages" );
	}
	check( array() === $created, 'sin permiso no se crea nada' );
	sk_reset();
	foreach ( Tools::TOOLS as $tool ) {
		check( true === Executor::permission( $tool ), "$tool: con edit_pages" );
	}
	check_error( Executor::permission( 'otra' ), 'digitalisimo_sales_forbidden', 'una herramienta desconocida no tiene permiso' );
	$list = Executor::run( 'list-templates', array() );
	check( true === $list['ok'] && 8 === count( $list['templates'] ) && count( $list['cases'] ) >= 10 && 'containers' === $list['variant'] && '' !== $list['funnel'], 'list-templates: catálogo, casos y embudo' );
	check( ! isset( $list['templates'][0]['placeholders'] ) && $list['templates'][0]['placeholders_count'] > 100, 'list-templates sin template: sólo cuántos placeholders' );
	check( 26 === count( $list['templates'][0]['blocks'] ) && array( 'key' => 'pie', 'name' => 'Pie', 'required' => true ) === end( $list['templates'][0]['blocks'] ), 'list-templates: los bloques de cada plantilla, en orden, y cuál no se quita' );
	$one = Executor::run( 'list-templates', array( 'template' => 'carrito' ) );
	check( 1 === count( $one['templates'] ) && count( $one['templates'][0]['placeholders'] ) > 10 && isset( $one['templates'][0]['urgency'] ), 'list-templates con template: sus placeholders y su urgencia' );
	check( array( '/aviso-de-privacidad/', '/pago/', '/politica-de-reembolso/', '/terminos-y-condiciones/' ) === array_column( $one['templates'][0]['internal_links'], 'path' ), 'list-templates: los enlaces internos del carrito' );
	check_error( Executor::run( 'list-templates', array( 'template' => 'otra' ) ), 'digitalisimo_sales_invalid', 'plantilla desconocida' );
	check_error( Executor::run( 'list-templates', array( 'post_id' => 3 ) ), 'digitalisimo_sales_invalid', 'parámetro desconocido' );
	$g = Executor::run( 'guide', array( 'section' => 'reglas' ) );
	check( true === $g['ok'] && 0 === strpos( $g['content'], '## Reglas' ) && 'text/markdown' === $g['mimeType'], 'guide: una parte' );
	check( strlen( Executor::run( 'guide', array() )['content'] ) > 5000, 'guide: entera' );
	check_error( Executor::run( 'create-page', array( 'template' => 'venta-corta' ), $fake ), 'digitalisimo_sales_invalid', 'create-page sin título' );
	check_error( Executor::run( 'create-page', array( 'template' => 'venta-corta', 'title' => 'X', 'status' => 'publish' ), $fake ), 'digitalisimo_sales_invalid', 'create-page no acepta un estado' );
	check_error( Executor::run( 'create-page', array( 'template' => 'venta-corta', 'title' => 'X', 'replacements' => array( '[producto]' => 'https://evil.test' ) ), $fake ), 'digitalisimo_sales_external_url', 'create-page rechaza una URL de otro sitio' );
	check( array() === $created, 'con un error no se crea nada' );
	$out = Executor::run(
		'create-page',
		array(
			'template'     => 'venta-corta',
			'title'        => '<b>Mi página</b>',
			'replacements' => array( '[producto]' => 'Curso' ),
			'whatsapp'     => '5218711234567',
		),
		$fake
	);
	check( true === $out['ok'] && 77 === $out['id'] && 'draft' === $out['status'] && 'containers' === $out['variant'] && 'Mi página' === $out['title'], 'create-page: borrador con contenedores y título en texto plano' );
	check( 1 === count( $created ) && 'Mi página' === $created[0][0] && 'container' === $created[0][1][0]['elType'], 'create-page guarda los elementos de la variante elegida' );
	check( $out['pending_count'] > 0 && $out['review'] && false !== strpos( $out['llm_instructions'], 'BORRADOR' ) && ! $out['whatsapp_pending'], 'create-page informa lo pendiente y lo que hay que revisar' );
	check( array( '/aviso-de-privacidad/', '/carrito/', '/politica-de-reembolso/', '/terminos-y-condiciones/' ) === array_column( $out['pages_to_create'], 'path' ), 'create-page dice qué páginas del sitio faltan' );
	$GLOBALS['sk']['containers'] = false;
	$out                         = Executor::run( 'create-page', array( 'template' => 'captacion', 'title' => 'Clase' ), $fake );
	check( 'sections' === $out['variant'] && 'section' === $created[1][1][0]['elType'], 'create-page sin contenedores: secciones' );
	check( ! in_array( 'publish_pages', $GLOBALS['sk']['asked'], true ) && ! in_array( 'publish_posts', $GLOBALS['sk']['asked'], true ), 'publish_pages nunca se consulta' );

	// Pages::create: borrador, guardado normal del documento y nada a medias.
	sk_reset();
	$p = Pages::create( 'Página', array( array( 'id' => 'abc1234' ) ) );
	check( 'draft' === $GLOBALS['sk']['inserted'][0]['post_status'] && 'page' === $GLOBALS['sk']['inserted'][0]['post_type'], 'Pages: inserta un borrador' );
	check( 1 === count( $GLOBALS['sk']['saves'] ) && array( 'template' => 'elementor_canvas' ) === ( $GLOBALS['sk']['doc_settings'][101] ?? null ) && array( array( 'id' => 'abc1234' ) ) === $GLOBALS['sk']['saves'][0][1]['elements'], 'Pages: guarda el lienzo (update_settings) y los elementos (Document::save)' );
	check( \Elementor\Plugin::$instance->documents->docs[101]->built && 'draft' === $p['status'] && false === strpos( $p['preview_url'], 'preview_nonce' ) && false !== strpos( $p['edit_url'], 'action=elementor' ), 'Pages: marcada como del constructor, en borrador, con sus URL' );
	$GLOBALS['sk']['save_publishes'] = true;
	$p                               = Pages::create( 'Otra', array() );
	check( 'draft' === $p['status'] && 'draft' === $GLOBALS['sk']['updated'][0]['post_status'], 'Pages: si algo la publica al guardar, vuelve a borrador' );
	$GLOBALS['sk']['save_publishes'] = false;
	$GLOBALS['sk']['save_fails']     = true;
	check_error( Pages::create( 'Falla', array() ), 'digitalisimo_sales_save', 'Pages: si el guardado falla, error' );
	check( array( 103, true ) === end( $GLOBALS['sk']['deleted'] ), 'Pages: y la página a medias se borra' );
	$GLOBALS['sk']['save_fails']    = false;
	$GLOBALS['sk']['role_excluded'] = true;
	check_error( Pages::create( 'X', array() ), 'digitalisimo_sales_forbidden', 'Pages: un rol excluido del constructor no crea nada' );
	check( 3 === count( $GLOBALS['sk']['inserted'] ), 'Pages: sin permiso del constructor no se inserta nada' );
	$code = file_get_contents( $base . '/class-pages.php' ) . file_get_contents( $base . '/class-builder.php' ) . file_get_contents( $base . '/mcp/class-executor.php' );
	check( ! preg_match( "/'publish'|wp_publish_post|publish_pages/", preg_replace( '#^\s*(/\*|\*|//).*$#m', '', $code ) ), 'ningún código del kit publica ni consulta publish_pages' );

	// ================================================================ 6. Registro
	sk_reset();
	\Digitalisimo\Elements\Sales\Mcp\Module::reset();
	\Digitalisimo\Elements\Sales\Mcp\Module::init();
	check( array( 'elementor/init', array( \Digitalisimo\Elements\Sales\Mcp\Module::class, 'boot' ), 20 ) === $GLOBALS['sk']['hooks'][0], 'init: arranca en elementor/init' );
	$registry                                   = new \Elementor\Modules\Mcp\Registry\Ability_Registry();
	$mcp                                        = new \Elementor\Modules\Mcp\Module();
	$mcp->test_registry                         = $registry;
	\Elementor\Plugin::$instance->modules_manager->mcp = $mcp;
	\Digitalisimo\Elements\Sales\Mcp\Module::boot();
	check( array( 'digitalisimo-sales/list-templates', 'digitalisimo-sales/create-page', 'digitalisimo-sales/guide' ) === array_keys( $registry->abilities ), 'con el registro del núcleo: las tres herramientas en él' );
	$ability = $registry->abilities['digitalisimo-sales/create-page'];
	check( false === $ability->is_exposed_via_proxy() && true === $ability->check_permission(), 'fuera del proxy del editor; permiso con edit_pages' );
	$GLOBALS['sk']['caps'] = array( 'read' );
	check( false === $ability->check_permission(), 'check_permission: un WP_Error no da permiso (true estricto)' );
	$def = $ability->definition_for_test();
	check( $def->input_schema === Tools::input_schema( 'create-page' ) && call_user_func( $def->permission_callback ) instanceof WP_Error, 'la definición del núcleo lleva el esquema y el permiso propios' );
	$GLOBALS['sk']['caps'] = array( 'edit_pages', 'edit_posts' );
	$ability->register();
	$args = $GLOBALS['sk']['abilities']['digitalisimo-sales/create-page'];
	check( false === $args['meta']['show_in_rest'] && array( $ability, 'execute_guarded' ) === $args['execute_callback'] && true === call_user_func( $args['permission_callback'] ), 'register(): definición propia y execute_guarded del núcleo' );
	$cat_hook = array_filter( $GLOBALS['sk']['hooks'], static function ( $h ) { return 'wp_abilities_api_categories_init' === $h[0]; } );
	call_user_func( array_values( $cat_hook )[0][1] );
	check( 'Páginas de venta' === $GLOBALS['sk']['categories']['digitalisimo-sales']['label'], 'categoría propia' );
	$r = $ability->execute_guarded( array( 'section' => 'x' ) );
	check_error( $r, 'digitalisimo_sales_invalid', 'execute_guarded llega al ejecutor (y valida la entrada)' );
	// Sin registro del núcleo: wp_register_ability y el filtro del servidor.
	sk_reset();
	\Digitalisimo\Elements\Sales\Mcp\Module::reset();
	\Digitalisimo\Elements\Sales\Mcp\Module::boot();
	$hooks = array_column( $GLOBALS['sk']['hooks'], 0 );
	check( in_array( 'wp_abilities_api_init', $hooks, true ) && in_array( 'elementor/mcp/server/tools', $hooks, true ), 'sin registro del núcleo: wp_abilities_api_init y el filtro del servidor' );
	\Digitalisimo\Elements\Sales\Mcp\Module::register_abilities();
	check( 3 === count( $GLOBALS['sk']['abilities'] ) && array( 'x', 'digitalisimo-sales/list-templates', 'digitalisimo-sales/create-page', 'digitalisimo-sales/guide' ) === \Digitalisimo\Elements\Sales\Mcp\Module::tool_slugs( array( 'x' ) ), 'y se registran las tres' );
	// MCP apagado: nada.
	sk_reset();
	$GLOBALS['sk']['mcp'] = false;
	\Digitalisimo\Elements\Sales\Mcp\Module::reset();
	\Digitalisimo\Elements\Sales\Mcp\Module::boot();
	check( array() === $GLOBALS['sk']['hooks'] && array() === \Digitalisimo\Elements\Sales\Mcp\Module::abilities(), 'con el MCP del sitio apagado no se registra nada' );

	// ================================================================ 7. Aviso al importar
	sk_reset();
	check( 'opcion' === Import_Notice::reason( 'kit-ventas.zip' ), 'ZIP sin la carga sin filtrar: aviso «opcion»' );
	check( '' === Import_Notice::reason( 'plantilla.json' ), 'un JSON suelto se importa sin esa opción: sin aviso' );
	$GLOBALS['sk']['options']['elementor_unfiltered_files_upload'] = 1;
	check( '' === Import_Notice::reason( 'kit-ventas.ZIP' ), 'con la opción encendida: sin aviso' );
	$GLOBALS['sk']['filter_off'] = true;
	$dom                         = class_exists( 'DOMDocument' ) && class_exists( 'SimpleXMLElement' );
	check( ( $dom ? 'filtro' : 'servidor' ) === Import_Notice::reason( 'kit.zip' ), 'opción encendida pero otro código la apaga (o falta DOM): «filtro» o «servidor»' );
	$GLOBALS['sk']['can_json'] = false;
	check( 'usuario' === Import_Notice::reason( 'kit.zip' ) && 'usuario' === Import_Notice::reason( 'plantilla.json' ), 'un usuario que no puede subir JSON: «usuario»' );
	sk_reset();
	$_REQUEST = array(
		'library_action' => 'direct_import_template',
		'_nonce'         => 'buen-nonce',
	);
	$_FILES   = array( 'file' => array( 'name' => 'kit-ventas.zip', 'tmp_name' => '/tmp/x' ) );
	try {
		Import_Notice::guard();
		check( false, 'guard: debía volver a la biblioteca' );
	} catch ( Sk_Redirect $e ) {
		check( 'https://sitio.test/wp-admin/edit.php?post_type=elementor_library&tabs_group=library&digitalisimo-import=opcion' === $e->getMessage(), 'guard: vuelve a la biblioteca con el motivo, antes de la importación del núcleo' );
	}
	unset( $GLOBALS['sk']['redirect'] );
	$_REQUEST['_nonce'] = 'malo';
	Import_Notice::guard();
	check( ! isset( $GLOBALS['sk']['redirect'] ), 'guard: con un nonce malo decide el núcleo' );
	$_REQUEST['_nonce']                                           = 'buen-nonce';
	$GLOBALS['sk']['options']['elementor_unfiltered_files_upload'] = 1;
	Import_Notice::guard();
	check( ! isset( $GLOBALS['sk']['redirect'] ), 'guard: con la opción encendida importa el núcleo' );
	check( ! in_array( 'wp_ajax_elementor_library_direct_actions', $init_hooks, true ) && in_array( 'elementor/init', $init_hooks, true ), 'fuera de la administración el aviso no se engancha (Module::init con is_admin)' );
	$_GET = array( 'digitalisimo-import' => 'opcion' );
	$GLOBALS['sk']['caps'][] = 'manage_options';
	ob_start();
	Import_Notice::notice();
	$html = ob_get_clean();
	check( false !== strpos( $html, 'notice-error' ) && false !== strpos( $html, 'Habilitar cargas de archivos sin filtrar' ) && false !== strpos( $html, 'page=elementor-settings#tab-advanced' ), 'el aviso dice qué falta y dónde se enciende' );
	$_GET = array( 'digitalisimo-import' => '<script>' );
	ob_start();
	Import_Notice::notice();
	check( '' === ob_get_clean(), 'un motivo desconocido no pinta nada' );
	Import_Notice::init();
	check( in_array( array( 'wp_ajax_elementor_library_direct_actions', array( Import_Notice::class, 'guard' ), 1 ), $GLOBALS['sk']['hooks'], true ), 'Import_Notice::init: antes que el núcleo (prioridad 1)' );

	// ================================================================ 8. Revisión adversarial (R1–R8)
	// Cada bloque anota sus fallas y la prueba falla al final con la lista; todos fallan con el código de f61dae87.
	$GLOBALS['sk_review'] = array();
	function review( string $label, $ok, string $message ) {
		++$GLOBALS['sk_count'];
		if ( ! $ok ) {
			$GLOBALS['sk_review'][ $label ][] = $message;
		}
	}
	function review_block( string $label, callable $block ) {
		try {
			$block();
		} catch ( Throwable $e ) {
			review( $label, false, 'excepción: ' . $e->getMessage() );
		}
	}
	sk_reset();
	$GLOBALS['sk']['containers'] = false;

	// R1 · Ningún enlace ni recurso de otro sitio entra por un reemplazo, ni en HTML (las cargas del revisor).
	review_block(
		'R1',
		static function () use ( $build ) {
			$bad = array(
				'<a href="https://evil.ru\\@sitio.test/pago">Paga</a>',
				'<a href="https:&#47;&#47;evil.ru/pago">Paga</a>',
				'<a href="https:&#x2F;&#x2F;evil.ru/pago">Paga</a>',
				'<a href="http:evil.ru/pago">Paga</a>',
				'<a href="https:\\\\evil.ru/pago">Paga</a>',
				'<a href="https:/\\evil.ru/pago">Paga</a>',
				'<img src="https:&#47;&#47;evil.ru/pixel.gif">',
				'<img src=x onerror=alert(3)>',
				'<a href="/carrito/">ruta del sitio</a>',
				'<span style="color:red">rojo</span>',
				'<strong class="x">clase</strong>',
				'<form action="/x"><input></form>',
				'<iframe src="/x"></iframe>',
				'<script>alert(1)</script>',
				'<svg onload=alert(1)>',
				'https:&#47;&#47;evil.ru/pago',
				'http:evil.ru/x',
				'https:\\\\evil.ru/y',
				'https://evil.ru\\@sitio.test/pago',
				'https://wa.me@evil.ru',
				'https://wa.me.evil.ru',
				'JAVASCRIPT:alert(1)',
				'//evil.com/x',
			);
			foreach ( $bad as $v ) {
				foreach ( array( '[La promesa en una frase y por qué ahora.]', '[producto]', '[Resultado concreto]' ) as $key ) {
					$r = $build( 'venta-corta', array( 'replacements' => array( $key => $v ) ) );
					review( 'R1', $r instanceof WP_Error && in_array( $r->get_error_code(), array( 'digitalisimo_sales_markup', 'digitalisimo_sales_external_url' ), true ), "$key: se aceptó $v" );
				}
			}
			$r = $build( 'venta-corta', array( 'replacements' => array( '[La promesa en una frase y por qué ahora.]' => '<strong>Vende</strong> <em>más</em><br><b>hoy</b> <i>ya</i><br />' ) ) );
			review( 'R1', is_array( $r ) && false !== strpos( sk_strings( $r['elements'] ), '<p><strong>Vende</strong> <em>más</em><br><b>hoy</b> <i>ya</i><br /></p>' ), 'el formato en línea sin atributos se admite' );
			$r = $build( 'venta-corta', array( 'replacements' => array( '[producto]' => 'Escríbenos a hola@sitio.test o https://wa.me/5218711234567' ) ) );
			review( 'R1', is_array( $r ), 'un correo y wa.me se admiten' );
		}
	);

	// R2 · Una lista de reemplazos va por aparición en la plantilla entera, también con omit_blocks: cada valor queda en
	// su bloque (la carga del revisor: 13 «[Nombre real]» y omit_blocks funciona-para-ti); los de un bloque quitado y los
	// que sobran se informan en ignored.
	review_block(
		'R2',
		static function () use ( $build ) {
			$tpl   = Catalog::load( 'venta-completa', 'sections' );
			$meta  = Catalog::meta( 'venta-completa' );
			$n     = Placeholders::summary( $tpl['content'] )['[Nombre real]']['count'];
			$names = array();
			for ( $i = 1; $i <= $n; $i++ ) {
				$names[] = 'PERSONA' . $i . 'X';
			}
			$block_of = array();
			$sections = array();
			foreach ( $meta['blocks'] as $b ) {
				$sections[ $b['key'] ] = $b['sections'];
				foreach ( $b['sections'] as $s ) {
					$block_of[ $s ] = $b['key'];
				}
			}
			// Qué nombres quedan en cada bloque: por la posición de las secciones de primer nivel (los IDs se regeneran).
			$who = static function ( array $elements, array $template_ids ) use ( $block_of ) {
				$out = array();
				foreach ( $elements as $i => $top ) {
					preg_match_all( '/PERSONA([0-9]+)X/', json_encode( $top, JSON_UNESCAPED_UNICODE ), $m );
					if ( $m[1] ) {
						$key         = $block_of[ $template_ids[ $i ] ];
						$out[ $key ] = array_merge( $out[ $key ] ?? array(), array_map( 'intval', $m[1] ) );
					}
				}
				return $out;
			};
			$of_name = static function ( array $ignored, string $key ): array {
				return array_values(
					array_filter(
						$ignored,
						static function ( $x ) use ( $key ) {
							return $key === $x['placeholder'];
						}
					)
				);
			};
			$all   = array_column( $tpl['content'], 'id' );
			$close = '2026-12-15 23:59';
			$full  = $build( 'venta-completa', array( 'replacements' => array( '[Nombre real]' => $names ), 'close_date' => $close ) );
			$ref   = $who( $full['elements'], $all );
			review( 'R2', 13 === $n && array( 'funciona-para-ti' => array( 1, 2, 3 ), 'objeciones' => array( 4, 5, 6, 7 ), 'comunidad' => range( 8, 13 ) ) === $ref && array() === $full['ignored'], 'la plantilla entera: 3 nombres en funciona-para-ti, 4 en objeciones y 6 en comunidad, nada ignorado: ' . json_encode( $ref ) );
			foreach ( array( $close, '' ) as $c ) {
				$input = array(
					'replacements' => array( '[Nombre real]' => $names ),
					'omit_blocks'  => array( 'funciona-para-ti' ),
				);
				if ( '' !== $c ) {
					$input['close_date'] = $c;
				}
				$r        = $build( 'venta-completa', $input );
				$gone     = array_merge( $sections['funciona-para-ti'], '' === $c ? $meta['urgency'] : array() );
				$kept     = array_values( array_diff( $all, $gone ) );
				$got      = $who( $r['elements'], $kept );
				$expected = $ref;
				unset( $expected['funciona-para-ti'] );
				$tag = '' === $c ? 'sin close_date' : 'con close_date';
				review( 'R2', count( $r['elements'] ) === count( $kept ) && $expected === $got, "$tag, sin funciona-para-ti: cada nombre en su bloque (objeciones 4-7, comunidad 8-13): " . json_encode( $got ) );
				$ign = $of_name( $r['ignored'], '[Nombre real]' );
				review( 'R2', 1 === count( $ign ) && array( 1, 2, 3 ) === ( $ign[0]['values'] ?? null ) && false !== strpos( $ign[0]['reason'], 'bloques que se quitaron' ), "$tag: los tres nombres del bloque quitado se informan en ignored: " . json_encode( $r['ignored'], JSON_UNESCAPED_UNICODE ) );
				review( 'R2', ! isset( array_column( $r['pending'], 'count', 'placeholder' )['[Nombre real]'] ) && 10 === $r['filled'], "$tag: ningún «[Nombre real]» pendiente y diez llenados ({$r['filled']})" );
			}
			// Sobran: 15 valores para 13 apariciones.
			$r   = $build( 'venta-completa', array( 'replacements' => array( '[Nombre real]' => array_merge( $names, array( 'SOBRA14', 'SOBRA15' ) ) ), 'close_date' => $close ) );
			$ign = $of_name( $r['ignored'], '[Nombre real]' );
			review( 'R2', 1 === count( $ign ) && array( 14, 15 ) === $ign[0]['values'] && false !== strpos( $ign[0]['reason'], 'Sobran 2 de 15' ) && false === strpos( sk_strings( $r['elements'] ), 'SOBRA' ), 'los valores que sobran se informan en ignored: ' . json_encode( $r['ignored'], JSON_UNESCAPED_UNICODE ) );
			// Un valor único de un placeholder que sólo está en el bloque quitado.
			$only = array();
			foreach ( Placeholders::summary( array( $tpl['content'][ array_search( $sections['funciona-para-ti'][0], $all, true ) ] ) ) as $key => $info ) {
				$rest = Placeholders::summary( array_values( array_filter( $tpl['content'], static function ( $e ) use ( $sections ) { return ! in_array( $e['id'], $sections['funciona-para-ti'], true ); } ) ) );
				if ( 'texto' === $info['kind'] && ! isset( $rest[ $key ] ) ) {
					$only[] = $key;
				}
			}
			$r   = $only ? $build( 'venta-completa', array( 'replacements' => array( $only[0] => 'Solo aqui' ), 'omit_blocks' => array( 'funciona-para-ti' ), 'close_date' => $close ) ) : array();
			$ign = $only ? $of_name( $r['ignored'], $only[0] ) : array();
			review( 'R2', 1 === count( $ign ) && false !== strpos( $ign[0]['reason'], 'Sólo aparece en bloques que se quitaron' ) && ! in_array( $only[0], $r['filled_keys'], true ), 'un valor que sólo iba en el bloque quitado se informa: ' . json_encode( $only, JSON_UNESCAPED_UNICODE ) );
		}
	);

	// R4 · Sólo cuenta como URL un esquema (`http:`, `https:`, `javascript:`, `data:`…), `//` o `www.`: los textos que el
	// revisor vio rechazados pasan; los enlaces de otro sitio se siguen rechazando.
	review_block(
		'R4',
		static function () use ( $build ) {
			foreach ( array( 'Curso de JavaScript: de cero a experto', 'Domina ASP.NET y Socket.io', 'Lo logras hoy.Es tu momento', 'Data: lo que aprendes', 'Visita otro.mx/oferta', 'Node.js, Vue.js y Next.js', 'Escríbenos a hola@sitio.test' ) as $good ) {
				review( 'R4', '' === Builder::external_url( $good, 'sitio.test' ), "external_url rechazó un texto que no es una dirección: $good" );
				$r = $build( 'venta-corta', array( 'replacements' => array( '[producto]' => $good ) ) );
				review( 'R4', is_array( $r ) && in_array( '[producto]', $r['filled_keys'], true ), "create-page rechazó: $good" );
			}
			foreach ( array( 'https://otro.mx/oferta', 'http:otro.mx', 'www.otro.mx', '//otro.mx', 'javascript:alert(1)', "java\tscript:alert(1)", 'java&#x09;script:alert(1)', 'data:text/html,x', 'Ve a HTTPS://OTRO.MX', 'ftp://otro.mx/a' ) as $bad ) {
				review( 'R4', '' !== Builder::external_url( $bad, 'sitio.test' ), "external_url aceptó: $bad" );
			}
		}
	);

	// R5 · La venta corta (02) nombra el cierre sin contador: close_date llena sus textos de cierre; list-templates dice qué
	// plantillas admiten close_date.
	review_block(
		'R5',
		static function () use ( $build ) {
			$r = $build( 'venta-corta', array( 'close_date' => '2026-12-15 23:59' ) );
			review( 'R5', is_array( $r ), 'venta-corta acepta close_date: ' . ( $r instanceof WP_Error ? $r->get_error_message() : '' ) );
			if ( is_array( $r ) ) {
				$all  = sk_strings( $r['elements'] );
				$pend = array_column( $r['pending'], 'count', 'placeholder' );
				review( 'R5', false !== strpos( $all, 'terminan el 15 a las 23:59' ) && ! isset( $pend['[día de cierre]'] ) && ! isset( $pend['[hora de cierre]'] ) && '2026-12-15 23:59' === $r['close_date'], 'venta-corta: close_date llena [día de cierre] y [hora de cierre]' );
				review( 'R5', sk_widgets( $r['elements'] ) === Catalog::meta( 'venta-corta' )['widget_types'] && array() === $r['removed_urgency'], 'venta-corta con close_date: los mismos widgets, sin contador ni nada quitado' );
			}
			$r = $build( 'venta-corta', array() );
			review( 'R5', is_array( $r ) && isset( array_column( $r['pending'], 'count', 'placeholder' )['[día de cierre]'] ), 'venta-corta sin close_date: los textos de cierre quedan pendientes' );
			$r = $build( 'venta-corta', array( 'close_date' => '2026-11-01 10:00' ) );
			review( 'R5', $r instanceof WP_Error && 'digitalisimo_sales_close_date' === $r->get_error_code(), 'venta-corta: una fecha pasada se sigue rechazando' );
			$r = $build( 'captacion', array( 'close_date' => '2026-12-15 23:59' ) );
			review( 'R5', $r instanceof WP_Error && 'digitalisimo_sales_close_date' === $r->get_error_code(), 'captacion (sin contador ni textos de cierre) no lleva close_date' );
			sk_reset();
			$list = Executor::run( 'list-templates', array() );
			$by   = array_column( $list['templates'] ?? array(), 'close_date', 'id' );
			review( 'R5', array( 'venta-completa' => true, 'venta-corta' => true, 'captacion' => false, 'gracias-registro' => false, 'carrito' => true, 'oferta-unica' => false, 'gracias-compra' => false, 'prueba-social' => false ) === $by, 'list-templates dice qué plantillas admiten close_date: ' . json_encode( $by ) );
			review( 'R5', false !== strpos( Tools::DESCRIPTIONS['create-page'], 'venta-corta y carrito' ) && false !== strpos( Tools::input_schema( 'create-page' )['properties']['close_date']['description'], 'venta-corta' ), 'las descripciones dicen que la venta corta admite close_date' );
			$GLOBALS['sk']['containers'] = false;
		}
	);

	// R8 · Guardar como editora (sin manage_options) no deja avisos de PHP del saneador del núcleo: el lienzo va por
	// update_settings() (dentro de save() el saneador recorre los ajustes: db.php:268) y ningún contenedor llega sin
	// widgetType (content-sanitizer/module.php:45). El doble del saneador reproduce los dos avisos y el manejador de
	// errores de esta prueba los vuelve excepción.
	review_block(
		'R8',
		static function () use ( $build ) {
			foreach ( array( 'sections', 'containers' ) as $variant ) {
				sk_reset();
				add_filter( 'elementor/document/save/data', 'sk_core_sanitizer', 10, 2 );
				$els = $build( 'venta-corta', array(), $variant )['elements'];
				$p   = Pages::create( 'De la editora', $els );
				review( 'R8', is_array( $p ) && 'draft' === $p['status'], "$variant: como editora se guarda sin avisos de PHP del núcleo: " . ( $p instanceof WP_Error ? ( $GLOBALS['sk']['save_error'] ?? $p->get_error_message() ) : '' ) );
				$saved = $GLOBALS['sk']['saves'][0][1] ?? array();
				review( 'R8', array( 'elements' ) === array_keys( $saved ) && array( 'template' => 'elementor_canvas' ) === ( $GLOBALS['sk']['doc_settings'][101] ?? null ), "$variant: el lienzo va por update_settings(), no por save()" );
				$after = $GLOBALS['sk']['filtered'][0] ?? array();
				review( 'R8', isset( $after['elements'] ) && false === strpos( json_encode( $after['elements'] ), '"widgetType":""' ) && sk_widgets( $after['elements'] ) === sk_widgets( $els ), "$variant: el widgetType vacío de los contenedores no sale del filtro" );
				review( 'R8', ! array_filter( $GLOBALS['sk']['hooks'], static function ( $h ) { return 'elementor/document/save/data' === $h[0] && 'sk_core_sanitizer' !== $h[1]; } ), "$variant: los filtros del guardado se quitan al terminar" );
			}
		}
	);

	if ( $GLOBALS['sk_review'] ) {
		throw new RuntimeException( 'Revisión: ' . json_encode( $GLOBALS['sk_review'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	echo 'Kit de ventas en el MCP de Elements: ' . $GLOBALS['sk_count'] . " comprobaciones en verde (PHP " . PHP_VERSION . ").\n";
}
