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
 * - la lista de componentes de la página se invalida antes (la de la instantánea) y después (la de la página);
 * - si la página está publicada (o privada), sus hojas `frontend` (locales y de clases globales) se congelan: las
 *   inválidas se marcan válidas para esta petición (con `should_exist` según haya archivo), así el núcleo sirve los
 *   archivos tal cual o ninguno y no escribe nada; los estilos de la instantánea (locales y de sus clases globales) se
 *   imprimen en línea después, con el renderizador del núcleo; al terminar, lo que se marcó vuelve a inválido. Abrir el
 *   enlace no cambia ningún archivo ni ningún `ver` de la publicada;
 * - si es un borrador (nadie guarda su HTML), sus hojas se invalidan antes y después: la instantánea se pinta con su CSS
 *   y la siguiente visita lo regenera.
 */
final class Preview_Link {
	const CONTEXT     = 'frontend';
	const HANDLE      = 'digitalisimo-mcp-preview';
	const TTL_OPTION  = 'elementor_element_cache_ttl';
	const LIVE        = array( 'publish', 'private' );
	const TOKEN_CLASS = 'Elementor\\Modules\\Mcp\\Preview\\Preview_Token';

	/** @var bool */
	private static $initialized = false;

	/** @var int Página del enlace firmado de esta petición. */
	private static $post = 0;

	/** @var bool ¿Estaba publicada al abrir el enlace? */
	private static $live = false;

	/** @var array<string, array{context: bool, leaves: array<int, array>}> Lo que se congeló, por raíz. */
	private static $frozen = array();

	/** @var bool */
	private static $printed = false;

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'parse_request', array( __CLASS__, 'request' ), 2 );
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
		Css_Sync::invalidate_related( $id );
		if ( self::$live ) {
			self::freeze( $id );
			add_action( 'elementor/frontend/after_enqueue_post_styles', array( __CLASS__, 'print_styles' ), 20 );
		} else {
			Css_Sync::invalidate_styles( $id );
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

	/** `elementor/frontend/after_enqueue_post_styles` 20 (las hojas del núcleo ya están encoladas): CSS en línea. */
	public static function print_styles(): void {
		if ( ! self::$post || ! self::$live || self::$printed || ! function_exists( 'wp_add_inline_style' ) ) {
			return;
		}
		self::$printed = true;
		$css           = self::snapshot_css( self::$post );
		if ( '' === $css ) {
			return;
		}
		wp_register_style( self::HANDLE, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, (string) preg_replace( '/<\/(style)/i', '<\\\\/$1', $css ) );
	}

	/**
	 * Los estilos de la instantánea (la revisión que el manejador del núcleo sirve como `_elementor_data`): primero los de
	 * sus clases globales, en el orden del núcleo, y después los locales, como en las hojas del núcleo. '' si falta algo.
	 */
	public static function snapshot_css( int $id ): string {
		$utils    = 'Elementor\\Modules\\AtomicWidgets\\Utils\\Utils';
		$elements = 'Elementor\\Modules\\GlobalClasses\\Utils\\Atomic_Elements_Utils';
		$styles   = 'Elementor\\Modules\\AtomicWidgets\\Styles\\Atomic_Widget_Styles';
		$renderer = 'Elementor\\Modules\\AtomicWidgets\\Styles\\Styles_Renderer';
		if ( ! class_exists( $utils ) || ! class_exists( $elements ) || ! class_exists( $styles ) || ! class_exists( $renderer ) || ! class_exists( 'Elementor\\Plugin' ) ) {
			return '';
		}
		try {
			$local   = array();
			$classes = array();
			$utils::traverse_post_elements(
				(string) $id,
				static function ( $element ) use ( &$local, &$classes, $utils, $elements ) {
					if ( ! is_array( $element ) || ! $utils::is_atomic( $elements::get_element_instance( $elements::get_element_type( $element ) ) ) ) {
						return;
					}
					if ( ! empty( $element['styles'] ) && is_array( $element['styles'] ) ) {
						$local = array_merge( $local, $element['styles'] );
					}
					$classes = array_merge( $classes, $elements::collect_class_ids_from_element_data( $element ) );
				}
			);
			$local  = $styles::get_license_based_filtered_styles( $local );
			$plugin = \Elementor\Plugin::$instance;
			$render = $renderer::make( $plugin->breakpoints->get_breakpoints_config() )->on_font_enqueue(
				static function ( $font ) use ( $plugin ) {
					$plugin->frontend->enqueue_font( $font );
				}
			);
			return $render->render( self::global_styles( array_values( array_unique( $classes ) ) ) ) . $render->render( is_array( $local ) ? $local : array() );
		} catch ( \Throwable $e ) {
			return '';
		}
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
			self::thaw( $id );
		} else {
			Css_Sync::invalidate_styles( $id );
		}
		Css_Sync::invalidate_related( $id );
		if ( function_exists( 'remove_filter' ) ) {
			remove_filter( 'pre_option_' . self::TTL_OPTION, array( __CLASS__, 'no_html_cache' ) );
		}
		self::$post    = 0;
		self::$live    = false;
		self::$frozen  = array();
		self::$printed = false;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$post        = 0;
		self::$live        = false;
		self::$frozen      = array();
		self::$printed     = false;
	}

	/** Para las pruebas: la página del enlace de esta petición y lo congelado. */
	public static function state(): array {
		return array(
			'post'   => self::$post,
			'live'   => self::$live,
			'frozen' => self::$frozen,
		);
	}

	/**
	 * Hojas `frontend` de la página en las raíces local y global: el contexto y cada punto que el núcleo escribiría
	 * (inválido, o válido con un archivo que debería existir y falta) se marcan válidos para esta petición.
	 */
	private static function freeze( int $id ): void {
		$class = Css_Sync::VALIDITY_CLASS;
		if ( ! class_exists( $class ) ) {
			return;
		}
		$validity = new $class();
		foreach ( array( Css_Sync::key( 'local' ), Css_Sync::key( 'global' ) ) as $root ) {
			$context = array( $root, $id, self::CONTEXT );
			$state   = array(
				'context' => $validity->is_valid( $context ),
				'leaves'  => array(),
			);
			if ( ! $state['context'] ) {
				$validity->validate( $context, 'preview-' . $id );
			}
			foreach ( self::breakpoints() as $breakpoint ) {
				$leaf   = array_merge( $context, array( $breakpoint ) );
				$has    = self::has_file( $leaf );
				$meta   = $validity->get_meta( $leaf );
				$should = is_array( $meta ) && array_key_exists( 'should_exist', $meta ) ? (bool) $meta['should_exist'] : null;
				if ( $state['context'] && $validity->is_valid( $leaf ) && ( false === $should || $has ) ) {
					continue;
				}
				$validity->validate( $leaf, array( 'should_exist' => $has ) );
				$state['leaves'][] = $leaf;
			}
			self::$frozen[ $root ] = $state;
		}
	}

	/** Lo congelado vuelve a inválido (nunca a válido): el siguiente render de la página lo regenera con sus datos. */
	private static function thaw( int $id ): void {
		$class = Css_Sync::VALIDITY_CLASS;
		if ( ! class_exists( $class ) ) {
			return;
		}
		$validity = new $class();
		foreach ( self::$frozen as $root => $state ) {
			if ( empty( $state['context'] ) ) {
				$validity->invalidate( array( $root, $id, self::CONTEXT ) );
				continue;
			}
			foreach ( $state['leaves'] as $leaf ) {
				$validity->invalidate( $leaf );
			}
		}
	}

	/** Los puntos que recorre el núcleo (`Atomic_Styles_Manager::get_breakpoints()`): escritorio y los demás. */
	private static function breakpoints(): array {
		$names = array( 'desktop' );
		if ( class_exists( 'Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) && is_object( \Elementor\Plugin::$instance->breakpoints ) ) {
			foreach ( (array) \Elementor\Plugin::$instance->breakpoints->get_breakpoints() as $key => $breakpoint ) {
				$names[] = is_object( $breakpoint ) && method_exists( $breakpoint, 'get_name' ) ? (string) $breakpoint->get_name() : (string) $key;
			}
		}
		return array_values( array_unique( array_filter( $names, 'strlen' ) ) );
	}

	/** ¿Existe y no está vacío el archivo de esa hoja? (la ruta de `CSS_Files_Manager`). */
	private static function has_file( array $path ): bool {
		$base = class_exists( 'Elementor\\Core\\Files\\Base' ) ? (string) \Elementor\Core\Files\Base::get_base_uploads_dir() : '';
		if ( '' === $base ) {
			return false;
		}
		$file = $base . 'css/' . preg_replace( '/[^a-zA-Z0-9_-]/', '', implode( '-', $path ) ) . '.css';
		return is_file( $file ) && filesize( $file ) > 0;
	}
}
