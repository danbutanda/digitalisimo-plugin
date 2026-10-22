<?php
/**
 * F7a, frente J: prioridad manual del Theme Builder (`modules/digitalisimo-design/theme-builder/class-priority.php`).
 *
 * - Sin manuales entre las candidatas, el array que devuelve el `get_location_templates()` real de PRO es idéntico
 *   (`===`) al de hoy, con el filtro enganchado y con un índice que sólo nombra otras plantillas.
 * - Con manuales, el orden de D20; un `exclude` y un borrador mandan; las ubicaciones múltiples no se tocan.
 * - El índice existe siempre (`[]`, con carga automática), se lee sólo desde `alloptions` (0 consultas sin la opción) y
 *   se reindexa al guardar, en la papelera, al restaurar, al borrar y al escribirse la meta de una plantilla.
 * - El derivado de PRO tiene la línea del filtro una sola vez y justo después del `asort` (lectura por tokens).
 * - El campo `priority` de los kits exporta, importa con el valor recibido y llama a `reindex()`; rango 1–99.
 * - Guarda: sin los símbolos del Theme Builder no se registra nada.
 */
namespace Elementor {
	class Controls_Manager { const NUMBER = 'number'; const SELECT = 'select'; const SWITCHER = 'switcher'; const RAW_HTML = 'raw_html'; const SECTION = 'section'; }
}
namespace Elementor\TemplateLibrary {
	class Source_Local { const CPT = 'elementor_library'; }
}
namespace Elementor\Core\Base {
	/** Documento con la pila de controles y la inyección como en `Controls_Stack`. */
	class Document {
		public $id = 0;
		public $controls = array();
		public $order = array();
		public $injection = null;
		public $injections = array();
		public function __construct( $id = 0, array $base = array( 'post_title', 'post_status' ) ) {
			$this->id = $id;
			foreach ( $base as $name ) { $this->controls[ $name ] = array( 'type' => 'text' ); $this->order[] = $name; }
		}
		public function get_main_id() { return $this->id; }
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function start_injection( array $position ) {
			if ( null !== $this->injection ) { throw new \RuntimeException( 'Inyección abierta dos veces.' ); }
			$of = isset( $this->controls[ $position['of'] ] ) ? $position['of'] : ( $position['fallback']['of'] ?? '' );
			if ( ! isset( $this->controls[ $of ] ) ) { throw new \RuntimeException( 'Inyección sin destino.' ); }
			$this->injection    = array_search( $of, $this->order, true ) + 1;
			$this->injections[] = $of;
		}
		public function end_injection() { $this->injection = null; }
		public function add_control( $name, array $args ) {
			if ( null === $this->injection ) { throw new \RuntimeException( 'Control fuera de la inyección: ' . $name ); }
			$this->controls[ $name ] = $args;
			array_splice( $this->order, $this->injection++, 0, array( $name ) );
		}
	}
}
namespace ElementorPro\Modules\ThemeBuilder\Documents {
	class Theme_Document extends \Elementor\Core\Base\Document {
		public $location = '';
		public function get_location() { return $this->location; }
	}
}
namespace ElementorPro\Modules\ThemeBuilder\Conditions {
	/** Condiciones con la prioridad del núcleo (`modules/theme-builder/conditions/*.php`). */
	abstract class Fake_Condition {
		public static function get_priority() { return 100; }
		public function get_sub_conditions() { return array(); }
		public function check( $args ) { return true; }
	}
	class General extends Fake_Condition { public function get_name() { return 'general'; } }
	class Singular extends Fake_Condition {
		public static function get_priority() { return 60; }
		public function get_name() { return 'singular'; }
		public function get_sub_conditions() { return array( 'page' ); }
	}
	class Page extends Fake_Condition {
		public static function get_priority() { return 40; }
		public function get_name() { return 'page'; }
		public function check( $args ) { return empty( $args['id'] ) || (int) $args['id'] === $GLOBALS['current_page']; }
	}
	class Archive extends Fake_Condition {
		public static function get_priority() { return 80; }
		public function get_name() { return 'archive'; }
		public function check( $args ) { return false; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );

	function check_priority( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	// Ganchos de WordPress con prioridad y número de argumentos.
	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function apply_filters( $hook, $value, ...$rest ) {
		if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) { return $value; }
		ksort( $GLOBALS['wp_filters'][ $hook ] );
		foreach ( $GLOBALS['wp_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] ) ); }
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) { return; }
		ksort( $GLOBALS['wp_filters'][ $hook ] );
		foreach ( $GLOBALS['wp_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); }
		}
	}
	function has_priority_hook( $hook ) { return ! empty( $GLOBALS['wp_filters'][ $hook ] ); }

	// Opciones: `alloptions` sólo con las de carga automática; leer una opción fuera de ella cuenta como consulta.
	$GLOBALS['options'] = array();
	$GLOBALS['autoload'] = array();
	$GLOBALS['queries'] = 0;
	$GLOBALS['writes'] = array();
	function wp_load_alloptions( $force = false ) {
		$all = array();
		foreach ( $GLOBALS['options'] as $name => $value ) { if ( ! empty( $GLOBALS['autoload'][ $name ] ) ) { $all[ $name ] = is_array( $value ) ? serialize( $value ) : $value; } }
		return $all;
	}
	function get_option( $name, $default = false ) {
		if ( ! array_key_exists( $name, $GLOBALS['options'] ) || empty( $GLOBALS['autoload'][ $name ] ) ) { $GLOBALS['queries']++; }
		return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['options'][ $name ] = $value;
		if ( null !== $autoload ) { $GLOBALS['autoload'][ $name ] = (bool) $autoload; } elseif ( ! isset( $GLOBALS['autoload'][ $name ] ) ) { $GLOBALS['autoload'][ $name ] = true; }
		$GLOBALS['writes'][] = $name;
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = true ) {
		if ( array_key_exists( $name, $GLOBALS['options'] ) ) { return false; }
		$GLOBALS['options'][ $name ] = $value;
		$GLOBALS['autoload'][ $name ] = (bool) $autoload;
		$GLOBALS['writes'][] = $name;
		return true;
	}
	function get_current_blog_id() { return 1; }

	// Entradas y metas; las metas disparan sus acciones como WordPress.
	$GLOBALS['posts'] = array();
	$GLOBALS['meta'] = array();
	function get_post_type( $id ) { return $GLOBALS['posts'][ (int) $id ]['type'] ?? false; }
	function get_post_status( $id ) { return $GLOBALS['posts'][ (int) $id ]['status'] ?? false; }
	function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['meta'][ (int) $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) {
		$existed = isset( $GLOBALS['meta'][ (int) $id ][ $key ] );
		$GLOBALS['meta'][ (int) $id ][ $key ] = $value;
		do_action( $existed ? 'updated_post_meta' : 'added_post_meta', 7, (int) $id, $key, $value );
		return true;
	}
	function add_post_meta( $id, $key, $value ) {
		$GLOBALS['meta'][ (int) $id ][ $key ] = $value;
		do_action( 'added_post_meta', 8, (int) $id, $key, $value );
		return true;
	}
	function delete_post_meta( $id, $key ) {
		unset( $GLOBALS['meta'][ (int) $id ][ $key ] );
		do_action( 'deleted_post_meta', array( 9 ), (int) $id, $key, '' );
		return true;
	}
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }

	require $root . '/modules/digitalisimo-design/class-module.php';
	require $root . '/modules/digitalisimo-design/theme-builder/class-priority.php';
	$priority = \Digitalisimo\Elements\Design\Theme_Builder\Priority::class;

	// Guarda: el Theme Builder aún no existe (su clase se declara abajo), así que no se registra ningún gancho propio.
	$priority::init();
	foreach ( array( $priority::FILTER, 'elementor/documents/register_controls', 'elementor/document/after_save', 'trashed_post', 'updated_post_meta', 'digitalisimo_elements_kit_theme_builder_fields', 'admin_init' ) as $hook ) {
		check_priority( ! has_priority_hook( $hook ), 'Sin el Theme Builder no se registra ' . $hook . '.' );
	}
	$missing = \Digitalisimo\Elements\Design\Module::missing();
	check_priority( isset( $missing['theme-builder'] ) && in_array( 'ElementorPro\Modules\ThemeBuilder\Module::instance', $missing['theme-builder'], true ), 'La guarda anota el símbolo que falta.' );
	$GLOBALS['wp_filters'] = array();
}
namespace ElementorPro\Modules\ThemeBuilder {
	if ( true ) { // Declarada en ejecución, después de la prueba de la guarda.
		class Module {
			public static $instance;
			public $locations;
			public $conditions;
			public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
			public function get_locations_manager() { return $this->locations; }
			public function get_conditions_manager() { return $this->conditions; }
			public function get_document( $id ) { return null; }
		}
		class Fake_Locations {
			public $registered = array( 'header' => array( 'multiple' => false ), 'footer' => array( 'multiple' => false ), 'popup' => array( 'multiple' => true ), 'digitalisimo_before_header' => array( 'multiple' => true ) );
			public function get_location( $location ) { return $this->registered[ $location ] ?? null; }
			public function inspector_log( $args ) {}
		}
	}
}
namespace {
	$tb = \ElementorPro\Modules\ThemeBuilder\Module::instance();
	$tb->locations = new \ElementorPro\Modules\ThemeBuilder\Fake_Locations();

	// El gestor real del derivado de PRO, con su caché real.
	require $root . '/modules/theme-builder/classes/conditions-cache.php';
	require $root . '/modules/theme-builder/classes/conditions-manager.php';
	$GLOBALS['current_page'] = 7;
	foreach ( array( 101, 102, 103, 104, 105, 106 ) as $id ) { $GLOBALS['posts'][ $id ] = array( 'type' => 'elementor_library', 'status' => 'publish' ); }
	$GLOBALS['posts'][105]['status'] = 'draft';
	$GLOBALS['options']['elementor_pro_theme_builder_conditions'] = array(
		'header' => array(
			101 => array( 'include/general' ),
			102 => array( 'include/singular' ),
			103 => array( 'include/singular/page/7' ),
			104 => array( 'include/general', 'exclude/singular' ),
			105 => array( 'include/general' ),
			106 => array( 'include/archive' ),
		),
		'popup'  => array(
			101 => array( 'include/general' ),
			102 => array( 'include/singular' ),
		),
	);
	$GLOBALS['autoload']['elementor_pro_theme_builder_conditions'] = true;
	$manager = new \ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager();
	foreach ( array( 'General', 'Singular', 'Page', 'Archive' ) as $class ) {
		$class = '\ElementorPro\Modules\ThemeBuilder\Conditions\\' . $class;
		$manager->register_condition_instance( new $class() );
	}
	$tb->conditions = $manager;

	// Línea base: el derivado sin nadie enganchado al filtro. Gana el número más bajo (D20 parte de esta escala).
	$baseline = $manager->get_location_templates( 'header' );
	check_priority( array( 103 => 20, 102 => 60, 101 => 100 ) === $baseline, 'Línea base inesperada: ' . json_encode( $baseline ) );
	$popup_baseline = $manager->get_location_templates( 'popup' );

	$priority::init();
	foreach ( array( $priority::FILTER, 'elementor/documents/register_controls', 'elementor/document/after_save', 'trashed_post', 'untrashed_post', 'deleted_post', 'added_post_meta', 'updated_post_meta', 'digitalisimo_elements_kit_theme_builder_fields', 'admin_init' ) as $hook ) {
		check_priority( has_priority_hook( $hook ), 'Falta el gancho ' . $hook . '.' );
	}

	// Sin la opción: 0 consultas y el mismo array.
	$GLOBALS['queries'] = 0;
	check_priority( $baseline === $manager->get_location_templates( 'header' ), 'Sin la opción, el orden debe ser idéntico (===).' );
	check_priority( 0 === $GLOBALS['queries'], 'Sin la opción no se consulta nada (' . $GLOBALS['queries'] . ').' );
	check_priority( array() === $priority::index(), 'Sin la opción el índice está vacío.' );

	// La opción existe siempre: `admin_init` la crea vacía y con carga automática, una sola vez.
	do_action( 'admin_init' );
	check_priority( array() === ( $GLOBALS['options'][ $priority::OPTION ] ?? null ) && ! empty( $GLOBALS['autoload'][ $priority::OPTION ] ), 'La opción se crea vacía y con carga automática.' );
	$write_count = count( $GLOBALS['writes'] );
	do_action( 'admin_init' );
	check_priority( count( $GLOBALS['writes'] ) === $write_count, 'Crear la opción es idempotente.' );
	// Una opción sin carga automática se corrige (la lectura protegida la ignoraría).
	$GLOBALS['autoload'][ $priority::OPTION ] = false;
	$GLOBALS['options'][ $priority::OPTION ] = array( 101 => 15 );
	do_action( 'admin_init' );
	check_priority( ! empty( $GLOBALS['autoload'][ $priority::OPTION ] ) && array( 101 => 15 ) === $GLOBALS['options'][ $priority::OPTION ], 'La opción existente pasa a carga automática sin perder datos.' );
	update_option( $priority::OPTION, array(), true );
	$reset = new \ReflectionProperty( $priority, 'memo' );
	$reset->setValue( null, array() );

	$GLOBALS['queries'] = 0;
	check_priority( $baseline === $manager->get_location_templates( 'header' ), 'Con el índice vacío, el orden es idéntico (===).' );
	check_priority( 0 === $GLOBALS['queries'], 'El índice con carga automática se lee sin consulta.' );

	/** Escribe el índice como lo dejaría `reindex()` y lo relee. */
	$set_index = function ( array $index ) use ( $priority, $reset ) {
		update_option( $priority::OPTION, $index, true );
		$reset->setValue( null, array() );
	};

	// Manuales que no son candidatas: la excluida (104), el borrador (105), la que no cumple (106) y otra ajena.
	$set_index( array( 104 => 1, 105 => 1, 106 => 1, 999 => 1 ) );
	check_priority( $baseline === $manager->get_location_templates( 'header' ), 'Una manual excluida, en borrador o que no cumple no cambia nada (===).' );

	// D20.
	$set_index( array( 101 => 10 ) );
	check_priority( array( 101 => 10, 103 => 20, 102 => 60 ) === $manager->get_location_templates( 'header' ), 'La manual sustituye a la calculada.' );
	$set_index( array( 101 => 20 ) );
	check_priority( array( 101 => 20, 103 => 20, 102 => 60 ) === $manager->get_location_templates( 'header' ), 'A igual número gana la manual.' );
	$set_index( array( 101 => 30, 102 => 30 ) );
	check_priority( array( 103 => 20, 102 => 30, 101 => 30 ) === $manager->get_location_templates( 'header' ), 'Entre manuales iguales gana el ID más alto.' );
	$set_index( array( 103 => 99 ) );
	check_priority( array( 102 => 60, 101 => 100, 103 => 99 ) !== $manager->get_location_templates( 'header' ) && array( 102 => 60, 103 => 99, 101 => 100 ) === $manager->get_location_templates( 'header' ), 'Una manual también puede bajar una plantilla.' );
	// Ubicación múltiple: se imprimen todas, el orden no se toca.
	$set_index( array( 102 => 1 ) );
	check_priority( $popup_baseline === $manager->get_location_templates( 'popup' ), 'Las ubicaciones múltiples no se reordenan.' );

	// `apply()` puro: automáticas empatadas conservan su orden; valores del núcleo intactos; vacío intacto.
	$set_index( array( 9 => 20 ) );
	check_priority( array( 9 => 20, 5 => 20, 3 => 20, 4 => 40 ) === $priority::apply( array( 5 => 20, 3 => 20, 4 => 40, 9 => 60 ), 'header' ), 'Orden estable entre automáticas empatadas.' );
	check_priority( array() === $priority::apply( array(), 'header' ), 'Sin candidatas, nada.' );
	$same = array( 3 => 20, 5 => 20 );
	check_priority( $same === $priority::apply( $same, 'footer' ), 'Sin manual entre las candidatas, el mismo array.' );

	// reindex(): guardado, meta por cualquier vía, papelera, restauración y borrado.
	$set_index( array() );
	$GLOBALS['meta'][101]['_elementor_page_settings'] = array( 'hide_title' => 'yes', $priority::SETTING => '15' );
	do_action( 'elementor/document/after_save', new \Elementor\Core\Base\Document( 101 ), array() );
	check_priority( array( 101 => 15 ) === $GLOBALS['options'][ $priority::OPTION ] && ! empty( $GLOBALS['autoload'][ $priority::OPTION ] ), 'Guardar indexa la plantilla con carga automática.' );
	update_post_meta( 102, '_elementor_page_settings', array( $priority::SETTING => 25 ) );
	check_priority( array( 101 => 15, 102 => 25 ) === $GLOBALS['options'][ $priority::OPTION ], 'Escribir la meta (copia de red, duplicador, WP-CLI) reindexa.' );
	add_post_meta( 103, '_elementor_page_settings', array( $priority::SETTING => 5 ) );
	check_priority( 5 === ( $GLOBALS['options'][ $priority::OPTION ][103] ?? 0 ), 'Añadir la meta reindexa.' );
	delete_post_meta( 103, '_elementor_page_settings' );
	check_priority( ! isset( $GLOBALS['options'][ $priority::OPTION ][103] ), 'Borrar la meta reindexa.' );
	$GLOBALS['posts'][200] = array( 'type' => 'page', 'status' => 'publish' );
	update_post_meta( 200, '_elementor_page_settings', array( $priority::SETTING => 3 ) );
	check_priority( ! isset( $GLOBALS['options'][ $priority::OPTION ][200] ), 'Una página que no es plantilla nunca entra en el índice.' );
	$write_count = count( $GLOBALS['writes'] );
	update_post_meta( 101, '_elementor_other', 'x' );
	update_post_meta( 101, '_elementor_page_settings', $GLOBALS['meta'][101]['_elementor_page_settings'] );
	check_priority( count( $GLOBALS['writes'] ) === $write_count, 'Sin cambios en el número no se escribe la opción.' );
	$GLOBALS['posts'][101]['status'] = 'trash';
	do_action( 'trashed_post', 101 );
	check_priority( array( 102 => 25 ) === $GLOBALS['options'][ $priority::OPTION ], 'La papelera saca la plantilla del índice.' );
	$GLOBALS['posts'][101]['status'] = 'draft';
	do_action( 'untrashed_post', 101 );
	check_priority( array( 101 => 15, 102 => 25 ) === $GLOBALS['options'][ $priority::OPTION ], 'Restaurarla la devuelve al índice.' );
	unset( $GLOBALS['posts'][102] );
	do_action( 'deleted_post', 102 );
	check_priority( array( 101 => 15 ) === $GLOBALS['options'][ $priority::OPTION ], 'Borrarla la saca del índice.' );
	check_priority( array( 101 => 15 ) === $priority::index(), 'El índice en memoria sigue a la opción.' );

	// Rango 1–99 y forma.
	foreach ( array( 0, 100, -5, '-5', '12abc', 'abc', '', null, 3.5, array( 4 ), true ) as $bad ) {
		check_priority( 0 === $priority::sanitize( $bad ), 'Fuera de rango o mal formado: ' . var_export( $bad, true ) );
	}
	foreach ( array( 1 => 1, 99 => 99, '42' => 42, ' 7 ' => 7 ) as $good => $expected ) {
		check_priority( $expected === $priority::sanitize( is_int( $good ) ? $good : (string) $good ), 'Válido: ' . $good );
	}
	check_priority( 8 === $priority::sanitize( 8.0 ), 'Un número entero en coma flotante vale.' );
	update_post_meta( 101, '_elementor_page_settings', array( $priority::SETTING => 120 ) );
	check_priority( ! isset( $GLOBALS['options'][ $priority::OPTION ][101] ), 'Un valor fuera de rango deja la plantilla automática.' );
	$GLOBALS['options'][ $priority::OPTION ] = array( 101 => 0, 'x' => 4, 102 => 150, 103 => '9' );
	$reset->setValue( null, array() );
	check_priority( array( 103 => 9 ) === $priority::index(), 'La lectura descarta lo que no es un índice válido.' );

	// Campo `priority` de los kits (parte `theme-builder`).
	$part_source = file_get_contents( $root . '/modules/digitalisimo-import/kits/parts/class-theme-builder.php' );
	check_priority( preg_match( "/const FIELD_PATTERN\s*=\s*'([^']+)'/", $part_source, $pattern ) && 1 === preg_match( $pattern[1], $priority::FIELD ), 'El nombre del campo cumple el patrón de la parte theme-builder.' );
	$fields = apply_filters( 'digitalisimo_elements_kit_theme_builder_fields', array( 'otro' => array() ), 101 );
	check_priority( isset( $fields['otro'], $fields['priority']['export'], $fields['priority']['import'] ) && is_callable( $fields['priority']['export'] ) && is_callable( $fields['priority']['import'] ), 'El campo priority se declara sin quitar los demás.' );
	$GLOBALS['meta'][101]['_elementor_page_settings'] = array( $priority::SETTING => 12 );
	check_priority( 12 === call_user_func( $fields['priority']['export'], 101 ), 'Exportar devuelve el número.' );
	$GLOBALS['meta'][101]['_elementor_page_settings'] = array( 'hide_title' => 'yes' );
	check_priority( null === call_user_func( $fields['priority']['export'], 101 ), 'Sin prioridad manual, exportar devuelve null.' );
	$GLOBALS['options'][ $priority::OPTION ] = array();
	$reset->setValue( null, array() );
	$GLOBALS['posts'][301] = array( 'type' => 'elementor_library', 'status' => 'draft' );
	call_user_func( $fields['priority']['import'], 301, '33', null );
	check_priority( array( $priority::SETTING => 33 ) === ( $GLOBALS['meta'][301]['_elementor_page_settings'] ?? null ) && array( 301 => 33 ) === $GLOBALS['options'][ $priority::OPTION ], 'Importar escribe el ajuste si falta y reindexa.' );
	$GLOBALS['posts'][302] = array( 'type' => 'elementor_library', 'status' => 'draft' );
	$GLOBALS['meta'][302]['_elementor_page_settings'] = array( 'hide_title' => 'yes' );
	call_user_func( $fields['priority']['import'], 302, 44, null );
	check_priority( array( 'hide_title' => 'yes', $priority::SETTING => 44 ) === $GLOBALS['meta'][302]['_elementor_page_settings'] && 44 === ( $GLOBALS['options'][ $priority::OPTION ][302] ?? 0 ), 'Importar conserva los demás ajustes.' );
	// Ajustes de la plantilla aún sin guardar o distintos (V-m20): el índice usa el valor recibido.
	$GLOBALS['posts'][303] = array( 'type' => 'elementor_library', 'status' => 'draft' );
	$GLOBALS['meta'][303]['_elementor_page_settings'] = array( $priority::SETTING => 50 );
	call_user_func( $fields['priority']['import'], 303, 11, null );
	check_priority( 11 === ( $GLOBALS['options'][ $priority::OPTION ][303] ?? 0 ) && 50 === $GLOBALS['meta'][303]['_elementor_page_settings'][ $priority::SETTING ], 'Importar reindexa con el valor recibido sin pisar el ajuste guardado.' );
	call_user_func( $fields['priority']['import'], 304, 140, null );
	check_priority( ! isset( $GLOBALS['meta'][304] ) && ! isset( $GLOBALS['options'][ $priority::OPTION ][304] ), 'Un valor importado fuera de rango se ignora.' );

	// Control: plantillas con ubicación no múltiple, detrás de post_status (o de post_title).
	$header = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( 101 );
	$header->location = 'header';
	do_action( 'elementor/documents/register_controls', $header );
	$control = $header->controls[ $priority::SETTING ] ?? array();
	check_priority( array( 'post_title', 'post_status', $priority::SETTING ) === $header->order && array( 'post_status' ) === $header->injections, 'El control va justo detrás de post_status.' );
	check_priority( 'number' === ( $control['type'] ?? '' ) && 1 === ( $control['min'] ?? 0 ) && 99 === ( $control['max'] ?? 0 ) && '' === ( $control['default'] ?? null ) && empty( $control['selectors'] ) && empty( $control['prefix_class'] ), 'NUMBER 1–99, vacío por defecto y sin salida.' );
	check_priority( false !== strpos( $control['description'] ?? '', 'Número menor, más prioridad' ) && false === stripos( $control['label'] . $control['description'], 'elementor' ), 'Ayuda en español y sin el nombre del núcleo.' );
	$single = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( 102, array( 'post_title' ) );
	$single->location = 'single';
	do_action( 'elementor/documents/register_controls', $single );
	check_priority( isset( $single->controls[ $priority::SETTING ] ) && array( 'post_title' ) === $single->injections, 'Sin post_status, detrás de post_title; una ubicación del núcleo no registrada también vale.' );
	foreach ( array( 'popup' => 'múltiple', 'digitalisimo_before_header' => 'de gancho', '' => 'sin ubicación', 'otra' => 'desconocida' ) as $location => $label ) {
		$doc = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( 103 );
		$doc->location = $location;
		do_action( 'elementor/documents/register_controls', $doc );
		check_priority( ! isset( $doc->controls[ $priority::SETTING ] ), 'Ubicación ' . $label . ': sin control.' );
	}
	$page = new \Elementor\Core\Base\Document( 200 );
	do_action( 'elementor/documents/register_controls', $page );
	check_priority( ! isset( $page->controls[ $priority::SETTING ] ), 'Un documento que no es plantilla no lleva el control.' );
	$bare = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( 104, array() );
	$bare->location = 'header';
	do_action( 'elementor/documents/register_controls', $bare );
	check_priority( ! isset( $bare->controls[ $priority::SETTING ] ), 'Sin post_status ni post_title no se inyecta nada.' );

	// El derivado de PRO: la línea del filtro, una sola vez, justo después del único asort (por tokens).
	$path   = $root . '/modules/theme-builder/classes/conditions-manager.php';
	$source = file_get_contents( $path );
	check_priority( 1 === substr_count( $source, $priority::FILTER ) && 1 === substr_count( $source, 'asort(' ), 'Un solo asort y una sola mención del filtro.' );
	$tokens = array_values( array_filter( token_get_all( $source ), function ( $token ) { return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ); } ) );
	$text   = function ( $token ) { return is_array( $token ) ? $token[1] : $token; };
	$found  = -1;
	foreach ( $tokens as $i => $token ) {
		if ( is_array( $token ) && T_STRING === $token[0] && 'asort' === $token[1] ) { $found = $i; }
	}
	check_priority( $found > 0, 'No se encontró el asort.' );
	$asort_line = $tokens[ $found ][2];
	$expected   = array( 'asort', '(', '$conditions_priority', ')', ';', '$conditions_priority', '=', 'apply_filters', '(', "'digitalisimo_elements_theme_builder_priorities'", ',', '$conditions_priority', ',', '$location', ')', ';', 'return', '$conditions_priority', ';', '}' );
	$actual     = array_map( $text, array_slice( $tokens, $found, count( $expected ) ) );
	check_priority( $expected === $actual, 'La línea del filtro debe ir justo después del asort: ' . implode( ' ', $actual ) );
	check_priority( $tokens[ $found + 5 ][2] === $asort_line + 1, 'El filtro está en la línea siguiente al asort.' );
	check_priority( 411 === $asort_line, 'El asort sigue en la línea 411 (' . $asort_line . ').' );

	echo "DIGITALÍSIMO Elements: prioridad manual del Theme Builder validada (sin manuales, el mismo array).\n";
}
