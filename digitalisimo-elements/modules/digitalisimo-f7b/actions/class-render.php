<?php
namespace Digitalisimo\Elements\F7b\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Marcado de las acciones con destino y de «Oculto al cargar» (F7b, frente B; V.3, C2-2, C2-3, C2-5, C2-6, C1-6).
 *
 * Todo se decide con los ajustes guardados del elemento y su propio HTML: es determinista y entra en la caché de
 * elementos del documento (`elementor/core/base/document.php:1834-1880`). Ninguna acción hace dinámico el elemento
 * (C1-21).
 *
 * - `elementor/frontend/before_render` (prioridad 5): sanea las filas con las listas cerradas de `Rules` (destinos del
 *   mismo documento), encola la hoja `e-animation-<nombre>` de cada animación (la caché del documento guarda los
 *   handles encolados al pintar, `document.php:1873-1874`), pide los paquetes de PRO y el popup, y abre un búfer.
 * - `elementor/frontend/after_render` (prioridad 15): cierra el búfer y retoca sólo la etiqueta de apertura del
 *   envoltorio (la que lleva `data-id="<id>"`): `data-digi-actions` con las filas, `role="button" tabindex="0"` si el
 *   elemento no tiene descendientes interactivos, y `hidden`; el `<noscript>` de «Oculto al cargar» va como último
 *   hijo del envoltorio. Los búferes se abren antes (5) y se cierran después (15) que los del enlace del elemento (10),
 *   así que el enlace superpuesto cuenta como descendiente.
 * - Regla de `click` (C2-3): con un único `a` o `button` dentro, el disparador es ese control (si es un `a` sin `href`
 *   ni `tabindex`, recibe `tabindex="0"` y, si no tiene, `role="button"`); sin ninguno, el envoltorio se convierte en
 *   botón; con varios, o con un único control que no es enlace ni botón, las filas `click` no se registran.
 * - `elementor/frontend/builder_content_data`: en cada documento que se imprime (también desde la caché de elementos,
 *   donde no se vuelve a pintar ningún elemento) recoge las filas de popup y panel lateral y llama a
 *   `Pro_Gate::require( 'f7b-action' )` y a `Popup\Module::add_popup_to_location()` con cada popup publicado.
 * - `elementor/element/parse_css`: `Hidden_Rule::add()` en el CSS del documento con «Oculto al cargar» o con una
 *   acción que muestra u oculta (C2-2).
 *
 * En el editor y en la vista previa no cambia nada: el elemento se ve siempre y las acciones no corren.
 */
final class Render {
	const OPEN  = 5;
	const CLOSE = 15;

	/** `display` del `<noscript>` de cada tipo: `var(--display)` en contenedores, `flex` en columnas, `block` en el resto. */
	const DISPLAY = array(
		'container' => 'var(--display)',
		'column'    => 'flex',
	);

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $actions = false;

	/** @var bool */
	private static $hidden = false;

	/** @var array[] Pila de elementos con búfer abierto. */
	private static $frames = array();

	/** @var array<int,array|null> IDs de los elementos de cada documento (claves). */
	private static $ids = array();

	/** @var array<int,bool> Documentos ya recorridos en `content_data()`. */
	private static $seen = array();

	/** @var bool|null Prueba: fuerza el modo editor. */
	public static $editing = null;

	/**
	 * @param bool $actions Algún documento usa acciones (o se edita).
	 * @param bool $hidden  Algún documento usa «Oculto al cargar» (o se edita).
	 */
	public static function init( bool $actions, bool $hidden ): void {
		if ( self::$initialized || ( ! $actions && ! $hidden ) ) {
			return;
		}
		self::$initialized = true;
		self::$actions     = $actions;
		self::$hidden      = $hidden;
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ), self::OPEN );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ), self::CLOSE );
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'parse_css' ), 10, 2 );
		if ( $actions ) {
			add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'content_data' ), 10, 2 );
		}
	}

	/**
	 * Estado por petición (pruebas).
	 *
	 * @param bool $hooks También el arranque (otra vez `init()`).
	 */
	public static function reset( bool $hooks = false ): void {
		self::$frames = array();
		self::$ids    = array();
		self::$seen   = array();
		if ( $hooks ) {
			self::$initialized = false;
		}
	}

	/**
	 * @param object $element Elemento que se va a pintar.
	 */
	public static function before_render( $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_id' ) || self::editing() ) {
			return;
		}
		$settings = self::settings( $element );
		$on       = self::$actions && 'yes' === ( $settings[ Controls::ON ] ?? '' );
		$hidden   = self::$hidden && 'yes' === ( $settings[ Controls::HIDDEN ] ?? '' );
		if ( ! $on && ! $hidden ) {
			return;
		}
		$id   = (string) $element->get_id();
		$rows = $on ? Rules::rows( $settings[ Controls::LIST ] ?? array(), $id, self::document_ids() ) : array();
		if ( ! $rows && ! $hidden ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( isset( $row['m'] ) ) {
				self::enqueue_animation( $row['m'] );
			}
		}
		self::require_pro( $rows );
		self::$frames[] = array(
			'object' => spl_object_id( $element ),
			'id'     => $id,
			'type'   => method_exists( $element, 'get_type' ) ? (string) $element->get_type() : '',
			'rows'   => $rows,
			'hidden' => $hidden,
		);
		ob_start();
	}

	/**
	 * @param object $element Elemento pintado.
	 */
	public static function after_render( $element ): void {
		if ( ! self::$frames || ! is_object( $element ) ) {
			return;
		}
		$frame = end( self::$frames );
		if ( spl_object_id( $element ) !== $frame['object'] ) {
			return;
		}
		array_pop( self::$frames );
		$html = (string) ob_get_clean();
		echo self::process( $html, $frame ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del elemento con atributos escapados.
	}

	/**
	 * Retoca el HTML pintado de un elemento. Pura: la prueban las pruebas con HTML real.
	 *
	 * @param string $html  HTML del elemento (envoltorio y contenido).
	 * @param array  $frame `id`, `type`, `rows` (filas de `Rules::rows()`) y `hidden`.
	 */
	public static function process( string $html, array $frame ): string {
		$id  = (string) ( $frame['id'] ?? '' );
		$pos = '' === $id ? false : strpos( $html, 'data-id="' . $id . '"' );
		if ( false === $pos ) {
			return $html;
		}
		$start = strrpos( substr( $html, 0, $pos ), '<' );
		$end   = strpos( $html, '>', $pos );
		if ( false === $start || false === $end || ! preg_match( '/^<([a-zA-Z][a-zA-Z0-9-]*)/', substr( $html, $start, 40 ), $m ) ) {
			return $html;
		}
		$tag   = strtolower( $m[1] );
		$open  = substr( $html, $start, $end - $start );
		$close = strrpos( $html, '</' . $tag );
		if ( false === $close || $close < $end ) {
			$close = strlen( $html );
		}
		$inner = substr( $html, $end + 1, $close - $end - 1 );
		$add   = '';
		$rows  = isset( $frame['rows'] ) && is_array( $frame['rows'] ) ? $frame['rows'] : array();
		if ( $rows ) {
			$mode = self::mode( $tag, $open, $inner );
			if ( 'none' === $mode['mode'] ) {
				$rows = array_values(
					array_filter(
						$rows,
						function ( $row ) {
							return 'click' !== $row['t'];
						}
					)
				);
			}
			$wants = false;
			foreach ( $rows as $row ) {
				$wants = $wants || in_array( $row['t'], array( 'click', 'hover' ), true );
			}
			if ( $wants && 'wrapper' === $mode['mode'] ) {
				$add .= ' role="button" tabindex="0"';
			}
			if ( $wants && 'inner' === $mode['mode'] && '' !== $mode['patch'] ) {
				$at    = $mode['offset'] + 2; // Tras «<a».
				$inner = substr( $inner, 0, $at ) . $mode['patch'] . substr( $inner, $at );
			}
			if ( $rows ) {
				$add .= ' data-digi-actions="' . self::attr( self::json( $rows ) ) . '"';
			}
		}
		$tail = '';
		if ( ! empty( $frame['hidden'] ) && ! preg_match( '/\shidden(?=[\s=>\/]|$)/i', $open ) ) {
			$add     .= ' hidden';
			$display  = self::DISPLAY[ (string) ( $frame['type'] ?? '' ) ] ?? 'block';
			$tail     = '<noscript><style>.elementor-element.elementor-element-' . $id . '[hidden]{display:' . $display . '!important}</style></noscript>';
		}
		if ( '' === $add && '' === $tail ) {
			return $html;
		}
		return substr( $html, 0, $end ) . $add . '>' . $inner . $tail . substr( $html, $close );
	}

	/**
	 * Cómo se dispara `click` en este elemento.
	 *
	 * @return array{mode:string,offset:int,patch:string} `self` (el envoltorio es un enlace o un botón), `wrapper`
	 *                                                    (sin descendientes interactivos: se convierte en botón),
	 *                                                    `inner` (un único `a` o `button`) o `none`.
	 */
	public static function mode( string $tag, string $open, string $inner ): array {
		$out = array(
			'mode'   => 'none',
			'offset' => -1,
			'patch'  => '',
		);
		if ( 'button' === $tag || ( 'a' === $tag && preg_match( '/\shref\s*=/i', $open ) ) ) {
			$out['mode'] = 'self';
			return $out;
		}
		$found = Rules::analyze( $inner );
		if ( 0 === $found['count'] ) {
			// Un envoltorio con su propio `role` o `tabindex` (atributos personalizados) no se convierte.
			$out['mode'] = preg_match( '/\s(?:role|tabindex)\s*=/i', $open ) ? 'none' : 'wrapper';
			return $out;
		}
		if ( 1 !== $found['count'] || ! in_array( $found['tag'], array( 'a', 'button' ), true ) ) {
			return $out;
		}
		$out['mode']   = 'inner';
		$out['offset'] = $found['offset'];
		$attrs         = $found['attrs'];
		if ( 'a' === $found['tag'] && ! preg_match( '/\shref\s*=/i', $attrs ) && ! preg_match( '/\stabindex\s*=/i', $attrs ) ) {
			$out['patch'] = ' tabindex="0"' . ( preg_match( '/\srole\s*=/i', $attrs ) ? '' : ' role="button"' );
		}
		return $out;
	}

	/**
	 * `elementor/frontend/builder_content_data`: popups y paneles de las filas del documento, en cada impresión.
	 *
	 * @param mixed $data    Elementos del documento.
	 * @param mixed $post_id Documento.
	 * @return mixed Los mismos datos.
	 */
	public static function content_data( $data, $post_id = 0 ) {
		if ( ! is_array( $data ) || self::editing() ) {
			return $data;
		}
		$post_id = (int) $post_id;
		if ( $post_id > 0 && isset( self::$seen[ $post_id ] ) ) {
			return $data;
		}
		if ( $post_id > 0 ) {
			self::$seen[ $post_id ] = true;
		}
		try {
			$rows = array();
			self::collect( $data, $rows, 0 );
			self::require_pro( $rows );
		} catch ( \Throwable $e ) {
			// Ante cualquier duda, los paquetes de PRO se imprimen como siempre.
			self::gate( 'f7b-action' );
		}
		return $data;
	}

	/**
	 * `elementor/element/parse_css`: la regla `[hidden]` en el CSS del documento que la necesita.
	 *
	 * @param object $post_css CSS del documento.
	 * @param object $element  Elemento.
	 */
	public static function parse_css( $post_css, $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_id' ) ) {
			return;
		}
		$settings = self::settings( $element );
		$needs    = self::$hidden && 'yes' === ( $settings[ Controls::HIDDEN ] ?? '' );
		if ( ! $needs && self::$actions && 'yes' === ( $settings[ Controls::ON ] ?? '' ) ) {
			$needs = Rules::needs_hidden_rule( Rules::rows( $settings[ Controls::LIST ] ?? array(), (string) $element->get_id() ) );
		}
		if ( $needs && class_exists( '\Digitalisimo\Elements\F7b\Hidden_Rule' ) ) {
			\Digitalisimo\Elements\F7b\Hidden_Rule::add( $post_css );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------

	/** Filas de popup y panel lateral de un árbol de elementos guardado (sin comprobar destinos: sólo PRO). */
	private static function collect( array $elements, array &$rows, int $depth ): void {
		if ( $depth > 64 ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( 'yes' === ( $settings[ Controls::ON ] ?? '' ) && ! empty( $settings[ Controls::LIST ] ) ) {
				$id = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
				foreach ( Rules::rows( $settings[ Controls::LIST ], $id ) as $row ) {
					if ( in_array( $row['a'], Rules::PRO, true ) ) {
						$rows[] = $row;
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect( $element['elements'], $rows, $depth + 1 );
			}
		}
	}

	/** Popups y off-canvas: paquetes de PRO y popup en su ubicación (C1-6). */
	private static function require_pro( array $rows ): void {
		if ( ! Rules::needs_pro( $rows ) ) {
			return;
		}
		self::gate( 'f7b-action' );
		foreach ( $rows as $row ) {
			if ( 'popup_open' === $row['a'] && ! empty( $row['p'] ) && self::is_popup( (int) $row['p'] ) ) {
				self::add_popup( (int) $row['p'] );
			}
		}
	}

	private static function gate( string $reason ): void {
		if ( class_exists( '\Digitalisimo\Elements\Front\Pro_Gate' ) ) {
			\Digitalisimo\Elements\Front\Pro_Gate::require( $reason );
		}
	}

	/** Popup publicado de la biblioteca: nunca otro documento en la ubicación de popups. */
	private static function is_popup( int $id ): bool {
		if ( $id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $id ) ) {
			return false;
		}
		return 'popup' === get_post_meta( $id, '_elementor_template_type', true );
	}

	private static function add_popup( int $id ): void {
		$popup = '\ElementorPro\Modules\Popup\Module';
		if ( ! class_exists( $popup ) || ! method_exists( $popup, 'add_popup_to_location' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
			return;
		}
		$pro = \ElementorPro\Plugin::instance();
		if ( ! is_object( $pro ) || empty( $pro->modules_manager ) || ! $pro->modules_manager->get_modules( 'theme-builder' ) ) {
			return;
		}
		$popup::add_popup_to_location( $id );
	}

	/** Hoja de una animación de entrada del núcleo, si está registrada. */
	private static function enqueue_animation( string $name ): void {
		$handle = 'e-animation-' . $name;
		if ( function_exists( 'wp_style_is' ) && wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
		}
	}

	/** IDs de los elementos del documento que se está pintando (memorizados por documento), o `null`. */
	private static function document_ids(): ?array {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->documents ) ) {
			return null;
		}
		$document = \Elementor\Plugin::$instance->documents->get_current();
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! method_exists( $document, 'get_elements_data' ) ) {
			return null;
		}
		$key = (int) ( method_exists( $document, 'get_post' ) && is_object( $document->get_post() ) ? $document->get_post()->ID : $document->get_main_id() );
		if ( ! array_key_exists( $key, self::$ids ) ) {
			$ids  = array();
			$data = $document->get_elements_data();
			self::walk_ids( is_array( $data ) ? $data : array(), $ids, 0 );
			self::$ids[ $key ] = $ids ? $ids : null;
		}
		return self::$ids[ $key ];
	}

	private static function walk_ids( array $elements, array &$ids, int $depth ): void {
		if ( $depth > 64 ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( is_array( $element ) && isset( $element['id'] ) && is_scalar( $element['id'] ) ) {
				$ids[ (string) $element['id'] ] = true;
				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					self::walk_ids( $element['elements'], $ids, $depth + 1 );
				}
			}
		}
	}

	/** Ajustes del elemento con los valores por defecto del repetidor (el editor no guarda los valores por defecto). */
	private static function settings( $element ): array {
		$settings = method_exists( $element, 'get_settings' ) ? $element->get_settings() : array();
		return is_array( $settings ) ? $settings : array();
	}

	private static function editing(): bool {
		if ( null !== self::$editing ) {
			return self::$editing;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	private static function json( array $rows ): string {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $rows ) : json_encode( $rows );
		return is_string( $json ) ? $json : '[]';
	}

	private static function attr( string $value ): string {
		return function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
