<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Enlaces firmados de `create-preview-link` (`?elementor_preview_token=…`, `Public_Preview_Handler` del núcleo): pintan
 * una instantánea (una revisión) de la página como si fuera la publicada, con sus hojas `frontend` y su caché HTML.
 *
 * Sin cuidado, esa petición escribe en la página: si sus hojas estaban inválidas las regenera con el CSS de la
 * instantánea (el archivo `local-<id>-frontend-*.css` que enlaza el HTML ya entregado o guardado por una caché de
 * página) y guarda el HTML de la instantánea 24 h en `_elementor_element_cache`. Aquí, durante una petición con un token
 * válido (firma, vigencia y revisión de esa página, comprobados con `Preview_Token` del núcleo):
 * - la caché HTML del documento se apaga (`pre_option_elementor_element_cache_ttl` → `disable`): ni se lee ni se guarda;
 * - si la página está publicada (o privada), la petición no escribe nada que vean otros: las opciones de validez de la
 *   caché atómica (`elementor_atomic_cache_validity__*`: estilos locales y globales, relaciones de clases y de
 *   componentes) se leen y se escriben sólo en memoria (`pre_option_…`, `pre_update_option_…`,
 *   `pre_update_site_option_…`). En esa copia el contexto `frontend` de la página queda válido y cada hoja suya válida
 *   sin archivo: el núcleo no sirve ni regenera ninguna hoja `local-<id>-frontend-*` ni `global-<id>-frontend-*`, y la
 *   lista de componentes de la instantánea se calcula ahí. El CSS completo de la instantánea (sus clases globales y las
 *   de lo que incrusta, y sus estilos locales) va en línea, partido por punto como en las hojas del núcleo y en su
 *   posición (escritorio y después de mayor a menor; en cada punto, globales antes que locales). Un visitante que llega
 *   mientras tanto lee la base de datos tal cual (no hay ventana) y, al terminar, la copia se descarta;
 * - y tampoco se escribe ninguna meta `_elementor_*` de la página (`_elementor_css`, `_elementor_page_assets`, el uso de
 *   clases globales…: `add/update/delete_post_metadata`), ni su `post-<id>.css`: su `_elementor_css` se lee `empty`, así
 *   `Post_CSS` del núcleo no la sirve ni la regenera, y el CSS de página de la instantánea (ajustes de la página y
 *   estilos clásicos) va en línea donde el núcleo encola esa hoja (`Snapshot_Post_Css`, en memoria, como el
 *   `Post_Preview` del editor); sus assets se calculan con la instantánea sin guardarse;
 * - si es un borrador (nadie guarda su HTML), sus hojas y su lista de componentes se invalidan antes y después: la
 *   instantánea se pinta con su CSS y la siguiente visita lo regenera.
 */
final class Preview_Link {
	const CONTEXT     = 'frontend';
	const HANDLE      = 'digitalisimo-mcp-preview';
	const TTL_OPTION  = 'elementor_element_cache_ttl';
	const LIVE        = array( 'publish', 'private' );
	const TOKEN_CLASS = 'Elementor\\Modules\\Mcp\\Preview\\Preview_Token';
	/** Prioridad de `parse_request`: después del manejador de enlaces firmados del núcleo (1). */
	const PRIORITY    = 2;
	/** Metas de la página que el render del núcleo escribe como caché (CSS de página, assets, HTML, uso de clases). */
	const META_PREFIX = '_elementor_';
	/** Raíces de las hojas atómicas del núcleo en el orden en que se registran (`base` 10, `default` 15, `global` 20, `local` 30). */
	const RANKS = array(
		'base'    => 0,
		'default' => 1,
		'global'  => 2,
		'local'   => 3,
	);

	/** @var bool */
	private static $initialized = false;

	/** @var int Página del enlace firmado de esta petición. */
	private static $post = 0;

	/** @var bool ¿Estaba publicada al abrir el enlace? */
	private static $live = false;

	/** @var array<string, mixed> Opciones de validez en memoria (nombre => valor) durante el enlace de una publicada. */
	private static $memory = array();

	/** @var bool */
	private static $printed = false;

	/** @var bool */
	private static $post_printed = false;

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'parse_request', array( __CLASS__, 'request' ), self::PRIORITY );
		add_action( 'shutdown', array( __CLASS__, 'shutdown' ), 0 );
	}

	/** `parse_request` 2 (el manejador del núcleo va en 1), antes de la consulta y del render. */
	public static function request( $wp = null ): void {
		$arg = Css_Sync::key( 'token' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el enlace lleva su propio token firmado.
		$raw = isset( $_GET[ $arg ] ) && is_string( $_GET[ $arg ] ) ? sanitize_text_field( wp_unslash( $_GET[ $arg ] ) ) : '';
		if ( '' === $raw || self::$post ) {
			return;
		}
		$id = self::post_for( $raw );
		if ( $id <= 0 ) {
			return;
		}
		self::$post = $id;
		self::$live = in_array( get_post_status( $id ), self::LIVE, true );
		add_filter( 'pre_option_' . self::TTL_OPTION, array( __CLASS__, 'no_html_cache' ) );
		if ( self::$live ) {
			self::sandbox( true );
			self::freeze( $id );
			self::guard( true );
			add_action( 'elementor/frontend/after_enqueue_post_styles', array( __CLASS__, 'print_post_css' ), 5 );
			add_action( 'elementor/frontend/after_enqueue_post_styles', array( __CLASS__, 'print_styles' ), 20 );
		} else {
			Css_Sync::invalidate_related( $id );
			Css_Sync::invalidate_styles( $id );
		}
	}

	/**
	 * Las opciones de `Cache_Validity` que escribe el render de una página: sus estilos locales y globales, las
	 * relaciones de sus clases globales con lo que incrusta y su lista de componentes.
	 */
	public static function sandboxed(): array {
		$prefix = Css_Sync::key( 'validity' );
		$global = Css_Sync::key( 'global' );
		return array(
			$prefix . Css_Sync::key( 'local' ),
			$prefix . $global,
			$prefix . $global . '_' . Css_Sync::key( 'global_related' ),
			$prefix . $global . '_' . Css_Sync::key( 'global_related_reverse' ),
			$prefix . Css_Sync::key( 'related' ),
		);
	}

	/** `pre_option_<opción>`: durante el enlace firmado de una publicada, la copia en memoria. */
	public static function read( $pre, $option = '' ) {
		return is_string( $option ) && array_key_exists( $option, self::$memory ) ? self::$memory[ $option ] : $pre;
	}

	/** `pre_update_option_<opción>`: lo nuevo va a la copia en memoria y a la base vuelve lo anterior (nada se guarda). */
	public static function write( $value, $old_value = null, $option = '' ) {
		if ( ! is_string( $option ) || ! array_key_exists( $option, self::$memory ) ) {
			return $value;
		}
		self::$memory[ $option ] = $value;
		return $old_value;
	}

	/** `pre_update_site_option_<opción>`: tampoco como opción de red. */
	public static function keep( $value, $old_value = null ) {
		return $old_value;
	}

	/**
	 * `deleted_option`: una purga de todo el sitio durante la petición (la borra de verdad: no es de este enlace) vacía
	 * también la copia, y la página se vuelve a congelar en ella.
	 */
	public static function deleted( $option ): void {
		if ( ! is_string( $option ) || ! array_key_exists( $option, self::$memory ) ) {
			return;
		}
		self::$memory[ $option ] = array( 'state' => false );
		if ( self::$post && in_array( $option, array_slice( self::sandboxed(), 0, 2 ), true ) ) {
			self::freeze( self::$post );
		}
	}

	/**
	 * `get_post_metadata` 20 (después del manejador del núcleo, en 10), sólo de la página: su `_elementor_css` se lee
	 * `empty`, así `Post_CSS` del núcleo no sirve ni regenera `post-<id>.css` (lo pinta `print_post_css()`), y sus
	 * `_elementor_page_assets` vacíos, así los assets se calculan con la instantánea.
	 */
	public static function read_meta( $value, $object_id = 0, $meta_key = '' ) {
		if ( ! self::$post || ! self::$live || (int) $object_id !== self::$post ) {
			return $value;
		}
		if ( Css_Sync::key( 'css' ) === $meta_key ) {
			return array( array( 'status' => 'empty' ) );
		}
		return Css_Sync::key( 'assets' ) === $meta_key ? array( '' ) : $value;
	}

	/** `add_post_metadata` y `update_post_metadata`: ninguna meta `_elementor_*` de la página se guarda (se da por hecha). */
	public static function keep_meta( $check, $object_id = 0, $meta_key = '' ) {
		if ( null !== $check || ! self::$post || ! self::$live || (int) $object_id !== self::$post || ! is_string( $meta_key ) || 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
			return $check;
		}
		return true;
	}

	/** `delete_post_metadata`: tampoco se borra, salvo un borrado de esa meta en todas las entradas (una purga general). */
	public static function keep_meta_delete( $check, $object_id = 0, $meta_key = '', $meta_value = '', $delete_all = false ) {
		return $delete_all ? $check : self::keep_meta( $check, $object_id, $meta_key );
	}

	/**
	 * `elementor/frontend/after_enqueue_post_styles` 5, donde el núcleo encola `post-<id>.css` (justo antes): el CSS de
	 * página de la instantánea en línea (`Snapshot_Post_Css`, sin archivo ni meta), en una hoja propia que depende de
	 * `elementor-frontend` como la del núcleo.
	 */
	public static function print_post_css(): void {
		if ( ! self::$post || ! self::$live || self::$post_printed || ! function_exists( 'wp_styles' ) || ! class_exists( 'Elementor\\Core\\Files\\CSS\\Post_Local_Cache' ) ) {
			return;
		}
		self::$post_printed = true;
		require_once __DIR__ . '/class-snapshot-post-css.php';
		try {
			$handle = Snapshot_Post_Css::HANDLE_PREFIX . self::$post;
			wp_register_style( $handle, false, wp_styles()->query( 'elementor-frontend', 'registered' ) ? array( 'elementor-frontend' ) : array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( $handle );
			( new Snapshot_Post_Css( self::$post ) )->enqueue();
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/** Los filtros de las metas de la página durante el enlace firmado de una publicada, o fuera. */
	private static function guard( bool $on ): void {
		$hook = $on ? 'add_filter' : 'remove_filter';
		$hook( 'get_post_metadata', array( __CLASS__, 'read_meta' ), 20, 3 );
		$hook( 'add_post_metadata', array( __CLASS__, 'keep_meta' ), PHP_INT_MAX, 3 );
		$hook( 'update_post_metadata', array( __CLASS__, 'keep_meta' ), PHP_INT_MAX, 3 );
		$hook( 'delete_post_metadata', array( __CLASS__, 'keep_meta_delete' ), PHP_INT_MAX, 5 );
	}

	/** Copia en memoria de las opciones de validez (con lo guardado al empezar) y sus filtros, o fuera. */
	private static function sandbox( bool $on ): void {
		$options = self::sandboxed();
		if ( $on ) {
			foreach ( $options as $option ) {
				self::$memory[ $option ] = get_option( $option, array( 'state' => false ) );
			}
		}
		$hook = $on ? 'add_filter' : 'remove_filter';
		foreach ( $options as $option ) {
			$hook( 'pre_option_' . $option, array( __CLASS__, 'read' ), PHP_INT_MAX, 2 );
			$hook( 'pre_update_option_' . $option, array( __CLASS__, 'write' ), PHP_INT_MAX, 3 );
			$hook( 'pre_update_site_option_' . $option, array( __CLASS__, 'keep' ), PHP_INT_MAX, 2 );
		}
		$hook( 'deleted_option', array( __CLASS__, 'deleted' ), 10, 1 );
		if ( ! $on ) {
			self::$memory = array();
		}
	}

	/** La caché HTML del documento, apagada durante el enlace firmado. */
	public static function no_html_cache() {
		return 'disable';
	}

	/** La página de un token válido (firma, vigencia y revisión de esa página, como el núcleo), o 0. */
	public static function post_for( string $raw ): int {
		$class = self::TOKEN_CLASS;
		if ( ! class_exists( $class ) || ! method_exists( $class, 'decode' ) || ! method_exists( $class, 'secret' ) || ! method_exists( $class, 'is_expired' ) ) {
			return 0;
		}
		$claims = $class::decode( $raw, $class::secret() );
		if ( ! is_array( $claims ) || $class::is_expired( $claims, time() ) ) {
			return 0;
		}
		$id       = isset( $claims['post_id'] ) ? (int) $claims['post_id'] : 0;
		$revision = isset( $claims['revision_id'] ) ? get_post( (int) $claims['revision_id'] ) : null;
		if ( $id <= 0 || ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $id ) {
			return 0;
		}
		return $id;
	}

	/**
	 * `elementor/frontend/after_enqueue_post_styles` 20 (el núcleo ya encoló sus hojas, sin las de la página): los estilos
	 * de la instantánea en línea, una hoja por punto y raíz (`digitalisimo-mcp-preview-<global|local>-<punto>`), en la
	 * posición que tendrían las hojas de la página entre las del núcleo (`place()`).
	 */
	public static function print_styles(): void {
		if ( ! self::$post || ! self::$live || self::$printed || ! function_exists( 'wp_add_inline_style' ) || ! function_exists( 'wp_styles' ) ) {
			return;
		}
		self::$printed = true;
		$order         = self::breakpoints();
		$css           = self::snapshot_css( self::$post, $order );
		$ours          = array();
		foreach ( $order as $index => $breakpoint ) {
			foreach ( array( 'global', 'local' ) as $root ) {
				$text = $css[ $root ][ $breakpoint ] ?? '';
				if ( '' === $text ) {
					continue;
				}
				$handle = self::HANDLE . '-' . $root . '-' . $breakpoint;
				wp_register_style( $handle, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_enqueue_style( $handle );
				wp_add_inline_style( $handle, (string) preg_replace( '/<\/(style)/i', '<\\\\/$1', $text ) );
				$ours[ $handle ] = array( $index, self::RANKS[ $root ] );
			}
		}
		if ( $ours ) {
			$styles        = wp_styles();
			$styles->queue = self::place( (array) $styles->queue, $ours, $order );
		}
	}

	/**
	 * La cola de estilos con las hojas en línea de la instantánea donde el núcleo habría puesto las de la página: el
	 * núcleo encola sus hojas atómicas seguidas, por punto (`Atomic_Styles_Manager::get_breakpoints()`) y, en cada uno,
	 * por raíz en el orden en que se registran (`base` 10, `default` 15, `global` 20, `local` 30). La de la instantánea
	 * va detrás de las de su punto y su raíz. Si la cola no tiene esa forma, van al final (el núcleo las encola al final
	 * de `after_enqueue_post_styles`).
	 *
	 * @param array<int, string>              $queue Cola de `wp_styles()`.
	 * @param array<string, array{int, int}> $ours  Asa => [ índice del punto en `$order`, rango de la raíz ].
	 * @param array<int, string>              $order Puntos en el orden del núcleo.
	 */
	public static function place( array $queue, array $ours, array $order ): array {
		$queue = array_values( array_diff( $queue, array_keys( $ours ) ) );
		$index = array_flip( $order );
		$key   = static function ( $handle ) use ( $index ) {
			if ( is_string( $handle ) && preg_match( '/^(base|default|global|local)(?:-.+)?-([A-Za-z0-9_]+)$/', $handle, $m ) && isset( $index[ $m[2] ], self::RANKS[ $m[1] ] ) ) {
				return array( $index[ $m[2] ], self::RANKS[ $m[1] ] );
			}
			return null;
		};
		$core  = array_keys( array_filter( array_map( $key, $queue ) ) );
		if ( ! $core || count( $core ) !== max( $core ) - min( $core ) + 1 ) {
			return array_merge( $queue, array_keys( $ours ) );
		}
		$first = min( $core );
		$block = array();
		foreach ( array_slice( $queue, $first, count( $core ) ) as $n => $handle ) {
			$block[] = array( $key( $handle ), 0, $n, $handle );
		}
		$n = 0;
		foreach ( $ours as $handle => $position ) {
			$block[] = array( array_values( $position ), 1, $n++, $handle );
		}
		usort(
			$block,
			static function ( $a, $b ) {
				return array( $a[0][0], $a[0][1], $a[1], $a[2] ) <=> array( $b[0][0], $b[0][1], $b[1], $b[2] );
			}
		);
		return array_merge( array_slice( $queue, 0, $first ), array_column( $block, 3 ), array_slice( $queue, $first + count( $core ) ) );
	}

	/**
	 * El CSS de la instantánea (la revisión que el manejador del núcleo sirve como `_elementor_data`) como el de las hojas
	 * del núcleo: `[ 'global' => [ punto => css ], 'local' => [ punto => css ] ]`, cada estilo partido por punto como
	 * `Atomic_Styles_Manager::group_by_breakpoint()` y pintado con `Styles_Renderer`. Globales: las clases de la página y
	 * de lo que incrusta (`elementor/document/related_posts`, como `Atomic_Global_Styles`), en el orden del núcleo.
	 * Locales: los estilos de sus elementos atómicos. Vacío si falta algo del núcleo.
	 */
	public static function snapshot_css( int $id, array $order ): array {
		$utils    = 'Elementor\\Modules\\AtomicWidgets\\Utils\\Utils';
		$elements = 'Elementor\\Modules\\GlobalClasses\\Utils\\Atomic_Elements_Utils';
		$styles   = 'Elementor\\Modules\\AtomicWidgets\\Styles\\Atomic_Widget_Styles';
		$renderer = 'Elementor\\Modules\\AtomicWidgets\\Styles\\Styles_Renderer';
		if ( ! class_exists( $utils ) || ! class_exists( $elements ) || ! class_exists( $styles ) || ! class_exists( $renderer ) || ! class_exists( 'Elementor\\Plugin' ) ) {
			return array();
		}
		try {
			$local   = array();
			$classes = array();
			foreach ( array_merge( array( $id ), self::embedded( $id ) ) as $post_id ) {
				$found = array();
				$utils::traverse_post_elements(
					(string) $post_id,
					static function ( $element ) use ( &$local, &$found, $utils, $elements, $post_id, $id ) {
						if ( ! is_array( $element ) ) {
							return;
						}
						$found = array_merge( $found, $elements::collect_class_ids_from_element_data( $element ) );
						if ( $post_id === $id && ! empty( $element['styles'] ) && is_array( $element['styles'] ) && $utils::is_atomic( $elements::get_element_instance( $elements::get_element_type( $element ) ) ) ) {
							$local = array_merge( $local, $element['styles'] );
						}
					}
				);
				$classes = array_merge( $classes, (array) apply_filters( 'elementor/global_classes/extract_class_ids_from_post', array_values( array_unique( $found ) ), $post_id ) );
			}
			$local  = $styles::get_license_based_filtered_styles( $local );
			$plugin = \Elementor\Plugin::$instance;
			$render = $renderer::make( $plugin->breakpoints->get_breakpoints_config() )->on_font_enqueue(
				static function ( $font ) use ( $plugin ) {
					$plugin->frontend->enqueue_font( $font );
				}
			);
			$out = array();
			foreach ( array(
				'global' => self::global_styles( array_values( array_unique( array_filter( $classes, 'is_string' ) ) ) ),
				'local'  => is_array( $local ) ? $local : array(),
			) as $root => $list ) {
				$groups = self::by_breakpoint( $list );
				foreach ( $order as $breakpoint ) {
					$out[ $root ][ $breakpoint ] = empty( $groups[ $breakpoint ] ) ? '' : (string) $render->render( array_values( $groups[ $breakpoint ] ) );
				}
			}
			return $out;
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/** Cada estilo partido por punto, como `Atomic_Styles_Manager::group_by_breakpoint()` (sin punto: escritorio). */
	public static function by_breakpoint( array $styles ): array {
		$group = array();
		foreach ( $styles as $style ) {
			if ( ! is_array( $style ) || ! isset( $style['id'] ) || empty( $style['variants'] ) || ! is_array( $style['variants'] ) ) {
				continue;
			}
			foreach ( $style['variants'] as $variant ) {
				$breakpoint = is_array( $variant ) && ! empty( $variant['meta']['breakpoint'] ) ? (string) $variant['meta']['breakpoint'] : 'desktop';
				if ( ! isset( $group[ $breakpoint ][ $style['id'] ] ) ) {
					$group[ $breakpoint ][ $style['id'] ] = array(
						'id'       => $style['id'],
						'type'     => $style['type'] ?? '',
						'variants' => array(),
					);
					if ( isset( $style['cssName'] ) ) {
						$group[ $breakpoint ][ $style['id'] ]['cssName'] = $style['cssName'];
					}
				}
				$group[ $breakpoint ][ $style['id'] ]['variants'][] = $variant;
			}
		}
		return $group;
	}

	/** Lo que incrusta la página, de forma transitiva (`elementor/document/related_posts`, como `Atomic_Global_Styles`). */
	private static function embedded( int $id ): array {
		$seen  = array( $id => true );
		$queue = array( $id );
		$all   = array();
		while ( $queue ) {
			$current = array_shift( $queue );
			foreach ( (array) apply_filters( 'elementor/document/related_posts', array(), $current ) as $related ) {
				$related = is_numeric( $related ) ? (int) $related : 0;
				if ( $related > 0 && ! isset( $seen[ $related ] ) ) {
					$seen[ $related ] = true;
					$all[]            = $related;
					$queue[]          = $related;
				}
			}
		}
		return $all;
	}

	/** Las clases globales que usa la instantánea, con el orden y las etiquetas del núcleo (`Atomic_Global_Styles`). */
	private static function global_styles( array $ids ): array {
		$repository = 'Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';
		if ( empty( $ids ) || ! class_exists( $repository ) ) {
			return array();
		}
		$repo    = $repository::make();
		$order   = $repo->all_labels();
		$ordered = array_values( array_intersect( array_keys( $order ), $ids ) );
		if ( empty( $ordered ) ) {
			return array();
		}
		$items = $repo->get_by_ids( $ordered );
		$out   = array();
		foreach ( array_reverse( $ordered ) as $class_id ) {
			if ( empty( $items[ $class_id ] ) ) {
				continue;
			}
			$item          = $items[ $class_id ];
			$label         = $order[ $class_id ] ?? ( $item['label'] ?? $class_id );
			$item['id']    = $label;
			$item['label'] = $label;
			$out[]         = $item;
		}
		return $out;
	}

	public static function shutdown(): void {
		if ( ! self::$post ) {
			return;
		}
		$id = self::$post;
		if ( self::$live ) {
			self::sandbox( false );
			self::guard( false );
			remove_action( 'elementor/frontend/after_enqueue_post_styles', array( __CLASS__, 'print_post_css' ), 5 );
			remove_action( 'elementor/frontend/after_enqueue_post_styles', array( __CLASS__, 'print_styles' ), 20 );
		} else {
			Css_Sync::invalidate_styles( $id );
			Css_Sync::invalidate_related( $id );
		}
		remove_filter( 'pre_option_' . self::TTL_OPTION, array( __CLASS__, 'no_html_cache' ) );
		self::$post         = 0;
		self::$live         = false;
		self::$printed      = false;
		self::$post_printed = false;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$post        = 0;
		self::$live        = false;
		self::$memory       = array();
		self::$printed      = false;
		self::$post_printed = false;
	}

	/** Para las pruebas: la página del enlace de esta petición y las opciones que van en memoria. */
	public static function state(): array {
		return array(
			'post'   => self::$post,
			'live'   => self::$live,
			'memory' => array_keys( self::$memory ),
		);
	}

	/**
	 * En la copia en memoria: el contexto `frontend` de la página en las raíces local y global queda válido (con su `ver`
	 * si ya lo era) y cada hoja suya válida sin archivo (`should_exist` falso): el núcleo no sirve ni escribe ninguna hoja
	 * de la página en esta petición (sus estilos van en línea, `print_styles()`), y su lista de componentes se recalcula
	 * con la instantánea.
	 */
	private static function freeze( int $id ): void {
		$class = Css_Sync::VALIDITY_CLASS;
		if ( ! class_exists( $class ) ) {
			return;
		}
		$validity = new $class();
		foreach ( array( Css_Sync::key( 'local' ), Css_Sync::key( 'global' ) ) as $root ) {
			$context = array( $root, $id, self::CONTEXT );
			if ( ! $validity->is_valid( $context ) ) {
				$validity->validate( $context, 'preview-' . $id );
			}
			foreach ( self::breakpoints() as $breakpoint ) {
				$validity->validate( array_merge( $context, array( $breakpoint ) ), array( 'should_exist' => false ) );
			}
		}
		$validity->invalidate( array( Css_Sync::key( 'related' ), (string) $id ) );
	}

	/**
	 * Los puntos en el orden en que el núcleo pinta sus hojas (`Atomic_Styles_Manager::get_breakpoints()`): escritorio y
	 * después los activos de mayor a menor (la lista del núcleo, invertida).
	 */
	public static function breakpoints(): array {
		$names = array();
		if ( class_exists( 'Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) && is_object( \Elementor\Plugin::$instance->breakpoints ) ) {
			foreach ( (array) \Elementor\Plugin::$instance->breakpoints->get_breakpoints() as $key => $breakpoint ) {
				$names[] = is_object( $breakpoint ) && method_exists( $breakpoint, 'get_name' ) ? (string) $breakpoint->get_name() : (string) $key;
			}
		}
		return array_values( array_unique( array_filter( array_merge( array( 'desktop' ), array_reverse( $names ) ), 'strlen' ) ) );
	}
}
