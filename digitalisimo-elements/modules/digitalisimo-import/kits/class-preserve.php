<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Unconverted_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Marcador `digitalisimo-unconverted` (D9, §10.3): conserva lo que el núcleo no sabe pintar.
 *
 * El núcleo descarta al guardar todo elemento que no puede instanciar (un widget de un plugin que este sitio no
 * tiene, un contenedor con el experimento apagado, un elemento v4) y, al importar un kit, quita las instancias de
 * componentes v4. `wrap()` sustituye cada uno por un widget oculto que guarda el original completo y el motivo
 * (`unknown` o `component`). El marcador no pinta nada en la página, avisa en el editor y, al exportar, devuelve
 * el original con su ID. Cuando el tipo vuelve a existir, `restore()` lo devuelve a su sitio al cargar el
 * documento: nada se reescribe en la base de datos hasta que alguien guarda.
 *
 * Dónde corre (§5.4 y §7.1):
 * - `elementor/document/save/data` con prioridad 5 dentro del contexto de una importación de kits
 *   (`on_save_data()`, que llama `Context::preserve()`), antes de que el núcleo capture los elementos en la
 *   prioridad 10;
 * - `elementor/template_library/sources/local/import/elements` siempre (`filter_library_import()`), para las
 *   plantillas sueltas de la biblioteca;
 * - con marcadores en el sitio (la opción `digitalisimo_elements_unconverted`, que se lee de `alloptions`):
 *   `load/data`, `should_render` de los envoltorios, `parse_css` y `after_save`. Sin marcadores, el sitio no paga
 *   nada: ni una consulta ni un filtro más.
 *
 * Nunca codifica la carga para esquivar kses (CC-17): para un usuario sin `unfiltered_html`, el núcleo la sanea
 * después de `save/data` como al resto del contenido, y `stats()` lo deja dicho para el informe.
 */
final class Preserve {
	/** Ajuste del marcador que guarda `{ v, type, reason, source, element }`. */
	const SETTING = 'digitalisimo_unconverted';

	/** Ajuste de la sección, la columna o el contenedor que K crea para colocar un marcador donde no cabe un widget. */
	const WRAPPER_SETTING = 'digitalisimo_unconverted_wrapper';

	const VERSION = 1;

	const REASON_UNKNOWN   = 'unknown';
	const REASON_COMPONENT = 'component';
	const REASONS          = array( self::REASON_UNKNOWN, self::REASON_COMPONENT );

	const SOURCE_KIT       = 'kit';
	const SOURCE_LIBRARY   = 'library';
	const SOURCE_MIGRATION = 'migration';
	const SOURCES          = array( self::SOURCE_KIT, self::SOURCE_LIBRARY, self::SOURCE_MIGRATION );

	/** Instancia de un componente v4 (`Component_Instance_Prop_Type::WIDGET_TYPE` del núcleo). */
	const COMPONENT_TYPE = 'e-component';

	/** Huella de los tipos ya devueltos a su sitio en un documento: la caché se invalida una sola vez por cambio. */
	const META_RESTORED = '_digitalisimo_unconverted_restored';

	/** Metas del núcleo que se borran para que el CSS y la caché de elementos se regeneren sin el marcador. */
	const META_CSS           = '_elementor_css';
	const META_ELEMENT_CACHE = '_elementor_element_cache';

	/** Tipos que pueden ser envoltorio de un marcador. */
	const WRAPPER_TYPES = array( 'section', 'column', 'container' );

	/** Profundidad máxima que se recorre (la misma que admite el guardián para el JSON del kit). */
	const MAX_DEPTH = 256;

	/** Forma de un ID de elemento que se conserva tal cual; otro valor recibe uno derivado del contenido. */
	const ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

	const PRIORITY_SAVE = 5;
	const PRIORITY      = 20;

	/** @var bool */
	private static $booted = false;

	/** @var bool Ganchos que sólo existen con marcadores en el sitio. */
	private static $runtime = false;

	/** @var bool Se espera importar plantillas en esta petición. */
	private static $expect = false;

	/** @var bool */
	private static $registered = false;

	/** @var bool */
	private static $watching = false;

	/** @var bool[] Tipo => ¿registrado?, por petición. */
	private static $known = array();

	/** @var array{documents: array, kses: array, why: array} */
	private static $stats = array(
		'documents' => array(),
		'kses'      => array(),
		'why'       => array(),
	);

	/**
	 * Engancha el marcador. Lo llama `Module::init()`; idempotente.
	 *
	 * Siempre: el registro condicionado del widget y el filtro de la biblioteca (coste cero hasta que el núcleo
	 * registra widgets o importa una plantilla). Sólo con marcadores en el sitio: lo que los desenvuelve y oculta.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ), self::PRIORITY );
		add_filter( 'elementor/template_library/sources/local/import/elements', array( __CLASS__, 'filter_library_import' ), self::PRIORITY );
		if ( self::count() > 0 ) {
			self::runtime_hooks();
		}
	}

	/**
	 * Número de marcadores del sitio, leído de `alloptions` (la opción se guarda siempre con autoload).
	 *
	 * Así un sitio sin marcadores no hace ninguna consulta: la opción ni existe.
	 */
	public static function count(): int {
		if ( ! function_exists( 'wp_load_alloptions' ) ) {
			return 0;
		}
		$all = wp_load_alloptions();
		return is_array( $all ) && isset( $all[ Module::OPTION_UNCONVERTED ] ) ? max( 0, (int) $all[ Module::OPTION_UNCONVERTED ] ) : 0;
	}

	/**
	 * Anuncia que esta petición va a importar (o deja de hacerlo). El widget se registra aunque el sitio aún no
	 * tenga marcadores, y los guardados recalculan los contadores.
	 *
	 * Lo llaman la intercepción de la biblioteca y, si lo prefiere, el contexto de importación (§5.4).
	 */
	public static function expect( bool $on = true ): void {
		self::$expect = $on;
		if ( $on ) {
			self::watch_saves();
		}
	}

	/** ¿Debe existir el widget en esta petición? Con marcadores, con una importación en curso o en `expect()` (CR-21). */
	public static function should_register(): bool {
		return self::$expect || self::count() > 0 || self::context_active();
	}

	/**
	 * `elementor/widgets/register` (20): registra el marcador sólo si hace falta.
	 *
	 * @param object $manager `Widgets_Manager` del núcleo.
	 */
	public static function register_widget( $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) || ! self::should_register() ) {
			return;
		}
		if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( Module::WIDGET ) ) {
			self::$registered = true;
			return;
		}
		$manager->register( new Unconverted_Widget() );
		self::$registered = true;
	}

	/**
	 * Registra el marcador ahora si todavía no existe (los widgets del núcleo pueden haberse registrado antes de
	 * saber que se importaba). Devuelve si el marcador queda registrado.
	 */
	public static function ensure_widget(): bool {
		$manager = self::widgets_manager();
		if ( null === $manager ) {
			return false;
		}
		if ( $manager->get_widget_types( Module::WIDGET ) ) {
			self::$registered = true;
			return true;
		}
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return false;
		}
		$manager->register( new Unconverted_Widget() );
		self::$registered = (bool) $manager->get_widget_types( Module::WIDGET );
		return self::$registered;
	}

	/**
	 * Sustituye por un marcador cada elemento que no se puede instanciar y, salvo en la biblioteca, cada instancia
	 * de componente v4 mientras el núcleo no los admita en kits. Lo conocido no cambia. Idempotente.
	 *
	 * Colocación: un marcador es un widget. En la raíz va dentro de una sección y una columna si el documento usa
	 * secciones o los contenedores no existen, y si no, dentro de un contenedor; como hijo directo de una sección,
	 * dentro de una columna; como hijo de un widget anidado, dentro de un contenedor. Esos envoltorios llevan
	 * `digitalisimo_unconverted_wrapper = 1`, no se pintan mientras sólo contengan marcadores y desaparecen cuando
	 * el original vuelve.
	 *
	 * @param array  $elements Árbol de elementos de un documento.
	 * @param string $source   `kit`, `library` o `migration` (va en la carga, para el informe).
	 * @param array  $env      Para pruebas: `known( string $el_type, ?string $widget_type ): bool`, `container`
	 *                         (bool) y `components` (bool: el núcleo admite componentes en kits). Por defecto,
	 *                         lo registrado en el núcleo.
	 */
	public static function wrap( array $elements, string $source = self::SOURCE_KIT, array $env = array() ): array {
		$env    = self::env( $env );
		$source = in_array( $source, self::SOURCES, true ) ? $source : self::SOURCE_KIT;
		return self::wrap_list( $elements, null, false, $source, $env, self::uses_sections( $elements ), 0 );
	}

	/**
	 * `elementor/document/save/data` (5) dentro del contexto de importación de kits.
	 *
	 * @param mixed  $data     `{ elements, settings }` del guardado.
	 * @param object $document Documento que se guarda.
	 *
	 * @return mixed
	 */
	public static function on_save_data( $data, $document = null ) {
		if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}
		$data['elements'] = self::wrap( $data['elements'], self::SOURCE_KIT );
		$found            = self::markers( $data['elements'] );
		if ( ! $found ) {
			return $data;
		}
		self::ensure_widget();
		self::watch_saves();
		foreach ( $found as $item ) {
			self::$stats['why'][ $item['type'] ] = $item['why'];
		}
		$id = self::document_id( $document );
		if ( $id > 0 ) {
			self::$stats['documents'][ $id ] = self::summarize( $found );
			if ( function_exists( 'current_user_can' ) && ! current_user_can( 'unfiltered_html' ) ) {
				self::$stats['kses'][ $id ] = count( $found );
			}
		}
		return $data;
	}

	/**
	 * `elementor/template_library/sources/local/import/elements` (20): el marcador en las plantillas sueltas.
	 *
	 * Los componentes v4 no se envuelven aquí: la biblioteca del núcleo los conserva tal cual.
	 *
	 * @param mixed $content Árbol de elementos de la plantilla.
	 *
	 * @return mixed
	 */
	public static function filter_library_import( $content ) {
		if ( ! is_array( $content ) || ! $content ) {
			return $content;
		}
		$content = self::wrap( $content, self::SOURCE_LIBRARY );
		if ( self::count_markers( $content ) > 0 ) {
			self::ensure_widget();
			self::watch_saves();
		}
		return $content;
	}

	/**
	 * `elementor/document/load/data` (20), sólo con marcadores y sólo en documentos con `_digitalisimo_unconverted`.
	 *
	 * Devuelve a su sitio los marcadores `unknown` cuyo tipo ya existe. La primera vez que cambia lo devuelto en un
	 * documento, borra su CSS y su caché de elementos para que se regeneren sin el marcador (CC-22). En el editor
	 * (nunca en el front) aplica además la conversión de `digitalisimo_elements_unconverted_convert`.
	 *
	 * @param mixed  $data     Árbol de elementos.
	 * @param object $document Documento que se carga.
	 *
	 * @return mixed
	 */
	public static function filter_load_data( $data, $document = null ) {
		if ( ! is_array( $data ) || ! $data ) {
			return $data;
		}
		$id = self::document_id( $document );
		if ( $id <= 0 || ! get_post_meta( $id, Module::META_UNCONVERTED, true ) ) {
			return $data;
		}
		$restored = array();
		$data     = self::restore( $data, array(), $restored );
		if ( $restored ) {
			self::invalidate_once( $id, $restored );
		}
		if ( self::is_editor_context() ) {
			$data = self::convert( $data );
		}
		return $data;
	}

	/**
	 * Devuelve a su sitio cada marcador `unknown` cuyo tipo ya existe. Los `component` nunca vuelven solos.
	 *
	 * Un envoltorio que sólo contenía marcadores desaparece si todos vuelven; si alguien añadió contenido dentro o
	 * alguno sigue sin poder pintarse, se queda y el original vuelve en su posición.
	 *
	 * @param array      $elements Árbol de elementos.
	 * @param array      $env      Ver `wrap()`.
	 * @param array|null $restored Recibe los tipos devueltos.
	 */
	public static function restore( array $elements, array $env = array(), ?array &$restored = null ): array {
		$env      = self::env( $env );
		$restored = array();
		return self::transform(
			$elements,
			function ( array $marker ) use ( $env ) {
				$info = self::info( $marker );
				if ( null === $info || self::REASON_UNKNOWN !== $info['reason'] ) {
					return null;
				}
				return '' === self::reason( $info['element'], self::SOURCE_LIBRARY, $env ) ? $info['element'] : null;
			},
			$restored,
			0
		);
	}

	/**
	 * Conversión de los marcadores con `digitalisimo_elements_unconverted_convert( ?array $element, array $original,
	 * string $type )`. Sólo la llaman el editor (desde `filter_load_data()`) y DIGITALÍSIMO Migración: nunca el
	 * front (CR-21). Un resultado que no es un elemento (`elType`) deja el marcador.
	 *
	 * @param array      $elements  Árbol de elementos.
	 * @param array|null $converted Recibe los tipos convertidos.
	 */
	public static function convert( array $elements, ?array &$converted = null ): array {
		$converted = array();
		if ( ! function_exists( 'apply_filters' ) || ( function_exists( 'has_filter' ) && ! has_filter( Module::FILTER_UNCONVERTED_CONVERT ) ) ) {
			return $elements;
		}
		return self::transform(
			$elements,
			function ( array $marker ) {
				$info = self::info( $marker );
				if ( null === $info ) {
					return null;
				}
				$result = apply_filters( Module::FILTER_UNCONVERTED_CONVERT, null, $info['element'], $info['type'] );
				return is_array( $result ) && isset( $result['elType'] ) && is_string( $result['elType'] ) && '' !== $result['elType'] ? $result : null;
			},
			$converted,
			0
		);
	}

	/**
	 * Para una exportación o una comparación: cada marcador pasa a su original (sea cual sea su motivo) y los
	 * envoltorios que sólo contenían marcadores desaparecen. Es la forma que tenía el contenido antes de importarse.
	 */
	public static function unwrap( array $elements ): array {
		$changed = array();
		return self::transform(
			$elements,
			function ( array $marker ) {
				$info = self::info( $marker );
				return null === $info ? null : $info['element'];
			},
			$changed,
			0
		);
	}

	/**
	 * `elementor/frontend/{section,column,container}/should_render` (20): un envoltorio de K no se pinta mientras
	 * sólo contenga marcadores.
	 *
	 * @param bool   $should  Valor del núcleo.
	 * @param object $element Elemento que se pinta.
	 */
	public static function filter_should_render( $should, $element = null ) {
		if ( ! $should || ! is_object( $element ) || ! method_exists( $element, 'get_data' ) ) {
			return $should;
		}
		$settings = $element->get_data( 'settings' );
		if ( ! is_array( $settings ) || empty( $settings[ self::WRAPPER_SETTING ] ) ) {
			return $should;
		}
		$children = $element->get_data( 'elements' );
		return is_array( $children ) && self::only_markers( $children ) ? false : $should;
	}

	/**
	 * `elementor/element/parse_css` (10): el CSS del documento se genera desde los datos guardados, que aún tienen
	 * el marcador. Si su original ya se pinta (`restore()`), se añaden aquí sus reglas, con el mismo ID.
	 *
	 * @param object $post_css CSS de la entrada (`Core\Files\CSS\Post`).
	 * @param object $element  Elemento cuyo CSS se acaba de generar.
	 */
	public static function parse_css( $post_css, $element = null ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || Module::WIDGET !== $element->get_name() || ! method_exists( $element, 'get_data' ) ) {
			return;
		}
		$info = self::info(
			array(
				'elType'     => 'widget',
				'widgetType' => Module::WIDGET,
				'settings'   => $element->get_data( 'settings' ),
			)
		);
		if ( null === $info || self::REASON_UNKNOWN !== $info['reason'] || '' !== self::reason( $info['element'], self::SOURCE_LIBRARY, self::env( array() ) ) ) {
			return;
		}
		$manager = self::elements_manager();
		if ( null === $manager || ! is_object( $post_css ) || ! class_exists( '\Elementor\Core\Files\CSS\Post', false ) || ! $post_css instanceof \Elementor\Core\Files\CSS\Post ) {
			return;
		}
		try {
			$original = $manager->create_element_instance( $info['element'] );
			if ( ! $original ) {
				return;
			}
			// `render_styles()` es protegido: pinta los estilos globales, los propios y los de los hijos, igual que
			// para cualquier otro elemento del documento.
			$render = new \ReflectionMethod( \Elementor\Core\Files\CSS\Post::class, 'render_styles' );
			if ( PHP_VERSION_ID < 80100 ) {
				$render->setAccessible( true );
			}
			$render->invoke( $post_css, $original );
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * `elementor/document/after_save` (10): recalcula el contador del documento y el del sitio si cambian.
	 *
	 * @param object $document Documento guardado.
	 * @param mixed  $data     Datos guardados (después de `save/data` y de kses).
	 */
	public static function after_save( $document, $data = null ): void {
		$id = self::document_id( $document );
		if ( $id <= 0 || ! is_array( $data ) || ! isset( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return;
		}
		if ( method_exists( $document, 'is_autosave' ) && $document->is_autosave() ) {
			return;
		}
		self::recount_document( $id, $data['elements'] );
	}

	/**
	 * Fija `_digitalisimo_unconverted` de un documento y, si cambió, recalcula el total del sitio.
	 *
	 * @return int Marcadores del documento.
	 */
	public static function recount_document( int $post_id, array $elements ): int {
		$count = self::count_markers( $elements );
		$had   = (int) get_post_meta( $post_id, Module::META_UNCONVERTED, true );
		if ( $count === $had ) {
			return $count;
		}
		if ( $count > 0 ) {
			update_post_meta( $post_id, Module::META_UNCONVERTED, $count );
		} else {
			delete_post_meta( $post_id, Module::META_UNCONVERTED );
			delete_post_meta( $post_id, self::META_RESTORED );
		}
		self::recount_site();
		return $count;
	}

	/**
	 * Recalcula `digitalisimo_elements_unconverted` (suma de las metas de los documentos). Con cero la opción se
	 * borra, para que un sitio sin marcadores no la cargue.
	 */
	public static function recount_site(): int {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return 0;
		}
		$sum = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)),0) FROM {$wpdb->postmeta} WHERE meta_key = %s", Module::META_UNCONVERTED ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $sum > 0 ) {
			update_option( Module::OPTION_UNCONVERTED, $sum, true );
		} else {
			delete_option( Module::OPTION_UNCONVERTED );
		}
		return $sum;
	}

	/** ¿Es un marcador? */
	public static function is_marker( $element ): bool {
		return is_array( $element )
			&& isset( $element['elType'], $element['widgetType'] )
			&& 'widget' === $element['elType']
			&& Module::WIDGET === $element['widgetType'];
	}

	/** ¿Es un envoltorio creado por K? */
	public static function is_wrapper( $element ): bool {
		return is_array( $element )
			&& isset( $element['elType'] )
			&& in_array( $element['elType'], self::WRAPPER_TYPES, true )
			&& ! empty( $element['settings'][ self::WRAPPER_SETTING ] );
	}

	/**
	 * Carga de un marcador: `{ v, type, reason, source, element }` con el original en `element`, o null.
	 *
	 * Para el remapeo (CV-19), la comparación de IDs de la exportación (D20) y el informe.
	 */
	public static function info( $marker ): ?array {
		if ( ! self::is_marker( $marker ) || ! isset( $marker['settings'][ self::SETTING ] ) || ! is_array( $marker['settings'][ self::SETTING ] ) ) {
			return null;
		}
		$info = $marker['settings'][ self::SETTING ];
		if ( ! isset( $info['element'] ) || ! is_array( $info['element'] ) ) {
			return null;
		}
		return array(
			'v'       => isset( $info['v'] ) ? (int) $info['v'] : self::VERSION,
			'type'    => isset( $info['type'] ) && is_string( $info['type'] ) ? $info['type'] : self::type_of( $info['element'] ),
			'reason'  => isset( $info['reason'] ) && in_array( $info['reason'], self::REASONS, true ) ? $info['reason'] : self::REASON_UNKNOWN,
			'source'  => isset( $info['source'] ) && in_array( $info['source'], self::SOURCES, true ) ? $info['source'] : self::SOURCE_KIT,
			'element' => $info['element'],
		);
	}

	/** El original que guarda un marcador, o null. */
	public static function payload( $marker ): ?array {
		$info = self::info( $marker );
		return null === $info ? null : $info['element'];
	}

	/**
	 * Devuelve el marcador con otro original (por ejemplo, el remapeado por `Remapper`), sin tocar lo demás.
	 *
	 * @param array $marker   Marcador.
	 * @param array $original Elemento original nuevo.
	 */
	public static function with_payload( array $marker, array $original ): array {
		if ( null === self::info( $marker ) ) {
			return $marker;
		}
		$marker['settings'][ self::SETTING ]['element'] = $original;
		return $marker;
	}

	/** Construye el marcador de un elemento (§10.3). Conserva su ID si tiene una forma válida. */
	public static function marker( array $element, string $reason = self::REASON_UNKNOWN, string $source = self::SOURCE_KIT ): array {
		$id = isset( $element['id'] ) && ( is_string( $element['id'] ) || is_int( $element['id'] ) ) ? (string) $element['id'] : '';
		if ( 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			$id = self::derived_id( self::fingerprint( $element ), 'marker' );
		}
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => Module::WIDGET,
			'settings'   => array(
				self::SETTING => array(
					'v'       => self::VERSION,
					'type'    => self::type_of( $element ),
					'reason'  => in_array( $reason, self::REASONS, true ) ? $reason : self::REASON_UNKNOWN,
					'source'  => in_array( $source, self::SOURCES, true ) ? $source : self::SOURCE_KIT,
					'element' => $element,
				),
			),
			'elements'   => array(),
		);
	}

	/** Tipo legible de un elemento: `widget/<widgetType>` para un widget y `<elType>` para lo demás. */
	public static function type_of( array $element ): string {
		$el_type     = isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : '';
		$widget_type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		return '' !== $widget_type ? $el_type . '/' . $widget_type : $el_type;
	}

	/** Marcadores del árbol (sin entrar en las cargas). */
	public static function count_markers( array $elements ): int {
		return count( self::markers( $elements ) );
	}

	/**
	 * Cargas de los marcadores del árbol, sin entrar en ellas.
	 *
	 * @return array[] `{ id, type, reason, source, why }` por marcador.
	 */
	public static function markers( array $elements ): array {
		$found = array();
		self::collect( $elements, $found, 0 );
		return $found;
	}

	/** ¿Contiene el árbol algún marcador? */
	public static function contains_markers( array $elements ): bool {
		return self::count_markers( $elements ) > 0;
	}

	/**
	 * ¿Son todos marcadores (o envoltorios de K que sólo contienen marcadores)? Una lista vacía no lo es.
	 */
	public static function only_markers( array $elements, int $depth = 0 ): bool {
		if ( ! $elements || $depth > self::MAX_DEPTH ) {
			return false;
		}
		foreach ( $elements as $element ) {
			if ( self::is_marker( $element ) ) {
				continue;
			}
			if ( self::is_wrapper( $element ) && ! empty( $element['elements'] ) && is_array( $element['elements'] ) && self::only_markers( $element['elements'], $depth + 1 ) ) {
				continue;
			}
			return false;
		}
		return true;
	}

	/**
	 * Lo que pasó en esta petición, para el informe de la sesión: marcadores por documento (`tipo` => `motivo` =>
	 * número), documentos guardados por un usuario sin `unfiltered_html`, cuya carga sanea kses (CC-17), y por qué
	 * falta cada tipo (`why()`, §10.4).
	 *
	 * @return array{documents: array, kses: array, why: array}
	 */
	public static function stats(): array {
		return self::$stats;
	}

	/**
	 * Olvida el estado de la petición.
	 *
	 * @internal Para pruebas.
	 */
	public static function reset(): void {
		self::$booted     = false;
		self::$runtime    = false;
		self::$expect     = false;
		self::$registered = false;
		self::$watching   = false;
		self::$known      = array();
		self::$stats      = array(
			'documents' => array(),
			'kses'      => array(),
			'why'       => array(),
		);
	}

	/**
	 * Por qué este sitio no puede pintar un elemento (§10.4), para el aviso del informe. K nunca activa un
	 * experimento ni un plugin: sólo lo dice.
	 *
	 * @return string `component`, `experiment:container`, `experiment:atomic` (v4), `experiment:nested-elements`,
	 *                `plugin:woocommerce` o `unknown`.
	 */
	public static function why( array $element, string $reason = self::REASON_UNKNOWN ): string {
		if ( self::REASON_COMPONENT === $reason ) {
			return 'component';
		}
		$el_type     = isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : '';
		$widget_type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		$type        = 'widget' === $el_type ? $widget_type : $el_type;
		if ( 'container' === $type ) {
			return 'experiment:container';
		}
		if ( 0 === strpos( $type, 'e-' ) ) {
			return 'experiment:atomic';
		}
		if ( 'widget' === $el_type && ( 0 === strpos( $widget_type, 'nested-' ) || ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) ) ) {
			return 'experiment:nested-elements';
		}
		if ( 'widget' === $el_type && 1 === preg_match( '/^(?:woocommerce|wc)-/', $widget_type ) ) {
			return 'plugin:woocommerce';
		}
		return 'unknown';
	}

	/**
	 * ¿Está registrado el tipo en el núcleo? Sin núcleo cargado no se puede decidir y se responde que sí: el
	 * elemento queda como estaba, igual que sin K.
	 */
	public static function is_registered( string $el_type, ?string $widget_type = null ): bool {
		if ( 'widget' === $el_type && Module::WIDGET === $widget_type ) {
			return true;
		}
		$key = $el_type . '|' . (string) $widget_type;
		if ( isset( self::$known[ $key ] ) ) {
			return self::$known[ $key ];
		}
		if ( 'widget' === $el_type ) {
			$manager = self::widgets_manager();
			if ( null === $manager ) {
				return true;
			}
			$type = null !== $widget_type && '' !== $widget_type ? $manager->get_widget_types( $widget_type ) : null;
		} else {
			$manager = self::elements_manager();
			if ( null === $manager ) {
				return true;
			}
			$type = $manager->get_element_types( $el_type );
		}
		self::$known[ $key ] = is_object( $type );
		return self::$known[ $key ];
	}

	/** ¿Admite el núcleo componentes v4 en kits (`IS_IMPORT_EXPORT_SUPPORTED`)? */
	public static function components_supported(): bool {
		$class = '\Elementor\Modules\Components\Module';
		return class_exists( $class ) && is_callable( array( $class, 'is_import_export_supported' ) ) && (bool) call_user_func( array( $class, 'is_import_export_supported' ) );
	}

	/**
	 * ¿Es una petición del editor? `load/data` sólo convierte allí: nunca en el front, ni en la vista previa, ni
	 * en el cron, ni para un visitante.
	 */
	public static function is_editor_context(): bool {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		$editor = ( function_exists( 'is_admin' ) && is_admin() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		return $editor && function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' );
	}

	/** Ganchos que sólo existen con marcadores en el sitio. */
	private static function runtime_hooks(): void {
		if ( self::$runtime ) {
			return;
		}
		self::$runtime = true;
		add_filter( 'elementor/document/load/data', array( __CLASS__, 'filter_load_data' ), self::PRIORITY, 2 );
		foreach ( self::WRAPPER_TYPES as $type ) {
			add_filter( "elementor/frontend/{$type}/should_render", array( __CLASS__, 'filter_should_render' ), self::PRIORITY, 2 );
		}
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'parse_css' ), 10, 2 );
		self::watch_saves();
	}

	/** Recalcula los contadores al guardar (una sola vez por petición). */
	private static function watch_saves(): void {
		if ( self::$watching || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$watching = true;
		add_action( 'elementor/document/after_save', array( __CLASS__, 'after_save' ), 10, 2 );
	}

	/**
	 * ¿Hay una importación de kits en curso (`Context::active()`)? Sin cargar nada: si la clase del contexto no se
	 * cargó, no hay ninguna.
	 */
	private static function context_active(): bool {
		$class = __NAMESPACE__ . '\\Context';
		if ( ! class_exists( $class, false ) || ! method_exists( $class, 'active' ) ) {
			return false;
		}
		$method = new \ReflectionMethod( $class, 'active' );
		return $method->isStatic() && $method->isPublic() && (bool) $method->invoke( null );
	}

	/** Borra el CSS y la caché de elementos del documento la primera vez que cambia lo devuelto a su sitio. */
	private static function invalidate_once( int $post_id, array $restored ): void {
		$types = array_values( array_unique( $restored ) );
		sort( $types, SORT_STRING );
		$signature = sha1( implode( "\n", $types ) );
		if ( get_post_meta( $post_id, self::META_RESTORED, true ) === $signature ) {
			return;
		}
		if ( class_exists( '\Elementor\Core\Files\CSS\Post', false ) && is_callable( array( '\Elementor\Core\Files\CSS\Post', 'create' ) ) ) {
			try {
				\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
			} catch ( \Throwable $error ) {
				delete_post_meta( $post_id, self::META_CSS );
			}
		} else {
			delete_post_meta( $post_id, self::META_CSS );
		}
		delete_post_meta( $post_id, self::META_ELEMENT_CACHE );
		update_post_meta( $post_id, self::META_RESTORED, $signature );
	}

	/**
	 * Recorre el árbol y sustituye cada marcador por lo que devuelva `$replace` (null: se queda). Un envoltorio de
	 * K que sólo contenía marcadores desaparece si ya no queda ninguno: sus hijos ocupan su lugar.
	 */
	private static function transform( array $elements, callable $replace, array &$changed, int $depth ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				$out[] = $element;
				continue;
			}
			if ( self::is_marker( $element ) ) {
				$new = $replace( $element );
				if ( is_array( $new ) ) {
					$info      = self::info( $element );
					$changed[] = null === $info ? self::type_of( $new ) : $info['type'];
					$out[]     = $new;
				} else {
					$out[] = $element;
				}
				continue;
			}
			if ( $depth >= self::MAX_DEPTH || empty( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
				$out[] = $element;
				continue;
			}
			$collapsible         = self::is_wrapper( $element ) && self::only_markers( $element['elements'] );
			$children            = self::transform( $element['elements'], $replace, $changed, $depth + 1 );
			if ( $collapsible && ! self::contains_markers( $children ) ) {
				foreach ( $children as $child ) {
					$out[] = $child;
				}
				continue;
			}
			$element['elements'] = $children;
			$out[]               = $element;
		}
		return $out;
	}

	private static function wrap_list( array $elements, ?string $parent, bool $inner, string $source, array $env, bool $sections, int $depth ): array {
		$out = array();
		foreach ( $elements as $key => $element ) {
			if ( ! is_array( $element ) ) {
				$out[ $key ] = $element;
				continue;
			}
			$reason = self::reason( $element, $source, $env );
			if ( '' === $reason ) {
				if ( $depth < self::MAX_DEPTH && ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$element['elements'] = self::wrap_list( $element['elements'], (string) $element['elType'], ! empty( $element['isInner'] ), $source, $env, $sections, $depth + 1 );
				}
				$out[ $key ] = $element;
				continue;
			}
			$out[ $key ] = self::place( self::marker( $element, $reason, $source ), $parent, $inner, $env, $sections );
		}
		return $out;
	}

	/** Motivo para envolver un elemento, o '' si se puede instanciar. */
	private static function reason( array $element, string $source, array $env ): string {
		$el_type     = isset( $element['elType'] ) ? $element['elType'] : null;
		$widget_type = isset( $element['widgetType'] ) ? $element['widgetType'] : null;
		if ( 'widget' === $el_type && Module::WIDGET === $widget_type ) {
			return '';
		}
		if ( self::SOURCE_LIBRARY !== $source && 'widget' === $el_type && self::COMPONENT_TYPE === $widget_type && empty( $env['components'] ) ) {
			return self::REASON_COMPONENT;
		}
		if ( ! is_string( $el_type ) || '' === $el_type ) {
			return self::REASON_UNKNOWN;
		}
		if ( 'widget' === $el_type && ( ! is_string( $widget_type ) || '' === $widget_type ) ) {
			// Sin `widgetType`, el núcleo pediría la lista entera de widgets como si fuera uno: se conserva aparte.
			return self::REASON_UNKNOWN;
		}
		return call_user_func( $env['known'], $el_type, 'widget' === $el_type ? $widget_type : null ) ? '' : self::REASON_UNKNOWN;
	}

	/** Coloca un marcador donde el núcleo admite un widget (ver `wrap()`). */
	private static function place( array $marker, ?string $parent, bool $inner, array $env, bool $sections ): array {
		$seed = (string) $marker['id'];
		if ( null === $parent ) {
			if ( $sections || empty( $env['container'] ) ) {
				return self::wrapper( 'section', array( self::wrapper( 'column', array( $marker ), $seed, false ) ), $seed, false );
			}
			return self::wrapper( 'container', array( $marker ), $seed, false );
		}
		if ( 'section' === $parent ) {
			return self::wrapper( 'column', array( $marker ), $seed, $inner );
		}
		if ( 'widget' === $parent && ! empty( $env['container'] ) ) {
			return self::wrapper( 'container', array( $marker ), $seed, true );
		}
		return $marker;
	}

	private static function wrapper( string $type, array $children, string $seed, bool $inner ): array {
		$settings = array( self::WRAPPER_SETTING => 1 );
		if ( 'column' === $type ) {
			$settings['_column_size'] = 100;
		}
		return array(
			'id'       => self::derived_id( $seed, $type ),
			'elType'   => $type,
			'settings' => $settings,
			'elements' => $children,
			'isInner'  => $inner,
		);
	}

	/** ID de 7 hex estable: el mismo elemento recibe siempre el mismo envoltorio. */
	private static function derived_id( string $seed, string $salt ): string {
		return substr( sha1( $seed . '|' . $salt ), 0, 7 );
	}

	private static function fingerprint( array $element ): string {
		$json = json_encode( $element, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE );
		return is_string( $json ) ? $json : serialize( $element ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	private static function uses_sections( array $elements ): bool {
		foreach ( $elements as $element ) {
			if ( is_array( $element ) && isset( $element['elType'] ) && 'section' === $element['elType'] ) {
				return true;
			}
		}
		return false;
	}

	private static function env( array $env ): array {
		if ( ! isset( $env['known'] ) || ! is_callable( $env['known'] ) ) {
			$env['known'] = array( __CLASS__, 'is_registered' );
		}
		if ( ! isset( $env['container'] ) ) {
			$env['container'] = self::is_registered( 'container' );
		}
		if ( ! isset( $env['components'] ) ) {
			$env['components'] = self::components_supported();
		}
		return $env;
	}

	private static function collect( array $elements, array &$found, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( self::is_marker( $element ) ) {
				$info    = self::info( $element );
				$found[] = array(
					'id'     => isset( $element['id'] ) ? (string) $element['id'] : '',
					'type'   => null === $info ? '' : $info['type'],
					'reason' => null === $info ? self::REASON_UNKNOWN : $info['reason'],
					'source' => null === $info ? self::SOURCE_KIT : $info['source'],
					'why'    => null === $info ? 'unknown' : self::why( $info['element'], $info['reason'] ),
				);
				continue;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect( $element['elements'], $found, $depth + 1 );
			}
		}
	}

	/** `tipo` => `motivo` => número. */
	private static function summarize( array $found ): array {
		$summary = array();
		foreach ( $found as $item ) {
			$summary[ $item['type'] ][ $item['reason'] ] = ( isset( $summary[ $item['type'] ][ $item['reason'] ] ) ? $summary[ $item['type'] ][ $item['reason'] ] : 0 ) + 1;
		}
		return $summary;
	}

	private static function document_id( $document ): int {
		return is_object( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
	}

	/** @return object|null */
	private static function widgets_manager() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}
		return \Elementor\Plugin::$instance->widgets_manager;
	}

	/** @return object|null */
	private static function elements_manager() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->elements_manager ) ) {
			return null;
		}
		return \Elementor\Plugin::$instance->elements_manager;
	}
}
