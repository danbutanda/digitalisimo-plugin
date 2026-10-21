<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Análisis de dependencias de los iconos de fuente (F-front, frente C; D4).
 *
 * Los iconos en línea (SVG) nunca se aplican solos en un sitio existente: este análisis busca lo que dejaría de
 * verse si Font Awesome y eicons dejan de cargarse, y su resultado se muestra al administrador. «Aplicar» lo decide
 * él; la opción de red sólo actúa donde el análisis sale limpio. Nunca escribe nada fuera de su propia opción.
 *
 * Fuentes: CSS adicional del tema, CSS técnico de DIGITALÍSIMO SEO, widgets clásicos de texto, HTML y bloques, las
 * opciones de migración de iconos del núcleo y, por lotes con cursor por id, cada entrada: `post_content` de los
 * tipos públicos, título y clases de los elementos de menú, fragmentos de código, CSS de página y del kit, y los
 * datos del constructor (cadenas, CSS y clases de cada elemento, claves `icon` antiguas sin migrar y widgets de
 * terceros en uso). Lo que el análisis no puede ver (PHP y hojas de temas o plugins) lo explica la pantalla.
 */
final class Icon_Scan {
	const OPTION       = 'digitalisimo_elements_icon_scan';
	/** Evento único que continúa un análisis pendiente, lote a lote. */
	const HOOK         = 'digitalisimo_elements_icon_scan';
	const BATCH        = 200;
	const MAX_FINDINGS = 50;
	const EXCERPT      = 120;
	/** Opciones del núcleo que indican iconos antiguos de Font Awesome 4. */
	const NEEDS_UPDATE = 'elementor_icon_manager_needs_update';
	const FA4_SHIM     = 'elementor_load_fa4_shim';
	/** Metadatos pequeños que se leen de una vez por lote. */
	const SMALL_META   = array( '_elementor_page_settings', '_elementor_code', '_menu_item_classes' );
	/** Datos del constructor: se leen entrada por entrada para no cargar un lote entero en memoria. */
	const DATA_META    = '_elementor_data';
	const WIDGET_OPTIONS = array( 'widget_text', 'widget_custom_html', 'widget_block' );
	/** Tipos no públicos cuyo contenido también se imprime (bloques reutilizables y plantillas de un tema de bloques). */
	const CONTENT_TYPES  = array( 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
	/** Espacios de nombres propios: sus widgets pintan los iconos con el gestor del núcleo. */
	const OWN_NAMESPACES = array( 'Elementor\\', 'ElementorPro\\', 'Digitalisimo\\' );
	/** Fuente => texto para la pantalla. */
	const SOURCES = array(
		'custom_css'         => 'CSS adicional del tema',
		'seo_css'            => 'CSS técnico de DIGITALÍSIMO SEO',
		'widget_text'        => 'Widget clásico de texto',
		'widget_custom_html' => 'Widget clásico de HTML',
		'widget_block'       => 'Widget de bloques',
		'option'             => 'Ajuste de iconos del constructor',
		'post_content'       => 'Contenido de la entrada',
		'menu_title'         => 'Título de un elemento de menú',
		'menu_classes'       => 'Clases de un elemento de menú',
		'snippet'            => 'Código personalizado',
		'kit_css'            => 'CSS personalizado del kit',
		'page_css'           => 'CSS personalizado de la página',
		'element_css'        => 'CSS personalizado de un elemento',
		'element_class'      => 'Clases CSS de un elemento',
		'element_text'       => 'Texto o HTML de un elemento',
		'fa4'                => 'Icono antiguo sin migrar',
		'third_party'        => 'Widget de terceros',
	);

	/** Patrones de cualquier texto (CSS, JavaScript o HTML), sin distinguir mayúsculas. */
	private const COMMON = array(
		'font-awesome'    => '/font[\s-]?awesome/i',
		'fa-prefix'       => '/\bfa-[a-z0-9-]+/i',
		'eicons'          => '/\beicons?\b|\.eicon-/i',
		'elementor-icons' => '/elementor-icons/i',
		// [class*="fa-"], [class^="fa"], [class~=fas]…
		'class-attribute' => '/\[\s*class\s*[\^*~|$]?=\s*["\']?\s*fa(?:-|[srlbdt]?(?=[\s"\'\]]))/i',
	);
	/** Patrones de selectores (CSS y selectores escritos en JavaScript). */
	private const SELECTORS = array(
		'fa-class'      => '/\.fa[srlbdt]?(?![\w-])/i',
		// Un `i` dentro de los contenedores de icono del constructor: deja de existir con los iconos en línea.
		'icon-selector' => '/(?:\.elementor-icon|-button-icon|-icon-list-icon|-social-icon|-icon-box-icon)[\w-]*[^{},;]*?[\s>]\s*i(?![\w-])/i',
	);
	/** Llamadas de JavaScript que ponen o leen clases. */
	private const JS_CLASS_CALL = '/(?:classList\s*\.\s*(?:add|remove|toggle|contains|replace)|\b(?:add|remove|toggle|has)Class|\.className\s*\+?=|setAttribute\s*\(\s*["\']class["\']\s*,)\s*\(?([^)\n;]*)/i';
	/** Una clase de Font Awesome o de eicons como palabra completa. */
	private const FA_TOKEN     = '/^fa[srlbdt]?$/i';
	private const PREFIX_TOKEN = '/^fa-[a-z0-9-]+$/i';
	private const EICON_TOKEN  = '/^eicons?$|^eicon-[a-z0-9-]+$/i';

	private static $started = false;
	/** @var array<string,string> Tipo de widget => clase registrada. */
	private static $classes = array();

	public static function init(): void {
		if ( self::$started ) {
			return;
		}
		self::$started = true;
		add_action( self::HOOK, array( __CLASS__, 'cron' ) );
	}

	/** Estado guardado: status none|running|done, cursor, entradas revisadas, hallazgos (≤ 50) y su total. */
	public static function state(): array {
		$state = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		return array_merge( self::blank(), is_array( $state ) ? $state : array() );
	}

	/** clean|findings|pending, o n/a sin núcleo incluido o sin análisis. */
	public static function verdict(): string {
		if ( ! self::available() ) {
			return 'n/a';
		}
		return self::verdict_of( self::state() );
	}

	private static function verdict_of( array $state ): string {
		if ( 'running' === ( $state['status'] ?? '' ) ) {
			return 'pending';
		}
		if ( 'done' === ( $state['status'] ?? '' ) ) {
			return (int) ( $state['total'] ?? 0 ) > 0 || ! empty( $state['findings'] ) ? 'findings' : 'clean';
		}
		return 'n/a';
	}

	/** Si el análisis tiene sentido: núcleo incluido y experimento de iconos inactivo sin el valor propio. */
	public static function applicable(): bool {
		if ( ! self::available() ) {
			return false;
		}
		$native = class_exists( __NAMESPACE__ . '\Core_Defaults' ) ? Core_Defaults::native_icons() : '';
		if ( '' === $native && function_exists( 'get_option' ) ) {
			$saved  = (string) get_option( 'elementor_experiment-' . Core_Defaults::FEATURE, '' );
			$native = in_array( $saved, array( 'active', 'inactive' ), true ) ? $saved : 'inactive';
		}
		return 'active' !== $native;
	}

	/** Etiqueta de una fuente para la pantalla. */
	public static function label( string $source ): string {
		return self::SOURCES[ $source ] ?? $source;
	}

	/** «Analizar»: empieza un análisis nuevo aunque ya haya uno terminado o en curso, y revisa el primer lote. */
	public static function start( int $limit = self::BATCH ): array {
		$state = self::state();
		if ( ! self::applicable() ) {
			return $state;
		}
		return self::advance( self::begin( $state ), $limit );
	}

	/**
	 * Avanza el análisis: con uno en curso revisa las siguientes `$limit` entradas por id; si nunca hubo uno, lo empieza
	 * (fuentes globales y primer lote). Uno terminado se devuelve tal cual: sólo `start()` lo repite. Si quedan entradas,
	 * agenda su continuación; al terminar, pide a la transición que copie el veredicto al valor aplicado.
	 */
	public static function run_batch( int $limit = self::BATCH ): array {
		$state = self::state();
		if ( 'running' !== $state['status'] ) {
			if ( 'done' === $state['status'] || ! self::applicable() ) {
				return $state;
			}
			$state = self::begin( $state );
		}
		return self::advance( $state, $limit );
	}

	/** Estado de un análisis nuevo, con el veredicto del anterior para saber si cambió. */
	private static function begin( array $old ): array {
		$state             = self::blank();
		$state['status']   = 'running';
		$state['started']  = time();
		$state['previous'] = self::verdict_of( $old );
		self::scan_globals( $state );
		return $state;
	}

	private static function advance( array $state, int $limit ): array {
		$limit = max( 1, min( 1000, $limit ) );
		$rows  = self::posts_after( (int) $state['cursor'], $limit );
		self::scan_posts( $rows, $state );
		$state['updated'] = time();
		$done = count( $rows ) < $limit;
		if ( $done ) {
			$state['status']   = 'done';
			$state['finished'] = time();
		}
		self::save( $state );
		if ( $done ) {
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::HOOK );
			}
			// Sólo si el veredicto cambió: repetir un análisis con el mismo resultado no vuelve a disparar la transición.
			if ( self::verdict_of( $state ) !== $state['previous'] && class_exists( __NAMESPACE__ . '\Transition' ) && method_exists( __NAMESPACE__ . '\Transition', 'schedule' ) ) {
				Transition::schedule();
			}
		} elseif ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
		return $state;
	}

	/** Evento: continúa sólo un análisis en curso. */
	public static function cron(): void {
		if ( 'running' === self::state()['status'] ) {
			self::run_batch( self::BATCH );
		}
	}

	/**
	 * Busca en un texto lo que depende de los iconos de fuente.
	 *
	 * @param string $kind css|js|html|class.
	 * @return array<int,array{pattern:string,excerpt:string}> Un hallazgo por patrón, con su extracto (≤ 120).
	 */
	public static function scan_text( string $text, string $kind ): array {
		if ( '' === trim( $text ) ) {
			return array();
		}
		$found = array();
		if ( 'css' === $kind ) {
			self::css( $text, $found );
		} elseif ( 'js' === $kind ) {
			self::js( $text, $found );
		} elseif ( 'class' === $kind ) {
			self::classes( $text, $found );
		} else {
			self::html( $text, $found );
		}
		return array_values( $found );
	}

	private static function blank(): array {
		return array( 'status' => 'none', 'cursor' => 0, 'scanned' => 0, 'total' => 0, 'findings' => array(), 'classes' => array(), 'started' => 0, 'updated' => 0, 'finished' => 0, 'previous' => 'n/a' );
	}

	private static function available(): bool {
		return class_exists( __NAMESPACE__ . '\Core_Defaults' ) && Core_Defaults::active();
	}

	/** Sin carga automática: sólo lo leen la pantalla y la transición. */
	private static function save( array $state ): void {
		update_option( self::OPTION, $state, false );
	}

	private static function css( string $text, array &$found ): void {
		self::find( array_merge( self::COMMON, self::SELECTORS ), $text, $found );
	}

	private static function js( string $text, array &$found ): void {
		self::find( array_merge( self::COMMON, self::SELECTORS ), $text, $found );
		if ( isset( $found['class-list'] ) || ! preg_match_all( self::JS_CLASS_CALL, $text, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return;
		}
		foreach ( $calls as $call ) {
			preg_match_all( '/(["\'`])(.*?)\1/', $call[1][0], $strings );
			foreach ( $strings[2] as $classes ) {
				foreach ( preg_split( '/\s+/', trim( $classes ) ) as $token ) {
					if ( preg_match( self::FA_TOKEN, $token ) || preg_match( self::PREFIX_TOKEN, $token ) || preg_match( self::EICON_TOKEN, $token ) ) {
						$found['class-list'] = array( 'pattern' => 'class-list', 'excerpt' => self::excerpt( $text, $call[0][1] ) );
						return;
					}
				}
			}
		}
	}

	private static function classes( string $text, array &$found ): void {
		foreach ( preg_split( '/[\s,]+/', trim( $text ) ) as $token ) {
			$pattern = preg_match( self::FA_TOKEN, $token ) ? 'fa-class' : ( preg_match( self::PREFIX_TOKEN, $token ) ? 'fa-prefix' : ( preg_match( self::EICON_TOKEN, $token ) ? 'eicons' : ( false !== stripos( $token, 'elementor-icons' ) ? 'elementor-icons' : '' ) ) );
			if ( '' !== $pattern && ! isset( $found[ $pattern ] ) ) {
				$found[ $pattern ] = array( 'pattern' => $pattern, 'excerpt' => self::excerpt( $text, (int) stripos( $text, $token ) ) );
			}
		}
	}

	private static function html( string $text, array &$found ): void {
		self::find( self::COMMON, $text, $found );
		// Clases de cualquier etiqueta: `fa`, `fas`… como palabra completa.
		if ( preg_match_all( '/\bclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $text, $attrs, PREG_SET_ORDER ) ) {
			foreach ( $attrs as $attr ) {
				self::classes( $attr[1] . ' ' . ( $attr[2] ?? '' ) . ' ' . ( $attr[3] ?? '' ), $found );
			}
		}
		// Hojas y estilos en línea como CSS; scripts y manejadores como JavaScript.
		if ( preg_match_all( '#<style\b[^>]*>(.*?)</style>#is', $text, $blocks ) ) {
			foreach ( $blocks[1] as $css ) {
				self::css( $css, $found );
			}
		}
		if ( preg_match_all( '/\bstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $text, $styles, PREG_SET_ORDER ) ) {
			foreach ( $styles as $style ) {
				self::css( $style[1] . ' ' . ( $style[2] ?? '' ), $found );
			}
		}
		if ( preg_match_all( '#<script\b[^>]*>(.*?)</script>#is', $text, $scripts ) ) {
			foreach ( $scripts[1] as $js ) {
				self::js( $js, $found );
			}
		}
		if ( preg_match_all( '/\bon[a-z]+\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $text, $handlers, PREG_SET_ORDER ) ) {
			foreach ( $handlers as $handler ) {
				self::js( $handler[1] . ' ' . ( $handler[2] ?? '' ), $found );
			}
		}
	}

	/** Primer resultado de cada patrón. Un texto que la expresión no puede recorrer cuenta como hallazgo. */
	private static function find( array $patterns, string $text, array &$found ): void {
		foreach ( $patterns as $pattern => $regex ) {
			if ( isset( $found[ $pattern ] ) ) {
				continue;
			}
			$hit = preg_match( $regex, $text, $m, PREG_OFFSET_CAPTURE );
			if ( false === $hit ) {
				$found['unreadable'] = array( 'pattern' => 'unreadable', 'excerpt' => self::excerpt( $text, 0 ) );
			} elseif ( $hit ) {
				$found[ $pattern ] = array( 'pattern' => $pattern, 'excerpt' => self::excerpt( $text, (int) $m[0][1] ) );
			}
		}
	}

	/** Hasta 120 caracteres alrededor del hallazgo, en una línea. Se guarda tal cual: la pantalla lo escapa. */
	private static function excerpt( string $text, int $offset ): string {
		$start = max( 0, $offset - 40 );
		while ( $start > 0 && 0x80 === ( ord( $text[ $start ] ) & 0xC0 ) ) {
			$start--;
		}
		$piece = substr( $text, $start, 600 );
		$piece = trim( (string) preg_replace( '/[\x00-\x20\x7F]+/', ' ', $piece ) );
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$piece = wp_check_invalid_utf8( $piece, true );
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $piece, 0, self::EXCERPT, 'UTF-8' ) : substr( $piece, 0, self::EXCERPT );
	}

	/** Registra hallazgos: cuenta todos, guarda los 50 primeros y no repite fuente, id y patrón. */
	private static function check( array &$state, string $source, $id, string $text, string $kind ): void {
		foreach ( self::scan_text( $text, $kind ) as $match ) {
			self::add( $state, $source, $id, $match['pattern'], $match['excerpt'] );
		}
	}

	private static function add( array &$state, string $source, $id, string $pattern, string $excerpt ): void {
		foreach ( $state['findings'] as $finding ) {
			if ( $finding['source'] === $source && $finding['id'] === $id && $finding['pattern'] === $pattern ) {
				return;
			}
		}
		$state['total'] = (int) $state['total'] + 1;
		if ( count( $state['findings'] ) < self::MAX_FINDINGS ) {
			$state['findings'][] = array( 'source' => $source, 'id' => $id, 'pattern' => $pattern, 'excerpt' => self::excerpt( $excerpt, 0 ) );
		}
	}

	/**
	 * Fuentes que no son entradas: se revisan al empezar. Sin id (salvo el número de cada widget clásico): la pantalla
	 * muestra fuente, id y extracto, y nunca el nombre interno de una opción.
	 */
	private static function scan_globals( array &$state ): void {
		if ( function_exists( 'wp_get_custom_css' ) ) {
			self::check( $state, 'custom_css', '', (string) wp_get_custom_css(), 'css' );
		}
		if ( class_exists( '\Digitalisimo_Integrations_SEO_Resolver' ) && method_exists( '\Digitalisimo_Integrations_SEO_Resolver', 'option' ) ) {
			$seo = \Digitalisimo_Integrations_SEO_Resolver::option( 'perf_custom_css' );
			self::check( $state, 'seo_css', '', is_string( $seo ) ? $seo : '', 'css' );
		}
		foreach ( self::WIDGET_OPTIONS as $option ) {
			$instances = get_option( $option, array() );
			if ( ! is_array( $instances ) ) {
				continue;
			}
			foreach ( $instances as $number => $instance ) {
				if ( is_array( $instance ) ) {
					self::check( $state, $option, (int) $number, implode( "\n", self::strings( $instance ) ), 'html' );
				}
			}
		}
		if ( null !== get_option( self::NEEDS_UPDATE, null ) ) {
			self::add( $state, 'option', '', 'fa4-pending', 'La actualización de los iconos de Font Awesome 4 del constructor está pendiente.' );
		}
		if ( 'yes' === get_option( self::FA4_SHIM, '' ) ) {
			self::add( $state, 'option', '', 'fa4-shim', 'El constructor carga la compatibilidad con Font Awesome 4.' );
		}
	}

	/** Entradas siguientes al cursor, por id, sin revisiones, papelera ni borradores automáticos. */
	private static function posts_after( int $cursor, int $limit ): array {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE ID > %d AND post_type <> %s AND post_status NOT IN (%s, %s) ORDER BY ID ASC LIMIT %d", $cursor, 'revision', 'trash', 'auto-draft', $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	private static function scan_posts( array $rows, array &$state ): void {
		if ( ! $rows ) {
			return;
		}
		global $wpdb;
		$ids    = array_map( 'intval', array_column( $rows, 'ID' ) );
		$in     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$keys   = implode( ',', array_fill( 0, count( self::SMALL_META ), '%s' ) );
		$meta   = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($keys) AND post_id IN ($in)", array_merge( self::SMALL_META, $ids ) ), ARRAY_A ) as $row ) {
			$meta[ (int) $row['post_id'] ][ $row['meta_key'] ] ??= $row['meta_value'];
		}
		$with_data = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ($in)", array_merge( array( self::DATA_META ), $ids ) ) ) );
		$public    = function_exists( 'get_post_types' ) ? (array) get_post_types( array( 'public' => true ) ) : array( 'post' => 'post', 'page' => 'page' );
		$kit       = (int) get_option( 'elementor_active_kit', 0 );
		foreach ( $rows as $row ) {
			$id   = (int) $row['ID'];
			$type = (string) $row['post_type'];
			$m    = $meta[ $id ] ?? array();
			if ( isset( $public[ $type ] ) || in_array( $type, $public, true ) || in_array( $type, self::CONTENT_TYPES, true ) ) {
				self::check( $state, 'post_content', $id, (string) $row['post_content'], 'html' );
			}
			if ( 'nav_menu_item' === $type ) {
				self::check( $state, 'menu_title', $id, (string) $row['post_title'], 'html' );
				$classes = maybe_unserialize( $m['_menu_item_classes'] ?? '' );
				self::check( $state, 'menu_classes', $id, implode( ' ', array_filter( (array) $classes, 'is_string' ) ), 'class' );
			}
			if ( isset( $m['_elementor_code'] ) ) {
				self::check( $state, 'snippet', $id, (string) $m['_elementor_code'], 'html' );
			}
			$page = maybe_unserialize( $m['_elementor_page_settings'] ?? '' );
			if ( is_array( $page ) && isset( $page['custom_css'] ) && is_string( $page['custom_css'] ) ) {
				self::check( $state, $id === $kit ? 'kit_css' : 'page_css', $id, $page['custom_css'], 'css' );
			}
			if ( in_array( $id, $with_data, true ) ) {
				$raw  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $id, self::DATA_META ) );
				$data = json_decode( $raw, true );
				if ( is_array( $data ) ) {
					self::walk( $data, $id, $state );
				} elseif ( '' !== trim( $raw ) ) {
					self::check( $state, 'element_text', $id, $raw, 'html' );
				}
				unset( $raw, $data );
			}
			$state['cursor']  = $id;
			$state['scanned'] = (int) $state['scanned'] + 1;
		}
	}

	/** Elementos del constructor, a cualquier profundidad. */
	private static function walk( array $elements, int $id, array &$state ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				self::settings( $element['settings'], $id, $state );
			}
			if ( 'widget' === ( $element['elType'] ?? '' ) && ! empty( $element['widgetType'] ) && is_string( $element['widgetType'] ) ) {
				self::third_party( $element['widgetType'], $id, $state );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk( $element['elements'], $id, $state );
			}
		}
	}

	/**
	 * Ajustes de un elemento (también repetidores). Un valor del control de iconos (`value` + `library`) se pinta en
	 * línea y no cuenta; una clave `icon` de texto sin `__fa4_migrated` es un icono antiguo que sigue pintando `<i>`.
	 */
	private static function settings( array $settings, int $id, array &$state ): void {
		if ( isset( $settings['library'] ) && array_key_exists( 'value', $settings ) ) {
			return;
		}
		$migrated = ! empty( $settings['__fa4_migrated'] );
		foreach ( $settings as $key => $value ) {
			if ( '__fa4_migrated' === $key || '__globals__' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				self::settings( $value, $id, $state );
				continue;
			}
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}
			if ( 'icon' === $key ) {
				if ( ! $migrated ) {
					self::add( $state, 'fa4', $id, 'fa4-icon', $value );
				}
				continue;
			}
			// El valor antiguo de un control ya migrado no se pinta.
			if ( $migrated && preg_match( '/^\s*(?:fa[srlbdt]?\s+)?fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*\s*$/i', $value ) ) {
				continue;
			}
			if ( 'custom_css' === $key ) {
				self::check( $state, 'element_css', $id, $value, 'css' );
			} elseif ( is_string( $key ) && preg_match( '/(?:^|_)css_classes$/', $key ) ) {
				self::check( $state, 'element_class', $id, $value, 'class' );
			} else {
				self::check( $state, 'element_text', $id, $value, 'html' );
			}
		}
	}

	/** Un hallazgo por clase de widget ajena a los espacios propios; un filtro puede declararla compatible. */
	private static function third_party( string $type, int $id, array &$state ): void {
		$class = self::widget_class( $type );
		if ( '' === $class || in_array( $class, $state['classes'], true ) ) {
			return;
		}
		foreach ( self::OWN_NAMESPACES as $prefix ) {
			if ( 0 === strpos( $class, $prefix ) ) {
				return;
			}
		}
		$state['classes'][] = $class;
		/**
		 * Si un widget de terceros puede depender de los iconos de fuente. `false` lo declara compatible con los
		 * iconos en línea.
		 *
		 * @param bool   $finding Por defecto true.
		 * @param string $class   Clase del widget.
		 * @param string $type    Nombre del widget.
		 */
		if ( apply_filters( 'digitalisimo_elements_icon_scan_third_party', true, $class, $type ) ) {
			self::add( $state, 'third_party', $id, 'third-party', $type . ' (' . $class . ')' );
		}
	}

	private static function widget_class( string $type ): string {
		if ( isset( self::$classes[ $type ] ) ) {
			return self::$classes[ $type ];
		}
		$class = '';
		if ( class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->widgets_manager ) && is_object( \Elementor\Plugin::$instance->widgets_manager ) ) {
			$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type );
			$class  = is_object( $widget ) ? ltrim( get_class( $widget ), '\\' ) : '';
		}
		self::$classes[ $type ] = $class;
		return $class;
	}

	/** Cadenas de un arreglo, a cualquier profundidad. */
	private static function strings( array $value ): array {
		$out = array();
		array_walk_recursive(
			$value,
			static function ( $item ) use ( &$out ) {
				if ( is_string( $item ) ) {
					$out[] = $item;
				}
			}
		);
		return $out;
	}
}
