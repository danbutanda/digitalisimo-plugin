<?php
namespace Digitalisimo\Elements\F7b\Overrides;

defined( 'ABSPATH' ) || exit;

/**
 * Aplicación de las sobrescrituras al pintar una instancia del widget Plantilla (F7b, frente E; V.2, C1-1, C1-3).
 *
 * Gancho permanente: sólo `elementor/widget/before_render_content` (`elementor/includes/base/widget-base.php:628`),
 * que corre antes de `render()` del Plantilla. Si la instancia tiene filas válidas y su plantilla está publicada
 * (la misma condición con la que `render()` pinta, `modules/library/widgets/template.php:87-102`), se apila un marco
 * `{ widget, template, rows }` y, mientras haya marcos, se enganchan:
 *
 * - `elementor/frontend/builder_content_data` (`elementor/includes/frontend.php:1173`): para el `$post_id` de un marco
 *   aún sin aplicar (el más interno), devuelve los datos con `Apply::apply()`. Otra plantilla, u otra carga de la
 *   misma plantilla, recibe sus datos intactos.
 * - `pre_option_elementor_element_cache_ttl` → `'disable'` mientras el documento en curso
 *   (`documents->get_current()`, que `get_builder_content()` cambia a la plantilla antes de pintarla) sea la plantilla
 *   de un marco aplicado: `Document::print_elements()` toma entonces la rama sin caché
 *   (`elementor/core/base/document.php:1835-1848`), que ni lee ni escribe `_elementor_element_cache` de esa plantilla.
 *   La caché es por documento (`document.php:1850-1880`, 1963-1995): sin esto, la instancia leería el HTML sin
 *   sobrescribir o dejaría el suyo a las demás. Las otras plantillas, y la página de fuera, siguen con su caché: el
 *   Plantilla es dinámico (`Element_Base::is_dynamic_content()`, `element-base.php:308`) y la página lo guarda como
 *   shortcode que se pinta en cada visita.
 * - Retirada al terminar la instancia: en `elementor/frontend/widget/after_render` de ese widget
 *   (`element-base.php:572`) y, antes, en `elementor/widget/render_content` (`widget-base.php:663`), que es lo único
 *   que llega cuando el editor pinta con `render_content()` sin `print_element()` (`document.php:387-403`,
 *   `widget-base.php:783-799`). Con la pila vacía se quitan los cuatro ganchos.
 *
 * Una instancia sin filas no apila nada: pinta como hoy, con la caché de su plantilla. Sin sufijos en los IDs (C1-7).
 * También se engancha `elementor/markdown/element_output` (experimento «markdown_rendering», inactivo por defecto):
 * el Markdown del Plantilla (`template.php:113-127`) no pasa por `builder_content_data`, así que se rehace con los datos
 * sobrescritos para que no muestre lo ocultado.
 */
final class Render {
	const WIDGET = 'template';

	const HOOK_DATA    = 'elementor/frontend/builder_content_data';
	const HOOK_CACHE   = 'pre_option_elementor_element_cache_ttl';
	const HOOK_AFTER   = 'elementor/frontend/widget/after_render';
	const HOOK_CONTENT = 'elementor/widget/render_content';

	/** Tipo de entrada de las plantillas de la biblioteca. */
	const LIBRARY = 'elementor_library';

	/** @var bool */
	private static $initialized = false;

	/** @var array<int,array{widget:object,template:int,rows:array,applied:bool}> Instancias en curso, la última encima. */
	private static $frames = array();

	/** @var bool */
	private static $hooked = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/widget/before_render_content', array( __CLASS__, 'before' ), 10, 2 );
		add_filter( 'elementor/markdown/element_output', array( __CLASS__, 'markdown' ), 10, 3 );
	}

	/**
	 * `elementor/widget/before_render_content`: apila la instancia del Plantilla si tiene sobrescrituras.
	 *
	 * @param mixed $widget Widget que se va a pintar.
	 * @param mixed $args   `[ 'mode' => 'normal'|'markdown' ]`.
	 */
	public static function before( $widget, $args = array() ): void {
		if ( ! self::is_template( $widget ) ) {
			return;
		}
		if ( is_array( $args ) && isset( $args['mode'] ) && 'normal' !== $args['mode'] ) {
			return;
		}
		$template = self::template_id( $widget );
		if ( ! $template ) {
			return;
		}
		$rows = Apply::rows( self::raw_rows( $widget ) );
		if ( ! $rows ) {
			return;
		}
		self::$frames[] = array(
			'widget'   => $widget,
			'template' => $template,
			'rows'     => $rows,
			'applied'  => false,
		);
		self::hook();
	}

	/**
	 * `elementor/frontend/builder_content_data`: datos sobrescritos de la plantilla de la instancia en curso.
	 *
	 * @param mixed $data    Datos de elementos.
	 * @param mixed $post_id Documento que se va a pintar.
	 * @return mixed
	 */
	public static function data( $data, $post_id = 0 ) {
		$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
		for ( $i = count( self::$frames ) - 1; $i >= 0; $i-- ) {
			if ( self::$frames[ $i ]['applied'] || self::$frames[ $i ]['template'] !== $post_id ) {
				continue;
			}
			self::$frames[ $i ]['applied'] = true;
			return is_array( $data ) ? Apply::apply( $data, self::$frames[ $i ]['rows'] ) : $data;
		}
		return $data;
	}

	/**
	 * `pre_option_elementor_element_cache_ttl`: `'disable'` mientras se pinta la plantilla de un marco aplicado.
	 *
	 * @param mixed $pre Valor previo del filtro (`false` = leer la opción).
	 * @return mixed
	 */
	public static function cache_ttl( $pre ) {
		$current = self::current_document();
		if ( ! $current ) {
			return $pre;
		}
		foreach ( self::$frames as $frame ) {
			if ( $frame['applied'] && $frame['template'] === $current ) {
				return 'disable';
			}
		}
		return $pre;
	}

	/**
	 * `elementor/frontend/widget/after_render`: fin de la instancia.
	 *
	 * @param mixed $element Elemento pintado.
	 */
	public static function after( $element ): void {
		self::pop( $element );
	}

	/**
	 * `elementor/widget/render_content`: fin de la instancia también cuando el editor pinta sin `print_element()`.
	 *
	 * @param mixed $content HTML del widget (no se cambia).
	 * @param mixed $widget  Widget pintado.
	 * @return mixed
	 */
	public static function content( $content, $widget = null ) {
		self::pop( $widget );
		return $content;
	}

	/**
	 * `elementor/markdown/element_output`: Markdown del Plantilla con sus sobrescrituras.
	 *
	 * @param mixed $markdown Markdown del elemento.
	 * @param mixed $element  Instancia.
	 * @param mixed $data     Datos del elemento.
	 * @return mixed
	 */
	public static function markdown( $markdown, $element = null, $data = null ) {
		$renderer = '\\Elementor\\Modules\\MarkdownRender\\Markdown_Renderer';
		if ( ! self::is_template( $element ) || ! class_exists( $renderer ) || ! class_exists( '\\Elementor\\Plugin', false ) ) {
			return $markdown;
		}
		$template = self::template_id( $element );
		$rows     = $template ? Apply::rows( self::raw_rows( $element ) ) : array();
		if ( ! $rows ) {
			return $markdown;
		}
		$plugin   = \Elementor\Plugin::$instance;
		$document = isset( $plugin->documents ) ? $plugin->documents->get( $template ) : null;
		if ( ! $document || ! method_exists( $document, 'get_elements_data' ) ) {
			return $markdown;
		}
		$elements = $document->get_elements_data();
		return ( new $renderer() )->render_elements_data( Apply::apply( is_array( $elements ) ? $elements : array(), $rows ) );
	}

	/** Instancias en curso (pruebas y diagnóstico). */
	public static function frames(): array {
		return self::$frames;
	}

	// -----------------------------------------------------------------------------------------------------------------

	private static function is_template( $widget ): bool {
		return is_object( $widget ) && method_exists( $widget, 'get_name' ) && self::WIDGET === $widget->get_name();
	}

	/** Plantilla de la instancia si se va a pintar: publicada y de la biblioteca. */
	private static function template_id( $widget ): int {
		$id = method_exists( $widget, 'get_settings' ) ? $widget->get_settings( 'template_id' ) : 0;
		$id = is_scalar( $id ) ? absint( $id ) : 0;
		if ( ! $id || 'publish' !== get_post_status( $id ) || self::LIBRARY !== get_post_type( $id ) ) {
			return 0;
		}
		return $id;
	}

	/**
	 * Filas guardadas tal cual: los ajustes crudos de la instancia, sin depender de que el control esté registrado
	 * (en el front no lo está).
	 */
	private static function raw_rows( $widget ) {
		$settings = method_exists( $widget, 'get_data' ) ? $widget->get_data( 'settings' ) : array();
		return is_array( $settings ) && isset( $settings[ Apply::REPEATER ] ) ? $settings[ Apply::REPEATER ] : array();
	}

	private static function current_document(): int {
		if ( ! class_exists( '\\Elementor\\Plugin', false ) ) {
			return 0;
		}
		$plugin   = \Elementor\Plugin::$instance;
		$document = is_object( $plugin ) && isset( $plugin->documents ) && method_exists( $plugin->documents, 'get_current' ) ? $plugin->documents->get_current() : null;
		return is_object( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
	}

	private static function pop( $widget ): void {
		if ( ! is_object( $widget ) ) {
			return;
		}
		for ( $i = count( self::$frames ) - 1; $i >= 0; $i-- ) {
			if ( self::$frames[ $i ]['widget'] === $widget ) {
				array_splice( self::$frames, $i, 1 );
				break;
			}
		}
		if ( ! self::$frames ) {
			self::unhook();
		}
	}

	private static function hook(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_filter( self::HOOK_DATA, array( __CLASS__, 'data' ), 10, 2 );
		add_filter( self::HOOK_CACHE, array( __CLASS__, 'cache_ttl' ), 10, 1 );
		add_action( self::HOOK_AFTER, array( __CLASS__, 'after' ), 10, 1 );
		add_filter( self::HOOK_CONTENT, array( __CLASS__, 'content' ), 10, 2 );
	}

	private static function unhook(): void {
		if ( ! self::$hooked ) {
			return;
		}
		self::$hooked = false;
		remove_filter( self::HOOK_DATA, array( __CLASS__, 'data' ), 10 );
		remove_filter( self::HOOK_CACHE, array( __CLASS__, 'cache_ttl' ), 10 );
		remove_action( self::HOOK_AFTER, array( __CLASS__, 'after' ), 10 );
		remove_filter( self::HOOK_CONTENT, array( __CLASS__, 'content' ), 10 );
	}
}
