<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Exporta, importa, revierte e inspecciona kits con DIGITALÍSIMO Elements: `wp digitalisimo-elements kit …` (§12.5).
 *
 * Un solo flujo (§4.1 punto 5): el trabajo lo hace el importador de K (`Session::import_file()`,
 * `Session::export_file()` y `Session::inspect_file()`: guardián, normalizador, contexto, procesos del importador 3.0
 * y `Finish::after_save()`), igual que en la App y en la pantalla «Kits». Este comando valida las opciones, pide un
 * administrador y escribe el informe en tabla o en JSON, sin nombres del constructor original (CR-18).
 *
 * Importar nunca instala ni activa plugins o temas, ni cambia experimentos: `--mode=fresh` sólo vale en un sitio vacío
 * (D1) y un Template Kit siempre entra en «sólo agregar».
 */
final class Cli {
	const INCLUDE_VALUES = array( 'templates', 'content', 'settings', 'plugins' );

	/** Registra el comando (sólo con WP-CLI). Lo llama `Module::init()`. */
	public static function init(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( Module::CLI_COMMAND, __CLASS__ );
		}
	}

	/**
	 * Exporta este sitio como kit.
	 *
	 * Los archivos de medios del sitio se incluyen en el kit sin tope total; nunca los de otros sitios de la red.
	 *
	 * ## OPTIONS
	 *
	 * <archivo>
	 * : Ruta del ZIP que se crea (se sobrescribe si existe).
	 *
	 * [--include=<partes>]
	 * : Qué exportar, separado por comas: templates, content, settings y plugins. Por defecto, templates, content y
	 * settings.
	 *
	 * [--post-types=<tipos>]
	 * : Tipos de contenido propios que se exportan, separados por comas. Por defecto, todos los registrados.
	 *
	 * [--title=<titulo>]
	 * : Título del kit. Por defecto, el nombre del sitio.
	 *
	 * [--media=<modo>]
	 * : embed incluye en el kit los archivos de medios del sitio; link deja sus URL.
	 * ---
	 * default: embed
	 * options:
	 *   - embed
	 *   - link
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-elements kit export kit.zip --title="Mi sitio" --user=admin
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function export( $args, $assoc_args ) {
		self::require_admin();
		$options = self::export_options( $args, $assoc_args );
		if ( is_string( $options ) ) {
			\WP_CLI::error( $options );
		}
		\WP_CLI::log( 'Exportando el kit…' );
		try {
			$result = Session::export_file( $options['target'], $options['args'] );
		} catch ( \Throwable $error ) {
			\WP_CLI::error( self::message( $error ) );
		}
		$report = Report::normalize( isset( $result['report'] ) ? $result['report'] : array() );
		foreach ( $report['entries'] as $entry ) {
			if ( Part_Context::INFO !== $entry['level'] ) {
				\WP_CLI::warning( Report::describe_entry( $entry ) );
			}
		}
		$manifest = isset( $result['manifest'][ Module::MANIFEST_KEY ] ) && is_array( $result['manifest'][ Module::MANIFEST_KEY ] ) ? $result['manifest'][ Module::MANIFEST_KEY ] : array();
		$embedded = isset( $manifest['media']['embedded'] ) && is_numeric( $manifest['media']['embedded'] ) ? (int) $manifest['media']['embedded'] : 0;
		\WP_CLI::success( sprintf( 'Kit exportado en %s, con %d archivos de medios incluidos.', $options['target'], $embedded ) );
	}

	/**
	 * Importa un kit en este sitio.
	 *
	 * Por defecto entra en «sólo agregar»: no cambia nada de lo que ya tiene el sitio y lo que se ve queda en borrador.
	 * Nunca instala ni activa plugins o temas, ni cambia experimentos.
	 *
	 * ## OPTIONS
	 *
	 * <archivo>
	 * : ZIP del kit (1.x, 2.x, 3.0 o Template Kit).
	 *
	 * [--include=<partes>]
	 * : Qué importar, separado por comas: templates, content y settings (plugins sólo se informa).
	 *
	 * [--mode=<modo>]
	 * : add («sólo agregar») o fresh («sitio nuevo», sólo en un sitio vacío: aplica también los ajustes del kit, su
	 * portada y publica).
	 * ---
	 * default: add
	 * options:
	 *   - add
	 *   - fresh
	 * ---
	 *
	 * [--format=<formato>]
	 * : Formato del informe.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-elements kit import kit.zip --user=admin
	 *     wp digitalisimo-elements kit import kit.zip --mode=fresh --user=admin --format=json
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function import( $args, $assoc_args ) {
		self::require_admin();
		$options = self::import_options( $args, $assoc_args );
		if ( is_string( $options ) ) {
			\WP_CLI::error( $options );
		}
		if ( Module::MODE_FRESH === $options['args']['mode'] ) {
			$blockers = Report::fresh_blockers();
			if ( $blockers ) {
				\WP_CLI::error( 'Este sitio no está vacío: sólo se puede importar en «sólo agregar» (--mode=add). ' . implode( ' ', $blockers ) );
			}
		}
		if ( 'json' !== $options['format'] ) {
			\WP_CLI::log( 'Importando el kit…' );
		}
		try {
			$result = Session::import_file( $options['file'], $options['args'] );
		} catch ( \Throwable $error ) {
			\WP_CLI::error( self::message( $error ) );
		}
		$session = isset( $result['session'] ) && is_string( $result['session'] ) ? $result['session'] : '';
		if ( ! Report::is_session_id( $session ) ) {
			\WP_CLI::error( 'El importador no abrió una sesión para el kit.' );
		}
		self::print_report( $session, $options['format'] );
		$public = Report::public_report( $session );
		if ( $public && $public['levels']['error'] > 0 ) {
			\WP_CLI::warning( sprintf( 'La importación dejó %d errores (sesión %s).', $public['levels']['error'], $session ) );
		} elseif ( 'json' !== $options['format'] ) {
			\WP_CLI::success( sprintf( 'Kit importado (sesión %s). El informe completo también está en la pantalla «Kits».', $session ) );
		}
	}

	/**
	 * Revierte la última importación de este sitio.
	 *
	 * Manda a la papelera los documentos que creó, borra los archivos de medios que importó y restaura los ajustes
	 * del kit. Lo que ya existía y lo que reutilizó no se toca.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : No pedir confirmación.
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function revert( $args, $assoc_args ) {
		self::require_admin();
		$session = Report::last_session();
		if ( '' === $session ) {
			\WP_CLI::error( 'No hay ninguna importación terminada que revertir en este sitio.' );
		}
		$row = Report::session( $session );
		\WP_CLI::confirm( sprintf( '¿Revertir la importación «%s» (%s)?', null === $row ? $session : $row['title'], $session ), $assoc_args );
		try {
			$result = Session::import_module()->revert_last_imported_kit();
		} catch ( \Throwable $error ) {
			\WP_CLI::error( self::message( $error ) );
		}
		if ( ! is_array( $result ) || empty( $result['revert_completed'] ) ) {
			\WP_CLI::error( 'La importación no se pudo revertir.' );
		}
		\WP_CLI::success( sprintf( 'Se revirtió la importación %s.', $session ) );
	}

	/**
	 * Muestra el informe de una importación.
	 *
	 * ## OPTIONS
	 *
	 * [<sesion>]
	 * : ID de la sesión. Por defecto, la importación más reciente.
	 *
	 * [--format=<formato>]
	 * : Formato.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function report( $args, $assoc_args ) {
		$format = self::format( $assoc_args );
		if ( '' === $format ) {
			\WP_CLI::error( '--format sólo admite table o json.' );
		}
		$session = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $session ) {
			$rows    = Report::sessions();
			$session = $rows ? $rows[0]['id'] : '';
			if ( '' === $session ) {
				\WP_CLI::error( 'Todavía no se importó ningún kit en este sitio.' );
			}
		}
		if ( ! Report::is_session_id( $session ) || null === Report::session( $session ) ) {
			\WP_CLI::error( 'Esa importación no es de este sitio.' );
		}
		self::print_report( $session, $format );
	}

	/**
	 * Revisa un kit sin importarlo: guardián, normalizador y análisis. No escribe nada fuera de los temporales.
	 *
	 * ## OPTIONS
	 *
	 * <archivo>
	 * : ZIP del kit.
	 *
	 * [--format=<formato>]
	 * : Formato.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function inspect( $args, $assoc_args ) {
		$format = self::format( $assoc_args );
		$file   = self::kit_file( $args );
		if ( '' === $format || is_array( $file ) ) {
			\WP_CLI::error( '' === $format ? '--format sólo admite table o json.' : $file[0] );
		}
		try {
			$inspection = Session::inspect_file( $file );
		} catch ( \Throwable $error ) {
			\WP_CLI::error( 'El kit no se puede importar: ' . self::message( $error ) );
		}
		$public = self::inspection_report( $inspection );
		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $public, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $public['rows'], array( 'seccion', 'nivel', 'aviso' ) );
		\WP_CLI::success( 'El kit pasó el guardián y se puede importar.' );
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Ayudas (públicas y estáticas para las pruebas unitarias; devuelven el mensaje de error en vez de cortar).

	/**
	 * Opciones de `export`: `{ target, args }` para `Session::export_file()`, o el mensaje de error.
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 *
	 * @return array|string
	 */
	public static function export_options( array $args, array $assoc_args ) {
		$target = isset( $args[0] ) ? (string) $args[0] : '';
		if ( 1 !== preg_match( '/\.zip$/i', $target ) ) {
			return 'Indica la ruta del ZIP que se crea, terminada en .zip.';
		}
		$folder = dirname( $target );
		if ( ! is_dir( $folder ) || ! is_writable( $folder ) ) {
			return 'No se puede escribir en la carpeta del ZIP.';
		}
		$media = isset( $assoc_args['media'] ) ? (string) $assoc_args['media'] : Export_Context::MEDIA_EMBED;
		if ( ! in_array( $media, array( Export_Context::MEDIA_EMBED, Export_Context::MEDIA_LINK ), true ) ) {
			return '--media sólo admite embed o link.';
		}
		$include = self::list_option( $assoc_args, 'include' );
		if ( array_diff( $include, self::INCLUDE_VALUES ) ) {
			return '--include sólo admite: ' . implode( ', ', self::INCLUDE_VALUES ) . '.';
		}
		$out = array(
			'include' => $include,
			'title'   => isset( $assoc_args['title'] ) ? trim( wp_strip_all_tags( (string) $assoc_args['title'] ) ) : '',
			'media'   => $media,
		);
		$types = array_values( array_filter( array_map( 'sanitize_key', self::list_option( $assoc_args, 'post-types' ) ), 'strlen' ) );
		if ( $types ) {
			$out['post_types'] = $types;
		}
		return array(
			'target' => $target,
			'args'   => $out,
		);
	}

	/**
	 * Opciones de `import`: `{ file, format, args: { mode, include } }` para `Session::import_file()`, o el mensaje.
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 *
	 * @return array|string
	 */
	public static function import_options( array $args, array $assoc_args ) {
		$file = self::kit_file( $args );
		if ( is_array( $file ) ) {
			return $file[0];
		}
		$format = self::format( $assoc_args );
		if ( '' === $format ) {
			return '--format sólo admite table o json.';
		}
		$mode = isset( $assoc_args['mode'] ) ? (string) $assoc_args['mode'] : Module::MODE_ADD;
		if ( ! in_array( $mode, array( Module::MODE_ADD, Module::MODE_FRESH ), true ) ) {
			return '--mode sólo admite add o fresh.';
		}
		$include = self::list_option( $assoc_args, 'include' );
		if ( array_diff( $include, self::INCLUDE_VALUES ) ) {
			return '--include sólo admite: ' . implode( ', ', self::INCLUDE_VALUES ) . '.';
		}
		return array(
			'file'   => $file,
			'format' => $format,
			'args'   => array(
				'mode'    => $mode,
				'include' => $include,
			),
		);
	}

	/**
	 * Informe de `inspect` a partir de `Session::inspect_file()`: formato, origen, pedidos, análisis y si se podría
	 * importar como «nuevo», sin nombres del constructor original (CR-18) ni direcciones del proveedor.
	 *
	 * @param array $inspection `record()` del kit normalizado más `media`, `entries`, `bytes` y `mode`.
	 */
	public static function inspection_report( array $inspection ): array {
		$format = isset( $inspection['format'] ) && is_string( $inspection['format'] ) ? $inspection['format'] : '';
		$origin = isset( $inspection['origin'] ) && is_array( $inspection['origin'] ) ? $inspection['origin'] : array();
		$detail = array(
			'format'   => $format,
			'origin'   => $origin,
			'requests' => isset( $inspection['requests'] ) && is_array( $inspection['requests'] ) ? $inspection['requests'] : array(),
			'analysis' => isset( $inspection['analysis'] ) && is_array( $inspection['analysis'] ) ? $inspection['analysis'] : array(),
		);
		$decision  = isset( $inspection['mode'] ) && is_array( $inspection['mode'] ) ? $inspection['mode'] : array();
		$fresh     = isset( $decision['mode'] ) && Module::MODE_FRESH === $decision['mode'];
		$why       = isset( $decision['error'] ) && is_string( $decision['error'] ) ? $decision['error'] : '';
		$requested = Report::requested( $detail );
		$lines     = Report::analysis_lines( $detail );
		$row       = function ( string $section, string $text ): array {
			return array(
				'seccion' => isset( Report::SECTIONS[ $section ] ) ? Report::SECTIONS[ $section ] : Report::SECTIONS['other'],
				'nivel'   => Report::level_label( Part_Context::INFO ),
				'aviso'   => $text,
			);
		};
		$rows   = array( $row( 'summary', Report::format_label( $format ) . ' · ' . ( $fresh ? 'se puede importar como sitio nuevo o en «sólo agregar»' : 'se importa en «sólo agregar»' ) . ( '' !== $why ? ' · ' . Report::brand( $why ) : '' ) ) );
		foreach ( Report::requested_lines( $requested ) as $line ) {
			$rows[] = $row( 'requested', $line );
		}
		foreach ( $lines as $section => $items ) {
			foreach ( $items as $line ) {
				$rows[] = $row( $section, $line );
			}
		}
		return array(
			'format'       => $format,
			'format_label' => Report::format_label( $format ),
			'kit'          => isset( $origin['kit'] ) && is_string( $origin['kit'] ) ? $origin['kit'] : '',
			'origin'       => array(
				'home' => Report::scrub( isset( $origin['home'] ) && is_string( $origin['home'] ) ? $origin['home'] : '' ),
				'site' => Report::scrub( isset( $origin['site'] ) && is_string( $origin['site'] ) ? $origin['site'] : '' ),
			),
			'fresh'        => $fresh,
			'fresh_reason' => Report::brand( $why ),
			'requested'    => $requested,
			'analysis'     => $lines,
			'media'        => isset( $inspection['media'] ) && is_numeric( $inspection['media'] ) ? (int) $inspection['media'] : 0,
			'entries'      => isset( $inspection['entries'] ) && is_numeric( $inspection['entries'] ) ? (int) $inspection['entries'] : 0,
			'bytes'        => isset( $inspection['bytes'] ) && is_numeric( $inspection['bytes'] ) ? (int) $inspection['bytes'] : 0,
			'rows'         => $rows,
		);
	}

	/** Imprime el informe de una sesión en tabla o en JSON (sin nombres del constructor original, CR-18). */
	public static function print_report( string $session, string $format ): void {
		$public = Report::public_report( $session );
		if ( null === $public ) {
			\WP_CLI::error( 'Esa importación no es de este sitio.' );
		}
		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $public, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}
		\WP_CLI::log( sprintf( '%s · %s · %s', $public['title'], $public['mode_label'], $public['session'] ) );
		\WP_CLI\Utils\format_items( 'table', Report::table_rows( $public ), array( 'seccion', 'nivel', 'aviso' ) );
	}

	/** Lista separada por comas de una opción, en minúsculas y sin vacíos ni repetidos. */
	public static function list_option( array $assoc_args, string $key ): array {
		if ( ! isset( $assoc_args[ $key ] ) || ! is_scalar( $assoc_args[ $key ] ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( (string) $assoc_args[ $key ] ) ) ), 'strlen' ) ) );
	}

	/** `table` o `json`; '' si no vale. */
	private static function format( array $assoc_args ): string {
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		return in_array( $format, array( 'table', 'json' ), true ) ? $format : '';
	}

	/**
	 * El ZIP del primer argumento, que debe existir y poder leerse.
	 *
	 * @return string|string[] La ruta, o `[ mensaje ]`.
	 */
	private static function kit_file( array $args ) {
		$file = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $file || ! is_file( $file ) || ! is_readable( $file ) ) {
			return array( 'Indica un ZIP de kit que exista y se pueda leer.' );
		}
		if ( 1 !== preg_match( '/\.zip$/i', $file ) ) {
			return array( 'El kit debe ser un archivo ZIP.' );
		}
		return $file;
	}

	private static function require_admin(): void {
		if ( ! current_user_can( Module::CAPABILITY ) ) {
			\WP_CLI::error( 'Ejecuta el comando con un administrador del sitio: añade --user=<usuario>.' );
		}
	}

	/** Mensaje de un error, sin rutas del servidor. */
	private static function message( \Throwable $error ): string {
		$text = trim( wp_strip_all_tags( $error->getMessage() ) );
		return '' === $text ? 'Error desconocido.' : str_replace( ABSPATH, '…/', $text );
	}
}
