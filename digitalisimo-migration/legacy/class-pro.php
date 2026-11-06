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
 * - Widget global (C9, `Convert\Globals` de Elements): antes de convertir, cada instancia se sustituye por el elemento
 *   guardado en su plantilla de la biblioteca (`template_content()` la lee del sitio); lo que queda dentro se convierte
 *   con los mapas en la misma pasada. La plantilla no se borra y `--revert` devuelve la instancia (copia original). Una
 *   instancia sin plantilla se queda como está y el informe lo dice (`pro.global_missing`).
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

	/* ---------------------------------------------------------------- Conversión */

	/**
	 * Convierte en memoria los widgets de PRO con mapa listo de un árbol (sin escribir nada).
	 *
	 * @return array{0:array, 1:array, 2:string[], 3:array} elementos, registro de Converter::tree() (con una fila
	 *         `source => global` por instancia de widget global sustituida), widgets de PRO que no se convirtieron porque
	 *         su widget propio no está registrado e instancias de widget global sin plantilla (filas de `Globals::tree()`).
	 */
	public static function convert_data( array $data ): array {
		if ( ! self::available() ) {
			return array( $data, array(), array(), array() );
		}
		$converter = self::CONVERTER;
		$globals   = array();
		$missing   = array();
		$out       = $data;
		if ( self::globals() ) {
			$class = self::GLOBALS;
			$out   = $class::tree( $data, array( __CLASS__, 'template_content' ), $globals );
			foreach ( $globals as $i => $row ) {
				if ( empty( $row['expanded'] ) ) {
					$missing[] = $row;
					unset( $globals[ $i ] );
				}
			}
		}
		$log     = array();
		$out     = $converter::tree( $out, $log );
		$blocked = array();
		foreach ( $log as $row ) {
			if ( ! isset( self::ready()[ (string) ( $row['source'] ?? '' ) ] ) ) {
				$blocked[] = (string) $row['source'];
			}
		}
		if ( $blocked ) {
			// Un destino sin registrar: el documento entero queda como está (nunca a medias).
			return array( $data, array(), array_values( array_unique( $blocked ) ), $missing );
		}
		return array( $out, array_merge( array_values( $globals ), $log ), array(), $missing );
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
		list( $data, $log, $blocked, $missing ) = self::convert_data( $data );
		self::report_missing( $post_id, $missing );
		if ( $blocked ) {
			foreach ( $blocked as $type ) {
				Report::add( $post_id, 'pro.target_missing', array( 'widget' => $type, 'target' => (string) ( self::missing_targets()[ $type ] ?? '' ) ) );
			}
			return array( 'converted' => 0, 'blocked' => $blocked );
		}
		if ( ! $log ) {
			return $none;
		}
		$json   = (string) wp_json_encode( $data );
		$backup = (string) get_metadata( 'post', $post_id, Migration::BACKUP, true );
		$mark   = Migration::f8_mark( $post_id );
		if ( '' === $backup ) {
			// Primera escritura de la migración en este documento: la copia lleva dentro la marca F8 (como
			// Migration::convert_document()), así que el documento no pasa por migrado con una versión antigua.
			add_metadata( 'post', $post_id, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw, 'f8' => self::mark( $post_id, $json ) ) ) ), true );
		}
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( $json ) );
		if ( '' !== $backup && is_array( $mark ) ) {
			// Ya lo tocó esta migración: se actualiza la huella para que --revert no lo tome por editado. Una copia sin
			// marca (versión ≤ 1.0.20) se deja así: su modo restringido no cambia.
			$mark['hash'] = md5( $json );
			update_metadata( 'post', $post_id, Migration::F8_MARK, wp_slash( wp_json_encode( $mark ) ) );
		}
		Migration::clear_css( $post_id );
		foreach ( self::codes( $log ) as $item ) {
			Report::add( $post_id, $item[0], $item[1] );
		}
		return array( 'converted' => count( $log ), 'blocked' => array() );
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
				$sig = $name . '|' . $source . '|' . (string) ( $code['key'] ?? '' ) . '|' . (string) ( $code['reason'] ?? '' );
				if ( ! isset( $codes[ $sig ] ) ) {
					$codes[ $sig ] = array( $name, array( 'widget' => $source, 'target' => $target, 'name' => (string) ( $code['key'] ?? '' ), 'note' => (string) ( $code['reason'] ?? '' ), 'count' => 0 ) );
				}
				++$codes[ $sig ][1]['count'];
			}
		}
		return array_merge( array_values( $converted ), array_values( $codes ) );
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
		if ( false === strpos( $current, '"' . self::MARKER . '"' ) && false === strpos( $current, '"' . self::GLOBAL_KEY . '"' ) ) {
			return;
		}
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
				list( , $log, $blocked, $missing ) = self::convert_data( $data );
				self::report_missing( $post_id, $missing );
				foreach ( self::codes( $log ) as $item ) {
					Report::add( $post_id, $item[0], $item[1] );
				}
				foreach ( $blocked as $type ) {
					Report::add( $post_id, 'pro.target_missing', array( 'widget' => $type, 'target' => (string) ( self::missing_targets()[ $type ] ?? '' ) ) );
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
			$target   = 'global' === $type && self::globals() ? 'su contenido' : (string) ( $ready[ $type ] ?? '' );
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
