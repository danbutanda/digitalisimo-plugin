<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Parts;
use Digitalisimo\Elements\Import\Kits\Preserve;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Session;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;
use Elementor\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Runner final `digitalisimo` (§7.3).
 *
 * `import()` corre en la petición final, antes de que el núcleo guarde los documentos: arma los mapas (§9.1),
 * importa las partes en su orden (`origin`, `element-defaults`, `kit-settings`, `files`, `media`, `theme-builder`,
 * `term-meta` y el resto por clave) y deja el contexto para el remapeo de `elementor/document/save/data` (20).
 * `after_save()` cierra la sesión cuando ya está todo guardado: procedencia local, condiciones cerradas (§9.5),
 * borradores de «revisar antes de publicar», cachés, contadores de marcadores, informe y limpieza.
 */
class Finish extends Import_Runner_Base {
	/** Clave de meta pendiente con las condiciones de un fragmento de Custom Code (las traduce `after_save()`). */
	const PENDING_CONDITIONS = '_digitalisimo_import_conditions';

	/** @var string */
	private $session = '';

	/** @var string */
	private $mode = Module::MODE_ADD;

	/** @var string */
	private $kit = '';

	/** @var array[] Instantánea del informe ya guardado de cada contexto (por `spl_object_id`). */
	private static $snapshots = array();

	public static function get_name(): string {
		return Module::RUNNER;
	}

	public function should_import( array $data ) {
		return true;
	}

	public function import( array $data, array $imported_data ) {
		$this->session = (string) ( $data['session_id'] ?? '' );
		$record        = Session::get( $this->session );
		$record        = null === $record ? array() : $record;
		$this->mode    = Module::MODE_FRESH === ( $record['mode'] ?? '' ) ? Module::MODE_FRESH : Module::MODE_ADD;
		$this->kit     = (string) ( $record['origin']['kit'] ?? '' );
		if ( Context::session() !== $this->session ) {
			Context::activate( $this->session );
		}

		$context = self::context( $this->session, $imported_data, $data );
		$last    = self::last_runner();
		if ( '' !== $last && Module::RUNNER !== $last ) {
			$context->report( 'runner.after', array( 'runner' => $last ), Part_Context::WARNING );
		}
		$summary = array();
		$covered = array();
		foreach ( Parts::all() as $key => $part ) {
			$input = self::part_input( $data, (string) $key, $part );
			if ( null === $input ) {
				continue;
			}
			if ( 'theme-builder' === $key ) {
				$covered = array_values( array_map( 'strval', array_keys( $input['data'] ) ) );
			}
			$context->begin_part( (string) $key );
			try {
				$summary[ $key ] = $part->import( $input['data'], $input['version'], $context );
			} catch ( \Throwable $error ) {
				$context->report( 'part.error', array( 'message' => $error->getMessage() ), Part_Context::ERROR );
			}
			$context->end_part();
		}
		self::original_types( $data, $context );
		if ( Module::MODE_ADD === $this->mode ) {
			$dir = (string) ( $data['extracted_directory_path'] ?? '' );
			foreach ( array( 'default-styles', 'global-classes', 'global-variables' ) as $v4 ) {
				if ( '' !== $dir && ( is_dir( $dir . $v4 ) || is_file( $dir . $v4 . '.json' ) ) ) {
					$context->report( 'v4.skipped', array( 'what' => $v4 ), Part_Context::INFO );
				}
			}
		}
		Context::set_import_context( $context, '' === $last || Module::RUNNER === $last );
		Session::update(
			$this->session,
			array(
				'last_runner'  => $last,
				'tb_covered'   => $covered,
				'manifest_own' => isset( $data['manifest'][ Module::MANIFEST_KEY ] ) && is_array( $data['manifest'][ Module::MANIFEST_KEY ] ) ? $data['manifest'][ Module::MANIFEST_KEY ] : array(),
				'extracted'    => self::uploads_relative( (string) ( $data['extracted_directory_path'] ?? '' ) ),
				'parts'        => self::clean_summary( $summary ),
			)
		);
		self::persist( $this->session, $context );

		return array(
			Module::RUNNER => array(
				'session' => $this->session,
				'mode'    => $this->mode,
			),
		);
	}

	/** Puntero de K en la sesión nativa (§5.3): `{ v, mode, kit, report_option }`. */
	public function get_import_session_metadata(): array {
		return array(
			'v'             => Module::FORMAT,
			'mode'          => $this->mode,
			'kit'           => $this->kit,
			'report_option' => Session::option( $this->session ),
		);
	}

	// ── Contexto, mapas y partes ────────────────────────────────────────────────────────────────────────────────

	/**
	 * Contexto de importación de la sesión, con los mapas de §9.1.
	 *
	 * @param string $session       Sesión del núcleo.
	 * @param array  $imported_data Lo importado por los runners anteriores; vacío usa los mapas guardados.
	 * @param array  $data          Datos del proceso (manifiesto).
	 */
	public static function context( string $session, array $imported_data, array $data = array() ): Import_Context {
		$record = Session::get( $session );
		$record = null === $record ? array() : $record;
		$saved  = isset( $record['maps'] ) && is_array( $record['maps'] ) ? $record['maps'] : array();
		$maps   = $imported_data ? self::maps( $record, $imported_data ) : $saved;
		foreach ( $saved as $name => $map ) {
			if ( ! isset( $maps[ $name ] ) && is_array( $map ) ) {
				$maps[ $name ] = $map;
			}
		}
		$own = isset( $data['manifest'][ Module::MANIFEST_KEY ] ) && is_array( $data['manifest'][ Module::MANIFEST_KEY ] ) ? $data['manifest'][ Module::MANIFEST_KEY ] : ( isset( $record['manifest_own'] ) && is_array( $record['manifest_own'] ) ? $record['manifest_own'] : array() );
		$dir = Session::staged_dir( $record );
		return new Import_Context(
			array(
				'session'     => $session,
				'mode'        => (string) ( $record['mode'] ?? Module::MODE_ADD ),
				'kit'         => (string) ( $record['origin']['kit'] ?? '' ),
				'origin'      => isset( $record['origin'] ) && is_array( $record['origin'] ) ? $record['origin'] : array(),
				'manifest'    => $own,
				'user'        => get_current_user_id(),
				'max_post_id' => (int) ( $record['max_post_id'] ?? 0 ),
				'kit_id'      => (int) ( $record['kit_id'] ?? 0 ),
				'staged_dir'  => null === $dir ? '' : $dir,
				'maps'        => $maps,
				'stored'      => isset( $record['stored'] ) && is_array( $record['stored'] ) ? $record['stored'] : array(),
			)
		);
	}

	/** Mapas nativos (§9.1): entradas, plantillas, términos, menús, globales, URL y adjuntos. */
	public static function maps( array $record, array $imported_data ): array {
		$post = ImportExportUtils::map_old_new_post_ids( $imported_data );
		if ( isset( $record['wxr']['pairs'] ) && is_array( $record['wxr']['pairs'] ) ) {
			$post += array_map( 'intval', $record['wxr']['pairs'] );
		}
		$template = array();
		foreach ( $post as $old => $new ) {
			if ( 'elementor_library' === get_post_type( (int) $new ) ) {
				$template[ $old ] = (int) $new;
			}
		}
		$menu      = array();
		$menu_slug = array();
		foreach ( isset( $imported_data['taxonomies'] ) && is_array( $imported_data['taxonomies'] ) ? $imported_data['taxonomies'] : array() as $by_type ) {
			foreach ( is_array( $by_type ) ? $by_type : array() as $taxonomy => $terms ) {
				if ( 'nav_menu' !== $taxonomy || ! is_array( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( is_array( $term ) && isset( $term['old_id'], $term['new_id'] ) ) {
						$menu[ $term['old_id'] ] = (int) $term['new_id'];
						if ( isset( $term['old_slug'], $term['new_slug'] ) && '' !== (string) $term['new_slug'] ) {
							$menu_slug[ (string) $term['old_slug'] ] = (string) $term['new_slug'];
						}
					}
				}
			}
		}
		$origin  = isset( $record['origin'] ) && is_array( $record['origin'] ) ? $record['origin'] : array();
		$home    = rtrim( (string) home_url(), '/' );
		$uploads = wp_upload_dir( null, false );
		$url     = array();
		if ( ! empty( $origin['uploads'] ) && empty( $uploads['error'] ) ) {
			$url[ rtrim( (string) $origin['uploads'], '/' ) ] = rtrim( (string) $uploads['baseurl'], '/' );
		}
		foreach ( array( 'home', 'site' ) as $key ) {
			$prefix = rtrim( (string) ( $origin[ $key ] ?? '' ), '/' );
			if ( '' !== $prefix && ! isset( $url[ $prefix ] ) ) {
				$url[ $prefix ] = $home;
			}
		}
		$map = isset( $record['settings_map'] ) && is_array( $record['settings_map'] ) ? $record['settings_map'] : array();
		return array(
			'post'              => $post,
			'template'          => $template,
			'term'              => ImportExportUtils::map_old_new_term_ids( $imported_data ),
			'menu'              => $menu,
			'menu_slug'         => $menu_slug,
			'global_color'      => isset( $map['global_color'] ) && is_array( $map['global_color'] ) ? $map['global_color'] : array(),
			'global_typography' => isset( $map['global_typography'] ) && is_array( $map['global_typography'] ) ? $map['global_typography'] : array(),
			'url'               => $url,
			'attachment'        => self::attachments( $record ),
		);
	}

	/** URL vieja → adjunto nuevo: los medios del paquete por `_elementor_source_image_hash` y los del WXR. */
	private static function attachments( array $record ): array {
		global $wpdb;
		$map   = array();
		$dir   = Session::staged_dir( $record );
		$media = array();
		if ( null !== $dir && is_file( $dir . 'media.json' ) ) {
			$media = json_decode( (string) file_get_contents( $dir . 'media.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo propio.
		}
		$hashes = array();
		foreach ( is_array( $media ) ? array_keys( $media ) : array() as $url ) {
			$hashes[ sha1( (string) $url ) ] = (string) $url;
		}
		foreach ( array_chunk( array_keys( $hashes ), 100 ) as $chunk ) {
			$in   = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_source_image_hash' AND meta_value IN ($in)", $chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $row ) {
				$map[ $hashes[ $row->meta_value ] ] = (int) $row->post_id;
			}
		}
		if ( isset( $record['wxr']['attachments'] ) && is_array( $record['wxr']['attachments'] ) ) {
			foreach ( $record['wxr']['attachments'] as $url => $id ) {
				if ( ! isset( $map[ $url ] ) ) {
					$map[ (string) $url ] = (int) $id;
				}
			}
		}
		return $map;
	}

	/**
	 * Datos y versión de una parte: `digitalisimo/<clave>.json` y su versión del manifiesto. Sin archivo, la parte
	 * recibe `[]` con su versión actual (la de procedencia corre también en kits de terceros). Para
	 * `element-defaults`, sin archivo propio se usa el nativo `kit-elements-defaults.json` (versión 1). Null si el
	 * kit trae una versión que la parte no lee.
	 *
	 * @return array{data: array, version: int}|null
	 */
	public static function part_input( array $data, string $key, Part $part ): ?array {
		$dir     = (string) ( $data['extracted_directory_path'] ?? '' );
		$file    = '' === $dir ? '' : $dir . Parts::file( $key );
		$version = isset( $data['manifest'][ Module::MANIFEST_KEY ]['parts'][ $key ] ) && is_numeric( $data['manifest'][ Module::MANIFEST_KEY ]['parts'][ $key ] ) ? (int) $data['manifest'][ Module::MANIFEST_KEY ]['parts'][ $key ] : 0;
		$json    = null;
		if ( '' !== $file && is_file( $file ) ) {
			$json    = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- extraído del kit.
			$version = $version > 0 ? $version : 1;
		} elseif ( 'element-defaults' === $key && '' !== $dir && is_file( $dir . 'kit-elements-defaults.json' ) ) {
			$json    = json_decode( (string) file_get_contents( $dir . 'kit-elements-defaults.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$version = 1;
		} else {
			$version = $part->version();
		}
		if ( ! Parts::accepts( $key, $version ) ) {
			Context::report(
				'part.version',
				array(
					'part'    => $key,
					'version' => $version,
				),
				Part_Context::WARNING
			);
			return null;
		}
		return array(
			'data'    => is_array( $json ) ? $json : array(),
			'version' => $version,
		);
	}

	/**
	 * Guarda en la sesión lo que dejó un contexto: `stored`, mapas, «revisar antes de publicar» y lo nuevo del informe.
	 */
	public static function persist( string $session, Import_Context $context ): void {
		$record = Session::get( $session );
		if ( null === $record ) {
			return;
		}
		$id     = spl_object_id( $context );
		$snap   = isset( self::$snapshots[ $id ] ) ? self::$snapshots[ $id ] : array(
			'entries' => 0,
			'counts'  => array(),
			'dropped' => 0,
		);
		$report = $context->get_report();
		$delta  = array(
			'entries' => array_slice( $report['entries'], $snap['entries'] ),
			'counts'  => array(),
			'dropped' => $report['dropped'] - $snap['dropped'],
		);
		foreach ( $report['counts'] as $code => $n ) {
			$diff = (int) $n - (int) ( $snap['counts'][ $code ] ?? 0 );
			if ( 0 !== $diff ) {
				$delta['counts'][ $code ] = $diff;
			}
		}
		self::$snapshots[ $id ] = array(
			'entries' => count( $report['entries'] ),
			'counts'  => $report['counts'],
			'dropped' => $report['dropped'],
		);
		$review = isset( $record['review'] ) && is_array( $record['review'] ) ? $record['review'] : array();
		foreach ( $context->review() as $post => $items ) {
			$review[ (int) $post ] = $items;
		}
		Session::update(
			$session,
			array(
				'stored' => $context->stored_all(),
				'maps'   => $context->maps(),
				'review' => $review,
				'report' => Session::merge_report( isset( $record['report'] ) && is_array( $record['report'] ) ? $record['report'] : array(), $delta ),
			)
		);
		Context::refresh();
	}

	/**
	 * Arma el remapeo en la petición de un runner que quedó detrás del final (§17): usa los mapas guardados.
	 */
	public static function arm( string $session ): void {
		if ( null !== Session::get( $session ) ) {
			Context::set_import_context( self::context( $session, array() ), true );
		}
	}

	/** Nombre del último runner del proceso en curso, o '' si no se sabe. */
	public static function last_runner(): string {
		try {
			$module = Session::import_module();
		} catch ( \RuntimeException $error ) {
			return '';
		}
		$import = isset( $module->import ) ? $module->import : null;
		if ( ! is_object( $import ) || ! method_exists( $import, 'get_runners_name' ) ) {
			return '';
		}
		$names = (array) $import->get_runners_name();
		return $names ? (string) end( $names ) : '';
	}

	/** Resumen de las partes con sólo escalares y listas cortas (cabe en la sesión). */
	private static function clean_summary( array $summary ): array {
		$json = json_encode( $summary );
		if ( ! is_string( $json ) || strlen( $json ) > 16384 ) {
			return array_map(
				function ( $item ) {
					return is_array( $item ) ? count( $item ) : $item;
				},
				$summary
			);
		}
		return $summary;
	}

	// ── Cierre ──────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Cierra la sesión cuando ya está todo guardado (§7.3). En la App, en `…/runner/after-run` del último runner; en
	 * WP-CLI, al final del comando. Una sola vez por sesión.
	 *
	 * @return array El informe de la sesión.
	 */
	public static function after_save( string $session ): array {
		$record = Session::get( $session );
		if ( null === $record ) {
			return array();
		}
		if ( ! empty( $record['finished'] ) ) {
			return isset( $record['report'] ) && is_array( $record['report'] ) ? $record['report'] : array();
		}
		if ( Context::session() === $session ) {
			Context::flush();
		} else {
			Context::activate( $session );
		}
		$record  = (array) Session::get( $session );
		$context = Context::import_context();
		if ( null === $context || $context->session() !== $session ) {
			$context = self::context( $session, array() );
		}
		$ids = self::session_posts( $session );

		// 1. Procedencia: la huella de la copia local, ya guardada (D7).
		foreach ( $ids as $id ) {
			$origin = get_post_meta( $id, Module::META_ORIGIN, true );
			if ( is_array( $origin ) && (string) ( $origin['session'] ?? '' ) === $session ) {
				Session::refresh_local( $id );
			}
		}

		// 2. Condiciones cerradas (§9.5): Custom Code aquí; el Theme Builder lo hace su parte (o el respaldo de abajo).
		self::snippet_conditions( $ids, $context );
		self::theme_conditions( $ids, $context, isset( $record['tb_covered'] ) && is_array( $record['tb_covered'] ) && null !== Parts::get( 'theme-builder' ) ? $record['tb_covered'] : array() );
		foreach ( Parts::all() as $key => $part ) {
			if ( ! method_exists( $part, 'after_save' ) ) {
				continue;
			}
			$context->begin_part( (string) $key );
			try {
				$part->after_save( $context );
			} catch ( \Throwable $error ) {
				$context->report( 'part.error', array( 'message' => $error->getMessage() ), Part_Context::ERROR );
			}
			$context->end_part();
		}

		self::located_sections( $ids, Module::MODE_FRESH === ( $record['mode'] ?? '' ) ? Module::MODE_FRESH : Module::MODE_ADD );

		// 3. «Revisar antes de publicar» queda en borrador en los dos modos (§5.5).
		$review = isset( $record['review'] ) && is_array( $record['review'] ) ? $record['review'] : array();
		foreach ( array_unique( array_merge( array_keys( $review ), $context->review_ids() ) ) as $post_id ) {
			$post_id = (int) $post_id;
			Context::force_draft( $post_id );
			if ( in_array( get_post_status( $post_id ), array( 'publish', 'future' ), true ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
			}
		}

		// 4. Cachés y 5. contadores de marcadores.
		if ( class_exists( '\Elementor\Plugin' ) && isset( Plugin::$instance->files_manager ) ) {
			Plugin::$instance->files_manager->clear_cache();
		}
		self::count_markers();

		// 6. URL del origen que quedan (§9.3).
		$left = self::origin_urls_left( $ids, isset( $record['origin'] ) && is_array( $record['origin'] ) ? $record['origin'] : array() );
		if ( $left > 0 ) {
			$context->report( 'urls.left', array( 'documents' => $left ), Part_Context::WARNING );
		}
		if ( ! empty( $record['front'] ) || ! empty( Context::notes()['front'] ) ) {
			$context->report( 'front.kept', array(), Part_Context::INFO );
		}

		// 7. Huellas tras importar: la reversión sólo restaura lo que nadie cambió después.
		$backup = isset( $record['backup'] ) && is_array( $record['backup'] ) ? $record['backup'] : array();
		$kit_id = (int) ( $record['kit_id'] ?? 0 );
		if ( $kit_id > 0 ) {
			$settings              = get_post_meta( $kit_id, '_elementor_page_settings', true );
			$backup['hash_after']  = Part_Context::hash( is_array( $settings ) ? $settings : array() );
			$backup['metas_after'] = array();
			foreach ( array_merge( array_keys( isset( $backup['metas_before'] ) ? (array) $backup['metas_before'] : array() ), array_keys( isset( $backup['v4_before'] ) ? (array) $backup['v4_before'] : array() ) ) as $key ) {
				$backup['metas_after'][ $key ] = Part_Context::hash( metadata_exists( 'post', $kit_id, $key ) ? get_post_meta( $kit_id, $key, true ) : null );
			}
		}

		self::persist( $session, $context );
		Context::flush();
		Session::update(
			$session,
			array(
				'backup'   => $backup,
				'status'   => Session::STATUS_IMPORTED,
				'finished' => time(),
			)
		);
		$final  = (array) Session::get( $session );
		$report = isset( $final['report'] ) && is_array( $final['report'] ) ? $final['report'] : array();

		// 8. Aviso único y acción pública.
		if ( get_current_user_id() > 0 ) {
			update_user_meta(
				get_current_user_id(),
				Module::USER_META_NOTICE,
				array(
					'session' => $session,
					'time'    => time(),
				)
			);
		}
		do_action( Module::ACTION_IMPORTED, $session, $report );

		// 9. Temporales: se borran, salvo los medios que necesita «Aplicar los ajustes del kit» (hasta 24 h). La copia
		// extraída del núcleo (`uploads/elementor/tmp/…`, sin protección) ya no hace falta.
		if ( empty( $backup['kit_settings'] ) && ! empty( $final['token'] ) ) {
			Session::delete_tmp( (string) $final['token'] );
		}
		if ( ! empty( $final['extracted'] ) && is_string( $final['extracted'] ) ) {
			Session::delete_extracted( $final['extracted'] );
		}
		Session::schedule_cleanup( $session );
		return $report;
	}

	/**
	 * `original_type` de `manifest.templates` (un kit convertido trae como `section` lo que este sitio no tiene) →
	 * `_digitalisimo_original_type` de la plantilla creada, la haya creado el runner de plantillas o un delegado de
	 * PRO (las secciones las importa el del Theme Builder). Así la pantalla «Kits» no la publica y el informe la
	 * lista (CC-4).
	 */
	private static function original_types( array $data, Import_Context $context ): void {
		$templates = isset( $data['manifest']['templates'] ) && is_array( $data['manifest']['templates'] ) ? $data['manifest']['templates'] : array();
		foreach ( $templates as $old => $settings ) {
			if ( ! is_array( $settings ) || ! isset( $settings['original_type'] ) || ! is_string( $settings['original_type'] ) ) {
				continue;
			}
			$type = substr( (string) preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $settings['original_type'] ) ), 0, 64 );
			$new  = (int) $context->mapped( 'template', $old, 0 );
			if ( '' === $type || $new <= 0 || ! $context->is_new_post( $new ) || metadata_exists( 'post', $new, Module::META_ORIGINAL_TYPE ) ) {
				continue;
			}
			update_post_meta( $new, Module::META_ORIGINAL_TYPE, $type );
			$context->report(
				'original-type',
				array(
					'post' => $new,
					'type' => $type,
				),
				Part_Context::INFO
			);
		}
	}

	/**
	 * Una sección importada con ubicación y condiciones se aplica sola como una cabecera: queda en borrador en
	 * «agregar» (D8). Sin ubicación sólo se ve incrustada y conserva su estado.
	 *
	 * @param int[] $ids Lo que creó la sesión.
	 */
	private static function located_sections( array $ids, string $mode ): void {
		if ( Module::MODE_ADD !== $mode ) {
			return;
		}
		foreach ( $ids as $id ) {
			if ( 'elementor_library' !== get_post_type( $id ) || Session::is_theme_template( (string) get_post_meta( $id, '_elementor_template_type', true ) ) ) {
				continue;
			}
			if ( '' === (string) get_post_meta( $id, '_elementor_location', true ) || ! get_post_meta( $id, '_elementor_conditions', true ) ) {
				continue;
			}
			Context::force_draft( $id );
			if ( in_array( get_post_status( $id ), array( 'publish', 'future' ), true ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
			}
		}
	}

	/** Ruta relativa a `uploads` de una carpeta bajo `uploads`, o ''. */
	private static function uploads_relative( string $path ): string {
		$uploads = wp_upload_dir( null, false );
		if ( '' === $path || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$base = rtrim( str_replace( '\\', '/', (string) $uploads['basedir'] ), '/' ) . '/';
		$path = str_replace( '\\', '/', $path );
		return 0 === strpos( $path, $base ) ? substr( $path, strlen( $base ) ) : '';
	}

	/** IDs de lo que creó la sesión (cualquier estado y tipo). */
	public static function session_posts( string $session ): array {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_import_session_id' AND meta_value = %s", $session ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Condiciones de los fragmentos de Custom Code, traducidas y cerradas (§7.8 #5, §9.5). */
	private static function snippet_conditions( array $ids, Import_Context $context ): void {
		foreach ( $ids as $id ) {
			if ( 'elementor_snippet' !== get_post_type( $id ) || ! metadata_exists( 'post', $id, self::PENDING_CONDITIONS ) ) {
				continue;
			}
			$raw = get_post_meta( $id, self::PENDING_CONDITIONS, true );
			delete_post_meta( $id, self::PENDING_CONDITIONS );
			if ( ! is_array( $raw ) || ! $raw ) {
				continue;
			}
			$translated = self::translate_conditions( $raw, $context );
			if ( null === $translated ) {
				$context->flag_review( $id, 'conditions-unresolved', array( 'conditions' => self::condition_strings( $raw ) ) );
				continue;
			}
			self::save_conditions( $id, $translated );
		}
	}

	/**
	 * Plantillas del Theme Builder que no cubre `digitalisimo/theme-builder.json` (kits del núcleo o de terceros): el
	 * núcleo guardó sus condiciones con los `sub_id` del origen (V-24); se traducen, y si alguna no tiene destino la
	 * plantilla queda sin condiciones, en borrador y para revisar (§9.5). Las cubiertas las traduce la parte.
	 *
	 * @param int[]    $ids     Lo que creó la sesión.
	 * @param string[] $covered IDs de origen que trae `theme-builder.json`.
	 */
	private static function theme_conditions( array $ids, Import_Context $context, array $covered ): void {
		foreach ( $ids as $id ) {
			if ( 'elementor_library' !== get_post_type( $id ) || ! Session::is_theme_template( (string) get_post_meta( $id, '_elementor_template_type', true ) ) ) {
				continue;
			}
			$origin = get_post_meta( $id, Module::META_ORIGIN, true );
			if ( is_array( $origin ) && in_array( (string) ( $origin['id'] ?? '' ), $covered, true ) ) {
				continue;
			}
			$raw = get_post_meta( $id, '_elementor_conditions', true );
			if ( ! is_array( $raw ) || ! $raw ) {
				continue;
			}
			$translated = self::translate_conditions( $raw, $context );
			if ( null === $translated ) {
				self::save_conditions( $id, array() );
				$context->flag_review( $id, 'conditions-unresolved', array( 'conditions' => self::condition_strings( $raw ) ) );
				continue;
			}
			if ( self::condition_strings( $translated ) !== self::condition_strings( $raw ) ) {
				self::save_conditions( $id, $translated );
			}
		}
	}

	/**
	 * Traduce condiciones del Theme Builder o de Custom Code con los mapas de la sesión (§9.5). La regla es una sola,
	 * la de `Kits\Remapper::theme_conditions()` (también la usa la parte `theme-builder`).
	 *
	 * @param array          $conditions Cadenas `type/name/sub_name/sub_id` o arrays con esas claves.
	 * @param Import_Context $context    Contexto con los mapas.
	 *
	 * @return array[]|null Condiciones `{ type, name, sub_name, sub_id }`, o null si alguna queda sin destino.
	 */
	public static function translate_conditions( array $conditions, Import_Context $context ): ?array {
		$result = ( new Remapper( $context ) )->theme_conditions( $conditions );
		return empty( $result['unresolved'] ) && isset( $result['conditions'] ) && is_array( $result['conditions'] ) ? $result['conditions'] : null;
	}

	/** `{ type, name, sub_name, sub_id }` de una condición en cadena o array, o null si no se entiende. */
	private static function condition_parts( $condition ): ?array {
		if ( is_string( $condition ) ) {
			$values = array_pad( explode( '/', $condition ), 4, '' );
		} elseif ( is_array( $condition ) && isset( $condition['type'] ) ) {
			$values = array( $condition['type'], $condition['name'] ?? '', $condition['sub_name'] ?? '', $condition['sub_id'] ?? '' );
		} elseif ( is_array( $condition ) ) {
			$values = array_pad( array_values( $condition ), 4, '' );
		} else {
			return null;
		}
		foreach ( array_slice( $values, 0, 4 ) as $value ) {
			if ( ! is_scalar( $value ) ) {
				return null;
			}
		}
		return array(
			'type'     => (string) $values[0],
			'name'     => (string) $values[1],
			'sub_name' => (string) $values[2],
			'sub_id'   => (string) $values[3],
		);
	}

	/** Condiciones como cadenas `type/name/sub_name/sub_id`, para el informe y para comparar. */
	private static function condition_strings( array $conditions ): array {
		$out = array();
		foreach ( $conditions as $condition ) {
			$parts = self::condition_parts( $condition );
			if ( null !== $parts ) {
				$out[] = rtrim( implode( '/', $parts ), '/' );
			}
		}
		return $out;
	}

	/** Guarda condiciones con el gestor del Theme Builder (regenera su caché) o, si no aplica, en la meta. */
	private static function save_conditions( int $post_id, array $conditions ): void {
		$saved = false;
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			$saved = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions( $post_id, $conditions );
		}
		if ( false === $saved ) {
			$strings = self::condition_strings( $conditions );
			if ( $strings ) {
				update_post_meta( $post_id, '_elementor_conditions', $strings );
			} else {
				delete_post_meta( $post_id, '_elementor_conditions' );
			}
		}
	}

	/**
	 * Recalcula `digitalisimo_elements_unconverted`. Una sola regla, la del marcador (`Preserve::recount_site()`):
	 * cuenta también la papelera (un documento recuperado conserva sus marcadores) y borra la opción con cero.
	 */
	public static function count_markers(): void {
		Preserve::recount_site();
	}

	/** Documentos de la sesión que aún tienen URL del origen en su contenido, sus datos o sus ajustes (§9.3). */
	private static function origin_urls_left( array $ids, array $origin ): int {
		$prefixes = array_unique( array_filter( array( (string) ( $origin['home'] ?? '' ), (string) ( $origin['site'] ?? '' ) ) ) );
		$home     = rtrim( (string) home_url(), '/' );
		$prefixes = array_filter(
			$prefixes,
			function ( $prefix ) use ( $home ) {
				return rtrim( $prefix, '/' ) !== $home;
			}
		);
		if ( ! $prefixes ) {
			return 0;
		}
		$left = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'attachment' === $post->post_type ) {
				continue;
			}
			$text = $post->post_content . (string) get_post_meta( $id, '_elementor_data', true ) . (string) wp_json_encode( get_post_meta( $id, '_elementor_page_settings', true ) );
			foreach ( $prefixes as $prefix ) {
				$escaped = str_replace( '/', '\\/', $prefix );
				if ( false !== strpos( $text, $prefix ) || false !== strpos( $text, $escaped ) ) {
					++$left;
					break;
				}
			}
		}
		return $left;
	}
}
