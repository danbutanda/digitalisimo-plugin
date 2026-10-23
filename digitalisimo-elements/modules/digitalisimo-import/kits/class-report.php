<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * El informe de una importación de kit: lo que leen la pantalla «Kits» y `wp digitalisimo-elements kit report`.
 *
 * - **Fuente única del estado** (§5.3): las sesiones nativas (`elementor_import_sessions`, `elementor_revert_sessions`)
 *   unidas a la opción de detalle `digitalisimo_elements_kit_session_<sesión>`. Una sesión revertida nunca ofrece
 *   acciones, y «Revertir» sólo se ofrece en la última sesión terminada.
 * - **Secciones** (§12.2): cada aviso del informe (`{ part, code, level, data }`) va a una sección según el prefijo de
 *   su código (`SECTION_PREFIXES`); lo que no casa con ninguno va a «Otros avisos», nunca se esconde.
 * - **Sin nombres del constructor original** (CR-18): los plugins de su familia se muestran como «Funciones incluidas
 *   en DIGITALÍSIMO Elements», el tema pedido nunca se nombra, y con el núcleo incluido los textos que vienen del kit
 *   pasan por la identidad propia. Vale para la pantalla, la salida JSON y la de WP-CLI.
 * - **D1**: `fresh_blockers()` dice por qué un sitio no está vacío. Lo usan la pantalla, la subida y WP-CLI.
 *
 * Sólo lee, salvo `save_detail()`, que guarda cambios de la pantalla en la opción de detalle si no hay
 * `Session::update()` (el importador, frente A, es quien escribe esa opción).
 */
final class Report {
	/** @var string[]|null Textos de la petición (`labels()`). */
	private static $labels = null;

	const NATIVE_IMPORT_SESSIONS = 'elementor_import_sessions';
	/** Nombre real en el núcleo (`IEC:module.php:55`), no el de §5.3. */
	const NATIVE_REVERT_SESSIONS = 'elementor_revert_sessions';
	const NATIVE_SESSION_META    = '_elementor_import_session_id';

	const STATUS_IMPORTED    = 'imported';
	const STATUS_IN_PROGRESS = 'in-progress';
	const STATUS_REVERTED    = 'reverted';

	const MAX_ROWS = 20;

	/**
	 * Filtro de textos (punto de extensión genérico de K, V.11.4 de F4-Woo): una etapa da texto a sus códigos nuevos.
	 * Sólo añade: un código que ya tiene texto en `CODE_LABELS` nunca cambia (ver `labels()`).
	 */
	const FILTER_LABELS = 'digitalisimo_elements_kit_report_labels';

	/** Prefijo con que una etapa da texto a un motivo de `not_exported` propio: `not-exported.<motivo>`. */
	const NOT_EXPORTED_CODE = 'not-exported.';

	/** Secciones del detalle, en el orden de §12.2, más «Otros avisos». */
	const SECTIONS = array(
		'summary'      => 'Resumen',
		'review'       => 'Revisar antes de publicar',
		'preserved'    => 'Conservado sin mostrar',
		'requested'    => 'Lo que el kit pedía y no se aplicó',
		'unresolved'   => 'Referencias sin destino',
		'media'        => 'Medios',
		'urls'         => 'URL del origen que quedan',
		'not-exported' => 'No exportado en el origen',
		'configure'    => 'Configurar después',
		'settings'     => 'Ajustes del kit no aplicados',
		'drafts'       => 'Borradores',
		'trash'        => 'Papelera tras revertir',
		'other'        => 'Otros avisos',
	);

	/**
	 * Prefijos de código de cada sección (convención para todos los frentes). Un código casa con un prefijo si es
	 * igual o si sigue con `.`, `:` o `-` (`files.rejected.path` → `files`; `note:form` → `note`). Se prueban en este
	 * orden (`theme-builder.closed` es una referencia sin destino antes que `theme`, lo que pedía el kit); la pantalla
	 * los muestra en el de `SECTIONS`.
	 */
	const SECTION_PREFIXES = array(
		'review'       => array( 'review', 'gate' ),
		'preserved'    => array( 'unconverted', 'preserve', 'preserved', 'original-type', 'unknown-type', 'unknown-defaults', 'component', 'kses', 'templates.unknown_type', 'content.missing_type', 'element-defaults.unconverted' ),
		'unresolved'   => array( 'unresolved', 'map-conflict', 'reference', 'element-missing', 'route-changed', 'theme-builder', 'map.miss', 'never-exists' ),
		'requested'    => array( 'requested', 'theme', 'experiment', 'experiments', 'plugin', 'plugins', 'v4', 'front', 'custom_code', 'taxonomies', 'woocommerce' ),
		'media'        => array( 'media', 'files', 'file', 'svg', 'download', 'attachment', 'blocked-type', 'fonts', 'icons', 'assets' ),
		'urls'         => array( 'origin-url', 'origin-urls', 'urls', 'url' ),
		'not-exported' => array( 'not-exported' ),
		'configure'    => array( 'note', 'configure' ),
		'settings'     => array( 'settings', 'kit-settings', 'globals', 'element-defaults', 'term-meta' ),
		'drafts'       => array( 'draft', 'drafts', 'status', 'publish', 'origin', 'floating', 'wxr.reused' ),
		'trash'        => array( 'trash', 'trashed', 'revert' ),
	);

	/** Sección de un código sin prefijo conocido, según la parte que lo emitió. */
	const PART_SECTIONS = array(
		'media'        => 'media',
		'files'        => 'media',
		'kit-settings' => 'settings',
	);

	/** Textos de los códigos conocidos. Se busca el código y después sus prefijos (`a.b.c` → `a.b` → `a`). */
	const CODE_LABELS = array(
		'review'             => 'Una regla de acceso quedó sin destino: el documento queda en borrador hasta que lo revises',
		'gate'               => 'Una regla de acceso quedó sin destino',
		'map-conflict'       => 'Dos destinos para el mismo valor: se conservó el primero',
		'invalid-code'       => 'Aviso con un código no válido',
		'unresolved'         => 'Referencia sin destino',
		'route-changed'      => 'La ruta cambió',
		'element-missing'    => 'El elemento de destino no existe en el documento',
		'files.rejected'     => 'Archivo no incluido',
		'files'              => 'Archivos propios',
		'media.embedded'     => 'Medios incluidos en el kit',
		'media.linked'       => 'Medios enlazados al origen',
		'media.downloaded'   => 'Medios descargados',
		'media.failed'       => 'Medios que no se pudieron descargar',
		'media.timeout'      => 'No descargado (tiempo)',
		'media'              => 'Medios',
		'svg.sanitized'      => 'SVG saneados',
		'svg.skipped'        => 'SVG omitidos: el sitio no permite subir SVG sin filtrar',
		'svg'                => 'SVG',
		'blocked-type'       => 'Tipo de archivo bloqueado por la red',
		'unconverted'        => 'Elemento conservado sin mostrar',
		'original-type'      => 'Documento de un tipo que este sitio no tiene, conservado como plantilla en borrador',
		'unknown-defaults'   => 'Valores por defecto de elementos que este sitio no tiene, conservados',
		'component'          => 'Componente v4: el núcleo aún no los admite en kits',
		'kses'               => 'Contenido saneado por los permisos del usuario que importó',
		'theme'              => 'El kit pedía un tema: no se instaló ni se activó',
		'experiments'        => 'El kit pedía experimentos: no se cambiaron',
		'experiment'         => 'El kit pedía un experimento: no se cambió',
		'plugins'            => 'El kit pedía plugins: no se instalaron ni se activaron',
		'plugin'             => 'El kit pedía un plugin: no se instaló ni se activó',
		'v4'                 => 'Ajustes v4 del kit no fusionados',
		'origin-url'         => 'URL del sitio de origen que sigue en el contenido',
		'urls'               => 'URL del sitio de origen',
		'not-exported'       => 'No se exportó en el sitio de origen',
		'note:form'          => 'Formulario: revisa destinatarios, redirección y webhooks',
		'note:google_places' => 'Reseñas: configura la clave de Google Places de este sitio',
		'note:acf'           => 'Campos personalizados: este sitio debe tener los campos que usa el kit',
		'note:sidebar'       => 'Barra lateral: elige una de este sitio',
		'note'               => 'Configurar después',
		'settings'           => 'Ajustes del kit',
		'globals'            => 'Colores y tipografías del kit',
		'draft'              => 'Quedó en borrador',
		'trash'              => 'Enviado a la papelera al revertir',
		'revert'             => 'Reversión',
		// Códigos que emiten el importador, las partes y los derivados de PRO (convención de §12.2).
		'templates.unknown_type'       => 'Plantilla de un tipo que este sitio no tiene, conservada como sección',
		'element-defaults.unconverted' => 'Valores por defecto de elementos que este sitio no tiene, conservados',
		'element-defaults.kept'        => 'Valores por defecto que el sitio ya tenía: se conservaron los suyos',
		'term-meta.kept'               => 'Metas de término que el sitio ya tenía: se conservaron las suyas',
		'front'                        => 'El kit traía una portada: la del sitio no cambió',
		'custom_code.skipped'          => 'Custom Code no se importó: hace falta el permiso unfiltered_html',
		'theme-builder.closed'         => 'Plantilla del Theme Builder sin condiciones: una quedó sin destino y queda para revisar',
		'map.miss'                     => 'Valor sin destino en un mapa de K: se dejó como estaba',
		'never-exists'                 => 'El valor de cierre coincide con un objeto real del sitio',
		'fonts'                        => 'Fuentes propias',
		'icons.skipped'                => 'Conjunto de iconos omitido: no se pudo reconstruir con sus archivos',
		'icons'                        => 'Iconos propios',
		'assets.attachment_failed'     => 'Archivo de una fuente o de un conjunto de iconos que no viajó en el kit',
		'assets'                       => 'Archivos de fuentes e iconos',
		'origin.reuse'                 => 'Reutilizado: el mismo kit ya lo había importado sin cambios',
		'origin.update'                => 'Borrador «actualización»: el kit trae otra versión de algo ya importado',
		'origin.conflict'              => 'Borrador «conflicto»: la copia importada antes se había editado',
		'floating'                     => 'Botón flotante en borrador',
		'wxr.object'                   => 'Meta con objetos serializados descartada',
		'wxr.blacklist'                => 'Meta interna descartada del contenido',
		'part.error'                   => 'Una parte del kit falló',
		'taxonomies.missing'           => 'El kit usa una taxonomía que este sitio no tiene',
		'taxonomies'                   => 'Términos del kit que no se pudieron crear',
		'woocommerce'                  => 'Páginas de WooCommerce del kit: las del sitio no cambiaron',
		'wxr.reused'                   => 'Contenido reutilizado: el mismo kit ya lo había importado sin cambios',
		'content.missing_type'         => 'Contenido de un tipo que este sitio no tiene, conservado como plantilla en borrador',
	);

	/** Motivos de `not_exported` (§8.3) con texto propio. */
	const NOT_EXPORTED_LABELS = array(
		'drafts'     => 'Borradores y entradas privadas',
		'private'    => 'Entradas privadas',
		'variations' => 'Variaciones de producto',
		'components' => 'Documentos componente v4',
		'webhooks'   => 'URL de webhooks de formularios',
		'comments'   => 'Comentarios',
		'users'      => 'Usuarios y sus datos',
	);

	const LEVEL_LABELS = array(
		'info'    => 'Información',
		'warning' => 'Aviso',
		'error'   => 'Error',
	);

	/** Plugins y temas de la familia del constructor, por carpeta (CR-18). */
	const FAMILY_SLUGS = array( 'elementor', 'elementor-pro', 'pro-elements', 'digitalisimo-elements' );
	const FAMILY_LABEL = 'Funciones incluidas en DIGITALÍSIMO Elements';

	/** Plantillas del Theme Builder y popups: se publican desde el Theme Builder, nunca desde «Kits» (D8). */
	const THEME_BUILDER_TYPES = array( 'header', 'footer', 'single', 'single-post', 'single-page', 'archive', 'search-results', 'error-404', 'product', 'product-archive', 'loop-item', 'popup' );

	/** Tipos que nunca publica «Publicar páginas y plantillas» (D8): botones flotantes y fragmentos de código. */
	const NEVER_PUBLISH_TYPES = array( 'e-floating-buttons', 'elementor_snippet', 'elementor_font', 'elementor_icons' );

	// ---------------------------------------------------------------------------------------------------------------
	// Sesiones.

	/** ¿Es un ID de sesión válido (`uniqid()` del núcleo)? */
	public static function is_session_id( $session ): bool {
		return is_string( $session ) && 1 === preg_match( Import_Context::SESSION_PATTERN, $session );
	}

	/** Nombre de la opción de detalle de una sesión, o '' si el ID no es válido. */
	public static function option_name( string $session ): string {
		return self::is_session_id( $session ) ? Module::OPTION_SESSION_PREFIX . $session : '';
	}

	/**
	 * Detalle de K de una sesión de este sitio, con todas las claves de §5.3 y los tipos correctos; [] si no existe.
	 *
	 * @param string $session ID de la sesión.
	 */
	public static function detail( string $session ): array {
		$name = self::option_name( $session );
		if ( '' === $name ) {
			return array();
		}
		$raw = get_option( $name, null );
		return is_array( $raw ) ? self::normalize_detail( $raw ) : array();
	}

	/**
	 * Guarda el detalle de una sesión (sin autoload). Sólo para las acciones de la pantalla «Kits».
	 *
	 * @param string $session ID de la sesión; la opción debe existir ya (la crea el importador).
	 * @param array  $detail  Detalle completo.
	 */
	public static function save_detail( string $session, array $detail ): bool {
		$name = self::option_name( $session );
		if ( '' === $name || ! is_array( get_option( $name, null ) ) ) {
			return false;
		}
		return (bool) update_option( $name, $detail, false );
	}

	/** El detalle con valores por defecto, para que la pantalla nunca lea una clave inexistente. */
	public static function normalize_detail( array $raw ): array {
		$detail = array_replace(
			array(
				'v'            => 0,
				'status'       => '',
				'mode'         => '',
				'requested'    => '',
				'format'       => '',
				'user'         => 0,
				'time'         => 0,
				'origin'       => array(),
				'max_post_id'  => 0,
				'kit_id'       => 0,
				'staged_rel'   => '',
				'settings_map' => array(),
				'analysis'     => array(),
				'backup'       => array(),
				'report'       => array(),
				'review'       => array(),
				'published'    => array(),
				'reverted'     => 0,
			),
			$raw
		);
		foreach ( array( 'origin', 'settings_map', 'analysis', 'backup', 'review', 'published' ) as $key ) {
			if ( ! is_array( $detail[ $key ] ) ) {
				$detail[ $key ] = array();
			}
		}
		foreach ( array( 'v', 'user', 'time', 'max_post_id', 'kit_id', 'reverted' ) as $key ) {
			$detail[ $key ] = is_numeric( $detail[ $key ] ) ? (int) $detail[ $key ] : 0;
		}
		$detail['mode']   = in_array( $detail['mode'], array( Module::MODE_ADD, Module::MODE_FRESH ), true ) ? $detail['mode'] : '';
		$detail['report'] = self::normalize( $detail['report'] );
		foreach ( array( 'status', 'requested', 'format' ) as $key ) {
			$detail[ $key ] = is_string( $detail[ $key ] ) ? $detail[ $key ] : '';
		}
		if ( '' === $detail['format'] && isset( $detail['origin']['format'] ) && is_string( $detail['origin']['format'] ) ) {
			$detail['format'] = $detail['origin']['format'];
		}
		foreach ( array( 'format', 'kit', 'home', 'site', 'uploads' ) as $key ) {
			$detail['origin'][ $key ] = isset( $detail['origin'][ $key ] ) && is_string( $detail['origin'][ $key ] ) ? $detail['origin'][ $key ] : '';
		}
		return $detail;
	}

	/** Sesiones nativas de importación de este sitio, por ID. */
	public static function native_sessions(): array {
		$sessions = get_option( self::NATIVE_IMPORT_SESSIONS, array() );
		$out      = array();
		foreach ( is_array( $sessions ) ? $sessions : array() as $id => $session ) {
			$id = is_array( $session ) && isset( $session['session_id'] ) ? (string) $session['session_id'] : (string) $id;
			if ( is_array( $session ) && self::is_session_id( $id ) ) {
				$out[ $id ] = $session;
			}
		}
		return $out;
	}

	/** Sesiones revertidas de este sitio, por ID. */
	public static function reverted_sessions(): array {
		$sessions = get_option( self::NATIVE_REVERT_SESSIONS, array() );
		$out      = array();
		foreach ( is_array( $sessions ) ? $sessions : array() as $session ) {
			if ( is_array( $session ) && isset( $session['session_id'] ) && self::is_session_id( (string) $session['session_id'] ) ) {
				$out[ (string) $session['session_id'] ] = $session;
			}
		}
		return $out;
	}

	/**
	 * ID de la sesión que revertiría el núcleo: la última importación por `start_timestamp`, si terminó.
	 *
	 * La reversión nativa sólo deshace la última sesión (`IEC:processes/revert.php:53-78`). Si la última está a medias
	 * (se subió el kit y no se importó), no se ofrece revertir nada: el núcleo revertiría esa, no la anterior.
	 */
	public static function last_session(): string {
		$sessions = self::native_sessions();
		if ( ! $sessions ) {
			return '';
		}
		uasort(
			$sessions,
			function ( $a, $b ) {
				return (int) ( isset( $a['start_timestamp'] ) ? $a['start_timestamp'] : 0 ) <=> (int) ( isset( $b['start_timestamp'] ) ? $b['start_timestamp'] : 0 );
			}
		);
		$last = end( $sessions );
		$id   = (string) key( $sessions );
		return self::STATUS_IMPORTED === self::native_status( $last ) ? $id : '';
	}

	/** Estado de una sesión nativa de importación: terminada o a medias. */
	public static function native_status( array $session ): string {
		return ! empty( $session['end_timestamp'] ) || isset( $session['runners'] ) ? self::STATUS_IMPORTED : self::STATUS_IN_PROGRESS;
	}

	/**
	 * Filas de la lista de la pantalla: sesiones nativas y revertidas unidas a su detalle, de la más nueva a la más
	 * vieja. `actions` es verdadero sólo en sesiones terminadas y sin revertir; `revert`, sólo en la última.
	 *
	 * @return array[] `{ id, title, time, status, mode, format, created, trashed, detail, actions, revert }`.
	 */
	public static function sessions(): array {
		$rows = array();
		$last = self::last_session();
		foreach ( self::native_sessions() as $id => $session ) {
			$rows[ $id ] = self::row( $id, $session, self::native_status( $session ), $last );
		}
		foreach ( self::reverted_sessions() as $id => $session ) {
			$session['start_timestamp'] = isset( $session['import_timestamp'] ) ? $session['import_timestamp'] : 0;
			$rows[ $id ]                = self::row( $id, $session, self::STATUS_REVERTED, $last );
		}
		uasort(
			$rows,
			function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);
		$rows   = array_slice( $rows, 0, self::MAX_ROWS, true );
		$counts = self::created_counts( array_keys( $rows ) );
		foreach ( $rows as $id => $row ) {
			$rows[ $id ]['created'] = isset( $counts[ $id ]['live'] ) ? $counts[ $id ]['live'] : 0;
			$rows[ $id ]['trashed'] = isset( $counts[ $id ]['trash'] ) ? $counts[ $id ]['trash'] : 0;
		}
		return array_values( $rows );
	}

	/**
	 * Una fila de `sessions()`, o null si la sesión no es de este sitio.
	 *
	 * @param string $session ID de la sesión.
	 */
	public static function session( string $session ): ?array {
		if ( ! self::is_session_id( $session ) ) {
			return null;
		}
		foreach ( self::sessions() as $row ) {
			if ( $row['id'] === $session ) {
				return $row;
			}
		}
		$native = self::native_sessions();
		if ( isset( $native[ $session ] ) ) {
			return self::row( $session, $native[ $session ], self::native_status( $native[ $session ] ), self::last_session() );
		}
		return null;
	}

	private static function row( string $id, array $session, string $status, string $last ): array {
		$detail = self::detail( $id );
		if ( self::STATUS_REVERTED !== $status && $detail && ( ! empty( $detail['reverted'] ) || self::STATUS_REVERTED === $detail['status'] ) ) {
			$status = self::STATUS_REVERTED;
		}
		return array(
			'id'      => $id,
			'title'   => self::brand( isset( $session['kit_title'] ) && is_string( $session['kit_title'] ) && '' !== $session['kit_title'] ? $session['kit_title'] : 'Kit sin título' ),
			'time'    => isset( $session['start_timestamp'] ) ? (int) $session['start_timestamp'] : 0,
			'status'  => $status,
			'mode'    => $detail ? $detail['mode'] : '',
			'format'  => $detail ? $detail['format'] : '',
			'created' => 0,
			'trashed' => 0,
			'detail'  => (bool) $detail,
			'actions' => self::STATUS_IMPORTED === $status && (bool) $detail,
			'revert'  => self::STATUS_IMPORTED === $status && $id === $last,
		);
	}

	/**
	 * Entradas creadas por cada sesión (meta nativa de la sesión), vivas y en la papelera, en una sola consulta.
	 *
	 * @param string[] $sessions IDs.
	 *
	 * @return array[] ID => `{ live, trash }`.
	 */
	public static function created_counts( array $sessions ): array {
		global $wpdb;
		$sessions = array_values( array_filter( $sessions, array( __CLASS__, 'is_session_id' ) ) );
		if ( ! $sessions || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$in   = implode( ',', array_fill( 0, count( $sessions ), '%s' ) );
		$sql  = "SELECT pm.meta_value AS session, SUM( p.post_status <> 'trash' ) AS live, SUM( p.post_status = 'trash' ) AS trash FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type <> 'revision' AND pm.meta_value IN ( {$in} ) GROUP BY pm.meta_value";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( self::NATIVE_SESSION_META ), $sessions ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- marcadores generados arriba.
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[ (string) $row['session'] ] = array(
				'live'  => (int) $row['live'],
				'trash' => (int) $row['trash'],
			);
		}
		return $out;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Informe.

	/**
	 * El informe con la forma `{ entries:[{part,code,level,data}], counts, dropped }`, descartando lo que no la cumple.
	 *
	 * @param mixed $report Informe guardado.
	 */
	public static function normalize( $report ): array {
		$out = array(
			'entries' => array(),
			'counts'  => array(),
			'dropped' => 0,
		);
		if ( ! is_array( $report ) ) {
			return $out;
		}
		foreach ( isset( $report['entries'] ) && is_array( $report['entries'] ) ? $report['entries'] : array() as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['code'] ) || ! is_string( $entry['code'] ) || 1 !== preg_match( Part_Context::CODE_PATTERN, $entry['code'] ) ) {
				continue;
			}
			$out['entries'][] = array(
				'part'  => isset( $entry['part'] ) && is_string( $entry['part'] ) ? $entry['part'] : '',
				'code'  => $entry['code'],
				'level' => isset( $entry['level'] ) && in_array( $entry['level'], Part_Context::LEVELS, true ) ? $entry['level'] : Part_Context::WARNING,
				'data'  => isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : array(),
			);
		}
		foreach ( isset( $report['counts'] ) && is_array( $report['counts'] ) ? $report['counts'] : array() as $code => $count ) {
			if ( is_string( $code ) && 1 === preg_match( Part_Context::CODE_PATTERN, $code ) && is_numeric( $count ) ) {
				$out['counts'][ $code ] = (int) $count;
			}
		}
		$out['dropped'] = isset( $report['dropped'] ) && is_numeric( $report['dropped'] ) ? max( 0, (int) $report['dropped'] ) : 0;
		return $out;
	}

	/**
	 * Sección de un código (`SECTION_PREFIXES`); si ninguno casa, la de la parte, y si no, `other`.
	 *
	 * @param string $code Código del aviso.
	 * @param string $part Parte que lo emitió.
	 */
	public static function section_for( string $code, string $part = '' ): string {
		foreach ( self::SECTION_PREFIXES as $section => $prefixes ) {
			foreach ( $prefixes as $prefix ) {
				if ( self::has_prefix( $code, $prefix ) ) {
					return $section;
				}
			}
		}
		return isset( self::PART_SECTIONS[ $part ] ) ? self::PART_SECTIONS[ $part ] : 'other';
	}

	/** ¿Es `$code` igual a `$prefix` o empieza por él seguido de `.`, `:` o `-`? */
	public static function has_prefix( string $code, string $prefix ): bool {
		if ( $code === $prefix ) {
			return true;
		}
		$next = substr( $code, strlen( $prefix ), 1 );
		return 0 === strpos( $code, $prefix ) && in_array( $next, array( '.', ':', '-' ), true );
	}

	/**
	 * Avisos y contadores agrupados por sección, en el orden de `SECTIONS` (sin `summary`).
	 *
	 * @param array $report Informe (se normaliza).
	 *
	 * @return array[] Sección => `{ entries: array[], counts: int[] }`; sólo las secciones con algo.
	 */
	public static function group( $report ): array {
		$report  = self::normalize( $report );
		$grouped = array();
		foreach ( $report['entries'] as $entry ) {
			$section                          = self::section_for( $entry['code'], $entry['part'] );
			$grouped[ $section ]['entries'][] = $entry;
		}
		foreach ( $report['counts'] as $code => $count ) {
			$section                              = self::section_for( $code );
			$grouped[ $section ]['counts'][ $code ] = $count;
		}
		$out = array();
		foreach ( array_keys( self::SECTIONS ) as $section ) {
			if ( isset( $grouped[ $section ] ) ) {
				$out[ $section ] = array_replace(
					array(
						'entries' => array(),
						'counts'  => array(),
					),
					$grouped[ $section ]
				);
			}
		}
		return $out;
	}

	/** Avisos por nivel: `{ info, warning, error }`. */
	public static function levels( $report ): array {
		$out = array_fill_keys( Part_Context::LEVELS, 0 );
		foreach ( self::normalize( $report )['entries'] as $entry ) {
			++$out[ $entry['level'] ];
		}
		return $out;
	}

	/**
	 * Texto de un código: el suyo o el del prefijo más largo que tenga; '' si no hay ninguno.
	 *
	 * @param string $code Código del aviso.
	 */
	public static function code_label( string $code ): string {
		$labels    = self::labels();
		$candidate = $code;
		while ( '' !== $candidate ) {
			if ( isset( $labels[ $candidate ] ) ) {
				return $labels[ $candidate ];
			}
			$cut = max( (int) strrpos( $candidate, '.' ), (int) strrpos( $candidate, ':' ) );
			if ( $cut <= 0 ) {
				break;
			}
			$candidate = substr( $candidate, 0, $cut );
		}
		return '';
	}

	/**
	 * Textos de los códigos: `CODE_LABELS` más los que añade `digitalisimo_elements_kit_report_labels` (una vez por
	 * petición). Sin funciones enganchadas, exactamente `CODE_LABELS`.
	 *
	 * @return string[] Código => texto.
	 */
	public static function labels(): array {
		if ( null === self::$labels ) {
			self::$labels = function_exists( 'apply_filters' ) ? self::add_only( self::CODE_LABELS, apply_filters( self::FILTER_LABELS, self::CODE_LABELS ) ) : self::CODE_LABELS;
		}
		return self::$labels;
	}

	/**
	 * Acota lo que devuelve el filtro de textos: sólo códigos nuevos (`[a-z0-9_:.-]`, ≤ 100) con un texto plano no
	 * vacío (≤ 200 caracteres). Un código que ya tiene texto, uno quitado o un filtro que no devuelve un array no
	 * cambian nada; el orden de `$base` se conserva y lo nuevo va detrás.
	 *
	 * @param string[] $base     Textos de K.
	 * @param mixed    $filtered Lo que devolvió el filtro.
	 *
	 * @return string[]
	 */
	public static function add_only( array $base, $filtered ): array {
		if ( ! is_array( $filtered ) ) {
			return $base;
		}
		foreach ( $filtered as $code => $text ) {
			if ( ! is_string( $code ) || isset( $base[ $code ] ) || 1 !== preg_match( '/^[a-z0-9_:.-]{1,100}$/', $code ) || ! is_string( $text ) ) {
				continue;
			}
			$text = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( $text ) ) );
			if ( '' !== $text ) {
				$base[ $code ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 200 ) : substr( $text, 0, 200 );
			}
		}
		return $base;
	}

	/**
	 * Olvida los textos de la petición.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset_labels(): void {
		self::$labels = null;
	}

	/** Etiqueta de un nivel. */
	public static function level_label( string $level ): string {
		return isset( self::LEVEL_LABELS[ $level ] ) ? self::LEVEL_LABELS[ $level ] : self::LEVEL_LABELS['warning'];
	}

	/**
	 * Una línea de texto plano por aviso: «texto (código) · clave: valor…». Para WP-CLI y para la pantalla.
	 *
	 * @param array $entry Aviso normalizado.
	 */
	public static function describe_entry( array $entry ): string {
		$code  = isset( $entry['code'] ) ? (string) $entry['code'] : '';
		$label = self::code_label( $code );
		$text  = '' === $label ? $code : $label . ' (' . $code . ')';
		$data  = self::flatten( isset( $entry['data'] ) && is_array( $entry['data'] ) ? self::public_value( $entry['data'] ) : array() );
		if ( $data ) {
			$pairs = array();
			foreach ( $data as $key => $value ) {
				$pairs[] = $key . ': ' . $value;
			}
			$text .= ' · ' . implode( ' · ', $pairs );
		}
		return $text;
	}

	/**
	 * Datos de un aviso como pares `clave` => texto, con las claves anidadas unidas por punto. Recorta a 12 pares.
	 *
	 * @param array  $data   Datos del aviso.
	 * @param string $prefix Prefijo de las claves.
	 */
	public static function flatten( array $data, string $prefix = '' ): array {
		$out = array();
		foreach ( $data as $key => $value ) {
			$name = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_array( $value ) ) {
				$out += Part_Context::is_list( $value ) && ! array_filter( $value, 'is_array' )
					? array( $name => implode( ', ', array_map( array( __CLASS__, 'scalar_text' ), $value ) ) )
					: self::flatten( $value, $name );
			} else {
				$out[ $name ] = self::scalar_text( $value );
			}
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return array_slice( $out, 0, 12, true );
	}

	/** Texto de un escalar del informe. */
	public static function scalar_text( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'sí' : 'no';
		}
		if ( null === $value ) {
			return '—';
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Resumen y pedidos del kit.

	/** Texto de un modo. */
	public static function mode_label( string $mode ): string {
		if ( Module::MODE_FRESH === $mode ) {
			return 'Sitio nuevo';
		}
		return Module::MODE_ADD === $mode ? 'Sólo agregar' : 'Sin informe';
	}

	/** ¿Es un Template Kit convertido (que siempre entra en «agregar», D1)? */
	public static function is_template_kit( array $detail ): bool {
		$format = strtolower( ! empty( $detail['format'] ) && is_string( $detail['format'] ) ? $detail['format'] : ( isset( $detail['origin']['format'] ) ? (string) $detail['origin']['format'] : '' ) );
		return false !== strpos( $format, 'envato' ) || false !== strpos( $format, 'template-kit' );
	}

	/** Texto del formato del kit de origen. */
	public static function format_label( string $format ): string {
		$format = strtolower( $format );
		if ( '' === $format ) {
			return 'Kit';
		}
		if ( false !== strpos( $format, 'envato' ) || false !== strpos( $format, 'template-kit' ) ) {
			return 'Template Kit';
		}
		if ( false !== strpos( $format, 'digitalisimo' ) || 'own' === $format ) {
			return 'Kit de DIGITALÍSIMO Elements';
		}
		if ( 1 === preg_match( '/^(?:v)?([12])(?:[.x]|$)/', $format ) ) {
			return 'Kit en formato 1.x o 2.x';
		}
		return 1 === preg_match( '/^(?:v)?3(?:[.x]|$)/', $format ) ? 'Kit 3.0' : 'Kit';
	}

	/** El porqué del modo (§12.2, «Resumen»). */
	public static function mode_reason( array $detail ): string {
		$mode = isset( $detail['mode'] ) ? (string) $detail['mode'] : '';
		if ( Module::MODE_FRESH === $mode ) {
			return 'El administrador lo pidió y el sitio estaba vacío: se aplicaron también los ajustes del kit y su portada.';
		}
		if ( Module::MODE_ADD !== $mode ) {
			return 'Esta importación no tiene informe de DIGITALÍSIMO Elements.';
		}
		if ( self::is_template_kit( $detail ) ) {
			return 'Un Template Kit convertido siempre entra en «sólo agregar»: no cambió nada de lo que ya tenía el sitio y lo que se ve quedó en borrador.';
		}
		return '«Sólo agregar» es el modo por defecto: no cambió nada de lo que ya tenía el sitio y lo que se ve quedó en borrador.';
	}

	/**
	 * ¿Es un plugin de la familia del constructor? (CR-18) La misma regla que el normalizador
	 * (`Normalizer::is_builder_plugin()`): el nombre o la carpeta nombran el constructor; también, si su página es de
	 * un dominio del proveedor.
	 */
	public static function is_builder_plugin( $plugin ): bool {
		$file = '';
		$name = '';
		$uri  = '';
		if ( is_array( $plugin ) ) {
			$file = isset( $plugin['plugin'] ) && is_string( $plugin['plugin'] ) ? $plugin['plugin'] : '';
			$name = isset( $plugin['name'] ) && is_string( $plugin['name'] ) ? $plugin['name'] : '';
			$uri  = isset( $plugin['pluginUri'] ) && is_string( $plugin['pluginUri'] ) ? $plugin['pluginUri'] : '';
		} elseif ( is_string( $plugin ) ) {
			$name = $plugin;
		}
		$slug = strtolower( trim( (string) strtok( str_replace( '\\', '/', $file ), '/' ) ) );
		if ( in_array( (string) preg_replace( '/\.php$/', '', $slug ), self::FAMILY_SLUGS, true ) ) {
			return true;
		}
		if ( class_exists( __NAMESPACE__ . '\\Normalizer' ) && method_exists( __NAMESPACE__ . '\\Normalizer', 'is_builder_plugin' ) ) {
			if ( Normalizer::is_builder_plugin( $name, $file ) ) {
				return true;
			}
		} elseif ( 1 === preg_match( '/elementor|pro[ -]?elements|digital[ií]simo/iu', $name . ' ' . $file ) ) {
			return true;
		}
		return '' !== $uri && class_exists( '\Digitalisimo_Vendor_Free' ) && method_exists( '\Digitalisimo_Vendor_Free', 'vendor_url' ) && \Digitalisimo_Vendor_Free::vendor_url( $uri );
	}

	/** Nombre visible de un plugin pedido por el kit: sin enlace y sin nombrar la familia del constructor (CR-18). */
	public static function plugin_label( $plugin ): string {
		if ( self::is_builder_plugin( $plugin ) ) {
			return self::FAMILY_LABEL;
		}
		$name = '';
		if ( is_array( $plugin ) ) {
			$name = isset( $plugin['name'] ) && is_string( $plugin['name'] ) && '' !== trim( $plugin['name'] ) ? $plugin['name'] : ( isset( $plugin['plugin'] ) && is_string( $plugin['plugin'] ) ? $plugin['plugin'] : '' );
		} elseif ( is_scalar( $plugin ) ) {
			$name = (string) $plugin;
		}
		$name = trim( wp_strip_all_tags( $name ) );
		return '' === $name ? 'Plugin sin nombre' : self::brand( $name );
	}

	/**
	 * Lo que el kit pedía y no se aplicó (§12.2 punto 4), sin nombres del tema ni de la familia del constructor.
	 *
	 * Lee `requests` del normalizador (`{ theme, experiments: { nombre: estado }, plugins: string[],
	 * builder_plugins: n }`), guardado en `detail.requests` o en `detail.analysis.requests`.
	 *
	 * @param array $detail Detalle de la sesión.
	 *
	 * @return array `{ theme: bool, experiments: string[], plugins: string[] }`. Los plugins de la familia del
	 *               constructor son una sola entrada: «Funciones incluidas en DIGITALÍSIMO Elements».
	 */
	public static function requested( array $detail ): array {
		$analysis = isset( $detail['analysis'] ) && is_array( $detail['analysis'] ) ? $detail['analysis'] : array();
		$asked    = array();
		if ( isset( $detail['requests'] ) && is_array( $detail['requests'] ) ) {
			$asked = $detail['requests'];
		} elseif ( isset( $analysis['requests'] ) && is_array( $analysis['requests'] ) ) {
			$asked = $analysis['requests'];
		}
		$plugins = array();
		foreach ( isset( $asked['plugins'] ) && is_array( $asked['plugins'] ) ? $asked['plugins'] : array() as $key => $plugin ) {
			if ( is_string( $key ) && is_bool( $plugin ) ) {
				$plugin = $key;
			}
			$plugins[] = self::plugin_label( $plugin );
		}
		if ( ! empty( $asked['builder_plugins'] ) ) {
			$plugins[] = self::FAMILY_LABEL;
		}
		$experiments = array();
		foreach ( isset( $asked['experiments'] ) && is_array( $asked['experiments'] ) ? $asked['experiments'] : array() as $key => $value ) {
			$name = is_string( $key ) ? $key : ( is_array( $value ) && isset( $value['name'] ) ? $value['name'] : $value );
			if ( is_scalar( $name ) && '' !== (string) $name ) {
				$experiments[] = sanitize_key( (string) $name );
			}
		}
		return array(
			'theme'       => ! empty( $asked['theme'] ),
			'experiments' => array_values( array_unique( array_filter( $experiments ) ) ),
			'plugins'     => array_values( array_unique( $plugins ) ),
		);
	}

	/** Líneas de texto de `requested()`. */
	public static function requested_lines( array $requested ): array {
		$lines = array();
		if ( ! empty( $requested['theme'] ) ) {
			$lines[] = self::CODE_LABELS['theme'] . '.';
		}
		if ( ! empty( $requested['experiments'] ) ) {
			$lines[] = self::CODE_LABELS['experiments'] . ': ' . implode( ', ', $requested['experiments'] ) . '.';
		}
		if ( ! empty( $requested['plugins'] ) ) {
			$lines[] = self::CODE_LABELS['plugins'] . ': ' . implode( ', ', $requested['plugins'] ) . '.';
		}
		return $lines;
	}

	/**
	 * Lo que el análisis del normalizador dice de la importación, como líneas de texto por sección (§7.2 paso 8).
	 *
	 * @param array $detail Detalle de la sesión.
	 *
	 * @return array[] Sección => líneas.
	 */
	public static function analysis_lines( array $detail ): array {
		$a   = isset( $detail['analysis'] ) && is_array( $detail['analysis'] ) ? $detail['analysis'] : array();
		$out = array();
		$map = function ( $key ) use ( $a ) {
			$out = array();
			foreach ( isset( $a[ $key ] ) && is_array( $a[ $key ] ) ? $a[ $key ] : array() as $name => $count ) {
				if ( is_scalar( $name ) && is_numeric( $count ) && (int) $count > 0 ) {
					$out[] = self::brand( (string) $name ) . ' (' . (int) $count . ')';
				}
			}
			return $out;
		};
		$list = function ( $key ) use ( $a ) {
			$out = array();
			foreach ( isset( $a[ $key ] ) && is_array( $a[ $key ] ) ? $a[ $key ] : array() as $item ) {
				if ( is_scalar( $item ) && '' !== (string) $item ) {
					$out[] = self::brand( (string) $item );
				}
			}
			return $out;
		};
		$add = function ( $section, $label, array $items ) use ( &$out ) {
			if ( $items ) {
				$out[ $section ][] = $label . ': ' . implode( ', ', array_slice( $items, 0, 50 ) ) . ( count( $items ) > 50 ? '…' : '' ) . '.';
			}
		};
		$add( 'preserved', 'Elementos que este sitio no tiene, conservados sin mostrar', $map( 'unknown_widgets' ) );
		$add( 'preserved', 'Tipos de documento que este sitio no tiene, conservados como plantillas en borrador', $map( 'unknown_documents' ) );
		$add( 'preserved', 'Contenido de tipos que este sitio no tiene, conservado como plantillas en borrador', $map( 'missing_post_types' ) );
		$add( 'preserved', 'Taxonomías que este sitio no tiene: sus términos no se importaron', $map( 'missing_taxonomies' ) );
		if ( ! empty( $a['components'] ) && is_numeric( $a['components'] ) ) {
			$out['preserved'][] = sprintf( 'Componentes v4: %d. El núcleo aún no los admite en kits: se conservan sin mostrar.', (int) $a['components'] );
		}
		$add( 'requested', 'El contenido usa funciones apagadas en este sitio, que no se activaron (lo que las usa se conserva sin mostrar)', $map( 'experiments' ) );
		$add( 'media', 'Tipos de archivo que este sitio no admite', $map( 'blocked_types' ) );
		$add( 'media', 'Fuentes de Google que usa el kit (no se descargan)', $list( 'google_fonts' ) );
		$size  = function ( $bytes ) {
			return function_exists( 'size_format' ) ? (string) size_format( (int) $bytes ) : (int) $bytes . ' B';
		};
		$stage = isset( $detail['stage'] ) && is_array( $detail['stage'] ) ? $detail['stage'] : array();
		if ( isset( $stage['embedded'] ) && is_numeric( $stage['embedded'] ) ) {
			// Lo que se preparó de verdad (sha256 y tipo comprobados), no sólo lo que declaraba el kit.
			$out['media'][] = sprintf( 'Medios incluidos en el kit y comprobados: %d (%s).', (int) $stage['embedded'], $size( isset( $stage['bytes'] ) ? $stage['bytes'] : 0 ) );
			if ( ! empty( $stage['svg_sanitized'] ) && is_numeric( $stage['svg_sanitized'] ) ) {
				$out['media'][] = sprintf( 'SVG saneados: %d.', (int) $stage['svg_sanitized'] );
			}
			$reasons  = array(
				'sha256' => 'contenido distinto del declarado',
				'mime'   => 'tipo real distinto de su extensión',
				'svg'    => 'SVG no utilizable',
				'size'   => 'tamaño distinto del declarado',
				'write'  => 'no se pudo escribir',
				'path'   => 'ruta no admitida',
			);
			$rejected = array();
			foreach ( isset( $stage['rejected'] ) && is_array( $stage['rejected'] ) ? $stage['rejected'] : array() as $reason => $count ) {
				if ( is_numeric( $count ) && (int) $count > 0 ) {
					$rejected[] = ( isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : (string) $reason ) . ' (' . (int) $count . ')';
				}
			}
			$add( 'media', 'Archivos del kit descartados', $rejected );
		} elseif ( isset( $a['media']['embedded'] ) && is_numeric( $a['media']['embedded'] ) && (int) $a['media']['embedded'] > 0 ) {
			$out['media'][] = sprintf( 'Medios incluidos en el kit: %d (%s).', (int) $a['media']['embedded'], $size( isset( $a['media']['bytes'] ) ? $a['media']['bytes'] : 0 ) );
		}
		if ( ! empty( $detail['downloaded'] ) && is_numeric( $detail['downloaded'] ) ) {
			$out['media'][] = sprintf( 'Descargado durante la importación: %s.', $size( $detail['downloaded'] ) );
		}
		$hosts  = array();
		$vendor = 0;
		foreach ( isset( $a['hosts'] ) && is_array( $a['hosts'] ) ? $a['hosts'] : array() as $host => $count ) {
			if ( ! is_string( $host ) || ! is_numeric( $count ) || (int) $count <= 0 ) {
				continue;
			}
			if ( self::is_vendor_host( $host ) ) {
				$vendor += (int) $count;
			} else {
				$hosts[] = $host . ' (' . (int) $count . ')';
			}
		}
		$add( 'urls', 'Otros dominios que nombra el kit (sin enlace)', $hosts );
		if ( $vendor > 0 ) {
			$out['urls'][] = sprintf( 'Direcciones del proveedor del constructor original: %d. No se descargan ni se piden.', $vendor );
		}
		if ( ! empty( $a['forms'] ) && is_numeric( $a['forms'] ) ) {
			$out['configure'][] = sprintf( 'Formularios: %d. Revisa destinatarios, redirección y webhooks.', (int) $a['forms'] );
		}
		$needs = isset( $a['needs'] ) && is_array( $a['needs'] ) ? $a['needs'] : array();
		if ( ! empty( $needs['woocommerce'] ) ) {
			$out['configure'][] = 'El kit usa funciones de tienda: necesitan WooCommerce activo.';
		}
		if ( ! empty( $needs['acf'] ) ) {
			$out['configure'][] = 'El kit usa campos personalizados de ACF: este sitio debe tener esos campos.';
		}
		if ( ! empty( $needs['google_places'] ) ) {
			$out['configure'][] = 'El kit usa reseñas de Google: configura la clave de Google Places de este sitio.';
		}
		$add( 'other', 'Descartado del kit por seguridad', $map( 'hostile' ) );
		if ( ! empty( $a['ignored'] ) && is_numeric( $a['ignored'] ) ) {
			$out['other'][] = sprintf( 'Archivos fuera del kit que se ignoraron (documentación, capturas): %d.', (int) $a['ignored'] );
		}
		return $out;
	}

	/** Motivos de `not_exported` del manifiesto del origen (§8.3): motivo => número. */
	public static function not_exported( array $detail ): array {
		$analysis = isset( $detail['analysis'] ) && is_array( $detail['analysis'] ) ? $detail['analysis'] : array();
		$source   = array();
		if ( isset( $analysis['not_exported'] ) && is_array( $analysis['not_exported'] ) ) {
			$source = $analysis['not_exported'];
		} elseif ( isset( $analysis['manifest']['not_exported'] ) && is_array( $analysis['manifest']['not_exported'] ) ) {
			$source = $analysis['manifest']['not_exported'];
		}
		$out = array();
		foreach ( $source as $reason => $count ) {
			if ( is_string( $reason ) && 1 === preg_match( '/^[a-z0-9_-]+$/', $reason ) && is_numeric( $count ) && (int) $count > 0 ) {
				$out[ $reason ] = (int) $count;
			}
		}
		return $out;
	}

	/** Texto de un motivo de `not_exported`: el de K, el que añadió una etapa (`not-exported.<motivo>`) o el motivo. */
	public static function not_exported_label( string $reason ): string {
		if ( isset( self::NOT_EXPORTED_LABELS[ $reason ] ) ) {
			return self::NOT_EXPORTED_LABELS[ $reason ];
		}
		$labels = self::labels();
		return isset( $labels[ self::NOT_EXPORTED_CODE . $reason ] ) ? $labels[ self::NOT_EXPORTED_CODE . $reason ] : $reason;
	}

	/**
	 * Documentos de «Revisar antes de publicar» (A14): ID => avisos `{ part, reason, data }`.
	 *
	 * Se leen de `detail.review` (lo que persiste el runner desde `Import_Context::review()`) y, si falta, de los
	 * avisos `review` del informe.
	 */
	public static function review( array $detail ): array {
		$out = array();
		foreach ( isset( $detail['review'] ) && is_array( $detail['review'] ) ? $detail['review'] : array() as $post => $items ) {
			if ( is_numeric( $post ) && (int) $post > 0 ) {
				$out[ (int) $post ] = is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
			}
		}
		foreach ( self::normalize( isset( $detail['report'] ) ? $detail['report'] : array() )['entries'] as $entry ) {
			if ( 'review' === $entry['code'] && isset( $entry['data']['post'] ) && is_numeric( $entry['data']['post'] ) && ! isset( $out[ (int) $entry['data']['post'] ] ) ) {
				$out[ (int) $entry['data']['post'] ] = array(
					array(
						'part'   => $entry['part'],
						'reason' => isset( $entry['data']['reason'] ) ? (string) $entry['data']['reason'] : '',
						'data'   => array(),
					),
				);
			}
		}
		return $out;
	}

	/**
	 * ¿Quedan ajustes del kit por aplicar (D11)? Sólo en «agregar», con los ajustes en el respaldo y sin aplicar. El
	 * estado lo lleva `Runners\Site_Settings::apply()` en `backup.applied_hash`.
	 */
	public static function settings_pending( array $detail ): bool {
		return Module::MODE_ADD === ( $detail['mode'] ?? '' ) && ! empty( $detail['backup']['kit_settings'] ) && is_array( $detail['backup']['kit_settings'] ) && ! self::settings_applied( $detail );
	}

	/** ¿Se aplicaron los ajustes del kit desde la pantalla? */
	public static function settings_applied( array $detail ): bool {
		return ! empty( $detail['backup']['applied_hash'] ) && is_string( $detail['backup']['applied_hash'] );
	}

	// ---------------------------------------------------------------------------------------------------------------
	// D1: ¿el sitio está vacío?

	/**
	 * Motivos por los que este sitio no puede importar un kit como «nuevo» (D1). La regla es una sola, la del
	 * importador (`Session::site_is_empty()`); sin él, nunca se ofrece «nuevo» (fallar cerrado).
	 *
	 * @return string[] Vacío si el sitio está vacío.
	 */
	public static function fresh_blockers(): array {
		$class = __NAMESPACE__ . '\\Session';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'site_is_empty' ) ) {
			return array( 'El importador de kits no está disponible en este sitio.' );
		}
		$state = Session::site_is_empty();
		if ( is_array( $state ) && ! empty( $state['empty'] ) ) {
			return array();
		}
		$reasons = is_array( $state ) && isset( $state['reasons'] ) && is_array( $state['reasons'] ) ? array_values( array_filter( array_map( 'strval', $state['reasons'] ), 'strlen' ) ) : array();
		return $reasons ? $reasons : array( 'El sitio no está vacío.' );
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Salida.

	/**
	 * El informe completo de una sesión para WP-CLI (`--format=json`) y la pantalla, sin nombres del constructor
	 * original en los plugins ni en los títulos (CR-18). Null si la sesión no es de este sitio.
	 *
	 * @param string $session ID de la sesión.
	 */
	public static function public_report( string $session ): ?array {
		$row = self::session( $session );
		if ( null === $row ) {
			return null;
		}
		$detail = self::detail( $session );
		$report = $detail ? $detail['report'] : self::normalize( array() );
		$groups = self::group( $report );
		$out    = array(
			'session'      => $session,
			'title'        => $row['title'],
			'time'         => $row['time'],
			'status'       => $row['status'],
			'mode'         => $row['mode'],
			'mode_label'   => self::mode_label( $row['mode'] ),
			'reason'       => $detail ? self::mode_reason( $detail ) : self::mode_reason( array() ),
			'format'       => self::format_label( $row['format'] ),
			'created'      => $row['created'],
			'trashed'      => $row['trashed'],
			'levels'       => self::levels( $report ),
			'dropped'      => $report['dropped'],
			'requested'    => $detail ? self::requested( $detail ) : self::requested( array() ),
			'not_exported' => $detail ? self::not_exported( $detail ) : array(),
			'analysis'     => $detail ? self::analysis_lines( $detail ) : array(),
			'review'       => $detail ? array_map( 'count', self::review( $detail ) ) : array(),
			'settings'     => array(
				'pending' => $detail ? self::settings_pending( $detail ) : false,
				'applied' => $detail ? self::settings_applied( $detail ) : false,
			),
			'sections'     => array(),
		);
		foreach ( $groups as $section => $group ) {
			$entries = array();
			foreach ( $group['entries'] as $entry ) {
				$entries[] = array(
					'level' => $entry['level'],
					'code'  => $entry['code'],
					'part'  => $entry['part'],
					'text'  => self::describe_entry( $entry ),
					'data'  => self::public_value( $entry['data'] ),
				);
			}
			$out['sections'][ $section ] = array(
				'title'   => self::SECTIONS[ $section ],
				'entries' => $entries,
				'counts'  => $group['counts'],
			);
		}
		return $out;
	}

	/**
	 * Filas para `wp digitalisimo-elements kit report --format=table`: una por aviso y por contador.
	 *
	 * @param array $public Salida de `public_report()`.
	 *
	 * @return array[] `{ seccion, nivel, aviso }`.
	 */
	public static function table_rows( array $public ): array {
		$rows = array(
			array(
				'seccion' => self::SECTIONS['summary'],
				'nivel'   => self::level_label( 'info' ),
				'aviso'   => $public['mode_label'] . ' · ' . $public['format'] . ' · ' . $public['reason'],
			),
		);
		foreach ( self::requested_lines( $public['requested'] ) as $line ) {
			$rows[] = self::table_row( 'requested', 'info', $line );
		}
		foreach ( $public['analysis'] as $section => $lines ) {
			foreach ( $lines as $line ) {
				$rows[] = self::table_row( isset( self::SECTIONS[ $section ] ) ? $section : 'other', 'info', $line );
			}
		}
		foreach ( $public['review'] as $post => $count ) {
			$rows[] = self::table_row( 'review', 'warning', sprintf( 'Documento %d: %d reglas sin destino', $post, $count ) );
		}
		foreach ( $public['not_exported'] as $reason => $count ) {
			$rows[] = self::table_row( 'not-exported', 'info', self::not_exported_label( $reason ) . ': ' . $count );
		}
		foreach ( $public['sections'] as $section => $group ) {
			foreach ( $group['entries'] as $entry ) {
				$rows[] = self::table_row( $section, $entry['level'], $entry['text'] );
			}
			foreach ( $group['counts'] as $code => $count ) {
				$label  = self::code_label( $code );
				$rows[] = self::table_row( $section, 'info', ( '' === $label ? $code : $label ) . ': ' . $count );
			}
		}
		if ( $public['settings']['pending'] ) {
			$rows[] = self::table_row( 'settings', 'info', 'Los ajustes del kit quedan en el respaldo: aplícalos desde la pantalla «Kits».' );
		}
		if ( $public['dropped'] > 0 ) {
			$rows[] = self::table_row( 'other', 'info', sprintf( '%d avisos más no caben en el informe.', $public['dropped'] ) );
		}
		return $rows;
	}

	private static function table_row( string $section, string $level, string $text ): array {
		return array(
			'seccion' => self::SECTIONS[ $section ],
			'nivel'   => self::level_label( $level ),
			'aviso'   => $text,
		);
	}

	/**
	 * Un valor del informe listo para mostrar: los plugins de la familia del constructor sin nombre (CR-18) y, con el
	 * núcleo incluido, los textos con la identidad propia.
	 *
	 * @param mixed $value Valor del informe.
	 *
	 * @return mixed
	 */
	public static function public_value( $value ) {
		if ( is_string( $value ) ) {
			return self::internal_names( self::brand( $value ) );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( isset( $value['plugin'] ) && is_string( $value['plugin'] ) && self::is_builder_plugin( $value ) ) {
			return array( 'name' => self::FAMILY_LABEL );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::public_value( $item );
		}
		return $value;
	}

	/** Identificadores internos del constructor que el informe nombra con palabras propias. */
	const INTERNAL_NAMES = array(
		'elementor_library'  => 'plantilla',
		'elementor_snippet'  => 'fragmento de código',
		'elementor_font'     => 'fuente propia',
		'elementor_icons'    => 'conjunto de iconos',
		'e-floating-buttons' => 'botón flotante',
	);

	/**
	 * Un dato del informe sin los identificadores internos del constructor: las metas `_elementor_*` y los tipos
	 * `elementor_*` son nombres del proveedor que la pantalla «Kits» no muestra (brand-audit); se nombran por lo que son.
	 */
	public static function internal_names( string $text ): string {
		if ( false === stripos( $text, 'elementor' ) && false === strpos( $text, 'e-floating-buttons' ) ) {
			return $text;
		}
		$text = (string) preg_replace( '/(?<![A-Za-z0-9])_elementor[A-Za-z0-9_]*/', 'datos internos del constructor', $text );
		$text = strtr( $text, self::INTERNAL_NAMES );
		return (string) preg_replace( '/(?<![A-Za-z0-9_])elementor_[A-Za-z0-9_]+/', 'dato interno del constructor', $text );
	}

	/** Dominios del proveedor (`Digitalisimo_Vendor_Free::HOSTS`); sin esa clase, ninguno. */
	public static function vendor_hosts(): array {
		return class_exists( '\Digitalisimo_Vendor_Free' ) && defined( 'Digitalisimo_Vendor_Free::HOSTS' ) && is_array( \Digitalisimo_Vendor_Free::HOSTS ) ? \Digitalisimo_Vendor_Free::HOSTS : array();
	}

	/** ¿Es un host (o un subdominio) del proveedor? */
	public static function is_vendor_host( string $host ): bool {
		$host = strtolower( trim( $host, ". \t\n\r\0\x0B" ) );
		foreach ( self::vendor_hosts() as $vendor ) {
			if ( $host === $vendor || substr( $host, -strlen( $vendor ) - 1 ) === '.' . $vendor ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Un texto del informe sin direcciones del proveedor: cada una (con o sin esquema y ruta) pasa a «[dirección del
	 * proveedor]». El informe puede citar URL del kit; la pantalla nunca las muestra (§12.4).
	 */
	public static function scrub( string $text ): string {
		$hosts = self::vendor_hosts();
		if ( ! $hosts || '' === $text ) {
			return $text;
		}
		$alternatives = implode( '|', array_map( 'preg_quote', $hosts ) );
		$clean        = preg_replace( '#(?:[a-z][a-z0-9+.-]*:)?(?://)?(?<![a-z0-9-])(?:[a-z0-9-]+\.)*(?:' . $alternatives . ')(?![a-z0-9-])(?::\d+)?(?:[/?\#][^\s"\'<>]*)?#i', '[dirección del proveedor]', $text );
		return is_string( $clean ) ? $clean : $text;
	}

	/**
	 * Un texto que viene del kit, listo para mostrar: sin direcciones del proveedor (`scrub()`) y, con el núcleo
	 * incluido, con la identidad propia; con el plugin original, su nombre queda tal cual.
	 */
	public static function brand( string $text ): string {
		$text = self::scrub( $text );
		if ( class_exists( '\Digitalisimo_Elements_Core' ) && \Digitalisimo_Elements_Core::bundled() && class_exists( '\Digitalisimo_Elements_Identity' ) && method_exists( '\Digitalisimo_Elements_Identity', 'brand' ) ) {
			return (string) \Digitalisimo_Elements_Identity::brand( $text );
		}
		return $text;
	}
}
