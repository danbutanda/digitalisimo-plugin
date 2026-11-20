<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

// Sólo fuera de WordPress (pruebas sin dobles de traducción): los catálogos de códigos usan __() con el dominio de
// Migración. Con WordPress cargado existe la función global y ésta no se declara.
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}

/**
 * Informe de F8 por documento y por sitio (V.14 C2, §3.G).
 *
 * Cada frente añade códigos con un prefijo propio (`core.` A, `ext.` C, `ps.` E, `ps.dyn.` F, `tb.` G, `plat.` H,
 * `mk.` I y `pro.` los widgets de PRO) y los declara en su catálogo `report/codes-<frente>.php`:
 *
 *     return array( 'core.target_missing' => array( 'section' => 'review', 'label' => __( '…', 'digitalisimo-migration' ) ) );
 *
 * con `section` ∈ SECTIONS y, opcionales, `review_if_published` (el elemento pasa a «Pendiente de revisar» si el
 * documento, o el `id` del detalle en un código de sitio, está publicado) y `private` (el `name` del detalle sólo lo
 * ve un superadministrador en una red o quien gestiona el sitio fuera de ella; los demás ven el recuento).
 *
 * El informe acumula en memoria y se vuelca (`flush()`) en la opción `digitalisimo_migration_report_f8`, sin carga
 * automática, cada FLUSH_EVERY documentos y al cerrar cada etapa. Sólo en modo `run`: el análisis no escribe nada y
 * el editor (filtro de K) descarta todo (V.7).
 *
 * `detail` sólo admite escalares en DETAIL_KEYS; `note` es texto plano de 200 caracteres como mucho y nunca lleva
 * código ni valores de ajustes. `render()` escapa cada campo y construye él mismo los enlaces.
 */
final class Report {
	const SECTIONS    = array( 'converted', 'approx', 'created', 'dropped', 'review' );
	const OPTION      = 'digitalisimo_migration_report_f8';
	const VERSION     = 1;
	const MAX_ITEMS   = 12;
	const MAX_DOCS    = 100;
	/** Tope de los elementos del sitio (plantillas, fuentes, iconos, fragmentos…: la plataforma lo llena antes). */
	const MAX_SITE_ITEMS = 60;
	/** Huellas guardadas de lo que no cupo, por lista (para no contarlo dos veces). */
	const OVER_MAX = 100;
	const FLUSH_EVERY = 20;
	const DETAIL_KEYS = array( 'widget', 'element', 'count', 'name', 'target', 'id', 'type', 'note' );
	const NOTE_MAX    = 200;
	/** Catálogos y el prefijo que cada uno puede declarar. */
	const CATALOGS = array(
		'core'       => 'core.',
		'ext'        => 'ext.',
		'ps'         => 'ps.',
		'ps-dynamic' => 'ps.dyn.',
		'tb'         => 'tb.',
		'platform'   => 'plat.',
		'markers'    => 'mk.',
		'pro'        => 'pro.',
	);

	/** @var string modo en curso: `run`, `analysis` o `editor`. */
	private static $mode = 'run';
	/** @var array lo acumulado desde el último volcado. */
	private static $memory = null;
	/** @var array|null catálogo cargado. */
	private static $catalog = null;
	/** @var int documentos añadidos desde el último volcado. */
	private static $pending_docs = 0;

	/* ---------------------------------------------------------------- Acumular */

	/** Empieza un informe en memoria: `run` (la tarea), `analysis` (antes del clic) o `editor` (descarta todo). */
	public static function begin( string $mode ): void {
		self::$mode         = in_array( $mode, array( 'run', 'analysis', 'editor' ), true ) ? $mode : 'editor';
		self::$memory       = self::none();
		self::$pending_docs = 0;
	}

	/** Modo en curso. */
	public static function mode(): string {
		return self::$mode;
	}

	/** Informe vacío. */
	public static function none(): array {
		return array( 'v' => self::VERSION, 'docs' => array(), 'omitted' => 0, 'site' => array( 'items' => array(), 'more' => 0 ) );
	}

	/** ¿Se descarta lo que llega? En el editor (por el informe o por el contexto de la conversión). */
	private static function discarding(): bool {
		return 'editor' === self::$mode || ( class_exists( __NAMESPACE__ . '\\Context', false ) && 'editor' === Context::mode() );
	}

	/** Un código de un documento (o de una revisión: se informa como su documento). */
	public static function add( int $post_id, string $code, array $detail = array() ): void {
		if ( self::discarding() || $post_id <= 0 || ! self::valid_code( $code ) ) {
			return;
		}
		if ( null === self::$memory ) {
			self::$memory = self::none();
		}
		$item = self::item( $code, $detail, $post_id );
		$new  = ! isset( self::$memory['docs'][ $post_id ] );
		self::$memory = self::add_doc_items( self::$memory, $post_id, array( $item ), 0 );
		if ( $new && 'run' === self::$mode && ++self::$pending_docs >= self::FLUSH_EVERY ) {
			self::flush();
		}
	}

	/** Un código del sitio (plantillas, plugins, contraseñas…). */
	public static function site( string $code, array $detail = array() ): void {
		if ( self::discarding() || ! self::valid_code( $code ) ) {
			return;
		}
		if ( null === self::$memory ) {
			self::$memory = self::none();
		}
		self::$memory['site'] = self::add_items( self::$memory['site'], array( self::item( $code, $detail, (int) ( $detail['id'] ?? 0 ) ) ), 0, self::MAX_SITE_ITEMS );
	}

	/**
	 * Vuelca lo acumulado en la opción del informe (sólo en modo `run`) y vacía la memoria. El runner lo llama cada
	 * FLUSH_EVERY documentos, al cerrar cada etapa y al terminar cada petición.
	 */
	public static function flush(): void {
		self::$pending_docs = 0;
		if ( 'run' !== self::$mode || null === self::$memory || self::is_empty( self::$memory ) ) {
			if ( 'run' === self::$mode ) {
				self::$memory = self::none();
			}
			return;
		}
		$stored = self::stored();
		update_option( self::OPTION, self::merge( $stored, self::$memory ), false );
		self::$memory = self::none();
	}

	/** Lo guardado por la tarea (vacío si nada). */
	public static function stored(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null;
		return is_array( $stored ) ? $stored + self::none() : self::none();
	}

	/** Borra lo guardado: una tarea nueva empieza su informe (la anterior queda en su resumen). */
	public static function clear(): void {
		self::$memory       = self::none();
		self::$pending_docs = 0;
		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::OPTION );
		}
	}

	/**
	 * Lo acumulado: en `run`, lo guardado más lo que hay en memoria (sin escribir); en `analysis`, la memoria. Deja la
	 * memoria vacía.
	 *
	 * @return array{v:int, docs:array<int, array{items:array, more:int}>, omitted:int, site:array{items:array, more:int}}
	 */
	public static function take(): array {
		$memory       = null === self::$memory ? self::none() : self::$memory;
		$out          = 'run' === self::$mode ? self::merge( self::stored(), $memory ) : self::merge( self::none(), $memory );
		self::$memory = self::none();
		return $out;
	}

	/** ¿No hay nada? */
	public static function is_empty( array $report ): bool {
		return empty( $report['docs'] ) && empty( $report['omitted'] ) && empty( $report['site']['items'] ) && empty( $report['site']['more'] );
	}

	/** Une dos informes sin contar dos veces lo mismo (firma = código + detalle). */
	public static function merge( array $a, array $b ): array {
		$a += self::none();
		$b += self::none();
		foreach ( (array) $b['docs'] as $post_id => $doc ) {
			$a = self::add_doc_items( $a, (int) $post_id, (array) ( $doc['items'] ?? array() ), (int) ( $doc['more'] ?? 0 ), (array) ( $doc['over'] ?? array() ) );
		}
		$a['omitted'] = max( (int) $a['omitted'], (int) $b['omitted'] );
		$a['site']    = self::add_items( (array) $a['site'] + array( 'items' => array(), 'more' => 0 ), (array) ( $b['site']['items'] ?? array() ), (int) ( $b['site']['more'] ?? 0 ), self::MAX_SITE_ITEMS, (array) ( $b['site']['over'] ?? array() ) );
		return $a;
	}

	private static function add_doc_items( array $report, int $post_id, array $items, int $more, array $hashes = array() ): array {
		if ( ! $items && ! $more && ! $hashes ) {
			return $report;
		}
		if ( ! isset( $report['docs'][ $post_id ] ) ) {
			if ( count( $report['docs'] ) >= self::MAX_DOCS ) {
				++$report['omitted'];
				return $report;
			}
			$report['docs'][ $post_id ] = array( 'items' => array(), 'more' => 0 );
		}
		$report['docs'][ $post_id ] = self::add_items( $report['docs'][ $post_id ], $items, $more, self::MAX_ITEMS, $hashes );
		ksort( $report['docs'] );
		return $report;
	}

	/**
	 * Añade elementos a una lista con tope, sin repetir. Lo que no cabe se cuenta en `more` y su huella corta queda en
	 * `over` (hasta OVER_MAX), así que reintentar o unir dos pasadas no lo cuenta dos veces; pasado ese tope se cuenta
	 * lo mayor, nunca la suma.
	 */
	private static function add_items( array $list, array $items, int $more, int $max = self::MAX_ITEMS, array $hashes = array() ): array {
		$list += array( 'items' => array(), 'more' => 0 );
		$over  = array_flip( array_map( 'strval', (array) ( $list['over'] ?? array() ) ) );
		$extra = max( 0, (int) $list['more'] - count( $over ) );
		$seen  = array();
		foreach ( $list['items'] as $item ) {
			$seen[ self::signature( (array) $item ) ] = true;
		}
		$new_extra = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['code'] ) ) {
				continue;
			}
			$signature = self::signature( $item );
			$short     = substr( md5( $signature ), 0, 10 );
			if ( isset( $seen[ $signature ] ) || isset( $over[ $short ] ) ) {
				continue;
			}
			if ( count( $list['items'] ) < $max ) {
				$seen[ $signature ] = true;
				$list['items'][]    = $item;
			} elseif ( count( $over ) < self::OVER_MAX ) {
				$over[ $short ] = true;
			} else {
				++$new_extra;
			}
		}
		foreach ( $hashes as $short ) {
			if ( ! isset( $over[ (string) $short ] ) && count( $over ) < self::OVER_MAX ) {
				$over[ (string) $short ] = true;
			}
		}
		// Lo que no tiene huella (pasado el tope, o de un informe sin `over`) se cuenta como lo mayor.
		$extra        = max( $extra + $new_extra, $more - count( $hashes ) );
		$list['over'] = array_keys( $over );
		$list['more'] = count( $over ) + max( 0, $extra );
		if ( ! $list['over'] ) {
			unset( $list['over'] );
		}
		return $list;
	}

	private static function signature( array $item ): string {
		$detail = (array) ( $item['detail'] ?? array() );
		ksort( $detail );
		return (string) ( $item['code'] ?? '' ) . '|' . md5( (string) json_encode( $detail ) );
	}

	/** Código con forma válida (`prefijo.nombre`, minúsculas, puntos y guiones bajos). */
	private static function valid_code( string $code ): bool {
		return 1 === preg_match( '/^(core|ext|ps|tb|plat|mk|pro)\.[a-z0-9_.]{1,80}$/', $code );
	}

	/** Elemento del informe con el detalle saneado: sólo escalares de DETAIL_KEYS. */
	private static function item( string $code, array $detail, int $post_id ): array {
		$clean = array();
		foreach ( self::DETAIL_KEYS as $key ) {
			if ( ! array_key_exists( $key, $detail ) || ! is_scalar( $detail[ $key ] ) ) {
				continue;
			}
			$value = $detail[ $key ];
			if ( 'count' === $key || 'id' === $key ) {
				$value = (int) $value;
			} elseif ( is_bool( $value ) ) {
				$value = $value ? '1' : '';
			} else {
				$value = self::plain( (string) $value, 'note' === $key ? self::NOTE_MAX : 120 );
			}
			$clean[ $key ] = $value;
		}
		$item = array( 'code' => $code, 'detail' => $clean );
		$spec = self::catalog()[ $code ] ?? null;
		if ( $spec && ! empty( $spec['review_if_published'] ) && $post_id > 0 && self::published( $post_id ) ) {
			$item['review'] = 1;
		}
		return $item;
	}

	/** Texto plano y corto: sin etiquetas ni caracteres de control. */
	private static function plain( string $text, int $max ): string {
		$text = strip_tags( $text );
		$text = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $text );
		$text = trim( $text );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text, 'UTF-8' ) > $max ? mb_substr( $text, 0, $max - 1, 'UTF-8' ) . '…' : $text;
		}
		return strlen( $text ) > $max ? substr( $text, 0, $max - 3 ) . '...' : $text;
	}

	private static function published( int $post_id ): bool {
		if ( function_exists( 'wp_is_post_revision' ) ) {
			$parent  = (int) wp_is_post_revision( $post_id );
			$post_id = $parent ? $parent : $post_id;
		}
		return function_exists( 'get_post_status' ) && 'publish' === get_post_status( $post_id );
	}

	/* ---------------------------------------------------------------- Catálogo */

	/**
	 * Códigos de todos los frentes: `code => { section, label, review_if_published?, private? }`. Un código cuyo prefijo
	 * no es el de su catálogo, o con una sección desconocida, se ignora.
	 */
	public static function catalog(): array {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}
		$out = array();
		foreach ( self::CATALOGS as $name => $prefix ) {
			$file = __DIR__ . '/report/codes-' . $name . '.php';
			if ( ! is_file( $file ) ) {
				continue;
			}
			try {
				$codes = include $file;
			} catch ( \Throwable $e ) {
				// Un catálogo que no carga (por ejemplo, sin `namespace` en un entorno sin __()) no tumba el informe.
				$codes = array();
			}
			foreach ( is_array( $codes ) ? $codes : array() as $code => $spec ) {
				$code = (string) $code;
				if ( 0 !== strpos( $code, $prefix ) || ! is_array( $spec ) || ! in_array( $spec['section'] ?? '', self::SECTIONS, true ) || isset( $out[ $code ] ) ) {
					continue;
				}
				// `ps.dyn.` es de su propio catálogo: el de `ps.` no puede declararlo.
				if ( 'ps' === $name && 0 === strpos( $code, 'ps.dyn.' ) ) {
					continue;
				}
				$out[ $code ] = array(
					'section'             => (string) $spec['section'],
					'label'               => (string) ( $spec['label'] ?? $code ),
					'review_if_published' => ! empty( $spec['review_if_published'] ),
					'private'             => ! empty( $spec['private'] ),
				);
			}
		}
		self::$catalog = $out;
		return $out;
	}

	/** Vacía el catálogo cargado (pruebas). */
	public static function reset_catalog(): void {
		self::$catalog = null;
	}

	/** Sección efectiva de un elemento. Un código fuera del catálogo va a «Pendiente de revisar»: alguien debe verlo. */
	public static function section_of( array $item ): string {
		if ( ! empty( $item['review'] ) ) {
			return 'review';
		}
		$spec = self::catalog()[ (string) ( $item['code'] ?? '' ) ] ?? null;
		return $spec ? $spec['section'] : 'review';
	}

	/* ---------------------------------------------------------------- Lectura */

	/** ¿Queda algo para una persona? Sólo «Pendiente de revisar» cuenta (F8-D25). */
	public static function pending( array $report ): bool {
		foreach ( self::all_items( $report ) as $item ) {
			if ( 'review' === self::section_of( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/** Recuento por sección (documentos y sitio), para el resumen guardado y las columnas de la red. */
	public static function summary( array $report ): array {
		$out = array_fill_keys( self::SECTIONS, 0 );
		foreach ( self::all_items( $report ) as $item ) {
			$out[ self::section_of( $item ) ] += max( 1, (int) ( $item['detail']['count'] ?? 1 ) );
		}
		$out['documents'] = count( (array) ( $report['docs'] ?? array() ) ) + (int) ( $report['omitted'] ?? 0 );
		$out['pending']   = self::pending( $report );
		return $out;
	}

	/** Recuento de un código (o de los que empiezan por un prefijo terminado en punto). */
	public static function count_code( array $report, string $code ): int {
		$total = 0;
		$open  = '.' === substr( $code, -1 );
		foreach ( self::all_items( $report ) as $item ) {
			$c = (string) ( $item['code'] ?? '' );
			if ( $open ? 0 === strpos( $c, $code ) : $c === $code ) {
				$total += max( 1, (int) ( $item['detail']['count'] ?? 1 ) );
			}
		}
		return $total;
	}

	private static function all_items( array $report ): array {
		$items = array();
		foreach ( (array) ( $report['docs'] ?? array() ) as $doc ) {
			foreach ( (array) ( $doc['items'] ?? array() ) as $item ) {
				if ( is_array( $item ) ) {
					$items[] = $item;
				}
			}
		}
		foreach ( (array) ( $report['site']['items'] ?? array() ) as $item ) {
			if ( is_array( $item ) ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/* ---------------------------------------------------------------- Presentación */

	/**
	 * HTML del informe, con cada campo escapado. `site`: por sección y documento, con enlaces construidos aquí; `cli`:
	 * lo mismo sin enlaces (la terminal lo pasa a texto); `network`: sólo el recuento por sección. `$sections` limita
	 * las secciones (el detalle por documento del resumen final deja fuera «Pendiente de revisar», que va arriba) y
	 * `$level` es el nivel de sus encabezados.
	 */
	public static function render( array $report, string $where, array $sections = array(), int $level = 3 ): string {
		$report += self::none();
		if ( self::is_empty( $report ) ) {
			return '';
		}
		$level = max( 2, min( 6, $level ) );
		$labels = self::section_labels();
		if ( 'network' === $where ) {
			$parts = array();
			foreach ( self::summary( $report ) as $section => $count ) {
				if ( isset( $labels[ $section ] ) && $count ) {
					$parts[] = $labels[ $section ] . ': ' . (int) $count;
				}
			}
			return $parts ? '<span class="digi-migrate__f8">' . esc_html( implode( '; ', $parts ) ) . '</span>' : '';
		}
		$links = 'cli' !== $where;
		// Por sección: [ documento|sitio => [ elementos ] ].
		$by = array_fill_keys( self::SECTIONS, array() );
		foreach ( (array) $report['docs'] as $post_id => $doc ) {
			foreach ( (array) ( $doc['items'] ?? array() ) as $item ) {
				if ( is_array( $item ) ) {
					$by[ self::section_of( $item ) ][ (int) $post_id ][] = $item;
				}
			}
			if ( ! empty( $doc['more'] ) ) {
				$by['review'][ (int) $post_id ] = $by['review'][ (int) $post_id ] ?? array();
			}
		}
		foreach ( (array) $report['site']['items'] as $item ) {
			if ( is_array( $item ) ) {
				$by[ self::section_of( $item ) ][0][] = $item;
			}
		}
		$html = '';
		// «Pendiente de revisar» primero: es lo único que pide algo a una persona.
		foreach ( array( 'review', 'converted', 'approx', 'created', 'dropped' ) as $section ) {
			$groups = array_filter( $by[ $section ] );
			if ( ! $groups || ( $sections && ! in_array( $section, $sections, true ) ) ) {
				continue;
			}
			$html .= '<h' . $level . ' class="digi-migrate__section digi-migrate__section--' . esc_attr( $section ) . '">' . esc_html( $labels[ $section ] ) . '</h' . $level . '><ul class="digi-migrate__report" style="list-style:disc;margin-left:20px">';
			ksort( $groups );
			foreach ( $groups as $post_id => $items ) {
				$html .= '<li>' . ( $post_id ? self::document_html( (int) $post_id, $links ) : esc_html( __( 'Sitio', 'digitalisimo-migration' ) ) ) . '<ul style="list-style:circle;margin-left:20px">';
				foreach ( $items as $item ) {
					$html .= '<li>' . self::item_html( $item ) . '</li>';
				}
				$html .= '</ul></li>';
			}
			$html .= '</ul>';
		}
		$more = 0;
		foreach ( (array) $report['docs'] as $doc ) {
			$more += (int) ( $doc['more'] ?? 0 );
		}
		$more += (int) ( $report['site']['more'] ?? 0 );
		// Con secciones elegidas es el detalle por documento del resumen final: lo que no cupo lo dice render_detail().
		if ( ! $sections && ( $more || ! empty( $report['omitted'] ) ) ) {
			$html .= '<p class="digi-migrate__more">' . esc_html( sprintf( __( 'Además: %1$d anotaciones más en los documentos listados y %2$d documentos que no caben en la lista.', 'digitalisimo-migration' ), $more, (int) $report['omitted'] ) ) . '</p>';
		}
		return $html;
	}

	/** Nombres visibles de las secciones. */
	public static function section_labels(): array {
		return array(
			'converted' => __( 'Convertido', 'digitalisimo-migration' ),
			'approx'    => __( 'Convertido con diferencias', 'digitalisimo-migration' ),
			'created'   => __( 'Creado', 'digitalisimo-migration' ),
			'dropped'   => __( 'Sin equivalente', 'digitalisimo-migration' ),
			'review'    => __( 'Pendiente de revisar', 'digitalisimo-migration' ),
		);
	}

	/* ---------------------------------------------------------------- Resumen final (agregado) */

	/** Códigos que «Necesita tu atención» agrupa también por su `name` (el plugin del que depende el widget propio). */
	const ATTENTION_BY_NAME = array( 'pro.target_dependency' );
	/** Documentos que «Necesita tu atención» enlaza por asunto; los demás, en «Detalle» → «Por documento». */
	const ATTENTION_PLACES = 10;

	/**
	 * Acción concreta de los asuntos más frecuentes de «Pendiente de revisar», con la acción delante. Los demás usan su
	 * texto del catálogo, que ya la dice. `%1$s` es el `name` del detalle (el plugin, en pro.target_dependency).
	 */
	private static function actions(): array {
		return array(
			'core.residual'         => __( 'Ajustes de extensiones de Element Pack que ya no se aplican: abre el documento, mira si estaban encendidas y, si las necesitas, rehazlas con las opciones de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ),
			'core.document_failed'  => __( 'No se pudo convertir (la conversión se cortó dos veces) y se sigue viendo como antes: vuelve a pulsar Migrar o revísalo en el editor.', 'digitalisimo-migration' ),
			'pro.target_dependency' => __( 'Widgets de PRO sin convertir porque su widget propio necesita %1$s, que no está activo (sin %1$s tampoco se pintaban): si activas %1$s, vuelve a pulsar Migrar; si no lo usas, no hace falta nada.', 'digitalisimo-migration' ),
			'pro.target_missing'    => __( 'Widgets de PRO sin convertir porque su widget propio no está registrado en este sitio (se siguen viendo igual): actualiza DIGITALÍSIMO Elements y vuelve a pulsar Migrar.', 'digitalisimo-migration' ),
			'pro.unmapped'          => __( 'Ajustes de widgets de PRO que la conversión no reconoce y no pasaron al widget propio: revisa esos elementos en el editor.', 'digitalisimo-migration' ),
			'pro.condition_kept'    => __( 'Elementos que siguen como widget de PRO por una condición encendida de otro plugin (se ven y se ocultan igual): si quieres el widget propio, rehaz la condición con las opciones de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ),
			'pro.condition_lost'    => __( 'Una condición de otro plugin que una versión anterior no pasó y que ya no puede recuperarse: revisa en el editor si el elemento debe mostrarse u ocultarse.', 'digitalisimo-migration' ),
			'pro.recover_ambiguous' => __( 'Elementos con un id repetido en los que no se recuperó nada: revísalos en el editor.', 'digitalisimo-migration' ),
			'pro.global_missing'    => __( 'Widgets globales cuya plantilla no existe o está en la papelera (tampoco se pintaban): bórralos o elige otra plantilla en el editor.', 'digitalisimo-migration' ),
		);
	}

	/**
	 * Lo agregado del informe para el resumen final, sin escribir nada:
	 * - `attention`: lo de «Pendiente de revisar», por código (y plugin, ATTENTION_BY_NAME) y documento (0 = el sitio);
	 * - `widgets`: `pro.converted` por widget de origen y destino, sin el global, con su total y sus documentos;
	 * - `globals`: el widget global (instancias, documentos y plantillas, de `pro.converted` y `pro.global`);
	 * - `recovered`: `pro.recovered` por clave. El `pro.inert` de esa clave y ese widget en el mismo documento es el
	 *   mismo ajuste (lo que la copia describe y lo que se recuperó) y no se cuenta otra vez;
	 * - `inert`: lo que se deja tal cual sin cambiar nada de lo que se ve (`pro.inert` y `core.residual_inert`);
	 * - `codes`: lo demás, por sección y código, con su total, sus documentos, sus valores y sus notas;
	 * - `more` y `omitted`: lo que no cupo en el informe.
	 */
	public static function digest( array $report ): array {
		$report += self::none();
		$out     = array(
			'attention' => array(),
			'widgets'   => array(),
			'globals'   => array( 'count' => 0, 'listed' => 0, 'docs' => array(), 'templates' => array(), 'per' => array() ),
			'recovered' => array(),
			'inert'     => array( 'docs' => array(), 'names' => array(), 'pages' => array() ),
			'codes'     => array( 'converted' => array(), 'approx' => array(), 'created' => array(), 'dropped' => array() ),
			'more'      => (int) ( $report['site']['more'] ?? 0 ),
			'omitted'   => (int) $report['omitted'],
		);
		$places  = array();
		foreach ( (array) $report['docs'] as $post_id => $doc ) {
			if ( (int) $post_id > 0 ) {
				$places[ (int) $post_id ] = (array) ( $doc['items'] ?? array() );
				$out['more']             += (int) ( $doc['more'] ?? 0 );
			}
		}
		$places[0] = (array) ( $report['site']['items'] ?? array() );
		foreach ( $places as $post_id => $items ) {
			$items     = array_values( array_filter( $items, 'is_array' ) );
			$recovered = array();
			foreach ( $items as $item ) {
				if ( 'pro.recovered' === ( $item['code'] ?? '' ) && 'review' !== self::section_of( $item ) ) {
					$recovered[ (string) ( $item['detail']['widget'] ?? '' ) ][ (string) ( $item['detail']['name'] ?? '' ) ] = true;
				}
			}
			foreach ( $items as $item ) {
				$out = self::digest_item( $out, (int) $post_id, $item, $recovered );
			}
		}
		return $out;
	}

	/** Suma un elemento del informe a lo agregado (digest()). */
	private static function digest_item( array $out, int $post_id, array $item, array $recovered ): array {
		$code    = (string) ( $item['code'] ?? '' );
		$detail  = (array) ( $item['detail'] ?? array() );
		$count   = max( 1, (int) ( $detail['count'] ?? 1 ) );
		$section = self::section_of( $item );
		$widget  = (string) ( $detail['widget'] ?? '' );
		$name    = (string) ( $detail['name'] ?? '' );
		if ( 'review' === $section ) {
			$by                                     = in_array( $code, self::ATTENTION_BY_NAME, true ) ? $name : '';
			$key                                    = $code . '|' . $by;
			$out['attention'][ $key ]['code']       = $code;
			$out['attention'][ $key ]['name']       = $by;
			$out['attention'][ $key ]['docs'][ $post_id ][] = $item;
			return $out;
		}
		if ( 'pro.converted' === $code && 'global' === $widget ) {
			$out['globals']['count']                       += $count;
			$out['globals']['docs'][ $post_id ]             = true;
			$out['globals']['per'][ $post_id ]['converted'] = (int) ( $out['globals']['per'][ $post_id ]['converted'] ?? 0 ) + $count;
			return $out;
		}
		if ( 'pro.converted' === $code ) {
			$key                                     = $widget . '|' . (string) ( $detail['target'] ?? '' );
			$out['widgets'][ $key ]                  = $out['widgets'][ $key ] ?? array( 'widget' => $widget, 'target' => (string) ( $detail['target'] ?? '' ), 'count' => 0, 'docs' => array() );
			$out['widgets'][ $key ]['count']        += $count;
			$out['widgets'][ $key ]['docs'][ $post_id ] = true;
			return $out;
		}
		if ( 'pro.global' === $code ) {
			// La nota de cada instancia nombra su plantilla: «Instancia de la plantilla 543 pasada al widget global propio…»
			// (sigue vinculada) o «…sustituida por el elemento guardado en su plantilla 543…» (con un Elements sin el widget
			// global propio: ya no sigue a su plantilla).
			$note                                        = (string) ( $detail['note'] ?? '' );
			$kind                                        = false !== stripos( $note, 'sustituida' ) ? 'substituted' : 'linked';
			$out['globals']['listed']                   += $count;
			$out['globals']['docs'][ $post_id ]          = true;
			$out['globals']['per'][ $post_id ]['listed'] = (int) ( $out['globals']['per'][ $post_id ]['listed'] ?? 0 ) + $count;
			$out['globals']['per'][ $post_id ][ $kind ]  = (int) ( $out['globals']['per'][ $post_id ][ $kind ] ?? 0 ) + $count;
			if ( preg_match( '/\d+/', $note, $m ) ) {
				$out['globals']['templates'][ (int) $m[0] ] = $kind;
			}
			return $out;
		}
		if ( 'pro.recovered' === $code ) {
			$row                        = $out['recovered'][ $name ] ?? array( 'count' => 0, 'docs' => array(), 'widgets' => array() );
			$row['count']              += $count;
			$row['docs'][ $post_id ]    = true;
			$row['widgets'][ $widget ]  = true;
			$out['recovered'][ $name ]  = $row;
			return $out;
		}
		if ( 'pro.inert' === $code ) {
			// Una línea por widget con sus claves separadas por comas (Pro::codes()); las recuperadas ya están arriba. Una
			// línea larga llega recortada (Report::plain()): el último trozo, cortado, no es una clave y se descarta.
			$keys = array_values( array_filter( array_map( 'trim', explode( ',', $name ) ), 'strlen' ) );
			if ( $keys && in_array( substr( (string) end( $keys ), -3 ), array( '…', '...' ), true ) ) {
				array_pop( $keys );
				if ( ! array_diff( $keys, array_keys( (array) ( $recovered[ $widget ] ?? array() ) ) ) ) {
					return $out;
				}
			}
			$left = array_diff( $keys, array_keys( (array) ( $recovered[ $widget ] ?? array() ) ) );
			if ( $keys && ! $left ) {
				return $out;
			}
			foreach ( $left as $key ) {
				$out['inert']['names'][ $key ] = true;
			}
			$out['inert']['docs'][ $post_id ] = true;
			return $out;
		}
		if ( 'core.residual_inert' === $code ) {
			$out['inert']['pages'][ $post_id ] = true;
			return $out;
		}
		$row             = $out['codes'][ $section ][ $code ] ?? array( 'count' => 0, 'docs' => array(), 'values' => array(), 'notes' => array() );
		$row['count']   += $count;
		$row['docs'][ $post_id ] = true;
		list( $parts, $note ) = self::detail_parts( $item, array( 'count', 'id' ) );
		if ( $parts ) {
			$row['values'][ implode( ' · ', $parts ) ] = true;
		}
		if ( '' !== $note ) {
			$row['notes'][ $note ] = true;
		}
		$out['codes'][ $section ][ $code ] = $row;
		return $out;
	}

	/**
	 * «Necesita tu atención»: una entrada por código (y plugin) con la acción concreta y cada documento enlazado a su
	 * editor, con lo que identifica el asunto en él (elemento, widget, destino, ajuste, cantidad); hasta ATTENTION_PLACES
	 * documentos por asunto (los demás, en «Por documento»). Si el informe no pudo guardarlo todo (topes de documentos y
	 * de anotaciones), una entrada lo dice: entre lo que no cupo puede haber asuntos. Sin las claves de $skip (asuntos que
	 * quien llama ya junta con otro). HTML escapado de cada entrada, sin `<li>`.
	 *
	 * @return string[]
	 */
	public static function attention( array $report, string $where, ?array $digest = null, array $skip = array() ): array {
		$digest  = $digest ?? self::digest( $report );
		$actions = self::actions();
		$out     = array();
		foreach ( $digest['attention'] as $key => $group ) {
			if ( in_array( (string) $key, $skip, true ) ) {
				continue;
			}
			$code = (string) $group['code'];
			$name = (string) $group['name'];
			$text = (string) ( self::catalog()[ $code ]['label'] ?? $code );
			if ( isset( $actions[ $code ] ) && ( false === strpos( $actions[ $code ], '%1$s' ) || '' !== $name ) ) {
				$text = sprintf( $actions[ $code ], $name );
			}
			$out[] = esc_html( $text ) . ' ' . self::attention_places( $digest, (string) $key, $where );
		}
		if ( $digest['more'] || $digest['omitted'] ) {
			$out[] = esc_html(
				sprintf(
					'cli' === $where ? __( 'El informe no pudo guardarlo todo (guarda hasta %1$d anotaciones por documento y %2$d documentos): faltan %3$d anotaciones y %4$d documentos, y entre ellos puede haber asuntos que no salen en esta lista. Revisa el detalle por documento y el análisis del sitio en la pantalla Migrar a DIGITALÍSIMO.', 'digitalisimo-migration' ) : __( 'El informe no pudo guardarlo todo (guarda hasta %1$d anotaciones por documento y %2$d documentos): faltan %3$d anotaciones y %4$d documentos, y entre ellos puede haber asuntos que no salen en esta lista. Revisa «Por documento» en el detalle y el análisis del sitio, abajo.', 'digitalisimo-migration' ),
					self::MAX_ITEMS,
					self::MAX_DOCS,
					(int) $digest['more'],
					(int) $digest['omitted']
				)
			);
		}
		return $out;
	}

	/**
	 * «Dónde: …» de un asunto de attention(): cada documento enlazado a su editor con lo que identifica el asunto en él,
	 * hasta ATTENTION_PLACES. '' si el asunto no existe. HTML escapado.
	 */
	public static function attention_places( array $digest, string $key, string $where ): string {
		if ( empty( $digest['attention'][ $key ]['docs'] ) ) {
			return '';
		}
		$group  = $digest['attention'][ $key ];
		$links  = 'cli' !== $where;
		$skip   = in_array( (string) $group['code'], self::ATTENTION_BY_NAME, true ) ? array( 'name', 'target' ) : array();
		$places = array();
		$docs   = (array) $group['docs'];
		ksort( $docs );
		// El sitio, al final.
		if ( isset( $docs[0] ) ) {
			$site = $docs[0];
			unset( $docs[0] );
			$docs[0] = $site;
		}
		foreach ( array_slice( $docs, 0, self::ATTENTION_PLACES, true ) as $post_id => $items ) {
			$bits = array();
			foreach ( $items as $item ) {
				// La cantidad sólo cuando dice algo (más de una).
				if ( (int) ( $item['detail']['count'] ?? 0 ) <= 1 ) {
					unset( $item['detail']['count'] );
				}
				list( $parts, $note ) = self::detail_parts( $item, $skip );
				$bit                  = implode( ' · ', $parts ) . ( '' !== $note ? ( $parts ? ' — ' : '' ) . $note : '' );
				if ( '' !== $bit ) {
					$bits[ $bit ] = true;
				}
			}
			$place    = $post_id ? self::document_html( (int) $post_id, $links, true ) : esc_html( __( 'el sitio', 'digitalisimo-migration' ) );
			$places[] = $place . ( $bits ? ' <span class="digi-migrate__detail">(' . esc_html( implode( '; ', array_keys( $bits ) ) ) . ')</span>' : '' );
		}
		$rest = count( $docs ) - self::ATTENTION_PLACES;
		$tail = $rest > 0 ? ' ' . esc_html( sprintf( 'cli' === $where ? __( 'y %d más (la lista completa está en la pantalla, en «Detalle» → «Por documento»)', 'digitalisimo-migration' ) : __( 'y %d más (lista completa en «Detalle» → «Por documento»)', 'digitalisimo-migration' ), $rest ) ) : '';
		return '<span class="digi-migrate__where">' . esc_html( __( 'Dónde:', 'digitalisimo-migration' ) ) . ' ' . implode( ', ', $places ) . $tail . '.</span>';
	}

	/**
	 * Añade a lo agregado (digest()) un asunto de un documento que el informe no trae (lo que quien llama encuentra por su
	 * cuenta, sin escribir nada), salvo que ese documento ya esté en ese asunto.
	 */
	public static function add_attention( array $digest, string $code, string $name, int $post_id, array $detail ): array {
		$key = $code . '|' . ( in_array( $code, self::ATTENTION_BY_NAME, true ) ? $name : '' );
		if ( isset( $digest['attention'][ $key ]['docs'][ $post_id ] ) ) {
			return $digest;
		}
		$digest['attention'][ $key ]['code']                = $code;
		$digest['attention'][ $key ]['name']                = in_array( $code, self::ATTENTION_BY_NAME, true ) ? $name : '';
		$digest['attention'][ $key ]['docs'][ $post_id ][] = array( 'code' => $code, 'detail' => $detail );
		return $digest;
	}

	/** Documentos de un asunto de «Necesita tu atención» (sin el sitio). */
	public static function attention_docs( array $digest, string $key ): array {
		return array_values( array_filter( array_map( 'intval', array_keys( (array) ( $digest['attention'][ $key ]['docs'] ?? array() ) ) ) ) );
	}

	/**
	 * Detalle agregado del informe, sin «Pendiente de revisar» (va en attention()): por sección, una línea por tipo de
	 * widget de PRO con su total, una para el widget global, una por clave recuperada de la copia, una para lo que se deja
	 * tal cual y una por código para lo demás; y el detalle por documento en otro desplegable (en `cli`, no: la terminal
	 * resume y remite a la pantalla). Todo escapado.
	 */
	public static function render_detail( array $report, string $where, int $level = 3, ?array $digest = null ): string {
		$report += self::none();
		if ( self::is_empty( $report ) ) {
			return '';
		}
		$level  = max( 2, min( 5, $level ) );
		$d      = $digest ?? self::digest( $report );
		$html   = '';
		$labels = self::section_labels();
		foreach ( array( 'converted', 'approx', 'created', 'dropped' ) as $section ) {
			$lines = 'converted' === $section ? self::converted_lines( $d ) : array();
			foreach ( $d['codes'][ $section ] as $code => $row ) {
				$lines[] = self::code_line( (string) $code, $row );
			}
			if ( ! $lines ) {
				continue;
			}
			$html .= '<h' . $level . ' class="digi-migrate__section digi-migrate__section--' . esc_attr( $section ) . '">' . esc_html( $labels[ $section ] ) . '</h' . $level . '><ul class="digi-migrate__digest"><li>' . implode( '</li><li>', $lines ) . '</li></ul>';
		}
		if ( 'cli' === $where ) {
			return $html . ( '' !== $html ? '<p>' . esc_html( __( 'El detalle por documento está en la pantalla Migrar a DIGITALÍSIMO del sitio.', 'digitalisimo-migration' ) ) . '</p>' : '' );
		}
		// Por documento, todo: también lo pendiente, con cada campo (lo que «Necesita tu atención» no enlaza va aquí).
		$docs = self::render( $report, $where, array( 'review', 'converted', 'approx', 'created', 'dropped' ), $level + 1 );
		if ( '' !== $docs ) {
			$html .= '<details class="digi-migrate__docs"><summary>' . esc_html( __( 'Por documento', 'digitalisimo-migration' ) ) . '</summary>' . $docs . '</details>';
		}
		return $html;
	}

	/**
	 * Frase de lo que el informe dice que ya es de DIGITALÍSIMO Elements: widgets de PRO e instancias del widget global,
	 * con sus documentos. '' si no hay ninguno. Texto plano.
	 */
	public static function converted_phrase( array $d ): string {
		$widgets = 0;
		$docs    = array();
		foreach ( (array) $d['widgets'] as $row ) {
			$widgets += (int) $row['count'];
			$docs    += (array) $row['docs'];
		}
		$globals = self::globals_total( $d['globals'] );
		$docs   += (array) $d['globals']['docs'];
		$where   = self::docs_text( $docs );
		// Con topes, lo contado se queda corto.
		$least = $d['more'] || $d['omitted'] ? __( 'Al menos', 'digitalisimo-migration' ) . ' ' : '';
		if ( $widgets && $globals ) {
			return $least . sprintf( __( '%1$s y %2$s en %3$s son ahora widgets propios de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ), self::plural( $widgets, __( 'widget de PRO', 'digitalisimo-migration' ), __( 'widgets de PRO', 'digitalisimo-migration' ) ), self::plural( $globals, __( 'instancia del widget global', 'digitalisimo-migration' ), __( 'instancias de widgets globales', 'digitalisimo-migration' ) ), $where );
		}
		if ( $widgets ) {
			return $least . sprintf( __( '%1$s en %2$s son ahora widgets propios de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ), self::plural( $widgets, __( 'widget de PRO', 'digitalisimo-migration' ), __( 'widgets de PRO', 'digitalisimo-migration' ) ), $where );
		}
		if ( $globals ) {
			return $least . sprintf( __( '%1$s en %2$s pasaron al widget global propio.', 'digitalisimo-migration' ), self::plural( $globals, __( 'instancia del widget global', 'digitalisimo-migration' ), __( 'instancias de widgets globales', 'digitalisimo-migration' ) ), $where );
		}
		return '';
	}

	/**
	 * Instancias del widget global: por documento, lo mayor entre lo convertido (`pro.converted`) y lo listado
	 * (`pro.global`), porque el tope de anotaciones puede dejar fuera cualquiera de los dos.
	 */
	private static function globals_total( array $g ): int {
		$total = 0;
		foreach ( (array) ( $g['per'] ?? array() ) as $row ) {
			$total += max( (int) ( $row['converted'] ?? 0 ), (int) ( $row['listed'] ?? 0 ) );
		}
		return $total ? $total : max( (int) ( $g['count'] ?? 0 ), (int) ( $g['listed'] ?? 0 ) );
	}

	/** ¿Dice el informe que algo se convirtió, se creó o se dejó tal cual (en esta pasada o en una anterior)? */
	public static function has_converted( array $d ): bool {
		return $d['widgets'] || $d['globals']['docs'] || $d['recovered'] || $d['inert']['docs'] || $d['inert']['pages'] || $d['codes']['converted'] || $d['codes']['approx'] || $d['codes']['created'];
	}

	/**
	 * «Necesita tu atención» con sus entradas (HTML ya escapado de attention()), o una línea que dice que no hay nada.
	 */
	public static function attention_html( array $items, int $level = 2 ): string {
		$level = max( 2, min( 5, $level ) );
		if ( ! $items ) {
			return '<p class="digi-migrate__attention digi-migrate__attention--none"><strong>' . esc_html( __( 'Nada necesita tu atención.', 'digitalisimo-migration' ) ) . '</strong></p>';
		}
		return '<h' . $level . ' class="digi-migrate__attention-title">' . esc_html( __( 'Necesita tu atención', 'digitalisimo-migration' ) ) . '</h' . $level . '><ul class="digi-migrate__attention" style="list-style:disc;margin-left:20px"><li>' . implode( '</li><li>', $items ) . '</li></ul>';
	}

	/** Un desplegable (`<details>`), cerrado salvo con $open; nada si no hay contenido. */
	public static function folded( string $summary, string $html, string $class, bool $open = false ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}
		return '<details class="' . esc_attr( $class ) . '"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html( $summary ) . '</summary>' . $html . '</details>';
	}

	/** «1 widget de PRO», «12 widgets de PRO». Texto plano. */
	private static function plural( int $n, string $one, string $many ): string {
		return $n . ' ' . ( 1 === $n ? $one : $many );
	}

	/** Líneas de «Convertido» propias del resumen: tipos de widget de PRO, widget global, recuperado e inerte. */
	private static function converted_lines( array $d ): array {
		$lines = array();
		if ( $d['widgets'] ) {
			$rows = array_values( $d['widgets'] );
			usort( $rows, static function ( $a, $b ) {
				return array( $b['count'], $a['widget'] ) <=> array( $a['count'], $b['widget'] );
			} );
			$total = 0;
			$docs  = array();
			$sub   = array();
			foreach ( $rows as $row ) {
				$total += (int) $row['count'];
				$docs  += $row['docs'];
				$sub[]  = esc_html( $row['widget'] . ' → ' . $row['target'] . ': ' . self::in_docs( (int) $row['count'], $row['docs'] ) );
			}
			$lines[] = esc_html( sprintf( __( 'Widgets de PRO convertidos a su widget propio de DIGITALÍSIMO Elements, con el mismo contenido, enlaces e imágenes: %s.', 'digitalisimo-migration' ), self::in_docs( $total, $docs ) ) ) . '<ul><li>' . implode( '</li><li>', $sub ) . '</li></ul>';
		}
		$g = $d['globals'];
		if ( $g['docs'] ) {
			$substituted = 0;
			foreach ( (array) $g['per'] as $row ) {
				$substituted += (int) ( $row['substituted'] ?? 0 );
			}
			$total     = self::globals_total( $g );
			$linked    = max( 0, $total - $substituted );
			$templates = count( array_filter( (array) $g['templates'], static function ( $kind ) {
				return 'linked' === $kind;
			} ) );
			$parts     = array();
			if ( $linked ) {
				$parts[] = sprintf(
					1 === $linked ? __( '%1$s pasó al widget global propio y sigue vinculada a %2$s, que se conserva en la biblioteca (editar una plantilla cambia todas las páginas que la usan)', 'digitalisimo-migration' ) : __( '%1$s pasaron al widget global propio y siguen vinculadas a %2$s, que se conservan en la biblioteca (editar una plantilla cambia todas las páginas que la usan)', 'digitalisimo-migration' ),
					self::in_docs( $linked, $g['docs'], __( 'instancia', 'digitalisimo-migration' ), __( 'instancias', 'digitalisimo-migration' ) ),
					$templates ? sprintf( 1 === $templates ? __( '%d plantilla', 'digitalisimo-migration' ) : __( '%d plantillas', 'digitalisimo-migration' ), $templates ) : __( 'sus plantillas', 'digitalisimo-migration' )
				);
			}
			if ( $substituted ) {
				$parts[] = sprintf( 1 === $substituted ? __( '%d instancia se sustituyó por el elemento guardado en su plantilla: ya no sigue los cambios de la plantilla, que se conserva', 'digitalisimo-migration' ) : __( '%d instancias se sustituyeron por el elemento guardado en su plantilla: ya no siguen los cambios de la plantilla, que se conserva', 'digitalisimo-migration' ), $substituted );
			}
			$lines[] = esc_html( __( 'Widgets globales:', 'digitalisimo-migration' ) . ' ' . implode( '; ', $parts ) . '.' );
		}
		if ( $d['recovered'] ) {
			$rows = $d['recovered'];
			uksort( $rows, static function ( $a, $b ) use ( $rows ) {
				return array( $rows[ $b ]['count'], $a ) <=> array( $rows[ $a ]['count'], $b );
			} );
			$sub = array();
			foreach ( $rows as $key => $row ) {
				$widgets = array_values( array_filter( array_map( 'strval', array_keys( $row['widgets'] ) ), 'strlen' ) );
				sort( $widgets );
				$sub[] = esc_html( ( '' !== (string) $key ? (string) $key : __( '(sin nombre)', 'digitalisimo-migration' ) ) . ': ' . self::in_docs( (int) $row['count'], $row['docs'] ) . ( $widgets ? ' (' . implode( ', ', $widgets ) . ')' : '' ) );
			}
			$lines[] = esc_html( __( 'Ajustes que una versión anterior de la migración no pasó al widget propio, recuperados de la copia guardada antes de convertir: sólo donde faltaban (nada editado después cambió) y sin efecto en lo que se ve.', 'digitalisimo-migration' ) ) . '<ul><li>' . implode( '</li><li>', $sub ) . '</li></ul>';
		}
		$i     = $d['inert'];
		$parts = array();
		if ( $i['docs'] ) {
			$names = array_map( 'strval', array_keys( $i['names'] ) );
			sort( $names );
			$parts[] = sprintf( __( 'ajustes guardados que no son del widget de PRO en %1$s%2$s', 'digitalisimo-migration' ), self::docs_text( $i['docs'] ), $names ? ' (' . implode( ', ', $names ) . ')' : '' );
		}
		if ( $i['pages'] ) {
			$in      = array_filter( array_keys( $i['pages'] ) );
			$parts[] = __( 'ajustes de página de Element Pack que guardaban documentos migrados con una versión anterior', 'digitalisimo-migration' ) . ( $in ? ' (' . self::docs_text( array_fill_keys( $in, true ) ) . ')' : '' );
		}
		if ( $parts ) {
			$lines[] = esc_html( __( 'Se dejan tal cual, sin efecto en lo que se ve:', 'digitalisimo-migration' ) . ' ' . implode( '; ', $parts ) . '.' );
		}
		return $lines;
	}

	/** Una línea por código: su texto, su total, dónde, sus valores distintos (hasta seis) y sus notas (hasta tres). */
	private static function code_line( string $code, array $row ): string {
		$label  = rtrim( (string) ( self::catalog()[ $code ]['label'] ?? $code ), '. ' );
		$parts  = array( self::in_docs( (int) $row['count'], $row['docs'] ) );
		$values = array_keys( (array) $row['values'] );
		if ( $values ) {
			$parts[] = implode( '; ', array_slice( $values, 0, 6 ) ) . ( count( $values ) > 6 ? '; …' : '' );
		}
		$notes = array_keys( (array) $row['notes'] );
		$text  = $label . ' (' . implode( '; ', $parts ) . ')';
		if ( $notes ) {
			$text .= ' — ' . implode( ' ', array_slice( $notes, 0, 3 ) ) . ( count( $notes ) > 3 ? ' …' : '' );
		}
		return esc_html( $text . ( '.' === substr( $text, -1 ) ? '' : '.' ) );
	}

	/** «12 en 9 documentos», «2 en el sitio», «3 en 2 documentos y en el sitio». Texto plano. */
	private static function in_docs( int $count, array $docs, string $one = '', string $many = '' ): string {
		$site  = isset( $docs[0] );
		$n     = count( $docs ) - ( $site ? 1 : 0 );
		$noun  = 1 >= $count ? $one : ( '' !== $many ? $many : $one );
		$count = max( 1, $count ) . ( '' !== $noun ? ' ' . $noun : '' );
		if ( ! $n ) {
			return ( '1' === $count ? '' : $count . ' ' ) . __( 'en el sitio', 'digitalisimo-migration' );
		}
		return $count . ' ' . __( 'en', 'digitalisimo-migration' ) . ' ' . self::docs_text( $docs ) . ( $site ? ' ' . __( 'y en el sitio', 'digitalisimo-migration' ) : '' );
	}

	/** «1 documento», «9 documentos» (sin contar el sitio). Texto plano. */
	private static function docs_text( array $docs ): string {
		$n = count( $docs ) - ( isset( $docs[0] ) ? 1 : 0 );
		return sprintf( 1 === $n ? __( '%d documento', 'digitalisimo-migration' ) : __( '%d documentos', 'digitalisimo-migration' ), $n );
	}

	/**
	 * Título y enlace del documento (una revisión enlaza a sus revisiones, nunca al editor). Con $builder, el enlace abre
	 * el documento en el editor de DIGITALÍSIMO Elements (lo que pide «Necesita tu atención»).
	 */
	private static function document_html( int $post_id, bool $links, bool $builder = false ): string {
		$parent = function_exists( 'wp_is_post_revision' ) ? (int) wp_is_post_revision( $post_id ) : 0;
		$id     = $parent ? $parent : $post_id;
		$title  = function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '';
		$title  = '' !== trim( $title ) ? $title : '#' . $id;
		if ( $parent ) {
			$title = sprintf( __( 'Revisión de «%s»', 'digitalisimo-migration' ), $title );
			$link  = function_exists( 'admin_url' ) ? admin_url( 'revision.php?revision=' . $post_id ) : '';
		} else {
			$link = function_exists( 'get_edit_post_link' ) && ! $builder ? (string) get_edit_post_link( $id, 'raw' ) : '';
			$link = '' !== $link ? $link : ( function_exists( 'admin_url' ) ? admin_url( 'post.php?post=' . $id . '&action=elementor' ) : '' );
		}
		$text = esc_html( $title );
		return $links && '' !== $link ? '<a href="' . esc_url( $link ) . '">' . $text . '</a>' : $text;
	}

	/** Etiqueta del código y su detalle, todo escapado. */
	private static function item_html( array $item ): string {
		$code             = (string) ( $item['code'] ?? '' );
		$spec             = self::catalog()[ $code ] ?? array( 'label' => $code, 'private' => false );
		list( $parts, $note ) = self::detail_parts( $item );
		$html             = esc_html( (string) $spec['label'] );
		if ( $parts ) {
			$html .= ' <span class="digi-migrate__detail">(' . esc_html( implode( ' · ', $parts ) ) . ')</span>';
		}
		if ( '' !== $note ) {
			$html .= ' — ' . esc_html( $note );
		}
		return $html;
	}

	/**
	 * Detalle de un elemento en texto plano (sin escapar): `campo: valor` de cada clave con valor, salvo las de $skip, y la
	 * nota. El `name` de un código privado sólo lo ve quien puede (can_see_names()).
	 *
	 * @return array{0:string[], 1:string}
	 */
	private static function detail_parts( array $item, array $skip = array() ): array {
		$code   = (string) ( $item['code'] ?? '' );
		$spec   = self::catalog()[ $code ] ?? array( 'label' => $code, 'private' => false );
		$detail = (array) ( $item['detail'] ?? array() );
		if ( ! empty( $spec['private'] ) && isset( $detail['name'] ) && ! self::can_see_names() ) {
			unset( $detail['name'] );
		}
		$names = array(
			'widget'  => __( 'widget', 'digitalisimo-migration' ),
			'element' => __( 'elemento', 'digitalisimo-migration' ),
			'target'  => __( 'destino', 'digitalisimo-migration' ),
			'type'    => __( 'tipo', 'digitalisimo-migration' ),
			'name'    => __( 'nombre', 'digitalisimo-migration' ),
			'id'      => __( 'ID', 'digitalisimo-migration' ),
			'count'   => __( 'cantidad', 'digitalisimo-migration' ),
		);
		$parts = array();
		foreach ( $names as $key => $name ) {
			if ( ! in_array( $key, $skip, true ) && isset( $detail[ $key ] ) && '' !== (string) $detail[ $key ] ) {
				$parts[] = $name . ': ' . (string) $detail[ $key ];
			}
		}
		return array( $parts, in_array( 'note', $skip, true ) ? '' : (string) ( $detail['note'] ?? '' ) );
	}

	/** Nombres privados (contraseñas de aplicación): un superadministrador en una red; quien gestiona el sitio fuera. */
	private static function can_see_names(): bool {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return function_exists( 'is_super_admin' ) && is_super_admin();
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}
}

