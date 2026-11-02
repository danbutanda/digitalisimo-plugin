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
 * Cada frente añade códigos con un prefijo propio (`core.` A, `ext.` C, `ps.` E, `ps.dyn.` F, `tb.` G, `plat.` H y
 * `mk.` I) y los declara en su catálogo `report/codes-<frente>.php`:
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
		self::$memory['site'] = self::add_items( self::$memory['site'], array( self::item( $code, $detail, (int) ( $detail['id'] ?? 0 ) ) ), 0 );
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
			$a = self::add_doc_items( $a, (int) $post_id, (array) ( $doc['items'] ?? array() ), (int) ( $doc['more'] ?? 0 ) );
		}
		$a['omitted'] = max( (int) $a['omitted'], (int) $b['omitted'] );
		$a['site']    = self::add_items( (array) $a['site'] + array( 'items' => array(), 'more' => 0 ), (array) ( $b['site']['items'] ?? array() ), (int) ( $b['site']['more'] ?? 0 ) );
		return $a;
	}

	private static function add_doc_items( array $report, int $post_id, array $items, int $more ): array {
		if ( ! $items && ! $more ) {
			return $report;
		}
		if ( ! isset( $report['docs'][ $post_id ] ) ) {
			if ( count( $report['docs'] ) >= self::MAX_DOCS ) {
				++$report['omitted'];
				return $report;
			}
			$report['docs'][ $post_id ] = array( 'items' => array(), 'more' => 0 );
		}
		$report['docs'][ $post_id ] = self::add_items( $report['docs'][ $post_id ], $items, $more );
		ksort( $report['docs'] );
		return $report;
	}

	/** Añade elementos a una lista con tope, sin repetir; lo que no cabe se cuenta (lo mayor, nunca la suma). */
	private static function add_items( array $list, array $items, int $more ): array {
		$list += array( 'items' => array(), 'more' => 0 );
		$seen  = array();
		foreach ( $list['items'] as $item ) {
			$seen[ self::signature( (array) $item ) ] = true;
		}
		$overflow = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['code'] ) ) {
				continue;
			}
			$signature = self::signature( $item );
			if ( isset( $seen[ $signature ] ) ) {
				continue;
			}
			$seen[ $signature ] = true;
			if ( count( $list['items'] ) < self::MAX_ITEMS ) {
				$list['items'][] = $item;
			} else {
				++$overflow;
			}
		}
		$list['more'] = max( (int) $list['more'], $overflow, $more );
		return $list;
	}

	private static function signature( array $item ): string {
		$detail = (array) ( $item['detail'] ?? array() );
		ksort( $detail );
		return (string) ( $item['code'] ?? '' ) . '|' . md5( (string) json_encode( $detail ) );
	}

	/** Código con forma válida (`prefijo.nombre`, minúsculas, puntos y guiones bajos). */
	private static function valid_code( string $code ): bool {
		return 1 === preg_match( '/^(core|ext|ps|tb|plat|mk)\.[a-z0-9_.]{1,80}$/', $code );
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
			$codes = include $file;
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
	 * lo mismo sin enlaces (la terminal lo pasa a texto); `network`: sólo el recuento por sección.
	 */
	public static function render( array $report, string $where ): string {
		$report += self::none();
		if ( self::is_empty( $report ) ) {
			return '';
		}
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
			if ( ! $groups ) {
				continue;
			}
			$html .= '<h3 class="digi-migrate__section digi-migrate__section--' . esc_attr( $section ) . '">' . esc_html( $labels[ $section ] ) . '</h3><ul class="digi-migrate__report" style="list-style:disc;margin-left:20px">';
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
		if ( $more || ! empty( $report['omitted'] ) ) {
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

	/** Título y enlace del documento (una revisión enlaza a sus revisiones, nunca al editor). */
	private static function document_html( int $post_id, bool $links ): string {
		$parent = function_exists( 'wp_is_post_revision' ) ? (int) wp_is_post_revision( $post_id ) : 0;
		$id     = $parent ? $parent : $post_id;
		$title  = function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '';
		$title  = '' !== trim( $title ) ? $title : '#' . $id;
		if ( $parent ) {
			$title = sprintf( __( 'Revisión de «%s»', 'digitalisimo-migration' ), $title );
			$link  = function_exists( 'admin_url' ) ? admin_url( 'revision.php?revision=' . $post_id ) : '';
		} else {
			$link = function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $id, 'raw' ) : '';
			$link = '' !== $link ? $link : ( function_exists( 'admin_url' ) ? admin_url( 'post.php?post=' . $id . '&action=elementor' ) : '' );
		}
		$text = esc_html( wp_strip_all_tags_safe( $title ) );
		return $links && '' !== $link ? '<a href="' . esc_url( $link ) . '">' . $text . '</a>' : $text;
	}

	/** Etiqueta del código y su detalle, todo escapado. */
	private static function item_html( array $item ): string {
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
			if ( isset( $detail[ $key ] ) && '' !== (string) $detail[ $key ] ) {
				$parts[] = $name . ': ' . (string) $detail[ $key ];
			}
		}
		$html = esc_html( (string) $spec['label'] );
		if ( $parts ) {
			$html .= ' <span class="digi-migrate__detail">(' . esc_html( implode( ' · ', $parts ) ) . ')</span>';
		}
		if ( isset( $detail['note'] ) && '' !== (string) $detail['note'] ) {
			$html .= ' — ' . esc_html( (string) $detail['note'] );
		}
		return $html;
	}

	/** Nombres privados (contraseñas de aplicación): un superadministrador en una red; quien gestiona el sitio fuera. */
	private static function can_see_names(): bool {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return function_exists( 'is_super_admin' ) && is_super_admin();
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}
}

/** strip_tags tolerante a los entornos de prueba (wp_strip_all_tags si existe). */
function wp_strip_all_tags_safe( string $text ): string {
	return function_exists( 'wp_strip_all_tags' ) ? (string) wp_strip_all_tags( $text ) : strip_tags( $text );
}
