<?php
namespace Digitalisimo\Elements\Design\Document;

use Digitalisimo\Elements\Design\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Controles de documento (F7a, frente L; V.7 y §3.7 de la especificación): «Ocultar cabecera», «Ocultar pie» y
 * «Ocultar imagen destacada» por página. Son comodidad y respaldo del tema: ocultar una plantilla en una página ya se
 * puede con una condición `exclude` del Theme Builder, pero eso no quita la cabecera de respaldo del tema.
 *
 * - **Dónde:** ajustes de las entradas, páginas y demás documentos de tipo página (`PageBase`, sin el kit ni los
 *   botones flotantes) y de las plantillas de página de la biblioteca; nunca en plantillas del Theme Builder, popups
 *   ni el kit. Detrás de `post_status` (o de `post_title`), así que quedan bajo «Ocultar título».
 * - **Cabecera y pie, sólo con el tema DIGITALÍSIMO activo** (por su carpeta o por su `Update URI`, con el ayudante
 *   `Theme_Compat::active()`). Con otro tema no se ofrecen: Hello y los temas con ubicaciones pintarían su propia
 *   cabecera al quedar vacía la ubicación. En su lugar, una nota remite a las condiciones de la plantilla. Ocultan la
 *   ubicación entera: cada plantilla del Theme Builder (`skip_doc_in_location()`, así `do_location()` queda vacía) y el
 *   respaldo del tema por su filtro público `digitalisimo_theme_header_footer`, sólo para esa parte y sólo en esa
 *   petición. Los ganchos `digitalisimo_before_*` y `after_*` y sus ubicaciones siguen.
 * - **Imagen destacada, con cualquier tema:** sólo el widget «Imagen destacada» cuando muestra la del contenido
 *   consultado, en la propia página o en su plantilla de entrada; nunca dentro de un bucle (Loop Grid o Loop
 *   Carousel), ni la etiqueta dinámica ni la imagen para redes sociales.
 *
 * Coste: una página sin estos ajustes no registra ningún gancho; en el front sólo se lee la meta de la vista individual,
 * que WordPress ya cargó. Es igual para todos los visitantes de una URL: cacheable.
 */
final class Document_Controls {
	const HEADER   = 'digitalisimo_hide_header';
	const FOOTER   = 'digitalisimo_hide_footer';
	const FEATURED = 'digitalisimo_hide_featured_image';
	const KEYS     = array( self::HEADER, self::FOOTER, self::FEATURED );
	const NOTE     = 'digitalisimo_hide_header_footer_note';

	const META         = '_elementor_page_settings';
	const WIDGET       = 'theme-post-featured-image';
	const THEME_FILTER = 'digitalisimo_theme_header_footer';

	/** Tipos de documento `PageBase` que no son una página que se visita. */
	const EXCLUDED_TYPES = array( 'kit', 'floating-buttons' );

	const PAGE_BASE    = '\Elementor\Core\DocumentTypes\PageBase';
	const LIBRARY_PAGE = '\Elementor\Modules\Library\Documents\Page';
	const LOOP         = '\ElementorPro\Modules\LoopBuilder\Documents\Loop';
	const THEME_COMPAT = '\Digitalisimo\Elements\Theme_Compat';

	/** @var int Contenido consultado de esta petición, si pidió algo. */
	private static $queried = 0;

	/** @var string[] Partes (`header`, `footer`) ocultas en esta petición. */
	private static $hidden = array();

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		$symbols = array(
			'Elementor\Controls_Manager',
			'Elementor\Core\DocumentTypes\PageBase',
			'Elementor\Modules\Library\Documents\Page',
			'Elementor\Controls_Stack::start_injection',
			'ElementorPro\Modules\ThemeBuilder\Module::instance',
			'ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager::skip_doc_in_location',
			'ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager::get_documents_for_location',
		);
		if ( ! Module::requires( 'document', $symbols ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/documents/register_controls', array( __CLASS__, 'register_controls' ) );
		// Antes de `Visitor_Content` (20) y de que se pinte nada.
		add_action( 'template_redirect', array( __CLASS__, 'apply' ), 15 );
	}

	/** Controles de los documentos de tipo página, detrás de `post_status` (respaldo `post_title`, R-M14). */
	public static function register_controls( $document ): void {
		if ( ! self::is_page_document( $document ) ) {
			return;
		}
		if ( null === $document->get_controls( 'post_status' ) && null === $document->get_controls( 'post_title' ) ) {
			return;
		}
		$c = '\Elementor\Controls_Manager';
		$document->start_injection(
			array(
				'of'       => 'post_status',
				'fallback' => array( 'of' => 'post_title' ),
			)
		);
		if ( self::theme_active() ) {
			$document->add_control(
				self::HEADER,
				array(
					'label'        => 'Ocultar cabecera',
					'type'         => $c::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'separator'    => 'before',
					'description'  => 'Sólo en esta página: ni la plantilla de cabecera ni la del tema. El contenido de los ganchos de antes y después de la cabecera sigue.',
				)
			);
			$document->add_control(
				self::FOOTER,
				array(
					'label'        => 'Ocultar pie',
					'type'         => $c::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'description'  => 'Sólo en esta página: ni la plantilla de pie ni la del tema. El contenido de los ganchos de antes y después del pie sigue.',
				)
			);
		} else {
			$document->add_control(
				self::NOTE,
				array(
					'type'      => $c::RAW_HTML,
					'raw'       => 'Para quitar la cabecera o el pie sólo en esta página, excluye la página en las condiciones de esa plantilla del Theme Builder.',
					'separator' => 'before',
				)
			);
		}
		$document->add_control(
			self::FEATURED,
			array(
				'label'        => 'Ocultar imagen destacada',
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => 'Oculta el widget «Imagen destacada» en esta página y en su plantilla de entrada. Las tarjetas de los bucles, la etiqueta dinámica y la imagen para redes sociales no cambian.',
			)
		);
		$document->end_injection();
	}

	/**
	 * Contenido de la petición: la vista individual (también la portada estática), la página de entradas o la tienda.
	 * 0 si la petición no tiene contenido propio.
	 */
	public static function queried(): int {
		if ( is_singular() ) {
			return max( 0, (int) get_queried_object_id() );
		}
		if ( is_home() && ! is_front_page() ) {
			return max( 0, (int) get_option( 'page_for_posts' ) );
		}
		if ( function_exists( 'is_shop' ) && function_exists( 'wc_get_page_id' ) && is_shop() ) {
			return max( 0, (int) wc_get_page_id( 'shop' ) );
		}
		return 0;
	}

	/** `template_redirect` 15: lee los ajustes del contenido consultado y, sólo si pide algo, se engancha. */
	public static function apply(): void {
		self::$queried = 0;
		self::$hidden  = array();
		$id            = self::queried();
		if ( ! $id ) {
			return;
		}
		$settings = get_post_meta( $id, self::META, true );
		if ( ! is_array( $settings ) || ! $settings ) {
			return;
		}
		$parts = array();
		foreach ( array( 'header' => self::HEADER, 'footer' => self::FOOTER ) as $part => $key ) {
			if ( isset( $settings[ $key ] ) && 'yes' === $settings[ $key ] ) {
				$parts[] = $part;
			}
		}
		if ( $parts && ! self::theme_active() ) {
			$parts = array(); // Con otro tema, su cabecera propia ocuparía el hueco: no se aplica.
		}
		$featured = isset( $settings[ self::FEATURED ] ) && 'yes' === $settings[ self::FEATURED ];
		if ( ! $parts && ! $featured ) {
			return;
		}
		self::$queried = $id;
		if ( $parts ) {
			self::$hidden = $parts;
			foreach ( $parts as $part ) {
				self::skip_location( $part );
			}
			add_filter( self::THEME_FILTER, array( __CLASS__, 'theme_header_footer' ), 20, 2 );
		}
		if ( $featured ) {
			add_filter( 'elementor/frontend/widget/should_render', array( __CLASS__, 'featured_image' ), 10, 2 );
			// Sin `__dynamic__` guardado, el widget entraría en la caché de su plantilla con esta decisión: en esta
			// petición se pinta aparte, para que la caché nunca guarde la imagen oculta.
			add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'featured_dynamic' ), 10, 3 );
		}
	}

	/** Filtro público del tema: el respaldo no se pinta para la parte oculta. */
	public static function theme_header_footer( $show, $part = 'header' ) {
		return in_array( (string) $part, self::$hidden, true ) ? false : $show;
	}

	/** El widget «Imagen destacada» no se pinta con el contenido consultado, salvo dentro de un bucle. */
	public static function featured_image( $should_render, $element = null ) {
		if ( ! $should_render || ! self::is_featured_widget( $element ) || ! self::$queried || (int) get_the_ID() !== self::$queried || self::in_loop() ) {
			return $should_render;
		}
		return false;
	}

	/** `elementor/element/is_dynamic_content`: el widget «Imagen destacada» se pinta fuera de la caché en esta petición. */
	public static function featured_dynamic( $is_dynamic, $raw_data = array(), $element = null ) {
		return self::is_featured_widget( $element ) ? true : $is_dynamic;
	}

	/** ¿Se está pintando un elemento de bucle (Loop Grid, Loop Carousel, también de términos)? */
	public static function in_loop(): bool {
		global $wp_query;
		if ( is_object( $wp_query ) && isset( $wp_query->is_loop_widget ) ) {
			return true; // `Skin_Loop_Base::add_render_hooks()`.
		}
		$plugin = '\Elementor\Plugin';
		if ( ! class_exists( $plugin, false ) || ! isset( $plugin::$instance->documents ) || ! is_object( $plugin::$instance->documents ) || ! method_exists( $plugin::$instance->documents, 'get_current' ) ) {
			return false;
		}
		$current = $plugin::$instance->documents->get_current();
		if ( ! is_object( $current ) ) {
			return false;
		}
		$loop = self::LOOP;
		if ( class_exists( $loop ) && $current instanceof $loop ) {
			return true; // `Loop::get_content()` cambia el documento actual al elemento del bucle.
		}
		return method_exists( $current, 'get_name' ) && 'loop-item' === $current->get_name();
	}

	/** Saca de la cola de la ubicación cada plantilla que le corresponde: `do_location()` queda vacía. */
	private static function skip_location( string $part ): void {
		$class   = '\ElementorPro\Modules\ThemeBuilder\Module';
		$module  = call_user_func( array( $class, 'instance' ) );
		$manager = is_object( $module ) && method_exists( $module, 'get_conditions_manager' ) ? $module->get_conditions_manager() : null;
		$queue   = is_object( $module ) && method_exists( $module, 'get_locations_manager' ) ? $module->get_locations_manager() : null;
		if ( ! is_object( $manager ) || ! is_object( $queue ) ) {
			return;
		}
		foreach ( array_keys( (array) $manager->get_documents_for_location( $part ) ) as $document_id ) {
			$queue->skip_doc_in_location( $part, (int) $document_id );
		}
	}

	private static function is_featured_widget( $element ): bool {
		return is_object( $element ) && method_exists( $element, 'get_name' ) && self::WIDGET === $element->get_name();
	}

	/** Entradas, páginas y demás `PageBase` (sin el kit ni los botones flotantes) y la página de la biblioteca. */
	private static function is_page_document( $document ): bool {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_controls' ) ) {
			return false;
		}
		$library = self::LIBRARY_PAGE;
		if ( class_exists( $library ) && $document instanceof $library ) {
			return true;
		}
		$base = self::PAGE_BASE;
		if ( ! class_exists( $base ) || ! $document instanceof $base ) {
			return false;
		}
		return ! method_exists( $document, 'get_name' ) || ! in_array( (string) $document->get_name(), self::EXCLUDED_TYPES, true );
	}

	/** El tema DIGITALÍSIMO está activo (carpeta o `Update URI`), con el ayudante de `modules/digitalisimo-theme/`. */
	private static function theme_active(): bool {
		$class = self::THEME_COMPAT;
		return class_exists( $class ) && method_exists( $class, 'active' ) && (bool) call_user_func( array( $class, 'active' ) );
	}
}
