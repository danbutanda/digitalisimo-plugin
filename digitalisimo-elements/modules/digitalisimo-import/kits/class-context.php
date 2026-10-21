<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Contextos de K (§5.4): los filtros que sólo viven durante una importación o una exportación.
 *
 * Fuera de un contexto K no engancha nada caro: en una petición REST sólo compara la ruta (una cadena). El contexto
 * de importación se activa en `rest_request_before_callbacks` para `upload`, `import` e `import-runner` del
 * importador 3.0 (con la ruta normalizada: mayúsculas, barra final, `?rest_route=`; V-22), desde WP-CLI y desde la
 * pantalla «Kits»; **sólo con `manage_options`** (V-3). Sin esa capacidad no toca nada y la ruta devuelve su 403.
 * `process-media` responde 403 siempre (V-23).
 *
 * `hooks()` lista exactamente los filtros que registra `activate()` y quita `deactivate()`.
 */
final class Context {
	const ROUTE_PATTERN = '#^/elementor/v1/import-export-customization/(upload|import|import-runner|revert|process-media|export)$#';

	/** @var bool */
	private static $active = false;

	/** @var string Sesión del núcleo; '' en la subida, antes de que exista. */
	private static $session = '';

	/** @var array|null Detalle de la sesión de K. */
	private static $record = null;

	/** @var array|null Registro pendiente de la subida en curso (se une a la sesión al terminar). */
	private static $pending = null;

	/** @var array[] Ganchos registrados por `activate()`. */
	private static $registered = array();

	/** @var Import_Context|null Contexto de la petición final (lo usa el remapeo). */
	private static $import = null;

	/** @var bool ¿Es la petición final (la que guarda los documentos)? */
	private static $final = false;

	/** @var int[] Documentos ya remapeados en esta petición. */
	private static $remapped = array();

	/** @var array[] Resultados de búsquedas de procedencia de esta petición: `tipo|id` => resultado. */
	private static $lookups = array();

	/** @var array Cambios del detalle que se guardan al desactivar (`front`, `drafts`…). */
	private static $notes = array();

	/** @var Export_Context|null */
	private static $export = null;

	/** @var array Ajustes de la próxima exportación (`surface`, `media`, `kit_info`). */
	private static $export_args = array();

	/** @var array[] Ganchos de exportación registrados. */
	private static $export_registered = array();

	/** @var bool Evita recursión al pedir la miniatura completa. */
	private static $thumbnail = false;

	/** @var bool|null Valor previo de «controles de estilo» del núcleo mientras un contexto los pide (null: sin pedir). */
	private static $style_controls = null;

	// ── REST ────────────────────────────────────────────────────────────────────────────────────────────────────

	/** Operación del importador 3.0 que corresponde a una ruta REST, o ''. */
	public static function operation( string $route ): string {
		$route = strtolower( rtrim( $route, '/' ) );
		return preg_match( self::ROUTE_PATTERN, $route, $match ) ? $match[1] : '';
	}

	/**
	 * `rest_request_before_callbacks` (10).
	 *
	 * @param mixed            $response Respuesta (null o un error previo).
	 * @param array            $handler  Manejador.
	 * @param \WP_REST_Request $request  Petición.
	 *
	 * @return mixed
	 */
	public static function rest_before( $response, $handler = array(), $request = null ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$operation = self::operation( (string) $request->get_route() );
		if ( '' === $operation ) {
			return $response;
		}
		if ( 'process-media' === $operation ) {
			return new \WP_Error( 'digitalisimo_kit_process_media', 'DIGITALÍSIMO Elements no envía los medios de un kit a servicios externos.', array( 'status' => 403 ) );
		}
		if ( is_wp_error( $response ) || ! current_user_can( Module::CAPABILITY ) ) {
			return $response;
		}
		switch ( $operation ) {
			case 'upload':
				return self::before_upload( $response, $request );
			case 'import':
			case 'import-runner':
				$session = (string) $request->get_param( 'session' );
				if ( Session::valid_id( $session ) ) {
					self::activate( $session );
					if ( 'import-runner' === $operation && null !== self::$record && ! empty( self::$record['last_runner'] ) && (string) $request->get_param( 'runner' ) === self::$record['last_runner'] && Module::RUNNER !== self::$record['last_runner'] ) {
						Runners\Finish::arm( $session );
					}
				}
				return $response;
			case 'export':
				$info = $request->get_param( 'kitInfo' );
				self::prepare_export(
					array(
						'surface'  => Export_Context::SURFACE_APP,
						'media'    => Export_Context::MEDIA_EMBED,
						'kit_info' => is_array( $info ) ? $info : array(),
					)
				);
				return $response;
		}
		return $response;
	}

	/**
	 * `rest_request_after_callbacks` (10): une la subida a su sesión y desactiva los contextos.
	 *
	 * @return mixed
	 */
	public static function rest_after( $response, $handler = array(), $request = null ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$operation = self::operation( (string) $request->get_route() );
		if ( '' === $operation ) {
			return $response;
		}
		if ( 'upload' === $operation && null !== self::$pending ) {
			$pending = self::$pending;
			$data    = $response instanceof \WP_REST_Response ? $response->get_data() : null;
			$session = is_array( $data ) && isset( $data['data']['session'] ) && is_string( $data['data']['session'] ) ? $data['data']['session'] : '';
			if ( $response instanceof \WP_REST_Response && $response->get_status() < 300 && Session::valid_id( $session ) ) {
				$record                         = Session::bind( $session, $pending );
				$data['data'][ Module::SOURCE ] = array(
					'mode'     => $record['mode'],
					'format'   => $record['format'],
					'report'   => Session::screen_url( $session ),
					'requests' => $record['requests'],
				);
				$response->set_data( $data );
			} else {
				Session::delete_tmp( (string) ( $pending['token'] ?? '' ) );
			}
			self::$pending = null;
		}
		if ( 'export' === $operation ) {
			self::deactivate_export();
		}
		self::deactivate();
		return $response;
	}

	/**
	 * Subida (§7.2): sin URL, sin otros orígenes y con el ZIP local guardado y normalizado.
	 *
	 * @return mixed
	 */
	private static function before_upload( $response, \WP_REST_Request $request ) {
		$file_url = $request->get_param( 'file_url' );
		if ( ! empty( $file_url ) ) {
			return new \WP_Error( 'digitalisimo_kit_file_url', 'Los kits se importan desde un ZIP de este equipo.', array( 'status' => 403 ) );
		}
		$source = (string) $request->get_param( 'source' );
		if ( '' !== $source && Module::SOURCE !== $source ) {
			return new \WP_Error( 'digitalisimo_kit_source', 'Este origen de kits no está disponible: los kits se importan desde un ZIP de este equipo.', array( 'status' => 403 ) );
		}
		if ( Module::SOURCE === $source ) {
			// El kit ya pasó el guardián en la pantalla «Kits» o en la biblioteca: `Session::kit_result()` lo entrega.
			self::activate( '' );
			return $response;
		}
		$files = $request->get_file_params();
		$file  = isset( $files['e_import_file'] ) && is_array( $files['e_import_file'] ) ? $files['e_import_file'] : array();
		$tmp   = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( '' === $tmp || ! is_file( $tmp ) ) {
			return $response;
		}
		try {
			$pending = Session::prepare(
				$tmp,
				array(
					'mode'    => Module::MODE_ADD,
					'surface' => 'app',
					'target'  => $tmp,
				)
			);
		} catch ( \RuntimeException $error ) {
			return new \WP_Error( 'digitalisimo_kit_rejected', $error->getMessage(), array( 'status' => 400 ) );
		}
		self::set_pending( $pending );
		self::activate( '', $pending );
		return $response;
	}

	// ── Contexto de importación ─────────────────────────────────────────────────────────────────────────────────

	/**
	 * Activa el contexto de importación de una sesión.
	 *
	 * @param string     $session Sesión del núcleo, o '' en la subida.
	 * @param array|null $record  Detalle; por defecto, el guardado.
	 */
	public static function activate( string $session, ?array $record = null ): void {
		if ( self::$active ) {
			self::deactivate();
		}
		self::$session = Session::valid_id( $session ) ? $session : '';
		self::$record  = null !== $record ? $record : ( '' !== self::$session ? Session::get( self::$session ) : self::$pending );
		self::$active  = true;
		self::$notes   = array();
		self::full_controls();
		Media_Proxy::reset( null === self::$record ? array() : self::$record );
		foreach ( self::hooks( self::mode() ) as $hook ) {
			add_filter( $hook[0], $hook[1], $hook[2], $hook[3] );
			self::$registered[] = $hook;
		}
	}

	/** Quita los filtros del contexto y guarda lo anotado durante la petición. */
	public static function deactivate(): void {
		foreach ( self::$registered as $hook ) {
			remove_filter( $hook[0], $hook[1], $hook[2] );
		}
		self::$registered = array();
		if ( self::$active ) {
			self::flush();
		}
		if ( self::$active && null === self::$export ) {
			self::restore_controls();
		}
		self::$active   = false;
		self::$session  = '';
		self::$record   = null;
		self::$import   = null;
		self::$final    = false;
		self::$remapped = array();
		self::$lookups  = array();
		self::$notes    = array();
	}

	/**
	 * Guarda en el detalle de la sesión lo anotado durante la petición (portada pedida, borradores, pares del WXR,
	 * bytes descargados e informe) y lo olvida, para no guardarlo dos veces.
	 */
	public static function flush(): void {
		$changes     = self::$notes;
		self::$notes = array();
		$bytes       = Media_Proxy::take_downloaded();
		if ( '' === self::$session ) {
			return;
		}
		$record = Session::get( self::$session );
		if ( null === $record ) {
			return;
		}
		if ( $bytes > 0 ) {
			$changes['downloaded'] = (int) ( $record['downloaded'] ?? 0 ) + $bytes;
		}
		if ( isset( $changes['drafts'] ) ) {
			$changes['drafts'] = array_values( array_unique( array_map( 'intval', array_merge( isset( $record['drafts'] ) && is_array( $record['drafts'] ) ? $record['drafts'] : array(), $changes['drafts'] ) ) ) );
		}
		if ( isset( $changes['wxr'] ) ) {
			$changes['wxr'] = array_replace_recursive( isset( $record['wxr'] ) && is_array( $record['wxr'] ) ? $record['wxr'] : array(), $changes['wxr'] );
		}
		if ( isset( $changes['report'] ) ) {
			$changes['report'] = Session::merge_report( isset( $record['report'] ) && is_array( $record['report'] ) ? $record['report'] : array(), $changes['report'] );
		}
		if ( $changes ) {
			self::$record = Session::update( self::$session, $changes );
		}
	}

	/**
	 * Ganchos del contexto de importación (§5.4): `[ gancho, callback, prioridad, argumentos ]`.
	 *
	 * @param string $mode `add` o `fresh`: la portada sólo se protege en «agregar».
	 */
	public static function hooks( string $mode = Module::MODE_ADD ): array {
		$hooks = array(
			array( 'pre_http_request', array( Media_Proxy::class, 'pre_http' ), 1, 3 ),
			array( 'http_request_args', array( Media_Proxy::class, 'http_args' ), 10, 2 ),
			array( 'http_api_debug', array( Media_Proxy::class, 'measure' ), 10, 5 ),
			array( 'import_attachment_size_limit', array( Media_Proxy::class, 'file_limit' ), 10, 1 ),
			array( 'elementor/import/get_default_settings_conflicts', array( __CLASS__, 'no_conflicts' ), 99, 1 ),
			array( 'wp_insert_post_data', array( __CLASS__, 'post_status' ), 99, 2 ),
			array( 'wp_import_posts', array( Wxr_Guard::class, 'posts' ), 10, 1 ),
			array( 'wp_import_post_data_raw', array( Wxr_Guard::class, 'raw' ), 10, 1 ),
			array( 'wp_import_post_data_processed', array( Wxr_Guard::class, 'processed' ), 10, 2 ),
			array( 'wp_import_post_meta', array( Wxr_Guard::class, 'post_meta' ), 10, 3 ),
			array( 'wp_import_term_meta', array( Wxr_Guard::class, 'term_meta' ), 10, 3 ),
			array( 'wp_import_post_comments', array( Wxr_Guard::class, 'comments' ), 10, 3 ),
			array( 'wp_import_insert_post', array( Wxr_Guard::class, 'inserted' ), 10, 4 ),
			array( 'add_attachment', array( Wxr_Guard::class, 'attachment' ), 10, 1 ),
			array( 'wp_update_nav_menu_item', array( Wxr_Guard::class, 'menu_item' ), 10, 3 ),
			array( 'import_end', array( Wxr_Guard::class, 'end' ), 10, 0 ),
			array( 'elementor/import-export-customization/import/wp-content/query-args/customization', array( Wxr_Guard::class, 'query_args' ), 20, 3 ),
			array( 'elementor/document/save/data', array( __CLASS__, 'preserve' ), 5, 2 ),
			array( 'elementor/document/save/data', array( __CLASS__, 'remap' ), 20, 2 ),
			array( Module::FILTER_EXISTING_DOCUMENT, array( Session::class, 'existing_bridge' ), 5, 4 ),
			array( 'created_term', array( __CLASS__, 'term_created' ), 10, 3 ),
			array( 'wp_revisions_to_keep', array( __CLASS__, 'no_revisions' ), 99, 2 ),
			array( 'wp_update_attachment_metadata', array( __CLASS__, 'svg_dimensions' ), 5, 2 ),
		);
		if ( Module::MODE_FRESH !== $mode ) {
			$hooks[] = array( 'pre_update_option_page_on_front', array( __CLASS__, 'keep_front' ), 10, 3 );
			$hooks[] = array( 'pre_update_option_show_on_front', array( __CLASS__, 'keep_front' ), 10, 3 );
		}
		return $hooks;
	}

	public static function active(): bool {
		return self::$active;
	}

	public static function session(): string {
		return self::$session;
	}

	/** Detalle de la sesión activa (o el registro pendiente de la subida). */
	public static function record(): ?array {
		return self::$record;
	}

	/** Refresca el detalle tras guardarlo. */
	public static function refresh(): void {
		if ( '' !== self::$session ) {
			self::$record = Session::get( self::$session );
		}
	}

	/** `add` o `fresh`; «agregar» si no se sabe. */
	public static function mode(): string {
		return null !== self::$record && isset( self::$record['mode'] ) && Module::MODE_FRESH === self::$record['mode'] ? Module::MODE_FRESH : Module::MODE_ADD;
	}

	public static function set_pending( ?array $pending ): void {
		self::$pending = $pending;
	}

	public static function pending(): ?array {
		return self::$pending;
	}

	/** Fija el contexto de la petición final (lo crea `Runners\Finish`). */
	public static function set_import_context( Import_Context $context, bool $final ): void {
		self::$import = $context;
		self::$final  = $final;
	}

	public static function import_context(): ?Import_Context {
		return self::$import;
	}

	/** Anota un cambio del detalle que se guarda al desactivar. */
	public static function note( string $key, $value ): void {
		if ( 'drafts' === $key ) {
			self::$notes['drafts']   = isset( self::$notes['drafts'] ) ? self::$notes['drafts'] : array();
			self::$notes['drafts'][] = (int) $value;
			return;
		}
		if ( 'wxr' === $key && is_array( $value ) ) {
			self::$notes['wxr'] = array_replace_recursive( isset( self::$notes['wxr'] ) ? self::$notes['wxr'] : array(), $value );
			return;
		}
		self::$notes[ $key ] = $value;
	}

	/** Anota un aviso en el informe de la sesión (se guarda al desactivar). */
	public static function report( string $code, array $data = array(), string $level = Part_Context::INFO ): void {
		$report = isset( self::$notes['report'] ) ? self::$notes['report'] : array(
			'entries' => array(),
			'counts'  => array(),
			'dropped' => 0,
		);
		$report['counts'][ $code ] = ( $report['counts'][ $code ] ?? 0 ) + 1;
		if ( $report['counts'][ $code ] <= Part_Context::MAX_ENTRIES_PER_CODE ) {
			$report['entries'][] = array(
				'part'  => '',
				'code'  => $code,
				'level' => $level,
				'data'  => $data,
			);
		} else {
			++$report['dropped'];
		}
		self::$notes['report'] = $report;
	}

	/** Suma a un contador del informe de la sesión sin añadir una entrada (por ejemplo, `media.served`). */
	public static function tally( string $code, int $n = 1 ): void {
		$report = isset( self::$notes['report'] ) ? self::$notes['report'] : array(
			'entries' => array(),
			'counts'  => array(),
			'dropped' => 0,
		);
		$report['counts'][ $code ] = ( $report['counts'][ $code ] ?? 0 ) + $n;
		self::$notes['report']     = $report;
	}

	/** Lo pendiente de guardar (para `Finish::after_save()` en la misma petición). */
	public static function notes(): array {
		return self::$notes;
	}

	/** Un documento que debe quedar en borrador en los dos modos (actualización, conflicto o revisión). */
	public static function force_draft( int $post_id ): void {
		if ( $post_id > 0 ) {
			self::note( 'drafts', $post_id );
		}
	}

	/** ¿Está marcado para quedar en borrador? */
	public static function is_forced_draft( int $post_id ): bool {
		if ( isset( self::$notes['drafts'] ) && in_array( $post_id, self::$notes['drafts'], true ) ) {
			return true;
		}
		return null !== self::$record && isset( self::$record['drafts'] ) && is_array( self::$record['drafts'] ) && in_array( $post_id, array_map( 'intval', self::$record['drafts'] ), true );
	}

	/** Guarda el resultado de una búsqueda de procedencia, para que el runner sepa si es actualización o conflicto. */
	public static function note_lookup( string $type, $old_id, string $hash, array $lookup ): void {
		self::$lookups[ $type . '|' . ( is_scalar( $old_id ) ? (string) $old_id : '' ) ] = $lookup + array( 'hash' => $hash );
	}

	/** Resultado de la última búsqueda de procedencia de ese tipo e ID de origen, o null. */
	public static function lookup( string $type, $old_id ): ?array {
		$key = $type . '|' . ( is_scalar( $old_id ) ? (string) $old_id : '' );
		return isset( self::$lookups[ $key ] ) ? self::$lookups[ $key ] : null;
	}

	/**
	 * Tras crear un documento: anota su procedencia y, si es una actualización o un conflicto (D7), lo deja en
	 * borrador e informa.
	 *
	 * @param int        $post_id ID nuevo.
	 * @param string     $type    Tipo con que se buscó la procedencia.
	 * @param int|string $old_id  ID de origen.
	 * @param string     $hash    Huella de origen (`Session::document_hash()` o `Session::wxr_hash()`).
	 */
	public static function created( int $post_id, string $type, $old_id, string $hash ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$lookup = self::lookup( $type, $old_id );
		$status = null !== $lookup && in_array( $lookup['status'], array( 'update', 'conflict' ), true ) ? (string) $lookup['status'] : 'new';
		Session::mark_origin( $post_id, $type, $old_id, $hash, $status );
		if ( null !== $lookup && in_array( $lookup['status'], array( 'update', 'conflict' ), true ) ) {
			self::force_draft( $post_id );
			if ( in_array( get_post_status( $post_id ), array( 'publish', 'future' ), true ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
			}
			self::report(
				'origin.' . $lookup['status'],
				array(
					'post'     => $post_id,
					'previous' => (int) $lookup['previous'],
					'type'     => $type,
				),
				Part_Context::WARNING
			);
		}
	}

	// ── Filtros del contexto de importación ─────────────────────────────────────────────────────────────────────

	/**
	 * `created_term` (10): todo término que se crea durante la importación lleva la sesión, también los que crea el
	 * importador del WXR (categorías y etiquetas de las entradas), que el núcleo no marca: así «Revertir» los borra
	 * (A10). Los reutilizados no se crean y no se tocan.
	 *
	 * @param int    $term_id  Término nuevo.
	 * @param int    $tt_id    Su `term_taxonomy_id`.
	 * @param string $taxonomy Taxonomía.
	 */
	public static function term_created( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( ! self::$active || '' === self::$session || (int) $term_id <= 0 || ! function_exists( 'add_term_meta' ) ) {
			return;
		}
		add_term_meta( (int) $term_id, '_elementor_import_session_id', self::$session, true );
	}

	/**
	 * `wp_revisions_to_keep` (99): mientras se importa, ninguna revisión. El núcleo guarda cada documento varias
	 * veces (ajustes, elementos y texto plano) y cada guardado copiaba una revisión con todas sus metas: era la mitad
	 * del tiempo de las peticiones más largas (A13), para revisiones de un contenido recién creado que nadie edita.
	 * Lo que ya estaba en el sitio no se guarda durante la importación.
	 *
	 * @param int      $num  Revisiones que se conservan.
	 * @param \WP_Post $post Entrada.
	 */
	public static function no_revisions( $num = -1, $post = null ): int {
		return 0;
	}

	/**
	 * `wp_update_attachment_metadata` (5): el ancho y el alto de un SVG importado, leídos del archivo local. Sin ellos,
	 * el núcleo los pide por HTTP a la URL del propio sitio (`simplexml_load_file( wp_get_attachment_url() )`) en
	 * mitad de la importación: una petición a sí mismo que, con un solo proceso, espera hasta agotar su tiempo (A13).
	 *
	 * @param mixed $data Metadatos del adjunto.
	 * @param int   $id   Adjunto.
	 *
	 * @return mixed
	 */
	public static function svg_dimensions( $data, $id = 0 ) {
		if ( ! self::$active || ( ! empty( $data['width'] ) && ! empty( $data['height'] ) ) || 'image/svg+xml' !== get_post_mime_type( (int) $id ) ) {
			return $data;
		}
		$file = get_attached_file( (int) $id );
		if ( ! is_string( $file ) || ! is_file( $file ) || filesize( $file ) > 5242880 ) {
			return $data;
		}
		$internal = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( (string) file_get_contents( $file ), 'SimpleXMLElement', LIBXML_NONET ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local recién importado.
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		if ( false === $xml ) {
			return $data;
		}
		$attr  = $xml->attributes();
		$box   = preg_split( '/[\s,]+/', trim( (string) $attr->viewBox ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$data  = is_array( $data ) ? $data : array();
		$width = preg_match( '/\d+/', (string) $attr->width, $match ) ? (int) $match[0] : ( 4 === count( $box ) ? (int) $box[2] : 0 );
		$height = preg_match( '/\d+/', (string) $attr->height, $match ) ? (int) $match[0] : ( 4 === count( $box ) ? (int) $box[3] : 0 );
		if ( $width > 0 && $height > 0 ) {
			$data['width']  = $width;
			$data['height'] = $height;
		}
		return $data;
	}

	/** `elementor/import/get_default_settings_conflicts` (99): nunca se pisan condiciones existentes. */
	public static function no_conflicts( $conflicts = array() ): array {
		return array();
	}

	/**
	 * `wp_insert_post_data` (99): regla de estados de D8 para lo que crea la sesión (`ID > max_post_id`).
	 *
	 * @param array $data    Datos saneados de la entrada.
	 * @param array $postarr Datos recibidos.
	 */
	public static function post_status( $data, $postarr = array() ) {
		if ( ! self::$active || ! is_array( $data ) ) {
			return $data;
		}
		$id  = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$max = null !== self::$record && isset( self::$record['max_post_id'] ) ? (int) self::$record['max_post_id'] : null;
		if ( $id > 0 && ( null === $max || $id <= $max ) ) {
			return $data;
		}
		$status = isset( $data['post_status'] ) ? (string) $data['post_status'] : '';
		if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		$type     = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';
		$doc_type = isset( $postarr['meta_input']['_elementor_template_type'] ) ? (string) $postarr['meta_input']['_elementor_template_type'] : ( $id > 0 ? (string) get_post_meta( $id, '_elementor_template_type', true ) : '' );
		if ( self::should_draft( $type, $doc_type, $id ) ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	/**
	 * ¿Debe quedar en borrador (D8)? Siempre `elementor_snippet` y lo marcado; en «agregar», además lo que se ve sin
	 * que nadie lo enlace: entradas de tipos visibles, plantillas que se aplican solas y botones flotantes.
	 */
	public static function should_draft( string $type, string $doc_type, int $id = 0 ): bool {
		if ( 'elementor_snippet' === $type || ( $id > 0 && self::is_forced_draft( $id ) ) ) {
			return true;
		}
		if ( Module::MODE_FRESH === self::mode() ) {
			return false;
		}
		if ( 'e-floating-buttons' === $type ) {
			return true;
		}
		if ( 'elementor_library' === $type ) {
			return Session::is_theme_template( $doc_type );
		}
		if ( in_array( $type, array( 'attachment', 'nav_menu_item', 'revision', 'elementor_font', 'elementor_icons', 'product_variation', 'wp_block' ), true ) ) {
			return false;
		}
		return function_exists( 'is_post_type_viewable' ) && is_post_type_viewable( $type );
	}

	/**
	 * `pre_update_option_page_on_front` y `pre_update_option_show_on_front` (sólo «agregar»): la portada no cambia;
	 * se anota que el kit trae una.
	 */
	public static function keep_front( $value, $old_value = null, $option = '' ) {
		if ( $value !== $old_value ) {
			self::note( 'front', true );
		}
		return $old_value;
	}

	/**
	 * `elementor/document/save/data` (5): el marcador `digitalisimo-unconverted` envuelve lo que el núcleo no puede
	 * instanciar, antes de la captura del núcleo en 10 (V-25). Lo implementa `Kits\Preserve`.
	 */
	public static function preserve( $data, $document = null ) {
		if ( ! is_array( $data ) || ! isset( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}
		$class = __NAMESPACE__ . '\\Preserve';
		try {
			if ( class_exists( $class ) && method_exists( $class, 'on_save_data' ) ) {
				$result = $class::on_save_data( $data, $document );
				return is_array( $result ) ? $result : $data;
			}
			if ( class_exists( $class ) && method_exists( $class, 'wrap' ) ) {
				$data['elements'] = $class::wrap( $data['elements'], 'kit' );
			}
		} catch ( \Throwable $error ) {
			self::report( 'preserve.error', array( 'message' => $error->getMessage() ), Part_Context::ERROR );
		}
		return $data;
	}

	/**
	 * `elementor/document/save/data` (20): el remapeo de §9, sólo en la petición final, una vez por documento creado
	 * en la sesión (CV-12). Los documentos ajenos no se tocan. Lo implementa `Kits\Remapper`.
	 */
	public static function remap( $data, $document = null ) {
		if ( ! self::$final || null === self::$import || ! is_array( $data ) || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return $data;
		}
		$id = (int) $document->get_main_id();
		if ( $id <= 0 || isset( self::$remapped[ $id ] ) || (string) get_post_meta( $id, '_elementor_import_session_id', true ) !== self::$session ) {
			return $data;
		}
		self::$remapped[ $id ] = true;
		$class                 = __NAMESPACE__ . '\\Remapper';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'apply' ) ) {
			return $data;
		}
		try {
			$result = $class::apply( $data, self::$import, $id );
			return is_array( $result ) ? $result : $data;
		} catch ( \Throwable $error ) {
			self::$import->report(
				'remap.error',
				array(
					'post'    => $id,
					'message' => $error->getMessage(),
				),
				Part_Context::ERROR
			);
			return $data;
		}
	}

	/** ¿Ya se remapeó este documento en la petición? */
	public static function remapped( int $post_id ): bool {
		return isset( self::$remapped[ $post_id ] );
	}

	// ── Contexto de exportación ─────────────────────────────────────────────────────────────────────────────────

	/** Ajustes de la próxima exportación: `surface`, `media` y `kit_info`. */
	public static function prepare_export( array $args ): void {
		self::$export_args = $args;
	}

	/**
	 * Activa el contexto de exportación (`export-kit`, prioridad 20).
	 *
	 * @param array $include Lo que se exporta (`templates`, `content`, `settings`…).
	 */
	public static function activate_export( array $include = array() ): Export_Context {
		self::deactivate_export_hooks();
		self::full_controls();
		$args         = self::$export_args;
		self::$export = Export_Context::for_site(
			array(
				'surface' => isset( $args['surface'] ) ? (string) $args['surface'] : Export_Context::SURFACE_APP,
				'media'   => isset( $args['media'] ) ? (string) $args['media'] : Export_Context::MEDIA_EMBED,
				'include' => $include,
			)
		);
		foreach ( self::export_hooks() as $hook ) {
			add_filter( $hook[0], $hook[1], $hook[2], $hook[3] );
			self::$export_registered[] = $hook;
		}
		return self::$export;
	}

	/** Ganchos del contexto de exportación (§5.4). */
	public static function export_hooks(): array {
		return array(
			array( 'wxr_export_skip_postmeta', array( Wxr_Guard::class, 'skip_meta' ), 10, 2 ),
			array( 'wxr_export_skip_termmeta', array( Wxr_Guard::class, 'skip_meta' ), 10, 2 ),
			array( 'elementor/import-export-customization/export/wp-content/query-args/customization', array( __CLASS__, 'export_query_args' ), 20, 3 ),
			array( 'wp_get_attachment_image_src', array( __CLASS__, 'full_thumbnail' ), 10, 3 ),
			array( 'elementor/templates/collect_media_url', array( __CLASS__, 'collect_media' ), 10, 2 ),
			array( 'get_the_author_display_name', array( __CLASS__, 'no_author' ), 99, 1 ),
		);
	}

	/**
	 * `get_the_author_display_name` (99), sólo durante una exportación: el `author` del manifiesto (el nombre visible
	 * de quien creó el kit activo) es un dato personal y no viaja (CR-14). Los autores del WXR ya salen como
	 * `Autor <n>`.
	 */
	public static function no_author( $name = '' ): string {
		return '';
	}

	public static function export_context(): ?Export_Context {
		return self::$export;
	}

	/** Datos de la exportación en curso: `kit_info` (título del kit). */
	public static function export_args(): array {
		return self::$export_args;
	}

	/** Quita los ganchos de exportación; el contexto sigue disponible hasta `deactivate_export()`. */
	public static function deactivate_export_hooks(): void {
		foreach ( self::$export_registered as $hook ) {
			remove_filter( $hook[0], $hook[1], $hook[2] );
		}
		self::$export_registered = array();
	}

	public static function deactivate_export(): void {
		self::deactivate_export_hooks();
		self::$export      = null;
		self::$export_args = array();
		if ( ! self::$active ) {
			self::restore_controls();
		}
	}

	/**
	 * Fuera del editor y de REST (WP-CLI), el núcleo sólo da los controles de contenido de cada elemento: los de estilo
	 * quedan aparte. Importar y exportar un kit necesita todos (el saneador de valores por defecto y las referencias
	 * derivadas los leen), así que el contexto los pide y al terminar deja el valor anterior.
	 */
	private static function full_controls(): void {
		$class = '\Elementor\Core\Frontend\Performance';
		if ( null !== self::$style_controls || ! class_exists( $class ) || ! method_exists( $class, 'set_use_style_controls' ) ) {
			return;
		}
		self::$style_controls = (bool) call_user_func( array( $class, 'is_use_style_controls' ) );
		call_user_func( array( $class, 'set_use_style_controls' ), true );
	}

	private static function restore_controls(): void {
		$class = '\Elementor\Core\Frontend\Performance';
		if ( null === self::$style_controls || ! class_exists( $class ) ) {
			return;
		}
		call_user_func( array( $class, 'set_use_style_controls' ), self::$style_controls );
		self::$style_controls = null;
	}

	/**
	 * Anota algo que no se exportó a propósito (por ejemplo, la URL de un webhook) en
	 * `manifest.digitalisimo.not_exported`. Lo llaman los derivados de PRO de §7.8.
	 */
	public static function export_note( string $reason, int $count = 1 ): void {
		if ( null !== self::$export ) {
			self::$export->not_exported( $reason, $count );
		}
	}

	/** `…/export/wp-content/query-args/customization` (20): todo el contenido, no 20 por tipo (CC-13). */
	public static function export_query_args( $args, $post_type = '', $customization = null ) {
		if ( is_array( $args ) ) {
			$args['limit'] = -1;
		}
		return $args;
	}

	/** `wp_get_attachment_image_src` (10): la miniatura del manifiesto, en tamaño completo (CC-23). */
	public static function full_thumbnail( $image, $attachment_id = 0, $size = '' ) {
		if ( 'post-thumbnail' !== $size || self::$thumbnail ) {
			return $image;
		}
		self::$thumbnail = true;
		$full            = wp_get_attachment_image_src( (int) $attachment_id, 'full' );
		self::$thumbnail = false;
		return $full ? $full : $image;
	}

	/** `elementor/templates/collect_media_url` (10): adjuntos y URL que usa lo exportado, para la parte `media`. */
	public static function collect_media( $url = '', $attachment = array() ): void {
		if ( null === self::$export ) {
			return;
		}
		$attachment = is_array( $attachment ) ? $attachment : array();
		$id         = isset( $attachment['id'] ) ? (int) $attachment['id'] : ( isset( $attachment['ID'] ) ? (int) $attachment['ID'] : 0 );
		if ( $id > 0 ) {
			self::$export->add_exported( 'attachment', array( $id ) );
		}
		if ( is_string( $url ) && '' !== $url ) {
			self::$export->add_exported( 'media_url', array( $url ) );
		}
	}
}
