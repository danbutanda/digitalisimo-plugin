<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';
require_once __DIR__ . '/class-context.php';
require_once __DIR__ . '/class-report.php';

/**
 * Marcadores de K (`digitalisimo-unconverted`) que guardan un widget de Element Pack o de Prime Slider (F8-D19, V.12).
 *
 * Un marcador conserva lo que el sitio no sabe pintar: un kit importado sin Element Pack activo, una plantilla suelta,
 * o un widget que la propia Migración no pudo convertir (sin adaptador, destino no registrado, descarte declarado de
 * Prime Slider). Esta clase:
 *
 * - **convierte** su contenido con el filtro de K `digitalisimo_elements_unconverted_convert` (contrato en
 *   `ELE/modules/digitalisimo-import/kits/class-preserve.php`, `Preserve::convert()`), con los mismos convertidores que
 *   el resto de la migración: `Native::element()` (frente A), que a su vez llama a las extensiones (C) y a Prime Slider
 *   (E y F);
 * - en el **editor** la conversión es pura (V.7): `Context::begin( $post_id, 'editor' )`, sólo con `edit_post` sobre el
 *   documento y con DIGITALÍSIMO Elements ≥ 4.3.0.170; ningún convertidor crea medios, Loop Items, plantillas ni filas
 *   del registro (y una barrera lo garantiza: cualquier inserción de entrada o de archivo durante la conversión la
 *   anula), así que lo que necesitaría efectos se queda en su marcador para la etapa `markers`;
 * - en la **tarea** (`expand()`, desde `Migration::convert_document()`, siempre con `Context` en `run`) convierte los
 *   marcadores antes que el resto del documento, con su padre real, y deja el recuento del documento a
 *   `convert_document()` (V.12), así que el editor nunca borra al guardar un marcador creado o resuelto aquí;
 * - **clasifica** cada marcador por el mapa de Element Pack o de Prime Slider y por `target_ready()`, nunca por lo que
 *   devuelve la conversión: `convertible`, `blocked` (sin destino ahora) o `declared` (descarte declarado);
 * - lleva la etapa **`markers`** del runner (`stage()`): documentos por la aguja del marcador, revisiones incluidas.
 *
 * Esta clase se carga en cada petición (el filtro debe existir en el editor): no hace nada al cargarse y sólo carga
 * la conversión cuando el filtro o la tarea la necesitan.
 */
final class Markers {
	/** Filtro de K (`Module::FILTER_UNCONVERTED_CONVERT`). */
	const FILTER = 'digitalisimo_elements_unconverted_convert';
	/** Clase del marcador de K. */
	const PRESERVE = '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve';
	/** Tipo y ajuste del marcador (iguales a `Module::WIDGET` y `Preserve::SETTING`, sin cargar K). */
	const WIDGET  = 'digitalisimo-unconverted';
	const SETTING = 'digitalisimo_unconverted';
	/** Ajuste de los envoltorios que K crea para colocar un marcador (`Preserve::WRAPPER_SETTING`). */
	const WRAPPER_SETTING = 'digitalisimo_unconverted_wrapper';
	/** Aguja de la etapa (`_elementor_data` guardado tal cual: la carga del marcador va anidada, sin doble codificación). */
	const NEEDLE = '"widgetType":"digitalisimo-unconverted"';
	/** Agujas del original dentro de la carga (acotan la búsqueda a marcadores de Element Pack o de Prime Slider). */
	const PAYLOAD_NEEDLES = array( '"widgetType":"bdt-', '"widgetType":"fooevents-calendar"', '"widgetType":"lightbox"', '"widgetType":"prime-slider-' );
	/** Nombre de la etapa en el runner (listas de IDs, `Runner::ids()`). */
	const STAGE = 'markers';
	/** Padres donde un fragmento de Prime Slider (principal y hermanos) puede aplanarse sin romper la estructura. */
	const FRAGMENT_PARENTS = array( 'column', 'container' );
	/** Profundidad máxima que se recorre (la de K). */
	const MAX_DEPTH = 256;

	/** @var array<string,string> ID del original => `elType` de su padre efectivo, durante `expand()`. */
	private static $parents = array();
	/** @var bool `expand()` en curso: sólo entonces el filtro trabaja en modo `run`. */
	private static $expanding = false;
	/** @var bool La barrera de pureza detectó un intento de crear algo. */
	private static $tripped = false;
	/** @var int Marcadores convertidos por la última llamada a `expand()`. */
	public static $last = 0;
	/** @var array<string,string> Grupo de cada tipo, por petición. */
	private static $groups = array();
	/** @var array<string,string>|null Widgets de Element Pack sin adaptador (`unsupported.php`). */
	private static $unsupported = null;

	/* ---------------------------------------------------------------- Filtro de K */

	/**
	 * `digitalisimo_elements_unconverted_convert` (10, 3). Devuelve el elemento convertido o null (el marcador se queda).
	 *
	 * - `$result` no nulo: otro callback ya convirtió → se devuelve tal cual.
	 * - Sólo widgets de Element Pack (`bdt-*`, `fooevents-calendar`, `lightbox`) o de Prime Slider (`prime-slider-*`)
	 *   que `classify()` da por convertibles.
	 * - Un resultado que es otro marcador (destino ausente, descarte) devuelve null (C1-11).
	 * - En la tarea (`expand()`), con su documento y su padre; un fragmento de Prime Slider se conserva entero si su padre
	 *   lo admite (Native lo aplana después) y, si no, sólo su principal (`mk.fragment`).
	 * - Fuera de la tarea, conversión pura del editor: `edit_post`, Elements ≥ 4.3.0.170, nunca en el front, sólo el
	 *   principal y ningún efecto.
	 *
	 * @param mixed  $result   Lo que devolvió un callback anterior (null si ninguno).
	 * @param mixed  $original Elemento original guardado en el marcador.
	 * @param string $type     Tipo de la carga (`widget/<widgetType>`).
	 * @return array|null
	 */
	public static function convert( $result, $original = array(), $type = '' ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( ! is_array( $original ) ) {
			return null;
		}
		$widget = self::widget_of( $original );
		// El tipo de la carga y el original deben coincidir: una carga manipulada no decide nada (falla cerrado).
		if ( '' === $widget || ( '' !== (string) $type && 'widget/' . $widget !== (string) $type ) ) {
			return null;
		}
		if ( ! self::load() || 'convertible' !== self::group( $widget ) ) {
			return null;
		}
		$run = self::$expanding && Context::depth() > 0 && 'run' === Context::mode();
		if ( $run ) {
			return self::convert_run( $original, $widget, Context::post_id() );
		}
		return self::convert_pure( $original, $widget );
	}

	/** Conversión de la tarea: con efectos (Loop Items, imágenes de demostración, informe), en el documento en curso. */
	private static function convert_run( array $original, string $widget, int $post_id ) {
		$id     = isset( $original['id'] ) && is_scalar( $original['id'] ) ? (string) $original['id'] : '';
		$parent = (string) ( self::$parents[ $id ] ?? '' );
		// El original de un marcador nunca se convirtió: aunque el documento vaya en modo restringido (≤ 1.0.20), un
		// marcador es «algo heredado nuevo» y se convierte entero (V.3.4).
		$restricted         = Native::$restricted;
		Native::$restricted = false;
		Context::begin( $post_id, 'run' );
		try {
			list( $converted, $changes ) = Native::element( $original, $post_id, $parent );
		} catch ( \Throwable $e ) {
			return null;
		} finally {
			Context::end();
			Native::$restricted = $restricted;
		}
		$converted = self::accept( $converted, (int) $changes );
		if ( null === $converted ) {
			return null;
		}
		if ( '__fragment' === ( $converted['elType'] ?? '' ) && ! in_array( $parent, self::FRAGMENT_PARENTS, true ) ) {
			list( $converted, $dropped ) = self::main_of( $converted, $id );
			if ( $dropped ) {
				Report::add( $post_id, 'mk.fragment', array( 'widget' => $widget, 'element' => $id, 'count' => $dropped ) );
			}
		}
		return $converted;
	}

	/** Conversión pura del editor: sin efectos, sin informe y sólo para quien puede editar el documento. */
	private static function convert_pure( array $original, string $widget ) {
		$analysis = Context::depth() > 0 && 'analysis' === Context::mode();
		$post_id  = Context::depth() > 0 ? Context::post_id() : self::editor_post_id();
		if ( ! self::editor_allowed( $post_id ) ) {
			return null;
		}
		$id = isset( $original['id'] ) && is_scalar( $original['id'] ) ? (string) $original['id'] : '';
		Context::begin( $post_id, $analysis ? 'analysis' : 'editor' );
		try {
			$out = self::without_effects(
				static function () use ( $original, $post_id ) {
					return Native::element( $original, $post_id, '' );
				}
			);
		} catch ( \Throwable $e ) {
			$out = null;
		} finally {
			Context::end();
		}
		if ( self::$tripped || ! is_array( $out ) ) {
			// Habría creado algo (un Loop Item, una copia a Medios…): el marcador espera a la etapa `markers`.
			return null;
		}
		$converted = self::accept( $out[0] ?? null, (int) ( $out[1] ?? 0 ) );
		if ( null !== $converted && '__fragment' === ( $converted['elType'] ?? '' ) ) {
			// El editor no admite fragmentos: sólo el principal. El informe lo descarta (modo editor), pero queda dicho.
			list( $converted, $dropped ) = self::main_of( $converted, $id );
			if ( $dropped ) {
				Report::add( $post_id, 'mk.fragment', array( 'widget' => $widget, 'element' => $id, 'count' => $dropped ) );
			}
		}
		return $converted;
	}

	/** Un resultado utilizable: un elemento con cambios, que no es (ni empieza por) otro marcador. */
	private static function accept( $converted, int $changes ) {
		if ( ! is_array( $converted ) || $changes <= 0 || ! isset( $converted['elType'] ) || ! is_string( $converted['elType'] ) || '' === $converted['elType'] ) {
			return null;
		}
		if ( self::is_marker( $converted ) ) {
			return null;
		}
		if ( '__fragment' === $converted['elType'] ) {
			$parts = array_values( array_filter( (array) ( $converted['elements'] ?? array() ), 'is_array' ) );
			if ( ! $parts ) {
				return null;
			}
			foreach ( $parts as $part ) {
				if ( self::is_marker( $part ) ) {
					return null;
				}
			}
			$converted['elements'] = $parts;
		}
		return $converted;
	}

	/**
	 * Principal de un fragmento: el que conserva el ID del original o, si no, el primero (como `Converter`).
	 *
	 * @return array{0:array,1:int} principal y número de hermanos que se pierden.
	 */
	private static function main_of( array $fragment, string $id ): array {
		$parts = array_values( (array) ( $fragment['elements'] ?? array() ) );
		$main  = $parts[0];
		foreach ( $parts as $part ) {
			if ( '' !== $id && (string) ( $part['id'] ?? '' ) === $id ) {
				$main = $part;
				break;
			}
		}
		return array( $main, count( $parts ) - 1 );
	}

	/**
	 * ¿Puede convertirse en esta petición fuera de la tarea? Sólo en el editor (como `Preserve::is_editor_context()`:
	 * administración o REST, nunca el front ni el cron), con `edit_post` sobre el documento y con DIGITALÍSIMO Elements
	 * ≥ 4.3.0.170 (V.7). Sin documento conocido, no.
	 */
	private static function editor_allowed( int $post_id ): bool {
		if ( $post_id <= 0 || ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$preserve = self::PRESERVE;
		if ( class_exists( $preserve, false ) && method_exists( $preserve, 'is_editor_context' ) && ! $preserve::is_editor_context() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		return ! ( class_exists( __NAMESPACE__ . '\\Runner', false ) && null !== Runner::requirements() );
	}

	/**
	 * Documento del editor: `editor_post_id` de las peticiones AJAX del constructor, el del editor cargado, `post` de
	 * `post.php?action=elementor` o `get_the_ID()`; 0 si no se sabe (y entonces no se convierte).
	 */
	private static function editor_post_id(): int {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sólo identifica el documento; la capacidad se comprueba después.
		if ( isset( $_REQUEST['editor_post_id'] ) && is_scalar( $_REQUEST['editor_post_id'] ) && (int) $_REQUEST['editor_post_id'] > 0 ) {
			return (int) $_REQUEST['editor_post_id'];
		}
		if ( class_exists( '\\Elementor\\Plugin', false ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->editor ) && is_object( \Elementor\Plugin::$instance->editor ) && method_exists( \Elementor\Plugin::$instance->editor, 'get_post_id' ) ) {
			$id = (int) \Elementor\Plugin::$instance->editor->get_post_id();
			if ( $id > 0 ) {
				return $id;
			}
		}
		if ( isset( $_REQUEST['post'] ) && is_scalar( $_REQUEST['post'] ) && (int) $_REQUEST['post'] > 0 ) {
			return (int) $_REQUEST['post'];
		}
		// phpcs:enable
		return function_exists( 'get_the_ID' ) ? max( 0, (int) get_the_ID() ) : 0;
	}

	/**
	 * Ejecuta una conversión pura con una barrera: cualquier entrada nueva (Loop Item, plantilla, adjunto) o archivo
	 * subido durante la conversión se rechaza y la anula. Defensa además de `Context::pure()` en cada convertidor.
	 */
	private static function without_effects( callable $convert ) {
		self::$tripped = false;
		$post = array( __CLASS__, 'refuse_post' );
		$file = array( __CLASS__, 'refuse_file' );
		add_filter( 'wp_insert_post_empty_content', $post, PHP_INT_MAX, 2 );
		add_filter( 'wp_handle_sideload_prefilter', $file, PHP_INT_MAX );
		add_filter( 'wp_handle_upload_prefilter', $file, PHP_INT_MAX );
		try {
			return $convert();
		} finally {
			remove_filter( 'wp_insert_post_empty_content', $post, PHP_INT_MAX );
			remove_filter( 'wp_handle_sideload_prefilter', $file, PHP_INT_MAX );
			remove_filter( 'wp_handle_upload_prefilter', $file, PHP_INT_MAX );
		}
	}

	/**
	 * @internal Barrera de `without_effects()` (`wp_insert_post_empty_content`): ninguna entrada nueva.
	 *
	 * @param bool  $empty   Valor de WordPress.
	 * @param array $postarr Entrada que se iba a insertar.
	 */
	public static function refuse_post( $empty, $postarr = array() ) {
		self::$tripped = true;
		return true;
	}

	/**
	 * @internal Barrera de `without_effects()` (`wp_handle_*_prefilter`): ningún archivo nuevo en `uploads`.
	 *
	 * @param mixed $file Archivo que se iba a mover.
	 */
	public static function refuse_file( $file ) {
		self::$tripped = true;
		if ( is_array( $file ) ) {
			$file['error'] = __( 'La conversión del editor no copia archivos: se hará al pulsar Migrar.', 'digitalisimo-migration' );
		}
		return $file;
	}

	/* ---------------------------------------------------------------- Tarea */

	/**
	 * Convierte los marcadores de Element Pack y de Prime Slider de un documento con `Preserve::convert()` antes que el
	 * resto (V.12). Lo llama `Migration::convert_document()` dentro de su `Context::begin( $post_id, 'run' )`; en modo
	 * puro no hace nada. Anota `mk.converted` y los que quedan (`mk.blocked`, `mk.declared`).
	 *
	 * @param array $elements Árbol del documento.
	 * @param int   $post_id  Documento (o revisión).
	 * @return array{0:array,1:int} árbol y marcadores convertidos.
	 */
	public static function expand( array $elements, int $post_id ): array {
		self::$last = 0;
		if ( Context::pure() || ! self::can_expand() || ! self::load() ) {
			return array( $elements, 0 );
		}
		$before = self::classify( $elements );
		if ( ! $before['convertible'] && ! $before['blocked'] && ! $before['declared'] ) {
			return array( $elements, 0 );
		}
		// Una revisión se convierte igual, pero no se informa: lo diría dos veces su documento.
		$report = ! ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) );
		$count  = 0;
		if ( $before['convertible'] ) {
			self::ensure_filter();
			self::$parents = array();
			self::collect_parents( $elements, '', 0 );
			$converted       = array();
			$preserve        = self::PRESERVE;
			self::$expanding = true;
			try {
				$out = $preserve::convert( $elements, $converted );
			} finally {
				self::$expanding = false;
				self::$parents   = array();
			}
			$types = array();
			foreach ( (array) $converted as $type ) {
				$widget = 0 === strpos( (string) $type, 'widget/' ) ? substr( (string) $type, 7 ) : '';
				if ( Translator::is_known_id( $widget ) ) {
					$types[ $widget ] = ( $types[ $widget ] ?? 0 ) + 1;
					++$count;
				}
			}
			foreach ( $report ? $types : array() as $widget => $n ) {
				Report::add( $post_id, 'mk.converted', array( 'widget' => $widget, 'count' => $n ) );
			}
			if ( $count ) {
				$elements = Native::flatten( is_array( $out ) ? $out : $elements );
			}
		}
		// Lo que queda en su marcador: sin destino ahora (también lo que no pudo convertirse) o descarte declarado.
		$after = $count ? self::classify( $elements ) : $before;
		foreach ( $report ? array( 'mk.blocked' => array_merge( $after['blocked'], $after['convertible'] ), 'mk.declared' => $after['declared'] ) : array() as $code => $list ) {
			foreach ( array_count_values( $list ) as $widget => $n ) {
				Report::add( $post_id, $code, array( 'widget' => (string) $widget, 'count' => (int) $n ) );
			}
		}
		self::$last = $count;
		return array( $elements, $count );
	}

	/** ¿Están el marcador de K y su conversión? (Sin K no hay marcadores que convertir.) */
	private static function can_expand(): bool {
		$preserve = self::PRESERVE;
		return class_exists( $preserve ) && method_exists( $preserve, 'convert' ) && method_exists( $preserve, 'info' );
	}

	/** El filtro de K apunta a esta clase (lo hace el arranque; WP-CLI o una prueba pueden no haberlo hecho). */
	private static function ensure_filter(): void {
		if ( function_exists( 'has_filter' ) && false === has_filter( self::FILTER, array( __CLASS__, 'convert' ) ) ) {
			add_filter( self::FILTER, array( __CLASS__, 'convert' ), 10, 3 );
		}
	}

	/**
	 * Padre efectivo de cada marcador: el `elType` de su padre o, si el padre es un envoltorio de K que sólo contiene
	 * marcadores (desaparece al convertirlos), el del padre de éste. Decide, por ejemplo, la familia K de Prime Slider.
	 */
	private static function collect_parents( array $elements, string $parent, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( self::is_marker( $element ) ) {
				$original = self::payload( $element );
				$id       = is_array( $original ) && isset( $original['id'] ) && is_scalar( $original['id'] ) ? (string) $original['id'] : '';
				if ( '' !== $id ) {
					self::$parents[ $id ] = $parent;
				}
				continue;
			}
			if ( empty( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
				continue;
			}
			$type     = (string) ( $element['elType'] ?? '' );
			$collapse = ! empty( $element['settings'][ self::WRAPPER_SETTING ] ) && self::only_markers( $element['elements'] );
			self::collect_parents( $element['elements'], $collapse ? $parent : $type, $depth + 1 );
		}
	}

	private static function only_markers( array $elements ): bool {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || ( ! self::is_marker( $element ) && ( empty( $element['settings'][ self::WRAPPER_SETTING ] ) || empty( $element['elements'] ) || ! self::only_markers( (array) $element['elements'] ) ) ) ) {
				return false;
			}
		}
		return (bool) $elements;
	}

	/**
	 * Etapa `markers` del runner (V.12, C13). Recorre los documentos con la aguja del marcador (revisiones incluidas) y
	 * convierte, con `Migration::convert_document()` (copia, marca F8 y recuento), los que tienen algún marcador
	 * convertible. Un documento por entrada: el runner guarda el estado entre una y otra, así que una caída se atribuye
	 * al documento en curso (`markers_current`), que se salta y se apunta en `failed` (como `Runner::loop()`), y la
	 * etapa sigue. La primera entrada sólo calcula la lista (una búsqueda por etapa, `Runner::ids()`).
	 *
	 * Cursor en `markers_cursor`; al terminar, `markers_done = true` (el runner limpia ambos y pasa a `platform`).
	 */
	public static function stage( array $state, float $deadline ): array {
		if ( ! self::can_expand() || ! self::load() || ! self::load_migration() ) {
			return self::done( $state );
		}
		$ids            = Runner::ids( self::STAGE, array( __CLASS__, 'documents' ) );
		$state['total'] = count( $ids );
		if ( ! isset( $state['markers_cursor'] ) ) {
			$state['markers_cursor']  = 0;
			$state['markers_current'] = self::next_id( $ids, 0 );
			return $state['markers_current'] ? $state : self::done( $state );
		}
		$next = self::next_id( $ids, (int) $state['markers_cursor'] );
		if ( ! $next ) {
			return self::done( $state );
		}
		$state['counts'] = (array) ( $state['counts'] ?? array() ) + array( 'markers' => 0, 'failed' => array() );
		if ( (int) ( $state['markers_attempts'] ?? 1 ) > 1 && (int) ( $state['markers_current'] ?? 0 ) === $next ) {
			// La entrada anterior se cortó con este documento: se salta y queda en «failed» (core.document_failed).
			if ( ! in_array( $next, (array) $state['counts']['failed'], true ) ) {
				$state['counts']['failed'][] = $next;
			}
		} elseif ( self::convert_post( $next ) ) {
			$state['counts']['markers'] = (int) $state['counts']['markers'] + 1;
		}
		$state['markers_cursor'] = $next;
		$state['processed']      = (int) ( $state['processed'] ?? 0 ) + 1;
		$following               = self::next_id( $ids, $next );
		if ( ! $following ) {
			return self::done( $state );
		}
		$state['markers_current'] = $following;
		return $state;
	}

	private static function done( array $state ): array {
		unset( $state['markers_current'] );
		$state['markers_done'] = true;
		return $state;
	}

	private static function next_id( array $ids, int $after ): int {
		foreach ( $ids as $id ) {
			if ( (int) $id > $after ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/** Convierte un documento si tiene algún marcador convertible. Devuelve si convirtió alguno. */
	private static function convert_post( int $post_id ): bool {
		$raw = get_metadata( 'post', $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || false === strpos( $raw, self::NEEDLE ) ) {
			return false;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! self::classify( $data )['convertible'] ) {
			return false;
		}
		self::$last = 0;
		Migration::convert_document( $post_id );
		return self::$last > 0;
	}

	/**
	 * Documentos (y revisiones) con algún marcador cuyo original es de Element Pack o de Prime Slider (aguja del
	 * marcador y del original, una sola consulta, sin `DISTINCT`: hay una fila `_elementor_data` por documento).
	 *
	 * @return int[]
	 */
	public static function documents(): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$args = array( '_elementor_data', '%' . $wpdb->esc_like( self::NEEDLE ) . '%' );
		foreach ( self::PAYLOAD_NEEDLES as $needle ) {
			$args[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s AND ( " . implode( ' OR ', array_fill( 0, count( self::PAYLOAD_NEEDLES ), 'meta_value LIKE %s' ) ) . ' ) ORDER BY post_id';
		$ids = array_values( array_unique( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- marcadores fijos; una consulta por etapa.
		sort( $ids );
		return $ids;
	}

	/* ---------------------------------------------------------------- Clasificación */

	/**
	 * Clasifica los marcadores de Element Pack y de Prime Slider de unos elementos (marcadores sueltos o un árbol
	 * entero, sin entrar en las cargas), por el mapa y por la disponibilidad del destino (V.12):
	 *
	 * - `convertible`: Element Pack con mapa y destino registrado (o desconocido, sin el constructor cargado); Prime
	 *   Slider con mapa no descartado y destino o respaldo utilizable (`Dynamic::state()` en las familias E, F, G y L);
	 * - `blocked`: sin destino ahora (destino no registrado, mapa ausente, `Dynamic::state()` = `blocked`);
	 * - `declared`: descarte declarado (widget de Element Pack sin adaptador de `unsupported.php`, mapa `DESCARTAR` de
	 *   Prime Slider, o `Dynamic::state()` = `declared`, como event-calendar sin The Events Calendar).
	 *
	 * Los marcadores de otros plugins no cuentan. Cada lista lleva un `widgetType` por marcador (se repite).
	 *
	 * @return array{convertible:string[], blocked:string[], declared:string[]}
	 */
	public static function classify( array $elements ): array {
		$out = array( 'convertible' => array(), 'blocked' => array(), 'declared' => array() );
		if ( ! self::load() ) {
			return $out;
		}
		self::walk_markers(
			$elements,
			static function ( array $marker ) use ( &$out ) {
				$original = self::payload( $marker );
				$widget   = is_array( $original ) ? self::widget_of( $original ) : '';
				if ( '' !== $widget ) {
					$out[ self::group( $widget ) ][] = $widget;
				}
			},
			0
		);
		return $out;
	}

	/** Grupo de un tipo (`convertible`, `blocked` o `declared`), una vez por petición. */
	private static function group( string $widget ): string {
		if ( ! isset( self::$groups[ $widget ] ) ) {
			self::$groups[ $widget ] = Translator::is_prime_slider_id( $widget ) ? self::prime_slider_group( $widget ) : self::element_pack_group( $widget );
		}
		return self::$groups[ $widget ];
	}

	private static function element_pack_group( string $widget ): string {
		if ( ! Translator::map( $widget ) ) {
			// Sin adaptador: declarado si la tabla de decisiones lo descarta (con su alternativa); un ID que no está en
			// ninguna lista no tiene destino conocido.
			return isset( self::unsupported()[ $widget ] ) ? 'declared' : 'blocked';
		}
		return false === Native::target_ready( Translator::target( $widget ) ) ? 'blocked' : 'convertible';
	}

	private static function prime_slider_group( string $widget ): string {
		$maps = __NAMESPACE__ . '\\Prime_Slider\\Maps';
		$map  = class_exists( $maps ) && method_exists( $maps, 'get' ) ? $maps::get( $widget ) : null;
		if ( ! is_array( $map ) ) {
			return 'blocked';
		}
		if ( in_array( strtoupper( (string) ( $map['decision'] ?? '' ) ), array( 'DESCARTAR', 'DISCARD' ), true ) ) {
			return 'declared';
		}
		$dynamic = __NAMESPACE__ . '\\Prime_Slider\\Dynamic';
		if ( in_array( (string) ( $map['family'] ?? '' ), array( 'E', 'F', 'G', 'L' ), true ) ) {
			if ( ! class_exists( $dynamic ) || ! method_exists( $dynamic, 'state' ) ) {
				return 'blocked';
			}
			$state = (string) $dynamic::state( $widget );
			return 'declared' === $state ? 'declared' : ( 'blocked' === $state ? 'blocked' : 'convertible' );
		}
		// Familias de E: destino o respaldo utilizable (como `Converter::describe()`).
		$builder = __NAMESPACE__ . '\\Prime_Slider\\Builder';
		if ( ! class_exists( $builder ) || ! method_exists( $builder, 'usable' ) ) {
			return 'blocked';
		}
		$target   = (string) ( $map['target'] ?? '' );
		$fallback = (string) ( $map['fallback'] ?? '' );
		$primary  = 'container' === $target || ( '' !== $target && $builder::usable( $target ) );
		return $primary || ( '' !== $fallback && $builder::usable( $fallback ) ) ? 'convertible' : 'blocked';
	}

	/** @return array<string,string> Widgets de Element Pack sin adaptador y su motivo. */
	private static function unsupported(): array {
		if ( null === self::$unsupported ) {
			$file              = __DIR__ . '/unsupported.php';
			self::$unsupported = is_file( $file ) ? (array) require $file : array();
		}
		return self::$unsupported;
	}

	/** Olvida lo calculado en la petición (pruebas, o tras cambiar los plugins activos). */
	public static function reset(): void {
		self::$groups    = array();
		self::$parents   = array();
		self::$expanding = false;
		self::$tripped   = false;
		self::$last      = 0;
	}

	/* ---------------------------------------------------------------- Lectura de marcadores */

	/** Recorre los marcadores de un árbol (sin entrar en sus cargas). */
	private static function walk_markers( array $elements, callable $callback, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( self::is_marker( $element ) ) {
				$callback( $element );
				continue;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk_markers( $element['elements'], $callback, $depth + 1 );
			}
		}
	}

	private static function is_marker( $element ): bool {
		return is_array( $element ) && 'widget' === ( $element['elType'] ?? '' ) && self::WIDGET === ( $element['widgetType'] ?? '' );
	}

	/** El original de un marcador (con K, por `Preserve::info()`; sin K, por su forma pública). */
	private static function payload( array $marker ): ?array {
		$preserve = self::PRESERVE;
		if ( class_exists( $preserve, false ) && method_exists( $preserve, 'info' ) ) {
			$info = $preserve::info( $marker );
			return is_array( $info ) && is_array( $info['element'] ?? null ) ? $info['element'] : null;
		}
		$element = $marker['settings'][ self::SETTING ]['element'] ?? null;
		return is_array( $element ) ? $element : null;
	}

	/** `widgetType` de Element Pack o de Prime Slider de un original, o ''. */
	private static function widget_of( array $original ): string {
		$type = isset( $original['widgetType'] ) && is_string( $original['widgetType'] ) ? $original['widgetType'] : '';
		return 'widget' === ( $original['elType'] ?? '' ) && Translator::is_known_id( $type ) ? $type : '';
	}

	/** Carga la conversión (Native y sus dependencias) sólo cuando hace falta. */
	private static function load(): bool {
		if ( ! class_exists( __NAMESPACE__ . '\\Native', false ) ) {
			require_once __DIR__ . '/class-native.php';
		}
		return class_exists( __NAMESPACE__ . '\\Native', false );
	}

	private static function load_migration(): bool {
		if ( ! class_exists( __NAMESPACE__ . '\\Migration', false ) ) {
			require_once __DIR__ . '/class-migration.php';
		}
		return class_exists( __NAMESPACE__ . '\\Migration', false ) && class_exists( __NAMESPACE__ . '\\Runner', false ) && method_exists( __NAMESPACE__ . '\\Runner', 'ids' ) && is_callable( array( __NAMESPACE__ . '\\Runner', 'ids' ) );
	}
}
