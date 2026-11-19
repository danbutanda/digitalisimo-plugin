<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Widgets de PRO (docs/PRO_WIDGETS_OBJECTIVE.md, C0): inventario por widget y por familia, y conversión a los widgets
 * propios de DIGITALÍSIMO Elements de los que ya tienen un mapa listo. Toda la traducción vive en Elements
 * (`Digitalisimo\Elements\Convert\`: Catalog, Registry, Translator, Converter); aquí sólo se orquesta, igual que la
 * etapa de alias (Aliases): copia del original (Migration::BACKUP) antes de escribir, marca F8 con la huella de lo
 * escrito para que `--revert` no pise lo editado, cachés del documento vacías y códigos `pro.*` en el informe.
 *
 * Sin el núcleo de conversión de Elements (una versión anterior), el análisis dice que hay que actualizarlo y nada
 * se convierte. Lo que no tiene mapa listo queda como estaba: los widgets de PRO siguen registrados y se ven igual.
 *
 * Alcance:
 * - El inventario recorre por lotes (INVENTORY_BATCH filas por consulta, con cursor) todo documento con
 *   `_elementor_data`: entradas, páginas, plantillas de cualquier tipo, popups y Loop Items. Revisiones y
 *   autoguardados se cuentan aparte. En memoria sólo quedan los recuentos.
 * - La conversión toma sólo documentos que no son revisiones: los widgets de PRO siguen registrados, así que una
 *   revisión antigua se sigue viendo y, si se restaura, Migrar la convierte la próxima vez.
 * - Widget global (C9, `Convert\Globals` de Elements): cada instancia con plantilla válida pasa al widget global propio
 *   (`digitalisimo-global`, `Globals::link()`), que sigue leyendo la plantilla al pintar: editarla cambia todas las
 *   páginas que la usan, como en PRO. La plantilla, que es un documento de la biblioteca, la convierte la misma etapa;
 *   nunca se borra, y `--revert` devuelve la instancia (copia original). Con una versión de Elements sin el widget global
 *   propio, la instancia se sustituye por su contenido (`Globals::tree()`, como hasta 4.3.0.179). Una instancia sin
 *   plantilla se queda como está y el informe lo dice (`pro.global_missing`).
 * - CSS que nombra los widgets de PRO desde fuera de ellos (contenedores y secciones, la página, el kit activo y el CSS
 *   adicional del Personalizador): el traductor sólo traduce el CSS del propio widget, así que, tras cada regla que
 *   nombra las clases de un widget de PRO con mapa listo, se añade su copia con las clases del widget propio
 *   (`Convert\Converter::external_css()`/`tree_css()` de Elements). La regla original se queda. Lo hace la conversión de
 *   cada documento y la etapa `pro_css` (documentos ya convertidos o sin widgets de PRO cuyo CSS los nombra, el kit y el
 *   Personalizador). El CSS del árbol va en la copia del documento (Migration::BACKUP); el de la página, del kit y del
 *   Personalizador, en CSS_BACKUP, que `--revert` devuelve si nadie lo cambió después (`revert_css()`). El CSS
 *   adicional se escribe sin tocar su fuente preprocesada (Sass/LESS de un plugin). Cubre también el CSS del sitio que
 *   nombraba clases de los listados y formularios de PRO (`.elementor-post__title`, `.elementor-widget-form`; corrección
 *   de fidelidad de C5 y C6). Lo que nombre esas clases en archivos del tema o en fragmentos de código no se toca.
 * - Ajustes que no son del widget de PRO:
 *   - los de Element Pack (`Native::ELEMENT_PREFIXES`) se apartan antes del traductor de PRO y vuelven al elemento
 *     después: son de la etapa de Element Pack, que los convierte (o los informa) igual que en cualquier otro elemento,
 *     y nunca salen como ajustes de PRO sin reconocer;
 *   - los inertes y las condiciones de visualización de otros plugins los resuelve el traductor de Elements
 *     (`pro.inert`, y `pro.condition_kept` cuando una condición encendida deja el elemento como widget de PRO). Con un
 *     Elements sin esa corrección, una condición encendida que sale como `pro.unmapped` también deja el elemento como
 *     estaba: nada oculto se vuelve visible.
 * - Recuperación (`recover_document()`): en un documento convertido por una versión anterior, lo que esa versión no
 *   pasó al widget propio y esta sí pasa vuelve desde la copia guardada antes de convertir, sólo donde falta (nunca
 *   cambia un valor). Una condición de otro plugin sólo vuelve si el elemento coincide con la traducción de su copia (la
 *   copia puede ser anterior a la conversión: la huella F8 no basta); encendida, el elemento vuelve a ser el widget de
 *   PRO con sus hijos actuales. Si no se puede saber, el informe lo dice por documento. Lo revisado sin nada pendiente
 *   queda sellado (RECOVER_STAMP) y la etapa no lo vuelve a leer.
 * - Un widget propio que sólo se registra con otro plugin (WooCommerce, DEPENDENCIES) se informa como tal
 *   (`pro.target_dependency`), sin pedir que se actualice Elements.
 */
final class Pro {
	const CONVERTER = '\\Digitalisimo\\Elements\\Convert\\Converter';
	const CATALOG   = '\\Digitalisimo\\Elements\\Convert\\Catalog';
	const REGISTRY  = '\\Digitalisimo\\Elements\\Convert\\Registry';
	const GLOBALS   = '\\Digitalisimo\\Elements\\Convert\\Globals';
	/** Ajuste que deja en el elemento la sustitución de un widget global (su plantilla de origen). */
	const GLOBAL_KEY = '_digitalisimo_global';
	/** Marca que el traductor de Elements deja en los ajustes de un widget convertido. */
	const MARKER = '_digitalisimo_pro';
	/** Filas de `_elementor_data` por consulta del inventario y documentos por vuelta del análisis. */
	const INVENTORY_BATCH = 100;
	const ANALYSIS_MAX    = 500;
	const VERSION         = 1;
	/** Copia del CSS de la página, del kit o del Personalizador antes de añadirle las copias: `{time, where, before, hash}`. */
	const CSS_BACKUP = '_digitalisimo_pro_css_backup';
	/** Traductor de Elements (claves ajenas: `foreign()`, y el código que deja un elemento sin convertir). */
	const TRANSLATOR = '\\Digitalisimo\\Elements\\Convert\\Translator';
	/** Código de un elemento que se queda como widget de PRO por una condición de otro plugin encendida. */
	const KEPT = 'pro.condition_kept';
	/** Nombres de clave de condiciones o visibilidad (los mismos que `Translator::CONDITION_PATTERN` de Elements). */
	const CONDITION_PATTERN = '/condition|visibilit/i';
	/** Condiciones de visualización de otros plugins: prefijo => interruptor (los de `Translator::CONDITION_FAMILIES`). */
	const CONDITION_FAMILIES = array(
		'display_condition_'     => 'display_condition_enable',
		'pa_display_conditions_' => 'pa_display_conditions_switcher',
		'pa_condition_'          => 'pa_display_conditions_switcher',
		'pp_display_condition'   => 'pp_display_conditions_enable',
		'jedv_'                  => 'jedv_enabled',
	);
	/** Interruptores (`Translator::SWITCH_PATTERN`). */
	const SWITCH_PATTERN = '/_(enable|enabled|switch|switcher|show|active|on)$/';
	/**
	 * Sello de un documento ya revisado por recover_document() sin nada pendiente (valor: RECOVER_VERSION). La etapa no
	 * lo vuelve a leer ni a traducir; `--revert` lo quita. Sube RECOVER_VERSION cuando la recuperación cambie.
	 */
	const RECOVER_STAMP   = '_digitalisimo_pro_recovered';
	const RECOVER_VERSION = '2';
	/**
	 * Rasgos propios que la etapa de Element Pack escribe en cualquier elemento (visibilidad, figuras, enlace, información
	 * emergente, distintivo y relieve): la comparación de la recuperación los ignora y vuelven con el widget de PRO. Los
	 * demás `digitalisimo_*` son ajustes del widget propio (un pie de foto, un botón) y cuentan como edición.
	 */
	const EP_TRAITS = '/^digitalisimo_(visibility_|figures|element_link|tooltip|badge|relief)/';
	/**
	 * Widgets propios que sólo se registran con otro plugin: módulo de PRO (`Catalog::module()`) => nombre del plugin.
	 * Sin él, el widget de PRO tampoco se pinta y el documento se queda como está.
	 */
	const DEPENDENCIES = array( 'woocommerce' => 'WooCommerce' );

	/** @var array<string,string>|null widget de PRO => widget propio, de los mapas listos con destino registrado. */
	private static $ready = null;

	/** @var array<int,array|null> Contenido de las plantillas de widget global leídas en esta petición. */
	private static $templates = array();

	/** ¿Está el núcleo de conversión de DIGITALÍSIMO Elements? */
	public static function available(): bool {
		return class_exists( self::CONVERTER ) && class_exists( self::CATALOG ) && class_exists( self::REGISTRY );
	}

	/** Olvida los mapas leídos (pruebas, y tras cambiar los mapas de Elements). */
	public static function reset(): void {
		self::$ready     = null;
		self::$templates = array();
	}

	/** Destino del widget global de PRO: el widget global propio (vinculado a su plantilla) o, sin él, su contenido. */
	public static function global_target(): string {
		return self::globals() && method_exists( self::GLOBALS, 'link' ) && false !== self::registered( 'digitalisimo-global' ) ? 'digitalisimo-global' : 'su contenido';
	}

	/** ¿Sustituye esta versión de Elements los widgets globales por su contenido? */
	public static function globals(): bool {
		return self::available() && class_exists( self::GLOBALS );
	}

	/**
	 * `_elementor_data` de una plantilla de widget global del sitio (la biblioteca, tipo `widget`, fuera de la papelera),
	 * o null. Una vez por plantilla y petición.
	 */
	public static function template_content( $template_id ): ?array {
		$template_id = (int) $template_id;
		if ( array_key_exists( $template_id, self::$templates ) ) {
			return self::$templates[ $template_id ];
		}
		$data = null;
		$post = $template_id > 0 ? get_post( $template_id ) : null;
		if ( $post && 'elementor_library' === $post->post_type && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) && 'widget' === (string) get_metadata( 'post', $template_id, '_elementor_template_type', true ) ) {
			$raw  = get_metadata( 'post', $template_id, '_elementor_data', true );
			$raw  = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			$data = is_array( $raw ) ? $raw : null;
		}
		return self::$templates[ $template_id ] = $data;
	}

	/**
	 * Widgets de PRO que se convierten en este sitio: mapa listo y widget propio registrado (si Elementor no puede
	 * decirlo, como en las pruebas, cuenta como registrado; si dice que no, el widget se queda como está).
	 *
	 * @return array<string,string>
	 */
	public static function ready(): array {
		if ( null !== self::$ready ) {
			return self::$ready;
		}
		$out = array();
		if ( self::available() ) {
			// Los widgets registrados antes de leer los mapas: el del Formulario suma las claves de las acciones de envío
			// de terceros, que sólo existen con los widgets registrados (en un tic que empieza en esta etapa, nada lo hizo).
			self::registered( '' );
			$registry = self::REGISTRY;
			foreach ( (array) $registry::all() as $source => $map ) {
				$target = (string) ( $map['target'] ?? '' );
				if ( '' !== $target && false !== self::registered( $target ) ) {
					$out[ (string) $source ] = $target;
				}
			}
		}
		ksort( $out );
		self::$ready = $out;
		return $out;
	}

	/** Widgets de PRO con mapa listo cuyo widget propio no está registrado en este sitio (no se convierten). */
	public static function missing_targets(): array {
		$out = array();
		if ( self::available() ) {
			$registry = self::REGISTRY;
			foreach ( (array) $registry::all() as $source => $map ) {
				if ( ! isset( self::ready()[ (string) $source ] ) ) {
					$out[ (string) $source ] = (string) ( $map['target'] ?? '' );
				}
			}
		}
		return $out;
	}

	/**
	 * Plugin que falta para que se registre el widget propio de un widget de PRO (`DEPENDENCIES`), o '' si no depende de
	 * ninguno o está activo.
	 */
	public static function missing_dependency( string $type ): string {
		if ( ! self::available() ) {
			return '';
		}
		$catalog = self::CATALOG;
		$module  = method_exists( $catalog, 'module' ) ? (string) $catalog::module( $type ) : '';
		if ( ! isset( self::DEPENDENCIES[ $module ] ) ) {
			return '';
		}
		if ( 'woocommerce' === $module && class_exists( 'WooCommerce', false ) && function_exists( 'WC' ) ) {
			return '';
		}
		return self::DEPENDENCIES[ $module ];
	}

	/** Código y detalle del informe de un widget de PRO cuyo widget propio no está registrado en este sitio. */
	private static function blocked_item( string $type ): array {
		$target = (string) ( self::missing_targets()[ $type ] ?? '' );
		$plugin = self::missing_dependency( $type );
		if ( '' !== $plugin ) {
			return array( 'pro.target_dependency', array( 'widget' => $type, 'target' => $target, 'name' => $plugin ) );
		}
		return array( 'pro.target_missing', array( 'widget' => $type, 'target' => $target ) );
	}

	/** ¿Registrado en Elementor? null si no puede saberse (sin Elementor cargado). */
	private static function registered( string $widget ): ?bool {
		if ( ! class_exists( '\\Elementor\\Plugin', false ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) || ! is_object( \Elementor\Plugin::$instance->widgets_manager ) || ! method_exists( \Elementor\Plugin::$instance->widgets_manager, 'get_widget_types' ) ) {
			return null;
		}
		return (bool) \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
	}

	/** Familia del programa (`C1`…`C9`) de un widget de PRO, o ''. */
	public static function family( string $type ): string {
		if ( ! self::available() ) {
			return '';
		}
		$catalog = self::CATALOG;
		return (string) $catalog::family( $type );
	}

	/** Nombre de una familia (`C3` → «Navegación y búsqueda»). */
	public static function family_label( string $family ): string {
		if ( ! self::available() ) {
			return $family;
		}
		$catalog = self::CATALOG;
		return defined( $catalog . '::FAMILIES' ) ? (string) ( constant( $catalog . '::FAMILIES' )[ $family ] ?? $family ) : $family;
	}

	/* ---------------------------------------------------------------- Documentos por convertir */

	/**
	 * Documentos (no revisiones) con algún widget de PRO que se convierte, desde el id $after y hasta $limit (0: todos).
	 *
	 * @return int[]
	 */
	public static function documents( $after = 0, $limit = 0 ): array {
		global $wpdb;
		$names = array_keys( self::ready() );
		if ( self::globals() ) {
			$names[] = 'global';
		}
		if ( ! $names ) {
			return array();
		}
		$like = array();
		foreach ( $names as $name ) {
			$like[] = '%' . $wpdb->esc_like( '"widgetType":"' . $name . '"' ) . '%';
		}
		$sql = "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND m.post_id > %d AND ( " . implode( ' OR ', array_fill( 0, count( $like ), 'm.meta_value LIKE %s' ) ) . ' ) ORDER BY m.post_id' . ( $limit ? ' LIMIT ' . max( 1, (int) $limit ) : '' );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array( (int) $after ), $like ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Documentos (no revisiones) desde el id $after con widgets convertidos desde PRO (su marca, `MARKER`), copia de la
	 * migración y sin el sello de esta recuperación, hasta $limit (0: todos): los que una versión anterior pudo convertir
	 * sin pasar todo (recover_document()). Sólo consultas de ids: nunca carga las metas de los documentos.
	 *
	 * @return int[]
	 */
	public static function recover_documents( $after = 0, $limit = 0 ): array {
		global $wpdb;
		if ( ! self::available() ) {
			return array();
		}
		$stamp  = self::stamp();
		$out    = array();
		$cursor = (int) $after;
		$batch  = $limit ? max( 50, (int) $limit ) : 500;
		do {
			$sql = "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND m.post_id > %d AND m.meta_value LIKE %s ORDER BY m.post_id LIMIT " . (int) $batch;
			$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $cursor, '%' . $wpdb->esc_like( '"' . self::MARKER . '":"' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $ids ) {
				$cursor = (int) end( $ids );
				$list   = implode( ',', array_map( 'intval', $ids ) );
				$backup = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( {$list} )", Migration::BACKUP ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids enteros.
				$done   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id IN ( {$list} )", self::RECOVER_STAMP, $stamp ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids enteros.
				foreach ( array_values( array_diff( array_intersect( $ids, $backup ), $done ) ) as $id ) {
					$out[] = $id;
					if ( $limit && count( $out ) >= $limit ) {
						return $out;
					}
				}
			}
		} while ( count( $ids ) === $batch );
		return $out;
	}

	/** ¿Tiene el documento la copia de la migración? */
	private static function copy_exists( int $post_id ): bool {
		return '' !== (string) get_metadata( 'post', $post_id, Migration::BACKUP, true );
	}

	/** Valor del sello: la versión de la recuperación y la de Elements (que decide qué pasa tal cual). */
	public static function stamp(): string {
		return self::RECOVER_VERSION . '|' . ( defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? (string) DIGITALISIMO_ELEMENTS_VERSION : '' );
	}

	/** Sella un documento ya revisado (o convertido entero por esta versión): la etapa no lo vuelve a leer. */
	private static function seal( int $post_id ): void {
		update_metadata( 'post', $post_id, self::RECOVER_STAMP, self::stamp() );
	}

	/**
	 * Documentos de la etapa `pro`: los que tienen widgets de PRO por convertir (documents()) y los convertidos por una
	 * versión anterior (recover_documents()), por id desde $after y hasta $limit (0: todos).
	 *
	 * @return int[]
	 */
	public static function stage_documents( $after = 0, $limit = 0 ): array {
		$ids = array_values( array_unique( array_merge( self::documents( $after, $limit ), self::recover_documents( $after, $limit ) ) ) );
		sort( $ids );
		return $limit ? array_slice( $ids, 0, (int) $limit ) : $ids;
	}

	/* ---------------------------------------------------------------- Recuperación */

	/**
	 * Widgets de PRO de las copias de un documento por id: primero la copia del estado previo a F8 (BACKUP_F8, la más
	 * reciente) y, para los que no estén allí, la copia original (BACKUP). Un id repetido dentro de una copia no se sabe a
	 * qué elemento corresponde: va a `$repeated`, que recover_walk() salta.
	 *
	 * @param array<string,bool> $repeated Recibe los ids repetidos.
	 * @return array<string,array>
	 */
	private static function copy_widgets( int $post_id, array &$repeated = array() ): array {
		$out = array();
		foreach ( array( Migration::BACKUP_F8, Migration::BACKUP ) as $meta ) {
			$backup = json_decode( (string) get_metadata( 'post', $post_id, $meta, true ), true );
			$data   = is_array( $backup ) && isset( $backup['data'] ) && is_string( $backup['data'] ) ? json_decode( $backup['data'], true ) : null;
			if ( is_array( $data ) ) {
				$found = array();
				$seen  = array();
				self::index_pro( $data, $found, $seen );
				foreach ( $seen as $id => $times ) {
					if ( $times > 1 ) {
						$repeated[ (string) $id ] = true;
					}
				}
				$out += $found;
			}
		}
		return $out; // Los repetidos los salta recover_walk() antes de mirar la copia.
	}

	/** Widgets de PRO de un árbol por id (el primero) y cuántas veces sale cada id. */
	private static function index_pro( array $elements, array &$out, array &$seen = array() ): void {
		$catalog = self::CATALOG;
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$id   = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
			$type = (string) ( $element['widgetType'] ?? '' );
			if ( '' !== $id ) {
				$seen[ $id ] = (int) ( $seen[ $id ] ?? 0 ) + 1;
			}
			if ( '' !== $id && ! isset( $out[ $id ] ) && 'widget' === ( $element['elType'] ?? '' ) && $catalog::is_pro( $type ) ) {
				$out[ $id ] = $element;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::index_pro( $element['elements'], $out, $seen );
			}
		}
	}

	/** Ajustes sin las claves de Element Pack (ni sus valores dinámicos o globales). */
	private static function without_legacy( array $settings ): array {
		foreach ( array_keys( $settings ) as $key ) {
			if ( self::legacy_key( (string) $key ) ) {
				unset( $settings[ $key ] );
			}
		}
		foreach ( array( '__dynamic__', '__globals__' ) as $refs ) {
			if ( isset( $settings[ $refs ] ) && is_array( $settings[ $refs ] ) ) {
				foreach ( array_keys( $settings[ $refs ] ) as $key ) {
					if ( self::legacy_key( (string) $key ) ) {
						unset( $settings[ $refs ][ $key ] );
					}
				}
				if ( ! $settings[ $refs ] ) {
					unset( $settings[ $refs ] );
				}
			}
		}
		return $settings;
	}

	/**
	 * Recupera en un documento convertido por una versión anterior lo que esa versión no pasó al widget propio y esta sí
	 * pasa, desde la copia guardada antes de convertir (copy_widgets()). Por cada widget convertido (marca `MARKER`) cuyo
	 * original (mismo id, mismo widget de PRO) está en la copia, se traduce el original con el traductor de hoy y se
	 * compara con el elemento actual (same_settings()): la copia se escribe una sola vez y puede ser anterior a la
	 * conversión (mapas publicados por tandas), así que la huella F8 del documento no basta.
	 *
	 * - Lo que pasa tal cual y no es una condición (`pro.inert`: ajustes que ningún widget lee) se añade sólo donde falta,
	 *   en el elemento y en cada fila (por su `_id`, o por su posición si ninguna lo tiene): nunca cambia un valor.
	 *   Una fila con `_id` que ya no está se informa (`pro.recover_skipped`).
	 * - Una condición de otro plugin (encendida o apagada en la copia) sólo se recupera si el elemento coincide con la
	 *   traducción de su copia: encendida, el elemento vuelve a ser el widget de PRO (sólo `widgetType` y ajustes, con sus
	 *   hijos actuales y los rasgos `digitalisimo_*` que le dio la etapa de Element Pack), `pro.condition_restored`;
	 *   apagada, pasa tal cual. Si no coincide, no se puede saber cómo estaba al convertir: `pro.condition_lost`.
	 * - Un id repetido en la copia o en el documento: no se sabe cuál es su original y no se toca (`pro.recover_ambiguous`).
	 * - Los hijos se recorren siempre. Sin nada pendiente, el documento queda sellado (RECOVER_STAMP).
	 *
	 * @return array{changed:bool, count:int} si se escribió y cuántos ajustes o elementos volvieron.
	 */
	public static function recover_document( int $post_id ): array {
		$none       = array( 'changed' => false, 'count' => 0 );
		$translator = self::TRANSLATOR;
		if ( $post_id <= 0 || ! self::available() || ! class_exists( $translator ) || ! method_exists( $translator, 'foreign' ) ) {
			return $none;
		}
		$raw = (string) get_metadata( 'post', $post_id, '_elementor_data', true );
		if ( '' === $raw || false === strpos( $raw, '"' . self::MARKER . '"' ) || self::stamp() === (string) get_metadata( 'post', $post_id, self::RECOVER_STAMP, true ) ) {
			return $none;
		}
		$current = json_decode( $raw, true );
		if ( ! is_array( $current ) ) {
			return $none;
		}
		$repeated  = array();
		$originals = self::copy_widgets( $post_id, $repeated );
		$seen      = array();
		$ignored   = array();
		self::index_pro( $current, $ignored, $seen );
		foreach ( $seen as $id => $times ) {
			// Repetido en el documento: sólo importa si la copia tiene un original con ese id (si no, no hay qué recuperar).
			if ( $times > 1 && ( isset( $originals[ (string) $id ] ) || isset( $repeated[ (string) $id ] ) ) ) {
				$repeated[ (string) $id ] = true;
			}
		}
		$report = array();
		$count  = 0;
		$data   = ( $originals || $repeated ) ? self::recover_walk( $current, $originals, $repeated, $report, $count ) : $current;
		$open   = false;
		foreach ( $report as $item ) {
			Report::add( $post_id, $item[0], $item[1] );
			$open = $open || in_array( $item[0], array( 'pro.condition_lost', 'pro.recover_ambiguous' ), true );
		}
		$changed = $count && $data !== $current;
		if ( $changed ) {
			self::save( $post_id, $raw, $data );
			Migration::clear_css( $post_id );
		}
		if ( ! $open ) {
			// Revisado sin nada pendiente: la etapa no vuelve a leerlo (lo pendiente se informa en cada Migrar).
			self::seal( $post_id );
		}
		return array( 'changed' => $changed, 'count' => $changed ? $count : 0, 'open' => $open );
	}

	/** Recorre el documento actual y recupera cada widget convertido, siempre también en sus hijos. */
	private static function recover_walk( array $elements, array $originals, array $repeated, array &$report, int &$count ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$source   = isset( $settings[ self::MARKER ] ) && is_string( $settings[ self::MARKER ] ) ? $settings[ self::MARKER ] : '';
			$id       = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
			if ( '' !== $source && '' !== $id && isset( $repeated[ $id ] ) ) {
				self::recover_note( $report, 'pro.recover_ambiguous', $source, (string) ( $element['widgetType'] ?? '' ), '', $id );
			} elseif ( '' !== $source && '' !== $id && isset( $originals[ $id ] ) && $source === (string) ( $originals[ $id ]['widgetType'] ?? '' ) ) {
				$element = self::recover_element( $element, $originals[ $id ], $report, $count );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::recover_walk( $element['elements'], $originals, $repeated, $report, $count );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/** Una fila del informe de la recuperación, agrupada por código, widget y ajuste. */
	private static function recover_note( array &$report, string $code, string $source, string $target, string $key, string $element = '' ): void {
		$sig = $code . '|' . $source . '|' . $key . '|' . $element;
		if ( ! isset( $report[ $sig ] ) ) {
			$report[ $sig ] = array( $code, array( 'widget' => $source, 'target' => $target, 'name' => $key, 'count' => 0 ) + ( '' !== $element ? array( 'element' => $element ) : array() ) );
		}
		++$report[ $sig ][1]['count'];
	}

	/** Recuperación de un widget convertido desde su original de la copia (recover_document()). */
	private static function recover_element( array $element, array $original, array &$report, int &$count ): array {
		$registry   = self::REGISTRY;
		$translator = self::TRANSLATOR;
		$source     = (string) $original['widgetType'];
		$map        = $registry::map( $source );
		$settings   = (array) $element['settings'];
		$id         = (string) ( $element['id'] ?? '' );
		if ( ! is_array( $map ) || (string) ( $map['target'] ?? '' ) !== (string) ( $element['widgetType'] ?? '' ) ) {
			return $element;
		}
		$target = (string) $map['target'];
		$clean  = self::without_legacy( is_array( $original['settings'] ?? null ) ? $original['settings'] : array() );
		list( $translated, $codes ) = $registry::in_site_locale(
			static function () use ( $translator, $clean, $map ) {
				return $translator::translate( $clean, $map );
			}
		);
		$pass       = array();
		$keep       = array();
		$conditions = array();
		foreach ( (array) $codes as $code ) {
			$key = (string) ( $code['key'] ?? '' );
			if ( '' === $key || '.' === substr( $key, -1 ) ) {
				continue;
			}
			if ( ! empty( $code['keep'] ) ) {
				$keep[] = $key;
			} elseif ( 'pro.inert' === ( $code['code'] ?? '' ) ) {
				$pass[] = $key;
			}
			if ( ! empty( $code['condition'] ) || ! empty( $code['keep'] ) ) {
				$conditions[ $key ] = true;
			}
		}
		if ( ! $pass && ! $keep ) {
			return $element;
		}
		// El repetidor con su nombre en el widget propio, para comparar.
		$named = array();
		foreach ( array_merge( $pass, $keep ) as $key ) {
			$dot     = strpos( $key, '.' );
			$named[] = false === $dot ? $key : (string) ( $map['repeaters'][ substr( $key, 0, $dot ) ]['target'] ?? substr( $key, 0, $dot ) ) . substr( $key, $dot );
		}
		// Las versiones anteriores quitaban todas las claves de una condición de otro plugin: si el elemento lleva alguna
		// (arriba, en `__dynamic__`/`__globals__` o en una fila), la pasó esta versión (o la persona la puso después) y no
		// se perdió nada: ni se devuelve ni se informa.
		$condition_keys = array_keys( $conditions );
		if ( $condition_keys && self::has_any_key( $settings, $condition_keys, $map ) ) {
			$keep = array();
			$pass = array_values( array_diff( $pass, $condition_keys ) );
			if ( ! $pass ) {
				return $element;
			}
		}
		$same = self::same_settings( $settings, (array) $translated, $named );
		if ( $keep ) {
			if ( ! $same ) {
				foreach ( array_unique( $keep ) as $key ) {
					self::recover_note( $report, 'pro.condition_lost', $source, $target, $key, $id );
				}
				return $element;
			}
			// Vuelve el widget de PRO: sólo su tipo y sus ajustes; los hijos actuales se quedan.
			$own = array();
			foreach ( $settings as $key => $value ) {
				if ( 1 === preg_match( self::EP_TRAITS, (string) $key ) ) {
					$own[ $key ] = $value; // Rasgos que la etapa de Element Pack dio al elemento (visibilidad…).
				}
			}
			$element['widgetType'] = $source;
			$element['settings']   = $own + $clean;
			foreach ( array_unique( $keep ) as $key ) {
				self::recover_note( $report, 'pro.condition_restored', $source, $target, $key );
			}
			++$count;
			return $element;
		}
		foreach ( $pass as $key ) {
			if ( isset( $conditions[ $key ] ) && ! $same ) {
				// Una condición apagada en la copia, en un elemento que cambió desde entonces: pudo encenderse antes de
				// convertir; no se sabe y no se toca.
				self::recover_note( $report, 'pro.condition_lost', $source, $target, $key, $id );
				continue;
			}
			$dot = strpos( $key, '.' );
			if ( false === $dot ) {
				$added = false;
				if ( array_key_exists( $key, (array) $translated ) && ! array_key_exists( $key, $settings ) ) {
					$settings[ $key ] = $translated[ $key ];
					$added            = true;
				}
				foreach ( array( '__dynamic__', '__globals__' ) as $refs ) {
					if ( isset( $translated[ $refs ][ $key ] ) && ! isset( $settings[ $refs ][ $key ] ) ) {
						$settings[ $refs ]         = (array) ( $settings[ $refs ] ?? array() );
						$settings[ $refs ][ $key ] = $translated[ $refs ][ $key ];
						$added                     = true;
					}
				}
				if ( $added ) {
					self::recover_note( $report, 'pro.recovered', $source, $target, $key );
					++$count;
				}
				continue;
			}
			// Subcampo de un repetidor (`repetidor.clave`): por el `_id` de cada fila o, sin él, por su posición.
			$repeater = substr( $key, 0, $dot );
			$sub      = substr( $key, $dot + 1 );
			$to       = (string) ( $map['repeaters'][ $repeater ]['target'] ?? $repeater );
			$rows     = is_array( $settings[ $to ] ?? null ) ? $settings[ $to ] : array();
			foreach ( is_array( $translated[ $to ] ?? null ) ? $translated[ $to ] : array() as $position => $row ) {
				if ( ! is_array( $row ) || ! array_key_exists( $sub, $row ) ) {
					continue;
				}
				$at = null;
				if ( isset( $row['_id'] ) ) {
					foreach ( $rows as $i => $current ) {
						if ( is_array( $current ) && isset( $current['_id'] ) && (string) $row['_id'] === (string) $current['_id'] ) {
							$at = $i;
							break;
						}
					}
					if ( null === $at ) {
						self::recover_note( $report, 'pro.recover_skipped', $source, $target, $key );
						continue;
					}
				} elseif ( isset( $rows[ $position ] ) && is_array( $rows[ $position ] ) && ! isset( $rows[ $position ]['_id'] ) ) {
					$at = $position;
				} else {
					continue; // Sin `_id` y sin una fila en su lugar: nada que casar.
				}
				if ( ! array_key_exists( $sub, $rows[ $at ] ) ) {
					$rows[ $at ][ $sub ] = $row[ $sub ];
					self::recover_note( $report, 'pro.recovered', $source, $target, $key );
					++$count;
				}
			}
			if ( $rows ) {
				$settings[ $to ] = $rows;
			}
		}
		$element['settings'] = $settings;
		return $element;
	}

	/**
	 * ¿Lleva el elemento alguna de esas claves (`clave` o `repetidor.clave`), arriba, en `__dynamic__`/`__globals__` o en
	 * alguna fila del repetidor (con su nombre en el widget propio)?
	 */
	private static function has_any_key( array $settings, array $keys, array $map ): bool {
		foreach ( $keys as $key ) {
			$dot = strpos( (string) $key, '.' );
			if ( false === $dot ) {
				if ( array_key_exists( $key, $settings ) || isset( $settings['__dynamic__'][ $key ] ) || isset( $settings['__globals__'][ $key ] ) ) {
					return true;
				}
				continue;
			}
			$to  = (string) ( $map['repeaters'][ substr( $key, 0, $dot ) ]['target'] ?? substr( $key, 0, $dot ) );
			$sub = substr( $key, $dot + 1 );
			foreach ( is_array( $settings[ $to ] ?? null ) ? $settings[ $to ] : array() as $row ) {
				if ( is_array( $row ) && ( array_key_exists( $sub, $row ) || isset( $row['__dynamic__'][ $sub ] ) || isset( $row['__globals__'][ $sub ] ) ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Un valor con sus listas asociativas ordenadas por clave, a cualquier profundidad (para comparar). */
	private static function sorted( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sorted( $item );
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		return $value;
	}

	/**
	 * ¿Es el elemento actual la traducción de su original? Iguales sin lo que pasa tal cual ni las condiciones ($pass, con
	 * el repetidor ya con su nombre en el widget propio) y sin los rasgos que escribe la etapa de Element Pack (EP_TRAITS).
	 * Cualquier otra diferencia (una edición, también en un ajuste `digitalisimo_*` del widget propio, el CSS de efectos o
	 * el movimiento que escribió la etapa de Element Pack) dice que no se puede saber.
	 */
	private static function same_settings( array $current, array $translated, array $pass ): bool {
		$strip = static function ( array $settings ) use ( $pass ): array {
			foreach ( array_keys( $settings ) as $key ) {
				if ( 1 === preg_match( self::EP_TRAITS, (string) $key ) || in_array( (string) $key, $pass, true ) ) {
					unset( $settings[ $key ] );
				}
			}
			foreach ( $pass as $key ) {
				$dot = strpos( $key, '.' );
				if ( false === $dot ) {
					foreach ( array( '__dynamic__', '__globals__' ) as $refs ) {
						unset( $settings[ $refs ][ $key ] );
						if ( isset( $settings[ $refs ] ) && ! $settings[ $refs ] ) {
							unset( $settings[ $refs ] );
						}
					}
					continue;
				}
				$repeater = substr( $key, 0, $dot );
				foreach ( is_array( $settings[ $repeater ] ?? null ) ? array_keys( $settings[ $repeater ] ) : array() as $i ) {
					if ( is_array( $settings[ $repeater ][ $i ] ) ) {
						unset( $settings[ $repeater ][ $i ][ substr( $key, $dot + 1 ) ] );
					}
				}
			}
			return self::sorted( $settings );
		};
		return wp_json_encode( $strip( $current ) ) === wp_json_encode( $strip( $translated ) );
	}

	/* ---------------------------------------------------------------- Conversión */

	/**
	 * Convierte en memoria los widgets de PRO con mapa listo de un árbol (sin escribir nada).
	 *
	 * Los ajustes de Element Pack de cada widget de PRO se apartan antes del traductor y vuelven después al mismo
	 * elemento (`stash_legacy()`/`restore_legacy()`); con $native (la tarea, con el documento $post_id), los convierte la
	 * etapa de Element Pack (`Native::element()`) como en cualquier otro elemento.
	 *
	 * @return array{0:array, 1:array, 2:string[], 3:array, 4:array} elementos, registro de Converter::tree() (con una fila
	 *         `source => global` por instancia de widget global sustituida), widgets de PRO que no se convirtieron porque
	 *         su widget propio no está registrado, instancias de widget global sin plantilla (filas de `Globals::tree()`)
	 *         y widgets de PRO que se quedan como están por una condición de otro plugin encendida (mismo formato).
	 */
	public static function convert_data( array $data, int $post_id = 0, bool $native = false ): array {
		if ( ! self::available() ) {
			return array( $data, array(), array(), array(), array() );
		}
		$converter = self::CONVERTER;
		$globals   = array();
		$missing   = array();
		$out       = $data;
		if ( self::globals() ) {
			$class = self::GLOBALS;
			// El widget global propio (si está registrado) conserva el vínculo con la plantilla; si no, su contenido.
			$out   = 'digitalisimo-global' === self::global_target()
				? $class::link( $data, array( __CLASS__, 'template_content' ), $globals )
				: $class::tree( $data, array( __CLASS__, 'template_content' ), $globals );
			foreach ( $globals as $i => $row ) {
				if ( empty( $row['expanded'] ) ) {
					$missing[] = $row;
					unset( $globals[ $i ] );
				}
			}
		}
		$stash  = array();
		$before = self::stash_legacy( $out, $stash );
		$log    = array();
		$kept   = array();
		// `$kept` sólo lo llena un Elements con la corrección de las condiciones de otros plugins (los anteriores ignoran
		// el cuarto argumento).
		$out = $converter::tree( $before, $log, false, $kept );
		list( $out, $log, $kept ) = self::keep_conditions( $out, $before, $log, (array) $kept );
		$blocked = array();
		foreach ( $log as $row ) {
			if ( ! isset( self::ready()[ (string) ( $row['source'] ?? '' ) ] ) ) {
				$blocked[] = (string) $row['source'];
			}
		}
		if ( $blocked ) {
			// Un destino sin registrar: el documento entero queda como está (nunca a medias).
			return array( $data, array(), array_values( array_unique( $blocked ) ), $missing, array() );
		}
		$out = self::restore_legacy( $out, $stash, $post_id, $native );
		return array( $out, array_merge( array_values( $globals ), $log ), array(), $missing, $kept );
	}

	/** ¿Es una clave de Element Pack? (`Native::ELEMENT_PREFIXES`) */
	private static function legacy_key( string $key ): bool {
		foreach ( array( 'ep_', 'element_pack_', 'bdt_' ) as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Aparta las claves de Element Pack (y sus valores dinámicos o globales) de cada widget de PRO de un árbol, por la
	 * posición del elemento (el convertidor conserva el árbol). Devuelve el árbol sin ellas.
	 *
	 * @param array $stash Recibe `posición => ['settings' => …, '__dynamic__' => …, '__globals__' => …]`.
	 */
	private static function stash_legacy( array $elements, array &$stash, string $path = '' ): array {
		$catalog = self::CATALOG;
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$here = $path . '/' . $index;
			$type = (string) ( $element['widgetType'] ?? '' );
			if ( 'widget' === ( $element['elType'] ?? '' ) && '' !== $type && $catalog::is_pro( $type ) && isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$bag = array();
				foreach ( $element['settings'] as $key => $value ) {
					if ( self::legacy_key( (string) $key ) ) {
						$bag['settings'][ $key ] = $value;
						unset( $element['settings'][ $key ] );
					}
				}
				foreach ( array( '__dynamic__', '__globals__' ) as $refs ) {
					foreach ( is_array( $element['settings'][ $refs ] ?? null ) ? $element['settings'][ $refs ] : array() as $key => $value ) {
						if ( self::legacy_key( (string) $key ) ) {
							$bag[ $refs ][ $key ] = $value;
							unset( $element['settings'][ $refs ][ $key ] );
						}
					}
					if ( isset( $bag[ $refs ] ) && ! $element['settings'][ $refs ] ) {
						unset( $element['settings'][ $refs ] );
					}
				}
				if ( $bag ) {
					$stash[ $here ] = $bag;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::stash_legacy( $element['elements'], $stash, $here );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/**
	 * Devuelve a cada elemento las claves de Element Pack apartadas y, con $native, las convierte la etapa de Element
	 * Pack (`Native::element()`, con su modo restringido) en el elemento ya convertido.
	 */
	private static function restore_legacy( array $elements, array $stash, int $post_id, bool $native, string $path = '' ): array {
		if ( ! $stash ) {
			return $elements;
		}
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$here = $path . '/' . $index;
			if ( isset( $stash[ $here ] ) ) {
				$settings = (array) ( $element['settings'] ?? array() );
				$settings = $settings + (array) ( $stash[ $here ]['settings'] ?? array() );
				foreach ( array( '__dynamic__', '__globals__' ) as $refs ) {
					if ( ! empty( $stash[ $here ][ $refs ] ) ) {
						$settings[ $refs ] = (array) ( $settings[ $refs ] ?? array() ) + (array) $stash[ $here ][ $refs ];
					}
				}
				$element['settings'] = $settings;
				if ( $native && $post_id > 0 && isset( $settings[ self::MARKER ] ) && class_exists( __NAMESPACE__ . '\\Native' ) ) {
					// Sólo el elemento (sus hijos los trató ya la etapa de Element Pack): los hijos vuelven tal cual.
					$children = $element['elements'] ?? array();
					list( $converted ) = Native::element( array( 'elements' => array() ) + $element, $post_id );
					if ( is_array( $converted ) ) {
						$converted['elements'] = $children;
						$element               = $converted;
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::restore_legacy( $element['elements'], $stash, $post_id, $native, $here );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/**
	 * ¿Está encendido un ajuste de otro plugin que salió como `pro.unmapped`? (como `Translator::foreign()` de Elements):
	 * su familia de condiciones, un interruptor del mismo primer segmento entre $candidates (las claves que el mapa no
	 * declara), el propio interruptor, o una condición con valor. Una clave sin nada de eso, no.
	 */
	public static function condition_active( string $key, array $settings, ?array $candidates = null ): bool {
		foreach ( self::CONDITION_FAMILIES as $prefix => $switch ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return self::used( $settings[ $switch ] ?? null );
			}
		}
		$cut = strpos( $key, '_' );
		if ( false !== $cut && $cut > 0 ) {
			$head  = substr( $key, 0, $cut + 1 );
			$found = false;
			foreach ( null === $candidates ? array_keys( $settings ) : $candidates as $candidate ) {
				$candidate = (string) $candidate;
				$fits = 1 !== preg_match( self::CONDITION_PATTERN, $key ) || 1 === preg_match( self::CONDITION_PATTERN, $candidate ); // Una condición, sólo con un interruptor de condiciones.
				if ( $fits && $candidate !== $key && 0 === strpos( $candidate, $head ) && array_key_exists( $candidate, $settings ) && 1 === preg_match( self::SWITCH_PATTERN, $candidate ) ) {
					if ( self::used( $settings[ $candidate ] ) ) {
						return true;
					}
					$found = true;
				}
			}
			if ( $found ) {
				return false;
			}
		}
		if ( 1 === preg_match( self::SWITCH_PATTERN, $key ) ) {
			return self::used( $settings[ $key ] ?? null );
		}
		return 1 === preg_match( self::CONDITION_PATTERN, $key ) && self::used( $settings[ $key ] ?? null );
	}

	/** ¿Tiene efecto un valor guardado? */
	private static function used( $value ): bool {
		if ( is_array( $value ) || null === $value || is_bool( $value ) ) {
			return (bool) $value;
		}
		$value = strtolower( trim( (string) $value ) );
		return '' !== $value && 'no' !== $value && 'false' !== $value && '0' !== $value;
	}

	/**
	 * Red de seguridad para un Elements sin la corrección de los ajustes de otros plugins: un widget convertido cuyo
	 * registro trae `pro.unmapped` de un ajuste encendido de otro plugin (condition_active()) vuelve a su original
	 * (`$before`) y pasa a `$kept` con `pro.condition_kept`. Nada oculto se vuelve visible. Se recorre por posición (el
	 * convertidor conserva el árbol), así que un id repetido no confunde un elemento con otro.
	 *
	 * @return array{0:array, 1:array, 2:array} árbol, registro y elementos que se quedan como están.
	 */
	private static function keep_conditions( array $out, array $before, array $log, array $kept ): array {
		$unmapped = array();
		foreach ( $log as $row ) {
			foreach ( (array) ( $row['codes'] ?? array() ) as $code ) {
				if ( 'pro.unmapped' === ( $code['code'] ?? '' ) && '' !== (string) ( $row['element'] ?? '' ) ) {
					$unmapped[ (string) $row['element'] ][] = (string) ( $code['key'] ?? '' );
				}
			}
		}
		if ( ! $unmapped ) {
			return array( $out, $log, $kept );
		}
		$restored = array();
		$nth      = array();
		$out      = self::keep_walk( $out, $before, $unmapped, $restored, $nth );
		$drop = array();
		foreach ( $restored as $item ) {
			// Fuera del registro la fila de ese elemento: la n-ésima con su id (el registro va en el mismo orden).
			$seen = 0;
			foreach ( $log as $i => $row ) {
				if ( (string) ( $row['element'] ?? '' ) === $item['element'] && $seen++ === $item['nth'] ) {
					$kept[]     = array( 'source' => $item['source'], 'target' => (string) ( $row['target'] ?? '' ), 'element' => $item['element'], 'codes' => $item['codes'] );
					$drop[ $i ] = true;
					break;
				}
			}
		}
		return array( $out, array_values( array_diff_key( $log, $drop ) ), $kept );
	}

	/** Recorrido en paralelo de keep_conditions(): devuelve su original a cada widget con un ajuste ajeno encendido. */
	private static function keep_walk( array $out, array $before, array $unmapped, array &$restored, array &$nth ): array {
		foreach ( $out as $index => $element ) {
			$original = $before[ $index ] ?? null;
			if ( ! is_array( $element ) || ! is_array( $original ) ) {
				continue;
			}
			$id = (string) ( $original['id'] ?? '' );
			$at = -1;
			if ( isset( $element['settings'][ self::MARKER ] ) ) {
				$at         = (int) ( $nth[ $id ] ?? 0 );
				$nth[ $id ] = $at + 1;
			}
			if ( isset( $element['settings'][ self::MARKER ] ) && isset( $unmapped[ $id ] ) && (string) ( $element['id'] ?? '' ) === $id ) {
				$settings   = is_array( $original['settings'] ?? null ) ? $original['settings'] : array();
				$candidates = array();
				foreach ( $unmapped[ $id ] as $key ) {
					if ( false === strpos( $key, '.' ) ) {
						$candidates[] = $key;
					}
				}
				$codes = array();
				foreach ( $unmapped[ $id ] as $key ) {
					// Una clave de una fila no se puede evaluar fuera de ella: si habla de condiciones, falla cerrado.
					$on = false === strpos( $key, '.' ) ? self::condition_active( $key, $settings, $candidates ) : 1 === preg_match( self::CONDITION_PATTERN, $key );
					if ( $on && array_key_exists( false === strpos( $key, '.' ) ? $key : substr( $key, 0, (int) strpos( $key, '.' ) ), $settings ) ) {
						$codes[] = array( 'code' => self::KEPT, 'key' => $key, 'reason' => 'Ajuste de otro plugin encendido (condición, visibilidad o interruptor) que el widget propio no sabe aplicar: el elemento se queda como widget de PRO, que se sigue viendo (y ocultando) igual.', 'section' => 'review', 'keep' => true );
					}
				}
				if ( $codes ) {
					$children            = $element['elements'] ?? null;
					$element             = $original;
					if ( null !== $children ) {
						$element['elements'] = $children; // Los hijos, ya convertidos, se quedan.
					}
					$restored[] = array( 'element' => $id, 'source' => (string) ( $original['widgetType'] ?? '' ), 'codes' => $codes, 'nth' => $at );
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && is_array( $original['elements'] ?? null ) ) {
				$element['elements'] = self::keep_walk( $element['elements'], $original['elements'], $unmapped, $restored, $nth );
			}
			$out[ $index ] = $element;
		}
		return $out;
	}

	/** Lleva al informe los widgets de PRO que se quedan como están por una condición de otro plugin encendida. */
	private static function report_kept( int $post_id, array $kept ): void {
		$by = array();
		foreach ( $kept as $row ) {
			foreach ( (array) ( $row['codes'] ?? array() ) as $code ) {
				$sig = (string) $row['source'] . '|' . (string) ( $code['key'] ?? '' );
				if ( ! isset( $by[ $sig ] ) ) {
					$by[ $sig ] = array( 'widget' => (string) $row['source'], 'target' => (string) $row['target'], 'name' => (string) ( $code['key'] ?? '' ), 'note' => (string) ( $code['reason'] ?? '' ), 'count' => 0 );
				}
				++$by[ $sig ]['count'];
			}
		}
		foreach ( $by as $detail ) {
			Report::add( $post_id, self::KEPT, $detail );
		}
	}

	/** Lleva al informe las instancias de widget global sin plantilla de un documento (una fila por plantilla). */
	private static function report_missing( int $post_id, array $missing ): void {
		$by = array();
		foreach ( $missing as $row ) {
			$template          = (int) ( $row['template'] ?? 0 );
			$by[ $template ]   = (int) ( $by[ $template ] ?? 0 ) + 1;
		}
		foreach ( $by as $template => $count ) {
			Report::add( $post_id, 'pro.global_missing', array( 'widget' => 'global', 'name' => (string) $template, 'count' => $count ) );
		}
	}

	/**
	 * Convierte un documento (no una revisión): copia del original si aún no la tiene, marca F8 con la huella de lo
	 * escrito, cachés del documento y códigos `pro.*` en el informe. Las escrituras van al id indicado.
	 *
	 * @return array{converted:int, blocked:string[]} widgets convertidos y widgets de PRO sin destino registrado.
	 */
	public static function convert_document( $post_id ): array {
		$post_id = (int) $post_id;
		$none    = array( 'converted' => 0, 'blocked' => array() );
		if ( ! self::available() || $post_id <= 0 || ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) ) {
			return $none;
		}
		$raw = get_metadata( 'post', $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return $none;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return $none;
		}
		// Lo que una versión anterior no pasó al widget propio vuelve antes de convertir lo que quede (recover_document()).
		$recovered = self::recover_document( $post_id );
		if ( $recovered['changed'] ) {
			$raw  = (string) get_metadata( 'post', $post_id, '_elementor_data', true );
			$data = json_decode( $raw, true );
			$data = is_array( $data ) ? $data : array();
		}
		// Las claves de Element Pack de un widget de PRO las convierte la etapa de Element Pack, con su modo restringido.
		$restricted = class_exists( __NAMESPACE__ . '\\Native' ) ? Native::$restricted : false;
		if ( class_exists( __NAMESPACE__ . '\\Native' ) ) {
			Native::$restricted = Migration::restricted( $post_id );
		}
		Context::begin( $post_id, 'run' );
		try {
			list( $data, $log, $blocked, $missing, $kept ) = self::convert_data( $data, $post_id, true );
		} finally {
			Context::end();
			if ( class_exists( __NAMESPACE__ . '\\Native' ) ) {
				Native::$restricted = $restricted;
			}
		}
		self::report_missing( $post_id, $missing );
		if ( $blocked ) {
			foreach ( $blocked as $type ) {
				$item = self::blocked_item( $type );
				Report::add( $post_id, $item[0], $item[1] );
			}
			return array( 'converted' => 0, 'blocked' => $blocked, 'recovered' => $recovered['count'] );
		}
		self::report_kept( $post_id, (array) $kept );
		list( $data, $tree, $page ) = self::document_css( $post_id, $data );
		$css                        = $tree + $page;
		if ( ! $log && ! $css ) {
			return $recovered['count'] ? array( 'converted' => 0, 'blocked' => array(), 'recovered' => $recovered['count'] ) : $none;
		}
		if ( $log || $tree ) {
			self::save( $post_id, $raw, $data );
		}
		Migration::clear_css( $post_id );
		foreach ( self::codes( $log ) as $item ) {
			Report::add( $post_id, $item[0], $item[1] );
		}
		if ( $css ) {
			Report::add( $post_id, 'pro.css_external', array( 'count' => $css ) );
		}
		if ( $log && empty( $recovered['open'] ) && self::copy_exists( $post_id ) ) {
			// Lo convertido por esta versión ya pasa todo lo que hoy se sabe pasar: nada que recuperar en otra Migrar.
			self::seal( $post_id );
		}
		return array( 'converted' => count( $log ), 'blocked' => array(), 'css' => $css, 'recovered' => $recovered['count'] );
	}

	/**
	 * Etapa `pro_css`: sólo el CSS que nombra widgets de PRO desde fuera (árbol y página) de un documento que no tiene
	 * widgets de PRO por convertir. Devuelve cuántos CSS cambiaron.
	 */
	public static function convert_css_document( $post_id ): int {
		$post_id = (int) $post_id;
		if ( ! self::available() || $post_id <= 0 || ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) ) {
			return 0;
		}
		$raw  = get_metadata( 'post', $post_id, '_elementor_data', true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		$data = is_array( $data ) ? $data : array();
		list( $data, $tree, $page ) = self::document_css( $post_id, $data );
		if ( $tree && is_string( $raw ) && '' !== $raw ) {
			self::save( $post_id, $raw, $data );
		}
		$css = $tree + $page;
		if ( $css ) {
			Migration::clear_css( $post_id );
			Report::add( $post_id, 'pro.css_external', array( 'count' => $css ) );
		}
		return $css;
	}

	/**
	 * CSS del árbol (lo que no es un widget convertido) y de la página de un documento, con las copias de las reglas que
	 * nombran widgets de PRO. La página se escribe aquí (con CSS_BACKUP); el árbol lo escribe quien llama (`save()`, con
	 * la copia y la marca del documento).
	 *
	 * @return array{0: array, 1: int, 2: int} árbol, CSS del árbol que cambiaron y si cambió el de la página.
	 */
	private static function document_css( int $post_id, array $data ): array {
		$sets = self::class_sets();
		if ( ! $sets ) {
			return array( $data, 0, 0 );
		}
		$converter = self::CONVERTER;
		$tree      = 0;
		$data      = $converter::tree_css( $data, $sets, $tree );
		$changed   = 0;
		$page      = get_metadata( 'post', $post_id, '_elementor_page_settings', true );
		if ( is_array( $page ) && isset( $page['custom_css'] ) && is_string( $page['custom_css'] ) && '' !== $page['custom_css'] ) {
			$css = (string) $converter::external_css( $page['custom_css'], $sets );
			if ( $css !== $page['custom_css'] ) {
				self::backup_css( $post_id, 'page', $page['custom_css'], $css );
				$page['custom_css'] = $css;
				update_metadata( 'post', $post_id, '_elementor_page_settings', wp_slash( $page ) );
				$changed = 1;
			}
		}
		return array( $data, (int) $tree, $changed );
	}

	/**
	 * Juegos de clases de los mapas listos con destino registrado (`Convert\Converter::class_sets()`), o vacío con una
	 * versión de Elements sin la traducción del CSS desde fuera.
	 */
	public static function class_sets(): array {
		$converter = self::CONVERTER;
		if ( ! self::available() || ! method_exists( $converter, 'class_sets' ) || ! method_exists( $converter, 'tree_css' ) || ! method_exists( $converter, 'external_css' ) || ! self::ready() ) {
			return array();
		}
		return (array) $converter::class_sets( array_keys( self::ready() ) );
	}

	/** Guarda (la primera vez) el CSS anterior de un documento, el kit o el Personalizador, y la huella de lo escrito. */
	private static function backup_css( int $post_id, string $where, string $before, string $after ): void {
		$stored = json_decode( (string) get_metadata( 'post', $post_id, self::CSS_BACKUP, true ), true );
		$entry  = is_array( $stored ) && isset( $stored['before'] ) ? $stored : array( 'time' => time(), 'where' => $where, 'before' => $before );
		$entry['hash'] = md5( $after );
		if ( is_array( $stored ) ) {
			update_metadata( 'post', $post_id, self::CSS_BACKUP, wp_slash( (string) wp_json_encode( $entry ) ) );
		} else {
			add_metadata( 'post', $post_id, self::CSS_BACKUP, wp_slash( (string) wp_json_encode( $entry ) ), true );
		}
	}

	/**
	 * Documentos (no revisiones) desde el id $after cuyo CSS personalizado (del árbol o de la página) podría nombrar
	 * clases de PRO, hasta $limit (0: todos). Los widgets de PRO ya convertidos los cubre `documents()`.
	 *
	 * @return int[]
	 */
	public static function css_documents( $after = 0, $limit = 0 ): array {
		global $wpdb;
		if ( ! self::class_sets() ) {
			return array();
		}
		$sql = "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type <> 'revision' AND m.post_id > %d AND ( ( m.meta_key = '_elementor_data' AND m.meta_value LIKE %s ) OR ( m.meta_key = '_elementor_page_settings' AND m.meta_value LIKE %s ) ) AND ( m.meta_value LIKE %s OR m.meta_value LIKE %s ) ORDER BY m.post_id" . ( $limit ? ' LIMIT ' . max( 1, (int) $limit ) : '' );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, (int) $after, '%' . $wpdb->esc_like( '"custom_css":"' ) . '%', '%' . $wpdb->esc_like( 's:10:"custom_css"' ) . '%', '%' . $wpdb->esc_like( '.elementor-' ) . '%', '%' . $wpdb->esc_like( '.e-' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Final de la etapa `pro_css`: el CSS del kit activo y el CSS adicional del Personalizador del tema activo, con las
	 * copias de las reglas que nombran widgets de PRO. Devuelve cuántos cambiaron.
	 */
	public static function convert_site_css(): int {
		$sets = self::class_sets();
		if ( ! $sets ) {
			return 0;
		}
		$converter = self::CONVERTER;
		$changed   = 0;
		$kit       = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit > 0 ) {
			$settings = get_metadata( 'post', $kit, '_elementor_page_settings', true );
			if ( is_array( $settings ) && isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) && '' !== $settings['custom_css'] ) {
				$css = (string) $converter::external_css( $settings['custom_css'], $sets );
				if ( $css !== $settings['custom_css'] ) {
					self::backup_css( $kit, 'page', $settings['custom_css'], $css );
					$settings['custom_css'] = $css;
					update_metadata( 'post', $kit, '_elementor_page_settings', wp_slash( $settings ) );
					Migration::clear_css( $kit );
					Report::site( 'pro.css_external', array( 'name' => 'kit', 'id' => $kit ) );
					++$changed;
				}
			}
		}
		$post = function_exists( 'wp_get_custom_css_post' ) ? wp_get_custom_css_post() : null;
		if ( $post && isset( $post->ID, $post->post_content ) && '' !== (string) $post->post_content ) {
			$css = (string) $converter::external_css( (string) $post->post_content, $sets );
			if ( $css !== (string) $post->post_content ) {
				self::backup_css( (int) $post->ID, 'content', (string) $post->post_content, $css );
				self::write_content( (int) $post->ID, $css );
				Report::site( 'pro.css_external', array( 'name' => 'customizer', 'id' => (int) $post->ID ) );
				++$changed;
			}
		}
		return $changed;
	}

	/** Contenido de la entrada del CSS adicional (sin revisión nueva de quien migra: es la misma hoja con copias). */
	private static function write_content( int $post_id, string $css ): void {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => $css ), array( 'ID' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $post_id );
		}
	}

	/**
	 * `--revert`: devuelve el CSS anterior de la página, del kit y del Personalizador si nadie lo cambió después (con
	 * $force, siempre). El CSS del árbol vuelve con la copia del documento.
	 *
	 * @return array<int, array{0:string, 1:array}> códigos.
	 */
	public static function revert_css( bool $force = false ): array {
		global $wpdb;
		$out = array();
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id", self::CSS_BACKUP ) ) );
		foreach ( $ids as $post_id ) {
			$entry = json_decode( (string) get_metadata( 'post', $post_id, self::CSS_BACKUP, true ), true );
			if ( ! is_array( $entry ) || ! isset( $entry['before'] ) || ! is_string( $entry['before'] ) ) {
				delete_metadata( 'post', $post_id, self::CSS_BACKUP );
				continue;
			}
			if ( 'content' === ( $entry['where'] ?? '' ) ) {
				$post    = get_post( $post_id );
				$current = $post ? (string) $post->post_content : null;
			} else {
				$page    = get_metadata( 'post', $post_id, '_elementor_page_settings', true );
				$current = is_array( $page ) && isset( $page['custom_css'] ) && is_string( $page['custom_css'] ) ? $page['custom_css'] : null;
			}
			if ( null === $current ) {
				delete_metadata( 'post', $post_id, self::CSS_BACKUP );
				continue;
			}
			if ( ! $force && md5( $current ) !== (string) ( $entry['hash'] ?? '' ) ) {
				$out[] = array( 'core.revert_skipped', array( 'id' => $post_id ) );
				continue;
			}
			if ( 'content' === ( $entry['where'] ?? '' ) ) {
				self::write_content( $post_id, $entry['before'] );
			} else {
				$page['custom_css'] = $entry['before'];
				update_metadata( 'post', $post_id, '_elementor_page_settings', wp_slash( $page ) );
				Migration::clear_css( $post_id );
			}
			delete_metadata( 'post', $post_id, self::CSS_BACKUP );
			$out[] = array( 'pro.css_reverted', array( 'id' => $post_id ) );
		}
		return $out;
	}

	/** Escribe el árbol de un documento con la copia del original (la primera vez) y la marca F8 al día. */
	private static function save( int $post_id, string $raw, array $data ): void {
		$json   = (string) wp_json_encode( $data );
		$backup = (string) get_metadata( 'post', $post_id, Migration::BACKUP, true );
		$mark   = Migration::f8_mark( $post_id );
		if ( '' === $backup ) {
			// Primera escritura de la migración en este documento: la copia lleva dentro la marca F8 (como
			// Migration::convert_document()), así que el documento no pasa por migrado con una versión antigua.
			add_metadata( 'post', $post_id, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw, 'f8' => self::mark( $post_id, $json ) ) ) ), true );
		}
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( $json ) );
		if ( '' !== $backup && is_array( $mark ) && md5( $raw ) === (string) ( $mark['hash'] ?? '' ) ) {
			// Ya lo tocó esta migración y nadie lo editó después: se actualiza la huella para que --revert no lo tome por
			// editado. Si se editó (la huella ya no coincidía), se deja como está: --revert lo seguirá viendo editado y no
			// devolverá la copia. Una copia sin marca (versión ≤ 1.0.20) se deja así: su modo restringido no cambia.
			$mark['hash'] = md5( $json );
			update_metadata( 'post', $post_id, Migration::F8_MARK, wp_slash( wp_json_encode( $mark ) ) );
		}
	}

	/** Marca F8 de un documento que la migración toca por primera vez (mismo formato que Migration::mark_data()). */
	private static function mark( int $post_id, string $json ): array {
		$page = get_metadata( 'post', $post_id, '_elementor_page_settings', true );
		return array(
			'v'          => 1,
			'time'       => time(),
			'hash'       => md5( $json ),
			'page_hash'  => is_array( $page ) ? md5( serialize( $page ) ) : '', // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- sólo huella.
			'restricted' => 0,
		);
	}

	/**
	 * Códigos del informe de un registro de Converter::tree(), agrupados: `pro.converted` por widget de origen y cada
	 * código del traductor por widget, clave y motivo, con su recuento.
	 *
	 * @return array<int, array{0:string, 1:array}>
	 */
	public static function codes( array $log ): array {
		$converted = array();
		$codes     = array();
		foreach ( $log as $row ) {
			$source = (string) ( $row['source'] ?? '' );
			$target = (string) ( $row['target'] ?? '' );
			$key    = $source . '|' . $target;
			if ( ! isset( $converted[ $key ] ) ) {
				$converted[ $key ] = array( 'pro.converted', array( 'widget' => $source, 'target' => $target, 'count' => 0 ) );
			}
			++$converted[ $key ][1]['count'];
			foreach ( (array) ( $row['codes'] ?? array() ) as $code ) {
				$name = (string) ( $code['code'] ?? '' );
				if ( 0 !== strpos( $name, 'pro.' ) ) {
					continue;
				}
				if ( 'pro.inert' === $name ) {
					// Lo que pasa tal cual, en una línea por widget con sus claves: no ocupa el tope de líneas del documento.
					$sig = $name . '|' . $source;
					if ( ! isset( $codes[ $sig ] ) ) {
						$codes[ $sig ] = array( $name, array( 'widget' => $source, 'target' => $target, 'name' => '', 'count' => 0 ), array() );
					}
					$codes[ $sig ][2][ (string) ( $code['key'] ?? '' ) ] = true;
					++$codes[ $sig ][1]['count'];
					continue;
				}
				$sig = $name . '|' . $source . '|' . (string) ( $code['key'] ?? '' ) . '|' . (string) ( $code['reason'] ?? '' );
				if ( ! isset( $codes[ $sig ] ) ) {
					$codes[ $sig ] = array( $name, array( 'widget' => $source, 'target' => $target, 'name' => (string) ( $code['key'] ?? '' ), 'note' => (string) ( $code['reason'] ?? '' ), 'count' => 0 ) );
				}
				++$codes[ $sig ][1]['count'];
			}
		}
		// Lo pendiente primero: el informe guarda un número de líneas por documento y lo demás se cuenta aparte.
		$order = array( 'pro.unmapped' => 0, 'pro.condition_kept' => 0, 'pro.dropped' => 1, 'pro.approx' => 2, 'pro.css' => 3, 'pro.inert' => 5 );
		$out   = array();
		foreach ( $codes as $item ) {
			if ( isset( $item[2] ) ) {
				$keys           = array_keys( $item[2] );
				$item[1]['name'] = implode( ', ', array_map( static function ( $key ) {
					return '' === $key ? '(sin nombre)' : $key;
				}, $keys ) );
				unset( $item[2] );
			}
			$out[] = $item;
		}
		usort( $out, static function ( $a, $b ) use ( $order ) {
			return ( $order[ $a[0] ] ?? 3 ) <=> ( $order[ $b[0] ] ?? 3 );
		} );
		$pending = array_values( array_filter( $out, static function ( $item ) use ( $order ) {
			return ( $order[ $item[0] ] ?? 3 ) < 1;
		} ) );
		$rest    = array_values( array_filter( $out, static function ( $item ) use ( $order ) {
			return ( $order[ $item[0] ] ?? 3 ) >= 1;
		} ) );
		return array_merge( $pending, array_values( $converted ), $rest );
	}

	/**
	 * Informe de un documento convertido en una pasada anterior (etapa `report`): se describe su copia original, sin
	 * escribir nada, sólo si su contenido actual lleva widgets convertidos desde PRO.
	 */
	public static function describe_backup( int $post_id, array $original ): void {
		if ( ! self::available() || ! self::ready() ) {
			return;
		}
		$current = (string) get_metadata( 'post', $post_id, '_elementor_data', true );
		// Lo convertido lleva la marca del traductor; la sustitución del widget global, su plantilla de origen; el vínculo
		// (desde la corrección de fidelidad de C9), el widget global propio, sin ninguna de las dos.
		if ( false === strpos( $current, '"' . self::MARKER . '"' ) && false === strpos( $current, '"' . self::GLOBAL_KEY . '"' ) && false === strpos( $current, '"digitalisimo-global"' ) ) {
			return;
		}
		// Las claves de Element Pack de la copia son de la etapa de Element Pack (sus códigos los da ella); lo que se
		// recuperó, o no, lo informa recover_document() con el contenido actual.
		list( , $log ) = self::convert_data( $original );
		foreach ( self::codes( $log ) as $item ) {
			Report::add( $post_id, $item[0], $item[1] );
		}
	}

	/* ---------------------------------------------------------------- Inventario */

	/** Estado vacío del inventario. */
	private static function fresh_state( string $fingerprint, int $total ): array {
		return array(
			'v'           => self::VERSION,
			'available'   => true,
			'fingerprint' => $fingerprint,
			'cursor'      => 0,
			'processed'   => 0,
			'total'       => $total,
			'done'        => false,
			'time'        => time(),
			// Por widget: elementos y documentos (no revisiones), y lo mismo en revisiones y autoguardados.
			'widgets'     => array(),
			// Por familia: elementos y documentos (un documento cuenta una vez por familia).
			'families'    => array(),
			// Documentos con widgets de PRO por tipo de plantilla (`header`, `popup`, `loop-item`…) o tipo de entrada.
			'where'       => array(),
			'documents'   => 0,
			'revisions'   => 0,
			// Informe previsto (códigos `pro.*` de lo que Migrar convertiría), en el formato de Report.
			'report'      => Report::none(),
		);
	}

	/**
	 * Huella de lo que mira el inventario: documentos con `_elementor_data` (número, última fila y última modificación),
	 * copias de la migración, último informe de Migrar y mapas listos. Mientras no cambie, el inventario guardado vale.
	 *
	 * @return array{0:string, 1:int} huella y número de filas de `_elementor_data`.
	 */
	public static function fingerprint(): array {
		global $wpdb;
		$row     = $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(m.meta_id) AS last_id, MAX(p.post_modified_gmt) AS modified FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- sin datos de usuario.
		$total   = is_object( $row ) ? (int) $row->n : 0;
		$backups = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", Migration::BACKUP ) );
		$report  = class_exists( __NAMESPACE__ . '\\Runner', false ) ? get_option( Runner::REPORT, null ) : null;
		$parts   = array( self::VERSION, $total, is_object( $row ) ? (string) $row->last_id : '', is_object( $row ) ? (string) $row->modified : '', $backups, is_array( $report ) ? (int) ( $report['finished'] ?? 0 ) : 0, implode( ',', array_keys( self::ready() ) ) . ( self::globals() ? ',global' : '' ) );
		return array( md5( implode( '|', $parts ) ), $total );
	}

	/** ¿Está completo y al día un inventario guardado? (sin el núcleo de Elements, basta con que lo diga) */
	public static function current( $state ): bool {
		if ( ! is_array( $state ) || empty( $state['done'] ) ) {
			return false;
		}
		if ( ! self::available() ) {
			return empty( $state['available'] );
		}
		return ! empty( $state['available'] ) && (int) ( $state['v'] ?? 0 ) === self::VERSION && (string) ( $state['fingerprint'] ?? '' ) === self::fingerprint()[0];
	}

	/**
	 * Una vuelta del inventario: sigue desde el cursor de $state (o empieza si no hay, o si la huella cambió) hasta el
	 * plazo o ANALYSIS_MAX documentos ($deadline 0: sin plazo; $max 0: sin tope). Sin escribir nada: quien llama guarda
	 * el estado.
	 */
	public static function step( ?array $state, float $deadline = 0, int $max = self::ANALYSIS_MAX ): array {
		global $wpdb;
		if ( ! self::available() ) {
			return array( 'v' => self::VERSION, 'available' => false, 'done' => true, 'processed' => 0, 'total' => 0 );
		}
		list( $fingerprint, $total ) = self::fingerprint();
		if ( ! is_array( $state ) || empty( $state['available'] ) || (int) ( $state['v'] ?? 0 ) !== self::VERSION || (string) ( $state['fingerprint'] ?? '' ) !== $fingerprint ) {
			$state = self::fresh_state( $fingerprint, $total );
		}
		if ( ! empty( $state['done'] ) ) {
			return $state;
		}
		$count = 0;
		$mode  = Report::mode();
		Report::begin( 'analysis' );
		try {
			while ( true ) {
				// Una fila por documento con su tipo de entrada y, si es una plantilla, su tipo de plantilla.
				$sql  = "SELECT m.post_id, p.post_type, t.meta_value AS template_type, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id LEFT JOIN {$wpdb->postmeta} t ON t.post_id = m.post_id AND t.meta_key = '_elementor_template_type' WHERE m.meta_key = '_elementor_data' AND m.post_id > %d ORDER BY m.post_id LIMIT " . (int) self::INVENTORY_BATCH;
				$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, (int) $state['cursor'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$last = count( $rows ) < self::INVENTORY_BATCH;
				foreach ( $rows as $row ) {
					$post_id = (int) $row->post_id;
					if ( $post_id <= (int) $state['cursor'] ) {
						continue; // Fila repetida (dos metas de tipo de plantilla): el documento ya se contó.
					}
					$state           = self::count_document( $state, $post_id, (string) $row->post_type, (string) ( $row->template_type ?? '' ), (string) $row->meta_value );
					$state['cursor'] = $post_id;
					++$state['processed'];
					++$count;
				}
				unset( $rows, $row );
				if ( $last ) {
					$state['done'] = true;
					break;
				}
				if ( ( $deadline > 0 && microtime( true ) >= $deadline ) || ( $max > 0 && $count >= $max ) ) {
					break;
				}
			}
			$state['report'] = Report::merge( (array) $state['report'], Report::take() );
		} finally {
			Report::begin( $mode );
		}
		return $state;
	}

	/** Suma un documento al inventario y, si no es una revisión, lo que Migrar haría con él al informe previsto. */
	private static function count_document( array $state, int $post_id, string $post_type, string $template_type, string $raw ): array {
		if ( '' === $raw || false === strpos( $raw, '"widgetType"' ) ) {
			return $state;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return $state;
		}
		$converter = self::CONVERTER;
		$counts    = (array) $converter::count( $data );
		if ( ! $counts ) {
			return $state;
		}
		$revision = 'revision' === $post_type;
		$families = array();
		foreach ( $counts as $type => $n ) {
			$type = (string) $type;
			if ( ! isset( $state['widgets'][ $type ] ) ) {
				$state['widgets'][ $type ] = array( 'elements' => 0, 'documents' => 0, 'revision_elements' => 0, 'revisions' => 0 );
			}
			if ( $revision ) {
				$state['widgets'][ $type ]['revision_elements'] += (int) $n;
				++$state['widgets'][ $type ]['revisions'];
				continue;
			}
			$state['widgets'][ $type ]['elements'] += (int) $n;
			++$state['widgets'][ $type ]['documents'];
			$family = self::family( $type );
			if ( ! isset( $state['families'][ $family ] ) ) {
				$state['families'][ $family ] = array( 'elements' => 0, 'documents' => 0 );
			}
			$state['families'][ $family ]['elements'] += (int) $n;
			if ( empty( $families[ $family ] ) ) {
				++$state['families'][ $family ]['documents'];
				$families[ $family ] = true;
			}
		}
		if ( $revision ) {
			++$state['revisions'];
			return $state;
		}
		++$state['documents'];
		$where                     = 'elementor_library' === $post_type && '' !== $template_type ? $template_type : $post_type;
		$where                     = '' !== $where ? $where : 'post';
		$state['where'][ $where ]  = (int) ( $state['where'][ $where ] ?? 0 ) + 1;
		if ( array_intersect_key( $counts, self::ready() ) || ( isset( $counts['global'] ) && self::globals() ) ) {
			Context::begin( $post_id, 'analysis' );
			try {
				list( , $log, $blocked, $missing, $kept ) = self::convert_data( $data );
				self::report_missing( $post_id, $missing );
				foreach ( self::codes( $log ) as $item ) {
					Report::add( $post_id, $item[0], $item[1] );
				}
				foreach ( $blocked as $type ) {
					$item = self::blocked_item( $type );
					Report::add( $post_id, $item[0], $item[1] );
				}
				if ( ! $blocked ) {
					self::report_kept( $post_id, (array) $kept );
				}
			} finally {
				Context::end();
			}
		}
		return $state;
	}

	/**
	 * Inventario completo del sitio en curso, sin caché (WP-CLI y pruebas): recorre todos los documentos por lotes.
	 *
	 * @return array{available:bool, scanned:int, documents:int, revisions:int, widgets:array, families:array, where:array, report:array}
	 */
	public static function inventory(): array {
		$state = null;
		do {
			$state = self::step( $state, 0, 0 );
		} while ( empty( $state['done'] ) );
		return self::describe( $state );
	}

	/**
	 * Lectura de un estado del inventario: filas por widget con su familia, su destino si hay mapa listo y su estado,
	 * ordenadas por familia y uso; familias de más a menos usadas.
	 */
	public static function describe( array $state ): array {
		if ( empty( $state['available'] ) ) {
			return array( 'available' => false, 'scanned' => 0, 'documents' => 0, 'revisions' => 0, 'widgets' => array(), 'families' => array(), 'where' => array(), 'report' => Report::none() );
		}
		$ready   = self::ready();
		$missing = self::missing_targets();
		$widgets = array();
		foreach ( (array) ( $state['widgets'] ?? array() ) as $type => $row ) {
			$type     = (string) $type;
			$family   = self::family( $type );
			$target   = 'global' === $type && self::globals() ? self::global_target() : (string) ( $ready[ $type ] ?? '' );
			$widgets[] = array(
				'widget'            => $type,
				'family'            => $family,
				'family_label'      => self::family_label( $family ),
				'elements'          => (int) $row['elements'],
				'documents'         => (int) $row['documents'],
				'revision_elements' => (int) $row['revision_elements'],
				'revisions'         => (int) $row['revisions'],
				'target'            => $target,
				'status'            => self::status( $type, $target, $family, $missing ),
			);
		}
		usort( $widgets, static function ( $a, $b ) {
			return array( $a['family'], -$a['elements'], -$a['revision_elements'], $a['widget'] ) <=> array( $b['family'], -$b['elements'], -$b['revision_elements'], $b['widget'] );
		} );
		$families = array();
		foreach ( (array) ( $state['families'] ?? array() ) as $family => $row ) {
			$families[ (string) $family ] = array( 'label' => self::family_label( (string) $family ), 'elements' => (int) $row['elements'], 'documents' => (int) $row['documents'] );
		}
		uksort( $families, static function ( $a, $b ) use ( $families ) {
			return array( -$families[ $a ]['elements'], -$families[ $a ]['documents'], $a ) <=> array( -$families[ $b ]['elements'], -$families[ $b ]['documents'], $b );
		} );
		$where = array_map( 'intval', (array) ( $state['where'] ?? array() ) );
		arsort( $where );
		return array(
			'available' => true,
			'scanned'   => (int) ( $state['processed'] ?? 0 ),
			'documents' => (int) ( $state['documents'] ?? 0 ),
			'revisions' => (int) ( $state['revisions'] ?? 0 ),
			'widgets'   => $widgets,
			'families'  => $families,
			'where'     => $where,
			'report'    => (array) ( $state['report'] ?? Report::none() ),
		);
	}

	/** Estado legible de un widget del inventario. */
	public static function status( string $type, string $target, string $family, ?array $missing = null ): string {
		if ( '' !== $target ) {
			return sprintf( 'Se convierte a %s', $target );
		}
		$missing = null === $missing ? self::missing_targets() : $missing;
		if ( isset( $missing[ $type ] ) ) {
			$plugin = self::missing_dependency( $type );
			if ( '' !== $plugin ) {
				return sprintf( 'Pendiente: %s sólo se registra con %s activo', '' !== $missing[ $type ] ? $missing[ $type ] : 'su widget propio', $plugin );
			}
			return sprintf( 'Pendiente: %s no está registrado en este sitio', '' !== $missing[ $type ] ? $missing[ $type ] : 'su widget propio' );
		}
		return sprintf( 'Pendiente: familia %s', '' !== $family ? $family : '?' );
	}

	/* ---------------------------------------------------------------- Presentación */

	/** Progreso del inventario para la pantalla. */
	public static function progress_html( ?array $state ): string {
		$state = (array) $state;
		return '<p class="digi-migrate__analyzing">' . esc_html( sprintf( 'Contando los widgets de PRO… (%d de %d documentos)', (int) ( $state['processed'] ?? 0 ), (int) ( $state['total'] ?? 0 ) ) ) . '</p>';
	}

	/** Tabla «Widgets de PRO» del análisis (escapada campo a campo). */
	public static function table_html( array $inventory ): string {
		$html = '<h3>' . esc_html( 'Widgets de PRO' ) . '</h3>';
		if ( empty( $inventory['available'] ) ) {
			return $html . '<div class="notice notice-warning inline"><p>' . esc_html( 'Para contar y convertir los widgets de PRO hay que actualizar DIGITALÍSIMO Elements: esta versión no trae el núcleo de conversión. Migrar no toca esos widgets, que se siguen viendo igual.' ) . '</p></div>';
		}
		if ( ! $inventory['widgets'] ) {
			return $html . '<p>' . esc_html( sprintf( 'Ningún documento usa widgets de PRO (%d documentos revisados).', (int) $inventory['scanned'] ) ) . '</p>';
		}
		$html .= '<p>' . esc_html( sprintf( 'Widgets de PRO en %d documentos (entradas, páginas, plantillas, popups y Loop Items) y en %d revisiones o autoguardados, de %d documentos revisados. Los que tienen widget propio se convierten al pulsar Migrar; los demás se quedan como están y se siguen viendo igual hasta que se publique su familia.', (int) $inventory['documents'], (int) $inventory['revisions'], (int) $inventory['scanned'] ) ) . '</p>';
		$html .= '<table class="widefat striped digi-migrate__pro" style="max-width:960px"><thead><tr><th>' . esc_html( 'Familia' ) . '</th><th>' . esc_html( 'Widget' ) . '</th><th>' . esc_html( 'Elementos' ) . '</th><th>' . esc_html( 'Documentos' ) . '</th><th>' . esc_html( 'En revisiones' ) . '</th><th>' . esc_html( 'Estado' ) . '</th></tr></thead><tbody>';
		foreach ( $inventory['widgets'] as $row ) {
			$html .= '<tr><td>' . esc_html( $row['family'] . ' · ' . $row['family_label'] ) . '</td><td><code>' . esc_html( $row['widget'] ) . '</code></td><td>' . (int) $row['elements'] . '</td><td>' . (int) $row['documents'] . '</td><td>' . (int) $row['revision_elements'] . '</td><td>' . esc_html( $row['status'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		$parts = array();
		foreach ( $inventory['families'] as $family => $row ) {
			$parts[] = sprintf( '%s %s: %d elementos en %d documentos', $family, $row['label'], (int) $row['elements'], (int) $row['documents'] );
		}
		if ( $parts ) {
			$html .= '<p>' . esc_html( 'Por familia, de más a menos usada: ' . implode( '; ', $parts ) . '.' ) . '</p>';
		}
		$where = array();
		foreach ( $inventory['where'] as $type => $count ) {
			$where[] = sprintf( '%s: %d', $type, (int) $count );
		}
		if ( $where ) {
			$html .= '<p>' . esc_html( 'Documentos con widgets de PRO por tipo: ' . implode( ', ', $where ) . '.' ) . '</p>';
		}
		return $html;
	}

	/**
	 * Celda de la pantalla de red para el sitio en curso: documentos por convertir (una consulta) y, si el sitio tiene
	 * su análisis guardado, los widgets de PRO en uso. Nunca relee documentos.
	 */
	public static function network_cell( $analysis ): string {
		if ( ! self::available() ) {
			return 'Actualiza DIGITALÍSIMO Elements';
		}
		$parts   = array();
		$pending = count( self::documents() );
		$parts[] = sprintf( '%d documentos por convertir', $pending );
		$state   = is_array( $analysis ) && isset( $analysis['pro'] ) && is_array( $analysis['pro'] ) ? $analysis['pro'] : null;
		if ( $state && ! empty( $state['done'] ) && ! empty( $state['available'] ) ) {
			$elements = 0;
			$waiting  = 0;
			$ready    = self::ready();
			foreach ( (array) $state['widgets'] as $type => $row ) {
				$elements += (int) $row['elements'];
				$waiting  += isset( $ready[ (string) $type ] ) || ( 'global' === (string) $type && self::globals() ) ? 0 : (int) $row['elements'];
			}
			$parts[] = sprintf( 'en uso: %d elementos de %d widgets (%d pendientes de su familia)', $elements, count( (array) $state['widgets'] ), $waiting );
		}
		return implode( '; ', $parts );
	}
}
